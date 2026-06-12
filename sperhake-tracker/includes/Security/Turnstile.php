<?php
/**
 * Cloudflare Turnstile server-side verification.
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

final class Turnstile {

	private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	public function __construct(
		private readonly Options $options,
		private readonly Logger $logger
	) {}

	/**
	 * Whether Turnstile is fully configured (and therefore enforced).
	 */
	public function is_enabled(): bool {
		return '' !== $this->options->turnstile_site_key()
			&& '' !== $this->options->turnstile_secret();
	}

	/**
	 * Verify a Turnstile token. Returns true when verification passes OR when
	 * Turnstile is not enabled (so the feature degrades gracefully).
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
					'secret'   => $this->options->turnstile_secret(),
					'response' => $token,
					'remoteip' => $ip,
				],
			]
		);

		if ( $response instanceof WP_Error ) {
			// Fail OPEN on transport errors so a Cloudflare outage doesn't block
			// all customers, but record it for monitoring.
			$this->logger->warning( 'turnstile', 'Verification request failed; allowing through.', [ 'error' => $response->get_error_message() ] );

			return true;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$ok   = is_array( $body ) && ! empty( $body['success'] );

		if ( ! $ok ) {
			$this->logger->info(
				'turnstile',
				'Turnstile challenge rejected.',
				[ 'errors' => is_array( $body ) ? ( $body['error-codes'] ?? [] ) : [] ]
			);
		}

		return $ok;
	}
}
