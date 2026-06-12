<?php
/**
 * [sperhake_vehicle_search] shortcode and the post-payment "thank you" view.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Frontend;

use SperhakeTracker\Database\TransactionRepository;
use SperhakeTracker\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcode {

	public function __construct(
		private readonly TransactionRepository $transactions,
		private readonly Options $options
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
		$turnstile_site_key = $this->options->turnstile_site_key();
		$turnstile_enabled  = '' !== $turnstile_site_key && '' !== $this->options->turnstile_secret();
		$require_reference  = $this->options->require_reference();
		$reference_label    = $this->options->reference_label();

		if ( $turnstile_enabled ) {
			wp_enqueue_script( Assets::TURNSTILE );
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
