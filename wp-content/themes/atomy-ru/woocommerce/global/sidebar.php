<?php
/**
 * Archive sidebar with categories.
 *
 * @package Atomy_RU
 */

defined( 'ABSPATH' ) || exit;

$terms = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
	)
);
?>
<aside class="atomy-shop-sidebar">
	<h3>Категории</h3>
	<?php if ( ! is_wp_error( $terms ) && $terms ) : ?>
		<ul class="atomy-cat-list">
			<?php foreach ( $terms as $term ) : ?>
				<li>
					<a href="<?php echo esc_url( get_term_link( $term ) ); ?>" <?php echo is_product_category( $term->slug ) ? 'class="is-active"' : ''; ?>>
						<?php echo esc_html( $term->name ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</aside>
