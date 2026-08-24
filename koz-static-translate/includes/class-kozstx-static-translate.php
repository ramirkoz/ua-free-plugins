<?php
namespace ramirkz\kozstx;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMText;
use DOMXPath;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * This component owns isolated, plugin-prefixed translation workflow tables.
 * Direct queries are required for queue processing, joins, schema maintenance
 * and bulk operations. Table identifiers come only from the internal tables()
 * map or from validated plugin-owned names; value data still uses prepared
 * statements or wpdb CRUD helpers where applicable.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared

final class KOZSTX_Static_Translate {

	private const LEGACY_SETTINGS_OPTION = 'uafree_st_auto_settings';
	private const LEGACY_RUNTIME_OPTION = 'uafree_st_auto_runtime';
	private const MIGRATION_OPTION = 'kozstx_legacy_migrated_v092';

	const PAGE_SLUG = 'koz-static-translate';
	const DB_VERSION = '0.7.0';
	const DB_OPTION = 'kozstx_db_version';
	const SETTINGS_OPTION = 'kozstx_settings';
	const RUNTIME_OPTION = 'kozstx_runtime';
	const REWRITE_OPTION = 'kozstx_rewrite_version';
	const ROUTE_SCHEMA_VERSION = '0.9.16-final-language-contract';
	const CRON_HOOK = 'kozstx_cron';
	const CRON_INTERVAL = 'kozstx_minute';
	const AJAX_NONCE = 'kozstx_ajax';
	const SITEMAP_QUERY_VAR = 'kozstx_sitemap';
	const LANG_QUERY_VAR = 'kozstx_lang';
	const PATH_QUERY_VAR = 'kozstx_path';
	const API_VERSION = '2026-06-06';
	const DEFAULT_ENDPOINT = 'https://api.cognitive.microsofttranslator.com';
	const DEFAULT_SOURCE_LANGUAGE = 'uk';
	const FORBIDDEN_LANG_QUERY_VAR = 'kozstx_forbidden_lang';
	const LEGACY_PAGE_PATTERN = '/\s-\s(?:English|Deutsch|Español|Français|Polski|Italiano|Čeština|Română)\s*$/iu';
	const REPORT_TITLE_PATTERN = '/^Звіт\s+на\s+\d{2}\.\d{2}\.\d{4}/u';
	const LOCK_KEY = 'kozstx_worker_lock';
	const DYNAMIC_REST_NAMESPACE = 'kozstx/v1';
	const DYNAMIC_REST_ROUTE = '/dynamic';
	const SEO_SEGMENT_SCHEMA_OPTION = 'kozstx_seo_segment_schema_v0911';
	const TARGET_LANGUAGE_SCHEMA_OPTION = 'kozstx_target_language_schema_v0914';
	private const DEFAULT_TARGET_LANGUAGES = array( 'en', 'zh', 'es', 'ar', 'id', 'pt', 'fr', 'ja', 'de', 'hi' );
	private const RETIRED_V0912_LANGUAGE_SLUGS = array( 'pl', 'it', 'cs', 'ro' );

	private static bool $source_switcher_rendered = false;


	private static function migrate_legacy_options(): void {
		$pairs = array(
			self::SETTINGS_OPTION => self::LEGACY_SETTINGS_OPTION,
			self::RUNTIME_OPTION  => self::LEGACY_RUNTIME_OPTION,
			'kozstx_cleanup_last' => 'uafree_st_cleanup_last',
			'kozstx_reset_last'   => 'uafree_st_reset_current_last',
		);

		foreach ( $pairs as $current => $legacy ) {
			if ( false !== get_option( $current, false ) ) {
				continue;
			}
			$value = get_option( $legacy, false );
			if ( false !== $value ) {
				add_option( $current, $value, '', false );
			}
		}

		wp_clear_scheduled_hook( 'uafree_st_auto_cron' );
		delete_transient( 'uafree_st_internal_cron_spawn' );
	}

	private static function migrate_legacy_tables(): void {
		global $wpdb;

		$tables = array(
			'uafree_st_sources'         => 'kozstx_sources',
			'uafree_st_source_segments' => 'kozstx_source_segments',
			'uafree_st_translations'    => 'kozstx_translations',
			'uafree_st_memory'          => 'kozstx_memory',
			'uafree_st_queue'           => 'kozstx_queue',
			'uafree_st_usage'           => 'kozstx_usage',
			'uafree_st_logs'            => 'kozstx_logs',
		);

		foreach ( $tables as $legacy_suffix => $current_suffix ) {
			$legacy = $wpdb->prefix . $legacy_suffix;
			$current = $wpdb->prefix . $current_suffix;
			$legacy_exists = $legacy === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy ) );
			$current_exists = $current === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $current ) );
			if ( ! $legacy_exists || ! $current_exists ) {
				continue;
			}

			$legacy_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$legacy}`" );
			if ( $legacy_count <= 0 ) {
				continue;
			}

			$wpdb->query( "INSERT IGNORE INTO `{$current}` SELECT * FROM `{$legacy}`" );
		}

		update_option( self::MIGRATION_OPTION, gmdate( 'c' ), false );
	}

	public static function init(): void {
		/* 0.9.23 safety: no automatic upgrade/repair work on normal requests. */
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_dynamic_rest_route' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		/* 0.9.23 safety: automatic translation worker is intentionally disabled. */
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ), 1 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'template_router' ), -1000 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 20, 2 );
		add_filter( 'kozseo_hreflang_links', array( __CLASS__, 'seo_hreflang_links' ), 20, 2 );
		add_filter( 'kozseo_additional_sitemaps', array( __CLASS__, 'seo_additional_sitemaps' ), 20, 1 );
		add_filter( 'kozseo_translation_contract', array( __CLASS__, 'seo_translation_contract' ), 20, 1 );
		add_action( 'wp_body_open', array( __CLASS__, 'source_language_switcher' ), 100 );
		add_action( 'wp_footer', array( __CLASS__, 'source_language_switcher' ), 100 );
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 100, 3 );
		add_action( 'trashed_post', array( __CLASS__, 'on_trashed_post' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'on_trashed_post' ) );
		add_action( 'wp_ajax_kozstx_test', array( __CLASS__, 'ajax_test' ) );
		add_action( 'wp_ajax_kozstx_run', array( __CLASS__, 'ajax_run' ) );
		add_action( 'wp_ajax_kozstx_rebuild', array( __CLASS__, 'ajax_rebuild' ) );
		add_action( 'wp_ajax_kozstx_pause', array( __CLASS__, 'ajax_pause' ) );
		add_action( 'wp_ajax_kozstx_reconcile_page', array( __CLASS__, 'ajax_reconcile_page' ) );
		add_action( 'wp_ajax_kozstx_diagnose_page', array( __CLASS__, 'ajax_diagnose_page' ) );
		add_action( 'wp_ajax_kozstx_reconcile_core_batch', array( __CLASS__, 'ajax_reconcile_core_batch' ) );
		add_action( 'wp_ajax_kozstx_priority_core_discovery', array( __CLASS__, 'ajax_priority_core_discovery' ) );
		add_action( 'wp_ajax_kozstx_prepare_priority_core_source', array( __CLASS__, 'ajax_prepare_priority_core_source' ) );
		add_action( 'kozstx_admin_cleanup_section', array( KOZSTX_Cleanup::class, 'render_section' ) );
	}

	public static function activate(): void {
		/*
		 * Stability activation path.
		 * Do not scan posts, rebuild inventory, migrate translation rows,
		 * run readiness repairs, or start a worker during activation.
		 */
		wp_clear_scheduled_hook( self::CRON_HOOK );
		delete_transient( self::LOCK_KEY );
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		flush_rewrite_rules( false );
	}

	public static function maybe_upgrade(): void {
		self::migrate_legacy_options();
		self::repair_target_languages_v0914();
		$previous_db_version = (string) get_option( self::DB_OPTION, '' );
		$database_upgraded = self::DB_VERSION !== $previous_db_version;

		if ( $database_upgraded || false === get_option( self::MIGRATION_OPTION, false ) ) {
			self::install_schema();
			self::migrate_legacy_tables();
			self::repair_all_language_readiness();
			self::repair_donate_validation_deadlock_v0618();
			self::purge_all_caches();
			delete_option( 'kozstx_protected_progress_fix_v0611' );
			delete_option( 'kozstx_route_readiness_fix_v0612' );
		}

		if ( self::ROUTE_SCHEMA_VERSION !== (string) get_option( self::REWRITE_OPTION, '' ) ) {
			add_action(
				'init',
				static function (): void {
					KOZSTX_Static_Translate::add_rewrite_rules();
					flush_rewrite_rules( false );
					update_option( KOZSTX_Static_Translate::REWRITE_OPTION, KOZSTX_Static_Translate::ROUTE_SCHEMA_VERSION, false );
				},
				99
			);
		}

		self::ensure_seo_segment_schema_v0911();
		self::purge_forbidden_language_data();
		self::reconcile_source_language_change();
		self::ensure_cron();
	}



	private static function repair_target_languages_v0914(): void {
		if ( '0.9.14' === (string) get_option( self::TARGET_LANGUAGE_SCHEMA_OPTION, '' ) ) {
			return;
		}

		$current_raw = get_option( self::SETTINGS_OPTION, array() );
		$current = is_array( $current_raw ) ? $current_raw : array();
		$source_slug = sanitize_key( (string) ( $current['source_language'] ?? self::DEFAULT_SOURCE_LANGUAGE ) );
		$pool = self::source_languages();
		if ( ! isset( $pool[ $source_slug ] ) ) {
			$source_slug = self::DEFAULT_SOURCE_LANGUAGE;
		}

		$raw_targets = array_values( array_unique( array_map( 'sanitize_key', (array) ( $current['target_languages'] ?? array() ) ) ) );
		$had_v0912_retired = (bool) array_intersect( $raw_targets, self::RETIRED_V0912_LANGUAGE_SLUGS );
		$targets = array_values(
			array_filter(
				$raw_targets,
				static function ( string $slug ) use ( $pool, $source_slug ): bool {
					return isset( $pool[ $slug ] ) && $slug !== $source_slug;
				}
			)
		);

		/*
		 * 0.9.12 could restore obsolete PL/IT/CS/RO namespaces from historic
		 * translation data. If that happened, restore the supported target set
		 * without deleting translation memory. Fresh/custom valid subsets remain
		 * untouched.
		 */
		if ( $had_v0912_retired || empty( $targets ) ) {
			$targets = array_values(
				array_filter(
					self::DEFAULT_TARGET_LANGUAGES,
					static fn( string $slug ): bool => $slug !== $source_slug
				)
			);
		}

		$current['source_language'] = $source_slug;
		$current['target_languages'] = array_slice( $targets, 0, 10 );
		update_option( self::SETTINGS_OPTION, wp_parse_args( $current, self::default_settings() ), false );

		/* Stop accidental Azure work for the retired 0.9.12 namespaces only. */
		global $wpdb;
		$tables = self::tables();
		$queue = $tables['queue'];
		if ( $queue === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $queue ) ) ) {
			/* The queue table name is plugin-owned and already resolved from $wpdb->prefix.
			 * WordPress < 6.2 has no %i identifier placeholder, so keep the identifier
			 * trusted and prepare only the four language values.
			 */
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"DELETE FROM {$queue} WHERE language IN (%s,%s,%s,%s)",
				self::RETIRED_V0912_LANGUAGE_SLUGS[0],
				self::RETIRED_V0912_LANGUAGE_SLUGS[1],
				self::RETIRED_V0912_LANGUAGE_SLUGS[2],
				self::RETIRED_V0912_LANGUAGE_SLUGS[3]
			);
			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		self::purge_all_caches();
		update_option( self::TARGET_LANGUAGE_SCHEMA_OPTION, '0.9.14', false );
	}

	private static function ensure_seo_segment_schema_v0911(): void {
		if ( '0.9.11' === (string) get_option( self::SEO_SEGMENT_SCHEMA_OPTION, '' ) ) {
			return;
		}

		global $wpdb;
		$tables = self::tables();
		$table = $tables['sources'];

		if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			/*
			 * Existing installations may have source snapshots created before SEO
			 * metadata became part of the translation readiness contract. Mark the
			 * inventory for a normal bounded rescan. Each route/worker refreshes its
			 * own source progressively; no translation memory is deleted.
			 */
			$wpdb->query( "UPDATE {$table} SET scan_status = 'pending', last_error = ''" );
		}

		self::purge_all_caches();
		update_option( self::SEO_SEGMENT_SCHEMA_OPTION, '0.9.11', false );
	}

	private static function tables(): array {
		global $wpdb;

		return array(
			'sources' => $wpdb->prefix . 'kozstx_sources',
			'segments' => $wpdb->prefix . 'kozstx_source_segments',
			'translations' => $wpdb->prefix . 'kozstx_translations',
			'memory' => $wpdb->prefix . 'kozstx_memory',
			'queue' => $wpdb->prefix . 'kozstx_queue',
			'usage' => $wpdb->prefix . 'kozstx_usage',
			'logs' => $wpdb->prefix . 'kozstx_logs',
		);
	}

	private static function install_schema(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$tables = self::tables();
		$charset = $wpdb->get_charset_collate();

		$sql_sources = "CREATE TABLE {$tables['sources']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			post_type varchar(24) NOT NULL DEFAULT '',
			source_url text NOT NULL,
			source_path varchar(500) NOT NULL DEFAULT '',
			source_title longtext NOT NULL,
			local_hash char(64) NOT NULL DEFAULT '',
			render_hash char(64) NOT NULL DEFAULT '',
			is_report tinyint(1) unsigned NOT NULL DEFAULT 0,
			report_date datetime NULL,
			source_chars bigint(20) unsigned NOT NULL DEFAULT 0,
			segment_count int(10) unsigned NOT NULL DEFAULT 0,
			scan_status varchar(24) NOT NULL DEFAULT 'pending',
			last_error text NOT NULL,
			last_scanned_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_id (post_id),
			KEY source_kind (is_report,report_date),
			KEY scan_status (scan_status)
		) {$charset};";

		$sql_segments = "CREATE TABLE {$tables['segments']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_id bigint(20) unsigned NOT NULL,
			segment_key char(64) NOT NULL,
			segment_order int(10) unsigned NOT NULL DEFAULT 0,
			segment_type varchar(40) NOT NULL DEFAULT 'text',
			source_text longtext NOT NULL,
			source_hash char(64) NOT NULL DEFAULT '',
			is_protected tinyint(1) unsigned NOT NULL DEFAULT 0,
			occurrence_count int(10) unsigned NOT NULL DEFAULT 1,
			context_json longtext NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_segment (source_id,segment_key),
			KEY source_id (source_id),
			KEY protected_source (source_id,is_protected)
		) {$charset};";

		$sql_translations = "CREATE TABLE {$tables['translations']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_id bigint(20) unsigned NOT NULL,
			language varchar(16) NOT NULL,
			segment_key char(64) NOT NULL,
			source_hash char(64) NOT NULL DEFAULT '',
			translated_text longtext NOT NULL,
			status varchar(24) NOT NULL DEFAULT 'ready',
			chars_billed int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_language_segment (source_id,language,segment_key),
			KEY source_language (source_id,language),
			KEY language_status (language,status)
		) {$charset};";

		$sql_memory = "CREATE TABLE {$tables['memory']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			language varchar(16) NOT NULL,
			source_hash char(64) NOT NULL,
			source_text longtext NOT NULL,
			translated_text longtext NOT NULL,
			use_count bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY language_hash (language,source_hash),
			KEY language (language)
		) {$charset};";

		$sql_queue = "CREATE TABLE {$tables['queue']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			language varchar(16) NOT NULL,
			language_order int(10) unsigned NOT NULL DEFAULT 0,
			category varchar(16) NOT NULL DEFAULT 'core',
			priority int(10) unsigned NOT NULL DEFAULT 100,
			report_date datetime NULL,
			source_hash char(64) NOT NULL DEFAULT '',
			status varchar(24) NOT NULL DEFAULT 'queued',
			attempts int(10) unsigned NOT NULL DEFAULT 0,
			processed_segments int(10) unsigned NOT NULL DEFAULT 0,
			total_segments int(10) unsigned NOT NULL DEFAULT 0,
			last_error text NOT NULL,
			next_run_at datetime NULL,
			locked_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			finished_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_language (source_id,language),
			KEY queue_claim (status,next_run_at,priority,report_date,language_order),
			KEY post_id (post_id),
			KEY category_status (category,status)
		) {$charset};";

		$sql_usage = "CREATE TABLE {$tables['usage']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			cycle_key varchar(16) NOT NULL,
			language varchar(16) NOT NULL,
			characters bigint(20) unsigned NOT NULL DEFAULT 0,
			requests bigint(20) unsigned NOT NULL DEFAULT 0,
			last_request_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY cycle_language (cycle_key,language),
			KEY cycle_key (cycle_key)
		) {$charset};";

		$sql_logs = "CREATE TABLE {$tables['logs']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			queue_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_id bigint(20) unsigned NOT NULL DEFAULT 0,
			language varchar(16) NOT NULL DEFAULT '',
			level varchar(16) NOT NULL DEFAULT 'info',
			event varchar(40) NOT NULL DEFAULT '',
			characters bigint(20) unsigned NOT NULL DEFAULT 0,
			request_id varchar(120) NOT NULL DEFAULT '',
			message text NOT NULL,
			context_json longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY event (event),
			KEY queue_id (queue_id),
			KEY language (language)
		) {$charset};";

		dbDelta( $sql_sources );
		dbDelta( $sql_segments );
		dbDelta( $sql_translations );
		dbDelta( $sql_memory );
		dbDelta( $sql_queue );
		dbDelta( $sql_usage );
		dbDelta( $sql_logs );

		update_option( self::DB_OPTION, self::DB_VERSION, false );
		if ( false === get_option( self::SETTINGS_OPTION, false ) ) {
			add_option( self::SETTINGS_OPTION, self::default_settings(), '', false );
		}
		if ( false === get_option( self::RUNTIME_OPTION, false ) ) {
			add_option( self::RUNTIME_OPTION, self::default_runtime(), '', false );
		}
	}

	public static function register_settings(): void {
		register_setting(
			'kozstx_settings',
			self::SETTINGS_OPTION,
			array(
				'type' => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default' => self::default_settings(),
			)
		);
	}

	private static function default_settings(): array {
		return array(
			'auto_enabled' => 1,
			'routes_enabled' => 1,
			'switcher_enabled' => 1,
			'dynamic_content_enabled' => 1,
			'switcher_position' => 'right',
			'source_language' => self::DEFAULT_SOURCE_LANGUAGE,
			'target_languages' => self::DEFAULT_TARGET_LANGUAGES,
			'content_post_types' => array( 'page', 'post' ),
			'content_scope' => 'existing_inventory',
			'azure_key_enc' => '',
			'azure_region' => '',
			'azure_endpoint' => self::DEFAULT_ENDPOINT,
			'monthly_limit' => 2000000,
			'reserve_chars' => 5000,
			'batch_chars' => 12000,
			'max_segments' => 100,
			'max_attempts' => 12,
			'reset_day' => 1,
			'reset_hour_utc' => 0,
			'request_interval' => 60,
			'core_rescan_hours' => 12,
		);
	}

	private static function default_runtime(): array {
		return array(
			'manual_paused' => 0,
			'pause_reason' => '',
			'pause_until' => 0,
			'last_run_at' => '',
			'last_success_at' => '',
			'last_inventory_at' => '',
			'last_core_rescan_at' => '',
			'report_cursor_date' => '9999-12-31 23:59:59',
			'report_cursor_id' => PHP_INT_MAX,
			'report_bootstrap_complete' => 0,
			'last_error' => '',
			'active_source_language' => self::DEFAULT_SOURCE_LANGUAGE,
		);
	}

	private static function settings(): array {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
	}

	private static function runtime(): array {
		$saved = get_option( self::RUNTIME_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_runtime() );
	}

	private static function save_runtime( array $runtime ): void {
		update_option( self::RUNTIME_OPTION, wp_parse_args( $runtime, self::default_runtime() ), false );
	}

	public static function sanitize_settings( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$current = self::settings();
		$source_languages = self::source_languages();
		$source_language = sanitize_text_field( (string) ( $input['source_language'] ?? self::DEFAULT_SOURCE_LANGUAGE ) );
		if ( ! isset( $source_languages[ $source_language ] ) ) {
			$source_language = self::DEFAULT_SOURCE_LANGUAGE;
		}

		$target_languages = array_values(
			array_filter(
				array_unique( array_map( 'sanitize_key', (array) ( $input['target_languages'] ?? $current['target_languages'] ?? array() ) ) ),
				static function ( string $slug ) use ( $source_languages, $source_language ): bool {
					return isset( $source_languages[ $slug ] ) && $slug !== $source_language;
				}
			)
		);
		if ( empty( $target_languages ) ) {
			$target_languages = self::DEFAULT_TARGET_LANGUAGES;
		}
		$target_languages = array_slice( $target_languages, 0, 10 );

		$content_post_types = array_values(
			array_intersect(
				array_unique( array_map( 'sanitize_key', (array) ( $input['content_post_types'] ?? $current['content_post_types'] ?? array( 'page', 'post' ) ) ) ),
				array( 'page', 'post' )
			)
		);
		if ( empty( $content_post_types ) ) {
			$content_post_types = array( 'page', 'post' );
		}

		$content_scope = sanitize_key( (string) ( $input['content_scope'] ?? $current['content_scope'] ?? 'existing_inventory' ) );
		if ( ! in_array( $content_scope, array( 'all_published', 'existing_inventory' ), true ) ) {
			$content_scope = 'existing_inventory';
		}

		$key_enc = (string) ( $current['azure_key_enc'] ?? '' );
		$new_key = trim( (string) ( $input['azure_key_new'] ?? '' ) );
		if ( '' !== $new_key ) {
			$key_enc = self::encrypt_secret( $new_key );
		}
		if ( ! empty( $input['azure_key_clear'] ) ) {
			$key_enc = '';
		}

		$endpoint = esc_url_raw( (string) ( $input['azure_endpoint'] ?? self::DEFAULT_ENDPOINT ) );
		if ( ! self::valid_endpoint( $endpoint ) ) {
			$endpoint = self::DEFAULT_ENDPOINT;
		}

		return array(
			'auto_enabled' => empty( $input['auto_enabled'] ) ? 0 : 1,
			'routes_enabled' => empty( $input['routes_enabled'] ) ? 0 : 1,
			'switcher_enabled' => empty( $input['switcher_enabled'] ) ? 0 : 1,
			'dynamic_content_enabled' => empty( $input['dynamic_content_enabled'] ) ? 0 : 1,
			'switcher_position' => 'left' === ( $input['switcher_position'] ?? $current['switcher_position'] ?? '' ) ? 'left' : 'right',
			'source_language' => $source_language,
			'target_languages' => $target_languages,
			'content_post_types' => $content_post_types,
			'content_scope' => $content_scope,
			'azure_key_enc' => $key_enc,
			'azure_region' => sanitize_text_field( (string) ( $input['azure_region'] ?? '' ) ),
			'azure_endpoint' => $endpoint,
			'monthly_limit' => min( 100000000, max( 100000, absint( $input['monthly_limit'] ?? 2000000 ) ) ),
			'reserve_chars' => min( 100000, max( 0, absint( $input['reserve_chars'] ?? 5000 ) ) ),
			'batch_chars' => min( 30000, max( 1000, absint( $input['batch_chars'] ?? 12000 ) ) ),
			'max_segments' => min( 500, max( 1, absint( $input['max_segments'] ?? 100 ) ) ),
			'max_attempts' => min( 30, max( 3, absint( $input['max_attempts'] ?? 12 ) ) ),
			'reset_day' => min( 28, max( 1, absint( $input['reset_day'] ?? 1 ) ) ),
			'reset_hour_utc' => min( 23, max( 0, absint( $input['reset_hour_utc'] ?? 0 ) ) ),
			'request_interval' => min( 3600, max( 60, absint( $input['request_interval'] ?? 60 ) ) ),
			'core_rescan_hours' => min( 168, max( 1, absint( $input['core_rescan_hours'] ?? 12 ) ) ),
		);
	}

	public static function source_languages(): array {
		return array(
			'uk' => array( 'code' => 'uk', 'name' => 'Українська', 'native' => 'Українська', 'dir' => 'ltr' ),
			'en' => array( 'code' => 'en', 'name' => 'Англійська', 'native' => 'English', 'dir' => 'ltr' ),
			'zh' => array( 'code' => 'zh-Hans', 'name' => 'Китайська', 'native' => '简体中文', 'dir' => 'ltr' ),
			'es' => array( 'code' => 'es', 'name' => 'Іспанська', 'native' => 'Español', 'dir' => 'ltr' ),
			'ar' => array( 'code' => 'ar', 'name' => 'Арабська', 'native' => 'العربية', 'dir' => 'rtl' ),
			'id' => array( 'code' => 'id', 'name' => 'Індонезійська', 'native' => 'Bahasa Indonesia', 'dir' => 'ltr' ),
			'pt' => array( 'code' => 'pt', 'name' => 'Португальська', 'native' => 'Português', 'dir' => 'ltr' ),
			'fr' => array( 'code' => 'fr', 'name' => 'Французька', 'native' => 'Français', 'dir' => 'ltr' ),
			'ja' => array( 'code' => 'ja', 'name' => 'Японська', 'native' => '日本語', 'dir' => 'ltr' ),
			'de' => array( 'code' => 'de', 'name' => 'Німецька', 'native' => 'Deutsch', 'dir' => 'ltr' ),
			'hi' => array( 'code' => 'hi', 'name' => 'Гінді', 'native' => 'हिन्दी', 'dir' => 'ltr' ),
		);
	}

	private static function source_language_slug(): string {
		$settings = self::settings();
		$slug = sanitize_key( (string) ( $settings['source_language'] ?? self::DEFAULT_SOURCE_LANGUAGE ) );
		return isset( self::source_languages()[ $slug ] ) ? $slug : self::DEFAULT_SOURCE_LANGUAGE;
	}

	private static function source_language_info(): array {
		return self::source_languages()[ self::source_language_slug() ];
	}

	private static function source_language_code(): string {
		return (string) self::source_language_info()['code'];
	}

	public static function languages(): array {
		$pool = self::source_languages();
		$settings = self::settings();
		$selected = array_values(
			array_filter(
				array_unique( array_map( 'sanitize_key', (array) ( $settings['target_languages'] ?? array() ) ) ),
				static function ( string $slug ) use ( $pool ): bool {
					return isset( $pool[ $slug ] );
				}
			)
		);
		if ( empty( $selected ) ) {
			$selected = self::DEFAULT_TARGET_LANGUAGES;
		}
		$source_slug = self::source_language_slug();
		$result = array();
		$order = 0;
		foreach ( $selected as $slug ) {
			if ( $slug === $source_slug || ! isset( $pool[ $slug ] ) ) {
				continue;
			}
			$order++;
			$language = $pool[ $slug ];
			$language['og_locale'] = (string) ( $language['og_locale'] ?? self::default_og_locale( $slug ) );
			$language['order'] = $order;
			$result[ $slug ] = $language;
			if ( 10 === $order ) {
				break;
			}
		}
		return $result;
	}

	private static function default_og_locale( string $slug ): string {
		$map = array(
			'uk' => 'uk_UA', 'en' => 'en_US', 'zh' => 'zh_CN', 'es' => 'es_ES',
			'ar' => 'ar_AR', 'id' => 'id_ID', 'pt' => 'pt_PT', 'fr' => 'fr_FR',
			'ja' => 'ja_JP', 'de' => 'de_DE', 'hi' => 'hi_IN',
		);
		return (string) ( $map[ $slug ] ?? $slug );
	}

	private static function content_post_types(): array {
		$settings = self::settings();
		$types = array_values(
			array_intersect(
				array_unique( array_map( 'sanitize_key', (array) ( $settings['content_post_types'] ?? array( 'page', 'post' ) ) ) ),
				array( 'page', 'post' )
			)
		);
		return empty( $types ) ? array( 'page', 'post' ) : $types;
	}

	private static function content_scope(): string {
		$scope = sanitize_key( (string) ( self::settings()['content_scope'] ?? 'existing_inventory' ) );
		return in_array( $scope, array( 'all_published', 'existing_inventory' ), true ) ? $scope : 'existing_inventory';
	}

	private static function source_exists_for_post( int $post_id ): bool {
		global $wpdb;
		$tables = self::tables();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['sources']} WHERE post_id = %d", $post_id ) ) > 0;
	}

	public static function maybe_spawn_internal_worker(): void {
		/* 0.9.23 safety: no internal cron spawning. */
		return;
	}

	private static function purge_forbidden_language_data(): void {
		global $wpdb;
		$tables = self::tables();
		foreach ( array( 'queue', 'translations', 'memory', 'usage' ) as $key ) {
			$table = $tables[ $key ];
			if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				$wpdb->query( "DELETE FROM {$table} WHERE language = 'ru'" );
			}
		}
	}

	private static function reconcile_source_language_change(): void {
		global $wpdb;
		$runtime = self::runtime();
		$current = self::source_language_slug();
		$active = (string) ( $runtime['active_source_language'] ?? self::DEFAULT_SOURCE_LANGUAGE );
		if ( $active === $current ) {
			return;
		}
		$tables = self::tables();
		foreach ( array( 'translations', 'memory', 'queue' ) as $key ) {
			$table = $tables[ $key ];
			if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				$wpdb->query( "TRUNCATE TABLE {$table}" );
			}
		}
		if ( $tables['sources'] === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tables['sources'] ) ) ) {
			$wpdb->query( "UPDATE {$tables['sources']} SET scan_status = 'pending', last_error = ''" );
		}
		$runtime['active_source_language'] = $current;
		$runtime['report_cursor_date'] = '9999-12-31 23:59:59';
		$runtime['report_cursor_id'] = PHP_INT_MAX;
		$runtime['report_bootstrap_complete'] = 0;
		$runtime['last_core_rescan_at'] = '';
		$runtime['last_recent_reports_at'] = '';
		self::save_runtime( $runtime );
		flush_rewrite_rules( false );
	}

	public static function cron_schedules( array $schedules ): array {
		$schedules[ self::CRON_INTERVAL ] = array(
			'interval' => 60,
			'display' => 'KOZ Static Translate worker every minute',
		);
		return $schedules;
	}

	private static function ensure_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, self::CRON_INTERVAL, self::CRON_HOOK );
		}
	}

	public static function cron_tick(): void {
		/* 0.9.23 safety: background processing is disabled in this stability release. */
		return;
	}

	private static function cycle_info(): array {
		$settings = self::settings();
		$day = (int) $settings['reset_day'];
		$hour = (int) $settings['reset_hour_utc'];
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$current_month_start = new DateTimeImmutable(
			sprintf( '%s-%02d %02d:00:00', $now->format( 'Y-m' ), $day, $hour ),
			new DateTimeZone( 'UTC' )
		);

		if ( $now < $current_month_start ) {
			$start = $current_month_start->modify( '-1 month' );
		} else {
			$start = $current_month_start;
		}

		$next = $start->modify( '+1 month' );
		return array(
			'key' => $start->format( 'Y-m-d-H' ),
			'start_ts' => $start->getTimestamp(),
			'next_ts' => $next->getTimestamp(),
			'start_iso' => $start->format( DATE_ATOM ),
			'next_iso' => $next->format( DATE_ATOM ),
		);
	}

	private static function current_usage(): array {
		global $wpdb;
		$tables = self::tables();
		$cycle = self::cycle_info();
		$settings = self::settings();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT language, characters, requests
				FROM {$tables['usage']}
				WHERE cycle_key = %s",
				$cycle['key']
			),
			ARRAY_A
		);

		$by_language = array();
		$total_chars = 0;
		$total_requests = 0;
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$by_language[ (string) $row['language'] ] = array(
				'characters' => (int) $row['characters'],
				'requests' => (int) $row['requests'],
			);
			$total_chars += (int) $row['characters'];
			$total_requests += (int) $row['requests'];
		}

		$limit = (int) $settings['monthly_limit'];
		$remaining = max( 0, $limit - $total_chars );
		return array(
			'cycle' => $cycle,
			'limit' => $limit,
			'characters' => $total_chars,
			'requests' => $total_requests,
			'remaining' => $remaining,
			'percent' => $limit > 0 ? min( 100, ( $total_chars / $limit ) * 100 ) : 0,
			'by_language' => $by_language,
		);
	}

	private static function increment_usage( string $language, int $characters ): void {
		global $wpdb;
		$tables = self::tables();
		$cycle = self::cycle_info();
		$now = current_time( 'mysql', true );
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
	}

	private static function maybe_resume_for_new_cycle(): void {
		$runtime = self::runtime();
		$cycle = self::cycle_info();
		if (
			'monthly_limit' === (string) $runtime['pause_reason']
			&& time() >= (int) $runtime['pause_until']
		) {
			$runtime['pause_reason'] = '';
			$runtime['pause_until'] = 0;
			$runtime['last_error'] = '';
			self::save_runtime( $runtime );
			self::requeue_paused_jobs();
			self::log_event( 0, 0, '', 'info', 'monthly_cycle_resumed', 0, 'Новий місячний цикл. Чергу відновлено.' );
		}
	}

	private static function pause_until( string $reason, int $timestamp, string $message ): void {
		$runtime = self::runtime();
		$runtime['pause_reason'] = $reason;
		$runtime['pause_until'] = max( time() + 60, $timestamp );
		$runtime['last_error'] = self::short_text( $message, 1000 );
		self::save_runtime( $runtime );
	}

	private static function requeue_paused_jobs(): void {
		global $wpdb;
		$tables = self::tables();
		$now = current_time( 'mysql', true );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tables['queue']}
				SET status = 'queued', next_run_at = %s, updated_at = %s
				WHERE status = 'paused'",
				$now,
				$now
			)
		);
	}

	private static function credentials(): array {
		$settings = self::settings();
		$key = self::decrypt_secret( (string) ( $settings['azure_key_enc'] ?? '' ) );
		if ( '' === $key && defined( 'KOZSTX_AZURE_TRANSLATOR_KEY' ) ) {
			$key = trim( (string) KOZSTX_AZURE_TRANSLATOR_KEY );
		}
		if ( '' === $key && defined( 'UAFREE_AZURE_TRANSLATOR_KEY' ) ) {
			$key = trim( (string) UAFREE_AZURE_TRANSLATOR_KEY );
		}
		$region = trim( (string) ( $settings['azure_region'] ?? '' ) );
		if ( '' === $region && defined( 'KOZSTX_AZURE_TRANSLATOR_REGION' ) ) {
			$region = trim( (string) KOZSTX_AZURE_TRANSLATOR_REGION );
		}
		if ( '' === $region && defined( 'UAFREE_AZURE_TRANSLATOR_REGION' ) ) {
			$region = trim( (string) UAFREE_AZURE_TRANSLATOR_REGION );
		}
		$endpoint = trim( (string) ( $settings['azure_endpoint'] ?? self::DEFAULT_ENDPOINT ) );
		if ( ! self::valid_endpoint( $endpoint ) ) {
			$endpoint = self::DEFAULT_ENDPOINT;
		}
		return array(
			'key' => $key,
			'region' => $region,
			'endpoint' => untrailingslashit( $endpoint ),
			'configured' => '' !== $key,
		);
	}

	private static function encrypt_secret( string $plain ): string {
		if ( '' === $plain || ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}
		$key = hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
		$iv = random_bytes( 12 );
		$tag = '';
		$cipher = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $cipher ) {
			return '';
		}
		return base64_encode( $iv . $tag . $cipher );
	}

	private static function decrypt_secret( string $encoded ): string {
		if ( '' === $encoded || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$raw = base64_decode( $encoded, true );
		if ( false === $raw || strlen( $raw ) < 29 ) {
			return '';
		}
		$iv = substr( $raw, 0, 12 );
		$tag = substr( $raw, 12, 16 );
		$cipher = substr( $raw, 28 );
		$key = hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
		$plain = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return is_string( $plain ) ? $plain : '';
	}

	private static function migration_freeze_active(): bool {
		if ( false !== get_transient( self::LOCK_KEY ) ) {
			return true;
		}

		$runtime = self::runtime();
		$reason = (string) ( $runtime['pause_reason'] ?? '' );

		return ! empty( $runtime['manual_paused'] )
			&& 0 === strpos( $reason, 'UA FREE Suite Migration Bridge:' );
	}

	private static function valid_endpoint( string $endpoint ): bool {
		$parts = wp_parse_url( $endpoint );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) ) {
			return false;
		}
		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		return 'api.cognitive.microsofttranslator.com' === $host
			|| str_ends_with( $host, '.cognitiveservices.azure.com' );
	}

	private static function bootstrap_core_pages(): void {
		global $wpdb;
		$ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'page' AND post_status = 'publish'
			ORDER BY menu_order ASC, ID ASC"
		);
		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			self::upsert_source_post( (int) $id, false );
		}
		$runtime = self::runtime();
		$runtime['last_inventory_at'] = current_time( 'mysql', true );
		self::save_runtime( $runtime );
	}

	private static function bootstrap_reports_step( int $limit ): void {
		global $wpdb;
		$runtime = self::runtime();
		if ( ! empty( $runtime['report_bootstrap_complete'] ) ) {
			return;
		}

		$cursor_date = (string) $runtime['report_cursor_date'];
		$cursor_id = (int) $runtime['report_cursor_id'];
		$limit = min( 100, max( 1, $limit ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_date_gmt
				FROM {$wpdb->posts}
				WHERE post_type = 'post'
					AND post_status = 'publish'
					AND post_title LIKE %s
					AND (
						post_date_gmt < %s
						OR (post_date_gmt = %s AND ID < %d)
					)
				ORDER BY post_date_gmt DESC, ID DESC
				LIMIT %d",
				'Звіт на %',
				$cursor_date,
				$cursor_date,
				$cursor_id,
				$limit
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			$runtime['report_bootstrap_complete'] = 1;
			self::save_runtime( $runtime );
			return;
		}

		foreach ( $rows as $row ) {
			self::upsert_source_post( (int) $row['ID'], false );
			$runtime['report_cursor_date'] = (string) $row['post_date_gmt'];
			$runtime['report_cursor_id'] = (int) $row['ID'];
		}

		if ( count( $rows ) < $limit ) {
			$runtime['report_bootstrap_complete'] = 1;
		}
		self::save_runtime( $runtime );
	}

	private static function reconcile_recent_reports(): void {
		$runtime = self::runtime();
		$last = ! empty( $runtime['last_recent_reports_at'] ) ? strtotime( (string) $runtime['last_recent_reports_at'] . ' UTC' ) : 0;
		if ( $last && time() - $last < 6 * HOUR_IN_SECONDS ) {
			return;
		}

		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'post' AND post_status = 'publish' AND post_title LIKE %s
				ORDER BY post_date_gmt DESC, ID DESC LIMIT 50",
				'Звіт на %'
			)
		);
		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			self::upsert_source_post( (int) $id, false );
		}
		$runtime['last_recent_reports_at'] = current_time( 'mysql', true );
		self::save_runtime( $runtime );
	}

	private static function maybe_rescan_core_pages(): void {
		$settings = self::settings();
		$runtime = self::runtime();
		$last = ! empty( $runtime['last_core_rescan_at'] ) ? strtotime( (string) $runtime['last_core_rescan_at'] . ' UTC' ) : 0;
		if ( $last && time() - $last < (int) $settings['core_rescan_hours'] * HOUR_IN_SECONDS ) {
			return;
		}

		global $wpdb;
		$tables = self::tables();
		$now = current_time( 'mysql', true );
		$wpdb->query(
			"UPDATE {$tables['sources']}
			SET scan_status = 'pending'
			WHERE is_report = 0"
		);
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tables['queue']}
				SET status = 'queued', priority = 1, attempts = 0, next_run_at = %s, finished_at = NULL, updated_at = %s
				WHERE category = 'core'",
				$now,
				$now
			)
		);
		$runtime['last_core_rescan_at'] = $now;
		self::save_runtime( $runtime );
	}

	public static function on_save_post( int $post_id, WP_Post $post, bool $update ): void {
		if (
			wp_is_post_revision( $post_id )
			|| wp_is_post_autosave( $post_id )
			|| 'publish' !== $post->post_status
			|| ! in_array( $post->post_type, self::content_post_types(), true )
		) {
			return;
		}
		if ( 'existing_inventory' === self::content_scope() && ! self::source_exists_for_post( $post_id ) ) {
			return;
		}

		$source_id = self::upsert_source_post( $post_id, true );
		if ( $source_id <= 0 ) {
			return;
		}

		if ( 'post' === $post->post_type && self::is_daily_report( $post ) ) {
			self::mark_dynamic_core_dirty();
		}

		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron( time() );
		}
	}

	public static function on_trashed_post( int $post_id ): void {
		global $wpdb;
		$tables = self::tables();
		$source_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$tables['sources']} WHERE post_id = %d",
				$post_id
			)
		);
		if ( $source_id <= 0 ) {
			return;
		}
		delete_transient( self::source_render_cache_key( $source_id ) );
		$wpdb->delete( $tables['translations'], array( 'source_id' => $source_id ) );
		$wpdb->delete( $tables['segments'], array( 'source_id' => $source_id ) );
		$wpdb->delete( $tables['queue'], array( 'source_id' => $source_id ) );
		$wpdb->delete( $tables['sources'], array( 'id' => $source_id ) );
		self::mark_dynamic_core_dirty();
	}

	private static function upsert_source_post( int $post_id, bool $force_priority ): int {
		global $wpdb;
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! in_array( $post->post_type, self::content_post_types(), true ) ) {
			return 0;
		}
		if ( 'existing_inventory' === self::content_scope() && ! self::source_exists_for_post( $post_id ) ) {
			return 0;
		}

		$is_report = self::is_daily_report( $post );
		if ( 'page' !== $post->post_type && ! $is_report ) {
			return 0;
		}
		if ( 'page' === $post->post_type && preg_match( self::LEGACY_PAGE_PATTERN, html_entity_decode( $post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) {
			return 0;
		}

		$url = get_permalink( $post );
		if ( ! is_string( $url ) || '' === $url ) {
			return 0;
		}

		$local_hash = self::local_source_hash( $post );
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::tables()['sources'] . " WHERE post_id = %d",
				$post_id
			),
			ARRAY_A
		);
		$now = current_time( 'mysql', true );
		$estimated_chars = self::estimate_post_chars( $post );
		$data = array(
			'post_id' => $post_id,
			'post_type' => (string) $post->post_type,
			'source_url' => $url,
			'source_path' => self::url_path( $url ),
			'source_title' => (string) $post->post_title,
			'local_hash' => $local_hash,
			'is_report' => $is_report ? 1 : 0,
			'report_date' => $is_report ? self::post_date_gmt( $post ) : null,
			'updated_at' => $now,
		);

		$changed = ! is_array( $existing ) || ! hash_equals( (string) $existing['local_hash'], $local_hash );
		if ( $changed ) {
			$data['scan_status'] = 'pending';
			$data['last_error'] = '';
			if ( ! is_array( $existing ) || empty( $existing['source_chars'] ) ) {
				$data['source_chars'] = $estimated_chars;
			}
		}

		$tables = self::tables();
		if ( is_array( $existing ) ) {
			$wpdb->update( $tables['sources'], $data, array( 'id' => (int) $existing['id'] ) );
			$source_id = (int) $existing['id'];
		} else {
			$data['render_hash'] = '';
			$data['source_chars'] = $estimated_chars;
			$data['segment_count'] = 0;
			$data['scan_status'] = 'pending';
			$data['last_error'] = '';
			$data['created_at'] = $now;
			$wpdb->insert( $tables['sources'], $data );
			$source_id = (int) $wpdb->insert_id;
		}

		if ( $source_id <= 0 ) {
			return 0;
		}

		if ( $changed ) {
			delete_transient( self::source_render_cache_key( $source_id ) );
		}

		$priority = $is_report ? ( $force_priority ? 50 : 100 ) : ( $force_priority ? 1 : 10 );
		self::ensure_jobs_for_source( $source_id, $post_id, $local_hash, $is_report, $data['report_date'], $priority, $changed || $force_priority );
		return $source_id;
	}

	private static function ensure_jobs_for_source(
		int $source_id,
		int $post_id,
		string $source_hash,
		bool $is_report,
		?string $report_date,
		int $priority,
		bool $requeue
	): void {
		global $wpdb;
		$tables = self::tables();
		$now = current_time( 'mysql', true );
		foreach ( self::languages() as $slug => $language ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, status, source_hash, priority
					FROM {$tables['queue']}
					WHERE source_id = %d AND language = %s",
					$source_id,
					$slug
				),
				ARRAY_A
			);

			if ( is_array( $existing ) ) {
				$hash_changed = ! hash_equals( (string) $existing['source_hash'], $source_hash );
				$update = array(
					'post_id' => $post_id,
					'language_order' => (int) $language['order'],
					'category' => $is_report ? 'report' : 'core',
					'priority' => min( (int) $existing['priority'], $priority ),
					'report_date' => $report_date,
					'source_hash' => $source_hash,
					'updated_at' => $now,
				);
				if ( $requeue || $hash_changed || in_array( (string) $existing['status'], array( 'failed', 'paused' ), true ) ) {
					$update['status'] = 'queued';
					$update['attempts'] = 0;
					$update['last_error'] = '';
					$update['next_run_at'] = $now;
					$update['finished_at'] = null;
				}
				$wpdb->update( $tables['queue'], $update, array( 'id' => (int) $existing['id'] ) );
			} else {
				$wpdb->insert(
					$tables['queue'],
					array(
						'source_id' => $source_id,
						'post_id' => $post_id,
						'language' => $slug,
						'language_order' => (int) $language['order'],
						'category' => $is_report ? 'report' : 'core',
						'priority' => $priority,
						'report_date' => $report_date,
						'source_hash' => $source_hash,
						'status' => 'queued',
						'attempts' => 0,
						'processed_segments' => 0,
						'total_segments' => 0,
						'last_error' => '',
						'next_run_at' => $now,
						'created_at' => $now,
						'updated_at' => $now,
					)
				);
			}
		}
	}

	private static function mark_dynamic_core_dirty(): void {
		$ids = array_filter(
			array(
				(int) get_option( 'page_on_front' ),
				self::page_id_by_path( 'zvit' ),
			)
		);
		foreach ( array_unique( $ids ) as $id ) {
			self::upsert_source_post( (int) $id, true );
		}
	}

	private static function page_id_by_path( string $path ): int {
		$page = get_page_by_path( $path, OBJECT, 'page' );
		return $page instanceof WP_Post ? (int) $page->ID : 0;
	}

	private static function is_daily_report( WP_Post $post ): bool {
		return 'post' === $post->post_type
			&& (bool) preg_match( self::REPORT_TITLE_PATTERN, html_entity_decode( $post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	private static function post_date_gmt( WP_Post $post ): string {
		return '0000-00-00 00:00:00' !== $post->post_date_gmt && '' !== $post->post_date_gmt
			? $post->post_date_gmt
			: get_gmt_from_date( $post->post_date );
	}

	private static function estimate_post_chars( WP_Post $post ): int {
		$text = html_entity_decode(
			wp_strip_all_tags( strip_shortcodes( $post->post_title . ' ' . $post->post_excerpt . ' ' . $post->post_content ) ),
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);
		return max( 1, self::string_length( preg_replace( '/\s+/u', ' ', $text ) ) );
	}

	private static function local_source_hash( WP_Post $post ): string {
		global $wpdb;
		$meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value
				FROM {$wpdb->postmeta}
				WHERE post_id = %d
					AND (meta_key = '_thumbnail_id' OR meta_key = '_wp_page_template' OR meta_key LIKE %s)
				ORDER BY meta_key, meta_id",
				(int) $post->ID,
				'%pagelayer%'
			),
			ARRAY_A
		);
		$fingerprint = array();
		foreach ( is_array( $meta ) ? $meta : array() as $row ) {
			$fingerprint[] = array(
				'key' => (string) $row['meta_key'],
				'hash' => hash( 'sha256', (string) $row['meta_value'] ),
				'bytes' => strlen( (string) $row['meta_value'] ),
			);
		}
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'id' => (int) $post->ID,
					'title' => (string) $post->post_title,
					'excerpt' => (string) $post->post_excerpt,
					'content' => (string) $post->post_content,
					'modified_gmt' => (string) $post->post_modified_gmt,
					'meta' => $fingerprint,
				),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			)
		);
	}

	private static function source_render_cache_key( int $source_id ): string {
		$fingerprint = hash( 'sha256', max( 0, $source_id ) . '|' . KOZSTX_VERSION );
		return 'kozstx_render_source_' . substr( $fingerprint, 0, 32 );
	}

	private static function translated_render_cache_key( array $source, string $slug ): string {
		$source_id = (int) ( $source['id'] ?? 0 );
		$render_hash = (string) ( $source['render_hash'] ?? '' );
		$fingerprint = hash( 'sha256', $source_id . '|' . sanitize_key( $slug ) . '|' . $render_hash . '|' . KOZSTX_VERSION );
		return 'kozstx_render_' . substr( $fingerprint, 0, 32 );
	}

	private static function cached_render_html( string $key ): string {
		$cached = get_transient( $key );
		if ( ! is_string( $cached ) || '' === trim( $cached ) ) {
			return '';
		}
		return $cached;
	}

	private static function store_render_html( string $key, string $html, int $ttl ): void {
		$bytes = strlen( $html );
		if ( '' === trim( $html ) || $bytes < 256 || $bytes > ( 3 * MB_IN_BYTES ) ) {
			return;
		}
		set_transient( $key, $html, max( 5 * MINUTE_IN_SECONDS, $ttl ) );
	}

	private static function scan_source( array $source ): bool {
		global $wpdb;
		$tables = self::tables();
		$source_id = (int) $source['id'];
		$url = add_query_arg( 'kozstx_source_scan', wp_rand( 100000, 999999 ), (string) $source['source_url'] );
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 25,
				'redirection' => 3,
				'limit_response_size' => 10 * MB_IN_BYTES,
				'headers' => array(
					'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
					'Accept-Language' => self::source_language_code(),
					'Cache-Control' => 'no-cache, no-store, max-age=0',
					'Pragma' => 'no-cache',
				),
				'user-agent' => 'KOZSTX-Auto-Translate-Scanner/' . KOZSTX_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::source_scan_error( $source_id, $response->get_error_message() );
			return false;
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			self::source_scan_error( $source_id, 'HTTP ' . $status );
			return false;
		}
		$html = (string) wp_remote_retrieve_body( $response );
		if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
			self::source_scan_error( $source_id, 'Порожній HTML або DOMDocument недоступний.' );
			return false;
		}

		self::store_render_html( self::source_render_cache_key( $source_id ), $html, 6 * HOUR_IN_SECONDS );

		$segments = self::extract_segments_from_html( $html, $source_id );
		if ( empty( $segments ) ) {
			self::source_scan_error( $source_id, 'Текстові сегменти не знайдено.' );
			return false;
		}

		$now = current_time( 'mysql', true );
		$keys = array();
		$total_chars = 0;
		$order = 0;
		foreach ( $segments as $segment ) {
			$order++;
			$key = (string) $segment['segment_key'];
			$keys[] = $key;
			$protected = ! empty( $segment['is_protected'] );
			if ( ! $protected ) {
				$total_chars += self::string_length( (string) $segment['source_text'] );
			}
			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$tables['segments']} WHERE source_id = %d AND segment_key = %s",
					$source_id,
					$key
				)
			);
			$data = array(
				'source_id' => $source_id,
				'segment_key' => $key,
				'segment_order' => $order,
				'segment_type' => (string) $segment['segment_type'],
				'source_text' => (string) $segment['source_text'],
				'source_hash' => (string) $segment['source_hash'],
				'is_protected' => $protected ? 1 : 0,
				'occurrence_count' => (int) $segment['occurrence_count'],
				'context_json' => wp_json_encode( $segment['context'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'updated_at' => $now,
			);
			if ( $existing_id > 0 ) {
				$wpdb->update( $tables['segments'], $data, array( 'id' => $existing_id ) );
			} else {
				$data['created_at'] = $now;
				$wpdb->insert( $tables['segments'], $data );
			}

			if ( $protected ) {
				foreach ( self::languages() as $slug => $language ) {
					self::store_translation(
						$source_id,
						$slug,
						$key,
						(string) $segment['source_hash'],
						(string) $segment['source_text'],
						'ready',
						0
					);
				}
			} else {
				foreach ( self::languages() as $slug => $language ) {
					$memory = self::memory_translation( $slug, (string) $segment['source_hash'] );
					if ( '' !== $memory ) {
						self::store_translation(
							$source_id,
							$slug,
							$key,
							(string) $segment['source_hash'],
							$memory,
							'ready',
							0
						);
					}
				}
			}
		}

		self::delete_obsolete_segments( $source_id, $keys );
		$render_hash = hash(
			'sha256',
			implode( '|', array_map( static fn( array $segment ): string => (string) $segment['source_hash'], $segments ) )
		);
		$wpdb->update(
			$tables['sources'],
			array(
				'render_hash' => $render_hash,
				'source_chars' => $total_chars,
				'segment_count' => count( $segments ),
				'scan_status' => 'ready',
				'last_error' => '',
				'last_scanned_at' => $now,
				'updated_at' => $now,
			),
			array( 'id' => $source_id )
		);
		self::refresh_all_jobs_for_source( $source_id );
		return true;
	}

	private static function source_scan_error( int $source_id, string $message ): void {
		global $wpdb;
		$tables = self::tables();
		$wpdb->update(
			$tables['sources'],
			array(
				'scan_status' => 'error',
				'last_error' => self::short_text( $message, 1000 ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $source_id )
		);
	}

	private static function extract_segments_from_html( string $html, int $source_id ): array {
		$previous = libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$loaded = $dom->loadHTML(
			'<?xml encoding="utf-8" ?>' . $html,
			LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			return array();
		}
		$xpath = new DOMXPath( $dom );
		$bucket = array();
		$order = 0;

		$title = $dom->getElementsByTagName( 'title' )->item( 0 );
		if ( $title ) {
			$text = self::normalize_text( (string) $title->textContent );
			if ( self::is_translatable_text( $text ) ) {
				$order++;
				self::add_segment( $bucket, $text, 'document_title', array( 'source_id' => $source_id, 'first_order' => $order ) );
			}
		}

		$meta_map = array(
			'description' => 'meta_description',
			'og:title' => 'meta_og_title',
			'og:description' => 'meta_og_description',
			'og:site_name' => 'meta_og_site_name',
			'twitter:title' => 'meta_twitter_title',
			'twitter:description' => 'meta_twitter_description',
		);
		foreach ( $meta_map as $name => $type ) {
			$query = 0 === strpos( $name, 'og:' )
				? '//meta[@property="' . $name . '"]'
				: '//meta[@name="' . $name . '"]';
			$node = $xpath->query( $query )->item( 0 );
			if ( $node instanceof DOMElement ) {
				$text = self::normalize_text( $node->getAttribute( 'content' ) );
				if ( self::is_translatable_text( $text ) ) {
					$order++;
					self::add_segment( $bucket, $text, $type, array( 'source_id' => $source_id, 'first_order' => $order, 'meta' => $name ) );
				}
			}
		}

		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return array_values( $bucket );
		}

		$text_nodes = $xpath->query( './/text()[normalize-space(.) != ""]', $body );
		foreach ( $text_nodes as $node ) {
			$parent = $node->parentNode;
			if ( ! $parent instanceof DOMElement || self::excluded_element( $parent ) ) {
				continue;
			}
			$text = self::normalize_text( (string) $node->nodeValue );
			if ( ! self::is_translatable_text( $text ) ) {
				continue;
			}
			$order++;
			$type = self::segment_type_for_element( $parent );
			$context = array(
				'tag' => strtolower( $parent->tagName ),
				'class' => self::safe_context( $parent->getAttribute( 'class' ) ),
				'id' => self::safe_context( $parent->getAttribute( 'id' ) ),
				'source_id' => $source_id,
				'first_order' => $order,
			);
			self::add_segment( $bucket, $text, $type, $context );
		}

		$attributes = array(
			'alt' => 'attribute_alt',
			'title' => 'attribute_title',
			'placeholder' => 'attribute_placeholder',
			'aria-label' => 'attribute_aria_label',
		);
		foreach ( $attributes as $attribute => $type ) {
			$nodes = $xpath->query( './/*[@' . $attribute . ' and normalize-space(@' . $attribute . ') != ""]', $body );
			foreach ( $nodes as $element ) {
				if ( ! $element instanceof DOMElement || self::excluded_element( $element ) ) {
					continue;
				}
				$text = self::normalize_text( $element->getAttribute( $attribute ) );
				if ( ! self::is_translatable_text( $text ) ) {
					continue;
				}
				$order++;
				self::add_segment(
					$bucket,
					$text,
					$type,
					array(
						'tag' => strtolower( $element->tagName ),
						'attribute' => $attribute,
						'class' => self::safe_context( $element->getAttribute( 'class' ) ),
						'id' => self::safe_context( $element->getAttribute( 'id' ) ),
						'source_id' => $source_id,
						'first_order' => $order,
					)
				);
			}
		}

		$segments = array_values( $bucket );
		usort(
			$segments,
			static fn( array $a, array $b ): int => (int) $a['context']['first_order'] <=> (int) $b['context']['first_order']
		);
		return $segments;
	}

	private static function add_segment( array &$bucket, string $text, string $type, array $context ): void {
		$key = hash( 'sha256', $type . "\n" . self::lower( $text ) );
		if ( isset( $bucket[ $key ] ) ) {
			$bucket[ $key ]['occurrence_count']++;
			return;
		}
		$bucket[ $key ] = array(
			'segment_key' => $key,
			'segment_type' => $type,
			'source_text' => $text,
			'source_hash' => hash( 'sha256', $text ),
			'is_protected' => self::is_protected_segment( $text, $context, $type ),
			'occurrence_count' => 1,
			'context' => $context,
		);
	}

	private static function excluded_element( DOMElement $element ): bool {
		$excluded_tags = array( 'script', 'style', 'noscript', 'template', 'svg', 'canvas', 'iframe', 'code', 'pre' );
		$current = $element;
		while ( $current instanceof DOMElement ) {
			if ( in_array( strtolower( $current->tagName ), $excluded_tags, true ) ) {
				return true;
			}
			$class = strtolower( (string) $current->getAttribute( 'class' ) );
			if (
				false !== strpos( $class, 'screen-reader-response' )
				|| false !== strpos( $class, 'grecaptcha-badge' )
				|| false !== strpos( $class, 'kozstx-language-switcher' )
			) {
				return true;
			}
			$current = $current->parentNode;
		}
		return false;
	}

	private static function is_translatable_text( string $text ): bool {
		$length = self::string_length( $text );
		if ( $length < 2 || $length > 30000 ) {
			return false;
		}
		if ( preg_match( '/^(?:https?:\/\/|www\.|mailto:|tel:)/iu', $text ) ) {
			return false;
		}
		if ( preg_match( '/^[\d\s.,:+\-–—\/()%$€₴₿]+$/u', $text ) ) {
			return false;
		}
		return (bool) preg_match( '/[\p{L}]/u', $text );
	}



	private static function is_readiness_nonblocking_segment(
		string $text,
		string $type
	): bool {
		unset( $text );

		/*
		 * Universal readiness policy:
		 * SEO/social description metadata may remain pending without keeping
		 * an otherwise translated page provisional/noindex. Those segments
		 * remain available for later translation when the provider budget
		 * resumes.
		 */
		if (
			in_array(
				$type,
				array(
					'meta_description',
					'meta_og_description',
					'meta_twitter_description',
				),
				true
			)
		) {
			return true;
		}

		/*
		 * Image alt text is accessibility/ancillary metadata. It should be
		 * translated when possible, but must not block page readiness.
		 */
		if ( 'attribute_alt' === $type ) {
			return true;
		}

		return false;
	}

	private static function is_protected_segment(
		string $text,
		array $context,
		string $type
	): bool {
		$class = strtolower(
			(string) ( $context['class'] ?? '' )
		);
		$normalized = self::normalize_text( $text );
		$compact = preg_replace(
			'/\s+/u',
			'',
			$normalized
		);
		$has_cyrillic = (bool) preg_match(
			'/[\x{0400}-\x{04FF}]/u',
			$normalized
		);

		if ( self::is_readiness_nonblocking_segment( $normalized, $type ) ) {
			return true;
		}

		$context_id = strtolower( (string) ( $context['id'] ?? '' ) );
		$context_hint = trim( $class . ' ' . $context_id );
		if (
			'' !== $context_hint
			&& preg_match( '/(?:^|[\s_-])(consent|cookie|privacy|cmp)(?:$|[\s_-])/i', $context_hint )
		) {
			return true;
		}

		if (
			false !== strpos( $class, '__cf_email__' )
			|| false !== strpos( $class, 'notranslate' )
			|| '[email protected]' === strtolower(
				trim( $normalized )
			)
		) {
			return true;
		}

		$pure_sensitive = (
			preg_match(
				'/^(?:https?:\/\/|www\.|mailto:|tel:)/iu',
				$normalized
			)
			|| preg_match(
				'/^[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}$/u',
				$normalized
			)
			|| preg_match(
				'/^UA\d{20,32}$/iu',
				(string) $compact
			)
			|| preg_match(
				'/^\d{12,19}$/u',
				(string) $compact
			)
			|| preg_match(
				'/^(?:0x)?[A-Fa-f0-9]{32,}$/u',
				(string) $compact
			)
			|| preg_match(
				'/^[13][a-km-zA-HJ-NP-Z1-9]{25,42}$/u',
				(string) $compact
			)
			|| preg_match(
				'/^T[A-Za-z0-9]{32,35}$/u',
				(string) $compact
			)
			|| preg_match(
				'/^[1-9A-HJ-NP-Za-km-z]{32,120}$/u',
				(string) $compact
			)
		);

		if ( $pure_sensitive ) {
			return true;
		}

		/*
		 * `copy-this-*` often wraps a mixed label such as
		 * "Картка 5169...". Translate the label and protect the value
		 * through prepare_api_text(), instead of freezing the whole row.
		 */
		if (
			false !== strpos( $class, 'copy-this-' )
			&& ! $has_cyrillic
		) {
			return true;
		}

		/*
		 * Short non-Ukrainian technical labels and token names do not need
		 * translation from the Ukrainian source.
		 */
		if (
			! $has_cyrillic
			&& self::string_length( $normalized ) <= 220
		) {
			return true;
		}

		return false;
	}

	private static function delete_obsolete_segments( int $source_id, array $keys ): void {
		global $wpdb;
		$tables = self::tables();
		if ( empty( $keys ) ) {
			$wpdb->delete( $tables['translations'], array( 'source_id' => $source_id ) );
			$wpdb->delete( $tables['segments'], array( 'source_id' => $source_id ) );
			return;
		}
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$args = array_merge( array( $source_id ), $keys );
		$obsolete = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT segment_key FROM {$tables['segments']}
				WHERE source_id = %d AND segment_key NOT IN ({$placeholders})",
				...$args
			)
		);
		if ( empty( $obsolete ) ) {
			return;
		}
		$obsolete_placeholders = implode( ',', array_fill( 0, count( $obsolete ), '%s' ) );
		$delete_args = array_merge( array( $source_id ), $obsolete );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$tables['translations']}
				WHERE source_id = %d AND segment_key IN ({$obsolete_placeholders})",
				...$delete_args
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$tables['segments']}
				WHERE source_id = %d AND segment_key IN ({$obsolete_placeholders})",
				...$delete_args
			)
		);
	}

	private static function memory_translation( string $language, string $source_hash ): string {
		global $wpdb;
		$tables = self::tables();
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT translated_text FROM {$tables['memory']}
				WHERE language = %s AND source_hash = %s",
				$language,
				$source_hash
			)
		);
		return is_string( $value ) ? $value : '';
	}

	private static function store_memory( string $language, string $source_hash, string $source_text, string $translated_text ): void {
		global $wpdb;
		$tables = self::tables();
		$now = current_time( 'mysql', true );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$tables['memory']}
				(language, source_hash, source_text, translated_text, use_count, created_at, updated_at)
				VALUES (%s, %s, %s, %s, 1, %s, %s)
				ON DUPLICATE KEY UPDATE
				translated_text = VALUES(translated_text),
				use_count = use_count + 1,
				updated_at = VALUES(updated_at)",
				$language,
				$source_hash,
				$source_text,
				$translated_text,
				$now,
				$now
			)
		);
	}

	private static function store_translation(
		int $source_id,
		string $language,
		string $segment_key,
		string $source_hash,
		string $translated_text,
		string $status,
		int $chars_billed
	): void {
		global $wpdb;
		$tables = self::tables();
		$now = current_time( 'mysql', true );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$tables['translations']}
				(source_id, language, segment_key, source_hash, translated_text, status, chars_billed, created_at, updated_at)
				VALUES (%d, %s, %s, %s, %s, %s, %d, %s, %s)
				ON DUPLICATE KEY UPDATE
				source_hash = VALUES(source_hash),
				translated_text = VALUES(translated_text),
				status = VALUES(status),
				chars_billed = VALUES(chars_billed),
				updated_at = VALUES(updated_at)",
				$source_id,
				$language,
				$segment_key,
				$source_hash,
				$translated_text,
				$status,
				$chars_billed,
				$now,
				$now
			)
		);
	}

	private static function refresh_all_jobs_for_source( int $source_id ): void {
		global $wpdb;
		$tables = self::tables();
		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, language FROM {$tables['queue']} WHERE source_id = %d",
				$source_id
			),
			ARRAY_A
		);
		foreach ( is_array( $jobs ) ? $jobs : array() as $job ) {
			self::refresh_job_progress( (int) $job['id'], $source_id, (string) $job['language'] );
		}
	}

	private static function refresh_job_progress( int $job_id, int $source_id, string $language ): array {
		global $wpdb;
		$tables = self::tables();
		$counts = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total,
					SUM(
						CASE
							WHEN s.is_protected = 1 THEN 1
							WHEN t.id IS NOT NULL
								AND t.status = 'ready'
								AND t.source_hash = s.source_hash
							THEN 1
							ELSE 0
						END
					) AS ready,
					SUM(CASE WHEN s.is_protected = 1 THEN 1 ELSE 0 END) AS protected
				FROM {$tables['segments']} s
				LEFT JOIN {$tables['translations']} t
					ON t.source_id = s.source_id
					AND t.language = %s
					AND t.segment_key = s.segment_key
				WHERE s.source_id = %d",
				$language,
				$source_id
			),
			ARRAY_A
		);
		$total = (int) ( $counts['total'] ?? 0 );
		$ready = (int) ( $counts['ready'] ?? 0 );
		$done = $total > 0 && $ready >= $total;
		$now = current_time( 'mysql', true );
		$current_source_hash = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT local_hash FROM {$tables['sources']} WHERE id = %d",
				$source_id
			)
		);
		$data = array(
			'processed_segments' => $ready,
			'total_segments' => $total,
			'updated_at' => $now,
		);
		if ( $done ) {
			$data['status'] = 'done';
			$data['finished_at'] = $now;
			$data['last_error'] = '';
			$data['locked_at'] = null;
			$data['next_run_at'] = null;

			if ( '' !== $current_source_hash ) {
				$data['source_hash'] = $current_source_hash;
			}
		} elseif ( $total > 0 ) {
			$current = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$tables['queue']} WHERE id = %d", $job_id ) );
			if ( in_array( $current, array( 'done', 'failed' ), true ) ) {
				$data['status'] = 'queued';
				$data['finished_at'] = null;
				$data['next_run_at'] = $now;
			}
		}
		$wpdb->update( $tables['queue'], $data, array( 'id' => $job_id ) );
		return array(
			'total' => $total,
			'ready' => $ready,
			'protected' => (int) ( $counts['protected'] ?? 0 ),
			'done' => $done,
		);
	}

	private static function repair_all_language_readiness(): void {
		$repair_option = 'kozstx_route_readiness_fix_v0612';

		if ( get_option( $repair_option, false ) ) {
			return;
		}

		global $wpdb;
		$tables = self::tables();

		$jobs = $wpdb->get_results(
			"SELECT
				q.id,
				q.source_id,
				q.language,
				q.status,
				q.source_hash,
				s.local_hash,
				s.scan_status
			FROM {$tables['queue']} q
			INNER JOIN {$tables['sources']} s
				ON s.id = q.source_id",
			ARRAY_A
		);

		$fixed = 0;
		$checked = 0;

		foreach ( is_array( $jobs ) ? $jobs : array() as $job ) {
			$checked++;
			$before_status = (string) $job['status'];
			$before_hash = (string) $job['source_hash'];

			$progress = self::refresh_job_progress(
				(int) $job['id'],
				(int) $job['source_id'],
				(string) $job['language']
			);

			if (
				! empty( $progress['done'] )
				&& (
					'done' !== $before_status
					|| ! hash_equals(
						(string) $job['local_hash'],
						$before_hash
					)
				)
			) {
				$fixed++;
				self::source_translation_completed(
					(int) $job['source_id'],
					(string) $job['language']
				);
			}
		}

		update_option(
			$repair_option,
			array(
				'completed_at' => current_time( 'c' ),
				'jobs_checked' => $checked,
				'jobs_fixed' => $fixed,
			),
			false
		);

		self::purge_translation_cache();
	}


	private static function repair_donate_validation_deadlock_v0618(): void {
		$repair_option = 'kozstx_donate_validation_fix_v0618';

		if ( get_option( $repair_option, false ) ) {
			return;
		}

		global $wpdb;
		$tables = self::tables();
		$now = current_time( 'mysql', true );

		$source_id = (int) $wpdb->get_var(
			"SELECT id
			FROM {$tables['sources']}
			WHERE source_path = '/donate/'
				OR source_url LIKE '%/donate/'
			ORDER BY id ASC
			LIMIT 1"
		);

		$jobs_requeued = 0;

		if ( $source_id > 0 ) {
			$jobs_requeued = (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$tables['queue']}
					SET status = 'queued',
						priority = 0,
						attempts = 0,
						last_error = '',
						next_run_at = %s,
						locked_at = NULL,
						finished_at = NULL,
						updated_at = %s
					WHERE source_id = %d
						AND status <> 'done'",
					$now,
					$now,
					$source_id
				)
			);
		}

		$runtime = self::runtime();

		if (
			'azure_rate_limit' === (string) $runtime['pause_reason']
			|| (
				! empty( $runtime['pause_until'] )
				&& time() >= (int) $runtime['pause_until']
			)
		) {
			$runtime['pause_reason'] = '';
			$runtime['pause_until'] = 0;
			$runtime['last_error'] = '';
			self::save_runtime( $runtime );
		}

		/*
		 * Remove only translator request locks/rate counters. Translation
		 * rows, memory, usage and source scans remain untouched.
		 */
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_kozstx_dyn_rate_%'
				OR option_name LIKE '_transient_timeout_kozstx_dyn_rate_%'
				OR option_name LIKE '_transient_kozstx_dyn_lock_%'
				OR option_name LIKE '_transient_timeout_kozstx_dyn_lock_%'
				OR option_name LIKE '_transient_kozstx_render_%'
				OR option_name LIKE '_transient_timeout_kozstx_render_%'"
		);

		update_option(
			$repair_option,
			array(
				'completed_at' => current_time( 'c' ),
				'source_id' => $source_id,
				'jobs_requeued' => $jobs_requeued,
			),
			false
		);

		self::log_event(
			0,
			$source_id,
			'',
			'info',
			'donate_validation_fix_v0618',
			0,
			sprintf(
				'Donate jobs requeued: %d. Dynamic rate locks cleared.',
				$jobs_requeued
			)
		);
	}


	private static function release_stale_jobs(): void {
		global $wpdb;
		$tables = self::tables();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS );
		$now = current_time( 'mysql', true );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tables['queue']}
				SET status = 'retry', locked_at = NULL, next_run_at = %s, updated_at = %s,
					last_error = 'Відновлено після завислого worker lock.'
				WHERE status = 'running' AND locked_at IS NOT NULL AND locked_at < %s",
				$now,
				$now,
				$cutoff
			)
		);
	}

	private static function claim_next_job(): ?array {
		global $wpdb;
		$tables = self::tables();
		$now = current_time( 'mysql', true );
		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT q.*, s.scan_status, s.source_url, s.local_hash, s.source_chars, s.segment_count, s.source_title
				FROM {$tables['queue']} q
				INNER JOIN {$tables['sources']} s ON s.id = q.source_id
				WHERE q.status IN ('queued','retry')
					AND (q.next_run_at IS NULL OR q.next_run_at <= %s)
				ORDER BY q.priority ASC,
					CASE WHEN q.category = 'core' THEN 0 ELSE 1 END ASC,
					q.report_date DESC,
					q.language_order ASC,
					q.id ASC
				LIMIT 1",
				$now
			),
			ARRAY_A
		);
		if ( ! is_array( $job ) ) {
			return null;
		}
		$updated = $wpdb->update(
			$tables['queue'],
			array(
				'status' => 'running',
				'locked_at' => $now,
				'updated_at' => $now,
			),
			array(
				'id' => (int) $job['id'],
				'status' => (string) $job['status'],
			)
		);
		if ( 1 !== $updated ) {
			return null;
		}
		$job['status'] = 'running';
		return $job;
	}


	private static function complete_core_source_for_render(
		array $source,
		string $slug
	): array {
		$summary = array(
			'translated' => 0,
			'characters' => 0,
			'requests' => 0,
			'remaining' => 0,
			'message' => 'background_worker',
		);

		if (
			! isset( self::languages()[ $slug ] )
			|| 'ready' !== (string) ( $source['scan_status'] ?? '' )
		) {
			return $summary;
		}

		/*
		 * Public traffic must not generate Azure bursts. It may hydrate
		 * already known memory, while the scheduled worker performs the
		 * actual translation at the configured one-minute cadence.
		 */
		if ( ! self::migration_freeze_active() ) {
			self::hydrate_source_translations_from_memory(
				(int) $source['id'],
				$slug
			);
		}

		return $summary;
	}


	private static function process_one_step(): array {
		$runtime = self::runtime();
		$runtime['last_run_at'] = current_time( 'mysql', true );
		self::save_runtime( $runtime );

		$credentials = self::credentials();
		if ( ! $credentials['configured'] ) {
			return array( 'worked' => false, 'message' => 'Azure Translator key не налаштований.' );
		}

		$runtime = self::runtime();
		if ( ! empty( $runtime['manual_paused'] ) ) {
			return array( 'worked' => false, 'message' => 'Черга поставлена на ручну паузу.' );
		}
		if ( ! empty( $runtime['pause_until'] ) && time() < (int) $runtime['pause_until'] ) {
			return array( 'worked' => false, 'message' => 'Черга тимчасово призупинена: ' . (string) $runtime['pause_reason'] );
		}

		$usage = self::current_usage();
		$settings = self::settings();
		$available = max( 0, (int) $usage['remaining'] - (int) $settings['reserve_chars'] );
		if ( $available <= 0 ) {
			self::pause_for_monthly_limit();
			return array( 'worked' => false, 'message' => 'Місячний ліміт вичерпано.' );
		}

		$job = self::claim_next_job();
		if ( ! is_array( $job ) ) {
			return array( 'worked' => false, 'message' => 'Готових jobs у черзі немає.' );
		}

		$source = self::source_row( (int) $job['source_id'] );
		if ( ! is_array( $source ) ) {
			self::fail_job( $job, 'Source row не знайдено.', true );
			return array( 'worked' => true, 'message' => 'Некоректний job пропущено.' );
		}

		if ( 'ready' !== (string) $source['scan_status'] ) {
			if ( ! self::scan_source( $source ) ) {
				self::retry_job( $job, 'Не вдалося просканувати українську сторінку.', 10 * MINUTE_IN_SECONDS );
				return array( 'worked' => true, 'message' => 'Сканування джерела не вдалося; заплановано повтор.' );
			}
			$source = self::source_row( (int) $job['source_id'] );
		}

		$progress = self::refresh_job_progress( (int) $job['id'], (int) $job['source_id'], (string) $job['language'] );
		if ( ! empty( $progress['done'] ) ) {
			self::source_translation_completed( (int) $job['source_id'], (string) $job['language'] );
			return array( 'worked' => true, 'message' => 'Job уже завершений через translation memory.' );
		}

		$batch = self::pending_batch(
			(int) $job['source_id'],
			(string) $job['language'],
			min( (int) $settings['batch_chars'], $available ),
			(int) $settings['max_segments'],
			$available
		);
		if ( empty( $batch['segments'] ) ) {
			if ( ! empty( $batch['budget_blocked'] ) ) {
				self::pause_for_monthly_limit();
				self::pause_job( $job, 'Недостатньо залишку місячного бюджету для наступного сегмента.' );
				return array( 'worked' => true, 'message' => 'Чергу призупинено до нового циклу.' );
			}
			$progress = self::refresh_job_progress(
				(int) $job['id'],
				(int) $job['source_id'],
				(string) $job['language']
			);

			if ( ! empty( $progress['done'] ) ) {
				self::source_translation_completed(
					(int) $job['source_id'],
					(string) $job['language']
				);

				return array(
					'worked' => true,
					'message' => 'Мовну версію завершено: захищені реквізити збережено без перекладу.',
				);
			}

			return array(
				'worked' => true,
				'message' => 'Немає сегментів для перекладу, але job ще не завершений.',
			);
		}

		$language = self::languages()[ (string) $job['language'] ] ?? null;
		if ( ! is_array( $language ) ) {
			self::fail_job( $job, 'Невідома цільова мова.', true );
			return array( 'worked' => true, 'message' => 'Невідома мова job.' );
		}

		$result = self::azure_translate_batch( $batch['segments'], (string) $language['code'], $credentials );
		if ( is_wp_error( $result ) ) {
			$code = (string) $result->get_error_code();
			$data = $result->get_error_data();
			$retry_after = is_array( $data ) ? (int) ( $data['retry_after'] ?? 0 ) : 0;
			if ( 'azure_quota' === $code ) {
				$cycle = self::cycle_info();
				self::pause_until( 'monthly_limit', (int) $cycle['next_ts'], $result->get_error_message() );
				self::pause_job( $job, $result->get_error_message() );
			} elseif ( 'azure_rate_limit' === $code ) {
				$pause = $retry_after > 0 ? $retry_after : HOUR_IN_SECONDS;
				self::pause_until( 'azure_rate_limit', time() + $pause, $result->get_error_message() );
				self::retry_job( $job, $result->get_error_message(), $pause );
			} else {
				self::retry_job( $job, $result->get_error_message(), self::retry_delay( (int) $job['attempts'] ) );
			}
			self::log_event( (int) $job['id'], (int) $job['source_id'], (string) $job['language'], 'error', 'api_error', 0, $result->get_error_message(), is_array( $data ) ? $data : array() );
			return array( 'worked' => true, 'message' => 'Azure API error: ' . $result->get_error_message() );
		}

		$stored = 0;
		$invalid = 0;
		foreach ( $batch['segments'] as $index => $segment ) {
			$translation = (string) ( $result['translations'][ $index ] ?? '' );
			$translation = self::clean_api_translation( $translation );
			$flags = self::validate_translation( (string) $segment['source_text'], $translation, (string) $job['language'] );
			if ( ! empty( $flags ) ) {
				$invalid++;
				self::log_event(
					(int) $job['id'],
					(int) $job['source_id'],
					(string) $job['language'],
					'warning',
					'validation_failed',
					0,
					implode( ', ', $flags ),
					array( 'segment_key' => $segment['segment_key'] )
				);
				continue;
			}
			self::store_translation(
				(int) $job['source_id'],
				(string) $job['language'],
				(string) $segment['segment_key'],
				(string) $segment['source_hash'],
				$translation,
				'ready',
				self::string_length( (string) $segment['source_text'] )
			);
			self::store_memory(
				(string) $job['language'],
				(string) $segment['source_hash'],
				(string) $segment['source_text'],
				$translation
			);
			$stored++;
		}

		$characters = (int) $result['source_characters'];
		self::increment_usage( (string) $job['language'], $characters );
		self::log_event(
			(int) $job['id'],
			(int) $job['source_id'],
			(string) $job['language'],
			$invalid > 0 ? 'warning' : 'info',
			'api_success',
			$characters,
			sprintf( 'Збережено %d, не пройшло валідацію %d.', $stored, $invalid ),
			array(
				'api_version' => $result['api_version'],
				'request_id' => $result['request_id'],
			)
		);

		$progress = self::refresh_job_progress( (int) $job['id'], (int) $job['source_id'], (string) $job['language'] );
		if ( ! empty( $progress['done'] ) ) {
			self::source_translation_completed( (int) $job['source_id'], (string) $job['language'] );
			$runtime = self::runtime();
			$runtime['last_success_at'] = current_time( 'mysql', true );
			$runtime['last_error'] = '';
			self::save_runtime( $runtime );
			return array( 'worked' => true, 'message' => 'Мовну версію сторінки завершено.' );
		}

		if ( $invalid > 0 && 0 === $stored ) {
			self::retry_job( $job, 'Усі сегменти не пройшли автоматичну валідацію.', self::retry_delay( (int) $job['attempts'] ) );
		} else {
			self::queue_job_again( $job, (int) $settings['request_interval'] );
		}
		return array( 'worked' => true, 'message' => sprintf( 'Перекладено сегментів: %d. Залишилося: %d.', $stored, max( 0, $progress['total'] - $progress['ready'] ) ) );
	}

	private static function pending_batch( int $source_id, string $language, int $char_budget, int $max_segments, int $hard_budget ): array {
		global $wpdb;
		$tables = self::tables();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*
				FROM {$tables['segments']} s
				LEFT JOIN {$tables['translations']} t
					ON t.source_id = s.source_id
					AND t.language = %s
					AND t.segment_key = s.segment_key
				WHERE s.source_id = %d
					AND s.is_protected = 0
					AND (t.id IS NULL OR t.status <> 'ready' OR t.source_hash <> s.source_hash)
				ORDER BY s.segment_order ASC
				LIMIT 1000",
				$language,
				$source_id
			),
			ARRAY_A
		);
		$segments = array();
		$chars = 0;
		$budget_blocked = false;
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$memory = self::memory_translation( $language, (string) $row['source_hash'] );
			if ( '' !== $memory ) {
				self::store_translation( $source_id, $language, (string) $row['segment_key'], (string) $row['source_hash'], $memory, 'ready', 0 );
				continue;
			}
			$length = self::string_length( (string) $row['source_text'] );
			if ( $length > $char_budget && empty( $segments ) ) {
				if ( $length <= min( 30000, $hard_budget ) ) {
					$segments[] = $row;
					$chars += $length;
				} else {
					$budget_blocked = true;
				}
				break;
			}
			if ( $chars + $length > $char_budget || count( $segments ) >= $max_segments ) {
				break;
			}
			$segments[] = $row;
			$chars += $length;
		}
		return array( 'segments' => $segments, 'characters' => $chars, 'budget_blocked' => $budget_blocked );
	}

	private static function azure_translate_batch( array $segments, string $target_code, array $credentials ) {
		$inputs = array();
		$source_characters = 0;
		foreach ( $segments as $segment ) {
			$text = (string) $segment['source_text'];
			$source_characters += self::string_length( $text );
			$inputs[] = array(
				'text' => self::prepare_api_text( $text ),
				'language' => self::source_language_code(),
				'textType' => 'html',
				'targets' => array(
					array( 'language' => $target_code ),
				),
			);
		}

		$endpoint = self::new_api_url( (string) $credentials['endpoint'] );
		$headers = self::api_headers( $credentials );
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 30,
				'redirection' => 0,
				'headers' => $headers,
				'body' => wp_json_encode( array( 'inputs' => $inputs ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( in_array( $status, array( 400, 404, 405 ), true ) ) {
			return self::azure_translate_v3( $segments, $target_code, $credentials, $source_characters );
		}
		if ( $status < 200 || $status >= 300 ) {
			return self::azure_http_error( $response, $status );
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! isset( $data['value'] ) || ! is_array( $data['value'] ) ) {
			return new WP_Error( 'azure_invalid_response', 'Azure повернув некоректну відповідь 2026-06-06.' );
		}

		$translations = array();
		$reported_chars = 0;
		foreach ( $data['value'] as $item ) {
			$text = '';
			if ( isset( $item['translations'][0]['text'] ) ) {
				$text = (string) $item['translations'][0]['text'];
				$reported_chars += (int) ( $item['translations'][0]['sourceCharacters'] ?? 0 );
			}
			$translations[] = $text;
		}
		if ( count( $translations ) !== count( $segments ) ) {
			return new WP_Error( 'azure_response_count', 'Azure повернув іншу кількість перекладів, ніж було надіслано.' );
		}

		return array(
			'translations' => $translations,
			'source_characters' => $reported_chars > 0 ? $reported_chars : $source_characters,
			'api_version' => self::API_VERSION,
			'request_id' => (string) wp_remote_retrieve_header( $response, 'x-requestid' ),
		);
	}

	private static function azure_translate_v3( array $segments, string $target_code, array $credentials, int $source_characters ) {
		$body = array();
		foreach ( $segments as $segment ) {
			$body[] = array( 'Text' => self::prepare_api_text( (string) $segment['source_text'] ) );
		}
		$url = self::v3_api_url( (string) $credentials['endpoint'], $target_code );
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'redirection' => 0,
				'headers' => self::api_headers( $credentials ),
				'body' => wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'data_format' => 'body',
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return self::azure_http_error( $response, $status );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'azure_invalid_response_v3', 'Azure повернув некоректну v3.0 відповідь.' );
		}
		$translations = array();
		foreach ( $data as $item ) {
			$translations[] = (string) ( $item['translations'][0]['text'] ?? '' );
		}
		if ( count( $translations ) !== count( $segments ) ) {
			return new WP_Error( 'azure_response_count_v3', 'Azure v3.0 повернув іншу кількість перекладів.' );
		}
		return array(
			'translations' => $translations,
			'source_characters' => $source_characters,
			'api_version' => '3.0 fallback',
			'request_id' => (string) wp_remote_retrieve_header( $response, 'x-requestid' ),
		);
	}

	private static function api_headers( array $credentials ): array {
		$headers = array(
			'Content-Type' => 'application/json; charset=utf-8',
			'Ocp-Apim-Subscription-Key' => (string) $credentials['key'],
			'X-ClientTraceId' => wp_generate_uuid4(),
		);
		if ( '' !== (string) $credentials['region'] ) {
			$headers['Ocp-Apim-Subscription-Region'] = (string) $credentials['region'];
		}
		return $headers;
	}

	private static function new_api_url( string $endpoint ): string {
		$parts = wp_parse_url( $endpoint );
		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path = rtrim( (string) ( $parts['path'] ?? '' ), '/' );
		if ( '' === $path ) {
			$path = str_ends_with( $host, '.cognitiveservices.azure.com' )
				? '/translator/text/translate'
				: '/translate';
		} elseif ( ! str_ends_with( $path, '/translate' ) ) {
			$path .= '/translate';
		}
		$base = 'https://' . $host . $path;
		return add_query_arg( 'api-version', self::API_VERSION, $base );
	}

	private static function v3_api_url( string $endpoint, string $target_code ): string {
		$parts = wp_parse_url( $endpoint );
		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		$base = 'https://' . $host . '/translate';
		return add_query_arg(
			array(
				'api-version' => '3.0',
				'from' => self::source_language_code(),
				'to' => $target_code,
				'textType' => 'html',
			),
			$base
		);
	}

	private static function azure_http_error( $response, int $status ): WP_Error {
		$body = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		$message = '';
		if ( is_array( $data ) ) {
			$message = (string) ( $data['error']['message'] ?? $data['message'] ?? '' );
		}
		$message = $message ?: 'Azure Translator HTTP ' . $status;
		$retry_header = wp_remote_retrieve_header( $response, 'retry-after' );
		$retry_after = is_numeric( $retry_header ) ? (int) $retry_header : 0;
		if ( 429 === $status ) {
			$lower = strtolower( $message );
			$code = false !== strpos( $lower, 'quota' ) || false !== strpos( $lower, 'monthly' )
				? 'azure_quota'
				: 'azure_rate_limit';
			return new WP_Error( $code, $message, array( 'status' => $status, 'retry_after' => $retry_after ) );
		}
		if ( in_array( $status, array( 401, 403 ), true ) ) {
			return new WP_Error( 'azure_auth', $message, array( 'status' => $status ) );
		}
		return new WP_Error( 'azure_http_' . $status, $message, array( 'status' => $status, 'retry_after' => $retry_after ) );
	}

	private static function prepare_api_text( string $text ): string {
		$escaped = esc_html( $text );
		$terms = array(
			'UA FREE', 'PayPal', 'Monobank', 'MONOBANK', 'PrivatBank',
			'LiqPay', 'LIQPAY', 'Google Pay', 'Apple Pay', 'BTC', 'ETH', 'BNB',
			'USDT', 'USDC', 'XRP', 'Dogecoin', 'Bitcoin Cash', 'Stellar', 'ADA',
			'ERC-20', 'TRC-20', 'BEP-20', 'Solana', 'TGE', 'Maecenata',
		);
		usort( $terms, static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );
		foreach ( $terms as $term ) {
			$quoted = preg_quote( esc_html( $term ), '/' );
			$escaped = preg_replace(
				'/' . $quoted . '/iu',
				'<span class="notranslate" translate="no">$0</span>',
				$escaped
			);
		}
		return '<div>' . $escaped . '</div>';
	}

	private static function clean_api_translation( string $translation ): string {
		$translation = preg_replace( '/<\/?(?:div|span)\b[^>]*>/iu', '', $translation );
		$translation = preg_replace( '/<\/?mstrans:dictionary\b[^>]*>/iu', '', (string) $translation );
		$translation = html_entity_decode( (string) $translation, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$translation = preg_replace( '/\s+/u', ' ', (string) $translation );
		return trim( (string) $translation );
	}

	private static function validate_translation( string $source, string $translation, string $language ): array {
		$flags = array();
		if ( '' === trim( $translation ) ) {
			$flags[] = 'empty_translation';
			return $flags;
		}
		if ( preg_match( '/<[^>]+>/', $translation ) ) {
			$flags[] = 'html_left_in_translation';
		}
		if ( self::digit_signature( $source ) !== self::digit_signature( $translation ) ) {
			$flags[] = 'numbers_changed';
		}
		$source_tokens = self::protected_inline_tokens( $source );
		foreach ( $source_tokens as $token ) {
			if ( false === strpos( $translation, $token ) ) {
				$flags[] = 'protected_token_changed';
				break;
			}
		}
		if (
			'ru' !== $language
			&& preg_match( '/[\x{0400}-\x{04FF}]/u', $source )
			&& self::lower( self::normalize_text( $source ) ) === self::lower( self::normalize_text( $translation ) )
		) {
			$flags[] = 'unchanged_ukrainian';
		}
		return array_values( array_unique( $flags ) );
	}

	private static function protected_inline_tokens(
		string $text
	): array {
		$tokens = array();
		$patterns = array(
			'/https?:\/\/[^\s]+/u',
			'/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/u',
			'/\bUA\s*\d{20,32}\b/iu',
			'/\b\d{12,19}\b/u',
			'/\b0x[A-Fa-f0-9]{32,}\b/u',
			'/\b[13][a-km-zA-HJ-NP-Z1-9]{25,42}\b/u',
			'/\bT[A-Za-z0-9]{32,35}\b/u',
			'/\b[1-9A-HJ-NP-Za-km-z]{32,120}\b/u',
		);

		foreach ( $patterns as $pattern ) {
			preg_match_all( $pattern, $text, $matches );

			foreach ( $matches[0] ?? array() as $match ) {
				$tokens[] = (string) $match;
			}
		}

		return array_values( array_unique( $tokens ) );
	}

	private static function digit_signature(
		string $text
	): string {
		$compact = preg_replace(
			'/[\s\x{00A0}\x{2007}\x{202F}\x{200B}\x{FEFF}\-]+/u',
			'',
			$text
		);

		preg_match_all(
			'/\d{6,}/u',
			(string) $compact,
			$matches
		);

		$numbers = array_values(
			$matches[0] ?? array()
		);
		sort( $numbers, SORT_STRING );

		return implode( '|', $numbers );
	}

	private static function number_tokens( string $text ): array {
		preg_match_all( '/\d+(?:[.,]\d+)?/u', $text, $matches );
		return array_values( $matches[0] ?? array() );
	}

	private static function source_row( int $source_id ): ?array {
		global $wpdb;
		$tables = self::tables();
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$tables['sources']} WHERE id = %d", $source_id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	private static function queue_job_again( array $job, int $delay ): void {
		global $wpdb;
		$tables = self::tables();
		$next = gmdate( 'Y-m-d H:i:s', time() + max( 60, $delay ) );
		$wpdb->update(
			$tables['queue'],
			array(
				'status' => 'queued',
				'attempts' => 0,
				'last_error' => '',
				'next_run_at' => $next,
				'locked_at' => null,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $job['id'] )
		);
	}

	private static function retry_job( array $job, string $error, int $delay ): void {
		global $wpdb;
		$tables = self::tables();
		$settings = self::settings();
		$attempts = (int) $job['attempts'] + 1;
		$next = gmdate( 'Y-m-d H:i:s', time() + max( 60, $delay ) );
		$status = $attempts >= (int) $settings['max_attempts'] ? 'failed' : 'retry';
		if ( 'failed' === $status ) {
			// Не залишаємо automation назавжди мертвим: повтор через 24 години.
			$status = 'retry';
			$attempts = 0;
			$next = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
		}
		$wpdb->update(
			$tables['queue'],
			array(
				'status' => $status,
				'attempts' => $attempts,
				'last_error' => self::short_text( $error, 2000 ),
				'next_run_at' => $next,
				'locked_at' => null,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $job['id'] )
		);
	}

	private static function fail_job( array $job, string $error, bool $permanent ): void {
		global $wpdb;
		$tables = self::tables();
		$wpdb->update(
			$tables['queue'],
			array(
				'status' => $permanent ? 'failed' : 'retry',
				'last_error' => self::short_text( $error, 2000 ),
				'locked_at' => null,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $job['id'] )
		);
	}

	private static function pause_job( array $job, string $error ): void {
		global $wpdb;
		$tables = self::tables();
		$cycle = self::cycle_info();
		$wpdb->update(
			$tables['queue'],
			array(
				'status' => 'paused',
				'last_error' => self::short_text( $error, 2000 ),
				'next_run_at' => gmdate( 'Y-m-d H:i:s', (int) $cycle['next_ts'] ),
				'locked_at' => null,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $job['id'] )
		);
	}

	private static function pause_for_monthly_limit(): void {
		$cycle = self::cycle_info();
		self::pause_until( 'monthly_limit', (int) $cycle['next_ts'], 'Локальний місячний бюджет Azure Translator вичерпано.' );
		global $wpdb;
		$tables = self::tables();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tables['queue']}
				SET status = 'paused', next_run_at = %s, locked_at = NULL, updated_at = %s
				WHERE status IN ('queued','retry','running')",
				gmdate( 'Y-m-d H:i:s', (int) $cycle['next_ts'] ),
				current_time( 'mysql', true )
			)
		);
	}

	private static function retry_delay( int $attempts ): int {
		$minutes = min( 360, max( 5, (int) pow( 2, min( 6, $attempts ) ) * 5 ) );
		return $minutes * MINUTE_IN_SECONDS;
	}

	private static function source_translation_completed( int $source_id, string $language ): void {
		self::purge_translation_cache();
		self::log_event( 0, $source_id, $language, 'info', 'language_page_ready', 0, 'Мовна версія готова до публічного маршруту.' );
		$source = self::source_row( $source_id );
		if ( is_array( $source ) ) {
			do_action( 'kozstx_translation_ready', self::language_url( (string) $source['source_url'], $language ), (string) $source['source_url'], $language, (int) $source['post_id'] );
		}
	}

	private static function purge_translation_cache(): void {
		$runtime = self::runtime();
		$last = ! empty( $runtime['last_cache_purge_at'] ) ? strtotime( (string) $runtime['last_cache_purge_at'] . ' UTC' ) : 0;
		if ( $last && time() - $last < 5 * MINUTE_IN_SECONDS ) {
			return;
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		$runtime['last_cache_purge_at'] = current_time( 'mysql', true );
		self::save_runtime( $runtime );
	}

	private static function log_event(
		int $queue_id,
		int $source_id,
		string $language,
		string $level,
		string $event,
		int $characters,
		string $message,
		array $context = array()
	): void {
		global $wpdb;
		$tables = self::tables();
		$wpdb->insert(
			$tables['logs'],
			array(
				'queue_id' => $queue_id,
				'source_id' => $source_id,
				'language' => $language,
				'level' => sanitize_key( $level ),
				'event' => sanitize_key( $event ),
				'characters' => max( 0, $characters ),
				'request_id' => isset( $context['request_id'] ) ? sanitize_text_field( (string) $context['request_id'] ) : '',
				'message' => self::short_text( $message, 4000 ),
				'context_json' => wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'created_at' => current_time( 'mysql', true ),
			)
		);
	}

	private static function cleanup_old_logs(): void {
		global $wpdb;
		$tables = self::tables();
		$runtime = self::runtime();
		$last = ! empty( $runtime['last_log_cleanup_at'] ) ? strtotime( (string) $runtime['last_log_cleanup_at'] . ' UTC' ) : 0;
		if ( $last && time() - $last < DAY_IN_SECONDS ) {
			return;
		}
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$tables['logs']} WHERE created_at < %s", $cutoff ) );
		$runtime['last_log_cleanup_at'] = current_time( 'mysql', true );
		self::save_runtime( $runtime );
	}

	public static function add_rewrite_rules(): void {
		$slugs = array_keys( self::languages() );
		if ( empty( $slugs ) ) {
			return;
		}
		$pattern = implode( '|', array_map( static fn( string $slug ): string => preg_quote( $slug, '/' ), $slugs ) );
		add_rewrite_rule( '^uafree-translations-sitemap\.xml$', 'index.php?' . self::SITEMAP_QUERY_VAR . '=1', 'top' );
		add_rewrite_rule( '^ru(?:/.*)?$', 'index.php?' . self::FORBIDDEN_LANG_QUERY_VAR . '=1', 'top' );
		add_rewrite_rule( '^(' . $pattern . ')/?$', 'index.php?' . self::LANG_QUERY_VAR . '=$matches[1]&' . self::PATH_QUERY_VAR . '=__home__', 'top' );
		add_rewrite_rule( '^(' . $pattern . ')/(.*)$', 'index.php?' . self::LANG_QUERY_VAR . '=$matches[1]&' . self::PATH_QUERY_VAR . '=$matches[2]', 'top' );
	}

	public static function query_vars( array $vars ): array {
		$vars[] = self::LANG_QUERY_VAR;
		$vars[] = self::PATH_QUERY_VAR;
		$vars[] = self::SITEMAP_QUERY_VAR;
		$vars[] = self::FORBIDDEN_LANG_QUERY_VAR;
		return $vars;
	}

	public static function template_router(): void {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( get_query_var( self::FORBIDDEN_LANG_QUERY_VAR ) ) {
			status_header( 410 );
			header( 'X-Robots-Tag: noindex, nofollow', true );
			echo 'This language version is permanently unavailable.';
			exit;
		}
		if ( get_query_var( self::SITEMAP_QUERY_VAR ) ) {
			self::serve_sitemap();
		}
		$slug = sanitize_key( (string) get_query_var( self::LANG_QUERY_VAR ) );
		$path = (string) get_query_var( self::PATH_QUERY_VAR );
		if ( '' === $slug || ! isset( self::languages()[ $slug ] ) ) {
			$fallback = self::language_request_from_uri();
			if ( empty( $fallback['slug'] ) || ! isset( self::languages()[ $fallback['slug'] ] ) ) {
				return;
			}
			$slug = (string) $fallback['slug'];
			$path = (string) $fallback['path'];
			set_query_var( self::LANG_QUERY_VAR, $slug );
			set_query_var( self::PATH_QUERY_VAR, $path );
		}
		$settings = self::settings();
		if ( empty( $settings['routes_enabled'] ) ) {
			self::language_route_not_ready( $slug, 404 );
			return;
		}
		self::serve_language_page( $slug, $path );
	}


	private static function language_request_from_uri(): array {
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '/';
		$request_path = rawurldecode( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) );
		$home_path = rawurldecode( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		$home_path = '/' . trim( $home_path, '/' );
		if ( '/' !== $home_path && 0 === strpos( $request_path, $home_path . '/' ) ) {
			$request_path = substr( $request_path, strlen( $home_path ) );
		}
		$trimmed = trim( $request_path, '/' );
		if ( '' === $trimmed ) {
			return array( 'slug' => '', 'path' => '' );
		}
		$parts = explode( '/', $trimmed, 2 );
		$slug = sanitize_key( (string) ( $parts[0] ?? '' ) );
		if ( ! isset( self::languages()[ $slug ] ) ) {
			return array( 'slug' => '', 'path' => '' );
		}
		$path = isset( $parts[1] ) && '' !== trim( (string) $parts[1], '/' )
			? ltrim( (string) $parts[1], '/' )
			: '__home__';
		return array( 'slug' => $slug, 'path' => $path );
	}

	private static function serve_language_page( string $slug, string $path ): void {
		$source_url = '__home__' === $path
			? home_url( '/' )
			: home_url( '/' . ltrim( rawurldecode( $path ), '/' ) );

		/*
		 * Resolve through our own inventory first. WordPress url_to_postid()
		 * can return zero for PageLayer/custom-rendered routes even when the
		 * source exists and has already been translated.
		 */
		$source = self::source_by_url( $source_url );

		if ( ! is_array( $source ) ) {
			$post_id = '__home__' === $path
				? (int) get_option( 'page_on_front' )
				: (int) url_to_postid( $source_url );

			if ( $post_id > 0 ) {
				$source = self::source_by_post_id( $post_id );

				/*
				 * Generic inventory recovery for builder/custom-rendered pages that
				 * resolve to a real WordPress post but have not yet been registered
				 * in the translation source table. No route names are special-cased.
				 */
				if ( ! is_array( $source ) ) {
					$source_id = self::upsert_source_post( $post_id, true );
					if ( $source_id > 0 ) {
						$source = self::source_row( $source_id );
					}
				}
			}
		}

		if ( ! is_array( $source ) ) {
			self::language_route_not_ready( $slug, 404 );
			return;
		}

		if ( 'ready' !== (string) $source['scan_status'] ) {
			if ( self::migration_freeze_active() ) {
				self::language_route_not_ready( $slug, 503 );
				return;
			}

			self::scan_source( $source );
			$source = self::source_row( (int) $source['id'] );
		}

		$catchup = array(
			'translated' => 0,
			'characters' => 0,
			'message' => '',
		);

		if (
			is_array( $source )
			&& empty( $source['is_report'] )
		) {
			$catchup = self::complete_core_source_for_render(
				$source,
				$slug
			);
		}

		$strict_ready = is_array( $source )
			? self::language_ready( (int) $source['id'], $slug )
			: false;

		$provisional = ! $strict_ready
			&& is_array( $source )
			&& self::language_can_render_provisional(
				(int) $source['id'],
				$slug
			);

		if ( ! $strict_ready && ! $provisional ) {
			self::language_route_not_ready( $slug, 200 );
			return;
		}

		$translated_cache_key = self::translated_render_cache_key( $source, $slug );
		$translated = self::cached_render_html( $translated_cache_key );

		if ( '' === $translated ) {
			$html = self::cached_render_html( self::source_render_cache_key( (int) $source['id'] ) );

			if ( '' === $html ) {
				/*
				 * Fall back to one bounded same-site source request only when no
				 * recent source snapshot exists. Successful responses warm the
				 * source snapshot so subsequent translated crawler requests avoid
				 * the expensive loopback render entirely.
				 */
				$response = wp_safe_remote_get(
					(string) $source['source_url'],
					array(
						'timeout' => 20,
						'redirection' => 5,
						'limit_response_size' => 12 * MB_IN_BYTES,
						'headers' => array(
							'Accept' => 'text/html,application/xhtml+xml',
							'Accept-Language' => self::source_language_code(),
							'X-KOZSTX-Source-Render' => '1',
						),
						'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36',
					)
				);
				if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) < 200 || (int) wp_remote_retrieve_response_code( $response ) >= 300 ) {
					status_header( 503 );
					header( 'Retry-After: 300' );
					header( 'X-Robots-Tag: noindex, nofollow', true );
					echo 'Translation source is temporarily unavailable.';
					exit;
				}
				$html = (string) wp_remote_retrieve_body( $response );
				self::store_render_html( self::source_render_cache_key( (int) $source['id'] ), $html, 6 * HOUR_IN_SECONDS );
			}

			$translated = self::transform_html( $html, $source, $slug, $provisional );
			if ( ! is_wp_error( $translated ) ) {
				self::store_render_html( $translated_cache_key, (string) $translated, 6 * HOUR_IN_SECONDS );
			}
		}
		if ( is_wp_error( $translated ) ) {
			status_header( 503 );
			header( 'Retry-After: 300' );
			header( 'X-Robots-Tag: noindex, nofollow', true );
			echo esc_html( $translated->get_error_message() );
			exit;
		}
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );

		if ( $provisional ) {
			header( 'Cache-Control: public, max-age=60, s-maxage=120' );
			header( 'X-KOZSTX-Translation-State: partial', true );
			header( 'X-Robots-Tag: noindex, follow', true );
		} else {
			header( 'Cache-Control: public, max-age=300, s-maxage=900' );
			header( 'X-KOZSTX-Translation-State: ready', true );
		}

		header( 'X-KOZSTX-Translation: ' . $slug );
		header( 'X-KOZSTX-Translation-Version: ' . KOZSTX_VERSION );
		if (
			false !== strpos( $translated, 'pgc-sgb-cb' )
			|| false !== strpos( $translated, 'wp-block-pgcsimplygalleryblock' )
		) {
			header( 'X-KOZSTX-Gallery-Mode: verbatim-passthrough' );
		}
		if ( false !== strpos( $translated, 'id="kozstx-dynamic-translator"' ) ) {
			header( 'X-KOZSTX-Dynamic-Translate: 1' );
		}
		if ( false !== strpos( $translated, 'id="kozstx-language-link-guard"' ) ) {
			header( 'X-KOZSTX-Link-Guard: 1' );
		}
		if ( ! empty( $catchup['translated'] ) ) {
			header(
				'X-KOZSTX-Render-Catchup: ' .
				(int) $catchup['translated'] .
				'; chars=' .
				(int) $catchup['characters']
			);
		}
		echo $translated; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private static function language_route_not_ready( string $slug, int $status = 200 ): void {
		global $wp_query;

		if ( $wp_query instanceof WP_Query ) {
			$wp_query->is_404 = false;
		}

		$language = self::languages()[ $slug ] ?? array(
			'native' => strtoupper( $slug ),
			'html' => $slug,
			'dir' => 'ltr',
		);

		$path = (string) get_query_var( self::PATH_QUERY_VAR );
		$source_url = '__home__' === $path || '' === $path
			? home_url( '/' )
			: home_url( '/' . ltrim( rawurldecode( $path ), '/' ) );

		$post_id = '__home__' === $path || '' === $path
			? (int) get_option( 'page_on_front' )
			: (int) url_to_postid( $source_url );

		$ready = array();
		if ( $post_id > 0 ) {
			$source = self::source_by_post_id( $post_id );
			if ( is_array( $source ) ) {
				$ready = self::ready_languages_for_source( (int) $source['id'] );
			}
		}

		$status = in_array( $status, array( 200, 404, 503 ), true ) ? $status : 200;
		status_header( $status );
		nocache_headers();
		if ( 503 === $status ) {
			header( 'Retry-After: 60', true );
		}
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 200 === $status ? 'Cache-Control: public, max-age=60, s-maxage=120' : 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'X-KOZSTX-Translation-State: ' . ( 200 === $status ? 'pending' : 'not-ready' ), true );
		header( 'X-KOZSTX-Translation-Version: ' . KOZSTX_VERSION, true );
		header( 'X-Robots-Tag: noindex, ' . ( 404 === $status ? 'nofollow' : 'follow' ), true );

		$title = 'Translation is being prepared';
		$site_name = trim( (string) get_bloginfo( 'name' ) );
		if ( '' === $site_name ) {
			$site_name = 'WordPress';
		}
		$requested = (string) ( $language['native'] ?? strtoupper( $slug ) );
		$html_lang = (string) ( $language['html'] ?? $slug );
		$dir = 'rtl' === ( $language['dir'] ?? 'ltr' ) ? 'rtl' : 'ltr';

		echo '<!doctype html>';
		echo '<html lang="' . esc_attr( $html_lang ) . '" dir="' . esc_attr( $dir ) . '">';
		echo '<head>';
		echo '<meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<meta name="robots" content="noindex,' . ( 404 === $status ? 'nofollow' : 'follow' ) . '">';
		if ( 200 === $status ) {
			echo '<title></title>';
			echo '<meta name="description" content="">';
			echo '<link rel="canonical" href="' . esc_url( self::language_url( $source_url, $slug ) ) . '">';
			echo '<meta property="og:title" content="">';
			echo '<meta property="og:description" content="">';
			echo '<meta name="twitter:title" content="">';
			echo '<meta name="twitter:description" content="">';
		} else {
			echo '<title>' . esc_html( $title ) . ' | ' . esc_html( $site_name ) . '</title>';
		}
		echo '<style>
			:root{color-scheme:light}
			*{box-sizing:border-box}
			html,body{margin:0;min-height:100%;font-family:Arial,Helvetica,sans-serif;background:#f6f7f7;color:#1d2327}
			body{display:grid;place-items:center;padding:24px}
			.kozstx-wait{width:min(680px,100%);background:#fff;border:1px solid #dcdcde;border-radius:22px;padding:34px;box-shadow:0 18px 55px rgba(0,0,0,.10);text-align:center}
			.kozstx-logo{display:inline-flex;align-items:center;justify-content:center;min-width:150px;padding:14px 18px;margin-bottom:24px;border-radius:16px;background:#111;color:#fff;font-size:34px;font-weight:800;letter-spacing:-1px}
			h1{font-size:clamp(28px,5vw,46px);line-height:1.08;margin:0 0 16px}
			p{font-size:18px;line-height:1.6;margin:8px 0;color:#50575e}
			.kozstx-requested{display:inline-block;margin:12px 0 20px;padding:7px 12px;border-radius:999px;background:#eef6ff;color:#0a4b78;font-weight:700}
			.kozstx-actions{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin-top:26px}
			.kozstx-actions a,.kozstx-actions select{min-height:44px;border-radius:10px;border:1px solid #8c8f94;padding:10px 14px;font:600 15px/1.2 Arial,Helvetica,sans-serif}
			.kozstx-actions a{display:inline-flex;align-items:center;text-decoration:none;background:#2271b1;color:#fff;border-color:#2271b1}
			.kozstx-actions select{background:#fff;color:#1d2327}
			.kozstx-refresh{font-size:14px;color:#646970}
			.kozstx-code{margin-top:24px;font-size:13px;color:#787c82}
			@media(max-width:520px){.kozstx-wait{padding:26px 20px}.kozstx-actions>*{width:100%}}
		</style>';
		echo '</head><body>';
		echo '<main class="kozstx-wait">';
		echo '<div class="kozstx-logo" aria-label="' . esc_attr( $site_name ) . '">' . esc_html( $site_name ) . '</div>';
		echo '<div class="kozstx-requested">' . esc_html( $requested ) . '</div>';
		echo '<h1>Translation is being prepared</h1>';
		echo '<p>This language version is still being prepared automatically.</p>';
		echo '<p>The page will become available after translation and automatic validation are complete.</p>';
		echo '<p class="kozstx-refresh">The page will refresh automatically in <strong id="kozstx-countdown">10</strong> seconds.</p>';
		echo '<div class="kozstx-actions">';
		echo '<a href="' . esc_url( $source_url ) . '">View source page</a>';

		if ( ! empty( $ready ) ) {
			echo '<select aria-label="Language" onchange="if(this.value){window.location.href=this.value;}">';
			echo '<option value="">Other ready languages</option>';
			foreach ( $ready as $ready_slug ) {
				if ( ! isset( self::languages()[ $ready_slug ] ) ) {
					continue;
				}
				$info = self::languages()[ $ready_slug ];
				echo '<option value="' . esc_url( self::language_url( $source_url, $ready_slug ) ) . '">' . esc_html( (string) $info['native'] ) . '</option>';
			}
			echo '</select>';
		}

		echo '</div>';
		echo '<div class="kozstx-code">' . esc_html( (string) $status ) . ' · translation is finishing · KOZ Static Translate ' . esc_html( KOZSTX_VERSION ) . '</div>';
		echo '</main>';
		echo '<script>(function(){var n=10,e=document.getElementById("kozstx-countdown");window.setInterval(function(){n=Math.max(0,n-1);if(e){e.textContent=String(n);}if(n===0){window.location.reload();}},1000);}());</script>';
		echo '</body></html>';
		exit;
	}


	private static function residual_dom_translation(
		DOMDocument $dom,
		DOMXPath $xpath,
		array $source,
		string $slug,
		bool $suppress_seo = false
	): array {
		$summary = array(
			'memory' => 0,
			'translated' => 0,
			'characters' => 0,
			'invalid' => 0,
		);

		if (
			! isset( self::languages()[ $slug ] )
			|| self::DEFAULT_SOURCE_LANGUAGE !== self::source_language_slug()
		) {
			return $summary;
		}

		$body = $dom->getElementsByTagName( 'body' )->item( 0 );

		if ( ! $body instanceof DOMElement ) {
			return $summary;
		}

		$targets = array();

		if ( ! $suppress_seo ) {
			/*
			 * Metadata can change after the last source scan (SEO plugins, excerpts,
			 * page builders, cache refreshes). The normal segment map handles scanned
			 * values; this residual pass catches source-language metadata that is still
			 * present in the final translated DOM. This is deliberately generic and
			 * does not depend on a domain, route, brand name or hard-coded translation.
			 */
			$title = $dom->getElementsByTagName( 'title' )->item( 0 );
			if ( $title instanceof DOMElement ) {
				$text = self::normalize_text( (string) $title->textContent );
				if ( self::is_residual_source_text( $text ) ) {
					$hash = hash( 'sha256', $text );
					if ( ! isset( $targets[ $hash ] ) ) {
						$targets[ $hash ] = array(
							'source' => $text,
							'text_nodes' => array(),
							'element_text' => array(),
							'attributes' => array(),
						);
					}
					$targets[ $hash ]['element_text'][] = $title;
				}
			}

			$seo_meta_queries = array(
				'//meta[@name="description"]',
				'//meta[@property="og:title"]',
				'//meta[@property="og:description"]',
				'//meta[@property="og:site_name"]',
				'//meta[@name="twitter:title"]',
				'//meta[@name="twitter:description"]',
			);

			foreach ( $seo_meta_queries as $query ) {
				$nodes = $xpath->query( $query );
				foreach ( $nodes as $node ) {
					if ( ! $node instanceof DOMElement ) {
						continue;
					}
					$text = self::normalize_text( $node->getAttribute( 'content' ) );
					if ( ! self::is_residual_source_text( $text ) ) {
						continue;
					}
					$hash = hash( 'sha256', $text );
					if ( ! isset( $targets[ $hash ] ) ) {
						$targets[ $hash ] = array(
							'source' => $text,
							'text_nodes' => array(),
							'element_text' => array(),
							'attributes' => array(),
						);
					}
					$targets[ $hash ]['attributes'][] = array(
						'element' => $node,
						'attribute' => 'content',
					);
				}
			}
		}

		$text_nodes = $xpath->query(
			'.//text()[normalize-space(.) != ""]',
			$body
		);

		foreach ( $text_nodes as $node ) {
			$parent = $node->parentNode;

			if (
				! $parent instanceof DOMElement
				|| self::excluded_element( $parent )
			) {
				continue;
			}

			$raw = (string) $node->nodeValue;
			$text = self::normalize_text( $raw );

			if ( ! self::is_residual_source_text( $text ) ) {
				continue;
			}

			$hash = hash( 'sha256', $text );

			if ( ! isset( $targets[ $hash ] ) ) {
				$targets[ $hash ] = array(
					'source' => $text,
					'text_nodes' => array(),
					'element_text' => array(),
					'attributes' => array(),
				);
			}

			$targets[ $hash ]['text_nodes'][] = array(
				'node' => $node,
				'prefix' => preg_match( '/^\\s*/u', $raw, $m )
					? (string) $m[0]
					: '',
				'suffix' => preg_match( '/\\s*$/u', $raw, $m )
					? (string) $m[0]
					: '',
			);
		}

		foreach (
			array(
				'alt',
				'title',
				'placeholder',
				'aria-label',
			) as $attribute
		) {
			$elements = $xpath->query(
				'.//*[@' . $attribute . ']',
				$body
			);

			foreach ( $elements as $element ) {
				if (
					! $element instanceof DOMElement
					|| self::excluded_element( $element )
				) {
					continue;
				}

				$text = self::normalize_text(
					$element->getAttribute( $attribute )
				);

				if ( ! self::is_residual_source_text( $text ) ) {
					continue;
				}

				$hash = hash( 'sha256', $text );

				if ( ! isset( $targets[ $hash ] ) ) {
					$targets[ $hash ] = array(
						'source' => $text,
						'text_nodes' => array(),
						'element_text' => array(),
						'attributes' => array(),
					);
				}

				$targets[ $hash ]['attributes'][] = array(
					'element' => $element,
					'attribute' => $attribute,
				);
			}
		}

		if ( empty( $targets ) ) {
			return $summary;
		}

		$apply = static function (
			array $target,
			string $translation
		) use ( $dom ): void {
			foreach ( $target['text_nodes'] as $item ) {
				$node = $item['node'];

				if ( $node instanceof DOMText ) {
					$node->nodeValue =
						(string) $item['prefix']
						. $translation
						. (string) $item['suffix'];
				}
			}

			foreach ( (array) ( $target['element_text'] ?? array() ) as $element ) {
				if ( ! $element instanceof DOMElement ) {
					continue;
				}
				while ( $element->firstChild ) {
					$element->removeChild( $element->firstChild );
				}
				$element->appendChild( $dom->createTextNode( $translation ) );
			}

			foreach ( $target['attributes'] as $item ) {
				$element = $item['element'];

				if ( $element instanceof DOMElement ) {
					$element->setAttribute(
						(string) $item['attribute'],
						$translation
					);
				}
			}
		};

		$missing = array();

		foreach ( $targets as $hash => $target ) {
			$memory = self::memory_translation(
				$slug,
				$hash
			);

			if ( '' !== $memory ) {
				$apply( $target, $memory );
				self::store_translations_for_source_hash(
					(int) $source['id'],
					$slug,
					$hash,
					$memory
				);
				$summary['memory']++;
			} else {
				$missing[ $hash ] = $target;
			}
		}

		if ( empty( $missing ) ) {
			return $summary;
		}

		$settings = self::settings();
		$runtime = self::runtime();
		$credentials = self::credentials();

		if (
			empty( $credentials['configured'] )
			|| ! empty( $runtime['manual_paused'] )
			|| (
				! empty( $runtime['pause_until'] )
				&& time() < (int) $runtime['pause_until']
			)
		) {
			return $summary;
		}

		$usage = self::current_usage();
		$available = max(
			0,
			(int) $usage['remaining']
				- (int) $settings['reserve_chars']
		);

		if ( $available <= 0 ) {
			return $summary;
		}

		$lock_key = 'kozstx_residual_' . substr(
			hash(
				'sha256',
				(int) $source['id'] . '|' . $slug
			),
			0,
			32
		);

		if ( get_transient( $lock_key ) ) {
			return $summary;
		}

		set_transient( $lock_key, 1, 2 * MINUTE_IN_SECONDS );

		$started = microtime( true );
		$remaining = $missing;
		$requests = 0;

		while (
			! empty( $remaining )
			&& $requests < 8
			&& ( microtime( true ) - $started ) < 24
			&& $available > 0
		) {
			$segments = array();
			$selected_hashes = array();
			$chars = 0;

			foreach ( $remaining as $hash => $target ) {
				$text = (string) $target['source'];
				$length = self::string_length( $text );

				if (
					count( $segments ) >= 100
					|| $chars + $length > min( 45000, $available )
				) {
					break;
				}

				$segments[] = array(
					'source_hash' => $hash,
					'source_text' => $text,
				);
				$selected_hashes[] = $hash;
				$chars += $length;
			}

			if ( empty( $segments ) ) {
				break;
			}

			$api_result = self::azure_translate_batch(
				$segments,
				(string) self::languages()[ $slug ]['code'],
				$credentials
			);
			$requests++;

			if ( is_wp_error( $api_result ) ) {
				self::log_event(
					0,
					(int) $source['id'],
					$slug,
					'error',
					'residual_dom_error',
					0,
					$api_result->get_error_message()
				);
				break;
			}

			foreach ( $segments as $index => $segment ) {
				$hash = (string) $segment['source_hash'];
				$translation = self::clean_api_translation(
					(string) (
						$api_result['translations'][ $index ]
							?? ''
					)
				);

				$flags = self::validate_translation(
					(string) $segment['source_text'],
					$translation,
					$slug
				);

				if ( ! empty( $flags ) ) {
					$summary['invalid']++;
					unset( $remaining[ $hash ] );
					continue;
				}

				$apply( $targets[ $hash ], $translation );

				self::store_memory(
					$slug,
					$hash,
					(string) $segment['source_text'],
					$translation
				);

				self::store_translations_for_source_hash(
					(int) $source['id'],
					$slug,
					$hash,
					$translation
				);

				$summary['translated']++;
				unset( $remaining[ $hash ] );
			}

			$characters = (int) (
				$api_result['source_characters']
					?? $chars
			);

			if ( $characters > 0 ) {
				self::increment_usage(
					$slug,
					$characters
				);
				$summary['characters'] += $characters;
				$available = max(
					0,
					$available - $characters
				);
			}
		}

		delete_transient( $lock_key );

		global $wpdb;
		$tables = self::tables();
		$job_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id
				FROM {$tables['queue']}
				WHERE source_id = %d
					AND language = %s",
				(int) $source['id'],
				$slug
			)
		);

		if ( $job_id > 0 ) {
			$progress = self::refresh_job_progress(
				$job_id,
				(int) $source['id'],
				$slug
			);

			if ( ! empty( $progress['done'] ) ) {
				self::source_translation_completed(
					(int) $source['id'],
					$slug
				);
			}
		}

		self::log_event(
			$job_id,
			(int) $source['id'],
			$slug,
			$summary['invalid'] > 0 ? 'warning' : 'info',
			'residual_dom_translation',
			(int) $summary['characters'],
			sprintf(
				'DOM fallback: memory %d, Azure %d, rejected %d.',
				(int) $summary['memory'],
				(int) $summary['translated'],
				(int) $summary['invalid']
			)
		);

		return $summary;
	}

	private static function is_residual_source_text(
		string $text
	): bool {
		$text = self::normalize_text( $text );

		if (
			! self::is_translatable_text( $text )
			|| ! preg_match( '/[\\x{0400}-\\x{04FF}]/u', $text )
		) {
			return false;
		}

		/*
		 * Pure payment values are preserved. Mixed labels such as
		 * "Картка 5169..." are translated while the number is protected by
		 * prepare_api_text().
		 */
		$compact = preg_replace( '/\\s+/u', '', $text );

		if (
			preg_match(
				'/^(?:https?:\\/\\/|www\\.|mailto:|tel:)/iu',
				$text
			)
			|| preg_match(
				'/^[\\w.+-]+@[\\w.-]+\\.[A-Za-z]{2,}$/u',
				$text
			)
			|| preg_match(
				'/^UA\\d{20,32}$/iu',
				(string) $compact
			)
			|| preg_match(
				'/^\\d{12,19}$/u',
				(string) $compact
			)
			|| preg_match(
				'/^(?:0x)?[A-Fa-f0-9]{32,}$/u',
				(string) $compact
			)
			|| preg_match(
				'/^[1-9A-HJ-NP-Za-km-z]{32,120}$/u',
				(string) $compact
			)
		) {
			return false;
		}

		return true;
	}



	private static function neutralize_provisional_seo_dom( DOMDocument $dom, DOMXPath $xpath ): void {
		$title = $dom->getElementsByTagName( 'title' )->item( 0 );
		if ( $title instanceof DOMElement ) {
			$title->nodeValue = '';
		}

		$queries = array(
			'//meta[@name="description"]',
			'//meta[@property="og:title"]',
			'//meta[@property="og:description"]',
			'//meta[@property="og:site_name"]',
			'//meta[@name="twitter:title"]',
			'//meta[@name="twitter:description"]',
		);

		foreach ( $queries as $query ) {
			$nodes = $xpath->query( $query );
			foreach ( $nodes as $node ) {
				if ( $node instanceof DOMElement ) {
					$node->setAttribute( 'content', '' );
				}
			}
		}

		$robots_nodes = $xpath->query( '//meta[translate(@name,"ROBOTS","robots")="robots"]' );
		$remove = array();
		foreach ( $robots_nodes as $node ) {
			if ( $node instanceof DOMElement ) {
				$remove[] = $node;
			}
		}
		foreach ( $remove as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}

		$head = $dom->getElementsByTagName( 'head' )->item( 0 );
		if ( $head instanceof DOMElement ) {
			$robots = $dom->createElement( 'meta' );
			$robots->setAttribute( 'name', 'robots' );
			$robots->setAttribute( 'content', 'noindex, follow, max-image-preview:large' );
			$head->appendChild( $robots );
		}
	}

	private static function neutralize_provisional_structured_data_html( string $html ): string {
		/*
		 * A partially translated route must not publish source-language schema as
		 * if it were localized. The route is noindex until complete, so omit JSON-LD
		 * entirely and restore it automatically on the first fully-ready render.
		 */
		return (string) preg_replace(
			'~<script\\b[^>]*\\btype\\s*=\\s*["\']application/ld\\+json["\'][^>]*>.*?</script\\s*>~is',
			'',
			$html
		);
	}

	private static function transform_html( string $html, array $source, string $slug, bool $allow_source_fallback = false ) {
		$language = self::languages()[ $slug ] ?? null;
		if ( ! is_array( $language ) || ! class_exists( 'DOMDocument' ) ) {
			return new WP_Error( 'translation_dom', 'DOM translation renderer is unavailable.' );
		}

		/*
		 * Rewrite localized URLs while the JSON-LD is still valid, then protect
		 * every script block from DOMDocument reserialization. This prevents HTML
		 * entity decoding from turning quotes inside JSON strings into syntax
		 * errors on translated routes.
		 */
		$html = self::rewrite_json_ld_html(
			$html,
			(string) $source['source_url'],
			self::language_url( (string) $source['source_url'], $slug ),
			(string) $language['code']
		);

		$protected_scripts = array();
		$html = self::protect_executable_scripts(
			$html,
			$protected_scripts
		);

		/*
		 * SimpLy Gallery is initialized from exact frontend markup and block data.
		 * Preserve those blocks byte-for-byte as well.
		 */
		$protected_galleries = array();
		$html = self::protect_gallery_blocks(
			$html,
			$protected_galleries
		);

		$map = self::translation_map( (int) $source['id'], $slug );
		$text_map = self::translation_text_map( (int) $source['id'], $slug );
		if ( empty( $map ) && ! $allow_source_fallback ) {
			return new WP_Error( 'translation_map', 'Translation map is empty.' );
		}
		$previous = libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$loaded = $dom->loadHTML(
			'<?xml encoding="utf-8" ?>' . $html,
			LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			return new WP_Error( 'translation_dom_parse', 'Could not parse source HTML.' );
		}
		$xpath = new DOMXPath( $dom );

		$title = $dom->getElementsByTagName( 'title' )->item( 0 );
		if ( $title ) {
			$key = hash( 'sha256', 'document_title' . "\n" . self::lower( self::normalize_text( (string) $title->textContent ) ) );
			if ( isset( $map[ $key ] ) ) {
				$title->nodeValue = $map[ $key ];
			} else {
				$source_hash = hash(
					'sha256',
					self::normalize_text( (string) $title->textContent )
				);
				if ( isset( $text_map[ $source_hash ] ) ) {
					$title->nodeValue = $text_map[ $source_hash ];
				}
			}
		}
		$meta_map = array(
			'description' => 'meta_description',
			'og:title' => 'meta_og_title',
			'og:description' => 'meta_og_description',
			'og:site_name' => 'meta_og_site_name',
			'twitter:title' => 'meta_twitter_title',
			'twitter:description' => 'meta_twitter_description',
		);
		foreach ( $meta_map as $name => $type ) {
			$query = 0 === strpos( $name, 'og:' ) ? '//meta[@property="' . $name . '"]' : '//meta[@name="' . $name . '"]';
			$node = $xpath->query( $query )->item( 0 );
			if ( $node instanceof DOMElement ) {
				$key = hash( 'sha256', $type . "\n" . self::lower( self::normalize_text( $node->getAttribute( 'content' ) ) ) );
				if ( isset( $map[ $key ] ) ) {
					$node->setAttribute( 'content', $map[ $key ] );
				} else {
					$source_hash = hash( 'sha256', self::normalize_text( $node->getAttribute( 'content' ) ) );
					if ( isset( $text_map[ $source_hash ] ) ) {
						$node->setAttribute( 'content', $text_map[ $source_hash ] );
					}
				}
			}
		}



		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( $body ) {
			$text_nodes = $xpath->query( './/text()[normalize-space(.) != ""]', $body );
			foreach ( $text_nodes as $node ) {
				$parent = $node->parentNode;
				if ( ! $parent instanceof DOMElement || self::excluded_element( $parent ) ) {
					continue;
				}
				$text = self::normalize_text( (string) $node->nodeValue );
				$type = self::segment_type_for_element( $parent );
				$key = hash( 'sha256', $type . "\n" . self::lower( $text ) );
				if ( isset( $map[ $key ] ) ) {
					$node->nodeValue = $map[ $key ];
				} else {
					$source_hash = hash( 'sha256', $text );
					if ( isset( $text_map[ $source_hash ] ) ) {
						$node->nodeValue = $text_map[ $source_hash ];
					}
				}
			}
			foreach ( array( 'alt' => 'attribute_alt', 'title' => 'attribute_title', 'placeholder' => 'attribute_placeholder', 'aria-label' => 'attribute_aria_label' ) as $attribute => $type ) {
				$nodes = $xpath->query( './/*[@' . $attribute . ']', $body );
				foreach ( $nodes as $element ) {
					if ( ! $element instanceof DOMElement || self::excluded_element( $element ) ) {
						continue;
					}
					$text = self::normalize_text( $element->getAttribute( $attribute ) );
					$key = hash( 'sha256', $type . "\n" . self::lower( $text ) );
					if ( isset( $map[ $key ] ) ) {
						$element->setAttribute( $attribute, $map[ $key ] );
					} else {
						$source_hash = hash( 'sha256', $text );
						if ( isset( $text_map[ $source_hash ] ) ) {
							$element->setAttribute( $attribute, $text_map[ $source_hash ] );
						}
					}
				}
			}
		}

		$residual_summary = self::residual_dom_translation(
			$dom,
			$xpath,
			$source,
			$slug,
			$allow_source_fallback
		);

		if ( $allow_source_fallback ) {
			self::neutralize_provisional_seo_dom( $dom, $xpath );
		}


		$html_element = $dom->getElementsByTagName( 'html' )->item( 0 );
		if ( $html_element instanceof DOMElement ) {
			$html_element->setAttribute( 'lang', (string) $language['code'] );
			$html_element->setAttribute( 'dir', (string) $language['dir'] );
		}
		$og_locale = $xpath->query( '//meta[@property="og:locale"]' )->item( 0 );
		if ( $og_locale instanceof DOMElement ) {
			$og_locale->setAttribute( 'content', (string) ( $language['og_locale'] ?? $language['code'] ) );
		}
		self::rewrite_json_ld( $dom, $xpath, (string) $source['source_url'], self::language_url( (string) $source['source_url'], $slug ), (string) $language['code'] );
		self::remove_translatepress_switchers( $xpath );
		self::remove_existing_uafree_switchers( $xpath );
		self::replace_seo_links( $dom, $xpath, $source, $slug );
		$localized_route_map = self::rewrite_internal_links(
			$dom,
			$source,
			$slug
		);

		$has_protected_gallery = ! empty( $protected_galleries );
		$has_gallery_fallback = false;

		if ( ! $has_protected_gallery ) {
			$has_gallery_fallback = self::repair_gallery_media_urls(
				$dom,
				$xpath,
				(string) $source['source_url']
			);
		}

		self::inject_language_switcher( $dom, $source, $slug );
		$output = $dom->saveHTML();
		$output = preg_replace( '/^<\?xml[^>]+>\s*/', '', (string) $output );

		if (
			! empty( $residual_summary['memory'] )
			|| ! empty( $residual_summary['translated'] )
		) {
			$output = str_replace(
				'</body>',
				'<!-- KOZSTX residual: memory='
					. (int) $residual_summary['memory']
					. '; azure='
					. (int) $residual_summary['translated']
					. '; invalid='
					. (int) $residual_summary['invalid']
					. ' --></body>',
				(string) $output
			);
		}

		if ( $has_protected_gallery ) {
			$output = self::restore_gallery_blocks(
				(string) $output,
				$protected_galleries
			);
		} elseif ( $has_gallery_fallback ) {
			$output = self::inject_gallery_compatibility_script(
				(string) $output,
				(string) $source['source_url']
			);
		}

		if ( ! empty( $protected_scripts ) ) {
			$output = self::restore_executable_scripts(
				(string) $output,
				$protected_scripts
			);
		}

		if ( $allow_source_fallback ) {
			$output = self::neutralize_provisional_structured_data_html( (string) $output );
		}

		$output = self::inject_language_link_guard(
			(string) $output,
			$slug,
			$localized_route_map
		);

		if ( ! self::migration_freeze_active() && ! empty( self::settings()['dynamic_content_enabled'] ) ) {
			$output = self::inject_dynamic_content_translator(
				(string) $output,
				(int) $source['id'],
				$slug
			);
		}

		return $output;
	}



	private static function inject_language_link_guard(
		string $html,
		string $language,
		array $route_map = array()
	): string {
		if ( ! isset( self::languages()[ $language ] ) ) {
			return $html;
		}

		/*
		 * PageLayer and other frontend builders can restore stored source URLs
		 * after the server-side DOM rewrite. Keep a small route map for links
		 * present on the rendered page and enforce the selected language only
		 * for those known same-site routes.
		 */
		$normalized_route_map = array();

		foreach ( $route_map as $source_path => $localized_url ) {
			$source_path = self::normalized_source_path(
				(string) $source_path
			);
			$localized_url = esc_url_raw( (string) $localized_url );

			if (
				'' === $source_path
				|| '' === $localized_url
			) {
				continue;
			}

			$normalized_route_map[ $source_path ] = $localized_url;
		}

		if ( empty( $normalized_route_map ) ) {
			return $html;
		}

		$config = array(
			'language' => $language,
			'languageSlugs' => array_keys( self::languages() ),
			'routeMap' => $normalized_route_map,
			'version' => KOZSTX_VERSION,
		);

		$config_json = wp_json_encode(
			$config,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		$script = <<<'JS'
<script id="kozstx-language-link-guard">
(function(){
	'use strict';

	var CONFIG = __CONFIG__;
	var ATTRIBUTE_NAMES = [
		'href',
		'action',
		'data-href',
		'data-url',
		'data-link',
		'data-target-url'
	];
	var SELECTOR = [
		'a[href]',
		'area[href]',
		'form[action]',
		'[data-href]',
		'[data-url]',
		'[data-link]',
		'[data-target-url]',
		'[onclick]'
	].join(',');

	function normalizePath(pathname){
		var path = String(pathname || '/');

		try {
			path = decodeURIComponent(path);
		} catch (error) {}

		path = '/' + path.replace(/^\/+|\/+$/g, '');

		if (path === '/') {
			return '/';
		}

		var parts = path.split('/').filter(Boolean);

		if (
			parts.length > 0
			&& Array.isArray(CONFIG.languageSlugs)
			&& CONFIG.languageSlugs.indexOf(parts[0].toLowerCase()) !== -1
		) {
			parts.shift();
		}

		return '/' + parts.join('/').toLowerCase() + '/';
	}

	function localizedUrl(value){
		if (
			!value
			|| /^(?:#|mailto:|tel:|javascript:|data:|blob:)/i.test(value)
		) {
			return '';
		}

		try {
			var url = new URL(value, window.location.href);

			if (url.origin !== window.location.origin) {
				return '';
			}

			var path = normalizePath(url.pathname);
			var targetValue = CONFIG.routeMap[path];

			if (!targetValue) {
				return '';
			}

			var target = new URL(targetValue, window.location.origin);
			target.search = url.search;
			target.hash = url.hash;

			return target.href;
		} catch (error) {
			return '';
		}
	}

	function rewriteAttribute(element, attribute){
		if (
			!(element instanceof Element)
			|| !element.hasAttribute(attribute)
		) {
			return false;
		}

		var current = String(
			element.getAttribute(attribute) || ''
		);
		var target = localizedUrl(current);

		if (!target || current === target) {
			return false;
		}

		element.setAttribute(attribute, target);
		element.setAttribute(
			'data-kozstx-localized-route',
			CONFIG.language
		);
		return true;
	}

	function onclickTarget(element){
		if (
			!(element instanceof Element)
			|| !element.hasAttribute('onclick')
		) {
			return '';
		}

		var source = String(
			element.getAttribute('onclick') || ''
		);
		var candidates = source.match(
			/(?:https?:\/\/[^'"\s)]+|\/[^'"\s)]+)/gi
		) || [];

		for (var i = 0; i < candidates.length; i++) {
			var target = localizedUrl(candidates[i]);

			if (target) {
				return target;
			}
		}

		return '';
	}

	function rewriteElement(element){
		if (!(element instanceof Element)) {
			return;
		}

		ATTRIBUTE_NAMES.forEach(function(attribute){
			rewriteAttribute(element, attribute);
		});

		var onclick = onclickTarget(element);

		if (onclick) {
			element.setAttribute(
				'data-kozstx-localized-onclick',
				onclick
			);
		}
	}

	function scan(root){
		if (!root) {
			return;
		}

		if (
			root instanceof Element
			&& root.matches(SELECTOR)
		) {
			rewriteElement(root);
		}

		if (root.querySelectorAll) {
			root.querySelectorAll(SELECTOR).forEach(
				rewriteElement
			);
		}
	}

	function closestActionTarget(node){
		return node instanceof Element
			? node.closest(SELECTOR)
			: null;
	}

	function modifiedClick(event){
		return Boolean(
			event.metaKey
			|| event.ctrlKey
			|| event.shiftKey
			|| event.altKey
			|| (
				typeof event.button === 'number'
				&& event.button !== 0
			)
		);
	}

	function forceCorrectNavigation(event){
		if (
			event.type === 'click'
			&& modifiedClick(event)
		) {
			return;
		}

		var element = closestActionTarget(event.target);

		if (!element) {
			return;
		}

		var target = '';

		for (var i = 0; i < ATTRIBUTE_NAMES.length; i++) {
			var attribute = ATTRIBUTE_NAMES[i];

			if (element.hasAttribute(attribute)) {
				target = localizedUrl(
					String(
						element.getAttribute(attribute) || ''
					)
				);

				if (target) {
					element.setAttribute(attribute, target);
					break;
				}
			}
		}

		if (!target) {
			target = String(
				element.getAttribute(
					'data-kozstx-localized-onclick'
				) || ''
			);
		}

		if (!target) {
			return;
		}

		/*
		 * Own ordinary navigation in capture phase because PageLayer can keep
		 * an older source-language URL in a later click handler.
		 */
		event.preventDefault();
		event.stopPropagation();

		if (
			typeof event.stopImmediatePropagation === 'function'
		) {
			event.stopImmediatePropagation();
		}

		window.location.assign(target);
	}

	function start(){
		scan(document.documentElement);

		[100, 400, 1000, 2500, 5000].forEach(
			function(delay){
				window.setTimeout(function(){
					scan(document.documentElement);
				}, delay);
			}
		);

		document.addEventListener(
			'click',
			forceCorrectNavigation,
			true
		);
		document.addEventListener(
			'submit',
			forceCorrectNavigation,
			true
		);

		if ('MutationObserver' in window) {
			new MutationObserver(function(mutations){
				mutations.forEach(function(mutation){
					if (
						mutation.type === 'attributes'
						&& mutation.target instanceof Element
					) {
						rewriteElement(mutation.target);
						return;
					}

					mutation.addedNodes.forEach(function(node){
						if (node instanceof Element) {
							scan(node);
						}
					});
				});
			}).observe(document.documentElement, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ATTRIBUTE_NAMES.concat(
					['onclick']
				)
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener(
			'DOMContentLoaded',
			start,
			{once:true}
		);
	} else {
		start();
	}
})();
</script>
JS;

		$script = str_replace(
			'__CONFIG__',
			(string) $config_json,
			$script
		);

		if ( false !== stripos( $html, '</body>' ) ) {
			return (string) preg_replace(
				'/<\/body>/i',
				$script . '</body>',
				$html,
				1
			);
		}

		return $html . $script;
	}




	public static function register_dynamic_rest_route(): void {
		register_rest_route(
			self::DYNAMIC_REST_NAMESPACE,
			self::DYNAMIC_REST_ROUTE,
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( __CLASS__, 'dynamic_translate_request' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function dynamic_translate_request(
		WP_REST_Request $request
	): WP_REST_Response {
		global $wpdb;
		$tables = self::tables();
		$settings = self::settings();

		if ( self::migration_freeze_active() ) {
			return new WP_REST_Response(
				array(
					'ok' => false,
					'message' => 'Dynamic translation is paused during migration validation.',
				),
				423
			);
		}

		if (
			empty( $settings['dynamic_content_enabled'] )
			|| empty( $settings['routes_enabled'] )
		) {
			return new WP_REST_Response(
				array(
					'ok' => false,
					'message' => 'Dynamic translation is disabled.',
				),
				403
			);
		}

		$source_id = absint( $request->get_param( 'source_id' ) );
		$language = sanitize_key(
			(string) $request->get_param( 'language' )
		);
		$token = sanitize_text_field(
			(string) $request->get_param( 'token' )
		);
		$texts = $request->get_param( 'texts' );

		if (
			$source_id <= 0
			|| ! isset( self::languages()[ $language ] )
			|| ! self::valid_dynamic_token( $source_id, $language, $token )
			|| ! is_array( self::source_row( $source_id ) )
		) {
			return new WP_REST_Response(
				array(
					'ok' => false,
					'message' => 'Invalid dynamic translation request.',
				),
				403
			);
		}

		if ( ! is_array( $texts ) ) {
			return new WP_REST_Response(
				array(
					'ok' => false,
					'message' => 'Texts must be an array.',
				),
				400
			);
		}

		$texts = array_slice( $texts, 0, 100 );
		$unique = array();
		$total_chars = 0;

		foreach ( $texts as $text ) {
			$text = self::normalize_text(
				wp_strip_all_tags( (string) $text )
			);

			if (
				! self::is_dynamic_source_text( $text )
				|| self::string_length( $text ) > 30000
			) {
				continue;
			}

			$hash = hash( 'sha256', $text );

			if ( isset( $unique[ $hash ] ) ) {
				continue;
			}

			$length = self::string_length( $text );

			if ( $total_chars + $length > 30000 ) {
				break;
			}

			$unique[ $hash ] = $text;
			$total_chars += $length;
		}

		if ( empty( $unique ) ) {
			return new WP_REST_Response(
				array(
					'ok' => true,
					'translations' => array(),
					'cached' => 0,
					'translated' => 0,
				),
				200
			);
		}

		$rate_key = 'kozstx_dyn_rate_' . substr(
			hash( 'sha256', $token ),
			0,
			32
		);
		$rate = (int) get_transient( $rate_key );

		if ( $rate >= 6 ) {
			return new WP_REST_Response(
				array(
					'ok' => false,
					'message' => 'Dynamic translation rate limit reached.',
				),
				429
			);
		}

		set_transient( $rate_key, $rate + 1, HOUR_IN_SECONDS );

		$translations = array();
		$missing = array();
		$cached = 0;

		foreach ( $unique as $hash => $text ) {
			$value = self::memory_translation( $language, $hash );

			if ( '' !== $value ) {
				self::store_translations_for_source_hash(
					$source_id,
					$language,
					$hash,
					$value
				);

				$translations[] = array(
					'source' => $text,
					'translated' => $value,
				);
				$cached++;
			} else {
				$missing[ $hash ] = $text;
			}
		}

		$deferred = array();

		if ( ! empty( $missing ) ) {
			$static_hashes = array_flip(
				array_map(
					'strval',
					(array) $wpdb->get_col(
						$wpdb->prepare(
							"SELECT DISTINCT source_hash
							FROM {$tables['segments']}
							WHERE source_id = %d
								AND is_protected = 0",
							$source_id
						)
					)
				)
			);

			foreach ( $missing as $hash => $text ) {
				if ( isset( $static_hashes[ $hash ] ) ) {
					$deferred[] = $text;
					unset( $missing[ $hash ] );
				}
			}
		}

		if ( empty( $missing ) ) {
			return new WP_REST_Response(
				array(
					'ok' => true,
					'translations' => $translations,
					'cached' => $cached,
					'translated' => 0,
					'deferred' => $deferred,
				),
				200
			);
		}

		$usage = self::current_usage();
		$available = max(
			0,
			(int) $usage['remaining'] - (int) $settings['reserve_chars']
		);
		$missing_chars = array_sum(
			array_map(
				static fn( string $text ): int =>
					function_exists( 'mb_strlen' )
						? mb_strlen( $text, 'UTF-8' )
						: strlen( $text ),
				$missing
			)
		);

		if ( $available < $missing_chars ) {
			return new WP_REST_Response(
				array(
					'ok' => true,
					'translations' => $translations,
					'cached' => $cached,
					'translated' => 0,
					'paused' => 'monthly_limit',
				),
				200
			);
		}

		$credentials = self::credentials();

		if ( empty( $credentials['configured'] ) ) {
			return new WP_REST_Response(
				array(
					'ok' => false,
					'message' => 'Azure Translator is not configured.',
				),
				503
			);
		}

		$payload_hash = hash(
			'sha256',
			$source_id . '|' . $language . '|' . implode( '|', array_keys( $missing ) )
		);
		$lock_key = 'kozstx_dyn_lock_' . substr( $payload_hash, 0, 32 );

		if ( get_transient( $lock_key ) ) {
			return new WP_REST_Response(
				array(
					'ok' => true,
					'translations' => $translations,
					'cached' => $cached,
					'translated' => 0,
					'pending' => true,
				),
				202
			);
		}

		set_transient( $lock_key, 1, 90 );

		$segments = array();

		foreach ( $missing as $hash => $text ) {
			$segments[] = array(
				'source_hash' => $hash,
				'source_text' => $text,
			);
		}

		$result = self::azure_translate_batch(
			$segments,
			(string) self::languages()[ $language ]['code'],
			$credentials
		);

		delete_transient( $lock_key );

		if ( is_wp_error( $result ) ) {
			if ( 'azure_rate_limit' === (string) $result->get_error_code() ) {
				$data = $result->get_error_data();
				$retry_after = is_array( $data )
					? (int) ( $data['retry_after'] ?? 0 )
					: 0;

				self::pause_until(
					'azure_rate_limit',
					time() + ( $retry_after > 0 ? $retry_after : HOUR_IN_SECONDS ),
					$result->get_error_message()
				);
			}

			self::log_event(
				0,
				$source_id,
				$language,
				'error',
				'dynamic_translation_error',
				$missing_chars,
				$result->get_error_message()
			);

			return new WP_REST_Response(
				array(
					'ok' => false,
					'message' => $result->get_error_message(),
					'translations' => $translations,
				),
				503
			);
		}

		$translated_count = 0;
		$values = (array) ( $result['translations'] ?? array() );
		$hashes = array_keys( $missing );
		$source_values = array_values( $missing );

		foreach ( $values as $index => $translated ) {
			if ( ! isset( $source_values[ $index ], $hashes[ $index ] ) ) {
				continue;
			}

			$source_text = (string) $source_values[ $index ];
			$source_hash = (string) $hashes[ $index ];
			$translated = self::clean_api_translation(
				(string) $translated
			);

			$flags = self::validate_translation(
				$source_text,
				$translated,
				$language
			);

			if ( ! empty( $flags ) ) {
				self::log_event(
					0,
					$source_id,
					$language,
					'warning',
					'dynamic_translation_rejected',
					self::string_length( $source_text ),
					implode( ',', $flags )
				);
				continue;
			}

			self::store_memory(
				$language,
				$source_hash,
				$source_text,
				$translated
			);

			self::store_translations_for_source_hash(
				$source_id,
				$language,
				$source_hash,
				$translated
			);

			$translations[] = array(
				'source' => $source_text,
				'translated' => $translated,
			);
			$translated_count++;
		}

		self::increment_usage(
			$language,
			(int) ( $result['source_characters'] ?? $missing_chars )
		);

		$job_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$tables['queue']}
				WHERE source_id = %d AND language = %s",
				$source_id,
				$language
			)
		);

		if ( $job_id > 0 ) {
			$progress = self::refresh_job_progress(
				$job_id,
				$source_id,
				$language
			);

			if ( ! empty( $progress['done'] ) ) {
				self::source_translation_completed(
					$source_id,
					$language
				);
			}
		}

		self::log_event(
			0,
			$source_id,
			$language,
			'info',
			'dynamic_translation',
			(int) ( $result['source_characters'] ?? $missing_chars ),
			sprintf(
				'Динамічно перекладено %d із %d рядків.',
				$translated_count,
				count( $missing )
			)
		);

		return new WP_REST_Response(
			array(
				'ok' => true,
				'translations' => $translations,
				'cached' => $cached,
				'translated' => $translated_count,
				'deferred' => $deferred,
			),
			200
		);
	}

	private static function is_dynamic_source_text( string $text ): bool {
		if ( ! self::is_translatable_text( $text ) ) {
			return false;
		}

		if ( self::DEFAULT_SOURCE_LANGUAGE === self::source_language_slug() ) {
			return (bool) preg_match(
				'/[\x{0400}-\x{04FF}]/u',
				$text
			);
		}

		return true;
	}

	private static function dynamic_token(
		int $source_id,
		string $language,
		?string $date = null
	): string {
		$date = null === $date ? gmdate( 'Y-m-d' ) : $date;

		return hash_hmac(
			'sha256',
			$source_id . '|' . $language . '|' . $date,
			wp_salt( 'nonce' )
		);
	}

	private static function valid_dynamic_token(
		int $source_id,
		string $language,
		string $token
	): bool {
		if ( '' === $token ) {
			return false;
		}

		$today = self::dynamic_token( $source_id, $language );
		$yesterday = self::dynamic_token(
			$source_id,
			$language,
			gmdate( 'Y-m-d', time() - DAY_IN_SECONDS )
		);

		return hash_equals( $today, $token )
			|| hash_equals( $yesterday, $token );
	}


	private static function dynamic_translation_dictionary(
		int $source_id,
		string $language
	): array {
		global $wpdb;
		$tables = self::tables();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					s.source_text,
					COALESCE(
						NULLIF(t.translated_text, ''),
						NULLIF(m.translated_text, '')
					) AS translated_text
				FROM {$tables['segments']} s
				LEFT JOIN {$tables['translations']} t
					ON t.source_id = s.source_id
					AND t.language = %s
					AND t.segment_key = s.segment_key
					AND t.status = 'ready'
					AND t.source_hash = s.source_hash
				LEFT JOIN {$tables['memory']} m
					ON m.language = %s
					AND m.source_hash = s.source_hash
				WHERE s.source_id = %d
					AND s.is_protected = 0",
				$language,
				$language,
				$source_id
			),
			ARRAY_A
		);

		$dictionary = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$source = self::normalize_text(
				(string) $row['source_text']
			);
			$translated = self::normalize_text(
				(string) $row['translated_text']
			);

			if (
				'' !== $source
				&& '' !== $translated
				&& $source !== $translated
			) {
				$dictionary[ $source ] = $translated;
			}
		}

		return $dictionary;
	}


	private static function inject_dynamic_content_translator(
		string $html,
		int $source_id,
		string $language
	): string {
		$config = array(
			'endpoint' => esc_url_raw(
				rest_url(
					self::DYNAMIC_REST_NAMESPACE .
					self::DYNAMIC_REST_ROUTE
				)
			),
			'sourceId' => $source_id,
			'language' => $language,
			'sourceLanguage' => self::source_language_slug(),
			'token' => self::dynamic_token( $source_id, $language ),
			'maxBatch' => 100,
			'dictionary' => self::dynamic_translation_dictionary(
				$source_id,
				$language
			),
			'version' => KOZSTX_VERSION,
		);

		$config_json = wp_json_encode(
			$config,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		$script = <<<'JS'
<script id="kozstx-dynamic-translator">
(function(){
	'use strict';

	var CONFIG = __CONFIG__;
	var pending = new Map();
	var seen = new Set();
	var attempts = new Map();
	var chartsToRefresh = new Set();
	var chartSources = new WeakMap();
	var busy = false;
	var timer = 0;

	var EXCLUDED_SELECTOR = [
		'script',
		'style',
		'noscript',
		'template',
		'canvas',
		'code',
		'pre',
		'textarea',
		'input',
		'select',
		'[contenteditable="true"]',
		'.kozstx-language-switcher',
		'[data-kozstx-gallery-compat]',
		'.pgc-sgb-cb',
		'[class*="wp-block-pgcsimplygalleryblock"]',
		'.simply-gallery-amp',
		'.sgb-gallery'
	].join(',');

	function normalize(value){
		return String(value || '')
			.replace(/[\u00A0\u200B\u200C\u200D\u2060\uFEFF]/g, ' ')
			.replace(/\s+/g, ' ')
			.trim();
	}

	function containsSourceScript(value){
		if (CONFIG.sourceLanguage === 'uk') {
			return /[\u0400-\u04FF]/.test(value);
		}

		return /[A-Za-z\u00C0-\u024F\u0370-\u03FF\u0400-\u04FF\u0600-\u06FF\u0900-\u097F\u3040-\u30FF\u3400-\u9FFF]/.test(value);
	}

	function eligible(value){
		value = normalize(value);

		if (value.length < 2 || value.length > 1000) {
			return false;
		}

		if (!containsSourceScript(value)) {
			return false;
		}

		if (/^(?:https?:\/\/|www\.|mailto:|tel:)/i.test(value)) {
			return false;
		}

		if (/^[\d\s.,:+\-–—\/()%$€₴₿]+$/.test(value)) {
			return false;
		}

		return true;
	}

	function excluded(element){
		return element instanceof Element && Boolean(element.closest(EXCLUDED_SELECTOR));
	}

	function addTarget(source, apply){
		source = normalize(source);

		if (!eligible(source)) {
			return;
		}

		if (
			CONFIG.dictionary
			&& typeof CONFIG.dictionary[source] === 'string'
			&& normalize(CONFIG.dictionary[source])
		) {
			try {
				apply(normalize(CONFIG.dictionary[source]));
			} catch (error) {}
			return;
		}

		if (!pending.has(source)) {
			pending.set(source, []);
		}

		pending.get(source).push(apply);

		if (!seen.has(source)) {
			seen.add(source);
		}

		schedule();
	}

	function scanText(root){
		if (!root) {
			return;
		}

		var walker = document.createTreeWalker(
			root,
			NodeFilter.SHOW_TEXT,
			{
				acceptNode: function(node){
					var parent = node.parentElement;

					if (!parent || excluded(parent)) {
						return NodeFilter.FILTER_REJECT;
					}

					return eligible(node.nodeValue)
						? NodeFilter.FILTER_ACCEPT
						: NodeFilter.FILTER_REJECT;
				}
			}
		);

		var nodes = [];
		var node;

		while ((node = walker.nextNode())) {
			nodes.push(node);
		}

		nodes.forEach(function(textNode){
			var raw = String(textNode.nodeValue || '');
			var source = normalize(raw);
			var prefix = (raw.match(/^\s*/) || [''])[0];
			var suffix = (raw.match(/\s*$/) || [''])[0];

			addTarget(source, function(translated){
				if (textNode.isConnected && normalize(textNode.nodeValue) === source) {
					textNode.nodeValue = prefix + translated + suffix;
				}
			});
		});
	}

	function scanAttributes(root){
		if (!(root instanceof Element) && root !== document) {
			return;
		}

		var elements = [];

		if (root instanceof Element) {
			elements.push(root);
		}

		if (root.querySelectorAll) {
			root.querySelectorAll('[title],[aria-label],[placeholder]').forEach(
				function(element){
					elements.push(element);
				}
			);
		}

		elements.forEach(function(element){
			if (excluded(element)) {
				return;
			}

			['title','aria-label','placeholder'].forEach(function(attribute){
				if (!element.hasAttribute(attribute)) {
					return;
				}

				var source = normalize(element.getAttribute(attribute));

				addTarget(source, function(translated){
					if (
						element.isConnected &&
						normalize(element.getAttribute(attribute)) === source
					) {
						element.setAttribute(attribute, translated);
					}
				});
			});
		});
	}

	function chartInstances(){
		var charts = [];

		if (!window.Chart) {
			return charts;
		}

		try {
			if (window.Chart.instances) {
				Object.keys(window.Chart.instances).forEach(function(key){
					var chart = window.Chart.instances[key];

					if (chart && charts.indexOf(chart) === -1) {
						charts.push(chart);
					}
				});
			}
		} catch (error) {}

		if (typeof window.Chart.getChart === 'function') {
			document.querySelectorAll('canvas').forEach(function(canvas){
				try {
					var chart = window.Chart.getChart(canvas);

					if (chart && charts.indexOf(chart) === -1) {
						charts.push(chart);
					}
				} catch (error) {}
			});
		}

		return charts;
	}

	function rememberChartSource(chart, key, value){
		if (!chartSources.has(chart)) {
			chartSources.set(chart, new Map());
		}

		var map = chartSources.get(chart);

		if (!map.has(key)) {
			map.set(key, value);
		}

		return map.get(key);
	}

	function addChartTarget(chart, key, source, setter){
		source = rememberChartSource(chart, key, normalize(source));

		addTarget(source, function(translated){
			try {
				setter(translated);
				chartsToRefresh.add(chart);
			} catch (error) {}
		});
	}

	function scanCharts(){
		chartInstances().forEach(function(chart){
			if (!chart || !chart.data) {
				return;
			}

			if (Array.isArray(chart.data.labels)) {
				chart.data.labels.forEach(function(label, index){
					if (typeof label !== 'string') {
						return;
					}

					addChartTarget(
						chart,
						'label:' + index,
						label,
						function(translated){
							chart.data.labels[index] = translated;
						}
					);
				});
			}

			if (Array.isArray(chart.data.datasets)) {
				chart.data.datasets.forEach(function(dataset, datasetIndex){
					if (!dataset || typeof dataset.label !== 'string') {
						return;
					}

					addChartTarget(
						chart,
						'dataset:' + datasetIndex,
						dataset.label,
						function(translated){
							dataset.label = translated;
						}
					);
				});
			}

			var options = chart.options || {};
			var plugins = options.plugins || {};

			['title', 'subtitle'].forEach(function(name){
				var block = plugins[name];

				if (!block || typeof block.text !== 'string') {
					return;
				}

				addChartTarget(
					chart,
					'plugin:' + name,
					block.text,
					function(translated){
						block.text = translated;
					}
				);
			});

			var scales = options.scales || {};

			Object.keys(scales).forEach(function(scaleName){
				var scale = scales[scaleName];

				if (
					!scale
					|| !scale.title
					|| typeof scale.title.text !== 'string'
				) {
					return;
				}

				addChartTarget(
					chart,
					'scale:' + scaleName,
					scale.title.text,
					function(translated){
						scale.title.text = translated;
					}
				);
			});
		});
	}

	function refreshCharts(){
		if (chartsToRefresh.size === 0) {
			return;
		}

		var charts = Array.from(chartsToRefresh);
		chartsToRefresh.clear();

		window.requestAnimationFrame(function(){
			charts.forEach(function(chart){
				if (!chart || chart._destroyed) {
					return;
				}

				try {
					chart.update('none');
					return;
				} catch (error) {}

				try {
					chart.update(0);
					return;
				} catch (error) {}

				try {
					chart.render();
				} catch (error) {}
			});
		});
	}

	function scan(root){
		scanText(root);
		scanAttributes(root);
	}

	function schedule(){
		clearTimeout(timer);
		timer = window.setTimeout(flush, 180);
	}

	function applyTranslations(items){
		var completed = new Set();

		if (!Array.isArray(items)) {
			return completed;
		}

		items.forEach(function(item){
			var source = normalize(item && item.source);
			var translated = normalize(item && item.translated);

			if (!source || !translated || !pending.has(source)) {
				return;
			}

			var targets = pending.get(source) || [];
			pending.delete(source);
			attempts.delete(source);
			completed.add(source);

			targets.forEach(function(apply){
				try {
					apply(translated);
				} catch (error) {}
			});
		});

		refreshCharts();

		try {
			window.dispatchEvent(
				new CustomEvent('kozstx:dynamic-translated')
			);
		} catch (error) {}

		return completed;
	}

	function flush(){
		if (busy || pending.size === 0) {
			return;
		}

		var texts = Array.from(pending.keys()).slice(0, Number(CONFIG.maxBatch || 50));

		if (texts.length === 0) {
			return;
		}

		busy = true;

		fetch(CONFIG.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({
				source_id: CONFIG.sourceId,
				language: CONFIG.language,
				token: CONFIG.token,
				texts: texts
			})
		})
		.then(function(response){
			return response.json();
		})
		.then(function(payload){
			var completed = applyTranslations(
				payload && payload.translations
			);
			var deferred = new Set(
				Array.isArray(payload && payload.deferred)
					? payload.deferred.map(normalize)
					: []
			);

			deferred.forEach(function(text){
				if (pending.has(text)) {
					pending.delete(text);
					attempts.delete(text);
				}
			});

			texts.forEach(function(text){
				if (completed.has(text) || !pending.has(text)) {
					return;
				}

				var count = Number(attempts.get(text) || 0) + 1;
				attempts.set(text, count);

				if (count >= 10) {
					pending.delete(text);
					attempts.delete(text);
				}
			});
		})
		.catch(function(){
			texts.forEach(function(text){
				if (!pending.has(text)) {
					return;
				}

				var count = Number(attempts.get(text) || 0) + 1;
				attempts.set(text, count);

				if (count >= 10) {
					pending.delete(text);
					attempts.delete(text);
				}
			});
		})
		.finally(function(){
			busy = false;

			if (pending.size > 0) {
				window.setTimeout(schedule, 700);
			}
		});
	}

	function start(){
		scan(document.body || document.documentElement);

		[
			100,
			300,
			700,
			1200,
			2500,
			5000,
			8000,
			12000,
			20000,
			30000
		].forEach(function(delay){
			window.setTimeout(function(){
				scan(document.body || document.documentElement);
				scanCharts();
			}, delay);
		});

		window.addEventListener('load', function(){
			window.setTimeout(function(){
				scan(document.body || document.documentElement);
				scanCharts();
			}, 250);
		}, {once:true});

		if ('MutationObserver' in window) {
			new MutationObserver(function(mutations){
				mutations.forEach(function(mutation){
					if (mutation.type === 'characterData') {
						scanText(mutation.target.parentNode);
						return;
					}

					mutation.addedNodes.forEach(function(node){
						if (node.nodeType === Node.TEXT_NODE) {
							scanText(node.parentNode);
						} else if (node instanceof Element) {
							scan(node);
						}
					});
				});
			}).observe(document.documentElement, {
				childList: true,
				subtree: true,
				characterData: true
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, {once:true});
	} else {
		start();
	}
})();
</script>
JS;

		$script = str_replace(
			'__CONFIG__',
			(string) $config_json,
			$script
		);

		if ( false !== stripos( $html, '</body>' ) ) {
			return (string) preg_replace(
				'/<\/body>/i',
				$script . '</body>',
				$html,
				1
			);
		}

		return $html . $script;
	}



	private static function protect_executable_scripts(
		string $html,
		array &$protected
	): string {
		$protected = array();
		$index = 0;

		return (string) preg_replace_callback(
			'~<script\b[^>]*>.*?</script\s*>~is',
			static function ( array $match ) use ( &$protected, &$index ): string {
				$block = (string) $match[0];
				$token = hash(
					'sha256',
					(string) $index . "\n" . $block
				);

				$protected[ $token ] = $block;
				$index++;

				return '<script type="text/plain" data-kozstx-script-placeholder="' .
					esc_attr( $token ) .
					'"></script>';
			},
			$html
		);
	}

	private static function restore_executable_scripts(
		string $html,
		array $protected
	): string {
		foreach ( $protected as $token => $block ) {
			$pattern = '~<script\b[^>]*data-kozstx-script-placeholder=(["\'])'
				. preg_quote( (string) $token, '~' )
				. '\1[^>]*>\s*</script>~i';

			$html = (string) preg_replace_callback(
				$pattern,
				static fn( array $match ): string => (string) $block,
				$html,
				1
			);
		}

		/*
		 * Never expose an internal placeholder if a third-party HTML filter
		 * unexpectedly altered it.
		 */
		return (string) preg_replace(
			'~<script\b[^>]*data-kozstx-script-placeholder=(["\'])[^"\']+\1[^>]*>\s*</script>~i',
			'',
			$html
		);
	}


	private static function protect_gallery_blocks(
		string $html,
		array &$protected
	): string {
		$protected = array();
		$offset = 0;
		$index = 0;

		/*
		 * Match the outer SimpLy Gallery block. The `pgc-sgb-cb` class is the
		 * stable wrapper used by the plugin; the other names cover saved/older
		 * block variants.
		 */
		$opening_pattern = '~<([a-z][a-z0-9:-]*)\b(?=[^>]*(?:'
			. 'class\s*=\s*(["\'])[^"\']*(?:pgc-sgb-cb|wp-block-pgcsimplygalleryblock|simply-gallery-amp|sgb-gallery)[^"\']*\2'
			. '|data-gallery-id\s*='
			. '))[^>]*>~is';

		while (
			preg_match(
				$opening_pattern,
				$html,
				$match,
				PREG_OFFSET_CAPTURE,
				$offset
			)
		) {
			$opening = (string) $match[0][0];
			$start = (int) $match[0][1];
			$tag = strtolower( (string) $match[1][0] );
			$opening_end = $start + strlen( $opening );

			if ( preg_match( '~/\s*>$~', $opening ) ) {
				$end = $opening_end;
			} else {
				$end = self::matching_html_element_end(
					$html,
					$tag,
					$opening_end
				);
			}

			if ( null === $end || $end <= $start ) {
				$offset = $opening_end;
				continue;
			}

			$block = substr( $html, $start, $end - $start );
			$token = hash(
				'sha256',
				(string) $index . "\n" . $block
			);
			$placeholder = '<div data-kozstx-gallery-placeholder="' .
				esc_attr( $token ) .
				'"></div>';

			$protected[ $token ] = $block;
			$html = substr_replace(
				$html,
				$placeholder,
				$start,
				$end - $start
			);

			$offset = $start + strlen( $placeholder );
			$index++;
		}

		return $html;
	}

	private static function matching_html_element_end(
		string $html,
		string $tag,
		int $offset
	): ?int {
		$pattern = '~</?' . preg_quote( $tag, '~' ) . '\b[^>]*>~is';
		$depth = 1;
		$cursor = $offset;
		$length = strlen( $html );

		while ( $cursor < $length ) {
			if (
				! preg_match(
					$pattern,
					$html,
					$match,
					PREG_OFFSET_CAPTURE,
					$cursor
				)
			) {
				return null;
			}

			$token = (string) $match[0][0];
			$position = (int) $match[0][1];
			$cursor = $position + strlen( $token );

			if ( 0 === strpos( $token, '</' ) ) {
				$depth--;

				if ( 0 === $depth ) {
					return $cursor;
				}
			} elseif ( ! preg_match( '~/\s*>$~', $token ) ) {
				$depth++;
			}
		}

		return null;
	}

	private static function restore_gallery_blocks(
		string $html,
		array $protected
	): string {
		foreach ( $protected as $token => $block ) {
			$pattern = '~<div\b[^>]*data-kozstx-gallery-placeholder=(["\'])'
				. preg_quote( (string) $token, '~' )
				. '\1[^>]*>\s*</div>~i';

			$html = (string) preg_replace_callback(
				$pattern,
				static fn( array $match ): string => (string) $block,
				$html,
				1
			);
		}

		/*
		 * DOMDocument may normalize attributes, but the placeholder token remains
		 * stable. Any unreplaced marker means the wrapper was unexpectedly
		 * rewritten; remove it so a technical marker never reaches visitors.
		 */
		$html = (string) preg_replace(
			'~<div\b[^>]*data-kozstx-gallery-placeholder=(["\'])[^"\']+\1[^>]*>\s*</div>~i',
			'',
			$html
		);

		return $html;
	}


	private static function repair_gallery_media_urls(
		DOMDocument $dom,
		DOMXPath $xpath,
		string $source_url
	): bool {
		$gallery_query = '//*[contains(concat(" ", normalize-space(@class), " "), " pgc-sgb-cb ")'
			. ' or contains(@class, "wp-block-pgcsimplygalleryblock")'
			. ' or contains(concat(" ", normalize-space(@class), " "), " simply-gallery-amp ")'
			. ' or contains(concat(" ", normalize-space(@class), " "), " sgb-gallery ")]';

		$galleries = $xpath->query( $gallery_query );

		if ( ! $galleries instanceof DOMNodeList || 0 === $galleries->length ) {
			return false;
		}

		$single_url_attributes = array(
			'src',
			'poster',
			'data-src',
			'data-lazy-src',
			'data-original',
			'data-image',
			'data-thumb',
			'data-thumbnail',
			'data-full',
			'data-large',
			'data-small',
			'data-poster',
			'data-background',
			'data-bg',
		);

		$srcset_attributes = array(
			'srcset',
			'data-srcset',
			'data-lazy-srcset',
		);

		foreach ( $galleries as $gallery ) {
			if ( ! $gallery instanceof DOMElement ) {
				continue;
			}

			$gallery->setAttribute( 'data-kozstx-gallery-compat', '1' );

			$elements = $xpath->query( './/*', $gallery );

			foreach ( $elements as $element ) {
				if ( ! $element instanceof DOMElement ) {
					continue;
				}

				foreach ( $single_url_attributes as $attribute ) {
					if ( ! $element->hasAttribute( $attribute ) ) {
						continue;
					}

					$value = trim( $element->getAttribute( $attribute ) );

					if ( '' === $value ) {
						continue;
					}

					$element->setAttribute(
						$attribute,
						self::absolute_media_url( $value, $source_url )
					);
				}

				foreach ( $srcset_attributes as $attribute ) {
					if ( ! $element->hasAttribute( $attribute ) ) {
						continue;
					}

					$value = trim( $element->getAttribute( $attribute ) );

					if ( '' === $value ) {
						continue;
					}

					$element->setAttribute(
						$attribute,
						self::absolute_srcset( $value, $source_url )
					);
				}

				if ( $element->hasAttribute( 'style' ) ) {
					$element->setAttribute(
						'style',
						self::absolute_css_urls(
							$element->getAttribute( 'style' ),
							$source_url
						)
					);
				}

				/*
				 * SimpLy Gallery sometimes stores attachment or full-size media
				 * links in anchors. Resolve only links inside gallery containers,
				 * leaving normal translated navigation to the existing link rewriter.
				 */
				if (
					'a' === strtolower( $element->tagName )
					&& $element->hasAttribute( 'href' )
				) {
					$href = trim( $element->getAttribute( 'href' ) );

					if (
						'' !== $href
						&& ! preg_match(
							'/^(?:#|mailto:|tel:|javascript:|data:|blob:)/i',
							$href
						)
					) {
						$element->setAttribute(
							'href',
							self::absolute_media_url( $href, $source_url )
						);
					}
				}
			}
		}

		return true;
	}

	private static function absolute_srcset(
		string $srcset,
		string $source_url
	): string {
		if ( 0 === stripos( trim( $srcset ), 'data:' ) ) {
			return $srcset;
		}

		$items = preg_split( '/\s*,\s*/', $srcset );
		$result = array();

		foreach ( is_array( $items ) ? $items : array() as $item ) {
			$item = trim( (string) $item );

			if ( '' === $item ) {
				continue;
			}

			$parts = preg_split( '/\s+/', $item, 2 );
			$url = self::absolute_media_url(
				(string) ( $parts[0] ?? '' ),
				$source_url
			);
			$descriptor = trim( (string) ( $parts[1] ?? '' ) );

			$result[] = '' !== $descriptor
				? $url . ' ' . $descriptor
				: $url;
		}

		return implode( ', ', $result );
	}

	private static function absolute_css_urls(
		string $style,
		string $source_url
	): string {
		return (string) preg_replace_callback(
			'/url\(\s*(["\']?)(.*?)\1\s*\)/i',
			static function ( array $match ) use ( $source_url ): string {
				$url = self::absolute_media_url(
					trim( (string) ( $match[2] ?? '' ) ),
					$source_url
				);

				return 'url("' . esc_url_raw( $url ) . '")';
			},
			$style
		);
	}

	private static function absolute_media_url(
		string $url,
		string $source_url
	): string {
		$url = trim( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		if (
			'' === $url
			|| preg_match(
				'/^(?:https?:|data:|blob:|mailto:|tel:|javascript:|#)/i',
				$url
			)
		) {
			return $url;
		}

		if ( 0 === strpos( $url, '//' ) ) {
			$scheme = (string) wp_parse_url( $source_url, PHP_URL_SCHEME );
			return ( '' !== $scheme ? $scheme : 'https' ) . ':' . $url;
		}

		$base = wp_parse_url( $source_url );

		if (
			! is_array( $base )
			|| empty( $base['host'] )
		) {
			return $url;
		}

		$scheme = (string) ( $base['scheme'] ?? 'https' );
		$origin = $scheme . '://' . (string) $base['host'];

		if ( ! empty( $base['port'] ) ) {
			$origin .= ':' . (int) $base['port'];
		}

		if ( '/' === $url[0] ) {
			return $origin . self::normalize_url_path( $url );
		}

		$base_path = (string) ( $base['path'] ?? '/' );
		$directory = trailingslashit(
			str_replace( '\\', '/', dirname( $base_path ) )
		);

		return $origin . self::normalize_url_path( $directory . $url );
	}

	private static function normalize_url_path( string $path ): string {
		$query = '';
		$fragment = '';

		$fragment_position = strpos( $path, '#' );

		if ( false !== $fragment_position ) {
			$fragment = substr( $path, $fragment_position );
			$path = substr( $path, 0, $fragment_position );
		}

		$query_position = strpos( $path, '?' );

		if ( false !== $query_position ) {
			$query = substr( $path, $query_position );
			$path = substr( $path, 0, $query_position );
		}

		$segments = array();

		foreach ( explode( '/', str_replace( '\\', '/', $path ) ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}

			if ( '..' === $segment ) {
				array_pop( $segments );
				continue;
			}

			$segments[] = $segment;
		}

		return '/' . implode( '/', $segments ) . $query . $fragment;
	}

	private static function inject_gallery_compatibility_script(
		string $html,
		string $source_url
	): string {
		$source_json = wp_json_encode(
			$source_url,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		$script = <<<'JS'
<script id="kozstx-gallery-compat">
(function(){
	'use strict';

	var SOURCE_BASE = __SOURCE_BASE__;
	var ROOT_SELECTOR = '[data-kozstx-gallery-compat="1"],.pgc-sgb-cb,[class*="wp-block-pgcsimplygalleryblock"],.simply-gallery-amp,.sgb-gallery';
	var URL_ATTRS = [
		'data-src',
		'data-lazy-src',
		'data-original',
		'data-image',
		'data-full',
		'data-large',
		'data-thumb',
		'data-thumbnail'
	];
	var SRCSET_ATTRS = ['data-srcset','data-lazy-srcset'];

	function absolute(value){
		value = String(value || '').trim();

		if (!value || /^(?:data:|blob:|mailto:|tel:|javascript:|#)/i.test(value)) {
			return value;
		}

		try {
			return new URL(value, SOURCE_BASE).href;
		} catch (error) {
			return value;
		}
	}

	function looksLikePlaceholder(value){
		value = String(value || '');

		return !value ||
			/^data:image\//i.test(value) ||
			/about:blank/i.test(value) ||
			/placeholder|preloader|transparent/i.test(value);
	}

	function candidate(img){
		for (var i = 0; i < URL_ATTRS.length; i++) {
			var value = img.getAttribute(URL_ATTRS[i]);

			if (value && !looksLikePlaceholder(value)) {
				return absolute(value);
			}
		}

		return '';
	}

	function repairImage(img, index){
		if (!(img instanceof HTMLImageElement)) {
			return;
		}

		for (var i = 0; i < SRCSET_ATTRS.length; i++) {
			var srcset = img.getAttribute(SRCSET_ATTRS[i]);

			if (srcset && !img.getAttribute('srcset')) {
				img.setAttribute('srcset', srcset);
			}
		}

		var fallback = candidate(img);
		var current = img.getAttribute('src');

		if (fallback && (looksLikePlaceholder(current) || (img.complete && img.naturalWidth === 0))) {
			img.setAttribute('src', fallback);
		}

		if (index < 12) {
			img.loading = 'eager';
		} else if (!img.loading) {
			img.loading = 'lazy';
		}

		img.decoding = 'async';

		if (!img.dataset.uafreeGalleryRepair) {
			img.dataset.uafreeGalleryRepair = '1';
			img.addEventListener('error', function(){
				var retry = candidate(img);

				if (retry && img.getAttribute('src') !== retry) {
					img.setAttribute('src', retry);
				}
			}, {passive:true});
		}
	}

	function repair(root){
		var scope = root && root.querySelectorAll ? root : document;
		var galleries = [];

		if (root instanceof Element && root.matches(ROOT_SELECTOR)) {
			galleries.push(root);
		}

		scope.querySelectorAll(ROOT_SELECTOR).forEach(function(gallery){
			galleries.push(gallery);
		});

		galleries.forEach(function(gallery){
			gallery.querySelectorAll('img').forEach(repairImage);

			gallery.querySelectorAll('source').forEach(function(source){
				SRCSET_ATTRS.forEach(function(attribute){
					var value = source.getAttribute(attribute);

					if (value && !source.getAttribute('srcset')) {
						source.setAttribute('srcset', value);
					}
				});
			});
		});
	}

	function start(){
		repair(document);

		setTimeout(function(){
			repair(document);
			window.dispatchEvent(new Event('resize'));
			window.dispatchEvent(new Event('scroll'));
		}, 800);

		setTimeout(function(){
			repair(document);
			window.dispatchEvent(new Event('resize'));
		}, 2500);

		if ('MutationObserver' in window) {
			new MutationObserver(function(mutations){
				mutations.forEach(function(mutation){
					mutation.addedNodes.forEach(function(node){
						if (node instanceof Element) {
							repair(node);
						}
					});
				});
			}).observe(document.documentElement, {
				childList: true,
				subtree: true
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, {once:true});
	} else {
		start();
	}
})();
</script>
JS;

		$script = str_replace(
			'__SOURCE_BASE__',
			(string) $source_json,
			$script
		);

		if ( false !== stripos( $html, '</body>' ) ) {
			return (string) preg_replace(
				'/<\/body>/i',
				$script . '</body>',
				$html,
				1
			);
		}

		return $html . $script;
	}

	private static function rewrite_json_ld_html(
		string $html,
		string $source_url,
		string $translated_url,
		string $language_code
	): string {
		return (string) preg_replace_callback(
			'~(<script\b(?=[^>]*\btype\s*=\s*(["\'])application/ld\+json\2)[^>]*>)(.*?)(</script\s*>)~is',
			static function ( array $match ) use ( $source_url, $translated_url, $language_code ): string {
				$data = json_decode( trim( (string) $match[3] ), true );
				if ( ! is_array( $data ) ) {
					return (string) $match[0];
				}

				$data = self::rewrite_json_ld_value(
					$data,
					$source_url,
					$translated_url,
					$language_code
				);

				$json = wp_json_encode(
					$data,
					JSON_UNESCAPED_UNICODE
						| JSON_UNESCAPED_SLASHES
						| JSON_HEX_TAG
						| JSON_HEX_AMP
						| JSON_HEX_APOS
						| JSON_HEX_QUOT
				);

				if ( ! is_string( $json ) || '' === $json ) {
					return (string) $match[0];
				}

				return (string) $match[1] . $json . (string) $match[4];
			},
			$html
		);
	}


	private static function rewrite_json_ld( DOMDocument $dom, DOMXPath $xpath, string $source_url, string $translated_url, string $language_code ): void {
		$nodes = $xpath->query( '//script[@type="application/ld+json"]' );
		foreach ( $nodes as $node ) {
			$data = json_decode( (string) $node->textContent, true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$data = self::rewrite_json_ld_value( $data, $source_url, $translated_url, $language_code );
			$node->nodeValue = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
	}

	private static function rewrite_json_ld_value( $value, string $source_url, string $translated_url, string $language_code ) {
		if ( is_array( $value ) ) {
			$result = array();
			foreach ( $value as $key => $item ) {
				if ( 'inLanguage' === (string) $key ) {
					$result[ $key ] = $language_code;
				} else {
					$result[ $key ] = self::rewrite_json_ld_value( $item, $source_url, $translated_url, $language_code );
				}
			}
			return $result;
		}
		if ( is_string( $value ) && 0 === strpos( $value, $source_url ) ) {
			return $translated_url . substr( $value, strlen( $source_url ) );
		}
		return $value;
	}


	private static function translation_text_map(
		int $source_id,
		string $language
	): array {
		global $wpdb;

		$tables = self::tables();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					s.source_hash,
					COALESCE(
						NULLIF(t.translated_text, ''),
						NULLIF(m.translated_text, ''),
						s.source_text
					) AS translated_text
				FROM {$tables['segments']} s
				LEFT JOIN {$tables['translations']} t
					ON t.source_id = s.source_id
					AND t.language = %s
					AND t.segment_key = s.segment_key
					AND t.source_hash = s.source_hash
					AND t.status = 'ready'
				LEFT JOIN {$tables['memory']} m
					ON m.language = %s
					AND m.source_hash = s.source_hash
				WHERE s.source_id = %d",
				$language,
				$language,
				$source_id
			),
			ARRAY_A
		);

		$map = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$hash = (string) $row['source_hash'];

			if ( '' !== $hash && ! isset( $map[ $hash ] ) ) {
				$map[ $hash ] = (string) $row['translated_text'];
			}
		}

		return $map;
	}


	private static function translation_map( int $source_id, string $language ): array {
		global $wpdb;
		$tables = self::tables();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					s.segment_key,
					COALESCE(
						NULLIF(t.translated_text, ''),
						NULLIF(m.translated_text, ''),
						s.source_text
					) AS translated_text
				FROM {$tables['segments']} s
				LEFT JOIN {$tables['translations']} t
					ON t.source_id = s.source_id
					AND t.language = %s
					AND t.segment_key = s.segment_key
					AND t.status = 'ready'
					AND t.source_hash = s.source_hash
				LEFT JOIN {$tables['memory']} m
					ON m.language = %s
					AND m.source_hash = s.source_hash
				WHERE s.source_id = %d",
				$language,
				$language,
				$source_id
			),
			ARRAY_A
		);
		$map = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$map[ (string) $row['segment_key'] ] = (string) $row['translated_text'];
		}
		return $map;
	}

	private static function replace_seo_links( DOMDocument $dom, DOMXPath $xpath, array $source, string $slug ): void {
		$nodes = $xpath->query( '//link[@rel="canonical" or @rel="alternate"]' );
		$remove = array();
		foreach ( $nodes as $node ) {
			if ( $node instanceof DOMElement ) {
				$remove[] = $node;
			}
		}
		foreach ( $remove as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}
		$head = $dom->getElementsByTagName( 'head' )->item( 0 );
		if ( ! $head instanceof DOMElement ) {
			return;
		}
		$localized_url = self::language_url( (string) $source['source_url'], $slug );
		$canonical = $dom->createElement( 'link' );
		$canonical->setAttribute( 'rel', 'canonical' );
		$canonical->setAttribute( 'href', $localized_url );
		$head->appendChild( $canonical );

		/* Keep social sharing identity on the localized route as well. */
		$og_url = $xpath->query( '//meta[@property="og:url"]' )->item( 0 );
		if ( $og_url instanceof DOMElement ) {
			$og_url->setAttribute( 'content', $localized_url );
		}
		$source_info = self::source_language_info();
		$alternates = array( (string) $source_info['code'] => (string) $source['source_url'] );
		foreach ( self::ready_languages_for_source_read_only( (int) $source['id'] ) as $ready_slug ) {
			$info = self::languages()[ $ready_slug ];
			$alternates[ (string) $info['code'] ] = self::language_url( (string) $source['source_url'], $ready_slug );
		}
		$alternates['x-default'] = (string) $source['source_url'];
		foreach ( $alternates as $hreflang => $url ) {
			$link = $dom->createElement( 'link' );
			$link->setAttribute( 'rel', 'alternate' );
			$link->setAttribute( 'hreflang', $hreflang );
			$link->setAttribute( 'href', $url );
			$head->appendChild( $link );
		}
	}

	private static function rewrite_internal_links(
		DOMDocument $dom,
		array $source,
		string $slug
	): array {
		$host = strtolower(
			(string) wp_parse_url(
				home_url( '/' ),
				PHP_URL_HOST
			)
		);
		$links = $dom->getElementsByTagName( 'a' );
		$route_map = array();

		foreach ( $links as $link ) {
			if ( ! $link instanceof DOMElement ) {
				continue;
			}

			$href = trim( $link->getAttribute( 'href' ) );

			if (
				'' === $href
				|| '#' === $href
				|| preg_match(
					'/^(?:mailto:|tel:|javascript:|data:|blob:)/i',
					$href
				)
			) {
				continue;
			}

			$absolute = self::absolute_internal_url( $href );
			$link_host = strtolower(
				(string) wp_parse_url(
					$absolute,
					PHP_URL_HOST
				)
			);

			if (
				'' !== $link_host
				&& $link_host !== $host
			) {
				continue;
			}

			$source_path = self::normalized_source_path(
				$absolute
			);

			$target_source = self::source_by_url(
				$absolute
			);

			if ( ! is_array( $target_source ) ) {
				$target_post_id = (int) url_to_postid( $absolute );
				if ( $target_post_id > 0 ) {
					$target_source = self::source_by_post_id( $target_post_id );
				}
			}

			if (
				! is_array( $target_source )
				|| ! self::language_route_available(
					(int) $target_source['id'],
					$slug
				)
			) {
				continue;
			}

			$new_url = self::language_url(
				(string) $target_source['source_url'],
				$slug
			);
			$route_map[ $source_path ] = $new_url;

			$query = (string) wp_parse_url(
				$href,
				PHP_URL_QUERY
			);
			$fragment = (string) wp_parse_url(
				$href,
				PHP_URL_FRAGMENT
			);

			if ( '' !== $query ) {
				$new_url .= '?' . $query;
			}

			if ( '' !== $fragment ) {
				$new_url .= '#' . rawurlencode(
					rawurldecode( $fragment )
				);
			}

			$link->setAttribute( 'href', $new_url );
			$link->setAttribute(
				'data-kozstx-localized-route',
				$slug
			);
		}

		return $route_map;
	}

	private static function language_route_available(
		int $source_id,
		string $slug
	): bool {
		static $cache = array();

		$key = $source_id . '|' . $slug;

		if ( array_key_exists( $key, $cache ) ) {
			return (bool) $cache[ $key ];
		}

		$cache[ $key ] = $source_id > 0 && isset( self::languages()[ $slug ] );

		return (bool) $cache[ $key ];
	}

	private static function absolute_internal_url( string $href ): string {
		$href = html_entity_decode(
			trim( $href ),
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);

		if ( preg_match( '~^https?://~i', $href ) ) {
			return $href;
		}

		if ( 0 === strpos( $href, '//' ) ) {
			$scheme = (string) wp_parse_url( home_url( '/' ), PHP_URL_SCHEME );
			return ( '' !== $scheme ? $scheme : 'https' ) . ':' . $href;
		}

		if ( 0 === strpos( $href, '/' ) ) {
			return home_url( $href );
		}

		return home_url( '/' . ltrim( $href, '/' ) );
	}

	private static function normalized_source_path( string $url ): string {
		$path = rawurldecode(
			(string) wp_parse_url( $url, PHP_URL_PATH )
		);
		$path = '/' . trim( $path, '/' );

		if ( '/' === $path || '' === trim( $path, '/' ) ) {
			return '/';
		}

		foreach ( array_keys( self::languages() ) as $language_slug ) {
			$prefix = '/' . $language_slug;

			if ( $path === $prefix ) {
				return '/';
			}

			if ( 0 === strpos( $path, $prefix . '/' ) ) {
				$path = substr( $path, strlen( $prefix ) );
				break;
			}
		}

		return '/' . trim( $path, '/' ) . '/';
	}

	private static function source_by_url( string $url ): ?array {
		static $inventory = null;

		if ( null === $inventory ) {
			global $wpdb;
			$tables = self::tables();
			$rows = $wpdb->get_results(
				"SELECT * FROM {$tables['sources']}",
				ARRAY_A
			);

			$inventory = array();

			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$path = self::normalized_source_path(
					(string) $row['source_url']
				);

				if ( ! isset( $inventory[ $path ] ) ) {
					$inventory[ $path ] = $row;
				}
			}
		}

		$path = self::normalized_source_path( $url );

		if ( isset( $inventory[ $path ] ) ) {
			return $inventory[ $path ];
		}

		$post_id = (int) url_to_postid(
			strtok( self::absolute_internal_url( $url ), '?#' )
		);

		return $post_id > 0
			? self::source_by_post_id( $post_id )
			: null;
	}

	private static function remove_translatepress_switchers( DOMXPath $xpath ): void {
		$nodes = $xpath->query( '//*[contains(@class,"trp-language-switcher") or contains(@class,"trp-floater") or contains(@class,"trp-language-switcher-container")]' );
		$remove = array();
		foreach ( $nodes as $node ) {
			$remove[] = $node;
		}
		foreach ( $remove as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}
	}

	private static function remove_existing_uafree_switchers(
		DOMXPath $xpath
	): void {
		$nodes = $xpath->query(
			'//*[contains(concat(" ", normalize-space(@class), " "), " kozstx-language-switcher ")'
				. ' or @data-kozstx-language-switcher="1"]'
		);

		$remove = array();

		foreach ( $nodes as $node ) {
			$remove[] = $node;
		}

		foreach ( $remove as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}
	}

	private static function available_languages_for_source(
		int $source_id
	): array {
		$available = array();

		foreach ( self::languages() as $slug => $language ) {
			if (
				self::language_ready( $source_id, $slug )
				|| self::language_can_render_provisional(
					$source_id,
					$slug
				)
			) {
				$available[] = $slug;
			}
		}

		return $available;
	}

	private static function inject_language_switcher( DOMDocument $dom, array $source, string $current_slug ): void {
		$settings = self::settings();
		if ( empty( $settings['switcher_enabled'] ) ) {
			return;
		}
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body instanceof DOMElement ) {
			return;
		}
		$wrap = $dom->createElement( 'div' );
		$wrap->setAttribute( 'class', 'kozstx-language-switcher' );
		$wrap->setAttribute( 'data-kozstx-language-switcher', '1' );
		$side = 'left' === ( $settings['switcher_position'] ?? 'right' ) ? 'left' : 'right';
		$wrap->setAttribute( 'style', 'position:fixed;' . $side . ':14px;bottom:14px;z-index:2147483000;background:#fff;border:1px solid #c3c4c7;border-radius:999px;padding:8px 10px;box-shadow:0 4px 18px rgba(0,0,0,.2);font:14px/1.3 Arial,sans-serif;display:flex;align-items:center;gap:6px' );
		$icon = $dom->createElement( 'span', '🌐' );
		$icon->setAttribute( 'aria-hidden', 'true' );
		$wrap->appendChild( $icon );
		$select = $dom->createElement( 'select' );
		$select->setAttribute( 'aria-label', 'Language' );
		$select->setAttribute( 'onchange', 'if(this.value){window.location.href=this.value;}' );
		$ready = self::ready_languages_for_source_read_only( (int) $source['id'] );
		$current_is_ready = in_array( $current_slug, $ready, true );

		/*
		 * A provisional route may keep the switcher for navigation back to the
		 * source language, but it must not advertise any other unfinished routes.
		 */
		if ( ! $current_is_ready ) {
			if ( ! self::language_can_render_provisional( (int) $source['id'], $current_slug ) ) {
				return;
			}
			$ready = array( $current_slug );
		}

		$source_info = self::source_language_info();
		$option_source = $dom->createElement( 'option', (string) $source_info['native'] );
		$option_source->setAttribute( 'value', (string) $source['source_url'] );
		$select->appendChild( $option_source );
		foreach ( $ready as $slug ) {
			$info = self::languages()[ $slug ] ?? null;
			if ( ! is_array( $info ) ) {
				continue;
			}
			$option = $dom->createElement(
				'option',
				(string) $info['native']
			);
			$option->setAttribute(
				'value',
				self::language_url(
					(string) $source['source_url'],
					$slug
				)
			);

			if ( $slug === $current_slug ) {
				$option->setAttribute(
					'selected',
					'selected'
				);
			}

			$select->appendChild( $option );
		}
		$wrap->appendChild( $select );
		$body->appendChild( $wrap );
	}

	/**
	 * Supply reciprocal hreflang links to KOZ SEO Core on source-language
	 * pages. Only fully translated language routes are advertised to crawlers;
	 * provisional routes keep their protective noindex state and are omitted.
	 *
	 * @param array<string,string> $links Existing hreflang links.
	 * @param string               $canonical Current canonical URL.
	 * @return array<string,string>
	 */

	/**
	 * Expose an optional language contract to SEO consumers. Static Translate is
	 * the source of truth only for its own public namespaces and hreflang codes;
	 * consumers must continue to work when this plugin is absent.
	 *
	 * @param array<string,mixed> $contract Existing contract.
	 * @return array<string,mixed>
	 */
	public static function seo_translation_contract( array $contract ): array {
		$source = self::source_language_info();
		$targets = array();
		foreach ( self::languages() as $slug => $language ) {
			$targets[ $slug ] = array(
				'code' => (string) ( $language['code'] ?? $slug ),
				'dir'  => (string) ( $language['dir'] ?? 'ltr' ),
			);
		}

		return array(
			'provider'         => 'koz-static-translate',
			'version'          => KOZSTX_VERSION,
			'source_slug'      => self::source_language_slug(),
			'source_code'      => (string) ( $source['code'] ?? self::DEFAULT_SOURCE_LANGUAGE ),
			'target_languages' => $targets,
			'routes_enabled'   => ! empty( self::settings()['routes_enabled'] ),
		);
	}

	public static function seo_additional_sitemaps( array $sitemaps ): array {
		$settings = self::settings();
		if ( empty( $settings['routes_enabled'] ) ) {
			return $sitemaps;
		}
		$sitemaps[] = home_url( '/uafree-translations-sitemap.xml' );
		return array_values( array_unique( array_filter( array_map( 'strval', $sitemaps ) ) ) );
	}

	public static function seo_hreflang_links( array $links, string $canonical ): array {
		if ( '' === trim( $canonical ) ) {
			return $links;
		}

		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$url_host = strtolower( (string) wp_parse_url( $canonical, PHP_URL_HOST ) );
		if ( '' === $home_host || $home_host !== $url_host ) {
			return $links;
		}

		$source = self::source_by_url( $canonical );
		if ( ! is_array( $source ) || empty( $source['source_url'] ) ) {
			return $links;
		}

		$source_url = (string) $source['source_url'];
		$source_info = self::source_language_info();
		$source_code = (string) ( $source_info['code'] ?? self::source_language_code() );
		if ( '' !== $source_code ) {
			$links[ $source_code ] = $source_url;
		}

		foreach ( self::ready_languages_for_source_read_only( (int) $source['id'] ) as $ready_slug ) {
			$info = self::languages()[ $ready_slug ] ?? null;
			if ( ! is_array( $info ) || empty( $info['code'] ) ) {
				continue;
			}
			$links[ (string) $info['code'] ] = self::language_url( $source_url, $ready_slug );
		}

		$links['x-default'] = $source_url;
		return $links;
	}

	public static function source_language_switcher(): void {
		$settings = self::settings();

		if (
			self::$source_switcher_rendered
			|| empty( $settings['switcher_enabled'] )
			|| is_admin()
			|| '' !== (string) get_query_var( self::LANG_QUERY_VAR )
			|| ! ( is_singular() || is_front_page() )
		) {
			return;
		}

		$post_id = is_front_page()
			? (int) get_option( 'page_on_front' )
			: (int) get_queried_object_id();

		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '/';
		$request_path = (string) wp_parse_url(
			$request_uri,
			PHP_URL_PATH
		);
		$current_url = home_url(
			'/' . ltrim( $request_path, '/' )
		);

		$source = self::source_by_url( $current_url );

		if ( ! is_array( $source ) && $post_id > 0 ) {
			$source = self::source_by_post_id( $post_id );
		}

		$ready = is_array( $source )
			? self::ready_languages_for_source_read_only( (int) $source['id'] )
			: array();

		$source_url = is_array( $source ) && ! empty( $source['source_url'] )
			? (string) $source['source_url']
			: ( is_front_page() ? home_url( '/' ) : get_permalink( $post_id ) );

		if ( empty( $ready ) || ! is_string( $source_url ) || '' === $source_url ) {
			return;
		}

		self::$source_switcher_rendered = true;

		$side = 'left' === ( $settings['switcher_position'] ?? 'right' )
			? 'left'
			: 'right';
		$source_info = self::source_language_info();

		echo '<div class="kozstx-language-switcher" data-kozstx-language-switcher="1" style="position:fixed;' . esc_attr( $side ) . ':14px;bottom:14px;z-index:2147483000;background:#fff;border:1px solid #c3c4c7;border-radius:999px;padding:8px 10px;box-shadow:0 4px 18px rgba(0,0,0,.2);font:14px/1.3 Arial,sans-serif;display:flex;align-items:center;gap:6px">';
		echo '<span aria-hidden="true">🌐</span>';
		echo '<select aria-label="Language" onchange="if(this.value){window.location.href=this.value;}">';
		echo '<option value="' . esc_url( $source_url ) . '" selected>' . esc_html( (string) $source_info['native'] ) . '</option>';

		foreach ( $ready as $slug ) {
			$info = self::languages()[ $slug ];
			echo '<option value="' . esc_url( self::language_url( $source_url, $slug ) ) . '">' . esc_html( (string) $info['native'] ) . '</option>';
		}

		echo '</select></div>';
	}

	public static function reset_current_translation_state(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Insufficient permissions.', 'koz-static-translate' ),
			);
		}

		global $wpdb;
		$tables = self::tables();
		$counts = array();

		foreach ( $tables as $key => $table ) {
			$exists = $table === $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
			);

			$counts[ $key ] = $exists
				? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" )
				: 0;

			if ( $exists ) {
				$wpdb->query( "TRUNCATE TABLE `{$table}`" );
			}
		}

		$settings = self::settings();
		$settings['auto_enabled'] = 0;
		$settings['routes_enabled'] = 0;
		$settings['switcher_enabled'] = 1;
		update_option( self::SETTINGS_OPTION, $settings, false );

		$runtime = self::default_runtime();
		$runtime['active_source_language'] = self::source_language_slug();
		self::save_runtime( $runtime );

		delete_transient( self::LOCK_KEY );
		self::bootstrap_core_pages();
		self::add_rewrite_rules();
		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPTION, self::DB_VERSION, false );
		self::purge_all_caches();

		return array(
			'success' => true,
			'message' => 'Поточні переклади, memory, queue, usage та logs скинуто. Налаштування Azure і ключ збережено. Публічні мовні URL та автоматичну чергу вимкнено до нового контрольного запуску.',
			'counts' => $counts,
		);
	}

	public static function purge_all_caches(): void {
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		$runtime = self::runtime();
		$runtime['last_cache_purge_at'] = current_time( 'mysql', true );
		self::save_runtime( $runtime );
	}

	private static function source_by_post_id( int $post_id ): ?array {
		global $wpdb;
		$tables = self::tables();
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$tables['sources']} WHERE post_id = %d", $post_id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}


	private static function hydrate_source_translations_from_memory(
		int $source_id,
		string $slug
	): int {
		global $wpdb;
		$tables = self::tables();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					s.segment_key,
					s.source_hash,
					s.source_text,
					m.translated_text
				FROM {$tables['segments']} s
				INNER JOIN {$tables['memory']} m
					ON m.language = %s
					AND m.source_hash = s.source_hash
					AND m.translated_text <> ''
				LEFT JOIN {$tables['translations']} t
					ON t.source_id = s.source_id
					AND t.language = %s
					AND t.segment_key = s.segment_key
				WHERE s.source_id = %d
					AND s.is_protected = 0
					AND (
						t.id IS NULL
						OR t.status <> 'ready'
						OR t.source_hash <> s.source_hash
						OR t.translated_text = ''
					)",
				$slug,
				$slug,
				$source_id
			),
			ARRAY_A
		);

		$hydrated = 0;

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			self::store_translation(
				$source_id,
				$slug,
				(string) $row['segment_key'],
				(string) $row['source_hash'],
				(string) $row['translated_text'],
				'ready',
				0
			);
			$hydrated++;
		}

		return $hydrated;
	}

	private static function store_translations_for_source_hash(
		int $source_id,
		string $slug,
		string $source_hash,
		string $translated_text
	): int {
		global $wpdb;
		$tables = self::tables();

		$segments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT segment_key, source_hash
				FROM {$tables['segments']}
				WHERE source_id = %d
					AND source_hash = %s
					AND is_protected = 0",
				$source_id,
				$source_hash
			),
			ARRAY_A
		);

		$stored = 0;

		foreach ( is_array( $segments ) ? $segments : array() as $segment ) {
			self::store_translation(
				$source_id,
				$slug,
				(string) $segment['segment_key'],
				(string) $segment['source_hash'],
				$translated_text,
				'ready',
				0
			);
			$stored++;
		}

		return $stored;
	}

	private static function language_can_render_provisional(
		int $source_id,
		string $slug
	): bool {
		if ( ! isset( self::languages()[ $slug ] ) ) {
			return false;
		}

		if ( ! self::migration_freeze_active() ) {
			self::hydrate_source_translations_from_memory(
				$source_id,
				$slug
			);
		}

		global $wpdb;
		$tables = self::tables();

		$coverage = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(
						CASE
							WHEN s.is_protected = 0 THEN 1
							ELSE 0
						END
					) AS translatable,
					SUM(
						CASE
							WHEN s.is_protected = 0
								AND (
									(
										t.id IS NOT NULL
										AND t.status = 'ready'
										AND t.source_hash = s.source_hash
										AND t.translated_text <> ''
									)
									OR (
										m.id IS NOT NULL
										AND m.translated_text <> ''
									)
								)
							THEN 1
							ELSE 0
						END
					) AS available
				FROM {$tables['segments']} s
				LEFT JOIN {$tables['translations']} t
					ON t.source_id = s.source_id
					AND t.language = %s
					AND t.segment_key = s.segment_key
				LEFT JOIN {$tables['memory']} m
					ON m.language = %s
					AND m.source_hash = s.source_hash
				WHERE s.source_id = %d",
				$slug,
				$slug,
				$source_id
			),
			ARRAY_A
		);

		$translatable = (int) (
			$coverage['translatable']
				?? 0
		);
		$available = (int) (
			$coverage['available']
				?? 0
		);

		$runtime = self::runtime();

		return self::provisional_coverage_allowed(
			$translatable,
			$available,
			(string) ( $runtime['pause_reason'] ?? '' )
		);
	}

	private static function provisional_coverage_allowed(
		int $translatable,
		int $available,
		string $pause_reason
	): bool {
		unset( $pause_reason );

		return $translatable > 0 && $available > 0;
	}


	private static function language_ready(
		int $source_id,
		string $slug
	): bool {
		if ( ! isset( self::languages()[ $slug ] ) ) {
			return false;
		}

		self::hydrate_source_translations_from_memory(
			$source_id,
			$slug
		);

		global $wpdb;
		$tables = self::tables();

		$source = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					id,
					post_id,
					local_hash,
					scan_status,
					is_report,
					report_date
				FROM {$tables['sources']}
				WHERE id = %d",
				$source_id
			),
			ARRAY_A
		);

		if (
			! is_array( $source )
			|| 'ready' !== (string) $source['scan_status']
		) {
			return false;
		}

		$counts = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total,
					SUM(
						CASE
							WHEN s.is_protected = 1 THEN 1
							WHEN t.id IS NOT NULL
								AND t.status = 'ready'
								AND t.source_hash = s.source_hash
								AND t.translated_text <> ''
							THEN 1
							ELSE 0
						END
					) AS ready
				FROM {$tables['segments']} s
				LEFT JOIN {$tables['translations']} t
					ON t.source_id = s.source_id
					AND t.language = %s
					AND t.segment_key = s.segment_key
				WHERE s.source_id = %d",
				$slug,
				$source_id
			),
			ARRAY_A
		);

		$total = (int) ( $counts['total'] ?? 0 );
		$ready = (int) ( $counts['ready'] ?? 0 );

		if ( $total <= 0 || $ready < $total ) {
			return false;
		}

		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status, source_hash
				FROM {$tables['queue']}
				WHERE source_id = %d AND language = %s",
				$source_id,
				$slug
			),
			ARRAY_A
		);

		$now = current_time( 'mysql', true );
		$language = self::languages()[ $slug ];

		if ( is_array( $job ) ) {
			$wpdb->update(
				$tables['queue'],
				array(
					'post_id' => (int) $source['post_id'],
					'language_order' => (int) $language['order'],
					'category' => ! empty( $source['is_report'] )
						? 'report'
						: 'core',
					'report_date' => $source['report_date'],
					'source_hash' => (string) $source['local_hash'],
					'status' => 'done',
					'processed_segments' => $ready,
					'total_segments' => $total,
					'last_error' => '',
					'next_run_at' => null,
					'locked_at' => null,
					'finished_at' => $now,
					'updated_at' => $now,
				),
				array( 'id' => (int) $job['id'] )
			);
		} else {
			$wpdb->insert(
				$tables['queue'],
				array(
					'source_id' => $source_id,
					'post_id' => (int) $source['post_id'],
					'language' => $slug,
					'language_order' => (int) $language['order'],
					'category' => ! empty( $source['is_report'] )
						? 'report'
						: 'core',
					'priority' => ! empty( $source['is_report'] ) ? 100 : 10,
					'report_date' => $source['report_date'],
					'source_hash' => (string) $source['local_hash'],
					'status' => 'done',
					'attempts' => 0,
					'processed_segments' => $ready,
					'total_segments' => $total,
					'last_error' => '',
					'next_run_at' => null,
					'locked_at' => null,
					'created_at' => $now,
					'updated_at' => $now,
					'finished_at' => $now,
				)
			);
		}

		return true;
	}

	private static function language_ready_read_only( int $source_id, string $slug ): bool {
		if ( ! isset( self::languages()[ $slug ] ) ) {
			return false;
		}

		global $wpdb;
		$tables = self::tables();
		$source = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, scan_status FROM {$tables['sources']} WHERE id = %d",
				$source_id
			),
			ARRAY_A
		);
		if ( ! is_array( $source ) || 'ready' !== (string) $source['scan_status'] ) {
			return false;
		}

		$queue_status = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$tables['queue']} WHERE source_id = %d AND language = %s LIMIT 1",
				$source_id,
				$slug
			)
		);
		if ( 'done' === $queue_status ) {
			return true;
		}

		$counts = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total,
					SUM(
						CASE
							WHEN s.is_protected = 1 THEN 1
							WHEN t.id IS NOT NULL
								AND t.status = 'ready'
								AND t.source_hash = s.source_hash
								AND t.translated_text <> ''
							THEN 1
							ELSE 0
						END
					) AS ready
				FROM {$tables['segments']} s
				LEFT JOIN {$tables['translations']} t
					ON t.source_id = s.source_id
					AND t.language = %s
					AND t.segment_key = s.segment_key
				WHERE s.source_id = %d",
				$slug,
				$source_id
			),
			ARRAY_A
		);

		$total = (int) ( $counts['total'] ?? 0 );
		$ready = (int) ( $counts['ready'] ?? 0 );
		return $total > 0 && $ready >= $total;
	}

	private static function ready_languages_for_source_read_only( int $source_id ): array {
		$ready = array();
		foreach ( self::languages() as $slug => $language ) {
			unset( $language );
			if ( self::language_ready_read_only( $source_id, $slug ) ) {
				$ready[] = $slug;
			}
		}
		return $ready;
	}

	private static function ready_languages_for_source(
		int $source_id
	): array {
		$ready = array();

		foreach ( self::languages() as $slug => $language ) {
			if ( self::language_ready( $source_id, $slug ) ) {
				$ready[] = $slug;
			}
		}

		return $ready;
	}

	private static function language_url( string $source_url, string $slug ): string {
		$path = (string) wp_parse_url( $source_url, PHP_URL_PATH );
		$path = '/' === $path || '' === $path ? '/' : '/' . ltrim( $path, '/' );
		return home_url( '/' . $slug . ( '/' === $path ? '/' : $path ) );
	}

	private static function translatepress_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'translatepress-multilingual/index.php' );
	}

	private static function serve_sitemap(): void {
		global $wpdb;
		$tables = self::tables();
		$rows = $wpdb->get_results(
			"SELECT s.id, s.source_url, s.updated_at, q.language
			FROM {$tables['sources']} s
			INNER JOIN {$tables['queue']} q ON q.source_id = s.id
			WHERE q.status = 'done' AND q.source_hash = s.local_hash AND s.scan_status = 'ready'
			ORDER BY s.is_report ASC, s.report_date DESC, s.id ASC, q.language_order ASC",
			ARRAY_A
		);
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/xml; charset=utf-8' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$slug = (string) $row['language'];
			if ( ! isset( self::languages()[ $slug ] ) ) {
				continue;
			}
			echo "  <url>\n";
			echo '    <loc>' . esc_xml( self::language_url( (string) $row['source_url'], $slug ) ) . "</loc>\n";
			if ( ! empty( $row['updated_at'] ) ) {
				echo '    <lastmod>' . esc_xml( gmdate( DATE_W3C, strtotime( (string) $row['updated_at'] . ' UTC' ) ) ) . "</lastmod>\n";
			}
			echo "  </url>\n";
		}
		echo '</urlset>';
		exit;
	}

	public static function robots_txt( string $output, bool $public ): string {
		if ( ! $public ) {
			return $output;
		}
		$line = 'Sitemap: ' . home_url( '/uafree-translations-sitemap.xml' );
		if ( false === strpos( $output, $line ) ) {
			$output = rtrim( $output ) . "\n" . $line . "\n";
		}
		return $output;
	}

	private static function xml_escape( string $value ): string {
		return htmlspecialchars( $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
	}

	public static function admin_menu(): void {
		$parent = KOZSTX_Suite_Registry::suite_page();
		add_submenu_page(
			$parent,
			__( 'KOZ Static Translate', 'koz-static-translate' ),
			__( 'Static Translate', 'koz-static-translate' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'admin_page' )
		);
	}

	public static function enqueue_admin_assets(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		if ( self::PAGE_SLUG !== $page ) {
			return;
		}
		wp_enqueue_style( 'kozstx-admin', KOZSTX_URL . 'assets/admin.css', array(), KOZSTX_VERSION );
		wp_enqueue_script( 'kozstx-admin', KOZSTX_URL . 'assets/admin.js', array(), KOZSTX_VERSION, true );
		$usage = self::current_usage();
		wp_localize_script(
			'kozstx-admin',
			'KOZSTXAdmin',
			array(
				'nonce'        => wp_create_nonce( self::AJAX_NONCE ),
				'resetTs'      => (int) $usage['cycle']['next_ts'],
				'unknownError' => __( 'Unknown error.', 'koz-static-translate' ),
				'running'      => __( 'Working…', 'koz-static-translate' ),
				'done'         => __( 'Done.', 'koz-static-translate' ),
				'days'         => __( 'd', 'koz-static-translate' ),
				'hours'        => __( 'h', 'koz-static-translate' ),
				'minutes'      => __( 'm', 'koz-static-translate' ),
				'seconds'      => __( 's', 'koz-static-translate' ),
			)
		);
	}

	public static function admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		/* 0.9.24 safety: opening this screen must never run migrations, scans, repairs or cron setup. */
		$settings     = self::settings();
		$runtime      = self::runtime();
		$credentials  = self::credentials();
		$usage        = self::current_usage();
		$queue        = self::queue_stats();
		$inventory    = self::inventory_stats();
		$languages    = self::language_stats();
		$jobs         = self::recent_jobs();
		$logs         = self::recent_logs();
		$paused_until = (int) $runtime['pause_until'];
		?>
		<div class="wrap koz-static-translate-wrap">
			<h1><?php echo esc_html__( 'KOZ Static Translate', 'koz-static-translate' ); ?> <code><?php echo esc_html( KOZSTX_VERSION ); ?></code></h1>
			<p><?php echo esc_html__( 'Queued static translation with Azure Translator, translation memory and public language routes.', 'koz-static-translate' ); ?></p>

			<?php if ( ! $credentials['configured'] ) : ?>
				<div class="notice notice-warning inline"><p><strong><?php echo esc_html__( 'Azure Translator is not configured.', 'koz-static-translate' ); ?></strong> <?php echo esc_html__( 'Enter the key, region and endpoint in the settings below.', 'koz-static-translate' ); ?></p></div>
			<?php endif; ?>

			<div class="koz-st-kpis">
				<div class="koz-st-card"><strong><?php echo esc_html( self::source_language_info()['native'] ); ?></strong><span><?php echo esc_html__( 'Source language', 'koz-static-translate' ); ?></span></div>
				<div class="koz-st-card"><strong><?php echo $credentials['configured'] ? esc_html__( 'Configured', 'koz-static-translate' ) : esc_html__( 'Missing', 'koz-static-translate' ); ?></strong><span><?php echo esc_html__( 'Azure key', 'koz-static-translate' ); ?></span></div>
				<div class="koz-st-card"><strong><?php echo esc_html( number_format_i18n( $queue['queued'] + $queue['running'] + $queue['retry'] ) ); ?></strong><span><?php echo esc_html__( 'Pending jobs', 'koz-static-translate' ); ?></span></div>
				<div class="koz-st-card"><strong><?php echo esc_html( number_format( $usage['percent'], 2 ) ); ?>%</strong><span><?php echo esc_html__( 'Monthly usage', 'koz-static-translate' ); ?></span></div>
			</div>

			<div class="koz-st-panel">
				<h2><?php echo esc_html__( 'Runtime status', 'koz-static-translate' ); ?></h2>
				<table class="widefat striped"><tbody>
					<tr><th><?php echo esc_html__( 'Automatic worker', 'koz-static-translate' ); ?></th><td><strong><?php echo esc_html__( 'Disabled (safety mode)', 'koz-static-translate' ); ?></strong></td></tr>
					<tr><th><?php echo esc_html__( 'Public language routes', 'koz-static-translate' ); ?></th><td><?php echo ! empty( $settings['routes_enabled'] ) ? esc_html__( 'Enabled when a page is ready', 'koz-static-translate' ) : esc_html__( 'Disabled', 'koz-static-translate' ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'Floating language switcher', 'koz-static-translate' ); ?></th><td><?php echo ! empty( $settings['switcher_enabled'] ) ? esc_html__( 'Enabled', 'koz-static-translate' ) : esc_html__( 'Disabled', 'koz-static-translate' ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'Manual pause', 'koz-static-translate' ); ?></th><td><?php echo ! empty( $runtime['manual_paused'] ) ? esc_html__( 'Yes', 'koz-static-translate' ) : esc_html__( 'No', 'koz-static-translate' ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'System pause', 'koz-static-translate' ); ?></th><td><?php echo esc_html( (string) $runtime['pause_reason'] ?: __( 'None', 'koz-static-translate' ) ); ?><?php if ( $paused_until > time() ) : ?>, <?php echo esc_html__( 'until', 'koz-static-translate' ); ?> <code><?php echo esc_html( gmdate( 'Y-m-d H:i:s', $paused_until ) ); ?> UTC</code><?php endif; ?></td></tr>
					<tr><th><?php echo esc_html__( 'Last run', 'koz-static-translate' ); ?></th><td><?php echo esc_html( (string) $runtime['last_run_at'] ?: __( 'Not run yet', 'koz-static-translate' ) ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'Last success', 'koz-static-translate' ); ?></th><td><?php echo esc_html( (string) $runtime['last_success_at'] ?: __( 'No successful run yet', 'koz-static-translate' ) ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'Last error', 'koz-static-translate' ); ?></th><td><?php echo esc_html( (string) $runtime['last_error'] ?: __( 'None', 'koz-static-translate' ) ); ?></td></tr>
				</tbody></table>
				<div class="koz-st-actions">
					<button class="button" disabled><?php echo esc_html__( 'Azure actions disabled (safety mode)', 'koz-static-translate' ); ?></button>
					<button class="button" disabled><?php echo esc_html__( 'Rebuild disabled (safety mode)', 'koz-static-translate' ); ?></button>
				</div>
				<div class="koz-st-actions" style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
					<label for="kozstx-reconcile-path"><strong><?php echo esc_html__( 'One-page readiness check', 'koz-static-translate' ); ?></strong></label>
					<input type="text" id="kozstx-reconcile-path" class="regular-text" value="/ua-free/" placeholder="/ua-free/" />
					<button class="button button-primary" id="kozstx-reconcile-page"><?php echo esc_html__( 'Reconcile this page from Translation Memory', 'koz-static-translate' ); ?></button>
					<button class="button" id="kozstx-diagnose-page"><?php echo esc_html__( 'Show missing source segments', 'koz-static-translate' ); ?></button>
					<span><?php echo esc_html__( 'Local database only. No scan, no rebuild, no Azure.', 'koz-static-translate' ); ?></span>
				</div>
				<div id="kozstx-auto-status"></div>
				<div id="kozstx-diagnostic-results" style="margin-top:12px"></div>
				<hr style="margin:18px 0">
				<div class="koz-st-actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
					<strong><?php echo esc_html__( 'Priority Core readiness', 'koz-static-translate' ); ?></strong>
					<button class="button button-primary" id="kozstx-reconcile-core-batch"><?php echo esc_html__( 'Reconcile next 4 Priority Core pages', 'koz-static-translate' ); ?></button>
					<span><?php echo esc_html__( 'Priority Core is detected automatically from Home + merged internal navigation sources: all assigned primary/main/header menus, the richest unassigned classic menu fallback, and block-theme Navigation. External URLs are discarded, duplicates removed, exact paths only. Secondary/legacy core and report posts are excluded. 4 pages per click. No Azure, scan, rebuild or automatic loop.', 'koz-static-translate' ); ?></span>
				</div>
				<div id="kozstx-core-batch-results" style="margin-top:10px"></div>
				<div class="koz-st-actions" style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
					<button class="button" id="kozstx-priority-core-discovery"><?php echo esc_html__( 'Show Priority Core discovery', 'koz-static-translate' ); ?></button>
					<span><?php echo esc_html__( 'Read-only diagnostic: menu path → source inventory → core queue. No writes, no Azure.', 'koz-static-translate' ); ?></span>
				</div>
				<div id="kozstx-priority-core-discovery-results" style="margin-top:10px"></div>
				<div class="koz-st-actions" style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
					<button class="button button-primary" id="kozstx-prepare-priority-core-source"><?php echo esc_html__( 'Prepare next pending Priority Core source', 'koz-static-translate' ); ?></button>
					<span><?php echo esc_html__( 'Universal and bounded: one detected internal Priority Core page per click. Local source scan + Translation Memory hydration only. No Azure, no full inventory rebuild, no automatic loop.', 'koz-static-translate' ); ?></span>
				</div>
				<div id="kozstx-prepare-priority-core-results" style="margin-top:10px"></div>
			</div>

			<div class="koz-st-two-columns">
				<div class="koz-st-panel">
					<h2><?php echo esc_html__( 'Monthly budget', 'koz-static-translate' ); ?></h2>
					<table class="widefat striped"><tbody>
						<tr><th><?php echo esc_html__( 'Current cycle', 'koz-static-translate' ); ?></th><td><code><?php echo esc_html( $usage['cycle']['start_iso'] ); ?></code> → <code><?php echo esc_html( $usage['cycle']['next_iso'] ); ?></code></td></tr>
						<tr><th><?php echo esc_html__( 'Used characters', 'koz-static-translate' ); ?></th><td><?php echo esc_html( number_format_i18n( $usage['characters'] ) ); ?> / <?php echo esc_html( number_format_i18n( $usage['limit'] ) ); ?></td></tr>
						<tr><th><?php echo esc_html__( 'Remaining characters', 'koz-static-translate' ); ?></th><td><?php echo esc_html( number_format_i18n( $usage['remaining'] ) ); ?></td></tr>
						<tr><th><?php echo esc_html__( 'API requests', 'koz-static-translate' ); ?></th><td><?php echo esc_html( number_format_i18n( $usage['requests'] ) ); ?></td></tr>
						<tr><th><?php echo esc_html__( 'Limit resets in', 'koz-static-translate' ); ?></th><td><strong id="kozstx-countdown" data-reset="<?php echo esc_attr( (string) $usage['cycle']['next_ts'] ); ?>"><?php echo esc_html__( 'Calculating…', 'koz-static-translate' ); ?></strong></td></tr>
					</tbody></table>
					<div class="koz-st-progress"><span style="width:<?php echo esc_attr( (string) min( 100, $usage['percent'] ) ); ?>%"></span></div>
					<?php if ( ! empty( $usage['by_language'] ) ) : ?>
						<h3><?php echo esc_html__( 'Azure usage by language code', 'koz-static-translate' ); ?></h3>
						<table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Language code', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Characters', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Requests', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Status', 'koz-static-translate' ); ?></th></tr></thead><tbody>
						<?php foreach ( $usage['by_language'] as $usage_slug => $usage_row ) : $usage_active = isset( self::languages()[ (string) $usage_slug ] ); ?>
							<tr><td><code><?php echo esc_html( (string) $usage_slug ); ?></code></td><td><?php echo esc_html( number_format_i18n( (int) ( $usage_row['characters'] ?? 0 ) ) ); ?></td><td><?php echo esc_html( number_format_i18n( (int) ( $usage_row['requests'] ?? 0 ) ) ); ?></td><td><?php echo $usage_active ? esc_html__( 'Active', 'koz-static-translate' ) : esc_html__( 'Inactive / legacy', 'koz-static-translate' ); ?></td></tr>
						<?php endforeach; ?>
						</tbody></table>
					<?php endif; ?>
				</div>
				<div class="koz-st-panel">
					<h2><?php echo esc_html__( 'Inventory and queue', 'koz-static-translate' ); ?></h2>
					<table class="widefat striped"><tbody>
						<tr><th><?php echo esc_html__( 'Core pages', 'koz-static-translate' ); ?></th><td><?php echo esc_html( $inventory['core_sources'] . ' / ' . $inventory['core_expected'] ); ?></td></tr>
						<tr><th><?php echo esc_html__( 'Daily reports', 'koz-static-translate' ); ?></th><td><?php echo esc_html( $inventory['report_sources'] . ' / ' . $inventory['report_expected'] ); ?></td></tr>
						<tr><th><?php echo esc_html__( 'Report backfill', 'koz-static-translate' ); ?></th><td><?php echo ! empty( $runtime['report_bootstrap_complete'] ) ? esc_html__( 'Inventory complete', 'koz-static-translate' ) : esc_html__( 'Processing newest to oldest', 'koz-static-translate' ); ?></td></tr>
						<tr><th><?php echo esc_html__( 'Queue status', 'koz-static-translate' ); ?></th><td><code><?php echo esc_html( sprintf( 'queued %d · running %d · retry %d · paused %d · done %d · failed %d', $queue['queued'], $queue['running'], $queue['retry'], $queue['paused'], $queue['done'], $queue['failed'] ) ); ?></code></td></tr>
						<tr><th><?php echo esc_html__( 'Estimated pending characters', 'koz-static-translate' ); ?></th><td><?php echo esc_html( number_format_i18n( $inventory['pending_chars'] ) ); ?></td></tr>
					</tbody></table>
				</div>
			</div>

			<div class="koz-st-panel">
				<h2><?php echo esc_html__( 'Language progress', 'koz-static-translate' ); ?></h2>
				<table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Language', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Core pages', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Reports', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Newest ready report', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Oldest ready report', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Pending', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Characters this cycle', 'koz-static-translate' ); ?></th></tr></thead><tbody>
				<?php foreach ( $languages as $row ) : ?><tr><td><strong><?php echo esc_html( $row['name'] ); ?></strong><br><code><?php echo esc_html( $row['code'] ); ?></code></td><td><?php echo esc_html( $row['core_done'] . ' / ' . $row['core_total'] ); ?></td><td><?php echo esc_html( $row['report_done'] . ' / ' . $row['report_total'] ); ?></td><td><?php echo esc_html( $row['newest_report'] ?: '—' ); ?></td><td><?php echo esc_html( $row['oldest_report'] ?: '—' ); ?></td><td><?php echo esc_html( number_format_i18n( $row['pending'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['used_chars'] ) ); ?></td></tr><?php endforeach; ?>
				</tbody></table>
			</div>

			<div class="koz-st-panel">
				<h2><?php echo esc_html__( 'Automation settings', 'koz-static-translate' ); ?></h2>
				<form method="post" action="options.php">
					<?php settings_fields( 'kozstx_settings' ); ?>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><?php echo esc_html__( 'Source language', 'koz-static-translate' ); ?></th><td><select name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[source_language]"><?php foreach ( self::source_languages() as $slug => $language ) : ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( self::source_language_slug(), $slug ); ?>><?php echo esc_html( $language['native'] . ' — ' . $language['name'] ); ?></option><?php endforeach; ?></select><p class="description"><?php echo esc_html__( 'Changing the source language rebuilds the queue and translation memory.', 'koz-static-translate' ); ?></p></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Target languages', 'koz-static-translate' ); ?></th><td><?php $selected_targets = array_map( 'sanitize_key', (array) ( $settings['target_languages'] ?? array() ) ); foreach ( self::source_languages() as $slug => $language ) : if ( $slug === self::source_language_slug() ) { continue; } ?><label style="display:inline-block;min-width:180px;margin:0 12px 8px 0"><input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[target_languages][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected_targets, true ) ); ?>> <?php echo esc_html( $language['native'] . ' (' . $slug . ')' ); ?></label><?php endforeach; ?><p class="description"><?php echo esc_html__( 'Select up to 10 public translation namespaces. Existing translation memory is preserved when languages are restored.', 'koz-static-translate' ); ?></p></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Azure Translator key', 'koz-static-translate' ); ?></th><td><input type="password" autocomplete="new-password" class="regular-text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[azure_key_new]" value="" placeholder="<?php echo esc_attr( $credentials['configured'] ? __( 'Key already saved; leave empty to keep it', 'koz-static-translate' ) : __( 'Paste the Azure key', 'koz-static-translate' ) ); ?>"><br><label><input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[azure_key_clear]" value="1"> <?php echo esc_html__( 'Delete the saved key', 'koz-static-translate' ); ?></label><p class="description"><?php echo esc_html__( 'The key is encrypted in the WordPress database using WordPress salts.', 'koz-static-translate' ); ?></p></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Azure region', 'koz-static-translate' ); ?></th><td><input class="regular-text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[azure_region]" value="<?php echo esc_attr( (string) $settings['azure_region'] ); ?>" placeholder="westeurope"></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Azure endpoint', 'koz-static-translate' ); ?></th><td><input class="large-text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[azure_endpoint]" value="<?php echo esc_attr( (string) $settings['azure_endpoint'] ); ?>"></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Automatic queue', 'koz-static-translate' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[auto_enabled]" value="1" <?php checked( ! empty( $settings['auto_enabled'] ) ); ?>> <?php echo esc_html__( 'Run through WP-Cron', 'koz-static-translate' ); ?></label></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Public language URLs', 'koz-static-translate' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[routes_enabled]" value="1" <?php checked( ! empty( $settings['routes_enabled'] ) ); ?>> <?php echo esc_html__( 'Enable each page after its translation is complete', 'koz-static-translate' ); ?></label></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Floating language switcher', 'koz-static-translate' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[switcher_enabled]" value="1" <?php checked( ! empty( $settings['switcher_enabled'] ) ); ?>> <?php echo esc_html__( 'Show on original and translated pages', 'koz-static-translate' ); ?></label> <select name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[switcher_position]"><option value="right" <?php selected( $settings['switcher_position'], 'right' ); ?>><?php echo esc_html__( 'Right', 'koz-static-translate' ); ?></option><option value="left" <?php selected( $settings['switcher_position'], 'left' ); ?>><?php echo esc_html__( 'Left', 'koz-static-translate' ); ?></option></select></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Dynamic content', 'koz-static-translate' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[dynamic_content_enabled]" value="1" <?php checked( ! empty( $settings['dynamic_content_enabled'] ) ); ?>> <?php echo esc_html__( 'Translate text and charts added after page load', 'koz-static-translate' ); ?></label><p class="description"><?php echo esc_html__( 'Repeated text is reused from translation memory without another Azure charge.', 'koz-static-translate' ); ?></p></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Monthly character limit', 'koz-static-translate' ); ?></th><td><input type="number" min="100000" max="100000000" step="1000" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[monthly_limit]" value="<?php echo esc_attr( (string) $settings['monthly_limit'] ); ?>"></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Reserve before limit', 'koz-static-translate' ); ?></th><td><input type="number" min="0" max="100000" step="1000" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[reserve_chars]" value="<?php echo esc_attr( (string) $settings['reserve_chars'] ); ?>"></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Cycle start', 'koz-static-translate' ); ?></th><td><?php echo esc_html__( 'Day', 'koz-static-translate' ); ?> <input type="number" min="1" max="28" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[reset_day]" value="<?php echo esc_attr( (string) $settings['reset_day'] ); ?>">, <?php echo esc_html__( 'UTC hour', 'koz-static-translate' ); ?> <input type="number" min="0" max="23" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[reset_hour_utc]" value="<?php echo esc_attr( (string) $settings['reset_hour_utc'] ); ?>"></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Characters per API step', 'koz-static-translate' ); ?></th><td><input type="number" min="1000" max="30000" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[batch_chars]" value="<?php echo esc_attr( (string) $settings['batch_chars'] ); ?>"></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Segments per API step', 'koz-static-translate' ); ?></th><td><input type="number" min="1" max="500" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[max_segments]" value="<?php echo esc_attr( (string) $settings['max_segments'] ); ?>"></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Request interval, seconds', 'koz-static-translate' ); ?></th><td><input type="number" min="60" max="3600" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[request_interval]" value="<?php echo esc_attr( (string) $settings['request_interval'] ); ?>"></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Core page rescan, hours', 'koz-static-translate' ); ?></th><td><input type="number" min="1" max="168" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[core_rescan_hours]" value="<?php echo esc_attr( (string) $settings['core_rescan_hours'] ); ?>"></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Maximum consecutive errors', 'koz-static-translate' ); ?></th><td><input type="number" min="3" max="30" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[max_attempts]" value="<?php echo esc_attr( (string) $settings['max_attempts'] ); ?>"></td></tr>
					</table>
					<?php submit_button( __( 'Save settings', 'koz-static-translate' ) ); ?>
				</form>
			</div>

			<div class="koz-st-panel">
				<h2><?php echo esc_html__( 'Recent jobs', 'koz-static-translate' ); ?></h2>
				<table class="widefat striped"><thead><tr><th>ID</th><th><?php echo esc_html__( 'Page', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Language', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Category', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Status', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Progress', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Error', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Updated', 'koz-static-translate' ); ?></th></tr></thead><tbody>
				<?php if ( empty( $jobs ) ) : ?><tr><td colspan="8"><?php echo esc_html__( 'No jobs yet.', 'koz-static-translate' ); ?></td></tr><?php endif; ?>
				<?php foreach ( $jobs as $job ) : ?><tr><td><?php echo esc_html( (string) $job['id'] ); ?></td><td><?php echo esc_html( html_entity_decode( (string) $job['source_title'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ); ?></td><td><code><?php echo esc_html( $job['language'] ); ?></code></td><td><?php echo esc_html( $job['category'] ); ?></td><td><?php echo esc_html( $job['status'] ); ?></td><td><?php echo esc_html( $job['processed_segments'] . ' / ' . $job['total_segments'] ); ?></td><td><?php echo esc_html( self::short_text( (string) $job['last_error'], 160 ) ); ?></td><td><?php echo esc_html( (string) $job['updated_at'] ); ?></td></tr><?php endforeach; ?>
				</tbody></table>
			</div>

			<div class="koz-st-panel">
				<h2><?php echo esc_html__( 'Event log', 'koz-static-translate' ); ?></h2>
				<table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Date', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Level', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Event', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Language', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Characters', 'koz-static-translate' ); ?></th><th><?php echo esc_html__( 'Message', 'koz-static-translate' ); ?></th></tr></thead><tbody>
				<?php if ( empty( $logs ) ) : ?><tr><td colspan="6"><?php echo esc_html__( 'No events yet.', 'koz-static-translate' ); ?></td></tr><?php endif; ?>
				<?php foreach ( $logs as $log ) : ?><tr><td><?php echo esc_html( $log['created_at'] ); ?></td><td><?php echo esc_html( $log['level'] ); ?></td><td><?php echo esc_html( $log['event'] ); ?></td><td><code><?php echo esc_html( $log['language'] ); ?></code></td><td><?php echo esc_html( number_format_i18n( $log['characters'] ) ); ?></td><td><?php echo esc_html( self::short_text( $log['message'], 220 ) ); ?></td></tr><?php endforeach; ?>
				</tbody></table>
				<p><strong><?php echo esc_html__( 'Language sitemap:', 'koz-static-translate' ); ?></strong> <a href="<?php echo esc_url( home_url( '/uafree-translations-sitemap.xml' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( home_url( '/uafree-translations-sitemap.xml' ) ); ?></a></p>
			</div>
			<?php do_action( 'kozstx_admin_cleanup_section' ); ?>
		</div>
		<?php
	}

	private static function admin_script( int $reset_ts ): void {
		unset( $reset_ts );
	}

	private static function queue_stats(): array {
		global $wpdb;
		$tables = self::tables();
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$tables['queue']} GROUP BY status", ARRAY_A );
		$stats = array( 'queued' => 0, 'running' => 0, 'retry' => 0, 'paused' => 0, 'done' => 0, 'failed' => 0 );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$status = (string) $row['status'];
			if ( isset( $stats[ $status ] ) ) {
				$stats[ $status ] = (int) $row['total'];
			}
		}
		return $stats;
	}

	private static function inventory_stats(): array {
		global $wpdb;
		$tables = self::tables();
		$core_expected = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'"
		);
		$legacy_count = 0;
		$legacy_titles = $wpdb->get_col(
			"SELECT post_title FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'"
		);
		foreach ( is_array( $legacy_titles ) ? $legacy_titles : array() as $title ) {
			if ( preg_match( self::LEGACY_PAGE_PATTERN, html_entity_decode( (string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) {
				$legacy_count++;
			}
		}
		$core_expected = max( 0, $core_expected - $legacy_count );
		$report_expected = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_title LIKE %s",
				'Звіт на %'
			)
		);
		$core_sources = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['sources']} WHERE is_report = 0" );
		$report_sources = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['sources']} WHERE is_report = 1" );
		$pending_chars = (int) $wpdb->get_var(
			"SELECT COALESCE(SUM(s.source_chars),0)
			FROM {$tables['queue']} q
			INNER JOIN {$tables['sources']} s ON s.id = q.source_id
			WHERE q.status <> 'done'"
		);
		return array(
			'core_expected' => $core_expected,
			'report_expected' => $report_expected,
			'core_sources' => $core_sources,
			'report_sources' => $report_sources,
			'pending_chars' => $pending_chars,
		);
	}

	private static function language_stats(): array {
		global $wpdb;
		$tables = self::tables();
		$usage = self::current_usage();
		$result = array();
		foreach ( self::languages() as $slug => $language ) {
			$core_total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['queue']} WHERE language = %s AND category = 'core'", $slug ) );
			$core_done = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['queue']} WHERE language = %s AND category = 'core' AND status = 'done'", $slug ) );
			$report_total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['queue']} WHERE language = %s AND category = 'report'", $slug ) );
			$report_done = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['queue']} WHERE language = %s AND category = 'report' AND status = 'done'", $slug ) );
			$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['queue']} WHERE language = %s AND status <> 'done'", $slug ) );
			$newest = (string) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(report_date) FROM {$tables['queue']} WHERE language = %s AND category = 'report' AND status = 'done'", $slug ) );
			$oldest = (string) $wpdb->get_var( $wpdb->prepare( "SELECT MIN(report_date) FROM {$tables['queue']} WHERE language = %s AND category = 'report' AND status = 'done'", $slug ) );
			$used = (int) ( $usage['by_language'][ $slug ]['characters'] ?? 0 );
			$result[] = array(
				'slug' => $slug,
				'code' => (string) $language['code'],
				'name' => (string) $language['name'],
				'core_total' => $core_total,
				'core_done' => $core_done,
				'report_total' => $report_total,
				'report_done' => $report_done,
				'pending' => $pending,
				'newest_report' => $newest,
				'oldest_report' => $oldest,
				'used_chars' => $used,
			);
		}
		return $result;
	}

	private static function recent_jobs(): array {
		global $wpdb;
		$tables = self::tables();
		$rows = $wpdb->get_results(
			"SELECT q.*, s.source_title
			FROM {$tables['queue']} q
			LEFT JOIN {$tables['sources']} s ON s.id = q.source_id
			ORDER BY q.updated_at DESC, q.id DESC LIMIT 40",
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	private static function recent_logs(): array {
		global $wpdb;
		$tables = self::tables();
		$rows = $wpdb->get_results(
			"SELECT * FROM {$tables['logs']} ORDER BY id DESC LIMIT 50",
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	public static function ajax_test(): void {
		self::ajax_guard();
		$credentials = self::credentials();
		if ( ! $credentials['configured'] ) {
			wp_send_json_error( array( 'message' => __( 'Azure key is not configured.', 'koz-static-translate' ) ), 400 );
		}
		$usage = self::current_usage();
		if ( $usage['remaining'] < 50 ) {
			wp_send_json_error( array( 'message' => __( 'The monthly allowance is too low even for a test.', 'koz-static-translate' ) ), 400 );
		}
		$segment = array(
			'source_text' => self::source_language_slug() === 'uk' ? 'KOZ connection test' : 'KOZ connection test',
			'source_hash' => hash( 'sha256', self::source_language_slug() === 'uk' ? 'KOZ connection test' : 'KOZ connection test' ),
			'segment_key' => hash( 'sha256', 'test' ),
		);
		$test_slug = array_key_first( self::languages() );
		$test_language = self::languages()[ $test_slug ];
		$result = self::azure_translate_batch( array( $segment ), (string) $test_language['code'], $credentials );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		self::increment_usage( $test_slug, (int) $result['source_characters'] );
		self::log_event( 0, 0, $test_slug, 'info', 'api_test', (int) $result['source_characters'], 'Azure test successful.', array( 'request_id' => $result['request_id'], 'api_version' => $result['api_version'] ) );
		wp_send_json_success( array( 'message' => __( 'Azure API works. Response: ', 'koz-static-translate' ) . self::clean_api_translation( (string) $result['translations'][0] ) ) );
	}

	public static function ajax_run(): void {
		self::ajax_guard();
		wp_send_json_error(
			array( 'message' => __( 'Background translation processing is temporarily disabled in version 0.9.36.', 'koz-static-translate' ) ),
			409
		);
	}

	public static function ajax_rebuild(): void {
		self::ajax_guard();
		wp_send_json_error(
			array( 'message' => __( 'Inventory rebuild is temporarily disabled in version 0.9.36.', 'koz-static-translate' ) ),
			409
		);
	}

	public static function ajax_pause(): void {
		self::ajax_guard();
		$runtime = self::runtime();
		$runtime['manual_paused'] = empty( $runtime['manual_paused'] ) ? 1 : 0;
		self::save_runtime( $runtime );
		wp_send_json_success( array( 'message' => ! empty( $runtime['manual_paused'] ) ? __( 'The automatic queue is paused.', 'koz-static-translate' ) : __( 'The automatic queue is active.', 'koz-static-translate' ) ) );
	}


	public static function ajax_reconcile_page(): void {
		self::ajax_guard();
		check_ajax_referer( self::AJAX_NONCE );

		$raw_path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
		$raw_path = is_string( $raw_path ) ? trim( $raw_path ) : '';
		if ( '' === $raw_path ) {
			wp_send_json_error( array( 'message' => __( 'Enter a source page path, for example /ua-free/.', 'koz-static-translate' ) ), 400 );
		}

		$parsed_path = (string) wp_parse_url( $raw_path, PHP_URL_PATH );
		$path = '' !== $parsed_path ? $parsed_path : $raw_path;
		$path = '/' . ltrim( $path, '/' );
		if ( '/' !== $path ) {
			$path = trailingslashit( $path );
		}

		global $wpdb;
		$tables = self::tables();

		$source = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, post_id, source_path, source_title, local_hash, scan_status, is_report, report_date
				FROM {$tables['sources']}
				WHERE source_path = %s
				LIMIT 1",
				$path
			),
			ARRAY_A
		);

		if ( ! is_array( $source ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: source page path. */
						__( 'Source %s is not present in the current inventory. No scan was started.', 'koz-static-translate' ),
						$path
					),
				),
				404
			);
		}

		if ( 'ready' !== (string) $source['scan_status'] ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: source page path. */
						__( 'Source %s is not scan-ready. No scan, rebuild or Azure request was started.', 'koz-static-translate' ),
						$path
					),
				),
				409
			);
		}

		$ready_languages = array();
		$details = array();
		$now = current_time( 'mysql', true );
		$nonblocking_promoted = 0;

		$segment_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, segment_type, source_text, is_protected
				FROM {$tables['segments']}
				WHERE source_id = %d
				ORDER BY segment_order ASC, id ASC",
				(int) $source['id']
			),
			ARRAY_A
		);

		foreach ( is_array( $segment_rows ) ? $segment_rows : array() as $segment_row ) {
			if ( ! empty( $segment_row['is_protected'] ) ) {
				continue;
			}
			if (
				! self::is_readiness_nonblocking_segment(
					(string) $segment_row['source_text'],
					(string) $segment_row['segment_type']
				)
			) {
				continue;
			}

			$updated = $wpdb->update(
				$tables['segments'],
				array(
					'is_protected' => 1,
					'updated_at' => $now,
				),
				array( 'id' => (int) $segment_row['id'] )
			);
			if ( false !== $updated ) {
				$nonblocking_promoted++;
			}
		}

		foreach ( self::languages() as $slug => $language ) {
			$counts = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						COUNT(*) AS total,
						SUM(
							CASE
								WHEN s.is_protected = 1 THEN 1
								WHEN (
									t.id IS NOT NULL
									AND t.status = 'ready'
									AND t.source_hash = s.source_hash
									AND t.translated_text <> ''
								) THEN 1
								WHEN (
									m.id IS NOT NULL
									AND m.translated_text <> ''
								) THEN 1
								ELSE 0
							END
						) AS ready
					FROM {$tables['segments']} s
					LEFT JOIN {$tables['translations']} t
						ON t.source_id = s.source_id
						AND t.language = %s
						AND t.segment_key = s.segment_key
					LEFT JOIN {$tables['memory']} m
						ON m.language = %s
						AND m.source_hash = s.source_hash
					WHERE s.source_id = %d",
					$slug,
					$slug,
					(int) $source['id']
				),
				ARRAY_A
			);

			$total = (int) ( $counts['total'] ?? 0 );
			$ready = (int) ( $counts['ready'] ?? 0 );
			$complete = $total > 0 && $ready >= $total;

			$details[] = sprintf(
				'%s %d/%d',
				$slug,
				$ready,
				$total
			);

			if ( ! $complete ) {
				continue;
			}

			$ready_languages[] = $slug;
			$wpdb->update(
				$tables['queue'],
				array(
					'post_id' => (int) $source['post_id'],
					'language_order' => (int) $language['order'],
					'category' => ! empty( $source['is_report'] ) ? 'report' : 'core',
					'report_date' => $source['report_date'],
					'source_hash' => (string) $source['local_hash'],
					'status' => 'done',
					'processed_segments' => $ready,
					'total_segments' => $total,
					'last_error' => '',
					'next_run_at' => null,
					'locked_at' => null,
					'finished_at' => $now,
					'updated_at' => $now,
				),
				array(
					'source_id' => (int) $source['id'],
					'language' => $slug,
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: source path, 2: ready language count, 3: total language count, 4: non-blocking segments promoted, 5: per-language readiness details. */
					__( '%1$s checked locally. Fully ready languages: %2$d/%3$d. Non-blocking segments fixed: %4$d. Azure: 0. %5$s', 'koz-static-translate' ),
					$path,
					count( $ready_languages ),
					count( self::languages() ),
					$nonblocking_promoted,
					implode( ' · ', $details )
				),
				'ready_languages' => $ready_languages,
				'path' => $path,
			)
		);
	}


	public static function ajax_diagnose_page(): void {
		self::ajax_guard();
		check_ajax_referer( self::AJAX_NONCE );

		$raw_path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
		$raw_path = is_string( $raw_path ) ? trim( $raw_path ) : '';
		if ( '' === $raw_path ) {
			wp_send_json_error(
				array( 'message' => __( 'Enter a source page path, for example /ua-free/.', 'koz-static-translate' ) ),
				400
			);
		}

		$parsed_path = (string) wp_parse_url( $raw_path, PHP_URL_PATH );
		$path = '' !== $parsed_path ? $parsed_path : $raw_path;
		$path = '/' . ltrim( $path, '/' );
		if ( '/' !== $path ) {
			$path = trailingslashit( $path );
		}

		global $wpdb;
		$tables = self::tables();

		$source = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, source_path, source_title, scan_status
				FROM {$tables['sources']}
				WHERE source_path = %s
				LIMIT 1",
				$path
			),
			ARRAY_A
		);

		if ( ! is_array( $source ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: source page path. */
						__( 'Source %s is not present in the current inventory. No scan was started.', 'koz-static-translate' ),
						$path
					),
				),
				404
			);
		}

		$segments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					id,
					segment_key,
					segment_order,
					segment_type,
					source_text,
					source_hash,
					is_protected,
					occurrence_count
				FROM {$tables['segments']}
				WHERE source_id = %d
				ORDER BY segment_order ASC, id ASC",
				(int) $source['id']
			),
			ARRAY_A
		);

		$languages = self::languages();
		$missing_by_segment = array();

		foreach ( $languages as $slug => $language ) {
			$missing_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						s.segment_key
					FROM {$tables['segments']} s
					LEFT JOIN {$tables['translations']} t
						ON t.source_id = s.source_id
						AND t.language = %s
						AND t.segment_key = s.segment_key
					LEFT JOIN {$tables['memory']} m
						ON m.language = %s
						AND m.source_hash = s.source_hash
					WHERE s.source_id = %d
						AND s.is_protected = 0
						AND NOT (
							(
								t.id IS NOT NULL
								AND t.status = 'ready'
								AND t.source_hash = s.source_hash
								AND t.translated_text <> ''
							)
							OR (
								m.id IS NOT NULL
								AND m.translated_text <> ''
							)
						)",
					$slug,
					$slug,
					(int) $source['id']
				),
				ARRAY_A
			);

			foreach ( is_array( $missing_rows ) ? $missing_rows : array() as $missing_row ) {
				$key = (string) $missing_row['segment_key'];
				if ( ! isset( $missing_by_segment[ $key ] ) ) {
					$missing_by_segment[ $key ] = array();
				}
				$missing_by_segment[ $key ][] = $slug;
			}
		}

		$rows = array();
		foreach ( is_array( $segments ) ? $segments : array() as $segment ) {
			$key = (string) $segment['segment_key'];
			if ( empty( $missing_by_segment[ $key ] ) ) {
				continue;
			}

			$rows[] = array(
				'order' => (int) $segment['segment_order'],
				'type' => sanitize_key( (string) $segment['segment_type'] ),
				'text' => self::short_text( wp_strip_all_tags( (string) $segment['source_text'] ), 500 ),
				'occurrences' => (int) $segment['occurrence_count'],
				'missing_languages' => array_values( $missing_by_segment[ $key ] ),
				'missing_count' => count( $missing_by_segment[ $key ] ),
				'segment_key' => substr( $key, 0, 12 ),
			);

			if ( count( $rows ) >= 25 ) {
				break;
			}
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: source path, 2: number of missing source segments shown. */
					__( '%1$s diagnostic complete. Missing source segments shown: %2$d. Read-only. Azure: 0.', 'koz-static-translate' ),
					$path,
					count( $rows )
				),
				'path' => $path,
				'scan_status' => (string) $source['scan_status'],
				'total_segments' => is_array( $segments ) ? count( $segments ) : 0,
				'rows' => $rows,
			)
		);
	}





	private static function priority_core_normalized_path( array $source ): string {
		$path = isset( $source['source_path'] ) ? (string) $source['source_path'] : '/';
		$path = '/' . ltrim( $path, '/' );
		return '/' === $path ? '/' : trailingslashit( $path );
	}

	private static function priority_core_path_from_url( string $url ): string {
		$relative = wp_make_link_relative( $url );
		$path = (string) wp_parse_url( $relative, PHP_URL_PATH );
		$path = '/' . ltrim( $path, '/' );
		return '/' === $path ? '/' : trailingslashit( $path );
	}


	private static function priority_core_collect_internal_menu_items( array $items, string $home_host ): array {
		$entries = array();

		foreach ( $items as $item ) {
			if ( ! is_object( $item ) || empty( $item->url ) ) {
				continue;
			}

			$item_url = (string) $item->url;
			$item_host = (string) wp_parse_url( $item_url, PHP_URL_HOST );
			if ( '' !== $item_host && '' !== $home_host && 0 !== strcasecmp( $item_host, $home_host ) ) {
				continue;
			}

			$path = self::priority_core_path_from_url( $item_url );
			$entries[] = array(
				'path' => $path,
				'label' => ! empty( $item->title ) ? wp_strip_all_tags( (string) $item->title ) : $path,
			);
		}

		return $entries;
	}

	private static function priority_core_block_navigation_entries( string $home_host ): array {
		$best = array();

		$navigation_posts = get_posts(
			array(
				'post_type' => 'wp_navigation',
				'post_status' => 'publish',
				'numberposts' => 20,
				'orderby' => 'modified',
				'order' => 'DESC',
				'no_found_rows' => true,
			)
		);

		foreach ( is_array( $navigation_posts ) ? $navigation_posts : array() as $navigation_post ) {
			if ( ! is_object( $navigation_post ) || empty( $navigation_post->post_content ) ) {
				continue;
			}

			$blocks = parse_blocks( (string) $navigation_post->post_content );
			$entries = array();

			$walk = static function ( array $nodes ) use ( &$walk, &$entries, $home_host ): void {
				foreach ( $nodes as $block ) {
					if ( ! is_array( $block ) ) {
						continue;
					}

					$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
					$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

					if ( in_array( $name, array( 'core/navigation-link', 'core/navigation-submenu' ), true ) ) {
						$url = isset( $attrs['url'] ) ? (string) $attrs['url'] : '';
						if ( '' !== $url ) {
							$item_host = (string) wp_parse_url( $url, PHP_URL_HOST );
							if ( '' === $item_host || '' === $home_host || 0 === strcasecmp( $item_host, $home_host ) ) {
								$path = self::priority_core_path_from_url( $url );
								$label = isset( $attrs['label'] ) ? wp_strip_all_tags( (string) $attrs['label'] ) : $path;
								$entries[] = array(
									'path' => $path,
									'label' => $label,
								);
							}
						}
					}

					if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
						$walk( $block['innerBlocks'] );
					}
				}
			};

			$walk( $blocks );

			if ( count( $entries ) > count( $best ) ) {
				$best = $entries;
			}
		}

		return $best;
	}


	private static function priority_core_menu_entries(): array {
		$home_host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$home_path = self::priority_core_path_from_url( home_url( '/' ) );

		/*
		 * Build one universal navigation set from the strongest available
		 * WordPress navigation sources. Internal URLs only, exact paths only.
		 * No site-specific titles, slugs or page names.
		 */
		$merged = array();
		$seen = array();
		$source_rank = 0;

		$append_entries = static function ( array $entries ) use ( &$merged, &$seen, &$source_rank ): void {
			foreach ( $entries as $entry ) {
				$path = isset( $entry['path'] ) ? (string) $entry['path'] : '';
				if ( '' === $path || isset( $seen[ $path ] ) ) {
					continue;
				}

				$merged[] = array(
					'path' => $path,
					'label' => isset( $entry['label'] ) ? (string) $entry['label'] : $path,
					'source_rank' => $source_rank,
				);
				$seen[ $path ] = true;
			}
			$source_rank++;
		};

		$locations = get_nav_menu_locations();
		$registered = get_registered_nav_menus();
		$assigned_menu_ids = array();

		/*
		 * 1) Merge all assigned locations that look like primary/main/header
		 * navigation. This supports themes with split desktop/mobile menus.
		 */
		foreach ( is_array( $locations ) ? $locations : array() as $location => $menu_id ) {
			$menu_id = absint( $menu_id );
			if ( $menu_id <= 0 ) {
				continue;
			}

			$location_label = isset( $registered[ $location ] ) ? (string) $registered[ $location ] : '';
			$haystack = strtolower( (string) $location . ' ' . $location_label );
			$is_navigation_location = false;

			foreach ( array( 'primary', 'main', 'header', 'top', 'navigation', 'nav' ) as $keyword ) {
				if ( false !== strpos( $haystack, $keyword ) ) {
					$is_navigation_location = true;
					break;
				}
			}

			if ( ! $is_navigation_location ) {
				continue;
			}

			$assigned_menu_ids[ $menu_id ] = true;
			$items = wp_get_nav_menu_items(
				$menu_id,
				array( 'update_post_term_cache' => false )
			);
			$append_entries(
				self::priority_core_collect_internal_menu_items(
					is_array( $items ) ? $items : array(),
					$home_host
				)
			);
		}

		/*
		 * 2) Add the richest unassigned classic menu as a builder fallback.
		 * Builders often render a classic menu without a registered theme
		 * location. Only one richest menu is merged to avoid footer/menu noise.
		 */
		$best_unassigned = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			if ( ! is_object( $menu ) || empty( $menu->term_id ) ) {
				continue;
			}

			$menu_id = (int) $menu->term_id;
			if ( isset( $assigned_menu_ids[ $menu_id ] ) ) {
				continue;
			}

			$items = wp_get_nav_menu_items(
				$menu_id,
				array( 'update_post_term_cache' => false )
			);
			$entries = self::priority_core_collect_internal_menu_items(
				is_array( $items ) ? $items : array(),
				$home_host
			);

			if ( count( $entries ) > count( $best_unassigned ) ) {
				$best_unassigned = $entries;
			}
		}
		if ( ! empty( $best_unassigned ) ) {
			$append_entries( $best_unassigned );
		}

		/*
		 * 3) Merge the richest block-theme Navigation source.
		 */
		$block_entries = self::priority_core_block_navigation_entries( $home_host );
		if ( ! empty( $block_entries ) ) {
			$append_entries( $block_entries );
		}

		/*
		 * If no navigation source was discovered, Home remains the only
		 * Priority Core entry. Otherwise, preserve first-seen navigation order.
		 */
		$entries = array(
			array(
				'path' => $home_path,
				'label' => __( 'Home', 'koz-static-translate' ),
				'rank' => 0,
			),
		);
		$final_seen = array( $home_path => true );
		$rank = 1;

		foreach ( $merged as $entry ) {
			$path = (string) $entry['path'];
			if ( isset( $final_seen[ $path ] ) ) {
				continue;
			}

			$entries[] = array(
				'path' => $path,
				'label' => (string) $entry['label'],
				'rank' => $rank,
			);
			$final_seen[ $path ] = true;
			$rank++;
		}

		return $entries;
	}

	private static function priority_core_meta( array $source ): array {
		$path = self::priority_core_normalized_path( $source );

		foreach ( self::priority_core_menu_entries() as $entry ) {
			if ( $path !== (string) $entry['path'] ) {
				continue;
			}

			return array(
				'is_priority' => true,
				'rank' => (int) $entry['rank'],
				'label' => (string) $entry['label'],
			);
		}

		return array(
			'is_priority' => false,
			'rank' => 999,
			'label' => '',
		);
	}

	private static function compare_priority_core( array $a, array $b ): int {
		$meta_a = self::priority_core_meta( $a );
		$meta_b = self::priority_core_meta( $b );

		if ( $meta_a['rank'] !== $meta_b['rank'] ) {
			return $meta_a['rank'] <=> $meta_b['rank'];
		}

		return (int) $a['id'] <=> (int) $b['id'];
	}

	public static function ajax_reconcile_core_batch(): void {
		self::ajax_guard();
		check_ajax_referer( self::AJAX_NONCE );

		$cursor = isset( $_POST['cursor'] ) ? absint( wp_unslash( $_POST['cursor'] ) ) : 0;
		$limit = 4;

		global $wpdb;
		$tables = self::tables();

		/*
		 * Read only the small non-report core inventory, then keep ONLY the
		 * explicit Priority Core set. Secondary/legacy core URLs are ignored
		 * completely by this endpoint.
		 */
		$sources = $wpdb->get_results(
			"SELECT
				s.id,
				s.post_id,
				s.source_path,
				s.source_title,
				s.local_hash,
				s.scan_status,
				s.is_report,
				s.report_date
			FROM {$tables['sources']} s
			WHERE s.scan_status = 'ready'
				AND s.is_report = 0
				AND EXISTS (
					SELECT 1
					FROM {$tables['queue']} q
					WHERE q.source_id = s.id
						AND q.category = 'core'
				)
			ORDER BY s.id ASC",
			ARRAY_A
		);

		$all_core = array();
		foreach ( is_array( $sources ) ? $sources : array() as $candidate ) {
			$meta = self::priority_core_meta( $candidate );
			if ( empty( $meta['is_priority'] ) ) {
				continue;
			}
			$all_core[] = $candidate;
		}

		usort( $all_core, array( __CLASS__, 'compare_priority_core' ) );

		$total_core = count( $all_core );
		$rows = array_slice( $all_core, $cursor, $limit );

		if ( empty( $rows ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'Priority Core pass reached the end. Only Home and pages linked from the merged internal navigation sources were checked. Secondary core and reports were not touched.', 'koz-static-translate' ),
					'next_cursor' => 0,
					'pass_complete' => true,
					'processed_sources' => 0,
					'queue_jobs_completed' => 0,
					'results' => array(),
				)
			);
		}

		$processed_sources = 0;
		$queue_jobs_completed = 0;
		$next_cursor = $cursor + count( $rows );
		$results = array();
		$now = current_time( 'mysql', true );

		foreach ( $rows as $source ) {
			$source_id = (int) $source['id'];
			$processed_sources++;

			$nonblocking_promoted = 0;
			$segment_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, segment_type, source_text, is_protected
					FROM {$tables['segments']}
					WHERE source_id = %d
					ORDER BY segment_order ASC, id ASC",
					$source_id
				),
				ARRAY_A
			);

			foreach ( is_array( $segment_rows ) ? $segment_rows : array() as $segment_row ) {
				if ( ! empty( $segment_row['is_protected'] ) ) {
					continue;
				}
				if (
					! self::is_readiness_nonblocking_segment(
						(string) $segment_row['source_text'],
						(string) $segment_row['segment_type']
					)
				) {
					continue;
				}

				$updated = $wpdb->update(
					$tables['segments'],
					array(
						'is_protected' => 1,
						'updated_at' => $now,
					),
					array( 'id' => (int) $segment_row['id'] )
				);
				if ( false !== $updated ) {
					$nonblocking_promoted++;
				}
			}

			$ready_languages = 0;
			$incomplete_languages = array();

			foreach ( self::languages() as $slug => $language ) {
				$counts = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT
							COUNT(*) AS total,
							SUM(
								CASE
									WHEN s.is_protected = 1 THEN 1
									WHEN (
										t.id IS NOT NULL
										AND t.status = 'ready'
										AND t.source_hash = s.source_hash
										AND t.translated_text <> ''
									) THEN 1
									WHEN (
										m.id IS NOT NULL
										AND m.translated_text <> ''
									) THEN 1
									ELSE 0
								END
							) AS ready
						FROM {$tables['segments']} s
						LEFT JOIN {$tables['translations']} t
							ON t.source_id = s.source_id
							AND t.language = %s
							AND t.segment_key = s.segment_key
						LEFT JOIN {$tables['memory']} m
							ON m.language = %s
							AND m.source_hash = s.source_hash
						WHERE s.source_id = %d",
						$slug,
						$slug,
						$source_id
					),
					ARRAY_A
				);

				$total = (int) ( $counts['total'] ?? 0 );
				$ready = (int) ( $counts['ready'] ?? 0 );
				$complete = $total > 0 && $ready >= $total;

				if ( ! $complete ) {
					$incomplete_languages[] = sprintf( '%s %d/%d', $slug, $ready, $total );
					continue;
				}

				$ready_languages++;
				$previous_status = (string) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT status
						FROM {$tables['queue']}
						WHERE source_id = %d
							AND language = %s
						LIMIT 1",
						$source_id,
						$slug
					)
				);

				$wpdb->update(
					$tables['queue'],
					array(
						'post_id' => (int) $source['post_id'],
						'language_order' => (int) $language['order'],
						'category' => 'core',
						'report_date' => $source['report_date'],
						'source_hash' => (string) $source['local_hash'],
						'status' => 'done',
						'processed_segments' => $ready,
						'total_segments' => $total,
						'last_error' => '',
						'next_run_at' => null,
						'locked_at' => null,
						'finished_at' => $now,
						'updated_at' => $now,
					),
					array(
						'source_id' => $source_id,
						'language' => $slug,
					)
				);

				if ( 'done' !== $previous_status ) {
					$queue_jobs_completed++;
				}
			}

			$priority_meta = self::priority_core_meta( $source );
			$missing_segments_max = 0;
			foreach ( $incomplete_languages as $item ) {
				if ( preg_match( '/\s(\d+)\/(\d+)$/', $item, $matches ) ) {
					$missing_segments_max = max(
						$missing_segments_max,
						max( 0, (int) $matches[2] - (int) $matches[1] )
					);
				}
			}

			$results[] = array(
				'path' => (string) $source['source_path'],
				'priority_label' => (string) $priority_meta['label'],
				'priority_rank' => (int) $priority_meta['rank'] + 1,
				'ready_languages' => $ready_languages,
				'total_languages' => count( self::languages() ),
				'nonblocking_fixed' => $nonblocking_promoted,
				'missing_segments_max' => $missing_segments_max,
				'incomplete' => array_slice( $incomplete_languages, 0, 3 ),
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: number of core sources checked, 2: newly completed queue jobs. */
					__( 'Priority Core batch complete: %1$d page(s) checked, %2$d queue job(s) newly marked ready. Azure: 0.', 'koz-static-translate' ),
					$processed_sources,
					$queue_jobs_completed
				),
				'next_cursor' => $next_cursor,
				'pass_complete' => $next_cursor >= $total_core,
				'processed_sources' => $processed_sources,
				'queue_jobs_completed' => $queue_jobs_completed,
				'results' => $results,
			)
		);
	}


	public static function ajax_priority_core_discovery(): void {
		self::ajax_guard();
		check_ajax_referer( self::AJAX_NONCE );

		global $wpdb;
		$tables = self::tables();

		$entries = self::priority_core_menu_entries();
		$rows = array();

		foreach ( $entries as $entry ) {
			$path = isset( $entry['path'] ) ? (string) $entry['path'] : '';
			if ( '' === $path ) {
				continue;
			}

			$source = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, source_path, source_title, scan_status, is_report
					FROM {$tables['sources']}
					WHERE source_path = %s
					LIMIT 1",
					$path
				),
				ARRAY_A
			);

			$queue_category = '';
			$queue_statuses = array();

			if ( is_array( $source ) ) {
				$queue_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT category, status, COUNT(*) AS jobs
						FROM {$tables['queue']}
						WHERE source_id = %d
						GROUP BY category, status
						ORDER BY category ASC, status ASC",
						(int) $source['id']
					),
					ARRAY_A
				);

				foreach ( is_array( $queue_rows ) ? $queue_rows : array() as $queue_row ) {
					$category = isset( $queue_row['category'] ) ? (string) $queue_row['category'] : '';
					if ( '' === $queue_category && '' !== $category ) {
						$queue_category = $category;
					}
					$queue_statuses[] = sprintf(
						'%s:%s=%d',
						$category,
						isset( $queue_row['status'] ) ? (string) $queue_row['status'] : '',
						isset( $queue_row['jobs'] ) ? (int) $queue_row['jobs'] : 0
					);
				}
			}

			$rows[] = array(
				'rank' => isset( $entry['rank'] ) ? (int) $entry['rank'] + 1 : 0,
				'label' => isset( $entry['label'] ) ? (string) $entry['label'] : $path,
				'path' => $path,
				'source_found' => is_array( $source ),
				'source_id' => is_array( $source ) ? (int) $source['id'] : 0,
				'scan_status' => is_array( $source ) ? (string) $source['scan_status'] : '',
				'is_report' => is_array( $source ) ? (int) $source['is_report'] : 0,
				'queue_category' => $queue_category,
				'queue_statuses' => $queue_statuses,
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of detected Priority Core navigation entries. */
					__( 'Priority Core discovery complete: %d internal navigation entries detected. Read-only. Azure: 0.', 'koz-static-translate' ),
					count( $rows )
				),
				'rows' => $rows,
			)
		);
	}


	public static function ajax_prepare_priority_core_source(): void {
		self::ajax_guard();
		check_ajax_referer( self::AJAX_NONCE );

		if ( self::migration_freeze_active() ) {
			wp_send_json_error(
				array( 'message' => __( 'Source preparation is unavailable while migration freeze is active.', 'koz-static-translate' ) ),
				409
			);
		}

		global $wpdb;
		$tables = self::tables();
		$entries = self::priority_core_menu_entries();
		$selected = null;
		$source = null;

		foreach ( $entries as $entry ) {
			$path = isset( $entry['path'] ) ? (string) $entry['path'] : '';
			if ( '' === $path ) {
				continue;
			}

			$candidate = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT *
					FROM {$tables['sources']}
					WHERE source_path = %s
					LIMIT 1",
					$path
				),
				ARRAY_A
			);

			/*
			 * Universal one-page inventory recovery: if a detected internal
			 * navigation URL is a real WordPress page but has no source row,
			 * register that one page only. No site-specific slugs/titles.
			 */
			if ( ! is_array( $candidate ) ) {
				$url = home_url( ltrim( $path, '/' ) );
				$post_id = (int) url_to_postid( $url );
				if ( $post_id > 0 ) {
					$source_id = self::upsert_source_post( $post_id, true );
					if ( $source_id > 0 ) {
						$candidate = self::source_row( $source_id );
					}
				}
			}

			if (
				! is_array( $candidate )
				|| ! empty( $candidate['is_report'] )
			) {
				continue;
			}

			$has_core_queue = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$tables['queue']}
					WHERE source_id = %d
						AND category = 'core'",
					(int) $candidate['id']
				)
			);
			if ( $has_core_queue <= 0 ) {
				continue;
			}

			if ( 'ready' === (string) $candidate['scan_status'] ) {
				continue;
			}

			$selected = $entry;
			$source = $candidate;
			break;
		}

		if ( ! is_array( $source ) || ! is_array( $selected ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'No pending Priority Core source needs preparation. Readiness reconciliation may continue for already scanned pages.', 'koz-static-translate' ),
					'prepared' => false,
					'azure_requests' => 0,
				)
			);
		}

		$before_status = (string) $source['scan_status'];
		$scan_ok = self::scan_source( $source );
		$source = self::source_row( (int) $source['id'] );

		if ( ! $scan_ok || ! is_array( $source ) || 'ready' !== (string) $source['scan_status'] ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: 1: Priority Core label, 2: source path. */
						__( 'Could not prepare Priority Core source %1$s (%2$s). No Azure request was made.', 'koz-static-translate' ),
						isset( $selected['label'] ) ? (string) $selected['label'] : '',
						isset( $selected['path'] ) ? (string) $selected['path'] : ''
					),
					'prepared' => false,
					'azure_requests' => 0,
				),
				500
			);
		}

		$done_jobs = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$tables['queue']}
				WHERE source_id = %d
					AND category = 'core'
					AND status = 'done'",
				(int) $source['id']
			)
		);
		$total_jobs = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$tables['queue']}
				WHERE source_id = %d
					AND category = 'core'",
				(int) $source['id']
			)
		);

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: Priority Core label, 2: source path, 3: previous scan status, 4: completed core jobs, 5: total core jobs. */
					__( '%1$s (%2$s) prepared: scan %3$s → ready; local readiness %4$d/%5$d. Azure: 0.', 'koz-static-translate' ),
					isset( $selected['label'] ) ? (string) $selected['label'] : '',
					isset( $selected['path'] ) ? (string) $selected['path'] : '',
					$before_status,
					$done_jobs,
					$total_jobs
				),
				'prepared' => true,
				'path' => isset( $selected['path'] ) ? (string) $selected['path'] : '',
				'label' => isset( $selected['label'] ) ? (string) $selected['label'] : '',
				'scan_status' => (string) $source['scan_status'],
				'done_jobs' => $done_jobs,
				'total_jobs' => $total_jobs,
				'azure_requests' => 0,
			)
		);
	}

	private static function ajax_guard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'koz-static-translate' ) ), 403 );
		}
		check_ajax_referer( self::AJAX_NONCE );
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
	}


	public static function public_status(): array {
		$settings = self::settings();
		$runtime = self::runtime();
		return array(
			'version' => KOZSTX_VERSION,
			'source_language' => self::source_language_slug(),
			'target_languages' => array_keys( self::languages() ),
			'content_post_types' => self::content_post_types(),
			'content_scope' => self::content_scope(),
			'auto_enabled' => false,
			'routes_enabled' => ! empty( $settings['routes_enabled'] ),
			'switcher_enabled' => ! empty( $settings['switcher_enabled'] ),
			'dynamic_content_enabled' => ! empty( $settings['dynamic_content_enabled'] ),
			'migration_frozen' => self::migration_freeze_active(),
			'last_run_at' => (string) ( $runtime['last_run_at'] ?? '' ),
			'last_success_at' => (string) ( $runtime['last_success_at'] ?? '' ),
			'last_error' => (string) ( $runtime['last_error'] ?? '' ),
		);
	}

	private static function normalize_text( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/[\x{00A0}\x{200B}\x{FEFF}]/u', ' ', $text );
		$text = preg_replace( '/\s+/u', ' ', (string) $text );
		return trim( (string) $text );
	}

	private static function segment_type_for_element( DOMElement $element ): string {
		$tag = strtolower( $element->tagName );
		if ( preg_match( '/^h[1-6]$/', $tag ) ) {
			return 'heading';
		}
		if ( 'a' === $tag ) {
			return 'link';
		}
		if ( in_array( $tag, array( 'button', 'input' ), true ) ) {
			return 'button';
		}
		if ( 'label' === $tag ) {
			return 'label';
		}
		if ( 'li' === $tag ) {
			return 'list_item';
		}
		if ( in_array( $tag, array( 'p', 'blockquote' ), true ) ) {
			return 'paragraph';
		}
		return 'text';
	}

	private static function safe_context( string $value ): string {
		$value = self::normalize_text( $value );
		return self::string_length( $value ) > 200 ? self::string_substr( $value, 0, 200 ) : $value;
	}

	private static function url_path( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return '' === $path ? '/' : $path;
	}

	private static function lower( string $value ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	private static function string_length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	private static function string_substr( string $value, int $start, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, $start, $length, 'UTF-8' ) : substr( $value, $start, $length );
	}

	private static function short_text( string $value, int $limit ): string {
		$value = self::normalize_text( $value );
		return self::string_length( $value ) > $limit ? self::string_substr( $value, 0, $limit - 1 ) . '…' : $value;
	}
}
