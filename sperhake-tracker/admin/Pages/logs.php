<?php
/**
 * System logs view.
 *
 * @package SperhakeTracker
 * @var array<int, object> $rows     Log rows.
 * @var string             $level    Active level filter.
 * @var array<int, object> $searches Recent search-audit rows.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SperhakeTracker\Database\SearchLogRepository;

$levels = [
	''        => __( 'All levels', 'sperhake-tracker' ),
	'info'    => __( 'Info', 'sperhake-tracker' ),
	'warning' => __( 'Warning', 'sperhake-tracker' ),
	'error'   => __( 'Error', 'sperhake-tracker' ),
	'debug'   => __( 'Debug', 'sperhake-tracker' ),
];
?>
<div class="wrap sperhake-logs">
	<h1><?php esc_html_e( 'System Logs', 'sperhake-tracker' ); ?></h1>

	<form method="get" class="sperhake-filters">
		<input type="hidden" name="page" value="sperhake-tracker-logs" />
		<select name="level" onchange="this.form.submit()">
			<?php foreach ( $levels as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $level, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th style="width:160px"><?php esc_html_e( 'Time (UTC)', 'sperhake-tracker' ); ?></th>
				<th style="width:90px"><?php esc_html_e( 'Level', 'sperhake-tracker' ); ?></th>
				<th style="width:110px"><?php esc_html_e( 'Channel', 'sperhake-tracker' ); ?></th>
				<th><?php esc_html_e( 'Message', 'sperhake-tracker' ); ?></th>
				<th><?php esc_html_e( 'Context', 'sperhake-tracker' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No log entries.', 'sperhake-tracker' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->created_at ); ?></td>
						<td><span class="sperhake-badge sperhake-badge--<?php echo esc_attr( $row->level ); ?>"><?php echo esc_html( $row->level ); ?></span></td>
						<td><?php echo esc_html( $row->channel ); ?></td>
						<td><?php echo esc_html( $row->message ); ?></td>
						<td><code style="font-size:11px"><?php echo esc_html( (string) $row->context ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<h2 style="margin-top:32px;"><?php esc_html_e( 'Search Audit Log', 'sperhake-tracker' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Recent vehicle searches, for abuse/enumeration detection. IPs are shown for the retained window only.', 'sperhake-tracker' ); ?></p>
	<table class="widefat striped">
		<thead>
			<tr>
				<th style="width:160px"><?php esc_html_e( 'Time (UTC)', 'sperhake-tracker' ); ?></th>
				<th><?php esc_html_e( 'License Plate', 'sperhake-tracker' ); ?></th>
				<th><?php esc_html_e( 'Reference?', 'sperhake-tracker' ); ?></th>
				<th><?php esc_html_e( 'Result', 'sperhake-tracker' ); ?></th>
				<th><?php esc_html_e( 'IP Address', 'sperhake-tracker' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $searches ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No searches recorded.', 'sperhake-tracker' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $searches as $s ) : ?>
					<tr>
						<td><?php echo esc_html( $s->created_at ); ?></td>
						<td><?php echo esc_html( $s->license_plate ); ?></td>
						<td><?php echo $s->reference_provided ? esc_html__( 'Yes', 'sperhake-tracker' ) : '—'; ?></td>
						<td><span class="sperhake-badge sperhake-badge--<?php echo esc_attr( 'found' === $s->result ? 'paid' : ( in_array( $s->result, [ 'blocked', 'captcha_failed', 'error' ], true ) ? 'failed' : 'pending' ) ); ?>"><?php echo esc_html( $s->result ); ?></span></td>
						<td><code><?php echo esc_html( SearchLogRepository::format_ip( $s->ip_address ) ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
