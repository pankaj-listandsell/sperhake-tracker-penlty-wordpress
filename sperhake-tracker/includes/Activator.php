<?php
/**
 * Runs on plugin activation.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker;

use SperhakeTracker\Database\Schema;
use SperhakeTracker\Security\Encryption;
use SperhakeTracker\Cron\RetryQueue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {

	public static function activate(): void {
		// Guard against an unsupported PHP version.
		if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
			deactivate_plugins( SPERHAKE_TRACKER_BASENAME );
			wp_die(
				esc_html__( 'Sperhake Vehicle Tracker requires PHP 8.2 or higher.', 'sperhake-tracker' ),
				esc_html__( 'Plugin activation error', 'sperhake-tracker' ),
				[ 'back_link' => true ]
			);
		}

		Schema::create_tables();
		Encryption::ensure_key();
		self::seed_default_options();
		self::create_secure_storage();

		// Schedule the retry cron event.
		if ( ! wp_next_scheduled( RetryQueue::HOOK ) ) {
			wp_schedule_event( time() + 300, 'sperhake_five_minutes', RetryQueue::HOOK );
		}

		// Persist the DB version for future migrations.
		update_option( 'sperhake_tracker_db_version', Schema::DB_VERSION, false );

		flush_rewrite_rules();
	}

	/**
	 * Establish default option values without overwriting existing config.
	 */
	private static function seed_default_options(): void {
		$defaults = [
			'sperhake_api_settings'    => [
				'api_url'     => '',
				'webhook_url' => '',
				'timeout'     => 20,
				'headers'     => '',
			],
			'sperhake_stripe_settings' => [
				'mode'     => 'test',
				'currency' => 'eur',
			],
			'sperhake_email_settings'  => [
				'from_name'    => get_bloginfo( 'name' ),
				'from_email'   => get_bloginfo( 'admin_email' ),
				'subject'      => __( 'Your payment receipt – Abschleppdienst Sperhake', 'sperhake-tracker' ),
				'attach_pdf'   => 1,
			],
			'sperhake_gdpr_settings'   => [
				'retention_days' => 365,
			],
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key, false ) ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Create a protected uploads sub-directory for generated PDFs.
	 */
	private static function create_secure_storage(): void {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'sperhake-receipts';

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// Block direct web access to stored receipts.
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Order deny,allow\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		$index = $dir . '/index.html';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}
}
