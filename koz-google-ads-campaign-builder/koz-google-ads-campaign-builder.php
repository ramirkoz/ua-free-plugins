<?php
/**
 * Plugin Name: KOZ Google Ads Campaign Builder
 * Description: Builds reviewable Google Ad Grants and standard Google Ads Editor import packages.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://www.linkedin.com/in/tonykoz/
 * Plugin URI: https://github.com/ramirkoz/ua-free-plugins
 * Version: 1.5.0
 * Author: Tony Kozyriev
 * Text Domain: koz-google-ads-campaign-builder
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOZGADS_VERSION', '1.5.0' );
define( 'KOZGADS_FILE', __FILE__ );
define( 'KOZGADS_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOZGADS_URL', plugin_dir_url( __FILE__ ) );

require_once KOZGADS_DIR . 'includes/class-kozgads-runtime-i18n.php';
require_once KOZGADS_DIR . 'includes/class-kozgads-integrations.php';
require_once KOZGADS_DIR . 'includes/class-kozgads-copy.php';
require_once KOZGADS_DIR . 'includes/class-kozgads-report.php';
require_once KOZGADS_DIR . 'includes/class-kozgads-export.php';
require_once KOZGADS_DIR . 'includes/class-kozgads-checks.php';
require_once KOZGADS_DIR . 'includes/class-kozgads-health.php';
require_once KOZGADS_DIR . 'includes/class-kozgads-plugin.php';

\ramirkz\kozgads\KOZGADS_Runtime_I18n::register( 'koz-google-ads-campaign-builder', KOZGADS_DIR );
\ramirkz\kozgads\KOZGADS_Plugin::init();

function kozgads_get_status(): array {
	return \ramirkz\kozgads\KOZGADS_Plugin::get_status();
}

function kozgads_get_manager_summary(): array {
	return \ramirkz\kozgads\KOZGADS_Plugin::manager_summary();
}

add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		foreach ( (array) get_option( 'active_plugins', array() ) as $kozgads_plugin_file ) {
			$kozgads_plugin_file = (string) $kozgads_plugin_file;
			if ( str_starts_with( $kozgads_plugin_file, 'ua-free-google-ads-campaign-builder/' ) || str_starts_with( $kozgads_plugin_file, 'ua-free-ad-grants-builder/' ) ) {
				?>
				<div class="notice notice-warning"><p><?php echo esc_html__( 'The former UA FREE Google Ads builder is still active. Deactivate it after checking that KOZ Google Ads Campaign Builder works.', 'koz-google-ads-campaign-builder' ); ?> <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php echo esc_html__( 'Plugins', 'koz-google-ads-campaign-builder' ); ?></a></p></div>
				<?php
				return;
			}
		}
	}
);

if ( is_admin() ) {
	require_once KOZGADS_DIR . 'includes/class-kozgads-admin-support-panel.php';
	\ramirkz\kozgads\KOZGADS_Admin_Support_Panel::init();
}
