<?php
/**
 * Transactional email sender (HTML template + PDF receipt attachment).
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Emails;

use SperhakeTracker\Logging\Logger;
use SperhakeTracker\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Mailer {

	public function __construct(
		private readonly Options $options,
		private readonly Logger $logger
	) {}

	/**
	 * Send the payment-confirmation email with the receipt attached.
	 *
	 * @param object $transaction Paid transaction row.
	 * @param string $receipt_path Absolute path to the PDF (optional).
	 */
	public function send_receipt( object $transaction, string $receipt_path = '' ): bool {
		$to = (string) $transaction->customer_email;
		if ( ! is_email( $to ) ) {
			$this->logger->warning( 'email', 'No valid recipient for receipt.', [ 'ref' => $transaction->transaction_ref ] );

			return false;
		}

		$from_name  = (string) $this->options->get( Options::EMAIL, 'from_name', get_bloginfo( 'name' ) );
		$from_email = (string) $this->options->get( Options::EMAIL, 'from_email', get_bloginfo( 'admin_email' ) );
		$subject    = (string) $this->options->get(
			Options::EMAIL,
			'subject',
			__( 'Your payment receipt – Abschleppdienst Sperhake', 'sperhake-tracker' )
		);

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		];

		$attachments = [];
		$attach_pdf  = (bool) $this->options->get( Options::EMAIL, 'attach_pdf', 1 );
		if ( $attach_pdf && '' !== $receipt_path && is_readable( $receipt_path ) ) {
			$attachments[] = $receipt_path;
		}

		$body = $this->render_template( $transaction );

		// Briefly force the from-name/address for this send only.
		$name_filter = static fn() => $from_name;
		$mail_filter = static fn() => $from_email;
		add_filter( 'wp_mail_from_name', $name_filter );
		add_filter( 'wp_mail_from', $mail_filter );

		$sent = wp_mail( $to, $subject, $body, $headers, $attachments );

		remove_filter( 'wp_mail_from_name', $name_filter );
		remove_filter( 'wp_mail_from', $mail_filter );

		$this->logger->log(
			$sent ? Logger::INFO : Logger::ERROR,
			'email',
			$sent ? 'Receipt email sent.' : 'Receipt email failed.',
			[ 'ref' => $transaction->transaction_ref, 'to' => $to ]
		);

		return $sent;
	}

	/**
	 * Render the HTML email body.
	 */
	private function render_template( object $transaction ): string {
		$template = SPERHAKE_TRACKER_DIR . 'templates/emails/receipt.php';
		$theme    = locate_template( 'sperhake-tracker/emails/receipt.php' );
		if ( $theme ) {
			$template = $theme;
		}

		$data = [
			'transaction'  => $transaction,
			'amount'       => $this->format_amount( $transaction ),
			'company_name' => __( 'Abschleppdienst Sperhake', 'sperhake-tracker' ),
			'site_url'     => home_url( '/' ),
			'logo_url'     => (string) $this->options->get( Options::EMAIL, 'logo_url', '' ),
			// Signed, 24h download link (the PDF is also attached to this email).
			'receipt_url'  => $transaction->receipt_path ? \SperhakeTracker\Support\ReceiptLink::create( $transaction ) : '',
		];

		if ( ! is_readable( $template ) ) {
			return esc_html__( 'Thank you for your payment.', 'sperhake-tracker' );
		}

		ob_start();
		// Expose $data keys to the template.
		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $template;

		return (string) ob_get_clean();
	}

	private function format_amount( object $transaction ): string {
		$amount = (int) $transaction->amount_cents / 100;

		return number_format_i18n( $amount, 2 ) . ' ' . strtoupper( (string) $transaction->currency );
	}
}
