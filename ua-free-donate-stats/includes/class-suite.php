<?php
namespace UAFree\DonateStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Suite {
	/**
	 * @return array<string,array<string,string>>
	 */
	public static function manifest(): array {
		return array(
			'uafree-static-translate' => array(
				'name' => 'UA FREE Static Translate',
				'description' => __( 'Automated multilingual publishing with translation memory and controlled cleanup.', 'ua-free-donate-stats' ),
			),
			'uafree-seo-core' => array(
				'name' => 'UA FREE SEO Core',
				'description' => __( 'Standalone SEO, sitemap, schema, AI discovery and accessibility checks.', 'ua-free-donate-stats' ),
			),
			'uafree-404-guard' => array(
				'name' => 'UA FREE 404 Guard & URL Intelligence',
				'description' => __( 'Privacy-conscious 404/410 logging, redirects and URL diagnostics.', 'ua-free-donate-stats' ),
			),
			'uafree-migration-cleanup' => array(
				'name' => 'UA FREE Migration & Cleanup',
				'description' => __( 'Environment inventory, snapshots, dry-run migration and controlled cleanup.', 'ua-free-donate-stats' ),
			),
			'uafree-site-bridge' => array(
				'name' => 'UA FREE Site Bridge',
				'description' => __( 'Authenticated read-only diagnostics for authorized clients and AI actions.', 'ua-free-donate-stats' ),
			),
			'uafree-ad-grants-builder' => array(
				'name' => 'UA FREE Google Ads Campaign Builder',
				'description' => __( 'Campaign planning and exports for Google Ad Grants or standard Google Ads.', 'ua-free-donate-stats' ),
			),
			'ua-free-donate-stats' => array(
				'name' => 'UA FREE Donate Stats & Conversions',
				'description' => __( 'Local privacy-safe donation journey events and conversion reporting.', 'ua-free-donate-stats' ),
			),
			'uafree-copy' => array(
				'name' => 'UA FREE Copy',
				'description' => __( 'Accessible copy-to-clipboard actions for administrator-selected values.', 'ua-free-donate-stats' ),
			),
			'uafree-translate-diagnostics' => array(
				'name' => 'UA FREE Translate Diagnostics',
				'description' => __( 'Read-only translation queue, memory, limits, cron and error diagnostics.', 'ua-free-donate-stats' ),
			),
			'uafree-url-only-comment-spam' => array(
				'name' => 'UA FREE URL-Only Comment Spam',
				'description' => __( 'A small rule that sends URL-only comments to spam.', 'ua-free-donate-stats' ),
			),
			'uafree-analytics-dashboard' => array(
				'name' => 'UA FREE Analytics Dashboard',
				'description' => __( 'A unified local dashboard for available UA FREE plugin data.', 'ua-free-donate-stats' ),
			),
			'uafree-consent-manager' => array(
				'name' => 'UA FREE Consent Manager',
				'description' => __( 'Consent categories, script gating and Google Consent Mode integration.', 'ua-free-donate-stats' ),
			),
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

			foreach ( $installed as $file => $data ) {
				if (
					0 === strpos( $file, $slug . '/' )
					|| $file === $slug . '.php'
					|| ( 'uafree-ad-grants-builder' === $slug && str_contains( $file, 'ad-grants-builder' ) )
				) {
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
