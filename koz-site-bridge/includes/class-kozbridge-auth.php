<?php
/**
 * API keys, scopes, expiry and the hourly security throttle.
 *
 * Keys are stored only as HMAC-SHA256 digests. A key has a label, a set of scopes,
 * an optional expiry date and the date it was last used (no IP address, User-Agent
 * or request history). The key created before 0.6.0 keeps working as "Legacy key"
 * with every scope.
 *
 * @package KOZ_Site_Bridge
 */

namespace ramirkz\kozbridge;

use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZBRIDGE_Auth {
	public const KEYS_OPTION       = 'kozbridge_keys';
	public const LEGACY_HASH       = 'kozbridge_api_key_hash';
	public const LEGACY_DATE       = 'kozbridge_api_key_created_at';
	public const LEGACY_USED       = 'kozbridge_api_key_last_used';
	public const REQUESTS_PER_HOUR = 120;
	private const RATE_TTL         = 3700;
	private const RATE_LOCK_TTL    = 5;
	private const MAX_KEYS         = 10;

	/** Scope => label. */
	public static function scopes(): array {
		return array(
			'basic'     => KOZBRIDGE_Bridge::tr( 'Basic diagnostics (ping, overview, plugins, suite, diagnostics, security headers, consent check)' ),
			'content'   => KOZBRIDGE_Bridge::tr( 'Content (content, navigation, links, sitemap)' ),
			'audit'     => KOZBRIDGE_Bridge::tr( 'Audits and probes (page audit, sitemap audit, HTTP probe, performance)' ),
			'logs'      => KOZBRIDGE_Bridge::tr( '404 log' ),
			'error_log' => KOZBRIDGE_Bridge::tr( 'PHP error log' ),
		);
	}

	/** Callback method => scope it needs. */
	public static function scope_for( string $method ): string {
		$map = array(
			'ping'             => 'basic',
			'overview'         => 'basic',
			'plugins'          => 'basic',
			'suite'            => 'basic',
			'diagnostics'      => 'basic',
			'security_headers' => 'basic',
			'consent_check'    => 'basic',
			'content'          => 'content',
			'navigation'       => 'content',
			'links'            => 'content',
			'sitemap_inventory' => 'content',
			'page_audit'       => 'audit',
			'rendered_audit'   => 'audit',
			'http_probe'       => 'audit',
			'performance'      => 'audit',
			'error_404_log'    => 'logs',
			'admin_error_log'  => 'error_log',
		);
		return $map[ $method ] ?? 'basic';
	}

	public static function key_digest( string $key ): string {
		return hash_hmac( 'sha256', $key, wp_salt( 'auth' ) );
	}

	private static function verify_hash( string $key, string $stored_hash ): bool {
		if ( str_starts_with( $stored_hash, 'v2:' ) ) {
			$expected = substr( $stored_hash, 3 );
			return 1 === preg_match( '/^[a-f0-9]{64}$/', $expected ) && hash_equals( $expected, self::key_digest( $key ) );
		}
		return wp_check_password( $key, $stored_hash );
	}

	private static function stored_keys(): array {
		$keys = get_option( self::KEYS_OPTION, array() );
		return is_array( $keys ) ? $keys : array();
	}

	/**
	 * All keys, the legacy key first.
	 *
	 * @return array<int,array{id:string,label:string,hash:string,scopes:array,created:string,last_used:string,expires:string,legacy:bool}>
	 */
	public static function keys(): array {
		$result = array();
		$legacy = (string) get_option( self::LEGACY_HASH, '' );
		if ( '' !== $legacy ) {
			$result[] = array(
				'id'        => 'legacy',
				'label'     => KOZBRIDGE_Bridge::tr( 'Legacy key' ),
				'hash'      => $legacy,
				'scopes'    => array_keys( self::scopes() ),
				'created'   => (string) get_option( self::LEGACY_DATE, '' ),
				'last_used' => (string) get_option( self::LEGACY_USED, '' ),
				'expires'   => '',
				'legacy'    => true,
			);
		}
		foreach ( self::stored_keys() as $key ) {
			if ( ! is_array( $key ) || empty( $key['id'] ) || empty( $key['hash'] ) ) {
				continue;
			}
			$result[] = array(
				'id'        => sanitize_key( (string) $key['id'] ),
				'label'     => sanitize_text_field( (string) ( $key['label'] ?? '' ) ),
				'hash'      => (string) $key['hash'],
				'scopes'    => array_values( array_intersect( (array) ( $key['scopes'] ?? array() ), array_keys( self::scopes() ) ) ),
				'created'   => (string) ( $key['created'] ?? '' ),
				'last_used' => (string) ( $key['last_used'] ?? '' ),
				'expires'   => (string) ( $key['expires'] ?? '' ),
				'legacy'    => false,
			);
		}
		return $result;
	}

	public static function has_keys(): bool {
		return ! empty( self::keys() );
	}

	/**
	 * Creates a key and returns the plain value (shown once) or an empty string.
	 */
	public static function create( string $label, array $scopes, int $days ): string {
		$keys = self::stored_keys();
		if ( count( $keys ) >= self::MAX_KEYS ) {
			return '';
		}
		$scopes = array_values( array_intersect( $scopes, array_keys( self::scopes() ) ) );
		if ( empty( $scopes ) ) {
			$scopes = array( 'basic' );
		}
		try {
			$plain = 'kozb_' . bin2hex( random_bytes( 32 ) );
			$id    = bin2hex( random_bytes( 4 ) );
		} catch ( \Throwable $error ) {
			$plain = 'kozb_' . wp_generate_password( 64, false, false );
			$id    = strtolower( wp_generate_password( 8, false, false ) );
		}
		$keys[] = array(
			'id'        => $id,
			'label'     => '' !== trim( $label ) ? substr( sanitize_text_field( $label ), 0, 60 ) : KOZBRIDGE_Bridge::tr( 'API key' ),
			'hash'      => 'v2:' . self::key_digest( $plain ),
			'scopes'    => $scopes,
			'created'   => current_time( 'mysql' ),
			'last_used' => '',
			'expires'   => $days > 0 ? gmdate( 'Y-m-d', time() + $days * DAY_IN_SECONDS ) : '',
		);
		update_option( self::KEYS_OPTION, array_values( $keys ), false );
		return $plain;
	}

	public static function revoke( string $id ): void {
		if ( 'legacy' === $id ) {
			delete_option( self::LEGACY_HASH );
			delete_option( self::LEGACY_DATE );
			delete_option( self::LEGACY_USED );
		} else {
			update_option( self::KEYS_OPTION, array_values( array_filter( self::stored_keys(), static fn( $key ): bool => is_array( $key ) && ( $key['id'] ?? '' ) !== $id ) ), false );
		}
		self::delete_rate_limit_options();
	}

	/** Records the day a key was last used (at most one write per key per day). */
	private static function touch( array $key ): void {
		$today = current_time( 'Y-m-d' );
		if ( str_starts_with( (string) $key['last_used'], $today ) ) {
			return;
		}
		if ( ! empty( $key['legacy'] ) ) {
			update_option( self::LEGACY_USED, $today, false );
			return;
		}
		$keys = self::stored_keys();
		foreach ( $keys as $index => $stored ) {
			if ( is_array( $stored ) && ( $stored['id'] ?? '' ) === $key['id'] ) {
				$keys[ $index ]['last_used'] = $today;
			}
		}
		update_option( self::KEYS_OPTION, $keys, false );
	}

	private static function find( string $plain ): ?array {
		foreach ( self::keys() as $key ) {
			if ( self::verify_hash( $plain, $key['hash'] ) ) {
				return $key;
			}
		}
		return null;
	}

	/**
	 * REST permission: HTTPS, a valid unexpired key with the scope, and the hourly throttle.
	 *
	 * @return true|WP_Error
	 */
	public static function permission( WP_REST_Request $request, string $scope ) {
		if ( 'https' !== wp_parse_url( home_url(), PHP_URL_SCHEME ) ) {
			return new WP_Error( 'kozbridge_https_required', 'Site Bridge requires HTTPS.', array( 'status' => 503 ) );
		}
		if ( ! self::has_keys() ) {
			return new WP_Error( 'kozbridge_not_configured', 'API key is not configured.', array( 'status' => 503 ) );
		}
		$plain = trim( (string) $request->get_header( 'X-KOZ-Key' ) );
		if ( '' === $plain ) {
			$plain = trim( (string) $request->get_header( 'X-UAFree-Key' ) );
		}
		$key = ( strlen( $plain ) >= 32 && strlen( $plain ) <= 128 ) ? self::find( $plain ) : null;
		if ( null === $key ) {
			return new WP_Error( 'kozbridge_unauthorized', 'Invalid or missing API key.', array( 'status' => 401 ) );
		}
		if ( '' !== $key['expires'] && $key['expires'] < gmdate( 'Y-m-d' ) ) {
			return new WP_Error( 'kozbridge_key_expired', 'The API key has expired.', array( 'status' => 401 ) );
		}
		if ( ! in_array( $scope, $key['scopes'], true ) ) {
			return new WP_Error( 'kozbridge_scope', 'This API key is not allowed to use this endpoint.', array( 'status' => 403 ) );
		}
		$rate = self::increment_rate_limit( substr( self::key_digest( $plain ), 0, 16 ) );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}
		if ( $rate > self::REQUESTS_PER_HOUR ) {
			return new WP_Error( 'kozbridge_rate_limit', 'Hourly API rate limit exceeded.', array( 'status' => 429 ) );
		}
		self::touch( $key );
		return true;
	}

	/** @return int|WP_Error */
	private static function increment_rate_limit( string $key_id ) {
		global $wpdb;
		$rate_key  = 'kozbridge_rl_' . $key_id;
		$bucket    = gmdate( 'YmdH' );
		$lock_name = 'kozbridge_rate_' . substr( hash( 'sha256', $rate_key ), 0, 40 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MySQL advisory lock coordinates concurrent rate-limit updates.
		$lock = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, self::RATE_LOCK_TTL ) );
		if ( '1' !== (string) $lock ) {
			return new WP_Error( 'kozbridge_rate_busy', 'Rate limiter is busy. Retry shortly.', array( 'status' => 503 ) );
		}
		try {
			$state = get_transient( $rate_key );
			if ( ! is_array( $state ) || (string) ( $state['bucket'] ?? '' ) !== $bucket || (int) ( $state['expires'] ?? 0 ) <= time() ) {
				$state = array( 'bucket' => $bucket, 'count' => 0, 'expires' => time() + self::RATE_TTL );
			}
			$current = max( 0, (int) ( $state['count'] ?? 0 ) );
			if ( $current >= self::REQUESTS_PER_HOUR ) {
				return self::REQUESTS_PER_HOUR + 1;
			}
			$state['count'] = $current + 1;
			if ( ! set_transient( $rate_key, $state, self::RATE_TTL ) ) {
				return new WP_Error( 'kozbridge_rate_storage', 'Rate limiter storage failed.', array( 'status' => 503 ) );
			}
			return (int) $state['count'];
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Releases the advisory lock acquired above.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	public static function delete_rate_limit_options(): void {
		global $wpdb;
		foreach ( array( 'kozbridge_rl_', 'koz_site_bridge_rl_', 'uafree_bridge_rl_' ) as $prefix ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup scans only Site Bridge transient names.
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT option_name FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
					$wpdb->options,
					$wpdb->esc_like( '_transient_' . $prefix ) . '%',
					$wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%'
				)
			);
			foreach ( (array) $rows as $option_name ) {
				delete_option( (string) $option_name );
			}
		}
	}
}
