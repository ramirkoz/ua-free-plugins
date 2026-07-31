<?php
/**
 * Plugin Name: UA FREE SEO Core
 * Description: Lightweight standalone SEO, metadata, schema, sitemap integration, AI discovery and accessibility audit originally developed for a charitable foundation website.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://uafree.org/
 * Plugin URI: https://uafree.org/ua-free-plugins/#plugin-ua-free-seo-core
 * Version: 2.0.7
 * Author: UA FREE
 * Text Domain: ua-free-seo-core
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UAFREE_SEO_VERSION', '2.0.7' );
define( 'UAFREE_SEO_FILE', __FILE__ );
define( 'UAFREE_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'UAFREE_SEO_URL', plugin_dir_url( __FILE__ ) );

require_once UAFREE_SEO_DIR . 'includes/class-ua-free-suite-registry.php';
require_once UAFREE_SEO_DIR . 'includes/class-uafree-seo-scanner.php';
require_once UAFREE_SEO_DIR . 'includes/class-uafree-seo-core.php';
require_once UAFREE_SEO_DIR . 'includes/class-uafree-seo-admin.php';

register_activation_hook( __FILE__, array( 'UAFree_SEO_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'UAFree_SEO_Core', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		\UAFree\Suite\Registry::register(
			array(
				'slug'          => 'ua-free-seo-core',
				'name'          => 'UA FREE SEO Core',
				'version'       => UAFREE_SEO_VERSION,
				'settings_page' => UAFree_SEO_Admin::PAGE,
			)
		);
		UAFree_SEO_Core::init();
		UAFree_SEO_Admin::init();
	}
);


if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-uafree-admin-support-footer.php';
	\UAFree_Admin_Support_Footer::init();
}
