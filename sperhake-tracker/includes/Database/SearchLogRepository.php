<?php
/**
 * Data-access layer for the wp_sperhake_search_logs table (abuse detection).
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SearchLogRepository {

	private string $table;

	public function __construct() {
		$this->table = Schema::search_logs_table();
	}

	/**
	 * Record a single search attempt.
	 *
	 * @param string $plate     Normalised plate.
	 * @param string $ip        Raw client IP.
	 * @param string $result    One of: found, not_found, invalid, blocked, captcha_failed, error.
	 * @param bool   $reference Whether a reference number was supplied.
	 */
	public function record( string $plate, string $ip, string $result, bool $reference = false ): void {
		global $wpdb;

		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		$wpdb->insert(
			$this->table,
			[
				'license_plate'      => substr( $plate, 0, 32 ),
				'reference_provided' => $reference ? 1 : 0,
				'ip_address'         => false === $packed ? null : $packed,
				'ip_hash'            => '' === $ip ? null : hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ),
				'user_agent'         => isset( $_SERVER['HTTP_USER_AGENT'] )
					? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 )
					: null,
				'result'             => substr( $result, 0, 20 ),
				'created_at'         => current_time( 'mysql', true ),
			],
			[ '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Count searches from an IP hash within the last N seconds (sliding window).
	 */
	public function count_recent_for_ip( string $ip, int $seconds ): int {
		global $wpdb;

		if ( '' === $ip ) {
			return 0;
		}

		$hash   = hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $seconds );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE ip_hash = %s AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$hash,
				$cutoff
			)
		);
	}

	/**
	 * Fetch recent search rows for the admin viewer.
	 *
	 * @return array<int, object>
	 */
	public function recent( int $limit = 100 ): array {
		global $wpdb;

		$limit = max( 1, min( 500, $limit ) );

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d", $limit ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Retention cleanup.
	 */
	public function delete_older_than( int $days ): int {
		global $wpdb;

		if ( $days <= 0 ) {
			return 0;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		return (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$this->table} WHERE created_at < %s", $cutoff ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Convert a stored packed IP back to a readable string for display.
	 */
	public static function format_ip( ?string $packed ): string {
		if ( null === $packed || '' === $packed ) {
			return '—';
		}

		$ip = @inet_ntop( $packed ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return false === $ip ? '—' : $ip;
	}
}
