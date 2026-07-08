<?php
/**
 * Store visibility, cart page, WooCommerce tweaks.
 *
 * @package Atomy_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atomy_Core_Store {

	public function register(): void {
		add_action( 'init', array( $this, 'ensure_store_live' ), 5 );
		add_filter( 'woocommerce_coming_soon_should_show_coming_soon_page', '__return_false' );
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_false' );
		add_filter( 'woocommerce_coupons_enabled', '__return_false' );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'loop_add_to_cart_with_qty' ), 20, 2 );
	}

	/**
	 * Wrap the loop "add to cart" button with a quantity stepper.
	 * The stepper is shown instead of the button while the product is in the cart.
	 */
	public function loop_add_to_cart_with_qty( string $html, $product ): string {
		if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return $html;
		}
		$qty = 0;
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( (int) $item['product_id'] === $product->get_id() ) {
					$qty = (int) $item['quantity'];
					break;
				}
			}
		}
		$stepper = sprintf(
			'<div class="atomy-qty atomy-qty--loop" data-product-id="%1$d"%2$s>'
			. '<button type="button" class="atomy-qty__btn" data-qty-minus aria-label="Уменьшить количество">&minus;</button>'
			. '<span class="atomy-qty__num">%3$d</span>'
			. '<button type="button" class="atomy-qty__btn" data-qty-plus aria-label="Увеличить количество">+</button>'
			. '</div>',
			$product->get_id(),
			$qty > 0 ? '' : ' hidden',
			max( 1, $qty )
		);
		return sprintf(
			'<div class="atomy-loop-buy%1$s" data-loop-buy data-product-id="%2$d">%3$s%4$s</div>',
			$qty > 0 ? ' has-qty' : '',
			$product->get_id(),
			$html,
			$stepper
		);
	}

	public function ensure_store_live(): void {
		if ( 'yes' === get_option( 'woocommerce_coming_soon' ) ) {
			update_option( 'woocommerce_coming_soon', 'no' );
		}
		if ( 'yes' === get_option( 'woocommerce_store_pages_only' ) ) {
			update_option( 'woocommerce_store_pages_only', 'no' );
		}
		$this->ensure_classic_cart_page();
	}

	private function ensure_classic_cart_page(): void {
		$page_id = (int) get_option( 'woocommerce_cart_page_id' );
		if ( ! $page_id ) {
			return;
		}
		$content = (string) get_post_field( 'post_content', $page_id );
		if ( str_contains( $content, 'wp:woocommerce/cart' ) ) {
			wp_update_post(
				array(
					'ID'           => $page_id,
					'post_content' => '[woocommerce_cart]',
				)
			);
		}
	}
}
