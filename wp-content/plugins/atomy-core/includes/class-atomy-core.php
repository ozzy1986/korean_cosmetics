<?php
/**
 * Main plugin bootstrap.
 *
 * @package Atomy_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ATOMY_CORE_DIR . 'includes/services/class-prices.php';
require_once ATOMY_CORE_DIR . 'includes/services/class-request-mode.php';
require_once ATOMY_CORE_DIR . 'includes/services/class-requests.php';
require_once ATOMY_CORE_DIR . 'includes/services/class-notifications.php';
require_once ATOMY_CORE_DIR . 'includes/services/class-settings.php';
require_once ATOMY_CORE_DIR . 'includes/services/class-importer.php';
require_once ATOMY_CORE_DIR . 'includes/services/class-store.php';
require_once ATOMY_CORE_DIR . 'includes/services/class-wishlist.php';
require_once ATOMY_CORE_DIR . 'includes/class-atomy-cli.php';

/**
 * Plugin singleton.
 */
class Atomy_Core {

	/** @var Atomy_Core|null */
	private static $instance = null;

	/** @var Atomy_Core_Prices */
	public $prices;

	/** @var Atomy_Core_Request_Mode */
	public $request_mode;

	/** @var Atomy_Core_Requests */
	public $requests;

	/** @var Atomy_Core_Notifications */
	public $notifications;

	/** @var Atomy_Core_Settings */
	public $settings;

	/** @var Atomy_Core_Importer */
	public $importer;

	/** @var Atomy_Core_Store */
	public $store;

	/** @var Atomy_Core_Wishlist */
	public $wishlist;

	public static function instance(): Atomy_Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	public static function activate(): void {
		Atomy_Core_Requests::register_post_type();
		flush_rewrite_rules();
	}

	public function boot(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p>Atomy Core requires WooCommerce.</p></div>';
			} );
			return;
		}

		$this->prices        = new Atomy_Core_Prices();
		$this->request_mode  = new Atomy_Core_Request_Mode();
		$this->requests      = new Atomy_Core_Requests();
		$this->notifications = new Atomy_Core_Notifications();
		$this->settings      = new Atomy_Core_Settings();
		$this->importer      = new Atomy_Core_Importer();
		$this->store         = new Atomy_Core_Store();
		$this->wishlist      = new Atomy_Core_Wishlist();

		$this->prices->register();
		$this->request_mode->register();
		$this->requests->register();
		$this->notifications->register();
		$this->settings->register();
		$this->importer->register();
		$this->store->register();
		$this->wishlist->register();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Atomy_Core_CLI::register();
		}
	}
}

/**
 * Global accessor.
 */
function atomy_core(): Atomy_Core {
	return Atomy_Core::instance();
}

/**
 * Price HTML helper.
 */
function atomy_price_html( $product = null ): string {
	return atomy_core()->prices->render_price_html( $product );
}

/**
 * PV HTML helper.
 */
function atomy_pv_html( $product = null ): string {
	return atomy_core()->prices->render_pv_html( $product );
}

/**
 * Badges HTML helper.
 */
function atomy_badges_html( $product = null ): string {
	return atomy_core()->prices->render_badges_html( $product );
}

/**
 * Wishlist page URL helper.
 */
function atomy_wishlist_url(): string {
	return atomy_core()->wishlist->get_page_url();
}
