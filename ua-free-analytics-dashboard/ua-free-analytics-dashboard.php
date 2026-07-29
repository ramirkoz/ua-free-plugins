<?php
/**
 * Plugin Name: UA FREE Suite Control Center
 * Description: A clear status and navigation center for the UA FREE Plugin Suite.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://uafree.org/
 * Plugin URI: https://uafree.org/ua-free-plugins/#plugin-ua-free-suite-control-center
 * Version: 0.3.11
 * Author: UA FREE
 * Text Domain: ua-free-analytics-dashboard
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UAFREE_ANALYTICS_DASHBOARD_VERSION', '0.3.11' );
define( 'UAFREE_ANALYTICS_DASHBOARD_FILE', __FILE__ );
define( 'UAFREE_ANALYTICS_DASHBOARD_DIR', plugin_dir_path( __FILE__ ) );
define( 'UAFREE_ANALYTICS_DASHBOARD_URL', plugin_dir_url( __FILE__ ) );

require_once UAFREE_ANALYTICS_DASHBOARD_DIR . 'includes/class-ua-free-suite-registry.php';
require_once UAFREE_ANALYTICS_DASHBOARD_DIR . 'includes/class-uafree-analytics-dashboard.php';

register_activation_hook( __FILE__, array( \UAFree\AnalyticsDashboard\Dashboard::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \UAFree\AnalyticsDashboard\Dashboard::class, 'deactivate' ) );

\UAFree\AnalyticsDashboard\Dashboard::init();

\UAFree\Suite\Registry::register(
	array(
		'slug'          => 'ua-free-analytics-dashboard',
		'name'          => 'UA FREE Suite Control Center',
		'version'       => UAFREE_ANALYTICS_DASHBOARD_VERSION,
		'settings_page' => 'ua-free-analytics-dashboard',
	)
);

function uafree_analytics_dashboard_get_report( int $days = 30 ): array {
	return \UAFree\AnalyticsDashboard\Dashboard::report( $days );
}

function uafree_analytics_dashboard_get_metric_schema(): array {
	return \UAFree\AnalyticsDashboard\Dashboard::metric_schema();
}


function uafree_suite_control_center_get_summary( int $days = 30 ): array {
	return \UAFree\AnalyticsDashboard\Dashboard::control_summary( $days );
}


if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-uafree-admin-support-footer.php';
	\UAFree_Admin_Support_Footer::init();
}
