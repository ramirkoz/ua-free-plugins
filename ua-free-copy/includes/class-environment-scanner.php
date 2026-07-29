<?php
namespace UAFree\CopyTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Environment_Scanner {
	/**
	 * Find other installed plugins whose public headers indicate clipboard or copy functionality.
	 * No foreign options or stored values are read.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function copy_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed = get_plugins();
		$active    = (array) get_option( 'active_plugins', array() );
		$self      = plugin_basename( UAFREE_COPY_FILE );
		$result    = array();

		foreach ( $installed as $file => $headers ) {
			if ( $file === $self ) {
				continue;
			}
			$name        = (string) ( $headers['Name'] ?? '' );
			$description = (string) ( $headers['Description'] ?? '' );
			$haystack    = strtolower( wp_strip_all_tags( $name . ' ' . $description ) );
			if ( ! preg_match( '/(?:clipboard|copy to|copy button|копію|буфер)/u', $haystack ) ) {
				continue;
			}
			$result[] = array(
				'name'    => $name,
				'version' => (string) ( $headers['Version'] ?? '' ),
				'active'  => in_array( $file, $active, true ) || ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $file ) ),
			);
		}
		return $result;
	}
}
