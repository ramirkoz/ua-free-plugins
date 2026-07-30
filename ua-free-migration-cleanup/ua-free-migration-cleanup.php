<?php
/**
 * Plugin Name: UA FREE Migration & Cleanup
 * Plugin URI: https://uafree.org/ua-free-plugins/#plugin-ua-free-migration-cleanup
 * Description: Controlled snapshots, environment checks, migration and verified cleanup of plugin leftovers.
 * Version: 0.8.9
 * Author: UA FREE
 * Author URI: https://uafree.org/
 * Text Domain: ua-free-migration-cleanup
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-ua-free-suite-registry.php';
\UAFree\Suite\Registry::register( array(
	'slug' => 'ua-free-migration-cleanup',
	'name' => 'UA FREE Migration & Cleanup',
	'version' => '0.8.9',
	'settings_page' => 'ua-free-migration-cleanup',
) );

define( 'UAFREE_MC_VERSION', '0.8.9' );
define( 'UAFREE_MC_FILE', __FILE__ );
define( 'UAFREE_MC_DIR', plugin_dir_path( __FILE__ ) );
define( 'UAFREE_MC_URL', plugin_dir_url( __FILE__ ) );

require_once UAFREE_MC_DIR . 'includes/class-uafree-mc-suite-registry.php';
require_once UAFREE_MC_DIR . 'includes/class-uafree-mc-environment-scanner.php';
require_once UAFREE_MC_DIR . 'includes/class-uafree-mc-snapshot-manager.php';
require_once UAFREE_MC_DIR . 'includes/class-uafree-mc-admin.php';
require_once UAFREE_MC_DIR . 'includes/class-uafree-mc-plugin.php';

UAFree_MC_Plugin::init();


if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-uafree-admin-support-footer.php';
	\UAFree_Admin_Support_Footer::init();
}
