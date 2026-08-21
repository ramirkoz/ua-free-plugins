<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'kozconsent_settings' );
$kozconsent_legacy_option = implode( '_', array( 'ua', 'free', 'consent', 'manager', 'settings' ) );
delete_option( $kozconsent_legacy_option );
