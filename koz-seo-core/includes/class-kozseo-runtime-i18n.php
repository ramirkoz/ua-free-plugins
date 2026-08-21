<?php
namespace ramirkz\kozseo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZSEO_Runtime_I18n {
	private static bool $booted = false;
	private static array $domains = array();
	private static array $cache = array();

	public static function register( string $domain, string $plugin_dir ): void {
		$domain = sanitize_key( $domain );
		if ( '' === $domain ) {
			return;
		}
		self::$domains[ $domain ] = trailingslashit( $plugin_dir );
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_filter( 'gettext', array( __CLASS__, 'gettext' ), 20, 3 );
		add_filter( 'gettext_with_context', array( __CLASS__, 'gettext_with_context' ), 20, 4 );
		add_filter( 'ngettext', array( __CLASS__, 'ngettext' ), 20, 5 );
		add_filter( 'ngettext_with_context', array( __CLASS__, 'ngettext_with_context' ), 20, 6 );
	}

	public static function language(): string {
		if ( is_admin() && function_exists( 'get_user_locale' ) ) {
			$locale = (string) get_user_locale();
		} elseif ( function_exists( 'determine_locale' ) ) {
			$locale = (string) determine_locale();
		} else {
			$locale = (string) get_locale();
		}
		$locale = strtolower( str_replace( '-', '_', $locale ) );
		$language = strtok( $locale, '_' );
		$supported = array( 'uk', 'zh', 'es', 'ar', 'id', 'pt', 'fr', 'ja', 'de', 'hi' );
		return in_array( $language, $supported, true ) ? $language : 'en';
	}

	private static function dictionary( string $domain ): array {
		$language = self::language();
		if ( 'en' === $language || ! isset( self::$domains[ $domain ] ) ) {
			return array();
		}
		$key = $domain . ':' . $language;
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}
		$file = self::$domains[ $domain ] . 'assets/i18n/' . $language . '.json';
		if ( ! is_readable( $file ) ) {
			self::$cache[ $key ] = array();
			return array();
		}
		$decoded = wp_json_file_decode( $file, array( 'associative' => true ) );
		self::$cache[ $key ] = is_array( $decoded ) ? $decoded : array();
		return self::$cache[ $key ];
	}

	private static function translate( string $text, string $domain ): string {
		$dictionary = self::dictionary( $domain );
		return isset( $dictionary[ $text ] ) && is_string( $dictionary[ $text ] ) && '' !== $dictionary[ $text ]
			? $dictionary[ $text ]
			: $text;
	}

	public static function gettext( string $translation, string $text, string $domain ): string {
		return isset( self::$domains[ $domain ] ) ? self::translate( $text, $domain ) : $translation;
	}
	public static function gettext_with_context( string $translation, string $text, string $context, string $domain ): string {
		unset( $context );
		return isset( self::$domains[ $domain ] ) ? self::translate( $text, $domain ) : $translation;
	}
	public static function ngettext( string $translation, string $single, string $plural, int $number, string $domain ): string {
		return isset( self::$domains[ $domain ] ) ? self::translate( 1 === $number ? $single : $plural, $domain ) : $translation;
	}
	public static function ngettext_with_context( string $translation, string $single, string $plural, int $number, string $context, string $domain ): string {
		unset( $context );
		return isset( self::$domains[ $domain ] ) ? self::translate( 1 === $number ? $single : $plural, $domain ) : $translation;
	}
}
