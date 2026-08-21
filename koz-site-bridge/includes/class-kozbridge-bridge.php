<?php
namespace ramirkz\kozbridge;

use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZBRIDGE_Bridge {
	private const API_NAMESPACE = 'kozbridge/v1';
	private const LEGACY_API_NAMESPACES = array( 'koz-site-bridge/v1', 'uafree-bridge/v1' );
	private const KEY_HASH_OPTION = 'kozbridge_api_key_hash';
	private const KEY_DATE_OPTION = 'kozbridge_api_key_created_at';
	private const LEGACY_LOG_OPTION = 'kozbridge_api_log';
	private const LEGACY_KEY_HASH_OPTIONS = array( 'uafree_bridge_api_key_hash', 'koz_site_bridge_api_key_hash' );
	private const LEGACY_KEY_DATE_OPTIONS = array( 'uafree_bridge_api_key_created_at', 'koz_site_bridge_api_key_created_at' );
	private const LEGACY_LOG_OPTIONS = array( 'uafree_bridge_api_log', 'koz_site_bridge_api_log' );
	private const RATE_LIMIT      = 120;
	private const RATE_TTL        = 3700;
	private const RATE_LOCK_TTL   = 5;
	private const CONTENT_LIMIT   = 50;
	private const HTTP_TIMEOUT    = 8;
	private const MAX_REDIRECTS   = 5;
	private const BODY_SAMPLE_MAX = 500;
	private const PAGE_BODY_MAX   = 2097152;
	private const AUDIT_LIMIT     = 5;
	private const SITEMAP_LIMIT   = 100;
	private const SITEMAP_URL_MAX = 1000;
	private const SITEMAP_CHILD_MAX = 50;
	private const LOG_TAIL_BYTES   = 262144;
	private const LOG_LINE_LIMIT   = 200;

	public static function migrate_legacy_data(): void {
		self::migrate_first_available_option( self::KEY_HASH_OPTION, self::LEGACY_KEY_HASH_OPTIONS );
		self::migrate_first_available_option( self::KEY_DATE_OPTION, self::LEGACY_KEY_DATE_OPTIONS );
		self::migrate_first_available_option( self::LEGACY_LOG_OPTION, self::LEGACY_LOG_OPTIONS );
	}

	private static function migrate_first_available_option( string $current, array $legacy_names ): void {
		if ( false !== get_option( $current, false ) ) {
			return;
		}
		foreach ( $legacy_names as $legacy_name ) {
			$value = get_option( (string) $legacy_name, false );
			if ( false !== $value ) {
				add_option( $current, $value, '', false );
				return;
			}
		}
	}

	public static function init(): void {
		self::migrate_legacy_data();
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'rest_headers' ), 10, 4 );
	}

	private static function language(): string {
		$locale = is_admin() && function_exists( 'get_user_locale' )
			? (string) get_user_locale()
			: ( function_exists( 'determine_locale' ) ? (string) determine_locale() : (string) get_locale() );
		$locale = strtolower( str_replace( '-', '_', $locale ) );
		$language = strtok( $locale, '_' );
		return in_array( $language, array( 'uk', 'zh', 'es', 'ar', 'id', 'pt', 'fr', 'ja', 'de', 'hi' ), true ) ? $language : 'en';
	}

	private static function tr( string $text ): string {
		$language = self::language();
		if ( 'en' === $language ) {
			return $text;
		}

		static $dictionaries = array();
		if ( ! array_key_exists( $language, $dictionaries ) ) {
			$file = KOZBRIDGE_DIR . 'assets/i18n/' . $language . '.json';
			$decoded = is_readable( $file ) ? wp_json_file_decode( $file, array( 'associative' => true ) ) : array();
			$dictionaries[ $language ] = is_array( $decoded ) ? $decoded : array();
		}

		return isset( $dictionaries[ $language ][ $text ] ) && is_string( $dictionaries[ $language ][ $text ] )
			? $dictionaries[ $language ][ $text ]
			: $text;
	}

	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! current_user_can( 'manage_options' ) || ! str_contains( $hook_suffix, 'koz-site-bridge' ) ) {
			return;
		}

		wp_enqueue_style(
			'kozbridge-admin',
			plugins_url( '../assets/kozbridge-admin.css', __FILE__ ),
			array(),
			KOZBRIDGE_VERSION
		);
		wp_enqueue_script(
			'kozbridge-admin',
			plugins_url( '../assets/kozbridge-admin.js', __FILE__ ),
			array(),
			KOZBRIDGE_VERSION,
			true
		);
		wp_localize_script(
			'kozbridge-admin',
			'KOZBRIDGEAdmin',
			array(
				'revokeConfirm' => self::tr( 'Revoke the key?' ),
				'copied'        => self::tr( 'API key copied.' ),
			)
		);
	}

	public static function admin_menu(): void {
		$parent = KOZBRIDGE_Suite_Registry::suite_page();

		add_submenu_page(
			$parent,
			self::tr( 'KOZ Site Bridge' ),
			self::tr( 'Site Bridge' ),
			'manage_options',
			'koz-site-bridge',
			array( __CLASS__, 'admin_page' )
		);
	}

	public static function admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plain_key = '';

		if ( isset( $_POST['kozbridge_generate'] ) ) {
			check_admin_referer( 'kozbridge_generate_action' );

			try {
				$plain_key = 'kozb_' . bin2hex( random_bytes( 32 ) );
			} catch ( \Throwable $error ) {
				$plain_key = 'kozb_' . wp_generate_password( 64, false, false );
			}

			update_option(
				self::KEY_HASH_OPTION,
				'v2:' . self::key_digest( $plain_key ),
				false
			);
			update_option( self::KEY_DATE_OPTION, current_time( 'mysql' ), false );
			delete_option( self::LEGACY_LOG_OPTION );
			self::delete_rate_limit_options();

			echo '<div class="notice notice-success"><p>' . esc_html( self::tr( 'New API key created. Copy it now: it will not be displayed again.' ) ) . '</p></div>';
		}

		if ( isset( $_POST['kozbridge_revoke'] ) ) {
			check_admin_referer( 'kozbridge_revoke_action' );
			delete_option( self::KEY_HASH_OPTION );
			delete_option( self::KEY_DATE_OPTION );
			delete_option( self::LEGACY_LOG_OPTION );
			self::delete_rate_limit_options();

			echo '<div class="notice notice-success"><p>' . esc_html( self::tr( 'API key revoked. External access is closed.' ) ) . '</p></div>';
		}

		$stored_hash = (string) get_option( self::KEY_HASH_OPTION, '' );
		$has_key     = '' !== $stored_hash;
		$legacy_key  = $has_key && ! str_starts_with( $stored_hash, 'v2:' );
		$key_created = (string) get_option( self::KEY_DATE_OPTION, '' );
		$schema_url  = rest_url( self::API_NAMESPACE . '/openapi' );
		$ping_url    = rest_url( self::API_NAMESPACE . '/ping' );
		$https_ok    = 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME );
		?>
		<div class="wrap kozbridge-wrap">
			<h1>KOZ Site Bridge <small><?php echo esc_html( KOZBRIDGE_VERSION ); ?></small></h1>

			<div class="notice notice-info inline">
				<p><strong><?php echo esc_html( self::tr( 'Read-only.' ) ); ?></strong>
				<?php echo esc_html( self::tr( 'The plugin does not modify pages, plugins, themes, users or site settings. Persistent records are limited to the API-key hash, creation date and automatically expiring rate-limit counters.' ) ); ?></p>
			</div>

			<?php if ( ! $https_ok ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( self::tr( 'API disabled: Home URL does not use HTTPS.' ) ); ?></p></div>
			<?php endif; ?>

			<?php if ( $legacy_key ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( self::tr( 'The active key was created by an older version. It still works, but should be rotated to the faster HMAC format.' ) ); ?></p></div>
			<?php endif; ?>

			<table class="widefat striped kozbridge-table">
				<tbody>
					<tr><th><?php echo esc_html( self::tr( 'Mode' ) ); ?></th><td><?php echo esc_html( self::tr( 'Read-only, privacy-safe' ) ); ?></td></tr>
					<tr><th><?php echo esc_html( self::tr( 'API key' ) ); ?></th><td><?php echo $has_key ? '<strong class="kozbridge-status kozbridge-status--active">' . esc_html( self::tr( 'active' ) ) . '</strong>' : '<strong class="kozbridge-status kozbridge-status--missing">' . esc_html( self::tr( 'not created' ) ) . '</strong>'; ?></td></tr>
					<tr><th><?php echo esc_html( self::tr( 'Key created' ) ); ?></th><td><?php echo esc_html( $key_created ?: '—' ); ?></td></tr>
					<tr><th>OpenAPI</th><td><code class="kozbridge-url"><?php echo esc_html( $schema_url ); ?></code></td></tr>
					<tr><th>Ping</th><td><code class="kozbridge-url"><?php echo esc_html( $ping_url ); ?></code></td></tr>
					<tr><th><?php echo esc_html( self::tr( 'Limit' ) ); ?></th><td><?php echo esc_html( (string) self::RATE_LIMIT ); ?> <?php echo esc_html( self::tr( 'successfully authenticated requests per hour.' ) ); ?></td></tr>
					<tr><th><?php echo esc_html( self::tr( 'API log' ) ); ?></th><td><?php echo esc_html( self::tr( 'Disabled. IP addresses, User-Agent strings and request history are not stored.' ) ); ?></td></tr>
				</tbody>
			</table>

			<?php if ( '' !== $plain_key ) : ?>
				<section class="kozbridge-key-card">
					<h2><?php echo esc_html( self::tr( 'API key is displayed once' ) ); ?></h2>
					<input type="text" readonly value="<?php echo esc_attr( $plain_key ); ?>" class="kozbridge-key-input" data-kozbridge-select-key>
					<div class="kozbridge-key-actions"><button type="button" class="button button-primary" data-kozbridge-copy-key><?php echo esc_html( self::tr( 'Copy API key' ) ); ?></button><strong><?php echo esc_html( self::tr( 'Do not send the key in chat.' ) ); ?></strong><span class="screen-reader-text" aria-live="polite" data-kozbridge-key-status></span></div>
				</section>
			<?php endif; ?>

			<div class="kozbridge-actions">
				<form method="post">
					<?php wp_nonce_field( 'kozbridge_generate_action' ); ?>
					<button class="button button-primary" name="kozbridge_generate" value="1"><?php echo esc_html( $has_key ? self::tr( 'Rotate API key' ) : self::tr( 'Create API key' ) ); ?></button>
				</form>

				<?php if ( $has_key ) : ?>
					<form method="post" data-kozbridge-revoke-form>
						<?php wp_nonce_field( 'kozbridge_revoke_action' ); ?>
						<button class="button" name="kozbridge_revoke" value="1"><?php echo esc_html( self::tr( 'Revoke API key' ) ); ?></button>
					</form>
				<?php endif; ?>
			</div>

			<h2><?php echo esc_html( self::tr( 'Private GPT connection' ) ); ?></h2>
			<ol class="kozbridge-steps">
				<li><?php echo esc_html( self::tr( 'Create a private GPT with “Only me” access.' ) ); ?></li>
				<li><?php echo esc_html( self::tr( 'Import the OpenAPI schema from the URL above.' ) ); ?></li>
				<li>Authentication: <strong>API key → Custom header</strong>.</li>
				<li>Header: <code>X-KOZ-Key</code>.</li>
				<li><?php echo esc_html( self::tr( 'Legacy endpoint and X-UAFree-Key remain accepted during migration.' ) ); ?></li>
				<li><?php echo esc_html( self::tr( 'Paste the key and test pingSiteBridge.' ) ); ?></li>
			</ol>

			<h2><?php echo esc_html( self::tr( 'Safe HTTP profiles' ) ); ?></h2>
			<p><?php echo esc_html( self::tr( 'HTTP probe accepts safe public frontend paths on the current site without a query string. WordPress administration, REST, login, executable and private system paths remain blocked. Profiles: default, googlebot, adsbot-google, adsbot-google-mobile.' ) ); ?></p>
		</div>
		<?php
	}

	public static function register_routes(): void {
		$routes = array(
			'/ping'        => 'ping',
			'/overview'    => 'overview',
			'/plugins'     => 'plugins',
			'/suite'       => 'suite',
			'/content'     => 'content',
			'/navigation'  => 'navigation',
			'/links'       => 'links',
			'/404-log'     => 'error_404_log',
			'/diagnostics'    => 'diagnostics',
			'/admin-error-log' => 'admin_error_log',
			'/sitemap'        => 'sitemap_inventory',
			'/page-audit'     => 'page_audit',
			'/rendered-audit' => 'rendered_audit',
			'/http-probe'     => 'http_probe',
		);

		foreach ( array_merge( array( self::API_NAMESPACE ), self::LEGACY_API_NAMESPACES ) as $namespace ) {
			register_rest_route(
				$namespace,
				'/openapi',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'openapi' ),
					'permission_callback' => '__return_true',
				)
			);

			foreach ( $routes as $route => $method ) {
				register_rest_route(
					$namespace,
					$route,
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( __CLASS__, $method ),
						'permission_callback' => array( __CLASS__, 'permission' ),
						'args'                => self::route_args( $method ),
					)
				);
			}
		}
	}

	private static function route_args( string $method ): array {
		if ( 'content' === $method ) {
			return array(
				'type' => array(
					'default'           => 'page',
					'sanitize_callback' => 'sanitize_key',
				),
				'page' => array(
					'default'           => 1,
					'sanitize_callback' => 'absint',
					'validate_callback' => static fn( $value ): bool => (int) $value >= 1,
				),
				'limit' => array(
					'default'           => 20,
					'sanitize_callback' => 'absint',
					'validate_callback' => static fn( $value ): bool => (int) $value >= 1 && (int) $value <= self::CONTENT_LIMIT,
				),
				'search' => array(
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
			);
		}

		if ( 'links' === $method ) {
			return array(
				'type' => array(
					'default'           => 'page',
					'sanitize_callback' => 'sanitize_key',
				),
				'page' => array(
					'default'           => 1,
					'sanitize_callback' => 'absint',
					'validate_callback' => static fn( $value ): bool => (int) $value >= 1,
				),
				'pages' => array(
					'default'           => 2,
					'sanitize_callback' => 'absint',
					'validate_callback' => static fn( $value ): bool => (int) $value >= 1 && (int) $value <= 3,
				),
				'max_links' => array(
					'default'           => 20,
					'sanitize_callback' => 'absint',
					'validate_callback' => static fn( $value ): bool => (int) $value >= 1 && (int) $value <= 30,
				),
				'check' => array(
					'default'           => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
				),
			);
		}

		if ( 'error_404_log' === $method ) {
			return array(
				'limit' => array(
					'default'           => 100,
					'sanitize_callback' => 'absint',
					'validate_callback' => static fn( $value ): bool => (int) $value >= 1 && (int) $value <= 200,
				),
			);
		}

		if ( 'sitemap_inventory' === $method ) {
			return array(
				'page' => array(
					'default'           => 1,
					'sanitize_callback' => 'absint',
					'validate_callback' => static fn( $value ): bool => (int) $value >= 1,
				),
				'limit' => array(
					'default'           => 50,
					'sanitize_callback' => 'absint',
					'validate_callback' => static fn( $value ): bool => (int) $value >= 1 && (int) $value <= self::SITEMAP_LIMIT,
				),
			);
		}

		if ( 'page_audit' === $method ) {
			return array(
				'path' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => array( __CLASS__, 'validate_probe_path' ),
				),
				'profile' => array(
					'default'           => 'default',
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => static fn( $value ): bool => array_key_exists( (string) $value, self::user_agent_profiles() ),
				),
			);
		}

		if ( 'rendered_audit' === $method ) {
			return array(
				'page' => array(
					'default'           => 1,
					'sanitize_callback' => 'absint',
					'validate_callback' => static fn( $value ): bool => (int) $value >= 1,
				),
				'limit' => array(
					'default'           => self::AUDIT_LIMIT,
					'sanitize_callback' => 'absint',
					'validate_callback' => static fn( $value ): bool => (int) $value >= 1 && (int) $value <= self::AUDIT_LIMIT,
				),
				'profile' => array(
					'default'           => 'default',
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => static fn( $value ): bool => array_key_exists( (string) $value, self::user_agent_profiles() ),
				),
			);
		}

		if ( 'admin_error_log' === $method ) {
			return array(
				'limit' => array(
					'default'           => 80,
					'sanitize_callback' => 'absint',
					'validate_callback' => static fn( $value ): bool => (int) $value >= 1 && (int) $value <= self::LOG_LINE_LIMIT,
				),
			);
		}

		if ( 'http_probe' === $method ) {
			return array(
				'path' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => array( __CLASS__, 'validate_probe_path' ),
				),
				'method' => array(
					'default'           => 'HEAD',
					'sanitize_callback' => static fn( $value ): string => strtoupper( sanitize_key( (string) $value ) ),
					'validate_callback' => static fn( $value ): bool => in_array( strtoupper( (string) $value ), array( 'GET', 'HEAD' ), true ),
				),
				'profile' => array(
					'default'           => 'default',
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => static fn( $value ): bool => array_key_exists( (string) $value, self::user_agent_profiles() ),
				),
				'include_sample' => array(
					'default'           => false,
					'sanitize_callback' => 'rest_sanitize_boolean',
				),
			);
		}

		return array();
	}

	public static function validate_probe_path( $value ): bool {
		return null !== self::normalise_probe_path( (string) $value );
	}

	public static function permission( WP_REST_Request $request ) {
		if ( 'https' !== wp_parse_url( home_url(), PHP_URL_SCHEME ) ) {
			return new WP_Error( 'kozbridge_https_required', 'Site Bridge requires HTTPS.', array( 'status' => 503 ) );
		}

		$stored_hash = (string) get_option( self::KEY_HASH_OPTION, '' );
		if ( '' === $stored_hash ) {
			return new WP_Error( 'kozbridge_not_configured', 'API key is not configured.', array( 'status' => 503 ) );
		}

		$key = trim( (string) $request->get_header( 'X-KOZ-Key' ) );
		if ( '' === $key ) {
			$key = trim( (string) $request->get_header( 'X-UAFree-Key' ) );
		}
		if ( strlen( $key ) < 32 || strlen( $key ) > 128 || ! self::verify_key( $key, $stored_hash ) ) {
			return new WP_Error( 'kozbridge_unauthorized', 'Invalid or missing API key.', array( 'status' => 401 ) );
		}

		$key_id = substr( self::key_digest( $key ), 0, 16 );
		$rate   = self::increment_rate_limit( $key_id );

		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		if ( $rate > self::RATE_LIMIT ) {
			return new WP_Error( 'kozbridge_rate_limit', 'Hourly API rate limit exceeded.', array( 'status' => 429 ) );
		}

		return true;
	}

	private static function key_digest( string $key ): string {
		return hash_hmac( 'sha256', $key, wp_salt( 'auth' ) );
	}

	private static function verify_key( string $key, string $stored_hash ): bool {
		if ( str_starts_with( $stored_hash, 'v2:' ) ) {
			$expected = substr( $stored_hash, 3 );
			return preg_match( '/^[a-f0-9]{64}$/', $expected ) && hash_equals( $expected, self::key_digest( $key ) );
		}

		return wp_check_password( $key, $stored_hash );
	}

	private static function increment_rate_limit( string $key_id ) {
		global $wpdb;

		$rate_key   = 'kozbridge_rl_' . $key_id;
		$bucket     = gmdate( 'YmdH' );
		$lock_name  = 'kozbridge_rate_' . substr( hash( 'sha256', $rate_key ), 0, 40 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MySQL advisory lock coordinates concurrent rate-limit updates.
		$lock_value = $wpdb->get_var(
			$wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, self::RATE_LOCK_TTL )
		);

		if ( '1' !== (string) $lock_value ) {
			return new WP_Error(
				'kozbridge_rate_busy',
				'Rate limiter is busy. Retry shortly.',
				array( 'status' => 503 )
			);
		}

		try {
			$state = get_transient( $rate_key );

			if (
				! is_array( $state )
				|| (string) ( $state['bucket'] ?? '' ) !== $bucket
				|| (int) ( $state['expires'] ?? 0 ) <= time()
			) {
				$state = array(
					'bucket'  => $bucket,
					'count'   => 0,
					'expires' => time() + self::RATE_TTL,
				);
			}

			$current = max( 0, (int) ( $state['count'] ?? 0 ) );

			// Saturating counter: after the limit, do not perform another persistent write.
			if ( $current >= self::RATE_LIMIT ) {
				return self::RATE_LIMIT + 1;
			}

			$state['count'] = $current + 1;

			if ( ! set_transient( $rate_key, $state, self::RATE_TTL ) ) {
				return new WP_Error(
					'kozbridge_rate_storage',
					'Rate limiter storage failed.',
					array( 'status' => 503 )
				);
			}

			return (int) $state['count'];
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Releases the advisory lock acquired above.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	private static function delete_rate_limit_options(): void {
		global $wpdb;
		foreach ( array( 'kozbridge_rl_', 'koz_site_bridge_rl_', 'uafree_bridge_rl_' ) as $prefix ) {
			$value_prefix   = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
			$timeout_prefix = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup scans only Site Bridge transient names.
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT option_name FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
					$wpdb->options,
					$value_prefix,
					$timeout_prefix
				)
			);
			foreach ( (array) $rows as $option_name ) {
				delete_option( (string) $option_name );
			}
		}
	}

	private static function response( array $data, int $status = 200 ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		return $response;
	}

	public static function rest_headers( bool $served, $result, WP_REST_Request $request, WP_REST_Server $server ): bool {
		$route = (string) $request->get_route();
		foreach ( array_merge( array( self::API_NAMESPACE ), self::LEGACY_API_NAMESPACES ) as $namespace ) {
			if ( str_starts_with( $route, '/' . $namespace . '/' ) ) {
				header( 'Cache-Control: no-store, private', true );
				header( 'Pragma: no-cache', true );
				header( 'X-Content-Type-Options: nosniff', true );
				break;
			}
		}
		return $served;
	}

	public static function ping( WP_REST_Request $request ): WP_REST_Response {
		return self::response(
			array(
				'ok'             => true,
				'plugin'         => 'KOZ Site Bridge',
				'version'        => KOZBRIDGE_VERSION,
				'mode'           => 'read-only',
				'site'           => home_url( '/' ),
				'server_time'    => current_time( 'c' ),
				'authentication' => 'X-KOZ-Key',
				'request_log'    => false,
			)
		);
	}

	public static function overview( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb, $wp_version;

		$theme       = wp_get_theme();
		$cron        = _get_cron_array();
		$cron_events = 0;
		$overdue     = 0;
		$now         = time();

		if ( is_array( $cron ) ) {
			foreach ( $cron as $timestamp => $hooks ) {
				foreach ( $hooks as $events ) {
					$cron_events += count( $events );
					if ( (int) $timestamp < $now ) {
						$overdue += count( $events );
					}
				}
			}
		}

		$post_types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			$counts = wp_count_posts( $type->name );
			$post_types[] = array(
				'name'      => $type->name,
				'label'     => $type->label,
				'published' => isset( $counts->publish ) ? (int) $counts->publish : 0,
			);
		}

		return self::response(
			array(
				'site' => array(
					'name'                => get_bloginfo( 'name' ),
					'description'         => get_bloginfo( 'description' ),
					'home_url'            => home_url( '/' ),
					'site_url'            => site_url( '/' ),
					'locale'              => get_locale(),
					'timezone'            => wp_timezone_string(),
					'permalink_structure' => (string) get_option( 'permalink_structure', '' ),
					'search_visibility'   => '1' === (string) get_option( 'blog_public' ) ? 'indexable' : 'discouraged',
					'https'               => 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ),
					'multisite'           => is_multisite(),
					'environment'         => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'unknown',
				),
				'system' => array(
					'wordpress'               => $wp_version,
					'php'                     => PHP_VERSION,
					'database'                => $wpdb->db_version(),
					'php_memory_limit'        => (string) ini_get( 'memory_limit' ),
					'wp_memory_limit'         => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : null,
					'wp_max_memory_limit'     => defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : null,
					'debug'                   => defined( 'WP_DEBUG' ) && WP_DEBUG,
					'debug_log'               => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
					'debug_display'           => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
					'cron_disabled'           => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
					'persistent_object_cache' => wp_using_ext_object_cache(),
				),
				'theme' => array(
					'name'        => $theme->get( 'Name' ),
					'version'     => $theme->get( 'Version' ),
					'stylesheet'  => get_stylesheet(),
					'template'    => get_template(),
					'child_theme' => is_child_theme(),
				),
				'public_content' => $post_types,
				'cron' => array(
					'total_events' => $cron_events,
					'overdue'      => $overdue,
				),
				'privacy_policy_url' => function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '',
			)
		);
	}

	public static function plugins( WP_REST_Request $request ): WP_REST_Response {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins      = get_plugins();
		$active       = (array) get_option( 'active_plugins', array() );
		$network      = is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array();
		$updates      = get_site_transient( 'update_plugins' );
		$result       = array();

		foreach ( $plugins as $file => $data ) {
			$status = 'inactive';
			if ( in_array( $file, $network, true ) ) {
				$status = 'network-active';
			} elseif ( in_array( $file, $active, true ) ) {
				$status = 'active';
			}

			$available = null;
			if ( is_object( $updates ) && isset( $updates->response[ $file ]->new_version ) ) {
				$available = sanitize_text_field( (string) $updates->response[ $file ]->new_version );
			}

			$result[] = array(
				'name'             => sanitize_text_field( (string) ( $data['Name'] ?? '' ) ),
				'version'          => sanitize_text_field( (string) ( $data['Version'] ?? '' ) ),
				'status'           => $status,
				'update_available' => $available,
				'requires_wp'      => sanitize_text_field( (string) ( $data['RequiresWP'] ?? '' ) ),
				'requires_php'     => sanitize_text_field( (string) ( $data['RequiresPHP'] ?? '' ) ),
			);
		}

		usort(
			$result,
			static function ( array $left, array $right ): int {
				$order = array( 'network-active' => 0, 'active' => 1, 'inactive' => 2 );
				$a = $order[ $left['status'] ] ?? 9;
				$b = $order[ $right['status'] ] ?? 9;
				return $a === $b ? strcasecmp( $left['name'], $right['name'] ) : $a <=> $b;
			}
		);

		return self::response(
			array(
				'count'   => count( $result ),
				'plugins' => $result,
			)
		);
	}

	public static function suite( WP_REST_Request $request ): WP_REST_Response {
		$rows = array();

		$registry = class_exists( '\ramirkz\kozbridge\KOZBRIDGE_Suite_Registry' )
			? '\ramirkz\kozbridge\KOZBRIDGE_Suite_Registry'
			: ( class_exists( '\UAFree\Suite\Registry' ) ? '\UAFree\Suite\Registry' : '' );

		if ( '' !== $registry ) {
			foreach ( $registry::status() as $component ) {
				$rows[] = array(
					'name'      => (string) $component['name'],
					'version'   => (string) $component['version'],
					'installed' => (bool) $component['installed'],
					'active'    => (bool) $component['active'],
				);
			}
		}

		return self::response(
			array(
				'count'      => count( $rows ),
				'components' => $rows,
			)
		);
	}

	public static function content( WP_REST_Request $request ): WP_REST_Response {
		$type   = sanitize_key( (string) $request->get_param( 'type' ) );
		$page   = max( 1, (int) $request->get_param( 'page' ) );
		$limit  = min( self::CONTENT_LIMIT, max( 1, (int) $request->get_param( 'limit' ) ) );
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );

		$public_types = get_post_types( array( 'public' => true ), 'names' );
		if ( ! in_array( $type, $public_types, true ) ) {
			return self::response( array( 'error' => 'Post type is not public or does not exist.' ), 400 );
		}

		$query = new WP_Query(
			array(
				'post_type'              => $type,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'paged'                  => $page,
				's'                      => $search,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => false,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$content = strip_shortcodes( (string) $post->post_content );
			$content = wp_strip_all_tags( $content, true );
			$content = preg_replace( '/\s+/u', ' ', $content ) ?? '';

			$items[] = array(
				'id'          => (int) $post->ID,
				'type'        => $post->post_type,
				'title'       => get_the_title( $post ),
				'url'         => get_permalink( $post ),
				'modified'    => get_post_modified_time( 'c', true, $post ),
				'excerpt'     => wp_trim_words( $content, 55, '…' ),
				'word_count'  => self::word_count( $content ),
				'seo'         => self::seo_meta( (int) $post->ID ),
			);
		}

		return self::response(
			array(
				'type'        => $type,
				'page'        => $page,
				'limit'       => $limit,
				'total'       => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
				'items'       => $items,
			)
		);
	}

	private static function word_count( string $text ): int {
		$words = preg_split( '/[\s\p{Z}]+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $words ) ? count( $words ) : 0;
	}

	private static function seo_meta( int $post_id ): array {
		$keys = array(
			'rank_math_title',
			'rank_math_description',
			'rank_math_canonical_url',
			'_yoast_wpseo_title',
			'_yoast_wpseo_metadesc',
			'_yoast_wpseo_canonical',
			'_seopress_titles_title',
			'_seopress_titles_desc',
			'_seopress_robots_canonical',
		);

		$result = array();
		foreach ( $keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$result[ $key ] = self::truncate_text( sanitize_text_field( (string) $value ), 500 );
			}
		}
		return $result;
	}

	public static function navigation( WP_REST_Request $request ): WP_REST_Response {
		$locations = get_nav_menu_locations();
		$result    = array();

		foreach ( $locations as $location => $menu_id ) {
			$menu = wp_get_nav_menu_object( $menu_id );
			if ( ! $menu ) {
				continue;
			}

			$items = array();
			foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $item ) {
				if ( 'publish' !== $item->post_status ) {
					continue;
				}

				$items[] = array(
					'id'        => (int) $item->ID,
					'parent_id' => (int) $item->menu_item_parent,
					'title'     => wp_strip_all_tags( (string) $item->title ),
					'url'       => esc_url_raw( (string) $item->url ),
					'target'    => in_array( $item->target, array( '', '_blank' ), true ) ? $item->target : '',
				);
			}

			$result[] = array(
				'location' => sanitize_key( (string) $location ),
				'name'     => sanitize_text_field( (string) $menu->name ),
				'items'    => $items,
			);
		}

		return self::response(
			array(
				'count' => count( $result ),
				'menus' => $result,
			)
		);
	}

	public static function links( WP_REST_Request $request ): WP_REST_Response {
		$type      = sanitize_key( (string) $request->get_param( 'type' ) );
		$page      = max( 1, (int) $request->get_param( 'page' ) );
		$pages     = min( 3, max( 1, (int) $request->get_param( 'pages' ) ) );
		$max_links = min( 30, max( 1, (int) $request->get_param( 'max_links' ) ) );
		$check     = rest_sanitize_boolean( $request->get_param( 'check' ) );

		$public_types = get_post_types( array( 'public' => true ), 'names' );
		if ( ! in_array( $type, $public_types, true ) ) {
			return self::response( array( 'error' => 'Post type is not public or does not exist.' ), 400 );
		}

		$query = new WP_Query(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => $pages,
				'paged'          => $page,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$result = array();
		$total  = 0;

		foreach ( $query->posts as $post ) {
			if ( $total >= $max_links ) {
				break;
			}

			$page_url = get_permalink( $post );
			$response = wp_safe_remote_get(
				$page_url,
				array(
					'timeout'             => self::HTTP_TIMEOUT,
					'redirection'         => 0,
					'limit_response_size' => 1048576,
					'user-agent'          => self::user_agent_profiles()['default'],
					'reject_unsafe_urls'  => true,
				)
			);

			if ( is_wp_error( $response ) ) {
				$result[] = array(
					'page'  => $page_url,
					'error' => sanitize_text_field( $response->get_error_message() ),
					'links' => array(),
				);
				continue;
			}

			$links = array();
			foreach ( self::extract_internal_urls( (string) wp_remote_retrieve_body( $response ), $page_url ) as $url ) {
				if ( $total >= $max_links ) {
					break;
				}

				$row = array( 'url' => $url );
				if ( $check ) {
					$safe_check_url = self::safe_probe_url( $url );

					if ( null === $safe_check_url ) {
						$row['check'] = array(
							'status'  => 0,
							'skipped' => true,
							'reason'  => 'Path is outside the safe public frontend scope.',
						);
					} else {
						$row['check'] = self::simple_request(
							$safe_check_url,
							'HEAD',
							self::user_agent_profiles()['default'],
							false
						);
					}
				}

				$links[] = $row;
				++$total;
			}

			$result[] = array(
				'page'  => $page_url,
				'links' => $links,
			);
		}

		return self::response(
			array(
				'type'        => $type,
				'page'        => $page,
				'pages_read'  => count( $result ),
				'links_found' => $total,
				'items'       => $result,
			)
		);
	}

	private static function extract_internal_urls( string $html, string $base_url ): array {
		$urls = array();

		if ( class_exists( '\DOMDocument' ) ) {
			$previous = libxml_use_internal_errors( true );
			$document = new \DOMDocument();
			$loaded   = $document->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR );
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );

			if ( $loaded ) {
				foreach ( $document->getElementsByTagName( 'a' ) as $node ) {
					if ( ! $node->hasAttribute( 'href' ) ) {
						continue;
					}

					$url = self::absolute_url( html_entity_decode( $node->getAttribute( 'href' ), ENT_QUOTES, 'UTF-8' ), $base_url );
					if ( $url && self::same_origin( $url ) ) {
						$urls[ $url ] = true;
					}
				}
			}
		}

		if ( empty( $urls ) && preg_match_all( '#<a\b([^>]*)>#isu', $html, $matches ) ) {
			foreach ( (array) $matches[1] as $raw_attrs ) {
				$attrs = self::parse_html_attributes( (string) $raw_attrs );
				$href  = trim( (string) ( $attrs['href'] ?? '' ) );
				if ( '' === $href ) {
					continue;
				}
				$url = self::absolute_url( html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $base_url );
				if ( $url && self::same_origin( $url ) ) {
					$urls[ $url ] = true;
				}
			}
		}

		return array_keys( $urls );
	}

	private static function absolute_url( string $url, string $base_url ): ?string {
		$url = trim( $url );
		if ( '' === $url || str_starts_with( $url, '#' ) ) {
			return null;
		}

		$lower = strtolower( $url );
		foreach ( array( 'mailto:', 'tel:', 'javascript:', 'data:', 'blob:' ) as $scheme ) {
			if ( str_starts_with( $lower, $scheme ) ) {
				return null;
			}
		}

		if ( preg_match( '#^https?://#i', $url ) ) {
			$clean = esc_url_raw( $url );
			return '' !== $clean ? $clean : null;
		}

		$base = wp_parse_url( $base_url );
		if ( ! is_array( $base ) || empty( $base['scheme'] ) || empty( $base['host'] ) ) {
			return null;
		}

		$origin = $base['scheme'] . '://' . $base['host'];
		if ( isset( $base['port'] ) ) {
			$origin .= ':' . (int) $base['port'];
		}

		if ( str_starts_with( $url, '//' ) ) {
			return null;
		}

		if ( str_starts_with( $url, '/' ) ) {
			return esc_url_raw( $origin . $url );
		}

		$base_path = isset( $base['path'] ) ? (string) $base['path'] : '/';
		$directory = trailingslashit( dirname( $base_path ) );
		return esc_url_raw( $origin . $directory . $url );
	}

	private static function same_origin( string $url ): bool {
		$home = wp_parse_url( home_url( '/' ) );
		$test = wp_parse_url( $url );

		if ( ! is_array( $home ) || ! is_array( $test ) ) {
			return false;
		}

		$home_port = isset( $home['port'] ) ? (int) $home['port'] : ( 'https' === ( $home['scheme'] ?? '' ) ? 443 : 80 );
		$test_port = isset( $test['port'] ) ? (int) $test['port'] : ( 'https' === ( $test['scheme'] ?? '' ) ? 443 : 80 );

		return strtolower( (string) ( $home['scheme'] ?? '' ) ) === strtolower( (string) ( $test['scheme'] ?? '' ) )
			&& strtolower( (string) ( $home['host'] ?? '' ) ) === strtolower( (string) ( $test['host'] ?? '' ) )
			&& $home_port === $test_port;
	}

	public static function error_404_log( WP_REST_Request $request ): WP_REST_Response {
		$limit = min( 200, max( 1, (int) $request->get_param( 'limit' ) ) );
		$raw   = get_option( 'uafree_404_guard_log', array() );
		$rows  = array();

		if ( is_array( $raw ) ) {
			foreach ( $raw as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$path_fingerprint = strtolower( (string) ( $row['path_fingerprint'] ?? '' ) );
				if ( ! preg_match( '/^[a-f0-9]{16}$/', $path_fingerprint ) ) {
					continue;
				}

				$query_fingerprints = array();
				foreach ( (array) ( $row['query_key_fingerprints'] ?? array() ) as $fingerprint ) {
					$fingerprint = strtolower( (string) $fingerprint );
					if ( preg_match( '/^[a-f0-9]{16}$/', $fingerprint ) ) {
						$query_fingerprints[] = $fingerprint;
					}
				}

				$rows[] = array(
					'status'                 => in_array( (string) ( $row['status'] ?? '404' ), array( '404', '410' ), true ) ? (string) $row['status'] : '404',
					'kind'                   => in_array( (string) ( $row['kind'] ?? 'unknown' ), array( 'human', 'bot', 'unknown' ), true ) ? (string) $row['kind'] : 'unknown',
					'path_fingerprint'       => $path_fingerprint,
					'query_key_fingerprints' => array_values( array_unique( $query_fingerprints ) ),
					'count'                  => max( 0, absint( $row['count'] ?? 0 ) ),
					'sample_denominator'     => max( 1, absint( $row['sample_denominator'] ?? 1 ) ),
					'first_seen'             => sanitize_text_field( (string) ( $row['first_seen'] ?? '' ) ),
					'last_seen'              => sanitize_text_field( (string) ( $row['last_seen'] ?? '' ) ),
					'referrer_scope'         => in_array( (string) ( $row['referrer_scope'] ?? 'none' ), array( 'none', 'same-site', 'external' ), true ) ? (string) $row['referrer_scope'] : 'none',
					'source'                 => sanitize_key( (string) ( $row['source'] ?? 'unknown' ) ),
				);
			}
		}

		usort(
			$rows,
			static fn( array $a, array $b ): int => strcmp( $b['last_seen'], $a['last_seen'] )
		);

		$rows = array_slice( $rows, 0, $limit );

		return self::response(
			array(
				'available'  => false !== get_option( 'uafree_404_guard_log', false ),
				'count'      => count( $rows ),
				'privacy'    => 'No raw path, query name, query value, IP, User-Agent or referrer is returned.',
				'rows'       => $rows,
			)
		);
	}

	public static function diagnostics( WP_REST_Request $request ): WP_REST_Response {
		$checks = array(
			'home'        => '/',
			'robots'      => '/robots.txt',
			'wp_sitemap'  => '/wp-sitemap.xml',
			'llms_txt'    => '/llms.txt',
			'ai_manifest' => '/.well-known/koz-ai-manifest.json',
			'not_found'   => '/__kozbridge_404_probe__/',
		);

		$result = array();
		foreach ( $checks as $name => $path ) {
			$url = home_url( $path );
			$result[ $name ] = array_merge(
				array( 'path' => $path ),
				self::simple_request( $url, 'HEAD', self::user_agent_profiles()['default'], false )
			);
		}

		$issues = array();

		if ( empty( get_option( 'permalink_structure' ) ) ) {
			$issues[] = array( 'severity' => 'medium', 'code' => 'plain_permalinks', 'message' => 'Pretty permalinks are disabled.' );
		}
		if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
			$issues[] = array( 'severity' => 'high', 'code' => 'debug_display', 'message' => 'WP_DEBUG_DISPLAY is enabled.' );
		}
		if ( isset( $result['not_found']['status'] ) && 404 !== (int) $result['not_found']['status'] ) {
			$issues[] = array( 'severity' => 'high', 'code' => 'invalid_404_status', 'message' => 'A random missing path does not return HTTP 404.' );
		}
		if ( isset( $result['home']['duration_ms'] ) && (int) $result['home']['duration_ms'] > 2500 ) {
			$issues[] = array( 'severity' => 'medium', 'code' => 'slow_home', 'message' => 'Homepage response exceeded 2500 ms during the internal probe.' );
		}

		return self::response(
			array(
				'generated_at' => current_time( 'c' ),
				'checks'       => $result,
				'issues'       => $issues,
			)
		);
	}


	/**
	 * Return recent sanitized lines from fixed WordPress/PHP error-log candidates.
	 *
	 * This endpoint is authenticated, read-only and intentionally does not accept
	 * a filesystem path from the caller.
	 */
	public static function admin_error_log( WP_REST_Request $request ): WP_REST_Response {
		$limit = min( self::LOG_LINE_LIMIT, max( 1, (int) $request->get_param( 'limit' ) ) );
		$sources = array();

		foreach ( self::error_log_candidates() as $label => $candidate ) {
			$path = self::normalise_error_log_candidate( $candidate );
			if ( null === $path ) {
				continue;
			}

			$key = self::safe_log_path( $path );
			if ( isset( $sources[ $key ] ) ) {
				continue;
			}

			$lines = self::read_log_tail( $path, $limit );
			$sources[ $key ] = array(
				'label'        => sanitize_key( (string) $label ),
				'path'         => $key,
				'readable'     => is_readable( $path ),
				'size_bytes'   => is_file( $path ) ? max( 0, (int) filesize( $path ) ) : 0,
				'last_modified'=> is_file( $path ) ? gmdate( 'c', (int) filemtime( $path ) ) : '',
				'line_count'   => count( $lines ),
				'lines'        => $lines,
			);
		}

		return self::response(
			array(
				'generated_at' => current_time( 'c' ),
				'mode'         => 'read-only',
				'privacy'      => 'Credentials, cookies, email addresses, query strings and local filesystem prefixes are redacted. No arbitrary log path is accepted.',
				'runtime'      => array(
					'wp_debug'          => defined( 'WP_DEBUG' ) ? (bool) WP_DEBUG : false,
					'wp_debug_log'      => defined( 'WP_DEBUG_LOG' ) ? ( is_string( WP_DEBUG_LOG ) ? self::safe_log_path( (string) WP_DEBUG_LOG ) : (bool) WP_DEBUG_LOG ) : false,
					'wp_debug_display'  => defined( 'WP_DEBUG_DISPLAY' ) ? (bool) WP_DEBUG_DISPLAY : null,
					'php_log_errors'    => filter_var( ini_get( 'log_errors' ), FILTER_VALIDATE_BOOLEAN ),
					'php_error_log'     => self::safe_log_path( (string) ini_get( 'error_log' ) ),
					'memory_limit'      => sanitize_text_field( (string) ini_get( 'memory_limit' ) ),
					'max_execution_time'=> max( 0, (int) ini_get( 'max_execution_time' ) ),
				),
				'source_count'  => count( $sources ),
				'sources'       => array_values( $sources ),
			)
		);
	}

	private static function error_log_candidates(): array {
		$candidates = array(
			'wp_debug'       => trailingslashit( WP_CONTENT_DIR ) . 'debug.log',
			'php_error_log'  => (string) ini_get( 'error_log' ),
			'site_error_log' => trailingslashit( ABSPATH ) . 'error_log',
		);

		$parent = dirname( untrailingslashit( ABSPATH ) );
		if ( '' !== $parent && $parent !== untrailingslashit( ABSPATH ) ) {
			$candidates['parent_error_log'] = trailingslashit( $parent ) . 'error_log';
		}

		return $candidates;
	}

	private static function normalise_error_log_candidate( string $candidate ): ?string {
		$candidate = trim( $candidate );
		if ( '' === $candidate || in_array( strtolower( $candidate ), array( 'syslog', 'stderr', 'error_log' ), true ) ) {
			return null;
		}
		if ( str_contains( $candidate, '://' ) || str_contains( $candidate, "\0" ) ) {
			return null;
		}
		if ( ! self::is_absolute_path( $candidate ) ) {
			$candidate = trailingslashit( ABSPATH ) . ltrim( $candidate, '/\\' );
		}
		$real = realpath( $candidate );
		return is_string( $real ) && is_file( $real ) ? $real : null;
	}

	private static function is_absolute_path( string $path ): bool {
		return str_starts_with( $path, '/' ) || 1 === preg_match( '/^[A-Za-z]:[\\\\\/]/', $path );
	}

	private static function read_log_tail( string $path, int $limit ): array {
		if ( ! is_readable( $path ) || ! is_file( $path ) ) {
			return array();
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		global $wp_filesystem;
		if ( ! WP_Filesystem() || ! is_object( $wp_filesystem ) ) {
			return array();
		}

		$data = $wp_filesystem->get_contents( $path );
		if ( false === $data || ! is_string( $data ) ) {
			return array();
		}

		if ( strlen( $data ) > self::LOG_TAIL_BYTES ) {
			$data = substr( $data, -self::LOG_TAIL_BYTES );
		}

		$lines = preg_split( '/\R/u', $data ) ?: array();
		$lines = array_values( array_filter( array_map( 'trim', $lines ), static fn( string $line ): bool => '' !== $line ) );
		$lines = array_slice( $lines, -$limit );
		return array_values( array_map( array( __CLASS__, 'redact_log_line' ), $lines ) );
	}

	private static function redact_log_line( string $line ): string {
		$line = wp_check_invalid_utf8( $line, true );
		$replacements = array(
			untrailingslashit( WP_CONTENT_DIR ) => '<WP_CONTENT>',
			untrailingslashit( ABSPATH )        => '<ABSPATH>',
		);
		$parent = dirname( untrailingslashit( ABSPATH ) );
		if ( '' !== $parent && $parent !== untrailingslashit( ABSPATH ) ) {
			$replacements[ $parent ] = '<SITE_PARENT>';
		}
		foreach ( $replacements as $from => $to ) {
			if ( '' !== $from ) {
				$line = str_replace( array( $from, wp_normalize_path( $from ) ), $to, $line );
			}
		}

		$line = preg_replace( '/\b(Authorization|Cookie|Set-Cookie|X-KOZ-Key|X-UAFree-Key|api[_ -]?key|token|secret)\b\s*[:=]\s*[^\s,;]+/i', '$1=[redacted]', $line ) ?? $line;
		$line = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted-email]', $line ) ?? $line;
		$line = preg_replace_callback(
			'#https?://[^\s"\']+#i',
			static function ( array $match ): string {
				$url = (string) $match[0];
				$question = strpos( $url, '?' );
				return false === $question ? $url : substr( $url, 0, $question ) . '?[redacted]';
			},
			$line
		) ?? $line;
		$line = preg_replace( '/\b[A-Fa-f0-9]{48,}\b/', '[redacted-token]', $line ) ?? $line;
		$line = preg_replace( '/\b[A-Za-z0-9_\-]{64,}\b/', '[redacted-token]', $line ) ?? $line;
		return self::truncate_text( $line, 2000 );
	}

	private static function safe_log_path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path ) {
			return '';
		}
		$path = wp_normalize_path( $path );
		$content = wp_normalize_path( untrailingslashit( WP_CONTENT_DIR ) );
		$root = wp_normalize_path( untrailingslashit( ABSPATH ) );
		$parent = wp_normalize_path( dirname( $root ) );
		if ( '' !== $content && str_starts_with( $path, $content ) ) {
			return '<WP_CONTENT>' . substr( $path, strlen( $content ) );
		}
		if ( '' !== $root && str_starts_with( $path, $root ) ) {
			return '<ABSPATH>' . substr( $path, strlen( $root ) );
		}
		if ( '' !== $parent && str_starts_with( $path, $parent ) ) {
			return '<SITE_PARENT>' . substr( $path, strlen( $parent ) );
		}
		return basename( $path );
	}

	public static function sitemap_inventory( WP_REST_Request $request ): WP_REST_Response {
		$page  = max( 1, (int) $request->get_param( 'page' ) );
		$limit = min( self::SITEMAP_LIMIT, max( 1, (int) $request->get_param( 'limit' ) ) );
		$data  = self::collect_sitemap_urls();

		if ( is_wp_error( $data ) ) {
			return self::response(
				array(
					'error' => sanitize_text_field( $data->get_error_message() ),
				),
				502
			);
		}

		$total       = count( $data );
		$total_pages = max( 1, (int) ceil( $total / $limit ) );
		$offset      = ( $page - 1 ) * $limit;
		$items       = array_slice( $data, $offset, $limit );

		return self::response(
			array(
				'source'      => '/wp-sitemap.xml',
				'page'        => $page,
				'limit'       => $limit,
				'total'       => $total,
				'total_pages' => $total_pages,
				'items'       => array_values( $items ),
			)
		);
	}

	public static function page_audit( WP_REST_Request $request ): WP_REST_Response {
		$path     = self::normalise_probe_path( (string) $request->get_param( 'path' ) );
		$profile  = sanitize_key( (string) $request->get_param( 'profile' ) );
		$profiles = self::user_agent_profiles();

		if ( null === $path ) {
			return self::response( array( 'error' => 'Only a safe same-site public path is allowed.' ), 400 );
		}
		if ( ! isset( $profiles[ $profile ] ) ) {
			return self::response( array( 'error' => 'Unknown User-Agent profile.' ), 400 );
		}

		return self::response( self::audit_url( home_url( $path ), $profiles[ $profile ], false ) );
	}

	public static function rendered_audit( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$limit    = min( self::AUDIT_LIMIT, max( 1, (int) $request->get_param( 'limit' ) ) );
		$profile  = sanitize_key( (string) $request->get_param( 'profile' ) );
		$profiles = self::user_agent_profiles();

		if ( ! isset( $profiles[ $profile ] ) ) {
			return self::response( array( 'error' => 'Unknown User-Agent profile.' ), 400 );
		}

		$urls = self::collect_sitemap_urls();
		if ( is_wp_error( $urls ) ) {
			return self::response( array( 'error' => sanitize_text_field( $urls->get_error_message() ) ), 502 );
		}

		$total       = count( $urls );
		$total_pages = max( 1, (int) ceil( $total / $limit ) );
		$offset      = ( $page - 1 ) * $limit;
		$batch       = array_slice( $urls, $offset, $limit );
		$items       = array();

		foreach ( $batch as $url ) {
			$items[] = self::audit_url( (string) $url, $profiles[ $profile ], true );
		}

		return self::response(
			array(
				'page'        => $page,
				'limit'       => $limit,
				'total'       => $total,
				'total_pages' => $total_pages,
				'profile'     => $profile,
				'items'       => $items,
			)
		);
	}

	private static function collect_sitemap_urls() {
		$root_url = home_url( '/wp-sitemap.xml' );
		$root     = self::fetch_sitemap_body( $root_url );
		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$root_locations = self::extract_sitemap_locations( $root );
		$urls           = array();
		$children       = array();

		foreach ( $root_locations as $location ) {
			if ( self::is_safe_sitemap_url( $location ) && self::looks_like_sitemap_url( $location ) ) {
				$children[ $location ] = true;
			} elseif ( self::is_safe_public_url( $location ) ) {
				$urls[ $location ] = true;
			}
		}

		foreach ( array_slice( array_keys( $children ), 0, self::SITEMAP_CHILD_MAX ) as $child_url ) {
			$body = self::fetch_sitemap_body( $child_url );
			if ( is_wp_error( $body ) ) {
				continue;
			}
			foreach ( self::extract_sitemap_locations( $body ) as $location ) {
				if ( self::is_safe_public_url( $location ) && ! self::looks_like_sitemap_url( $location ) ) {
					$urls[ $location ] = true;
					if ( count( $urls ) >= self::SITEMAP_URL_MAX ) {
						break 2;
					}
				}
			}
		}

		$result = array_keys( $urls );
		sort( $result, SORT_STRING );
		return $result;
	}

	private static function fetch_sitemap_body( string $url ) {
		if ( ! self::same_origin( $url ) || ! self::is_safe_sitemap_url( $url ) ) {
			return new WP_Error( 'kozbridge_sitemap_url', 'Unsafe sitemap URL.' );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => self::HTTP_TIMEOUT,
				'redirection'         => 0,
				'limit_response_size' => self::PAGE_BODY_MAX,
				'user-agent'          => self::user_agent_profiles()['default'],
				'reject_unsafe_urls'  => true,
				'headers'             => array( 'Accept' => 'application/xml,text/xml;q=0.9,*/*;q=0.1' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'kozbridge_sitemap_status', 'Sitemap did not return HTTP 200.' );
		}

		return (string) wp_remote_retrieve_body( $response );
	}

	private static function extract_sitemap_locations( string $xml ): array {
		$locations = array();
		if ( preg_match_all( '#<loc>(.*?)</loc>#isu', $xml, $matches ) ) {
			foreach ( (array) $matches[1] as $raw ) {
				$url = esc_url_raw( html_entity_decode( wp_strip_all_tags( (string) $raw ), ENT_QUOTES | ENT_XML1, 'UTF-8' ) );
				if ( '' !== $url && self::same_origin( $url ) ) {
					$locations[ $url ] = true;
				}
			}
		}
		return array_keys( $locations );
	}

	private static function looks_like_sitemap_url( string $url ): bool {
		$path = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		return str_ends_with( $path, '.xml' ) && str_contains( $path, 'sitemap' );
	}

	private static function is_safe_sitemap_url( string $url ): bool {
		if ( ! self::same_origin( $url ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}
		$path = strtolower( rawurldecode( (string) ( $parts['path'] ?? '' ) ) );
		return '/wp-sitemap.xml' === $path || ( str_starts_with( $path, '/wp-sitemap-' ) && str_ends_with( $path, '.xml' ) );
	}

	private static function is_safe_public_url( string $url ): bool {
		if ( ! self::same_origin( $url ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}
		return null !== self::normalise_probe_path( (string) ( $parts['path'] ?? '/' ) );
	}

	private static function audit_url( string $url, string $user_agent, bool $from_sitemap ): array {
		$fetch = self::fetch_public_page( $url, $user_agent );
		if ( isset( $fetch['error'] ) ) {
			return array(
				'url'    => $url,
				'status' => (int) ( $fetch['status'] ?? 0 ),
				'error'  => (string) $fetch['error'],
				'chain'  => (array) ( $fetch['chain'] ?? array() ),
				'issues' => array(
					array( 'severity' => 'high', 'code' => 'fetch_failed', 'message' => 'Rendered page could not be fetched safely.' ),
				),
			);
		}

		$final_url = (string) ( $fetch['final_url'] ?? $url );
		$status    = (int) ( $fetch['status'] ?? 0 );
		$headers   = (array) ( $fetch['headers'] ?? array() );
		$html      = (string) ( $fetch['body'] ?? '' );
		$analysis  = self::analyse_html( $html, $final_url );
		$issues    = self::audit_issues( $status, $headers, $analysis, $from_sitemap );

		return array(
			'url'          => $url,
			'final_url'    => $final_url,
			'status'       => $status,
			'duration_ms'  => (int) ( $fetch['duration_ms'] ?? 0 ),
			'headers'      => $headers,
			'redirects'    => max( 0, count( (array) ( $fetch['chain'] ?? array() ) ) - 1 ),
			'chain'        => (array) ( $fetch['chain'] ?? array() ),
			'from_sitemap' => $from_sitemap,
			'analysis'     => $analysis,
			'issues'       => $issues,
		);
	}

	private static function fetch_public_page( string $url, string $user_agent ): array {
		if ( ! self::is_safe_public_url( $url ) ) {
			return array( 'status' => 0, 'error' => 'URL is outside the safe public frontend scope.', 'chain' => array() );
		}

		$chain   = array();
		$current = $url;
		$total_started = microtime( true );

		for ( $step = 0; $step <= self::MAX_REDIRECTS; $step++ ) {
			$started  = microtime( true );
			$response = wp_safe_remote_get(
				$current,
				array(
					'timeout'             => self::HTTP_TIMEOUT,
					'redirection'         => 0,
					'limit_response_size' => self::PAGE_BODY_MAX,
					'user-agent'          => $user_agent,
					'reject_unsafe_urls'  => true,
					'headers'             => array( 'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.1' ),
				)
			);
			$duration = (int) round( ( microtime( true ) - $started ) * 1000 );

			if ( is_wp_error( $response ) ) {
				return array(
					'status'      => 0,
					'error'       => sanitize_text_field( $response->get_error_message() ),
					'chain'       => $chain,
					'duration_ms' => (int) round( ( microtime( true ) - $total_started ) * 1000 ),
				);
			}

			$status   = (int) wp_remote_retrieve_response_code( $response );
			$headers  = self::selected_headers( $response );
			$location = (string) ( $headers['location'] ?? '' );
			$chain[]  = array(
				'url'         => $current,
				'status'      => $status,
				'duration_ms' => $duration,
				'location'    => $location,
			);

			if ( $status >= 300 && $status < 400 && '' !== $location ) {
				$next_absolute = self::absolute_url( $location, $current );
				if ( null === $next_absolute || ! self::is_safe_public_url( $next_absolute ) ) {
					return array(
						'status'      => $status,
						'error'       => 'Redirect target is outside the safe public frontend scope.',
						'chain'       => $chain,
						'duration_ms' => (int) round( ( microtime( true ) - $total_started ) * 1000 ),
					);
				}
				$current = $next_absolute;
				continue;
			}

			return array(
				'status'      => $status,
				'final_url'   => $current,
				'headers'     => $headers,
				'body'        => (string) wp_remote_retrieve_body( $response ),
				'chain'       => $chain,
				'duration_ms' => (int) round( ( microtime( true ) - $total_started ) * 1000 ),
			);
		}

		return array(
			'status'      => 0,
			'error'       => 'Redirect limit exceeded.',
			'chain'       => $chain,
			'duration_ms' => (int) round( ( microtime( true ) - $total_started ) * 1000 ),
		);
	}

	private static function selected_headers( $response ): array {
		$headers = array();
		foreach ( array( 'location', 'content-type', 'server', 'cf-ray', 'cf-cache-status', 'cf-mitigated', 'x-robots-tag', 'retry-after' ) as $name ) {
			$value = wp_remote_retrieve_header( $response, $name );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$headers[ $name ] = self::truncate_text( sanitize_text_field( (string) $value ), 500 );
			}
		}
		return $headers;
	}

	private static function analyse_html( string $html, string $base_url ): array {
		$result = array(
			'document_language' => '',
			'title'             => '',
			'meta_description'  => '',
			'meta_robots'       => '',
			'canonical'         => '',
			'hreflang'          => array(),
			'headings'          => array( 'h1' => 0, 'h2' => 0, 'h3' => 0, 'h4' => 0, 'h5' => 0, 'h6' => 0 ),
			'h1_texts'          => array(),
			'images'            => array( 'total' => 0, 'missing_alt' => 0, 'empty_alt' => 0, 'issue_samples' => array() ),
			'links'             => array( 'total' => 0, 'internal' => 0, 'external' => 0, 'empty_href' => 0, 'unsafe_scheme' => 0, 'internal_samples' => array() ),
			'schema_types'      => array(),
			'open_graph'        => array(),
			'rendered_word_count' => 0,
		);

		if ( '' === trim( $html ) ) {
			return $result;
		}
		if ( ! class_exists( '\DOMDocument' ) ) {
			return self::analyse_html_fallback( $html, $base_url, $result );
		}

		$previous = libxml_use_internal_errors( true );
		$document = new \DOMDocument();
		$loaded   = $document->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			return $result;
		}

		$html_nodes = $document->getElementsByTagName( 'html' );
		if ( $html_nodes->length > 0 ) {
			$result['document_language'] = sanitize_text_field( (string) $html_nodes->item( 0 )->getAttribute( 'lang' ) );
		}

		$title_nodes = $document->getElementsByTagName( 'title' );
		if ( $title_nodes->length > 0 ) {
			$result['title'] = self::clean_dom_text( (string) $title_nodes->item( 0 )->textContent, 300 );
		}

		foreach ( $document->getElementsByTagName( 'meta' ) as $node ) {
			$name     = strtolower( trim( (string) $node->getAttribute( 'name' ) ) );
			$property = strtolower( trim( (string) $node->getAttribute( 'property' ) ) );
			$content  = self::clean_dom_text( (string) $node->getAttribute( 'content' ), 1000 );
			if ( 'description' === $name && '' === $result['meta_description'] ) {
				$result['meta_description'] = $content;
			} elseif ( 'robots' === $name && '' === $result['meta_robots'] ) {
				$result['meta_robots'] = strtolower( $content );
			}
			if ( str_starts_with( $property, 'og:' ) && '' !== $content && count( $result['open_graph'] ) < 10 ) {
				$result['open_graph'][ $property ] = $content;
			}
		}

		foreach ( $document->getElementsByTagName( 'link' ) as $node ) {
			$rel  = strtolower( trim( (string) $node->getAttribute( 'rel' ) ) );
			$href = trim( (string) $node->getAttribute( 'href' ) );
			if ( '' === $href ) {
				continue;
			}
			$absolute = self::absolute_url( html_entity_decode( $href, ENT_QUOTES, 'UTF-8' ), $base_url );
			if ( str_contains( ' ' . $rel . ' ', ' canonical ' ) && '' === $result['canonical'] && null !== $absolute ) {
				$result['canonical'] = $absolute;
			}
			if ( str_contains( ' ' . $rel . ' ', ' alternate ' ) && $node->hasAttribute( 'hreflang' ) && null !== $absolute ) {
				$lang = sanitize_text_field( strtolower( trim( (string) $node->getAttribute( 'hreflang' ) ) ) );
				if ( '' !== $lang && count( $result['hreflang'] ) < 30 ) {
					$result['hreflang'][] = array( 'lang' => $lang, 'url' => $absolute );
				}
			}
		}

		foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $heading ) {
			$nodes = $document->getElementsByTagName( $heading );
			$result['headings'][ $heading ] = $nodes->length;
			if ( 'h1' === $heading ) {
				foreach ( $nodes as $node ) {
					if ( count( $result['h1_texts'] ) >= 5 ) {
						break;
					}
					$text = self::clean_dom_text( (string) $node->textContent, 300 );
					if ( '' !== $text ) {
						$result['h1_texts'][] = $text;
					}
				}
			}
		}

		foreach ( $document->getElementsByTagName( 'img' ) as $node ) {
			++$result['images']['total'];
			$has_alt = $node->hasAttribute( 'alt' );
			$alt     = $has_alt ? trim( (string) $node->getAttribute( 'alt' ) ) : '';
			if ( ! $has_alt ) {
				++$result['images']['missing_alt'];
			} elseif ( '' === $alt ) {
				++$result['images']['empty_alt'];
			}
			if ( ( ! $has_alt || '' === $alt ) && count( $result['images']['issue_samples'] ) < 20 ) {
				$src      = trim( (string) $node->getAttribute( 'src' ) );
				$absolute = '' === $src ? null : self::absolute_url( html_entity_decode( $src, ENT_QUOTES, 'UTF-8' ), $base_url );
				$result['images']['issue_samples'][] = array(
					'src'   => null !== $absolute ? $absolute : self::truncate_text( sanitize_text_field( $src ), 500 ),
					'issue' => $has_alt ? 'empty_alt' : 'missing_alt',
				);
			}
		}

		foreach ( $document->getElementsByTagName( 'a' ) as $node ) {
			++$result['links']['total'];
			$href = trim( (string) $node->getAttribute( 'href' ) );
			if ( '' === $href ) {
				++$result['links']['empty_href'];
				continue;
			}
			$lower = strtolower( $href );
			if ( str_starts_with( $lower, 'javascript:' ) || str_starts_with( $lower, 'data:' ) || str_starts_with( $lower, 'blob:' ) ) {
				++$result['links']['unsafe_scheme'];
				continue;
			}
			$absolute = self::absolute_url( html_entity_decode( $href, ENT_QUOTES, 'UTF-8' ), $base_url );
			if ( null === $absolute ) {
				continue;
			}
			if ( self::same_origin( $absolute ) ) {
				++$result['links']['internal'];
				if ( count( $result['links']['internal_samples'] ) < 30 ) {
					$result['links']['internal_samples'][ $absolute ] = true;
				}
			} else {
				++$result['links']['external'];
			}
		}
		$result['links']['internal_samples'] = array_keys( $result['links']['internal_samples'] );

		foreach ( $document->getElementsByTagName( 'script' ) as $node ) {
			if ( 'application/ld+json' !== strtolower( trim( (string) $node->getAttribute( 'type' ) ) ) ) {
				continue;
			}
			$decoded = json_decode( (string) $node->textContent, true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				self::collect_schema_types( $decoded, $result['schema_types'] );
				if ( count( $result['schema_types'] ) >= 30 ) {
					break;
				}
			}
		}
		$result['schema_types'] = array_values( array_unique( array_slice( $result['schema_types'], 0, 30 ) ) );

		$body_nodes = $document->getElementsByTagName( 'body' );
		if ( $body_nodes->length > 0 ) {
			$text = self::clean_dom_text( (string) $body_nodes->item( 0 )->textContent, 2000000 );
			$result['rendered_word_count'] = self::word_count( $text );
		}

		return $result;
	}

	private static function analyse_html_fallback( string $html, string $base_url, array $result ): array {
		if ( preg_match( '#<html\b([^>]*)>#isu', $html, $match ) ) {
			$attrs = self::parse_html_attributes( (string) $match[1] );
			$result['document_language'] = sanitize_text_field( (string) ( $attrs['lang'] ?? '' ) );
		}
		if ( preg_match( '#<title\b[^>]*>(.*?)</title>#isu', $html, $match ) ) {
			$result['title'] = self::clean_dom_text( html_entity_decode( (string) $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 300 );
		}

		if ( preg_match_all( '#<meta\b([^>]*)>#isu', $html, $matches ) ) {
			foreach ( (array) $matches[1] as $raw_attrs ) {
				$attrs    = self::parse_html_attributes( (string) $raw_attrs );
				$name     = strtolower( trim( (string) ( $attrs['name'] ?? '' ) ) );
				$property = strtolower( trim( (string) ( $attrs['property'] ?? '' ) ) );
				$content  = self::clean_dom_text( html_entity_decode( (string) ( $attrs['content'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 1000 );
				if ( 'description' === $name && '' === $result['meta_description'] ) {
					$result['meta_description'] = $content;
				} elseif ( 'robots' === $name && '' === $result['meta_robots'] ) {
					$result['meta_robots'] = strtolower( $content );
				}
				if ( str_starts_with( $property, 'og:' ) && '' !== $content && count( $result['open_graph'] ) < 10 ) {
					$result['open_graph'][ $property ] = $content;
				}
			}
		}

		if ( preg_match_all( '#<link\b([^>]*)>#isu', $html, $matches ) ) {
			foreach ( (array) $matches[1] as $raw_attrs ) {
				$attrs = self::parse_html_attributes( (string) $raw_attrs );
				$rel   = strtolower( trim( (string) ( $attrs['rel'] ?? '' ) ) );
				$href  = trim( (string) ( $attrs['href'] ?? '' ) );
				if ( '' === $href ) {
					continue;
				}
				$absolute = self::absolute_url( html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $base_url );
				if ( str_contains( ' ' . $rel . ' ', ' canonical ' ) && '' === $result['canonical'] && null !== $absolute ) {
					$result['canonical'] = $absolute;
				}
				if ( str_contains( ' ' . $rel . ' ', ' alternate ' ) && isset( $attrs['hreflang'] ) && null !== $absolute ) {
					$lang = sanitize_text_field( strtolower( trim( (string) $attrs['hreflang'] ) ) );
					if ( '' !== $lang && count( $result['hreflang'] ) < 30 ) {
						$result['hreflang'][] = array( 'lang' => $lang, 'url' => $absolute );
					}
				}
			}
		}

		foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $heading ) {
			if ( preg_match_all( '#<' . $heading . '\b[^>]*>(.*?)</' . $heading . '>#isu', $html, $matches ) ) {
				$result['headings'][ $heading ] = count( $matches[0] );
				if ( 'h1' === $heading ) {
					foreach ( array_slice( (array) $matches[1], 0, 5 ) as $text ) {
						$clean = self::clean_dom_text( html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 300 );
						if ( '' !== $clean ) {
							$result['h1_texts'][] = $clean;
						}
					}
				}
			}
		}

		if ( preg_match_all( '#<img\b([^>]*)>#isu', $html, $matches ) ) {
			foreach ( (array) $matches[1] as $raw_attrs ) {
				++$result['images']['total'];
				$attrs   = self::parse_html_attributes( (string) $raw_attrs );
				$has_alt = array_key_exists( 'alt', $attrs );
				$alt     = $has_alt ? trim( (string) $attrs['alt'] ) : '';
				if ( ! $has_alt ) {
					++$result['images']['missing_alt'];
				} elseif ( '' === $alt ) {
					++$result['images']['empty_alt'];
				}
				if ( ( ! $has_alt || '' === $alt ) && count( $result['images']['issue_samples'] ) < 20 ) {
					$src      = trim( (string) ( $attrs['src'] ?? '' ) );
					$absolute = '' === $src ? null : self::absolute_url( html_entity_decode( $src, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $base_url );
					$result['images']['issue_samples'][] = array(
						'src'   => null !== $absolute ? $absolute : self::truncate_text( sanitize_text_field( $src ), 500 ),
						'issue' => $has_alt ? 'empty_alt' : 'missing_alt',
					);
				}
			}
		}

		if ( preg_match_all( '#<a\b([^>]*)>#isu', $html, $matches ) ) {
			foreach ( (array) $matches[1] as $raw_attrs ) {
				++$result['links']['total'];
				$attrs = self::parse_html_attributes( (string) $raw_attrs );
				$href  = trim( (string) ( $attrs['href'] ?? '' ) );
				if ( '' === $href ) {
					++$result['links']['empty_href'];
					continue;
				}
				$lower = strtolower( $href );
				if ( str_starts_with( $lower, 'javascript:' ) || str_starts_with( $lower, 'data:' ) || str_starts_with( $lower, 'blob:' ) ) {
					++$result['links']['unsafe_scheme'];
					continue;
				}
				$absolute = self::absolute_url( html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $base_url );
				if ( null === $absolute ) {
					continue;
				}
				if ( self::same_origin( $absolute ) ) {
					++$result['links']['internal'];
					if ( count( $result['links']['internal_samples'] ) < 30 ) {
						$result['links']['internal_samples'][ $absolute ] = true;
					}
				} else {
					++$result['links']['external'];
				}
			}
		}
		$result['links']['internal_samples'] = array_keys( $result['links']['internal_samples'] );

		if ( preg_match_all( '#<script\b([^>]*)>(.*?)</script>#isu', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$attrs = self::parse_html_attributes( (string) $match[1] );
				if ( 'application/ld+json' !== strtolower( trim( (string) ( $attrs['type'] ?? '' ) ) ) ) {
					continue;
				}
				$decoded = json_decode( html_entity_decode( (string) $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), true );
				if ( JSON_ERROR_NONE === json_last_error() ) {
					self::collect_schema_types( $decoded, $result['schema_types'] );
					if ( count( $result['schema_types'] ) >= 30 ) {
						break;
					}
				}
			}
		}
		$result['schema_types'] = array_values( array_unique( array_slice( $result['schema_types'], 0, 30 ) ) );

		$body = $html;
		if ( preg_match( '#<body\b[^>]*>(.*?)</body>#isu', $html, $match ) ) {
			$body = (string) $match[1];
		}
		$result['rendered_word_count'] = self::word_count( self::clean_dom_text( html_entity_decode( $body, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 2000000 ) );
		return $result;
	}

	private static function parse_html_attributes( string $raw ): array {
		$attrs = array();
		if ( preg_match_all( '/([:\\w-]+)(?:\\s*=\\s*(?:"([^"]*)"|\'([^\']*)\'|([^\\s"\'=<>`]+)))?/u', $raw, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$name = strtolower( (string) $match[1] );
				if ( '' === $name || array_key_exists( $name, $attrs ) ) {
					continue;
				}
				$value = '';
				if ( isset( $match[2] ) && '' !== $match[2] ) {
					$value = $match[2];
				} elseif ( isset( $match[3] ) && '' !== $match[3] ) {
					$value = $match[3];
				} elseif ( isset( $match[4] ) ) {
					$value = $match[4];
				}
				$attrs[ $name ] = $value;
			}
		}
		return $attrs;
	}

	private static function truncate_text( string $text, int $max_length ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $max_length );
		}
		return substr( $text, 0, $max_length );
	}

	private static function clean_dom_text( string $text, int $max_length ): string {
		$text = wp_strip_all_tags( $text, true );
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? '';
		return self::truncate_text( trim( $text ), $max_length );
	}

	private static function collect_schema_types( $value, array &$types ): void {
		if ( count( $types ) >= 30 || ! is_array( $value ) ) {
			return;
		}
		if ( isset( $value['@type'] ) ) {
			foreach ( (array) $value['@type'] as $type ) {
				if ( is_scalar( $type ) ) {
					$clean = sanitize_text_field( (string) $type );
					if ( '' !== $clean ) {
						$types[] = $clean;
					}
				}
			}
		}
		foreach ( $value as $child ) {
			if ( is_array( $child ) ) {
				self::collect_schema_types( $child, $types );
				if ( count( $types ) >= 30 ) {
					break;
				}
			}
		}
	}

	private static function audit_issues( int $status, array $headers, array $analysis, bool $from_sitemap ): array {
		$issues = array();
		if ( 200 !== $status ) {
			$issues[] = array( 'severity' => 'high', 'code' => 'http_status', 'message' => 'Page does not return HTTP 200.' );
			return $issues;
		}

		$content_type = strtolower( (string) ( $headers['content-type'] ?? '' ) );
		if ( '' !== $content_type && ! str_contains( $content_type, 'text/html' ) && ! str_contains( $content_type, 'application/xhtml+xml' ) ) {
			$issues[] = array( 'severity' => 'medium', 'code' => 'non_html', 'message' => 'Response is not HTML.' );
			return $issues;
		}

		if ( '' === (string) ( $analysis['title'] ?? '' ) ) {
			$issues[] = array( 'severity' => 'high', 'code' => 'missing_title', 'message' => 'Rendered HTML has no title.' );
		}
		if ( '' === (string) ( $analysis['meta_description'] ?? '' ) ) {
			$issues[] = array( 'severity' => 'low', 'code' => 'missing_meta_description', 'message' => 'Rendered HTML has no meta description.' );
		}
		$h1_count = (int) ( $analysis['headings']['h1'] ?? 0 );
		if ( 0 === $h1_count ) {
			$issues[] = array( 'severity' => 'medium', 'code' => 'missing_h1', 'message' => 'Rendered HTML has no H1.' );
		} elseif ( $h1_count > 1 ) {
			$issues[] = array( 'severity' => 'low', 'code' => 'multiple_h1', 'message' => 'Rendered HTML has multiple H1 headings.' );
		}
		if ( '' === (string) ( $analysis['canonical'] ?? '' ) ) {
			$issues[] = array( 'severity' => $from_sitemap ? 'medium' : 'low', 'code' => 'missing_canonical', 'message' => 'Rendered HTML has no canonical link.' );
		} elseif ( ! self::same_origin( (string) $analysis['canonical'] ) ) {
			$issues[] = array( 'severity' => 'high', 'code' => 'external_canonical', 'message' => 'Canonical points outside this site.' );
		}
		$robots   = strtolower( (string) ( $analysis['meta_robots'] ?? '' ) );
		$x_robots = strtolower( (string) ( $headers['x-robots-tag'] ?? '' ) );
		if ( str_contains( $robots, 'noindex' ) || str_contains( $x_robots, 'noindex' ) ) {
			$issues[] = array( 'severity' => $from_sitemap ? 'high' : 'medium', 'code' => 'noindex', 'message' => 'Page is marked noindex.' );
		}
		$image_issues = (int) ( $analysis['images']['missing_alt'] ?? 0 ) + (int) ( $analysis['images']['empty_alt'] ?? 0 );
		if ( $image_issues > 0 ) {
			$issues[] = array( 'severity' => 'low', 'code' => 'image_alt', 'message' => 'Rendered HTML contains images with missing or empty alt attributes.', 'count' => $image_issues );
		}
		if ( (int) ( $analysis['links']['empty_href'] ?? 0 ) > 0 ) {
			$issues[] = array( 'severity' => 'low', 'code' => 'empty_links', 'message' => 'Rendered HTML contains anchors with an empty href.', 'count' => (int) $analysis['links']['empty_href'] );
		}
		if ( '' === (string) ( $analysis['document_language'] ?? '' ) ) {
			$issues[] = array( 'severity' => 'low', 'code' => 'missing_html_lang', 'message' => 'Rendered HTML has no language on the html element.' );
		}
		return $issues;
	}

	public static function http_probe( WP_REST_Request $request ): WP_REST_Response {
		$path = self::normalise_probe_path( (string) $request->get_param( 'path' ) );
		if ( null === $path ) {
			return self::response( array( 'error' => 'Only a safe same-site public path is allowed.' ), 400 );
		}

		$method         = strtoupper( (string) $request->get_param( 'method' ) );
		$profile        = sanitize_key( (string) $request->get_param( 'profile' ) );
		$include_sample = rest_sanitize_boolean( $request->get_param( 'include_sample' ) );
		$profiles       = self::user_agent_profiles();

		if ( ! isset( $profiles[ $profile ] ) ) {
			return self::response( array( 'error' => 'Unknown User-Agent profile.' ), 400 );
		}

		$url   = home_url( $path );
		$chain = array();

		for ( $step = 0; $step <= self::MAX_REDIRECTS; $step++ ) {
			$result = self::simple_request( $url, $method, $profiles[ $profile ], $include_sample && 'GET' === $method );
			$result['url'] = $url;
			$chain[] = $result;

			$status   = (int) ( $result['status'] ?? 0 );
			$location = (string) ( $result['headers']['location'] ?? '' );

			if ( $status < 300 || $status >= 400 || '' === $location ) {
				break;
			}

			$next_absolute = self::absolute_url( $location, $url );
			$next          = null === $next_absolute ? null : self::safe_probe_url( $next_absolute );

			if ( null === $next ) {
				$chain[] = array(
					'status' => 0,
					'error'  => 'Redirect target is outside the safe public frontend scope and was not followed.',
					'url'    => $location,
				);
				break;
			}

			$url = $next;
		}

		return self::response(
			array(
				'path'             => $path,
				'method'           => $method,
				'profile'          => $profile,
				'redirects_followed' => max( 0, count( $chain ) - 1 ),
				'chain'            => $chain,
			)
		);
	}

	private static function allowed_probe_paths(): array {
		return array(
			'/',
			'/robots.txt',
			'/wp-sitemap.xml',
			'/llms.txt',
			'/.well-known/koz-ai-manifest.json',
			'/.well-known/uafree-ai-manifest.json',
		);
	}

	private static function safe_probe_url( string $url ): ?string {
		if ( ! self::same_origin( $url ) ) {
			return null;
		}

		$parts = wp_parse_url( $url );

		if (
			! is_array( $parts )
			|| isset( $parts['query'] )
			|| isset( $parts['fragment'] )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
		) {
			return null;
		}

		$normal = self::normalise_probe_path( (string) ( $parts['path'] ?? '/' ) );

		return null === $normal ? null : home_url( $normal );
	}

	private static function normalise_probe_path( string $path ): ?string {
		$path = trim( $path );

		if (
			'' === $path
			|| ! str_starts_with( $path, '/' )
			|| str_starts_with( $path, '//' )
			|| str_contains( $path, '://' )
			|| str_contains( $path, '\\' )
			|| str_contains( $path, "\0" )
			|| str_contains( $path, '#' )
		) {
			return null;
		}

		$parts = wp_parse_url( $path );
		if (
			! is_array( $parts )
			|| isset( $parts['scheme'] )
			|| isset( $parts['host'] )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
		) {
			return null;
		}

		$decoded_path = strtolower( rawurldecode( (string) ( $parts['path'] ?? '' ) ) );
		foreach (
			array(
				'/wp-admin',
				'/wp-login.php',
				'/wp-cron.php',
				'/wp-json',
				'/xmlrpc.php',
				'/admin-ajax.php',
				'/wp-comments-post.php',
			) as $blocked_prefix
		) {
			if ( str_starts_with( $decoded_path, $blocked_prefix ) ) {
				return null;
			}
		}

		$clean_path = (string) ( $parts['path'] ?? '/' );
		$segments   = array();

		foreach ( explode( '/', $clean_path ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}
			if ( '..' === rawurldecode( $segment ) ) {
				return null;
			}
			$segments[] = rawurlencode( rawurldecode( $segment ) );
		}

		$normal = '/' . implode( '/', $segments );
		if ( str_ends_with( $clean_path, '/' ) && '/' !== $normal ) {
			$normal .= '/';
		}

		if ( isset( $parts['query'] ) ) {
			return null;
		}

		if ( in_array( $normal, self::allowed_probe_paths(), true ) ) {
			return $normal;
		}

		return self::is_safe_frontend_path( $normal ) ? $normal : null;
	}

	private static function is_safe_frontend_path( string $path ): bool {
		if ( strlen( $path ) > 512 ) {
			return false;
		}

		$decoded = strtolower( rawurldecode( $path ) );
		foreach (
			array(
				'/wp-admin',
				'/wp-login.php',
				'/wp-cron.php',
				'/wp-json',
				'/xmlrpc.php',
				'/admin-ajax.php',
				'/wp-comments-post.php',
				'/wp-content',
				'/wp-includes',
				'/.git',
				'/.svn',
				'/.env',
				'/wp-config',
			) as $blocked_prefix
		) {
			if ( str_starts_with( $decoded, $blocked_prefix ) ) {
				return false;
			}
		}

		if ( preg_match( '/\.(?:php[0-9]*|phtml|phar|cgi|pl|py|sh|sql|ini|log|bak|zip|tar|gz|7z|env|htaccess|htpasswd)$/i', $decoded ) ) {
			return false;
		}

		return true;
	}

	private static function user_agent_profiles(): array {
		return array(
			'default' => 'KOZ-Site-Bridge/' . KOZBRIDGE_VERSION,
			'googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
			'adsbot-google' => 'AdsBot-Google (+http://www.google.com/adsbot.html)',
			'adsbot-google-mobile' => 'Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Mobile Safari/537.36 AdsBot-Google-Mobile',
		);
	}

	private static function simple_request( string $url, string $method, string $user_agent, bool $include_sample ): array {
		if ( ! self::same_origin( $url ) ) {
			return array( 'status' => 0, 'error' => 'Only same-origin requests are allowed.' );
		}

		$started  = microtime( true );
		$response = wp_safe_remote_request(
			$url,
			array(
				'method'              => $method,
				'timeout'             => self::HTTP_TIMEOUT,
				'redirection'         => 0,
				'limit_response_size' => $include_sample ? 16384 : 1,
				'user-agent'          => $user_agent,
				'reject_unsafe_urls'  => true,
				'headers'             => array(
					'Accept' => 'text/html,application/json,text/plain;q=0.9,*/*;q=0.1',
				),
			)
		);
		$duration = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return array(
				'status'      => 0,
				'error'       => sanitize_text_field( $response->get_error_message() ),
				'duration_ms' => $duration,
				'headers'     => array(),
			);
		}

		$headers = array();
		foreach (
			array(
				'location',
				'content-type',
				'server',
				'cf-ray',
				'cf-cache-status',
				'cf-mitigated',
				'x-robots-tag',
				'retry-after',
			) as $name
		) {
			$value = wp_remote_retrieve_header( $response, $name );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$headers[ $name ] = self::truncate_text( sanitize_text_field( (string) $value ), 500 );
			}
		}

		$result = array(
			'status'      => (int) wp_remote_retrieve_response_code( $response ),
			'duration_ms' => $duration,
			'headers'     => $headers,
		);

		if ( $include_sample ) {
			$sample = wp_strip_all_tags( (string) wp_remote_retrieve_body( $response ), true );
			$sample = preg_replace( '/\s+/u', ' ', $sample ) ?? '';
			$result['body_sample'] = self::truncate_text( trim( $sample ), self::BODY_SAMPLE_MAX );
		}

		return $result;
	}

	public static function openapi( WP_REST_Request $request ): WP_REST_Response {
		$base = untrailingslashit( rest_url( self::API_NAMESPACE ) );

		$parameters = array(
			'content' => array(
				self::query_parameter( 'type', 'string', false, 'Public post type, normally page or post.' ),
				self::query_parameter( 'page', 'integer', false, 'Page number.' ),
				self::query_parameter( 'limit', 'integer', false, 'Items per page, maximum 50.' ),
				self::query_parameter( 'search', 'string', false, 'Optional WordPress search text.' ),
			),
			'links' => array(
				self::query_parameter( 'type', 'string', false, 'Public post type.' ),
				self::query_parameter( 'page', 'integer', false, 'Content page number.' ),
				self::query_parameter( 'pages', 'integer', false, 'Number of public pages to inspect, maximum 3.' ),
				self::query_parameter( 'max_links', 'integer', false, 'Maximum internal links, maximum 30.' ),
				self::query_parameter( 'check', 'boolean', false, 'Whether to perform same-site HEAD checks.' ),
			),
			'404-log' => array(
				self::query_parameter( 'limit', 'integer', false, 'Maximum privacy-safe grouped rows.' ),
			),
			'admin-error-log' => array(
				self::query_parameter( 'limit', 'integer', false, 'Recent sanitized log lines per fixed local source, maximum 200.' ),
			),
			'sitemap' => array(
				self::query_parameter( 'page', 'integer', false, 'Sitemap result page.' ),
				self::query_parameter( 'limit', 'integer', false, 'URLs per page, maximum 100.' ),
			),
			'page-audit' => array(
				self::query_parameter( 'path', 'string', true, 'Safe same-site public frontend path beginning with /.' ),
				self::query_parameter( 'profile', 'string', false, 'default, googlebot, adsbot-google or adsbot-google-mobile.' ),
			),
			'rendered-audit' => array(
				self::query_parameter( 'page', 'integer', false, 'Audit batch number over sitemap URLs.' ),
				self::query_parameter( 'limit', 'integer', false, 'Rendered pages per batch, maximum 5.' ),
				self::query_parameter( 'profile', 'string', false, 'default, googlebot, adsbot-google or adsbot-google-mobile.' ),
			),
			'http-probe' => array(
				self::query_parameter( 'path', 'string', true, 'Safe same-site public frontend path beginning with /.' ),
				self::query_parameter( 'method', 'string', false, 'HEAD or GET.' ),
				self::query_parameter( 'profile', 'string', false, 'default, googlebot, adsbot-google or adsbot-google-mobile.' ),
				self::query_parameter( 'include_sample', 'boolean', false, 'Include a sanitized body sample for GET requests.' ),
			),
		);

		$paths = array();
		$operations = array(
			'/ping'        => array( 'pingSiteBridge', 'Test authentication and availability.' ),
			'/overview'    => array( 'getSiteOverview', 'Get a read-only WordPress environment summary.' ),
			'/plugins'     => array( 'getPluginInventory', 'Get plugin names, versions and activation states without plugin file paths.' ),
			'/suite'       => array( 'getSuiteStatus', 'Get KOZ Suite component status.' ),
			'/content'     => array( 'getPublicContentInventory', 'Get metadata and excerpts for public content.' ),
			'/navigation'  => array( 'getNavigationInventory', 'Get public WordPress menu items.' ),
			'/links'       => array( 'scanInternalLinks', 'Inspect a small number of same-site public links.' ),
			'/404-log'     => array( 'getPrivacySafe404Log', 'Read only privacy-safe grouped 404/410 fingerprints.' ),
			'/diagnostics'    => array( 'runSafeDiagnostics', 'Run fixed same-site checks for home, robots, sitemap, AI discovery and 404 status.' ),
			'/admin-error-log' => array( 'getAdminErrorLog', 'Read recent privacy-redacted lines from fixed local WordPress/PHP error-log candidates for private troubleshooting.' ),
			'/sitemap'        => array( 'getSitemapInventory', 'List same-site public URLs discovered from the WordPress sitemap.' ),
			'/page-audit'     => array( 'auditRenderedPage', 'Fetch and inspect one safe public frontend page including rendered SEO, H1, alt, links, hreflang and schema signals.' ),
			'/rendered-audit' => array( 'auditSitemapBatch', 'Audit a small batch of sitemap URLs and return concrete per-URL issues.' ),
			'/http-probe'     => array( 'probeSameSitePath', 'Probe one safe public frontend path with an allowlisted browser or crawler User-Agent.' ),
		);

		foreach ( $operations as $path => $operation ) {
			$key = ltrim( $path, '/' );
			$paths[ $path ] = array(
				'get' => self::openapi_operation(
					$operation[0],
					$operation[1],
					$parameters[ $key ] ?? array()
				),
			);
		}

		return self::response(
			array(
				'openapi' => '3.1.0',
				'info' => array(
					'title'       => 'KOZ Site Bridge',
					'description' => 'Read-only privacy-safe WordPress diagnostics for a private GPT or controlled automation.',
					'version'     => KOZBRIDGE_VERSION,
				),
				'servers' => array( array( 'url' => $base ) ),
				'components' => array(
					'securitySchemes' => array(
						'ApiKeyAuth' => array(
							'type' => 'apiKey',
							'in'   => 'header',
							'name' => 'X-KOZ-Key',
						),
					),
					'schemas' => array(
						'BridgeResponse' => array(
							'type' => 'object',
							'properties' => array(
								'ok' => array(
									'type'        => 'boolean',
									'description' => 'Optional success flag.',
								),
								'error' => array(
									'type'        => 'string',
									'description' => 'Optional error description.',
								),
								'message' => array(
									'type'        => 'string',
									'description' => 'Optional human-readable message.',
								),
								'plugin' => array(
									'type'        => 'string',
									'description' => 'Optional plugin name.',
								),
								'version' => array(
									'type'        => 'string',
									'description' => 'Optional plugin version.',
								),
								'count' => array(
									'type'        => 'integer',
									'description' => 'Optional result count.',
								),
								'items' => array(
									'type'        => 'array',
									'description' => 'Optional result list.',
									'items'       => array(
										'type'                 => 'object',
										'properties'           => array(
											'id' => array(
												'type'        => 'integer',
												'description' => 'Optional item identifier.',
											),
										),
										'additionalProperties' => true,
									),
								),
								'data' => array(
									'type'        => 'object',
									'description' => 'Optional structured result payload.',
									'properties'  => array(
										'status' => array(
											'type'        => 'string',
											'description' => 'Optional status value.',
										),
									),
									'additionalProperties' => true,
								),
							),
							'additionalProperties' => true,
						),
						'ErrorResponse' => array(
							'type' => 'object',
							'properties' => array(
								'code'    => array( 'type' => 'string' ),
								'message' => array( 'type' => 'string' ),
								'data'    => array(
									'type' => 'object',
									'properties' => array(
										'status' => array( 'type' => 'integer' ),
									),
								),
							),
						),
					),
				),
				'security' => array( array( 'ApiKeyAuth' => array() ) ),
				'paths'    => $paths,
			)
		);
	}

	private static function query_parameter( string $name, string $type, bool $required, string $description ): array {
		return array(
			'name'        => $name,
			'in'          => 'query',
			'required'    => $required,
			'description' => $description,
			'schema'      => array( 'type' => $type ),
		);
	}

	private static function openapi_operation( string $operation_id, string $description, array $parameters ): array {
		return array(
			'operationId' => $operation_id,
			'description' => $description,
			'parameters'  => $parameters,
			'responses'   => array(
				'200' => array(
					'description' => 'Successful read-only response.',
					'content' => array(
						'application/json' => array(
							'schema' => array( '$ref' => '#/components/schemas/BridgeResponse' ),
						),
					),
				),
				'400' => self::error_response( 'Invalid request.' ),
				'401' => self::error_response( 'Invalid or missing API key.' ),
				'429' => self::error_response( 'Rate limit exceeded.' ),
				'503' => self::error_response( 'Bridge unavailable.' ),
			),
		);
	}

	private static function error_response( string $description ): array {
		return array(
			'description' => $description,
			'content' => array(
				'application/json' => array(
					'schema' => array( '$ref' => '#/components/schemas/ErrorResponse' ),
				),
			),
		);
	}
}
