<?php
/**
 * Server-rendered vehicle result card (returned via AJAX).
 *
 * @package SperhakeTracker
 * @var array<string, mixed> $vehicle          Normalised vehicle data.
 * @var string               $pay_nonce        Nonce for the pay action.
 * @var array<string, string> $invoice_customer Stripe billing details to pre-fill the invoice form.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_penalty   = $vehicle['penalty_cents'] > 0;
$penalty_label = number_format_i18n( $vehicle['penalty_cents'] / 100, 2 ) . ' ' . strtoupper( (string) $vehicle['currency'] );

// Invoice-form pre-fill (from the Stripe billing snapshot). Defensive default
// in case this template is included directly / by a theme override.
$invoice_customer = ( isset( $invoice_customer ) && is_array( $invoice_customer ) ) ? $invoice_customer : [];
$ic = static function ( string $key ) use ( $invoice_customer ): string {
	return isset( $invoice_customer[ $key ] ) ? (string) $invoice_customer[ $key ] : '';
};
// Fall back to the vehicle owner's name when Stripe captured none.
$invoice_name = '' !== $ic( 'legal_name' ) ? $ic( 'legal_name' ) : (string) ( $vehicle['owner_name'] ?? '' );

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
		$row( __( 'Case Number', 'sperhake-tracker' ), (string) ( $vehicle['case_number'] ?? '' ) );
		$row( __( 'Owner', 'sperhake-tracker' ), (string) $vehicle['owner_name'] );
		$row( __( 'Vehicle Brand', 'sperhake-tracker' ), (string) ( $vehicle['vehicle_brand'] ?? '' ) );
		$row( __( 'Partner', 'sperhake-tracker' ), (string) ( $vehicle['partner_name'] ?? '' ) );
		$row( __( 'Towing Vehicle', 'sperhake-tracker' ), (string) ( $vehicle['truck_code'] ?? '' ) );
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
		$dest     = trim( (string) ( $vehicle['destination_address'] ?? '' ) );
		$dest_lat = (string) ( $vehicle['destination_lat'] ?? '' );
		$dest_lng = (string) ( $vehicle['destination_lng'] ?? '' );

		// Origin = where the vehicle was towed from; destination = where it now sits.
		// Prefer exact coordinates for each endpoint, falling back to the address.
		$origin_addr  = trim( (string) ( $vehicle['pickup_address'] ?? '' ) );
		$origin_lat   = (string) ( $vehicle['origin_lat'] ?? '' );
		$origin_lng   = (string) ( $vehicle['origin_lng'] ?? '' );
		$origin_point = ( '' !== $origin_lat && '' !== $origin_lng ) ? $origin_lat . ',' . $origin_lng : $origin_addr;
		$dest_point   = ( '' !== $dest_lat && '' !== $dest_lng ) ? $dest_lat . ',' . $dest_lng : $dest;

		if ( '' !== $dest_point && '' !== $origin_point ) {
			// Plot both pins and the driving route between them.
			$map_url   = 'https://www.google.com/maps/dir/?api=1'
				. '&origin=' . rawurlencode( $origin_point )
				. '&destination=' . rawurlencode( $dest_point );
			$map_label = __( 'View Route on Map', 'sperhake-tracker' );
		} elseif ( '' !== $dest_point ) {
			// Only the destination is known — drop a single pin.
			$map_url   = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $dest_point );
			$map_label = __( 'Track on Map', 'sperhake-tracker' );
		} else {
			$map_url   = '';
			$map_label = '';
		}
		?>
		<div class="sperhake-penalty is-paid">
			<div class="sperhake-penalty__clear"><?php esc_html_e( 'Penalty Paid', 'sperhake-tracker' ); ?></div>
		</div>

		<?php if ( '' !== $dest ) : ?>
			<div class="sperhake-vehicle__destination">
				<?php if ( '' !== $origin_addr ) : ?>
					<h4 class="sperhake-route__title"><?php esc_html_e( 'Relocation Route', 'sperhake-tracker' ); ?></h4>
					<ol class="sperhake-route">
						<li class="sperhake-route__leg sperhake-route__leg--from">
							<span class="sperhake-route__dot" aria-hidden="true"></span>
							<span class="sperhake-route__label"><?php esc_html_e( 'Towed From', 'sperhake-tracker' ); ?></span>
							<span class="sperhake-route__address"><?php echo esc_html( $origin_addr ); ?></span>
						</li>
						<li class="sperhake-route__leg sperhake-route__leg--to">
							<span class="sperhake-route__dot" aria-hidden="true"></span>
							<span class="sperhake-route__label"><?php esc_html_e( 'Relocated To', 'sperhake-tracker' ); ?></span>
							<span class="sperhake-route__address"><?php echo esc_html( $dest ); ?></span>
						</li>
					</ol>
				<?php else : ?>
					<h4><?php esc_html_e( 'Vehicle Relocated To', 'sperhake-tracker' ); ?></h4>
					<p class="sperhake-destination__address"><?php echo esc_html( $dest ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="sperhake-destination__actions">
			<?php if ( '' !== $map_url ) : ?>
				<a class="sperhake-btn sperhake-btn--map" href="<?php echo esc_url( $map_url ); ?>" target="_blank" rel="noopener noreferrer">
					<svg class="sperhake-btn__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
						<path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
						<circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="2"/>
					</svg>
					<?php echo esc_html( $map_label ); ?>
				</a>
			<?php endif; ?>
			<button
				type="button"
				class="sperhake-btn sperhake-btn--invoice"
				id="sperhake-invoice-btn"
				aria-expanded="false"
				aria-controls="sperhake-invoice-form"
			>
				<?php esc_html_e( 'Request Invoice', 'sperhake-tracker' ); ?>
			</button>
		</div>

		<div class="sperhake-invoice" id="sperhake-invoice-form" hidden>
			<h4 class="sperhake-invoice__title"><?php esc_html_e( 'Invoice Details', 'sperhake-tracker' ); ?></h4>
			<p class="sperhake-invoice__intro">
				<?php esc_html_e( 'Please check your details below and change anything that isn\'t right. We\'ll send your invoice to this name and address.', 'sperhake-tracker' ); ?>
			</p>

			<div class="sperhake-invoice__grid">
				<label class="sperhake-invoice__field sperhake-invoice__field--full">
					<span class="sperhake-invoice__field-label"><?php esc_html_e( 'Name', 'sperhake-tracker' ); ?></span>
					<input type="text" id="sperhake-invoice-name" class="sperhake-invoice__input" autocomplete="name"
						value="<?php echo esc_attr( $invoice_name ); ?>" />
				</label>
				<label class="sperhake-invoice__field sperhake-invoice__field--full">
					<span class="sperhake-invoice__field-label"><?php esc_html_e( 'Email', 'sperhake-tracker' ); ?></span>
					<input type="email" id="sperhake-invoice-email" class="sperhake-invoice__input" autocomplete="email"
						value="<?php echo esc_attr( $ic( 'email' ) ); ?>" />
				</label>
				<label class="sperhake-invoice__field sperhake-invoice__field--full">
					<span class="sperhake-invoice__field-label"><?php esc_html_e( 'Street & House Number', 'sperhake-tracker' ); ?></span>
					<input type="text" id="sperhake-invoice-street" class="sperhake-invoice__input" autocomplete="street-address"
						value="<?php echo esc_attr( $ic( 'address_street' ) ); ?>" />
				</label>
				<label class="sperhake-invoice__field">
					<span class="sperhake-invoice__field-label"><?php esc_html_e( 'Postal Code', 'sperhake-tracker' ); ?></span>
					<input type="text" id="sperhake-invoice-zip" class="sperhake-invoice__input" autocomplete="postal-code"
						value="<?php echo esc_attr( $ic( 'address_zip' ) ); ?>" />
				</label>
				<label class="sperhake-invoice__field">
					<span class="sperhake-invoice__field-label"><?php esc_html_e( 'City', 'sperhake-tracker' ); ?></span>
					<input type="text" id="sperhake-invoice-city" class="sperhake-invoice__input" autocomplete="address-level2"
						value="<?php echo esc_attr( $ic( 'address_city' ) ); ?>" />
				</label>
				<label class="sperhake-invoice__field sperhake-invoice__field--full">
					<span class="sperhake-invoice__field-label"><?php esc_html_e( 'Country', 'sperhake-tracker' ); ?></span>
					<input type="text" id="sperhake-invoice-country" class="sperhake-invoice__input" autocomplete="country-name"
						value="<?php echo esc_attr( $ic( 'address_country' ) ); ?>" />
				</label>
			</div>

			<button
				type="button"
				class="sperhake-btn sperhake-btn--map sperhake-invoice__submit"
				id="sperhake-invoice-submit"
				data-case="<?php echo esc_attr( (string) $vehicle['vehicle_id'] ); ?>"
				data-nonce="<?php echo esc_attr( $invoice_nonce ); ?>"
			>
				<?php esc_html_e( 'Send Invoice Request', 'sperhake-tracker' ); ?>
			</button>
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
