<?php
namespace UAFree\Guard404;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Guard {
	public const SETTINGS_OPTION = 'uafree_404_guard_settings';
	public const LOG_OPTION      = 'uafree_404_guard_log';
	public const REDIRECT_OPTION = 'uafree_404_guard_redirects';
	public const GONE_OPTION     = 'uafree_404_guard_gone_rules';
	public const CAPTURE_OPTION  = 'uafree_404_guard_capture';

	private const CAPTURE_LOCK_OPTION        = 'uafree_404_guard_capture_lock';
	private const CAPTURE_WINDOW_SECONDS     = 600;
	private const CAPTURE_SAMPLE_DENOMINATOR = 128;
	private const LOG_WRITE_INTERVAL_SECONDS = 30;
	private const CAPTURE_MAX_WRITE_SLOTS    = 20;
	private const ALLOWED_REDIRECT_STATUSES = array( 301, 302, 307, 308 );
	private const ALLOWED_GONE_TYPES        = array( 'exact_path', 'path_prefix', 'query_key', 'query_pair' );
	private const MAX_REQUEST_BYTES         = 2000;
	private const MAX_QUERY_PAIRS           = 64;

	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'handle_request' ), 0 );
		add_action( 'wp_footer', array( __CLASS__, 'print_datalayer' ), 99 );
		add_filter( 'uafree_404_guard_public_status', array( __CLASS__, 'public_status' ) );
	}

	public static function activate(): void {
		if ( false === get_option( self::SETTINGS_OPTION, false ) ) {
			add_option( self::SETTINGS_OPTION, self::defaults(), '', false );
		}
		foreach ( array( self::LOG_OPTION, self::REDIRECT_OPTION, self::GONE_OPTION ) as $option ) {
			if ( false === get_option( $option, false ) ) {
				add_option( $option, array(), '', false );
			}
		}
		if ( false === get_option( self::CAPTURE_OPTION, false ) ) {
			// A tiny autoloaded state avoids an extra database read on every public 404 request.
			add_option( self::CAPTURE_OPTION, array(), '', true );
		}
	}

	public static function deactivate(): void {
		// Persistent rules and diagnostics are preserved. Ephemeral capture state is not.
		update_option( self::CAPTURE_OPTION, array(), true );
		delete_option( self::CAPTURE_LOCK_OPTION );
	}

	public static function defaults(): array {
		return array(
			'enabled'         => 1,
			'minimal_bot_404' => 1,
			'log_humans'      => 1,
			'log_bots'        => 0,
			'emit_datalayer'  => 0,
			'log_query_keys'  => 1,
			'log_limit'       => 500,
		);
	}

	public static function settings(): array {
		$raw = get_option( self::SETTINGS_OPTION, array() );
		$raw = is_array( $raw ) ? wp_parse_args( $raw, self::defaults() ) : self::defaults();
		return self::normalise_settings( $raw, false );
	}

	public static function sanitize_settings( $input ): array {
		return self::normalise_settings( is_array( $input ) ? $input : array(), true );
	}

	public static function capture_ready(): bool {
		$raw      = get_option( self::LOG_OPTION, array() );
		$settings = self::settings();
		if ( ! is_array( $raw ) || count( $raw ) > (int) $settings['log_limit'] ) {
			return false;
		}
		$allowed_keys = array(
			'id', 'status', 'kind', 'path_display', 'path_fingerprint', 'query_key_fingerprints',
			'query_key_count', 'count', 'sample_denominator', 'first_seen', 'last_seen',
			'referrer_scope', 'referrer_host_fingerprint', 'source', 'rule_fingerprint',
		);
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || array_diff( array_keys( $row ), $allowed_keys ) ) {
				return false;
			}
			$path_fingerprint = self::scalar_string( $row['path_fingerprint'] ?? '' );
			if ( ! preg_match( '/^[a-f0-9]{16}$/', $path_fingerprint ) || self::scalar_string( $row['path_display'] ?? '' ) !== '/[path:' . $path_fingerprint . ']' || ! isset( $row['query_key_fingerprints'] ) || ! is_array( $row['query_key_fingerprints'] ) ) {
				return false;
			}
			foreach ( $row['query_key_fingerprints'] as $fingerprint ) {
				if ( ! preg_match( '/^[a-f0-9]{16}$/', self::scalar_string( $fingerprint ) ) ) {
					return false;
				}
			}
		}
		return true;
	}

	public static function start_capture(): array {
		if ( ! self::capture_ready() ) {
			return self::capture_status();
		}
		$now            = time();
		$current_bucket = intdiv( $now, self::LOG_WRITE_INTERVAL_SECONDS );
		$expires_at     = ( ( $current_bucket + self::CAPTURE_MAX_WRITE_SLOTS ) * self::LOG_WRITE_INTERVAL_SECONDS ) - 1;
		$state = array(
			'started_at' => $now,
			'expires_at' => min( $now + self::CAPTURE_WINDOW_SECONDS, $expires_at ),
		);
		if ( false === get_option( self::CAPTURE_OPTION, false ) ) {
			add_option( self::CAPTURE_OPTION, $state, '', true );
		} else {
			update_option( self::CAPTURE_OPTION, $state, true );
		}
		delete_option( self::CAPTURE_LOCK_OPTION );
		return self::capture_status();
	}

	public static function stop_capture(): void {
		update_option( self::CAPTURE_OPTION, array(), true );
		delete_option( self::CAPTURE_LOCK_OPTION );
	}

	public static function capture_status(): array {
		$raw        = get_option( self::CAPTURE_OPTION, array() );
		$raw        = is_array( $raw ) ? $raw : array();
		$started_at = absint( self::scalar_string( $raw['started_at'] ?? 0 ) );
		$expires_at = absint( self::scalar_string( $raw['expires_at'] ?? 0 ) );
		$now        = time();
		$active     = $started_at > 0 && $expires_at > $now && $expires_at <= ( $started_at + self::CAPTURE_WINDOW_SECONDS );
		return array(
			'active'             => $active,
			'started_at'         => $active ? $started_at : 0,
			'expires_at'         => $active ? $expires_at : 0,
			'seconds_remaining'  => $active ? max( 0, $expires_at - $now ) : 0,
			'sample_denominator' => self::CAPTURE_SAMPLE_DENOMINATOR,
			'write_interval'     => self::LOG_WRITE_INTERVAL_SECONDS,
			'maximum_write_slots' => self::CAPTURE_MAX_WRITE_SLOTS,
		);
	}

	private static function normalise_settings( array $input, bool $form_submission ): array {
		$defaults = self::defaults();
		$result   = array();
		foreach ( array( 'enabled', 'minimal_bot_404', 'log_humans', 'log_bots', 'emit_datalayer', 'log_query_keys' ) as $key ) {
			$value          = array_key_exists( $key, $input ) ? $input[ $key ] : ( $form_submission ? 0 : $defaults[ $key ] );
			$result[ $key ] = self::strict_boolean( $value, 0 );
		}
		$limit               = self::scalar_string( $input['log_limit'] ?? $defaults['log_limit'] );
		$result['log_limit'] = max( 50, min( 1000, absint( $limit ) ) );
		return $result;
	}

	private static function strict_boolean( $value, int $default = 0 ): int {
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

	private static function bypass(): bool {
		return is_admin()
			|| wp_doing_ajax()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ( is_user_logged_in() && current_user_can( 'manage_options' ) );
	}

	public static function request_uri(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( self::scalar_string( $_SERVER['REQUEST_URI'] ) ) )
			: '/';
		$uri = preg_replace( '/[\x00-\x1F\x7F].*$/s', '', $uri ) ?? '/';
		return self::truncate( '' !== $uri ? $uri : '/', self::MAX_REQUEST_BYTES );
	}

	public static function request_path(): string {
		$path = wp_parse_url( self::request_uri(), PHP_URL_PATH );
		return self::normalise_path( is_string( $path ) ? $path : '/' );
	}

	private static function raw_query(): string {
		$query = wp_parse_url( self::request_uri(), PHP_URL_QUERY );
		return is_string( $query ) ? self::truncate( $query, self::MAX_REQUEST_BYTES ) : '';
	}

	public static function user_agent(): string {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( self::scalar_string( $_SERVER['HTTP_USER_AGENT'] ) ) )
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
		);
		$filtered = apply_filters( 'uafree_404_guard_bot_markers', $default_markers );
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

	public static function handle_request(): void {
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) || self::bypass() ) {
			return;
		}

		$redirect = self::matching_redirect();
		if ( null !== $redirect ) {
			self::log( (string) $redirect['status'], self::classify_request(), 'redirect', (string) $redirect['id'] );
			wp_safe_redirect( (string) $redirect['target'], (int) $redirect['status'], 'UA FREE 404 Guard' );
			exit;
		}

		$gone = self::matching_gone_rule();
		if ( null !== $gone ) {
			self::log( '410', self::classify_request(), 'gone_rule', (string) $gone['id'] );
			self::minimal_response( 410, __( 'Content removed', 'ua-free-404-guard' ), __( 'This content is no longer available.', 'ua-free-404-guard' ) );
		}

		if ( ! is_404() ) {
			return;
		}

		$kind = self::classify_request();
		if ( ( 'bot' === $kind && ! empty( $settings['log_bots'] ) ) || ( 'bot' !== $kind && ! empty( $settings['log_humans'] ) ) ) {
			self::log( '404', $kind, 'wordpress_404' );
		}
		header( 'X-Robots-Tag: noindex, follow, noarchive', true );
		if ( 'bot' === $kind && ! empty( $settings['minimal_bot_404'] ) ) {
			self::minimal_response( 404, __( 'Page not found', 'ua-free-404-guard' ), __( 'The requested page was not found.', 'ua-free-404-guard' ) );
		}
	}

	public static function print_datalayer(): void {
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) || empty( $settings['emit_datalayer'] ) || self::bypass() || ! is_404() || 'human' !== self::classify_request() ) {
			return;
		}
		$payload = array(
			'event'            => 'uafree_404',
			'status'           => 404,
			'path_fingerprint' => self::fingerprint( self::request_path(), 'path' ),
		);
		echo '<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push(' . wp_json_encode( $payload ) . ');</script>';
	}

	private static function matching_redirect(): ?array {
		$path = self::request_path();
		if ( self::is_protected_path( $path ) ) {
			return null;
		}
		foreach ( self::redirects() as $rule ) {
			if ( ! empty( $rule['enabled'] ) && hash_equals( (string) $rule['source'], $path ) ) {
				return $rule;
			}
		}
		return null;
	}

	private static function matching_gone_rule(): ?array {
		$path  = self::request_path();
		$pairs = self::query_pairs( self::raw_query() );
		if ( self::is_protected_path( $path ) ) {
			return null;
		}
		foreach ( self::gone_rules() as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			$type  = (string) $rule['type'];
			$value = (string) $rule['value'];
			if ( 'exact_path' === $type && hash_equals( $value, $path ) ) {
				return $rule;
			}
			if ( 'path_prefix' === $type && ( hash_equals( $value, $path ) || str_starts_with( $path, $value . '/' ) ) ) {
				return $rule;
			}
			if ( 'query_key' === $type ) {
				foreach ( $pairs as $pair ) {
					if ( hash_equals( $value, $pair['key'] ) ) {
						return $rule;
					}
				}
			}
			if ( 'query_pair' === $type ) {
				$expected = self::split_query_rule( $value );
				if ( null === $expected ) {
					continue;
				}
				foreach ( $pairs as $pair ) {
					if ( hash_equals( $expected['key'], $pair['key'] ) && hash_equals( $expected['value'], $pair['value'] ) ) {
						return $rule;
					}
				}
			}
		}
		return null;
	}

	public static function redirects(): array {
		$value  = get_option( self::REDIRECT_OPTION, array() );
		$result = array();
		if ( ! is_array( $value ) ) {
			return $result;
		}
		foreach ( $value as $row ) {
			$rule = self::normalise_redirect_rule( $row );
			if ( null !== $rule ) {
				$result[] = $rule;
			}
		}
		return $result;
	}

	private static function normalise_redirect_rule( $row ): ?array {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$id     = sanitize_text_field( self::scalar_string( $row['id'] ?? '' ) );
		$source = self::normalise_path( self::scalar_string( $row['source'] ?? '' ) );
		$target = self::normalise_same_site_url( $row['target'] ?? '' );
		$status = absint( self::scalar_string( $row['status'] ?? 301 ) );
		if ( '' === $id || '/' === $source || self::is_protected_path( $source ) || '' === $target || ! in_array( $status, self::ALLOWED_REDIRECT_STATUSES, true ) ) {
			return null;
		}
		$target_path = self::normalise_path( (string) wp_parse_url( $target, PHP_URL_PATH ) );
		if ( hash_equals( $source, $target_path ) ) {
			return null;
		}
		return array(
			'id'      => self::truncate( $id, 100 ),
			'source'  => $source,
			'target'  => $target,
			'status'  => $status,
			'enabled' => self::strict_boolean( $row['enabled'] ?? 1, 1 ),
		);
	}

	public static function gone_rules(): array {
		$value  = get_option( self::GONE_OPTION, array() );
		$result = array();
		if ( ! is_array( $value ) ) {
			return $result;
		}
		foreach ( $value as $row ) {
			$rule = self::normalise_gone_rule( $row );
			if ( null !== $rule ) {
				$result[] = $rule;
			}
		}
		return $result;
	}

	private static function normalise_gone_rule( $row ): ?array {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$id    = sanitize_text_field( self::scalar_string( $row['id'] ?? '' ) );
		$type  = sanitize_key( self::scalar_string( $row['type'] ?? '' ) );
		$value = self::normalise_gone_value( $type, $row['value'] ?? '' );
		if ( '' === $id || ! in_array( $type, self::ALLOWED_GONE_TYPES, true ) || '' === $value ) {
			return null;
		}
		return array(
			'id'      => self::truncate( $id, 100 ),
			'type'    => $type,
			'value'   => $value,
			'enabled' => self::strict_boolean( $row['enabled'] ?? 1, 1 ),
		);
	}

	public static function normalise_gone_value( string $type, $value ): string {
		$value = self::scalar_string( $value );
		if ( in_array( $type, array( 'exact_path', 'path_prefix' ), true ) ) {
			$path = self::normalise_path( $value );
			return '/' === $path || self::is_protected_path( $path ) ? '' : $path;
		}
		if ( 'query_key' === $type ) {
			return self::normalise_query_key( $value );
		}
		if ( 'query_pair' === $type ) {
			$pair = self::split_query_rule( $value );
			return null === $pair ? '' : $pair['key'] . '=' . $pair['value'];
		}
		return '';
	}

	private static function normalise_query_key( string $key ): string {
		$key = rawurldecode( trim( $key ) );
		return preg_match( '/^[A-Za-z0-9_.~-]{1,100}$/', $key ) ? $key : '';
	}

	private static function split_query_rule( string $value ): ?array {
		$parts = explode( '=', $value, 2 );
		if ( 2 !== count( $parts ) ) {
			return null;
		}
		$key   = self::normalise_query_key( $parts[0] );
		$right = rawurldecode( str_replace( '+', ' ', trim( $parts[1] ) ) );
		$right = self::truncate( preg_replace( '/[\x00-\x1F\x7F]/u', '', $right ) ?? '', 300 );
		if ( '' === $key || '' === $right || preg_match( '/[\r\n]/', $right ) ) {
			return null;
		}
		return array( 'key' => $key, 'value' => $right );
	}

	private static function query_pairs( string $query ): array {
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
			$value = isset( $parts[1] ) ? rawurldecode( str_replace( '+', ' ', $parts[1] ) ) : '';
			$value = self::truncate( preg_replace( '/[\x00-\x1F\x7F]/u', '', $value ) ?? '', 300 );
			$result[] = array( 'key' => $key, 'value' => $value );
		}
		return $result;
	}

	public static function logs(): array {
		return array_values( self::log_map() );
	}

	private static function log_map(): array {
		$value  = get_option( self::LOG_OPTION, array() );
		$result = array();
		if ( ! is_array( $value ) ) {
			return $result;
		}
		foreach ( $value as $key => $row ) {
			$normal = self::normalise_log_row( $row, (string) $key );
			if ( null !== $normal ) {
				$result[ $normal['id'] ] = $normal;
			}
		}
		return $result;
	}

	private static function normalise_log_row( $row, string $fallback_key ): ?array {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$status = self::controlled_status( self::scalar_string( $row['status'] ?? '404' ) );
		$kind   = self::controlled_kind( self::scalar_string( $row['kind'] ?? 'unknown' ) );
		$source = self::controlled_source( self::scalar_string( $row['source'] ?? 'legacy' ) );
		$path   = self::normalise_path(
			self::scalar_string( $row['path'] ?? wp_parse_url( self::scalar_string( $row['uri'] ?? '/' ), PHP_URL_PATH ) )
		);

		$query_key_fingerprints = array();
		if ( isset( $row['query_key_fingerprints'] ) && is_array( $row['query_key_fingerprints'] ) ) {
			foreach ( array_slice( $row['query_key_fingerprints'], 0, self::MAX_QUERY_PAIRS ) as $fingerprint ) {
				$fingerprint = strtolower( self::scalar_string( $fingerprint ) );
				if ( preg_match( '/^[a-f0-9]{16}$/', $fingerprint ) ) {
					$query_key_fingerprints[ $fingerprint ] = true;
				}
			}
		} elseif ( isset( $row['query_keys'] ) && is_array( $row['query_keys'] ) ) {
			foreach ( array_slice( $row['query_keys'], 0, self::MAX_QUERY_PAIRS ) as $query_key ) {
				$key = self::normalise_query_key( self::scalar_string( $query_key ) );
				if ( '' !== $key ) {
					$query_key_fingerprints[ self::fingerprint( $key, 'query-key' ) ] = true;
				}
			}
		} elseif ( isset( $row['uri'] ) ) {
			$query = wp_parse_url( self::scalar_string( $row['uri'] ), PHP_URL_QUERY );
			if ( is_string( $query ) ) {
				foreach ( self::query_pairs( $query ) as $pair ) {
					$query_key_fingerprints[ self::fingerprint( $pair['key'], 'query-key' ) ] = true;
				}
			}
		}
		$query_key_fingerprints = array_keys( $query_key_fingerprints );
		sort( $query_key_fingerprints, SORT_STRING );

		$referrer_scope = self::controlled_referrer_scope( self::scalar_string( $row['referrer_scope'] ?? '' ) );
		$referrer_hash  = self::scalar_string( $row['referrer_host_fingerprint'] ?? '' );
		if ( '' === $referrer_scope && isset( $row['referrer'] ) ) {
			$referrer      = self::referrer_summary( self::scalar_string( $row['referrer'] ) );
			$referrer_scope = $referrer['scope'];
			$referrer_hash  = $referrer['host_fingerprint'];
		}
		$path_fingerprint = self::scalar_string( $row['path_fingerprint'] ?? '' );
		if ( ! preg_match( '/^[a-f0-9]{16}$/', $path_fingerprint ) ) {
			$path_fingerprint = self::fingerprint( $path, 'path' );
		}
		$rule_fingerprint = self::scalar_string( $row['rule_fingerprint'] ?? '' );
		if ( '' === $rule_fingerprint && '' !== self::scalar_string( $row['rule_id'] ?? '' ) ) {
			$rule_fingerprint = self::fingerprint( self::scalar_string( $row['rule_id'] ), 'rule' );
		}
		$sample_denominator = absint( self::scalar_string( $row['sample_denominator'] ?? 1 ) );
		$sample_denominator = max( 1, min( 4096, $sample_denominator ) );
		$id = self::scalar_string( $row['id'] ?? $fallback_key );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $id ) ) {
			$id = md5( $status . '|' . $kind . '|' . $source . '|' . $path_fingerprint . '|' . implode( ',', $query_key_fingerprints ) );
		}
		return array(
			'id'                        => $id,
			'status'                    => $status,
			'kind'                      => $kind,
			'path_display'              => '/[path:' . $path_fingerprint . ']',
			'path_fingerprint'          => $path_fingerprint,
			'query_key_fingerprints'    => $query_key_fingerprints,
			'query_key_count'           => count( $query_key_fingerprints ),
			'count'                     => max( 0, absint( self::scalar_string( $row['count'] ?? 0 ) ) ),
			'sample_denominator'        => $sample_denominator,
			'first_seen'                => self::safe_mysql_datetime( $row['first_seen'] ?? '' ),
			'last_seen'                 => self::safe_mysql_datetime( $row['last_seen'] ?? '' ),
			'referrer_scope'            => '' !== $referrer_scope ? $referrer_scope : 'none',
			'referrer_host_fingerprint' => preg_match( '/^[a-f0-9]{16}$/', $referrer_hash ) ? $referrer_hash : '',
			'source'                    => $source,
			'rule_fingerprint'          => preg_match( '/^[a-f0-9]{16}$/', $rule_fingerprint ) ? $rule_fingerprint : '',
		);
	}

	public static function log( string $status, string $kind, string $source, string $rule_id = '' ): void {
		$capture = self::capture_status();
		if ( empty( $capture['active'] ) ) {
			return;
		}

		$settings = self::settings();
		$kind     = self::controlled_kind( $kind );
		if ( ( 'bot' === $kind && empty( $settings['log_bots'] ) ) || ( 'bot' !== $kind && empty( $settings['log_humans'] ) ) ) {
			return;
		}
		if ( ! self::sample_capture_request() || ! self::acquire_log_write_slot() ) {
			return;
		}

		$path  = self::request_path();
		$pairs = self::query_pairs( self::raw_query() );
		$key_fingerprints = array();
		if ( ! empty( $settings['log_query_keys'] ) ) {
			foreach ( $pairs as $pair ) {
				$key_fingerprints[ self::fingerprint( $pair['key'], 'query-key' ) ] = true;
			}
		}
		$query_key_fingerprints = array_keys( $key_fingerprints );
		sort( $query_key_fingerprints, SORT_STRING );
		$status           = self::controlled_status( $status );
		$source           = self::controlled_source( $source );
		$path_fingerprint = self::fingerprint( $path, 'path' );
		$key              = md5( $status . '|' . $kind . '|' . $source . '|' . $path_fingerprint . '|' . implode( ',', $query_key_fingerprints ) );
		$log              = self::log_map();
		$now              = current_time( 'mysql' );
		$referrer_raw = isset( $_SERVER['HTTP_REFERER'] )
			? esc_url_raw( wp_unslash( self::scalar_string( $_SERVER['HTTP_REFERER'] ) ) )
			: '';
		$referrer = self::referrer_summary( $referrer_raw );

		if ( ! isset( $log[ $key ] ) ) {
			$log[ $key ] = array(
				'id'                        => $key,
				'status'                    => $status,
				'kind'                      => $kind,
				'path_display'              => self::redacted_path_display( $path ),
				'path_fingerprint'          => $path_fingerprint,
				'query_key_fingerprints'    => $query_key_fingerprints,
				'query_key_count'           => count( $query_key_fingerprints ),
				'count'                     => 0,
				'sample_denominator'        => self::CAPTURE_SAMPLE_DENOMINATOR,
				'first_seen'                => $now,
				'last_seen'                 => $now,
				'referrer_scope'            => $referrer['scope'],
				'referrer_host_fingerprint' => $referrer['host_fingerprint'],
				'source'                    => $source,
				'rule_fingerprint'          => '' !== $rule_id ? self::fingerprint( $rule_id, 'rule' ) : '',
			);
		}
		$log[ $key ]['count']                     = absint( $log[ $key ]['count'] ) + 1;
		$log[ $key ]['last_seen']                 = $now;
		$log[ $key ]['referrer_scope']            = $referrer['scope'];
		$log[ $key ]['referrer_host_fingerprint'] = $referrer['host_fingerprint'];
		$log[ $key ]['rule_fingerprint']          = '' !== $rule_id ? self::fingerprint( $rule_id, 'rule' ) : '';
		$log[ $key ]['sample_denominator']        = self::CAPTURE_SAMPLE_DENOMINATOR;

		uasort(
			$log,
			static fn( array $a, array $b ): int => strcmp( (string) ( $b['last_seen'] ?? '' ), (string) ( $a['last_seen'] ?? '' ) )
		);
		$limit = (int) $settings['log_limit'];
		if ( count( $log ) > $limit ) {
			$log = array_slice( $log, 0, $limit, true );
		}
		update_option( self::LOG_OPTION, $log, false );
		do_action(
			'uafree_404_guard_event',
			array(
				'status'             => (string) $log[ $key ]['status'],
				'kind'               => (string) $log[ $key ]['kind'],
				'path_fingerprint'   => (string) $log[ $key ]['path_fingerprint'],
				'query_key_count'    => (int) $log[ $key ]['query_key_count'],
				'captured_count'     => (int) $log[ $key ]['count'],
				'sample_denominator' => (int) $log[ $key ]['sample_denominator'],
				'source'             => (string) $log[ $key ]['source'],
			)
		);
	}

	private static function sample_capture_request(): bool {
		if ( function_exists( 'wp_rand' ) ) {
			return 1 === wp_rand( 1, self::CAPTURE_SAMPLE_DENOMINATOR );
		}
		try {
			return 1 === random_int( 1, self::CAPTURE_SAMPLE_DENOMINATOR );
		} catch ( \Throwable $error ) {
			return false;
		}
	}

	private static function acquire_log_write_slot(): bool {
		$bucket = (string) intdiv( time(), self::LOG_WRITE_INTERVAL_SECONDS );
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() && function_exists( 'wp_cache_add' ) ) {
			return (bool) wp_cache_add( 'capture-write-' . $bucket, 1, 'uafree-404-guard', self::LOG_WRITE_INTERVAL_SECONDS + 5 );
		}

		$current = get_option( self::CAPTURE_LOCK_OPTION, false );
		if ( (string) $current === $bucket ) {
			return false;
		}
		if ( false === $current ) {
			return add_option( self::CAPTURE_LOCK_OPTION, $bucket, '', false );
		}

		global $wpdb;
		if ( ! is_object( $wpdb ) || empty( $wpdb->options ) || ! method_exists( $wpdb, 'update' ) || ! function_exists( 'maybe_serialize' ) ) {
			return false;
		}
		$old_serialized = maybe_serialize( $current );
		$new_serialized = maybe_serialize( $bucket );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic compare-and-swap prevents concurrent capture writes.
		$updated = (int) $wpdb->update(
			$wpdb->options,
			array( 'option_value' => $new_serialized ),
			array(
				'option_name'  => self::CAPTURE_LOCK_OPTION,
				'option_value' => $old_serialized,
			),
			array( '%s' ),
			array( '%s', '%s' )
		);
		if ( 1 === $updated && function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::CAPTURE_LOCK_OPTION, 'options' );
		}
		return 1 === $updated;
	}

	public static function sanitize_legacy_log(): int {
		$raw        = get_option( self::LOG_OPTION, array() );
		$before     = is_array( $raw ) ? count( $raw ) : 0;
		$normalised = self::log_map();
		update_option( self::LOG_OPTION, $normalised, false );
		return $before;
	}

	public static function minimal_response( int $status, string $title, string $message ): void {
		status_header( $status );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'X-Content-Type-Options: nosniff', true );
		header( 'Referrer-Policy: no-referrer', true );
		header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'", true );
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ), true );
		$home = home_url( '/' );
		echo '<!doctype html><html lang="' . esc_attr( substr( determine_locale(), 0, 2 ) ) . '"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive">';
		echo '<title>' . esc_html( $title ) . '</title><style>body{font-family:system-ui,sans-serif;background:#f6f7f7;color:#1d2327;margin:0}main{max-width:700px;margin:12vh auto;padding:32px;background:#fff;border-radius:12px;text-align:center}h1{font-size:56px;margin:0 0 12px}a{display:inline-block;padding:12px 20px;background:#2271b1;color:#fff;text-decoration:none;border-radius:8px}</style></head>';
		echo '<body><main><h1>' . esc_html( (string) $status ) . '</h1><p>' . esc_html( $message ) . '</p><a href="' . esc_url( $home ) . '">' . esc_html__( 'Return to the homepage', 'ua-free-404-guard' ) . '</a></main></body></html>';
		exit;
	}

	private static function truncate( string $value, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	public static function normalise_path( string $path ): string {
		$path = self::truncate( $path, self::MAX_REQUEST_BYTES );
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '/[\x00-\x1F\x7F].*$/s', '', $path ) ?? '/';
		$path = preg_replace_callback(
			'/%([0-9A-Fa-f]{2})/',
			static function ( array $match ): string {
				$char = chr( hexdec( $match[1] ) );
				return str_contains( 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789._~-/\\', $char ) ? ( '\\' === $char ? '/' : $char ) : strtoupper( $match[0] );
			},
			$path
		) ?? $path;
		$path = preg_replace( '#/+#', '/', '/' . ltrim( $path, '/' ) ) ?? '/';
		$parts = array();
		foreach ( explode( '/', $path ) as $part ) {
			if ( '' === $part || '.' === $part ) {
				continue;
			}
			if ( '..' === $part ) {
				array_pop( $parts );
				continue;
			}
			$parts[] = self::truncate( sanitize_text_field( $part ), 180 );
		}
		$normalised = '/' . implode( '/', $parts );
		return '/' === $normalised ? '/' : untrailingslashit( $normalised );
	}

	public static function is_protected_path( string $path ): bool {
		$path = self::normalise_path( $path );
		return '/' === $path || self::is_system_path( $path );
	}

	private static function is_system_path( string $path ): bool {
		$path = self::normalise_path( $path );
		return (bool) preg_match( '#^/(wp-admin|wp-login\.php|wp-json|wp-cron\.php|xmlrpc\.php|wp-comments-post\.php)(/|$)#i', $path );
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
		$query = isset( $test['query'] ) && is_string( $test['query'] ) ? '?' . $test['query'] : '';
		$fragment = isset( $test['fragment'] ) && is_string( $test['fragment'] ) ? '#' . rawurlencode( rawurldecode( $test['fragment'] ) ) : '';
		return home_url( $path ) . $query . $fragment;
	}

	private static function default_port( string $scheme ): int {
		return 'https' === strtolower( $scheme ) ? 443 : 80;
	}

	public static function has_redirect_source( string $source ): bool {
		$source = self::normalise_path( $source );
		foreach ( self::redirects() as $rule ) {
			if ( hash_equals( $source, (string) $rule['source'] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function would_create_redirect_cycle( string $source, string $target ): bool {
		$source      = self::normalise_path( $source );
		$target_path = self::normalise_path( (string) wp_parse_url( $target, PHP_URL_PATH ) );
		$map         = array();
		foreach ( self::redirects() as $rule ) {
			$map[ (string) $rule['source'] ] = self::normalise_path( (string) wp_parse_url( (string) $rule['target'], PHP_URL_PATH ) );
		}
		$map[ $source ] = $target_path;
		$seen           = array();
		$current        = $source;
		for ( $i = 0, $max = count( $map ) + 1; $i < $max; $i++ ) {
			if ( isset( $seen[ $current ] ) ) {
				return true;
			}
			$seen[ $current ] = true;
			if ( ! isset( $map[ $current ] ) ) {
				return false;
			}
			$current = $map[ $current ];
		}
		return true;
	}

	public static function public_status( $value = null ): array {
		$settings  = self::settings();
		$raw_log   = get_option( self::LOG_OPTION, array() );
		$raw_redir = get_option( self::REDIRECT_OPTION, array() );
		$raw_gone  = get_option( self::GONE_OPTION, array() );
		$capture = self::capture_status();
		return array(
			'version'          => UAFREE_404_VERSION,
			'enabled'          => ! empty( $settings['enabled'] ),
			'capture_active'   => ! empty( $capture['active'] ),
			'log_entries'      => is_array( $raw_log ) ? count( $raw_log ) : 0,
			'redirect_rules'   => is_array( $raw_redir ) ? count( $raw_redir ) : 0,
			'gone_rules'       => is_array( $raw_gone ) ? count( $raw_gone ) : 0,
			'log_schema'       => 3,
			'privacy_contract' => 3,
		);
	}

	public static function suggestion( array $row ): string {
		if ( '410' === self::controlled_status( self::scalar_string( $row['status'] ?? '' ) ) ) {
			return __( 'A 410 rule is active.', 'ua-free-404-guard' );
		}
		if ( 'same_site' === self::controlled_referrer_scope( self::scalar_string( $row['referrer_scope'] ?? '' ) ) ) {
			return __( 'Check and fix the internal link.', 'ua-free-404-guard' );
		}
		if ( 'bot' === self::controlled_kind( self::scalar_string( $row['kind'] ?? '' ) ) && preg_match( '#/(\[redacted|wp-config|phpmyadmin|\.git|vendor/phpunit)#i', self::scalar_string( $row['path_display'] ?? '' ) ) ) {
			return __( 'Likely automated probing. Keep 404 or add a narrow 410 rule.', 'ua-free-404-guard' );
		}
		if ( absint( self::scalar_string( $row['count'] ?? 0 ) ) >= 10 ) {
			return __( 'Frequent request. Review whether a redirect is appropriate.', 'ua-free-404-guard' );
		}
		return __( 'Monitor. No automatic action is recommended.', 'ua-free-404-guard' );
	}

	public static function export_logs(): array {
		$result = array();
		foreach ( self::logs() as $row ) {
			$result[] = array(
				'status'                    => (string) $row['status'],
				'kind'                      => (string) $row['kind'],
				'path_fingerprint'          => (string) $row['path_fingerprint'],
				'query_key_count'           => (int) $row['query_key_count'],
				'captured_count'            => (int) $row['count'],
				'sample_denominator'        => (int) $row['sample_denominator'],
				'first_seen'                => (string) $row['first_seen'],
				'last_seen'                 => (string) $row['last_seen'],
				'referrer_scope'            => (string) $row['referrer_scope'],
				'referrer_host_fingerprint' => (string) $row['referrer_host_fingerprint'],
				'source'                    => (string) $row['source'],
				'rule_fingerprint'          => (string) $row['rule_fingerprint'],
			);
		}
		return $result;
	}

	public static function export_redirects(): array {
		$result = array();
		foreach ( self::redirects() as $rule ) {
			$result[] = array(
				'rule_fingerprint'   => self::fingerprint( (string) $rule['id'], 'rule' ),
				'source_fingerprint' => self::fingerprint( (string) $rule['source'], 'path' ),
				'target_fingerprint' => self::fingerprint( (string) $rule['target'], 'target' ),
			);
		}
		return $result;
	}

	public static function export_gone_rules(): array {
		$result = array();
		foreach ( self::gone_rules() as $rule ) {
			$result[] = array(
				'rule_fingerprint'  => self::fingerprint( (string) $rule['id'], 'rule' ),
				'value_fingerprint' => self::fingerprint( (string) $rule['value'], 'gone' ),
			);
		}
		return $result;
	}

	public static function fingerprint( string $value, string $context ): string {
		$key = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'ua-free-404-guard';
		return substr( hash_hmac( 'sha256', $context . '|' . $value, $key ), 0, 16 );
	}

	private static function redacted_path_display( string $path ): string {
		return '/[path:' . self::fingerprint( self::normalise_path( $path ), 'path' ) . ']';
	}

	private static function referrer_summary( string $referrer ): array {
		if ( '' === $referrer ) {
			return array( 'scope' => 'none', 'host_fingerprint' => '' );
		}
		$host      = strtolower( (string) wp_parse_url( esc_url_raw( $referrer, array( 'http', 'https' ) ), PHP_URL_HOST ) );
		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( '' === $host ) {
			return array( 'scope' => 'invalid', 'host_fingerprint' => '' );
		}
		if ( hash_equals( $home_host, $host ) ) {
			return array( 'scope' => 'same_site', 'host_fingerprint' => '' );
		}
		return array( 'scope' => 'external', 'host_fingerprint' => self::fingerprint( $host, 'host' ) );
	}

	private static function controlled_status( string $status ): string {
		return in_array( $status, array( '301', '302', '307', '308', '404', '410' ), true ) ? $status : '404';
	}

	private static function controlled_kind( string $kind ): string {
		return in_array( $kind, array( 'human', 'bot', 'unknown' ), true ) ? $kind : 'unknown';
	}

	private static function controlled_source( string $source ): string {
		return in_array( $source, array( 'redirect', 'gone_rule', 'wordpress_404', 'legacy' ), true ) ? $source : 'legacy';
	}

	private static function controlled_referrer_scope( string $scope ): string {
		return in_array( $scope, array( 'none', 'same_site', 'external', 'invalid' ), true ) ? $scope : '';
	}

	private static function safe_mysql_datetime( $value ): string {
		$value = self::scalar_string( $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ? $value : '';
	}
}
