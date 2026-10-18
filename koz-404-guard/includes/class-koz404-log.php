<?php
/**
 * Grouped 404/410/redirect log in its own table.
 *
 * One row per status, visitor type, source and path. A hit is a single
 * INSERT … ON DUPLICATE KEY UPDATE. No IP address, User-Agent, query value or
 * external referrer is stored. Paths are stored only when "Show paths" is on;
 * otherwise only salted fingerprints are kept, as before 2.2.0.
 *
 * @package KOZ_404_Guard
 */

namespace ramirkz\koz404;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter

final class KOZ404_Log {
	public const CLEANUP_HOOK = 'koz404_cleanup';

	private const SOURCES = array( 'redirect', 'gone_rule', 'wordpress_404', 'system_route_404', 'attachment_404', 'static_404', 'legacy' );

	public static function init(): void {
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup' ) );
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'koz404_log';
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				row_hash char(32) NOT NULL,
				status smallint(5) unsigned NOT NULL DEFAULT 404,
				kind varchar(10) NOT NULL DEFAULT 'human',
				source varchar(20) NOT NULL DEFAULT 'wordpress_404',
				path varchar(400) NOT NULL DEFAULT '',
				path_fingerprint char(16) NOT NULL DEFAULT '',
				query_keys varchar(255) NOT NULL DEFAULT '',
				query_key_count smallint(5) unsigned NOT NULL DEFAULT 0,
				referrer_scope varchar(10) NOT NULL DEFAULT 'none',
				referrer_path varchar(400) NOT NULL DEFAULT '',
				referrer_host_fingerprint char(16) NOT NULL DEFAULT '',
				rule_id varchar(100) NOT NULL DEFAULT '',
				hits bigint(20) unsigned NOT NULL DEFAULT 0,
				first_seen datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				last_seen datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				ignored tinyint(1) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY row_hash (row_hash),
				KEY last_seen (last_seen),
				KEY status_hits (status,hits),
				KEY rule_id (rule_id)
			) {$charset};"
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

	/** Moves the pre-2.2.0 option log into the table (fingerprints only) and removes the option. */
	public static function import_legacy_option(): void {
		global $wpdb;
		$rows = get_option( KOZ404_Guard::LOG_OPTION, false );
		if ( false === $rows ) {
			return;
		}
		if ( is_array( $rows ) && self::table_exists() ) {
			foreach ( array_slice( $rows, 0, 1000 ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$fingerprint = KOZ404_Guard::scalar_string( $row['path_fingerprint'] ?? '' );
				if ( ! preg_match( '/^[a-f0-9]{16}$/', $fingerprint ) ) {
					continue;
				}
				$status = in_array( (int) ( $row['status'] ?? 404 ), array( 301, 302, 307, 308, 404, 410 ), true ) ? (int) $row['status'] : 404;
				$kind   = in_array( (string) ( $row['kind'] ?? '' ), array( 'human', 'bot', 'unknown' ), true ) ? (string) $row['kind'] : 'unknown';
				$source = in_array( (string) ( $row['source'] ?? '' ), self::SOURCES, true ) ? (string) $row['source'] : 'legacy';
				$first  = preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ( $row['first_seen'] ?? '' ) ) ? (string) $row['first_seen'] : current_time( 'mysql' );
				$last   = preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ( $row['last_seen'] ?? '' ) ) ? (string) $row['last_seen'] : $first;
				$wpdb->query(
					$wpdb->prepare(
						'INSERT IGNORE INTO %i (row_hash, status, kind, source, path_fingerprint, query_key_count, referrer_scope, hits, first_seen, last_seen) VALUES (%s, %d, %s, %s, %s, %d, %s, %d, %s, %s)',
						self::table(),
						md5( 'legacy|' . $status . '|' . $kind . '|' . $source . '|' . $fingerprint ),
						$status,
						$kind,
						$source,
						$fingerprint,
						absint( $row['query_key_count'] ?? 0 ),
						in_array( (string) ( $row['referrer_scope'] ?? '' ), array( 'none', 'same_site', 'external', 'invalid' ), true ) ? (string) $row['referrer_scope'] : 'none',
						absint( $row['count'] ?? 0 ),
						$first,
						$last
					)
				);
			}
		}
		delete_option( KOZ404_Guard::LOG_OPTION );
	}

	public static function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	public static function unschedule_cleanup(): void {
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}

	/** Records one hit. Called only for visitors that pass the logging settings. */
	public static function record( int $status, string $kind, string $source, string $rule_id = '' ): void {
		global $wpdb;
		$settings = KOZ404_Guard::settings();
		if ( empty( $settings['log_enabled'] ) || ! self::table_exists() ) {
			return;
		}
		if ( ( 'bot' === $kind && empty( $settings['log_bots'] ) ) || ( 'bot' !== $kind && empty( $settings['log_humans'] ) ) ) {
			return;
		}
		$source = in_array( $source, self::SOURCES, true ) ? $source : 'wordpress_404';
		$path   = KOZ404_Guard::request_path();
		$show   = ! empty( $settings['log_paths'] );

		$keys = array();
		if ( ! empty( $settings['log_query_keys'] ) ) {
			foreach ( KOZ404_Guard::query_pairs( KOZ404_Guard::raw_query() ) as $pair ) {
				$keys[ $show ? $pair['key'] : KOZ404_Guard::fingerprint( $pair['key'], 'query-key' ) ] = true;
			}
		}
		$keys = array_keys( $keys );
		sort( $keys, SORT_STRING );
		$path_fingerprint = KOZ404_Guard::fingerprint( $path, 'path' );
		$referrer         = KOZ404_Guard::referrer_summary( KOZ404_Guard::current_referrer() );
		$hash             = md5( $status . '|' . $kind . '|' . $source . '|' . $path_fingerprint . '|' . implode( ',', $keys ) );
		$now              = current_time( 'mysql' );

		// With a persistent object cache, a row is written at most once per 10 seconds.
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() && ! wp_cache_add( 'hit-' . $hash, 1, 'koz-404-guard', 10 ) ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (row_hash, status, kind, source, path, path_fingerprint, query_keys, query_key_count, referrer_scope, referrer_path, referrer_host_fingerprint, rule_id, hits, first_seen, last_seen)
				VALUES (%s, %d, %s, %s, %s, %s, %s, %d, %s, %s, %s, %s, 1, %s, %s)
				ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), referrer_scope = VALUES(referrer_scope), referrer_path = IF(VALUES(referrer_path) <> \'\', VALUES(referrer_path), referrer_path), referrer_host_fingerprint = VALUES(referrer_host_fingerprint), rule_id = VALUES(rule_id), path = VALUES(path)',
				self::table(),
				$hash,
				$status,
				$kind,
				$source,
				$show ? KOZ404_Guard::truncate( $path, 400 ) : '',
				$path_fingerprint,
				KOZ404_Guard::truncate( implode( ',', $keys ), 255 ),
				count( $keys ),
				$referrer['scope'],
				$show && 'same_site' === $referrer['scope'] ? KOZ404_Guard::truncate( $referrer['path'], 400 ) : '',
				$referrer['host_fingerprint'],
				KOZ404_Guard::truncate( $rule_id, 100 ),
				$now,
				$now
			)
		);

		do_action(
			'koz404_event',
			array(
				'status'           => (string) $status,
				'kind'             => $kind,
				'path_fingerprint' => $path_fingerprint,
				'query_key_count'  => count( $keys ),
				'source'           => $source,
			)
		);
	}

	/** Deletes rows older than the retention period and trims the table to the row limit. */
	public static function cleanup(): void {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return;
		}
		$settings = KOZ404_Guard::settings();
		$cutoff   = wp_date( 'Y-m-d H:i:s', time() - (int) $settings['log_retention'] * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE last_seen < %s', self::table(), $cutoff ) );
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );
		$limit = (int) $settings['log_limit'];
		if ( $total > $limit ) {
			$edge = $wpdb->get_var( $wpdb->prepare( 'SELECT last_seen FROM %i ORDER BY last_seen DESC LIMIT %d, 1', self::table(), $limit ) );
			if ( is_string( $edge ) ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE last_seen <= %s', self::table(), $edge ) );
			}
		}
	}

	/**
	 * Log rows for the admin screen.
	 *
	 * @param array{view?:string,kind?:string,search?:string,orderby?:string,page?:int,per_page?:int,show_ignored?:bool} $args
	 * @return array{rows:array,total:int}
	 */
	public static function query( array $args ): array {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return array( 'rows' => array(), 'total' => 0 );
		}
		$where  = array( '1=1' );
		$params = array( self::table() );
		$view   = (string) ( $args['view'] ?? '404' );
		if ( '404' === $view ) {
			$where[] = 'status = 404';
		} elseif ( '410' === $view ) {
			$where[] = 'status = 410';
		} elseif ( 'redirect' === $view ) {
			$where[] = 'status BETWEEN 300 AND 399';
		} elseif ( 'internal' === $view ) {
			$where[] = "status = 404 AND referrer_scope = 'same_site'";
		}
		if ( empty( $args['show_ignored'] ) ) {
			$where[] = 'ignored = 0';
		}
		$kind = (string) ( $args['kind'] ?? '' );
		if ( in_array( $kind, array( 'human', 'bot' ), true ) ) {
			$where[]  = 'kind = %s';
			$params[] = $kind;
		}
		$search = (string) ( $args['search'] ?? '' );
		if ( '' !== $search ) {
			$where[]  = 'path LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		$order    = 'last' === ( $args['orderby'] ?? 'hits' ) ? 'last_seen DESC' : 'hits DESC, last_seen DESC';
		$per_page = max( 10, min( 200, (int) ( $args['per_page'] ?? 50 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$sql_where = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The WHERE clause is built from fixed fragments; values use placeholders.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$sql_where}", $params ) );
		$rows_params   = $params;
		$rows_params[] = $per_page;
		$rows_params[] = ( $page - 1 ) * $per_page;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- See above; ORDER BY is one of two fixed strings; replacements are passed as one array.
		$rows =$wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$sql_where} ORDER BY {$order} LIMIT %d OFFSET %d", $rows_params ), ARRAY_A );
		return array( 'rows' => is_array( $rows ) ? $rows : array(), 'total' => $total );
	}

	public static function row( int $id ): ?array {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public static function set_ignored( int $id, bool $ignored ): void {
		global $wpdb;
		if ( self::table_exists() ) {
			$wpdb->update( self::table(), array( 'ignored' => $ignored ? 1 : 0 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
		}
	}

	public static function delete_row( int $id ): void {
		global $wpdb;
		if ( self::table_exists() ) {
			$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		}
	}

	/** Removes 404 rows for a path once a rule handles it. */
	public static function resolve_path( string $path ): void {
		global $wpdb;
		if ( self::table_exists() && '' !== $path ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE status = 404 AND path_fingerprint = %s', self::table(), KOZ404_Guard::fingerprint( $path, 'path' ) ) );
		}
	}

	public static function clear(): void {
		global $wpdb;
		if ( self::table_exists() ) {
			$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', self::table() ) );
		}
	}

	/** Paths are removed from all rows; fingerprints and counters stay. */
	public static function forget_paths(): void {
		global $wpdb;
		if ( self::table_exists() ) {
			$wpdb->query( $wpdb->prepare( "UPDATE %i SET path = '', referrer_path = '', query_keys = ''", self::table() ) );
		}
	}

	/** @return array<string,array{hits:int,last:string}> Redirect hits per rule ID. */
	public static function rule_hits(): array {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return array();
		}
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT rule_id, SUM(hits) AS hits, MAX(last_seen) AS last FROM %i WHERE rule_id <> '' GROUP BY rule_id", self::table() ), ARRAY_A );
		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$result[ (string) $row['rule_id'] ] = array( 'hits' => (int) $row['hits'], 'last' => (string) $row['last'] );
		}
		return $result;
	}

	/** @return array{rows:int,open_404:int,internal_404:int} */
	public static function summary_counts(): array {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return array( 'rows' => 0, 'open_404' => 0, 'internal_404' => 0 );
		}
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT COUNT(*) AS total_rows, SUM(status = 404 AND ignored = 0) AS open_404, SUM(status = 404 AND ignored = 0 AND referrer_scope = 'same_site') AS internal_404 FROM %i", self::table() ),
			ARRAY_A
		);
		return array(
			'rows'         => (int) ( $row['total_rows'] ?? 0 ),
			'open_404'     => (int) ( $row['open_404'] ?? 0 ),
			'internal_404' => (int) ( $row['internal_404'] ?? 0 ),
		);
	}

	/** Top open 404 rows since a date. */
	public static function top( int $limit, string $since = '', bool $internal_only = false ): array {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return array();
		}
		$since = '' !== $since ? $since : '1970-01-01 00:00:00';
		$extra = $internal_only ? " AND referrer_scope = 'same_site'" : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $extra is a fixed fragment.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE status = 404 AND ignored = 0 AND last_seen >= %s{$extra} ORDER BY hits DESC LIMIT %d", self::table(), $since, $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** Whether a path has 404 hits in the log, for the link audit. */
	public static function hits_for_path( string $path ): int {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(hits) FROM %i WHERE status = 404 AND path_fingerprint = %s', self::table(), KOZ404_Guard::fingerprint( $path, 'path' ) ) );
	}

	/** Privacy-safe export: fingerprints and counters only. */
	public static function export(): array {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return array();
		}
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT status, kind, source, path_fingerprint, query_key_count, referrer_scope, referrer_host_fingerprint, rule_id, hits, first_seen, last_seen, ignored FROM %i ORDER BY hits DESC LIMIT 5000', self::table() ), ARRAY_A );
		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$result[] = array(
				'status'                    => (string) $row['status'],
				'kind'                      => (string) $row['kind'],
				'source'                    => (string) $row['source'],
				'path_fingerprint'          => (string) $row['path_fingerprint'],
				'query_key_count'           => (int) $row['query_key_count'],
				'captured_count'            => (int) $row['hits'],
				'first_seen'                => (string) $row['first_seen'],
				'last_seen'                 => (string) $row['last_seen'],
				'referrer_scope'            => (string) $row['referrer_scope'],
				'referrer_host_fingerprint' => (string) $row['referrer_host_fingerprint'],
				'rule_fingerprint'          => '' !== (string) $row['rule_id'] ? KOZ404_Guard::fingerprint( (string) $row['rule_id'], 'rule' ) : '',
				'ignored'                   => (bool) $row['ignored'],
			);
		}
		return $result;
	}

	public static function drop(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table() ) );
	}
}
