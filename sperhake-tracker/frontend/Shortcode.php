<?php
/**
 * [sperhake_vehicle_search] shortcode and the post-payment "thank you" view.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Frontend;

use SperhakeTracker\Api\VehicleApiClient;
use SperhakeTracker\Database\TransactionRepository;
use SperhakeTracker\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcode {

	public function __construct(
		private readonly TransactionRepository $transactions,
		private readonly Options $options,
		private readonly VehicleApiClient $api
	) {}

	public function register(): void {
		add_shortcode( 'sperhake_vehicle_search', [ $this, 'render' ] );
	}

	/**
	 * Render the search UI (and the success panel when returning from Stripe).
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			[
				'title' => __( 'Find Your Vehicle', 'sperhake-tracker' ),
			],
			(array) $atts,
			'sperhake_vehicle_search'
		);

		// On-demand asset loading.
		wp_enqueue_style( Assets::HANDLE );
		wp_enqueue_script( Assets::HANDLE );

		// Settings consumed by the form template.
		$recaptcha_site_key = $this->options->recaptcha_site_key();
		$recaptcha_enabled  = '' !== $recaptcha_site_key && '' !== $this->options->recaptcha_secret();
		$require_reference  = $this->options->require_reference();
		$reference_label    = $this->options->reference_label();

		if ( $recaptcha_enabled ) {
			wp_enqueue_script( Assets::RECAPTCHA );
		}

		ob_start();

		// If the visitor just returned from a successful Stripe Checkout, show
		// the confirmation panel above the search form.
		$this->maybe_render_success();

		$template = $this->locate_template( 'frontend/search-form.php' );
		if ( $template ) {
			include $template;
		}

		return (string) ob_get_clean();
	}

	/**
	 * Detect ?sperhake_payment=success&ref=...&token=... and render the receipt panel.
	 */
	private function maybe_render_success(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only public confirmation, validated by per-row token.
		if ( ! isset( $_GET['sperhake_payment'] ) || 'success' !== sanitize_key( wp_unslash( $_GET['sperhake_payment'] ) ) ) {
			return;
		}

		$ref   = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		// phpcs:enable

		if ( '' === $ref || '' === $token ) {
			return;
		}

		$transaction = $this->transactions->find_for_receipt( $ref, $token );
		if ( ! $transaction ) {
			return;
		}

		$receipt_url = '';
		if ( 'paid' === $transaction->payment_status && $transaction->receipt_path ) {
			// Time-limited, HMAC-signed link (valid 24h).
			$receipt_url = \SperhakeTracker\Support\ReceiptLink::create( $transaction );
		}

		$template = $this->locate_template( 'frontend/payment-success.php' );
		if ( $template ) {
			include $template;
		}

		// Automatically re-run the vehicle search so the customer immediately sees
		// the relocation destination and route map without re-entering their plate.
		$this->render_post_payment_vehicle( $transaction );
	}

	/**
	 * Call the search API for the just-paid vehicle and render its result card
	 * (relocation destination + route map) beneath the confirmation panel.
	 */
	private function render_post_payment_vehicle( object $transaction ): void {
		$plate = (string) $transaction->license_plate;
		if ( '' === $plate ) {
			return;
		}

		// The reference (second factor) was stored alongside the transaction.
		$reference = '';
		if ( ! empty( $transaction->meta ) ) {
			$meta = json_decode( (string) $transaction->meta, true );
			if ( is_array( $meta ) && isset( $meta['reference'] ) ) {
				$reference = (string) $meta['reference'];
			}
		}

		$result = $this->api->search_vehicle( $plate, $reference );
		if ( empty( $result['ok'] ) || empty( $result['data'] ) ) {
			return;
		}

		// Only surface the relocation/route card once the API confirms payment.
		// The destination is populated post-payment, and the relocation API may
		// briefly still report the case as unpaid (webhook sync is async) — in
		// which case we avoid re-rendering a stale "Pay Now" button.
		if ( empty( $result['data']['is_paid'] ) ) {
			return;
		}

		$template = $this->locate_template( 'frontend/vehicle-result.php' );
		if ( '' === $template ) {
			return;
		}

		$vehicle              = $result['data'];
		$vehicle['reference'] = $reference; // Carry through for the pay step (no-op once paid).

		// Nonces consumed by the result template's pay/invoice buttons.
		$pay_nonce     = wp_create_nonce( 'sperhake_pay' );
		$invoice_nonce = wp_create_nonce( 'sperhake_invoice' );

		echo '<div class="sperhake-results sperhake-results--post-payment" aria-live="polite">';
		include $template;
		echo '</div>';
	}

	/**
	 * Allow themes to override templates via /sperhake-tracker/<file>.
	 */
	private function locate_template( string $relative ): string {
		$theme = locate_template( 'sperhake-tracker/' . basename( $relative ) );
		if ( $theme ) {
			return $theme;
		}

		$path = SPERHAKE_TRACKER_DIR . 'templates/' . $relative;

		return is_readable( $path ) ? $path : '';
	}
}
