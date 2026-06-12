<?php
/**
 * WP-Cron worker: retries failed external-API notifications and applies
 * the data-retention policy.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Cron;

use SperhakeTracker\Api\ExternalApiClient;
use SperhakeTracker\Database\SearchLogRepository;
use SperhakeTracker\Database\TransactionRepository;
use SperhakeTracker\Logging\Logger;
use SperhakeTracker\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RetryQueue {

	public const HOOK        = 'sperhake_retry_api_sync';
	private const MAX_ATTEMPTS = 6;

	public function __construct(
		private readonly ExternalApiClient $externalApi,
		private readonly TransactionRepository $transactions,
		private readonly Options $options,
		private readonly Logger $logger,
		private readonly SearchLogRepository $searchLogs
	) {}

	public function register(): void {
		add_filter( 'cron_schedules', [ $this, 'add_schedule' ] );
		add_action( self::HOOK, [ $this, 'run' ] );
	}

	/**
	 * Register a custom 5-minute schedule.
	 *
	 * @param array<string, array{interval:int, display:string}> $schedules Existing schedules.
	 * @return array<string, array{interval:int, display:string}>
	 */
	public function add_schedule( array $schedules ): array {
		if ( ! isset( $schedules['sperhake_five_minutes'] ) ) {
			$schedules['sperhake_five_minutes'] = [
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 5 minutes (Sperhake)', 'sperhake-tracker' ),
			];
		}

		return $schedules;
	}

	/**
	 * Cron callback: retry pending notifications, then prune old data.
	 */
	public function run(): void {
		$this->retry_pending_syncs();
		$this->apply_retention();
	}

	private function retry_pending_syncs(): void {
		$rows = $this->transactions->pending_api_sync( self::MAX_ATTEMPTS, 25 );

		foreach ( $rows as $transaction ) {
			$result   = $this->externalApi->notify_payment_completed( $transaction );
			$attempts = (int) $transaction->api_attempts + 1;

			$status = $result['ok'] ? 'synced' : ( $attempts >= self::MAX_ATTEMPTS ? 'abandoned' : 'failed' );

			$this->transactions->update(
				(int) $transaction->id,
				[
					'api_sync_status' => $status,
					'api_attempts'    => $attempts,
				]
			);

			if ( 'abandoned' === $status ) {
				$this->logger->error(
					'cron',
					'External API sync abandoned after max attempts.',
					[ 'ref' => $transaction->transaction_ref ]
				);
			}
		}
	}

	private function apply_retention(): void {
		$days = $this->options->retention_days();
		if ( $days <= 0 ) {
			return; // Retention disabled.
		}

		$deleted   = $this->transactions->delete_older_than( $days );
		$pruned    = $this->logger->prune( max( 30, $days ) );
		// Search logs are abuse-detection data; keep a shorter window (max 90 days).
		$searches  = $this->searchLogs->delete_older_than( min( 90, max( 1, $days ) ) );

		if ( $deleted || $pruned || $searches ) {
			$this->logger->info(
				'cron',
				'Retention cleanup completed.',
				[ 'transactions_deleted' => $deleted, 'logs_pruned' => $pruned, 'searches_pruned' => $searches ]
			);
		}
	}
}
