<?php
/**
 * Google reCAPTCHA v2 ("I'm not a robot") server-side verification.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Security;

use SperhakeTracker\Logging\Logger;
use SperhakeTracker\Support\Options;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Recaptcha {

	private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	public function __construct(
		private readonly Options $options,
		private readonly Logger $logger
	) {}

	/**
	 * Whether reCAPTCHA is fully configured (and therefore enforced).
	 */
	public function is_enabled(): bool {
		return '' !== $this->options->recaptcha_site_key()
			&& '' !== $this->options->recaptcha_secret();
	}

	/**
	 * Verify a reCAPTCHA token. Returns true when verification passes OR when
	 * reCAPTCHA is not enabled (so the feature degrades gracefully).
	 */
	public function verify( string $token, string $ip = '' ): bool {
		if ( ! $this->is_enabled() ) {
			return true;
		}

		if ( '' === $token ) {
			return false;
		}

		$response = wp_remote_post(
			self::VERIFY_URL,
			[
				'timeout' => 10,
				'body'    => [
					'secret'   => $this->options->recaptcha_secret(),
					'response' => $token,
					'remoteip' => $ip,
				],
			]
		);

		if ( $response instanceof WP_Error ) {
			// Fail OPEN on transport errors so a Google outage doesn't block
			// all customers, but record it for monitoring.
			$this->logger->warning( 'recaptcha', 'Verification request failed; allowing through.', [ 'error' => $response->get_error_message() ] );

			return true;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$ok   = is_array( $body ) && ! empty( $body['success'] );

		if ( ! $ok ) {
			$this->logger->info(
				'recaptcha',
				'reCAPTCHA challenge rejected.',
				[ 'errors' => is_array( $body ) ? ( $body['error-codes'] ?? [] ) : [] ]
			);
		}

		return $ok;
	}
}
