<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
$atomy_cats = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		'orderby'    => 'count',
		'order'      => 'DESC',
	)
);
$atomy_cats = is_wp_error( $atomy_cats ) ? array() : $atomy_cats;
?>
<header class="site-header">
	<div class="topbar">
		<div class="container topbar__inner">
			<span class="topbar__note">
				<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 3h13v13H1zM14 8h4l3 3v5h-7M5.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM18.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>
				Бесплатная доставка по России
			</span>
			<nav class="topbar__links">
				<a href="<?php echo esc_url( home_url( '/request/' ) ); ?>">Оформить заявку</a>
				<a href="mailto:atomy_ru@atomypark.com">Поддержка</a>
			</nav>
		</div>
	</div>
	<div class="header-bar">
		<div class="container header-bar__inner">
			<a class="site-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<img class="site-logo__img" src="https://resource.atomy.ru/20260610134910/fo/images/common/CI-blue_68.svg" alt="ATOMY" width="120" height="32" />
			</a>
			<form class="header-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<input type="search" name="s" placeholder="Введите запрос, чтобы найти" value="<?php echo esc_attr( get_search_query() ); ?>" aria-label="Поиск" />
				<input type="hidden" name="post_type" value="product" />
				<button type="submit" aria-label="Найти">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				</button>
			</form>
			<div class="header-actions">
				<a class="header-wish" href="<?php echo esc_url( function_exists( 'atomy_wishlist_url' ) ? atomy_wishlist_url() : home_url( '/wishlist/' ) ); ?>" aria-label="Избранное">
					<span class="header-wish__icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20s-6.5-4-9-8.2C1.6 9 2.4 5.9 5.2 5.1 7.1 4.5 9 5.3 10 6.9l2 .1 2-.1c1-1.6 2.9-2.4 4.8-1.8 2.8.8 3.6 3.9 2.2 6.7C18.5 16 12 20 12 20z"/></svg>
						<span class="header-wish__count is-empty" data-wish-count>0</span>
					</span>
					<span class="header-wish__label">Избранное</span>
				</a>
				<a class="header-cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="Корзина">
					<span class="header-cart__icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="21" r="1.5"/><circle cx="18" cy="21" r="1.5"/><path d="M2 3h2l2.4 12.2a2 2 0 0 0 2 1.6h8.7a2 2 0 0 0 2-1.6L23 7H6"/></svg>
						<span class="header-cart__count"><?php echo esc_html( (string) ( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ) ); ?></span>
					</span>
					<span class="header-cart__label">Корзина</span>
				</a>
			</div>
		</div>
	</div>
	<nav class="header-nav">
		<div class="container header-nav__inner">
			<div class="nav-mega" data-mega>
				<button type="button" class="nav-toggle" data-menu-toggle aria-expanded="false" aria-label="Все категории">
					<span class="nav-toggle__bars"><span></span><span></span><span></span></span>
					<em>Все категории</em>
				</button>
				<div class="nav-mega__panel" data-mega-panel>
					<?php foreach ( $atomy_cats as $term ) : ?>
						<a class="nav-mega__item" href="<?php echo esc_url( get_term_link( $term ) ); ?>">
							<span class="nav-mega__name"><?php echo esc_html( $term->name ); ?></span>
							<span class="nav-mega__count"><?php echo esc_html( (string) $term->count ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="category-nav__wrap">
				<ul class="category-nav">
					<?php foreach ( $atomy_cats as $term ) : ?>
						<li><a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<a class="header-nav__all" href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">Все товары</a>
		</div>
	</nav>
</header>
<main class="site-main">
