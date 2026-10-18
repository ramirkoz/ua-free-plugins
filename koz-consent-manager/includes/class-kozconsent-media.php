<?php
/**
 * Blocks third-party embeds (YouTube, Vimeo, Google Maps and others) until the
 * visitor allows "External media".
 *
 * Every matching <iframe> in the page keeps its element, size and classes, so the
 * theme layout is unchanged. Its src moves to data-kozconsent-src and a local
 * notice is shown through srcdoc. consent-manager.js restores the original source
 * after consent or when the visitor loads one embed. Pages are always rewritten the
 * same way, so page caches stay correct for every visitor.
 *
 * @package KOZ_Consent_Manager
 */

namespace ramirkz\kozconsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZCONSENT_Media {
	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), PHP_INT_MAX );
	}

	/** Service name => host patterns. */
	public static function services(): array {
		$services = array(
			'YouTube'     => array( 'youtube.com/', 'youtube-nocookie.com/', 'youtu.be/' ),
			'Vimeo'       => array( 'player.vimeo.com/', 'vimeo.com/' ),
			'Google Maps' => array( 'google.com/maps', 'maps.google.', 'goo.gl/maps' ),
			'Spotify'     => array( 'open.spotify.com/' ),
			'SoundCloud'  => array( 'soundcloud.com/' ),
			'Facebook'    => array( 'facebook.com/plugins', 'facebook.com/v' ),
			'Instagram'   => array( 'instagram.com/' ),
			'TikTok'      => array( 'tiktok.com/' ),
			'X'           => array( 'platform.twitter.com/', '//twitter.com/', '//x.com/' ),
		);
		$filtered = apply_filters( 'kozconsent_media_services', $services );
		return is_array( $filtered ) ? $filtered : $services;
	}

	public static function service_for( string $src ): string {
		$src = strtolower( $src );
		foreach ( self::services() as $name => $patterns ) {
			foreach ( (array) $patterns as $pattern ) {
				if ( '' !== (string) $pattern && str_contains( $src, strtolower( (string) $pattern ) ) ) {
					return (string) $name;
				}
			}
		}
		return '';
	}

	public static function start_buffer(): void {
		$s = KOZCONSENT_Settings::stored();
		if ( empty( $s['enabled'] ) || empty( $s['block_media'] ) || is_admin() || is_feed() || wp_doing_ajax() || wp_doing_cron() || is_customize_preview() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		ob_start( array( __CLASS__, 'filter_html' ) );
	}

	public static function filter_html( string $html ): string {
		if ( false === stripos( $html, '<iframe' ) ) {
			return $html;
		}
		$result = preg_replace_callback( '/<iframe\b([^>]*)>/i', array( __CLASS__, 'replace_iframe' ), $html );
		return is_string( $result ) ? $result : $html;
	}

	/** @param array<int,string> $match */
	private static function replace_iframe( array $match ): string {
		$attrs = $match[1];
		if ( str_contains( $attrs, 'data-kozconsent-src' ) || ! preg_match( '/\ssrc\s*=\s*(["\'])(.*?)\1/is', ' ' . $attrs, $src_match ) ) {
			return $match[0];
		}
		$src = html_entity_decode( $src_match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( '' === trim( $src ) || str_contains( $src, 'googletagmanager.com' ) ) {
			return $match[0];
		}
		$service = self::service_for( $src );
		if ( '' === $service ) {
			return $match[0];
		}
		$attrs = preg_replace( '/\s(src|srcdoc)\s*=\s*(["\']).*?\2/is', '', ' ' . $attrs ) ?? $attrs;
		return '<iframe' . $attrs
			. ' data-kozconsent-src="' . esc_attr( $src ) . '"'
			. ' data-kozconsent-service="' . esc_attr( $service ) . '"'
			. ' data-kozconsent-category="external_media"'
			. ' srcdoc="' . esc_attr( self::notice( $service ) ) . '">';
	}

	/** Local notice shown inside the blocked iframe. */
	private static function notice( string $service ): string {
		static $cache = array();
		if ( isset( $cache[ $service ] ) ) {
			return $cache[ $service ];
		}
		$accent  = KOZCONSENT_Settings::sanitize_color( (string) KOZCONSENT_Settings::stored()['accent_color'] );
		$message = sprintf(
			/* translators: %s: service name, for example YouTube. */
			__( 'Content from %s is loaded only with your consent, because the service may set cookies.', 'koz-consent-manager' ),
			$service
		);
		$event = "frameElement&&frameElement.dispatchEvent(new CustomEvent('kozconsent:load',{bubbles:true,detail:{always:%s}}))";
		$html  = '<!doctype html><html lang="' . esc_attr( KOZCONSENT_Settings::context_language() ) . '"><meta charset="utf-8">'
			. '<style>html,body{height:100%;margin:0}body{display:flex;align-items:center;justify-content:center;background:#1d2327;color:#fff;font:15px/1.45 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;text-align:center}'
			. 'div{max-width:92%;padding:12px}p{margin:0 0 12px}button{margin:4px;padding:9px 16px;border:1px solid ' . $accent . ';border-radius:6px;background:' . $accent . ';color:#fff;font:inherit;cursor:pointer}button+button{background:transparent;border-color:#fff}</style>'
			. '<div><p>' . esc_html( $message ) . '</p>'
			. '<button type="button" onclick="' . esc_attr( sprintf( $event, 'false' ) ) . '">' . esc_html__( 'Load content', 'koz-consent-manager' ) . '</button>'
			. '<button type="button" onclick="' . esc_attr( sprintf( $event, 'true' ) ) . '">' . esc_html__( 'Always allow external media', 'koz-consent-manager' ) . '</button></div></html>';
		$cache[ $service ] = $html;
		return $html;
	}
}
