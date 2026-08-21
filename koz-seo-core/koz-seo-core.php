<?php
/**
 * Plugin Name: KOZ SEO Core
 * Plugin URI: https://github.com/ramirkoz/ua-free-plugins
 * Description: Lightweight standalone SEO, metadata, schema, sitemap integration, AI discovery and accessibility audit.
 * Version: 2.1.22
 * Author: Tony Kozyriev
 * Author URI: https://www.linkedin.com/in/tonykoz/
 * Text Domain: koz-seo-core
 * Domain Path: /languages
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOZSEO_VERSION', '2.1.22' );
define( 'KOZSEO_FILE', __FILE__ );
define( 'KOZSEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOZSEO_URL', plugin_dir_url( __FILE__ ) );

require_once KOZSEO_DIR . 'includes/class-kozseo-runtime-i18n.php';
require_once KOZSEO_DIR . 'includes/class-kozseo-suite-registry.php';
require_once KOZSEO_DIR . 'includes/class-kozseo-scanner.php';
require_once KOZSEO_DIR . 'includes/class-kozseo-core.php';
require_once KOZSEO_DIR . 'includes/class-kozseo-admin.php';
require_once KOZSEO_DIR . 'includes/class-kozseo-alt-manager.php';

\ramirkz\kozseo\KOZSEO_Runtime_I18n::register( 'koz-seo-core', KOZSEO_DIR );
\ramirkz\kozseo\KOZSEO_Suite_Registry::register(
	array(
		'slug'          => 'koz-seo-core',
		'name'          => 'KOZ SEO Core',
		'version'       => KOZSEO_VERSION,
		'settings_page' => 'koz-seo-core',
	)
);

register_activation_hook(
	KOZSEO_FILE,
	static function (): void {
		KOZSEO_Core::migrate_legacy_settings();
		KOZSEO_Core::activate();
	}
);
register_deactivation_hook( KOZSEO_FILE, array( 'KOZSEO_Core', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		KOZSEO_Core::migrate_legacy_settings();
		KOZSEO_Core::init();
		KOZSEO_Admin::init();
		KOZSEO_Alt_Manager::init();
	}
);


if ( is_admin() ) {
	require_once KOZSEO_DIR . 'includes/class-kozseo-admin-support-panel.php';
	\ramirkz\kozseo\KOZSEO_Admin_Support_Panel::init();
}
