<?php
/**
 * Plugin Name: Atomy Core
 * Description: Atomy.ru clone core: catalog prices/PV, order requests, import, notifications.
 * Version: 1.3.1
 * Author: Atomy Russia
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 *
 * @package Atomy_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ATOMY_CORE_VERSION', '1.3.1' );
define( 'ATOMY_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ATOMY_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once ATOMY_CORE_DIR . 'includes/class-atomy-core.php';

register_activation_hook( __FILE__, array( 'Atomy_Core', 'activate' ) );

Atomy_Core::instance();
