<?php
/**
 * Plugin Name: UA FREE Donate Stats & Conversions
 * Description: Privacy-conscious local donation journey analytics with optional consent-gated dataLayer events for Google Ads workflows.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://uafree.org/
 * Plugin URI: https://uafree.org/ua-free-plugins/#plugin-ua-free-donate-stats
 * Version: 1.2.4
 * Author: UA FREE
 * Text Domain: ua-free-donate-stats
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

namespace UAFree\DonateStats {

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UAFREE_DONATE_STATS_VERSION', '1.2.4' );
define( 'UAFREE_DONATE_STATS_FILE', __FILE__ );
define( 'UAFREE_DONATE_STATS_DIR', plugin_dir_path( __FILE__ ) );
define( 'UAFREE_DONATE_STATS_URL', plugin_dir_url( __FILE__ ) );

require_once UAFREE_DONATE_STATS_DIR . 'includes/class-storage.php';
require_once UAFREE_DONATE_STATS_DIR . 'includes/class-tracker.php';
require_once UAFREE_DONATE_STATS_DIR . 'includes/class-suite.php';
require_once UAFREE_DONATE_STATS_DIR . 'includes/class-admin.php';
require_once UAFREE_DONATE_STATS_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );

Plugin::init();

}

namespace {

if ( ! function_exists( 'uafree_donate_stats_get_summary' ) ) {
	/**
	 * Public read-only API for dashboards and integrations.
	 *
	 * @return array<string,mixed>
	 */
	function uafree_donate_stats_get_summary( int $days = 30 ): array {
		return \UAFree\DonateStats\Storage::summary( $days );
	}
}

if ( ! function_exists( 'uafree_donate_stats_record_event' ) ) {
	/**
	 * Record a server-confirmed aggregate event without personal or payment data.
	 */
	function uafree_donate_stats_record_event(
		string $event_type,
		string $target_key = '',
		string $context_key = 'server',
		string $language = 'und'
	): bool {
		return \UAFree\DonateStats\Tracker::record_server_event(
			$event_type,
			$target_key,
			$context_key,
			$language
		);
	}
}

}


namespace {

if ( ! function_exists( 'uafree_donate_stats_get_manager_summary' ) ) {
	/**
	 * Manager-facing summary for the Suite Control Center and admin UI.
	 *
	 * @return array<string,mixed>
	 */
	function uafree_donate_stats_get_manager_summary( int $days = 30 ): array {
		return \UAFree\DonateStats\Admin::manager_summary( $days );
	}
}

}

namespace {

if ( ! function_exists( 'uafree_donate_stats_get_confirmation_status' ) ) {
	/**
	 * Public read-only status for Control Center.
	 *
	 * @return array<string,mixed>
	 */
	function uafree_donate_stats_get_confirmation_status(): array {
		return \UAFree\DonateStats\Plugin::confirmation_status();
	}
}

}


namespace {
if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-uafree-admin-support-footer.php';
	\UAFree_Admin_Support_Footer::init();
}
}
