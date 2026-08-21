<?php
/**
 * Plugin Name: KOZ Copy Actions
 * Description: Accessible, privacy-safe copy-to-clipboard actions for WordPress.
 * Version: 1.1.14
 * Author: Tony Kozyriev
 * Author URI: https://www.linkedin.com/in/tonykoz/
 * Plugin URI: https://github.com/ramirkoz/ua-free-plugins
 * Text Domain: koz-copy-actions
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOZCOAC_VERSION', '1.1.14' );
define( 'KOZCOAC_FILE', __FILE__ );
define( 'KOZCOAC_DIR', plugin_dir_path( __FILE__ ) );

require_once KOZCOAC_DIR . 'includes/class-kozcoac-runtime-i18n.php';
require_once KOZCOAC_DIR . 'includes/class-plugin.php';
require_once KOZCOAC_DIR . 'includes/class-environment-scanner.php';
require_once KOZCOAC_DIR . 'includes/class-admin.php';

\ramirkz\kozcopyactions\KOZCOAC_Runtime_I18n::register( 'koz-copy-actions', KOZCOAC_DIR );

register_activation_hook(
	__FILE__,
	array( '\ramirkz\kozcopyactions\KOZCOAC_Plugin', 'activate' )
);

add_action(
	'plugins_loaded',
	static function (): void {
		\ramirkz\kozcopyactions\KOZCOAC_Plugin::init();
		\ramirkz\kozcopyactions\KOZCOAC_Admin::init();
	}
);

/**
 * Read-only status API for companion plugins.
 *
 * @return array<string,mixed>
 */
function kozcoac_get_status(): array {
	return \ramirkz\kozcopyactions\KOZCOAC_Plugin::public_status();
}

if ( is_admin() ) {
	require_once KOZCOAC_DIR . 'includes/class-kozcoac-admin-support-panel.php';
	\ramirkz\kozcopyactions\KOZCOAC_Admin_Support_Panel::init();
}
