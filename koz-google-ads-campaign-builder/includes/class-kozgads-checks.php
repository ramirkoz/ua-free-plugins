<?php
/**
 * Live same-site AdsBot destination check (desktop and mobile).
 *
 * @package KOZ_Google_Ads_Campaign_Builder
 */

namespace ramirkz\kozgads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZGADS_Checks {
	public const OPTION = 'kozgads_live_destination_check';
	public const ACTION = 'kozgads_live_check';

	private const AGENTS = array(
		'desktop' => 'Mozilla/5.0 (compatible; AdsBot-Google/1.0; +http://www.google.com/adsbot.html)',
		'mobile'  => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36 (compatible; AdsBot-Google-Mobile; +http://www.google.com/mobile/adsbot.html)',
	);

	public static function init(): void {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'run' ) );
	}

	/** @return array<int,array{key:string,title:string,language:string,url:string}> */
	public static function targets( array $settings, array $landings ): array {
		$targets = array();
		$seen    = array();
		foreach ( $landings as $key => $page ) {
			foreach ( KOZGADS_Plugin::campaign_languages( $settings ) as $language ) {
				$url = esc_url_raw( KOZGADS_Plugin::localised_url( (string) $page['url'], $language, $settings ) );
				if ( '' === $url || isset( $seen[ $url ] ) ) {
					continue;
				}
				$seen[ $url ] = true;
				$targets[]    = array( 'key' => (string) $key, 'title' => trim( (string) ( $page['title'] ?? $key ) ), 'language' => sanitize_key( $language ), 'url' => $url );
			}
		}
		return array_slice( $targets, 0, 24 );
	}

	private static function accept_language( string $language ): string {
		$map = array( 'zh' => 'zh-CN,zh;q=0.9', 'en' => 'en-US,en;q=0.9' );
		return $map[ $language ] ?? ( $language . ',' . $language . ';q=0.9,en;q=0.5' );
	}

	/** @return array<string,mixed> */
	public static function probe( string $url, string $language, string $device ): array {
		$home = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$out  = array( 'ok' => false, 'http_code' => 0, 'error' => '', 'elapsed_ms' => 0, 'html_lang' => '', 'language_match' => false, 'script_mismatch' => false, 'noindex' => false );
		if ( '' === $url || strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) !== $home ) {
			$out['error'] = 'Destination is not a same-site URL.';
			return $out;
		}
		$started  = microtime( true );
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 15,
				'redirection'         => 5,
				'limit_response_size' => 2 * MB_IN_BYTES,
				'headers'             => array( 'Accept' => 'text/html,application/xhtml+xml', 'Accept-Language' => self::accept_language( $language ) ),
				'user-agent'          => self::AGENTS[ $device ] ?? self::AGENTS['desktop'],
			)
		);
		$out['elapsed_ms'] = (int) round( ( microtime( true ) - $started ) * 1000 );
		if ( is_wp_error( $response ) ) {
			$out['error'] = sanitize_text_field( $response->get_error_message() );
			return $out;
		}
		$code    = (int) wp_remote_retrieve_response_code( $response );
		$signals = KOZGADS_Copy::parse( (string) wp_remote_retrieve_body( $response ) );
		$robots  = strtolower( (string) wp_remote_retrieve_header( $response, 'x-robots-tag' ) );
		$out['ok']              = $code >= 200 && $code < 300;
		$out['http_code']       = $code;
		$out['html_lang']       = (string) $signals['lang'];
		$out['language_match']  = '' === $signals['lang'] || 0 === strpos( (string) $signals['lang'], $language );
		$out['script_mismatch'] = KOZGADS_Copy::script_mismatch( (string) $signals['text'], $language );
		$out['noindex']         = ! empty( $signals['noindex'] ) || str_contains( $robots, 'noindex' );
		return $out;
	}

	/** @return array{ok:bool,http_code:int,blocked:?bool,error:string} */
	private static function robots(): array {
		$response = wp_safe_remote_get( home_url( '/robots.txt' ), array( 'timeout' => 6, 'redirection' => 3, 'limit_response_size' => 256 * KB_IN_BYTES, 'user-agent' => self::AGENTS['desktop'] ) );
		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'http_code' => 0, 'blocked' => null, 'error' => sanitize_text_field( $response->get_error_message() ) );
		}
		$code    = (int) wp_remote_retrieve_response_code( $response );
		$body    = strtolower( (string) wp_remote_retrieve_body( $response ) );
		$blocked = false;
		if ( preg_match_all( '/user-agent\s*:\s*adsbot-google(?:-mobile)?\s*(.*?)(?=user-agent\s*:|\z)/is', $body, $groups ) ) {
			foreach ( (array) $groups[1] as $group ) {
				if ( preg_match( '/disallow\s*:\s*\/\s*(?:$|\r?\n)/mi', (string) $group ) ) {
					$blocked = true;
				}
			}
		}
		return array( 'ok' => $code >= 200 && $code < 400, 'http_code' => $code, 'blocked' => $blocked, 'error' => '' );
	}

	public static function run(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this plugin.', 'koz-google-ads-campaign-builder' ) );
		}
		check_admin_referer( self::ACTION );
		$settings = KOZGADS_Plugin::settings();
		$rows     = array();
		$issues   = array();
		$warnings = array();
		foreach ( self::targets( $settings, KOZGADS_Plugin::selected_landing_pages( $settings ) ) as $target ) {
			$label = $target['title'] . ' [' . strtoupper( $target['language'] ) . ']';
			foreach ( array( 'desktop', 'mobile' ) as $device ) {
				$probe  = self::probe( $target['url'], $target['language'], $device );
				$rows[] = array_merge( $target, $probe, array( 'device' => $device ) );
				if ( empty( $probe['ok'] ) ) {
					$issues[] = $label . ' ' . $device . ': HTTP ' . (int) $probe['http_code'] . ( '' !== $probe['error'] ? ' (' . $probe['error'] . ')' : '' ) . '.';
					continue;
				}
				// Ads may point to noindex pages; it only matters for organic search.
				if ( ! empty( $probe['noindex'] ) && 'desktop' === $device ) {
					$warnings[] = $label . ': the page is marked noindex (KOZ Static Translate does this for partly translated pages). Ads can still serve; Google Search will not index it.';
				}
				if ( (int) $probe['elapsed_ms'] > 5000 ) {
					$warnings[] = $label . ' ' . $device . ': slow response (' . (int) $probe['elapsed_ms'] . ' ms).';
				}
				if ( empty( $probe['language_match'] ) || ! empty( $probe['script_mismatch'] ) ) {
					$warnings[] = $label . ': the page language may not match the campaign language.';
				}
			}
		}
		$robots = self::robots();
		if ( true === $robots['blocked'] ) {
			$issues[] = 'robots.txt blocks AdsBot-Google.';
		} elseif ( empty( $robots['ok'] ) ) {
			$warnings[] = 'robots.txt could not be verified with the AdsBot user agent.';
		}
		$issues   = array_values( array_unique( $issues ) );
		$warnings = array_values( array_unique( $warnings ) );
		update_option(
			self::OPTION,
			array(
				'checked_at'    => gmdate( 'c' ),
				'ready'         => ! empty( $rows ) && empty( $issues ),
				'target_count'  => count( $rows ),
				'issue_count'   => count( $issues ),
				'warning_count' => count( $warnings ),
				'issues'        => $issues,
				'warnings'      => $warnings,
				'robots'        => $robots,
				'rows'          => $rows,
			),
			false
		);
		KOZGADS_Plugin::redirect_notice( 'live_check_complete' );
	}
}
