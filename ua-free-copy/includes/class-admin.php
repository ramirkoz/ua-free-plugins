<?php
namespace UAFree\CopyTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	private const PAGE = 'uafree-copy';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'remove_legacy_settings_menu' ), 10000 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( UAFREE_COPY_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu(): void {
		self::ensure_suite_menu();
		add_submenu_page(
			'uafree-suite',
			__( 'UA FREE Copy', 'ua-free-copy' ),
			__( 'Copy', 'ua-free-copy' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}

	private static function ensure_suite_menu(): void {
		global $menu;
		$exists = false;
		foreach ( (array) $menu as $item ) {
			if ( isset( $item[2] ) && 'uafree-suite' === $item[2] ) {
				$exists = true;
				break;
			}
		}
		if ( ! $exists ) {
			add_menu_page(
				'UA FREE',
				'UA FREE',
				'manage_options',
				'uafree-suite',
				array( __CLASS__, 'page' ),
				'none',
				58
			);
		}
	}

	public static function register_settings(): void {
		register_setting(
			'uafree_copy_group',
			Plugin::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Plugin::class, 'sanitize_settings' ),
				'default'           => Plugin::defaults(),
			)
		);
	}

	public static function assets( string $hook ): void {
		if ( ! str_contains( $hook, self::PAGE ) && ! str_contains( $hook, 'uafree-suite' ) ) {
			return;
		}
		wp_enqueue_style( 'uafree-copy-admin', plugin_dir_url( UAFREE_COPY_FILE ) . 'assets/admin.css', array(), UAFREE_COPY_VERSION );
	}

	public static function action_links( array $links ): array {
		$url = admin_url( 'admin.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'ua-free-copy' ) . '</a>' );
		return $links;
	}

	public static function remove_legacy_settings_menu(): void {
		remove_submenu_page( 'options-general.php', self::PAGE );
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings  = Plugin::settings();
		$conflicts = Environment_Scanner::copy_plugins();
		?>
		<div class="wrap uafree-copy-admin">
			<h1><?php esc_html_e( 'UA FREE Copy', 'ua-free-copy' ); ?></h1>
			<p class="uafree-lead"><?php esc_html_e( 'Accessible copy-to-clipboard actions without storing the copied values or loading an external service.', 'ua-free-copy' ); ?></p>

			<?php if ( array_filter( $conflicts, static fn( array $plugin ): bool => ! empty( $plugin['active'] ) ) ) : ?>
				<div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Another active clipboard plugin was detected.', 'ua-free-copy' ); ?></strong> <?php esc_html_e( 'Do not enable two copy handlers on the same elements until you have tested the result.', 'ua-free-copy' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'uafree_copy_group' ); ?>
				<input type="hidden" name="<?php echo esc_attr( Plugin::OPTION ); ?>[schema_version]" value="<?php echo esc_attr( (string) Plugin::SCHEMA_VERSION ); ?>">

				<div class="uafree-card">
					<h2><?php esc_html_e( 'Activation and scope', 'ua-free-copy' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable copy actions', 'ua-free-copy' ); ?></th>
							<td><label><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[enabled]" value="1" <?php checked( 1, (int) $settings['enabled'] ); ?>> <?php esc_html_e( 'Load the copy handler on matching public pages.', 'ua-free-copy' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><label for="uafree-copy-paths"><?php esc_html_e( 'Allowed paths', 'ua-free-copy' ); ?></label></th>
							<td><textarea id="uafree-copy-paths" name="<?php echo esc_attr( Plugin::OPTION ); ?>[path_rules]" rows="5" class="large-text code" spellcheck="false"><?php echo esc_textarea( (string) $settings['path_rules'] ); ?></textarea><p class="description"><?php esc_html_e( 'Optional. One local path per line. Use an ending * for a prefix. Leave empty to allow all public paths. Query strings and external URLs are rejected.', 'ua-free-copy' ); ?></p></td>
						</tr>
					</table>
				</div>

				<div class="uafree-card">
					<h2><?php esc_html_e( 'Copy targets', 'ua-free-copy' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="uafree-copy-selectors"><?php esc_html_e( 'CSS selectors', 'ua-free-copy' ); ?></label></th>
							<td><textarea id="uafree-copy-selectors" name="<?php echo esc_attr( Plugin::OPTION ); ?>[selectors]" rows="12" class="large-text code" spellcheck="false"><?php echo esc_textarea( (string) $settings['selectors'] ); ?></textarea><p class="description"><?php esc_html_e( 'One simple class, ID or approved data attribute selector per line. Complex selectors are rejected to reduce accidental page-wide bindings.', 'ua-free-copy' ); ?></p></td>
						</tr>
					</table>
					<div class="uafree-code-examples">
						<p><code>&lt;span class="uafree-copy"&gt;Text to copy&lt;/span&gt;</code></p>
						<p><code>&lt;button data-uafree-copy="account" data-copy-target="#account-value"&gt;Copy&lt;/button&gt;</code></p>
						<p><code>&lt;span id="account-value"&gt;Displayed value&lt;/span&gt;</code></p>
					</div>
					<p class="description"><?php esc_html_e( 'The value is read from data-copy-value, an allowed #id in data-copy-target, a form field value, or the visible text. The plugin never writes that value to WordPress.', 'ua-free-copy' ); ?></p>
				</div>

				<div class="uafree-card">
					<h2><?php esc_html_e( 'Behaviour and accessibility', 'ua-free-copy' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><?php esc_html_e( 'Whitespace', 'ua-free-copy' ); ?></th><td><select name="<?php echo esc_attr( Plugin::OPTION ); ?>[whitespace]"><option value="collapse" <?php selected( 'collapse', $settings['whitespace'] ); ?>><?php esc_html_e( 'Collapse repeated whitespace', 'ua-free-copy' ); ?></option><option value="preserve" <?php selected( 'preserve', $settings['whitespace'] ); ?>><?php esc_html_e( 'Preserve formatting', 'ua-free-copy' ); ?></option></select></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Visual feedback', 'ua-free-copy' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[show_icon]" value="1" <?php checked( 1, (int) $settings['show_icon'] ); ?>> <?php esc_html_e( 'Show copy icon', 'ua-free-copy' ); ?></label><br><label><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[show_toast]" value="1" <?php checked( 1, (int) $settings['show_toast'] ); ?>> <?php esc_html_e( 'Show success or error message', 'ua-free-copy' ); ?></label></td></tr>
						<tr><th scope="row"><label for="uafree-copy-duration"><?php esc_html_e( 'Message duration', 'ua-free-copy' ); ?></label></th><td><input id="uafree-copy-duration" type="number" min="500" max="5000" step="100" name="<?php echo esc_attr( Plugin::OPTION ); ?>[toast_duration]" value="<?php echo esc_attr( (string) $settings['toast_duration'] ); ?>"> ms</td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Message position', 'ua-free-copy' ); ?></th><td><select name="<?php echo esc_attr( Plugin::OPTION ); ?>[toast_position]"><option value="bottom-center" <?php selected( 'bottom-center', $settings['toast_position'] ); ?>><?php esc_html_e( 'Bottom centre', 'ua-free-copy' ); ?></option><option value="bottom-left" <?php selected( 'bottom-left', $settings['toast_position'] ); ?>><?php esc_html_e( 'Bottom left', 'ua-free-copy' ); ?></option><option value="bottom-right" <?php selected( 'bottom-right', $settings['toast_position'] ); ?>><?php esc_html_e( 'Bottom right', 'ua-free-copy' ); ?></option></select></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Target decoration', 'ua-free-copy' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[decorate_targets]" value="1" <?php checked( 1, (int) $settings['decorate_targets'] ); ?>> <?php esc_html_e( 'Add keyboard focus and accessible button semantics to non-interactive elements.', 'ua-free-copy' ); ?></label></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Links', 'ua-free-copy' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[prevent_link_navigation]" value="1" <?php checked( 1, (int) $settings['prevent_link_navigation'] ); ?>> <?php esc_html_e( 'Prevent navigation when a matching link is clicked.', 'ua-free-copy' ); ?></label><p class="description"><?php esc_html_e( 'Enable only when the link is intentionally used as a copy control.', 'ua-free-copy' ); ?></p></td></tr>
					</table>
				</div>

				<?php submit_button( __( 'Save settings', 'ua-free-copy' ) ); ?>
			</form>

			<div class="uafree-card">
				<h2><?php esc_html_e( 'Diagnostics', 'ua-free-copy' ); ?></h2>
				<?php
				$configured_selectors = sprintf(
					/* translators: %d: number of configured CSS selectors. */
					__( 'Configured selectors: %d', 'ua-free-copy' ),
					count( Plugin::selectors() )
				);
				?>
				<p><?php echo esc_html( $configured_selectors ); ?></p>
				<p><?php esc_html_e( 'Copied values stored by this plugin: no. External requests: no. Cookies: no. Telemetry: no.', 'ua-free-copy' ); ?></p>
				<?php if ( empty( $conflicts ) ) : ?><p><?php esc_html_e( 'No other installed clipboard plugins were detected from their public plugin headers.', 'ua-free-copy' ); ?></p><?php else : ?>
					<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Detected plugin', 'ua-free-copy' ); ?></th><th><?php esc_html_e( 'Version', 'ua-free-copy' ); ?></th><th><?php esc_html_e( 'Status', 'ua-free-copy' ); ?></th></tr></thead><tbody><?php foreach ( $conflicts as $plugin ) : ?><tr><td><?php echo esc_html( (string) $plugin['name'] ); ?></td><td><?php echo esc_html( (string) $plugin['version'] ); ?></td><td><?php echo esc_html( ! empty( $plugin['active'] ) ? __( 'Active', 'ua-free-copy' ) : __( 'Installed', 'ua-free-copy' ) ); ?></td></tr><?php endforeach; ?></tbody></table>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'A successful browser copy dispatches the local custom event uafree:copy-success. Its detail contains only a configured target key, never the copied value.', 'ua-free-copy' ); ?></p>
			</div>

		</div>
		<?php
	}

}
