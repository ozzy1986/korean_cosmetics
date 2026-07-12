<?php
/**
 * WP-CLI commands.
 *
 * @package Atomy_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atomy_Core_CLI {

	public static function register(): void {
		WP_CLI::add_command( 'atomy import', array( __CLASS__, 'import' ) );
		WP_CLI::add_command( 'atomy telegram:resolve-chat', array( __CLASS__, 'resolve_chat' ) );
		WP_CLI::add_command( 'atomy settings:apply-secrets', array( __CLASS__, 'apply_secrets' ) );
		WP_CLI::add_command( 'atomy seo:enrich', array( __CLASS__, 'seo_enrich' ) );
		WP_CLI::add_command( 'atomy seo:verification', array( __CLASS__, 'seo_verification' ) );
	}

	/**
	 * Import catalog from scraped data directory.
	 *
	 * ## OPTIONS
	 *
	 * [--data=<path>]
	 * : Path to data directory.
	 */
	public static function import( array $args, array $assoc_args ): void {
		$data = $assoc_args['data'] ?? '/var/www/ru-atomy.ru/project/data';
		$result = atomy_core()->importer->import_from_dir( $data );
		if ( isset( $result['error'] ) ) {
			WP_CLI::error( $result['error'] );
		}
		WP_CLI::success( wp_json_encode( $result ) );
	}

	/**
	 * Resolve Telegram chat_id from bot updates.
	 */
	public static function resolve_chat( array $args, array $assoc_args ): void {
		$chat_id = atomy_core()->notifications->resolve_chat_id_from_updates();
		if ( ! $chat_id ) {
			WP_CLI::error( 'No chat_id found. Send /start to the bot first.' );
		}
		WP_CLI::success( 'chat_id=' . $chat_id );
	}

	/**
	 * Apply secrets from VPS-only file (not in repo).
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 */
	public static function apply_secrets( array $args, array $assoc_args ): void {
		$file = $assoc_args['file'] ?? '/root/ru-atomy-secrets.json';
		if ( ! file_exists( $file ) ) {
			WP_CLI::error( 'Secrets file not found: ' . $file );
		}
		$data = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $data ) ) {
			WP_CLI::error( 'Invalid secrets JSON' );
		}
		atomy_core()->settings->save(
			array(
				'telegram_token'   => $data['telegram_token'] ?? '',
				'telegram_chat_id' => $data['telegram_chat_id'] ?? '',
				'recipient_email'  => $data['recipient_email'] ?? '',
				'email_enabled'    => true,
				'telegram_enabled' => true,
				'smtp_enabled'     => ! empty( $data['smtp_enabled'] ),
				'smtp_host'        => $data['smtp_host'] ?? '',
				'smtp_port'        => (int) ( $data['smtp_port'] ?? 587 ),
				'smtp_user'        => $data['smtp_user'] ?? '',
				'smtp_pass'        => $data['smtp_pass'] ?? '',
				'smtp_secure'      => $data['smtp_secure'] ?? 'tls',
				'smtp_from'        => $data['smtp_from'] ?? '',
			)
		);
		WP_CLI::success( 'Secrets applied.' );
	}

	/**
	 * Enrich product SEO: excerpt, description alts, attachment alts.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Preview changes without saving.
	 *
	 * [--force]
	 * : Overwrite existing excerpts.
	 *
	 * [--limit=<n>]
	 * : Limit number of products processed.
	 */
	public static function seo_enrich( array $args, array $assoc_args ): void {
		$dry_run = isset( $assoc_args['dry-run'] );
		$force   = isset( $assoc_args['force'] );
		$limit   = isset( $assoc_args['limit'] ) ? max( 0, (int) $assoc_args['limit'] ) : 0;
		$result  = atomy_core()->seo->enrich_products( $dry_run, $force, $limit );
		WP_CLI::success( wp_json_encode( $result ) );
	}

	/**
	 * Save search engine verification codes.
	 *
	 * ## OPTIONS
	 *
	 * [--google=<code>]
	 * : Google Search Console verification code.
	 *
	 * [--yandex=<code>]
	 * : Yandex Webmaster verification code.
	 */
	public static function seo_verification( array $args, array $assoc_args ): void {
		$google = (string) ( $assoc_args['google'] ?? '' );
		$yandex = (string) ( $assoc_args['yandex'] ?? '' );
		atomy_core()->seo->save_verification_codes( $google, $yandex );
		WP_CLI::success( 'Verification codes saved.' );
	}
}
