<?php
/**
 * Plugin Name: KOZ Site Bridge
 * Plugin URI: https://github.com/ramirkoz/ua-free-plugins
 * Description: Secure read-only WordPress diagnostics API with controlled same-site HTTP probes for private automation and GPT Actions.
 * Version: 0.5.7
 * Author: Tony Kozyriev
 * Author URI: https://www.linkedin.com/in/tonykoz/
 * Text Domain: koz-site-bridge
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOZBRIDGE_VERSION', '0.5.7' );
define( 'KOZBRIDGE_FILE', __FILE__ );
define( 'KOZBRIDGE_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOZBRIDGE_URL', plugin_dir_url( __FILE__ ) );

require_once KOZBRIDGE_DIR . 'includes/class-kozbridge-runtime-i18n.php';
require_once KOZBRIDGE_DIR . 'includes/class-kozbridge-suite-registry.php';
require_once KOZBRIDGE_DIR . 'includes/class-kozbridge-bridge.php';

\ramirkz\kozbridge\KOZBRIDGE_Runtime_I18n::register( 'koz-site-bridge', KOZBRIDGE_DIR );
\ramirkz\kozbridge\KOZBRIDGE_Suite_Registry::register(
	array(
		'slug'          => 'koz-site-bridge',
		'name'          => 'KOZ Site Bridge',
		'version'       => KOZBRIDGE_VERSION,
		'settings_page' => 'koz-site-bridge',
	)
);

register_activation_hook(
	KOZBRIDGE_FILE,
	static function (): void {
		// Preserve compatible settings without changing the activation state of any other plugin.
		\ramirkz\kozbridge\KOZBRIDGE_Bridge::migrate_legacy_data();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		\ramirkz\kozbridge\KOZBRIDGE_Bridge::init();
	}
);

if ( is_admin() ) {
	require_once KOZBRIDGE_DIR . 'includes/class-kozbridge-admin-support-panel.php';
	\ramirkz\kozbridge\KOZBRIDGE_Admin_Support_Panel::init();
}
