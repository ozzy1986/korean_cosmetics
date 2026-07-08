<?php
/**
 * Product search results.
 *
 * @package Atomy_RU
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );
?>
<div class="container atomy-shop-layout">
	<?php wc_get_template( 'global/sidebar.php' ); ?>
	<div class="atomy-shop-main">
		<h1 class="section-title">
			<?php
			printf(
				'Результаты поиска: %s',
				esc_html( get_search_query() )
			);
			?>
		</h1>
		<?php if ( have_posts() ) : ?>
			<?php woocommerce_product_loop_start(); ?>
			<?php
			while ( have_posts() ) :
				the_post();
				wc_get_template_part( 'content', 'product' );
			endwhile;
			?>
			<?php woocommerce_product_loop_end(); ?>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<p class="atomy-empty-search">По вашему запросу ничего не найдено. Попробуйте другие ключевые слова.</p>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer( 'shop' );
