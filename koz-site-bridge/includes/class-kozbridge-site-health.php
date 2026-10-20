<?php
/**
 * Site Health test and debug information for Site Bridge.
 *
 * @package KOZ_Site_Bridge
 */

namespace ramirkz\kozbridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZBRIDGE_Site_Health {
	private const KEY_MAX_AGE_DAYS = 180;

	public static function init(): void {
		add_filter( 'site_status_tests', array( __CLASS__, 'register_test' ) );
		add_filter( 'debug_information', array( __CLASS__, 'debug_information' ) );
	}

	private static function t( string $text ): string {
		return KOZBRIDGE_Bridge::tr( $text );
	}

	public static function register_test( array $tests ): array {
		$tests['direct']['kozbridge_keys'] = array(
			'label' => self::t( 'KOZ Site Bridge access' ),
			'test'  => array( __CLASS__, 'run_test' ),
		);
		return $tests;
	}

	public static function run_test(): array {
		$problems = array();
		$status   = 'good';
		$keys     = KOZBRIDGE_Auth::keys();

		if ( ! empty( $keys ) && 'https' !== wp_parse_url( home_url(), PHP_URL_SCHEME ) ) {
			$problems[] = self::t( 'The Home URL does not use HTTPS, so the API is disabled.' );
			$status     = 'critical';
		}
		foreach ( $keys as $key ) {
			$created = '' !== $key['created'] ? strtotime( $key['created'] ) : false;
			if ( false !== $created && $created < time() - self::KEY_MAX_AGE_DAYS * DAY_IN_SECONDS && '' === $key['expires'] ) {
				/* translators: %s: key label. */
				$problems[] = sprintf( self::t( '“%s” is older than 180 days and never expires. Create a new key and revoke the old one.' ), $key['label'] );
				$status     = 'critical' === $status ? $status : 'recommended';
			}
			if ( in_array( 'error_log', $key['scopes'], true ) ) {
				/* translators: %s: key label. */
				$problems[] = sprintf( self::t( '“%s” can read the PHP error log. Allow this only while you troubleshoot.' ), $key['label'] );
				$status     = 'critical' === $status ? $status : 'recommended';
			}
		}

		$label = 'good' === $status ? self::t( 'KOZ Site Bridge access is limited' ) : self::t( 'KOZ Site Bridge access needs attention' );
		$text  = empty( $keys ) ? self::t( 'No API keys exist; external access is closed.' ) : sprintf( self::t( '%d API key(s) can read site diagnostics.' ), count( $keys ) );
		$description = '<p>' . esc_html( $text ) . '</p>';
		if ( ! empty( $problems ) ) {
			$description .= '<ul>';
			foreach ( $problems as $problem ) {
				$description .= '<li>' . esc_html( $problem ) . '</li>';
			}
			$description .= '</ul>';
		}

		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array( 'label' => self::t( 'Security' ), 'color' => 'good' === $status ? 'blue' : 'orange' ),
			'description' => $description,
			'actions'     => '<p><a href="' . esc_url( admin_url( 'admin.php?page=koz-site-bridge' ) ) . '">' . esc_html( self::t( 'Manage API keys' ) ) . '</a></p>',
			'test'        => 'kozbridge_keys',
		);
	}

	public static function debug_information( array $info ): array {
		$keys = KOZBRIDGE_Auth::keys();
		$info['koz-site-bridge'] = array(
			'label'  => 'KOZ Site Bridge',
			'fields' => array(
				'version'   => array( 'label' => 'Version', 'value' => KOZBRIDGE_VERSION ),
				'keys'      => array( 'label' => 'API keys', 'value' => (string) count( $keys ) ),
				'scopes'    => array( 'label' => 'Scopes in use', 'value' => implode( ', ', array_unique( array_merge( array(), ...array_map( static fn( array $key ): array => $key['scopes'], $keys ?: array( array( 'scopes' => array() ) ) ) ) ) ) ?: '—' ),
				'abilities' => array( 'label' => 'Abilities', 'value' => function_exists( 'wp_register_ability' ) && KOZBRIDGE_Admin::settings()['abilities'] ? 'registered' : 'off' ),
			),
		);
		return $info;
	}
}
