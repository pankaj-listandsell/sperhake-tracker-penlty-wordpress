<?php
/**
 * Builds and verifies time-limited, HMAC-signed receipt download URLs.
 *
 * A link is valid only until its embedded expiry timestamp and only if the
 * signature matches, so a forwarded/leaked URL stops working after the TTL.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReceiptLink {

	/** Default lifetime of a receipt link. */
	public const DEFAULT_TTL = DAY_IN_SECONDS; // 24 hours.

	public const STATUS_VALID   = 'valid';
	public const STATUS_EXPIRED = 'expired';
	public const STATUS_INVALID = 'invalid';

	/**
	 * Create a signed, expiring download URL for a transaction.
	 *
	 * @param object $transaction Transaction row (needs ->transaction_ref).
	 * @param int    $ttl         Seconds until expiry.
	 */
	public static function create( object $transaction, int $ttl = self::DEFAULT_TTL ): string {
		$ref = (string) $transaction->transaction_ref;
		$exp = time() + max( 60, $ttl );
		$sig = self::sign( $ref, $exp );

		return add_query_arg(
			[
				'action' => 'sperhake_receipt',
				'ref'    => rawurlencode( $ref ),
				'exp'    => $exp,
				'sig'    => $sig,
			],
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Verify a signed link. Returns one of the STATUS_* constants.
	 */
	public static function verify( string $ref, int $exp, string $sig ): string {
		$expected = self::sign( $ref, $exp );

		// Constant-time comparison to avoid signature timing leaks.
		if ( '' === $sig || ! hash_equals( $expected, $sig ) ) {
			return self::STATUS_INVALID;
		}

		if ( $exp < time() ) {
			return self::STATUS_EXPIRED;
		}

		return self::STATUS_VALID;
	}

	/**
	 * Compute the HMAC for a (ref, exp) pair.
	 */
	private static function sign( string $ref, int $exp ): string {
		return hash_hmac( 'sha256', $ref . '|' . $exp, self::secret() );
	}

	/**
	 * Signing secret. Prefers the dedicated encryption key, falls back to a WP salt.
	 * Either way it is site-specific and not exposed to the browser.
	 */
	private static function secret(): string {
		if ( defined( 'SPERHAKE_ENCRYPTION_KEY' ) && '' !== (string) SPERHAKE_ENCRYPTION_KEY ) {
			return 'receipt:' . (string) SPERHAKE_ENCRYPTION_KEY;
		}

		return 'receipt:' . wp_salt( 'secure_auth' );
	}
}
