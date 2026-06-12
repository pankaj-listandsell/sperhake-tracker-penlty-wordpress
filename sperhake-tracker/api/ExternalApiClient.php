<?php
/**
 * Client that pushes completed-payment notifications to the external API.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Api;

use SperhakeTracker\Logging\Logger;
use SperhakeTracker\Support\Options;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExternalApiClient {

	public function __construct(
		private readonly Options $options,
		private readonly Logger $logger
	) {}

	/**
	 * Notify the relocation API that a payment completed.
	 *
	 * POSTs to {api_url}/{case_id}/paid with the documented body:
	 *   { stripe_payment_intent_id, amount, currency,
	 *     customer: { legal_name, email, address_street, address_zip,
	 *                 address_city, address_country } }
	 *
	 * @param object $transaction Transaction row.
	 * @return array{ok: bool, code: int, body: string}
	 */
	public function notify_payment_completed( object $transaction ): array {
		$base    = $this->options->api_url();
		$case_id = (string) ( $transaction->vehicle_id ?? '' );

		if ( '' === $base ) {
			// No API base configured — nothing to do.
			return [ 'ok' => true, 'code' => 0, 'body' => 'no_endpoint' ];
		}

		if ( '' === $case_id ) {
			$this->logger->error(
				'api-forward',
				'Cannot notify payment: transaction has no case id.',
				[ 'ref' => $transaction->transaction_ref ]
			);

			return [ 'ok' => false, 'code' => 0, 'body' => 'no_case_id' ];
		}

		// Billing details captured from the Stripe session at webhook time.
		$meta     = json_decode( (string) $transaction->meta, true );
		$customer = is_array( $meta ) && isset( $meta['customer'] ) && is_array( $meta['customer'] ) ? $meta['customer'] : [];

		$payload = [
			'stripe_payment_intent_id' => (string) ( $transaction->stripe_payment_intent ?? '' ),
			'amount'                   => round( (int) $transaction->amount_cents / 100, 2 ),
			'currency'                 => strtoupper( (string) $transaction->currency ),
			'customer'                 => [
				'legal_name'      => (string) ( $customer['legal_name'] ?? $transaction->customer_name ?? '' ),
				'email'           => (string) ( $customer['email'] ?? $transaction->customer_email ?? '' ),
				'address_street'  => (string) ( $customer['address_street'] ?? '' ),
				'address_zip'     => (string) ( $customer['address_zip'] ?? '' ),
				'address_city'    => (string) ( $customer['address_city'] ?? '' ),
				'address_country' => (string) ( $customer['address_country'] ?? '' ),
			],
		];

		$body    = (string) wp_json_encode( $payload );
		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'User-Agent'   => 'SperhakeTracker/' . SPERHAKE_TRACKER_VERSION,
		];

		$key    = $this->options->api_key();
		$secret = $this->options->api_secret();
		if ( '' !== $key ) {
			$headers['X-API-Key'] = $key;
		}
		if ( '' !== $secret ) {
			$headers['X-Signature'] = hash_hmac( 'sha256', $body, $secret );
		}

		$url = rtrim( $base, '/' ) . '/' . rawurlencode( $case_id ) . '/paid';

		$response = wp_remote_post(
			$url,
			[
				'headers'   => $headers,
				'body'      => $body,
				'timeout'   => $this->options->api_timeout(),
				'sslverify' => true,
			]
		);

		if ( $response instanceof WP_Error ) {
			$this->logger->error(
				'api-forward',
				'Payment notification request errored.',
				[ 'ref' => $transaction->transaction_ref, 'error' => $response->get_error_message() ]
			);

			return [ 'ok' => false, 'code' => 0, 'body' => $response->get_error_message() ];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$resp = (string) wp_remote_retrieve_body( $response );
		$ok   = $code >= 200 && $code < 300;

		$this->logger->log(
			$ok ? Logger::INFO : Logger::WARNING,
			'api-forward',
			$ok ? 'Payment notification delivered.' : 'Payment notification rejected.',
			[ 'ref' => $transaction->transaction_ref, 'status' => $code ]
		);

		return [ 'ok' => $ok, 'code' => $code, 'body' => $resp ];
	}
}
