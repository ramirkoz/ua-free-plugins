<?php
/**
 * Plugin Name: KOZ Suite Control Center
 * Description: Unified status, navigation and privacy-safe reporting for the KOZ WordPress Suite.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://www.linkedin.com/in/tonykoz/
 * Plugin URI: https://github.com/ramirkoz/ua-free-plugins
 * Version: 0.4.7
 * Author: Tony Kozyriev
 * Text Domain: koz-suite-control-center
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOZSUITECC_VERSION', '0.4.7' );
define( 'KOZSUITECC_FILE', __FILE__ );
define( 'KOZSUITECC_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOZSUITECC_URL', plugin_dir_url( __FILE__ ) );

require_once KOZSUITECC_DIR . 'includes/class-kozsuitecc-runtime-i18n.php';
\ramirkz\kozsuitecc\KOZSUITECC_Runtime_I18n::register( 'koz-suite-control-center', KOZSUITECC_DIR );

require_once KOZSUITECC_DIR . 'includes/class-kozsuitecc-dashboard.php';

register_activation_hook( __FILE__, array( '\ramirkz\kozsuitecc\KOZSUITECC_Dashboard', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\ramirkz\kozsuitecc\KOZSUITECC_Dashboard', 'deactivate' ) );

\ramirkz\kozsuitecc\KOZSUITECC_Dashboard::init();

if ( is_admin() ) {
	require_once KOZSUITECC_DIR . 'includes/class-kozsuitecc-admin-support-panel.php';
	\ramirkz\kozsuitecc\KOZSUITECC_Admin_Support_Panel::init();
}
