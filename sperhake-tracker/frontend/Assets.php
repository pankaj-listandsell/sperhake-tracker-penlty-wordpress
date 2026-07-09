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
	public const RECAPTCHA = 'sperhake-recaptcha';

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

		// Google reCAPTCHA v2 (implicit render: scans for .g-recaptcha on load).
		// hl forces the widget language; default to the site locale (e.g. "de").
		$recaptcha_lang = $this->recaptcha_language();
		wp_register_script(
			self::RECAPTCHA,
			add_query_arg( 'hl', $recaptcha_lang, 'https://www.google.com/recaptcha/api.js' ),
			[],
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- external, versioned by Google.
			true
		);

		wp_register_script(
			self::HANDLE,
			SPERHAKE_TRACKER_URL . 'assets/js/frontend.js',
			[],
			SPERHAKE_TRACKER_VERSION,
			true
		);

		$site_key = $this->options->recaptcha_site_key();

		wp_localize_script(
			self::HANDLE,
			'SperhakeTracker',
			[
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'sperhake_search' ),
				'payNonce'          => wp_create_nonce( 'sperhake_pay' ),
				'recaptchaSiteKey'  => $site_key,
				'recaptchaEnabled'  => '' !== $site_key && '' !== $this->options->recaptcha_secret(),
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

	/**
	 * reCAPTCHA widget language code derived from the site locale.
	 *
	 * WordPress locales look like "de_DE"; reCAPTCHA expects "de" (with a few
	 * region-specific exceptions it handles itself, e.g. "pt-BR", "zh-CN").
	 * Filterable via "sperhake_recaptcha_language" for manual overrides.
	 */
	private function recaptcha_language(): string {
		$locale = (string) get_locale();              // e.g. "de_DE".
		$lang   = strtolower( str_replace( '_', '-', $locale ) );

		// A handful of reCAPTCHA codes keep their region suffix; everything else
		// uses the base language subtag.
		$regional = [ 'zh-cn', 'zh-tw', 'zh-hk', 'pt-br', 'pt-pt', 'en-gb', 'fr-ca' ];
		if ( ! in_array( $lang, $regional, true ) ) {
			$lang = strtok( $lang, '-' );             // "de-de" -> "de".
		}

		/** Allow integrators to force a specific reCAPTCHA language code. */
		return (string) apply_filters( 'sperhake_recaptcha_language', $lang );
	}
}
