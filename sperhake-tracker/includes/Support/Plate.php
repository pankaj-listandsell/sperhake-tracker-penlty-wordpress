<?php
/**
 * License-plate normalisation & validation.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plate {

	/**
	 * Normalise user input to a canonical uppercase plate.
	 * Keeps A–Z, 0–9 and single hyphens/spaces.
	 */
	public static function normalise( string $input ): string {
		$plate = strtoupper( trim( $input ) );
		$plate = preg_replace( '/\s+/', ' ', $plate ) ?? '';
		// Strip anything that is not a letter, digit, space or hyphen.
		$plate = preg_replace( '/[^A-Z0-9\- ]/', '', $plate ) ?? '';

		return substr( trim( $plate ), 0, 32 );
	}

	/**
	 * Basic sanity check: 2–12 alphanumerics after removing separators.
	 */
	public static function is_valid( string $plate ): bool {
		$compact = preg_replace( '/[^A-Z0-9]/', '', strtoupper( $plate ) ) ?? '';
		$len     = strlen( $compact );

		return $len >= 2 && $len <= 12;
	}
}
