<?php
/**
 * Plugin Name: KOZ Google Ads Campaign Builder
 * Description: Builds reviewable Google Ad Grants and standard Google Ads Editor import packages.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://www.linkedin.com/in/tonykoz/
 * Plugin URI: https://github.com/ramirkoz/ua-free-plugins
 * Version: 1.4.13
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

define( 'KOZGADS_VERSION', '1.4.13' );
define( 'KOZGADS_FILE', __FILE__ );
define( 'KOZGADS_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOZGADS_URL', plugin_dir_url( __FILE__ ) );

require_once KOZGADS_DIR . 'includes/class-kozgads-runtime-i18n.php';
require_once KOZGADS_DIR . 'includes/class-kozgads-plugin.php';

\ramirkz\kozgads\KOZGADS_Runtime_I18n::register( 'koz-google-ads-campaign-builder', KOZGADS_DIR );
\ramirkz\kozgads\KOZGADS_Plugin::init();

function kozgads_deactivate_legacy(): void {
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$active_plugins = (array) get_option( 'active_plugins', array() );
	$legacy_files   = array();
	foreach ( $active_plugins as $plugin_file ) {
		$plugin_file = (string) $plugin_file;
		if (
			( str_starts_with( $plugin_file, 'ua-free-google-ads-campaign-builder/' )
				|| str_starts_with( $plugin_file, 'ua-free-ad-grants-builder/' ) )
			&& plugin_basename( KOZGADS_FILE ) !== $plugin_file
		) {
			$legacy_files[] = $plugin_file;
		}
	}

	if ( ! empty( $legacy_files ) ) {
		deactivate_plugins( $legacy_files, true );
		set_transient( 'kozgads_legacy_deactivated', count( $legacy_files ), MINUTE_IN_SECONDS );
	}
}

register_activation_hook( KOZGADS_FILE, 'kozgads_deactivate_legacy' );

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
		$count = (int) get_transient( 'kozgads_legacy_deactivated' );
		if ( $count <= 0 ) {
			return;
		}
		delete_transient( 'kozgads_legacy_deactivated' );
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html__( 'KOZ Google Ads Campaign Builder activated. The former UA FREE package was deactivated without deleting its files or settings.', 'koz-google-ads-campaign-builder' ); ?></p>
		</div>
		<?php
	}
);

if ( is_admin() ) {
	require_once KOZGADS_DIR . 'includes/class-kozgads-admin-support-panel.php';
	\ramirkz\kozgads\KOZGADS_Admin_Support_Panel::init();
}
