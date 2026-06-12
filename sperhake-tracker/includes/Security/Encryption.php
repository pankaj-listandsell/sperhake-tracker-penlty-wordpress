<?php
/**
 * Symmetric encryption helper for secrets at rest (API keys, Stripe keys).
 *
 * Uses libsodium (XChaCha20-Poly1305) when available, falling back to
 * OpenSSL AES-256-GCM. The key is generated once and stored in wp_options.
 * For higher security define SPERHAKE_ENCRYPTION_KEY (base64) in wp-config.php.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Encryption {

	private const OPTION_KEY = 'sperhake_encryption_key';
	private const PREFIX     = 'sperhake_v1::';

	/**
	 * Ensure an encryption key exists (called on activation).
	 */
	public static function ensure_key(): void {
		if ( defined( 'SPERHAKE_ENCRYPTION_KEY' ) ) {
			return;
		}

		if ( false === get_option( self::OPTION_KEY, false ) ) {
			$key = base64_encode( random_bytes( 32 ) );
			// Autoload off: secret should not be in every page load's alloptions cache.
			add_option( self::OPTION_KEY, $key, '', false );
		}
	}

	/**
	 * Retrieve the raw 32-byte key.
	 */
	private function key(): string {
		if ( defined( 'SPERHAKE_ENCRYPTION_KEY' ) ) {
			$decoded = base64_decode( (string) SPERHAKE_ENCRYPTION_KEY, true );
			if ( false !== $decoded && 32 === strlen( $decoded ) ) {
				return $decoded;
			}
		}

		$stored = get_option( self::OPTION_KEY, '' );
		$raw    = base64_decode( (string) $stored, true );

		if ( false === $raw || 32 !== strlen( $raw ) ) {
			// Last-resort deterministic key derived from WP salts. Not ideal but
			// guarantees the plugin keeps functioning rather than fatalling.
			$raw = hash( 'sha256', wp_salt( 'secure_auth' ), true );
		}

		return $raw;
	}

	/**
	 * Encrypt a plaintext string. Returns a portable, prefixed token.
	 */
	public function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}

		$key = $this->key();

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			$token  = self::PREFIX . 'sodium::' . base64_encode( $nonce . $cipher );
			sodium_memzero( $plaintext );

			return $token;
		}

		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

		return self::PREFIX . 'openssl::' . base64_encode( $iv . $tag . $cipher );
	}

	/**
	 * Decrypt a token produced by encrypt(). Returns '' on failure.
	 */
	public function decrypt( string $token ): string {
		if ( '' === $token || ! str_starts_with( $token, self::PREFIX ) ) {
			// Treat unknown / legacy plaintext as-is so settings still display.
			return $token;
		}

		$key     = $this->key();
		$payload = substr( $token, strlen( self::PREFIX ) );

		if ( str_starts_with( $payload, 'sodium::' ) ) {
			$bin = base64_decode( substr( $payload, 8 ), true );
			if ( false === $bin || strlen( $bin ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return '';
			}
			$nonce  = substr( $bin, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = substr( $bin, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $key );

			return false === $plain ? '' : $plain;
		}

		if ( str_starts_with( $payload, 'openssl::' ) ) {
			$bin = base64_decode( substr( $payload, 9 ), true );
			if ( false === $bin || strlen( $bin ) <= 28 ) {
				return '';
			}
			$iv     = substr( $bin, 0, 12 );
			$tag    = substr( $bin, 12, 16 );
			$cipher = substr( $bin, 28 );
			$plain  = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

			return false === $plain ? '' : $plain;
		}

		return '';
	}

	/**
	 * Mask a secret for display, e.g. "sk_live_****abcd".
	 */
	public static function mask( string $secret ): string {
		$len = strlen( $secret );
		if ( $len <= 8 ) {
			return str_repeat( '*', max( 0, $len ) );
		}

		return substr( $secret, 0, 7 ) . str_repeat( '*', 4 ) . substr( $secret, -4 );
	}
}
