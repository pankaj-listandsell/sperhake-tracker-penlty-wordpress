<?php
/**
 * Transaction logs view (filters, table, pagination, CSV export).
 *
 * @package SperhakeTracker
 * @var array<int, object>      $rows    Transaction rows.
 * @var int                     $total   Total matching rows.
 * @var array<string, mixed>    $filters Active filters.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$per_page    = (int) $filters['per_page'];
$current     = (int) $filters['page'];
$total_pages = max( 1, (int) ceil( $total / $per_page ) );

$statuses = [
	''        => __( 'All statuses', 'sperhake-tracker' ),
	'paid'    => __( 'Paid', 'sperhake-tracker' ),
	'pending' => __( 'Pending', 'sperhake-tracker' ),
	'failed'  => __( 'Failed', 'sperhake-tracker' ),
];

$export_url = wp_nonce_url(
	add_query_arg(
		[
			'action' => 'sperhake_export_csv',
			'status' => $filters['status'],
			'plate'  => $filters['license_plate'],
			'from'   => $filters['date_from'],
			'to'     => $filters['date_to'],
		],
		admin_url( 'admin-post.php' )
	),
	'sperhake_export_csv'
);
?>
<div class="wrap sperhake-transactions">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Transaction Logs', 'sperhake-tracker' ); ?></h1>
	<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'sperhake-tracker' ); ?></a>
	<hr class="wp-header-end">

	<form method="get" class="sperhake-filters">
		<input type="hidden" name="page" value="sperhake-tracker-transactions" />
		<select name="status">
			<?php foreach ( $statuses as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="text" name="plate" value="<?php echo esc_attr( $filters['license_plate'] ); ?>" placeholder="<?php esc_attr_e( 'License plate', 'sperhake-tracker' ); ?>" />
		<input type="date" name="from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
		<input type="date" name="to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
		<?php submit_button( __( 'Filter', 'sperhake-tracker' ), 'secondary', '', false ); ?>
	</form>

	<p class="sperhake-count">
		<?php
		printf(
			/* translators: %s: number of records */
			esc_html__( '%s record(s) found.', 'sperhake-tracker' ),
			esc_html( number_format_i18n( $total ) )
		);
		?>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Transaction ID', 'sperhake-tracker' ); ?></th>
				<th><?php esc_html_e( 'License Plate', 'sperhake-tracker' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'sperhake-tracker' ); ?></th>
				<th><?php esc_html_e( 'Amount', 'sperhake-tracker' ); ?></th>
				<th><?php esc_html_e( 'Payment', 'sperhake-tracker' ); ?></th>
				<th><?php esc_html_e( 'API Sync', 'sperhake-tracker' ); ?></th>
				<th><?php esc_html_e( 'Date', 'sperhake-tracker' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No transactions found.', 'sperhake-tracker' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><code><?php echo esc_html( $row->transaction_ref ); ?></code></td>
						<td><?php echo esc_html( $row->license_plate ); ?></td>
						<td>
							<?php echo esc_html( $row->customer_name ?: '—' ); ?><br />
							<small><?php echo esc_html( $row->customer_email ?: '' ); ?></small>
						</td>
						<td><?php echo esc_html( number_format_i18n( (int) $row->amount_cents / 100, 2 ) . ' ' . strtoupper( (string) $row->currency ) ); ?></td>
						<td><span class="sperhake-badge sperhake-badge--<?php echo esc_attr( $row->payment_status ); ?>"><?php echo esc_html( ucfirst( (string) $row->payment_status ) ); ?></span></td>
						<td><span class="sperhake-badge sperhake-badge--<?php echo esc_attr( $row->api_sync_status ); ?>"><?php echo esc_html( ucfirst( (string) $row->api_sync_status ) ); ?></span></td>
						<td><?php echo esc_html( $row->created_at ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
					[
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $current,
						'total'     => $total_pages,
						'prev_text' => '‹',
						'next_text' => '›',
					]
				)
			);
			?>
		</div></div>
	<?php endif; ?>
</div>
