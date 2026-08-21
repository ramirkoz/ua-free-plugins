<?php
namespace ramirkz\koztdiag;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZTDIAG_Translate_Diagnostics {
	private const PAGE_SLUG = 'koz-translate-diagnostics';
	private const EXPORT_ACTION = 'koztdiag_export';
	private const DEEP_SCAN_QUERY = 'koztdiag_deep';
	private const DEEP_SCAN_NONCE = 'koztdiag_deep';
	private const TEXT_DOMAIN = 'koz-translate-diagnostics';
	private const CURRENT_TRANSLATOR_CLASS = '\\ramirkz\\kozstx\\KOZSTX_Static_Translate';
	private const LEGACY_TRANSLATOR_CLASS = 'UAFree_Static_Translate_Autonomous';

	private static array $table_exists_cache = array();
	private static array $table_columns_cache = array();
	private static array $table_status_cache = array();

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 30 );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'export_json' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KOZTDIAG_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function admin_menu(): void {
		$parent = KOZTDIAG_Suite_Registry::suite_page();
		add_submenu_page(
			$parent,
			__( 'Translation Diagnostics', 'koz-translate-diagnostics' ),
			__( 'Translation Diagnostics', 'koz-translate-diagnostics' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'admin_page' )
		);
	}

	public static function action_links( array $links ): array {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' .
			esc_html__( 'Diagnostics', 'koz-translate-diagnostics' ) .
			'</a>'
		);
		return $links;
	}

	private static function preferred_table( string $current_suffix, string $legacy_suffix ): string {
		global $wpdb;
		$current = $wpdb->prefix . $current_suffix;
		if ( self::table_exists( $current ) ) {
			return $current;
		}
		return $wpdb->prefix . $legacy_suffix;
	}

	private static function tables(): array {
		return array(
			'sources'      => self::preferred_table( 'kozstx_sources', 'uafree_st_sources' ),
			'segments'     => self::preferred_table( 'kozstx_source_segments', 'uafree_st_source_segments' ),
			'translations' => self::preferred_table( 'kozstx_translations', 'uafree_st_translations' ),
			'memory'       => self::preferred_table( 'kozstx_memory', 'uafree_st_memory' ),
			'queue'        => self::preferred_table( 'kozstx_queue', 'uafree_st_queue' ),
			'usage'        => self::preferred_table( 'kozstx_usage', 'uafree_st_usage' ),
			'logs'         => self::preferred_table( 'kozstx_logs', 'uafree_st_logs' ),
		);
	}

	private static function prepare_sql( string $query, array $args = array() ): string {
		global $wpdb;
		if ( empty( $args ) ) {
			return $query;
		}
		return (string) $wpdb->prepare( $query, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query templates and identifier arguments are defined by this read-only diagnostics class.
	}

	private static function table_status( string $table ): array {
		global $wpdb;
		if ( array_key_exists( $table, self::$table_status_cache ) ) {
			return self::$table_status_cache[ $table ];
		}
		$query = self::prepare_sql( 'SHOW TABLE STATUS LIKE %s', array( $wpdb->esc_like( $table ) ) );
		$row = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only metadata query; request-local cache is used above.
		self::$table_status_cache[ $table ] = is_array( $row ) ? $row : array();
		return self::$table_status_cache[ $table ];
	}

	private static function table_exists( string $table ): bool {
		if ( array_key_exists( $table, self::$table_exists_cache ) ) {
			return self::$table_exists_cache[ $table ];
		}
		self::$table_exists_cache[ $table ] = ! empty( self::table_status( $table ) );
		return self::$table_exists_cache[ $table ];
	}

	private static function table_columns( string $table ): array {
		global $wpdb;
		if ( array_key_exists( $table, self::$table_columns_cache ) ) {
			return self::$table_columns_cache[ $table ];
		}
		if ( ! self::table_exists( $table ) ) {
			self::$table_columns_cache[ $table ] = array();
			return array();
		}
		$query = self::prepare_sql( 'SHOW COLUMNS FROM %i', array( $table ) );
		$rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only schema query; request-local cache is used above.
		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$field = (string) ( $row['Field'] ?? '' );
			if ( '' !== $field ) {
				$result[] = $field;
			}
		}
		self::$table_columns_cache[ $table ] = $result;
		return $result;
	}

	private static function has_columns( string $table, array $required ): bool {
		$columns = self::table_columns( $table );
		return empty( array_diff( $required, $columns ) );
	}

	private static function database_version(): string {
		global $wpdb;
		return (string) $wpdb->get_var( 'SELECT VERSION()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only environment diagnostic.
	}

	private static function translator_plugin(): array {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();
		$active = (array) get_option( 'active_plugins', array() );
		$network_active = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
		$matches = array();

		foreach ( $plugins as $file => $plugin ) {
			$name = (string) ( $plugin['Name'] ?? '' );
			if ( false === stripos( $name, 'KOZ Static Translate' ) && false === stripos( $name, 'UA FREE Static Translate' ) && false === stripos( (string) $file, 'koz-static-translate' ) && false === stripos( (string) $file, 'ua-free-static-translate' ) ) {
				continue;
			}
			$is_network_active = isset( $network_active[ $file ] );
			$matches[] = array(
				'component_fingerprint' => self::fingerprint( (string) $file, 16 ),
				'name' => self::redact_preview( $name, 120 ),
				'version' => self::safe_version( (string) ( $plugin['Version'] ?? '' ) ),
				'active' => in_array( $file, $active, true ) || $is_network_active,
				'network_active' => $is_network_active,
			);
		}

		$current_loaded = class_exists( self::CURRENT_TRANSLATOR_CLASS, false );
		$legacy_loaded = class_exists( self::LEGACY_TRANSLATOR_CLASS, false );
		$current_api = $current_loaded && method_exists( self::CURRENT_TRANSLATOR_CLASS, 'public_status' );
		$legacy_api = $legacy_loaded && method_exists( self::LEGACY_TRANSLATOR_CLASS, 'public_status' );
		$version = '';
		if ( defined( 'KOZSTX_VERSION' ) ) {
			$version = self::safe_version( (string) constant( 'KOZSTX_VERSION' ) );
		} elseif ( defined( 'UAFREE_ST_VERSION' ) ) {
			$version = self::safe_version( (string) constant( 'UAFREE_ST_VERSION' ) );
		}

		return array(
			'matches' => $matches,
			'constant_version' => $version,
			'class_loaded' => $current_loaded || $legacy_loaded,
			'public_api_available' => $current_api || $legacy_api,
			'api_generation' => $current_api ? 'kozstx' : ( $legacy_api ? 'legacy' : 'none' ),
		);
	}

	private static function translator_api_status( bool $deep = false ): array {
		if ( ! $deep ) {
			return array( 'status_source' => 'deferred_to_deep' );
		}

		$class = '';
		if ( class_exists( self::CURRENT_TRANSLATOR_CLASS, false ) && method_exists( self::CURRENT_TRANSLATOR_CLASS, 'public_status' ) ) {
			$class = self::CURRENT_TRANSLATOR_CLASS;
		} elseif ( class_exists( self::LEGACY_TRANSLATOR_CLASS, false ) && method_exists( self::LEGACY_TRANSLATOR_CLASS, 'public_status' ) ) {
			$class = self::LEGACY_TRANSLATOR_CLASS;
		}
		if ( '' === $class ) {
			return array();
		}

		try {
			$status = call_user_func( array( $class, 'public_status' ) );
			return is_array( $status ) ? self::normalize_translator_api_status( $status ) : array();
		} catch ( \Throwable $error ) {
			return array(
				'api_error' => self::redact_preview( $error->getMessage(), 240 ),
			);
		}
	}

	private static function normalize_translator_api_status( array $status ): array {
		$safe = array(
			'contract_version' => 1,
			'version' => self::safe_version( (string) ( $status['version'] ?? '' ) ),
			'source_language' => sanitize_key( (string) ( $status['source_language'] ?? '' ) ),
			'target_languages' => self::normalize_languages( (array) ( $status['target_languages'] ?? array() ) ),
			'content_post_types' => array_values( array_filter( array_map( 'sanitize_key', (array) ( $status['content_post_types'] ?? array() ) ) ) ),
			'content_scope' => sanitize_key( (string) ( $status['content_scope'] ?? '' ) ),
			'auto_enabled' => ! empty( $status['auto_enabled'] ),
			'routes_enabled' => ! empty( $status['routes_enabled'] ),
			'switcher_enabled' => ! empty( $status['switcher_enabled'] ),
			'dynamic_content_enabled' => ! empty( $status['dynamic_content_enabled'] ),
			'migration_frozen' => ! empty( $status['migration_frozen'] ),
			'last_run_at' => self::safe_timestamp( (string) ( $status['last_run_at'] ?? '' ) ),
			'last_success_at' => self::safe_timestamp( (string) ( $status['last_success_at'] ?? '' ) ),
		);
		if ( '' !== (string) ( $status['last_error'] ?? '' ) ) {
			$safe['last_error'] = self::redact_preview( (string) $status['last_error'], 300 );
		}
		return $safe;
	}

	private static function safe_version( string $version ): string {
		if ( '' === $version || $version !== trim( $version ) || strlen( $version ) > 64 ) {
			return '';
		}

		if ( ! preg_match( '/^v?\d{1,6}(?:\.\d{1,6}){1,3}(?:-([0-9A-Za-z][0-9A-Za-z-]*(?:\.[0-9A-Za-z][0-9A-Za-z-]*)*))?(?:\+([0-9A-Za-z][0-9A-Za-z-]*(?:\.[0-9A-Za-z][0-9A-Za-z-]*)*))?$/D', $version, $matches ) ) {
			return '';
		}

		$allowed_labels = array(
			'dev', 'alpha', 'beta', 'rc', 'pre', 'preview', 'nightly', 'canary',
			'stable', 'release', 'hotfix', 'foundation', 'safe', 'public',
			'universal', 'legacy', 'build',
		);

		foreach ( array( (string) ( $matches[1] ?? '' ), (string) ( $matches[2] ?? '' ) ) as $suffix ) {
			if ( '' === $suffix ) {
				continue;
			}
			foreach ( preg_split( '/[.-]+/', strtolower( $suffix ) ) ?: array() as $label ) {
				if ( '' === $label || ctype_digit( $label ) ) {
					continue;
				}
				if ( preg_match( '/^(dev|alpha|beta|rc|pre|preview|nightly|canary|hotfix|build)\d+$/', $label ) ) {
					continue;
				}
				if ( ! in_array( $label, $allowed_labels, true ) ) {
					return '';
				}
			}
		}

		return $version;
	}

	private static function is_version_report_key( string $key ): bool {
		return in_array( strtolower( $key ), array( 'db_version_option', 'rewrite_version_option' ), true );
	}

	private static function safe_timestamp( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		return preg_match( '/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2}(?:[.+-][A-Za-z0-9:]+|Z)?)?$/', $value ) ? $value : '';
	}

	private static function translator_option( string $current, string $legacy, mixed $default = false ): mixed {
		$value = get_option( $current, false );
		if ( false !== $value ) {
			return $value;
		}
		return get_option( $legacy, $default );
	}

	private static function safe_settings(): array {
		$settings = self::translator_option( 'kozstx_settings', 'uafree_st_auto_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		$known_keys = array(
			'source_language', 'target_languages', 'content_post_types', 'content_scope',
			'auto_enabled', 'routes_enabled', 'switcher_enabled', 'dynamic_content_enabled',
			'azure_key_enc', 'azure_key', 'azure_endpoint', 'azure_region', 'monthly_limit',
		);
		return array(
			'source_language' => sanitize_key( (string) ( $settings['source_language'] ?? '' ) ),
			'target_languages' => self::normalize_languages( (array) ( $settings['target_languages'] ?? array() ) ),
			'content_post_types' => array_values( array_filter( array_map( 'sanitize_key', (array) ( $settings['content_post_types'] ?? array() ) ) ) ),
			'content_scope' => sanitize_key( (string) ( $settings['content_scope'] ?? '' ) ),
			'auto_enabled' => ! empty( $settings['auto_enabled'] ),
			'routes_enabled' => ! empty( $settings['routes_enabled'] ),
			'switcher_enabled' => ! empty( $settings['switcher_enabled'] ),
			'dynamic_content_enabled' => ! empty( $settings['dynamic_content_enabled'] ),
			'azure_key_configured' => ! empty( $settings['azure_key_enc'] ) || ! empty( $settings['azure_key'] ),
			'azure_endpoint_configured' => ! empty( $settings['azure_endpoint'] ),
			'azure_region_configured' => ! empty( $settings['azure_region'] ),
			'monthly_limit' => isset( $settings['monthly_limit'] ) ? (int) $settings['monthly_limit'] : 0,
			'unknown_key_count' => count( array_diff( array_keys( $settings ), $known_keys ) ),
		);
	}

	private static function safe_runtime(): array {
		$runtime = self::translator_option( 'kozstx_runtime', 'uafree_st_auto_runtime', array() );
		$runtime = is_array( $runtime ) ? $runtime : array();
		$allowed = array(
			'manual_paused', 'pause_until', 'pause_reason', 'last_error', 'last_run_at',
			'last_success_at', 'last_inventory_at', 'last_core_rescan_at',
			'active_source_language', 'last_worker_started_at', 'last_worker_finished_at',
		);
		$safe = array();
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $runtime ) ) {
				continue;
			}
			$value = $runtime[ $key ];
			if ( 'last_error' === $key || 'pause_reason' === $key ) {
				$value = self::redact_preview( (string) $value, 300 );
			} elseif ( str_contains( $key, '_at' ) ) {
				$value = self::safe_timestamp( (string) $value );
			}
			$safe[ $key ] = is_scalar( $value ) || null === $value ? $value : self::sanitize_recursive( $value );
		}
		$safe['unknown_key_count'] = count( array_diff( array_keys( $runtime ), $allowed ) );
		return $safe;
	}

	private static function sanitize_recursive( mixed $value, string $parent_key = '' ): mixed {
		if ( is_array( $value ) ) {
			$result = array();
			foreach ( $value as $key => $item ) {
				$key_string = (string) $key;
				if ( self::is_version_report_key( $key_string ) ) {
					$result[ $key ] = is_string( $item ) ? self::safe_version( $item ) : '';
					continue;
				}
				if ( self::is_sensitive_key( $key_string ) ) {
					$result[ $key ] = is_bool( $item ) || is_int( $item ) || is_float( $item ) ? $item : '[REDACTED]';
					continue;
				}
				$result[ $key ] = self::sanitize_recursive( $item, $key_string );
			}
			return $result;
		}
		if ( is_object( $value ) ) {
			return self::sanitize_recursive( get_object_vars( $value ), $parent_key );
		}
		if ( is_string( $value ) ) {
			return self::redact_preview( $value, 500 );
		}
		return $value;
	}

	private static function is_sensitive_key( string $key ): bool {
		$key = strtolower( $key );
		if ( str_ends_with( $key, '_fingerprint' ) || str_ends_with( $key, '_configured' ) || str_ends_with( $key, '_count' ) ) {
			return false;
		}
		return (bool) preg_match( '/(?:^|_)(?:api[_-]?key|key|token|secret|password|authorization|credential|file|path|url|uri|host|domain|table|table_name|relation|query|sql|option|option_name|hook|transient)(?:$|_)/i', $key );
	}

	private static function merge_filter_values( mixed $original, mixed $filtered ): mixed {
		if ( is_array( $original ) ) {
			if ( ! is_array( $filtered ) ) {
				return $original;
			}
			$result = array();
			foreach ( $original as $key => $value ) {
				$result[ $key ] = array_key_exists( $key, $filtered )
					? self::merge_filter_values( $value, $filtered[ $key ] )
					: $value;
			}
			return $result;
		}

		if ( is_bool( $original ) && is_bool( $filtered ) ) {
			return $filtered;
		}
		if ( is_int( $original ) && is_int( $filtered ) ) {
			return $filtered;
		}
		if ( is_float( $original ) && ( is_float( $filtered ) || is_int( $filtered ) ) ) {
			return (float) $filtered;
		}
		if ( null === $original && null === $filtered ) {
			return null;
		}

		// String replacements and type changes from third-party filters are ignored.
		// This prevents an extension from inserting paths, credentials or identifiers
		// into the privacy-safe JSON export through an existing report field.
		return $original;
	}

	private static function fingerprint( string $value, int $length = 16 ): string {
		$length = max( 8, min( 64, $length ) );
		return substr( hash_hmac( 'sha256', $value, wp_salt( 'nonce' ) ), 0, $length );
	}

	private static function redact_preview( string $text, int $limit = 180 ): string {
		$text = wp_strip_all_tags( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$text = (string) preg_replace( '/\bAuthorization\s*:\s*(?:(?:Bearer|Basic|Digest|Token)\s+)?[^\s,;]+/iu', 'Authorization: [REDACTED]', $text );
		$text = (string) preg_replace( '/\b(?:Bearer|Basic)\s+[A-Za-z0-9._~+\/=:-]+/iu', '[REDACTED]', $text );
		$text = (string) preg_replace( '/((?:api[_ -]?key|subscription[_ -]?key|token|secret|password|credential)\s*[:=]\s*)[^\s,;]+/iu', '$1[REDACTED]', $text );
		$patterns = array(
			'#https?://[^\s<>"\']+#iu',
			'~\b[A-Za-z]:[\\\\/](?:[^\\\\/\r\n]+[\\\\/])*[^\\\\/\r\n]*~u',
			'~(?<![A-Za-z0-9])\\\\\\\\(?:[^\\\\\r\n]+\\\\)+[^\\\\\r\n]*~u',
			'~(?<![A-Za-z0-9:\\/])(?:[A-Za-z0-9._-]+\\\\)+(?:[A-Za-z0-9._-]+)(?![A-Za-z0-9\\\\])~u',
			'#(?<![A-Za-z0-9])/(?:[^/\s]+/)+[^/\s]*#u',
			'#(?<![A-Za-z0-9:/])(?:[A-Za-z0-9._-]+/)+(?:[A-Za-z0-9._-]+)(?![A-Za-z0-9/])#u',
			'/\b(?:[A-Za-z0-9-]+\.)+[A-Za-z]{2,63}\b/u',
			'/\b(?:wp_[A-Za-z0-9_]+|[A-Za-z][A-Za-z0-9]*_[A-Za-z0-9_]+_[A-Za-z0-9_]+)\b/u',
			'/\b(?:FROM|JOIN|UPDATE|INTO|TABLE|RELATION)\s+[`"\[]?[A-Za-z0-9_.-]+[`"\]]?/iu',
			'/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/u',
			'/\b[A-Z]{2}\d{2}(?:[ -]?[A-Z0-9]){11,30}\b/iu',
			'/\b(?:\d[ -]?){12,19}\b/u',
			'/\b0x[A-Fa-f0-9]{32,}\b/u',
			'/\b[13][a-km-zA-HJ-NP-Z1-9]{25,42}\b/u',
			'/\bT[A-Za-z0-9]{32,35}\b/u',
			'/\b[1-9A-HJ-NP-Za-km-z]{32,120}\b/u',
		);
		$text = (string) preg_replace( $patterns, '[REDACTED]', $text );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $limit, 'UTF-8' );
		}
		return substr( $text, 0, $limit );
	}

	private static function normalize_languages( array $languages ): array {
		$result = array();
		foreach ( $languages as $language ) {
			$language = strtolower( trim( (string) $language ) );
			if ( preg_match( '/^[a-z][a-z0-9-]{1,15}$/', $language ) ) {
				$result[] = $language;
			}
		}
		return array_values( array_unique( $result ) );
	}

	private static function detected_languages( array $api_status, array $settings, bool $deep = false ): array {
		global $wpdb;
		$languages = array();
		if ( isset( $api_status['target_languages'] ) && is_array( $api_status['target_languages'] ) ) {
			$languages = array_merge( $languages, $api_status['target_languages'] );
		}
		$languages = array_merge( $languages, (array) ( $settings['target_languages'] ?? array() ) );
		if ( ! $deep ) {
			return self::normalize_languages( $languages );
		}

		$tables = self::tables();
		foreach ( array( 'translations', 'memory', 'queue', 'usage', 'logs' ) as $key ) {
			$table = $tables[ $key ];
			if ( ! self::table_exists( $table ) || ! in_array( 'language', self::table_columns( $table ), true ) ) {
				continue;
			}
			$query = self::prepare_sql( "SELECT DISTINCT language FROM %i WHERE language <> '' ORDER BY language ASC LIMIT 200", array( $table ) );
			$rows = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only diagnostic query.
			$languages = array_merge( $languages, is_array( $rows ) ? $rows : array() );
		}
		return self::normalize_languages( $languages );
	}

	private static function language_name( string $language ): string {
		$catalog = array(
			'uk' => 'Українська', 'en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français',
			'es' => 'Español', 'pt' => 'Português', 'it' => 'Italiano', 'pl' => 'Polski',
			'cs' => 'Čeština', 'sk' => 'Slovenčina', 'ro' => 'Română', 'hu' => 'Magyar',
			'lt' => 'Lietuvių', 'lv' => 'Latviešu', 'et' => 'Eesti', 'sv' => 'Svenska',
			'no' => 'Norsk', 'fi' => 'Suomi', 'da' => 'Dansk', 'zh' => '简体中文',
			'ja' => '日本語', 'ko' => '한국어', 'ar' => 'العربية', 'he' => 'עברית',
			'tr' => 'Türkçe', 'id' => 'Bahasa Indonesia', 'hi' => 'हिन्दी', 'ru' => 'Русский',
		);
		return $catalog[ $language ] ?? strtoupper( $language );
	}

	private static function table_snapshot( bool $deep = false ): array {
		global $wpdb;
		$result = array();
		foreach ( self::tables() as $key => $table ) {
			$status = self::table_status( $table );
			$exists = ! empty( $status );
			$rows = 0;
			$estimated = false;
			$columns = array();

			if ( $exists ) {
				if ( $deep ) {
					$query = self::prepare_sql( 'SELECT COUNT(*) FROM %i', array( $table ) );
					$rows = (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only aggregate.
					$columns = self::table_columns( $table );
				} else {
					$rows = (int) ( $status['Rows'] ?? 0 );
					$estimated = true;
				}
			}

			$result[ $key ] = array(
				'name_fingerprint' => self::fingerprint( $table, 16 ),
				'exists' => $exists,
				'rows' => $rows,
				'rows_estimated' => $estimated,
				'schema_checked' => $deep,
				'column_count' => count( $columns ),
				'schema_fingerprint' => $deep ? self::fingerprint( implode( '|', $columns ), 20 ) : '',
			);
		}
		return $result;
	}

	private static function source_summary( bool $deep = false ): array {
		global $wpdb;
		$table = self::tables()['sources'];
		if ( ! self::table_exists( $table ) ) {
			return array(
				'total' => 0,
				'total_estimated' => false,
				'aggregates_performed' => false,
				'by_status' => array(),
				'by_post_type' => array(),
				'recent' => array(),
			);
		}
		$columns = self::table_columns( $table );
		$status = self::table_status( $table );
		$total = (int) ( $status['Rows'] ?? 0 );
		if ( $deep ) {
			$query = self::prepare_sql( 'SELECT COUNT(*) FROM %i', array( $table ) );
			$total = (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only aggregate.
		}
		$summary = array(
			'total' => $total,
			'total_estimated' => ! $deep,
			'aggregates_performed' => $deep,
			'by_status' => array(),
			'by_post_type' => array(),
			'recent' => array(),
		);

		if ( $deep && in_array( 'scan_status', $columns, true ) ) {
			$query = self::prepare_sql( 'SELECT scan_status AS item, COUNT(*) AS total FROM %i GROUP BY scan_status ORDER BY total DESC', array( $table ) );
			$rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only aggregate.
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$summary['by_status'][ (string) $row['item'] ] = (int) $row['total'];
			}
		}
		if ( $deep && in_array( 'post_type', $columns, true ) ) {
			$query = self::prepare_sql( 'SELECT post_type AS item, COUNT(*) AS total FROM %i GROUP BY post_type ORDER BY total DESC', array( $table ) );
			$rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only aggregate.
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$summary['by_post_type'][ (string) $row['item'] ] = (int) $row['total'];
			}
		}

		$selectable = array_intersect(
			array( 'id', 'post_id', 'post_type', 'source_path', 'scan_status', 'segment_count', 'source_chars', 'last_error', 'last_scanned_at', 'updated_at' ),
			$columns
		);
		if ( ! empty( $selectable ) ) {
			$order_column = in_array( 'updated_at', $columns, true ) ? 'updated_at' : ( in_array( 'id', $columns, true ) ? 'id' : '' );
			$placeholders = implode( ', ', array_fill( 0, count( $selectable ), '%i' ) );
			$query_template = 'SELECT ' . $placeholders . ' FROM %i';
			$query_args = array_values( $selectable );
			$query_args[] = $table;
			if ( '' !== $order_column ) {
				$query_template .= ' ORDER BY %i DESC';
				$query_args[] = $order_column;
			}
			$query_template .= ' LIMIT %d';
			$query_args[] = $deep ? 50 : 10;
			$query = self::prepare_sql( $query_template, $query_args ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifier placeholders are generated from a fixed allowlist.
			$rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only diagnostic query.
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				if ( isset( $row['source_path'] ) ) {
					$row['source_path_fingerprint'] = self::path_fingerprint( (string) $row['source_path'] );
					unset( $row['source_path'] );
				}
				if ( isset( $row['last_error'] ) ) {
					$row['last_error'] = self::redact_preview( (string) $row['last_error'], 200 );
				}
				$summary['recent'][] = $row;
			}
		}
		return $summary;
	}

	private static function path_fingerprint( string $value ): string {
		$path = (string) wp_parse_url( $value, PHP_URL_PATH );
		if ( '' === $path ) {
			$path = $value;
		}
		$path = '/' . ltrim( rawurldecode( $path ), '/' );
		return self::fingerprint( $path, 20 );
	}


	private static function queue_summary( bool $deep = false ): array {
		global $wpdb;
		if ( ! $deep ) {
			return array( '_meta' => array( 'aggregates_performed' => false ) );
		}
		$table = self::tables()['queue'];
		if ( ! self::table_exists( $table ) || ! self::has_columns( $table, array( 'language', 'status' ) ) ) {
			return array( '_meta' => array( 'aggregates_performed' => true ) );
		}
		$columns = self::table_columns( $table );
		if ( in_array( 'last_error', $columns, true ) ) {
			$query = self::prepare_sql(
				"SELECT language, status, COUNT(*) AS total, SUM(CASE WHEN last_error <> '' THEN 1 ELSE 0 END) AS errors FROM %i GROUP BY language, status ORDER BY language ASC, status ASC",
				array( $table )
			);
		} else {
			$query = self::prepare_sql(
				'SELECT language, status, COUNT(*) AS total, 0 AS errors FROM %i GROUP BY language, status ORDER BY language ASC, status ASC',
				array( $table )
			);
		}
		$rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only aggregate.
		$result = array( '_meta' => array( 'aggregates_performed' => true ) );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$language = strtolower( (string) $row['language'] );
			$status = (string) $row['status'];
			$result[ $language ][ $status ] = array(
				'total' => (int) $row['total'],
				'errors' => (int) $row['errors'],
			);
		}
		return $result;
	}

	private static function translation_summary( array $languages, bool $deep = false ): array {
		if ( ! $deep ) {
			$result = array();
			foreach ( $languages as $language ) {
				$result[ $language ] = array(
					'name' => self::language_name( $language ),
					'counts_performed' => false,
					'expected_translatable_segments' => null,
					'protected_segments' => null,
					'translation_rows' => null,
					'exact_ready_rows' => null,
					'effective_ready_segments' => null,
					'memory_fallback_segments' => null,
					'non_ready_rows' => null,
					'empty_rows' => null,
					'stale_rows' => null,
					'hash_validation_performed' => false,
					'memory_rows' => null,
					'memory_ready_rows' => null,
					'missing_estimate' => null,
				);
			}
			return $result;
		}
		global $wpdb;
		$tables = self::tables();
		$result = array();
		$expected = 0;
		$protected = 0;

		if ( self::table_exists( $tables['segments'] ) && self::has_columns( $tables['segments'], array( 'is_protected' ) ) ) {
			$query = self::prepare_sql(
				'SELECT COUNT(*) AS total, SUM(CASE WHEN is_protected = 1 THEN 1 ELSE 0 END) AS protected FROM %i',
				array( $tables['segments'] )
			);
			$row = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only aggregate.
			$expected = max( 0, (int) ( $row['total'] ?? 0 ) - (int) ( $row['protected'] ?? 0 ) );
			$protected = (int) ( $row['protected'] ?? 0 );
		}

		$translation_rows = array();
		$hash_validation_performed = false;
		if ( self::table_exists( $tables['translations'] ) && self::has_columns( $tables['translations'], array( 'language', 'status', 'translated_text', 'source_hash', 'source_id', 'segment_key' ) ) ) {
			if (
				$deep
				&& self::table_exists( $tables['segments'] )
				&& self::has_columns( $tables['segments'], array( 'source_id', 'segment_key', 'source_hash' ) )
			) {
				$hash_validation_performed = true;
				$query = self::prepare_sql(
					"SELECT t.language,
						COUNT(*) AS total,
						SUM(CASE WHEN t.status = 'ready' AND t.translated_text <> '' AND s.segment_key IS NOT NULL AND t.source_hash = s.source_hash THEN 1 ELSE 0 END) AS ready,
						SUM(CASE WHEN t.status <> 'ready' THEN 1 ELSE 0 END) AS non_ready,
						SUM(CASE WHEN t.translated_text = '' THEN 1 ELSE 0 END) AS empty_rows,
						SUM(CASE WHEN s.segment_key IS NOT NULL AND t.source_hash <> s.source_hash THEN 1 ELSE 0 END) AS stale
					FROM %i t
					LEFT JOIN %i s
						ON s.source_id = t.source_id AND s.segment_key = t.segment_key
					GROUP BY t.language
					ORDER BY t.language ASC",
					array( $tables['translations'], $tables['segments'] )
				);
				$translation_rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only aggregate.
			} else {
				$query = self::prepare_sql(
					"SELECT language,
						COUNT(*) AS total,
						SUM(CASE WHEN status = 'ready' AND translated_text <> '' THEN 1 ELSE 0 END) AS ready,
						SUM(CASE WHEN status <> 'ready' THEN 1 ELSE 0 END) AS non_ready,
						SUM(CASE WHEN translated_text = '' THEN 1 ELSE 0 END) AS empty_rows
					FROM %i
					GROUP BY language
					ORDER BY language ASC",
					array( $tables['translations'] )
				);
				$translation_rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only aggregate.
			}
		}

		$memory = array();
		if ( self::table_exists( $tables['memory'] ) && self::has_columns( $tables['memory'], array( 'language', 'translated_text' ) ) ) {
			$query = self::prepare_sql( "SELECT language, COUNT(*) AS total, SUM(CASE WHEN translated_text <> '' THEN 1 ELSE 0 END) AS ready FROM %i GROUP BY language", array( $tables['memory'] ) );
			$rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only aggregate.
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$memory[ strtolower( (string) $row['language'] ) ] = array(
					'total' => (int) $row['total'],
					'ready' => (int) $row['ready'],
				);
			}
		}

		$indexed = array();
		foreach ( is_array( $translation_rows ) ? $translation_rows : array() as $row ) {
			$indexed[ strtolower( (string) $row['language'] ) ] = $row;
		}

		$effective = array();
		$effective_available = self::table_exists( $tables['segments'] )
			&& self::has_columns( $tables['segments'], array( 'source_id', 'segment_key', 'source_hash', 'is_protected' ) )
			&& self::table_exists( $tables['translations'] )
			&& self::has_columns( $tables['translations'], array( 'source_id', 'language', 'segment_key', 'source_hash', 'translated_text', 'status' ) );
		$effective_memory_available = self::table_exists( $tables['memory'] )
			&& self::has_columns( $tables['memory'], array( 'language', 'source_hash', 'translated_text' ) );
		if ( $effective_available ) {
			foreach ( $languages as $language ) {
				if ( $effective_memory_available ) {
					$query = self::prepare_sql(
						"SELECT
							SUM(CASE WHEN NULLIF(t.translated_text, '') IS NOT NULL OR NULLIF(m.translated_text, '') IS NOT NULL THEN 1 ELSE 0 END) AS effective_ready,
							SUM(CASE WHEN NULLIF(t.translated_text, '') IS NULL AND NULLIF(m.translated_text, '') IS NOT NULL THEN 1 ELSE 0 END) AS memory_fallback
						FROM %i s
						LEFT JOIN %i t ON t.source_id = s.source_id AND t.language = %s AND t.segment_key = s.segment_key AND t.status = 'ready' AND t.source_hash = s.source_hash
						LEFT JOIN %i m ON m.language = %s AND m.source_hash = s.source_hash
						WHERE s.is_protected = 0",
						array( $tables['segments'], $tables['translations'], $language, $tables['memory'], $language )
					);
				} else {
					$query = self::prepare_sql(
						"SELECT
							SUM(CASE WHEN NULLIF(t.translated_text, '') IS NOT NULL THEN 1 ELSE 0 END) AS effective_ready,
							0 AS memory_fallback
						FROM %i s
						LEFT JOIN %i t ON t.source_id = s.source_id AND t.language = %s AND t.segment_key = s.segment_key AND t.status = 'ready' AND t.source_hash = s.source_hash
						WHERE s.is_protected = 0",
						array( $tables['segments'], $tables['translations'], $language )
					);
				}
				$effective_row = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only effective translation aggregate.
				$effective[ $language ] = array(
					'ready' => (int) ( $effective_row['effective_ready'] ?? 0 ),
					'memory_fallback' => (int) ( $effective_row['memory_fallback'] ?? 0 ),
				);
			}
		}

		foreach ( $languages as $language ) {
			$row = $indexed[ $language ] ?? array();
			$ready = (int) ( $row['ready'] ?? 0 );
			$effective_ready = (int) ( $effective[ $language ]['ready'] ?? $ready );
			$result[ $language ] = array(
				'name' => self::language_name( $language ),
				'counts_performed' => true,
				'expected_translatable_segments' => $expected,
				'protected_segments' => $protected,
				'translation_rows' => (int) ( $row['total'] ?? 0 ),
				'exact_ready_rows' => $ready,
				'effective_ready_segments' => $effective_ready,
				'memory_fallback_segments' => (int) ( $effective[ $language ]['memory_fallback'] ?? 0 ),
				'non_ready_rows' => (int) ( $row['non_ready'] ?? 0 ),
				'empty_rows' => (int) ( $row['empty_rows'] ?? 0 ),
				'stale_rows' => $hash_validation_performed ? (int) ( $row['stale'] ?? 0 ) : null,
				'hash_validation_performed' => $hash_validation_performed,
				'memory_rows' => (int) ( $memory[ $language ]['total'] ?? 0 ),
				'memory_ready_rows' => (int) ( $memory[ $language ]['ready'] ?? 0 ),
				'missing_estimate' => max( 0, $expected - $effective_ready ),
			);
		}
		return $result;
	}

	private static function usage_summary( bool $deep = false ): array {
		global $wpdb;
		if ( ! $deep ) {
			return array( 'checked' => false, 'rows' => array() );
		}
		$table = self::tables()['usage'];
		if ( ! self::table_exists( $table ) ) {
			return array( 'checked' => true, 'rows' => array() );
		}
		$columns = self::table_columns( $table );
		$select = array_intersect( array( 'cycle_key', 'language', 'characters', 'requests', 'last_request_at', 'updated_at' ), $columns );
		if ( empty( $select ) ) {
			return array( 'checked' => true, 'rows' => array() );
		}
		$order_column = in_array( 'cycle_key', $columns, true ) ? 'cycle_key' : '';
		$placeholders = implode( ', ', array_fill( 0, count( $select ), '%i' ) );
		$query_template = 'SELECT ' . $placeholders . ' FROM %i';
		$query_args = array_values( $select );
		$query_args[] = $table;
		if ( '' !== $order_column ) {
			$query_template .= ' ORDER BY %i DESC';
			$query_args[] = $order_column;
		}
		$query_template .= ' LIMIT 200';
		$query = self::prepare_sql( $query_template, $query_args ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifier placeholders are generated from a fixed allowlist.
		$rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only diagnostic query.
		return array( 'checked' => true, 'rows' => is_array( $rows ) ? $rows : array() );
	}

	private static function recent_errors( bool $deep = false ): array {
		global $wpdb;
		$table = self::tables()['logs'];
		if ( ! self::table_exists( $table ) ) {
			return array();
		}
		$columns = self::table_columns( $table );
		$select = array_intersect( array( 'id', 'source_id', 'language', 'level', 'event', 'characters', 'message', 'created_at' ), $columns );
		if ( empty( $select ) ) {
			return array();
		}
		$filter_levels = in_array( 'level', $columns, true );
		$order_column = in_array( 'id', $columns, true ) ? 'id' : ( in_array( 'created_at', $columns, true ) ? 'created_at' : '' );
		$placeholders = implode( ', ', array_fill( 0, count( $select ), '%i' ) );
		$query_template = 'SELECT ' . $placeholders . ' FROM %i';
		$query_args = array_values( $select );
		$query_args[] = $table;
		if ( $filter_levels ) {
			$query_template .= ' WHERE level IN (%s, %s)';
			$query_args[] = 'error';
			$query_args[] = 'warning';
		}
		if ( '' !== $order_column ) {
			$query_template .= ' ORDER BY %i DESC';
			$query_args[] = $order_column;
		}
		$query_template .= ' LIMIT %d';
		$query_args[] = $deep ? 50 : 10;
		$query = self::prepare_sql( $query_template, $query_args ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifier placeholders are generated from a fixed allowlist.
		$rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only diagnostic query.
		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( isset( $row['message'] ) ) {
				$row['message'] = self::redact_preview( (string) $row['message'], 300 );
			}
			$result[] = $row;
		}
		return $result;
	}

	private static function cron_snapshot(): array {
		$cron = _get_cron_array();
		$results = array();
		foreach ( is_array( $cron ) ? $cron : array() as $timestamp => $hooks ) {
			foreach ( (array) $hooks as $hook => $events ) {
				if ( false === stripos( (string) $hook, 'kozstx' ) && false === stripos( (string) $hook, 'uafree_st' ) && false === stripos( (string) $hook, 'uafree_static' ) ) {
					continue;
				}
				foreach ( (array) $events as $event ) {
					$results[] = array(
						'hook_fingerprint' => self::fingerprint( (string) $hook, 16 ),
						'timestamp' => (int) $timestamp,
						'utc' => gmdate( 'c', (int) $timestamp ),
						'schedule' => sanitize_key( (string) ( $event['schedule'] ?? '' ) ),
						'interval' => (int) ( $event['interval'] ?? 0 ),
					);
				}
			}
		}
		return $results;
	}

	private static function transient_snapshot( bool $deep = false ): array {
		global $wpdb;
		if ( ! $deep ) {
			return array( 'checked' => false, 'fingerprints' => array() );
		}
		$patterns = array(
			'_transient_kozstx_%',
			'_transient_timeout_kozstx_%',
			'_transient_uafree_st_%',
			'_transient_timeout_uafree_st_%',
		);
		$query = self::prepare_sql(
			'SELECT option_name FROM %i WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s ORDER BY option_name ASC LIMIT 500',
			array( $wpdb->options, $patterns[0], $patterns[1], $patterns[2], $patterns[3] )
		);
		$rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only diagnostic query.
		$fingerprints = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$fingerprints[] = self::fingerprint( (string) ( $row['option_name'] ?? '' ), 16 );
		}
		return array( 'checked' => true, 'fingerprints' => array_values( array_unique( $fingerprints ) ) );
	}

	private static function hints( array $translator, array $api_status, array $settings, array $runtime, array $tables, array $sources, array $languages, array $queue, bool $deep ): array {
		$hints = array();
		if ( empty( $translator['matches'] ) ) {
			$hints[] = __( 'KOZ Static Translate was not detected.', 'koz-translate-diagnostics' );
		}
		if ( ! empty( $translator['matches'] ) && empty( array_filter( array_column( $translator['matches'], 'active' ) ) ) ) {
			$hints[] = __( 'The translator plugin is installed but inactive.', 'koz-translate-diagnostics' );
		}
		if ( empty( $translator['public_api_available'] ) ) {
			$hints[] = __( 'The translator public diagnostics API is unavailable; the report uses the read-only database compatibility layer.', 'koz-translate-diagnostics' );
		}
		if ( isset( $api_status['api_error'] ) ) {
			$hints[] = __( 'The translator public diagnostics API returned an error.', 'koz-translate-diagnostics' );
		}
		if ( ! empty( $api_status['migration_frozen'] ) ) {
			$hints[] = __( 'The translator reports an active migration freeze.', 'koz-translate-diagnostics' );
		}
		$missing_tables = array();
		foreach ( $tables as $key => $table ) {
			if ( empty( $table['exists'] ) ) {
				$missing_tables[] = $key;
			}
		}
		if ( ! empty( $missing_tables ) ) {
			$hints[] = sprintf(
				/* translators: %s: comma-separated list of missing translator database tables. */
				__( 'Missing translator tables: %s.', 'koz-translate-diagnostics' ),
				implode( ', ', $missing_tables )
			);
		}
		if ( empty( $settings['azure_key_configured'] ) ) {
			$hints[] = __( 'The Azure Translator key is not configured.', 'koz-translate-diagnostics' );
		}
		if ( ! empty( $runtime['manual_paused'] ) ) {
			$hints[] = __( 'Automatic translation is manually paused.', 'koz-translate-diagnostics' );
		}
		if ( ! empty( $runtime['pause_until'] ) && time() < (int) $runtime['pause_until'] ) {
			$hints[] = __( 'Automatic translation is temporarily paused.', 'koz-translate-diagnostics' );
		}
		if ( 'monthly_limit' === (string) ( $runtime['pause_reason'] ?? '' ) ) {
			$hints[] = __( 'Automatic translation is paused because the configured monthly character limit was reached.', 'koz-translate-diagnostics' );
		}
		if ( 0 === (int) ( $sources['total'] ?? 0 ) ) {
			$hints[] = __( 'No translation sources were found.', 'koz-translate-diagnostics' );
		}
		if ( ! $deep ) {
			$hints[] = __( 'The quick scan does not compare translation hashes. Run the deep scan before treating stale-row counts as verified.', 'koz-translate-diagnostics' );
		}
		foreach ( $languages as $language => $data ) {
			if ( null !== ( $data['missing_estimate'] ?? null ) && $data['missing_estimate'] > 0 ) {
				$hints[] = sprintf(
					/* translators: 1: language code, 2: approximate number of missing translation rows. */
					__( '%1$s: approximately %2$d current translation rows are missing.', 'koz-translate-diagnostics' ),
					strtoupper( $language ),
					$data['missing_estimate']
				);
			}
			if ( null !== $data['stale_rows'] && $data['stale_rows'] > 0 ) {
				$hints[] = sprintf(
					/* translators: 1: language code, 2: number of translation rows with a stale source hash. */
					__( '%1$s: %2$d translation rows have a stale source hash.', 'koz-translate-diagnostics' ),
					strtoupper( $language ),
					$data['stale_rows']
				);
			}
			$queue_errors = 0;
			foreach ( (array) ( $queue[ $language ] ?? array() ) as $status ) {
				$queue_errors += (int) ( $status['errors'] ?? 0 );
			}
			if ( $queue_errors > 0 ) {
				$hints[] = sprintf(
					/* translators: 1: language code, 2: number of queue rows containing an error. */
					__( '%1$s: %2$d queue rows contain an error.', 'koz-translate-diagnostics' ),
					strtoupper( $language ),
					$queue_errors
				);
			}
		}
		return array_values( array_unique( $hints ) );
	}



	private static function language_script_signal( string $text, string $language, string $source_text = '' ): string {
		$text = trim( wp_strip_all_tags( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$source_text = trim( wp_strip_all_tags( html_entity_decode( $source_text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		if ( '' === $text ) {
			return 'empty';
		}

		$normalized = static function ( string $value ): string {
			$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
			return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
		};
		if ( '' !== $source_text && $normalized( $text ) === $normalized( $source_text ) ) {
			return 'source_equal';
		}

		$count = static function ( string $pattern, string $value ): int {
			$matches = array();
			return preg_match_all( $pattern, $value, $matches ) ?: 0;
		};
		$latin = $count( '/\p{Latin}/u', $text );
		$cyrillic = $count( '/\p{Cyrillic}/u', $text );
		$arabic = $count( '/\p{Arabic}/u', $text );
		$han = $count( '/\p{Han}/u', $text );
		$hiragana = $count( '/\p{Hiragana}/u', $text );
		$katakana = $count( '/\p{Katakana}/u', $text );
		$devanagari = $count( '/\p{Devanagari}/u', $text );
		$total = max( 1, $latin + $cyrillic + $arabic + $han + $hiragana + $katakana + $devanagari );
		$language = strtolower( $language );

		if ( in_array( $language, array( 'en', 'de', 'fr', 'es', 'pt', 'id', 'it', 'pl', 'cs', 'sk', 'ro', 'hu', 'lt', 'lv', 'et', 'sv', 'no', 'fi', 'da' ), true ) ) {
			if ( ( $cyrillic + $arabic + $han + $hiragana + $katakana + $devanagari ) / $total >= 0.25 ) {
				return 'mismatch';
			}
			return $latin > 0 ? 'match' : 'unknown';
		}
		if ( 'zh' === $language ) {
			return $han > 0 ? 'match' : ( $cyrillic > 0 ? 'mismatch' : 'unknown' );
		}
		if ( 'ja' === $language ) {
			return ( $han + $hiragana + $katakana ) > 0 ? 'match' : ( $cyrillic > 0 ? 'mismatch' : 'unknown' );
		}
		if ( 'ar' === $language ) {
			return $arabic > 0 ? 'match' : ( $cyrillic > 0 ? 'mismatch' : 'unknown' );
		}
		if ( 'hi' === $language ) {
			return $devanagari > 0 ? 'match' : ( $cyrillic > 0 ? 'mismatch' : 'unknown' );
		}
		return 'unknown';
	}

	private static function translation_readiness( array $languages, bool $deep = false ): array {
		$result = array(
			'checked' => $deep,
			'available' => false,
			'scope' => 'critical_metadata_h1_plus_effective_render_coverage',
			'summary' => array(),
			'blockers' => array(),
			'blocker_limit' => 100,
		);
		if ( ! $deep || empty( $languages ) ) {
			return $result;
		}

		global $wpdb;
		$tables = self::tables();
		if (
			! self::table_exists( $tables['sources'] )
			|| ! self::table_exists( $tables['segments'] )
			|| ! self::table_exists( $tables['translations'] )
			|| ! self::has_columns( $tables['sources'], array( 'id', 'post_id', 'scan_status' ) )
			|| ! self::has_columns( $tables['segments'], array( 'source_id', 'segment_key', 'segment_type', 'source_hash', 'source_text', 'context_json', 'is_protected' ) )
			|| ! self::has_columns( $tables['translations'], array( 'source_id', 'language', 'segment_key', 'source_hash', 'translated_text', 'status' ) )
		) {
			return $result;
		}
		$result['available'] = true;
		$critical_types = array( 'document_title', 'meta_description', 'meta_og_title', 'meta_og_description', 'heading' );

		foreach ( $languages as $language ) {
			$language = sanitize_key( (string) $language );
			if ( '' === $language ) {
				continue;
			}

			$memory_available = self::table_exists( $tables['memory'] )
				&& self::has_columns( $tables['memory'], array( 'language', 'source_hash', 'translated_text' ) );
			if ( $memory_available ) {
				$coverage_query = self::prepare_sql(
					"SELECT s.id AS source_id, s.post_id, s.scan_status,
						COUNT(seg.id) AS total_segments,
						SUM(CASE WHEN NULLIF(t.translated_text, '') IS NOT NULL OR NULLIF(m.translated_text, '') IS NOT NULL THEN 1 ELSE 0 END) AS ready_segments
					FROM %i s
					INNER JOIN %i seg ON seg.source_id = s.id AND seg.is_protected = 0
					LEFT JOIN %i t ON t.source_id = s.id AND t.language = %s AND t.segment_key = seg.segment_key AND t.status = 'ready' AND t.source_hash = seg.source_hash
					LEFT JOIN %i m ON m.language = %s AND m.source_hash = seg.source_hash
					GROUP BY s.id, s.post_id, s.scan_status
					ORDER BY s.id ASC",
					array( $tables['sources'], $tables['segments'], $tables['translations'], $language, $tables['memory'], $language )
				);
			} else {
				$coverage_query = self::prepare_sql(
					"SELECT s.id AS source_id, s.post_id, s.scan_status,
						COUNT(seg.id) AS total_segments,
						SUM(CASE WHEN NULLIF(t.translated_text, '') IS NOT NULL THEN 1 ELSE 0 END) AS ready_segments
					FROM %i s
					INNER JOIN %i seg ON seg.source_id = s.id AND seg.is_protected = 0
					LEFT JOIN %i t ON t.source_id = s.id AND t.language = %s AND t.segment_key = seg.segment_key AND t.status = 'ready' AND t.source_hash = seg.source_hash
					GROUP BY s.id, s.post_id, s.scan_status
					ORDER BY s.id ASC",
					array( $tables['sources'], $tables['segments'], $tables['translations'], $language )
				);
			}
			$coverage_rows = $wpdb->get_results( $coverage_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only readiness aggregate.
			$pages = array();
			foreach ( is_array( $coverage_rows ) ? $coverage_rows : array() as $row ) {
				$source_id = (int) ( $row['source_id'] ?? 0 );
				$total = (int) ( $row['total_segments'] ?? 0 );
				$ready = (int) ( $row['ready_segments'] ?? 0 );
				$pages[ $source_id ] = array(
					'source_id' => $source_id,
					'post_id' => (int) ( $row['post_id'] ?? 0 ),
					'scan_status' => sanitize_key( (string) ( $row['scan_status'] ?? '' ) ),
					'total_segments' => $total,
					'ready_segments' => $ready,
					'coverage_percent' => $total > 0 ? round( ( $ready / $total ) * 100, 1 ) : 0.0,
					'critical_required' => 0,
					'critical_ready' => 0,
					'mixed_language_fields' => array(),
					'missing_critical_fields' => array(),
				);
			}

			$critical_placeholders = implode( ', ', array_fill( 0, count( $critical_types ), '%s' ) );
			if ( $memory_available ) {
				$critical_query = 'SELECT s.id AS source_id, s.post_id, seg.segment_type, seg.source_hash, seg.source_text, seg.context_json, '
					. "COALESCE(NULLIF(t.translated_text, ''), NULLIF(m.translated_text, ''), '') AS effective_translated_text "
					. 'FROM %i s INNER JOIN %i seg ON seg.source_id = s.id AND seg.is_protected = 0 '
					. "LEFT JOIN %i t ON t.source_id = s.id AND t.language = %s AND t.segment_key = seg.segment_key AND t.status = 'ready' AND t.source_hash = seg.source_hash "
					. 'LEFT JOIN %i m ON m.language = %s AND m.source_hash = seg.source_hash '
					. 'WHERE seg.segment_type IN (' . $critical_placeholders . ') ORDER BY s.id ASC, seg.segment_order ASC';
				$critical_args = array_merge( array( $tables['sources'], $tables['segments'], $tables['translations'], $language, $tables['memory'], $language ), $critical_types );
			} else {
				$critical_query = 'SELECT s.id AS source_id, s.post_id, seg.segment_type, seg.source_hash, seg.source_text, seg.context_json, '
					. "COALESCE(NULLIF(t.translated_text, ''), '') AS effective_translated_text "
					. 'FROM %i s INNER JOIN %i seg ON seg.source_id = s.id AND seg.is_protected = 0 '
					. "LEFT JOIN %i t ON t.source_id = s.id AND t.language = %s AND t.segment_key = seg.segment_key AND t.status = 'ready' AND t.source_hash = seg.source_hash "
					. 'WHERE seg.segment_type IN (' . $critical_placeholders . ') ORDER BY s.id ASC, seg.segment_order ASC';
				$critical_args = array_merge( array( $tables['sources'], $tables['segments'], $tables['translations'], $language ), $critical_types );
			}
			$critical_sql = self::prepare_sql( $critical_query, $critical_args ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed allowlisted placeholders only.
			$critical_rows = $wpdb->get_results( $critical_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared read-only readiness detail query.

			foreach ( is_array( $critical_rows ) ? $critical_rows : array() as $row ) {
				$source_id = (int) ( $row['source_id'] ?? 0 );
				if ( ! isset( $pages[ $source_id ] ) ) {
					continue;
				}
				$type = sanitize_key( (string) ( $row['segment_type'] ?? '' ) );
				$field = $type;
				if ( 'heading' === $type ) {
					$context = json_decode( (string) ( $row['context_json'] ?? '' ), true );
					if ( ! is_array( $context ) || 'h1' !== strtolower( (string) ( $context['tag'] ?? '' ) ) ) {
						continue;
					}
					$field = 'h1';
				}
				$pages[ $source_id ]['critical_required']++;
				$translated = (string) ( $row['effective_translated_text'] ?? '' );
				$ready = '' !== trim( $translated );
				if ( ! $ready ) {
					$pages[ $source_id ]['missing_critical_fields'][] = $field;
					continue;
				}
				$pages[ $source_id ]['critical_ready']++;
				$signal = self::language_script_signal( $translated, $language, (string) ( $row['source_text'] ?? '' ) );
				if ( in_array( $signal, array( 'mismatch', 'source_equal' ), true ) ) {
					$pages[ $source_id ]['mixed_language_fields'][] = $field;
				}
			}

			$summary = array(
				'name' => self::language_name( $language ),
				'pages_checked' => 0,
				'pages_ready_for_indexing' => 0,
				'pages_blocked' => 0,
				'pages_with_mixed_language_critical_fields' => 0,
			);
			foreach ( $pages as $page ) {
				if ( $page['total_segments'] <= 0 ) {
					continue;
				}
				$summary['pages_checked']++;
				$blockers = array();
				if ( 'ready' !== $page['scan_status'] ) {
					$blockers[] = 'source_scan_not_ready';
				}
				if ( $page['ready_segments'] < $page['total_segments'] ) {
					$blockers[] = 'translation_coverage_incomplete';
				}
				$missing = array_values( array_unique( $page['missing_critical_fields'] ) );
				$mixed = array_values( array_unique( $page['mixed_language_fields'] ) );
				if ( ! empty( $missing ) ) {
					$blockers[] = 'critical_fields_missing';
				}
				if ( ! empty( $mixed ) ) {
					$blockers[] = 'mixed_language_critical_fields';
					$summary['pages_with_mixed_language_critical_fields']++;
				}
				$ready_for_indexing = empty( $blockers );
				if ( $ready_for_indexing ) {
					$summary['pages_ready_for_indexing']++;
				} else {
					$summary['pages_blocked']++;
					if ( count( $result['blockers'] ) < $result['blocker_limit'] ) {
						$result['blockers'][] = array(
							'post_id' => (int) $page['post_id'],
							'source_id' => (int) $page['source_id'],
							'language' => $language,
							'coverage_percent' => (float) $page['coverage_percent'],
							'ready_segments' => (int) $page['ready_segments'],
							'total_segments' => (int) $page['total_segments'],
							'missing_critical_fields' => $missing,
							'mixed_language_fields' => $mixed,
							'blockers' => $blockers,
							'index_recommendation' => 'keep_noindex',
						);
					}
				}
			}
			$result['summary'][ $language ] = $summary;
		}
		return $result;
	}

	private static function readiness_blocker_label( string $code ): string {
		$labels = array(
			'source_scan_not_ready' => __( 'Source scan not ready', 'koz-translate-diagnostics' ),
			'translation_coverage_incomplete' => __( 'Translation coverage incomplete', 'koz-translate-diagnostics' ),
			'critical_fields_missing' => __( 'Critical fields missing', 'koz-translate-diagnostics' ),
			'mixed_language_critical_fields' => __( 'Mixed-language critical fields', 'koz-translate-diagnostics' ),
		);
		return (string) ( $labels[ $code ] ?? $code );
	}

	private static function translated_route_url( int $post_id, string $language ): string {
		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return '';
		}
		$path = (string) wp_parse_url( $permalink, PHP_URL_PATH );
		$path = '/' . trim( $path, '/' );
		if ( '/' !== $path ) {
			$path .= '/';
		}
		return home_url( '/' . sanitize_key( $language ) . ( '/' === $path ? '/' : $path ) );
	}

	private static function safe_suite_status(): array {
		$result = array();
		foreach ( (array) KOZTDIAG_Suite_Registry::status() as $slug => $component ) {
			$result[] = array(
				'component_fingerprint' => self::fingerprint( (string) $slug, 16 ),
				'name' => self::redact_preview( (string) ( $component['name'] ?? '' ), 120 ),
				'version' => self::safe_version( (string) ( $component['version'] ?? '' ) ),
				'installed' => ! empty( $component['installed'] ),
				'active' => ! empty( $component['active'] ),
			);
		}
		return $result;
	}

	public static function report( bool $deep = false, bool $strict_export = false ): array {
		$translator = self::translator_plugin();
		$api_status = self::translator_api_status( $deep );
		$settings = self::safe_settings();
		$runtime = self::safe_runtime();
		$tables = self::table_snapshot( $deep );
		$sources = self::source_summary( $deep );
		$detected_languages = self::detected_languages( $api_status, $settings, $deep );
		$queue = self::queue_summary( $deep );
		$languages = self::translation_summary( $detected_languages, $deep );
		$site_host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		$report = array(
			'report' => array(
				'name' => 'KOZ Translate Diagnostics',
				'version' => KOZTDIAG_VERSION,
				'generated_at_utc' => gmdate( 'c' ),
				'generated_at_site' => current_time( 'c' ),
				'read_only' => true,
				'diagnostic_mode' => $deep ? 'deep' : 'quick',
				'compatibility_mode' => empty( $translator['public_api_available'] ),
				'database_writes' => false,
				'external_requests' => false,
				'privacy_boundary_version' => 4,
			),
			'environment' => array(
				'wordpress' => get_bloginfo( 'version' ),
				'php' => PHP_VERSION,
				'database' => self::database_version(),
				'locale' => determine_locale(),
				'timezone' => wp_timezone_string(),
				'multisite' => is_multisite(),
				'site_host_fingerprint' => self::fingerprint( $site_host, 16 ),
				'permalink_structure_fingerprint' => self::fingerprint( (string) get_option( 'permalink_structure', '' ), 16 ),
			),
			'translator_plugin' => $translator,
			'translator_api_status' => $api_status,
			'db_version_option' => self::safe_version( (string) self::translator_option( 'kozstx_db_version', 'uafree_st_auto_db_version', '' ) ),
			'rewrite_version_option' => self::safe_version( (string) self::translator_option( 'kozstx_rewrite_version', 'uafree_st_auto_rewrite_version', '' ) ),
			'settings' => $settings,
			'runtime' => $runtime,
			'tables' => $tables,
			'sources' => $sources,
			'detected_languages' => $detected_languages,
			'languages' => $languages,
			'translation_readiness' => self::translation_readiness( $detected_languages, $deep ),
			'queue' => $queue,
			'usage' => self::usage_summary( $deep ),
			'recent_errors' => self::recent_errors( $deep ),
			'cron' => self::cron_snapshot(),
			'transients' => self::transient_snapshot( $deep ),
			'suite' => self::safe_suite_status(),
		);
		$report['hints'] = self::hints( $translator, $api_status, $settings, $runtime, $tables, $sources, $languages, $queue, $deep );
		$filtered = apply_filters( 'koztdiag_generated_report', $report, $deep );
		if ( is_array( $filtered ) ) {
			$report = self::merge_filter_values( $report, $filtered );
		}
		return self::privacy_safe_report( $report, $strict_export );
	}



	private static function is_migration_pause_reason( string $reason ): bool {
		return str_starts_with( $reason, 'UA FREE Suite Migration Bridge:' )
			|| str_starts_with( $reason, 'KOZ WordPress Suite Migration Bridge:' );
	}

	private static function strict_export_recursive( mixed $value, string $key = '', string $parent_key = '' ): mixed {
		if ( is_array( $value ) ) {
			$result = array();
			foreach ( $value as $child_key => $item ) {
				$child_key_string = (string) $child_key;
				if ( in_array( $child_key_string, array( 'post_id', 'source_id' ), true ) ) {
					$result[ $child_key ] = '[REDACTED:' . self::fingerprint( (string) $item, 16 ) . ']';
					continue;
				}
				if ( self::is_version_report_key( $child_key_string ) ) {
					$result[ $child_key ] = is_string( $item ) ? self::safe_version( $item ) : '';
					continue;
				}
				if ( self::is_sensitive_key( $child_key_string ) ) {
					$result[ $child_key ] = is_bool( $item ) || is_int( $item ) || is_float( $item ) ? $item : '[REDACTED]';
					continue;
				}
				$result[ $child_key ] = self::strict_export_recursive( $item, $child_key_string, $key );
			}
			return $result;
		}
		if ( is_object( $value ) ) {
			return self::strict_export_recursive( get_object_vars( $value ), $key, $parent_key );
		}
		if ( ! is_string( $value ) ) {
			return $value;
		}
		if ( '' === $value ) {
			return '';
		}

		if ( 'pause_reason' === $key ) {
			if ( 'monthly_limit' === $value ) {
				return 'monthly_limit';
			}
			if ( self::is_migration_pause_reason( $value ) ) {
				return 'migration_bridge';
			}
			return 'other';
		}

		if ( preg_match( '/(?:^|_)(?:message|error|reason|name|title|description|preview|text)(?:$|_)/i', $key ) ) {
			if ( 'name' === $key && 'report' === $parent_key ) {
				return 'KOZ Translate Diagnostics';
			}
			return '[REDACTED:' . self::fingerprint( $value, 16 ) . ']';
		}

		return self::redact_preview( $value, 500 );
	}

	private static function privacy_safe_report( array $report, bool $strict_export = false ): array {
		$safe = self::sanitize_recursive( $report );
		if ( ! is_array( $safe ) ) {
			return array();
		}
		if ( ! $strict_export ) {
			return $safe;
		}
		$strict = self::strict_export_recursive( $safe );
		return is_array( $strict ) ? $strict : array();
	}

	public static function public_status(): array {
		$translator = self::translator_plugin();
		$settings = self::safe_settings();
		$runtime = self::safe_runtime();
		$migration_frozen = ! empty( $runtime['manual_paused'] )
			&& self::is_migration_pause_reason( (string) ( $runtime['pause_reason'] ?? '' ) );
		$tables = self::tables();
		$source_status = self::table_status( $tables['sources'] );
		$source_count = (int) ( $source_status['Rows'] ?? 0 );
		$languages = self::normalize_languages( (array) ( $settings['target_languages'] ?? array() ) );
		$basic_hints = 0;
		$basic_hints += empty( $translator['matches'] ) ? 1 : 0;
		$basic_hints += empty( $settings['azure_key_configured'] ) ? 1 : 0;
		$basic_hints += ! empty( $runtime['manual_paused'] ) || '' !== (string) ( $runtime['pause_reason'] ?? '' ) ? 1 : 0;

		return array(
			'version' => KOZTDIAG_VERSION,
			'status_contract_version' => 2,
			'read_only' => true,
			'status_scope' => 'lightweight',
			'translator_detected' => ! empty( $translator['matches'] ),
			'translator_active' => ! empty( array_filter( array_column( $translator['matches'], 'active' ) ) ),
			'translator_api_available' => ! empty( $translator['public_api_available'] ),
			'translator_version' => (string) ( $translator['constant_version'] ?: ( $translator['matches'][0]['version'] ?? '' ) ),
			'migration_frozen' => $migration_frozen,
			'source_count' => $source_count,
			'source_count_estimated' => ! empty( $source_status ),
			'languages' => $languages,
			'languages_scope' => 'configured',
			'basic_hint_count' => $basic_hints,
			'hint_count_scope' => 'basic',
			'database_writes' => false,
			'external_requests' => false,
		);
	}

	public static function export_json(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export this report.', 'koz-translate-diagnostics' ) );
		}
		check_admin_referer( self::EXPORT_ACTION );

		// Legacy export URLs must not trigger a second deep scan. A deep scan now
		// prepares the strict privacy-safe payload once and the browser downloads
		// that same in-memory result without another origin request.
		wp_safe_redirect( self::deep_scan_url() );
		exit;
	}

	private static function export_url(): string {
		return wp_nonce_url(
			add_query_arg( array( 'action' => self::EXPORT_ACTION ), admin_url( 'admin-post.php' ) ),
			self::EXPORT_ACTION
		);
	}

	private static function deep_scan_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page' => self::PAGE_SLUG,
					self::DEEP_SCAN_QUERY => '1',
				),
				admin_url( 'admin.php' )
			),
			self::DEEP_SCAN_NONCE
		);
	}

	private static function status_label( bool $condition, string $success, string $failure ): string {
		return $condition ? $success : $failure;
	}

	public static function admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$deep = false;
		if ( isset( $_GET[ self::DEEP_SCAN_QUERY ] ) && '1' === sanitize_text_field( wp_unslash( $_GET[ self::DEEP_SCAN_QUERY ] ) ) ) {
			$deep = (bool) check_admin_referer( self::DEEP_SCAN_NONCE, '_wpnonce' );
		}
		$report = self::report( $deep );
		$translator = $report['translator_plugin'];
		$languages = $report['languages'];
		$export_json = '';
		$export_filename = '';
		if ( $deep ) {
			$strict_report = self::privacy_safe_report( $report, true );
			$encoded = wp_json_encode(
				$strict_report,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			);
			$export_json = is_string( $encoded ) ? $encoded : '';
			$export_filename = 'koz-translate-diagnostics-' . gmdate( 'Ymd-His' ) . '.json';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'KOZ Translate Diagnostics', 'koz-translate-diagnostics' ); ?></h1>
			<div class="notice notice-info inline"><p><strong><?php esc_html_e( 'Read-only:', 'koz-translate-diagnostics' ); ?></strong> <?php esc_html_e( 'this plugin does not call Azure, run the translation queue, change options, alter cron, clear caches or modify translation data.', 'koz-translate-diagnostics' ); ?></p></div>
			<p><?php esc_html_e( 'The plugin first uses the translator public diagnostics API. For older translator versions it automatically falls back to a read-only compatibility scan.', 'koz-translate-diagnostics' ); ?></p>
			<?php if ( $deep ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Deep scan completed. It compares source hashes and uses heavier read-only database queries.', 'koz-translate-diagnostics' ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'Quick scan is active. It uses metadata and limited recent-row reads only; full-table aggregates and hash joins are deferred to deep mode.', 'koz-translate-diagnostics' ); ?></p></div>
			<?php endif; ?>
			<p>
				<?php if ( $deep && '' !== $export_json ) : ?>
					<button class="button button-primary" type="button" id="koztdiag-download-report"><?php esc_html_e( 'Download deep privacy-safe JSON report', 'koz-translate-diagnostics' ); ?></button>
				<?php else : ?>
					<a class="button button-primary" href="<?php echo esc_url( self::deep_scan_url() ); ?>"><?php esc_html_e( 'Run deep hash scan', 'koz-translate-diagnostics' ); ?></a>
				<?php endif; ?>
			</p>
			<?php if ( $deep && '' !== $export_json ) : ?>
				<textarea id="koztdiag-export-payload" hidden><?php echo esc_textarea( $export_json ); ?></textarea>
				<script>
				(function () {
					'use strict';
					var button = document.getElementById('koztdiag-download-report');
					var payload = document.getElementById('koztdiag-export-payload');
					if (!button || !payload) { return; }
					button.addEventListener('click', function () {
						var blob = new Blob([payload.value], { type: 'application/json;charset=utf-8' });
						var url = URL.createObjectURL(blob);
						var link = document.createElement('a');
						link.href = url;
						link.download = <?php echo wp_json_encode( $export_filename ); ?>;
						document.body.appendChild(link);
						link.click();
						link.remove();
						URL.revokeObjectURL(url);
					});
				}());
				</script>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Translator status', 'koz-translate-diagnostics' ); ?></h2>
			<table class="widefat striped" style="max-width:1180px"><tbody>
				<tr><th style="width:330px"><?php esc_html_e( 'Installed', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ! empty( $translator['matches'] ), __( 'Yes', 'koz-translate-diagnostics' ), __( 'No', 'koz-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Active', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ! empty( array_filter( array_column( $translator['matches'], 'active' ) ) ), __( 'Yes', 'koz-translate-diagnostics' ), __( 'No', 'koz-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Detected version', 'koz-translate-diagnostics' ); ?></th><td><code><?php echo esc_html( (string) ( $translator['constant_version'] ?: ( $translator['matches'][0]['version'] ?? '' ) ) ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Public diagnostics API', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ! empty( $translator['public_api_available'] ), __( 'Available', 'koz-translate-diagnostics' ), __( 'Compatibility mode', 'koz-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Diagnostic mode', 'koz-translate-diagnostics' ); ?></th><td><code><?php echo esc_html( (string) $report['report']['diagnostic_mode'] ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Migration freeze', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ( ! empty( $report['translator_api_status']['migration_frozen'] ) || ( ! empty( $report['runtime']['manual_paused'] ) && self::is_migration_pause_reason( (string) ( $report['runtime']['pause_reason'] ?? '' ) ) ) ), __( 'Active', 'koz-translate-diagnostics' ), __( 'Inactive', 'koz-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Source language', 'koz-translate-diagnostics' ); ?></th><td><code><?php echo esc_html( (string) ( $report['settings']['source_language'] ?? '' ) ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Azure key configured', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ! empty( $report['settings']['azure_key_configured'] ), __( 'Yes', 'koz-translate-diagnostics' ), __( 'No', 'koz-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Manual pause', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ! empty( $report['runtime']['manual_paused'] ), __( 'Yes', 'koz-translate-diagnostics' ), __( 'No', 'koz-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Pause reason', 'koz-translate-diagnostics' ); ?></th><td><code><?php echo esc_html( (string) ( $report['runtime']['pause_reason'] ?? '' ) ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Last runtime error', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( (string) ( $report['runtime']['last_error'] ?? '' ) ); ?></td></tr>
			</tbody></table>

			<h2><?php esc_html_e( 'Data inventory', 'koz-translate-diagnostics' ); ?></h2>
			<table class="widefat striped" style="max-width:1180px"><thead><tr><th><?php esc_html_e( 'Table', 'koz-translate-diagnostics' ); ?></th><th><?php esc_html_e( 'Exists', 'koz-translate-diagnostics' ); ?></th><th><?php esc_html_e( 'Rows', 'koz-translate-diagnostics' ); ?></th></tr></thead><tbody>
			<?php foreach ( $report['tables'] as $key => $table ) : ?>
				<tr><td><code><?php echo esc_html( $key ); ?></code></td><td><?php echo esc_html( $table['exists'] ? __( 'Yes', 'koz-translate-diagnostics' ) : __( 'No', 'koz-translate-diagnostics' ) ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $table['rows'] ) ); ?><?php if ( ! empty( $table['rows_estimated'] ) ) : ?> <span class="description"><?php esc_html_e( '(estimate)', 'koz-translate-diagnostics' ); ?></span><?php endif; ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<p><strong><?php esc_html_e( 'Translation sources:', 'koz-translate-diagnostics' ); ?></strong> <?php echo esc_html( number_format_i18n( (int) $report['sources']['total'] ) ); ?></p>

			<h2><?php esc_html_e( 'Languages', 'koz-translate-diagnostics' ); ?></h2>
			<table class="widefat striped" style="max-width:1500px"><thead><tr>
				<th><?php esc_html_e( 'Language', 'koz-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Expected segments', 'koz-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Ready', 'koz-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Missing estimate', 'koz-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Stale', 'koz-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Memory', 'koz-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Queue', 'koz-translate-diagnostics' ); ?></th>
			</tr></thead><tbody>
			<?php if ( empty( $languages ) ) : ?><tr><td colspan="7"><?php esc_html_e( 'No target languages were detected.', 'koz-translate-diagnostics' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $languages as $language => $data ) :
				$queue_total = 0;
				$queue_parts = array();
				foreach ( (array) ( $report['queue'][ $language ] ?? array() ) as $status => $queue_data ) {
					$queue_total += (int) $queue_data['total'];
					$queue_parts[] = $status . ': ' . (int) $queue_data['total'];
				}
				?>
				<tr>
					<td><strong><?php echo esc_html( $data['name'] ); ?></strong><br><code><?php echo esc_html( $language ); ?></code></td>
					<td><?php echo null === $data['expected_translatable_segments'] ? esc_html__( 'Not checked', 'koz-translate-diagnostics' ) : esc_html( number_format_i18n( (int) $data['expected_translatable_segments'] ) ); ?></td>
					<td><?php echo null === $data['exact_ready_rows'] ? esc_html__( 'Not checked', 'koz-translate-diagnostics' ) : esc_html( number_format_i18n( (int) $data['exact_ready_rows'] ) ); ?></td>
					<td><strong><?php echo null === $data['missing_estimate'] ? esc_html__( 'Not checked', 'koz-translate-diagnostics' ) : esc_html( number_format_i18n( (int) $data['missing_estimate'] ) ); ?></strong></td>
					<td><?php echo null === $data['stale_rows'] ? esc_html__( 'Not checked', 'koz-translate-diagnostics' ) : esc_html( number_format_i18n( (int) $data['stale_rows'] ) ); ?></td>
					<td><?php echo null === $data['memory_ready_rows'] ? esc_html__( 'Not checked', 'koz-translate-diagnostics' ) : esc_html( number_format_i18n( (int) $data['memory_ready_rows'] ) ); ?></td>
					<td><?php echo esc_html( $queue_total > 0 ? implode( ', ', $queue_parts ) : __( 'No queue rows', 'koz-translate-diagnostics' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<p class="description"><?php esc_html_e( 'Quick mode does not calculate completeness totals. Deep mode calculates the missing estimate from current translatable segments and exact ready translation rows; translation memory may reduce the work required.', 'koz-translate-diagnostics' ); ?></p>


			<h2><?php esc_html_e( 'Translation index readiness', 'koz-translate-diagnostics' ); ?></h2>
			<?php $readiness = (array) ( $report['translation_readiness'] ?? array() ); ?>
			<?php if ( empty( $readiness['checked'] ) ) : ?>
				<p><?php esc_html_e( 'Run the deep scan to calculate page-level translation coverage, critical metadata/H1 readiness and mixed-language blockers.', 'koz-translate-diagnostics' ); ?></p>
			<?php elseif ( empty( $readiness['available'] ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Translation readiness could not be calculated because the required translator tables or columns were not available.', 'koz-translate-diagnostics' ); ?></p></div>
			<?php else : ?>
				<table class="widefat striped" style="max-width:1180px"><thead><tr>
					<th><?php esc_html_e( 'Language', 'koz-translate-diagnostics' ); ?></th>
					<th><?php esc_html_e( 'Pages checked', 'koz-translate-diagnostics' ); ?></th>
					<th><?php esc_html_e( 'Ready for indexing', 'koz-translate-diagnostics' ); ?></th>
					<th><?php esc_html_e( 'Keep noindex', 'koz-translate-diagnostics' ); ?></th>
					<th><?php esc_html_e( 'Mixed-language critical fields', 'koz-translate-diagnostics' ); ?></th>
				</tr></thead><tbody>
				<?php foreach ( (array) ( $readiness['summary'] ?? array() ) as $language => $summary ) : ?>
					<tr>
						<td><strong><?php echo esc_html( (string) ( $summary['name'] ?? strtoupper( (string) $language ) ) ); ?></strong><br><code><?php echo esc_html( (string) $language ); ?></code></td>
						<td><?php echo esc_html( number_format_i18n( (int) ( $summary['pages_checked'] ?? 0 ) ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) ( $summary['pages_ready_for_indexing'] ?? 0 ) ) ); ?></td>
						<td><strong><?php echo esc_html( number_format_i18n( (int) ( $summary['pages_blocked'] ?? 0 ) ) ); ?></strong></td>
						<td><?php echo esc_html( number_format_i18n( (int) ( $summary['pages_with_mixed_language_critical_fields'] ?? 0 ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
				<p class="description"><?php esc_html_e( '“Ready for indexing” is a diagnostic recommendation only. It requires complete current translation rows plus translated title, meta description, Open Graph title/description and H1 when those fields exist in the source. The plugin does not change robots directives.', 'koz-translate-diagnostics' ); ?></p>

				<?php if ( ! empty( $readiness['blockers'] ) ) : ?>
					<h3><?php esc_html_e( 'Highest-priority blockers', 'koz-translate-diagnostics' ); ?></h3>
					<table class="widefat striped" style="max-width:1500px"><thead><tr>
						<th><?php esc_html_e( 'Page', 'koz-translate-diagnostics' ); ?></th>
						<th><?php esc_html_e( 'Language', 'koz-translate-diagnostics' ); ?></th>
						<th><?php esc_html_e( 'Coverage', 'koz-translate-diagnostics' ); ?></th>
						<th><?php esc_html_e( 'Missing critical fields', 'koz-translate-diagnostics' ); ?></th>
						<th><?php esc_html_e( 'Mixed-language fields', 'koz-translate-diagnostics' ); ?></th>
						<th><?php esc_html_e( 'Reason', 'koz-translate-diagnostics' ); ?></th>
						<th><?php esc_html_e( 'Recommendation', 'koz-translate-diagnostics' ); ?></th>
					</tr></thead><tbody>
					<?php foreach ( (array) $readiness['blockers'] as $blocker ) :
						$post_id = (int) ( $blocker['post_id'] ?? 0 );
						$language = sanitize_key( (string) ( $blocker['language'] ?? '' ) );
						$route = self::translated_route_url( $post_id, $language );
						$title = $post_id > 0 ? get_the_title( $post_id ) : '';
						$reason_labels = array_map( array( __CLASS__, 'readiness_blocker_label' ), (array) ( $blocker['blockers'] ?? array() ) );
						?>
						<tr>
							<td><?php if ( '' !== $route ) : ?><a href="<?php echo esc_url( $route ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( '' !== $title ? $title : '#' . $post_id ); ?></a><?php else : ?><?php echo esc_html( '#' . $post_id ); ?><?php endif; ?></td>
							<td><code><?php echo esc_html( strtoupper( $language ) ); ?></code></td>
							<td><?php echo esc_html( number_format_i18n( (float) ( $blocker['coverage_percent'] ?? 0 ), 1 ) . '%' ); ?></td>
							<td><?php echo esc_html( empty( $blocker['missing_critical_fields'] ) ? '—' : implode( ', ', (array) $blocker['missing_critical_fields'] ) ); ?></td>
							<td><?php echo esc_html( empty( $blocker['mixed_language_fields'] ) ? '—' : implode( ', ', (array) $blocker['mixed_language_fields'] ) ); ?></td>
							<td><?php echo esc_html( empty( $reason_labels ) ? '—' : implode( '; ', $reason_labels ) ); ?></td>
							<td><strong><?php esc_html_e( 'Keep noindex', 'koz-translate-diagnostics' ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php endif; ?>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Diagnostic findings', 'koz-translate-diagnostics' ); ?></h2>
			<ul style="max-width:1150px;background:#fff;border:1px solid #dcdcde;padding:18px 18px 18px 36px">
			<?php if ( empty( $report['hints'] ) ) : ?><li><?php esc_html_e( 'No automatic findings were generated.', 'koz-translate-diagnostics' ); ?></li><?php endif; ?>
			<?php foreach ( $report['hints'] as $hint ) : ?><li><?php echo esc_html( $hint ); ?></li><?php endforeach; ?>
			</ul>

			<h2><?php esc_html_e( 'Privacy', 'koz-translate-diagnostics' ); ?></h2>
			<p><?php esc_html_e( 'The exported report uses an allowlisted translator API contract, removes raw plugin paths and table names, fingerprints operational identifiers, and applies a final privacy pass after third-party filters.', 'koz-translate-diagnostics' ); ?></p>
		</div>
		<?php
	}
}
