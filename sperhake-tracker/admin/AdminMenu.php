<?php
/**
 * Builds the "Sperhake Tracker" admin menu and renders its pages.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Admin;

use SperhakeTracker\Admin\Settings\SettingsRegistry;
use SperhakeTracker\Database\SearchLogRepository;
use SperhakeTracker\Database\TransactionRepository;
use SperhakeTracker\Logging\Logger;
use SperhakeTracker\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminMenu {

	private const CAPABILITY = 'manage_options';
	private const SLUG       = 'sperhake-tracker';

	public function __construct(
		private readonly Options $options,
		private readonly TransactionRepository $transactions,
		private readonly Logger $logger,
		private readonly SettingsRegistry $settings,
		private readonly SearchLogRepository $searchLogs
	) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'admin_post_sperhake_export_csv', [ $this, 'export_csv' ] );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'Sperhake Tracker', 'sperhake-tracker' ),
			__( 'Sperhake Tracker', 'sperhake-tracker' ),
			self::CAPABILITY,
			self::SLUG,
			[ $this, 'render_dashboard' ],
			'dashicons-car',
			56
		);

		$submenus = [
			[ self::SLUG, __( 'Dashboard', 'sperhake-tracker' ), [ $this, 'render_dashboard' ] ],
			[ self::SLUG . '-api', __( 'API Settings', 'sperhake-tracker' ), [ $this, 'render_api_settings' ] ],
			[ self::SLUG . '-stripe', __( 'Stripe Settings', 'sperhake-tracker' ), [ $this, 'render_stripe_settings' ] ],
			[ self::SLUG . '-email', __( 'Email Settings', 'sperhake-tracker' ), [ $this, 'render_email_settings' ] ],
			[ self::SLUG . '-transactions', __( 'Transaction Logs', 'sperhake-tracker' ), [ $this, 'render_transactions' ] ],
			[ self::SLUG . '-logs', __( 'System Logs', 'sperhake-tracker' ), [ $this, 'render_logs' ] ],
		];

		foreach ( $submenus as [ $slug, $label, $callback ] ) {
			add_submenu_page( self::SLUG, $label, $label, self::CAPABILITY, $slug, $callback );
		}
	}

	public function enqueue( string $hook ): void {
		if ( ! str_contains( $hook, self::SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'sperhake-admin',
			SPERHAKE_TRACKER_URL . 'assets/css/admin.css',
			[],
			SPERHAKE_TRACKER_VERSION
		);
	}

	/* ==================================================================
	 * Page renderers
	 * ============================================================== */

	public function render_dashboard(): void {
		$this->guard();
		$stats = $this->transactions->stats();
		$this->view( 'dashboard', [ 'stats' => $stats, 'options' => $this->options ] );
	}

	public function render_api_settings(): void {
		$this->guard();
		$this->settings->render_page( __( 'API Settings', 'sperhake-tracker' ), 'sperhake_api_group', 'sperhake_api_page' );
	}

	public function render_stripe_settings(): void {
		$this->guard();
		$this->settings->render_page( __( 'Stripe Settings', 'sperhake-tracker' ), 'sperhake_stripe_group', 'sperhake_stripe_page' );
	}

	public function render_email_settings(): void {
		$this->guard();
		$this->settings->render_page( __( 'Email Settings', 'sperhake-tracker' ), 'sperhake_email_group', 'sperhake_email_page' );
	}

	public function render_transactions(): void {
		$this->guard();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$filters = [
			'status'        => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'license_plate' => isset( $_GET['plate'] ) ? sanitize_text_field( wp_unslash( $_GET['plate'] ) ) : '',
			'date_from'     => isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
			'date_to'       => isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '',
			'page'          => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
			'per_page'      => 20,
		];
		// phpcs:enable

		$result = $this->transactions->query( $filters );

		$this->view(
			'transactions',
			[
				'rows'    => $result['rows'],
				'total'   => $result['total'],
				'filters' => $filters,
			]
		);
	}

	public function render_logs(): void {
		$this->guard();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$level    = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '';
		$rows     = $this->logger->recent( 300, $level );
		$searches = $this->searchLogs->recent( 100 );

		$this->view( 'logs', [ 'rows' => $rows, 'level' => $level, 'searches' => $searches ] );
	}

	/* ==================================================================
	 * CSV export (admin-post action)
	 * ============================================================== */

	public function export_csv(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'sperhake-tracker' ) );
		}
		check_admin_referer( 'sperhake_export_csv' );

		$filters = [
			'status'        => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'license_plate' => isset( $_GET['plate'] ) ? sanitize_text_field( wp_unslash( $_GET['plate'] ) ) : '',
			'date_from'     => isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
			'date_to'       => isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '',
			'per_page'      => 5000,
			'page'          => 1,
		];

		$result = $this->transactions->query( $filters );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sperhake-transactions-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv(
			$out,
			[ 'Transaction ID', 'License Plate', 'Customer', 'Email', 'Amount', 'Currency', 'Payment Status', 'API Status', 'Stripe Intent', 'Created', 'Paid' ]
		);

		foreach ( $result['rows'] as $row ) {
			fputcsv(
				$out,
				[
					$row->transaction_ref,
					$row->license_plate,
					$row->customer_name,
					$row->customer_email,
					number_format( (int) $row->amount_cents / 100, 2, '.', '' ),
					strtoupper( (string) $row->currency ),
					$row->payment_status,
					$row->api_sync_status,
					$row->stripe_payment_intent,
					$row->created_at,
					$row->paid_at,
				]
			);
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/* ================================================================== */

	private function guard(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sperhake-tracker' ) );
		}
	}

	/**
	 * Include an admin view template with extracted data.
	 *
	 * @param array<string, mixed> $data Template variables.
	 */
	private function view( string $name, array $data = [] ): void {
		$file = SPERHAKE_TRACKER_DIR . 'admin/Pages/' . $name . '.php';
		if ( ! is_readable( $file ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'View not found.', 'sperhake-tracker' ) . '</p></div>';

			return;
		}

		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $file;
	}
}
