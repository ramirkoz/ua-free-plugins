<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

foreach (
	array(
		'uafree_suite_support_settings',
		'uafree_suite_install_id',
		'uafree_suite_heartbeat_token',
		'uafree_suite_heartbeat_token_expiry',
		'uafree_suite_control_center_migration_version',
	)
	as $option
) {
	delete_option( $option );
}

while (
	false !== (
		$timestamp = wp_next_scheduled(
			'uafree_suite_daily_heartbeat'
		)
	)
) {
	if (
		false === wp_unschedule_event(
			$timestamp,
			'uafree_suite_daily_heartbeat'
		)
	) {
		break;
	}
}
