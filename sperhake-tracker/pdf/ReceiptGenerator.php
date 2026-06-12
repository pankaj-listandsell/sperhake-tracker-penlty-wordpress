<?php
/**
 * Generates and stores PDF receipts using DomPDF.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use SperhakeTracker\Logging\Logger;
use SperhakeTracker\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReceiptGenerator {

	public function __construct(
		private readonly Options $options,
		private readonly Logger $logger
	) {}

	/**
	 * Build a PDF for a paid transaction and store it securely.
	 *
	 * @param object $transaction Paid transaction row.
	 * @return string Absolute path to the stored PDF, or '' on failure.
	 */
	public function generate( object $transaction ): string {
		if ( ! class_exists( Dompdf::class ) ) {
			$this->logger->error( 'pdf', 'DomPDF is not installed (run composer install).' );

			return '';
		}

		try {
			$html = $this->render_html( $transaction );

			$dompdf_options = new DompdfOptions();
			$dompdf_options->set( 'isRemoteEnabled', true );
			$dompdf_options->set( 'defaultFont', 'DejaVu Sans' );

			$dompdf = new Dompdf( $dompdf_options );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();

			$output = $dompdf->output();

			return $this->store( $transaction, (string) $output );
		} catch ( \Throwable $e ) {
			$this->logger->error(
				'pdf',
				'Receipt generation failed.',
				[ 'ref' => $transaction->transaction_ref, 'error' => $e->getMessage() ]
			);

			return '';
		}
	}

	/**
	 * Write the PDF bytes into the protected uploads sub-directory.
	 */
	private function store( object $transaction, string $bytes ): string {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'sperhake-receipts';

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$filename = sprintf(
			'receipt-%s-%s.pdf',
			sanitize_file_name( $transaction->transaction_ref ),
			substr( md5( $transaction->receipt_token . $transaction->id ), 0, 8 )
		);

		$path = trailingslashit( $dir ) . $filename;

		// Use the WP_Filesystem API for portability.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		global $wp_filesystem;
		WP_Filesystem();

		if ( $wp_filesystem && $wp_filesystem->put_contents( $path, $bytes, FS_CHMOD_FILE ) ) {
			$this->logger->info( 'pdf', 'Receipt stored.', [ 'ref' => $transaction->transaction_ref, 'file' => $filename ] );

			// Store only the basename; AjaxHandler resolves it back to the secure dir.
			return $path;
		}

		$this->logger->error( 'pdf', 'Failed to write receipt file.', [ 'ref' => $transaction->transaction_ref ] );

		return '';
	}

	/**
	 * Render the receipt HTML.
	 */
	private function render_html( object $transaction ): string {
		$template = SPERHAKE_TRACKER_DIR . 'templates/pdf-receipt.php';
		$theme    = locate_template( 'sperhake-tracker/pdf-receipt.php' );
		if ( $theme ) {
			$template = $theme;
		}

		$meta    = json_decode( (string) $transaction->meta, true );
		$vehicle = is_array( $meta ) && isset( $meta['vehicle'] ) ? $meta['vehicle'] : [];

		$data = [
			'transaction'  => $transaction,
			'vehicle'      => $vehicle,
			'amount'       => number_format( (int) $transaction->amount_cents / 100, 2, '.', ',' ) . ' ' . strtoupper( (string) $transaction->currency ),
			'company_name' => __( 'Abschleppdienst Sperhake', 'sperhake-tracker' ),
			'logo_url'     => (string) $this->options->get( Options::EMAIL, 'logo_url', '' ),
			'paid_date'    => mysql2date( get_option( 'date_format' ), (string) ( $transaction->paid_at ?: $transaction->created_at ) ),
		];

		if ( ! is_readable( $template ) ) {
			return '<h1>' . esc_html__( 'Payment Receipt', 'sperhake-tracker' ) . '</h1>';
		}

		ob_start();
		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $template;

		return (string) ob_get_clean();
	}
}
