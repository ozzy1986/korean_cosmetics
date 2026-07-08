<?php
/**
 * WooCommerce fallback wrapper.
 *
 * @package Atomy_RU
 */

defined( 'ABSPATH' ) || exit;

if ( is_shop() || is_product_taxonomy() ) {
	wc_get_template( 'archive-product.php' );
	return;
}

get_header( 'shop' );
?>
<div class="container products-grid">
	<?php woocommerce_content(); ?>
</div>
<?php
get_footer( 'shop' );
