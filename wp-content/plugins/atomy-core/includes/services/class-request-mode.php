<?php
/**
 * Request mode: keep cart, disable checkout/payment.
 *
 * @package Atomy_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atomy_Core_Request_Mode {

	public function register(): void {
		add_filter( 'woocommerce_cart_needs_payment', '__return_false' );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'disable_gateways' ) );
		add_filter( 'woocommerce_get_checkout_url', array( $this, 'checkout_to_request_url' ) );
		add_filter( 'woocommerce_order_button_text', array( $this, 'request_button_text' ) );
		add_action( 'template_redirect', array( $this, 'redirect_checkout' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function disable_gateways( array $gateways ): array {
		return array();
	}

	public function get_request_page_url(): string {
		$page_id = (int) get_option( 'atomy_request_page_id' );
		if ( $page_id ) {
			return get_permalink( $page_id ) ?: home_url( '/request/' );
		}
		return home_url( '/request/' );
	}

	public function checkout_to_request_url( string $url ): string {
		return $this->get_request_page_url();
	}

	public function request_button_text( string $text ): string {
		return 'Оформить заявку';
	}

	public function redirect_checkout(): void {
		if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
			wp_safe_redirect( $this->get_request_page_url() );
			exit;
		}
		if ( is_account_page() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
	}

	public function enqueue_assets(): void {
		wp_enqueue_style(
			'atomy-request',
			ATOMY_CORE_URL . 'assets/request.css',
			array(),
			ATOMY_CORE_VERSION
		);
		wp_enqueue_script(
			'atomy-request',
			ATOMY_CORE_URL . 'assets/request.js',
			array( 'jquery' ),
			ATOMY_CORE_VERSION,
			true
		);
		wp_localize_script(
			'atomy-request',
			'atomyRequest',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'atomy_request' ),
				'shopUrl' => get_permalink( wc_get_page_id( 'shop' ) ) ?: home_url( '/' ),
			)
		);
	}
}
