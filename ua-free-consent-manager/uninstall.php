<?php
/**
 * Remove only UA FREE Consent Manager settings.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'uafree_consent_manager_settings' );
