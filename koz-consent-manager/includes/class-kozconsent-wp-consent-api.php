<?php
/**
 * WP Consent API bridge.
 *
 * Declares an opt-in consent type, registers the plugin as a consent provider and
 * (in consent-manager.js) passes every choice to wp_set_consent(), so Site Kit,
 * WooCommerce, WP Statistics and other API-aware plugins follow the visitor's choice.
 *
 * Category mapping: necessary → functional; analytics → statistics,
 * statistics-anonymous and preferences; advertising → marketing.
 *
 * @package KOZ_Consent_Manager
 */

namespace ramirkz\kozconsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZCONSENT_WP_Consent_API {
	public static function init(): void {
		if ( empty( KOZCONSENT_Settings::stored()['wp_consent_api'] ) ) {
			return;
		}
		add_filter( 'wp_get_consent_type', array( __CLASS__, 'consent_type' ), 10, 1 );
		add_filter( 'wp_consent_api_registered_' . plugin_basename( KOZCONSENT_FILE ), '__return_true' );
	}

	public static function consent_type( $type ): string {
		return empty( KOZCONSENT_Settings::stored()['enabled'] ) ? (string) $type : 'optin';
	}

	public static function is_active(): bool {
		return function_exists( 'wp_has_consent' ) || class_exists( 'WP_CONSENT_API', false );
	}

	public static function mapping(): array {
		return array(
			'functional'           => 'necessary',
			'preferences'          => 'analytics',
			'statistics'           => 'analytics',
			'statistics-anonymous' => 'analytics',
			'marketing'            => 'advertising',
		);
	}
}
