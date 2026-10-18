<?php
/**
 * Read-only view of the KOZ Static Translate 0.10+ Azure budget, background worker,
 * page-text queue, rejected segments and translation sitemap.
 *
 * Nothing here writes options, schedules events or contacts Azure.
 *
 * @package KOZ_Translate_Diagnostics
 */

namespace ramirkz\koztdiag;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZTDIAG_Budget {
	private const QUOTA_CLASS = '\\ramirkz\\kozstx\\KOZSTX_Quota';
	private const TRANSLATOR_CLASS = '\\ramirkz\\kozstx\\KOZSTX_Static_Translate';
	private const CRON_HOOK = 'kozstx_cron';
	private const DAILY_OPTION = 'kozstx_usage_daily';
	private const REJECTED_OPTION = 'kozstx_rejected_segments';
	private const RESIDUAL_OPTION = 'kozstx_residual_queue';
	private const REJECTION_LIMIT = 2;
	public const SITEMAP_PATH = 'koz-translations-sitemap.xml';
	private const RESIDUAL_LIMIT = 3000;
	private const LEGACY_SITEMAP_PATH = 'uafree-translations-sitemap.xml';

	/** Hooks that earlier translator releases scheduled and 0.10.0 no longer uses. */
	private const RETIRED_HOOKS = array( 'uafree_st_auto_cron' );

	private static ?array $snapshot = null;

	/** Settings keys the translator 0.10+ is known to store. */
	public static function known_setting_keys(): array {
		return array(
			'switcher_position', 'reserve_chars', 'batch_chars', 'max_segments', 'max_attempts', 'reset_day', 'reset_hour_utc',
			'request_interval', 'core_rescan_hours', 'daily_limit', 'background_worker', 'protected_terms',
			'report_title_prefix',
		);
	}

	/**
	 * Settings added in translator 0.10.0, reduced to flags and numbers.
	 */
	public static function settings_summary( array $settings ): array {
		$terms = $settings['protected_terms'] ?? '';
		$terms = is_array( $terms ) ? $terms : preg_split( '/\R/', (string) $terms );
		$terms = array_filter( array_map( 'trim', (array) $terms ), 'strlen' );
		return array(
			'daily_limit' => isset( $settings['daily_limit'] ) ? max( 0, (int) $settings['daily_limit'] ) : null,
			'reserve_chars' => isset( $settings['reserve_chars'] ) ? max( 0, (int) $settings['reserve_chars'] ) : null,
			'background_worker' => ! empty( $settings['background_worker'] ),
			'protected_terms_count' => count( $terms ),
			'report_title_prefix_set' => '' !== trim( (string) ( $settings['report_title_prefix'] ?? '' ) ),
		);
	}

	private static function quota_available(): bool {
		return class_exists( self::QUOTA_CLASS, false );
	}

	private static function call( string $method, mixed $default, array $args = array() ): mixed {
		if ( ! self::quota_available() || ! method_exists( self::QUOTA_CLASS, $method ) ) {
			return $default;
		}
		try {
			return call_user_func_array( array( self::QUOTA_CLASS, $method ), $args );
		} catch ( \Throwable $error ) {
			return $default;
		}
	}

	private static function option_array( string $name ): array {
		$value = get_option( $name, array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Budget, worker, queue and sitemap state. Cached for the request.
	 */
	public static function snapshot(): array {
		if ( null !== self::$snapshot ) {
			return self::$snapshot;
		}

		$raw_settings = get_option( 'kozstx_settings', array() );
		$raw_settings = is_array( $raw_settings ) ? $raw_settings : array();
		$monthly_limit = (int) ( $raw_settings['monthly_limit'] ?? 0 );

		$usage = (array) self::call( 'current_usage', array() );
		$monthly_used = (int) ( $usage['characters'] ?? 0 );
		if ( isset( $usage['limit'] ) ) {
			$monthly_limit = (int) $usage['limit'];
		}
		$daily_limit = (int) self::call( 'daily_limit', 0 );
		if ( $daily_limit <= 0 ) {
			$configured = (int) ( $raw_settings['daily_limit'] ?? 0 );
			$daily_limit = $configured > 0 ? $configured : (int) ceil( $monthly_limit / 30 );
		}

		$daily_raw = self::option_array( self::DAILY_OPTION );
		$today_key = gmdate( 'Y-m-d' );
		$today = (int) array_sum( array_map( 'intval', (array) ( $daily_raw[ $today_key ] ?? array() ) ) );

		$by_source = array();
		$cutoff = gmdate( 'Y-m-d', time() - 29 * DAY_IN_SECONDS );
		foreach ( $daily_raw as $day => $kinds ) {
			if ( ! is_string( $day ) || $day < $cutoff ) {
				continue;
			}
			foreach ( (array) $kinds as $kind => $characters ) {
				$kind = sanitize_key( (string) $kind );
				$by_source[ $kind ] = (int) ( $by_source[ $kind ] ?? 0 ) + (int) $characters;
			}
		}
		ksort( $by_source );

		$rejected = self::option_array( self::REJECTED_OPTION );
		$blocked = count( array_filter( $rejected, static fn( $count ): bool => (int) $count >= self::REJECTION_LIMIT ) );

		$residual = self::option_array( self::RESIDUAL_OPTION );
		$residual_by_language = array();
		foreach ( $residual as $language => $items ) {
			if ( is_array( $items ) && ! empty( $items ) ) {
				$residual_by_language[ sanitize_key( (string) $language ) ] = count( $items );
			}
		}
		ksort( $residual_by_language );

		$next_run = wp_next_scheduled( self::CRON_HOOK );
		$retired = array();
		foreach ( self::RETIRED_HOOKS as $hook ) {
			if ( false !== wp_next_scheduled( $hook ) ) {
				$retired[] = $hook;
			}
		}

		$cycle = (array) ( $usage['cycle'] ?? array() );
		$percent = $monthly_limit > 0 ? min( 100.0, ( $monthly_used / $monthly_limit ) * 100 ) : 0.0;

		self::$snapshot = array(
			'available' => ! empty( $raw_settings ),
			'quota_api' => self::quota_available(),
			'monthly' => array(
				'limit' => $monthly_limit,
				'used' => $monthly_used,
				'remaining' => max( 0, $monthly_limit - $monthly_used ),
				'percent' => round( $percent, 1 ),
				'checked' => ! empty( $usage ),
				'resets_at' => isset( $cycle['next_ts'] ) ? gmdate( 'c', (int) $cycle['next_ts'] ) : '',
			),
			'daily' => array(
				'limit' => $daily_limit,
				'used' => $today,
				'remaining' => max( 0, $daily_limit - $today ),
				'resets_at' => gmdate( 'c', (int) strtotime( $today_key . ' 00:00:00 UTC' ) + DAY_IN_SECONDS ),
			),
			'available_now' => (int) self::call( 'available', max( 0, min( $monthly_limit - $monthly_used, $daily_limit - $today ) ) ),
			'blocked_by' => sanitize_key( (string) self::call( 'blocked_reason', '' ) ),
			'usage_by_source_30_days' => $by_source,
			'worker' => array(
				'enabled' => ! empty( $raw_settings['background_worker'] ),
				'event' => self::CRON_HOOK,
				'scheduled' => false !== $next_run,
				'next_run_utc' => false !== $next_run ? gmdate( 'c', (int) $next_run ) : '',
				'next_run_overdue' => false !== $next_run && (int) $next_run < time() - 15 * MINUTE_IN_SECONDS,
				'cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'retired_hooks' => $retired,
			),
			'residual_queue' => array(
				'total' => (int) array_sum( $residual_by_language ),
				'by_language' => $residual_by_language,
			),
			'rejected_segments' => array(
				'tracked' => count( $rejected ),
				'blocked' => $blocked,
			),
			'sitemap' => self::sitemap_status(),
			'settings' => self::settings_summary( $raw_settings ),
		);
		return self::$snapshot;
	}

	/**
	 * Whether the translation sitemap rewrite rule is stored. Local check, no HTTP request.
	 */
	private static function sitemap_status(): array {
		$rules = get_option( 'rewrite_rules', array() );
		$rules = is_array( $rules ) ? $rules : array();
		$has = static function ( string $path ) use ( $rules ): bool {
			return isset( $rules[ '^' . str_replace( '.', '\\.', $path ) . '$' ] );
		};
		$permalinks = '' !== (string) get_option( 'permalink_structure', '' );
		return array(
			'pretty_permalinks' => $permalinks,
			'rule_registered' => $has( self::SITEMAP_PATH ),
			'legacy_rule_registered' => $has( self::LEGACY_SITEMAP_PATH ),
		);
	}

	/**
	 * Findings for the budget, worker and queues, most important first.
	 *
	 * @return array<int,array{status:string,message:string}>
	 */
	public static function findings(): array {
		$s = self::snapshot();
		if ( empty( $s['available'] ) ) {
			return array();
		}
		$findings = array();
		$monthly = $s['monthly'];
		$daily = $s['daily'];

		if ( 'monthly' === $s['blocked_by'] ) {
			$findings[] = array(
				'status' => 'critical',
				'message' => __( 'The monthly Azure Translator character budget is used up. Translation is stopped until the limit resets.', 'koz-translate-diagnostics' ),
			);
		} elseif ( $monthly['limit'] > 0 && $monthly['percent'] >= 80 ) {
			$findings[] = array(
				'status' => $monthly['percent'] >= 95 ? 'critical' : 'recommended',
				'message' => sprintf(
					/* translators: %s: percent of the monthly character limit already used. */
					__( '%s%% of the monthly Azure Translator character limit is used.', 'koz-translate-diagnostics' ),
					number_format_i18n( (float) $monthly['percent'], 1 )
				),
			);
		}
		if ( 'daily' === $s['blocked_by'] || ( $daily['limit'] > 0 && $daily['used'] >= $daily['limit'] ) ) {
			$findings[] = array(
				'status' => 'recommended',
				'message' => __( 'Today\'s Azure Translator budget is used up. Translation continues after midnight UTC.', 'koz-translate-diagnostics' ),
			);
		}

		$pending = (int) $s['residual_queue']['total'];
		if ( $pending > 0 && empty( $s['worker']['enabled'] ) ) {
			$findings[] = array(
				'status' => 'recommended',
				'message' => sprintf(
					/* translators: %s: number of page text items waiting for translation. */
					__( '%s page text items are waiting for translation, but the background worker is off. Enable it or run a queue step in KOZ Static Translate.', 'koz-translate-diagnostics' ),
					number_format_i18n( $pending )
				),
			);
		}
		if ( $pending >= self::RESIDUAL_LIMIT ) {
			$findings[] = array(
				'status' => 'recommended',
				'message' => sprintf(
					/* translators: %s: maximum number of queued page text items. */
					__( 'The page text queue is full (%s items). New untranslated text is not queued until the worker translates part of it.', 'koz-translate-diagnostics' ),
					number_format_i18n( self::RESIDUAL_LIMIT )
				),
			);
		}
		if ( ! empty( $s['worker']['enabled'] ) && empty( $s['worker']['scheduled'] ) ) {
			$findings[] = array(
				'status' => 'critical',
				'message' => __( 'The background worker is enabled, but its scheduled event is missing. Save the KOZ Static Translate settings once to schedule it again.', 'koz-translate-diagnostics' ),
			);
		}
		if ( ! empty( $s['worker']['next_run_overdue'] ) ) {
			$findings[] = array(
				'status' => 'recommended',
				'message' => empty( $s['worker']['cron_disabled'] )
					? __( 'The background worker run is overdue. WP-Cron runs on site visits; add a real cron job for wp-cron.php for regular runs.', 'koz-translate-diagnostics' )
					: __( 'The background worker run is overdue and WP-Cron is disabled (DISABLE_WP_CRON). Check that the server cron job for wp-cron.php works.', 'koz-translate-diagnostics' ),
			);
		}
		if ( ! empty( $s['worker']['retired_hooks'] ) ) {
			$findings[] = array(
				'status' => 'recommended',
				'message' => __( 'A scheduled event from the former UA FREE translator is still present. KOZ Static Translate 0.10.0 or later removes it on the next admin page load.', 'koz-translate-diagnostics' ),
			);
		}
		if ( (int) $s['rejected_segments']['blocked'] > 0 ) {
			$findings[] = array(
				'status' => 'good',
				'message' => sprintf(
					/* translators: %s: number of text segments that are no longer sent to Azure. */
					__( '%s text segments are no longer sent to Azure because their translation was rejected twice. They can be allowed again in KOZ Static Translate.', 'koz-translate-diagnostics' ),
					number_format_i18n( (int) $s['rejected_segments']['blocked'] )
				),
			);
		}
		if ( ! empty( $s['sitemap']['pretty_permalinks'] ) && empty( $s['sitemap']['rule_registered'] ) && version_compare( defined( 'KOZSTX_VERSION' ) ? (string) constant( 'KOZSTX_VERSION' ) : '0', '0.10.0', '>=' ) ) {
			$findings[] = array(
				'status' => 'recommended',
				'message' => __( 'The translation sitemap address is not registered in the rewrite rules. Open Settings → Permalinks and save once.', 'koz-translate-diagnostics' ),
			);
		}
		return $findings;
	}

	public static function source_label( string $kind ): string {
		$labels = array(
			'queue' => __( 'Queue (admin run)', 'koz-translate-diagnostics' ),
			'background' => __( 'Background worker', 'koz-translate-diagnostics' ),
			'dynamic' => __( 'Dynamic content (administrators)', 'koz-translate-diagnostics' ),
			'test' => __( 'Connection test', 'koz-translate-diagnostics' ),
		);
		return $labels[ $kind ] ?? $kind;
	}

	public static function is_supported(): bool {
		return class_exists( self::TRANSLATOR_CLASS, false ) && self::quota_available();
	}
}
