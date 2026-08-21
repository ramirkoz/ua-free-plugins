<?php
/**
 * Plugin Name: KOZ Translate Diagnostics
 * Plugin URI: https://github.com/ramirkoz/ua-free-plugins
 * Description: Read-only diagnostics and privacy-safe reports for KOZ Static Translate installations.
 * Version: 0.3.6
 * Author: Tony Kozyriev
 * Author URI: https://www.linkedin.com/in/tonykoz/
 * Text Domain: koz-translate-diagnostics
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOZTDIAG_VERSION', '0.3.6' );
define( 'KOZTDIAG_FILE', __FILE__ );
define( 'KOZTDIAG_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOZTDIAG_URL', plugin_dir_url( __FILE__ ) );

require_once KOZTDIAG_DIR . 'includes/class-koztdiag-runtime-i18n.php';
require_once KOZTDIAG_DIR . 'includes/class-koztdiag-suite-registry.php';
require_once KOZTDIAG_DIR . 'includes/class-koztdiag-translate-diagnostics.php';

\ramirkz\koztdiag\KOZTDIAG_Runtime_I18n::register( 'koz-translate-diagnostics', KOZTDIAG_DIR );
\ramirkz\koztdiag\KOZTDIAG_Suite_Registry::register(
	array(
		'slug'          => 'koz-translate-diagnostics',
		'name'          => 'KOZ Translate Diagnostics',
		'version'       => KOZTDIAG_VERSION,
		'settings_page' => 'koz-translate-diagnostics',
	)
);

register_activation_hook(
	KOZTDIAG_FILE,
	static function (): void {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$legacy = array();
		foreach ( (array) get_option( 'active_plugins', array() ) as $plugin_file ) {
			$plugin_file = (string) $plugin_file;
			if ( str_starts_with( $plugin_file, 'ua-free-translate-diagnostics/' ) ) {
				$legacy[] = $plugin_file;
			}
		}
		if ( ! empty( $legacy ) ) {
			deactivate_plugins( $legacy, true );
			set_transient( 'koztdiag_legacy_deactivated', count( $legacy ), MINUTE_IN_SECONDS );
		}
	}
);

\ramirkz\koztdiag\KOZTDIAG_Translate_Diagnostics::init();

add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$count = (int) get_transient( 'koztdiag_legacy_deactivated' );
		if ( $count <= 0 ) {
			return;
		}
		delete_transient( 'koztdiag_legacy_deactivated' );
		?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'KOZ Translate Diagnostics activated. The former UA FREE package was deactivated without deleting data.', 'koz-translate-diagnostics' ); ?></p></div>
		<?php
	}
);

if ( is_admin() ) {
	require_once KOZTDIAG_DIR . 'includes/class-koztdiag-admin-support-panel.php';
	\ramirkz\koztdiag\KOZTDIAG_Admin_Support_Panel::init();
}
