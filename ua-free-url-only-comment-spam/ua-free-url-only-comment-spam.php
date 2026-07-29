<?php
/**
 * Plugin Name: UA FREE URL-Only Comment Spam
 * Description: Privacy-first moderation for comments that contain only one or more URLs.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://uafree.org/
 * Plugin URI: https://uafree.org/ua-free-plugins/#plugin-ua-free-url-only-comment-spam
 * Version: 1.0.5
 * Author: UA FREE
 * Text Domain: ua-free-url-only-comment-spam
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UAFREE_URL_SPAM_VERSION', '1.0.5' );
define( 'UAFREE_URL_SPAM_FILE', __FILE__ );
define( 'UAFREE_URL_SPAM_DIR', plugin_dir_path( __FILE__ ) );
define( 'UAFREE_URL_SPAM_URL', plugin_dir_url( __FILE__ ) );

require_once UAFREE_URL_SPAM_DIR . 'includes/class-detector.php';
require_once UAFREE_URL_SPAM_DIR . 'includes/class-suite-registry.php';
require_once UAFREE_URL_SPAM_DIR . 'includes/class-admin.php';
require_once UAFREE_URL_SPAM_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'UAFree\\URLSpam\\Plugin', 'activate' ) );

UAFree\URLSpam\Plugin::instance()->boot();

if ( ! function_exists( 'uafree_url_only_comment_spam_get_status' ) ) {
	/**
	 * Read-only status API for other UA FREE Suite components.
	 *
	 * @return array<string,mixed>
	 */
	function uafree_url_only_comment_spam_get_status(): array {
		return UAFree\URLSpam\Plugin::instance()->get_status();
	}
}


if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-uafree-admin-support-footer.php';
	\UAFree_Admin_Support_Footer::init();
}
