<?php
/**
 * Plugin Name: KOZ Donate Stats & Conversions
 * Description: Local donation-journey analytics and consent-aware conversion events for WordPress.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://www.linkedin.com/in/tonykoz/
 * Plugin URI: https://github.com/ramirkoz/ua-free-plugins
 * Version: 1.3.11
 * Author: Tony Kozyriev
 * Text Domain: koz-donate-stats
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOZDONATE_VERSION', '1.3.11' );
define( 'KOZDONATE_FILE', __FILE__ );
define( 'KOZDONATE_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOZDONATE_URL', plugin_dir_url( __FILE__ ) );

require_once KOZDONATE_DIR . 'includes/class-kozdonate-runtime-i18n.php';
\ramirkz\kozdonate\KOZDONATE_Runtime_I18n::register( 'koz-donate-stats', KOZDONATE_DIR );

require_once KOZDONATE_DIR . 'includes/class-storage.php';
require_once KOZDONATE_DIR . 'includes/class-tracker.php';
require_once KOZDONATE_DIR . 'includes/class-suite.php';
require_once KOZDONATE_DIR . 'includes/class-admin.php';
require_once KOZDONATE_DIR . 'includes/class-plugin.php';

\ramirkz\kozdonate\KOZDONATE_Plugin::init();

register_activation_hook( __FILE__, array( '\ramirkz\kozdonate\KOZDONATE_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\ramirkz\kozdonate\KOZDONATE_Plugin', 'deactivate' ) );

if ( is_admin() ) {
	require_once KOZDONATE_DIR . 'includes/class-kozdonate-admin-support-panel.php';
	\ramirkz\kozdonate\KOZDONATE_Admin_Support_Panel::init();
}
