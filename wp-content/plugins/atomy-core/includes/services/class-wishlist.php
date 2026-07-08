<?php
/**
 * Client-side wishlist (localStorage). Server only renders saved product cards.
 *
 * @package Atomy_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atomy_Core_Wishlist {

	public function register(): void {
		add_action( 'init', array( $this, 'ensure_page' ), 20 );
		add_shortcode( 'atomy_wishlist', array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_atomy_wishlist_cards', array( $this, 'ajax_cards' ) );
		add_action( 'wp_ajax_nopriv_atomy_wishlist_cards', array( $this, 'ajax_cards' ) );
	}

	public function get_page_url(): string {
		$id = (int) get_option( 'atomy_wishlist_page_id' );
		if ( $id ) {
			return get_permalink( $id ) ?: home_url( '/wishlist/' );
		}
		return home_url( '/wishlist/' );
	}

	public function ensure_page(): void {
		$id = (int) get_option( 'atomy_wishlist_page_id' );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			return;
		}
		$existing = get_page_by_path( 'wishlist' );
		if ( $existing ) {
			update_option( 'atomy_wishlist_page_id', $existing->ID );
			return;
		}
		$new_id = wp_insert_post(
			array(
				'post_title'   => 'Избранное',
				'post_name'    => 'wishlist',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[atomy_wishlist]',
			)
		);
		if ( $new_id && ! is_wp_error( $new_id ) ) {
			update_option( 'atomy_wishlist_page_id', (int) $new_id );
		}
	}

	public function render_shortcode(): string {
		$shop_url = get_permalink( wc_get_page_id( 'shop' ) );
		ob_start();
		?>
		<div class="atomy-wishlist" data-wishlist-page>
			<div class="atomy-wishlist__empty" data-wishlist-empty hidden>
				<p>В избранном пока пусто. Добавляйте товары кнопкой с сердечком.</p>
				<a class="btn btn--primary" href="<?php echo esc_url( $shop_url ); ?>">Перейти в каталог</a>
			</div>
			<div class="atomy-wishlist__loading" data-wishlist-loading>Загрузка избранного…</div>
			<div class="products-grid">
				<ul class="products columns-4" data-wishlist-grid></ul>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function ajax_cards(): void {
		check_ajax_referer( 'atomy_wishlist', 'nonce' );
		$ids = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( empty( $ids ) ) {
			wp_send_json_success( array( 'html' => '' ) );
		}

		$query = new WP_Query(
			array(
				'post_type'           => 'product',
				'post__in'            => $ids,
				'orderby'             => 'post__in',
				'posts_per_page'      => 60,
				'post_status'         => 'publish',
				'ignore_sticky_posts' => true,
			)
		);

		ob_start();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}
		}
		wp_reset_postdata();

		wp_send_json_success( array( 'html' => (string) ob_get_clean() ) );
	}
}
