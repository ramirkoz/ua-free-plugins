<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UAFree_MC_Plugin {
	public static function init(): void {
		UAFree_MC_Admin::init();
	}
}
