<?php
/**
 * Theme bootstrap.
 *
 * @package Atomy_RU
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ATOMY_RU_VERSION', '1.1.0' );

function atomy_ru_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'woocommerce' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);
	register_nav_menus(
		array(
			'primary' => 'Primary Menu',
			'footer'  => 'Footer Menu',
		)
	);
}
add_action( 'after_setup_theme', 'atomy_ru_setup' );

function atomy_ru_assets(): void {
	wp_enqueue_style(
		'atomy-ru-fonts',
		'https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700;800&display=swap',
		array(),
		null
	);
	$css_path = get_template_directory() . '/assets/css/main.css';
	$js_path  = get_template_directory() . '/assets/js/main.js';
	$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : ATOMY_RU_VERSION;
	$js_ver   = file_exists( $js_path ) ? (string) filemtime( $js_path ) : ATOMY_RU_VERSION;
	$wc_deps  = array( 'atomy-ru-style' );
	if ( wp_style_is( 'woocommerce-layout', 'registered' ) ) {
		$wc_deps[] = 'woocommerce-layout';
	}
	if ( wp_style_is( 'woocommerce-general', 'registered' ) ) {
		$wc_deps[] = 'woocommerce-general';
	}
	wp_enqueue_style( 'atomy-ru-style', get_stylesheet_uri(), array( 'atomy-ru-fonts' ), ATOMY_RU_VERSION );
	wp_enqueue_style( 'atomy-ru-main', get_template_directory_uri() . '/assets/css/main.css', $wc_deps, $css_ver );
	wp_enqueue_script( 'atomy-ru-main', get_template_directory_uri() . '/assets/js/main.js', array(), $js_ver, true );
	wp_localize_script(
		'atomy-ru-main',
		'atomyTheme',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'atomy_wishlist' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'atomy_ru_assets', 20 );

function atomy_ru_cart_count_fragment( array $fragments ): array {
	ob_start();
	?>
	<span class="header-cart__count"><?php echo esc_html( (string) WC()->cart->get_cart_contents_count() ); ?></span>
	<?php
	$fragments['.header-cart__count'] = ob_get_clean();
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'atomy_ru_cart_count_fragment' );

function atomy_ru_loop_add_to_cart_text( string $text ): string {
	return 'В корзину';
}
add_filter( 'woocommerce_product_add_to_cart_text', 'atomy_ru_loop_add_to_cart_text' );
add_filter( 'woocommerce_product_single_add_to_cart_text', 'atomy_ru_loop_add_to_cart_text' );

function atomy_ru_cart_button_text( string $text ): string {
	return 'Оформить заявку';
}
add_filter( 'woocommerce_proceed_to_checkout_button_text', 'atomy_ru_cart_button_text' );

add_filter( 'loop_shop_columns', fn() => 4, 20 );
add_filter( 'loop_shop_per_page', fn() => 24, 20 );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

function atomy_ru_placeholder_src(): string {
	return get_template_directory_uri() . '/assets/img/placeholder.svg';
}
add_filter( 'woocommerce_placeholder_img_src', 'atomy_ru_placeholder_src' );

function atomy_ru_page_title( string $title ): string {
	if ( is_shop() && ! is_search() ) {
		return 'Каталог';
	}
	return $title;
}
add_filter( 'woocommerce_page_title', 'atomy_ru_page_title' );

function atomy_ru_hide_archive_title( bool $show ): bool {
	if ( is_product() ) {
		return false;
	}
	return $show;
}
add_filter( 'woocommerce_show_page_title', 'atomy_ru_hide_archive_title' );

function atomy_ru_single_product_hooks(): void {
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
	add_action( 'woocommerce_single_product_summary', 'atomy_ru_single_summary_meta', 9 );
	add_action( 'woocommerce_single_product_summary', 'atomy_ru_single_summary_price', 10 );
	add_action( 'woocommerce_single_product_summary', 'atomy_ru_single_wishlist_button', 35 );
}
add_action( 'woocommerce_before_single_product', 'atomy_ru_single_product_hooks' );

function atomy_ru_single_wishlist_button(): void {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	printf(
		'<button type="button" class="atomy-wish atomy-wish--single" data-wish-toggle data-product-id="%1$s" aria-label="Добавить в избранное">'
		. '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.5-4.6-10-9.2C.7 9 1.6 5.6 4.6 4.6 6.7 3.9 8.9 4.7 10 6.4l1 1.5 1-1.5c1.1-1.7 3.3-2.5 5.4-1.8 3 1 3.9 4.4 2.6 7.2C19.5 16.4 12 21 12 21z"/></svg>'
		. '<span class="atomy-wish__text">В избранное</span></button>',
		esc_attr( (string) $product->get_id() )
	);
}

function atomy_ru_single_summary_meta(): void {
	if ( function_exists( 'atomy_badges_html' ) ) {
		echo atomy_badges_html();
	}
}

function atomy_ru_single_summary_price(): void {
	echo '<div class="atomy-summary-prices">';
	if ( function_exists( 'atomy_price_html' ) ) {
		echo atomy_price_html();
	}
	if ( function_exists( 'atomy_pv_html' ) ) {
		echo atomy_pv_html();
	}
	echo '</div>';
}

add_filter( 'woocommerce_get_stock_html', '__return_empty_string' );

function atomy_ru_product_search( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}
	if ( isset( $_GET['post_type'] ) && 'product' === $_GET['post_type'] ) {
		$query->set( 'post_type', 'product' );
	}
}
add_action( 'pre_get_posts', 'atomy_ru_product_search' );

function atomy_ru_homepage_banners(): array {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}
	$candidates = array(
		ABSPATH . 'project/data/homepage.json',
		get_template_directory() . '/data/homepage.json',
	);
	$upload    = wp_upload_dir();
	$local_dir = trailingslashit( $upload['basedir'] ) . 'banners/';
	$local_url = trailingslashit( $upload['baseurl'] ) . 'banners/';
	$slides    = array();
	foreach ( $candidates as $file ) {
		if ( ! file_exists( $file ) ) {
			continue;
		}
		$data = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $data ) || empty( $data['banners'] ) ) {
			continue;
		}
		// Only PNG/JPG are real main-visual slides; SVG entries are sub-brand logos and category quick-links.
		foreach ( $data['banners'] as $banner ) {
			$img = (string) ( $banner['image'] ?? '' );
			if ( ! preg_match( '/\.(png|jpe?g)(\?.*)?$/i', $img ) ) {
				continue;
			}
			// Prefer a locally cached copy for speed/stability.
			$basename = basename( (string) wp_parse_url( $img, PHP_URL_PATH ) );
			if ( $basename && file_exists( $local_dir . $basename ) ) {
				$banner['image'] = $local_url . $basename;
			}
			$banner['link'] = atomy_ru_localize_banner_link( (string) ( $banner['link'] ?? '' ) );
			$slides[]       = $banner;
		}
		if ( $slides ) {
			break;
		}
	}
	$cached = $slides;
	return $cached;
}

/**
 * Map donor atomy.ru banner URLs onto this site's pages.
 *
 * Category deep links resolve via the _atomy_disp_ctg_no term meta,
 * product links via SKU; anything unresolvable falls back to the shop page.
 */
function atomy_ru_localize_banner_link( string $link ): string {
	$shop = (string) get_permalink( wc_get_page_id( 'shop' ) );
	if ( '' === $link ) {
		return $shop;
	}
	$host      = strtolower( (string) wp_parse_url( $link, PHP_URL_HOST ) );
	$site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	if ( '' === $host || $host === $site_host ) {
		return $link;
	}

	parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $query );
	if ( ! empty( $query['dispCtgNo'] ) ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 1,
				'meta_key'   => '_atomy_disp_ctg_no',
				'meta_value' => (string) $query['dispCtgNo'],
			)
		);
		if ( ! is_wp_error( $terms ) && $terms ) {
			$term_link = get_term_link( $terms[0] );
			if ( ! is_wp_error( $term_link ) ) {
				return $term_link;
			}
		}
		return $shop;
	}

	$path = (string) wp_parse_url( $link, PHP_URL_PATH );
	if ( preg_match( '~/product/([A-Za-z0-9_-]+)~', $path, $m ) ) {
		$product_id = wc_get_product_id_by_sku( $m[1] );
		if ( $product_id ) {
			return (string) get_permalink( $product_id );
		}
	}
	return $shop;
}

/**
 * Ensure legal pages exist; content lives in page-{slug}.php templates.
 */
function atomy_ru_ensure_legal_pages(): void {
	$pages = array(
		'terms'   => 'Пользовательское соглашение',
		'privacy' => 'Политика конфиденциальности',
	);
	foreach ( $pages as $slug => $title ) {
		if ( get_page_by_path( $slug ) ) {
			continue;
		}
		wp_insert_post(
			array(
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
	}
}
add_action( 'init', 'atomy_ru_ensure_legal_pages', 20 );

/**
 * Resolve the tile visual for a product category.
 *
 * @return array{url:string,kind:string} kind is 'icon' (donor line SVG), 'photo' (product image) or '' (none).
 */
function atomy_ru_category_image( WP_Term $term ): array {
	$upload = wp_upload_dir();

	// 1. Donor line icon mapped by dispCtgNo (downloaded into uploads/category-icons).
	$disp = (string) get_term_meta( $term->term_id, '_atomy_disp_ctg_no', true );
	if ( $disp ) {
		$icon_path = trailingslashit( $upload['basedir'] ) . 'category-icons/' . $disp . '.svg';
		if ( file_exists( $icon_path ) ) {
			return array(
				'url'  => trailingslashit( $upload['baseurl'] ) . 'category-icons/' . $disp . '.svg',
				'kind' => 'icon',
			);
		}
	}

	// 2. Explicit term thumbnail.
	$thumb_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
	if ( $thumb_id ) {
		$url = wp_get_attachment_image_url( (int) $thumb_id, 'woocommerce_thumbnail' );
		if ( $url ) {
			return array( 'url' => $url, 'kind' => 'photo' );
		}
	}

	// 3. Fallback: first product photo in the category (cached).
	$cached = get_term_meta( $term->term_id, '_atomy_tile_image', true );
	if ( $cached ) {
		return array( 'url' => $cached, 'kind' => 'photo' );
	}
	$products = get_posts(
		array(
			'post_type'      => 'product',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			),
			'meta_query'     => array(
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'EXISTS',
				),
			),
		)
	);
	if ( $products ) {
		$url = get_the_post_thumbnail_url( (int) $products[0], 'woocommerce_thumbnail' );
		if ( $url ) {
			update_term_meta( $term->term_id, '_atomy_tile_image', $url );
			return array( 'url' => $url, 'kind' => 'photo' );
		}
	}
	return array( 'url' => '', 'kind' => '' );
}

/**
 * SEO: favicon, meta description and Open Graph tags.
 */
function atomy_ru_head_meta(): void {
	$favicon = 'https://image.atomy.ru/disp/siteInfo/seo/favicon_v20250526140452.ico';
	echo '<link rel="icon" href="' . esc_url( $favicon ) . '" sizes="any" />' . "\n";

	$desc      = 'Atomy Russia — премиальная косметика, продукты для здоровья и товары для дома по абсолютной цене. Цены до и после регистрации, баллы PV.';
	$og_image  = '';
	$og_type   = 'website';

	if ( is_product() ) {
		$og_type = 'product';
		$post_id = get_queried_object_id();
		$excerpt = wp_strip_all_tags( (string) get_the_excerpt( $post_id ) );
		if ( '' !== trim( $excerpt ) ) {
			$desc = wp_trim_words( $excerpt, 32 );
		} else {
			$desc = get_the_title( $post_id ) . ' — купить в Atomy Russia. Цена до и после регистрации, баллы PV.';
		}
		$thumb = get_the_post_thumbnail_url( $post_id, 'large' );
		if ( $thumb ) {
			$og_image = $thumb;
		}
	} elseif ( is_product_category() || is_product_tag() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$desc = 'Каталог Atomy: ' . $term->name . '. Премиальное качество по абсолютной цене.';
		}
	}

	if ( '' === $og_image ) {
		$banners  = function_exists( 'atomy_ru_homepage_banners' ) ? atomy_ru_homepage_banners() : array();
		$og_image = ! empty( $banners[0]['image'] ) ? $banners[0]['image'] : '';
	}

	$desc  = trim( wp_strip_all_tags( $desc ) );
	$title = wp_get_document_title();
	$url   = is_singular() ? get_permalink() : home_url( add_query_arg( null, null ) );

	echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
	echo '<meta property="og:site_name" content="Atomy Russia" />' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '" />' . "\n";
	echo '<meta property="og:locale" content="ru_RU" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
	if ( $og_image ) {
		echo '<meta property="og:image" content="' . esc_url( $og_image ) . '" />' . "\n";
	}
	echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
}
add_action( 'wp_head', 'atomy_ru_head_meta', 2 );

remove_action( 'wp_head', 'wp_generator' );
add_filter( 'xmlrpc_enabled', '__return_false' );

function atomy_ru_shop_page_title(): void {
	$page_id = (int) get_option( 'woocommerce_shop_page_id' );
	if ( $page_id && 'Shop' === get_post_field( 'post_title', $page_id ) ) {
		wp_update_post(
			array(
				'ID'         => $page_id,
				'post_title' => 'Каталог',
			)
		);
	}
}
add_action( 'init', 'atomy_ru_shop_page_title', 20 );

function atomy_ru_catalog_redirect(): void {
	if ( is_404() && '/catalog' === untrailingslashit( (string) parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) ) ) {
		wp_safe_redirect( get_permalink( wc_get_page_id( 'shop' ) ), 301 );
		exit;
	}
}
add_action( 'init', 'atomy_ru_catalog_redirect' );

/**
 * Use classic PHP templates instead of WC block templates.
 */
function atomy_ru_disable_wc_block_templates( bool $has_block, string $template_name ): bool {
	return false;
}
add_filter( 'woocommerce_has_block_template', 'atomy_ru_disable_wc_block_templates', 10, 2 );
