<?php
/**
 * KOZ Suite Control Center uninstall cleanup.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'kozsuitecc_migration_version' );
delete_option( 'kozsuitecontrolcenter_migration_version' );
