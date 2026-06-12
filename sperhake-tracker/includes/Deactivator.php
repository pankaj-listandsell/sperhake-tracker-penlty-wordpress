<?php
/**
 * Runs on plugin deactivation.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker;

use SperhakeTracker\Cron\RetryQueue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Deactivator {

	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( RetryQueue::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, RetryQueue::HOOK );
		}
		wp_clear_scheduled_hook( RetryQueue::HOOK );

		flush_rewrite_rules();
	}
}
