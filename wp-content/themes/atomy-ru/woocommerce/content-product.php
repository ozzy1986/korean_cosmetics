<?php
/**
 * Product loop card.
 *
 * @package Atomy_RU
 */

defined( 'ABSPATH' ) || exit;

global $product;
if ( ! $product instanceof WC_Product ) {
	return;
}
?>
<li <?php wc_product_class( 'atomy-product-card', $product ); ?>>
	<button type="button" class="atomy-wish" data-wish-toggle data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>" aria-label="Добавить в избранное" title="В избранное">
		<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.5-4.6-10-9.2C.7 9 1.6 5.6 4.6 4.6 6.7 3.9 8.9 4.7 10 6.4l1 1.5 1-1.5c1.1-1.7 3.3-2.5 5.4-1.8 3 1 3.9 4.4 2.6 7.2C19.5 16.4 12 21 12 21z"/></svg>
	</button>
	<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="atomy-product-card__link">
		<div class="atomy-product-card__image">
			<?php echo $product->get_image( 'woocommerce_thumbnail' ); ?>
		</div>
		<h2 class="atomy-product-card__title woocommerce-loop-product__title"><?php echo esc_html( $product->get_name() ); ?></h2>
	</a>
	<?php echo atomy_badges_html( $product ); ?>
	<?php echo atomy_price_html( $product ); ?>
	<?php echo atomy_pv_html( $product ); ?>
	<div class="atomy-product-card__actions">
		<?php woocommerce_template_loop_add_to_cart(); ?>
	</div>
</li>
