<?php
/**
 * Database schema management (dbDelta).
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schema {

	/** Bump this when the schema changes to trigger a migration. */
	public const DB_VERSION = '1.1.0';

	public static function transactions_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'sperhake_transactions';
	}

	public static function logs_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'sperhake_logs';
	}

	public static function search_logs_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'sperhake_search_logs';
	}

	/**
	 * Run schema creation/upgrade if the stored DB version is behind.
	 * Safe to call on every load (dbDelta is idempotent); cheap version guard first.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( 'sperhake_tracker_db_version' ) === self::DB_VERSION ) {
			return;
		}

		self::create_tables();
		update_option( 'sperhake_tracker_db_version', self::DB_VERSION, false );
	}

	/**
	 * Create or upgrade all plugin tables using dbDelta().
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$transactions    = self::transactions_table();
		$logs            = self::logs_table();
		$search_logs     = self::search_logs_table();

		// NOTE: dbDelta is whitespace/format sensitive — keep two spaces after PRIMARY KEY.
		$sql_transactions = "CREATE TABLE {$transactions} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			transaction_ref VARCHAR(64) NOT NULL,
			license_plate VARCHAR(32) NOT NULL,
			vehicle_id VARCHAR(64) DEFAULT NULL,
			customer_name VARCHAR(190) DEFAULT NULL,
			customer_email VARCHAR(190) DEFAULT NULL,
			amount_cents BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			currency VARCHAR(8) NOT NULL DEFAULT 'eur',
			payment_status VARCHAR(32) NOT NULL DEFAULT 'pending',
			stripe_session_id VARCHAR(191) DEFAULT NULL,
			stripe_payment_intent VARCHAR(191) DEFAULT NULL,
			stripe_charge_id VARCHAR(191) DEFAULT NULL,
			receipt_path VARCHAR(255) DEFAULT NULL,
			receipt_token VARCHAR(64) DEFAULT NULL,
			api_sync_status VARCHAR(32) NOT NULL DEFAULT 'pending',
			api_attempts SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			meta LONGTEXT DEFAULT NULL,
			created_at DATETIME NULL DEFAULT NULL,
			updated_at DATETIME NULL DEFAULT NULL,
			paid_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY transaction_ref (transaction_ref),
			KEY license_plate (license_plate),
			KEY payment_status (payment_status),
			KEY stripe_session_id (stripe_session_id),
			KEY api_sync_status (api_sync_status),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_logs = "CREATE TABLE {$logs} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			level VARCHAR(16) NOT NULL DEFAULT 'info',
			channel VARCHAR(40) NOT NULL DEFAULT 'general',
			message TEXT NOT NULL,
			context LONGTEXT DEFAULT NULL,
			created_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY level (level),
			KEY channel (channel),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_search_logs = "CREATE TABLE {$search_logs} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			license_plate VARCHAR(32) NOT NULL,
			reference_provided TINYINT(1) NOT NULL DEFAULT 0,
			ip_address VARBINARY(16) DEFAULT NULL,
			ip_hash CHAR(64) DEFAULT NULL,
			user_agent VARCHAR(255) DEFAULT NULL,
			result VARCHAR(20) NOT NULL DEFAULT 'unknown',
			created_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY license_plate (license_plate),
			KEY ip_hash (ip_hash),
			KEY result (result),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_transactions );
		dbDelta( $sql_logs );
		dbDelta( $sql_search_logs );
	}

	/**
	 * Drop all plugin tables. Used by uninstall when full cleanup is requested.
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$transactions = self::transactions_table();
		$logs         = self::logs_table();
		$search_logs  = self::search_logs_table();

		// Table names are built from $wpdb->prefix and cannot be parameterised.
		$wpdb->query( "DROP TABLE IF EXISTS {$transactions}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$logs}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$search_logs}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
