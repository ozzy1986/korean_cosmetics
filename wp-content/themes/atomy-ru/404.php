<?php
/**
 * Not found page.
 *
 * @package Atomy_RU
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="container atomy-empty-page">
	<span class="atomy-empty-page__code" aria-hidden="true">404</span>
	<h1>Страница не найдена</h1>
	<p>Возможно, адрес изменился или страница была удалена. Перейдите в каталог или вернитесь на главную.</p>
	<div class="atomy-empty-page__actions">
		<a class="btn btn--primary" href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">Перейти в каталог</a>
		<a class="btn btn--secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>">На главную</a>
	</div>
</section>
<?php
get_footer();
