<?php
/**
 * Database-backed logger for system/API/payment events.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Logging;

use SperhakeTracker\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Logger {

	public const DEBUG   = 'debug';
	public const INFO    = 'info';
	public const WARNING = 'warning';
	public const ERROR   = 'error';

	/**
	 * Write a log entry.
	 *
	 * @param string               $level   One of the level constants.
	 * @param string               $channel Logical channel (api, stripe, webhook, email...).
	 * @param string               $message Human-readable message.
	 * @param array<string, mixed> $context Structured context (secrets are stripped).
	 */
	public function log( string $level, string $channel, string $message, array $context = [] ): void {
		global $wpdb;

		$context = $this->redact( $context );

		$wpdb->insert(
			Schema::logs_table(),
			[
				'level'      => substr( $level, 0, 16 ),
				'channel'    => substr( $channel, 0, 40 ),
				'message'    => $message,
				'context'    => $context ? wp_json_encode( $context ) : null,
				'created_at' => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%s', '%s' ]
		);

		// Mirror errors to the PHP error log when WP_DEBUG is on.
		if ( self::ERROR === $level && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[sperhake:%s] %s %s', $channel, $message, wp_json_encode( $context ) ) ); // phpcs:ignore
		}
	}

	public function debug( string $channel, string $message, array $context = [] ): void {
		$this->log( self::DEBUG, $channel, $message, $context );
	}

	public function info( string $channel, string $message, array $context = [] ): void {
		$this->log( self::INFO, $channel, $message, $context );
	}

	public function warning( string $channel, string $message, array $context = [] ): void {
		$this->log( self::WARNING, $channel, $message, $context );
	}

	public function error( string $channel, string $message, array $context = [] ): void {
		$this->log( self::ERROR, $channel, $message, $context );
	}

	/**
	 * Remove sensitive values from context before persisting.
	 *
	 * @param array<string, mixed> $context Raw context.
	 * @return array<string, mixed>
	 */
	private function redact( array $context ): array {
		$blocked = [ 'api_key', 'api_secret', 'secret', 'secret_key', 'password', 'authorization', 'webhook_secret', 'card', 'cvc' ];

		array_walk_recursive(
			$context,
			static function ( &$value, $key ) use ( $blocked ): void {
				if ( is_string( $key ) && in_array( strtolower( $key ), $blocked, true ) ) {
					$value = '***redacted***';
				}
			}
		);

		return $context;
	}

	/**
	 * Fetch recent log rows for the admin viewer.
	 *
	 * @return array<int, object>
	 */
	public function recent( int $limit = 200, string $level = '' ): array {
		global $wpdb;

		$table = Schema::logs_table();
		$limit = max( 1, min( 1000, $limit ) );

		if ( '' !== $level ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE level = %s ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$level,
					$limit
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			)
		);
	}

	/**
	 * Delete log rows older than a number of days.
	 */
	public function prune( int $days ): int {
		global $wpdb;

		$table  = Schema::logs_table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff
			)
		);
	}
}
