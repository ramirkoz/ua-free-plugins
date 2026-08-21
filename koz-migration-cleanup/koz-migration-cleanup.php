<?php
/**
 * Plugin Name: KOZ Migration & Cleanup
 * Plugin URI: https://github.com/ramirkoz/ua-free-plugins
 * Description: Creates privacy-safe WordPress environment snapshots and conservatively identifies likely plugin leftovers.
 * Version: 0.9.7
 * Author: Tony Kozyriev
 * Author URI: https://www.linkedin.com/in/tonykoz/
 * Text Domain: koz-migration-cleanup
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOZMIG_VERSION', '0.9.7' );
define( 'KOZMIG_FILE', __FILE__ );
define( 'KOZMIG_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOZMIG_URL', plugin_dir_url( __FILE__ ) );

require_once KOZMIG_DIR . 'includes/class-kozmig-runtime-i18n.php';
require_once KOZMIG_DIR . 'includes/class-kozmig-suite-registry.php';
require_once KOZMIG_DIR . 'includes/class-kozmig-content-migration-scanner.php';
require_once KOZMIG_DIR . 'includes/class-kozmig-environment-scanner.php';
require_once KOZMIG_DIR . 'includes/class-kozmig-snapshot-manager.php';
require_once KOZMIG_DIR . 'includes/class-kozmig-admin.php';
require_once KOZMIG_DIR . 'includes/class-kozmig-plugin.php';

\ramirkz\kozmig\KOZMIG_Runtime_I18n::register( 'koz-migration-cleanup', KOZMIG_DIR );

\ramirkz\kozmig\KOZMIG_Suite_Registry::register(
	array(
		'slug'          => 'koz-migration-cleanup',
		'name'          => 'KOZ Migration & Cleanup',
		'version'       => KOZMIG_VERSION,
		'settings_page' => 'koz-migration-cleanup',
	)
);

register_activation_hook( KOZMIG_FILE, array( '\\ramirkz\\kozmig\\KOZMIG_Plugin', 'activate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		\ramirkz\kozmig\KOZMIG_Plugin::init();
	}
);

add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$count = (int) get_transient( 'kozmig_legacy_deactivated' );
		if ( $count <= 0 ) {
			return;
		}
		delete_transient( 'kozmig_legacy_deactivated' );
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html__( 'KOZ Migration & Cleanup activated. The former UA FREE package was deactivated without deleting its files or data.', 'koz-migration-cleanup' ); ?></p>
		</div>
		<?php
	}
);

if ( is_admin() ) {
	require_once KOZMIG_DIR . 'includes/class-kozmig-admin-support-panel.php';
	\ramirkz\kozmig\KOZMIG_Admin_Support_Panel::init();
}
