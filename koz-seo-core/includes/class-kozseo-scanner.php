<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZSEO_Scanner {
	public static function providers(): array {
		return array(
			'rank-math' => array(
				'name'        => 'Rank Math SEO',
				'plugin_keys' => array( 'seo-by-rank-math/rank-math.php', 'seo-by-rank-math-pro/rank-math-pro.php' ),
				'meta'        => array( 'rank_math_title', 'rank_math_description', 'rank_math_canonical_url', 'rank_math_robots', 'rank_math_facebook_image', 'rank_math_twitter_image' ),
				'options'     => array( 'rank-math-options-general', 'rank-math-options-titles', 'rank_math_modules' ),
			),
			'yoast' => array(
				'name'        => 'Yoast SEO',
				'plugin_keys' => array( 'wordpress-seo/wp-seo.php', 'wordpress-seo-premium/wp-seo-premium.php' ),
				'meta'        => array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_canonical', '_yoast_wpseo_meta-robots-noindex', '_yoast_wpseo_opengraph-image' ),
				'options'     => array( 'wpseo', 'wpseo_titles', 'wpseo_social' ),
			),
			'aioseo' => array(
				'name'        => 'All in One SEO',
				'plugin_keys' => array( 'all-in-one-seo-pack/all_in_one_seo_pack.php', 'all-in-one-seo-pack-pro/all_in_one_seo_pack.php' ),
				'meta'        => array( '_aioseo_title', '_aioseo_description' ),
				'options'     => array( 'aioseo_options', 'aioseo_options_internal' ),
			),
			'seopress' => array(
				'name'        => 'SEOPress',
				'plugin_keys' => array( 'wp-seopress/seopress.php', 'wp-seopress-pro/seopress-pro.php' ),
				'meta'        => array( '_seopress_titles_title', '_seopress_titles_desc', '_seopress_robots_canonical', '_seopress_robots_index', '_seopress_social_fb_img' ),
				'options'     => array( 'seopress_titles_option_name', 'seopress_social_option_name', 'seopress_advanced_option_name' ),
			),
			'the-seo-framework' => array(
				'name'        => 'The SEO Framework',
				'plugin_keys' => array( 'autodescription/autodescription.php' ),
				'meta'        => array( '_genesis_title', '_genesis_description', '_genesis_canonical_uri', '_social_image_url' ),
				'options'     => array( 'autodescription-site-settings' ),
			),
		);
	}

	private static function installed_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return get_plugins();
	}

	private static function active_plugin_files(): array {
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$network = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
			$active  = array_values( array_unique( array_merge( $active, $network ) ) );
		}
		return $active;
	}

	/**
	 * Lightweight provider inventory. No metadata counts and no full-table scans.
	 */
	public static function inventory(): array {
		$plugins = self::installed_plugins();
		$active  = self::active_plugin_files();
		$result  = array();

		foreach ( self::providers() as $id => $provider ) {
			$installed_files = array_values( array_intersect( array_keys( $plugins ), $provider['plugin_keys'] ) );
			$result[ $id ] = array(
				'id'              => $id,
				'name'            => $provider['name'],
				'installed'       => ! empty( $installed_files ),
				'active'          => (bool) array_intersect( $installed_files, $active ),
				'installed_count' => count( $installed_files ),
			);
		}

		return $result;
	}

	/**
	 * SEO environment scan. Exact metadata counts are explicit deep-mode work.
	 */
	public static function scan( bool $deep = false ): array {
		global $wpdb;
		$inventory = self::inventory();
		$providers = array();
		$meta_counts = array();

		if ( $deep ) {
			$all_meta = array( 'kozseo_title', 'kozseo_description', 'kozseo_canonical', 'uafree_seo_title', 'uafree_seo_description', 'uafree_seo_canonical' );
			foreach ( self::providers() as $provider ) {
				$all_meta = array_merge( $all_meta, $provider['meta'] );
			}
			$all_meta = array_values( array_unique( array_map( 'strval', $all_meta ) ) );
			if ( $all_meta ) {
				foreach ( $all_meta as $meta_key ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit administrator-triggered deep inventory scan.
					$meta_counts[ $meta_key ] = (int) $wpdb->get_var(
						$wpdb->prepare(
							'SELECT COUNT(*) FROM %i WHERE meta_key = %s',
							$wpdb->postmeta,
							$meta_key
						)
					);
				}
			}
		}

		foreach ( self::providers() as $id => $provider ) {
			$option_presence_count = 0;
			foreach ( $provider['options'] as $option_name ) {
				if ( false !== get_option( $option_name, false ) ) {
					$option_presence_count++;
				}
			}
			$provider_meta_total = null;
			if ( $deep ) {
				$provider_meta_total = 0;
				foreach ( $provider['meta'] as $meta_key ) {
					$provider_meta_total += (int) ( $meta_counts[ $meta_key ] ?? 0 );
				}
			}
			$providers[ $id ] = array_merge(
				$inventory[ $id ],
				array(
					'metadata_count'        => $provider_meta_total,
					'metadata_count_exact'  => $deep,
					'option_presence_count' => $option_presence_count,
					'has_detected_data'     => $deep
						? ( $provider_meta_total > 0 || $option_presence_count > 0 )
						: ( $option_presence_count > 0 ? true : null ),
				)
			);
		}

		$own_data = array(
			'titles'       => null,
			'descriptions' => null,
			'canonicals'   => null,
			'exact'        => $deep,
		);
		if ( $deep ) {
			$own_data['titles']       = (int) ( $meta_counts['kozseo_title'] ?? 0 ) + (int) ( $meta_counts['uafree_seo_title'] ?? 0 );
			$own_data['descriptions'] = (int) ( $meta_counts['kozseo_description'] ?? 0 ) + (int) ( $meta_counts['uafree_seo_description'] ?? 0 );
			$own_data['canonicals']   = (int) ( $meta_counts['kozseo_canonical'] ?? 0 ) + (int) ( $meta_counts['uafree_seo_canonical'] ?? 0 );
		}

		return array(
			'generated_at' => gmdate( 'c' ),
			'mode'         => $deep ? 'deep' : 'quick',
			'providers'    => $providers,
			'own_data'     => $own_data,
		);
	}

	public static function conflicting_active_plugin(): bool {
		foreach ( self::inventory() as $provider ) {
			if ( ! empty( $provider['active'] ) ) {
				return true;
			}
		}
		return false;
	}
}
