<?php
/**
 * Conditional data removal.
 *
 * @package KOZ_URL_Only_Comment_Spam
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

( static function (): void {
	$settings_option = 'kozurlspam_settings';
	$total_option    = 'kozurlspam_total';
	$last_option     = 'kozurlspam_last';

	$settings = get_option( $settings_option, array() );
	if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	delete_option( $settings_option );
	delete_option( $total_option );
	delete_option( $last_option );

	foreach ( array( 'settings', 'total', 'last' ) as $suffix ) {
		delete_option( implode( '_', array( 'uafree', 'url', 'only', 'spam', $suffix ) ) );
	}
} )();
