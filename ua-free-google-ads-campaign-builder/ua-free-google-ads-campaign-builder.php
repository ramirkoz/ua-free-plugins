<?php
/**
 * Plugin Name: UA FREE Google Ads Campaign Builder
 * Description: Builds reviewable Google Ad Grants or standard Google Ads campaign packages for Google Ads Editor.
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://uafree.org/
 * Plugin URI: https://uafree.org/ua-free-plugins/#plugin-ua-free-google-ads-campaign-builder
 * Version: 1.3.4
 * Author: UA FREE
 * Text Domain: ua-free-google-ads-campaign-builder
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-ua-free-suite-registry.php';
\UAFree\Suite\Registry::register( array(
	'slug' => 'ua-free-google-ads-campaign-builder',
	'name' => 'UA FREE Google Ads Campaign Builder',
	'version' => '1.3.4',
	'settings_page' => 'ua-free-google-ads-campaign-builder',
) );

define( 'UAFREE_GACB_VERSION', '1.3.4' );
define( 'UAFREE_GACB_FILE', __FILE__ );
define( 'UAFREE_GACB_DIR', plugin_dir_path( __FILE__ ) );

require_once UAFREE_GACB_DIR . 'includes/class-uafree-google-ads-campaign-builder.php';

add_action( 'plugins_loaded', static function (): void {
	load_plugin_textdomain( 'ua-free-google-ads-campaign-builder', false, dirname( plugin_basename( UAFREE_GACB_FILE ) ) . '/languages' );
} );


if ( ! function_exists( 'uafree_google_ads_campaign_builder_deactivate_legacy' ) ) {
	/**
	 * Deactivate the old standalone Ad Grants Builder after this replacement activates.
	 *
	 * The old plugin files and options remain untouched for rollback.
	 */
	function uafree_google_ads_campaign_builder_deactivate_legacy(): void {
		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );
		$installed      = get_plugins();
		$legacy_files   = array();

		foreach ( $active_plugins as $plugin_file ) {
			$plugin_file = (string) $plugin_file;
			$plugin_data = isset( $installed[ $plugin_file ] ) && is_array( $installed[ $plugin_file ] )
				? $installed[ $plugin_file ]
				: array();
			$name = sanitize_text_field( (string) ( $plugin_data['Name'] ?? '' ) );

			if (
				str_starts_with( $plugin_file, 'ua-free-ad-grants-builder/' )
				|| 'UA FREE Ad Grants Builder' === $name
			) {
				$legacy_files[] = $plugin_file;
			}
		}

		if ( ! empty( $legacy_files ) ) {
			deactivate_plugins( $legacy_files, true );
			set_transient(
				'uafree_gacb_legacy_deactivated',
				count( $legacy_files ),
				MINUTE_IN_SECONDS
			);
		}
	}
}

register_activation_hook(
	UAFREE_GACB_FILE,
	'uafree_google_ads_campaign_builder_deactivate_legacy'
);

\UAFree\GoogleAdsCampaignBuilder\Plugin::init();

if ( ! function_exists( 'uafree_google_ads_campaign_builder_get_status' ) ) {
	/**
	 * Return privacy-safe plugin status for local integrations.
	 *
	 * @return array<string,mixed>
	 */
	function uafree_google_ads_campaign_builder_get_status(): array {
		return \UAFree\GoogleAdsCampaignBuilder\Plugin::get_status();
	}
}


if ( ! function_exists( 'uafree_google_ads_campaign_builder_get_manager_summary' ) ) {
	/**
	 * Return the manager-facing local readiness summary.
	 *
	 * @return array<string,mixed>
	 */
	function uafree_google_ads_campaign_builder_get_manager_summary(): array {
		return \UAFree\GoogleAdsCampaignBuilder\Plugin::manager_summary();
	}
}


add_action( 'admin_notices', static function (): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$count = (int) get_transient( 'uafree_gacb_legacy_deactivated' );
	if ( $count <= 0 ) {
		return;
	}
	delete_transient( 'uafree_gacb_legacy_deactivated' );
	?>
	<div class="notice notice-success is-dismissible">
		<p><?php echo esc_html(
			'UA FREE Google Ads Campaign Builder активовано. Старий UA FREE Ad Grants Builder деактивовано без видалення його файлів або налаштувань.'
		); ?></p>
	</div>
	<?php
} );


if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-uafree-admin-support-footer.php';
	\UAFree_Admin_Support_Footer::init();
}
