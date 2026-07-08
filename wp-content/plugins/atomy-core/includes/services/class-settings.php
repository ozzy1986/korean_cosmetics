<?php
/**
 * Plugin settings (secrets in WP options).
 *
 * @package Atomy_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atomy_Core_Settings {

	private const OPTION_KEY = 'atomy_core_settings';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function get_all(): array {
		$defaults = array(
			'recipient_email'  => get_option( 'admin_email' ),
			'telegram_token'   => '',
			'telegram_chat_id' => '',
			'email_enabled'    => true,
			'telegram_enabled' => true,
			'smtp_enabled'     => false,
			'smtp_host'        => '',
			'smtp_port'        => 587,
			'smtp_user'        => '',
			'smtp_pass'        => '',
			'smtp_secure'      => 'tls',
			'smtp_from'        => '',
		);
		$stored = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	public function save( array $data ): void {
		$current = $this->get_all();
		$merged  = array_merge( $current, $data );
		update_option( self::OPTION_KEY, $merged, false );
	}

	public function get_recipient_email(): string {
		return (string) $this->get_all()['recipient_email'];
	}

	public function get_telegram_token(): string {
		return (string) $this->get_all()['telegram_token'];
	}

	public function get_telegram_chat_id(): string {
		return (string) $this->get_all()['telegram_chat_id'];
	}

	public function set_telegram_chat_id( string $chat_id ): void {
		$this->save( array( 'telegram_chat_id' => $chat_id ) );
	}

	public function email_enabled(): bool {
		return (bool) $this->get_all()['email_enabled'];
	}

	public function telegram_enabled(): bool {
		return (bool) $this->get_all()['telegram_enabled'];
	}

	public function smtp_enabled(): bool {
		return (bool) $this->get_all()['smtp_enabled'];
	}

	public function get_smtp_host(): string {
		return (string) $this->get_all()['smtp_host'];
	}

	public function get_smtp_port(): int {
		return (int) $this->get_all()['smtp_port'];
	}

	public function get_smtp_user(): string {
		return (string) $this->get_all()['smtp_user'];
	}

	public function get_smtp_pass(): string {
		return (string) $this->get_all()['smtp_pass'];
	}

	public function get_smtp_secure(): string {
		return (string) $this->get_all()['smtp_secure'];
	}

	public function get_smtp_from(): string {
		return (string) $this->get_all()['smtp_from'];
	}

	public function add_menu(): void {
		add_options_page(
			'Atomy Core',
			'Atomy Core',
			'manage_options',
			'atomy-core',
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting( 'atomy_core_settings_group', self::OPTION_KEY, array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		return array(
			'recipient_email'  => sanitize_email( $input['recipient_email'] ?? '' ),
			'telegram_token'   => sanitize_text_field( $input['telegram_token'] ?? '' ),
			'telegram_chat_id' => sanitize_text_field( $input['telegram_chat_id'] ?? '' ),
			'email_enabled'    => ! empty( $input['email_enabled'] ),
			'telegram_enabled' => ! empty( $input['telegram_enabled'] ),
			'smtp_enabled'     => ! empty( $input['smtp_enabled'] ),
			'smtp_host'        => sanitize_text_field( $input['smtp_host'] ?? '' ),
			'smtp_port'        => absint( $input['smtp_port'] ?? 587 ),
			'smtp_user'        => sanitize_text_field( $input['smtp_user'] ?? '' ),
			'smtp_pass'        => sanitize_text_field( $input['smtp_pass'] ?? '' ),
			'smtp_secure'      => sanitize_text_field( $input['smtp_secure'] ?? 'tls' ),
			'smtp_from'        => sanitize_email( $input['smtp_from'] ?? '' ),
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = $this->get_all();
		?>
		<div class="wrap">
			<h1>Atomy Core Settings</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'atomy_core_settings_group' ); ?>
				<table class="form-table">
					<tr>
						<th>Email получателя</th>
						<td><input type="email" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[recipient_email]" value="<?php echo esc_attr( $settings['recipient_email'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th>Telegram bot token</th>
						<td><input type="password" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[telegram_token]" value="<?php echo esc_attr( $settings['telegram_token'] ); ?>" class="regular-text" autocomplete="new-password" /></td>
					</tr>
					<tr>
						<th>Telegram chat_id</th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[telegram_chat_id]" value="<?php echo esc_attr( $settings['telegram_chat_id'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th>Email enabled</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email_enabled]" value="1" <?php checked( $settings['email_enabled'] ); ?> /> Включено</label></td>
					</tr>
					<tr>
						<th>Telegram enabled</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[telegram_enabled]" value="1" <?php checked( $settings['telegram_enabled'] ); ?> /> Включено</label></td>
					</tr>
					<tr><th colspan="2"><h2>SMTP (Brevo / Gmail)</h2></th></tr>
					<tr>
						<th>SMTP enabled</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[smtp_enabled]" value="1" <?php checked( $settings['smtp_enabled'] ); ?> /> Включено</label></td>
					</tr>
					<tr>
						<th>SMTP host</th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[smtp_host]" value="<?php echo esc_attr( $settings['smtp_host'] ); ?>" class="regular-text" placeholder="smtp-relay.brevo.com" /></td>
					</tr>
					<tr>
						<th>SMTP port</th>
						<td><input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[smtp_port]" value="<?php echo esc_attr( (string) $settings['smtp_port'] ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th>SMTP user</th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[smtp_user]" value="<?php echo esc_attr( $settings['smtp_user'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th>SMTP password</th>
						<td><input type="password" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[smtp_pass]" value="<?php echo esc_attr( $settings['smtp_pass'] ); ?>" class="regular-text" autocomplete="new-password" /></td>
					</tr>
					<tr>
						<th>SMTP secure</th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[smtp_secure]" value="<?php echo esc_attr( $settings['smtp_secure'] ); ?>" class="regular-text" placeholder="tls" /></td>
					</tr>
					<tr>
						<th>SMTP From</th>
						<td><input type="email" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[smtp_from]" value="<?php echo esc_attr( $settings['smtp_from'] ); ?>" class="regular-text" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
