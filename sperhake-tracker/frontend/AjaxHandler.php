<?php
/**
 * AJAX endpoint for the public vehicle search.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Frontend;

use SperhakeTracker\Api\VehicleApiClient;
use SperhakeTracker\Database\SearchLogRepository;
use SperhakeTracker\Logging\Logger;
use SperhakeTracker\Security\Recaptcha;
use SperhakeTracker\Support\Options;
use SperhakeTracker\Support\Plate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AjaxHandler {

	public function __construct(
		private readonly VehicleApiClient $api,
		private readonly Options $options,
		private readonly Logger $logger,
		private readonly Recaptcha $recaptcha,
		private readonly SearchLogRepository $searchLogs
	) {}

	public function register(): void {
		add_action( 'wp_ajax_sperhake_search', [ $this, 'handle_search' ] );
		add_action( 'wp_ajax_nopriv_sperhake_search', [ $this, 'handle_search' ] );

		// Public, signed receipt download.
		add_action( 'wp_ajax_sperhake_receipt', [ $this, 'handle_receipt' ] );
		add_action( 'wp_ajax_nopriv_sperhake_receipt', [ $this, 'handle_receipt' ] );

		// Request an invoice for a paid case.
		add_action( 'wp_ajax_sperhake_request_invoice', [ $this, 'handle_request_invoice' ] );
		add_action( 'wp_ajax_nopriv_sperhake_request_invoice', [ $this, 'handle_request_invoice' ] );
	}

	/**
	 * Handle a "request invoice" click for a paid case.
	 */
	public function handle_request_invoice(): void {
		check_ajax_referer( 'sperhake_invoice', 'nonce' );

		$case_id = isset( $_POST['case_id'] ) ? sanitize_text_field( wp_unslash( $_POST['case_id'] ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( '' === $case_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing case reference.', 'sperhake-tracker' ) ], 422 );
		}

		if ( '' !== $email && ! is_email( $email ) ) {
			wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.', 'sperhake-tracker' ) ], 422 );
		}

		$result = $this->api->request_invoice( $case_id, $email );

		if ( empty( $result['ok'] ) ) {
			wp_send_json_error(
				[ 'message' => $result['message'] ?? __( 'Could not request the invoice.', 'sperhake-tracker' ) ],
				502
			);
		}

		wp_send_json_success( [ 'message' => $result['message'] ?? __( 'Invoice requested.', 'sperhake-tracker' ) ] );
	}

	/**
	 * Handle the license-plate search request.
	 */
	public function handle_search(): void {
		check_ajax_referer( 'sperhake_search', 'nonce' );

		$ip    = $this->client_ip();
		$raw   = isset( $_POST['license_plate'] ) ? sanitize_text_field( wp_unslash( $_POST['license_plate'] ) ) : '';
		$plate = Plate::normalise( $raw );
		$ref   = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';

		// 1. Bot protection — Google reCAPTCHA v2 (skipped automatically if unconfigured).
		$token = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';
		if ( ! $this->recaptcha->verify( $token, $ip ) ) {
			$this->searchLogs->record( $plate, $ip, 'captcha_failed', '' !== $ref );
			wp_send_json_error(
				[ 'message' => __( 'Verification failed. Please complete the challenge and try again.', 'sperhake-tracker' ) ],
				403
			);
		}

		// 2. Lightweight rate limiting per IP to deter enumeration of plates.
		if ( $this->is_rate_limited() ) {
			$this->searchLogs->record( $plate, $ip, 'blocked', '' !== $ref );
			wp_send_json_error(
				[ 'message' => __( 'Too many requests. Please wait a moment and try again.', 'sperhake-tracker' ) ],
				429
			);
		}

		// 3. Validate the plate.
		if ( ! Plate::is_valid( $plate ) ) {
			$this->searchLogs->record( $plate, $ip, 'invalid', '' !== $ref );
			wp_send_json_error(
				[ 'message' => __( 'Please enter a valid license plate.', 'sperhake-tracker' ) ],
				422
			);
		}

		// 4. Require the second factor (case/reference number) when enabled.
		if ( $this->options->require_reference() && '' === $ref ) {
			$this->searchLogs->record( $plate, $ip, 'invalid', false );
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: configured reference field label */
						__( 'Please enter your %s to look up this vehicle.', 'sperhake-tracker' ),
						$this->options->reference_label()
					),
				],
				422
			);
		}

		$result = $this->api->search_vehicle( $plate, $ref );

		if ( empty( $result['ok'] ) ) {
			$status = ( $result['code'] ?? 0 ) === 404 ? 404 : 502;
			$this->searchLogs->record( $plate, $ip, 404 === $status ? 'not_found' : 'error', '' !== $ref );
			wp_send_json_error(
				[ 'message' => $result['message'] ?? __( 'Vehicle not found.', 'sperhake-tracker' ) ],
				$status
			);
		}

		$vehicle            = $result['data'];
		$vehicle['reference'] = $ref; // Carry the reference through to the pay step.

		$this->searchLogs->record( $plate, $ip, 'found', '' !== $ref );

		// Render the result HTML server-side so escaping is centralised.
		$html = $this->render_result( $vehicle );

		wp_send_json_success(
			[
				'html'          => $html,
				'has_penalty'   => $vehicle['penalty_cents'] > 0,
				'penalty_cents' => $vehicle['penalty_cents'],
				'license_plate' => $vehicle['license_plate'],
			]
		);
	}

	/**
	 * Stream a stored PDF receipt to the customer.
	 *
	 * Access is granted by a time-limited, HMAC-signed URL (no login required).
	 * Expired links return 410 Gone; tampered/invalid links return 404.
	 */
	public function handle_receipt(): void {
		$ref = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
		$exp = isset( $_GET['exp'] ) ? (int) $_GET['exp'] : 0;
		$sig = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';

		$status = \SperhakeTracker\Support\ReceiptLink::verify( $ref, $exp, $sig );

		if ( \SperhakeTracker\Support\ReceiptLink::STATUS_EXPIRED === $status ) {
			status_header( 410 );
			wp_die(
				esc_html__( 'This receipt link has expired. Please contact us to request a new copy.', 'sperhake-tracker' ),
				esc_html__( 'Link expired', 'sperhake-tracker' ),
				[ 'response' => 410 ]
			);
		}

		if ( \SperhakeTracker\Support\ReceiptLink::STATUS_VALID !== $status ) {
			status_header( 404 );
			wp_die( esc_html__( 'Receipt not found.', 'sperhake-tracker' ) );
		}

		$repo        = \SperhakeTracker\Plugin::instance()->get( 'transactions' );
		$transaction = $repo->find_by_ref( $ref );

		if ( ! $transaction || 'paid' !== $transaction->payment_status || ! $transaction->receipt_path ) {
			status_header( 404 );
			wp_die( esc_html__( 'Receipt not found.', 'sperhake-tracker' ) );
		}

		$path = $this->resolve_receipt_path( (string) $transaction->receipt_path );
		if ( '' === $path || ! is_readable( $path ) ) {
			status_header( 404 );
			wp_die( esc_html__( 'Receipt file is unavailable.', 'sperhake-tracker' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="receipt-' . sanitize_file_name( $transaction->transaction_ref ) . '.pdf"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Resolve a stored relative receipt path to an absolute uploads path,
	 * guarding against path traversal.
	 */
	private function resolve_receipt_path( string $stored ): string {
		$uploads = wp_upload_dir();
		$base     = trailingslashit( $uploads['basedir'] ) . 'sperhake-receipts';
		$absolute = trailingslashit( $base ) . basename( $stored );

		$real_base = realpath( $base );
		$real_file = realpath( $absolute );

		if ( false === $real_base || false === $real_file ) {
			return '';
		}

		return str_starts_with( $real_file, $real_base ) ? $real_file : '';
	}

	/**
	 * Render the search-result template into a string.
	 *
	 * @param array<string, mixed> $vehicle Normalised vehicle data.
	 */
	private function render_result( array $vehicle ): string {
		$template = SPERHAKE_TRACKER_DIR . 'templates/frontend/vehicle-result.php';
		$theme    = locate_template( 'sperhake-tracker/vehicle-result.php' );
		if ( $theme ) {
			$template = $theme;
		}

		if ( ! is_readable( $template ) ) {
			return '';
		}

		// Build the Pay-Now & invoice nonces the template needs.
		$pay_nonce     = wp_create_nonce( 'sperhake_pay' );
		$invoice_nonce = wp_create_nonce( 'sperhake_invoice' );

		ob_start();
		include $template;

		return (string) ob_get_clean();
	}

	/**
	 * Crude IP throttle: max 15 searches / 5 minutes.
	 */
	private function is_rate_limited(): bool {
		$ip  = $this->client_ip() ?: 'unknown';
		$key = 'sperhake_rl_' . md5( $ip );

		$count = (int) get_transient( $key );
		if ( $count >= 15 ) {
			return true;
		}

		set_transient( $key, $count + 1, 5 * MINUTE_IN_SECONDS );

		return false;
	}

	/**
	 * Best-effort client IP (REMOTE_ADDR only; proxy headers are spoofable
	 * and should be resolved at the web-server / Cloudflare layer instead).
	 */
	private function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
