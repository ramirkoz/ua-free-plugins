<?php
/**
 * Settings storage, defaults, banner templates and languages.
 *
 * @package KOZ_Consent_Manager
 */

namespace ramirkz\kozconsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZCONSENT_Settings {
	public const OPTION_NAME = 'kozconsent_settings';
	public const CATEGORIES  = array( 'analytics', 'advertising', 'external_media' );
	public const TEMPLATE_FIELDS = array(
		'banner_title',
		'banner_message',
		'accept_label',
		'reject_label',
		'customize_label',
		'save_label',
		'reopen_label',
	);
	public const SUPPORTED_LANGUAGES = array( 'uk', 'en', 'zh', 'es', 'ar', 'id', 'pt', 'fr', 'ja', 'de', 'hi' );
	public const LAYOUTS = array( 'bar', 'box', 'modal' );

	private const TEMPLATE_LIMITS = array(
		'banner_title'    => 120,
		'banner_message'  => 700,
		'accept_label'    => 80,
		'reject_label'    => 80,
		'customize_label' => 80,
		'save_label'      => 80,
		'reopen_label'    => 100,
	);

	private static ?array $option_cache = null;
	private static ?array $template_defaults_cache = null;

	public static function legacy_option_name(): string {
		return implode( '_', array( 'ua', 'free', 'consent', 'manager', 'settings' ) );
	}

	/** Stored settings without templates. */
	public static function storage_defaults(): array {
		return array(
			'schema_version'       => 3,
			'enabled'              => true,
			'policy_version'       => '1',
			'cookie_lifetime_days' => 180,
			'debug_mode'           => false,
			'google_consent_mode'  => false,
			'gcm_region'           => 'all',
			'gcm_wait'             => 500,
			'gcm_url_passthrough'  => false,
			'gcm_ads_redaction'    => true,
			'microsoft_consent'    => true,
			'datalayer_events'     => true,
			'wp_consent_api'       => true,
			'honor_gpc'            => true,
			'block_media'          => true,
			'youtube_nocookie'     => true,
			'layout'               => 'bar',
			'accent_color'         => '#2271b1',
			'reopen'               => 'floating',
			'log_enabled'          => false,
			'log_retention'        => 365,
			'handle_map'           => array(),
			'templates'            => array(),
		);
	}

	public static function migrate(): void {
		if ( false !== get_option( self::OPTION_NAME, false ) ) {
			return;
		}
		$legacy = get_option( self::legacy_option_name(), false );
		if ( is_array( $legacy ) ) {
			add_option( self::OPTION_NAME, self::normalize( $legacy ), '', true );
		}
	}

	/**
	 * Re-saves the option as autoloaded (it is read on every page) and fills new keys.
	 */
	public static function upgrade(): void {
		$stored = get_option( self::OPTION_NAME, false );
		if ( false === $stored ) {
			add_option( self::OPTION_NAME, self::storage_defaults(), '', true );
			return;
		}
		if ( is_array( $stored ) && 3 === (int) ( $stored['schema_version'] ?? 0 ) ) {
			return;
		}
		$normalized = self::normalize( $stored );
		delete_option( self::OPTION_NAME );
		add_option( self::OPTION_NAME, $normalized, '', true );
		self::$option_cache = null;
	}

	public static function language_from_locale( string $locale ): string {
		$locale   = strtolower( str_replace( '-', '_', $locale ) );
		$language = strtok( $locale, '_' );
		return is_string( $language ) && in_array( $language, self::SUPPORTED_LANGUAGES, true ) ? $language : 'en';
	}

	public static function context_language(): string {
		if ( is_admin() && function_exists( 'get_user_locale' ) ) {
			return self::language_from_locale( (string) get_user_locale() );
		}
		if ( function_exists( 'determine_locale' ) ) {
			return self::language_from_locale( (string) determine_locale() );
		}
		return self::language_from_locale( (string) get_locale() );
	}

	public static function site_language(): string {
		return self::language_from_locale( (string) get_locale() );
	}

	public static function language_name( string $language ): string {
		$names = array(
			'uk' => 'Українська',
			'en' => 'English',
			'zh' => '中文',
			'es' => 'Español',
			'ar' => 'العربية',
			'id' => 'Bahasa Indonesia',
			'pt' => 'Português',
			'fr' => 'Français',
			'ja' => '日本語',
			'de' => 'Deutsch',
			'hi' => 'हिन्दी',
		);
		return $names[ $language ] ?? 'English';
	}

	private static function all_template_defaults(): array {
		if ( null === self::$template_defaults_cache ) {
			$file = KOZCONSENT_DIR . 'assets/template-defaults.json';
			$data = is_readable( $file ) ? wp_json_file_decode( $file, array( 'associative' => true ) ) : null;
			self::$template_defaults_cache = is_array( $data ) ? $data : array();
		}
		return self::$template_defaults_cache;
	}

	public static function template_defaults( string $language ): array {
		$language = in_array( $language, self::SUPPORTED_LANGUAGES, true ) ? $language : 'en';
		$all      = self::all_template_defaults();
		$english  = isset( $all['en'] ) && is_array( $all['en'] ) ? $all['en'] : array(
			'banner_title'    => 'Privacy settings',
			'banner_message'  => 'We use necessary technologies to operate the site. Analytics, advertising and external media are enabled only after your consent.',
			'accept_label'    => 'Accept all',
			'reject_label'    => 'Reject optional',
			'customize_label' => 'Customize',
			'save_label'      => 'Save choices',
			'reopen_label'    => 'Privacy settings',
		);
		$template = isset( $all[ $language ] ) && is_array( $all[ $language ] ) ? $all[ $language ] : $english;
		$clean    = array();
		foreach ( self::TEMPLATE_FIELDS as $field ) {
			$clean[ $field ] = isset( $template[ $field ] ) && is_string( $template[ $field ] ) && '' !== $template[ $field ]
				? $template[ $field ]
				: (string) ( $english[ $field ] ?? '' );
		}
		return $clean;
	}

	public static function sanitize_template( array $input, string $language ): array {
		$defaults = self::template_defaults( $language );
		$clean    = array();
		foreach ( self::TEMPLATE_FIELDS as $field ) {
			$text = sanitize_textarea_field( (string) ( $input[ $field ] ?? '' ) );
			if ( '' === $text ) {
				$text = $defaults[ $field ];
			}
			$limit           = self::TEMPLATE_LIMITS[ $field ];
			$clean[ $field ] = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit ) : substr( $text, 0, $limit );
		}
		return $clean;
	}

	public static function sanitize_policy( string $policy ): string {
		$policy = preg_replace( '/[^A-Za-z0-9._-]/', '', sanitize_text_field( $policy ) ) ?? '';
		$policy = trim( $policy, '._-' );
		return '' === $policy ? '1' : substr( $policy, 0, 60 );
	}

	public static function sanitize_color( string $color ): string {
		$color = sanitize_hex_color( trim( $color ) );
		return is_string( $color ) && '' !== $color ? $color : '#2271b1';
	}

	/** Parses "handle=category" lines into a handle map. */
	public static function parse_handle_map( string $text ): array {
		$map = array();
		foreach ( preg_split( '/\R/', $text ) ?: array() as $line ) {
			$parts = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			$handle   = sanitize_key( $parts[0] );
			$category = sanitize_key( $parts[1] );
			if ( '' !== $handle && in_array( $category, self::CATEGORIES, true ) ) {
				$map[ $handle ] = $category;
			}
		}
		return array_slice( $map, 0, 100, true );
	}

	public static function normalize( mixed $stored ): array {
		$n = self::storage_defaults();
		if ( ! is_array( $stored ) ) {
			return $n;
		}
		$bool = static fn( string $key, bool $default ): bool => array_key_exists( $key, $stored ) ? ! empty( $stored[ $key ] ) : $default;

		$n['enabled']              = $bool( 'enabled', true );
		$n['policy_version']       = isset( $stored['policy_version'] ) ? self::sanitize_policy( (string) $stored['policy_version'] ) : '1';
		$n['cookie_lifetime_days'] = isset( $stored['cookie_lifetime_days'] ) ? max( 1, min( 730, (int) $stored['cookie_lifetime_days'] ) ) : 180;
		$n['debug_mode']           = $bool( 'debug_mode', false );
		$n['google_consent_mode']  = $bool( 'google_consent_mode', false );
		$n['gcm_region']           = in_array( $stored['gcm_region'] ?? 'all', array( 'all', 'eea' ), true ) ? (string) ( $stored['gcm_region'] ?? 'all' ) : 'all';
		$n['gcm_wait']             = isset( $stored['gcm_wait'] ) ? max( 0, min( 5000, (int) $stored['gcm_wait'] ) ) : 500;
		$n['gcm_url_passthrough']  = $bool( 'gcm_url_passthrough', false );
		$n['gcm_ads_redaction']    = $bool( 'gcm_ads_redaction', true );
		$n['microsoft_consent']    = $bool( 'microsoft_consent', true );
		$n['datalayer_events']     = $bool( 'datalayer_events', true );
		$n['wp_consent_api']       = $bool( 'wp_consent_api', true );
		$n['honor_gpc']            = $bool( 'honor_gpc', true );
		$n['block_media']          = $bool( 'block_media', true );
		$n['youtube_nocookie']     = $bool( 'youtube_nocookie', true );
		$n['layout']               = in_array( $stored['layout'] ?? 'bar', self::LAYOUTS, true ) ? (string) ( $stored['layout'] ?? 'bar' ) : 'bar';
		$n['accent_color']         = self::sanitize_color( (string) ( $stored['accent_color'] ?? '#2271b1' ) );
		$n['reopen']               = in_array( $stored['reopen'] ?? 'floating', array( 'floating', 'hidden' ), true ) ? (string) ( $stored['reopen'] ?? 'floating' ) : 'floating';
		$n['log_enabled']          = $bool( 'log_enabled', false );
		$n['log_retention']        = isset( $stored['log_retention'] ) ? max( 30, min( 1825, (int) $stored['log_retention'] ) ) : 365;
		$map = array();
		foreach ( (array) ( $stored['handle_map'] ?? array() ) as $handle => $category ) {
			$handle   = sanitize_key( (string) $handle );
			$category = sanitize_key( (string) $category );
			if ( '' !== $handle && in_array( $category, self::CATEGORIES, true ) ) {
				$map[ $handle ] = $category;
			}
		}
		$n['handle_map'] = $map;

		if ( isset( $stored['templates'] ) && is_array( $stored['templates'] ) ) {
			foreach ( self::SUPPORTED_LANGUAGES as $language ) {
				if ( isset( $stored['templates'][ $language ] ) && is_array( $stored['templates'][ $language ] ) ) {
					$n['templates'][ $language ] = self::sanitize_template( $stored['templates'][ $language ], $language );
				}
			}
		} else {
			$legacy = array_intersect_key( $stored, array_flip( self::TEMPLATE_FIELDS ) );
			if ( ! empty( $legacy ) ) {
				$language                    = self::site_language();
				$n['templates'][ $language ] = self::sanitize_template( $legacy, $language );
			}
		}
		return $n;
	}

	public static function stored(): array {
		if ( null === self::$option_cache ) {
			self::$option_cache = self::normalize( get_option( self::OPTION_NAME, array() ) );
		}
		return self::$option_cache;
	}

	public static function save( array $normalized ): void {
		$normalized['schema_version'] = 3;
		update_option( self::OPTION_NAME, $normalized, true );
		self::$option_cache = null;
	}

	public static function template( string $language ): array {
		$stored = self::stored();
		return isset( $stored['templates'][ $language ] ) ? $stored['templates'][ $language ] : self::template_defaults( $language );
	}

	/** Settings merged with the banner template of the current language. */
	public static function current(): array {
		$stored = self::stored();
		$language = self::context_language();
		unset( $stored['templates'] );
		return array_merge( $stored, self::template( $language ), array( 'language' => $language ) );
	}
}
