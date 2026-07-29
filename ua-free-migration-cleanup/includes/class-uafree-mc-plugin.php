<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UAFree_MC_Plugin {
	public static function init(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
		UAFree_MC_Admin::init();
	}

	public static function load_textdomain(): void {
		load_plugin_textdomain(
			UAFREE_MC_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( UAFREE_MC_FILE ) ) . '/languages'
		);
	}
}
