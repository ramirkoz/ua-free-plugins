<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UAFree_Static_Translate_Cleanup {

	const CLEAN_ACTION = 'uafree_st_cleanup_legacy_translators';
	const DOWNLOAD_ACTION = 'uafree_st_cleanup_download_backup';
	const LAST_OPTION = 'uafree_st_cleanup_last';
	const RESET_ACTION = 'uafree_st_reset_current_translations';
	const RESET_DOWNLOAD_ACTION = 'uafree_st_reset_download_backup';
	const RESET_LAST_OPTION = 'uafree_st_reset_current_last';
	const RESET_CONFIRM_PHRASE = 'СКИНУТИ ПОТОЧНІ ПЕРЕКЛАДИ';
	const CONFIRM_PHRASE = 'ВИДАЛИТИ ЗАЛИШКИ ПЕРЕКЛАДАЧІВ';

	public static function init(): void {
		add_action( 'admin_post_' . self::CLEAN_ACTION, array( __CLASS__, 'clean' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( __CLASS__, 'download_backup' ) );
		add_action( 'admin_post_' . self::RESET_ACTION, array( __CLASS__, 'reset_current' ) );
		add_action( 'admin_post_' . self::RESET_DOWNLOAD_ACTION, array( __CLASS__, 'download_current_backup' ) );
	}

	public static function render_section(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$scan = self::scan();
		$last = get_option( self::LAST_OPTION, array() );
		?>
		<h2>8. Очищення залишків попередніх перекладачів</h2>
		<div style="background:#fff;border:1px solid #dcdcde;padding:16px;max-width:1200px">
			<p>Плагін шукає залишки TranslatePress, WPML, Polylang, Weglot, GTranslate, старі мовні сторінки та застарілі таблиці ранніх версій UA FREE Static Translate. Поточні таблиці 0.6 не чіпає.</p>
			<table class="widefat striped">
				<tbody>
					<tr><th style="width:360px">Активні старі перекладачі</th><td><?php echo esc_html( empty( $scan['active_plugins'] ) ? 'не знайдено' : implode( ', ', $scan['active_plugins'] ) ); ?></td></tr>
					<tr><th>Таблиці для очищення</th><td><?php echo esc_html( number_format_i18n( count( $scan['tables'] ) ) ); ?></td></tr>
					<tr><th>Options</th><td><?php echo esc_html( number_format_i18n( count( $scan['options'] ) ) ); ?></td></tr>
					<tr><th>Meta rows</th><td><?php echo esc_html( number_format_i18n( $scan['meta_rows'] ) ); ?></td></tr>
					<tr><th>Cron hooks</th><td><?php echo esc_html( number_format_i18n( count( $scan['cron_hooks'] ) ) ); ?></td></tr>
					<tr><th>Legacy мовні сторінки</th><td><?php echo esc_html( number_format_i18n( count( $scan['legacy_pages'] ) ) ); ?></td></tr>
					<tr><th>Заборонені російські записи цього плагіна</th><td><?php echo esc_html( number_format_i18n( $scan['russian_rows'] ) ); ?></td></tr>
				</tbody>
			</table>

			<?php if ( ! empty( $scan['active_plugins'] ) ) : ?>
				<div class="notice notice-warning inline"><p>Спочатку деактивуй і видали старий перекладач. Очищення блокується, поки його код активний.</p></div>
			<?php elseif ( 0 === $scan['total_items'] ) : ?>
				<p><strong style="color:#0f5132">Залишків не знайдено.</strong></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::CLEAN_ACTION ); ?>">
					<?php wp_nonce_field( self::CLEAN_ACTION ); ?>
					<p><label>Введи фразу <code><?php echo esc_html( self::CONFIRM_PHRASE ); ?></code><br><input class="large-text" name="confirm_phrase" autocomplete="off" required></label></p>
					<p><button class="button button-primary" type="submit">Створити backup і очистити залишки</button></p>
				</form>
			<?php endif; ?>

			<?php if ( is_array( $last ) && ! empty( $last['backup_file'] ) ) : ?>
				<hr>
				<p><strong>Останнє очищення:</strong> <?php echo esc_html( (string) $last['completed_at'] ); ?>. SHA-256 <code><?php echo esc_html( (string) $last['sha256'] ); ?></code>.</p>
				<p><a class="button" href="<?php echo esc_url( self::download_url() ); ?>">Завантажити backup очищення</a></p>
			<?php endif; ?>
		</div>

		<h2>9. Скидання поточного стану перекладів 0.6</h2>
		<div style="background:#fff;border:1px solid #dcdcde;padding:16px;max-width:1200px">
			<p>
				Цей блок очищає поточні таблиці UA FREE Static Translate, де могли
				лишитися старі pilot-переклади на кшталт <code>gold</code> і
				<code>All gathered</code>.
			</p>
			<p>
				<strong>Azure key та налаштування зберігаються.</strong>
				Публічні мовні URL і автоматична черга після скидання вимикаються
				до нового контрольного перекладу.
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::RESET_ACTION ); ?>">
				<?php wp_nonce_field( self::RESET_ACTION ); ?>
				<p>
					<label>
						Введи фразу
						<code><?php echo esc_html( self::RESET_CONFIRM_PHRASE ); ?></code><br>
						<input type="text" name="confirm_phrase" style="width:100%;max-width:700px" autocomplete="off" required>
					</label>
				</p>
				<p>
					<button class="button button-secondary" type="submit">
						Створити backup і скинути поточні переклади
					</button>
				</p>
			</form>

			<?php
			$reset_last = get_option( self::RESET_LAST_OPTION, array() );
			if ( is_array( $reset_last ) && ! empty( $reset_last['completed_at'] ) ) :
			?>
				<hr>
				<p>
					Останнє скидання:
					<code><?php echo esc_html( (string) $reset_last['completed_at'] ); ?></code><br>
					SHA-256:
					<code><?php echo esc_html( (string) ( $reset_last['sha256'] ?? '' ) ); ?></code>
				</p>
				<p>
					<a class="button" href="<?php echo esc_url( self::current_backup_download_url() ); ?>">
						Завантажити backup поточного стану
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function known_plugins(): array {
		return array(
			'translatepress-multilingual/index.php' => 'TranslatePress',
			'sitepress-multilingual-cms/sitepress.php' => 'WPML',
			'polylang/polylang.php' => 'Polylang',
			'weglot/weglot.php' => 'Weglot',
			'gtranslate/gtranslate.php' => 'GTranslate',
			'google-language-translator/google-language-translator.php' => 'Google Language Translator',
		);
	}

	public static function scan(): array {
		global $wpdb;
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = array();
		foreach ( self::known_plugins() as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$active[] = $name;
			}
		}

		$all_tables = $wpdb->get_col( 'SHOW TABLES' );
		$patterns = array(
			$wpdb->prefix . 'trp_',
			$wpdb->prefix . 'icl_',
			$wpdb->prefix . 'weglot_',
			$wpdb->prefix . 'gtranslate_',
		);
		$obsolete_exact = array(
			$wpdb->prefix . 'uafree_st_entries',
			$wpdb->prefix . 'uafree_st_segments',
			$wpdb->prefix . 'uafree_st_glossary',
		);
		$tables = array();
		foreach ( is_array( $all_tables ) ? $all_tables : array() as $table ) {
			foreach ( $patterns as $prefix ) {
				if ( 0 === strpos( (string) $table, $prefix ) ) {
					$tables[] = (string) $table;
				}
			}
			if ( in_array( (string) $table, $obsolete_exact, true ) ) {
				$tables[] = (string) $table;
			}
		}
		$tables = array_values( array_unique( $tables ) );

		$option_rows = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options}
			WHERE option_name LIKE 'trp\\_%'
				OR option_name LIKE 'icl\\_%'
				OR option_name LIKE 'weglot\\_%'
				OR option_name LIKE 'gtranslate\\_%'
				OR option_name LIKE 'google_language_translator%'
				OR option_name LIKE 'polylang%'
				OR option_name IN ('sitepress','uafree_st_db_version')"
		);
		$options = array_values( array_filter( array_map( 'strval', (array) $option_rows ) ) );

		$meta_conditions = array(
			"meta_key LIKE 'trp\\_%'", "meta_key LIKE '\\_trp\\_%'",
			"meta_key LIKE 'icl\\_%'", "meta_key LIKE '\\_icl\\_%'",
			"meta_key LIKE 'weglot\\_%'", "meta_key LIKE 'gtranslate\\_%'",
		);
		$meta_rows = 0;
		foreach ( array( $wpdb->postmeta, $wpdb->termmeta, $wpdb->usermeta ) as $table ) {
			$meta_rows += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' OR ', $meta_conditions ) );
		}

		$cron = _get_cron_array();
		$cron_hooks = array();
		foreach ( is_array( $cron ) ? $cron : array() as $timestamp => $hooks ) {
			foreach ( array_keys( (array) $hooks ) as $hook ) {
				if ( preg_match( '/(?:trp|translatepress|wpml|icl_|polylang|weglot|gtranslate)/i', (string) $hook ) ) {
					$cron_hooks[] = (string) $hook;
				}
			}
		}
		$cron_hooks = array_values( array_unique( $cron_hooks ) );

		$legacy_pages = $wpdb->get_results(
			"SELECT ID, post_title, post_status, post_name FROM {$wpdb->posts}
			WHERE post_type = 'page' AND post_status NOT IN ('trash','auto-draft')",
			ARRAY_A
		);
		$legacy_pages = array_values( array_filter( (array) $legacy_pages, static function ( array $row ): bool {
			$title = html_entity_decode( (string) $row['post_title'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			return (bool) preg_match( '/\\s-\\s(?:English|Deutsch|Español|Français|Polski|Русский|日本語|العربية|Português|简体中文|हिन्दी)\\s*$/iu', $title );
		} ) );

		$russian_rows = 0;
		foreach ( array( $wpdb->prefix . 'uafree_st_queue', $wpdb->prefix . 'uafree_st_translations', $wpdb->prefix . 'uafree_st_memory', $wpdb->prefix . 'uafree_st_usage' ) as $table ) {
			if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				$russian_rows += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE language = 'ru'" );
			}
		}

		$total = count( $tables ) + count( $options ) + $meta_rows + count( $cron_hooks ) + count( $legacy_pages ) + $russian_rows;
		return array(
			'active_plugins' => $active,
			'tables' => $tables,
			'options' => $options,
			'meta_rows' => $meta_rows,
			'cron_hooks' => $cron_hooks,
			'legacy_pages' => $legacy_pages,
			'russian_rows' => $russian_rows,
			'total_items' => $total,
		);
	}

	public static function clean(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостатньо прав.' );
		}
		check_admin_referer( self::CLEAN_ACTION );
		$phrase = isset( $_POST['confirm_phrase'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm_phrase'] ) ) : '';
		if ( self::CONFIRM_PHRASE !== $phrase ) {
			wp_die( 'Фраза підтвердження не збігається.' );
		}
		$scan = self::scan();
		if ( ! empty( $scan['active_plugins'] ) ) {
			wp_die( 'Старий перекладач ще активний.' );
		}
		$backup = self::create_backup( $scan );
		self::perform_cleanup( $scan );
		$last = array(
			'completed_at' => current_time( 'c' ),
			'backup_file' => $backup['file'],
			'sha256' => $backup['sha256'],
			'scan' => $scan,
		);
		update_option( self::LAST_OPTION, $last, false );
		flush_rewrite_rules( false );
		wp_safe_redirect( admin_url( 'admin.php?page=uafree-static-translate-auto' ) );
		exit;
	}

	private static function create_backup( array $scan ): array {
		global $wpdb;
		$uploads = wp_upload_dir();
		$dir = trailingslashit( $uploads['basedir'] ) . 'uafree-private';
		wp_mkdir_p( $dir );
		@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		@file_put_contents( $dir . '/.htaccess', "Deny from all\n" );
		$file = $dir . '/translation-cleanup-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 8, false, false ) . '.jsonl.gz';
		$gz = gzopen( $file, 'wb9' );
		if ( ! $gz ) {
			wp_die( 'Не вдалося створити backup.' );
		}
		$write = static function ( array $row ) use ( $gz ): void {
			gzwrite( $gz, wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
		};
		$write( array( 'type' => 'manifest', 'generated_at' => current_time( 'c' ), 'scan' => $scan ) );
		foreach ( $scan['tables'] as $table ) {
			if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) { continue; }
			$create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
			$write( array( 'type' => 'table_schema', 'table' => $table, 'create_sql' => $create[1] ?? '' ) );
			$offset = 0;
			do {
				$rows = $wpdb->get_results( "SELECT * FROM `{$table}` LIMIT 500 OFFSET {$offset}", ARRAY_A );
				foreach ( (array) $rows as $row ) { $write( array( 'type' => 'table_row', 'table' => $table, 'row' => $row ) ); }
				$offset += count( (array) $rows );
			} while ( count( (array) $rows ) === 500 );
		}
		if ( ! empty( $scan['options'] ) ) {
			$ph = implode( ',', array_fill( 0, count( $scan['options'] ), '%s' ) );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->options} WHERE option_name IN ({$ph})", ...$scan['options'] ), ARRAY_A );
			foreach ( (array) $rows as $row ) { $write( array( 'type' => 'option', 'row' => $row ) ); }
		}
		$meta_conditions = "meta_key LIKE 'trp\\_%' OR meta_key LIKE '\\_trp\\_%' OR meta_key LIKE 'icl\\_%' OR meta_key LIKE '\\_icl\\_%' OR meta_key LIKE 'weglot\\_%' OR meta_key LIKE 'gtranslate\\_%'";
		foreach ( array( 'postmeta' => $wpdb->postmeta, 'termmeta' => $wpdb->termmeta, 'usermeta' => $wpdb->usermeta ) as $kind => $meta_table ) {
			$offset = 0;
			do {
				$rows = $wpdb->get_results( "SELECT * FROM {$meta_table} WHERE {$meta_conditions} LIMIT 500 OFFSET {$offset}", ARRAY_A );
				foreach ( (array) $rows as $row ) { $write( array( 'type' => $kind, 'row' => $row ) ); }
				$offset += count( (array) $rows );
			} while ( count( (array) $rows ) === 500 );
		}
		$write( array( 'type' => 'cron_hooks', 'hooks' => $scan['cron_hooks'] ) );
		foreach ( $scan['legacy_pages'] as $page ) {
			$post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", (int) $page['ID'] ), ARRAY_A );
			$meta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->postmeta} WHERE post_id = %d", (int) $page['ID'] ), ARRAY_A );
			$write( array( 'type' => 'legacy_page', 'post' => $post, 'meta' => $meta ) );
		}
		gzclose( $gz );
		return array( 'file' => $file, 'sha256' => hash_file( 'sha256', $file ) );
	}

	private static function perform_cleanup( array $scan ): void {
		global $wpdb;
		foreach ( $scan['tables'] as $table ) {
			if ( preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
				$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
			}
		}
		if ( ! empty( $scan['options'] ) ) {
			$ph = implode( ',', array_fill( 0, count( $scan['options'] ), '%s' ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name IN ({$ph})", ...$scan['options'] ) );
		}
		$conds = "meta_key LIKE 'trp\\_%' OR meta_key LIKE '\\_trp\\_%' OR meta_key LIKE 'icl\\_%' OR meta_key LIKE '\\_icl\\_%' OR meta_key LIKE 'weglot\\_%' OR meta_key LIKE 'gtranslate\\_%'";
		foreach ( array( $wpdb->postmeta, $wpdb->termmeta, $wpdb->usermeta ) as $table ) {
			$wpdb->query( "DELETE FROM {$table} WHERE {$conds}" );
		}
		$cron = _get_cron_array();
		foreach ( is_array( $cron ) ? $cron : array() as $timestamp => $hooks ) {
			foreach ( array_keys( (array) $hooks ) as $hook ) {
				if ( in_array( (string) $hook, $scan['cron_hooks'], true ) ) {
					wp_clear_scheduled_hook( (string) $hook );
				}
			}
		}
		foreach ( $scan['legacy_pages'] as $page ) {
			wp_trash_post( (int) $page['ID'] );
		}
		foreach ( array( $wpdb->prefix . 'uafree_st_queue', $wpdb->prefix . 'uafree_st_translations', $wpdb->prefix . 'uafree_st_memory', $wpdb->prefix . 'uafree_st_usage' ) as $table ) {
			if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				$wpdb->query( "DELETE FROM {$table} WHERE language = 'ru'" );
			}
		}
	}

	private static function download_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DOWNLOAD_ACTION ), self::DOWNLOAD_ACTION );
	}

	public static function download_backup(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Недостатньо прав.' ); }
		check_admin_referer( self::DOWNLOAD_ACTION );
		$last = get_option( self::LAST_OPTION, array() );
		$file = is_array( $last ) ? (string) ( $last['backup_file'] ?? '' ) : '';
		if ( '' === $file || ! is_file( $file ) ) { wp_die( 'Backup не знайдено.' ); }
		nocache_headers();
		header( 'Content-Type: application/gzip' );
		header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '"' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file );
		exit;
	}

	public static function reset_current(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостатньо прав.' );
		}

		check_admin_referer( self::RESET_ACTION );

		$phrase = isset( $_POST['confirm_phrase'] )
			? sanitize_text_field( wp_unslash( $_POST['confirm_phrase'] ) )
			: '';

		if ( self::RESET_CONFIRM_PHRASE !== $phrase ) {
			wp_die( 'Фраза підтвердження не збігається.' );
		}

		$backup = self::create_current_backup();
		$result = UAFree_Static_Translate_Autonomous::reset_current_translation_state();

		if ( empty( $result['success'] ) ) {
			wp_die( esc_html( (string) ( $result['message'] ?? 'Скидання не виконано.' ) ) );
		}

		update_option(
			self::RESET_LAST_OPTION,
			array(
				'completed_at' => current_time( 'c' ),
				'backup_file' => $backup['file'],
				'sha256' => $backup['sha256'],
				'counts' => $result['counts'] ?? array(),
				'message' => $result['message'],
			),
			false
		);

		wp_safe_redirect(
			add_query_arg(
				'uafree_reset_done',
				'1',
				admin_url( 'admin.php?page=uafree-static-translate-auto' )
			)
		);
		exit;
	}

	private static function current_tables(): array {
		global $wpdb;

		return array(
			$wpdb->prefix . 'uafree_st_sources',
			$wpdb->prefix . 'uafree_st_source_segments',
			$wpdb->prefix . 'uafree_st_translations',
			$wpdb->prefix . 'uafree_st_memory',
			$wpdb->prefix . 'uafree_st_queue',
			$wpdb->prefix . 'uafree_st_usage',
			$wpdb->prefix . 'uafree_st_logs',
		);
	}

	private static function create_current_backup(): array {
		global $wpdb;

		$uploads = wp_upload_dir();
		$dir = trailingslashit( $uploads['basedir'] ) . 'uafree-private';
		wp_mkdir_p( $dir );
		@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		@file_put_contents( $dir . '/.htaccess', "Deny from all\n" );

		$file = $dir . '/current-translation-reset-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 8, false, false ) . '.jsonl.gz';
		$gz = gzopen( $file, 'wb9' );

		if ( ! $gz ) {
			wp_die( 'Не вдалося створити backup поточного стану.' );
		}

		$write = static function ( array $row ) use ( $gz ): void {
			gzwrite(
				$gz,
				wp_json_encode(
					$row,
					JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
				) . "\n"
			);
		};

		$write(
			array(
				'type' => 'manifest',
				'generated_at' => current_time( 'c' ),
				'plugin_version' => UAFREE_ST_VERSION,
				'purpose' => 'backup before resetting current UA FREE translation state',
			)
		);

		foreach ( self::current_tables() as $table ) {
			if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
				continue;
			}

			$exists = $table === $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
			);

			if ( ! $exists ) {
				continue;
			}

			$create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
			$write(
				array(
					'type' => 'table_schema',
					'table' => $table,
					'create_sql' => $create[1] ?? '',
				)
			);

			$offset = 0;
			do {
				$rows = $wpdb->get_results(
					"SELECT * FROM `{$table}` LIMIT 500 OFFSET {$offset}",
					ARRAY_A
				);

				foreach ( (array) $rows as $row ) {
					$write(
						array(
							'type' => 'table_row',
							'table' => $table,
							'row' => $row,
						)
					);
				}

				$count = count( (array) $rows );
				$offset += $count;
			} while ( 500 === $count );
		}

		$write(
			array(
				'type' => 'settings_snapshot',
				'settings' => get_option( 'uafree_st_auto_settings', array() ),
				'runtime' => get_option( 'uafree_st_auto_runtime', array() ),
			)
		);

		gzclose( $gz );

		return array(
			'file' => $file,
			'sha256' => hash_file( 'sha256', $file ),
		);
	}

	private static function current_backup_download_url(): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::RESET_DOWNLOAD_ACTION ),
			self::RESET_DOWNLOAD_ACTION
		);
	}

	public static function download_current_backup(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостатньо прав.' );
		}

		check_admin_referer( self::RESET_DOWNLOAD_ACTION );

		$last = get_option( self::RESET_LAST_OPTION, array() );
		$file = is_array( $last )
			? (string) ( $last['backup_file'] ?? '' )
			: '';

		if ( '' === $file || ! is_file( $file ) ) {
			wp_die( 'Backup поточного стану не знайдено.' );
		}

		nocache_headers();
		header( 'Content-Type: application/gzip' );
		header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '"' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file );
		exit;
	}

}
