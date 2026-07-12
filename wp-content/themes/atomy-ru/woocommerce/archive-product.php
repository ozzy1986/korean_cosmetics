<?php
/**
 * Shop archive layout.
 *
 * @package Atomy_RU
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );
?>
<div class="container atomy-shop-layout">
	<?php wc_get_template( 'global/sidebar.php' ); ?>
	<div class="atomy-shop-main">
		<?php if ( apply_filters( 'woocommerce_show_page_title', true ) ) : ?>
			<h1 class="section-title"><?php woocommerce_page_title(); ?></h1>
			<?php if ( function_exists( 'atomy_seo_category_intro' ) ) : ?>
				<?php $intro = atomy_seo_category_intro(); ?>
				<?php if ( $intro ) : ?>
					<p class="archive-intro"><?php echo esc_html( $intro ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>
		<?php do_action( 'woocommerce_before_main_content' ); ?>
		<?php if ( woocommerce_product_loop() ) : ?>
			<?php do_action( 'woocommerce_before_shop_loop' ); ?>
			<div class="products-grid">
				<?php woocommerce_product_loop_start(); ?>
				<?php while ( have_posts() ) : the_post(); ?>
					<?php wc_get_template_part( 'content', 'product' ); ?>
				<?php endwhile; ?>
				<?php woocommerce_product_loop_end(); ?>
			</div>
			<?php do_action( 'woocommerce_after_shop_loop' ); ?>
		<?php else : ?>
			<?php do_action( 'woocommerce_no_products_found' ); ?>
		<?php endif; ?>
		<?php do_action( 'woocommerce_after_main_content' ); ?>
	</div>
</div>
<?php
get_footer( 'shop' );
