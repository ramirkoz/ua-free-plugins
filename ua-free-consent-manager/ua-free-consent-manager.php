<?php
/**
 * Plugin Name: UA FREE Consent Manager
 * Description: Privacy-first consent manager and local allowlist for optional WordPress scripts.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://uafree.org/
 * Plugin URI: https://uafree.org/ua-free-plugins/#plugin-ua-free-consent-manager
 * Version: 0.1.6
 * Author: UA FREE
 * Text Domain: ua-free-consent-manager
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UAFREE_CONSENT_MANAGER_VERSION', '0.1.6' );
define( 'UAFREE_CONSENT_MANAGER_FILE', __FILE__ );
define( 'UAFREE_CONSENT_MANAGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'UAFREE_CONSENT_MANAGER_URL', plugin_dir_url( __FILE__ ) );

require_once UAFREE_CONSENT_MANAGER_DIR . 'includes/class-ua-free-suite-registry.php';
require_once UAFREE_CONSENT_MANAGER_DIR . 'includes/class-uafree-consent-manager.php';

\UAFree\ConsentManager\Consent_Manager::init();

\UAFree\Suite\Registry::register(
	array(
		'slug'          => 'ua-free-consent-manager',
		'name'          => 'UA FREE Consent Manager',
		'version'       => UAFREE_CONSENT_MANAGER_VERSION,
		'settings_page' => 'ua-free-consent-manager',
	)
);

register_activation_hook(
	__FILE__,
	array( '\\UAFree\\ConsentManager\\Consent_Manager', 'activate' )
);

/**
 * Return the current visitor consent state.
 *
 * @return array{necessary:bool,analytics:bool,advertising:bool,external_media:bool,policy_version:string,updated_at:?string}
 */
function uafree_consent_manager_get_status(): array {
	return \UAFree\ConsentManager\Consent_Manager::status();
}

/**
 * Check whether a consent category is currently allowed.
 */
function uafree_consent_manager_is_allowed( string $category ): bool {
	return \UAFree\ConsentManager\Consent_Manager::is_allowed( $category );
}

/**
 * Register a local script integration.
 *
 * Accepted keys: id, name, category, script_handles.
 * Script URLs, callbacks and executable code are intentionally rejected.
 */
function uafree_consent_manager_register_integration( array $integration ): bool {
	return \UAFree\ConsentManager\Consent_Manager::register_integration( $integration );
}

/**
 * Return privacy-safe registered integration metadata.
 */
function uafree_consent_manager_get_integrations(): array {
	return \UAFree\ConsentManager\Consent_Manager::public_integrations();
}


if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-uafree-admin-support-footer.php';
	\UAFree_Admin_Support_Footer::init();
}
