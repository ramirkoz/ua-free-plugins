<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

( static function (): void {
	foreach (
		array(
			'kozbridge_api_key_hash',
			'kozbridge_api_key_created_at',
			'kozbridge_api_log',
			'uafree_bridge_api_key_hash',
			'uafree_bridge_api_key_created_at',
			'uafree_bridge_api_log',
			'koz_site_bridge_api_key_hash',
			'koz_site_bridge_api_key_created_at',
			'koz_site_bridge_api_log',
		)
		as $option_name
	) {
		delete_option( $option_name );
	}

	global $wpdb;
	foreach ( array( 'kozbridge_rl_', 'koz_site_bridge_rl_', 'uafree_bridge_rl_' ) as $prefix ) {
		$value_like   = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
		$timeout_like = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall removes only Site Bridge transient options.
		$names = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT option_name FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
				$wpdb->options,
				$value_like,
				$timeout_like
			)
		);
		foreach ( (array) $names as $name ) {
			delete_option( (string) $name );
		}
	}
} )();
