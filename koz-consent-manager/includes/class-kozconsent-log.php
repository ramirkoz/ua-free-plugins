<?php
/**
 * Optional consent records (proof of consent), off by default.
 *
 * One row per random consent ID created in the visitor's browser: the latest
 * choice, policy version, time, language and whether a Global Privacy Control
 * signal was sent. No IP address, User-Agent, account or page address is stored.
 *
 * @package KOZ_Consent_Manager
 */

namespace ramirkz\kozconsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter

final class KOZCONSENT_Log {
	public const REST_NAMESPACE = 'kozconsent/v1';
	public const CLEANUP_HOOK   = 'kozconsent_log_cleanup';
	private const MAX_ROWS      = 200000;

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup' ) );
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'kozconsent_log';
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				consent_id char(32) NOT NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				policy_version varchar(60) NOT NULL DEFAULT '',
				choice varchar(10) NOT NULL DEFAULT 'custom',
				analytics tinyint(1) unsigned NOT NULL DEFAULT 0,
				advertising tinyint(1) unsigned NOT NULL DEFAULT 0,
				external_media tinyint(1) unsigned NOT NULL DEFAULT 0,
				gpc tinyint(1) unsigned NOT NULL DEFAULT 0,
				language varchar(5) NOT NULL DEFAULT '',
				changes int(10) unsigned NOT NULL DEFAULT 1,
				PRIMARY KEY  (id),
				UNIQUE KEY consent_id (consent_id),
				KEY updated_at (updated_at)
			) {$wpdb->get_charset_collate()};"
		);
	}

	public static function table_exists(): bool {
		global $wpdb;
		static $exists = null;
		if ( null === $exists ) {
			$exists = self::table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( self::table() ) ) );
		}
		return $exists;
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}

	public static function register_route(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/log',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_record' ),
				// Public by design: visitors are not logged in and pages may be cached, so no nonce exists.
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'             => array( 'type' => 'string', 'required' => true, 'pattern' => '^[a-f0-9]{32}$' ),
					'choice'         => array( 'type' => 'string', 'required' => true, 'enum' => array( 'accept', 'reject', 'custom' ) ),
					'analytics'      => array( 'type' => 'boolean', 'required' => true ),
					'advertising'    => array( 'type' => 'boolean', 'required' => true ),
					'external_media' => array( 'type' => 'boolean', 'required' => true ),
					'policy'         => array( 'type' => 'string', 'required' => true ),
					'gpc'            => array( 'type' => 'boolean', 'default' => false ),
					'language'       => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);
	}

	public static function rest_record( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$settings = KOZCONSENT_Settings::stored();
		if ( empty( $settings['log_enabled'] ) || ! self::table_exists() ) {
			return new \WP_REST_Response( array( 'recorded' => false ), 200 );
		}
		$policy = KOZCONSENT_Settings::sanitize_policy( (string) $request->get_param( 'policy' ) );
		if ( ! hash_equals( (string) $settings['policy_version'], $policy ) ) {
			return new \WP_REST_Response( array( 'recorded' => false ), 200 );
		}
		$language = sanitize_key( (string) $request->get_param( 'language' ) );
		$language = in_array( $language, KOZCONSENT_Settings::SUPPORTED_LANGUAGES, true ) ? $language : '';
		$now      = current_time( 'mysql', true );
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (consent_id, created_at, updated_at, policy_version, choice, analytics, advertising, external_media, gpc, language, changes)
				VALUES (%s, %s, %s, %s, %s, %d, %d, %d, %d, %s, 1)
				ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at), policy_version = VALUES(policy_version), choice = VALUES(choice), analytics = VALUES(analytics), advertising = VALUES(advertising), external_media = VALUES(external_media), gpc = VALUES(gpc), language = VALUES(language), changes = changes + 1',
				self::table(),
				(string) $request->get_param( 'id' ),
				$now,
				$now,
				$policy,
				(string) $request->get_param( 'choice' ),
				$request->get_param( 'analytics' ) ? 1 : 0,
				$request->get_param( 'advertising' ) ? 1 : 0,
				$request->get_param( 'external_media' ) ? 1 : 0,
				$request->get_param( 'gpc' ) ? 1 : 0,
				$language
			)
		);
		return new \WP_REST_Response( array( 'recorded' => true ), 200 );
	}

	public static function cleanup(): void {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return;
		}
		$days   = (int) KOZCONSENT_Settings::stored()['log_retention'];
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE updated_at < %s', self::table(), $cutoff ) );
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );
		if ( $total > self::MAX_ROWS ) {
			$edge = $wpdb->get_var( $wpdb->prepare( 'SELECT updated_at FROM %i ORDER BY updated_at DESC LIMIT %d, 1', self::table(), self::MAX_ROWS ) );
			if ( is_string( $edge ) ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE updated_at <= %s', self::table(), $edge ) );
			}
		}
	}

	/** @return array{total:int,accept:int,reject:int,custom:int,gpc:int} Last 30 days. */
	public static function stats(): array {
		global $wpdb;
		$empty = array( 'total' => 0, 'accept' => 0, 'reject' => 0, 'custom' => 0, 'gpc' => 0 );
		if ( ! self::table_exists() ) {
			return $empty;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, SUM(choice = 'accept') AS accept, SUM(choice = 'reject') AS reject, SUM(choice = 'custom') AS custom, SUM(gpc = 1) AS gpc FROM %i WHERE updated_at >= %s",
				self::table(),
				gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS )
			),
			ARRAY_A
		);
		return is_array( $row ) ? array_map( 'intval', array_merge( $empty, $row ) ) : $empty;
	}

	public static function recent( int $limit = 50 ): array {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return array();
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY updated_at DESC LIMIT %d', self::table(), $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public static function export_csv(): string {
		global $wpdb;
		$lines = array( 'consent_id,created_utc,updated_utc,policy_version,choice,analytics,advertising,external_media,gpc,language,changes' );
		if ( self::table_exists() ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT consent_id, created_at, updated_at, policy_version, choice, analytics, advertising, external_media, gpc, language, changes FROM %i ORDER BY updated_at DESC LIMIT %d', self::table(), self::MAX_ROWS ), ARRAY_N );
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$lines[] = implode( ',', array_map( static fn( $cell ): string => '"' . str_replace( '"', '""', (string) $cell ) . '"', $row ) );
			}
		}
		return implode( "\n", $lines ) . "\n";
	}

	public static function clear(): void {
		global $wpdb;
		if ( self::table_exists() ) {
			$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', self::table() ) );
		}
	}

	public static function drop(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table() ) );
	}
}
