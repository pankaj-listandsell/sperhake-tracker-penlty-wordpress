<?php
/**
 * HTML email body for the payment receipt.
 *
 * @package SperhakeTracker
 * @var object $transaction  Paid transaction.
 * @var string $amount       Formatted amount.
 * @var string $company_name Company name.
 * @var string $site_url     Home URL.
 * @var string $logo_url     Logo URL (optional).
 * @var string $receipt_url  Signed 24h download link (optional).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$receipt_url = isset( $receipt_url ) ? (string) $receipt_url : '';
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 0;">
		<tr><td align="center">
			<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;overflow:hidden;max-width:600px;width:100%;">
				<tr>
					<td style="background:#0f2a4a;padding:24px 32px;text-align:center;">
						<?php if ( '' !== $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $company_name ); ?>" style="max-height:48px;" />
						<?php else : ?>
							<span style="color:#ffffff;font-size:20px;font-weight:bold;"><?php echo esc_html( $company_name ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr><td style="padding:32px;">
					<h1 style="margin:0 0 8px;font-size:22px;color:#0f2a4a;"><?php esc_html_e( 'Thank you for your payment', 'sperhake-tracker' ); ?></h1>
					<p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#4b5563;">
						<?php esc_html_e( 'We have received your penalty payment. A copy of your receipt is attached to this email for your records.', 'sperhake-tracker' ); ?>
					</p>

					<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
						<?php
						$rows = [
							__( 'Transaction ID', 'sperhake-tracker' ) => $transaction->transaction_ref,
							__( 'License Plate', 'sperhake-tracker' )  => $transaction->license_plate,
							__( 'Amount Paid', 'sperhake-tracker' )    => $amount,
							__( 'Date', 'sperhake-tracker' )           => mysql2date( get_option( 'date_format' ), (string) ( $transaction->paid_at ?: $transaction->created_at ) ),
							__( 'Status', 'sperhake-tracker' )         => __( 'Paid', 'sperhake-tracker' ),
						];
						foreach ( $rows as $label => $value ) :
							?>
							<tr>
								<td style="padding:10px 0;border-bottom:1px solid #eef0f3;font-size:14px;color:#6b7280;"><?php echo esc_html( $label ); ?></td>
								<td style="padding:10px 0;border-bottom:1px solid #eef0f3;font-size:14px;font-weight:bold;text-align:right;color:#111827;"><?php echo esc_html( (string) $value ); ?></td>
							</tr>
						<?php endforeach; ?>
					</table>

					<?php if ( '' !== $receipt_url ) : ?>
						<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 0;">
							<tr><td style="border-radius:8px;background:#1d6fe0;">
								<a href="<?php echo esc_url( $receipt_url ); ?>" style="display:inline-block;padding:12px 22px;color:#ffffff;font-size:14px;font-weight:bold;text-decoration:none;border-radius:8px;">
									<?php esc_html_e( 'Download Receipt', 'sperhake-tracker' ); ?>
								</a>
							</td></tr>
						</table>
						<p style="margin:8px 0 0;font-size:12px;color:#9ca3af;"><?php esc_html_e( 'This download link is valid for 24 hours. The receipt is also attached as a PDF.', 'sperhake-tracker' ); ?></p>
					<?php endif; ?>

					<p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#9ca3af;">
						<?php
						printf(
							/* translators: %s: company name */
							esc_html__( 'If you have any questions about this payment, please contact %s.', 'sperhake-tracker' ),
							esc_html( $company_name )
						);
						?>
					</p>
				</td></tr>
				<tr><td style="background:#f9fafb;padding:16px 32px;text-align:center;font-size:12px;color:#9ca3af;">
					<?php echo esc_html( $company_name ); ?> · <a href="<?php echo esc_url( $site_url ); ?>" style="color:#0f2a4a;"><?php echo esc_html( wp_parse_url( $site_url, PHP_URL_HOST ) ); ?></a>
				</td></tr>
			</table>
		</td></tr>
	</table>
</body>
</html>
