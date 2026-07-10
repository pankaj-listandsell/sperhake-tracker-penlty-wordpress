<?php
/**
 * Client for the external Vehicle Tracking API.
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

final class VehicleApiClient {

	public function __construct(
		private readonly Options $options,
		private readonly Logger $logger
	) {}

	/**
	 * Search a vehicle by license plate (and optional reference number).
	 *
	 * @param string $license_plate Normalised plate.
	 * @param string $reference     Optional case/reference number (anti-enumeration).
	 * @return array{ok: bool, data?: array<string, mixed>, message?: string, code?: int}
	 */
	public function search_vehicle( string $license_plate, string $reference = '' ): array {
		$base = $this->options->api_url();

		if ( '' === $base ) {
			$this->logger->error( 'api', 'Vehicle API URL is not configured.' );

			return [
				'ok'      => false,
				'message' => __( 'Vehicle lookup is temporarily unavailable. Please contact us.', 'sperhake-tracker' ),
			];
		}

		$query = [ 'plate' => $license_plate ];
		if ( '' !== $reference ) {
			$query['reference'] = $reference;
		}

		// The relocation API is a GET endpoint: it authenticates via the
		// X-API-Key header and reads the plate from the query string
		// (e.g. /api/relocation/search?plate=B-AB+1234).
		$endpoint = $this->build_url( $base, 'search' ) . '?' . http_build_query( $query );
		$response = $this->get( $endpoint );

		if ( $response instanceof WP_Error ) {
			$this->logger->error(
				'api',
				'Vehicle search request failed.',
				[ 'plate' => $license_plate, 'error' => $response->get_error_message() ]
			);

			return [
				'ok'      => false,
				'message' => __( 'We could not reach the vehicle database. Please try again shortly.', 'sperhake-tracker' ),
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 404 === $code ) {
			return [
				'ok'      => false,
				'code'    => 404,
				'message' => ( is_array( $body ) && ! empty( $body['message'] ) )
					? (string) $body['message']
					: __( 'No vehicle was found for that license plate.', 'sperhake-tracker' ),
			];
		}

		// A released or cancelled case comes back as HTTP 200 with found=false and
		// a human-readable message (plus a released_at / cancelled_at timestamp).
		// Surface that instead of rendering an empty "found" card.
		if ( is_array( $body ) && array_key_exists( 'found', $body ) && false === $body['found'] ) {
			$message = ! empty( $body['message'] )
				? (string) $body['message']
				: __( 'This vehicle is not available for online payment.', 'sperhake-tracker' );

			$when = (string) ( $body['released_at'] ?? $body['cancelled_at'] ?? '' );
			if ( '' !== $when ) {
				$timestamp = strtotime( $when );
				if ( false !== $timestamp ) {
					$message .= ' (' . date_i18n( get_option( 'date_format' ), $timestamp ) . ')';
				}
			}

			return [
				'ok'      => false,
				'code'    => 404,
				'state'   => (string) ( $body['state'] ?? $body['status'] ?? 'unavailable' ),
				'message' => $message,
			];
		}

		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			$this->logger->warning(
				'api',
				'Vehicle search returned an unexpected response.',
				[ 'plate' => $license_plate, 'status' => $code ]
			);

			return [
				'ok'      => false,
				'message' => __( 'Unexpected response from the vehicle database.', 'sperhake-tracker' ),
			];
		}

		$vehicle = $this->normalise_vehicle( $body, $license_plate );

		// Defensive second-factor check: if a reference was supplied AND the API
		// echoes one back, they must match. The API is the primary gate (404),
		// this guards against a misconfigured endpoint that ignores the field.
		if ( '' !== $reference ) {
			$record = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : $body;
			$echo   = isset( $record['reference'] ) ? (string) $record['reference'] : '';
			if ( '' !== $echo && ! hash_equals( strtoupper( $echo ), strtoupper( $reference ) ) ) {
				return [
					'ok'      => false,
					'code'    => 404,
					'message' => __( 'No vehicle matched that plate and reference.', 'sperhake-tracker' ),
				];
			}
		}

		return [
			'ok'   => true,
			'data' => $vehicle,
		];
	}

	/**
	 * Normalise the raw API payload into a predictable shape for the frontend.
	 *
	 * @param array<string, mixed> $body Raw API body.
	 * @return array<string, mixed>
	 */
	private function normalise_vehicle( array $body, string $plate ): array {
		// The relocation API returns a flat record; some deployments nest it
		// under "data" — support both shapes.
		$v = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : $body;

		// Penalty: the relocation API exposes it as "amount" (major currency
		// units, e.g. 123 = €123.00). Fall back to legacy "penalty_amount".
		$penalty_amount = isset( $v['amount'] )
			? (float) $v['amount']
			: ( isset( $v['penalty_amount'] ) ? (float) $v['penalty_amount'] : 0.0 );

		// Relocation timestamp is ISO-8601 (e.g. "2026-06-11T01:33:00+02:00").
		// Split into date/time without shifting the API-provided local time.
		$relocation_at = (string) ( $v['relocation_at'] ?? '' );
		$towed_date    = (string) ( $v['towed_date'] ?? ( '' !== $relocation_at ? substr( $relocation_at, 0, 10 ) : '' ) );
		$towed_time    = (string) ( $v['towed_time'] ?? ( strlen( $relocation_at ) >= 16 ? substr( $relocation_at, 11, 5 ) : '' ) );

		// Origin ("address_a") is where the vehicle was towed from. Some
		// deployments still send the legacy "pickup_address" field — support both.
		$pickup_address = (string) ( $v['address_a'] ?? $v['pickup_address'] ?? '' );

		return [
			'license_plate'        => (string) ( $v['plate'] ?? $v['license_plate'] ?? $plate ),
			'owner_name'           => isset( $v['owner_name'] ) ? (string) $v['owner_name'] : '',
			// Numeric case id — used internally for the /{caseId}/paid & request-invoice routes.
			'vehicle_id'           => (string) ( $v['case_id'] ?? $v['vehicle_id'] ?? '' ),
			// Human-facing case reference (e.g. "UM-202600039"), shown to the customer.
			'case_number'          => (string) ( $v['case_number'] ?? '' ),
			'vehicle_brand'        => (string) ( $v['vehicle_brand'] ?? '' ),
			'vehicle_type'         => (string) ( $v['vehicle_type'] ?? '' ),
			'partner_name'         => (string) ( $v['partner_name'] ?? '' ),
			'truck_code'           => (string) ( $v['truck_code'] ?? '' ),
			'status'               => (string) ( $v['status_for_display'] ?? $v['status'] ?? 'unknown' ),
			'towed_date'           => $towed_date,
			'towed_time'           => $towed_time,
			'pickup_address'       => $pickup_address,
			'origin_lat'           => isset( $v['address_a_lat'] ) && null !== $v['address_a_lat'] ? (string) $v['address_a_lat'] : '',
			'origin_lng'           => isset( $v['address_a_lng'] ) && null !== $v['address_a_lng'] ? (string) $v['address_a_lng'] : '',
			'towing_location'      => (string) ( $v['towing_location'] ?? '' ),
			'storage_yard_name'    => (string) ( $v['storage_yard_name'] ?? '' ),
			'storage_yard_address' => (string) ( $v['storage_yard_address'] ?? '' ),
			'current_location'     => (string) ( $v['current_location'] ?? '' ),
			'contact_number'       => (string) ( $v['contact_number'] ?? '' ),
			'release_instructions' => (string) ( $v['release_instructions'] ?? $v['reason'] ?? '' ),
			'penalty_amount'       => $penalty_amount,
			'penalty_cents'        => (int) round( $penalty_amount * 100 ),
			'currency'             => strtolower( (string) ( $v['currency'] ?? $this->options->currency() ) ),
			'is_paid'              => ! empty( $v['is_paid'] ),
			// Destination ("address_b") is populated by the API once the case is paid.
			'destination_address'  => (string) ( $v['address_b'] ?? '' ),
			'destination_lat'      => isset( $v['address_b_lat'] ) && null !== $v['address_b_lat'] ? (string) $v['address_b_lat'] : '',
			'destination_lng'      => isset( $v['address_b_lng'] ) && null !== $v['address_b_lng'] ? (string) $v['address_b_lng'] : '',
		];
	}

	/**
	 * Ask the relocation API to (re)send the invoice for a paid case, forwarding
	 * the billing details the customer confirmed so the main system can update
	 * its customer + invoice records before (re)issuing the invoice.
	 *
	 * POST /{case_id}/request-invoice with a { customer: {...} } body.
	 *
	 * @param string                $case_id  Relocation case id (the normalised vehicle_id).
	 * @param array<string, string> $customer Confirmed billing details (legal_name,
	 *                                        email, address_street, address_zip,
	 *                                        address_city, address_country).
	 * @return array{ok: bool, message: string}
	 */
	public function request_invoice( string $case_id, array $customer = [] ): array {
		$base = $this->options->api_url();

		if ( '' === $base || '' === $case_id ) {
			return [
				'ok'      => false,
				'message' => __( 'Invoice requests are temporarily unavailable. Please contact us.', 'sperhake-tracker' ),
			];
		}

		$payload  = [
			'customer' => [
				'legal_name'      => (string) ( $customer['legal_name'] ?? '' ),
				'email'           => (string) ( $customer['email'] ?? '' ),
				'address_street'  => (string) ( $customer['address_street'] ?? '' ),
				'address_zip'     => (string) ( $customer['address_zip'] ?? '' ),
				'address_city'    => (string) ( $customer['address_city'] ?? '' ),
				'address_country' => (string) ( $customer['address_country'] ?? '' ),
			],
		];
		$body     = (string) wp_json_encode( $payload );
		$endpoint = $this->build_url( $base, rawurlencode( $case_id ) . '/request-invoice' );

		$response = wp_remote_post(
			$endpoint,
			[
				'headers'     => $this->headers( $body ),
				'body'        => $body,
				'timeout'     => $this->options->api_timeout(),
				'redirection' => 2,
				'sslverify'   => true,
			]
		);

		if ( $response instanceof WP_Error ) {
			$this->logger->error(
				'api',
				'Invoice request failed.',
				[ 'case' => $case_id, 'error' => $response->get_error_message() ]
			);

			return [
				'ok'      => false,
				'message' => __( 'We could not reach the invoice service. Please try again shortly.', 'sperhake-tracker' ),
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$this->logger->warning(
				'api',
				'Invoice request returned an unexpected response.',
				[ 'case' => $case_id, 'status' => $code ]
			);

			return [
				'ok'      => false,
				'message' => __( 'The invoice could not be requested right now. Please try again later.', 'sperhake-tracker' ),
			];
		}

		return [
			'ok'      => true,
			'message' => __( 'Invoice requested. It will be emailed to you shortly.', 'sperhake-tracker' ),
		];
	}

	/**
	 * Perform an authenticated GET request.
	 *
	 * @return array|WP_Error
	 */
	private function get( string $url ) {
		return wp_remote_get(
			$url,
			[
				'headers'     => $this->headers( '' ),
				'timeout'     => $this->options->api_timeout(),
				'redirection' => 2,
				'sslverify'   => true,
			]
		);
	}

	/**
	 * Build request headers including authentication + an HMAC signature.
	 *
	 * @return array<string, string>
	 */
	private function headers( string $body ): array {
		$headers = array_merge(
			[
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'User-Agent'   => 'SperhakeTracker/' . SPERHAKE_TRACKER_VERSION,
			],
			$this->options->api_headers()
		);

		$key    = $this->options->api_key();
		$secret = $this->options->api_secret();

		if ( '' !== $key ) {
			$headers['X-API-Key'] = $key;
		}

		if ( '' !== $secret ) {
			// HMAC over the request body so the server can verify integrity.
			$headers['X-Signature'] = hash_hmac( 'sha256', $body, $secret );
		}

		return $headers;
	}

	private function build_url( string $base, string $path ): string {
		return rtrim( $base, '/' ) . '/' . ltrim( $path, '/' );
	}
}
