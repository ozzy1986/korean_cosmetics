<?php
/**
 * Email + Telegram notifications.
 *
 * @package Atomy_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atomy_Core_Notifications {

	public function register(): void {
		add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
	}

	/**
	 * Format price as plain text (no HTML).
	 */
	private function format_plain_price( float $amount ): string {
		if ( $amount <= 0 ) {
			return '0 ₽';
		}
		return number_format( $amount, 0, '.', ' ' ) . ' ₽';
	}

	public function configure_smtp( $phpmailer ): void {
		if ( ! atomy_core()->settings->smtp_enabled() ) {
			return;
		}
		$host = atomy_core()->settings->get_smtp_host();
		if ( '' === $host ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host       = $host;
		$phpmailer->Port       = atomy_core()->settings->get_smtp_port();
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Username   = atomy_core()->settings->get_smtp_user();
		$phpmailer->Password   = atomy_core()->settings->get_smtp_pass();
		$secure                = atomy_core()->settings->get_smtp_secure();
		$phpmailer->SMTPSecure = $secure ?: 'tls';
		$from                  = atomy_core()->settings->get_smtp_from();
		if ( $from ) {
			$phpmailer->setFrom( $from, 'ru-atomy.ru' );
		}
	}

	public function dispatch( array $payload ): array {
		return array(
			'email'    => $this->send_email( $payload ),
			'telegram' => $this->send_telegram( $payload ),
		);
	}

	public function format_message( array $payload ): string {
		$customer = $payload['customer'];
		$lines    = array(
			'Новая заявка #' . $payload['id'],
			'Имя: ' . $customer['name'],
			'Email: ' . $customer['email'],
			'Телефон: ' . $customer['phone'],
			'Дата рождения: ' . ( $customer['birthdate'] ?? '—' ),
			'Город: ' . $customer['city'],
			'',
			'Товары:',
		);
		foreach ( $payload['cart'] as $item ) {
			$lines[] = sprintf(
				'- %s x%d | до рег: %s | после рег: %s | PV: %s',
				wp_strip_all_tags( (string) $item['name'] ),
				(int) $item['qty'],
				$this->format_plain_price( (float) $item['reg_price'] ),
				$this->format_plain_price( (float) $item['member_price'] ),
				number_format_i18n( (float) $item['pv'], 0 )
			);
		}
		return wp_strip_all_tags( implode( "\n", $lines ) );
	}

	private function send_email( array $payload ): bool {
		if ( ! atomy_core()->settings->email_enabled() ) {
			return false;
		}
		$to      = atomy_core()->settings->get_recipient_email();
		$subject = 'Новая заявка с ru-atomy.ru #' . $payload['id'];
		$body    = $this->format_message( $payload );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		return (bool) wp_mail( $to, $subject, $body, $headers );
	}

	private function send_telegram( array $payload ): bool {
		if ( ! atomy_core()->settings->telegram_enabled() ) {
			return false;
		}
		$token  = atomy_core()->settings->get_telegram_token();
		$chat_id = atomy_core()->settings->get_telegram_chat_id();
		if ( '' === $token || '' === $chat_id ) {
			return false;
		}
		$url  = 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage';
		$body = array(
			'chat_id' => $chat_id,
			'text'    => $this->format_message( $payload ),
		);
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	public function resolve_chat_id_from_updates(): ?string {
		$token = atomy_core()->settings->get_telegram_token();
		if ( '' === $token ) {
			return null;
		}
		$url      = 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/getUpdates';
		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['result'] ) || ! is_array( $data['result'] ) ) {
			return null;
		}
		$last = end( $data['result'] );
		$chat_id = $last['message']['chat']['id'] ?? $last['callback_query']['message']['chat']['id'] ?? null;
		if ( $chat_id ) {
			atomy_core()->settings->set_telegram_chat_id( (string) $chat_id );
		}
		return $chat_id ? (string) $chat_id : null;
	}
}
