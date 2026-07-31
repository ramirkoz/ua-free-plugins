<?php
/**
 * Conditional data removal.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$uafree_url_spam_settings = get_option( 'uafree_url_only_spam_settings', array() );
if ( ! is_array( $uafree_url_spam_settings ) || empty( $uafree_url_spam_settings['delete_data_on_uninstall'] ) ) {
	return;
}

delete_option( 'uafree_url_only_spam_settings' );
delete_option( 'uafree_url_only_spam_total' );
delete_option( 'uafree_url_only_spam_last' );
