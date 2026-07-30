<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UAFree_MC_Environment_Scanner {
	const CACHE_KEY = 'uafree_mc_environment_scan_v080';
	const CACHE_TTL = 15 * MINUTE_IN_SECONDS;
	const MAX_MATCHES = 100;

	public static function environment( bool $force = false ): array {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		global $wpdb;
		$plugins = self::plugin_inventory();
		$tables = self::table_inventory();
		$cron = self::cron_inventory();

		$report = array(
			'generated_at' => current_time( 'c' ),
			'plugin' => array(
				'name' => 'UA FREE Migration & Cleanup',
				'version' => UAFREE_MC_VERSION,
				'mode' => 'universal read-only inventory',
			),
			'site' => array(
				'home_url' => home_url( '/' ),
				'wordpress' => get_bloginfo( 'version' ),
				'php' => PHP_VERSION,
				'multisite' => is_multisite(),
				'theme' => wp_get_theme()->get( 'Name' ),
				'theme_version' => wp_get_theme()->get( 'Version' ),
				'database_prefix_hash' => hash( 'sha256', (string) $wpdb->prefix ),
			),
			'plugins' => $plugins,
			'database' => array(
				'table_count' => count( $tables ),
				'tables' => $tables,
				'options_bytes' => self::single_int_query( "SELECT COALESCE(SUM(LENGTH(option_name)+LENGTH(option_value)),0) FROM {$wpdb->options}" ),
				'autoload_bytes' => self::autoload_bytes(),
			),
			'cron' => $cron,
			'suite' => UAFree_MC_Suite_Registry::status(),
			'safety' => array(
				'database_writes' => false,
				'deletions' => false,
				'plugin_state_changes' => false,
				'option_values_exported' => false,
				'personal_data_exported' => false,
			),
		);

		$hash_payload = $report;
		unset( $hash_payload['generated_at'] );
		$report['snapshot_sha256'] = hash(
			'sha256',
			(string) wp_json_encode( $hash_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);

		set_transient( self::CACHE_KEY, $report, self::CACHE_TTL );
		return $report;
	}

	public static function inspect_plugin( string $plugin_file ): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		if ( ! isset( $plugins[ $plugin_file ] ) ) {
			return new WP_Error( 'plugin_not_found', __( 'The selected plugin is not installed.', 'ua-free-migration-cleanup' ) );
		}

		$data = $plugins[ $plugin_file ];
		$patterns = self::plugin_patterns( $plugin_file, $data );
		global $wpdb;

		$report = array(
			'generated_at' => current_time( 'c' ),
			'plugin' => array(
				'file' => $plugin_file,
				'slug' => dirname( $plugin_file ),
				'name' => isset( $data['Name'] ) ? wp_strip_all_tags( (string) $data['Name'] ) : '',
				'version' => isset( $data['Version'] ) ? (string) $data['Version'] : '',
				'author' => isset( $data['AuthorName'] ) ? wp_strip_all_tags( (string) $data['AuthorName'] ) : '',
				'text_domain' => isset( $data['TextDomain'] ) ? (string) $data['TextDomain'] : '',
				'status' => self::plugin_status( $plugin_file ),
			),
			'patterns' => $patterns,
			'likely_data' => array(
				'options' => self::matching_options( $patterns ),
				'postmeta' => self::matching_meta( $wpdb->postmeta, 'meta_key', $patterns ),
				'termmeta' => self::matching_meta( $wpdb->termmeta, 'meta_key', $patterns ),
				'usermeta' => self::matching_meta( $wpdb->usermeta, 'meta_key', $patterns ),
				'tables' => self::matching_tables( $patterns ),
				'cron_hooks' => self::matching_cron( $patterns ),
			),
			'interpretation' => array(
				'mode' => 'heuristic-read-only',
				'warning' => __( 'Matches are candidates, not proof of ownership. Cleanup requires a dedicated adapter and a verified snapshot.', 'ua-free-migration-cleanup' ),
			),
		);

		$report['inspection_sha256'] = hash(
			'sha256',
			(string) wp_json_encode( $report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);

		return $report;
	}

	public static function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	private static function plugin_inventory(): array {
		$rows = array();
		foreach ( get_plugins() as $file => $data ) {
			$rows[] = array(
				'file' => $file,
				'slug' => dirname( $file ),
				'name' => isset( $data['Name'] ) ? wp_strip_all_tags( (string) $data['Name'] ) : '',
				'version' => isset( $data['Version'] ) ? (string) $data['Version'] : '',
				'author' => isset( $data['AuthorName'] ) ? wp_strip_all_tags( (string) $data['AuthorName'] ) : '',
				'text_domain' => isset( $data['TextDomain'] ) ? (string) $data['TextDomain'] : '',
				'status' => self::plugin_status( $file ),
			);
		}

		usort(
			$rows,
			static fn( array $a, array $b ): int => strcasecmp( $a['name'], $b['name'] )
		);
		return $rows;
	}

	private static function plugin_status( string $plugin_file ): string {
		if ( is_multisite() && is_plugin_active_for_network( $plugin_file ) ) {
			return 'network-active';
		}
		return is_plugin_active( $plugin_file ) ? 'active' : 'inactive';
	}

	private static function table_inventory(): array {
		global $wpdb;
		$rows = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only environment inventory is cached by environment().
		$out = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$name = isset( $row['Name'] ) ? (string) $row['Name'] : '';
			if ( '' === $name || ! str_starts_with( $name, $wpdb->prefix ) ) {
				continue;
			}
			$out[] = array(
				'name_hash' => hash( 'sha256', $name ),
				'name' => $name,
				'rows' => isset( $row['Rows'] ) ? (int) $row['Rows'] : 0,
				'data_bytes' => isset( $row['Data_length'] ) ? (int) $row['Data_length'] : 0,
				'index_bytes' => isset( $row['Index_length'] ) ? (int) $row['Index_length'] : 0,
				'engine' => isset( $row['Engine'] ) ? (string) $row['Engine'] : '',
			);
		}
		return $out;
	}

	private static function cron_inventory(): array {
		$cron = _get_cron_array();
		$hooks = array();
		$events = 0;
		foreach ( is_array( $cron ) ? $cron : array() as $timestamp => $items ) {
			unset( $timestamp );
			foreach ( is_array( $items ) ? $items : array() as $hook => $instances ) {
				$hooks[ (string) $hook ] = true;
				$events += is_array( $instances ) ? count( $instances ) : 0;
			}
		}
		$hook_names = array_keys( $hooks );
		sort( $hook_names );
		return array(
			'hook_count' => count( $hook_names ),
			'event_count' => $events,
			'hooks' => $hook_names,
		);
	}

	private static function plugin_patterns( string $plugin_file, array $data ): array {
		$candidates = array(
			dirname( $plugin_file ),
			basename( $plugin_file, '.php' ),
			isset( $data['TextDomain'] ) ? (string) $data['TextDomain'] : '',
			isset( $data['Name'] ) ? (string) $data['Name'] : '',
		);

		$stop = array(
			'wordpress', 'plugin', 'plugins', 'the', 'and', 'for', 'with', 'from',
			'free', 'pro', 'premium', 'official', 'core', 'easy', 'simple', 'website',
		);
		$tokens = array();

		foreach ( $candidates as $candidate ) {
			$normalized = strtolower( remove_accents( wp_strip_all_tags( $candidate ) ) );
			$normalized = preg_replace( '/[^a-z0-9_-]+/', '-', $normalized );
			$normalized = trim( (string) $normalized, '-_' );
			if ( strlen( $normalized ) >= 4 ) {
				$tokens[] = $normalized;
				$tokens[] = str_replace( '-', '_', $normalized );
			}
			foreach ( preg_split( '/[-_]+/', $normalized ) ?: array() as $part ) {
				if ( strlen( $part ) >= 4 && ! in_array( $part, $stop, true ) ) {
					$tokens[] = $part;
				}
			}
		}

		$tokens = array_values( array_unique( array_filter( $tokens ) ) );
		usort( $tokens, static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );
		return array_slice( $tokens, 0, 12 );
	}

	private static function matching_options( array $patterns ): array {
		global $wpdb;
		if ( empty( $patterns ) ) {
			return array( 'count' => 0, 'items' => array(), 'truncated' => false );
		}
		$where = array();
		$args = array();
		foreach ( $patterns as $pattern ) {
			$where[] = 'option_name LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $pattern ) . '%';
		}
		$sql = "SELECT option_name, autoload, LENGTH(option_value) AS bytes FROM {$wpdb->options} WHERE " . implode( ' OR ', $where ) . ' ORDER BY option_name LIMIT ' . ( self::MAX_MATCHES + 1 );
		$prepared = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared; inspection results are intentionally live.
		$truncated = count( $rows ) > self::MAX_MATCHES;
		$rows = array_slice( $rows, 0, self::MAX_MATCHES );
		return array(
			'count' => count( $rows ),
			'items' => array_map(
				static fn( array $row ): array => array(
					'name' => (string) $row['option_name'],
					'autoload' => (string) $row['autoload'],
					'bytes' => (int) $row['bytes'],
				),
				$rows
			),
			'truncated' => $truncated,
		);
	}

	private static function matching_meta( string $table, string $key_column, array $patterns ): array {
		global $wpdb;
		if ( empty( $patterns ) ) {
			return array( 'count' => 0, 'items' => array(), 'truncated' => false );
		}

		$allowed = array( $wpdb->postmeta, $wpdb->termmeta, $wpdb->usermeta );
		if ( ! in_array( $table, $allowed, true ) ) {
			return array( 'count' => 0, 'items' => array(), 'truncated' => false );
		}

		$where = array();
		$args = array();
		foreach ( $patterns as $pattern ) {
			$where[] = "{$key_column} LIKE %s";
			$args[] = '%' . $wpdb->esc_like( $pattern ) . '%';
		}
		$sql = "SELECT {$key_column} AS meta_key, COUNT(*) AS rows_count, SUM(LENGTH(meta_value)) AS bytes FROM `{$table}` WHERE " . implode( ' OR ', $where ) . " GROUP BY {$key_column} ORDER BY rows_count DESC LIMIT " . ( self::MAX_MATCHES + 1 );
		$prepared = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared; inspection results are intentionally live.
		$truncated = count( $rows ) > self::MAX_MATCHES;
		$rows = array_slice( $rows, 0, self::MAX_MATCHES );
		return array(
			'count' => array_sum( array_map( static fn( array $row ): int => (int) $row['rows_count'], $rows ) ),
			'items' => array_map(
				static fn( array $row ): array => array(
					'key' => (string) $row['meta_key'],
					'rows' => (int) $row['rows_count'],
					'bytes' => (int) $row['bytes'],
				),
				$rows
			),
			'truncated' => $truncated,
		);
	}

	private static function matching_tables( array $patterns ): array {
		$tables = self::table_inventory();
		return array_values(
			array_filter(
				$tables,
				static function ( array $table ) use ( $patterns ): bool {
					$name = strtolower( (string) $table['name'] );
					foreach ( $patterns as $pattern ) {
						if ( str_contains( $name, strtolower( $pattern ) ) ) {
							return true;
						}
					}
					return false;
				}
			)
		);
	}

	private static function matching_cron( array $patterns ): array {
		$cron = self::cron_inventory();
		$out = array();
		foreach ( $cron['hooks'] as $hook ) {
			foreach ( $patterns as $pattern ) {
				if ( str_contains( strtolower( $hook ), strtolower( $pattern ) ) ) {
					$out[] = $hook;
					break;
				}
			}
		}
		return $out;
	}

	private static function autoload_bytes(): int {
		global $wpdb;
		$autoload_values = array( 'yes', 'on', 'auto', 'auto-on' );
		$placeholders = implode( ',', array_fill( 0, count( $autoload_values ), '%s' ) );
		$sql = "SELECT COALESCE(SUM(LENGTH(option_name)+LENGTH(option_value)),0) FROM {$wpdb->options} WHERE autoload IN ({$placeholders})";
		$prepared = $wpdb->prepare( $sql, $autoload_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return self::single_int_query( $prepared );
	}

	private static function single_int_query( string $sql ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Caller supplies a fixed or prepared read-only aggregate query.
	}
}
