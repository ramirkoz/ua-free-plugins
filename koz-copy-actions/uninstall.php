<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'kozcoac_copy_settings' );

// Remove only this plugin's historical setting key.
$kozcoac_legacy_option = implode( '_', array( 'uafree', 'copy', 'settings' ) );
delete_option( $kozcoac_legacy_option );
