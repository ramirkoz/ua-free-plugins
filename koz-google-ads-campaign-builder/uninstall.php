<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$kozgads_legacy_settings_key = implode( '_', array( 'uafree', 'agb', 'settings' ) );
$kozgads_legacy_package_key  = implode( '_', array( 'uafree', 'agb', 'last', 'package' ) );
$kozgads_settings = get_option( 'kozgads_settings', false );
if ( ! is_array( $kozgads_settings ) ) {
	$kozgads_settings = get_option( $kozgads_legacy_settings_key, array() );
}

if ( empty( $kozgads_settings['delete_data_on_uninstall'] ) ) {
	return;
}

$kozgads_package = get_option( 'kozgads_last_package', false );
if ( ! is_array( $kozgads_package ) ) {
	$kozgads_package = get_option( $kozgads_legacy_package_key, array() );
}
$kozgads_path   = is_array( $kozgads_package ) ? (string) ( $kozgads_package['path'] ?? '' ) : '';
$kozgads_upload = wp_upload_dir();
$kozgads_base   = empty( $kozgads_upload['error'] ) ? realpath( trailingslashit( (string) $kozgads_upload['basedir'] ) . 'ua-free-google-ads-packages' ) : false;
$kozgads_real   = '' !== $kozgads_path ? realpath( $kozgads_path ) : false;

if ( is_string( $kozgads_base ) && is_string( $kozgads_real ) && str_starts_with( $kozgads_real, trailingslashit( $kozgads_base ) ) && is_file( $kozgads_real ) ) {
	wp_delete_file( $kozgads_real );
}

delete_option( 'kozgads_settings' );
delete_option( 'kozgads_last_package' );
delete_option( $kozgads_legacy_settings_key );
delete_option( $kozgads_legacy_package_key );
