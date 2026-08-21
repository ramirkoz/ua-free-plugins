<?php
namespace ramirkz\kozmig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only content migration diagnostics.
 *
 * This scanner only identifies candidates. It never edits content, creates
 * redirects or changes attachment state.
 */
final class KOZMIG_Content_Migration_Scanner {
	private const SAMPLE_LIMIT = 100;

	public static function report(): array {
		return array(
			'placeholder_pages'       => self::placeholder_pages(),
			'legacy_attachment_links' => self::legacy_attachment_links(),
			'old_slug_redirect_map'   => self::old_slug_redirect_map(),
			'safety'                  => array(
				'read_only'          => true,
				'content_changes'    => false,
				'redirects_created'  => false,
				'attachment_changes' => false,
				'manual_verify'      => true,
			),
		);
	}

	private static function content_ids(): array {
		$ids = get_posts(
			array(
				'post_type'              => array( 'page', 'post' ),
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	private static function placeholder_pages(): array {
		$markers = array(
			'lorem ipsum',
			'dolor sit amet',
			'dummy text',
			'placeholder content',
			'this is a sample page',
		);
		$items = array();
		$total = 0;

		$page_ids = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( is_array( $page_ids ) ? $page_ids : array() as $page_id ) {
			$post = get_post( (int) $page_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$haystack = strtolower(
				remove_accents(
					wp_strip_all_tags(
						strip_shortcodes( (string) $post->post_title . ' ' . (string) $post->post_content )
					)
				)
			);
			$matched = '';
			foreach ( $markers as $marker ) {
				if ( str_contains( $haystack, $marker ) ) {
					$matched = $marker;
					break;
				}
			}
			if ( '' === $matched ) {
				continue;
			}

			++$total;
			if ( count( $items ) >= self::SAMPLE_LIMIT ) {
				continue;
			}
			$items[] = array(
				'path'   => self::post_path( (int) $page_id ),
				'title'  => wp_strip_all_tags( (string) $post->post_title ),
				'marker' => $matched,
			);
		}

		return array(
			'count'     => $total,
			'items'     => $items,
			'truncated' => $total > count( $items ),
		);
	}

	private static function legacy_attachment_links(): array {
		$total_links = 0;
		$source_count = 0;
		$items = array();

		foreach ( self::content_ids() as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post || '' === (string) $post->post_content ) {
				continue;
			}
			if ( ! preg_match_all( '/(?:\?|&amp;|&)attachment_id=(\d+)/i', (string) $post->post_content, $matches ) ) {
				continue;
			}

			$ids = array_values( array_unique( array_map( 'intval', $matches[1] ) ) );
			$total_links += count( $matches[1] );
			++$source_count;

			foreach ( $ids as $attachment_id ) {
				if ( count( $items ) >= self::SAMPLE_LIMIT ) {
					break;
				}
				$items[] = array(
					'source_path'            => self::post_path( $post_id ),
					'post_type'              => (string) $post->post_type,
					'attachment_fingerprint' => self::fingerprint( (string) $attachment_id ),
				);
			}
		}

		$attachment_counts = wp_count_posts( 'attachment' );
		$attachment_posts = isset( $attachment_counts->inherit ) ? (int) $attachment_counts->inherit : 0;

		return array(
			'attachment_posts' => $attachment_posts,
			'query_link_count' => $total_links,
			'source_count'     => $source_count,
			'items'            => $items,
			'truncated'        => $total_links > count( $items ),
		);
	}

	private static function old_slug_redirect_map(): array {
		$post_ids = get_posts(
			array(
				'post_type'              => array( 'page', 'post' ),
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'meta_key'               => '_wp_old_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Manual read-only migration inventory.
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);
		$items = array();
		$total = 0;

		foreach ( is_array( $post_ids ) ? $post_ids : array() as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$old_slugs = array_values( array_unique( array_filter( array_map( 'sanitize_title', (array) get_post_meta( (int) $post_id, '_wp_old_slug', false ) ) ) ) );
			$target_path = self::post_path( (int) $post_id );

			foreach ( $old_slugs as $old_slug ) {
				if ( '' === $old_slug || $old_slug === (string) $post->post_name ) {
					continue;
				}
				++$total;
				if ( count( $items ) >= self::SAMPLE_LIMIT ) {
					continue;
				}
				$items[] = array(
					'source_path'      => self::replace_final_slug( $target_path, (string) $post->post_name, $old_slug ),
					'old_slug'         => $old_slug,
					'target_path'      => $target_path,
					'post_type'        => (string) $post->post_type,
					'post_fingerprint' => self::fingerprint( (string) $post_id ),
					'confidence'       => 'wordpress_old_slug',
					'auto_apply'       => false,
				);
			}
		}

		return array(
			'count'     => $total,
			'items'     => $items,
			'truncated' => $total > count( $items ),
			'note'      => __( 'Redirect candidates are inventory only. Verify traffic, backlinks and destination content before creating any 301 redirect.', 'koz-migration-cleanup' ),
		);
	}

	private static function post_path( int $post_id ): string {
		$url = get_permalink( $post_id );
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return is_string( $path ) ? $path : '';
	}

	private static function replace_final_slug( string $target_path, string $current_slug, string $old_slug ): string {
		$trimmed = trim( $target_path, '/' );
		if ( '' === $trimmed ) {
			return '';
		}
		$segments = explode( '/', $trimmed );
		$last = array_pop( $segments );
		if ( rawurldecode( (string) $last ) !== $current_slug ) {
			return '';
		}
		$segments[] = rawurlencode( $old_slug );
		return '/' . implode( '/', $segments ) . '/';
	}

	private static function fingerprint( string $value ): string {
		return substr( hash_hmac( 'sha256', $value, wp_salt( 'auth' ) ), 0, 16 );
	}
}
