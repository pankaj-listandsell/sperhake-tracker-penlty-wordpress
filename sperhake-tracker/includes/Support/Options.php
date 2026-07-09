<?php
/**
 * Typed accessor around plugin settings, transparently decrypting secrets.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Support;

use SperhakeTracker\Security\Encryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Options {

	public const API    = 'sperhake_api_settings';
	public const STRIPE = 'sperhake_stripe_settings';
	public const EMAIL  = 'sperhake_email_settings';
	public const GDPR   = 'sperhake_gdpr_settings';

	/** Keys that are stored encrypted and must be decrypted on read. */
	private const ENCRYPTED_KEYS = [
		self::API    => [ 'api_key', 'api_secret', 'recaptcha_secret' ],
		self::STRIPE => [ 'secret_key', 'webhook_secret' ],
	];

	public function __construct( private readonly Encryption $encryption ) {}

	/**
	 * Return a settings group as an array.
	 *
	 * @return array<string, mixed>
	 */
	public function group( string $group ): array {
		$value = get_option( $group, [] );

		return is_array( $value ) ? $value : [];
	}

	/**
	 * Get a single setting value, decrypting if the field is a known secret.
	 */
	public function get( string $group, string $key, mixed $default = '' ): mixed {
		$data  = $this->group( $group );
		$value = $data[ $key ] ?? $default;

		if ( is_string( $value ) && $this->is_encrypted_field( $group, $key ) ) {
			return $this->encryption->decrypt( $value );
		}

		return $value;
	}

	public function is_encrypted_field( string $group, string $key ): bool {
		return in_array( $key, self::ENCRYPTED_KEYS[ $group ] ?? [], true );
	}

	/* ------------------------------------------------------------------
	 * Convenience getters used across the plugin
	 * --------------------------------------------------------------- */

	public function api_url(): string {
		return (string) $this->get( self::API, 'api_url', '' );
	}

	public function api_key(): string {
		return (string) $this->get( self::API, 'api_key', '' );
	}

	public function api_secret(): string {
		return (string) $this->get( self::API, 'api_secret', '' );
	}

	public function webhook_forward_url(): string {
		return (string) $this->get( self::API, 'webhook_url', '' );
	}

	public function api_timeout(): int {
		return max( 5, min( 120, (int) $this->get( self::API, 'timeout', 20 ) ) );
	}

	/**
	 * Parse the admin-defined custom headers (one "Key: Value" per line).
	 *
	 * @return array<string, string>
	 */
	public function api_headers(): array {
		$raw     = (string) $this->get( self::API, 'headers', '' );
		$headers = [];

		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || ! str_contains( $line, ':' ) ) {
				continue;
			}
			[ $name, $value ] = array_map( 'trim', explode( ':', $line, 2 ) );
			if ( '' !== $name ) {
				$headers[ $name ] = $value;
			}
		}

		return $headers;
	}

	public function stripe_mode(): string {
		return 'live' === $this->get( self::STRIPE, 'mode', 'test' ) ? 'live' : 'test';
	}

	public function stripe_publishable_key(): string {
		return (string) $this->get( self::STRIPE, 'publishable_key', '' );
	}

	public function stripe_secret_key(): string {
		return (string) $this->get( self::STRIPE, 'secret_key', '' );
	}

	public function stripe_webhook_secret(): string {
		return (string) $this->get( self::STRIPE, 'webhook_secret', '' );
	}

	public function currency(): string {
		return strtolower( (string) $this->get( self::STRIPE, 'currency', 'eur' ) ) ?: 'eur';
	}

	public function retention_days(): int {
		return max( 0, (int) $this->get( self::GDPR, 'retention_days', 365 ) );
	}

	/* ------------------------------------------------------------------
	 * Frontend security (reCAPTCHA + reference) — stored in the API group
	 * --------------------------------------------------------------- */

	public function recaptcha_site_key(): string {
		return (string) $this->get( self::API, 'recaptcha_site_key', '' );
	}

	public function recaptcha_secret(): string {
		return (string) $this->get( self::API, 'recaptcha_secret', '' );
	}

	public function require_reference(): bool {
		return (bool) $this->get( self::API, 'require_reference', 0 );
	}

	public function reference_label(): string {
		$label = trim( (string) $this->get( self::API, 'reference_label', '' ) );

		return '' !== $label ? $label : __( 'Case Number', 'sperhake-tracker' );
	}
}
