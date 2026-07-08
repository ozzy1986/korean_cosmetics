<?php
/**
 * Product price/PV rendering.
 *
 * @package Atomy_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atomy_Core_Prices {

	public function register(): void {
		add_filter( 'woocommerce_get_price_html', array( $this, 'filter_price_html' ), 20, 2 );
	}

	public function get_product( $product = null ): ?WC_Product {
		if ( $product instanceof WC_Product ) {
			return $product;
		}
		if ( is_numeric( $product ) ) {
			$p = wc_get_product( (int) $product );
			return $p ?: null;
		}
		global $product;
		return ( $product instanceof WC_Product ) ? $product : null;
	}

	public function get_reg_price( WC_Product $product ): float {
		return (float) $product->get_meta( '_atomy_reg_price' );
	}

	public function get_member_price( WC_Product $product ): float {
		$member = (float) $product->get_meta( '_atomy_member_price' );
		return $member > 0 ? $member : (float) $product->get_regular_price();
	}

	public function get_pv( WC_Product $product ): float {
		return (float) $product->get_meta( '_atomy_pv' );
	}

	public function get_badges( WC_Product $product ): array {
		$badges = $product->get_meta( '_atomy_badges' );
		return is_array( $badges ) ? $badges : array();
	}

	/**
	 * Plain price string with ruble sign (no HTML, no decimals).
	 */
	public function format_price( float $amount ): string {
		return number_format( $amount, 0, '.', ' ' ) . ' ₽';
	}

	private const BADGE_LABELS = array(
		'Halal'         => 'Halal',
		'charity'       => 'Корзина добра',
		'free-shipping' => 'Бесплатная доставка',
	);

	public function render_price_html( $product = null ): string {
		$product = $this->get_product( $product );
		if ( ! $product ) {
			return '';
		}
		$reg    = $this->get_reg_price( $product );
		$member = $this->get_member_price( $product );
		$html   = '<div class="atomy-prices">';
		if ( $reg > 0 && $reg !== $member ) {
			$html .= '<div class="atomy-price atomy-price--reg"><span class="atomy-price__label">Цена до регистрации</span><span class="atomy-price__old">' . esc_html( $this->format_price( $reg ) ) . '</span></div>';
		}
		if ( $member > 0 ) {
			$html .= '<div class="atomy-price atomy-price--member"><span class="atomy-price__label">Цена после регистрации</span><span class="atomy-price__now">' . esc_html( $this->format_price( $member ) ) . '</span></div>';
		}
		$html .= '</div>';
		return $html;
	}

	public function render_pv_html( $product = null ): string {
		$product = $this->get_product( $product );
		if ( ! $product ) {
			return '';
		}
		$pv = $this->get_pv( $product );
		if ( $pv <= 0 ) {
			return '';
		}
		return '<div class="atomy-pv"><strong>' . esc_html( number_format( $pv, 0, '.', ' ' ) ) . '</strong><span>PV</span></div>';
	}

	public function render_badges_html( $product = null ): string {
		$product = $this->get_product( $product );
		if ( ! $product ) {
			return '';
		}
		$badges = $this->get_badges( $product );
		if ( empty( $badges ) ) {
			return '';
		}
		$html = '<div class="atomy-badges">';
		foreach ( $badges as $badge ) {
			$label = self::BADGE_LABELS[ $badge ] ?? $badge;
			$html .= '<span class="atomy-badge atomy-badge--' . esc_attr( sanitize_html_class( $badge ) ) . '">' . esc_html( $label ) . '</span>';
		}
		$html .= '</div>';
		return $html;
	}

	public function filter_price_html( string $price, WC_Product $product ): string {
		return $this->render_price_html( $product );
	}
}
