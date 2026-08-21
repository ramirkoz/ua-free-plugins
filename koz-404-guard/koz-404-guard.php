<?php
/**
 * Plugin Name: KOZ 404 Guard & URL Intelligence
 * Plugin URI: https://github.com/ramirkoz/ua-free-plugins
 * Description: Privacy-safe 404/410 logging, controlled same-site redirects and URL intelligence.
 * Version: 2.1.4
 * Author: Tony Kozyriev
 * Author URI: https://www.linkedin.com/in/tonykoz/
 * Text Domain: koz-404-guard
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOZ404_VERSION', '2.1.4' );
define( 'KOZ404_FILE', __FILE__ );
define( 'KOZ404_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOZ404_URL', plugin_dir_url( __FILE__ ) );

require_once KOZ404_DIR . 'includes/class-koz404-runtime-i18n.php';
require_once KOZ404_DIR . 'includes/class-koz404-suite-registry.php';
require_once KOZ404_DIR . 'includes/class-koz404-environment-scanner.php';
require_once KOZ404_DIR . 'includes/class-koz404-guard.php';
require_once KOZ404_DIR . 'includes/class-koz404-admin.php';

\ramirkz\koz404\KOZ404_Runtime_I18n::register( 'koz-404-guard', KOZ404_DIR );
\ramirkz\koz404\KOZ404_Suite_Registry::register(
	array(
		'slug'          => 'koz-404-guard',
		'name'          => 'KOZ 404 Guard & URL Intelligence',
		'version'       => KOZ404_VERSION,
		'settings_page' => 'koz-404-guard',
	)
);

register_activation_hook(
	KOZ404_FILE,
	static function (): void {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$legacy = array();
		foreach ( (array) get_option( 'active_plugins', array() ) as $plugin_file ) {
			$plugin_file = (string) $plugin_file;
			if ( str_starts_with( $plugin_file, 'ua-free-404-guard/' ) ) {
				$legacy[] = $plugin_file;
			}
		}
		if ( ! empty( $legacy ) ) {
			deactivate_plugins( $legacy, true );
			set_transient( 'koz404_legacy_deactivated', count( $legacy ), MINUTE_IN_SECONDS );
		}
		\ramirkz\koz404\KOZ404_Guard::migrate_legacy_data();
		\ramirkz\koz404\KOZ404_Guard::activate();
	}
);
register_deactivation_hook( KOZ404_FILE, array( '\ramirkz\koz404\KOZ404_Guard', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		\ramirkz\koz404\KOZ404_Guard::migrate_legacy_data();
		\ramirkz\koz404\KOZ404_Guard::init();
		\ramirkz\koz404\KOZ404_Admin::init();
	}
);

add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) { return; }
		$count = (int) get_transient( 'koz404_legacy_deactivated' );
		if ( $count <= 0 ) { return; }
		delete_transient( 'koz404_legacy_deactivated' );
		?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'KOZ 404 Guard activated. The former UA FREE package was deactivated without deleting settings, rules or diagnostic data.', 'koz-404-guard' ); ?></p></div>
		<?php
	}
);

if ( is_admin() ) {
	require_once KOZ404_DIR . 'includes/class-koz404-admin-support-panel.php';
	\ramirkz\koz404\KOZ404_Admin_Support_Panel::init();
}
