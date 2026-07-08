<?php
/**
 * Request page template.
 *
 * @package Atomy_RU
 */

get_header();
?>
<div class="container">
	<?php echo do_shortcode( '[atomy_request_form]' ); ?>
</div>
<?php
get_footer();
