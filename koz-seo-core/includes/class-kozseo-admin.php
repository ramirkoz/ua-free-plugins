<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZSEO_Admin {
	public const PAGE = 'koz-seo-core';
	private const DEEP_ACTION = 'kozseo_deep_scan';
	private const EXPORT_ACTION = 'kozseo_export';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'export_report' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KOZSEO_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu(): void {
		$parent = \ramirkz\kozseo\KOZSEO_Suite_Registry::suite_page();
		add_submenu_page(
			$parent,
			__( 'SEO Core', 'koz-seo-core' ),
			__( 'SEO Core', 'koz-seo-core' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( ! str_ends_with( $hook_suffix, '_page_' . self::PAGE ) && 'toplevel_page_' . self::PAGE !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'kozseo-rendered-audit',
			KOZSEO_URL . 'assets/koz-rendered-audit.js',
			array(),
			KOZSEO_VERSION,
			true
		);

		wp_localize_script(
			'kozseo-rendered-audit',
			'kozseoRenderedAuditI18n',
			array(
				'running'           => __( 'Scanning rendered pages…', 'koz-seo-core' ),
				'completed'         => __( 'Rendered-page scan completed.', 'koz-seo-core' ),
				'failed'            => __( 'Request failed', 'koz-seo-core' ),
				'path'              => __( 'Path', 'koz-seo-core' ),
				'status'            => __( 'HTTP', 'koz-seo-core' ),
				'h1'                => __( 'H1', 'koz-seo-core' ),
				'imagesWithoutAlt'  => __( 'Images without useful alt text', 'koz-seo-core' ),
				'emptyLinks'        => __( 'Potential empty links', 'koz-seo-core' ),
				'metaDescription'   => __( 'Meta description', 'koz-seo-core' ),
				'noindex'           => __( 'Noindex', 'koz-seo-core' ),
				'yes'               => __( 'Yes', 'koz-seo-core' ),
				'no'                => __( 'No', 'koz-seo-core' ),
				'present'           => __( 'Present', 'koz-seo-core' ),
				'missing'           => __( 'Missing', 'koz-seo-core' ),
				'downloadFilename'  => 'koz-seo-rendered-audit-' . gmdate( 'Ymd-His' ) . '.json',
			)
		);
	}

	private static function rendered_audit_routes(): array {
		$ids = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$routes = array();
		$seen   = array();
		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

		foreach ( $ids as $id ) {
			$url = get_permalink( (int) $id );
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}
			$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			if ( '' === $host || $host !== $home_host ) {
				continue;
			}
			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			$path = '' === $path ? '/' : $path;
			$key  = trailingslashit( $path );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$routes[] = array(
				'id'   => (int) $id,
				'url'  => $url,
				'path' => $path,
			);
		}

		$home = home_url( '/' );
		if ( is_string( $home ) && '' !== $home && ! isset( $seen['/'] ) ) {
			array_unshift(
				$routes,
				array(
					'id'   => 0,
					'url'  => $home,
					'path' => '/',
				)
			);
		}

		return $routes;
	}

	public static function register_settings(): void {
		register_setting(
			'kozseo_settings',
			KOZSEO_Core::OPTION,
			array(
				'sanitize_callback' => array( 'KOZSEO_Core', 'sanitize_settings' ),
				'default'           => KOZSEO_Core::defaults(),
			)
		);
	}

	public static function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'koz-seo-core' ) . '</a>' );
		return $links;
	}

	private static function deep_requested(): bool {
		if ( empty( $_GET['kozseo_deep'] ) ) {
			return false;
		}
		check_admin_referer( self::DEEP_ACTION );
		return true;
	}

	private static function deep_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'            => self::PAGE,
					'kozseo_deep' => 1,
				),
				admin_url( 'admin.php' )
			),
			self::DEEP_ACTION
		);
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$deep     = self::deep_requested();
		$settings = KOZSEO_Core::settings();
		$scan     = KOZSEO_Scanner::scan( $deep );
		$audit    = KOZSEO_Core::accessibility_audit( $deep ? 500 : 50, false );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'KOZ SEO Core', 'koz-seo-core' ); ?> <small style="font-size:14px"><?php echo esc_html( KOZSEO_VERSION ); ?></small></h1>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Safety-first mode: scans are read-only, and data from previous SEO plugins is never removed automatically.', 'koz-seo-core' ); ?></p></div>
			<?php if ( KOZSEO_Scanner::conflicting_active_plugin() ) : ?>
				<div class="notice notice-error inline"><p><strong><?php esc_html_e( 'Another SEO plugin is active.', 'koz-seo-core' ); ?></strong> <?php esc_html_e( 'Keep KOZ SEO output disabled until the migration is verified to avoid duplicate metadata.', 'koz-seo-core' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'kozseo_settings' ); ?>
				<h2><?php esc_html_e( 'General settings', 'koz-seo-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'SEO output', 'koz-seo-core' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( KOZSEO_Core::OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Generate title, description, canonical, social metadata and schema', 'koz-seo-core' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Title separator', 'koz-seo-core' ); ?></th><td><select name="<?php echo esc_attr( KOZSEO_Core::OPTION ); ?>[separator]"><?php foreach ( array( '|', '-', '•', '·' ) as $separator ) : ?><option value="<?php echo esc_attr( $separator ); ?>" <?php selected( $settings['separator'], $separator ); ?>><?php echo esc_html( $separator ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th><?php esc_html_e( 'Organization name', 'koz-seo-core' ); ?></th><td><input class="regular-text" name="<?php echo esc_attr( KOZSEO_Core::OPTION ); ?>[organization_name]" value="<?php echo esc_attr( $settings['organization_name'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Organization type', 'koz-seo-core' ); ?></th><td><select name="<?php echo esc_attr( KOZSEO_Core::OPTION ); ?>[organization_type]"><?php foreach ( array( 'Organization', 'NGO', 'EducationalOrganization', 'GovernmentOrganization' ) as $type ) : ?><option value="<?php echo esc_attr( $type ); ?>" <?php selected( $settings['organization_type'], $type ); ?>><?php echo esc_html( $type ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th><?php esc_html_e( 'Organization description', 'koz-seo-core' ); ?></th><td><textarea class="large-text" rows="3" name="<?php echo esc_attr( KOZSEO_Core::OPTION ); ?>[organization_description]"><?php echo esc_textarea( $settings['organization_description'] ); ?></textarea></td></tr>
					<tr><th><?php esc_html_e( 'Organization logo attachment ID', 'koz-seo-core' ); ?></th><td><input type="number" min="0" name="<?php echo esc_attr( KOZSEO_Core::OPTION ); ?>[organization_logo_id]" value="<?php echo esc_attr( (string) $settings['organization_logo_id'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Default social image attachment ID', 'koz-seo-core' ); ?></th><td><input type="number" min="0" name="<?php echo esc_attr( KOZSEO_Core::OPTION ); ?>[default_social_image_id]" value="<?php echo esc_attr( (string) $settings['default_social_image_id'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Noindex', 'koz-seo-core' ); ?></th><td>
						<label><input type="checkbox" name="<?php echo esc_attr( KOZSEO_Core::OPTION ); ?>[noindex_search]" value="1" <?php checked( ! empty( $settings['noindex_search'] ) ); ?>> <?php esc_html_e( 'Search pages', 'koz-seo-core' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( KOZSEO_Core::OPTION ); ?>[noindex_author]" value="1" <?php checked( ! empty( $settings['noindex_author'] ) ); ?>> <?php esc_html_e( 'Author archives', 'koz-seo-core' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( KOZSEO_Core::OPTION ); ?>[noindex_date]" value="1" <?php checked( ! empty( $settings['noindex_date'] ) ); ?>> <?php esc_html_e( 'Date archives', 'koz-seo-core' ); ?></label>
					</td></tr>
					<tr><th><?php esc_html_e( 'AI discovery', 'koz-seo-core' ); ?></th><td>
						<label><input type="checkbox" name="<?php echo esc_attr( KOZSEO_Core::OPTION ); ?>[llms_txt]" value="1" <?php checked( ! empty( $settings['llms_txt'] ) ); ?>> <?php esc_html_e( 'Provide llms.txt', 'koz-seo-core' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( KOZSEO_Core::OPTION ); ?>[ai_manifest]" value="1" <?php checked( ! empty( $settings['ai_manifest'] ) ); ?>> <?php esc_html_e( 'Provide a public AI manifest', 'koz-seo-core' ); ?></label>
					</td></tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Existing SEO environment', 'koz-seo-core' ); ?></h2>
			<p><?php esc_html_e( 'Quick mode reads plugin inventory and option presence only. Exact metadata counts run only after explicit confirmation.', 'koz-seo-core' ); ?></p>
			<p><a class="button" href="<?php echo esc_url( self::deep_url() ); ?>"><?php esc_html_e( 'Run deep metadata count', 'koz-seo-core' ); ?></a> <?php if ( $deep ) : ?><strong><?php esc_html_e( 'Deep scan completed for this page load.', 'koz-seo-core' ); ?></strong><?php endif; ?></p>
			<table class="widefat striped" style="max-width:1100px"><thead><tr><th><?php esc_html_e( 'Provider', 'koz-seo-core' ); ?></th><th><?php esc_html_e( 'Installed', 'koz-seo-core' ); ?></th><th><?php esc_html_e( 'Active', 'koz-seo-core' ); ?></th><th><?php esc_html_e( 'Detected metadata', 'koz-seo-core' ); ?></th><th><?php esc_html_e( 'Detected option groups', 'koz-seo-core' ); ?></th></tr></thead><tbody>
			<?php foreach ( $scan['providers'] as $provider ) : ?>
			<tr><td><?php echo esc_html( $provider['name'] ); ?></td><td><?php echo esc_html( $provider['installed'] ? __( 'Yes', 'koz-seo-core' ) : __( 'No', 'koz-seo-core' ) ); ?></td><td><?php echo esc_html( $provider['active'] ? __( 'Yes', 'koz-seo-core' ) : __( 'No', 'koz-seo-core' ) ); ?></td><td><?php echo esc_html( $provider['metadata_count_exact'] ? (string) $provider['metadata_count'] : __( 'Not counted in quick mode', 'koz-seo-core' ) ); ?></td><td><?php echo esc_html( (string) $provider['option_presence_count'] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<p><strong><?php esc_html_e( 'Import and cleanup are intentionally disabled in this release.', 'koz-seo-core' ); ?></strong> <?php esc_html_e( 'Any future cleanup workflow will require a snapshot, dry run and explicit confirmation.', 'koz-seo-core' ); ?></p>

			<h2><?php esc_html_e( 'Accessibility audit', 'koz-seo-core' ); ?></h2>
			<table class="widefat striped" style="max-width:800px"><tbody>
			<tr><th><?php esc_html_e( 'Published items checked', 'koz-seo-core' ); ?></th><td><?php echo esc_html( (string) $audit['checked'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Items without an H1 in stored content', 'koz-seo-core' ); ?></th><td><?php echo esc_html( (string) $audit['missing_h1_count'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Images without useful alt text', 'koz-seo-core' ); ?></th><td><?php echo esc_html( (string) $audit['images_without_alt'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Potential empty links', 'koz-seo-core' ); ?></th><td><?php echo esc_html( (string) $audit['empty_links'] ); ?></td></tr>
			</tbody></table>

			<h2><?php esc_html_e( 'Rendered-page accessibility audit', 'koz-seo-core' ); ?></h2>
			<p><?php esc_html_e( 'This optional browser-side scan checks the actual public HTML of published pages. It sends requests only to this site, makes no database changes and does not submit forms.', 'koz-seo-core' ); ?></p>
			<?php $rendered_routes = self::rendered_audit_routes(); ?>
			<div id="kozseo-rendered-audit" data-version="<?php echo esc_attr( KOZSEO_VERSION ); ?>" data-routes="<?php echo esc_attr( wp_json_encode( $rendered_routes ) ); ?>">
				<p>
					<button type="button" class="button button-secondary" id="kozseo-rendered-audit-run"><?php esc_html_e( 'Run rendered-page scan', 'koz-seo-core' ); ?></button>
					<button type="button" class="button" id="kozseo-rendered-audit-download" disabled><?php esc_html_e( 'Download rendered audit JSON', 'koz-seo-core' ); ?></button>
					<span id="kozseo-rendered-audit-status" style="margin-left:8px"></span>
				</p>
				<p><strong><?php esc_html_e( 'Published page routes available', 'koz-seo-core' ); ?>:</strong> <?php echo esc_html( (string) count( $rendered_routes ) ); ?></p>
				<div id="kozseo-rendered-audit-summary"></div>
				<table class="widefat striped" id="kozseo-rendered-audit-table" style="max-width:1100px;display:none">
					<thead><tr><th><?php esc_html_e( 'Path', 'koz-seo-core' ); ?></th><th><?php esc_html_e( 'HTTP', 'koz-seo-core' ); ?></th><th><?php esc_html_e( 'H1', 'koz-seo-core' ); ?></th><th><?php esc_html_e( 'Images without useful alt text', 'koz-seo-core' ); ?></th><th><?php esc_html_e( 'Potential empty links', 'koz-seo-core' ); ?></th><th><?php esc_html_e( 'Meta description', 'koz-seo-core' ); ?></th><th><?php esc_html_e( 'Noindex', 'koz-seo-core' ); ?></th></tr></thead>
					<tbody></tbody>
				</table>
			</div>

			<?php KOZSEO_Alt_Manager::render_panel(); ?>

			<h2><?php esc_html_e( 'System endpoints', 'koz-seo-core' ); ?></h2>
			<ul><li><code>/wp-sitemap.xml</code></li><li><code>/llms.txt</code></li><li><code>/.well-known/koz-ai-manifest.json</code></li></ul>
			<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::EXPORT_ACTION ), self::EXPORT_ACTION ) ); ?>"><?php esc_html_e( 'Export privacy-safe JSON report', 'koz-seo-core' ); ?></a></p>

		</div>
		<?php
	}

	public static function export_report(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'koz-seo-core' ) );
		}
		check_admin_referer( self::EXPORT_ACTION );
		$report = array(
			'generated_at'             => gmdate( 'c' ),
			'privacy_contract_version' => 1,
			'plugin'                   => KOZSEO_Core::public_status(),
			'environment'              => KOZSEO_Scanner::scan( true ),
			'accessibility'            => KOZSEO_Core::accessibility_audit( 500, false ),
			'suite'                    => \ramirkz\kozseo\KOZSEO_Suite_Registry::status(),
			'privacy'                  => 'No option values, plugin file paths, post titles, post URLs, post content, credentials, IP addresses or personal data are included.',
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="koz-seo-report-' . gmdate( 'Ymd-His' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo wp_json_encode( $report, JSON_PRETTY_PRINT );
		exit;
	}
}
