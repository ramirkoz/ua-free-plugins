<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
$uafree_gacb_settings = get_option( 'uafree_agb_settings', array() );
if ( empty( $uafree_gacb_settings['delete_data_on_uninstall'] ) ) {
	return;
}
$uafree_gacb_package = get_option( 'uafree_agb_last_package', array() );
$uafree_gacb_path    = is_array( $uafree_gacb_package ) ? (string) ( $uafree_gacb_package['path'] ?? '' ) : '';
$uafree_gacb_upload  = wp_upload_dir();
$uafree_gacb_base    = empty( $uafree_gacb_upload['error'] ) ? realpath( trailingslashit( (string) $uafree_gacb_upload['basedir'] ) . 'ua-free-google-ads-packages' ) : false;
$uafree_gacb_real    = '' !== $uafree_gacb_path ? realpath( $uafree_gacb_path ) : false;
if ( is_string( $uafree_gacb_base ) && is_string( $uafree_gacb_real ) && str_starts_with( $uafree_gacb_real, trailingslashit( $uafree_gacb_base ) ) && is_file( $uafree_gacb_real ) ) {
	wp_delete_file( $uafree_gacb_real );
}
delete_option( 'uafree_agb_settings' );
delete_option( 'uafree_agb_last_package' );
