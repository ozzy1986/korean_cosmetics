<?php
/**
 * Single product layout.
 *
 * @package Atomy_RU
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );
?>
<div class="container atomy-single-wrap">
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<?php wc_get_template_part( 'content', 'single-product' ); ?>
	<?php endwhile; ?>
</div>
<?php
get_footer( 'shop' );
