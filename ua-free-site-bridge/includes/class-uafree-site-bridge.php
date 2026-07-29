<?php
namespace UAFree\SiteBridge;

use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bridge {
	private const API_NAMESPACE   = 'uafree-bridge/v1';
	private const KEY_HASH_OPTION = 'uafree_bridge_api_key_hash';
	private const KEY_DATE_OPTION = 'uafree_bridge_api_key_created_at';
	private const LEGACY_LOG_OPTION = 'uafree_bridge_api_log';
	private const RATE_LIMIT      = 120;
	private const RATE_TTL        = 3700;
	private const RATE_LOCK_TTL   = 5;
	private const CONTENT_LIMIT   = 50;
	private const HTTP_TIMEOUT    = 8;
	private const MAX_REDIRECTS   = 5;
	private const BODY_SAMPLE_MAX = 500;

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 20 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'rest_headers' ), 10, 4 );
	}

	private static function is_ukrainian(): bool {
		return str_starts_with( strtolower( determine_locale() ), 'uk' );
	}

	private static function t( string $uk, string $en ): string {
		return self::is_ukrainian() ? $uk : $en;
	}

	public static function admin_menu(): void {
		add_submenu_page(
			'uafree-suite',
			'UA FREE Site Bridge',
			'Site Bridge',
			'manage_options',
			'ua-free-site-bridge',
			array( __CLASS__, 'admin_page' )
		);
	}

	public static function admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plain_key = '';

		if ( isset( $_POST['uafree_bridge_generate'] ) ) {
			check_admin_referer( 'uafree_bridge_generate_action' );

			try {
				$plain_key = 'uafb_' . bin2hex( random_bytes( 32 ) );
			} catch ( \Throwable $error ) {
				$plain_key = 'uafb_' . wp_generate_password( 64, false, false );
			}

			update_option(
				self::KEY_HASH_OPTION,
				'v2:' . self::key_digest( $plain_key ),
				false
			);
			update_option( self::KEY_DATE_OPTION, current_time( 'mysql' ), false );
			delete_option( self::LEGACY_LOG_OPTION );
			self::delete_rate_limit_options();

			echo '<div class="notice notice-success"><p>' . esc_html( self::t(
				'Новий API-ключ створено. Скопіюйте його зараз: повторно він не відображатиметься.',
				'New API key created. Copy it now: it will not be displayed again.'
			) ) . '</p></div>';
		}

		if ( isset( $_POST['uafree_bridge_revoke'] ) ) {
			check_admin_referer( 'uafree_bridge_revoke_action' );
			delete_option( self::KEY_HASH_OPTION );
			delete_option( self::KEY_DATE_OPTION );
			delete_option( self::LEGACY_LOG_OPTION );
			self::delete_rate_limit_options();

			echo '<div class="notice notice-success"><p>' . esc_html( self::t(
				'API-ключ відкликано. Зовнішній доступ закрито.',
				'API key revoked. External access is closed.'
			) ) . '</p></div>';
		}

		$stored_hash = (string) get_option( self::KEY_HASH_OPTION, '' );
		$has_key     = '' !== $stored_hash;
		$legacy_key  = $has_key && ! str_starts_with( $stored_hash, 'v2:' );
		$key_created = (string) get_option( self::KEY_DATE_OPTION, '' );
		$schema_url  = rest_url( self::API_NAMESPACE . '/openapi' );
		$ping_url    = rest_url( self::API_NAMESPACE . '/ping' );
		$https_ok    = 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME );
		?>
		<div class="wrap">
			<h1>UA FREE Site Bridge <small><?php echo esc_html( UAFREE_SITE_BRIDGE_VERSION ); ?></small></h1>

			<div class="notice notice-info inline">
				<p><strong><?php echo esc_html( self::t( 'Лише читання.', 'Read-only.' ) ); ?></strong>
				<?php echo esc_html( self::t(
					'Плагін не змінює сторінки, плагіни, тему, користувачів або налаштування сайту. Постійні записи обмежені hash API-ключа, датою його створення та автоматично прострочуваними лічильниками rate limit.',
					'The plugin does not modify pages, plugins, themes, users or site settings. Persistent records are limited to the API-key hash, creation date and automatically expiring rate-limit counters.'
				) ); ?></p>
			</div>

			<?php if ( ! $https_ok ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( self::t(
					'API вимкнено: Home URL не використовує HTTPS.',
					'API disabled: Home URL does not use HTTPS.'
				) ); ?></p></div>
			<?php endif; ?>

			<?php if ( $legacy_key ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( self::t(
					'Активний ключ створено старою версією. Він ще працює, але рекомендовано перевипустити його для швидшої HMAC-перевірки.',
					'The active key was created by an older version. It still works, but should be rotated to the faster HMAC format.'
				) ); ?></p></div>
			<?php endif; ?>

			<table class="widefat striped" style="max-width:1050px;margin:16px 0">
				<tbody>
					<tr><th style="width:260px"><?php echo esc_html( self::t( 'Режим', 'Mode' ) ); ?></th><td><?php echo esc_html( self::t( 'Read-only, privacy-safe', 'Read-only, privacy-safe' ) ); ?></td></tr>
					<tr><th><?php echo esc_html( self::t( 'API-ключ', 'API key' ) ); ?></th><td><?php echo $has_key ? '<strong style="color:#008a20">' . esc_html( self::t( 'активний', 'active' ) ) . '</strong>' : '<strong style="color:#b32d2e">' . esc_html( self::t( 'не створено', 'not created' ) ) . '</strong>'; ?></td></tr>
					<tr><th><?php echo esc_html( self::t( 'Ключ створено', 'Key created' ) ); ?></th><td><?php echo esc_html( $key_created ?: '—' ); ?></td></tr>
					<tr><th>OpenAPI</th><td><code style="word-break:break-all"><?php echo esc_html( $schema_url ); ?></code></td></tr>
					<tr><th>Ping</th><td><code style="word-break:break-all"><?php echo esc_html( $ping_url ); ?></code></td></tr>
					<tr><th><?php echo esc_html( self::t( 'Ліміт', 'Limit' ) ); ?></th><td><?php echo esc_html( (string) self::RATE_LIMIT ); ?> <?php echo esc_html( self::t( 'успішно автентифікованих запитів на годину.', 'successfully authenticated requests per hour.' ) ); ?></td></tr>
					<tr><th><?php echo esc_html( self::t( 'Журнал API', 'API log' ) ); ?></th><td><?php echo esc_html( self::t( 'Вимкнений. IP, User-Agent і історія запитів не зберігаються.', 'Disabled. IP addresses, User-Agent strings and request history are not stored.' ) ); ?></td></tr>
				</tbody>
			</table>

			<?php if ( '' !== $plain_key ) : ?>
				<div style="max-width:1050px;background:#fff;border:2px solid #d63638;padding:18px;margin:16px 0">
					<h2 style="margin-top:0"><?php echo esc_html( self::t( 'API-ключ показується один раз', 'API key is displayed once' ) ); ?></h2>
					<input type="text" readonly value="<?php echo esc_attr( $plain_key ); ?>" style="width:100%;font-family:monospace;font-size:16px;padding:10px" onclick="this.select();">
					<p><strong><?php echo esc_html( self::t( 'Не надсилайте ключ у чат.', 'Do not send the key in chat.' ) ); ?></strong></p>
				</div>
			<?php endif; ?>

			<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0">
				<form method="post">
					<?php wp_nonce_field( 'uafree_bridge_generate_action' ); ?>
					<button class="button button-primary" name="uafree_bridge_generate" value="1"><?php echo esc_html( $has_key ? self::t( 'Перевипустити API-ключ', 'Rotate API key' ) : self::t( 'Створити API-ключ', 'Create API key' ) ); ?></button>
				</form>

				<?php if ( $has_key ) : ?>
					<form method="post" onsubmit="return confirm('<?php echo esc_js( self::t( 'Відкликати ключ?', 'Revoke the key?' ) ); ?>');">
						<?php wp_nonce_field( 'uafree_bridge_revoke_action' ); ?>
						<button class="button" name="uafree_bridge_revoke" value="1"><?php echo esc_html( self::t( 'Відкликати API-ключ', 'Revoke API key' ) ); ?></button>
					</form>
				<?php endif; ?>
			</div>

			<h2><?php echo esc_html( self::t( 'Підключення приватного GPT', 'Private GPT connection' ) ); ?></h2>
			<ol style="max-width:1050px;line-height:1.7">
				<li><?php echo esc_html( self::t( 'Створіть приватний GPT з доступом «Лише я».', 'Create a private GPT with “Only me” access.' ) ); ?></li>
				<li><?php echo esc_html( self::t( 'Імпортуйте OpenAPI schema з адреси вище.', 'Import the OpenAPI schema from the URL above.' ) ); ?></li>
				<li>Authentication: <strong>API key → Custom header</strong>.</li>
				<li>Header: <code>X-UAFree-Key</code>.</li>
				<li><?php echo esc_html( self::t( 'Вставте ключ і протестуйте pingSiteBridge.', 'Paste the key and test pingSiteBridge.' ) ); ?></li>
			</ol>

			<h2><?php echo esc_html( self::t( 'Безпечні HTTP-профілі', 'Safe HTTP profiles' ) ); ?></h2>
			<p><?php echo esc_html( self::t(
				'HTTP probe приймає лише явно дозволений безпечний шлях поточного сайту без query string. Доступні профілі: default, googlebot, adsbot-google, adsbot-google-mobile.',
				'HTTP probe accepts only an explicitly allowlisted safe path on the current site without a query string. Profiles: default, googlebot, adsbot-google, adsbot-google-mobile.'
			) ); ?></p>
		</div>
		<?php
	}

	public static function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/openapi',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'openapi' ),
				'permission_callback' => '__return_true',
			)
		);

		$routes = array(
			'/ping'        => 'ping',
			'/overview'    => 'overview',
			'/plugins'     => 'plugins',
			'/suite'       => 'suite',
			'/content'     => 'content',
			'/navigation'  => 'navigation',
			'/links'       => 'links',
			'/404-log'     => 'error_404_log',
			'/diagnostics' => 'diagnostics',
			'/http-probe'  => 'http_probe',
		);

		foreach ( $routes as $route => $method ) {
			register_rest_route(
				self::API_NAMESPACE,
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
			return new WP_Error( 'uafree_bridge_https_required', 'Site Bridge requires HTTPS.', array( 'status' => 503 ) );
		}

		$stored_hash = (string) get_option( self::KEY_HASH_OPTION, '' );
		if ( '' === $stored_hash ) {
			return new WP_Error( 'uafree_bridge_not_configured', 'API key is not configured.', array( 'status' => 503 ) );
		}

		$key = trim( (string) $request->get_header( 'X-UAFree-Key' ) );
		if ( strlen( $key ) < 32 || strlen( $key ) > 128 || ! self::verify_key( $key, $stored_hash ) ) {
			return new WP_Error( 'uafree_bridge_unauthorized', 'Invalid or missing API key.', array( 'status' => 401 ) );
		}

		$key_id = substr( self::key_digest( $key ), 0, 16 );
		$rate   = self::increment_rate_limit( $key_id );

		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		if ( $rate > self::RATE_LIMIT ) {
			return new WP_Error( 'uafree_bridge_rate_limit', 'Hourly API rate limit exceeded.', array( 'status' => 429 ) );
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

		$rate_key   = 'uafree_bridge_rl_' . $key_id;
		$bucket     = gmdate( 'YmdH' );
		$lock_name  = 'uafree_bridge_rate_' . substr( hash( 'sha256', $rate_key ), 0, 40 );
		$lock_value = $wpdb->get_var(
			$wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, self::RATE_LOCK_TTL )
		);

		if ( '1' !== (string) $lock_value ) {
			return new WP_Error(
				'uafree_bridge_rate_busy',
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
					'uafree_bridge_rate_storage',
					'Rate limiter storage failed.',
					array( 'status' => 503 )
				);
			}

			return (int) $state['count'];
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	private static function delete_rate_limit_options(): void {
		global $wpdb;

		$value_prefix   = $wpdb->esc_like( '_transient_uafree_bridge_rl_' ) . '%';
		$timeout_prefix = $wpdb->esc_like( '_transient_timeout_uafree_bridge_rl_' ) . '%';

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name
				FROM {$wpdb->options}
				WHERE option_name LIKE %s OR option_name LIKE %s",
				$value_prefix,
				$timeout_prefix
			)
		);

		foreach ( (array) $rows as $option_name ) {
			delete_option( (string) $option_name );
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
		if ( str_starts_with( $request->get_route(), '/' . self::API_NAMESPACE . '/' ) ) {
			header( 'Cache-Control: no-store, private', true );
			header( 'Pragma: no-cache', true );
			header( 'X-Content-Type-Options: nosniff', true );
		}
		return $served;
	}

	public static function ping( WP_REST_Request $request ): WP_REST_Response {
		return self::response(
			array(
				'ok'             => true,
				'plugin'         => 'UA FREE Site Bridge',
				'version'        => UAFREE_SITE_BRIDGE_VERSION,
				'mode'           => 'read-only',
				'site'           => home_url( '/' ),
				'server_time'    => current_time( 'c' ),
				'authentication' => 'X-UAFree-Key',
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
		$auto_updates = (array) get_site_option( 'auto_update_plugins', array() );
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
				'auto_update'      => in_array( $file, $auto_updates, true ),
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

		if ( class_exists( '\UAFree\Suite\Registry' ) ) {
			foreach ( \UAFree\Suite\Registry::status() as $component ) {
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
				$result[ $key ] = mb_substr( sanitize_text_field( (string) $value ), 0, 500 );
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
							'reason'  => 'Path is outside the fixed safe probe allowlist.',
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
			'ai_manifest' => '/.well-known/uafree-ai-manifest.json',
			'not_found'   => '/__uafree_site_bridge_404_probe__/',
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

	public static function http_probe( WP_REST_Request $request ): WP_REST_Response {
		$path = self::normalise_probe_path( (string) $request->get_param( 'path' ) );
		if ( null === $path ) {
			return self::response( array( 'error' => 'Only a same-site absolute path is allowed.' ), 400 );
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
					'error'  => 'Redirect target is outside the fixed safe probe allowlist and was not followed.',
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
			'/.well-known/uafree-ai-manifest.json',
			'/donate/',
			'/partner/',
			'/en/partner/',
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

		return in_array( $normal, self::allowed_probe_paths(), true ) ? $normal : null;
	}

	private static function user_agent_profiles(): array {
		return array(
			'default' => 'UAFree-Site-Bridge/' . UAFREE_SITE_BRIDGE_VERSION,
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
				$headers[ $name ] = mb_substr( sanitize_text_field( (string) $value ), 0, 500 );
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
			$result['body_sample'] = mb_substr( trim( $sample ), 0, self::BODY_SAMPLE_MAX );
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
			'http-probe' => array(
				self::query_parameter( 'path', 'string', true, 'Same-site absolute path beginning with /.' ),
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
			'/suite'       => array( 'getSuiteStatus', 'Get UA FREE Suite component status.' ),
			'/content'     => array( 'getPublicContentInventory', 'Get metadata and excerpts for public content.' ),
			'/navigation'  => array( 'getNavigationInventory', 'Get public WordPress menu items.' ),
			'/links'       => array( 'scanInternalLinks', 'Inspect a small number of same-site public links.' ),
			'/404-log'     => array( 'getPrivacySafe404Log', 'Read only privacy-safe grouped 404/410 fingerprints.' ),
			'/diagnostics' => array( 'runSafeDiagnostics', 'Run fixed same-site checks for home, robots, sitemap, AI discovery and 404 status.' ),
			'/http-probe'  => array( 'probeSameSitePath', 'Probe one same-site path with an allowlisted browser or crawler User-Agent.' ),
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
					'title'       => 'UA FREE Site Bridge',
					'description' => 'Read-only privacy-safe WordPress diagnostics for a private GPT or controlled automation.',
					'version'     => UAFREE_SITE_BRIDGE_VERSION,
				),
				'servers' => array( array( 'url' => $base ) ),
				'components' => array(
					'securitySchemes' => array(
						'ApiKeyAuth' => array(
							'type' => 'apiKey',
							'in'   => 'header',
							'name' => 'X-UAFree-Key',
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
