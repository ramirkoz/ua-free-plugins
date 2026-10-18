<?php
namespace ramirkz\koz404;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZ404_Environment_Scanner {
	private const PAGE_BUILDERS = array(
		'pagelayer/pagelayer.php'                    => 'PageLayer',
		'elementor/elementor.php'                    => 'Elementor',
		'brizy/brizy.php'                            => 'Brizy',
		'beaver-builder-lite-version/fl-builder.php' => 'Beaver Builder',
		'js_composer/js_composer.php'                => 'WPBakery Page Builder',
		'divi-builder/divi-builder.php'              => 'Divi Builder',
	);

	private const URL_PLUGINS = array(
		'redirection/redirection.php'                        => 'Redirection',
		'404-solution/404-solution.php'                      => '404 Solution',
		'safe-redirect-manager/safe-redirect-manager.php'   => 'Safe Redirect Manager',
		'rank-math/rank-math.php'                            => 'Rank Math',
		'wordpress-seo/wp-seo.php'                           => 'Yoast SEO',
	);

	public static function scan(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed = get_plugins();
		$active    = (array) get_option( 'active_plugins', array() );
		$network   = is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array();
		$active    = array_values( array_unique( array_merge( $active, $network ) ) );

		return array(
			'page_builders' => self::detect( self::PAGE_BUILDERS, $installed, $active ),
			'url_plugins'   => self::detect( self::URL_PLUGINS, $installed, $active ),
			'suggestions'   => self::suggestions( $installed ),
		);
	}

	public static function privacy_summary(): array {
		$scan = self::scan();
		return array(
			'page_builders_installed' => self::count_flag( $scan['page_builders'], 'installed' ),
			'page_builders_active'    => self::count_flag( $scan['page_builders'], 'active' ),
			'url_plugins_installed'   => self::count_flag( $scan['url_plugins'], 'installed' ),
			'url_plugins_active'      => self::count_flag( $scan['url_plugins'], 'active' ),
			'suggestion_count'        => count( $scan['suggestions'] ),
		);
	}

	private static function count_flag( array $items, string $key ): int {
		$count = 0;
		foreach ( $items as $item ) {
			if ( is_array( $item ) && ! empty( $item[ $key ] ) ) {
				$count++;
			}
		}
		return $count;
	}

	private static function detect( array $catalogue, array $installed, array $active ): array {
		$result = array();
		foreach ( $catalogue as $file => $name ) {
			$result[] = array(
				'name'      => $name,
				'installed' => isset( $installed[ $file ] ),
				'active'    => isset( $installed[ $file ] ) && in_array( $file, $active, true ),
			);
		}
		return $result;
	}

	private static function suggestions( array $installed ): array {
		$suggestions = array();
		if ( isset( $installed['pagelayer/pagelayer.php'] ) ) {
			$suggestions[] = array(
				'provider' => 'PageLayer',
				'type'     => 'query_key',
				'value'    => 'pagelayer-template',
				'note'     => __( 'A service query key was detected. Review it before creating a 410 rule.', 'koz-404-guard' ),
			);
			$suggestions[] = array(
				'provider' => 'PageLayer',
				'type'     => 'query_pair',
				'value'    => 'post_type=pagelayer-template',
				'note'     => __( 'A service query pair was detected. Review it before creating a 410 rule.', 'koz-404-guard' ),
			);
		}
		return $suggestions;
	}

	public static function internal_link_audit( int $limit = 100, bool $include_items = true ): array {
		$limit      = max( 1, min( 200, $limit ) );
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );
		$posts = get_posts(
			array(
				'post_type'      => array_values( $post_types ),
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$home     = wp_parse_url( home_url( '/' ) );
		$home_host = strtolower( (string) ( is_array( $home ) ? ( $home['host'] ?? '' ) : '' ) );
		$home_port = is_array( $home ) && isset( $home['port'] ) ? (int) $home['port'] : ( ( is_array( $home ) && 'https' === strtolower( (string) ( $home['scheme'] ?? '' ) ) ) ? 443 : 80 );
		$items    = array();
		$seen     = array();
		$links_checked = 0;
		$truncated = 0;
		$total_bytes = 0;
		$budget_exhausted = false;

		$posts_checked = 0;
		foreach ( $posts as $post_id_raw ) {
			$post_id = absint( $post_id_raw );
			if ( 0 === $post_id ) {
				continue;
			}
			$content = get_post_field( 'post_content', $post_id, 'raw' );
			$content = is_string( $content ) ? $content : '';
			$posts_checked++;
			if ( $total_bytes >= 20000000 ) {
				$budget_exhausted = true;
				break;
			}
			if ( strlen( $content ) > 500000 ) {
				$content = substr( $content, 0, 500000 );
				$truncated++;
			}
			$total_bytes += strlen( $content );
			foreach ( self::extract_links( $content ) as $href ) {
				if ( $links_checked >= 1000 ) {
					$budget_exhausted = true;
					break 2;
				}
				$links_checked++;
				$path = self::local_candidate_path( $href, $home_host, $home_port );
				if ( '' === $path || KOZ404_Guard::is_protected_path( $path ) || preg_match( '/\.[a-z0-9]{2,8}$/i', $path ) ) {
					continue;
				}
				$key = $post_id . '|' . $path;
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$status = self::route_status( $path );
				if ( 'ok' === $status ) {
					continue;
				}
				$item = array(
					'path_fingerprint' => KOZ404_Guard::fingerprint( $path, 'internal-link' ),
					'status'           => $status,
					'log_hits'         => KOZ404_Log::hits_for_path( $path ),
				);
				if ( $include_items ) {
					$item['post_id']    = $post_id;
					$item['post_title'] = $post_id > 0 ? get_the_title( $post_id ) : '';
					$item['path']       = $path;
					$item['url']        = home_url( $path );
				}
				$items[] = $item;
				if ( count( $items ) >= 200 ) {
					break 2;
				}
			}
		}

		return array(
			'performed'               => true,
			'posts_checked'           => $posts_checked,
			'links_checked'           => $links_checked,
			'content_truncated_count' => $truncated,
			'content_bytes_checked'    => $total_bytes,
			'budget_exhausted'         => $budget_exhausted,
			'candidate_count'         => count( $items ),
			'items'                   => $include_items ? $items : array(),
			'candidate_fingerprints'  => $include_items ? array() : array_column( $items, 'path_fingerprint' ),
		);
	}

	/**
	 * What happens to an internal path: 'ok' (content, archive or translated route),
	 * 'redirect' or 'gone' (a rule handles it) or 'missing'.
	 */
	public static function route_status( string $path, bool $nested = false ): string {
		$rule = KOZ404_Rules::describe_path( $path );
		if ( '' !== $rule ) {
			return $rule;
		}
		$trimmed = trim( $path, '/' );
		if ( '' === $trimmed ) {
			return 'ok';
		}
		$segments = explode( '/', $trimmed );
		if ( ! $nested && in_array( strtolower( $segments[0] ), KOZ404_Request::translator_languages(), true ) ) {
			return self::route_status( '/' . implode( '/', array_slice( $segments, 1 ) ), true );
		}
		if ( preg_match( '#^(.*?)/(?:page/\d+|feed(?:/[a-z0-9]+)?|amp)$#', '/' . $trimmed, $match ) ) {
			return '' === $match[1] ? 'ok' : self::route_status( $match[1], true );
		}
		if ( preg_match( '#^\d{4}(?:/\d{2}(?:/\d{2})?)?$#', $trimmed ) || 'feed' === $trimmed ) {
			return 'ok';
		}
		if ( 0 !== url_to_postid( home_url( user_trailingslashit( $path ) ) ) ) {
			return 'ok';
		}
		$last = sanitize_title( (string) end( $segments ) );
		if ( count( $segments ) >= 2 ) {
			$bases = array(
				'category' => '' !== (string) get_option( 'category_base' ) ? trim( (string) get_option( 'category_base' ), '/' ) : 'category',
				'post_tag' => '' !== (string) get_option( 'tag_base' ) ? trim( (string) get_option( 'tag_base' ), '/' ) : 'tag',
			);
			foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
				if ( ! isset( $bases[ $taxonomy->name ] ) && is_array( $taxonomy->rewrite ) && ! empty( $taxonomy->rewrite['slug'] ) ) {
					$bases[ $taxonomy->name ] = trim( (string) $taxonomy->rewrite['slug'], '/' );
				}
			}
			foreach ( $bases as $taxonomy => $base ) {
				if ( $base === $segments[0] && term_exists( $last, $taxonomy ) ) {
					return 'ok';
				}
			}
			if ( 'author' === $segments[0] && false !== get_user_by( 'slug', $last ) ) {
				return 'ok';
			}
		}
		foreach ( get_post_types( array( 'public' => true, 'has_archive' => true ) ) as $post_type ) {
			$archive = get_post_type_archive_link( $post_type );
			if ( is_string( $archive ) && KOZ404_Guard::normalise_path( (string) wp_parse_url( $archive, PHP_URL_PATH ) ) === $path ) {
				return 'ok';
			}
		}
		return 'missing';
	}

	public static function internal_link_not_performed(): array {
		return array(
			'performed'               => false,
			'posts_checked'           => 0,
			'links_checked'           => 0,
			'content_truncated_count' => 0,
			'content_bytes_checked'    => 0,
			'budget_exhausted'         => false,
			'candidate_count'         => null,
			'items'                   => array(),
			'candidate_fingerprints'  => array(),
		);
	}

	private static function extract_links( string $content ): array {
		$result = array();
		if ( class_exists( '\\WP_HTML_Tag_Processor' ) ) {
			$processor = new \WP_HTML_Tag_Processor( $content );
			while ( $processor->next_tag( 'A' ) ) {
				$href = $processor->get_attribute( 'href' );
				if ( is_string( $href ) ) {
					$result[] = $href;
				}
			}
			return $result;
		}
		if ( preg_match_all( '/<a\b[^>]*\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$result[] = (string) ( $match[1] ?? $match[2] ?? $match[3] ?? '' );
			}
		}
		return $result;
	}

	private static function local_candidate_path( string $href, string $home_host, int $home_port ): string {
		$href = html_entity_decode( trim( $href ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( '' === $href || str_starts_with( $href, '#' ) || preg_match( '#^(mailto:|tel:|javascript:|data:)#i', $href ) ) {
			return '';
		}
		$parsed = wp_parse_url( $href );
		if ( false === $parsed ) {
			return '';
		}
		if ( is_array( $parsed ) && isset( $parsed['host'] ) ) {
			$host = strtolower( (string) $parsed['host'] );
			$port = isset( $parsed['port'] ) ? (int) $parsed['port'] : ( 'https' === strtolower( (string) ( $parsed['scheme'] ?? '' ) ) ? 443 : 80 );
			if ( '' === $home_host || ! hash_equals( $home_host, $host ) || $home_port !== $port ) {
				return '';
			}
		}
		if ( ! is_array( $parsed ) || ! isset( $parsed['path'] ) || ! is_string( $parsed['path'] ) || '' === $parsed['path'] ) {
			return '';
		}
		return KOZ404_Guard::normalise_path( $parsed['path'] );
	}
}
