<?php
/**
 * Uninstall cleanup.
 *
 * Fired by WordPress when the user deletes the plugin. Removes options,
 * scheduled events, generated receipts and (optionally) database tables.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

// Only run from WordPress' uninstall routine.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/Autoloader.php';

if ( ! defined( 'SPERHAKE_TRACKER_DIR' ) ) {
	define( 'SPERHAKE_TRACKER_DIR', plugin_dir_path( __FILE__ ) );
}

( new \SperhakeTracker\Autoloader() )->register();

/*
 * Respect a "keep data" preference. If the admin disabled data removal we only
 * clear transient/cron artefacts and leave business records intact.
 */
$gdpr        = get_option( 'sperhake_gdpr_settings', [] );
$purge_data  = empty( $gdpr['preserve_on_uninstall'] );

// 1. Clear scheduled cron.
wp_clear_scheduled_hook( \SperhakeTracker\Cron\RetryQueue::HOOK );

// 2. Remove generated PDF receipts.
$uploads = wp_upload_dir();
$dir     = trailingslashit( $uploads['basedir'] ) . 'sperhake-receipts';
if ( $purge_data && is_dir( $dir ) ) {
	$files = glob( $dir . '/*' );
	if ( is_array( $files ) ) {
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}
	// Remove directory itself.
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}

if ( $purge_data ) {
	// 3. Delete options (including the encryption key).
	$options = [
		'sperhake_api_settings',
		'sperhake_stripe_settings',
		'sperhake_email_settings',
		'sperhake_gdpr_settings',
		'sperhake_tracker_db_version',
		'sperhake_encryption_key',
	];
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// 4. Drop custom tables.
	\SperhakeTracker\Database\Schema::drop_tables();
}
