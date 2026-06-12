<?php
/**
 * Registers all plugin settings via the WordPress Settings API and renders
 * the three settings pages (API, Stripe, Email).
 *
 * Secrets are encrypted on save and masked on display.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker\Admin\Settings;

use SperhakeTracker\Payments\WebhookController;
use SperhakeTracker\Security\Encryption;
use SperhakeTracker\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsRegistry {

	/** Sentinel echoed for secret fields so we can detect "unchanged". */
	private const SECRET_PLACEHOLDER = '__SPERHAKE_UNCHANGED__';

	public function __construct(
		private readonly Options $options,
		private readonly Encryption $encryption
	) {}

	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function register_settings(): void {
		$this->register_api_settings();
		$this->register_stripe_settings();
		$this->register_email_settings();
	}

	/* ==================================================================
	 * API settings
	 * ============================================================== */

	private function register_api_settings(): void {
		register_setting(
			'sperhake_api_group',
			Options::API,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_api' ],
				'default'           => [],
			]
		);

		add_settings_section( 'sperhake_api_main', __( 'Vehicle Tracking API', 'sperhake-tracker' ), '__return_false', 'sperhake_api_page' );

		$this->field( 'sperhake_api_page', 'sperhake_api_main', Options::API, 'api_url', __( 'API URL', 'sperhake-tracker' ), 'url' );
		$this->field( 'sperhake_api_page', 'sperhake_api_main', Options::API, 'api_key', __( 'API Key', 'sperhake-tracker' ), 'secret' );
		$this->field( 'sperhake_api_page', 'sperhake_api_main', Options::API, 'api_secret', __( 'API Secret', 'sperhake-tracker' ), 'secret' );
		$this->field( 'sperhake_api_page', 'sperhake_api_main', Options::API, 'headers', __( 'Custom Request Headers', 'sperhake-tracker' ), 'textarea', __( 'One per line, e.g. X-Tenant: sperhake', 'sperhake-tracker' ) );
		$this->field( 'sperhake_api_page', 'sperhake_api_main', Options::API, 'webhook_url', __( 'Forward Webhook URL', 'sperhake-tracker' ), 'url', __( 'Where completed payments are posted after success.', 'sperhake-tracker' ) );
		$this->field( 'sperhake_api_page', 'sperhake_api_main', Options::API, 'timeout', __( 'Request Timeout (seconds)', 'sperhake-tracker' ), 'number' );

		// --- Frontend security section (anti-bot + anti-enumeration). ---
		add_settings_section( 'sperhake_api_security', __( 'Frontend Search Security', 'sperhake-tracker' ), [ $this, 'security_section_intro' ], 'sperhake_api_page' );

		$this->field( 'sperhake_api_page', 'sperhake_api_security', Options::API, 'turnstile_site_key', __( 'Turnstile Site Key', 'sperhake-tracker' ), 'text', __( 'Cloudflare Turnstile site key. Leave blank to disable the CAPTCHA.', 'sperhake-tracker' ) );
		$this->field( 'sperhake_api_page', 'sperhake_api_security', Options::API, 'turnstile_secret', __( 'Turnstile Secret Key', 'sperhake-tracker' ), 'secret' );
		$this->field( 'sperhake_api_page', 'sperhake_api_security', Options::API, 'require_reference', __( 'Require reference number', 'sperhake-tracker' ), 'checkbox', __( 'Forces a second identifier alongside the plate to prevent enumeration.', 'sperhake-tracker' ) );
		$this->field( 'sperhake_api_page', 'sperhake_api_security', Options::API, 'reference_label', __( 'Reference field label', 'sperhake-tracker' ), 'text', __( 'e.g. "Case Number" or "Reference Number". Sent to the API as "reference".', 'sperhake-tracker' ) );
	}

	public function security_section_intro(): void {
		echo '<p>' . esc_html__( 'Protect the public search form against bots and plate enumeration.', 'sperhake-tracker' ) . '</p>';
	}

	/**
	 * @param array<string, mixed>|null $input Raw submitted values.
	 * @return array<string, mixed>
	 */
	public function sanitize_api( $input ): array {
		$input  = is_array( $input ) ? $input : [];
		$current = $this->options->group( Options::API );

		return [
			'api_url'            => esc_url_raw( trim( (string) ( $input['api_url'] ?? '' ) ) ),
			'webhook_url'        => esc_url_raw( trim( (string) ( $input['webhook_url'] ?? '' ) ) ),
			'timeout'            => max( 5, min( 120, (int) ( $input['timeout'] ?? 20 ) ) ),
			'headers'            => sanitize_textarea_field( (string) ( $input['headers'] ?? '' ) ),
			'api_key'            => $this->keep_or_encrypt( $input['api_key'] ?? '', $current['api_key'] ?? '' ),
			'api_secret'         => $this->keep_or_encrypt( $input['api_secret'] ?? '', $current['api_secret'] ?? '' ),
			'turnstile_site_key' => sanitize_text_field( (string) ( $input['turnstile_site_key'] ?? '' ) ),
			'turnstile_secret'   => $this->keep_or_encrypt( $input['turnstile_secret'] ?? '', $current['turnstile_secret'] ?? '' ),
			'require_reference'  => empty( $input['require_reference'] ) ? 0 : 1,
			'reference_label'    => sanitize_text_field( (string) ( $input['reference_label'] ?? '' ) ),
		];
	}

	/* ==================================================================
	 * Stripe settings
	 * ============================================================== */

	private function register_stripe_settings(): void {
		register_setting(
			'sperhake_stripe_group',
			Options::STRIPE,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_stripe' ],
				'default'           => [],
			]
		);

		add_settings_section( 'sperhake_stripe_main', __( 'Stripe Configuration', 'sperhake-tracker' ), '__return_false', 'sperhake_stripe_page' );

		$this->field( 'sperhake_stripe_page', 'sperhake_stripe_main', Options::STRIPE, 'mode', __( 'Mode', 'sperhake-tracker' ), 'mode' );
		$this->field( 'sperhake_stripe_page', 'sperhake_stripe_main', Options::STRIPE, 'publishable_key', __( 'Publishable Key', 'sperhake-tracker' ), 'text' );
		$this->field( 'sperhake_stripe_page', 'sperhake_stripe_main', Options::STRIPE, 'secret_key', __( 'Secret Key', 'sperhake-tracker' ), 'secret' );
		$this->field( 'sperhake_stripe_page', 'sperhake_stripe_main', Options::STRIPE, 'webhook_secret', __( 'Webhook Signing Secret', 'sperhake-tracker' ), 'secret' );
		$this->field( 'sperhake_stripe_page', 'sperhake_stripe_main', Options::STRIPE, 'currency', __( 'Currency', 'sperhake-tracker' ), 'text' );
		$this->field( 'sperhake_stripe_page', 'sperhake_stripe_main', Options::STRIPE, 'enable_sepa', __( 'Enable SEPA Direct Debit', 'sperhake-tracker' ), 'checkbox' );
		$this->field( 'sperhake_stripe_page', 'sperhake_stripe_main', Options::STRIPE, 'webhook_endpoint', __( 'Your Webhook Endpoint', 'sperhake-tracker' ), 'readonly_webhook' );
	}

	/**
	 * @param array<string, mixed>|null $input Raw submitted values.
	 * @return array<string, mixed>
	 */
	public function sanitize_stripe( $input ): array {
		$input   = is_array( $input ) ? $input : [];
		$current = $this->options->group( Options::STRIPE );

		return [
			'mode'            => 'live' === ( $input['mode'] ?? 'test' ) ? 'live' : 'test',
			'publishable_key' => sanitize_text_field( (string) ( $input['publishable_key'] ?? '' ) ),
			'secret_key'      => $this->keep_or_encrypt( $input['secret_key'] ?? '', $current['secret_key'] ?? '' ),
			'webhook_secret'  => $this->keep_or_encrypt( $input['webhook_secret'] ?? '', $current['webhook_secret'] ?? '' ),
			'currency'        => strtolower( sanitize_text_field( (string) ( $input['currency'] ?? 'eur' ) ) ) ?: 'eur',
			'enable_sepa'     => empty( $input['enable_sepa'] ) ? 0 : 1,
		];
	}

	/* ==================================================================
	 * Email settings
	 * ============================================================== */

	private function register_email_settings(): void {
		register_setting(
			'sperhake_email_group',
			Options::EMAIL,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_email' ],
				'default'           => [],
			]
		);

		add_settings_section( 'sperhake_email_main', __( 'Customer Email', 'sperhake-tracker' ), '__return_false', 'sperhake_email_page' );

		$this->field( 'sperhake_email_page', 'sperhake_email_main', Options::EMAIL, 'from_name', __( 'From Name', 'sperhake-tracker' ), 'text' );
		$this->field( 'sperhake_email_page', 'sperhake_email_main', Options::EMAIL, 'from_email', __( 'From Email', 'sperhake-tracker' ), 'email' );
		$this->field( 'sperhake_email_page', 'sperhake_email_main', Options::EMAIL, 'subject', __( 'Email Subject', 'sperhake-tracker' ), 'text' );
		$this->field( 'sperhake_email_page', 'sperhake_email_main', Options::EMAIL, 'logo_url', __( 'Logo URL', 'sperhake-tracker' ), 'url' );
		$this->field( 'sperhake_email_page', 'sperhake_email_main', Options::EMAIL, 'attach_pdf', __( 'Attach PDF receipt', 'sperhake-tracker' ), 'checkbox' );
	}

	/**
	 * @param array<string, mixed>|null $input Raw submitted values.
	 * @return array<string, mixed>
	 */
	public function sanitize_email( $input ): array {
		$input = is_array( $input ) ? $input : [];

		return [
			'from_name'  => sanitize_text_field( (string) ( $input['from_name'] ?? '' ) ),
			'from_email' => sanitize_email( (string) ( $input['from_email'] ?? '' ) ),
			'subject'    => sanitize_text_field( (string) ( $input['subject'] ?? '' ) ),
			'logo_url'   => esc_url_raw( (string) ( $input['logo_url'] ?? '' ) ),
			'attach_pdf' => empty( $input['attach_pdf'] ) ? 0 : 1,
		];
	}

	/* ==================================================================
	 * Rendering helpers
	 * ============================================================== */

	/**
	 * Register a single settings field with its render callback.
	 */
	private function field( string $page, string $section, string $group, string $key, string $label, string $type, string $help = '' ): void {
		add_settings_field(
			$group . '_' . $key,
			$label,
			[ $this, 'render_field' ],
			$page,
			$section,
			[
				'group' => $group,
				'key'   => $key,
				'type'  => $type,
				'help'  => $help,
				'label_for' => $group . '_' . $key,
			]
		);
	}

	/**
	 * Render a field. Secrets are shown masked; an empty submission keeps them.
	 *
	 * @param array<string, mixed> $args Field args.
	 */
	public function render_field( array $args ): void {
		$group = (string) $args['group'];
		$key   = (string) $args['key'];
		$type  = (string) $args['type'];
		$name  = sprintf( '%s[%s]', $group, $key );
		$id    = $group . '_' . $key;
		$raw   = $this->options->group( $group );
		$value = $raw[ $key ] ?? '';

		switch ( $type ) {
			case 'secret':
				$decrypted = $this->options->get( $group, $key, '' );
				$display   = '' !== $decrypted ? Encryption::mask( (string) $decrypted ) : '';
				printf(
					'<input type="password" id="%1$s" name="%2$s" value="" autocomplete="new-password" class="regular-text" placeholder="%3$s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					$display ? esc_attr( $display ) : esc_attr__( 'Not set', 'sperhake-tracker' )
				);
				echo '<p class="description">' . esc_html__( 'Stored encrypted. Leave blank to keep the current value.', 'sperhake-tracker' ) . '</p>';
				break;

			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="4" class="large-text code">%3$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_textarea( (string) $value )
				);
				break;

			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( ! empty( $value ), true, false ),
					esc_html__( 'Enabled', 'sperhake-tracker' )
				);
				break;

			case 'mode':
				$value = $value ?: 'test';
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( [ 'test' => __( 'Test', 'sperhake-tracker' ), 'live' => __( 'Live', 'sperhake-tracker' ) ] as $opt => $opt_label ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $opt_label ) );
				}
				echo '</select>';
				break;

			case 'readonly_webhook':
				printf(
					'<input type="text" readonly class="large-text code" onclick="this.select()" value="%s" />',
					esc_attr( WebhookController::webhook_url() )
				);
				echo '<p class="description">' . esc_html__( 'Add this URL as a Stripe webhook endpoint and subscribe to checkout.session.completed.', 'sperhake-tracker' ) . '</p>';
				break;

			case 'number':
				printf(
					'<input type="number" min="5" max="120" id="%1$s" name="%2$s" value="%3$s" class="small-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) ( $value ?: 20 ) )
				);
				break;

			default: // text, url, email.
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text" />',
					esc_attr( in_array( $type, [ 'url', 'email' ], true ) ? $type : 'text' ),
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
		}

		if ( '' !== (string) $args['help'] ) {
			echo '<p class="description">' . esc_html( (string) $args['help'] ) . '</p>';
		}
	}

	/**
	 * Render a complete settings page for a given option group + page slug.
	 */
	public function render_page( string $title, string $settings_group, string $page_slug ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap sperhake-settings">
			<h1><?php echo esc_html( $title ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( $settings_group );
				do_settings_sections( $page_slug );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Decide whether to encrypt a freshly submitted secret or keep the stored one.
	 */
	private function keep_or_encrypt( mixed $submitted, mixed $stored ): string {
		$submitted = trim( (string) $submitted );

		// Empty submission => keep whatever is already stored (already encrypted).
		if ( '' === $submitted ) {
			return (string) $stored;
		}

		return $this->encryption->encrypt( $submitted );
	}
}
