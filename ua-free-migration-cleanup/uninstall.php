<?php
/**
 * UA FREE Migration & Cleanup intentionally preserves data on uninstall.
 *
 * Snapshots and diagnostic records may be important for recovery. They must
 * be removed only through a separate, explicit administrator-controlled tool.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
