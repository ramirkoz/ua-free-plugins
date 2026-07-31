<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'uafree_bridge_api_key_hash' );
delete_option( 'uafree_bridge_api_key_created_at' );
delete_option( 'uafree_bridge_api_log' );

global $wpdb;

$uafree_bridge_prefix = $wpdb->esc_like( 'uafree_bridge_rl_' ) . '%';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall removes only options with the plugin-specific prefix.
$uafree_bridge_rows = $wpdb->get_col(
	$wpdb->prepare(
		'SELECT option_name FROM %i WHERE option_name LIKE %s',
		$wpdb->options,
		$uafree_bridge_prefix
	)
);

foreach ( (array) $uafree_bridge_rows as $uafree_bridge_option_name ) {
	delete_option( (string) $uafree_bridge_option_name );
}
