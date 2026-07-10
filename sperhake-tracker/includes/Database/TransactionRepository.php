<?php
/**
 * Data-access layer for the wp_sperhake_transactions table.
 *
 * All queries use $wpdb prepared statements / the $wpdb->insert()/update()
 * format API to guarantee protection against SQL injection.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TransactionRepository {

	private string $table;

	public function __construct() {
		$this->table = Schema::transactions_table();
	}

	/**
	 * Insert a new transaction row.
	 *
	 * @param array<string, mixed> $data Sanitised column => value pairs.
	 * @return int Inserted row ID (0 on failure).
	 */
	public function create( array $data ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$row = wp_parse_args(
			$data,
			[
				'transaction_ref'   => $this->generate_ref(),
				'license_plate'     => '',
				'vehicle_id'        => null,
				'customer_name'     => null,
				'customer_email'    => null,
				'amount_cents'      => 0,
				'currency'          => 'eur',
				'payment_status'    => 'pending',
				'stripe_session_id' => null,
				'api_sync_status'   => 'pending',
				'receipt_token'     => wp_generate_password( 32, false ),
				'meta'              => null,
				'created_at'        => $now,
				'updated_at'        => $now,
			]
		);

		if ( is_array( $row['meta'] ) ) {
			$row['meta'] = wp_json_encode( $row['meta'] );
		}

		$inserted = $wpdb->insert(
			$this->table,
			$row,
			$this->formats_for( $row )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update a row by primary key.
	 *
	 * @param array<string, mixed> $data Columns to update.
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql', true );

		if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
			$data['meta'] = wp_json_encode( $data['meta'] );
		}

		return false !== $wpdb->update(
			$this->table,
			$data,
			[ 'id' => $id ],
			$this->formats_for( $data ),
			[ '%d' ]
		);
	}

	public function find( int $id ): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	public function find_by_ref( string $ref ): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE transaction_ref = %s", $ref ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Find a recent still-pending transaction for a plate that already has a
	 * Stripe session, so it can be reused instead of creating a duplicate.
	 */
	public function find_reusable_pending( string $plate, int $within_minutes = 30 ): ?object {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $within_minutes * MINUTE_IN_SECONDS ) );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				 WHERE license_plate = %s
				   AND payment_status = 'pending'
				   AND stripe_session_id IS NOT NULL
				   AND stripe_session_id <> ''
				   AND created_at >= %s
				 ORDER BY id DESC
				 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$plate,
				$cutoff
			)
		);
	}

	public function find_by_session( string $session_id ): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE stripe_session_id = %s", $session_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Find the most recent paid transaction for a relocation case id.
	 */
	public function latest_paid_for_case( string $case_id ): ?object {
		global $wpdb;

		if ( '' === $case_id ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				 WHERE vehicle_id = %s AND payment_status = 'paid'
				 ORDER BY id DESC
				 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$case_id
			)
		);
	}

	/**
	 * Find the most recent paid transaction for a license plate.
	 */
	public function latest_paid_for_plate( string $plate ): ?object {
		global $wpdb;

		if ( '' === $plate ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				 WHERE license_plate = %s AND payment_status = 'paid'
				 ORDER BY id DESC
				 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$plate
			)
		);
	}

	/**
	 * Extract the billing details captured from Stripe for pre-filling the
	 * "request invoice" form. Prefers the Stripe billing snapshot stored in
	 * meta.customer at webhook time, falling back to the transaction columns.
	 *
	 * @return array{legal_name: string, email: string, address_street: string, address_zip: string, address_city: string, address_country: string}
	 */
	public static function customer_details( ?object $transaction ): array {
		$details = [
			'legal_name'      => '',
			'email'           => '',
			'address_street'  => '',
			'address_zip'     => '',
			'address_city'    => '',
			'address_country' => '',
		];

		if ( ! $transaction ) {
			return $details;
		}

		$meta     = json_decode( (string) ( $transaction->meta ?? '' ), true );
		$customer = is_array( $meta ) && isset( $meta['customer'] ) && is_array( $meta['customer'] ) ? $meta['customer'] : [];

		$details['legal_name']      = (string) ( $customer['legal_name'] ?? $transaction->customer_name ?? '' );
		$details['email']           = (string) ( $customer['email'] ?? $transaction->customer_email ?? '' );
		$details['address_street']  = (string) ( $customer['address_street'] ?? '' );
		$details['address_zip']     = (string) ( $customer['address_zip'] ?? '' );
		$details['address_city']    = (string) ( $customer['address_city'] ?? '' );
		$details['address_country'] = (string) ( $customer['address_country'] ?? '' );

		return $details;
	}

	/**
	 * Fetch a transaction by ref + receipt token (used for public receipt download).
	 */
	public function find_for_receipt( string $ref, string $token ): ?object {
		global $wpdb;

		$row = $this->find_by_ref( $ref );
		if ( ! $row ) {
			return null;
		}

		return hash_equals( (string) $row->receipt_token, $token ) ? $row : null;
	}

	/**
	 * Paginated + filtered query for the admin transaction log.
	 *
	 * @param array<string, mixed> $args Filter args.
	 * @return array{rows: array<int, object>, total: int}
	 */
	public function query( array $args = [] ): array {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			[
				'status'        => '',
				'license_plate' => '',
				'date_from'     => '',
				'date_to'       => '',
				'per_page'      => 20,
				'page'          => 1,
			]
		);

		$where  = [ '1=1' ];
		$params = [];

		if ( '' !== $args['status'] ) {
			$where[]  = 'payment_status = %s';
			$params[] = $args['status'];
		}
		if ( '' !== $args['license_plate'] ) {
			$where[]  = 'license_plate LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['license_plate'] ) . '%';
		}
		if ( '' !== $args['date_from'] ) {
			$where[]  = 'created_at >= %s';
			$params[] = $args['date_from'] . ' 00:00:00';
		}
		if ( '' !== $args['date_to'] ) {
			$where[]  = 'created_at <= %s';
			$params[] = $args['date_to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );

		// Total count.
		$count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var(
			$params ? $wpdb->prepare( $count_sql, $params ) : $count_sql // phpcs:ignore WordPress.DB.PreparedSQL
		);

		// Page slice.
		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$data_sql      = "SELECT * FROM {$this->table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$data_params   = array_merge( $params, [ $per_page, $offset ] );
		$rows          = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		return [
			'rows'  => $rows ?: [],
			'total' => $total,
		];
	}

	/**
	 * Return transactions whose external-API sync is pending and retryable.
	 *
	 * @return array<int, object>
	 */
	public function pending_api_sync( int $max_attempts, int $limit = 20 ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				 WHERE payment_status = 'paid'
				   AND api_sync_status IN ('pending','failed')
				   AND api_attempts < %d
				 ORDER BY id ASC
				 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$max_attempts,
				$limit
			)
		);
	}

	/**
	 * Dashboard aggregate stats.
	 *
	 * @return array<string, mixed>
	 */
	public function stats(): array {
		global $wpdb;

		$total_paid   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE payment_status = 'paid'" );
		$total_amount = (int) $wpdb->get_var( "SELECT COALESCE(SUM(amount_cents),0) FROM {$this->table} WHERE payment_status = 'paid'" );
		$pending      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE payment_status = 'pending'" );
		$sync_failed  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE api_sync_status = 'failed'" );

		return [
			'paid_count'     => $total_paid,
			'paid_amount'    => $total_amount,
			'pending_count'  => $pending,
			'sync_failed'    => $sync_failed,
		];
	}

	/**
	 * GDPR: collect rows belonging to an email address.
	 *
	 * @return array<int, object>
	 */
	public function find_by_email( string $email ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE customer_email = %s ORDER BY id DESC", $email ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * GDPR: anonymise rows belonging to an email address.
	 */
	public function anonymise_by_email( string $email ): int {
		global $wpdb;

		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table}
				 SET customer_name = NULL, customer_email = NULL, meta = NULL
				 WHERE customer_email = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$email
			)
		);
	}

	/**
	 * Retention cleanup: delete rows older than N days.
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

	/* ------------------------------------------------------------------ */

	private function generate_ref(): string {
		return 'TRX-' . strtoupper( wp_generate_password( 10, false, false ) );
	}

	/**
	 * Build the $wpdb format array (%s/%d) matching a data array.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return array<int, string>
	 */
	private function formats_for( array $data ): array {
		$int_cols = [ 'amount_cents', 'api_attempts' ];
		$formats  = [];

		foreach ( array_keys( $data ) as $col ) {
			$formats[] = in_array( $col, $int_cols, true ) ? '%d' : '%s';
		}

		return $formats;
	}
}
