<?php
/**
 * Finds third-party services on the home page and shows how consent controls each:
 * through Consent Mode, by blocking until consent, or not at all.
 *
 * Runs only when an administrator starts it. One request to the site's own home page.
 *
 * @package KOZ_Consent_Manager
 */

namespace ramirkz\kozconsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZCONSENT_Scanner {
	public const OPTION = 'kozconsent_scan';

	/**
	 * Known services: name, category, patterns and the control that applies.
	 * Control: google (Consent Mode), microsoft (UET/Clarity consent), media (blocked
	 * embeds) or none (not controlled unless mapped to a script handle).
	 */
	private static function catalogue(): array {
		return array(
			array( 'Google tag (gtag.js)', 'analytics', array( 'googletagmanager.com/gtag/js' ), 'google' ),
			array( 'Google Tag Manager', 'analytics', array( 'googletagmanager.com/gtm.js' ), 'google' ),
			array( 'Google Ads', 'advertising', array( "'AW-", '"AW-', 'googleadservices.com' ), 'google' ),
			array( 'Google AdSense', 'advertising', array( 'pagead2.googlesyndication.com' ), 'google' ),
			array( 'Microsoft Clarity', 'analytics', array( 'clarity.ms/tag' ), 'microsoft' ),
			array( 'Microsoft Advertising (UET)', 'advertising', array( 'bat.bing.com' ), 'microsoft' ),
			array( 'Meta Pixel', 'advertising', array( 'connect.facebook.net', 'fbevents.js' ), 'none' ),
			array( 'Hotjar', 'analytics', array( 'static.hotjar.com' ), 'none' ),
			array( 'TikTok Pixel', 'advertising', array( 'analytics.tiktok.com' ), 'none' ),
			array( 'LinkedIn Insight', 'advertising', array( 'snap.licdn.com' ), 'none' ),
			array( 'Yandex Metrica', 'analytics', array( 'mc.yandex.ru' ), 'none' ),
		);
	}

	public static function scan(): array {
		$response = wp_remote_get(
			add_query_arg( 'kozconsent_scan', (string) time(), home_url( '/' ) ),
			array(
				'timeout'     => 20,
				'redirection' => 3,
				'headers'     => array( 'Cache-Control' => 'no-cache' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$result = array( 'time' => time(), 'error' => true, 'services' => array() );
			update_option( self::OPTION, $result, false );
			return $result;
		}
		$html     = (string) wp_remote_retrieve_body( $response );
		$services = array();
		foreach ( self::catalogue() as $item ) {
			foreach ( $item[2] as $pattern ) {
				if ( str_contains( $html, $pattern ) ) {
					$services[] = array( 'name' => $item[0], 'category' => $item[1], 'control' => $item[3], 'count' => 1 );
					break;
				}
			}
		}
		$embeds = array();
		if ( preg_match_all( '/<iframe\b[^>]*>/i', $html, $matches ) ) {
			foreach ( $matches[0] as $tag ) {
				$blocked = str_contains( $tag, 'data-kozconsent-src' );
				$src     = preg_match( '/\s(?:data-kozconsent-src|src)\s*=\s*(["\'])(.*?)\1/is', $tag, $m ) ? html_entity_decode( $m[2] ) : '';
				$service = KOZCONSENT_Media::service_for( $src );
				if ( '' === $service ) {
					continue;
				}
				$key = $service . ( $blocked ? '|1' : '|0' );
				$embeds[ $key ] = ( $embeds[ $key ] ?? 0 ) + 1;
			}
		}
		foreach ( $embeds as $key => $count ) {
			list( $service, $blocked ) = explode( '|', $key );
			$services[] = array( 'name' => $service, 'category' => 'external_media', 'control' => '1' === $blocked ? 'media' : 'none', 'count' => $count );
		}
		$result = array( 'time' => time(), 'error' => false, 'services' => $services );
		update_option( self::OPTION, $result, false );
		return $result;
	}

	public static function last(): array {
		$scan = get_option( self::OPTION, array() );
		return is_array( $scan ) ? $scan : array();
	}

	public static function last_services(): array {
		$scan = self::last();
		return isset( $scan['services'] ) && is_array( $scan['services'] ) ? $scan['services'] : array();
	}

	/** Services found by the last scan that consent does not control. */
	public static function uncontrolled(): array {
		$s = KOZCONSENT_Settings::stored();
		return array_values(
			array_filter(
				self::last_services(),
				static function ( array $service ) use ( $s ): bool {
					if ( 'google' === $service['control'] ) {
						return empty( $s['google_consent_mode'] ) && ! KOZCONSENT_Consent_Mode::site_kit_consent_mode();
					}
					if ( 'microsoft' === $service['control'] ) {
						return empty( $s['microsoft_consent'] );
					}
					return 'none' === $service['control'];
				}
			)
		);
	}
}
