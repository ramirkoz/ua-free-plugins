<?php
/**
 * Site Health test for the consent setup.
 *
 * @package KOZ_Consent_Manager
 */

namespace ramirkz\kozconsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZCONSENT_Site_Health {
	public static function init(): void {
		add_filter( 'site_status_tests', array( __CLASS__, 'tests' ) );
	}

	public static function tests( array $tests ): array {
		$tests['direct']['kozconsent_setup'] = array(
			'label' => __( 'Visitor consent', 'koz-consent-manager' ),
			'test'  => array( __CLASS__, 'test' ),
		);
		return $tests;
	}

	public static function test(): array {
		$s        = KOZCONSENT_Settings::stored();
		$messages = array();
		$status   = 'good';
		if ( empty( $s['enabled'] ) ) {
			$status     = 'recommended';
			$messages[] = __( 'The consent banner is turned off.', 'koz-consent-manager' );
		}
		if ( KOZCONSENT_Consent_Mode::site_kit_consent_mode() && ! KOZCONSENT_WP_Consent_API::is_active() ) {
			$status     = 'recommended';
			$messages[] = __( 'Site Kit Consent Mode is on, but the WP Consent API plugin is not active, so Site Kit does not receive the visitor\'s choice.', 'koz-consent-manager' );
		}
		$uncontrolled = KOZCONSENT_Scanner::uncontrolled();
		if ( ! empty( $uncontrolled ) ) {
			$status     = 'recommended';
			$messages[] = sprintf(
				/* translators: %s: comma-separated service names. */
				__( 'The last scan found services that load without waiting for consent: %s.', 'koz-consent-manager' ),
				implode( ', ', array_unique( array_column( $uncontrolled, 'name' ) ) )
			);
		}
		if ( empty( KOZCONSENT_Scanner::last() ) ) {
			$messages[] = __( 'Run the service scan in KOZ Consent Manager to check which services wait for consent.', 'koz-consent-manager' );
		}
		$description = '';
		foreach ( empty( $messages ) ? array( __( 'The banner is on and the scanned services wait for consent.', 'koz-consent-manager' ) ) : $messages as $message ) {
			$description .= '<p>' . esc_html( $message ) . '</p>';
		}
		return array(
			'label'       => 'good' === $status ? __( 'Visitor consent is set up', 'koz-consent-manager' ) : __( 'Visitor consent needs attention', 'koz-consent-manager' ),
			'status'      => $status,
			'badge'       => array( 'label' => __( 'Privacy', 'koz-consent-manager' ), 'color' => 'blue' ),
			'description' => $description,
			'actions'     => sprintf( '<p><a href="%1$s">%2$s</a></p>', esc_url( admin_url( 'admin.php?page=' . KOZCONSENT_Manager::PAGE_SLUG . '&tab=integrations' ) ), esc_html__( 'Open KOZ Consent Manager', 'koz-consent-manager' ) ),
			'test'        => 'kozconsent_setup',
		);
	}
}
