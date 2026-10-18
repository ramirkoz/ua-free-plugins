<?php
/**
 * Front-end request handling: redirects, 410 rules, 404 responses, canonical guard
 * and the optional data-layer event.
 *
 * @package KOZ_404_Guard
 */

namespace ramirkz\koz404;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZ404_Request {
	private const STX_CLASS = '\\ramirkz\\kozstx\\KOZSTX_Static_Translate';

	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 0 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_datalayer' ), 99 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'guard_language_namespace_redirect' ), 10, 2 );
	}

	public static function handle(): void {
		$settings = KOZ404_Guard::settings();
		if ( empty( $settings['enabled'] ) || KOZ404_Guard::bypass() ) {
			return;
		}

		if ( KOZ404_Rules::has_active_rules() ) {
			$path     = KOZ404_Guard::request_path();
			$query    = KOZ404_Guard::raw_query();
			$redirect = KOZ404_Rules::match_redirect( $path, $query );
			if ( null !== $redirect ) {
				KOZ404_Log::record( $redirect['status'], KOZ404_Guard::classify_request(), 'redirect', $redirect['id'] );
				wp_safe_redirect( $redirect['url'], $redirect['status'], 'KOZ 404 Guard & URL Intelligence' );
				exit;
			}
			$gone = KOZ404_Rules::match_gone( $path, KOZ404_Guard::query_pairs( $query ) );
			if ( null !== $gone ) {
				KOZ404_Log::record( 410, KOZ404_Guard::classify_request(), 'gone_rule', $gone );
				self::minimal_response( 410, __( 'Content removed', 'koz-404-guard' ), __( 'This content is no longer available.', 'koz-404-guard' ) );
			}
		}

		if ( ! is_404() ) {
			return;
		}

		$kind   = KOZ404_Guard::classify_request();
		$source = self::classify_404_source();
		KOZ404_Log::record( 404, $kind, $source );
		header( 'X-Robots-Tag: noindex, follow, noarchive', true );
		if ( 'static_404' === $source && ! empty( $settings['static_404'] ) ) {
			self::static_response();
		}
		if ( 'bot' === $kind && ! empty( $settings['minimal_bot_404'] ) ) {
			self::minimal_response( 404, __( 'Page not found', 'koz-404-guard' ), __( 'The requested page was not found.', 'koz-404-guard' ) );
		}
	}

	/**
	 * Classifies a 404 without exposing the raw request. System routes such as Cloudflare
	 * /cdn-cgi/, legacy ?attachment_id= URLs and missing static files are kept apart from
	 * real content misses.
	 */
	public static function classify_404_source(): string {
		$path = KOZ404_Guard::request_path();
		if ( KOZ404_Guard::is_system_path( $path ) ) {
			return 'system_route_404';
		}
		if ( KOZ404_Guard::is_static_file_path( $path ) ) {
			return 'static_404';
		}
		foreach ( KOZ404_Guard::query_pairs( KOZ404_Guard::raw_query() ) as $pair ) {
			if ( 'attachment_id' === strtolower( $pair['key'] ) && preg_match( '/^[1-9][0-9]*$/', $pair['value'] ) ) {
				return 'attachment_404';
			}
		}
		return 'wordpress_404';
	}

	/** Plain 404 for a missing file: no theme, no database-heavy rendering. */
	private static function static_response(): void {
		status_header( 404 );
		header( 'Content-Type: text/plain; charset=utf-8', true );
		header( 'Cache-Control: public, max-age=300', true );
		header( 'X-Content-Type-Options: nosniff', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		echo "404 Not Found\n";
		exit;
	}

	public static function minimal_response( int $status, string $title, string $message ): void {
		status_header( $status );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'X-Content-Type-Options: nosniff', true );
		header( 'Referrer-Policy: no-referrer', true );
		header( "Content-Security-Policy: default-src 'none'; style-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'", true );
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ), true );
		$home = home_url( '/' );
		echo '<!doctype html><html lang="' . esc_attr( substr( determine_locale(), 0, 2 ) ) . '"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive">';
		echo '<title>' . esc_html( $title ) . '</title>';
		wp_enqueue_style( 'koz404-minimal-response', KOZ404_URL . 'assets/koz404-minimal-response.css', array(), KOZ404_VERSION );
		wp_print_styles( 'koz404-minimal-response' );
		echo '</head>';
		echo '<body><main><h1>' . esc_html( (string) $status ) . '</h1><p>' . esc_html( $message ) . '</p><a href="' . esc_url( $home ) . '">' . esc_html__( 'Return to the homepage', 'koz-404-guard' ) . '</a></main></body></html>';
		exit;
	}

	public static function enqueue_datalayer(): void {
		$settings = KOZ404_Guard::settings();
		if ( empty( $settings['enabled'] ) || empty( $settings['emit_datalayer'] ) || KOZ404_Guard::bypass() || ! is_404() || 'human' !== KOZ404_Guard::classify_request() ) {
			return;
		}
		$payload = array(
			'event'            => 'koz_404',
			'status'           => 404,
			'path_fingerprint' => KOZ404_Guard::fingerprint( KOZ404_Guard::request_path(), 'path' ),
		);
		wp_enqueue_script( 'koz404-datalayer', KOZ404_URL . 'assets/koz404-datalayer.js', array(), KOZ404_VERSION, true );
		wp_add_inline_script( 'koz404-datalayer', 'window.dataLayer.push(' . wp_json_encode( $payload ) . ');', 'after' );
	}

	/**
	 * Stops WordPress canonical guessing from collapsing a language namespace into an
	 * unrelated source-language URL on a 404 (/pl/about/ -> /about/). Same-namespace
	 * fixes such as /en/about -> /en/about/ remain allowed.
	 *
	 * @param string|false $redirect_url  Canonical redirect target or false.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public static function guard_language_namespace_redirect( $redirect_url, $requested_url ) {
		if ( ! is_404() || false === $redirect_url || ! is_string( $redirect_url ) || '' === $redirect_url ) {
			return $redirect_url;
		}
		$requested_path = KOZ404_Guard::request_path();
		if ( is_string( $requested_url ) && '' !== $requested_url ) {
			$parsed = wp_parse_url( $requested_url, PHP_URL_PATH );
			if ( is_string( $parsed ) && '' !== $parsed ) {
				$requested_path = KOZ404_Guard::normalise_path( $parsed );
			}
		}
		$source_namespace = self::language_like_namespace( $requested_path );
		if ( '' === $source_namespace ) {
			return $redirect_url;
		}
		$target_path = wp_parse_url( $redirect_url, PHP_URL_PATH );
		if ( ! is_string( $target_path ) || '' === $target_path ) {
			return false;
		}
		$target_namespace = self::language_like_namespace( KOZ404_Guard::normalise_path( $target_path ) );
		if ( '' !== $target_namespace && hash_equals( $source_namespace, $target_namespace ) ) {
			return $redirect_url;
		}
		return false;
	}

	/** Language slugs of KOZ Static Translate, when it is active. */
	public static function translator_languages(): array {
		static $languages = null;
		if ( null !== $languages ) {
			return $languages;
		}
		$languages = array();
		if ( class_exists( self::STX_CLASS, false ) && method_exists( self::STX_CLASS, 'languages' ) ) {
			try {
				$languages = array_map( 'strtolower', array_map( 'strval', array_keys( (array) call_user_func( array( self::STX_CLASS, 'languages' ) ) ) ) );
			} catch ( \Throwable $error ) {
				$languages = array();
			}
		}
		return $languages;
	}

	/**
	 * Language namespace of the first path segment: a two-letter code or a code with a
	 * region (en, uk, zh-cn, pt-br), or any slug KOZ Static Translate uses. Three-letter
	 * words such as /faq/ or /app/ no longer count unless the translator uses them.
	 */
	public static function language_like_namespace( string $path ): string {
		$path = trim( KOZ404_Guard::normalise_path( $path ), '/' );
		if ( '' === $path ) {
			return '';
		}
		$first = strtolower( (string) strtok( $path, '/' ) );
		$match = in_array( $first, self::translator_languages(), true )
			|| (bool) preg_match( '/^[a-z]{2}(?:-[a-z0-9]{2,8})?$/', $first );
		$candidate = $match ? $first : '';
		$filtered  = KOZ404_Guard::scalar_string( apply_filters( 'koz404_language_like_namespace', $candidate, $path ) );
		return preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/', strtolower( $filtered ) ) ? strtolower( $filtered ) : '';
	}
}
