<?php
/**
 * Read-only bridges to other KOZ plugins: Static Translate (languages, Azure
 * credentials and the shared monthly quota) and Donate Stats (donation event).
 *
 * @package KOZ_Google_Ads_Campaign_Builder
 */

namespace ramirkz\kozgads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZGADS_Integrations {
	private const STX_SETTINGS    = 'kozstx_settings';
	private const STX_QUOTA_CLASS = '\\ramirkz\\kozstx\\KOZSTX_Quota';
	private const DONATE_PLUGIN   = '\\ramirkz\\kozdonate\\KOZDONATE_Plugin';

	private static function legacy_stx_option(): string {
		return implode( '_', array( 'uafree', 'st', 'auto', 'settings' ) );
	}

	/** KOZ Static Translate status, or an empty array. */
	public static function stx_status(): array {
		if ( function_exists( 'kozstx_get_status' ) ) {
			$status = kozstx_get_status();
			return is_array( $status ) ? $status : array();
		}
		return array();
	}

	/** Source language slug from KOZ Static Translate (or the former UA FREE option). */
	public static function source_language(): string {
		$status = self::stx_status();
		if ( ! empty( $status['source_language'] ) ) {
			return sanitize_key( (string) $status['source_language'] );
		}
		$legacy = get_option( self::legacy_stx_option(), array() );
		return is_array( $legacy ) ? sanitize_key( (string) ( $legacy['source_language'] ?? '' ) ) : '';
	}

	/** @return string[] Languages Static Translate publishes (without the source language). */
	public static function translated_languages(): array {
		$status = self::stx_status();
		return array_values( array_map( 'sanitize_key', (array) ( $status['target_languages'] ?? array() ) ) );
	}

	private static function decrypt_secret( string $encoded ): string {
		if ( '' === $encoded || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$raw = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes the encrypted Azure key stored by KOZ Static Translate.
		if ( false === $raw || strlen( $raw ) < 29 ) {
			return '';
		}
		$key   = hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
		$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
		return is_string( $plain ) ? $plain : '';
	}

	private static function valid_azure_endpoint( string $endpoint ): bool {
		$parts = wp_parse_url( $endpoint );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) ) {
			return false;
		}
		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		return 'api.cognitive.microsofttranslator.com' === $host || str_ends_with( $host, '.cognitiveservices.azure.com' );
	}

	/** Azure Translator credentials saved in KOZ Static Translate. */
	public static function azure_credentials(): array {
		$settings = get_option( self::STX_SETTINGS, false );
		$source   = 'KOZ Static Translate';
		if ( ! is_array( $settings ) || empty( $settings['azure_key_enc'] ) ) {
			$settings = get_option( self::legacy_stx_option(), array() );
			$source   = 'legacy';
		}
		$settings = is_array( $settings ) ? $settings : array();
		$key      = self::decrypt_secret( (string) ( $settings['azure_key_enc'] ?? '' ) );
		foreach ( array( 'KOZSTX_AZURE_TRANSLATOR_KEY', 'UAFREE_AZURE_TRANSLATOR_KEY' ) as $constant ) {
			if ( '' === $key && defined( $constant ) ) {
				$key    = trim( (string) constant( $constant ) );
				$source = 'wp-config.php';
			}
		}
		$region = trim( (string) ( $settings['azure_region'] ?? '' ) );
		foreach ( array( 'KOZSTX_AZURE_TRANSLATOR_REGION', 'UAFREE_AZURE_TRANSLATOR_REGION' ) as $constant ) {
			if ( '' === $region && defined( $constant ) ) {
				$region = trim( (string) constant( $constant ) );
			}
		}
		$endpoint = untrailingslashit( trim( (string) ( $settings['azure_endpoint'] ?? '' ) ) );
		if ( ! self::valid_azure_endpoint( $endpoint ) ) {
			$endpoint = 'https://api.cognitive.microsofttranslator.com';
		}
		return array(
			'key'        => $key,
			'region'     => $region,
			'endpoint'   => $endpoint,
			'configured' => '' !== $key,
			'source'     => '' !== $key ? $source : '',
		);
	}

	/** Characters still allowed by the Static Translate quota, or null when there is no quota to respect. */
	public static function quota_available(): ?int {
		$quota = self::STX_QUOTA_CLASS;
		if ( class_exists( $quota ) && method_exists( $quota, 'available' ) ) {
			return max( 0, (int) call_user_func( array( $quota, 'available' ) ) );
		}
		return null;
	}

	/** Monthly Static Translate usage for display. */
	public static function quota_usage(): array {
		$quota = self::STX_QUOTA_CLASS;
		if ( class_exists( $quota ) && method_exists( $quota, 'current_usage' ) ) {
			$usage = call_user_func( array( $quota, 'current_usage' ) );
			return array(
				'characters' => (int) ( $usage['characters'] ?? 0 ),
				'limit'      => (int) ( $usage['limit'] ?? 0 ),
				'percent'    => round( (float) ( $usage['percent'] ?? 0 ), 1 ),
			);
		}
		return array();
	}

	/** Records Azure characters in the shared Static Translate quota. */
	public static function record_usage( string $language, int $characters ): void {
		$quota = self::STX_QUOTA_CLASS;
		if ( $characters > 0 && class_exists( $quota ) && method_exists( $quota, 'increment_usage' ) ) {
			call_user_func( array( $quota, 'increment_usage' ), $language, $characters, 'queue' );
		}
	}

	/**
	 * Donation tracking reported by KOZ Donate Stats.
	 *
	 * @return array{available:bool,ready:bool,event:string,data_layer:bool,ga4:bool,mode:string,page_ids:int[],confirmations:int,last_confirmation_at:string}
	 */
	public static function donate_stats(): array {
		$result = array(
			'available'            => false,
			'ready'                => false,
			'event'                => '',
			'data_layer'           => false,
			'ga4'                  => false,
			'mode'                 => '',
			'page_ids'             => array(),
			'confirmations'        => 0,
			'last_confirmation_at' => '',
		);
		$plugin = self::DONATE_PLUGIN;
		if ( ! class_exists( $plugin ) || ! method_exists( $plugin, 'settings' ) ) {
			return $result;
		}
		$settings = (array) call_user_func( array( $plugin, 'settings' ) );
		$status   = has_filter( 'kozdonate_public_status' ) ? (array) apply_filters( 'kozdonate_public_status', array() ) : array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reads the public status filter of KOZ Donate Stats.
		$result['available']            = true;
		$result['ready']                = ! empty( $status['tracking_ready'] ) || ( ! empty( $settings['enabled'] ) && ! empty( $settings['tracked_page_ids'] ) );
		$result['event']                = ! empty( $settings['data_layer_enabled'] ) ? sanitize_key( (string) ( $settings['data_layer_event'] ?? '' ) ) : '';
		$result['data_layer']           = ! empty( $settings['data_layer_enabled'] );
		$result['ga4']                  = ! empty( $settings['ga4_events'] );
		$result['mode']                 = sanitize_key( (string) ( $settings['ad_account_mode'] ?? '' ) );
		$result['page_ids']             = array_values( array_filter( array_map( 'absint', (array) ( $settings['tracked_page_ids'] ?? array() ) ) ) );
		$result['confirmations']        = (int) ( $status['confirmation_rows'] ?? 0 );
		$result['last_confirmation_at'] = (string) ( $status['last_confirmation_at'] ?? '' );
		return $result;
	}
}
