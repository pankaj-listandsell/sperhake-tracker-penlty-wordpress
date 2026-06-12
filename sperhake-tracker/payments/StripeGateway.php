<?php
/**
 * Stripe Checkout integration: creates Checkout Sessions for penalty payments.
 *
 * Security model: the penalty amount is NEVER taken from the browser. When the
 * customer clicks "Pay Now" we re-query the Vehicle API server-side for the
 * authoritative penalty for that plate, then build the Checkout Session from
 * that trusted figure.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Payments;

use SperhakeTracker\Api\VehicleApiClient;
use SperhakeTracker\Database\TransactionRepository;
use SperhakeTracker\Logging\Logger;
use SperhakeTracker\Support\Options;
use SperhakeTracker\Support\Plate;
use Stripe\StripeClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StripeGateway {

	public function __construct(
		private readonly Options $options,
		private readonly TransactionRepository $transactions,
		private readonly Logger $logger,
		private readonly ?VehicleApiClient $vehicleApi = null
	) {}

	public function register(): void {
		add_action( 'wp_ajax_sperhake_pay', [ $this, 'create_checkout_session' ] );
		add_action( 'wp_ajax_nopriv_sperhake_pay', [ $this, 'create_checkout_session' ] );
	}

	/**
	 * AJAX: create a Stripe Checkout Session and return its redirect URL.
	 */
	public function create_checkout_session(): void {
		check_ajax_referer( 'sperhake_pay', 'nonce' );

		if ( ! class_exists( StripeClient::class ) ) {
			$this->logger->error( 'stripe', 'Stripe SDK is not installed (run composer install).' );
			wp_send_json_error( [ 'message' => __( 'Payments are temporarily unavailable.', 'sperhake-tracker' ) ], 500 );
		}

		$secret = $this->options->stripe_secret_key();
		if ( '' === $secret ) {
			$this->logger->error( 'stripe', 'Stripe secret key is not configured.' );
			wp_send_json_error( [ 'message' => __( 'Payments are temporarily unavailable.', 'sperhake-tracker' ) ], 500 );
		}

		$plate = Plate::normalise(
			isset( $_POST['license_plate'] ) ? sanitize_text_field( wp_unslash( $_POST['license_plate'] ) ) : ''
		);
		if ( ! Plate::is_valid( $plate ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid license plate.', 'sperhake-tracker' ) ], 422 );
		}

		$reference = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
		if ( $this->options->require_reference() && '' === $reference ) {
			wp_send_json_error( [ 'message' => __( 'A reference number is required.', 'sperhake-tracker' ) ], 422 );
		}

		// Re-fetch authoritative penalty server-side (the reference re-gates access).
		$vehicle = $this->resolve_vehicle( $plate, $reference );
		if ( null === $vehicle || $vehicle['penalty_cents'] <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'There is no outstanding penalty to pay for this vehicle.', 'sperhake-tracker' ) ], 409 );
		}

		// The relocation API marks settled cases as paid — never charge twice.
		if ( ! empty( $vehicle['is_paid'] ) ) {
			wp_send_json_error( [ 'message' => __( 'This penalty has already been paid.', 'sperhake-tracker' ) ], 409 );
		}

		$return_url = $this->safe_return_url(
			isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : ''
		);

		// Duplicate-session guard: reuse an open Checkout Session for the same
		// plate/amount instead of spawning a new one per tab/click.
		$reused = $this->maybe_reuse_session( $plate, (int) $vehicle['penalty_cents'] );
		if ( null !== $reused ) {
			wp_send_json_success( $reused );
		}

		// Persist a pending transaction first so the webhook can reconcile it.
		$transaction_id = $this->transactions->create(
			[
				'license_plate'  => $plate,
				'vehicle_id'     => $vehicle['vehicle_id'],
				'customer_name'  => $vehicle['owner_name'],
				'amount_cents'   => $vehicle['penalty_cents'],
				'currency'       => $vehicle['currency'] ?: $this->options->currency(),
				'payment_status' => 'pending',
				'meta'           => [ 'vehicle' => $vehicle, 'reference' => $reference ],
			]
		);

		if ( ! $transaction_id ) {
			wp_send_json_error( [ 'message' => __( 'Could not start the payment. Please try again.', 'sperhake-tracker' ) ], 500 );
		}

		$transaction = $this->transactions->find( $transaction_id );

		try {
			$stripe  = new StripeClient( $secret );
			$success = add_query_arg(
				[
					'sperhake_payment' => 'success',
					'ref'              => rawurlencode( $transaction->transaction_ref ),
					'token'            => rawurlencode( $transaction->receipt_token ),
				],
				$return_url
			);
			$cancel  = add_query_arg( [ 'sperhake_payment' => 'cancelled' ], $return_url );

			$session = $stripe->checkout->sessions->create(
				[
					'mode'                 => 'payment',
					'payment_method_types' => $this->payment_method_types(),
					// Collect the payer's billing address so the relocation API's
					// /{case_id}/paid call can be populated with customer details.
					'billing_address_collection' => 'required',
					'line_items'           => [
						[
							'price_data' => [
								'currency'     => $transaction->currency,
								'unit_amount'  => (int) $transaction->amount_cents,
								'product_data' => [
									'name'        => sprintf(
										/* translators: %s: license plate */
										__( 'Towing penalty – %s', 'sperhake-tracker' ),
										$plate
									),
									'description' => __( 'Outstanding penalty payment to Abschleppdienst Sperhake.', 'sperhake-tracker' ),
								],
							],
							'quantity'   => 1,
						],
					],
					'client_reference_id'  => $transaction->transaction_ref,
					'success_url'          => $success . '#sperhake-receipt',
					'cancel_url'           => $cancel,
					'metadata'             => [
						'transaction_ref' => $transaction->transaction_ref,
						'license_plate'   => $plate,
						'vehicle_id'      => (string) $vehicle['vehicle_id'],
					],
				]
			);

			// Record the session id so the webhook can match it.
			$this->transactions->update(
				$transaction_id,
				[ 'stripe_session_id' => $session->id ]
			);

			$this->logger->info(
				'stripe',
				'Checkout session created.',
				[ 'ref' => $transaction->transaction_ref, 'session' => $session->id ]
			);

			wp_send_json_success(
				[
					'redirect_url' => $session->url,
					'session_id'   => $session->id,
				]
			);
		} catch ( \Throwable $e ) {
			$this->logger->error(
				'stripe',
				'Failed to create checkout session.',
				[ 'ref' => $transaction->transaction_ref, 'error' => $e->getMessage() ]
			);
			$this->transactions->update( $transaction_id, [ 'payment_status' => 'failed' ] );

			wp_send_json_error( [ 'message' => __( 'Could not reach the payment provider. Please try again.', 'sperhake-tracker' ) ], 502 );
		}
	}

	/**
	 * Resolve trusted vehicle data, preferring a live API call.
	 *
	 * @return array<string, mixed>|null
	 */
	private function resolve_vehicle( string $plate, string $reference = '' ): ?array {
		if ( $this->vehicleApi ) {
			$result = $this->vehicleApi->search_vehicle( $plate, $reference );
			if ( ! empty( $result['ok'] ) ) {
				return $result['data'];
			}
		}

		return null;
	}

	/**
	 * If a recent pending Checkout Session for this plate/amount is still open,
	 * return its redirect payload so we don't create duplicate sessions.
	 *
	 * @return array{redirect_url: string, session_id: string, reused: bool}|null
	 */
	private function maybe_reuse_session( string $plate, int $amount_cents ): ?array {
		$pending = $this->transactions->find_reusable_pending( $plate, 30 );
		if ( ! $pending || (int) $pending->amount_cents !== $amount_cents ) {
			return null;
		}

		try {
			$stripe  = new StripeClient( $this->options->stripe_secret_key() );
			$session = $stripe->checkout->sessions->retrieve( (string) $pending->stripe_session_id );

			if ( 'open' === ( $session->status ?? '' ) && ! empty( $session->url ) ) {
				$this->logger->info(
					'stripe',
					'Reusing open checkout session.',
					[ 'ref' => $pending->transaction_ref, 'session' => $session->id ]
				);

				return [
					'redirect_url' => $session->url,
					'session_id'   => $session->id,
					'reused'       => true,
				];
			}
		} catch ( \Throwable $e ) {
			// Fall through and create a fresh session.
			$this->logger->debug( 'stripe', 'Could not reuse session; creating new.', [ 'error' => $e->getMessage() ] );
		}

		return null;
	}

	/**
	 * Enabled payment method types. Card covers Apple Pay & Google Pay
	 * automatically in Checkout. SEPA is opt-in via settings.
	 *
	 * @return array<int, string>
	 */
	private function payment_method_types(): array {
		$types = [ 'card' ];

		if ( $this->options->get( Options::STRIPE, 'enable_sepa', 0 ) ) {
			$types[] = 'sepa_debit';
		}

		return $types;
	}

	/**
	 * Constrain the post-payment return URL to this site to prevent open redirects.
	 */
	private function safe_return_url( string $candidate ): string {
		$fallback = home_url( '/' );

		if ( '' === $candidate ) {
			return $fallback;
		}

		$host      = wp_parse_url( $candidate, PHP_URL_HOST );
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( $host && $site_host && strcasecmp( $host, $site_host ) === 0 ) {
			// Strip any previous payment params to avoid stacking.
			return remove_query_arg( [ 'sperhake_payment', 'ref', 'token' ], $candidate );
		}

		return $fallback;
	}
}
