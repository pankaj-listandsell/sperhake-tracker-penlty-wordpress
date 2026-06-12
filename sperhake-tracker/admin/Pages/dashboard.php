<?php
/**
 * Admin dashboard view.
 *
 * @package SperhakeTracker
 * @var array<string, mixed>            $stats   Aggregate stats.
 * @var \SperhakeTracker\Support\Options $options Settings accessor.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$paid_total = number_format_i18n( (int) $stats['paid_amount'] / 100, 2 );
$mode       = $options->stripe_mode();
$webhook    = \SperhakeTracker\Payments\WebhookController::webhook_url();
?>
<div class="wrap sperhake-dashboard">
	<h1><?php esc_html_e( 'Sperhake Tracker – Dashboard', 'sperhake-tracker' ); ?></h1>

	<?php if ( '' === $options->api_url() ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'The Vehicle Tracking API URL is not configured yet. Visit API Settings to get started.', 'sperhake-tracker' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( '' === $options->stripe_secret_key() ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Stripe is not configured. Visit Stripe Settings to enable payments.', 'sperhake-tracker' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="sperhake-cards">
		<div class="sperhake-card">
			<span class="sperhake-card__label"><?php esc_html_e( 'Payments Received', 'sperhake-tracker' ); ?></span>
			<span class="sperhake-card__value"><?php echo esc_html( number_format_i18n( (int) $stats['paid_count'] ) ); ?></span>
		</div>
		<div class="sperhake-card">
			<span class="sperhake-card__label"><?php esc_html_e( 'Total Collected (EUR)', 'sperhake-tracker' ); ?></span>
			<span class="sperhake-card__value">€<?php echo esc_html( $paid_total ); ?></span>
		</div>
		<div class="sperhake-card">
			<span class="sperhake-card__label"><?php esc_html_e( 'Pending', 'sperhake-tracker' ); ?></span>
			<span class="sperhake-card__value"><?php echo esc_html( number_format_i18n( (int) $stats['pending_count'] ) ); ?></span>
		</div>
		<div class="sperhake-card <?php echo $stats['sync_failed'] > 0 ? 'is-alert' : ''; ?>">
			<span class="sperhake-card__label"><?php esc_html_e( 'Failed API Syncs', 'sperhake-tracker' ); ?></span>
			<span class="sperhake-card__value"><?php echo esc_html( number_format_i18n( (int) $stats['sync_failed'] ) ); ?></span>
		</div>
	</div>

	<h2><?php esc_html_e( 'Setup Checklist', 'sperhake-tracker' ); ?></h2>
	<table class="widefat striped" style="max-width:900px">
		<tbody>
			<tr>
				<td><strong><?php esc_html_e( 'Stripe mode', 'sperhake-tracker' ); ?></strong></td>
				<td><span class="sperhake-badge sperhake-badge--<?php echo 'live' === $mode ? 'paid' : 'pending'; ?>"><?php echo esc_html( strtoupper( $mode ) ); ?></span></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Stripe webhook endpoint', 'sperhake-tracker' ); ?></strong></td>
				<td><code><?php echo esc_html( $webhook ); ?></code></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Search shortcode', 'sperhake-tracker' ); ?></strong></td>
				<td><code>[sperhake_vehicle_search]</code></td>
			</tr>
		</tbody>
	</table>
</div>
