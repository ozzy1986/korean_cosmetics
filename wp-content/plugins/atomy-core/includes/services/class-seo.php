<?php
/**
 * WordPress SEO integration: robots, sitemaps, canonical, structured data.
 *
 * @package Atomy_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atomy_Seo {

	/** @var Atomy_Seo_Text */
	private $text;

	public function __construct() {
		$this->text = new Atomy_Seo_Text();
	}

	public function register(): void {
		add_filter( 'wp_robots', array( $this, 'filter_wp_robots' ), 20 );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'filter_sitemaps_taxonomies' ) );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'filter_sitemaps_providers' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'filter_sitemaps_posts' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'output_canonical' ), 1 );
		add_action( 'wp_head', array( $this, 'output_verification_meta' ), 2 );
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 20, 2 );
		add_filter( 'woocommerce_structured_data_product', array( $this, 'filter_structured_data_product' ), 20, 2 );
		add_action( 'wp_head', array( $this, 'output_organization_json_ld' ), 5 );
	}

	public function get_text(): Atomy_Seo_Text {
		return $this->text;
	}

	/**
	 * @param array<string,mixed> $robots
	 * @return array<string,mixed>
	 */
	public function filter_wp_robots( array $robots ): array {
		if ( $this->should_noindex() ) {
			$robots['noindex'] = true;
			unset( $robots['nofollow'] );
		}
		return $robots;
	}

	public function should_noindex(): bool {
		if ( is_search() ) {
			return true;
		}
		if ( is_tax( 'product_tag' ) ) {
			return true;
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}
		if ( $this->is_request_page() || $this->is_wishlist_page() ) {
			return true;
		}
		if ( isset( $_GET['orderby'] ) || isset( $_GET['add-to-cart'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
		if ( isset( $_GET['s'] ) && '' !== (string) wp_unslash( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $taxonomies
	 * @return array<string,mixed>
	 */
	public function filter_sitemaps_taxonomies( array $taxonomies ): array {
		unset( $taxonomies['product_tag'] );
		return $taxonomies;
	}

	/**
	 * @param WP_Sitemaps_Provider|false $provider
	 * @param string                     $name
	 * @return WP_Sitemaps_Provider|false
	 */
	public function filter_sitemaps_providers( $provider, string $name ) {
		if ( 'users' === $name ) {
			return false;
		}
		return $provider;
	}

	/**
	 * @param array<string,mixed> $args
	 * @param string              $post_type
	 * @return array<string,mixed>
	 */
	public function filter_sitemaps_posts( array $args, string $post_type ): array {
		if ( 'page' !== $post_type ) {
			return $args;
		}
		$exclude = array_filter(
			array(
				(int) get_option( 'woocommerce_cart_page_id' ),
				(int) get_option( 'woocommerce_checkout_page_id' ),
				(int) get_option( 'atomy_request_page_id' ),
				(int) get_option( 'atomy_wishlist_page_id' ),
			)
		);
		if ( $exclude ) {
			$args['post__not_in'] = array_values(
				array_unique(
					array_merge( (array) ( $args['post__not_in'] ?? array() ), $exclude )
				)
			);
		}
		return $args;
	}

	public function output_canonical(): void {
		$url = $this->get_canonical_url();
		if ( '' === $url ) {
			return;
		}
		echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
	}

	public function get_canonical_url(): string {
		if ( is_singular( array( 'product', 'page' ) ) ) {
			return (string) get_permalink();
		}
		if ( is_product_category() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$link = get_term_link( $term );
				return is_wp_error( $link ) ? '' : (string) $link;
			}
		}
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			$shop_id = (int) wc_get_page_id( 'shop' );
			return $shop_id > 0 ? (string) get_permalink( $shop_id ) : '';
		}
		return '';
	}

	public function output_verification_meta(): void {
		$google = trim( (string) get_option( 'atomy_seo_google_verification', '' ) );
		$yandex = trim( (string) get_option( 'atomy_seo_yandex_verification', '' ) );
		if ( '' !== $google ) {
			echo '<meta name="google-site-verification" content="' . esc_attr( $google ) . '" />' . "\n";
		}
		if ( '' !== $yandex ) {
			echo '<meta name="yandex-verification" content="' . esc_attr( $yandex ) . '" />' . "\n";
		}
	}

	public function filter_robots_txt( string $output, bool $is_public ): string {
		if ( ! $is_public ) {
			return $output;
		}
		$lines = array(
			'Disallow: /cart/',
			'Disallow: /checkout/',
			'Disallow: /request/',
			'Disallow: /wishlist/',
			'Disallow: /?s=',
		);
		$sitemap = trailingslashit( home_url() ) . 'wp-sitemap.xml';
		$lines[] = 'Sitemap: ' . $sitemap;
		$block   = implode( "\n", $lines );
		if ( str_contains( $output, 'Disallow: /cart/' ) ) {
			return $output;
		}
		return rtrim( $output ) . "\n" . $block . "\n";
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	public function filter_structured_data_product( array $data, WC_Product $product ): array {
		$post_id = $product->get_id();
		$excerpt = trim( (string) get_post_field( 'post_excerpt', $post_id ) );
		if ( '' !== $excerpt ) {
			$data['description'] = $excerpt;
		}

		$sku = $product->get_sku();
		if ( $sku ) {
			$data['sku'] = $sku;
		}

		$member = (float) $product->get_meta( '_atomy_member_price' );
		if ( $member <= 0 ) {
			$member = (float) $product->get_regular_price();
		}

		if ( ! isset( $data['offers'] ) || ! is_array( $data['offers'] ) ) {
			$data['offers'] = array();
		}

		$data['offers']['price']           = $member > 0 ? (string) $member : $data['offers']['price'] ?? '';
		$data['offers']['priceCurrency']   = 'RUB';
		$data['offers']['availability']    = 'https://schema.org/InStock';
		$data['offers']['url']             = get_permalink( $post_id );
		$data['offers']['priceValidUntil'] = gmdate( 'Y-m-d', strtotime( '+1 year' ) );

		return $data;
	}

	public function output_organization_json_ld(): void {
		if ( ! is_front_page() ) {
			return;
		}
		$data = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
		);
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $logo_url ) {
				$data['logo'] = $logo_url;
			}
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}

	/**
	 * @param WP_Term|int|string $term
	 */
	public function category_intro_for_term( $term ): string {
		if ( is_numeric( $term ) ) {
			$term = get_term( (int) $term, 'product_cat' );
		} elseif ( is_string( $term ) ) {
			$term = get_term_by( 'slug', $term, 'product_cat' );
		}
		if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
			return '';
		}
		$count = (int) $term->count;
		return $this->text->category_intro( $term->name, $count );
	}

	public function build_product_intro_from_product( WC_Product $product ): string {
		$categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
		$tags       = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) );
		$member     = (float) $product->get_meta( '_atomy_member_price' );
		$pv         = (float) $product->get_meta( '_atomy_pv' );
		$badges     = $product->get_meta( '_atomy_badges' );

		return $this->text->product_intro(
			array(
				'name'         => $product->get_name(),
				'category'     => is_array( $categories ) && $categories ? (string) $categories[0] : '',
				'tags'         => is_array( $tags ) ? $tags : array(),
				'badges'       => is_array( $badges ) ? $badges : array(),
				'member_price' => $member,
				'pv'           => $pv,
			)
		);
	}

	/**
	 * Enrich products: excerpt, content alts, attachment alts.
	 *
	 * @return array{processed:int,updated:int,skipped:int}
	 */
	public function enrich_products( bool $dry_run = false, bool $force = false, int $limit = 0 ): array {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'fields'         => 'ids',
		);
		$query     = new WP_Query( $args );
		$processed = 0;
		$updated   = 0;
		$skipped   = 0;

		foreach ( $query->posts as $product_id ) {
			++$processed;
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				++$skipped;
				continue;
			}

			$intro   = $this->build_product_intro_from_product( $product );
			$content = (string) get_post_field( 'post_content', $product_id );
			$enhanced = $this->text->enhance_description_html( $content, $product->get_name() );
			$changed  = false;

			$current_excerpt = trim( (string) get_post_field( 'post_excerpt', $product_id ) );
			if ( $force || '' === $current_excerpt || $current_excerpt !== $intro ) {
				$changed = true;
				if ( ! $dry_run ) {
					wp_update_post(
						array(
							'ID'           => $product_id,
							'post_excerpt' => $intro,
						)
					);
				}
			}

			if ( $enhanced !== $content ) {
				$changed = true;
				if ( ! $dry_run ) {
					wp_update_post(
						array(
							'ID'           => $product_id,
							'post_content' => $enhanced,
						)
					);
				}
			}

			if ( ! $dry_run ) {
				$this->enrich_product_attachment_alts( $product_id, $product->get_name() );
			}

			if ( $changed ) {
				++$updated;
			} else {
				++$skipped;
			}
		}

		return array(
			'processed' => $processed,
			'updated'   => $updated,
			'skipped'   => $skipped,
		);
	}

	private function enrich_product_attachment_alts( int $product_id, string $name ): void {
		$thumb_id = (int) get_post_thumbnail_id( $product_id );
		$gallery  = array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $product_id, '_product_image_gallery', true ) ) ) );
		$ids      = array_values( array_unique( array_filter( array_merge( $thumb_id ? array( $thumb_id ) : array(), $gallery ) ) ) );
		$index    = 0;
		foreach ( $ids as $attach_id ) {
			++$index;
			$alt = trim( (string) get_post_meta( $attach_id, '_wp_attachment_image_alt', true ) );
			if ( '' === $alt ) {
				update_post_meta( $attach_id, '_wp_attachment_image_alt', $name . ' — фото ' . $index );
			}
		}
	}

	public function save_verification_codes( string $google = '', string $yandex = '' ): void {
		update_option( 'atomy_seo_google_verification', sanitize_text_field( $google ), false );
		update_option( 'atomy_seo_yandex_verification', sanitize_text_field( $yandex ), false );
	}

	private function is_request_page(): bool {
		$page_id = (int) get_option( 'atomy_request_page_id' );
		return $page_id > 0 && is_page( $page_id );
	}

	private function is_wishlist_page(): bool {
		$page_id = (int) get_option( 'atomy_wishlist_page_id' );
		return $page_id > 0 && is_page( $page_id );
	}
}
