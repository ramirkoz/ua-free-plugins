<?php
/**
 * Plugin Name: UA FREE Site Bridge
 * Description: Secure read-only WordPress diagnostics API with same-site HTTP probes for private automation and GPT Actions.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://uafree.org/
 * Plugin URI: https://uafree.org/ua-free-plugins/#plugin-ua-free-site-bridge
 * Version: 0.4.9
 * Author: UA FREE
 * Text Domain: ua-free-site-bridge
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UAFREE_SITE_BRIDGE_VERSION', '0.4.9' );
define( 'UAFREE_SITE_BRIDGE_FILE', __FILE__ );
define( 'UAFREE_SITE_BRIDGE_DIR', plugin_dir_path( __FILE__ ) );

require_once UAFREE_SITE_BRIDGE_DIR . 'includes/class-ua-free-suite-registry.php';
require_once UAFREE_SITE_BRIDGE_DIR . 'includes/class-uafree-site-bridge.php';

\UAFree\SiteBridge\Bridge::init();

\UAFree\Suite\Registry::register(
	array(
		'slug'          => 'ua-free-site-bridge',
		'name'          => 'UA FREE Site Bridge',
		'version'       => UAFREE_SITE_BRIDGE_VERSION,
		'settings_page' => 'ua-free-site-bridge',
	)
);


if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-uafree-admin-support-footer.php';
	\UAFree_Admin_Support_Footer::init();
}
