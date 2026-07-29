<?php
/**
 * Conditional data removal.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'uafree_url_only_spam_settings', array() );
if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

delete_option( 'uafree_url_only_spam_settings' );
delete_option( 'uafree_url_only_spam_total' );
delete_option( 'uafree_url_only_spam_last' );
