<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UAFree_MC_Admin {
	const PAGE_SLUG = 'ua-free-migration-cleanup';
	const SUITE_SLUG = 'uafree-suite';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'remove_legacy_menus' ), 10000 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		UAFree_MC_Snapshot_Manager::init();
	}

	public static function register_menu(): void {
		global $admin_page_hooks;
		if ( empty( $admin_page_hooks[ self::SUITE_SLUG ] ) ) {
			add_menu_page(
				'UA FREE Plugins',
				'UA FREE',
				'manage_options',
				self::SUITE_SLUG,
				array( __CLASS__, 'render_suite_page' ),
				'dashicons-admin-plugins',
				58
			);
		}

		add_submenu_page(
			self::SUITE_SLUG,
			'UA FREE Migration & Cleanup',
			__( 'Migration & Cleanup', 'ua-free-migration-cleanup' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function remove_legacy_menus(): void {
		remove_submenu_page( 'tools.php', self::PAGE_SLUG );
		remove_submenu_page( 'options-general.php', self::PAGE_SLUG );
	}

	public static function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) && false === strpos( $hook, self::SUITE_SLUG ) ) {
			return;
		}
		$css = '
		.uafree-wrap{max-width:1280px}.uafree-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}.uafree-card{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:18px}.uafree-card h2,.uafree-card h3{margin-top:0}.uafree-status{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:600}.uafree-status-active,.uafree-status-network-active{background:#d7f5dd;color:#14532d}.uafree-status-inactive{background:#fff3cd;color:#664d03}.uafree-status-available{background:#eef1f4;color:#3c434a}.uafree-muted{color:#646970}.uafree-kpi{font-size:28px;font-weight:650;line-height:1.2}.uafree-actions{display:flex;gap:8px;flex-wrap:wrap}.uafree-help{border-left:4px solid #72aee6;padding:10px 14px;background:#f6f7f7}.uafree-table code{white-space:normal;word-break:break-word}.uafree-danger-note{border-left:4px solid #d63638;background:#fcf0f1;padding:10px 14px}.uafree-good-note{border-left:4px solid #00a32a;background:#edfaef;padding:10px 14px}
		';
		wp_register_style( 'uafree-mc-admin', false, array(), UAFREE_MC_VERSION );
		wp_enqueue_style( 'uafree-mc-admin' );
		wp_add_inline_style( 'uafree-mc-admin', $css );
	}

	public static function render_suite_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		self::suite_cards();
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab_input = filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW );
		$tab = is_string( $tab_input ) ? sanitize_key( $tab_input ) : 'overview';
		$tabs = array(
			'overview' => __( 'Overview', 'ua-free-migration-cleanup' ),
			'environment' => __( 'Environment', 'ua-free-migration-cleanup' ),
			'support' => __( 'About & Support', 'ua-free-migration-cleanup' ),
		);
		?>
		<div class="wrap uafree-wrap">
			<h1><?php echo esc_html__( 'UA FREE Migration & Cleanup', 'ua-free-migration-cleanup' ); ?></h1>
			<p class="uafree-muted"><?php echo esc_html__( 'Створює контрольований snapshot, перевіряє середовище та прибирає лише підтверджені залишки плагінів.', 'ua-free-migration-cleanup' ); ?></p>
			<nav class="nav-tab-wrapper" aria-label="<?php echo esc_attr__( 'Plugin sections', 'ua-free-migration-cleanup' ); ?>">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			switch ( $tab ) {
				case 'environment':
					self::environment_tab();
					break;
				case 'support':
					self::support_tab();
					break;
				default:
					self::overview_tab();
			}
			?>
		</div>
		<?php
	}

	private static function overview_tab(): void {
		$report = UAFree_MC_Environment_Scanner::environment();
		$active = count( array_filter( $report['plugins'], static fn( array $row ): bool => in_array( $row['status'], array( 'active', 'network-active' ), true ) ) );
		?>
		<?php $rescanned = filter_input( INPUT_GET, 'rescanned', FILTER_VALIDATE_INT ); ?>
		<?php if ( 1 === $rescanned ) : ?>
			<div class="notice notice-success inline"><p><?php echo esc_html__( 'The environment inventory was refreshed.', 'ua-free-migration-cleanup' ); ?></p></div>
		<?php endif; ?>
		<div class="uafree-grid" style="margin-top:18px">
			<div class="uafree-card"><div class="uafree-kpi"><?php echo esc_html( (string) count( $report['plugins'] ) ); ?></div><p><?php echo esc_html__( 'Installed plugins', 'ua-free-migration-cleanup' ); ?></p></div>
			<div class="uafree-card"><div class="uafree-kpi"><?php echo esc_html( (string) $active ); ?></div><p><?php echo esc_html__( 'Active plugins', 'ua-free-migration-cleanup' ); ?></p></div>
			<div class="uafree-card"><div class="uafree-kpi"><?php echo esc_html( (string) $report['database']['table_count'] ); ?></div><p><?php echo esc_html__( 'WordPress database tables', 'ua-free-migration-cleanup' ); ?></p></div>
			<div class="uafree-card"><div class="uafree-kpi"><?php echo esc_html( size_format( (int) $report['database']['autoload_bytes'] ) ); ?></div><p><?php echo esc_html__( 'Estimated autoload size', 'ua-free-migration-cleanup' ); ?></p></div>
		</div>
		<div class="uafree-card" style="margin-top:16px">
			<h2><?php echo esc_html__( 'Safety model', 'ua-free-migration-cleanup' ); ?></h2>
			<div class="uafree-good-note"><strong><?php echo esc_html__( 'Read-only by default.', 'ua-free-migration-cleanup' ); ?></strong> <?php echo esc_html__( 'The universal scanner does not delete data, change plugin state, export option values or collect personal data.', 'ua-free-migration-cleanup' ); ?></div>
			<p><?php echo esc_html__( 'Candidate matches are heuristic. A plugin-specific adapter, dry run, snapshot and explicit confirmation are required before any cleanup operation can exist.', 'ua-free-migration-cleanup' ); ?></p>
			<div class="uafree-actions">
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=uafree_mc_export_environment' ), 'uafree_mc_export_environment' ) ); ?>"><?php echo esc_html__( 'Export environment snapshot', 'ua-free-migration-cleanup' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=uafree_mc_rescan' ), 'uafree_mc_rescan' ) ); ?>"><?php echo esc_html__( 'Refresh inventory', 'ua-free-migration-cleanup' ); ?></a>
			</div>
		</div>
		<div class="uafree-card" style="margin-top:16px">
			<h2><?php echo esc_html__( 'How to use this version', 'ua-free-migration-cleanup' ); ?></h2>
			<ol>
				<li><?php echo esc_html__( 'Open the Environment tab and inspect the installed plugin list.', 'ua-free-migration-cleanup' ); ?></li>
				<li><?php echo esc_html__( 'Open a plugin inspection to review likely options, metadata, tables and cron hooks.', 'ua-free-migration-cleanup' ); ?></li>
				<li><?php echo esc_html__( 'Export the JSON snapshot before developing or enabling a cleanup adapter.', 'ua-free-migration-cleanup' ); ?></li>
				<li><?php echo esc_html__( 'Do not treat heuristic matches as confirmed ownership.', 'ua-free-migration-cleanup' ); ?></li>
			</ol>
		</div>
		<?php
	}

	private static function environment_tab(): void {
		$plugin_file = '';
		$inspect_input = filter_input( INPUT_GET, 'inspect', FILTER_UNSAFE_RAW );
		if ( is_string( $inspect_input ) && '' !== $inspect_input ) {
			check_admin_referer( 'uafree_mc_inspect_plugin' );
			$plugin_file = sanitize_text_field( $inspect_input );
		}
		if ( '' !== $plugin_file ) {
			self::inspection_view( $plugin_file );
			return;
		}

		$report = UAFree_MC_Environment_Scanner::environment();
		?>
		<div class="uafree-card" style="margin-top:18px">
			<h2><?php echo esc_html__( 'Installed plugins', 'ua-free-migration-cleanup' ); ?></h2>
			<p><?php echo esc_html__( 'Inspect a plugin to find likely database and cron leftovers. The scan is read-only and intentionally conservative.', 'ua-free-migration-cleanup' ); ?></p>
			<table class="widefat striped uafree-table">
				<thead><tr><th><?php echo esc_html__( 'Plugin', 'ua-free-migration-cleanup' ); ?></th><th><?php echo esc_html__( 'Status', 'ua-free-migration-cleanup' ); ?></th><th><?php echo esc_html__( 'Version', 'ua-free-migration-cleanup' ); ?></th><th><?php echo esc_html__( 'File', 'ua-free-migration-cleanup' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $report['plugins'] as $plugin ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $plugin['name'] ); ?></strong><br><span class="uafree-muted"><?php echo esc_html( $plugin['author'] ); ?></span></td>
						<td><?php self::status_badge( $plugin['status'] ); ?></td>
						<td><?php echo esc_html( $plugin['version'] ?: '—' ); ?></td>
						<td><code><?php echo esc_html( $plugin['file'] ); ?></code></td>
						<td><a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=environment&inspect=' . rawurlencode( $plugin['file'] ) ), 'uafree_mc_inspect_plugin' ) ); ?>"><?php echo esc_html__( 'Inspect', 'ua-free-migration-cleanup' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function inspection_view( string $plugin_file ): void {
		$report = UAFree_MC_Environment_Scanner::inspect_plugin( $plugin_file );
		if ( is_wp_error( $report ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $report->get_error_message() ) . '</p></div>';
			return;
		}
		$likely = $report['likely_data'];
		?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=environment' ) ); ?>">&larr; <?php echo esc_html__( 'Back to plugin inventory', 'ua-free-migration-cleanup' ); ?></a></p>
		<div class="uafree-card">
			<h2><?php echo esc_html( $report['plugin']['name'] ); ?></h2>
			<p><code><?php echo esc_html( $report['plugin']['file'] ); ?></code> · <?php self::status_badge( $report['plugin']['status'] ); ?></p>
			<div class="uafree-help"><strong><?php echo esc_html__( 'Detected search patterns:', 'ua-free-migration-cleanup' ); ?></strong> <code><?php echo esc_html( implode( ', ', $report['patterns'] ) ); ?></code></div>
			<p class="uafree-danger-note"><?php echo esc_html( $report['interpretation']['warning'] ); ?></p>
			<div class="uafree-actions">
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=uafree_mc_export_plugin&plugin_file=' . rawurlencode( $plugin_file ) ), 'uafree_mc_export_plugin' ) ); ?>"><?php echo esc_html__( 'Export plugin inspection', 'ua-free-migration-cleanup' ); ?></a>
			</div>
		</div>
		<div class="uafree-grid" style="margin-top:16px">
			<?php self::candidate_card( __( 'Options', 'ua-free-migration-cleanup' ), $likely['options']['count'], $likely['options']['items'], 'name' ); ?>
			<?php self::candidate_card( __( 'Post metadata', 'ua-free-migration-cleanup' ), $likely['postmeta']['count'], $likely['postmeta']['items'], 'key' ); ?>
			<?php self::candidate_card( __( 'Term metadata', 'ua-free-migration-cleanup' ), $likely['termmeta']['count'], $likely['termmeta']['items'], 'key' ); ?>
			<?php self::candidate_card( __( 'User metadata', 'ua-free-migration-cleanup' ), $likely['usermeta']['count'], $likely['usermeta']['items'], 'key' ); ?>
			<?php self::candidate_card( __( 'Database tables', 'ua-free-migration-cleanup' ), count( $likely['tables'] ), $likely['tables'], 'name' ); ?>
			<?php self::candidate_card( __( 'Cron hooks', 'ua-free-migration-cleanup' ), count( $likely['cron_hooks'] ), array_map( static fn( string $hook ): array => array( 'name' => $hook ), $likely['cron_hooks'] ), 'name' ); ?>
		</div>
		<?php
	}

	private static function candidate_card( string $title, int $count, array $items, string $key ): void {
		?>
		<div class="uafree-card">
			<h3><?php echo esc_html( $title ); ?> <span class="uafree-muted">(<?php echo esc_html( (string) $count ); ?>)</span></h3>
			<?php if ( empty( $items ) ) : ?>
				<p class="uafree-muted"><?php echo esc_html__( 'No likely matches found.', 'ua-free-migration-cleanup' ); ?></p>
			<?php else : ?>
				<ul>
				<?php foreach ( array_slice( $items, 0, 12 ) as $item ) : ?>
					<li><code><?php echo esc_html( isset( $item[ $key ] ) ? (string) $item[ $key ] : '' ); ?></code><?php if ( isset( $item['rows'] ) ) : ?> <span class="uafree-muted">× <?php echo esc_html( (string) $item['rows'] ); ?></span><?php endif; ?></li>
				<?php endforeach; ?>
				</ul>
				<?php if ( count( $items ) > 12 ) : ?><p class="uafree-muted"><?php echo esc_html__( 'The exported JSON contains the full limited result set.', 'ua-free-migration-cleanup' ); ?></p><?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function suite_cards(): void {
		?>
		<div class="wrap uafree-wrap">
			<h1><?php echo esc_html__( 'UA FREE Plugin Suite', 'ua-free-migration-cleanup' ); ?></h1>
			<p><?php echo esc_html__( 'The suite was born from real operational needs of a charitable foundation website and is being rebuilt as a universal collection of lightweight WordPress tools.', 'ua-free-migration-cleanup' ); ?></p>
			<div class="uafree-grid">
			<?php foreach ( UAFree_MC_Suite_Registry::status() as $item ) : ?>
				<div class="uafree-card">
					<h2><?php echo esc_html( $item['name'] ); ?></h2>
					<?php self::status_badge( $item['status'] ); ?>
					<?php if ( $item['version'] ) : ?><span class="uafree-muted"> v<?php echo esc_html( $item['version'] ); ?></span><?php endif; ?>
					<p><?php echo esc_html( $item['description'] ); ?></p>
					<?php if ( 'available' === $item['status'] ) : ?><p class="uafree-muted"><?php echo esc_html__( 'Not installed yet. The public repository link will appear after release.', 'ua-free-migration-cleanup' ); ?></p><?php endif; ?>
				</div>
			<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function support_tab(): void {
		?>
		<div class="uafree-grid" style="margin-top:18px">
			<div class="uafree-card">
				<h2><?php echo esc_html__( 'Project origin', 'ua-free-migration-cleanup' ); ?></h2>
				<p><?php echo esc_html__( 'This plugin was originally created to solve real operational needs of a charitable foundation website and was later rebuilt as a universal WordPress tool.', 'ua-free-migration-cleanup' ); ?></p>
			</div>
			<div class="uafree-card">
				<h2><?php echo esc_html__( 'Support the foundation', 'ua-free-migration-cleanup' ); ?></h2>
				<p><?php echo esc_html__( 'You can donate, share the foundation’s work or publish a link to it.', 'ua-free-migration-cleanup' ); ?></p>
				<p><a class="button button-primary" href="https://uafree.org/donate/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open the foundation donation page', 'ua-free-migration-cleanup' ); ?></a></p>
			</div>
			<div class="uafree-card">
				<h2><?php echo esc_html__( 'Support development', 'ua-free-migration-cleanup' ); ?></h2>
				<p><?php echo esc_html__( 'Development support is separate from charitable donations. The dedicated privacy-first cryptocurrency support page will be linked here after it is published.', 'ua-free-migration-cleanup' ); ?></p>
			</div>
		</div>
		<?php
	}

	private static function status_badge( string $status ): void {
		$labels = array(
			'active' => __( 'Active', 'ua-free-migration-cleanup' ),
			'network-active' => __( 'Network active', 'ua-free-migration-cleanup' ),
			'inactive' => __( 'Inactive', 'ua-free-migration-cleanup' ),
			'available' => __( 'Available', 'ua-free-migration-cleanup' ),
		);
		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		echo '<span class="uafree-status uafree-status-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
	}
}
