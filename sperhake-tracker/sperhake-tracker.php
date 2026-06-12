<?php
/**
 * Plugin Name:       Sperhake Vehicle Tracking & Penalty Payment
 * Plugin URI:        https://abschleppdienst-sperhake.de/
 * Description:        Vehicle lookup by license plate, towing status, and online penalty payment via Stripe for Abschleppdienst Sperhake.
 * Version:           1.3.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Abschleppdienst Sperhake
 * Author URI:        https://abschleppdienst-sperhake.de/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sperhake-tracker
 * Domain Path:       /languages
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker;

// Abort if WordPress is not the one loading this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * --------------------------------------------------------------------------
 * Plugin constants
 * --------------------------------------------------------------------------
 */
define( 'SPERHAKE_TRACKER_VERSION', '1.3.0' );
define( 'SPERHAKE_TRACKER_FILE', __FILE__ );
define( 'SPERHAKE_TRACKER_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPERHAKE_TRACKER_URL', plugin_dir_url( __FILE__ ) );
define( 'SPERHAKE_TRACKER_BASENAME', plugin_basename( __FILE__ ) );

/*
 * --------------------------------------------------------------------------
 * Autoloading
 * --------------------------------------------------------------------------
 * 1. Prefer the Composer autoloader (provides Stripe SDK + DomPDF).
 * 2. Always register our lightweight PSR-4 autoloader for first-party classes
 *    so the plugin still boots if `composer dump-autoload` was never run.
 */
if ( is_readable( SPERHAKE_TRACKER_DIR . 'vendor/autoload.php' ) ) {
	require_once SPERHAKE_TRACKER_DIR . 'vendor/autoload.php';
}

require_once SPERHAKE_TRACKER_DIR . 'includes/Autoloader.php';
( new Autoloader() )->register();

/*
 * --------------------------------------------------------------------------
 * Activation / Deactivation / Uninstall
 * --------------------------------------------------------------------------
 */
register_activation_hook( __FILE__, [ Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Deactivator::class, 'deactivate' ] );

/*
 * --------------------------------------------------------------------------
 * Boot the plugin once WordPress has loaded all plugins.
 * --------------------------------------------------------------------------
 */
add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->boot();
	}
);
