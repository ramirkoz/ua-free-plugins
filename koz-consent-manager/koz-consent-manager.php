<?php
/**
 * Plugin Name: KOZ Consent Manager
 * Description: Privacy-first consent banner with Google Consent Mode v2, WP Consent API, Microsoft consent, blocked embeds until consent and optional consent records. No IP addresses stored.
 * Version: 0.3.0
 * Author: Tony Kozyriev
 * Author URI: https://www.linkedin.com/in/tonykoz/
 * Plugin URI: https://github.com/ramirkoz/ua-free-plugins
 * Text Domain: koz-consent-manager
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOZCONSENT_VERSION', '0.3.0' );
define( 'KOZCONSENT_FILE', __FILE__ );
define( 'KOZCONSENT_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOZCONSENT_URL', plugin_dir_url( __FILE__ ) );

require_once KOZCONSENT_DIR . 'includes/class-kozconsent-runtime-i18n.php';
require_once KOZCONSENT_DIR . 'includes/class-kozconsent-suite-registry.php';
require_once KOZCONSENT_DIR . 'includes/class-kozconsent-settings.php';
require_once KOZCONSENT_DIR . 'includes/class-consent-manager.php';
require_once KOZCONSENT_DIR . 'includes/class-kozconsent-consent-mode.php';
require_once KOZCONSENT_DIR . 'includes/class-kozconsent-wp-consent-api.php';
require_once KOZCONSENT_DIR . 'includes/class-kozconsent-frontend.php';
require_once KOZCONSENT_DIR . 'includes/class-kozconsent-media.php';
require_once KOZCONSENT_DIR . 'includes/class-kozconsent-log.php';
require_once KOZCONSENT_DIR . 'includes/class-kozconsent-scanner.php';
require_once KOZCONSENT_DIR . 'includes/class-kozconsent-site-health.php';
require_once KOZCONSENT_DIR . 'includes/class-kozconsent-admin.php';

$kozconsent_legacy_class = implode( '', array( 'KOZ', 'Consent', 'Manager' ) ) . '\\' . 'Consent_Manager';
if ( ! class_exists( $kozconsent_legacy_class, false ) ) {
	class_alias( \ramirkz\kozconsent\KOZCONSENT_Manager::class, $kozconsent_legacy_class );
}

\ramirkz\kozconsent\KOZCONSENT_Runtime_I18n::register( 'koz-consent-manager', KOZCONSENT_DIR );
\ramirkz\kozconsent\KOZCONSENT_Suite_Registry::register(
	array(
		'slug'          => 'koz-consent-manager',
		'name'          => 'KOZ Consent Manager',
		'version'       => KOZCONSENT_VERSION,
		'settings_page' => 'koz-consent-manager',
	)
);
\ramirkz\kozconsent\KOZCONSENT_Manager::init();

register_activation_hook( __FILE__, array( '\\ramirkz\\kozconsent\\KOZCONSENT_Manager', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\ramirkz\\kozconsent\\KOZCONSENT_Manager', 'deactivate' ) );

if ( is_admin() ) {
	require_once KOZCONSENT_DIR . 'includes/class-kozconsent-admin-support-panel.php';
	\ramirkz\kozconsent\KOZCONSENT_Admin_Support_Panel::init();
}
