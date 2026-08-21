<?php
namespace ramirkz\kozdonate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZDONATE_Suite {
	/**
	 * @return array<string,array<string,string>>
	 */
	public static function manifest(): array {
		return array(
			'koz-static-translate' => array( 'name' => 'KOZ Static Translate', 'legacy' => array( 'ua-free-static-translate' ), 'description' => __( 'Automated multilingual publishing with translation memory and controlled cleanup.', 'koz-donate-stats' ) ),
			'koz-seo-core' => array( 'name' => 'KOZ SEO Core', 'legacy' => array( 'ua-free-seo-core' ), 'description' => __( 'Standalone SEO, sitemap, schema, AI discovery and accessibility checks.', 'koz-donate-stats' ) ),
			'koz-404-guard' => array( 'name' => 'KOZ 404 Guard & URL Intelligence', 'legacy' => array( 'ua-free-404-guard' ), 'description' => __( 'Privacy-conscious 404/410 logging, redirects and URL diagnostics.', 'koz-donate-stats' ) ),
			'koz-migration-cleanup' => array( 'name' => 'KOZ Migration & Cleanup', 'legacy' => array( 'ua-free-migration-cleanup' ), 'description' => __( 'Environment inventory, snapshots, dry-run migration and controlled cleanup.', 'koz-donate-stats' ) ),
			'koz-site-bridge' => array( 'name' => 'KOZ Site Bridge', 'legacy' => array( 'ua-free-site-bridge' ), 'description' => __( 'Authenticated read-only diagnostics for authorized clients and AI actions.', 'koz-donate-stats' ) ),
			'koz-google-ads-campaign-builder' => array( 'name' => 'KOZ Google Ads Campaign Builder', 'legacy' => array( 'ua-free-google-ads-campaign-builder', 'uafree-ad-grants-builder' ), 'description' => __( 'Campaign planning and exports for Google Ad Grants or standard Google Ads.', 'koz-donate-stats' ) ),
			'koz-donate-stats' => array( 'name' => 'KOZ Donate Stats & Conversions', 'legacy' => array( 'ua-free-donate-stats' ), 'description' => __( 'Local privacy-safe donation journey events and conversion reporting.', 'koz-donate-stats' ) ),
			'koz-copy-actions' => array( 'name' => 'KOZ Copy Actions', 'legacy' => array( 'ua-free-copy' ), 'description' => __( 'Accessible copy-to-clipboard actions for administrator-selected values.', 'koz-donate-stats' ) ),
			'koz-translate-diagnostics' => array( 'name' => 'KOZ Translate Diagnostics', 'legacy' => array( 'ua-free-translate-diagnostics' ), 'description' => __( 'Read-only translation queue, memory, limits, cron and error diagnostics.', 'koz-donate-stats' ) ),
			'koz-url-only-comment-spam' => array( 'name' => 'KOZ URL-Only Comment Spam', 'legacy' => array( 'ua-free-url-only-comment-spam' ), 'description' => __( 'A small rule that sends URL-only comments to spam.', 'koz-donate-stats' ) ),
			'koz-suite-control-center' => array( 'name' => 'KOZ Suite Control Center', 'legacy' => array( 'ua-free-analytics-dashboard', 'ua-free-suite-control-center' ), 'description' => __( 'A unified local dashboard for available KOZ plugin data.', 'koz-donate-stats' ) ),
			'koz-consent-manager' => array( 'name' => 'KOZ Consent Manager', 'legacy' => array( 'ua-free-consent-manager' ), 'description' => __( 'Consent categories, script gating and Google Consent Mode integration.', 'koz-donate-stats' ) ),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function status(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed = get_plugins();
		$active = (array) get_option( 'active_plugins', array() );
		$network = is_multisite()
			? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) )
			: array();
		$result = array();

		foreach ( self::manifest() as $slug => $item ) {
			$plugin_file = '';
			$version = '';
			$prefixes = array_merge( array( $slug ), (array) ( $item['legacy'] ?? array() ) );

			foreach ( $installed as $file => $data ) {
				$matched = false;
				foreach ( $prefixes as $prefix ) {
					if ( 0 === strpos( (string) $file, (string) $prefix . '/' ) || $file === $prefix . '.php' ) {
						$matched = true;
						break;
					}
				}
				if ( $matched ) {
					$plugin_file = $file;
					$version = isset( $data['Version'] ) ? (string) $data['Version'] : '';
					break;
				}
			}

			$is_active = $plugin_file
				&& ( in_array( $plugin_file, $active, true ) || in_array( $plugin_file, $network, true ) );

			$result[] = array(
				'slug'        => $slug,
				'name'        => $item['name'],
				'description' => $item['description'],
				'installed'   => (bool) $plugin_file,
				'active'      => (bool) $is_active,
				'version'     => $version,
				'url'         => 'https://wordpress.org/plugins/' . rawurlencode( $slug ) . '/',
			);
		}
		return $result;
	}
}
