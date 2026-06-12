<?php
/**
 * Post-payment confirmation panel.
 *
 * @package SperhakeTracker
 * @var object $transaction Paid transaction row.
 * @var string $receipt_url Signed receipt download URL ('' if not ready).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_paid = 'paid' === $transaction->payment_status;
$amount  = number_format_i18n( (int) $transaction->amount_cents / 100, 2 ) . ' ' . strtoupper( (string) $transaction->currency );
$date    = mysql2date( get_option( 'date_format' ), (string) ( $transaction->paid_at ?: $transaction->created_at ) );
?>
<div class="sperhake-success" id="sperhake-receipt">
	<?php if ( $is_paid ) : ?>
		<div class="sperhake-success__icon" aria-hidden="true">✓</div>
		<h2 class="sperhake-success__title"><?php esc_html_e( 'Payment Successful', 'sperhake-tracker' ); ?></h2>
		<div class="sperhake-success__details">
			<div class="sperhake-detail"><span class="sperhake-detail__label"><?php esc_html_e( 'Transaction ID', 'sperhake-tracker' ); ?></span><span class="sperhake-detail__value"><?php echo esc_html( $transaction->transaction_ref ); ?></span></div>
			<?php if ( $transaction->customer_name ) : ?>
				<div class="sperhake-detail"><span class="sperhake-detail__label"><?php esc_html_e( 'Customer', 'sperhake-tracker' ); ?></span><span class="sperhake-detail__value"><?php echo esc_html( $transaction->customer_name ); ?></span></div>
			<?php endif; ?>
			<div class="sperhake-detail"><span class="sperhake-detail__label"><?php esc_html_e( 'License Plate', 'sperhake-tracker' ); ?></span><span class="sperhake-detail__value"><?php echo esc_html( $transaction->license_plate ); ?></span></div>
			<div class="sperhake-detail"><span class="sperhake-detail__label"><?php esc_html_e( 'Amount Paid', 'sperhake-tracker' ); ?></span><span class="sperhake-detail__value"><?php echo esc_html( $amount ); ?></span></div>
			<div class="sperhake-detail"><span class="sperhake-detail__label"><?php esc_html_e( 'Date', 'sperhake-tracker' ); ?></span><span class="sperhake-detail__value"><?php echo esc_html( $date ); ?></span></div>
			<div class="sperhake-detail"><span class="sperhake-detail__label"><?php esc_html_e( 'Status', 'sperhake-tracker' ); ?></span><span class="sperhake-detail__value"><span class="sperhake-badge sperhake-badge--paid"><?php esc_html_e( 'Paid', 'sperhake-tracker' ); ?></span></span></div>
		</div>
		<?php if ( '' !== $receipt_url ) : ?>
			<a class="sperhake-btn sperhake-btn--primary" href="<?php echo esc_url( $receipt_url ); ?>"><?php esc_html_e( 'Download Receipt', 'sperhake-tracker' ); ?></a>
		<?php else : ?>
			<p class="sperhake-success__pending"><?php esc_html_e( 'Your receipt is being generated and has been emailed to you.', 'sperhake-tracker' ); ?></p>
		<?php endif; ?>
	<?php else : ?>
		<h2 class="sperhake-success__title"><?php esc_html_e( 'Payment is being processed', 'sperhake-tracker' ); ?></h2>
		<p><?php esc_html_e( 'We have received your request and will confirm shortly. You will receive an email once the payment is complete.', 'sperhake-tracker' ); ?></p>
	<?php endif; ?>
</div>
