<?php
/**
 * Watches published content for address changes (slug, parent or date) and either
 * suggests a 301 redirect for approval or creates it, depending on the setting.
 *
 * @package KOZ_404_Guard
 */

namespace ramirkz\koz404;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZ404_Permalink_Watch {
	public const SUGGEST_OPTION = 'koz404_suggested_redirects';
	private const MAX_SUGGESTIONS = 200;

	/** @var array<int,string> Old paths captured before an update, by post ID. */
	private static array $before = array();

	public static function init(): void {
		add_action( 'pre_post_update', array( __CLASS__, 'remember' ), 10, 1 );
		add_action( 'post_updated', array( __CLASS__, 'compare' ), 10, 3 );
	}

	private static function watched( \WP_Post $post ): bool {
		if ( 'publish' !== $post->post_status || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return false;
		}
		$type = get_post_type_object( $post->post_type );
		return $type && $type->public && 'attachment' !== $post->post_type;
	}

	private static function path_of( int $post_id ): string {
		$url = get_permalink( $post_id );
		if ( ! is_string( $url ) || '' === $url || str_contains( $url, '?' ) ) {
			return '';
		}
		return KOZ404_Guard::normalise_path( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	}

	public static function remember( $post_id ): void {
		if ( 'off' === KOZ404_Guard::settings()['permalink_watch'] ) {
			return;
		}
		$post = get_post( (int) $post_id );
		if ( $post instanceof \WP_Post && self::watched( $post ) ) {
			self::$before[ (int) $post_id ] = self::path_of( (int) $post_id );
		}
	}

	public static function compare( $post_id, $post_after, $post_before ): void {
		$post_id = (int) $post_id;
		$mode    = KOZ404_Guard::settings()['permalink_watch'];
		$old     = self::$before[ $post_id ] ?? '';
		unset( self::$before[ $post_id ] );
		if ( 'off' === $mode || '' === $old || ! $post_after instanceof \WP_Post || ! self::watched( $post_after ) ) {
			return;
		}
		$new = self::path_of( $post_id );
		if ( '' === $new || $old === $new || KOZ404_Guard::is_protected_path( $old ) ) {
			return;
		}
		// The content is live at the new address again: a permalink redirect away from it would hide it.
		KOZ404_Rules::delete_permalink_redirect_from( $new );
		if ( KOZ404_Rules::has_redirect_source( $old ) ) {
			return;
		}
		if ( 'auto' === $mode ) {
			$error = KOZ404_Rules::save_redirect(
				array(
					'source' => $old,
					'target' => $new,
					'status' => 301,
					'origin' => 'permalink',
				)
			);
			if ( '' === $error ) {
				KOZ404_Log::resolve_path( $old );
			}
			return;
		}
		self::add_suggestion( $old, $new, $post_id );
	}

	private static function add_suggestion( string $old, string $new, int $post_id ): void {
		$list = self::suggestions();
		foreach ( $list as $key => $item ) {
			if ( $item['source'] === $old ) {
				unset( $list[ $key ] );
			}
		}
		$list[] = array(
			'id'      => md5( $old . '|' . $new ),
			'source'  => $old,
			'target'  => $new,
			'post_id' => $post_id,
			'created' => current_time( 'mysql' ),
		);
		update_option( self::SUGGEST_OPTION, array_slice( array_values( $list ), -self::MAX_SUGGESTIONS ), false );
	}

	public static function suggestions(): array {
		$list = get_option( self::SUGGEST_OPTION, array() );
		$out  = array();
		foreach ( is_array( $list ) ? $list : array() as $item ) {
			if ( is_array( $item ) && isset( $item['id'], $item['source'], $item['target'] ) ) {
				$out[] = array(
					'id'      => (string) $item['id'],
					'source'  => KOZ404_Guard::normalise_path( (string) $item['source'] ),
					'target'  => KOZ404_Guard::normalise_path( (string) $item['target'] ),
					'post_id' => (int) ( $item['post_id'] ?? 0 ),
					'created' => (string) ( $item['created'] ?? '' ),
				);
			}
		}
		return $out;
	}

	public static function dismiss( string $id ): void {
		update_option( self::SUGGEST_OPTION, array_values( array_filter( self::suggestions(), static fn( array $item ): bool => $item['id'] !== $id ) ), false );
	}

	/** Creates the suggested redirect. */
	public static function accept( string $id ): string {
		foreach ( self::suggestions() as $item ) {
			if ( $item['id'] !== $id ) {
				continue;
			}
			$error = KOZ404_Rules::save_redirect(
				array(
					'source' => $item['source'],
					'target' => $item['target'],
					'status' => 301,
					'origin' => 'permalink',
				)
			);
			if ( '' === $error || 'duplicate_redirect' === $error ) {
				self::dismiss( $id );
				KOZ404_Log::resolve_path( $item['source'] );
			}
			return $error;
		}
		return 'rule_not_found';
	}
}
