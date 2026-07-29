<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UAFree_MC_Snapshot_Manager {
	public static function init(): void {
		add_action( 'admin_post_uafree_mc_export_environment', array( __CLASS__, 'export_environment' ) );
		add_action( 'admin_post_uafree_mc_export_plugin', array( __CLASS__, 'export_plugin' ) );
		add_action( 'admin_post_uafree_mc_rescan', array( __CLASS__, 'rescan' ) );
	}

	public static function export_environment(): void {
		self::guard( 'uafree_mc_export_environment' );
		self::download_json(
			'uafree-environment-' . gmdate( 'Ymd-His' ) . '.json',
			UAFree_MC_Environment_Scanner::environment( true )
		);
	}

	public static function export_plugin(): void {
		self::guard( 'uafree_mc_export_plugin' );
		$plugin_file = isset( $_GET['plugin_file'] )
			? sanitize_text_field( wp_unslash( $_GET['plugin_file'] ) )
			: '';
		$report = UAFree_MC_Environment_Scanner::inspect_plugin( $plugin_file );
		if ( is_wp_error( $report ) ) {
			wp_die( esc_html( $report->get_error_message() ) );
		}
		$slug = sanitize_file_name( dirname( $plugin_file ) );
		self::download_json(
			'uafree-plugin-inspection-' . $slug . '-' . gmdate( 'Ymd-His' ) . '.json',
			$report
		);
	}

	public static function rescan(): void {
		self::guard( 'uafree_mc_rescan' );
		UAFree_MC_Environment_Scanner::clear_cache();
		wp_safe_redirect( admin_url( 'admin.php?page=' . UAFree_MC_Admin::PAGE_SLUG . '&rescanned=1' ) );
		exit;
	}

	private static function guard( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', UAFREE_MC_TEXT_DOMAIN ) );
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
