<?php
namespace UAFree\DonateStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Storage {
	public const DB_VERSION = '1.1.0';
	public const DAILY_SUFFIX = 'uafree_donate_daily';
	public const SESSIONS_SUFFIX = 'uafree_donate_sessions';
	public const CONFIRMATIONS_SUFFIX = 'uafree_donate_confirmations';

	private static string $daily_table = '';
	private static string $sessions_table = '';
	private static string $confirmations_table = '';

	public static function init(): void {
		global $wpdb;
		self::$daily_table = $wpdb->prefix . self::DAILY_SUFFIX;
		self::$sessions_table = $wpdb->prefix . self::SESSIONS_SUFFIX;
		self::$confirmations_table = $wpdb->prefix . self::CONFIRMATIONS_SUFFIX;
	}

	public static function daily_table(): string {
		self::ensure_initialized();
		return self::$daily_table;
	}

	public static function sessions_table(): string {
		self::ensure_initialized();
		return self::$sessions_table;
	}

	private static function ensure_initialized(): void {
		if ( '' === self::$daily_table || '' === self::$sessions_table || '' === self::$confirmations_table ) {
			self::init();
		}
	}

	public static function install_schema(): void {
		global $wpdb;
		self::init();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$daily_sql = "CREATE TABLE " . self::$daily_table . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			stat_date date NOT NULL,
			language varchar(16) NOT NULL DEFAULT 'und',
			context_key varchar(100) NOT NULL DEFAULT 'legacy',
			event_type varchar(32) NOT NULL DEFAULT '',
			target_key varchar(100) NOT NULL DEFAULT '',
			event_count bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_bucket_v2 (stat_date,language,context_key,event_type,target_key),
			KEY stat_date (stat_date),
			KEY event_type (event_type),
			KEY context_key (context_key)
		) {$charset};";

		$sessions_sql = "CREATE TABLE " . self::$sessions_table . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			stat_date date NOT NULL,
			language varchar(16) NOT NULL DEFAULT 'und',
			context_key varchar(100) NOT NULL DEFAULT 'legacy',
			session_hash char(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY daily_language_context_session (stat_date,language,context_key,session_hash),
			KEY stat_date (stat_date),
			KEY context_key (context_key),
			KEY session_hash (session_hash)
		) {$charset};";

		$confirmations_sql = "CREATE TABLE " . self::$confirmations_table . " (
			reference_hash char(64) NOT NULL,
			provider varchar(40) NOT NULL DEFAULT 'payment',
			language varchar(16) NOT NULL DEFAULT 'und',
			context_key varchar(100) NOT NULL DEFAULT 'confirmed-donation',
			created_at datetime NOT NULL,
			PRIMARY KEY  (reference_hash),
			KEY created_at (created_at),
			KEY provider (provider)
		) {$charset};";

		dbDelta( $daily_sql );
		dbDelta( $sessions_sql );
		dbDelta( $confirmations_sql );

		self::remove_obsolete_indexes();
		self::ensure_v2_indexes();

		update_option( 'uafree_donate_stats_db_version', self::DB_VERSION, false );
	}

	private static function remove_obsolete_indexes(): void {
		global $wpdb;

		$daily_indexes = self::index_names( self::$daily_table );
		if ( in_array( 'event_bucket', $daily_indexes, true ) ) {
			$wpdb->query( "ALTER TABLE `" . self::$daily_table . "` DROP INDEX `event_bucket`" );
		}

		$session_indexes = self::index_names( self::$sessions_table );
		if ( in_array( 'daily_language_session', $session_indexes, true ) ) {
			$wpdb->query( "ALTER TABLE `" . self::$sessions_table . "` DROP INDEX `daily_language_session`" );
		}
	}

	private static function ensure_v2_indexes(): void {
		global $wpdb;

		$daily_indexes = self::index_names( self::$daily_table );
		if ( ! in_array( 'event_bucket_v2', $daily_indexes, true ) ) {
			$wpdb->query(
				"ALTER TABLE `" . self::$daily_table . "`
				ADD UNIQUE KEY `event_bucket_v2`
				(`stat_date`,`language`,`context_key`,`event_type`,`target_key`)"
			);
		}

		$session_indexes = self::index_names( self::$sessions_table );
		if ( ! in_array( 'daily_language_context_session', $session_indexes, true ) ) {
			$wpdb->query(
				"ALTER TABLE `" . self::$sessions_table . "`
				ADD UNIQUE KEY `daily_language_context_session`
				(`stat_date`,`language`,`context_key`,`session_hash`)"
			);
		}
	}

	/**
	 * @return array<int,string>
	 */
	private static function index_names( string $table ): array {
		global $wpdb;
		$rows = $wpdb->get_results( "SHOW INDEX FROM `" . $table . "`", ARRAY_A );
		$names = array();

		foreach ( (array) $rows as $row ) {
			if ( isset( $row['Key_name'] ) ) {
				$names[] = (string) $row['Key_name'];
			}
		}

		return array_values( array_unique( $names ) );
	}

	public static function increment_event(
		string $language,
		string $context_key,
		string $event_type,
		string $target_key
	): bool {
		global $wpdb;
		self::ensure_initialized();

		$now = current_time( 'mysql', true );
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO " . self::$daily_table . "
				(stat_date, language, context_key, event_type, target_key, event_count, created_at, updated_at)
				VALUES (%s, %s, %s, %s, %s, 1, %s, %s)
				ON DUPLICATE KEY UPDATE
					event_count = event_count + 1,
					updated_at = VALUES(updated_at)",
				current_time( 'Y-m-d' ),
				$language,
				$context_key,
				$event_type,
				$target_key,
				$now,
				$now
			)
		);

		return false !== $result;
	}

	public static function store_session(
		string $language,
		string $context_key,
		string $session_hash
	): bool {
		global $wpdb;
		self::ensure_initialized();

		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO " . self::$sessions_table . "
				(stat_date, language, context_key, session_hash, created_at)
				VALUES (%s, %s, %s, %s, %s)",
				current_time( 'Y-m-d' ),
				$language,
				$context_key,
				$session_hash,
				current_time( 'mysql', true )
			)
		);

		return false !== $result;
	}


	/**
	 * Record one unique server-confirmed donation.
	 *
	 * The raw provider reference is never stored. Only its HMAC hash is kept
	 * to prevent the same callback from increasing the total twice.
	 *
	 * @return string recorded|duplicate|error
	 */
	public static function record_confirmation(
		string $reference_hash,
		string $provider,
		string $language,
		string $context_key
	): string {
		global $wpdb;
		self::ensure_initialized();

		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $reference_hash ) ) {
			return 'error';
		}

		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO " . self::$confirmations_table . "
				(reference_hash, provider, language, context_key, created_at)
				VALUES (%s, %s, %s, %s, %s)",
				$reference_hash,
				$provider,
				$language,
				$context_key,
				current_time( 'mysql', true )
			)
		);

		if ( false === $result ) {
			return 'error';
		}
		if ( 0 === (int) $result ) {
			return 'duplicate';
		}

		$accepted = self::increment_event(
			$language,
			$context_key,
			'donation_success',
			$provider
		);
		if ( ! $accepted ) {
			$wpdb->delete(
				self::$confirmations_table,
				array( 'reference_hash' => $reference_hash ),
				array( '%s' )
			);
			return 'error';
		}

		update_option(
			Plugin::LAST_CONFIRMATION_OPTION,
			current_time( 'mysql', true ),
			false
		);
		return 'recorded';
	}

	public static function cleanup_old_data( int $retention_days ): void {
		global $wpdb;
		self::ensure_initialized();

		$retention_days = max( 30, min( 730, $retention_days ) );
		$cutoff = gmdate( 'Y-m-d', time() - ( $retention_days * DAY_IN_SECONDS ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM " . self::$daily_table . " WHERE stat_date < %s",
				$cutoff
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM " . self::$sessions_table . " WHERE stat_date < %s",
				$cutoff
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM " . self::$confirmations_table . " WHERE created_at < %s",
				$cutoff . ' 00:00:00'
			)
		);
	}

	public static function reset(): void {
		global $wpdb;
		self::ensure_initialized();
		$wpdb->query( 'TRUNCATE TABLE ' . self::$daily_table );
		$wpdb->query( 'TRUNCATE TABLE ' . self::$sessions_table );
		$wpdb->query( 'TRUNCATE TABLE ' . self::$confirmations_table );
		delete_option( Plugin::LAST_CONFIRMATION_OPTION );
	}

	private static function date_cutoff( int $days ): string {
		$days = max( 1, min( 730, $days ) );
		return gmdate( 'Y-m-d', time() - ( max( 1, $days - 1 ) * DAY_IN_SECONDS ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function summary( int $days = 30 ): array {
		global $wpdb;
		self::ensure_initialized();

		$cutoff = self::date_cutoff( $days );
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_type, SUM(event_count) AS total
				FROM " . self::$daily_table . "
				WHERE stat_date >= %s
				GROUP BY event_type",
				$cutoff
			),
			ARRAY_A
		);

		$map = array();
		foreach ( (array) $events as $row ) {
			$map[ (string) $row['event_type'] ] = (int) $row['total'];
		}

		$sessions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT CONCAT(stat_date, ':', context_key, ':', session_hash))
				FROM " . self::$sessions_table . "
				WHERE stat_date >= %s",
				$cutoff
			)
		);

		$payment_opens = (int) ( $map['payment_open'] ?? 0 )
			+ (int) ( $map['external_click'] ?? 0 );
		$successes = (int) ( $map['donation_success'] ?? 0 );

		$result = array(
			'days'             => max( 1, min( 730, $days ) ),
			'views'            => (int) ( $map['page_view'] ?? 0 ),
			'sessions'         => $sessions,
			'donate_clicks'    => (int) ( $map['donate_click'] ?? 0 ),
			'payment_opens'    => $payment_opens,
			'copy_clicks'      => (int) ( $map['copy_click'] ?? 0 ),
			'successes'         => $successes,
			'donations_total'   => $successes,
			'conversions_total' => $successes,
			'engagements'       => (int) ( $map['donate_click'] ?? 0 ) + $payment_opens + (int) ( $map['copy_click'] ?? 0 ),
			'conversion_rate'   => $sessions > 0 ? ( $successes / $sessions ) * 100 : 0.0,
			'events_by_type'    => $map,
		);

		return apply_filters( 'uafree_donate_stats_summary', $result, $days );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function daily_rows( int $days = 30 ): array {
		global $wpdb;
		self::ensure_initialized();

		$cutoff = self::date_cutoff( $days );
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					stat_date,
					SUM(CASE WHEN event_type = 'page_view' THEN event_count ELSE 0 END) AS views,
					SUM(CASE WHEN event_type = 'donate_click' THEN event_count ELSE 0 END) AS donate_clicks,
					SUM(CASE WHEN event_type IN ('payment_open','external_click') THEN event_count ELSE 0 END) AS payment_opens,
					SUM(CASE WHEN event_type = 'copy_click' THEN event_count ELSE 0 END) AS copy_clicks,
					SUM(CASE WHEN event_type = 'donation_success' THEN event_count ELSE 0 END) AS successes
				FROM " . self::$daily_table . "
				WHERE stat_date >= %s
				GROUP BY stat_date
				ORDER BY stat_date DESC",
				$cutoff
			),
			ARRAY_A
		);

		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT stat_date, COUNT(DISTINCT CONCAT(context_key, ':', session_hash)) AS sessions
				FROM " . self::$sessions_table . "
				WHERE stat_date >= %s
				GROUP BY stat_date",
				$cutoff
			),
			OBJECT_K
		);

		$result = array();
		foreach ( (array) $events as $row ) {
			$date = (string) $row['stat_date'];
			$result[] = array(
				'stat_date'     => $date,
				'views'         => (int) $row['views'],
				'sessions'      => isset( $sessions[ $date ] ) ? (int) $sessions[ $date ]->sessions : 0,
				'donate_clicks' => (int) $row['donate_clicks'],
				'payment_opens' => (int) $row['payment_opens'],
				'copy_clicks'   => (int) $row['copy_clicks'],
				'successes'     => (int) $row['successes'],
			);
		}
		return $result;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function top_targets( int $days = 30 ): array {
		global $wpdb;
		self::ensure_initialized();

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_type, target_key, SUM(event_count) AS event_count
				FROM " . self::$daily_table . "
				WHERE stat_date >= %s
					AND event_type IN ('donate_click','payment_open','external_click','copy_click','donation_success')
				GROUP BY event_type, target_key
				ORDER BY event_count DESC
				LIMIT 50",
				self::date_cutoff( $days )
			),
			ARRAY_A
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function context_rows( int $days = 30 ): array {
		global $wpdb;
		self::ensure_initialized();

		$cutoff = self::date_cutoff( $days );
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					context_key,
					SUM(CASE WHEN event_type = 'page_view' THEN event_count ELSE 0 END) AS views,
					SUM(CASE WHEN event_type IN ('donate_click','payment_open','external_click','copy_click') THEN event_count ELSE 0 END) AS engagements,
					SUM(CASE WHEN event_type = 'donation_success' THEN event_count ELSE 0 END) AS successes
				FROM " . self::$daily_table . "
				WHERE stat_date >= %s
				GROUP BY context_key",
				$cutoff
			),
			OBJECT_K
		);

		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT context_key, COUNT(DISTINCT CONCAT(stat_date, ':', session_hash)) AS sessions
				FROM " . self::$sessions_table . "
				WHERE stat_date >= %s
				GROUP BY context_key",
				$cutoff
			),
			OBJECT_K
		);

		$keys = array_unique( array_merge( array_keys( (array) $events ), array_keys( (array) $sessions ) ) );
		$result = array();
		foreach ( $keys as $key ) {
			$session_count = isset( $sessions[ $key ] ) ? (int) $sessions[ $key ]->sessions : 0;
			$successes = isset( $events[ $key ] ) ? (int) $events[ $key ]->successes : 0;
			$result[] = array(
				'context_key'    => $key,
				'views'          => isset( $events[ $key ] ) ? (int) $events[ $key ]->views : 0,
				'sessions'       => $session_count,
				'engagements'    => isset( $events[ $key ] ) ? (int) $events[ $key ]->engagements : 0,
				'successes'      => $successes,
				'conversion_rate'=> $session_count > 0 ? ( $successes / $session_count ) * 100 : 0.0,
			);
		}
		usort(
			$result,
			static fn( array $a, array $b ): int =>
				( $b['views'] + $b['engagements'] ) <=> ( $a['views'] + $a['engagements'] )
		);
		return $result;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function language_rows( int $days = 30 ): array {
		global $wpdb;
		self::ensure_initialized();

		$cutoff = self::date_cutoff( $days );
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					language,
					SUM(CASE WHEN event_type = 'page_view' THEN event_count ELSE 0 END) AS views,
					SUM(CASE WHEN event_type IN ('donate_click','payment_open','external_click','copy_click') THEN event_count ELSE 0 END) AS engagements,
					SUM(CASE WHEN event_type = 'donation_success' THEN event_count ELSE 0 END) AS successes
				FROM " . self::$daily_table . "
				WHERE stat_date >= %s
				GROUP BY language",
				$cutoff
			),
			OBJECT_K
		);

		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT language, COUNT(DISTINCT CONCAT(stat_date, ':', context_key, ':', session_hash)) AS sessions
				FROM " . self::$sessions_table . "
				WHERE stat_date >= %s
				GROUP BY language",
				$cutoff
			),
			OBJECT_K
		);

		$languages = array_unique( array_merge( array_keys( (array) $events ), array_keys( (array) $sessions ) ) );
		$result = array();
		foreach ( $languages as $language ) {
			$result[] = array(
				'language'    => $language,
				'views'       => isset( $events[ $language ] ) ? (int) $events[ $language ]->views : 0,
				'sessions'    => isset( $sessions[ $language ] ) ? (int) $sessions[ $language ]->sessions : 0,
				'engagements' => isset( $events[ $language ] ) ? (int) $events[ $language ]->engagements : 0,
				'successes'   => isset( $events[ $language ] ) ? (int) $events[ $language ]->successes : 0,
			);
		}
		usort(
			$result,
			static fn( array $a, array $b ): int =>
				( $b['views'] + $b['engagements'] ) <=> ( $a['views'] + $a['engagements'] )
		);
		return $result;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function export_payload( int $days = 30 ): array {
		return array(
			'generated_at_utc' => gmdate( 'c' ),
			'period_days'      => max( 1, min( 730, $days ) ),
			'privacy'          => array(
				'contains_ip_addresses'  => false,
				'contains_user_agents'   => false,
				'contains_referrers'     => false,
				'contains_payment_data'  => false,
				'contains_personal_data' => false,
			),
			'summary'          => self::summary( $days ),
			'daily'            => self::daily_rows( $days ),
			'contexts'         => self::context_rows( $days ),
			'languages'        => self::language_rows( $days ),
			'targets'          => self::top_targets( $days ),
		);
	}

	public static function table_counts(): array {
		global $wpdb;
		self::ensure_initialized();
		return array(
			'daily_rows'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::$daily_table ),
			'session_rows'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::$sessions_table ),
			'confirmation_rows' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::$confirmations_table ),
		);
	}
}

Storage::init();
