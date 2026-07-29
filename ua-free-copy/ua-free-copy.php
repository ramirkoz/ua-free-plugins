<?php
/**
 * Plugin Name: UA FREE Copy
 * Description: Accessible, privacy-safe copy-to-clipboard actions for WordPress.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://uafree.org/
 * Plugin URI: https://uafree.org/ua-free-plugins/#plugin-ua-free-copy
 * Version: 1.0.6
 * Author: UA FREE
 * Text Domain: ua-free-copy
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UAFREE_COPY_VERSION', '1.0.6' );
define( 'UAFREE_COPY_FILE', __FILE__ );
define( 'UAFREE_COPY_DIR', plugin_dir_path( __FILE__ ) );

require_once UAFREE_COPY_DIR . 'includes/class-environment-scanner.php';
require_once UAFREE_COPY_DIR . 'includes/class-plugin.php';
require_once UAFREE_COPY_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( 'UAFree\\CopyTool\\Plugin', 'activate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		\UAFree\CopyTool\Plugin::init();
		\UAFree\CopyTool\Admin::init();
	}
);

if ( ! function_exists( 'uafree_copy_get_status' ) ) {
	/**
	 * Read-only status API for companion plugins.
	 *
	 * @return array<string,mixed>
	 */
	function uafree_copy_get_status(): array {
		return \UAFree\CopyTool\Plugin::public_status();
	}
}


if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-uafree-admin-support-footer.php';
	\UAFree_Admin_Support_Footer::init();
}
