<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Aggregate statistics and settings are intentionally preserved.
 * Administrators can explicitly truncate the statistics tables from the
 * plugin screen before uninstalling.
 */
