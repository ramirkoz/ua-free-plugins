<?php
/**
 * KOZ Consent Manager: public API, consent state and module wiring.
 *
 * Other plugins use register_integration(), is_allowed(), status() and
 * public_integrations(); these keep their 0.2.x signatures.
 *
 * @package KOZ_Consent_Manager
 */

namespace ramirkz\kozconsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZCONSENT_Manager {
	public const COOKIE_NAME = 'kozconsent';
	public const PAGE_SLUG   = 'koz-consent-manager';
	public const CATEGORIES  = KOZCONSENT_Settings::CATEGORIES;

	private static bool $booted = false;
	private static array $integrations = array();
	private static ?array $status_cache = null;

	public static function init(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		KOZCONSENT_Settings::migrate();
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
		KOZCONSENT_Frontend::init();
		KOZCONSENT_Consent_Mode::init();
		KOZCONSENT_Media::init();
		KOZCONSENT_WP_Consent_API::init();
		KOZCONSENT_Log::init();
		KOZCONSENT_Site_Health::init();
		if ( is_admin() ) {
			KOZCONSENT_Admin::init();
		}
	}

	public static function activate(): void {
		KOZCONSENT_Settings::migrate();
		self::maybe_upgrade();
	}

	public static function deactivate(): void {
		KOZCONSENT_Log::unschedule();
	}

	public static function maybe_upgrade(): void {
		if ( '3' === (string) get_option( 'kozconsent_db_version', '' ) ) {
			return;
		}
		KOZCONSENT_Settings::upgrade();
		KOZCONSENT_Log::install();
		KOZCONSENT_Log::schedule();
		update_option( 'kozconsent_db_version', '3', true );
	}

	public static function legacy_cookie_name(): string {
		return implode( '_', array( 'ua', 'free', 'consent' ) );
	}

	/** Settings with the banner template of the current language. */
	public static function settings(): array {
		return KOZCONSENT_Settings::current();
	}

	public static function defaults(): array {
		return array_merge( KOZCONSENT_Settings::storage_defaults(), KOZCONSENT_Settings::template_defaults( KOZCONSENT_Settings::context_language() ) );
	}

	/* ------------------------------------------------------------------
	 * Integrations (script handles that wait for a category)
	 * ---------------------------------------------------------------- */

	public static function register_integration( array $integration ): bool {
		$allowed_keys = array( 'id', 'name', 'category', 'script_handles' );
		if ( array_diff( array_keys( $integration ), $allowed_keys ) ) {
			return false;
		}
		$normalized = self::normalize_integration( $integration );
		if ( null === $normalized ) {
			return false;
		}
		self::$integrations[ $normalized['id'] ] = $normalized;
		return true;
	}

	private static function normalize_integration( array $integration ): ?array {
		$id       = sanitize_key( (string) ( $integration['id'] ?? '' ) );
		$category = sanitize_key( (string) ( $integration['category'] ?? '' ) );
		$handles  = isset( $integration['script_handles'] ) && is_array( $integration['script_handles'] ) ? $integration['script_handles'] : array();
		if ( '' === $id || ! in_array( $category, self::CATEGORIES, true ) ) {
			return null;
		}
		$clean = array_values( array_unique( array_filter( array_map( static fn( mixed $handle ): string => sanitize_key( (string) $handle ), $handles ) ) ) );
		if ( empty( $clean ) ) {
			return null;
		}
		$name = sanitize_text_field( (string) ( $integration['name'] ?? $id ) );
		return array(
			'id'             => $id,
			'name'           => '' !== $name ? $name : $id,
			'category'       => $category,
			'script_handles' => $clean,
		);
	}

	/** Code-registered integrations, filter additions and the handles set in the admin screen. */
	public static function normalized_integrations(): array {
		$all = self::$integrations;
		foreach ( (array) KOZCONSENT_Settings::stored()['handle_map'] as $handle => $category ) {
			$id = 'handle_' . $handle;
			$all[ $id ] = array(
				'id'             => $id,
				'name'           => $handle,
				'category'       => $category,
				'script_handles' => array( $handle ),
			);
		}
		$filtered = apply_filters( 'kozconsent_integrations', $all );
		$filtered = is_array( $filtered ) ? $filtered : $all;
		$result   = array();
		foreach ( $filtered as $key => $integration ) {
			if ( ! is_array( $integration ) ) {
				continue;
			}
			if ( ! isset( $integration['id'] ) && is_string( $key ) ) {
				$integration['id'] = $key;
			}
			$normalized = self::normalize_integration( array_intersect_key( $integration, array_flip( array( 'id', 'name', 'category', 'script_handles' ) ) ) );
			if ( null !== $normalized ) {
				$result[ $normalized['id'] ] = $normalized;
			}
		}
		ksort( $result );
		return $result;
	}

	public static function public_integrations(): array {
		return array_values( self::normalized_integrations() );
	}

	/* ------------------------------------------------------------------
	 * Consent state
	 * ---------------------------------------------------------------- */

	public static function status(): array {
		if ( null !== self::$status_cache ) {
			return self::$status_cache;
		}
		$policy  = (string) KOZCONSENT_Settings::stored()['policy_version'];
		$default = array(
			'necessary'      => true,
			'analytics'      => false,
			'advertising'    => false,
			'external_media' => false,
			'policy_version' => $policy,
			'updated_at'     => null,
		);

		$cookie = filter_input( INPUT_COOKIE, self::COOKIE_NAME, FILTER_UNSAFE_RAW );
		if ( ! is_string( $cookie ) ) {
			$cookie = filter_input( INPUT_COOKIE, self::legacy_cookie_name(), FILTER_UNSAFE_RAW );
		}
		if ( ! is_string( $cookie ) ) {
			self::$status_cache = $default;
			return $default;
		}
		$raw = rawurldecode( sanitize_text_field( $cookie ) );
		if ( '' === $raw || strlen( $raw ) > 2048 ) {
			self::$status_cache = $default;
			return $default;
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || 1 !== ( $decoded['schema_version'] ?? null ) || ! isset( $decoded['policy_version'] ) || ! hash_equals( $policy, (string) $decoded['policy_version'] ) ) {
			self::$status_cache = $default;
			return $default;
		}
		$updated_at = isset( $decoded['updated_at'] ) ? sanitize_text_field( (string) $decoded['updated_at'] ) : null;
		if ( ! is_string( $updated_at ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z$/', $updated_at ) ) {
			$updated_at = null;
		}
		self::$status_cache = array(
			'necessary'      => true,
			'analytics'      => true === ( $decoded['analytics'] ?? false ),
			'advertising'    => true === ( $decoded['advertising'] ?? false ),
			'external_media' => true === ( $decoded['external_media'] ?? false ),
			'policy_version' => $policy,
			'updated_at'     => $updated_at,
		);
		return self::$status_cache;
	}

	public static function reset_runtime_cache(): void {
		self::$status_cache = null;
	}

	public static function has_valid_choice(): bool {
		return null !== self::status()['updated_at'];
	}

	public static function is_allowed( string $category ): bool {
		$category = sanitize_key( $category );
		if ( 'necessary' === $category ) {
			return true;
		}
		if ( ! in_array( $category, self::CATEGORIES, true ) ) {
			return false;
		}
		return true === self::status()[ $category ];
	}
}
