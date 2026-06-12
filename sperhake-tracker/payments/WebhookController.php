<?php
/**
 * Stripe webhook receiver. This is the ONLY trusted source of payment truth.
 *
 * Registers a REST route at /wp-json/sperhake/v1/stripe-webhook and verifies
 * the Stripe signature before acting on any event.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Payments;

use SperhakeTracker\Api\ExternalApiClient;
use SperhakeTracker\Api\VehicleApiClient;
use SperhakeTracker\Database\TransactionRepository;
use SperhakeTracker\Emails\Mailer;
use SperhakeTracker\Logging\Logger;
use SperhakeTracker\Pdf\ReceiptGenerator;
use SperhakeTracker\Support\Options;
use Stripe\Webhook;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebhookController {

	public const NAMESPACE = 'sperhake/v1';
	public const ROUTE     = '/stripe-webhook';

	public function __construct(
		private readonly Options $options,
		private readonly TransactionRepository $transactions,
		private readonly Mailer $mailer,
		private readonly ReceiptGenerator $receipts,
		private readonly ExternalApiClient $externalApi,
		private readonly Logger $logger,
		private readonly ?VehicleApiClient $vehicleApi = null
	) {}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	public function register_route(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle' ],
				// Public endpoint — authentication is the Stripe signature itself.
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * The public webhook URL (shown in admin so the admin can paste it into Stripe).
	 */
	public static function webhook_url(): string {
		return rest_url( self::NAMESPACE . self::ROUTE );
	}

	/**
	 * Handle an incoming Stripe event.
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$payload    = $request->get_body();
		$sig_header = $request->get_header( 'stripe_signature' );
		$secret     = $this->options->stripe_webhook_secret();

		if ( '' === $secret ) {
			// Configuration denial, not a server fault — respond 403.
			$this->logger->error( 'webhook', 'Webhook secret is not configured; rejecting event.' );

			return new WP_REST_Response( [ 'error' => 'not_configured' ], 403 );
		}

		if ( ! class_exists( Webhook::class ) ) {
			$this->logger->error( 'webhook', 'Stripe SDK missing; cannot verify webhook.' );

			return new WP_REST_Response( [ 'error' => 'sdk_missing' ], 500 );
		}

		try {
			$event = Webhook::constructEvent( $payload, (string) $sig_header, $secret );
		} catch ( \UnexpectedValueException $e ) {
			$this->logger->warning( 'webhook', 'Invalid webhook payload.', [ 'error' => $e->getMessage() ] );

			return new WP_REST_Response( [ 'error' => 'invalid_payload' ], 400 );
		} catch ( \Stripe\Exception\SignatureVerificationException $e ) {
			$this->logger->warning( 'webhook', 'Invalid webhook signature.', [ 'error' => $e->getMessage() ] );

			return new WP_REST_Response( [ 'error' => 'invalid_signature' ], 400 );
		} catch ( \Throwable $e ) {
			$this->logger->error( 'webhook', 'Webhook verification failed.', [ 'error' => $e->getMessage() ] );

			return new WP_REST_Response( [ 'error' => 'verification_failed' ], 400 );
		}

		switch ( $event->type ) {
			case 'checkout.session.completed':
				$this->handle_session_completed( $event->data->object );
				break;

			case 'checkout.session.async_payment_succeeded':
				$this->handle_session_completed( $event->data->object );
				break;

			case 'checkout.session.async_payment_failed':
			case 'checkout.session.expired':
				$this->handle_session_failed( $event->data->object );
				break;

			default:
				// Acknowledge unhandled events so Stripe stops retrying.
				$this->logger->debug( 'webhook', 'Unhandled event type.', [ 'type' => $event->type ] );
		}

		return new WP_REST_Response( [ 'received' => true ], 200 );
	}

	/**
	 * Process a successful Checkout Session.
	 *
	 * @param object $session Stripe Checkout Session object.
	 */
	private function handle_session_completed( object $session ): void {
		$transaction = $this->transactions->find_by_session( (string) ( $session->id ?? '' ) );

		// Fallback: match by client_reference_id / metadata.
		if ( ! $transaction ) {
			$ref = (string) ( $session->client_reference_id ?? ( $session->metadata->transaction_ref ?? '' ) );
			if ( '' !== $ref ) {
				$transaction = $this->transactions->find_by_ref( $ref );
			}
		}

		if ( ! $transaction ) {
			$this->logger->warning( 'webhook', 'No local transaction matched session.', [ 'session' => $session->id ?? '' ] );

			return;
		}

		// Idempotency: do not re-process an already-paid transaction.
		if ( 'paid' === $transaction->payment_status ) {
			$this->logger->debug( 'webhook', 'Session already processed.', [ 'ref' => $transaction->transaction_ref ] );

			return;
		}

		// Only mark paid when Stripe confirms the money was captured.
		$payment_status = (string) ( $session->payment_status ?? '' );
		if ( 'paid' !== $payment_status && 'no_payment_required' !== $payment_status ) {
			$this->logger->info(
				'webhook',
				'Session completed but not yet paid.',
				[ 'ref' => $transaction->transaction_ref, 'status' => $payment_status ]
			);

			return;
		}

		$customer = $this->extract_customer( $session, $transaction );
		$email    = $customer['email'];

		// --- Critical: re-validate the captured amount against the live penalty. ---
		$paid_cents = isset( $session->amount_total ) ? (int) $session->amount_total : (int) $transaction->amount_cents;
		$validation = $this->validate_amount( $transaction, $paid_cents );

		$meta = json_decode( (string) $transaction->meta, true );
		$meta = is_array( $meta ) ? $meta : [];
		$meta['amount_check'] = $validation;
		$meta['customer']     = $customer; // Billing details for the /{case_id}/paid forward.

		$this->transactions->update(
			(int) $transaction->id,
			[
				'payment_status'        => 'paid',
				'amount_cents'          => $paid_cents,
				'stripe_payment_intent' => (string) ( $session->payment_intent ?? '' ),
				'stripe_charge_id'      => (string) ( $session->payment_intent ?? '' ),
				'customer_name'         => $customer['legal_name'] ?: (string) $transaction->customer_name,
				'customer_email'        => $email,
				'paid_at'               => current_time( 'mysql', true ),
				'meta'                  => $meta,
			]
		);

		$transaction = $this->transactions->find( (int) $transaction->id );

		if ( 'underpaid' === $validation['status'] ) {
			// Money was captured, but it no longer covers the current penalty.
			// Flag for manual review and DO NOT auto-forward / auto-release.
			$this->logger->error(
				'webhook',
				'Paid amount is below the current penalty — held for review.',
				[ 'ref' => $transaction->transaction_ref, 'paid' => $paid_cents, 'expected' => $validation['expected_cents'] ]
			);
			$this->transactions->update( (int) $transaction->id, [ 'api_sync_status' => 'review' ] );
			do_action( 'sperhake_payment_amount_mismatch', $transaction, $validation );
		} else {
			$this->logger->info( 'webhook', 'Payment confirmed.', [ 'ref' => $transaction->transaction_ref ] );
		}

		// 1. Generate the PDF receipt.
		$receipt_path = $this->receipts->generate( $transaction );
		if ( '' !== $receipt_path ) {
			$this->transactions->update( (int) $transaction->id, [ 'receipt_path' => $receipt_path ] );
			$transaction = $this->transactions->find( (int) $transaction->id );
		}

		// 2. Email the customer (with receipt attachment).
		if ( $email ) {
			$this->mailer->send_receipt( $transaction, $receipt_path );
		}

		// 3. Notify the external API (with automatic retry on failure), unless the
		//    payment is held for review due to an amount mismatch.
		if ( 'underpaid' !== $validation['status'] ) {
			$this->forward_to_external_api( $transaction );
		}

		do_action( 'sperhake_payment_completed', $transaction );
	}

	/**
	 * Re-fetch the live penalty and compare it with the captured amount.
	 *
	 * Statuses: 'verified' (matches), 'overpaid' (paid >= expected, allowed),
	 * 'underpaid' (paid < expected, held for review), 'skipped' (API unavailable).
	 *
	 * @return array{status: string, paid_cents: int, expected_cents: ?int}
	 */
	private function validate_amount( object $transaction, int $paid_cents ): array {
		$result = [
			'status'         => 'skipped',
			'paid_cents'     => $paid_cents,
			'expected_cents' => null,
		];

		if ( ! $this->vehicleApi ) {
			return $result;
		}

		$meta      = json_decode( (string) $transaction->meta, true );
		$reference = is_array( $meta ) && isset( $meta['reference'] ) ? (string) $meta['reference'] : '';

		$lookup = $this->vehicleApi->search_vehicle( (string) $transaction->license_plate, $reference );
		if ( empty( $lookup['ok'] ) ) {
			// API down/changed — don't punish the customer; leave as skipped.
			$this->logger->warning( 'webhook', 'Amount re-validation skipped (vehicle API unavailable).', [ 'ref' => $transaction->transaction_ref ] );

			return $result;
		}

		$expected                 = (int) $lookup['data']['penalty_cents'];
		$result['expected_cents'] = $expected;

		if ( $paid_cents >= $expected ) {
			$result['status'] = $paid_cents === $expected ? 'verified' : 'overpaid';
		} else {
			$result['status'] = 'underpaid';
		}

		return $result;
	}

	/**
	 * Mark a transaction failed/expired.
	 */
	private function handle_session_failed( object $session ): void {
		$transaction = $this->transactions->find_by_session( (string) ( $session->id ?? '' ) );
		if ( ! $transaction || 'paid' === $transaction->payment_status ) {
			return;
		}

		$this->transactions->update( (int) $transaction->id, [ 'payment_status' => 'failed' ] );
		$this->logger->info( 'webhook', 'Payment failed/expired.', [ 'ref' => $transaction->transaction_ref ] );
	}

	/**
	 * Attempt to forward to the external API immediately; queue a retry on failure.
	 */
	private function forward_to_external_api( object $transaction ): void {
		$result   = $this->externalApi->notify_payment_completed( $transaction );
		$attempts = (int) $transaction->api_attempts + 1;

		$this->transactions->update(
			(int) $transaction->id,
			[
				'api_sync_status' => $result['ok'] ? 'synced' : 'failed',
				'api_attempts'    => $attempts,
			]
		);
		// The WP-Cron RetryQueue will pick up any 'failed' rows automatically.
	}

	/**
	 * Pull the payer's billing details from the verified Stripe session for the
	 * relocation API's /{case_id}/paid call. Falls back to stored values.
	 *
	 * @return array{legal_name: string, email: string, address_street: string, address_zip: string, address_city: string, address_country: string}
	 */
	private function extract_customer( object $session, object $transaction ): array {
		$details = $session->customer_details ?? null;
		$address = $details->address ?? null;

		$street = trim(
			(string) ( $address->line1 ?? '' ) . ' ' . (string) ( $address->line2 ?? '' )
		);

		return [
			'legal_name'      => (string) ( $details->name ?? $transaction->customer_name ?? '' ),
			'email'           => $this->extract_email( $session, $transaction ),
			'address_street'  => $street,
			'address_zip'     => (string) ( $address->postal_code ?? '' ),
			'address_city'    => (string) ( $address->city ?? '' ),
			'address_country' => (string) ( $address->country ?? '' ),
		];
	}

	/**
	 * Pull the customer email from the session or fall back to the stored value.
	 */
	private function extract_email( object $session, object $transaction ): string {
		$email = '';

		if ( isset( $session->customer_details->email ) ) {
			$email = (string) $session->customer_details->email;
		} elseif ( isset( $session->customer_email ) ) {
			$email = (string) $session->customer_email;
		} elseif ( $transaction->customer_email ) {
			$email = (string) $transaction->customer_email;
		}

		return is_email( $email ) ? $email : '';
	}
}
