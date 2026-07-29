<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'uafree_bridge_api_key_hash' );
delete_option( 'uafree_bridge_api_key_created_at' );
delete_option( 'uafree_bridge_api_log' );


global $wpdb;
$prefix = $wpdb->esc_like( 'uafree_bridge_rl_' ) . '%';
$rows = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$prefix
	)
);
foreach ( (array) $rows as $option_name ) {
	delete_option( (string) $option_name );
}
