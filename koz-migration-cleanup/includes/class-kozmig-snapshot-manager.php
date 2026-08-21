<?php
namespace ramirkz\kozmig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZMIG_Snapshot_Manager {
	public static function init(): void {
		add_action( 'admin_post_kozmig_export_environment', array( __CLASS__, 'export_environment' ) );
		add_action( 'admin_post_kozmig_export_plugin', array( __CLASS__, 'export_plugin' ) );
		add_action( 'admin_post_kozmig_rescan', array( __CLASS__, 'rescan' ) );
	}

	public static function export_environment(): void {
		self::guard( 'kozmig_export_environment' );
		self::download_json(
			'kozmig-environment-' . gmdate( 'Ymd-His' ) . '.json',
			KOZMIG_Environment_Scanner::environment( true )
		);
	}

	public static function export_plugin(): void {
		self::guard( 'kozmig_export_plugin' );
		$plugin_file_input = filter_input( INPUT_GET, 'plugin_file', FILTER_UNSAFE_RAW );
		$plugin_file = is_string( $plugin_file_input )
			? sanitize_text_field( $plugin_file_input )
			: '';
		$report = KOZMIG_Environment_Scanner::inspect_plugin( $plugin_file );
		if ( is_wp_error( $report ) ) {
			wp_die( esc_html( $report->get_error_message() ) );
		}
		$slug = sanitize_file_name( dirname( $plugin_file ) );
		self::download_json(
			'kozmig-plugin-inspection-' . $slug . '-' . gmdate( 'Ymd-His' ) . '.json',
			$report
		);
	}

	public static function rescan(): void {
		self::guard( 'kozmig_rescan' );
		KOZMIG_Environment_Scanner::clear_cache();
		wp_safe_redirect( admin_url( 'admin.php?page=' . KOZMIG_Admin::PAGE_SLUG . '&rescanned=1' ) );
		exit;
	}

	private static function guard( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'koz-migration-cleanup' ) );
		}
		check_admin_referer( $action );
	}

	private static function download_json( string $filename, array $payload ): void {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}
}
