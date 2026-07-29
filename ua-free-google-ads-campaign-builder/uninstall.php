<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
$settings = get_option( 'uafree_agb_settings', array() );
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}
$package = get_option( 'uafree_agb_last_package', array() );
$path    = is_array( $package ) ? (string) ( $package['path'] ?? '' ) : '';
$upload  = wp_upload_dir();
$base    = empty( $upload['error'] ) ? realpath( trailingslashit( (string) $upload['basedir'] ) . 'ua-free-google-ads-packages' ) : false;
$real    = '' !== $path ? realpath( $path ) : false;
if ( is_string( $base ) && is_string( $real ) && str_starts_with( $real, trailingslashit( $base ) ) && is_file( $real ) ) {
	wp_delete_file( $real );
}
delete_option( 'uafree_agb_settings' );
delete_option( 'uafree_agb_last_package' );
