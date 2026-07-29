<?php
/**
 * UA FREE Suite registry.
 *
 * @package UAFree_URL_Only_Comment_Spam
 */

declare(strict_types=1);

namespace UAFree\URLSpam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Suite_Registry {
	/**
	 * @return array<string,array{name:string,plugin_file:string,description:string}>
	 */
	public static function components(): array {
		return array(
			'static-translate' => array(
				'name'        => 'UA FREE Static Translate',
				'plugin_file' => 'ua-free-static-translate/ua-free-static-translate.php',
				'description' => __( 'Static multilingual publishing with translation memory.', 'ua-free-url-only-comment-spam' ),
			),
			'translate-diagnostics' => array(
				'name'        => 'UA FREE Translate Diagnostics',
				'plugin_file' => 'ua-free-translate-diagnostics/ua-free-translate-diagnostics.php',
				'description' => __( 'Read-only diagnostics for translation data and queues.', 'ua-free-url-only-comment-spam' ),
			),
			'seo-core' => array(
				'name'        => 'UA FREE SEO Core',
				'plugin_file' => 'ua-free-seo-core/ua-free-seo-core.php',
				'description' => __( 'Lightweight SEO, schema, sitemap and discovery metadata.', 'ua-free-url-only-comment-spam' ),
			),
			'404-guard' => array(
				'name'        => 'UA FREE 404 Guard & URL Intelligence',
				'plugin_file' => 'ua-free-404-guard/ua-free-404-guard.php',
				'description' => __( 'Privacy-safe 404 logging, redirects and URL diagnostics.', 'ua-free-url-only-comment-spam' ),
			),
			'migration-cleanup' => array(
				'name'        => 'UA FREE Migration & Cleanup',
				'plugin_file' => 'ua-free-migration-cleanup/ua-free-migration-cleanup.php',
				'description' => __( 'Controlled discovery, snapshots and cleanup workflows.', 'ua-free-url-only-comment-spam' ),
			),
			'site-bridge' => array(
				'name'        => 'UA FREE Site Bridge',
				'plugin_file' => 'uafree-site-bridge/uafree-site-bridge.php',
				'description' => __( 'Restricted read-only site diagnostics API.', 'ua-free-url-only-comment-spam' ),
			),
			'ads-builder' => array(
				'name'        => 'UA FREE Google Ads Campaign Builder',
				'plugin_file' => 'ua-free-google-ads-campaign-builder/ua-free-google-ads-campaign-builder.php',
				'description' => __( 'Campaign packages for Google Ad Grants and standard Google Ads.', 'ua-free-url-only-comment-spam' ),
			),
			'donate-stats' => array(
				'name'        => 'UA FREE Donate Stats & Conversions',
				'plugin_file' => 'ua-free-donate-stats/ua-free-donate-stats.php',
				'description' => __( 'Privacy-first donation interaction statistics.', 'ua-free-url-only-comment-spam' ),
			),
			'copy' => array(
				'name'        => 'UA FREE Copy',
				'plugin_file' => 'ua-free-copy/ua-free-copy.php',
				'description' => __( 'Accessible clipboard actions without tracking copied values.', 'ua-free-url-only-comment-spam' ),
			),
			'url-spam' => array(
				'name'        => 'UA FREE URL-Only Comment Spam',
				'plugin_file' => 'ua-free-url-only-comment-spam/ua-free-url-only-comment-spam.php',
				'description' => __( 'Privacy-first filtering of URL-only comments.', 'ua-free-url-only-comment-spam' ),
			),
			'analytics' => array(
				'name'        => 'UA FREE Analytics Dashboard',
				'plugin_file' => 'ua-free-analytics-dashboard/ua-free-analytics-dashboard.php',
				'description' => __( 'A unified local analytics view for Suite events.', 'ua-free-url-only-comment-spam' ),
			),
			'consent' => array(
				'name'        => 'UA FREE Consent Manager',
				'plugin_file' => 'ua-free-consent-manager/ua-free-consent-manager.php',
				'description' => __( 'Consent controls for optional analytics integrations.', 'ua-free-url-only-comment-spam' ),
			),
		);
	}

	/**
	 * @return array<string,array{name:string,description:string,status:string}>
	 */
	public static function statuses(): array {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$all_plugins    = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$result         = array();

		foreach ( self::components() as $key => $component ) {
			$file   = $component['plugin_file'];
			$status = in_array( $file, $active_plugins, true ) ? 'active' : ( isset( $all_plugins[ $file ] ) ? 'installed' : 'available' );
			$result[ $key ] = array(
				'name'        => $component['name'],
				'description' => $component['description'],
				'status'      => $status,
			);
		}

		return $result;
	}
}
