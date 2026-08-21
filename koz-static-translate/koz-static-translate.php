<?php
/**
 * Plugin Name: KOZ Static Translate
 * Plugin URI: https://github.com/ramirkoz/ua-free-plugins
 * Description: Frontend static translation with queued Azure processing, translation memory, language routes, dynamic-content support and migration-safe compatibility.
 * Version: 0.9.17
 * Author: Tony Kozyriev
 * Author URI: https://www.linkedin.com/in/tonykoz/
 * Text Domain: koz-static-translate
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'KOZSTX_VERSION', '0.9.17' );
define( 'KOZSTX_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOZSTX_FILE', __FILE__ );
define( 'KOZSTX_URL', plugin_dir_url( __FILE__ ) );

require_once KOZSTX_DIR . 'includes/class-kozstx-suite-registry.php';
require_once KOZSTX_DIR . 'includes/class-kozstx-static-translate.php';
require_once KOZSTX_DIR . 'includes/class-kozstx-cleanup.php';

\ramirkz\kozstx\KOZSTX_Suite_Registry::register(
	array(
		'slug' => 'koz-static-translate',
		'name' => 'KOZ Static Translate',
		'version' => KOZSTX_VERSION,
		'settings_page' => 'koz-static-translate',
	)
);

register_activation_hook( KOZSTX_FILE, array( '\\ramirkz\\kozstx\\KOZSTX_Static_Translate', 'activate' ) );
register_deactivation_hook( KOZSTX_FILE, array( '\\ramirkz\\kozstx\\KOZSTX_Static_Translate', 'deactivate' ) );

register_activation_hook(
	KOZSTX_FILE,
	static function (): void {
		if ( ! function_exists( 'deactivate_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
		$legacy = array();
		foreach ( (array) get_option( 'active_plugins', array() ) as $plugin_file ) {
			$plugin_file = (string) $plugin_file;
			if ( str_starts_with( $plugin_file, 'ua-free-static-translate/' ) ) { $legacy[] = $plugin_file; }
		}
		if ( ! empty( $legacy ) ) {
			deactivate_plugins( $legacy, true );
			set_transient( 'kozstx_legacy_deactivated', count( $legacy ), MINUTE_IN_SECONDS );
		}
	}
);

\ramirkz\kozstx\KOZSTX_Static_Translate::init();
\ramirkz\kozstx\KOZSTX_Cleanup::init();

add_filter(
	'plugin_action_links_' . plugin_basename( KOZSTX_FILE ),
	static function ( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=koz-static-translate' ) ) . '">' . esc_html__( 'Settings', 'koz-static-translate' ) . '</a>' );
		return $links;
	}
);

function kozstx_get_status(): array {
	return \ramirkz\kozstx\KOZSTX_Static_Translate::public_status();
}

add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) { return; }
		$count = (int) get_transient( 'kozstx_legacy_deactivated' );
		if ( $count <= 0 ) { return; }
		delete_transient( 'kozstx_legacy_deactivated' );
		?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'KOZ Static Translate activated. The former UA FREE package was deactivated without deleting translation data.', 'koz-static-translate' ); ?></p></div><?php
	}
);

if ( is_admin() ) {
	require_once KOZSTX_DIR . 'includes/class-kozstx-admin-support-panel.php';
	\ramirkz\kozstx\KOZSTX_Admin_Support_Panel::init();
}
