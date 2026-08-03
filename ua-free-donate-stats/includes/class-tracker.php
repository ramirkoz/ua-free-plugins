<?php
namespace UAFree\DonateStats;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Tracker {
	public const REST_NAMESPACE = 'ua-free-donate-stats/v1';

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 50 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'uafree_donate_stats_record_server_event', array( __CLASS__, 'record_server_event' ), 10, 4 );
	}

	public static function enqueue(): void {
		$settings = Plugin::settings();
		$context = self::context_for_request( $settings );

		if ( ! $context ) {
			return;
		}

		wp_enqueue_script(
			'ua-free-donate-stats',
			UAFREE_DONATE_STATS_URL . 'assets/frontend.js',
			array(),
			UAFREE_DONATE_STATS_VERSION,
			true
		);

		$copy_integration_enabled = false;
		if ( class_exists( '\\UAFree\\CopyTool\\Plugin' ) ) {
			$copy_settings = \UAFree\CopyTool\Plugin::settings();
			$copy_integration_enabled = ! empty( $copy_settings['enabled'] );
		}

		$config = array(
			'endpoint'           => esc_url_raw( rest_url( self::REST_NAMESPACE . '/event' ) ),
			'contextKey'         => $context['key'],
			'contextToken'       => self::context_token( $context['key'] ),
			'sessionMinutes'     => (int) $settings['session_minutes'],
			'copySelector'       => $settings['copy_selector'],
			'copyIntegration'    => $copy_integration_enabled,
			'donateSelector'     => $settings['donate_selector'],
			'paymentSelector'    => $settings['payment_selector'],
			'successSelector'    => $settings['success_selector'],
			'paymentHosts'       => array_values( $settings['payment_hosts'] ),
			'allowClientSuccess' => ! empty( $settings['allow_client_success'] ),
			'dataLayerEnabled'   => ! empty( $settings['data_layer_enabled'] ),
			'dataLayerEvent'     => $settings['data_layer_event'],
			'adAccountMode'      => $settings['ad_account_mode'],
			'consentGate'        => $settings['consent_gate'],
			'consentCategory'    => $settings['consent_category'],
			'pluginVersion'      => UAFREE_DONATE_STATS_VERSION,
		);

		$config = apply_filters( 'uafree_donate_stats_frontend_config', $config, $context, $settings );

		wp_localize_script(
			'ua-free-donate-stats',
			'UAFreeDonateStats',
			$config
		);
	}

	/**
	 * @param array<string,mixed>|null $settings
	 * @return array<string,string>|false
	 */
	public static function context_for_request( ?array $settings = null ) {
		$settings = $settings ?? Plugin::settings();

		if ( empty( $settings['enabled'] ) || is_admin() || wp_doing_ajax() ) {
			return false;
		}

		if (
			! empty( $settings['exclude_admins'] )
			&& is_user_logged_in()
			&& current_user_can( 'manage_options' )
		) {
			return false;
		}

		$context = false;
		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			if ( $post_id && in_array( $post_id, $settings['tracked_page_ids'], true ) ) {
				$context = array(
					'key'   => 'post:' . $post_id,
					'label' => get_the_title( $post_id ) ?: 'Post ' . $post_id,
				);
			}
		}

		$path = null;
		if ( ! $context && ! empty( $settings['include_static_translations'] ) ) {
			$path = self::request_path();
			foreach ( Plugin::translated_routes( (array) $settings['tracked_page_ids'] ) as $route ) {
				if ( self::path_matches( $path, (string) $route['path'] ) ) {
					$context = array(
						'key'   => 'post:' . (int) $route['post_id'],
						'label' => (string) $route['title'] . ' [' . strtoupper( (string) $route['language'] ) . ']',
					);
					break;
				}
			}
		}

		if ( ! $context ) {
			$path = is_string( $path ) ? $path : self::request_path();
			foreach ( $settings['tracked_paths'] as $pattern ) {
				if ( self::path_matches( $path, $pattern ) ) {
					$context = array(
						'key'   => 'path:' . substr( hash( 'sha256', $pattern ), 0, 16 ),
						'label' => $pattern,
					);
					break;
				}
			}
		}

		$context = apply_filters( 'uafree_donate_stats_request_context', $context, $settings );
		if ( ! is_array( $context ) || empty( $context['key'] ) ) {
			return false;
		}

		$key = self::sanitize_key( (string) $context['key'], 100 );
		if ( '' === $key ) {
			return false;
		}

		$context['key'] = $key;
		$context['label'] = isset( $context['label'] )
			? sanitize_text_field( (string) $context['label'] )
			: $key;

		$should_track = apply_filters( 'uafree_donate_stats_should_track', true, $context, $settings );
		return $should_track ? $context : false;
	}

	private static function request_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '/';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$path = '/' . ltrim( $path, '/' );
		return '/' === $path ? '/' : untrailingslashit( $path ) . '/';
	}

	public static function path_matches( string $path, string $pattern ): bool {
		$path = '/' === $path ? '/' : untrailingslashit( $path ) . '/';
		$pattern = '/' === $pattern ? '/' : untrailingslashit( $pattern ) . '/';
		$quoted = preg_quote( $pattern, '~' );
		$regex = '~^' . str_replace( '\*', '[^?#]*', $quoted ) . '$~u';
		return 1 === preg_match( $regex, $path );
	}

	private static function context_token( string $context_key, ?string $date = null ): string {
		$date = $date ?? gmdate( 'Y-m-d' );
		return hash_hmac( 'sha256', $date . '|' . $context_key, wp_salt( 'nonce' ) );
	}

	private static function valid_context_token( string $context_key, string $token ): bool {
		$today = self::context_token( $context_key );
		$yesterday = self::context_token( $context_key, gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ) );
		return hash_equals( $today, $token ) || hash_equals( $yesterday, $token );
	}

	public static function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/event',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'record_event' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'event_type'    => array( 'required' => true, 'type' => 'string' ),
					'target_key'    => array( 'required' => false, 'type' => 'string' ),
					'language'      => array( 'required' => false, 'type' => 'string' ),
					'session_id'    => array( 'required' => true, 'type' => 'string' ),
					'context_key'   => array( 'required' => true, 'type' => 'string' ),
					'context_token' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/confirm',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'confirm_donation' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function record_event( WP_REST_Request $request ): WP_REST_Response {
		$settings = Plugin::settings();
		if ( empty( $settings['enabled'] ) || self::is_prefetch_request() || self::is_probable_bot() ) {
			return new WP_REST_Response( array( 'accepted' => false ), 200 );
		}

		if (
			! empty( $settings['exclude_admins'] )
			&& is_user_logged_in()
			&& current_user_can( 'manage_options' )
		) {
			return new WP_REST_Response( array( 'accepted' => false ), 200 );
		}

		$event_type = sanitize_key( (string) $request->get_param( 'event_type' ) );
		$target_key = self::sanitize_key( (string) $request->get_param( 'target_key' ), 100 );
		$language = self::sanitize_language( (string) $request->get_param( 'language' ) );
		$session_id = strtolower( sanitize_text_field( (string) $request->get_param( 'session_id' ) ) );
		$context_key = self::sanitize_key( (string) $request->get_param( 'context_key' ), 100 );
		$context_token = strtolower( sanitize_text_field( (string) $request->get_param( 'context_token' ) ) );

		$allowed_events = self::allowed_events();
		if ( ! in_array( $event_type, $allowed_events, true ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid event.' ), 400 );
		}

		if ( 'donation_success' === $event_type && empty( $settings['allow_client_success'] ) ) {
			return new WP_REST_Response( array( 'accepted' => false ), 200 );
		}

		if (
			'' === $context_key
			|| ! preg_match( '/^[a-f0-9]{64}$/', $context_token )
			|| ! self::valid_context_token( $context_key, $context_token )
		) {
			return new WP_REST_Response( array( 'message' => 'Invalid context.' ), 403 );
		}

		if ( ! preg_match( '/^[a-z0-9-]{20,64}$/', $session_id ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid session.' ), 400 );
		}

		$session_hash = hash_hmac(
			'sha256',
			gmdate( 'Y-m-d' ) . '|' . $session_id,
			wp_salt( 'auth' )
		);

		if ( ! self::within_rate_limit( $session_hash, $context_key ) ) {
			return new WP_REST_Response( array( 'accepted' => false ), 200 );
		}

		Storage::store_session( $language, $context_key, $session_hash );
		$accepted = Storage::increment_event( $language, $context_key, $event_type, $target_key );

		if ( $accepted ) {
			do_action(
				'uafree_donate_stats_event_recorded',
				array(
					'event_type'  => $event_type,
					'target_key'  => $target_key,
					'context_key' => $context_key,
					'language'    => $language,
					'source'      => 'browser',
				)
			);
		}

		return new WP_REST_Response( array( 'accepted' => (bool) $accepted ), 200 );
	}


	public static function verify_confirmation_signature(
		string $body,
		string $signature,
		string $secret
	): bool {
		$signature = strtolower( trim( $signature ) );
		if ( str_starts_with( $signature, 'sha256=' ) ) {
			$signature = substr( $signature, 7 );
		}
		if (
			1 !== preg_match( '/^[a-f0-9]{64}$/', $signature )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $secret )
		) {
			return false;
		}
		return hash_equals(
			hash_hmac( 'sha256', $body, $secret ),
			$signature
		);
	}

	public static function confirmation_reference_hash(
		string $reference,
		string $secret
	): string {
		return hash_hmac( 'sha256', trim( $reference ), $secret );
	}

	public static function confirm_donation( WP_REST_Request $request ): WP_REST_Response {
		$settings = Plugin::settings();
		if ( 'webhook' !== (string) $settings['confirmation_mode'] ) {
			return new WP_REST_Response( array( 'message' => 'Confirmation webhook is disabled.' ), 403 );
		}

		$body = (string) $request->get_body();
		$signature = (string) $request->get_header( 'x-uafree-signature' );
		$secret = (string) $settings['confirmation_secret'];
		if ( ! self::verify_confirmation_signature( $body, $signature, $secret ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid signature.' ), 403 );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || 'donation_success' !== sanitize_key( (string) ( $data['event'] ?? '' ) ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid event.' ), 400 );
		}

		$reference = trim( sanitize_text_field( (string) ( $data['reference'] ?? '' ) ) );
		if ( '' === $reference || strlen( $reference ) > 200 ) {
			return new WP_REST_Response( array( 'message' => 'Invalid reference.' ), 400 );
		}

		$provider = self::sanitize_key( (string) ( $data['provider'] ?? 'payment' ), 40 ) ?: 'payment';
		$language = self::sanitize_language( (string) ( $data['language'] ?? 'und' ) );
		$context_key = self::sanitize_key(
			(string) ( $data['context_key'] ?? 'confirmed-donation' ),
			100
		) ?: 'confirmed-donation';

		$status = Storage::record_confirmation(
			self::confirmation_reference_hash( $reference, $secret ),
			$provider,
			$language,
			$context_key
		);

		if ( 'error' === $status ) {
			return new WP_REST_Response( array( 'message' => 'Could not record confirmation.' ), 500 );
		}

		return new WP_REST_Response(
			array(
				'accepted' => true,
				'duplicate' => 'duplicate' === $status,
			),
			'recorded' === $status ? 201 : 200
		);
	}


	public static function record_server_event(
		string $event_type,
		string $target_key = '',
		string $context_key = 'server',
		string $language = 'und'
	): bool {
		$event_type = sanitize_key( $event_type );
		if ( ! in_array( $event_type, self::allowed_events(), true ) ) {
			return false;
		}

		$target_key = self::sanitize_key( $target_key, 100 );
		$context_key = self::sanitize_key( $context_key, 100 ) ?: 'server';
		$language = self::sanitize_language( $language );

		$accepted = Storage::increment_event( $language, $context_key, $event_type, $target_key );
		if ( $accepted ) {
			do_action(
				'uafree_donate_stats_event_recorded',
				array(
					'event_type'  => $event_type,
					'target_key'  => $target_key,
					'context_key' => $context_key,
					'language'    => $language,
					'source'      => 'server',
				)
			);
		}
		return $accepted;
	}

	/**
	 * @return array<int,string>
	 */
	private static function allowed_events(): array {
		return (array) apply_filters(
			'uafree_donate_stats_allowed_events',
			array(
				'page_view',
				'donate_click',
				'payment_open',
				'copy_click',
				'donation_success',
				'external_click',
			)
		);
	}

	private static function sanitize_language( string $language ): string {
		$language = strtolower( str_replace( '_', '-', sanitize_text_field( $language ) ) );
		return preg_match( '/^[a-z]{2,3}(?:-[a-z]{2})?$/', $language ) ? $language : 'und';
	}

	private static function sanitize_key( string $value, int $max_length ): string {
		$value = strtolower( sanitize_text_field( $value ) );
		$value = preg_replace( '/[^a-z0-9._:-]+/', '-', $value );
		$value = trim( (string) $value, '-.' );
		return substr( $value, 0, $max_length );
	}

	private static function within_rate_limit( string $session_hash, string $context_key ): bool {
		$session_key = 'uafree_ds_rate_' . substr( $session_hash, 0, 32 );
		$session_count = (int) get_transient( $session_key );
		if ( $session_count >= 180 ) {
			return false;
		}

		$server_bucket = self::server_abuse_bucket( $context_key );
		$event_key = 'uafree_ds_events_' . $server_bucket;
		$new_session_key = 'uafree_ds_new_sessions_' . $server_bucket;
		$seen_key = 'uafree_ds_seen_' . $server_bucket . '_' . substr( $session_hash, 0, 16 );

		$event_count = (int) get_transient( $event_key );
		if ( $event_count >= 60 ) {
			return false;
		}

		$seen = (bool) get_transient( $seen_key );
		if ( ! $seen ) {
			$new_session_count = (int) get_transient( $new_session_key );
			if ( $new_session_count >= 4 ) {
				return false;
			}
			set_transient( $new_session_key, $new_session_count + 1, HOUR_IN_SECONDS );
			set_transient( $seen_key, 1, HOUR_IN_SECONDS );
		}

		set_transient( $event_key, $event_count + 1, HOUR_IN_SECONDS );
		set_transient( $session_key, $session_count + 1, HOUR_IN_SECONDS );
		return true;
	}

	private static function server_abuse_bucket( string $context_key ): string {
		$remote_address = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: 'unknown';

		return substr(
			hash_hmac(
				'sha256',
				gmdate( 'Y-m-d-H' ) . '|' . $remote_address . '|' . $context_key,
				wp_salt( 'auth' )
			),
			0,
			32
		);
	}

	private static function is_prefetch_request(): bool {
		$purpose = '';
		if ( isset( $_SERVER['HTTP_PURPOSE'] ) ) {
			$purpose = strtolower( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_PURPOSE'] ) ) );
		} elseif ( isset( $_SERVER['HTTP_SEC_PURPOSE'] ) ) {
			$purpose = strtolower( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_SEC_PURPOSE'] ) ) );
		} elseif ( isset( $_SERVER['HTTP_X_PURPOSE'] ) ) {
			$purpose = strtolower( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_PURPOSE'] ) ) );
		}

		return str_contains( $purpose, 'prefetch' ) || str_contains( $purpose, 'prerender' );
	}

	private static function is_probable_bot(): bool {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? strtolower( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) )
			: '';
		if ( '' === $user_agent ) {
			return true;
		}
		return (bool) preg_match(
			'/bot|crawler|spider|slurp|headless|preview|facebookexternalhit|whatsapp|telegrambot|discordbot|bingpreview|curl|wget|python-requests|go-http-client|monitoring|uptime/i',
			$user_agent
		);
	}

	public static function public_status( $value = null ): array {
		$settings = Plugin::settings();
		$counts = Storage::table_counts();

		$tracked_page_count = count( $settings['tracked_page_ids'] );
		$tracked_path_count = count( $settings['tracked_paths'] );
		$configured = ( $tracked_page_count + $tracked_path_count ) > 0;

		return array(
			'version'              => UAFREE_DONATE_STATS_VERSION,
			'enabled'              => ! empty( $settings['enabled'] ),
			'configured'           => $configured,
			'tracking_ready'       => ! empty( $settings['enabled'] ) && $configured,
			'ad_account_mode'      => $settings['ad_account_mode'],
			'data_layer_enabled'   => ! empty( $settings['data_layer_enabled'] ),
			'tracked_page_count'   => $tracked_page_count,
			'tracked_path_count'   => $tracked_path_count,
			'tracked_target_count' => $tracked_page_count + $tracked_path_count,
			'retention_days'       => (int) $settings['retention_days'],
			'confirmation_mode'    => (string) $settings['confirmation_mode'],
			'confirmation_ready'   => ! empty( Plugin::confirmation_status()['ready'] ),
			'last_confirmation_at' => (string) Plugin::confirmation_status()['last_confirmation_at'],
			'daily_rows'           => $counts['daily_rows'],
			'session_rows'         => $counts['session_rows'],
			'confirmation_rows'    => $counts['confirmation_rows'],
			'stores_personal_data' => false,
		);
	}
}
