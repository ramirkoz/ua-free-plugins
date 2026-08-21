<?php
namespace ramirkz\kozmig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZMIG_Plugin {
	public static function init(): void {
		KOZMIG_Admin::init();
	}

	public static function activate(): void {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$legacy_files = array();
		foreach ( (array) get_option( 'active_plugins', array() ) as $plugin_file ) {
			$plugin_file = (string) $plugin_file;
			if ( str_starts_with( $plugin_file, 'ua-free-migration-cleanup/' ) && plugin_basename( KOZMIG_FILE ) !== $plugin_file ) {
				$legacy_files[] = $plugin_file;
			}
		}

		if ( ! empty( $legacy_files ) ) {
			deactivate_plugins( $legacy_files, true );
			delete_transient( 'uafree_mc_environment_scan_v091' );
		delete_transient( 'uafree_mc_legacy_deactivated' );

		set_transient( 'kozmig_legacy_deactivated', count( $legacy_files ), MINUTE_IN_SECONDS );
		}
	}
}
