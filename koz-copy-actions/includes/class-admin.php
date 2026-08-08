<?php
namespace ramirkz\kozcopyactions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZCOAC_Admin {
	private const PAGE = 'koz-copy-actions';
	private const FALLBACK_SUITE_PAGE = 'kozcoac-suite';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KOZCOAC_FILE ), array( __CLASS__, 'action_links' ) );
	}

	private static function find_existing_suite_page(): string {
		global $menu;

		foreach ( (array) $menu as $item ) {
			$label = isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : '';
			$slug = isset( $item[2] ) ? (string) $item[2] : '';
			if ( 'KOZ Suite' === trim( $label ) && '' !== $slug ) {
				return $slug;
			}
		}

		return '';
	}

	private static function suite_page(): string {
		$existing = self::find_existing_suite_page();
		if ( '' !== $existing ) {
			return $existing;
		}

		add_menu_page(
			__( 'KOZ WordPress Suite', 'koz-copy-actions' ),
			__( 'KOZ Suite', 'koz-copy-actions' ),
			'manage_options',
			self::FALLBACK_SUITE_PAGE,
			array( __CLASS__, 'page' ),
			'dashicons-layout',
			58
		);

		return self::FALLBACK_SUITE_PAGE;
	}

	public static function menu(): void {
		$parent = self::suite_page();

		add_submenu_page(
			$parent,
			__( 'KOZ Copy Actions', 'koz-copy-actions' ),
			__( 'Copy Actions', 'koz-copy-actions' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'kozcoac_copy_group',
			KOZCOAC_Plugin::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( KOZCOAC_Plugin::class, 'sanitize_settings' ),
				'default'           => KOZCOAC_Plugin::defaults(),
			)
		);
	}

	public static function assets( string $hook ): void {
		if ( ! str_contains( $hook, self::PAGE ) && ! str_contains( $hook, self::FALLBACK_SUITE_PAGE ) ) {
			return;
		}

		wp_enqueue_style(
			'kozcoac-copy-admin',
			plugin_dir_url( KOZCOAC_FILE ) . 'assets/admin.css',
			array(),
			KOZCOAC_VERSION
		);
	}

	public static function action_links( array $links ): array {
		$url = admin_url( 'admin.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'koz-copy-actions' ) . '</a>' );
		return $links;
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = KOZCOAC_Plugin::settings();
		$conflicts = KOZCOAC_Environment_Scanner::copy_plugins();
		?>
		<div class="wrap kozcoac-admin">
			<h1><?php esc_html_e( 'KOZ Copy Actions', 'koz-copy-actions' ); ?></h1>
			<p class="kozcoac-lead"><?php esc_html_e( 'Accessible copy-to-clipboard actions without storing copied values or loading an external service.', 'koz-copy-actions' ); ?></p>

			<?php if ( array_filter( $conflicts, static fn( array $plugin ): bool => ! empty( $plugin['active'] ) ) ) : ?>
				<div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Another active clipboard plugin was detected.', 'koz-copy-actions' ); ?></strong> <?php esc_html_e( 'Do not enable two copy handlers on the same elements until you have tested the result.', 'koz-copy-actions' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'kozcoac_copy_group' ); ?>
				<input type="hidden" name="<?php echo esc_attr( KOZCOAC_Plugin::OPTION ); ?>[schema_version]" value="<?php echo esc_attr( (string) KOZCOAC_Plugin::SCHEMA_VERSION ); ?>">

				<div class="kozcoac-card">
					<h2><?php esc_html_e( 'Activation and scope', 'koz-copy-actions' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable copy actions', 'koz-copy-actions' ); ?></th>
							<td><label><input type="checkbox" name="<?php echo esc_attr( KOZCOAC_Plugin::OPTION ); ?>[enabled]" value="1" <?php checked( 1, (int) $settings['enabled'] ); ?>> <?php esc_html_e( 'Load the copy handler on matching public pages.', 'koz-copy-actions' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><label for="kozcoac-copy-paths"><?php esc_html_e( 'Allowed paths', 'koz-copy-actions' ); ?></label></th>
							<td><textarea id="kozcoac-copy-paths" name="<?php echo esc_attr( KOZCOAC_Plugin::OPTION ); ?>[path_rules]" rows="5" class="large-text code" spellcheck="false"><?php echo esc_textarea( (string) $settings['path_rules'] ); ?></textarea><p class="description"><?php esc_html_e( 'Optional. One local path per line. Use an ending * for a prefix. Leave empty to allow all public paths.', 'koz-copy-actions' ); ?></p></td>
						</tr>
					</table>
				</div>

				<div class="kozcoac-card">
					<h2><?php esc_html_e( 'Copy targets', 'koz-copy-actions' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="kozcoac-copy-selectors"><?php esc_html_e( 'CSS selectors', 'koz-copy-actions' ); ?></label></th>
							<td><textarea id="kozcoac-copy-selectors" name="<?php echo esc_attr( KOZCOAC_Plugin::OPTION ); ?>[selectors]" rows="12" class="large-text code" spellcheck="false"><?php echo esc_textarea( (string) $settings['selectors'] ); ?></textarea><p class="description"><?php esc_html_e( 'One simple class, ID or approved data attribute selector per line.', 'koz-copy-actions' ); ?></p></td>
						</tr>
					</table>
				</div>

				<div class="kozcoac-card">
					<h2><?php esc_html_e( 'Behaviour and accessibility', 'koz-copy-actions' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><?php esc_html_e( 'Whitespace', 'koz-copy-actions' ); ?></th><td><select name="<?php echo esc_attr( KOZCOAC_Plugin::OPTION ); ?>[whitespace]"><option value="collapse" <?php selected( 'collapse', $settings['whitespace'] ); ?>><?php esc_html_e( 'Collapse repeated whitespace', 'koz-copy-actions' ); ?></option><option value="preserve" <?php selected( 'preserve', $settings['whitespace'] ); ?>><?php esc_html_e( 'Preserve formatting', 'koz-copy-actions' ); ?></option></select></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Visual feedback', 'koz-copy-actions' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( KOZCOAC_Plugin::OPTION ); ?>[show_icon]" value="1" <?php checked( 1, (int) $settings['show_icon'] ); ?>> <?php esc_html_e( 'Show copy icon', 'koz-copy-actions' ); ?></label><br><label><input type="checkbox" name="<?php echo esc_attr( KOZCOAC_Plugin::OPTION ); ?>[show_toast]" value="1" <?php checked( 1, (int) $settings['show_toast'] ); ?>> <?php esc_html_e( 'Show success or error message', 'koz-copy-actions' ); ?></label></td></tr>
						<tr><th scope="row"><label for="kozcoac-copy-duration"><?php esc_html_e( 'Message duration', 'koz-copy-actions' ); ?></label></th><td><input id="kozcoac-copy-duration" type="number" min="500" max="5000" step="100" name="<?php echo esc_attr( KOZCOAC_Plugin::OPTION ); ?>[toast_duration]" value="<?php echo esc_attr( (string) $settings['toast_duration'] ); ?>"> ms</td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Message position', 'koz-copy-actions' ); ?></th><td><select name="<?php echo esc_attr( KOZCOAC_Plugin::OPTION ); ?>[toast_position]"><option value="bottom-center" <?php selected( 'bottom-center', $settings['toast_position'] ); ?>><?php esc_html_e( 'Bottom centre', 'koz-copy-actions' ); ?></option><option value="bottom-left" <?php selected( 'bottom-left', $settings['toast_position'] ); ?>><?php esc_html_e( 'Bottom left', 'koz-copy-actions' ); ?></option><option value="bottom-right" <?php selected( 'bottom-right', $settings['toast_position'] ); ?>><?php esc_html_e( 'Bottom right', 'koz-copy-actions' ); ?></option></select></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Target decoration', 'koz-copy-actions' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( KOZCOAC_Plugin::OPTION ); ?>[decorate_targets]" value="1" <?php checked( 1, (int) $settings['decorate_targets'] ); ?>> <?php esc_html_e( 'Add keyboard focus and accessible button semantics to non-interactive elements.', 'koz-copy-actions' ); ?></label></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Links', 'koz-copy-actions' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( KOZCOAC_Plugin::OPTION ); ?>[prevent_link_navigation]" value="1" <?php checked( 1, (int) $settings['prevent_link_navigation'] ); ?>> <?php esc_html_e( 'Prevent navigation when a matching link is clicked.', 'koz-copy-actions' ); ?></label></td></tr>
					</table>
				</div>

				<?php submit_button( __( 'Save settings', 'koz-copy-actions' ) ); ?>
			</form>

			<div class="kozcoac-card">
				<h2><?php esc_html_e( 'Diagnostics', 'koz-copy-actions' ); ?></h2>
				<p><?php echo esc_html(
					sprintf(
						/* translators: %d: number of configured CSS selectors. */
						__( 'Configured selectors: %d', 'koz-copy-actions' ),
						count( KOZCOAC_Plugin::selectors() )
					)
				); ?></p>
				<p><?php esc_html_e( 'Copied values stored by this plugin: no. External requests: no. Cookies: no. Telemetry: no.', 'koz-copy-actions' ); ?></p>
			</div>
		</div>
		<?php
	}
}
