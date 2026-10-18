<?php
/**
 * Core: settings, request helpers, path normalisation, privacy helpers and module wiring.
 *
 * @package KOZ_404_Guard
 */

namespace ramirkz\koz404;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZ404_Guard {
	public const SETTINGS_OPTION = 'koz404_settings';
	public const DB_VERSION_OPTION = 'koz404_db_version';
	public const DB_VERSION = '2';

	/** Options used before 2.2.0. Rules keep their option names; the log moved to a table. */
	public const LOG_OPTION      = 'koz404_log';
	public const REDIRECT_OPTION = 'koz404_redirects';
	public const GONE_OPTION     = 'koz404_gone_rules';
	public const CAPTURE_OPTION  = 'koz404_capture';
	private const CAPTURE_LOCK_OPTION = 'koz404_capture_lock';

	private const MAX_REQUEST_BYTES = 2000;
	private const MAX_QUERY_PAIRS   = 64;

	private static ?array $settings_cache = null;

	public static function init(): void {
		KOZ404_Request::init();
		KOZ404_Log::init();
		KOZ404_Permalink_Watch::init();
		KOZ404_Site_Health::init();
		add_filter( 'koz404_public_status', array( __CLASS__, 'public_status' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
		add_action( 'update_option_' . self::SETTINGS_OPTION, array( __CLASS__, 'flush_settings_cache' ) );
	}

	public static function migrate_legacy_data(): void {
		$map = array(
			self::SETTINGS_OPTION => 'uafree_404_guard_settings',
			self::LOG_OPTION      => 'uafree_404_guard_log',
			self::REDIRECT_OPTION => 'uafree_404_guard_redirects',
			self::GONE_OPTION     => 'uafree_404_guard_gone_rules',
		);
		foreach ( $map as $current => $legacy ) {
			if ( false !== get_option( $current, false ) ) {
				continue;
			}
			$value = get_option( $legacy, false );
			if ( false !== $value ) {
				add_option( $current, $value, '', false );
			}
		}
	}

	public static function activate(): void {
		self::migrate_legacy_data();
		if ( false === get_option( self::SETTINGS_OPTION, false ) ) {
			add_option( self::SETTINGS_OPTION, self::defaults(), '', true );
		}
		foreach ( array( self::REDIRECT_OPTION, self::GONE_OPTION ) as $option ) {
			if ( false === get_option( $option, false ) ) {
				add_option( $option, array(), '', false );
			}
		}
		self::maybe_upgrade();
		KOZ404_Log::schedule_cleanup();
	}

	public static function deactivate(): void {
		// Rules and the log are preserved. Only the scheduled cleanup is removed.
		KOZ404_Log::unschedule_cleanup();
	}

	/**
	 * Creates the log table, moves the pre-2.2.0 option log into it and makes the
	 * settings and compiled rule index autoloaded. Runs once per schema version.
	 */
	public static function maybe_upgrade(): void {
		if ( self::DB_VERSION === (string) get_option( self::DB_VERSION_OPTION, '' ) ) {
			return;
		}
		KOZ404_Log::install();
		KOZ404_Log::import_legacy_option();
		delete_option( self::CAPTURE_OPTION );
		delete_option( self::CAPTURE_LOCK_OPTION );

		$settings = get_option( self::SETTINGS_OPTION, false );
		if ( is_array( $settings ) ) {
			// Re-add as autoloaded: the settings are read on every front-end request.
			delete_option( self::SETTINGS_OPTION );
			add_option( self::SETTINGS_OPTION, self::normalise_settings( wp_parse_args( $settings, self::defaults() ), false ), '', true );
		}
		KOZ404_Rules::rebuild_index();
		KOZ404_Log::schedule_cleanup();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
		self::flush_settings_cache();
	}

	public static function defaults(): array {
		return array(
			'enabled'         => 1,
			'minimal_bot_404' => 1,
			'static_404'      => 1,
			'log_enabled'     => 1,
			'log_humans'      => 1,
			'log_bots'        => 0,
			'log_paths'       => 1,
			'log_query_keys'  => 1,
			'log_retention'   => 90,
			'log_limit'       => 2000,
			'emit_datalayer'  => 0,
			'permalink_watch' => 'suggest',
		);
	}

	public static function flush_settings_cache(): void {
		self::$settings_cache = null;
	}

	public static function settings(): array {
		if ( null === self::$settings_cache ) {
			$raw = get_option( self::SETTINGS_OPTION, array() );
			$raw = is_array( $raw ) ? wp_parse_args( $raw, self::defaults() ) : self::defaults();
			self::$settings_cache = self::normalise_settings( $raw, false );
		}
		return self::$settings_cache;
	}

	public static function sanitize_settings( $input ): array {
		self::flush_settings_cache();
		return self::normalise_settings( is_array( $input ) ? $input : array(), true );
	}

	private static function normalise_settings( array $input, bool $form_submission ): array {
		$defaults = self::defaults();
		$result   = array();
		foreach ( array( 'enabled', 'minimal_bot_404', 'static_404', 'log_enabled', 'log_humans', 'log_bots', 'log_paths', 'log_query_keys', 'emit_datalayer' ) as $key ) {
			$value          = array_key_exists( $key, $input ) ? $input[ $key ] : ( $form_submission ? 0 : $defaults[ $key ] );
			$result[ $key ] = self::strict_boolean( $value, 0 );
		}
		$result['log_retention'] = max( 7, min( 730, absint( self::scalar_string( $input['log_retention'] ?? $defaults['log_retention'] ) ) ) );
		$result['log_limit']     = max( 100, min( 20000, absint( self::scalar_string( $input['log_limit'] ?? $defaults['log_limit'] ) ) ) );
		$watch                   = sanitize_key( self::scalar_string( $input['permalink_watch'] ?? $defaults['permalink_watch'] ) );
		$result['permalink_watch'] = in_array( $watch, array( 'off', 'suggest', 'auto' ), true ) ? $watch : $defaults['permalink_watch'];
		return $result;
	}

	public static function strict_boolean( $value, int $default = 0 ): int {
		if ( true === $value || 1 === $value || '1' === $value || 'on' === $value || 'yes' === $value ) {
			return 1;
		}
		if ( false === $value || 0 === $value || '0' === $value || '' === $value || null === $value || 'off' === $value || 'no' === $value ) {
			return 0;
		}
		return $default ? 1 : 0;
	}

	public static function scalar_string( $value ): string {
		return is_string( $value ) || is_int( $value ) || is_float( $value ) ? (string) $value : '';
	}

	public static function truncate( string $value, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	/** Administrators, admin screens, AJAX and REST are never redirected, blocked or logged. */
	public static function bypass(): bool {
		return is_admin()
			|| wp_doing_ajax()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ( is_user_logged_in() && current_user_can( 'manage_options' ) );
	}

	public static function request_uri(): string {
		// sanitize_text_field() would strip percent-encoded octets and break non-Latin
		// addresses (/звіт/ becomes /), so control characters are removed here and the path
		// is normalised by normalise_path().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$uri = preg_replace( '/[\x00-\x1F\x7F].*$/s', '', $uri ) ?? '/';
		$uri = wp_check_invalid_utf8( $uri, true );
		return self::truncate( '' !== $uri ? $uri : '/', self::MAX_REQUEST_BYTES );
	}

	public static function request_path(): string {
		$path = wp_parse_url( self::request_uri(), PHP_URL_PATH );
		return self::normalise_path( is_string( $path ) ? $path : '/' );
	}

	public static function raw_query(): string {
		$query = wp_parse_url( self::request_uri(), PHP_URL_QUERY );
		return is_string( $query ) ? self::truncate( $query, self::MAX_REQUEST_BYTES ) : '';
	}

	public static function user_agent(): string {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';
		return self::truncate( $ua, 400 );
	}

	public static function classify_request(): string {
		$ua = strtolower( self::user_agent() );
		if ( '' === $ua ) {
			return 'bot';
		}

		$default_markers = array(
			'bot', 'crawler', 'spider', 'slurp', 'archiver', 'fetcher', 'baiduspider', 'bytespider',
			'yandex', 'semrush', 'ahrefs', 'mj12bot', 'dotbot', 'petalbot', 'ccbot', 'gptbot',
			'claudebot', 'google-extended', 'headlesschrome', 'prefetch proxy', 'preview', 'monitoring',
			'curl/', 'wget/', 'python-requests', 'go-http-client', 'okhttp', 'scrapy',
		);
		$filtered = apply_filters( 'koz404_bot_markers', $default_markers );
		$markers  = is_array( $filtered ) ? array_slice( $filtered, 0, 100 ) : $default_markers;
		foreach ( $markers as $marker ) {
			if ( ! is_string( $marker ) && ! is_int( $marker ) && ! is_float( $marker ) ) {
				continue;
			}
			$marker = strtolower( self::truncate( sanitize_text_field( (string) $marker ), 80 ) );
			if ( '' !== $marker && str_contains( $ua, $marker ) ) {
				return 'bot';
			}
		}
		return 'human';
	}

	/**
	 * Query pairs of the current request. Values are only used for 410 matching and
	 * are never stored.
	 *
	 * @return array<int,array{key:string,value:string}>
	 */
	public static function query_pairs( string $query ): array {
		$result = array();
		foreach ( array_slice( explode( '&', $query ), 0, self::MAX_QUERY_PAIRS ) as $raw_pair ) {
			if ( '' === $raw_pair ) {
				continue;
			}
			$parts = explode( '=', $raw_pair, 2 );
			$key   = self::normalise_query_key( str_replace( '+', ' ', $parts[0] ) );
			if ( '' === $key ) {
				continue;
			}
			$value    = isset( $parts[1] ) ? rawurldecode( str_replace( '+', ' ', $parts[1] ) ) : '';
			$value    = self::truncate( preg_replace( '/[\x00-\x1F\x7F]/u', '', $value ) ?? '', 300 );
			$result[] = array( 'key' => $key, 'value' => $value );
		}
		return $result;
	}

	public static function normalise_query_key( string $key ): string {
		$key = rawurldecode( trim( $key ) );
		return preg_match( '/^[A-Za-z0-9_.~\[\]-]{1,100}$/', $key ) ? $key : '';
	}

	public static function normalise_path( string $path ): string {
		$path = self::truncate( $path, self::MAX_REQUEST_BYTES );
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '/[\x00-\x1F\x7F].*$/s', '', $path ) ?? '/';
		// Percent-encoded UTF-8 (Cyrillic and other scripts) is decoded, so /%D0%B7%D0%B2/ and
		// /зв/ are the same path and the log shows readable addresses.
		$decoded = rawurldecode( $path );
		if ( wp_check_invalid_utf8( $decoded ) === $decoded && ! preg_match( '/[\x00-\x1F\x7F]/', $decoded ) ) {
			$path = str_replace( '\\', '/', $decoded );
		} else {
			$path = preg_replace_callback(
				'/%([0-9A-Fa-f]{2})/',
				static function ( array $match ): string {
					$char = chr( hexdec( $match[1] ) );
					return str_contains( 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789._~-/\\', $char ) ? ( '\\' === $char ? '/' : $char ) : strtoupper( $match[0] );
				},
				$path
			) ?? $path;
		}
		$path  = preg_replace( '#/+#', '/', '/' . ltrim( $path, '/' ) ) ?? '/';
		$parts = array();
		foreach ( explode( '/', $path ) as $part ) {
			if ( '' === $part || '.' === $part ) {
				continue;
			}
			if ( '..' === $part ) {
				array_pop( $parts );
				continue;
			}
			$part = self::clean_segment( $part );
			if ( '' !== $part ) {
				$parts[] = self::truncate( $part, 180 );
			}
		}
		$normalised = '/' . implode( '/', $parts );
		return '/' === $normalised ? '/' : untrailingslashit( $normalised );
	}

	/** Removes tags, whitespace and characters that are unsafe in a path segment, keeping letters of any script. */
	private static function clean_segment( string $part ): string {
		$part = wp_strip_all_tags( $part );
		$part = preg_replace( '/[\s<>"\'`{}|^]+/u', '', $part ) ?? '';
		return trim( $part );
	}

	public static function is_protected_path( string $path ): bool {
		$path = self::normalise_path( $path );
		return '/' === $path || self::is_system_path( $path );
	}

	public static function is_system_path( string $path ): bool {
		$path = self::normalise_path( $path );
		return (bool) preg_match( '#^/(wp-admin|wp-login\.php|wp-json|wp-cron\.php|xmlrpc\.php|wp-comments-post\.php|cdn-cgi)(/|$)#i', $path );
	}

	/** Missing static files are answered without rendering the theme. */
	public static function is_static_file_path( string $path ): bool {
		$extensions = apply_filters( 'koz404_static_extensions', array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp', 'css', 'js', 'mjs', 'map', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'mp4', 'webm', 'mp3', 'ogg', 'wav', 'pdf', 'zip', 'gz', 'rar', '7z' ) );
		$extensions = array_filter( array_map( 'sanitize_key', is_array( $extensions ) ? $extensions : array() ) );
		if ( empty( $extensions ) ) {
			return false;
		}
		return (bool) preg_match( '/\.(' . implode( '|', array_map( 'preg_quote', $extensions ) ) . ')$/i', $path );
	}

	public static function normalise_same_site_url( $value ): string {
		$value = self::scalar_string( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( str_starts_with( $value, '/' ) && ! str_starts_with( $value, '//' ) ) {
			$relative = wp_parse_url( $value );
			if ( ! is_array( $relative ) ) {
				return '';
			}
			$value = home_url( self::normalise_path( (string) ( $relative['path'] ?? '/' ) ) );
			if ( isset( $relative['query'] ) && is_string( $relative['query'] ) && '' !== $relative['query'] ) {
				$value .= '?' . $relative['query'];
			}
			if ( isset( $relative['fragment'] ) && is_string( $relative['fragment'] ) && '' !== $relative['fragment'] ) {
				$value .= '#' . rawurlencode( rawurldecode( $relative['fragment'] ) );
			}
		}
		$url = esc_url_raw( $value, array( 'http', 'https' ) );
		if ( '' === $url ) {
			return '';
		}
		$home = wp_parse_url( home_url( '/' ) );
		$test = wp_parse_url( $url );
		if ( ! is_array( $home ) || ! is_array( $test ) ) {
			return '';
		}
		$home_host   = strtolower( (string) ( $home['host'] ?? '' ) );
		$test_host   = strtolower( (string) ( $test['host'] ?? '' ) );
		$home_scheme = strtolower( (string) ( $home['scheme'] ?? '' ) );
		$test_scheme = strtolower( (string) ( $test['scheme'] ?? '' ) );
		$home_port   = isset( $home['port'] ) ? (int) $home['port'] : self::default_port( $home_scheme );
		$test_port   = isset( $test['port'] ) ? (int) $test['port'] : self::default_port( $test_scheme );
		if ( '' === $home_host || ! hash_equals( $home_host, $test_host ) || ! hash_equals( $home_scheme, $test_scheme ) || $home_port !== $test_port || isset( $test['user'] ) || isset( $test['pass'] ) ) {
			return '';
		}
		$path = self::normalise_path( (string) ( $test['path'] ?? '/' ) );
		if ( self::is_system_path( $path ) ) {
			return '';
		}
		$query    = isset( $test['query'] ) && is_string( $test['query'] ) ? '?' . $test['query'] : '';
		$fragment = isset( $test['fragment'] ) && is_string( $test['fragment'] ) ? '#' . rawurlencode( rawurldecode( $test['fragment'] ) ) : '';
		return self::path_url( $path ) . $query . $fragment;
	}

	/** Site URL for a normalised path, following the permalink trailing-slash setting. */
	public static function path_url( string $path ): string {
		if ( '/' === $path ) {
			return home_url( '/' );
		}
		return home_url( preg_match( '/\.[A-Za-z0-9]{1,8}$/', $path ) ? $path : user_trailingslashit( $path ) );
	}

	private static function default_port( string $scheme ): int {
		return 'https' === strtolower( $scheme ) ? 443 : 80;
	}

	public static function fingerprint( string $value, string $context ): string {
		$key = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'koz-404-guard';
		return substr( hash_hmac( 'sha256', $context . '|' . $value, $key ), 0, 16 );
	}

	/**
	 * Referrer reduced to its scope. For same-site referrers the path is kept, so the page
	 * with the broken link can be found; external hosts are only fingerprinted.
	 *
	 * @return array{scope:string,host_fingerprint:string,path:string}
	 */
	public static function referrer_summary( string $referrer ): array {
		if ( '' === $referrer ) {
			return array( 'scope' => 'none', 'host_fingerprint' => '', 'path' => '' );
		}
		$parsed    = wp_parse_url( esc_url_raw( $referrer, array( 'http', 'https' ) ) );
		$host      = strtolower( (string) ( is_array( $parsed ) ? ( $parsed['host'] ?? '' ) : '' ) );
		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( '' === $host ) {
			return array( 'scope' => 'invalid', 'host_fingerprint' => '', 'path' => '' );
		}
		if ( hash_equals( $home_host, $host ) ) {
			return array( 'scope' => 'same_site', 'host_fingerprint' => '', 'path' => self::normalise_path( (string) ( $parsed['path'] ?? '/' ) ) );
		}
		return array( 'scope' => 'external', 'host_fingerprint' => self::fingerprint( $host, 'host' ), 'path' => '' );
	}

	public static function current_referrer(): string {
		return isset( $_SERVER['HTTP_REFERER'] ) && is_string( $_SERVER['HTTP_REFERER'] )
			? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) )
			: '';
	}

	/** Back-compat wrappers for code written against 2.1.x. */
	public static function redirects(): array {
		return KOZ404_Rules::redirects();
	}

	public static function gone_rules(): array {
		return KOZ404_Rules::gone_rules();
	}

	public static function public_status( $value = null ): array {
		$settings = self::settings();
		$counts   = KOZ404_Log::summary_counts();
		return array(
			'version'          => KOZ404_VERSION,
			'enabled'          => ! empty( $settings['enabled'] ),
			'logging_enabled'  => ! empty( $settings['log_enabled'] ),
			'capture_active'   => ! empty( $settings['log_enabled'] ),
			'log_entries'      => (int) $counts['rows'],
			'open_404'         => (int) $counts['open_404'],
			'internal_404'     => (int) $counts['internal_404'],
			'redirect_rules'   => count( KOZ404_Rules::redirects() ),
			'gone_rules'       => count( KOZ404_Rules::gone_rules() ),
			'log_schema'       => 5,
			'privacy_contract' => 4,
		);
	}

	public static function suggestion( array $row ): string {
		$status = (int) ( $row['status'] ?? 404 );
		$source = (string) ( $row['source'] ?? '' );
		if ( 410 === $status ) {
			return __( 'A 410 rule is active.', 'koz-404-guard' );
		}
		if ( $status >= 300 && $status < 400 ) {
			return __( 'Redirected by a rule.', 'koz-404-guard' );
		}
		if ( 'system_route_404' === $source ) {
			return __( 'System or infrastructure route. Do not redirect; exclude it from broken-content triage.', 'koz-404-guard' );
		}
		if ( 'attachment_404' === $source ) {
			return __( 'Legacy attachment URL. Inventory the attachment and backlinks before creating any redirect.', 'koz-404-guard' );
		}
		if ( 'same_site' === (string) ( $row['referrer_scope'] ?? '' ) ) {
			return __( 'Check and fix the internal link.', 'koz-404-guard' );
		}
		if ( 'bot' === (string) ( $row['kind'] ?? '' ) && preg_match( '#(wp-config|phpmyadmin|\.git|\.env|vendor/phpunit|\.php$)#i', (string) ( $row['path'] ?? '' ) ) ) {
			return __( 'Likely automated probing. Keep 404 or add a narrow 410 rule.', 'koz-404-guard' );
		}
		if ( 'static_404' === $source ) {
			return __( 'Missing file. Restore it or fix the reference.', 'koz-404-guard' );
		}
		if ( absint( $row['hits'] ?? 0 ) >= 10 ) {
			return __( 'Frequent request. Review whether a redirect is appropriate.', 'koz-404-guard' );
		}
		return __( 'Monitor. No automatic action is recommended.', 'koz-404-guard' );
	}
}
