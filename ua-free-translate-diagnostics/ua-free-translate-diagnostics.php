<?php
/**
 * Plugin Name: UA FREE Translate Diagnostics
 * Description: Read-only diagnostics for UA FREE Static Translate, created for a charitable foundation website and rebuilt as a universal WordPress tool.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Plugin URI: https://uafree.org/ua-free-plugins/#plugin-ua-free-translate-diagnostics
 * Version: 0.2.12
 * Author: UA FREE
 * Author URI: https://uafree.org/
 * Text Domain: ua-free-translate-diagnostics
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
	'slug' => 'ua-free-translate-diagnostics',
	'name' => 'UA FREE Translate Diagnostics',
	'version' => '0.2.12',
	'settings_page' => 'ua-free-translate-diagnostics',
) );

define( 'UAFREE_TD_VERSION', '0.2.12' );
define( 'UAFREE_TD_FILE', __FILE__ );
define( 'UAFREE_TD_DIR', plugin_dir_path( __FILE__ ) );

require_once UAFREE_TD_DIR . 'includes/class-uafree-translate-diagnostics.php';

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain(
			'ua-free-translate-diagnostics',
			false,
			dirname( plugin_basename( UAFREE_TD_FILE ) ) . '/languages'
		);
	}
);

UAFree_Translate_Diagnostics::init();


if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-uafree-admin-support-footer.php';
	\UAFree_Admin_Support_Footer::init();
}
