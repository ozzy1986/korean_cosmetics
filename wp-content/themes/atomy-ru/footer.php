
</main>
<footer class="site-footer">
	<div class="container site-footer__inner">
		<div class="site-footer__col site-footer__brand">
			<img class="site-footer__logo" src="https://resource.atomy.ru/20260610134910/fo/images/common/CI-white.svg" alt="ATOMY" width="120" height="32" />
			<p>Премиальное качество по абсолютной цене.</p>
		</div>
		<div class="site-footer__col">
			<h4>Компания</h4>
			<p>ООО АТОМИ РУС</p>
			<p>г. Москва, ул. Обручева, дом 23к3, эт. 7</p>
			<p>ОГРН: 1187746655910</p>
			<p>ИНН: 7728435112</p>
		</div>
		<div class="site-footer__col">
			<h4>Поддержка</h4>
			<p>E-mail: atomy_ru@atomypark.com</p>
			<p>Пн-Пт: 08:00-18:00</p>
			<p>Сб: 09:00-18:00</p>
		</div>
		<div class="site-footer__col">
			<h4>Каталог</h4>
			<ul class="site-footer__links">
			<?php
			wp_list_categories(
				array(
					'taxonomy'   => 'product_cat',
					'title_li'   => '',
					'number'     => 6,
					'hide_empty' => true,
				)
			);
			?>
			</ul>
		</div>
	</div>
	<div class="site-footer__disclaimer">
		<div class="container">
			<p>Данный сайт является сайтом партнёра-дистрибьютора компании Atomy.</p>
			<p>Сайт информационный, не магазин: заказы оформляются через дистрибьютора.</p>
		</div>
	</div>
	<div class="site-footer__bottom">
		<div class="container site-footer__bottom-inner">
			<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Официальный дистрибьютор ATOMY в России</span>
			<nav class="site-footer__legal">
				<a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>">Пользовательское соглашение</a>
				<span class="site-footer__legal-sep">|</span>
				<a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>">Политика конфиденциальности</a>
			</nav>
		</div>
	</div>
</footer>
<?php if ( empty( $_COOKIE['atomy_cookie_consent'] ) ) : ?>
<div class="cookie-notice" data-cookie-notice role="dialog" aria-live="polite" aria-label="Уведомление о cookie" aria-hidden="true">
	<p class="cookie-notice__text">Мы используем файлы cookie для работы сайта и корзины. Продолжая пользоваться сайтом, вы соглашаетесь с <a href="<?php echo esc_url( home_url( '/privacy/#cookies' ) ); ?>">политикой конфиденциальности</a>.</p>
	<button type="button" class="cookie-notice__btn" data-cookie-accept>Принять</button>
</div>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
