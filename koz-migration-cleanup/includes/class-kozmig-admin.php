<?php
namespace ramirkz\kozmig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZMIG_Admin {
	public const PAGE_SLUG = 'koz-migration-cleanup';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'remove_legacy_menus' ), 10000 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		KOZMIG_Snapshot_Manager::init();
	}

	public static function register_menu(): void {
		$parent = KOZMIG_Suite_Registry::suite_page();
		add_submenu_page(
			$parent,
			__( 'KOZ Migration & Cleanup', 'koz-migration-cleanup' ),
			__( 'Migration & Cleanup', 'koz-migration-cleanup' ),
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
		if ( false === strpos( $hook, self::PAGE_SLUG ) && false === strpos( $hook, KOZMIG_Suite_Registry::fallback_page() ) ) {
			return;
		}
		wp_enqueue_style(
			'kozmig-mc-admin',
			KOZMIG_URL . 'assets/admin.css',
			array(),
			KOZMIG_VERSION
		);
	}


	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab_input = filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW );
		$tab = is_string( $tab_input ) ? sanitize_key( $tab_input ) : 'overview';
		$tabs = array(
			'overview'    => __( 'Overview', 'koz-migration-cleanup' ),
			'environment' => __( 'Environment', 'koz-migration-cleanup' ),
		);

		if ( ! array_key_exists( $tab, $tabs ) ) {
			$tab = 'overview';
		}
		?>
		<div class="wrap kozmig-wrap">
			<h1><?php echo esc_html__( 'KOZ Migration & Cleanup', 'koz-migration-cleanup' ); ?></h1>
			<p class="kozmig-muted"><?php echo esc_html__( 'Creates a controlled snapshot, checks the environment and identifies likely plugin leftovers without deleting them.', 'koz-migration-cleanup' ); ?></p>
			<nav class="nav-tab-wrapper" aria-label="<?php echo esc_attr__( 'Plugin sections', 'koz-migration-cleanup' ); ?>">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			switch ( $tab ) {
				case 'environment':
					self::environment_tab();
					break;
				default:
					self::overview_tab();
			}
			?>
		</div>
		<?php
	}

	private static function overview_tab(): void {
		$report = KOZMIG_Environment_Scanner::environment();
		$active = count( array_filter( $report['plugins'], static fn( array $row ): bool => in_array( $row['status'], array( 'active', 'network-active' ), true ) ) );
		$migration = isset( $report['migration'] ) && is_array( $report['migration'] ) ? $report['migration'] : array();
		?>
		<?php $rescanned = filter_input( INPUT_GET, 'rescanned', FILTER_VALIDATE_INT ); ?>
		<?php if ( 1 === $rescanned ) : ?>
			<div class="notice notice-success inline"><p><?php echo esc_html__( 'The environment inventory was refreshed.', 'koz-migration-cleanup' ); ?></p></div>
		<?php endif; ?>
		<div class="kozmig-grid" style="margin-top:18px">
			<div class="kozmig-card"><div class="kozmig-kpi"><?php echo esc_html( (string) count( $report['plugins'] ) ); ?></div><p><?php echo esc_html__( 'Installed plugins', 'koz-migration-cleanup' ); ?></p></div>
			<div class="kozmig-card"><div class="kozmig-kpi"><?php echo esc_html( (string) $active ); ?></div><p><?php echo esc_html__( 'Active plugins', 'koz-migration-cleanup' ); ?></p></div>
			<div class="kozmig-card"><div class="kozmig-kpi"><?php echo esc_html( (string) $report['database']['table_count'] ); ?></div><p><?php echo esc_html__( 'WordPress database tables', 'koz-migration-cleanup' ); ?></p></div>
			<div class="kozmig-card"><div class="kozmig-kpi"><?php echo esc_html( size_format( (int) $report['database']['autoload_bytes'] ) ); ?></div><p><?php echo esc_html__( 'Estimated autoload size', 'koz-migration-cleanup' ); ?></p></div>
		</div>
		<div class="kozmig-card" style="margin-top:16px">
			<h2><?php echo esc_html__( 'Migration candidates', 'koz-migration-cleanup' ); ?></h2>
			<p><?php echo esc_html__( 'Read-only checks for placeholder pages, legacy attachment links and old slugs that may need a verified redirect map.', 'koz-migration-cleanup' ); ?></p>
			<div class="kozmig-grid">
				<div class="kozmig-card"><div class="kozmig-kpi"><?php echo esc_html( (string) ( $migration['placeholder_pages']['count'] ?? 0 ) ); ?></div><p><?php echo esc_html__( 'Placeholder pages', 'koz-migration-cleanup' ); ?></p></div>
				<div class="kozmig-card"><div class="kozmig-kpi"><?php echo esc_html( (string) ( $migration['legacy_attachment_links']['query_link_count'] ?? 0 ) ); ?></div><p><?php echo esc_html__( 'Legacy attachment links', 'koz-migration-cleanup' ); ?></p></div>
				<div class="kozmig-card"><div class="kozmig-kpi"><?php echo esc_html( (string) ( $migration['old_slug_redirect_map']['count'] ?? 0 ) ); ?></div><p><?php echo esc_html__( 'Old-slug redirect candidates', 'koz-migration-cleanup' ); ?></p></div>
			</div>
			<div class="kozmig-good-note" style="margin-top:12px"><strong><?php echo esc_html__( 'No redirects are created automatically.', 'koz-migration-cleanup' ); ?></strong> <?php echo esc_html__( 'Export the environment snapshot and verify traffic, backlinks and destination content before applying any 301 redirect.', 'koz-migration-cleanup' ); ?></div>
		</div>
		<div class="kozmig-card" style="margin-top:16px">
			<h2><?php echo esc_html__( 'Safety model', 'koz-migration-cleanup' ); ?></h2>
			<div class="kozmig-good-note"><strong><?php echo esc_html__( 'Read-only by default.', 'koz-migration-cleanup' ); ?></strong> <?php echo esc_html__( 'The universal scanner does not delete data, change plugin state, export option values or collect personal data.', 'koz-migration-cleanup' ); ?></div>
			<p><?php echo esc_html__( 'Candidate matches are heuristic. A plugin-specific adapter, dry run, snapshot and explicit confirmation are required before any cleanup operation can exist.', 'koz-migration-cleanup' ); ?></p>
			<div class="kozmig-actions">
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kozmig_export_environment' ), 'kozmig_export_environment' ) ); ?>"><?php echo esc_html__( 'Export environment snapshot', 'koz-migration-cleanup' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kozmig_rescan' ), 'kozmig_rescan' ) ); ?>"><?php echo esc_html__( 'Refresh inventory', 'koz-migration-cleanup' ); ?></a>
			</div>
		</div>
		<div class="kozmig-card" style="margin-top:16px">
			<h2><?php echo esc_html__( 'How to use this version', 'koz-migration-cleanup' ); ?></h2>
			<ol>
				<li><?php echo esc_html__( 'Open the Environment tab and inspect the installed plugin list.', 'koz-migration-cleanup' ); ?></li>
				<li><?php echo esc_html__( 'Open a plugin inspection to review likely options, metadata, tables and cron hooks.', 'koz-migration-cleanup' ); ?></li>
				<li><?php echo esc_html__( 'Export the JSON snapshot before developing or enabling a cleanup adapter.', 'koz-migration-cleanup' ); ?></li>
				<li><?php echo esc_html__( 'Do not treat heuristic matches as confirmed ownership.', 'koz-migration-cleanup' ); ?></li>
			</ol>
		</div>
		<?php
	}

	private static function environment_tab(): void {
		$plugin_file = '';
		$inspect_input = filter_input( INPUT_GET, 'inspect', FILTER_UNSAFE_RAW );
		if ( is_string( $inspect_input ) && '' !== $inspect_input ) {
			check_admin_referer( 'kozmig_inspect_plugin' );
			$plugin_file = sanitize_text_field( $inspect_input );
		}
		if ( '' !== $plugin_file ) {
			self::inspection_view( $plugin_file );
			return;
		}

		$report = KOZMIG_Environment_Scanner::environment();
		?>
		<div class="kozmig-card" style="margin-top:18px">
			<h2><?php echo esc_html__( 'Installed plugins', 'koz-migration-cleanup' ); ?></h2>
			<p><?php echo esc_html__( 'Inspect a plugin to find likely database and cron leftovers. The scan is read-only and intentionally conservative.', 'koz-migration-cleanup' ); ?></p>
			<table class="widefat striped kozmig-table">
				<thead><tr><th><?php echo esc_html__( 'Plugin', 'koz-migration-cleanup' ); ?></th><th><?php echo esc_html__( 'Status', 'koz-migration-cleanup' ); ?></th><th><?php echo esc_html__( 'Version', 'koz-migration-cleanup' ); ?></th><th><?php echo esc_html__( 'File', 'koz-migration-cleanup' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $report['plugins'] as $plugin ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $plugin['name'] ); ?></strong><br><span class="kozmig-muted"><?php echo esc_html( $plugin['author'] ); ?></span></td>
						<td><?php self::status_badge( $plugin['status'] ); ?></td>
						<td><?php echo esc_html( $plugin['version'] ?: '—' ); ?></td>
						<td><code><?php echo esc_html( $plugin['file'] ); ?></code></td>
						<td><a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=environment&inspect=' . rawurlencode( $plugin['file'] ) ), 'kozmig_inspect_plugin' ) ); ?>"><?php echo esc_html__( 'Inspect', 'koz-migration-cleanup' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function inspection_view( string $plugin_file ): void {
		$report = KOZMIG_Environment_Scanner::inspect_plugin( $plugin_file );
		if ( is_wp_error( $report ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $report->get_error_message() ) . '</p></div>';
			return;
		}
		$likely = $report['likely_data'];
		?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=environment' ) ); ?>">&larr; <?php echo esc_html__( 'Back to plugin inventory', 'koz-migration-cleanup' ); ?></a></p>
		<div class="kozmig-card">
			<h2><?php echo esc_html( $report['plugin']['name'] ); ?></h2>
			<p><code><?php echo esc_html( $report['plugin']['file'] ); ?></code> · <?php self::status_badge( $report['plugin']['status'] ); ?></p>
			<div class="kozmig-help"><strong><?php echo esc_html__( 'Detected search patterns:', 'koz-migration-cleanup' ); ?></strong> <code><?php echo esc_html( implode( ', ', $report['patterns'] ) ); ?></code></div>
			<p class="kozmig-danger-note"><?php echo esc_html( $report['interpretation']['warning'] ); ?></p>
			<div class="kozmig-actions">
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kozmig_export_plugin&plugin_file=' . rawurlencode( $plugin_file ) ), 'kozmig_export_plugin' ) ); ?>"><?php echo esc_html__( 'Export plugin inspection', 'koz-migration-cleanup' ); ?></a>
			</div>
		</div>
		<div class="kozmig-grid" style="margin-top:16px">
			<?php self::candidate_card( __( 'Options', 'koz-migration-cleanup' ), $likely['options']['count'], $likely['options']['items'], 'name' ); ?>
			<?php self::candidate_card( __( 'Post metadata', 'koz-migration-cleanup' ), $likely['postmeta']['count'], $likely['postmeta']['items'], 'key' ); ?>
			<?php self::candidate_card( __( 'Term metadata', 'koz-migration-cleanup' ), $likely['termmeta']['count'], $likely['termmeta']['items'], 'key' ); ?>
			<?php self::candidate_card( __( 'User metadata', 'koz-migration-cleanup' ), $likely['usermeta']['count'], $likely['usermeta']['items'], 'key' ); ?>
			<?php self::candidate_card( __( 'Database tables', 'koz-migration-cleanup' ), count( $likely['tables'] ), $likely['tables'], 'name' ); ?>
			<?php self::candidate_card( __( 'Cron hooks', 'koz-migration-cleanup' ), count( $likely['cron_hooks'] ), array_map( static fn( string $hook ): array => array( 'name' => $hook ), $likely['cron_hooks'] ), 'name' ); ?>
		</div>
		<?php
	}

	private static function candidate_card( string $title, int $count, array $items, string $key ): void {
		?>
		<div class="kozmig-card">
			<h3><?php echo esc_html( $title ); ?> <span class="kozmig-muted">(<?php echo esc_html( (string) $count ); ?>)</span></h3>
			<?php if ( empty( $items ) ) : ?>
				<p class="kozmig-muted"><?php echo esc_html__( 'No likely matches found.', 'koz-migration-cleanup' ); ?></p>
			<?php else : ?>
				<ul>
				<?php foreach ( array_slice( $items, 0, 12 ) as $item ) : ?>
					<li><code><?php echo esc_html( isset( $item[ $key ] ) ? (string) $item[ $key ] : '' ); ?></code><?php if ( isset( $item['rows'] ) ) : ?> <span class="kozmig-muted">× <?php echo esc_html( (string) $item['rows'] ); ?></span><?php endif; ?></li>
				<?php endforeach; ?>
				</ul>
				<?php if ( count( $items ) > 12 ) : ?><p class="kozmig-muted"><?php echo esc_html__( 'The exported JSON contains the full limited result set.', 'koz-migration-cleanup' ); ?></p><?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function status_badge( string $status ): void {
		$labels = array(
			'active' => __( 'Active', 'koz-migration-cleanup' ),
			'network-active' => __( 'Network active', 'koz-migration-cleanup' ),
			'inactive' => __( 'Inactive', 'koz-migration-cleanup' ),
			'available' => __( 'Available', 'koz-migration-cleanup' ),
		);
		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		echo '<span class="kozmig-status kozmig-status-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
	}
}
