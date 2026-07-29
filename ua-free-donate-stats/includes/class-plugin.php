<?php
namespace UAFree\DonateStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	public const OPTION = 'uafree_donate_stats_settings';
	public const PAGE_SLUG = 'ua-free-donate-stats';
	public const CLEANUP_HOOK = 'uafree_donate_stats_cleanup';
	public const EXPORT_CSV_ACTION = 'uafree_donate_stats_export_csv';
	public const EXPORT_JSON_ACTION = 'uafree_donate_stats_export_json';
	public const RESET_ACTION = 'uafree_donate_stats_reset';
	public const ROTATE_SECRET_ACTION = 'uafree_donate_stats_rotate_secret';
	public const RESET_PHRASE = 'DELETE DONATION STATS';
	public const LAST_CONFIRMATION_OPTION = 'uafree_donate_stats_last_confirmation_at';
	public const SETTINGS_VERSION = 3;

	public static function init(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup_old_data' ) );
		add_filter( 'uafree_donate_stats_public_status', array( Tracker::class, 'public_status' ) );

		Tracker::init();
		Admin::init();
	}

	public static function load_textdomain(): void {
		load_plugin_textdomain(
			'ua-free-donate-stats',
			false,
			dirname( plugin_basename( UAFREE_DONATE_STATS_FILE ) ) . '/languages'
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'settings_version'    => self::SETTINGS_VERSION,
			'enabled'             => 0,
			'retention_days'      => 90,
			'exclude_admins'      => 1,
			'session_minutes'     => 30,
			'tracked_page_ids'    => array(),
			'tracked_paths'       => array(),
			'copy_selector'       => '[data-uafree-copy], [class*="copy-this-"]',
			'donate_selector'     => '[data-uafree-donate]',
			'payment_selector'    => 'a[data-uafree-payment], .uafree-payment-link',
			'success_selector'    => '[data-uafree-donation-success]',
			'payment_hosts'       => array(),
			'allow_client_success'=> 0,
			'confirmation_mode'   => 'webhook',
			'confirmation_secret' => '',
			'ad_account_mode'     => 'none',
			'data_layer_enabled'  => 0,
			'data_layer_event'    => 'uafree_donation_event',
			'consent_gate'        => 'external_only',
			'consent_category'    => 'analytics',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function settings(): array {
		$current = get_option( self::OPTION, array() );
		$current = is_array( $current ) ? $current : array();
		$value = wp_parse_args( $current, self::defaults() );

		$value['tracked_page_ids'] = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', (array) $value['tracked_page_ids'] )
				)
			)
		);
		$value['tracked_paths'] = array_values( array_filter( (array) $value['tracked_paths'], 'is_string' ) );
		$value['payment_hosts'] = array_values( array_filter( (array) $value['payment_hosts'], 'is_string' ) );
		$mode = sanitize_key( (string) ( $value['confirmation_mode'] ?? 'webhook' ) );
		$value['confirmation_mode'] = in_array( $mode, array( 'webhook', 'client_marker', 'none' ), true )
			? $mode
			: 'webhook';
		$secret = strtolower( trim( (string) ( $value['confirmation_secret'] ?? '' ) ) );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $secret ) ) {
			$secret = self::new_confirmation_secret();
			$value['confirmation_secret'] = $secret;
			update_option( self::OPTION, array_merge( $current, array( 'confirmation_secret' => $secret ) ), false );
		}

		return $value;
	}

	public static function activate(): void {
		Storage::install_schema();

		if ( false === get_option( self::OPTION, false ) ) {
			$defaults = self::defaults();
			$defaults['confirmation_secret'] = self::new_confirmation_secret();
			add_option( self::OPTION, $defaults, '', false );
		}

		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}

		self::maybe_upgrade();
	}

	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( self::CLEANUP_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CLEANUP_HOOK );
		}
	}

	public static function maybe_upgrade(): void {
		if ( Storage::DB_VERSION !== (string) get_option( 'uafree_donate_stats_db_version', '' ) ) {
			Storage::install_schema();
		}

		$current = get_option( self::OPTION, array() );
		if ( false === $current ) {
			return;
		}
		$current = is_array( $current ) ? $current : array();

		if ( (int) ( $current['settings_version'] ?? 0 ) >= self::SETTINGS_VERSION ) {
			return;
		}

		/*
		 * Old data remains readable in the original tables. Site-specific tracked
		 * pages and paths are intentionally not guessed here. A site migration
		 * bridge can map them explicitly without shipping one site's assumptions
		 * to every public installation.
		 */
		$migrated = wp_parse_args( $current, self::defaults() );
		$migrated['settings_version'] = self::SETTINGS_VERSION;
		$migrated['tracked_page_ids'] = array();
		$migrated['tracked_paths'] = array();
		$migrated['data_layer_enabled'] = 0;
		$migrated['ad_account_mode'] = 'none';
		$migrated['confirmation_mode'] = 'webhook';
		if ( empty( $migrated['confirmation_secret'] ) ) {
			$migrated['confirmation_secret'] = self::new_confirmation_secret();
		}

		update_option( self::OPTION, self::sanitize_settings( $migrated ), false );
	}

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public static function sanitize_settings( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$defaults = self::defaults();

		$page_ids = array_slice(
			array_values(
				array_unique(
					array_filter(
						array_map( 'absint', (array) ( $input['tracked_page_ids'] ?? array() ) )
					)
				)
			),
			0,
			100
		);

		$paths_raw = is_array( $input['tracked_paths'] ?? null )
			? implode( "\n", $input['tracked_paths'] )
			: (string) ( $input['tracked_paths'] ?? '' );
		$paths = self::sanitize_lines( $paths_raw, array( __CLASS__, 'sanitize_path_pattern' ), 100 );

		$hosts_raw = is_array( $input['payment_hosts'] ?? null )
			? implode( "\n", $input['payment_hosts'] )
			: (string) ( $input['payment_hosts'] ?? '' );
		$hosts = self::sanitize_lines( $hosts_raw, array( __CLASS__, 'sanitize_hostname' ), 100 );

		$ad_mode = sanitize_key( (string) ( $input['ad_account_mode'] ?? 'none' ) );
		if ( ! in_array( $ad_mode, array( 'none', 'ad_grants', 'google_ads' ), true ) ) {
			$ad_mode = 'none';
		}

		$consent_gate = sanitize_key( (string) ( $input['consent_gate'] ?? 'external_only' ) );
		if ( ! in_array( $consent_gate, array( 'none', 'external_only', 'all' ), true ) ) {
			$consent_gate = 'external_only';
		}

		$data_layer_event = sanitize_key( (string) ( $input['data_layer_event'] ?? $defaults['data_layer_event'] ) );
		if ( '' === $data_layer_event ) {
			$data_layer_event = $defaults['data_layer_event'];
		}

		$confirmation_mode = sanitize_key( (string) ( $input['confirmation_mode'] ?? 'webhook' ) );
		if ( ! in_array( $confirmation_mode, array( 'webhook', 'client_marker', 'none' ), true ) ) {
			$confirmation_mode = 'webhook';
		}
		$current = get_option( self::OPTION, array() );
		$current = is_array( $current ) ? $current : array();
		$confirmation_secret = strtolower( trim( (string) ( $current['confirmation_secret'] ?? '' ) ) );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $confirmation_secret ) ) {
			$confirmation_secret = self::new_confirmation_secret();
		}

		return array(
			'settings_version'     => self::SETTINGS_VERSION,
			'enabled'              => empty( $input['enabled'] ) ? 0 : 1,
			'retention_days'       => max( 30, min( 730, absint( $input['retention_days'] ?? 90 ) ) ),
			'exclude_admins'       => empty( $input['exclude_admins'] ) ? 0 : 1,
			'session_minutes'      => max( 5, min( 120, absint( $input['session_minutes'] ?? 30 ) ) ),
			'tracked_page_ids'     => $page_ids,
			'tracked_paths'        => $paths,
			'copy_selector'        => self::sanitize_selector( (string) ( $input['copy_selector'] ?? $defaults['copy_selector'] ), $defaults['copy_selector'] ),
			'donate_selector'      => self::sanitize_selector( (string) ( $input['donate_selector'] ?? $defaults['donate_selector'] ), $defaults['donate_selector'] ),
			'payment_selector'     => self::sanitize_selector( (string) ( $input['payment_selector'] ?? $defaults['payment_selector'] ), $defaults['payment_selector'] ),
			'success_selector'     => self::sanitize_selector( (string) ( $input['success_selector'] ?? $defaults['success_selector'] ), $defaults['success_selector'] ),
			'payment_hosts'        => $hosts,
			'allow_client_success' => 'client_marker' === $confirmation_mode && ! empty( $input['allow_client_success'] ) ? 1 : 0,
			'confirmation_mode'    => $confirmation_mode,
			'confirmation_secret'  => $confirmation_secret,
			'ad_account_mode'      => $ad_mode,
			'data_layer_enabled'   => empty( $input['data_layer_enabled'] ) ? 0 : 1,
			'data_layer_event'     => $data_layer_event,
			'consent_gate'         => $consent_gate,
			'consent_category'     => sanitize_key( (string) ( $input['consent_category'] ?? 'analytics' ) ) ?: 'analytics',
		);
	}


	public static function new_confirmation_secret(): string {
		try {
			return bin2hex( random_bytes( 32 ) );
		} catch ( \Throwable $error ) {
			return hash( 'sha256', wp_generate_password( 64, true, true ) . wp_salt( 'auth' ) );
		}
	}

	public static function rotate_confirmation_secret(): string {
		$settings = self::settings();
		$settings['confirmation_secret'] = self::new_confirmation_secret();
		update_option( self::OPTION, $settings, false );
		return (string) $settings['confirmation_secret'];
	}

	public static function confirmation_callback_url(): string {
		return rest_url( Tracker::REST_NAMESPACE . '/confirm' );
	}

	/** @return array<string,mixed> */
	public static function confirmation_status(): array {
		$settings = self::settings();
		$mode = (string) $settings['confirmation_mode'];
		$secret = (string) $settings['confirmation_secret'];
		return array(
			'mode' => $mode,
			'ready' => 'webhook' === $mode && 1 === preg_match( '/^[a-f0-9]{64}$/', $secret ),
			'client_marker_enabled' => ! empty( $settings['allow_client_success'] ),
			'last_confirmation_at' => sanitize_text_field(
				(string) get_option( self::LAST_CONFIRMATION_OPTION, '' )
			),
		);
	}

	/**
	 * @return array<int,string>
	 */
	private static function sanitize_lines( string $raw, callable $callback, int $limit ): array {
		$parts = preg_split( '/[\r\n,]+/', wp_unslash( $raw ) );
		$result = array();
		foreach ( (array) $parts as $part ) {
			$value = call_user_func( $callback, trim( (string) $part ) );
			if ( '' !== $value ) {
				$result[] = $value;
			}
		}
		return array_slice( array_values( array_unique( $result ) ), 0, $limit );
	}

	private static function sanitize_path_pattern( string $value ): string {
		if ( '' === $value || str_contains( $value, '://' ) || str_contains( $value, '?' ) || str_contains( $value, '#' ) ) {
			return '';
		}
		$value = '/' . ltrim( sanitize_text_field( $value ), '/' );
		$value = preg_replace( '~/+~', '/', $value );
		$value = preg_replace( '~[^A-Za-z0-9_\-./*%]~', '', (string) $value );
		if ( strlen( $value ) > 200 ) {
			return '';
		}
		return '/' === $value ? '/' : untrailingslashit( $value ) . '/';
	}

	private static function sanitize_hostname( string $value ): string {
		$value = strtolower( trim( $value ) );
		$value = (string) preg_replace( '~^https?://~', '', $value );
		$value = explode( '/', $value, 2 )[0];
		$value = preg_replace( '/^www\./', '', $value );
		if ( ! preg_match( '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', (string) $value ) ) {
			return '';
		}
		return substr( (string) $value, 0, 253 );
	}

	private static function sanitize_selector( string $value, string $fallback ): string {
		$value = sanitize_text_field( wp_unslash( $value ) );
		$value = preg_replace( '/[\x00-\x1F\x7F{}<>]/u', '', $value );
		$value = trim( substr( (string) $value, 0, 400 ) );
		return '' !== $value ? $value : $fallback;
	}

	public static function cleanup_old_data(): void {
		Storage::cleanup_old_data( (int) self::settings()['retention_days'] );
	}
}
