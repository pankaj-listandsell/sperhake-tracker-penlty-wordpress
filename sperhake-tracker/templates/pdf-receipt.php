<?php
/**
 * HTML used by DomPDF to render the PDF receipt.
 *
 * @package SperhakeTracker
 * @var object               $transaction  Paid transaction.
 * @var array<string, mixed> $vehicle      Vehicle data snapshot.
 * @var string               $amount       Formatted amount.
 * @var string               $company_name Company name.
 * @var string               $logo_url     Logo URL (optional).
 * @var string               $paid_date    Localised paid date.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<style>
	* { font-family: "DejaVu Sans", sans-serif; }
	body { color: #1f2937; font-size: 12px; margin: 0; }
	.header { background: #0f2a4a; color: #fff; padding: 24px 32px; }
	.header h1 { margin: 0; font-size: 20px; }
	.header .sub { font-size: 11px; opacity: .85; }
	.content { padding: 28px 32px; }
	.title { font-size: 16px; color: #0f2a4a; margin: 0 0 4px; }
	table.meta { width: 100%; border-collapse: collapse; margin-top: 14px; }
	table.meta td { padding: 7px 0; border-bottom: 1px solid #eef0f3; }
	table.meta td.label { color: #6b7280; width: 45%; }
	table.meta td.value { text-align: right; font-weight: bold; color: #111827; }
	.total { margin-top: 18px; padding: 14px 16px; background: #f3f8ff; border: 1px solid #d7e6fb; border-radius: 6px; }
	.total .amt { font-size: 22px; font-weight: bold; color: #0f2a4a; }
	.footer { margin-top: 26px; font-size: 10px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
	<div class="header">
		<h1><?php echo esc_html( $company_name ); ?></h1>
		<div class="sub"><?php esc_html_e( 'Payment Receipt', 'sperhake-tracker' ); ?></div>
	</div>

	<div class="content">
		<p class="title"><?php esc_html_e( 'Receipt', 'sperhake-tracker' ); ?> #<?php echo esc_html( $transaction->transaction_ref ); ?></p>
		<p style="color:#6b7280;margin:0;"><?php echo esc_html( $paid_date ); ?></p>

		<table class="meta">
			<tr><td class="label"><?php esc_html_e( 'Transaction ID', 'sperhake-tracker' ); ?></td><td class="value"><?php echo esc_html( $transaction->transaction_ref ); ?></td></tr>
			<tr><td class="label"><?php esc_html_e( 'License Plate', 'sperhake-tracker' ); ?></td><td class="value"><?php echo esc_html( $transaction->license_plate ); ?></td></tr>
			<?php if ( ! empty( $vehicle['vehicle_id'] ) ) : ?>
				<tr><td class="label"><?php esc_html_e( 'Vehicle ID', 'sperhake-tracker' ); ?></td><td class="value"><?php echo esc_html( (string) $vehicle['vehicle_id'] ); ?></td></tr>
			<?php endif; ?>
			<?php if ( $transaction->customer_name ) : ?>
				<tr><td class="label"><?php esc_html_e( 'Customer', 'sperhake-tracker' ); ?></td><td class="value"><?php echo esc_html( $transaction->customer_name ); ?></td></tr>
			<?php endif; ?>
			<?php if ( $transaction->customer_email ) : ?>
				<tr><td class="label"><?php esc_html_e( 'Email', 'sperhake-tracker' ); ?></td><td class="value"><?php echo esc_html( $transaction->customer_email ); ?></td></tr>
			<?php endif; ?>
			<?php if ( ! empty( $vehicle['storage_yard_name'] ) ) : ?>
				<tr><td class="label"><?php esc_html_e( 'Storage Yard', 'sperhake-tracker' ); ?></td><td class="value"><?php echo esc_html( (string) $vehicle['storage_yard_name'] ); ?></td></tr>
			<?php endif; ?>
			<tr><td class="label"><?php esc_html_e( 'Payment Status', 'sperhake-tracker' ); ?></td><td class="value"><?php esc_html_e( 'Paid', 'sperhake-tracker' ); ?></td></tr>
		</table>

		<div class="total">
			<?php esc_html_e( 'Amount Paid', 'sperhake-tracker' ); ?>:
			<span class="amt"><?php echo esc_html( $amount ); ?></span>
		</div>

		<div class="footer">
			<?php
			printf(
				/* translators: %s: company name */
				esc_html__( 'This is a computer-generated receipt issued by %s. No signature is required.', 'sperhake-tracker' ),
				esc_html( $company_name )
			);
			?>
		</div>
	</div>
</body>
</html>
