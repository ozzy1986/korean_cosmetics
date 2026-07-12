<?php
/**
 * Front page: intro panel with slider, perks, category tiles, product rails.
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
$shop_url = get_permalink( wc_get_page_id( 'shop' ) );
?>
<?php if ( $banners ) : ?>
<h1 class="visually-hidden">Официальный дистрибьютор Atomy в России — каталог и цены</h1>
<section class="hero-slider" data-hero-slider>
	<div class="hero-slider__track">
		<?php foreach ( $banners as $i => $banner ) : ?>
			<?php
			$img  = esc_url( $banner['image'] ?? '' );
			$link = esc_url( $banner['link'] ?? $shop_url );
			if ( ! $img ) {
				continue;
			}
			?>
			<a class="hero-slide<?php echo 0 === $i ? ' is-active' : ''; ?>" href="<?php echo $link; ?>" data-slide>
				<img src="<?php echo $img; ?>" alt="" width="1920" height="440" loading="<?php echo 0 === $i ? 'eager' : 'lazy'; ?>"<?php echo 0 === $i ? ' fetchpriority="high"' : ''; ?> />
			</a>
		<?php endforeach; ?>
		<button type="button" class="hero-slider__btn hero-slider__btn--prev" data-slide-prev aria-label="Назад">&lsaquo;</button>
		<button type="button" class="hero-slider__btn hero-slider__btn--next" data-slide-next aria-label="Вперёд">&rsaquo;</button>
		<div class="hero-slider__dots" data-slide-dots></div>
	</div>
</section>
<?php else : ?>
<section class="hero">
	<div class="container hero__inner">
		<div class="hero__text">
			<span class="hero__eyebrow">Официальный дистрибьютор Atomy</span>
			<h1 class="hero__title">Корейское качество<br>по честной цене</h1>
			<p class="hero__subtitle">Косметика, витамины и товары для дома из Южной Кореи — напрямую от производителя.</p>
			<a class="btn btn--primary hero__cta" href="<?php echo esc_url( $shop_url ); ?>">Перейти в каталог</a>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="container perks">
	<ul class="perks__list">
		<li class="perks__item">
			<span class="perks__icon">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 3h13v13H1zM14 8h4l3 3v5h-7M5.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM18.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>
			</span>
			<span class="perks__text"><strong>Бесплатная доставка</strong><span>до пункта выдачи по всей России</span></span>
		</li>
		<li class="perks__item">
			<span class="perks__icon">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/><path d="M8.5 12l2.5 2.5L16 9.5"/></svg>
			</span>
			<span class="perks__text"><strong>Оригинальная продукция</strong><span>напрямую от Atomy Co., Ltd</span></span>
		</li>
		<li class="perks__item">
			<span class="perks__icon">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0l-7-7A2 2 0 0 1 3 12.2V5a2 2 0 0 1 2-2h7.2a2 2 0 0 1 1.4.6l7 7a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/></svg>
			</span>
			<span class="perks__text"><strong>Цены без наценки</strong><span>экономия до 30% после регистрации</span></span>
		</li>
		<li class="perks__item">
			<span class="perks__icon">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
			</span>
			<span class="perks__text"><strong>Поддержка дистрибьютора</strong><span>поможем с выбором и заказом</span></span>
		</li>
	</ul>
</section>

<?php if ( $home_cats ) : ?>
<section class="container cat-tiles-section">
	<h2 class="section-title">Выбирайте по категориям</h2>
	<div class="cat-tiles-scroll">
		<div class="cat-tiles">
			<?php
			foreach ( $home_cats as $term ) :
				$tile    = function_exists( 'atomy_ru_category_image' ) ? atomy_ru_category_image( $term ) : array( 'url' => '', 'kind' => '' );
				$initial = function_exists( 'mb_substr' ) ? mb_substr( $term->name, 0, 1 ) : substr( $term->name, 0, 1 );
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
		<a class="rail__more" href="<?php echo esc_url( $shop_url ); ?>">Смотреть все</a>
	</div>
	<div class="products-grid">
		<?php echo do_shortcode( '[products limit="8" columns="4" orderby="date" order="ASC"]' ); ?>
	</div>
</section>

<section class="promo-strip">
	<div class="container promo-strip__inner">
		<div class="promo-strip__text">
			<span class="promo-strip__title">Не знаете, с чего начать?</span>
			<span class="promo-strip__sub">Оставьте заявку — поможем с выбором и оформим заказ с бесплатной доставкой.</span>
		</div>
		<a class="btn btn--light" href="<?php echo esc_url( home_url( '/request/' ) ); ?>">Оформить заявку</a>
	</div>
</section>

<section class="rail rail--band">
	<div class="container">
		<div class="rail__head">
			<h2 class="rail__title">Бестселлеры</h2>
			<a class="rail__more" href="<?php echo esc_url( $shop_url ); ?>">Смотреть все</a>
		</div>
		<div class="products-grid">
			<?php echo do_shortcode( '[products limit="8" columns="4" orderby="popularity"]' ); ?>
		</div>
	</div>
</section>

<?php
get_footer();
