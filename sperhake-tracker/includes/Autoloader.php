<?php
/**
 * Lightweight PSR-4 autoloader for first-party plugin classes.
 *
 * Mirrors the mapping declared in composer.json so that the plugin works even
 * when the Composer-generated autoloader is unavailable.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Autoloader {

	/**
	 * Map of namespace prefixes to their base directories (relative to plugin root).
	 *
	 * Order matters: longest prefix is checked first so sub-namespaces win.
	 *
	 * @var array<string, string>
	 */
	private array $prefixes = [
		'SperhakeTracker\\Admin\\'    => 'admin/',
		'SperhakeTracker\\Frontend\\' => 'frontend/',
		'SperhakeTracker\\Api\\'      => 'api/',
		'SperhakeTracker\\Payments\\' => 'payments/',
		'SperhakeTracker\\Emails\\'   => 'emails/',
		'SperhakeTracker\\Pdf\\'      => 'pdf/',
		'SperhakeTracker\\'           => 'includes/',
	];

	/**
	 * Register the autoloader with the SPL stack.
	 */
	public function register(): void {
		// Ensure longest prefixes are evaluated first.
		uksort(
			$this->prefixes,
			static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a )
		);

		spl_autoload_register( [ $this, 'load_class' ] );
	}

	/**
	 * Resolve a fully-qualified class name to a file and require it.
	 */
	public function load_class( string $class ): void {
		foreach ( $this->prefixes as $prefix => $base_dir ) {
			if ( ! str_starts_with( $class, $prefix ) ) {
				continue;
			}

			$relative = substr( $class, strlen( $prefix ) );
			$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
			$file     = SPERHAKE_TRACKER_DIR . $base_dir . $relative . '.php';

			if ( is_readable( $file ) ) {
				require_once $file;
			}

			return;
		}
	}
}
