<?php
/**
 * Plugin Name: KOZ Translate Diagnostics
 * Plugin URI: https://github.com/ramirkoz/ua-free-plugins
 * Description: Read-only diagnostics, Site Health checks and privacy-safe reports for KOZ Static Translate installations.
 * Version: 0.4.0
 * Author: Tony Kozyriev
 * Author URI: https://www.linkedin.com/in/tonykoz/
 * Text Domain: koz-translate-diagnostics
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOZTDIAG_VERSION', '0.4.0' );
define( 'KOZTDIAG_FILE', __FILE__ );
define( 'KOZTDIAG_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOZTDIAG_URL', plugin_dir_url( __FILE__ ) );

require_once KOZTDIAG_DIR . 'includes/class-koztdiag-runtime-i18n.php';
require_once KOZTDIAG_DIR . 'includes/class-koztdiag-suite-registry.php';
require_once KOZTDIAG_DIR . 'includes/class-koztdiag-translate-diagnostics.php';
require_once KOZTDIAG_DIR . 'includes/class-koztdiag-budget.php';
require_once KOZTDIAG_DIR . 'includes/class-koztdiag-export.php';
require_once KOZTDIAG_DIR . 'includes/class-koztdiag-site-health.php';
require_once KOZTDIAG_DIR . 'includes/class-koztdiag-admin-page.php';

\ramirkz\koztdiag\KOZTDIAG_Runtime_I18n::register( 'koz-translate-diagnostics', KOZTDIAG_DIR );
\ramirkz\koztdiag\KOZTDIAG_Suite_Registry::register(
	array(
		'slug'          => 'koz-translate-diagnostics',
		'name'          => 'KOZ Translate Diagnostics',
		'version'       => KOZTDIAG_VERSION,
		'settings_page' => 'koz-translate-diagnostics',
	)
);

\ramirkz\koztdiag\KOZTDIAG_Translate_Diagnostics::init();

add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		foreach ( (array) get_option( 'active_plugins', array() ) as $plugin_file ) {
			if ( str_starts_with( (string) $plugin_file, 'ua-free-translate-diagnostics/' ) ) {
				?>
				<div class="notice notice-warning"><p><?php echo esc_html__( 'The former UA FREE Translate Diagnostics plugin is still active. Deactivate it manually; KOZ Translate Diagnostics replaces it.', 'koz-translate-diagnostics' ); ?></p></div>
				<?php
				return;
			}
		}
	}
);

if ( is_admin() ) {
	require_once KOZTDIAG_DIR . 'includes/class-koztdiag-admin-support-panel.php';
	\ramirkz\koztdiag\KOZTDIAG_Admin_Support_Panel::init();
}
