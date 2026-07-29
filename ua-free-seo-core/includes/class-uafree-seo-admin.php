<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UAFree_SEO_Admin {
	public const PAGE = 'uafree-seo-core';
	private const DEEP_ACTION = 'uafree_seo_deep_scan';
	private const EXPORT_ACTION = 'uafree_seo_export';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_uafree_seo_export', array( __CLASS__, 'export_report' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( UAFREE_SEO_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu(): void {
		add_submenu_page(
			'uafree-suite',
			__( 'SEO Core', 'ua-free-seo-core' ),
			__( 'SEO Core', 'ua-free-seo-core' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'uafree_seo_core',
			UAFree_SEO_Core::OPTION,
			array(
				'sanitize_callback' => array( 'UAFree_SEO_Core', 'sanitize_settings' ),
				'default'           => UAFree_SEO_Core::defaults(),
			)
		);
	}

	public static function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'ua-free-seo-core' ) . '</a>' );
		return $links;
	}

	private static function deep_requested(): bool {
		if ( empty( $_GET['uafree_seo_deep'] ) ) {
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
					'uafree_seo_deep' => 1,
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
		$settings = UAFree_SEO_Core::settings();
		$scan     = UAFree_SEO_Scanner::scan( $deep );
		$audit    = UAFree_SEO_Core::accessibility_audit( $deep ? 500 : 50, false );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'UA FREE SEO Core', 'ua-free-seo-core' ); ?> <small style="font-size:14px"><?php echo esc_html( UAFREE_SEO_VERSION ); ?></small></h1>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Development build. It performs no cleanup and does not remove data from previous SEO plugins.', 'ua-free-seo-core' ); ?></p></div>
			<?php if ( UAFree_SEO_Scanner::conflicting_active_plugin() ) : ?>
				<div class="notice notice-error inline"><p><strong><?php esc_html_e( 'Another SEO plugin is active.', 'ua-free-seo-core' ); ?></strong> <?php esc_html_e( 'Keep UA FREE SEO output disabled until the migration is verified to avoid duplicate metadata.', 'ua-free-seo-core' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'uafree_seo_core' ); ?>
				<h2><?php esc_html_e( 'General settings', 'ua-free-seo-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'SEO output', 'ua-free-seo-core' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( UAFree_SEO_Core::OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Generate title, description, canonical, social metadata and schema', 'ua-free-seo-core' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Title separator', 'ua-free-seo-core' ); ?></th><td><select name="<?php echo esc_attr( UAFree_SEO_Core::OPTION ); ?>[separator]"><?php foreach ( array( '|', '-', '•', '·' ) as $separator ) : ?><option value="<?php echo esc_attr( $separator ); ?>" <?php selected( $settings['separator'], $separator ); ?>><?php echo esc_html( $separator ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th><?php esc_html_e( 'Organization name', 'ua-free-seo-core' ); ?></th><td><input class="regular-text" name="<?php echo esc_attr( UAFree_SEO_Core::OPTION ); ?>[organization_name]" value="<?php echo esc_attr( $settings['organization_name'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Organization type', 'ua-free-seo-core' ); ?></th><td><select name="<?php echo esc_attr( UAFree_SEO_Core::OPTION ); ?>[organization_type]"><?php foreach ( array( 'Organization', 'NGO', 'EducationalOrganization', 'GovernmentOrganization' ) as $type ) : ?><option value="<?php echo esc_attr( $type ); ?>" <?php selected( $settings['organization_type'], $type ); ?>><?php echo esc_html( $type ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th><?php esc_html_e( 'Organization description', 'ua-free-seo-core' ); ?></th><td><textarea class="large-text" rows="3" name="<?php echo esc_attr( UAFree_SEO_Core::OPTION ); ?>[organization_description]"><?php echo esc_textarea( $settings['organization_description'] ); ?></textarea></td></tr>
					<tr><th><?php esc_html_e( 'Organization logo attachment ID', 'ua-free-seo-core' ); ?></th><td><input type="number" min="0" name="<?php echo esc_attr( UAFree_SEO_Core::OPTION ); ?>[organization_logo_id]" value="<?php echo esc_attr( (string) $settings['organization_logo_id'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Default social image attachment ID', 'ua-free-seo-core' ); ?></th><td><input type="number" min="0" name="<?php echo esc_attr( UAFree_SEO_Core::OPTION ); ?>[default_social_image_id]" value="<?php echo esc_attr( (string) $settings['default_social_image_id'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Noindex', 'ua-free-seo-core' ); ?></th><td>
						<label><input type="checkbox" name="<?php echo esc_attr( UAFree_SEO_Core::OPTION ); ?>[noindex_search]" value="1" <?php checked( ! empty( $settings['noindex_search'] ) ); ?>> <?php esc_html_e( 'Search pages', 'ua-free-seo-core' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( UAFree_SEO_Core::OPTION ); ?>[noindex_author]" value="1" <?php checked( ! empty( $settings['noindex_author'] ) ); ?>> <?php esc_html_e( 'Author archives', 'ua-free-seo-core' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( UAFree_SEO_Core::OPTION ); ?>[noindex_date]" value="1" <?php checked( ! empty( $settings['noindex_date'] ) ); ?>> <?php esc_html_e( 'Date archives', 'ua-free-seo-core' ); ?></label>
					</td></tr>
					<tr><th><?php esc_html_e( 'AI discovery', 'ua-free-seo-core' ); ?></th><td>
						<label><input type="checkbox" name="<?php echo esc_attr( UAFree_SEO_Core::OPTION ); ?>[llms_txt]" value="1" <?php checked( ! empty( $settings['llms_txt'] ) ); ?>> <?php esc_html_e( 'Provide llms.txt', 'ua-free-seo-core' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( UAFree_SEO_Core::OPTION ); ?>[ai_manifest]" value="1" <?php checked( ! empty( $settings['ai_manifest'] ) ); ?>> <?php esc_html_e( 'Provide a public AI manifest', 'ua-free-seo-core' ); ?></label>
					</td></tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Existing SEO environment', 'ua-free-seo-core' ); ?></h2>
			<p><?php esc_html_e( 'Quick mode reads plugin inventory and option presence only. Exact metadata counts run only after explicit confirmation.', 'ua-free-seo-core' ); ?></p>
			<p><a class="button" href="<?php echo esc_url( self::deep_url() ); ?>"><?php esc_html_e( 'Run deep metadata count', 'ua-free-seo-core' ); ?></a> <?php if ( $deep ) : ?><strong><?php esc_html_e( 'Deep scan completed for this page load.', 'ua-free-seo-core' ); ?></strong><?php endif; ?></p>
			<table class="widefat striped" style="max-width:1100px"><thead><tr><th><?php esc_html_e( 'Provider', 'ua-free-seo-core' ); ?></th><th><?php esc_html_e( 'Installed', 'ua-free-seo-core' ); ?></th><th><?php esc_html_e( 'Active', 'ua-free-seo-core' ); ?></th><th><?php esc_html_e( 'Detected metadata', 'ua-free-seo-core' ); ?></th><th><?php esc_html_e( 'Detected option groups', 'ua-free-seo-core' ); ?></th></tr></thead><tbody>
			<?php foreach ( $scan['providers'] as $provider ) : ?>
			<tr><td><?php echo esc_html( $provider['name'] ); ?></td><td><?php echo esc_html( $provider['installed'] ? __( 'Yes', 'ua-free-seo-core' ) : __( 'No', 'ua-free-seo-core' ) ); ?></td><td><?php echo esc_html( $provider['active'] ? __( 'Yes', 'ua-free-seo-core' ) : __( 'No', 'ua-free-seo-core' ) ); ?></td><td><?php echo esc_html( $provider['metadata_count_exact'] ? (string) $provider['metadata_count'] : __( 'Not counted in quick mode', 'ua-free-seo-core' ) ); ?></td><td><?php echo esc_html( (string) $provider['option_presence_count'] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<p><strong><?php esc_html_e( 'Import and cleanup are intentionally disabled in this development build.', 'ua-free-seo-core' ); ?></strong> <?php esc_html_e( 'The final version will require a snapshot, dry run and explicit confirmation.', 'ua-free-seo-core' ); ?></p>

			<h2><?php esc_html_e( 'Accessibility audit', 'ua-free-seo-core' ); ?></h2>
			<table class="widefat striped" style="max-width:800px"><tbody>
			<tr><th><?php esc_html_e( 'Published items checked', 'ua-free-seo-core' ); ?></th><td><?php echo esc_html( (string) $audit['checked'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Items without an H1 in stored content', 'ua-free-seo-core' ); ?></th><td><?php echo esc_html( (string) $audit['missing_h1_count'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Images without useful alt text', 'ua-free-seo-core' ); ?></th><td><?php echo esc_html( (string) $audit['images_without_alt'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Potential empty links', 'ua-free-seo-core' ); ?></th><td><?php echo esc_html( (string) $audit['empty_links'] ); ?></td></tr>
			</tbody></table>

			<h2><?php esc_html_e( 'System endpoints', 'ua-free-seo-core' ); ?></h2>
			<ul><li><code>/wp-sitemap.xml</code></li><li><code>/llms.txt</code></li><li><code>/.well-known/uafree-ai-manifest.json</code></li></ul>
			<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=uafree_seo_export' ), self::EXPORT_ACTION ) ); ?>"><?php esc_html_e( 'Export privacy-safe JSON report', 'ua-free-seo-core' ); ?></a></p>

			<h2><?php esc_html_e( 'Origin and support', 'ua-free-seo-core' ); ?></h2>
			<p><?php esc_html_e( 'You can support the charitable foundation, share its work, place a link to it, or support plugin development separately.', 'ua-free-seo-core' ); ?></p>
			<p><a class="button button-primary" href="https://uafree.org/donate/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support the foundation', 'ua-free-seo-core' ); ?></a> <a class="button" href="https://uafree.org/plugins/support-development/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support development', 'ua-free-seo-core' ); ?></a></p>
		</div>
		<?php
	}

	public static function export_report(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'ua-free-seo-core' ) );
		}
		check_admin_referer( self::EXPORT_ACTION );
		$report = array(
			'generated_at'             => gmdate( 'c' ),
			'privacy_contract_version' => 1,
			'plugin'                   => UAFree_SEO_Core::public_status(),
			'environment'              => UAFree_SEO_Scanner::scan( true ),
			'accessibility'            => UAFree_SEO_Core::accessibility_audit( 500, false ),
			'suite'                    => \UAFree\Suite\Registry::status(),
			'privacy'                  => 'No option values, plugin file paths, post titles, post URLs, post content, credentials, IP addresses or personal data are included.',
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ua-free-seo-report-' . gmdate( 'Ymd-His' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo wp_json_encode( $report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		exit;
	}
}
