<?php
/**
 * GDPR integration: privacy policy text, data export and erasure handlers.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\GDPR;

use SperhakeTracker\Database\TransactionRepository;
use SperhakeTracker\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PrivacyManager {

	public function __construct(
		private readonly TransactionRepository $transactions,
		private readonly Options $options
	) {}

	public function register(): void {
		add_action( 'admin_init', [ $this, 'add_privacy_policy_content' ] );
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_eraser' ] );
	}

	/**
	 * Suggest privacy-policy text for the WordPress privacy guide.
	 */
	public function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<p>%s</p>',
			esc_html__(
				'When you search for a towed vehicle or pay an outstanding penalty, Abschleppdienst Sperhake processes your license plate, name, email address and payment details. Payment card data is handled exclusively by Stripe and is never stored on our servers. Transaction records are retained for the period configured by the operator and are then automatically deleted.',
				'sperhake-tracker'
			)
		);

		wp_add_privacy_policy_content( 'Sperhake Vehicle Tracker', wp_kses_post( $content ) );
	}

	/**
	 * Register the personal-data exporter.
	 *
	 * @param array<string, mixed> $exporters Existing exporters.
	 * @return array<string, mixed>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['sperhake-tracker'] = [
			'exporter_friendly_name' => __( 'Sperhake Vehicle Tracker', 'sperhake-tracker' ),
			'callback'               => [ $this, 'export_data' ],
		];

		return $exporters;
	}

	/**
	 * Register the personal-data eraser.
	 *
	 * @param array<string, mixed> $erasers Existing erasers.
	 * @return array<string, mixed>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['sperhake-tracker'] = [
			'eraser_friendly_name' => __( 'Sperhake Vehicle Tracker', 'sperhake-tracker' ),
			'callback'             => [ $this, 'erase_data' ],
		];

		return $erasers;
	}

	/**
	 * Export all transactions tied to an email address.
	 *
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export_data( string $email, int $page = 1 ): array {
		$items = [];
		$rows  = $this->transactions->find_by_email( $email );

		foreach ( $rows as $row ) {
			$items[] = [
				'group_id'    => 'sperhake_transactions',
				'group_label' => __( 'Towing penalty payments', 'sperhake-tracker' ),
				'item_id'     => 'transaction-' . $row->id,
				'data'        => [
					[ 'name' => __( 'Transaction ID', 'sperhake-tracker' ), 'value' => $row->transaction_ref ],
					[ 'name' => __( 'License Plate', 'sperhake-tracker' ), 'value' => $row->license_plate ],
					[ 'name' => __( 'Customer Name', 'sperhake-tracker' ), 'value' => $row->customer_name ],
					[ 'name' => __( 'Email', 'sperhake-tracker' ), 'value' => $row->customer_email ],
					[ 'name' => __( 'Amount', 'sperhake-tracker' ), 'value' => number_format( (int) $row->amount_cents / 100, 2 ) . ' ' . strtoupper( (string) $row->currency ) ],
					[ 'name' => __( 'Status', 'sperhake-tracker' ), 'value' => $row->payment_status ],
					[ 'name' => __( 'Date', 'sperhake-tracker' ), 'value' => $row->created_at ],
				],
			];
		}

		return [
			'data' => $items,
			'done' => true,
		];
	}

	/**
	 * Anonymise transactions tied to an email address.
	 *
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public function erase_data( string $email, int $page = 1 ): array {
		$count = $this->transactions->anonymise_by_email( $email );

		return [
			'items_removed'  => $count > 0,
			'items_retained' => false,
			'messages'       => $count > 0
				? [ __( 'Personal data in towing penalty records was anonymised. Financial fields are retained for legal/accounting obligations.', 'sperhake-tracker' ) ]
				: [],
			'done'           => true,
		];
	}
}
