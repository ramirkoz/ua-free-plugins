<?php
/**
 * Azure Translator character budget: monthly cycle, daily budget, usage by source
 * and memory of segments that Azure translated but validation rejected.
 *
 * @package KOZ_Static_Translate
 */

namespace ramirkz\kozstx;

use DateTimeImmutable;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

final class KOZSTX_Quota {
	/** Per-day usage by source: array( 'Y-m-d' => array( kind => characters ) ), UTC days. */
	public const DAILY_OPTION = 'kozstx_usage_daily';

	/** Rejected segment counters: array( 'language|hash' => count ). */
	public const REJECTED_OPTION = 'kozstx_rejected_segments';

	/** A segment rejected this many times is not sent to Azure again until the list is cleared. */
	public const REJECTION_LIMIT = 2;

	private const KEEP_DAYS = 62;
	private const MAX_REJECTED = 5000;

	/**
	 * Where translated characters were spent.
	 *
	 * @return array<string,string>
	 */
	public static function kinds(): array {
		return array(
			'queue'      => __( 'Queue (admin run)', 'koz-static-translate' ),
			'background' => __( 'Background worker', 'koz-static-translate' ),
			'dynamic'    => __( 'Dynamic content (administrators)', 'koz-static-translate' ),
			'test'       => __( 'Connection test', 'koz-static-translate' ),
		);
	}

	public static function cycle_info(): array {
		$settings = KOZSTX_Static_Translate::settings();
		$day      = (int) $settings['reset_day'];
		$hour     = (int) $settings['reset_hour_utc'];
		$now      = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$current_month_start = new DateTimeImmutable(
			sprintf( '%s-%02d %02d:00:00', $now->format( 'Y-m' ), $day, $hour ),
			new DateTimeZone( 'UTC' )
		);

		$start = $now < $current_month_start ? $current_month_start->modify( '-1 month' ) : $current_month_start;
		$next  = $start->modify( '+1 month' );
		return array(
			'key'       => $start->format( 'Y-m-d-H' ),
			'start_ts'  => $start->getTimestamp(),
			'next_ts'   => $next->getTimestamp(),
			'start_iso' => $start->format( DATE_ATOM ),
			'next_iso'  => $next->format( DATE_ATOM ),
		);
	}

	public static function current_usage(): array {
		global $wpdb;
		$tables   = KOZSTX_Static_Translate::tables();
		$cycle    = self::cycle_info();
		$settings = KOZSTX_Static_Translate::settings();
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT language, characters, requests
				FROM {$tables['usage']}
				WHERE cycle_key = %s",
				$cycle['key']
			),
			ARRAY_A
		);

		$by_language    = array();
		$total_chars    = 0;
		$total_requests = 0;
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$by_language[ (string) $row['language'] ] = array(
				'characters' => (int) $row['characters'],
				'requests'   => (int) $row['requests'],
			);
			$total_chars    += (int) $row['characters'];
			$total_requests += (int) $row['requests'];
		}

		$limit     = (int) $settings['monthly_limit'];
		$remaining = max( 0, $limit - $total_chars );
		return array(
			'cycle'       => $cycle,
			'limit'       => $limit,
			'characters'  => $total_chars,
			'requests'    => $total_requests,
			'remaining'   => $remaining,
			'percent'     => $limit > 0 ? min( 100, ( $total_chars / $limit ) * 100 ) : 0,
			'by_language' => $by_language,
		);
	}

	/**
	 * Record characters billed by Azure.
	 *
	 * @param string $kind One of self::kinds().
	 */
	public static function increment_usage( string $language, int $characters, string $kind = 'queue' ): void {
		global $wpdb;
		$tables     = KOZSTX_Static_Translate::tables();
		$cycle      = self::cycle_info();
		$now        = current_time( 'mysql', true );
		$characters = max( 0, $characters );

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$tables['usage']}
				(cycle_key, language, characters, requests, last_request_at, created_at, updated_at)
				VALUES (%s, %s, %d, 1, %s, %s, %s)
				ON DUPLICATE KEY UPDATE
				characters = characters + VALUES(characters),
				requests = requests + 1,
				last_request_at = VALUES(last_request_at),
				updated_at = VALUES(updated_at)",
				$cycle['key'],
				$language,
				$characters,
				$now,
				$now,
				$now
			)
		);

		$kind  = array_key_exists( $kind, self::kinds() ) ? $kind : 'queue';
		$daily = self::daily_raw();
		$day   = gmdate( 'Y-m-d' );
		$daily[ $day ][ $kind ] = (int) ( $daily[ $day ][ $kind ] ?? 0 ) + $characters;
		ksort( $daily );
		update_option( self::DAILY_OPTION, array_slice( $daily, -self::KEEP_DAYS, null, true ), false );
	}

	/** @return array<string,array<string,int>> */
	private static function daily_raw(): array {
		$daily = get_option( self::DAILY_OPTION, array() );
		return is_array( $daily ) ? $daily : array();
	}

	/**
	 * Characters per day and source for the last $days UTC days, oldest first.
	 *
	 * @return array<string,array<string,int>>
	 */
	public static function daily_usage( int $days = 30 ): array {
		$raw    = self::daily_raw();
		$result = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day            = gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
			$result[ $day ] = array_map( 'intval', (array) ( $raw[ $day ] ?? array() ) );
		}
		return $result;
	}

	public static function today_usage(): int {
		$raw = self::daily_raw();
		return (int) array_sum( array_map( 'intval', (array) ( $raw[ gmdate( 'Y-m-d' ) ] ?? array() ) ) );
	}

	/**
	 * Daily character budget. 0 in the settings means monthly limit / 30.
	 */
	public static function daily_limit(): int {
		$settings = KOZSTX_Static_Translate::settings();
		$daily    = (int) ( $settings['daily_limit'] ?? 0 );
		return $daily > 0 ? $daily : (int) ceil( (int) $settings['monthly_limit'] / 30 );
	}

	/**
	 * Characters that may be sent to Azure right now: the monthly remainder minus the
	 * reserve, capped by what is left of today's budget.
	 */
	public static function available(): int {
		$settings = KOZSTX_Static_Translate::settings();
		$monthly  = max( 0, (int) self::current_usage()['remaining'] - (int) $settings['reserve_chars'] );
		$daily    = max( 0, self::daily_limit() - self::today_usage() );
		return min( $monthly, $daily );
	}

	/**
	 * Why nothing is available: 'monthly', 'daily' or '' when budget remains.
	 */
	public static function blocked_reason(): string {
		$settings = KOZSTX_Static_Translate::settings();
		if ( (int) self::current_usage()['remaining'] - (int) $settings['reserve_chars'] <= 0 ) {
			return 'monthly';
		}
		return self::daily_limit() - self::today_usage() <= 0 ? 'daily' : '';
	}

	/** Start of the next UTC day, when the daily budget renews. */
	public static function next_daily_reset(): int {
		return (int) strtotime( gmdate( 'Y-m-d', time() + DAY_IN_SECONDS ) . ' 00:00:00 UTC' );
	}

	/* ---------------------------------------------------------------------
	 * Rejected segments
	 * ------------------------------------------------------------------- */

	/** @return array<string,int> */
	private static function rejected_raw(): array {
		$rejected = get_option( self::REJECTED_OPTION, array() );
		return is_array( $rejected ) ? $rejected : array();
	}

	public static function is_rejected( string $language, string $source_hash ): bool {
		$rejected = self::rejected_raw();
		return (int) ( $rejected[ $language . '|' . $source_hash ] ?? 0 ) >= self::REJECTION_LIMIT;
	}

	public static function note_rejection( string $language, string $source_hash ): void {
		$rejected = self::rejected_raw();
		$key      = $language . '|' . $source_hash;
		$rejected[ $key ] = (int) ( $rejected[ $key ] ?? 0 ) + 1;
		if ( count( $rejected ) > self::MAX_REJECTED ) {
			$rejected = array_slice( $rejected, -self::MAX_REJECTED, null, true );
		}
		update_option( self::REJECTED_OPTION, $rejected, false );
	}

	/** Number of segments that are no longer sent because Azure output was rejected repeatedly. */
	public static function blocked_rejections(): int {
		return count( array_filter( self::rejected_raw(), static fn( $count ): bool => (int) $count >= self::REJECTION_LIMIT ) );
	}

	public static function clear_rejections(): void {
		delete_option( self::REJECTED_OPTION );
	}

	/* ---------------------------------------------------------------------
	 * Admin notices
	 * ------------------------------------------------------------------- */

	public static function admin_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		if ( 'dashboard' !== $id && ! str_contains( $id, 'koz-static-translate' ) ) {
			return;
		}
		if ( ! KOZSTX_Static_Translate::credentials_configured() ) {
			return;
		}

		$usage   = self::current_usage();
		$percent = (float) $usage['percent'];
		if ( $percent < 80 ) {
			return;
		}

		$class = $percent >= 95 ? 'notice-error' : 'notice-warning';
		printf(
			'<div class="notice %1$s"><p><strong>KOZ Static Translate:</strong> %2$s</p></div>',
			esc_attr( $class ),
			esc_html(
				sprintf(
					/* translators: 1: percent used, 2: used characters, 3: monthly limit, 4: reset date. */
					__( '%1$s%% of the monthly Azure Translator character limit is used (%2$s of %3$s). The limit resets on %4$s.', 'koz-static-translate' ),
					number_format_i18n( $percent, 1 ),
					number_format_i18n( (int) $usage['characters'] ),
					number_format_i18n( (int) $usage['limit'] ),
					wp_date( get_option( 'date_format' ), (int) $usage['cycle']['next_ts'] )
				)
			)
		);
	}
}
