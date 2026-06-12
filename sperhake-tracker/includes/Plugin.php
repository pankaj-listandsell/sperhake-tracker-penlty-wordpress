<?php
/**
 * Main plugin container & bootstrap.
 *
 * @package SperhakeTracker
 */

declare(strict_types=1);

namespace SperhakeTracker;

use SperhakeTracker\Admin\AdminMenu;
use SperhakeTracker\Admin\Settings\SettingsRegistry;
use SperhakeTracker\Api\ExternalApiClient;
use SperhakeTracker\Api\VehicleApiClient;
use SperhakeTracker\Cron\RetryQueue;
use SperhakeTracker\Database\SearchLogRepository;
use SperhakeTracker\Database\Schema;
use SperhakeTracker\Database\TransactionRepository;
use SperhakeTracker\Emails\Mailer;
use SperhakeTracker\Frontend\AjaxHandler;
use SperhakeTracker\Frontend\Assets;
use SperhakeTracker\Frontend\Shortcode;
use SperhakeTracker\GDPR\PrivacyManager;
use SperhakeTracker\Logging\Logger;
use SperhakeTracker\Payments\StripeGateway;
use SperhakeTracker\Payments\WebhookController;
use SperhakeTracker\Pdf\ReceiptGenerator;
use SperhakeTracker\Security\Encryption;
use SperhakeTracker\Security\Turnstile;
use SperhakeTracker\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?Plugin $instance = null;

	/**
	 * Simple service registry (lazy singletons).
	 *
	 * @var array<string, object>
	 */
	private array $services = [];

	private bool $booted = false;

	private function __construct() {}

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Wire and register every component with WordPress.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->register_core();

		// Run any pending schema upgrade (e.g. new search-logs table).
		Schema::maybe_upgrade();

		// Internationalisation. German is the default UI language regardless of
		// the site locale; override via the 'sperhake_tracker_locale' filter.
		$plugin_locale = static function ( string $locale, string $domain ): string {
			if ( 'sperhake-tracker' !== $domain ) {
				return $locale;
			}

			return (string) apply_filters( 'sperhake_tracker_locale', 'de_DE', $locale );
		};
		add_filter( 'plugin_locale', $plugin_locale, 10, 2 );

		// Force our bundled translation file whenever the domain is loaded — this
		// also covers WordPress 6.7+ just-in-time loading on the frontend, which
		// uses the site locale (e.g. en_US) and ignores the 'plugin_locale' filter.
		add_filter(
			'load_textdomain_mofile',
			static function ( string $mofile, string $domain ) use ( $plugin_locale ): string {
				if ( 'sperhake-tracker' !== $domain ) {
					return $mofile;
				}

				$locale = $plugin_locale( '', $domain );
				$forced = SPERHAKE_TRACKER_DIR . 'languages/sperhake-tracker-' . $locale . '.mo';

				return is_readable( $forced ) ? $forced : $mofile;
			},
			10,
			2
		);

		add_action(
			'init',
			static function (): void {
				load_plugin_textdomain( 'sperhake-tracker', false, dirname( SPERHAKE_TRACKER_BASENAME ) . '/languages' );
			}
		);

		if ( is_admin() ) {
			$this->register_admin();
		}

		$this->register_frontend();
		$this->register_payments();
		$this->register_gdpr();
		$this->register_cron();

		do_action( 'sperhake_tracker_booted', $this );
	}

	/* ---------------------------------------------------------------------
	 * Service container helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Fetch a registered service by key.
	 */
	public function get( string $key ): object {
		if ( ! isset( $this->services[ $key ] ) ) {
			throw new \RuntimeException( sprintf( 'Service "%s" is not registered.', $key ) );
		}

		return $this->services[ $key ];
	}

	private function set( string $key, object $service ): void {
		$this->services[ $key ] = $service;
	}

	/* ---------------------------------------------------------------------
	 * Registration groups
	 * ------------------------------------------------------------------ */

	private function register_core(): void {
		$logger     = new Logger();
		$encryption = new Encryption();
		$options    = new Options( $encryption );
		$repository = new TransactionRepository();
		$searchLogs = new SearchLogRepository();

		$this->set( 'logger', $logger );
		$this->set( 'encryption', $encryption );
		$this->set( 'options', $options );
		$this->set( 'transactions', $repository );
		$this->set( 'search_logs', $searchLogs );
		$this->set( 'turnstile', new Turnstile( $options, $logger ) );

		$this->set( 'vehicle_api', new VehicleApiClient( $options, $logger ) );
		$this->set( 'external_api', new ExternalApiClient( $options, $logger ) );
		$this->set( 'mailer', new Mailer( $options, $logger ) );
		$this->set( 'receipts', new ReceiptGenerator( $options, $logger ) );
	}

	private function register_admin(): void {
		$settings = new SettingsRegistry( $this->get( 'options' ), $this->get( 'encryption' ) );
		$settings->register();

		$menu = new AdminMenu(
			$this->get( 'options' ),
			$this->get( 'transactions' ),
			$this->get( 'logger' ),
			$settings,
			$this->get( 'search_logs' )
		);
		$menu->register();

		$this->set( 'settings', $settings );
		$this->set( 'admin_menu', $menu );
	}

	private function register_frontend(): void {
		$assets    = new Assets( $this->get( 'options' ) );
		$shortcode = new Shortcode( $this->get( 'transactions' ), $this->get( 'options' ), $this->get( 'vehicle_api' ) );
		$ajax      = new AjaxHandler(
			$this->get( 'vehicle_api' ),
			$this->get( 'options' ),
			$this->get( 'logger' ),
			$this->get( 'turnstile' ),
			$this->get( 'search_logs' )
		);

		$assets->register();
		$shortcode->register();
		$ajax->register();

		$this->set( 'assets', $assets );
		$this->set( 'shortcode', $shortcode );
		$this->set( 'ajax', $ajax );
	}

	private function register_payments(): void {
		$gateway = new StripeGateway(
			$this->get( 'options' ),
			$this->get( 'transactions' ),
			$this->get( 'logger' ),
			$this->get( 'vehicle_api' )
		);

		$webhook = new WebhookController(
			$this->get( 'options' ),
			$this->get( 'transactions' ),
			$this->get( 'mailer' ),
			$this->get( 'receipts' ),
			$this->get( 'external_api' ),
			$this->get( 'logger' ),
			$this->get( 'vehicle_api' )
		);

		$gateway->register();
		$webhook->register();

		$this->set( 'stripe', $gateway );
		$this->set( 'webhook', $webhook );
	}

	private function register_gdpr(): void {
		$privacy = new PrivacyManager( $this->get( 'transactions' ), $this->get( 'options' ) );
		$privacy->register();
		$this->set( 'privacy', $privacy );
	}

	private function register_cron(): void {
		$queue = new RetryQueue(
			$this->get( 'external_api' ),
			$this->get( 'transactions' ),
			$this->get( 'options' ),
			$this->get( 'logger' ),
			$this->get( 'search_logs' )
		);
		$queue->register();
		$this->set( 'retry_queue', $queue );
	}
}
