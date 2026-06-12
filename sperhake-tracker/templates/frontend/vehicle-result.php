<?php
/**
 * Server-rendered vehicle result card (returned via AJAX).
 *
 * @package SperhakeTracker
 * @var array<string, mixed> $vehicle   Normalised vehicle data.
 * @var string               $pay_nonce Nonce for the pay action.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_penalty   = $vehicle['penalty_cents'] > 0;
$penalty_label = number_format_i18n( $vehicle['penalty_cents'] / 100, 2 ) . ' ' . strtoupper( (string) $vehicle['currency'] );

/**
 * Render a labelled row if the value is non-empty.
 */
$row = static function ( string $label, string $value ): void {
	if ( '' === trim( $value ) ) {
		return;
	}
	printf(
		'<div class="sperhake-detail"><span class="sperhake-detail__label">%s</span><span class="sperhake-detail__value">%s</span></div>',
		esc_html( $label ),
		esc_html( $value )
	);
};
?>
<div class="sperhake-vehicle">
	<div class="sperhake-vehicle__head">
		<span class="sperhake-vehicle__found"><?php esc_html_e( 'Vehicle Found', 'sperhake-tracker' ); ?></span>
		<span class="sperhake-vehicle__plate"><?php echo esc_html( $vehicle['license_plate'] ); ?></span>
		<span class="sperhake-badge sperhake-badge--status"><?php echo esc_html( ucfirst( (string) $vehicle['status'] ) ); ?></span>
	</div>

	<div class="sperhake-vehicle__grid">
		<?php
		$row( __( 'Owner', 'sperhake-tracker' ), (string) $vehicle['owner_name'] );
		$row( __( 'Status', 'sperhake-tracker' ), ucfirst( (string) $vehicle['status'] ) );
		$row( __( 'Towed Date', 'sperhake-tracker' ), (string) $vehicle['towed_date'] );
		$row( __( 'Towed Time', 'sperhake-tracker' ), (string) $vehicle['towed_time'] );
		$row( __( 'Towed From', 'sperhake-tracker' ), (string) $vehicle['pickup_address'] );
		$row( __( 'Towing Location', 'sperhake-tracker' ), (string) $vehicle['towing_location'] );
		$row( __( 'Storage Yard', 'sperhake-tracker' ), (string) $vehicle['storage_yard_name'] );
		$row( __( 'Storage Address', 'sperhake-tracker' ), (string) $vehicle['storage_yard_address'] );
		$row( __( 'Current Location', 'sperhake-tracker' ), (string) $vehicle['current_location'] );
		$row( __( 'Contact', 'sperhake-tracker' ), (string) $vehicle['contact_number'] );
		?>
	</div>

	<?php if ( '' !== trim( (string) $vehicle['release_instructions'] ) ) : ?>
		<div class="sperhake-vehicle__instructions">
			<h4><?php esc_html_e( 'Vehicle Release Instructions', 'sperhake-tracker' ); ?></h4>
			<p><?php echo esc_html( (string) $vehicle['release_instructions'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	$is_paid = ! empty( $vehicle['is_paid'] );

	if ( $is_paid ) :
		// Paid case: show the relocation destination plus map & invoice actions.
		$dest      = trim( (string) ( $vehicle['destination_address'] ?? '' ) );
		$dest_lat  = (string) ( $vehicle['destination_lat'] ?? '' );
		$dest_lng  = (string) ( $vehicle['destination_lng'] ?? '' );
		$map_query = ( '' !== $dest_lat && '' !== $dest_lng ) ? $dest_lat . ',' . $dest_lng : $dest;
		$map_url   = '' !== $map_query ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $map_query ) : '';
		?>
		<div class="sperhake-penalty is-paid">
			<div class="sperhake-penalty__clear"><?php esc_html_e( 'Penalty Paid', 'sperhake-tracker' ); ?></div>
		</div>

		<?php if ( '' !== $dest ) : ?>
			<div class="sperhake-vehicle__destination">
				<h4><?php esc_html_e( 'Vehicle Relocated To', 'sperhake-tracker' ); ?></h4>
				<p class="sperhake-destination__address"><?php echo esc_html( $dest ); ?></p>
			</div>
		<?php endif; ?>

		<div class="sperhake-destination__actions">
			<?php if ( '' !== $map_url ) : ?>
				<a class="sperhake-btn sperhake-btn--map" href="<?php echo esc_url( $map_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Track on Map', 'sperhake-tracker' ); ?>
				</a>
			<?php endif; ?>
			<button
				type="button"
				class="sperhake-btn sperhake-btn--invoice"
				id="sperhake-invoice-btn"
				data-case="<?php echo esc_attr( (string) $vehicle['vehicle_id'] ); ?>"
				data-nonce="<?php echo esc_attr( $invoice_nonce ); ?>"
			>
				<?php esc_html_e( 'Request Invoice', 'sperhake-tracker' ); ?>
			</button>
		</div>

		<div class="sperhake-invoice">
			<input
				type="email"
				class="sperhake-invoice__email"
				id="sperhake-invoice-email"
				placeholder="<?php esc_attr_e( 'Send invoice to a different email (optional)', 'sperhake-tracker' ); ?>"
			/>
			<p class="sperhake-invoice__msg" id="sperhake-invoice-msg" hidden></p>
		</div>
	<?php else : ?>
		<div class="sperhake-penalty <?php echo $has_penalty ? 'is-due' : 'is-clear'; ?>">
			<?php if ( $has_penalty ) : ?>
				<div class="sperhake-penalty__amount">
					<span class="sperhake-penalty__label"><?php esc_html_e( 'Outstanding Penalty', 'sperhake-tracker' ); ?></span>
					<span class="sperhake-penalty__value">€<?php echo esc_html( number_format_i18n( $vehicle['penalty_cents'] / 100, 2 ) ); ?></span>
				</div>
				<button
					type="button"
					class="sperhake-btn sperhake-btn--pay"
					id="sperhake-pay-btn"
					data-plate="<?php echo esc_attr( $vehicle['license_plate'] ); ?>"
					data-reference="<?php echo esc_attr( (string) ( $vehicle['reference'] ?? '' ) ); ?>"
					data-nonce="<?php echo esc_attr( $pay_nonce ); ?>"
				>
					<?php
					printf(
						/* translators: %s: formatted amount */
						esc_html__( 'Pay Now – %s', 'sperhake-tracker' ),
						esc_html( $penalty_label )
					);
					?>
				</button>
				<p class="sperhake-penalty__note"><?php esc_html_e( 'Secure payment powered by Stripe. Cards, Apple Pay & Google Pay accepted.', 'sperhake-tracker' ); ?></p>
			<?php else : ?>
				<div class="sperhake-penalty__clear">
					<?php esc_html_e( 'No Outstanding Penalties', 'sperhake-tracker' ); ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
