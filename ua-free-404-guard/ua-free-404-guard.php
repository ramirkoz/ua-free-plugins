<?php
/**
 * Plugin Name: UA FREE 404 Guard
 * Description: Privacy-safe 404/410 logging, controlled same-site redirects and URL intelligence.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://uafree.org/
 * Plugin URI: https://uafree.org/ua-free-plugins/#plugin-ua-free-404-guard
 * Version: 2.0.6
 * Author: UA FREE
 * Text Domain: ua-free-404-guard
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UAFREE_404_VERSION', '2.0.6' );
define( 'UAFREE_404_FILE', __FILE__ );
define( 'UAFREE_404_DIR', plugin_dir_path( __FILE__ ) );

require_once UAFREE_404_DIR . 'includes/class-ua-free-suite-registry.php';
require_once UAFREE_404_DIR . 'includes/class-environment-scanner.php';
require_once UAFREE_404_DIR . 'includes/class-guard.php';
require_once UAFREE_404_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( 'UAFree\\Guard404\\Guard', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'UAFree\\Guard404\\Guard', 'deactivate' ) );

\UAFree\Suite\Registry::register(
	array(
		'slug'          => 'ua-free-404-guard',
		'name'          => 'UA FREE 404 Guard',
		'version'       => UAFREE_404_VERSION,
		'settings_page' => 'uafree-404-guard',
	)
);

add_action(
	'plugins_loaded',
	static function (): void {
		\UAFree\Guard404\Guard::init();
		\UAFree\Guard404\Admin::init();
	}
);


if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-uafree-admin-support-footer.php';
	\UAFree_Admin_Support_Footer::init();
}
