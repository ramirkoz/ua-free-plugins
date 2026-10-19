<?php
/**
 * Confirmed donations straight from payment services.
 *
 * LiqPay: server callback (POST data + signature) verified with the merchant's
 * private key, signature = base64(sha1(private_key . data . private_key)).
 * PayPal: Instant Payment Notification verified by posting it back to PayPal.
 *
 * Only an HMAC hash of the payment reference is stored to prevent double counting.
 * Amount and currency are stored only when "Store donation amounts" is on.
 * No name, email, card or account data is stored.
 *
 * @package KOZ_Donate_Stats
 */

namespace ramirkz\kozdonate;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZDONATE_Providers {
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		foreach ( array( 'liqpay', 'paypal' ) as $provider ) {
			register_rest_route(
				KOZDONATE_Tracker::REST_NAMESPACE,
				'/' . $provider,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, $provider ),
					// Payment services call these without a WordPress session; requests are verified by signature or PayPal postback.
					'permission_callback' => '__return_true',
				)
			);
		}
	}

	public static function liqpay_url(): string {
		return rest_url( KOZDONATE_Tracker::REST_NAMESPACE . '/liqpay' );
	}

	public static function paypal_url(): string {
		return rest_url( KOZDONATE_Tracker::REST_NAMESPACE . '/paypal' );
	}

	private static function language( string $value ): string {
		$value = strtolower( str_replace( '_', '-', sanitize_text_field( $value ) ) );
		return preg_match( '/^[a-z]{2,3}(?:-[a-z]{2})?$/', $value ) ? $value : 'und';
	}

	private static function record( string $provider, string $reference, string $language, ?float $amount, string $currency ): string {
		$settings = KOZDONATE_Plugin::settings();
		return KOZDONATE_Storage::record_confirmation(
			KOZDONATE_Tracker::confirmation_reference_hash( $provider . ':' . $reference, (string) $settings['confirmation_secret'] ),
			$provider,
			self::language( $language ),
			'confirmed-donation',
			$amount,
			$currency
		);
	}

	public static function liqpay_signature( string $data, string $private_key ): string {
		return base64_encode( sha1( $private_key . $data . $private_key, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- LiqPay signature format.
	}

	public static function liqpay( WP_REST_Request $request ): WP_REST_Response {
		$settings = KOZDONATE_Plugin::settings();
		$key      = (string) $settings['liqpay_private_key'];
		if ( empty( $settings['liqpay_enabled'] ) || '' === $key ) {
			return new WP_REST_Response( array( 'accepted' => false, 'message' => 'LiqPay confirmations are not configured.' ), 403 );
		}
		$data      = (string) $request->get_param( 'data' );
		$signature = (string) $request->get_param( 'signature' );
		if ( '' === $data || '' === $signature || strlen( $data ) > 20000 || ! hash_equals( self::liqpay_signature( $data, $key ), $signature ) ) {
			return new WP_REST_Response( array( 'accepted' => false, 'message' => 'Invalid signature.' ), 403 );
		}
		$payload = json_decode( (string) base64_decode( $data, true ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- LiqPay sends base64 JSON.
		if ( ! is_array( $payload ) ) {
			return new WP_REST_Response( array( 'accepted' => false, 'message' => 'Invalid data.' ), 400 );
		}
		$status = sanitize_key( (string) ( $payload['status'] ?? '' ) );
		if ( 'success' !== $status ) {
			// Sandbox, failures, reversals and pending states are acknowledged but not counted.
			return new WP_REST_Response( array( 'accepted' => false, 'status' => $status ), 200 );
		}
		$reference = sanitize_text_field( (string) ( $payload['payment_id'] ?? $payload['order_id'] ?? '' ) );
		if ( '' === $reference ) {
			return new WP_REST_Response( array( 'accepted' => false, 'message' => 'Missing reference.' ), 400 );
		}
		$result = self::record(
			'liqpay',
			$reference,
			(string) ( $payload['language'] ?? 'und' ),
			isset( $payload['amount'] ) ? (float) $payload['amount'] : null,
			sanitize_text_field( (string) ( $payload['currency'] ?? '' ) )
		);
		return new WP_REST_Response( array( 'accepted' => 'error' !== $result, 'duplicate' => 'duplicate' === $result ), 'error' === $result ? 500 : 200 );
	}

	public static function paypal( WP_REST_Request $request ): WP_REST_Response {
		$settings = KOZDONATE_Plugin::settings();
		if ( empty( $settings['paypal_enabled'] ) ) {
			return new WP_REST_Response( array( 'accepted' => false, 'message' => 'PayPal confirmations are not configured.' ), 403 );
		}
		$raw = (string) $request->get_body();
		if ( '' === $raw || strlen( $raw ) > 20000 ) {
			return new WP_REST_Response( array( 'accepted' => false ), 400 );
		}
		$fields = array();
		wp_parse_str( $raw, $fields );
		$sandbox  = '1' === (string) ( $fields['test_ipn'] ?? '' );
		$endpoint = $sandbox ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' : 'https://ipnpb.paypal.com/cgi-bin/webscr';
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 20,
				'httpversion' => '1.1',
				'headers'     => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'        => 'cmd=_notify-validate&' . $raw,
			)
		);
		if ( is_wp_error( $response ) || 'VERIFIED' !== trim( (string) wp_remote_retrieve_body( $response ) ) ) {
			return new WP_REST_Response( array( 'accepted' => false, 'message' => 'Not verified by PayPal.' ), 200 );
		}
		if ( $sandbox || 'Completed' !== (string) ( $fields['payment_status'] ?? '' ) ) {
			return new WP_REST_Response( array( 'accepted' => false, 'status' => sanitize_text_field( (string) ( $fields['payment_status'] ?? '' ) ) ), 200 );
		}
		$receiver = strtolower( (string) $settings['paypal_receiver'] );
		if ( '' !== $receiver && $receiver !== strtolower( sanitize_email( (string) ( $fields['receiver_email'] ?? '' ) ) ) ) {
			return new WP_REST_Response( array( 'accepted' => false, 'message' => 'Other receiver.' ), 200 );
		}
		$reference = sanitize_text_field( (string) ( $fields['txn_id'] ?? '' ) );
		if ( '' === $reference ) {
			return new WP_REST_Response( array( 'accepted' => false ), 200 );
		}
		$result = self::record(
			'paypal',
			$reference,
			'und',
			isset( $fields['mc_gross'] ) ? (float) $fields['mc_gross'] : null,
			sanitize_text_field( (string) ( $fields['mc_currency'] ?? '' ) )
		);
		return new WP_REST_Response( array( 'accepted' => 'error' !== $result, 'duplicate' => 'duplicate' === $result ), 200 );
	}
}
