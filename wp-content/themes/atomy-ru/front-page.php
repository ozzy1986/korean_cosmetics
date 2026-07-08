<?php
/**
 * Front page: hero slider, category tiles, product rails.
 *
 * @package Atomy_RU
 */

get_header();

$home_cats = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		'number'     => 12,
	)
);
$home_cats = is_wp_error( $home_cats ) ? array() : $home_cats;
$banners   = function_exists( 'atomy_ru_homepage_banners' ) ? atomy_ru_homepage_banners() : array();
if ( $banners ) {
	$banners = array_slice( $banners, 0, 8 );
}
?>
<?php if ( $banners ) : ?>
<section class="hero-slider" data-hero-slider>
	<div class="hero-slider__track">
		<?php foreach ( $banners as $i => $banner ) : ?>
			<?php
			$img  = esc_url( $banner['image'] ?? '' );
			$link = esc_url( $banner['link'] ?? get_permalink( wc_get_page_id( 'shop' ) ) );
			if ( ! $img ) {
				continue;
			}
			?>
			<a class="hero-slide<?php echo 0 === $i ? ' is-active' : ''; ?>" href="<?php echo $link; ?>" data-slide>
				<img src="<?php echo $img; ?>" alt="" loading="<?php echo 0 === $i ? 'eager' : 'lazy'; ?>" />
			</a>
		<?php endforeach; ?>
	</div>
	<button type="button" class="hero-slider__btn hero-slider__btn--prev" data-slide-prev aria-label="Назад">&lsaquo;</button>
	<button type="button" class="hero-slider__btn hero-slider__btn--next" data-slide-next aria-label="Вперёд">&rsaquo;</button>
	<div class="hero-slider__dots" data-slide-dots></div>
</section>
<?php else : ?>
<section class="hero">
	<div class="container hero__inner">
		<div class="hero__text">
			<span class="hero__eyebrow">ATOMY RUSSIA</span>
			<h1 class="hero__title">Премиальное качество<br>по абсолютной цене</h1>
			<p class="hero__subtitle">Косметика, здоровье и товары для дома от мировых лабораторий Atomy.</p>
			<a class="btn btn--primary hero__cta" href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">Перейти в каталог</a>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $home_cats ) : ?>
<section class="container cat-tiles-section">
	<div class="cat-tiles-scroll">
		<div class="cat-tiles">
			<?php
			foreach ( $home_cats as $term ) :
				$tile    = function_exists( 'atomy_ru_category_image' ) ? atomy_ru_category_image( $term ) : array( 'url' => '', 'kind' => '' );
				$initial = function_exists( 'mb_substr' ) ? mb_substr( $term->name, 0, 1 ) : substr( $term->name, 0, 1 );
				$kind    = $tile['kind'] ? $tile['kind'] : 'none';
				?>
				<a class="cat-tile" href="<?php echo esc_url( get_term_link( $term ) ); ?>">
					<?php if ( 'icon' === $tile['kind'] ) : ?>
						<span class="cat-tile__icon cat-tile__icon--icon">
							<i class="cat-tile__glyph" style="--glyph:url('<?php echo esc_url( $tile['url'] ); ?>')" aria-hidden="true"></i>
						</span>
					<?php elseif ( 'photo' === $tile['kind'] ) : ?>
						<span class="cat-tile__icon cat-tile__icon--photo">
							<img src="<?php echo esc_url( $tile['url'] ); ?>" alt="<?php echo esc_attr( $term->name ); ?>" loading="lazy" />
						</span>
					<?php else : ?>
						<span class="cat-tile__icon cat-tile__icon--none">
							<span class="cat-tile__initial"><?php echo esc_html( $initial ); ?></span>
						</span>
					<?php endif; ?>
					<span class="cat-tile__name"><?php echo esc_html( $term->name ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="container rail">
	<div class="rail__head">
		<h2 class="rail__title">Первый шаг в Атоми: с чего начать?</h2>
		<a class="rail__more" href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">Смотреть все</a>
	</div>
	<div class="products-grid">
		<?php echo do_shortcode( '[products limit="8" columns="4" orderby="date" order="ASC"]' ); ?>
	</div>
</section>

<section class="promo-strip">
	<div class="container promo-strip__inner">
		<span>Электронный каталог всегда под рукой</span>
		<a class="btn btn--ghost" href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">Открыть каталог</a>
	</div>
</section>

<section class="container rail">
	<div class="rail__head">
		<h2 class="rail__title">Бестселлеры</h2>
		<a class="rail__more" href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">Смотреть все</a>
	</div>
	<div class="products-grid">
		<?php echo do_shortcode( '[products limit="8" columns="4" orderby="popularity"]' ); ?>
	</div>
</section>

<?php
get_footer();
