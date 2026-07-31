<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UAFree_Translate_Diagnostics {
	private const PAGE_SLUG = 'ua-free-translate-diagnostics';
	private const EXPORT_ACTION = 'uafree_translate_diagnostics_export';
	private const DEEP_SCAN_QUERY = 'uafree_td_deep';
	private const DEEP_SCAN_NONCE = 'uafree_translate_diagnostics_deep';
	private const TEXT_DOMAIN = 'ua-free-translate-diagnostics';

	private static array $table_exists_cache = array();
	private static array $table_columns_cache = array();
	private static array $table_status_cache = array();

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 30 );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'export_json' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( UAFREE_TD_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function admin_menu(): void {
		add_submenu_page(
			'uafree-suite',
			__( 'Translation Diagnostics', 'ua-free-translate-diagnostics' ),
			__( 'Translation Diagnostics', 'ua-free-translate-diagnostics' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'admin_page' )
		);
	}

	public static function action_links( array $links ): array {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' .
			esc_html__( 'Diagnostics', 'ua-free-translate-diagnostics' ) .
			'</a>'
		);
		return $links;
	}

	private static function tables(): array {
		global $wpdb;
		return array(
			'sources' => $wpdb->prefix . 'uafree_st_sources',
			'segments' => $wpdb->prefix . 'uafree_st_source_segments',
			'translations' => $wpdb->prefix . 'uafree_st_translations',
			'memory' => $wpdb->prefix . 'uafree_st_memory',
			'queue' => $wpdb->prefix . 'uafree_st_queue',
			'usage' => $wpdb->prefix . 'uafree_st_usage',
			'logs' => $wpdb->prefix . 'uafree_st_logs',
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
			if ( false === stripos( $name, 'UA FREE Static Translate' ) && false === stripos( (string) $file, 'ua-free-static-translate' ) ) {
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

		return array(
			'matches' => $matches,
			'constant_version' => defined( 'UAFREE_ST_VERSION' ) ? self::safe_version( (string) UAFREE_ST_VERSION ) : '',
			'class_loaded' => class_exists( 'UAFree_Static_Translate_Autonomous', false ),
			'public_api_available' => class_exists( 'UAFree_Static_Translate_Autonomous', false ) && method_exists( 'UAFree_Static_Translate_Autonomous', 'public_status' ),
		);
	}

	private static function translator_api_status( bool $deep = false ): array {
		if ( ! $deep ) {
			return array( 'status_source' => 'deferred_to_deep' );
		}
		if ( ! class_exists( 'UAFree_Static_Translate_Autonomous', false ) || ! method_exists( 'UAFree_Static_Translate_Autonomous', 'public_status' ) ) {
			return array();
		}
		try {
			$status = UAFree_Static_Translate_Autonomous::public_status();
			return is_array( $status ) ? self::normalize_translator_api_status( $status ) : array();
		} catch ( Throwable $error ) {
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

	private static function safe_settings(): array {
		$settings = get_option( 'uafree_st_auto_settings', array() );
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
		$runtime = get_option( 'uafree_st_auto_runtime', array() );
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
		foreach ( $languages as $language ) {
			$row = $indexed[ $language ] ?? array();
			$ready = (int) ( $row['ready'] ?? 0 );
			$result[ $language ] = array(
				'name' => self::language_name( $language ),
				'counts_performed' => true,
				'expected_translatable_segments' => $expected,
				'protected_segments' => $protected,
				'translation_rows' => (int) ( $row['total'] ?? 0 ),
				'exact_ready_rows' => $ready,
				'non_ready_rows' => (int) ( $row['non_ready'] ?? 0 ),
				'empty_rows' => (int) ( $row['empty_rows'] ?? 0 ),
				'stale_rows' => $hash_validation_performed ? (int) ( $row['stale'] ?? 0 ) : null,
				'hash_validation_performed' => $hash_validation_performed,
				'memory_rows' => (int) ( $memory[ $language ]['total'] ?? 0 ),
				'memory_ready_rows' => (int) ( $memory[ $language ]['ready'] ?? 0 ),
				'missing_estimate' => max( 0, $expected - $ready ),
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
				if ( false === stripos( (string) $hook, 'uafree_st' ) && false === stripos( (string) $hook, 'uafree_static' ) ) {
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
		$query = self::prepare_sql(
			"SELECT option_name FROM %i WHERE option_name LIKE '_transient_uafree_st_%' OR option_name LIKE '_transient_timeout_uafree_st_%' ORDER BY option_name ASC LIMIT 500",
			array( $wpdb->options )
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
			$hints[] = __( 'UA FREE Static Translate was not detected.', 'ua-free-translate-diagnostics' );
		}
		if ( ! empty( $translator['matches'] ) && empty( array_filter( array_column( $translator['matches'], 'active' ) ) ) ) {
			$hints[] = __( 'The translator plugin is installed but inactive.', 'ua-free-translate-diagnostics' );
		}
		if ( empty( $translator['public_api_available'] ) ) {
			$hints[] = __( 'The translator public diagnostics API is unavailable; the report uses the read-only database compatibility layer.', 'ua-free-translate-diagnostics' );
		}
		if ( isset( $api_status['api_error'] ) ) {
			$hints[] = __( 'The translator public diagnostics API returned an error.', 'ua-free-translate-diagnostics' );
		}
		if ( ! empty( $api_status['migration_frozen'] ) ) {
			$hints[] = __( 'The translator reports an active migration freeze.', 'ua-free-translate-diagnostics' );
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
				__( 'Missing translator tables: %s.', 'ua-free-translate-diagnostics' ),
				implode( ', ', $missing_tables )
			);
		}
		if ( empty( $settings['azure_key_configured'] ) ) {
			$hints[] = __( 'The Azure Translator key is not configured.', 'ua-free-translate-diagnostics' );
		}
		if ( ! empty( $runtime['manual_paused'] ) ) {
			$hints[] = __( 'Automatic translation is manually paused.', 'ua-free-translate-diagnostics' );
		}
		if ( ! empty( $runtime['pause_until'] ) && time() < (int) $runtime['pause_until'] ) {
			$hints[] = __( 'Automatic translation is temporarily paused.', 'ua-free-translate-diagnostics' );
		}
		if ( 'monthly_limit' === (string) ( $runtime['pause_reason'] ?? '' ) ) {
			$hints[] = __( 'Automatic translation is paused because the configured monthly character limit was reached.', 'ua-free-translate-diagnostics' );
		}
		if ( 0 === (int) ( $sources['total'] ?? 0 ) ) {
			$hints[] = __( 'No translation sources were found.', 'ua-free-translate-diagnostics' );
		}
		if ( ! $deep ) {
			$hints[] = __( 'The quick scan does not compare translation hashes. Run the deep scan before treating stale-row counts as verified.', 'ua-free-translate-diagnostics' );
		}
		foreach ( $languages as $language => $data ) {
			if ( null !== ( $data['missing_estimate'] ?? null ) && $data['missing_estimate'] > 0 ) {
				$hints[] = sprintf(
					/* translators: 1: language code, 2: approximate number of missing translation rows. */
					__( '%1$s: approximately %2$d current translation rows are missing.', 'ua-free-translate-diagnostics' ),
					strtoupper( $language ),
					$data['missing_estimate']
				);
			}
			if ( null !== $data['stale_rows'] && $data['stale_rows'] > 0 ) {
				$hints[] = sprintf(
					/* translators: 1: language code, 2: number of translation rows with a stale source hash. */
					__( '%1$s: %2$d translation rows have a stale source hash.', 'ua-free-translate-diagnostics' ),
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
					__( '%1$s: %2$d queue rows contain an error.', 'ua-free-translate-diagnostics' ),
					strtoupper( $language ),
					$queue_errors
				);
			}
		}
		return array_values( array_unique( $hints ) );
	}


	private static function safe_suite_status(): array {
		if ( ! class_exists( '\\UAFree\\Suite\\Registry' ) ) {
			return array();
		}
		$result = array();
		foreach ( (array) \UAFree\Suite\Registry::status() as $slug => $component ) {
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
				'name' => 'UA FREE Translate Diagnostics',
				'version' => UAFREE_TD_VERSION,
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
			'db_version_option' => self::safe_version( (string) get_option( 'uafree_st_auto_db_version', '' ) ),
			'rewrite_version_option' => self::safe_version( (string) get_option( 'uafree_st_auto_rewrite_version', '' ) ),
			'settings' => $settings,
			'runtime' => $runtime,
			'tables' => $tables,
			'sources' => $sources,
			'detected_languages' => $detected_languages,
			'languages' => $languages,
			'queue' => $queue,
			'usage' => self::usage_summary( $deep ),
			'recent_errors' => self::recent_errors( $deep ),
			'cron' => self::cron_snapshot(),
			'transients' => self::transient_snapshot( $deep ),
			'suite' => self::safe_suite_status(),
		);
		$report['hints'] = self::hints( $translator, $api_status, $settings, $runtime, $tables, $sources, $languages, $queue, $deep );
		$filtered = apply_filters( 'uafree_translate_diagnostics_generated_report', $report, $deep );
		if ( is_array( $filtered ) ) {
			$report = self::merge_filter_values( $report, $filtered );
		}
		return self::privacy_safe_report( $report, $strict_export );
	}


	private static function strict_export_recursive( mixed $value, string $key = '', string $parent_key = '' ): mixed {
		if ( is_array( $value ) ) {
			$result = array();
			foreach ( $value as $child_key => $item ) {
				$child_key_string = (string) $child_key;
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
			if ( str_starts_with( $value, 'UA FREE Suite Migration Bridge:' ) ) {
				return 'migration_bridge';
			}
			return 'other';
		}

		if ( preg_match( '/(?:^|_)(?:message|error|reason|name|title|description|preview|text)(?:$|_)/i', $key ) ) {
			if ( 'name' === $key && 'report' === $parent_key ) {
				return 'UA FREE Translate Diagnostics';
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
			&& str_starts_with( (string) ( $runtime['pause_reason'] ?? '' ), 'UA FREE Suite Migration Bridge:' );
		$tables = self::tables();
		$source_status = self::table_status( $tables['sources'] );
		$source_count = (int) ( $source_status['Rows'] ?? 0 );
		$languages = self::normalize_languages( (array) ( $settings['target_languages'] ?? array() ) );
		$basic_hints = 0;
		$basic_hints += empty( $translator['matches'] ) ? 1 : 0;
		$basic_hints += empty( $settings['azure_key_configured'] ) ? 1 : 0;
		$basic_hints += ! empty( $runtime['manual_paused'] ) || '' !== (string) ( $runtime['pause_reason'] ?? '' ) ? 1 : 0;

		return array(
			'version' => UAFREE_TD_VERSION,
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
			wp_die( esc_html__( 'You do not have permission to export this report.', 'ua-free-translate-diagnostics' ) );
		}
		check_admin_referer( self::EXPORT_ACTION );
		nocache_headers();
		header( 'Content-Disposition: attachment; filename="ua-free-translate-diagnostics-' . gmdate( 'Ymd-His' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		wp_send_json(
			self::report( true, true ),
			200,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'UA FREE Translate Diagnostics', 'ua-free-translate-diagnostics' ); ?></h1>
			<div class="notice notice-info inline"><p><strong><?php esc_html_e( 'Read-only:', 'ua-free-translate-diagnostics' ); ?></strong> <?php esc_html_e( 'this plugin does not call Azure, run the translation queue, change options, alter cron, clear caches or modify translation data.', 'ua-free-translate-diagnostics' ); ?></p></div>
			<p><?php esc_html_e( 'The plugin first uses the translator public diagnostics API. For older translator versions it automatically falls back to a read-only compatibility scan.', 'ua-free-translate-diagnostics' ); ?></p>
			<?php if ( $deep ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Deep scan completed. It compares source hashes and uses heavier read-only database queries.', 'ua-free-translate-diagnostics' ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'Quick scan is active. It uses metadata and limited recent-row reads only; full-table aggregates and hash joins are deferred to deep mode.', 'ua-free-translate-diagnostics' ); ?></p></div>
			<?php endif; ?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::export_url() ); ?>"><?php esc_html_e( 'Download deep privacy-safe JSON report', 'ua-free-translate-diagnostics' ); ?></a>
				<?php if ( ! $deep ) : ?>
					<a class="button" href="<?php echo esc_url( self::deep_scan_url() ); ?>"><?php esc_html_e( 'Run deep hash scan', 'ua-free-translate-diagnostics' ); ?></a>
				<?php endif; ?>
			</p>

			<h2><?php esc_html_e( 'Translator status', 'ua-free-translate-diagnostics' ); ?></h2>
			<table class="widefat striped" style="max-width:1180px"><tbody>
				<tr><th style="width:330px"><?php esc_html_e( 'Installed', 'ua-free-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ! empty( $translator['matches'] ), __( 'Yes', 'ua-free-translate-diagnostics' ), __( 'No', 'ua-free-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Active', 'ua-free-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ! empty( array_filter( array_column( $translator['matches'], 'active' ) ) ), __( 'Yes', 'ua-free-translate-diagnostics' ), __( 'No', 'ua-free-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Detected version', 'ua-free-translate-diagnostics' ); ?></th><td><code><?php echo esc_html( (string) ( $translator['constant_version'] ?: ( $translator['matches'][0]['version'] ?? '' ) ) ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Public diagnostics API', 'ua-free-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ! empty( $translator['public_api_available'] ), __( 'Available', 'ua-free-translate-diagnostics' ), __( 'Compatibility mode', 'ua-free-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Diagnostic mode', 'ua-free-translate-diagnostics' ); ?></th><td><code><?php echo esc_html( (string) $report['report']['diagnostic_mode'] ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Migration freeze', 'ua-free-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ( ! empty( $report['translator_api_status']['migration_frozen'] ) || ( ! empty( $report['runtime']['manual_paused'] ) && str_starts_with( (string) ( $report['runtime']['pause_reason'] ?? '' ), 'UA FREE Suite Migration Bridge:' ) ) ), __( 'Active', 'ua-free-translate-diagnostics' ), __( 'Inactive', 'ua-free-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Source language', 'ua-free-translate-diagnostics' ); ?></th><td><code><?php echo esc_html( (string) ( $report['settings']['source_language'] ?? '' ) ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Azure key configured', 'ua-free-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ! empty( $report['settings']['azure_key_configured'] ), __( 'Yes', 'ua-free-translate-diagnostics' ), __( 'No', 'ua-free-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Manual pause', 'ua-free-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ! empty( $report['runtime']['manual_paused'] ), __( 'Yes', 'ua-free-translate-diagnostics' ), __( 'No', 'ua-free-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Pause reason', 'ua-free-translate-diagnostics' ); ?></th><td><code><?php echo esc_html( (string) ( $report['runtime']['pause_reason'] ?? '' ) ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Last runtime error', 'ua-free-translate-diagnostics' ); ?></th><td><?php echo esc_html( (string) ( $report['runtime']['last_error'] ?? '' ) ); ?></td></tr>
			</tbody></table>

			<h2><?php esc_html_e( 'Data inventory', 'ua-free-translate-diagnostics' ); ?></h2>
			<table class="widefat striped" style="max-width:1180px"><thead><tr><th><?php esc_html_e( 'Table', 'ua-free-translate-diagnostics' ); ?></th><th><?php esc_html_e( 'Exists', 'ua-free-translate-diagnostics' ); ?></th><th><?php esc_html_e( 'Rows', 'ua-free-translate-diagnostics' ); ?></th></tr></thead><tbody>
			<?php foreach ( $report['tables'] as $key => $table ) : ?>
				<tr><td><code><?php echo esc_html( $key ); ?></code></td><td><?php echo esc_html( $table['exists'] ? __( 'Yes', 'ua-free-translate-diagnostics' ) : __( 'No', 'ua-free-translate-diagnostics' ) ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $table['rows'] ) ); ?><?php if ( ! empty( $table['rows_estimated'] ) ) : ?> <span class="description"><?php esc_html_e( '(estimate)', 'ua-free-translate-diagnostics' ); ?></span><?php endif; ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<p><strong><?php esc_html_e( 'Translation sources:', 'ua-free-translate-diagnostics' ); ?></strong> <?php echo esc_html( number_format_i18n( (int) $report['sources']['total'] ) ); ?></p>

			<h2><?php esc_html_e( 'Languages', 'ua-free-translate-diagnostics' ); ?></h2>
			<table class="widefat striped" style="max-width:1500px"><thead><tr>
				<th><?php esc_html_e( 'Language', 'ua-free-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Expected segments', 'ua-free-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Ready', 'ua-free-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Missing estimate', 'ua-free-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Stale', 'ua-free-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Memory', 'ua-free-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Queue', 'ua-free-translate-diagnostics' ); ?></th>
			</tr></thead><tbody>
			<?php if ( empty( $languages ) ) : ?><tr><td colspan="7"><?php esc_html_e( 'No target languages were detected.', 'ua-free-translate-diagnostics' ); ?></td></tr><?php endif; ?>
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
					<td><?php echo null === $data['expected_translatable_segments'] ? esc_html__( 'Not checked', 'ua-free-translate-diagnostics' ) : esc_html( number_format_i18n( (int) $data['expected_translatable_segments'] ) ); ?></td>
					<td><?php echo null === $data['exact_ready_rows'] ? esc_html__( 'Not checked', 'ua-free-translate-diagnostics' ) : esc_html( number_format_i18n( (int) $data['exact_ready_rows'] ) ); ?></td>
					<td><strong><?php echo null === $data['missing_estimate'] ? esc_html__( 'Not checked', 'ua-free-translate-diagnostics' ) : esc_html( number_format_i18n( (int) $data['missing_estimate'] ) ); ?></strong></td>
					<td><?php echo null === $data['stale_rows'] ? esc_html__( 'Not checked', 'ua-free-translate-diagnostics' ) : esc_html( number_format_i18n( (int) $data['stale_rows'] ) ); ?></td>
					<td><?php echo null === $data['memory_ready_rows'] ? esc_html__( 'Not checked', 'ua-free-translate-diagnostics' ) : esc_html( number_format_i18n( (int) $data['memory_ready_rows'] ) ); ?></td>
					<td><?php echo esc_html( $queue_total > 0 ? implode( ', ', $queue_parts ) : __( 'No queue rows', 'ua-free-translate-diagnostics' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<p class="description"><?php esc_html_e( 'Quick mode does not calculate completeness totals. Deep mode calculates the missing estimate from current translatable segments and exact ready translation rows; translation memory may reduce the work required.', 'ua-free-translate-diagnostics' ); ?></p>

			<h2><?php esc_html_e( 'Diagnostic findings', 'ua-free-translate-diagnostics' ); ?></h2>
			<ul style="max-width:1150px;background:#fff;border:1px solid #dcdcde;padding:18px 18px 18px 36px">
			<?php if ( empty( $report['hints'] ) ) : ?><li><?php esc_html_e( 'No automatic findings were generated.', 'ua-free-translate-diagnostics' ); ?></li><?php endif; ?>
			<?php foreach ( $report['hints'] as $hint ) : ?><li><?php echo esc_html( $hint ); ?></li><?php endforeach; ?>
			</ul>

			<h2><?php esc_html_e( 'Privacy', 'ua-free-translate-diagnostics' ); ?></h2>
			<p><?php esc_html_e( 'The exported report uses an allowlisted translator API contract, removes raw plugin paths and table names, fingerprints operational identifiers, and applies a final privacy pass after third-party filters.', 'ua-free-translate-diagnostics' ); ?></p>
		</div>
		<?php
	}
}
