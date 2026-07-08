<?php
/**
 * Order request CPT and form handler.
 *
 * @package Atomy_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atomy_Core_Requests {

	public function register(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_shortcode( 'atomy_request_form', array( $this, 'render_form' ) );
		add_action( 'wp_ajax_atomy_submit_request', array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_atomy_submit_request', array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_atomy_set_qty', array( $this, 'handle_set_qty' ) );
		add_action( 'wp_ajax_nopriv_atomy_set_qty', array( $this, 'handle_set_qty' ) );
	}

	public static function register_post_type(): void {
		register_post_type(
			'atomy_request',
			array(
				'labels'       => array(
					'name'          => 'Заявки',
					'singular_name' => 'Заявка',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-email-alt',
				'supports'     => array( 'title' ),
				'capability_type' => 'post',
			)
		);
	}

	public function render_form(): string {
		if ( ! WC()->cart ) {
			return '<p>Корзина недоступна.</p>';
		}

		ob_start();
		?>
		<div class="atomy-request-wrap">
			<h2>Оформление заявки</h2>
			<?php if ( WC()->cart->is_empty() ) : ?>
				<p>Корзина пуста. <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Перейти в каталог</a></p>
			<?php else : ?>
				<div class="atomy-request-layout">
					<form id="atomy-request-form" class="atomy-request-form" novalidate>
						<input type="hidden" name="atomy_hp" value="" autocomplete="off" tabindex="-1" />
						<div class="atomy-field">
							<label for="atomy_name">Имя *</label>
							<input type="text" id="atomy_name" name="name" required maxlength="120" />
							<span class="atomy-field__error" aria-live="polite">Укажите имя</span>
						</div>
						<div class="atomy-field">
							<label for="atomy_email">Email *</label>
							<input type="email" id="atomy_email" name="email" required maxlength="120" />
							<span class="atomy-field__error" aria-live="polite">Укажите корректный email, например name@mail.ru</span>
						</div>
						<div class="atomy-field">
							<label for="atomy_phone">Телефон *</label>
							<input type="tel" id="atomy_phone" name="phone" required maxlength="40" />
							<span class="atomy-field__error" aria-live="polite">Укажите телефон</span>
						</div>
						<div class="atomy-field">
							<label for="atomy_birthdate">Дата рождения *</label>
							<div class="atomy-field__control" data-birthdate-control>
								<input type="text" id="atomy_birthdate" name="birthdate" required inputmode="numeric" autocomplete="bday" placeholder="ДД.ММ.ГГГГ" maxlength="10" />
								<button type="button" class="atomy-cal-toggle" data-cal-toggle aria-expanded="false" aria-label="Выбрать дату в календаре">
									<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 2.5v3M17 2.5v3M3.5 9h17M5 4.5h14A1.5 1.5 0 0 1 20.5 6v13a1.5 1.5 0 0 1-1.5 1.5H5A1.5 1.5 0 0 1 3.5 19V6A1.5 1.5 0 0 1 5 4.5Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
								</button>
							</div>
							<span class="atomy-field__error" aria-live="polite">Укажите дату рождения в формате ДД.ММ.ГГГГ</span>
						</div>
						<div class="atomy-field">
							<label for="atomy_city">Город доставки *</label>
							<input type="text" id="atomy_city" name="city" required maxlength="120" />
							<span class="atomy-field__error" aria-live="polite">Укажите город доставки</span>
						</div>
						<button type="submit" class="atomy-btn atomy-btn--primary">Отправить заявку</button>
						<div class="atomy-request-message" aria-live="polite"></div>
					</form>
					<?php $this->render_cart_summary(); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Cart summary styled like the main cart page (shop_table).
	 */
	private function render_cart_summary(): void {
		$cart = WC()->cart;
		?>
		<div class="atomy-request-cart">
			<div class="atomy-request-cart__head">
				<h3 class="atomy-request-cart__title">Ваш заказ</h3>
				<a class="atomy-request-cart__edit" href="<?php echo esc_url( wc_get_cart_url() ); ?>">Изменить корзину</a>
			</div>
			<table class="shop_table shop_table_responsive atomy-request-cart-table">
				<thead>
					<tr>
						<th class="product-name">Товар</th>
						<th class="product-price">Цена</th>
						<th class="product-quantity">Кол-во</th>
						<th class="product-subtotal">Сумма</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) :
						$product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
						if ( ! $product || ! $product->exists() || $cart_item['quantity'] <= 0 ) {
							continue;
						}
						$permalink = $product->is_visible() ? $product->get_permalink( $cart_item ) : '';
						$thumbnail = apply_filters(
							'woocommerce_cart_item_thumbnail',
							$product->get_image( 'woocommerce_thumbnail' ),
							$cart_item,
							$cart_item_key
						);
						$member    = atomy_core()->prices->get_member_price( $product );
						$line_sum  = $member * (int) $cart_item['quantity'];
						?>
						<tr class="cart_item" data-cart-key="<?php echo esc_attr( $cart_item_key ); ?>">
							<td class="product-name" data-title="Товар">
								<div class="atomy-request-cart__product">
									<div class="atomy-request-cart__thumb">
										<?php if ( $permalink ) : ?>
											<a href="<?php echo esc_url( $permalink ); ?>"><?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
										<?php else : ?>
											<?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<?php endif; ?>
									</div>
									<div class="atomy-request-cart__details">
										<?php if ( $permalink ) : ?>
											<a class="atomy-request-cart__name" href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
										<?php else : ?>
											<span class="atomy-request-cart__name"><?php echo esc_html( $product->get_name() ); ?></span>
										<?php endif; ?>
										<?php if ( function_exists( 'atomy_pv_html' ) ) : ?>
											<div class="atomy-request-cart__pv"><?php echo atomy_pv_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
										<?php endif; ?>
									</div>
								</div>
							</td>
							<td class="product-price" data-title="Цена">
								<?php echo $this->render_cart_item_prices( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</td>
							<td class="product-quantity" data-title="Кол-во">
								<div class="atomy-qty" data-cart-key="<?php echo esc_attr( $cart_item_key ); ?>">
									<button type="button" class="atomy-qty__btn" data-qty-minus aria-label="Уменьшить количество">&minus;</button>
									<span class="atomy-qty__num"><?php echo esc_html( (string) $cart_item['quantity'] ); ?></span>
									<button type="button" class="atomy-qty__btn" data-qty-plus aria-label="Увеличить количество">+</button>
								</div>
							</td>
							<td class="product-subtotal" data-title="Сумма">
								<strong class="atomy-request-cart__line-total" data-line-total><?php echo esc_html( atomy_core()->prices->format_price( $line_sum ) ); ?></strong>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="4" class="atomy-request-cart__total" data-title="Итого">
							<span class="atomy-request-cart__total-inner">
								<span class="atomy-request-cart__total-label">Итого</span>
								<span class="atomy-request-cart__total-sum" data-cart-total><?php echo wp_kses_post( $cart->get_cart_subtotal() ); ?></span>
							</span>
						</td>
					</tr>
				</tfoot>
			</table>
		</div>
		<?php
	}

	/**
	 * Compact reg/member prices for the request cart table.
	 */
	private function render_cart_item_prices( WC_Product $product ): string {
		$prices = atomy_core()->prices;
		$reg    = $prices->get_reg_price( $product );
		$member = $prices->get_member_price( $product );
		$html   = '<div class="atomy-request-cart__prices">';
		if ( $reg > 0 && $reg !== $member ) {
			$html .= '<div class="atomy-request-cart__price atomy-request-cart__price--reg">';
			$html .= '<s>' . esc_html( $prices->format_price( $reg ) ) . '</s>';
			$html .= '</div>';
		}
		if ( $member > 0 ) {
			$html .= '<div class="atomy-request-cart__price atomy-request-cart__price--member">';
			$html .= '<strong>' . esc_html( $prices->format_price( $member ) ) . '</strong>';
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	public function handle_submit(): void {
		check_ajax_referer( 'atomy_request', 'nonce' );

		if ( ! empty( $_POST['atomy_hp'] ) ) {
			wp_send_json_error( array( 'message' => 'Spam detected.' ), 400 );
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( $this->is_rate_limited( $ip ) ) {
			wp_send_json_error( array( 'message' => 'Слишком много запросов. Попробуйте позже.' ), 429 );
		}

		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			wp_send_json_error( array( 'message' => 'Корзина пуста.' ), 400 );
		}

		$name      = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$phone     = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$birthdate = sanitize_text_field( wp_unslash( $_POST['birthdate'] ?? '' ) );
		$city      = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );

		if ( '' === $name || '' === $email || '' === $phone || '' === $birthdate || '' === $city || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Заполните все обязательные поля корректно.' ), 400 );
		}

		if ( ! $this->is_valid_birthdate( $birthdate ) ) {
			wp_send_json_error( array( 'message' => 'Укажите корректную дату рождения в формате ДД.ММ.ГГГГ.' ), 400 );
		}

		$cart_snapshot = $this->build_cart_snapshot();
		$post_id       = wp_insert_post(
			array(
				'post_type'   => 'atomy_request',
				'post_status' => 'publish',
				'post_title'  => sprintf( 'Заявка от %s (%s)', $name, current_time( 'Y-m-d H:i' ) ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Ошибка сохранения заявки.' ), 500 );
		}

		update_post_meta( $post_id, '_atomy_customer', compact( 'name', 'email', 'phone', 'birthdate', 'city' ) );
		update_post_meta( $post_id, '_atomy_cart', $cart_snapshot );
		update_post_meta( $post_id, '_atomy_ip', $ip );

		$payload = array(
			'id'       => $post_id,
			'customer' => compact( 'name', 'email', 'phone', 'birthdate', 'city' ),
			'cart'     => $cart_snapshot,
		);

		$results = atomy_core()->notifications->dispatch( $payload );
		WC()->cart->empty_cart();
		$this->mark_rate_limit( $ip );

		wp_send_json_success(
			array(
				'message' => 'Заявка отправлена. Мы свяжемся с вами в ближайшее время.',
				'notify'  => $results,
			)
		);
	}

	/**
	 * AJAX: set cart item quantity (0 removes the item). Accepts cart_key or product_id.
	 */
	public function handle_set_qty(): void {
		check_ajax_referer( 'atomy_request', 'nonce' );

		$cart = WC()->cart;
		if ( ! $cart ) {
			wp_send_json_error( array( 'message' => 'Корзина недоступна.' ), 400 );
		}

		$key        = sanitize_text_field( wp_unslash( $_POST['cart_key'] ?? '' ) );
		$product_id = absint( $_POST['product_id'] ?? 0 );
		$qty        = max( 0, absint( $_POST['qty'] ?? 0 ) );

		if ( '' !== $key && ! $cart->get_cart_item( $key ) ) {
			$key = '';
		}
		if ( '' === $key && $product_id ) {
			foreach ( $cart->get_cart() as $k => $item ) {
				if ( (int) $item['product_id'] === $product_id ) {
					$key = $k;
					break;
				}
			}
		}

		if ( '' === $key ) {
			if ( $product_id && $qty > 0 ) {
				$added = $cart->add_to_cart( $product_id, $qty );
				if ( ! $added ) {
					wp_send_json_error( array( 'message' => 'Не удалось добавить товар.' ), 400 );
				}
				$key = $added;
			} elseif ( $qty > 0 ) {
				wp_send_json_error( array( 'message' => 'Товар не найден в корзине.' ), 404 );
			}
		} elseif ( $qty <= 0 ) {
			$cart->remove_cart_item( $key );
		} else {
			$cart->set_quantity( $key, $qty, false );
		}

		$cart->calculate_totals();

		$line_total = '';
		$item       = '' !== $key ? $cart->get_cart_item( $key ) : false;
		if ( $qty > 0 && $item && $item['data'] instanceof WC_Product ) {
			$member     = atomy_core()->prices->get_member_price( $item['data'] );
			$line_total = atomy_core()->prices->format_price( $member * $qty );
		}

		wp_send_json_success(
			array(
				'qty'        => $qty,
				'cart_key'   => $key,
				'removed'    => $qty <= 0,
				'line_total' => $line_total,
				'cart_total' => $cart->get_cart_subtotal(),
				'cart_count' => $cart->get_cart_contents_count(),
				'cart_empty' => $cart->is_empty(),
			)
		);
	}

	/**
	 * Validate a birthdate in ДД.ММ.ГГГГ format: real calendar date, not in the future, max 120 years back.
	 */
	private function is_valid_birthdate( string $value ): bool {
		if ( ! preg_match( '/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $m ) ) {
			return false;
		}
		if ( ! checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ) {
			return false;
		}
		$date = DateTimeImmutable::createFromFormat( '!d.m.Y', $value, wp_timezone() );
		if ( ! $date ) {
			return false;
		}
		$now = new DateTimeImmutable( 'now', wp_timezone() );
		$min = $now->modify( '-120 years' );
		return $date <= $now && $date >= $min;
	}

	private function build_cart_snapshot(): array {
		$items = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			if ( ! $product ) {
				continue;
			}
			$items[] = array(
				'sku'          => $product->get_sku(),
				'name'         => $product->get_name(),
				'qty'          => $cart_item['quantity'],
				'reg_price'    => atomy_core()->prices->get_reg_price( $product ),
				'member_price' => atomy_core()->prices->get_member_price( $product ),
				'pv'           => atomy_core()->prices->get_pv( $product ),
			);
		}
		return $items;
	}

	private function is_rate_limited( string $ip ): bool {
		if ( '' === $ip ) {
			return false;
		}
		$key  = 'atomy_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		return $hits >= 5;
	}

	private function mark_rate_limit( string $ip ): void {
		if ( '' === $ip ) {
			return;
		}
		$key  = 'atomy_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		set_transient( $key, $hits + 1, HOUR_IN_SECONDS );
	}
}
