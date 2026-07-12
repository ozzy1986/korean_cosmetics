<?php
/**
 * Pure SEO text generation (no WordPress dependencies).
 *
 * @package Atomy_Core
 */

declare( strict_types=1 );

class Atomy_Seo_Text {

	private const MIN_INTRO_LEN = 100;
	private const MAX_INTRO_LEN = 300;

	private const BADGE_LABELS = array(
		'Halal'   => 'Халяль',
		'charity' => 'Корзина добра',
	);

	/**
	 * Build a product intro (100–300 chars).
	 *
	 * @param array{name?:string,category?:string,tags?:string[],badges?:string[],member_price?:float|int,pv?:float|int} $d
	 */
	public function product_intro( array $d, int $max = self::MAX_INTRO_LEN ): string {
		$name     = trim( (string) ( $d['name'] ?? '' ) );
		$category = trim( (string) ( $d['category'] ?? '' ) );
		$tags     = array_values( array_filter( array_map( 'trim', (array) ( $d['tags'] ?? array() ) ) ) );
		$badges   = (array) ( $d['badges'] ?? array() );
		$price    = (float) ( $d['member_price'] ?? 0 );
		$pv       = (float) ( $d['pv'] ?? 0 );

		if ( '' === $name ) {
			$name = 'Продукт Atomy';
		}
		if ( '' === $category ) {
			$category = 'каталога Atomy';
		}

		$parts   = array();
		$parts[] = sprintf( '%s — оригинальный продукт Atomy из категории «%s».', $name, $category );

		if ( $tags ) {
			$parts[] = 'Особенности: ' . implode( ', ', $tags ) . '.';
		}

		$badge_labels = $this->format_badges( $badges );
		if ( $badge_labels ) {
			$parts[] = implode( ', ', $badge_labels ) . '.';
		}

		if ( $price > 0 ) {
			$parts[] = 'Цена для участников Atomy — ' . $this->format_price( $price ) . '.';
		}

		if ( $pv > 0 ) {
			$parts[] = 'Бонус PV — ' . $this->format_number( $pv ) . '.';
		}

		$parts[] = 'Заказ через официального дистрибьютора Atomy с доставкой по России.';

		$text = implode( ' ', $parts );
		$text = $this->ensure_min_length( $text, $name, $category );
		$text = $this->clamp( $text, $max );

		return $text;
	}

	/**
	 * Category archive intro.
	 */
	public function category_intro( string $name, int $count ): string {
		$label = trim( $name );
		if ( '' === $label ) {
			$label = 'Категория';
		}
		return sprintf(
			'Категория «%s» в каталоге Atomy: %d %s. Оригинальная продукция с доставкой по России от официального дистрибьютора.',
			$label,
			$count,
			$this->plural_products( $count )
		);
	}

	/**
	 * Clamp text to max length at a word boundary (multibyte-safe).
	 */
	public function clamp( string $text, int $max = self::MAX_INTRO_LEN ): string {
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? $text );
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}

		$slice = mb_substr( $text, 0, $max );
		$last_space = mb_strrpos( $slice, ' ' );
		if ( false !== $last_space && $last_space > 0 ) {
			$slice = mb_substr( $slice, 0, $last_space );
		}

		return rtrim( $slice, ".,;:!?«»\"'-" );
	}

	/**
	 * Enhance product description HTML: img alt, loading, decoding.
	 */
	public function enhance_description_html( string $html, string $alt_base ): string {
		$html = trim( $html );
		if ( '' === $html ) {
			return '';
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			return $html;
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML(
			'<?xml encoding="UTF-8"?><div id="atomy-seo-root">' . $html . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		if ( ! $loaded ) {
			return $html;
		}

		$xpath = new DOMXPath( $dom );
		$root  = $xpath->query( '//*[@id="atomy-seo-root"]' );
		if ( ! $root || ! $root->length ) {
			return $html;
		}

		$images = $xpath->query( './/img', $root->item( 0 ) );
		if ( ! $images ) {
			return $this->extract_inner_html( $dom, $root->item( 0 ) ) ?: $html;
		}

		$alt_base = trim( $alt_base );
		if ( '' === $alt_base ) {
			$alt_base = 'Продукт Atomy';
		}

		$index = 0;
		foreach ( $images as $img ) {
			if ( ! $img instanceof DOMElement ) {
				continue;
			}
			++$index;
			$existing_alt = trim( $img->getAttribute( 'alt' ) );
			if ( '' === $existing_alt ) {
				$img->setAttribute( 'alt', $alt_base . ' — фото ' . $index );
			}
			$img->setAttribute( 'loading', 'lazy' );
			$img->setAttribute( 'decoding', 'async' );
		}

		$result = $this->extract_inner_html( $dom, $root->item( 0 ) );
		return '' !== $result ? $result : $html;
	}

	/**
	 * @param string[] $badges
	 * @return string[]
	 */
	private function format_badges( array $badges ): array {
		$labels = array();
		foreach ( $badges as $badge ) {
			$key = (string) $badge;
			if ( isset( self::BADGE_LABELS[ $key ] ) ) {
				$labels[] = self::BADGE_LABELS[ $key ];
			}
		}
		return array_values( array_unique( $labels ) );
	}

	private function format_price( float $amount ): string {
		return number_format( $amount, 0, '.', ' ' ) . ' ₽';
	}

	private function format_number( float $amount ): string {
		return number_format( $amount, 0, '.', ' ' );
	}

	private function ensure_min_length( string $text, string $name, string $category ): string {
		if ( mb_strlen( $text ) >= self::MIN_INTRO_LEN ) {
			return $text;
		}
		$padding = sprintf(
			' %s из категории «%s» — качественная продукция бренда Atomy для ежедневного использования.',
			$name,
			$category
		);
		return trim( $text . $padding );
	}

	private function plural_products( int $count ): string {
		$mod10  = $count % 10;
		$mod100 = $count % 100;
		if ( $mod10 === 1 && $mod100 !== 11 ) {
			return 'товар';
		}
		if ( $mod10 >= 2 && $mod10 <= 4 && ( $mod100 < 10 || $mod100 >= 20 ) ) {
			return 'товара';
		}
		return 'товаров';
	}

	private function extract_inner_html( DOMDocument $dom, DOMNode $node ): string {
		$inner = '';
		foreach ( iterator_to_array( $node->childNodes ) as $child ) {
			$inner .= $dom->saveHTML( $child );
		}
		return trim( $inner );
	}
}
