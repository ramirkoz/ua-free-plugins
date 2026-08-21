<?php
/**
 * Plugin Name: KOZ URL-Only Comment Spam
 * Description: Privacy-first moderation for comments whose visible content contains only URLs.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://www.linkedin.com/in/tonykoz/
 * Plugin URI: https://github.com/ramirkoz/ua-free-plugins
 * Version: 1.1.11
 * Author: Tony Kozyriev
 * Text Domain: koz-url-only-comment-spam
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOZURLSPAM_VERSION', '1.1.11' );
define( 'KOZURLSPAM_FILE', __FILE__ );
define( 'KOZURLSPAM_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOZURLSPAM_URL', plugin_dir_url( __FILE__ ) );

require_once KOZURLSPAM_DIR . 'includes/class-kozurlspam-runtime-i18n.php';
\ramirkz\kozurlspam\KOZURLSPAM_Runtime_I18n::register( 'koz-url-only-comment-spam', KOZURLSPAM_DIR );

require_once KOZURLSPAM_DIR . 'includes/class-detector.php';
require_once KOZURLSPAM_DIR . 'includes/class-admin.php';
require_once KOZURLSPAM_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'ramirkz\kozurlspam\Plugin', 'activate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		\ramirkz\kozurlspam\Plugin::instance()->boot();
	}
);

if ( is_admin() ) {
	require_once KOZURLSPAM_DIR . 'includes/class-kozurlspam-admin-support-panel.php';
	\ramirkz\kozurlspam\KOZURLSPAM_Admin_Support_Panel::init();
}
