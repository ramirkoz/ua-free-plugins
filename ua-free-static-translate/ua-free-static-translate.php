<?php
/**
 * Plugin Name: UA FREE Static Translate
 * Description: Frontend-only static translation for the UA FREE live site, preserving proven gallery, PageLayer and donation-route compatibility during migration.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://uafree.org/
 * Plugin URI: https://uafree.org/ua-free-plugins/#plugin-ua-free-static-translate
 * Version: 0.8.5
 * Author: UA FREE
 * Text Domain: ua-free-static-translate
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-ua-free-suite-registry.php';
\UAFree\Suite\Registry::register( array(
	'slug' => 'ua-free-static-translate',
	'name' => 'UA FREE Static Translate',
	'version' => '0.8.5',
	'settings_page' => 'uafree-static-translate-auto',
) );

define( 'UAFREE_ST_VERSION', '0.8.5' );
define( 'UAFREE_ST_DIR', plugin_dir_path( __FILE__ ) );
define( 'UAFREE_ST_FILE', __FILE__ );

require_once UAFREE_ST_DIR . 'includes/class-uafree-static-translate-autonomous.php';
require_once UAFREE_ST_DIR . 'includes/class-uafree-static-translate-cleanup.php';

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain(
			'ua-free-static-translate',
			false,
			dirname( plugin_basename( UAFREE_ST_FILE ) ) . '/languages'
		);
	}
);

register_activation_hook(
	__FILE__,
	array( 'UAFree_Static_Translate_Autonomous', 'activate' )
);

register_deactivation_hook(
	__FILE__,
	array( 'UAFree_Static_Translate_Autonomous', 'deactivate' )
);

UAFree_Static_Translate_Autonomous::init();
UAFree_Static_Translate_Cleanup::init();
add_filter( 'plugin_action_links_' . plugin_basename( UAFREE_ST_FILE ), static function ( array $links ): array {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=uafree-static-translate-auto' ) ) . '">' . esc_html__( 'Settings', 'ua-free-static-translate' ) . '</a>' );
	return $links;
} );

if ( ! function_exists( 'uafree_static_translate_get_status' ) ) {
	function uafree_static_translate_get_status(): array {
		return UAFree_Static_Translate_Autonomous::public_status();
	}
}


if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-uafree-admin-support-footer.php';
	\UAFree_Admin_Support_Footer::init();
}
