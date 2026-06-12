<?php
/**
 * Registers and conditionally enqueues frontend assets.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Frontend;

use SperhakeTracker\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {

	public const HANDLE    = 'sperhake-tracker';
	public const TURNSTILE = 'sperhake-turnstile';

	public function __construct( private readonly Options $options ) {}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
	}

	/**
	 * Register (but do not force-enqueue) styles & scripts. The shortcode
	 * enqueues them on demand so unrelated pages stay lightweight.
	 */
	public function register_assets(): void {
		wp_register_style(
			self::HANDLE,
			SPERHAKE_TRACKER_URL . 'assets/css/frontend.css',
			[],
			SPERHAKE_TRACKER_VERSION
		);

		// Cloudflare Turnstile (explicit render API).
		wp_register_script(
			self::TURNSTILE,
			'https://challenges.cloudflare.com/turnstile/v0/api.js',
			[],
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- external, versioned by Cloudflare.
			true
		);

		wp_register_script(
			self::HANDLE,
			SPERHAKE_TRACKER_URL . 'assets/js/frontend.js',
			[],
			SPERHAKE_TRACKER_VERSION,
			true
		);

		$site_key = $this->options->turnstile_site_key();

		wp_localize_script(
			self::HANDLE,
			'SperhakeTracker',
			[
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'sperhake_search' ),
				'payNonce'          => wp_create_nonce( 'sperhake_pay' ),
				'turnstileSiteKey'  => $site_key,
				'turnstileEnabled'  => '' !== $site_key && '' !== $this->options->turnstile_secret(),
				'requireReference'  => $this->options->require_reference(),
				'i18n'              => [
					'searching'   => __( 'Searching…', 'sperhake-tracker' ),
					'search'      => __( 'Search Vehicle', 'sperhake-tracker' ),
					'redirecting' => __( 'Redirecting to secure checkout…', 'sperhake-tracker' ),
					'genericErr'  => __( 'Something went wrong. Please try again.', 'sperhake-tracker' ),
					'enterPlate'  => __( 'Please enter a license plate.', 'sperhake-tracker' ),
					'enterRef'    => __( 'Please enter the required reference.', 'sperhake-tracker' ),
					'captchaWait' => __( 'Please complete the verification first.', 'sperhake-tracker' ),
					'requestingInvoice' => __( 'Requesting…', 'sperhake-tracker' ),
					'invoiceRequested'  => __( 'Invoice requested. Please check your email.', 'sperhake-tracker' ),
				],
			]
		);
	}
}
