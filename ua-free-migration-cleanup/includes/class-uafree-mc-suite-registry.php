<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UAFree_MC_Suite_Registry {
	public static function manifest(): array {
		$plugins = array(
			array(
				'slug' => 'ua-free-static-translate',
				'name' => 'UA FREE Static Translate',
				'description' => __( 'Automatic multilingual translation with migration tools for previous translation solutions.', 'ua-free-migration-cleanup' ),
			),
			array(
				'slug' => 'ua-free-seo-core',
				'name' => 'UA FREE SEO Core',
				'description' => __( 'Standalone SEO, sitemap, structured data, AI discovery and accessibility checks.', 'ua-free-migration-cleanup' ),
			),
			array(
				'slug' => 'ua-free-404-guard',
				'name' => 'UA FREE 404 Guard & URL Intelligence',
				'description' => __( '404 intelligence, bot filtering and controlled redirect recommendations.', 'ua-free-migration-cleanup' ),
			),
			array(
				'slug' => 'ua-free-migration-cleanup',
				'name' => 'UA FREE Migration & Cleanup',
				'description' => __( 'Environment inventory, snapshots, migration and controlled cleanup.', 'ua-free-migration-cleanup' ),
			),
			array(
				'slug' => 'ua-free-site-bridge',
				'name' => 'UA FREE Site Bridge',
				'description' => __( 'Protected read-only diagnostics API for authorized internal audits.', 'ua-free-migration-cleanup' ),
			),
			array(
				'slug' => 'ua-free-google-ads-campaign-builder',
				'name' => 'UA FREE Google Ads Campaign Builder',
				'description' => __( 'Google Ad Grants package generation and landing page health checks.', 'ua-free-migration-cleanup' ),
			),
			array(
				'slug' => 'ua-free-donate-stats',
				'name' => 'UA FREE Donate Stats & Conversions',
				'description' => __( 'Privacy-safe local donation page and conversion statistics.', 'ua-free-migration-cleanup' ),
			),
			array(
				'slug' => 'ua-free-copy',
				'name' => 'UA FREE Copy',
				'description' => __( 'Lightweight accessible copy-to-clipboard actions for configured values.', 'ua-free-migration-cleanup' ),
			),
			array(
				'slug' => 'ua-free-translate-diagnostics',
				'name' => 'UA FREE Translate Diagnostics',
				'description' => __( 'Read-only diagnostics for UA FREE Static Translate.', 'ua-free-migration-cleanup' ),
			),
			array(
				'slug' => 'ua-free-url-only-comment-spam',
				'name' => 'UA FREE URL-Only Comment Spam',
				'description' => __( 'Minimal URL-only comment spam filter with an optional allowlist.', 'ua-free-migration-cleanup' ),
			),
			array(
				'slug' => 'ua-free-analytics-dashboard',
				'name' => 'UA FREE Suite Control Center',
				'description' => __( 'Unified local dashboard for installed UA FREE plugins.', 'ua-free-migration-cleanup' ),
			),
			array(
				'slug' => 'ua-free-consent-manager',
				'name' => 'UA FREE Consent Manager',
				'description' => __( 'Bilingual privacy and consent controls for optional external services.', 'ua-free-migration-cleanup' ),
			),
		);

		return apply_filters( 'uafree_suite_manifest', $plugins );
	}

	public static function status(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed = get_plugins();
		$rows = array();

		foreach ( self::manifest() as $item ) {
			$match_file = '';
			$version = '';
			foreach ( $installed as $file => $data ) {
				$directory = dirname( $file );
				if ( $item['slug'] === $directory || str_starts_with( $file, $item['slug'] . '/' ) ) {
					$match_file = $file;
					$version = isset( $data['Version'] ) ? (string) $data['Version'] : '';
					break;
				}
			}

			$status = 'available';
			if ( '' !== $match_file ) {
				$status = is_plugin_active( $match_file ) ? 'active' : 'inactive';
				if ( is_multisite() && is_plugin_active_for_network( $match_file ) ) {
					$status = 'network-active';
				}
			}

			$item['status'] = $status;
			$item['file'] = $match_file;
			$item['version'] = $version;
			$rows[] = $item;
		}

		return $rows;
	}
}
