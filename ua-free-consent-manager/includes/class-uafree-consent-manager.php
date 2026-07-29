<?php
/**
 * UA FREE Consent Manager runtime.
 */

namespace UAFree\ConsentManager;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Consent_Manager {
	private const OPTION_NAME = 'uafree_consent_manager_settings';
	private const COOKIE_NAME = 'uafree_consent';
	private const PAGE_SLUG   = 'ua-free-consent-manager';
	private const CATEGORIES  = array( 'analytics', 'advertising', 'external_media' );

	private static bool $booted = false;
	private static array $integrations = array();
	private static array $frontend_integrations = array();
	private static ?array $status_cache = null;

	public static function init(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 20 );
		add_action( 'admin_post_uafree_consent_manager_save', array( __CLASS__, 'save_settings' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'prepare_registered_scripts' ), PHP_INT_MAX );
		add_action( 'wp_print_scripts', array( __CLASS__, 'prepare_registered_scripts' ), PHP_INT_MAX );
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'prepare_registered_scripts' ), 1 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'filter_script_tag' ), PHP_INT_MAX, 3 );
		add_action( 'wp_footer', array( __CLASS__, 'render_frontend' ), 100 );
	}

	public static function load_textdomain(): void {
		load_plugin_textdomain(
			'ua-free-consent-manager',
			false,
			dirname( plugin_basename( UAFREE_CONSENT_MANAGER_FILE ) ) . '/languages'
		);
	}

	public static function activate(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults(), '', false );
		}
	}

	public static function defaults(): array {
		return array(
			'enabled'              => true,
			'policy_version'       => '1',
			'cookie_lifetime_days' => 180,
			'debug_mode'           => false,
			'banner_title'         => 'Налаштування конфіденційності',
			'banner_message'       => 'Ми використовуємо необхідні технології для роботи сайту. Аналітика, реклама та зовнішні медіа вмикаються лише після вашої згоди.',
			'accept_label'         => 'Прийняти всі',
			'reject_label'         => 'Відхилити необов’язкові',
			'customize_label'      => 'Налаштувати',
			'save_label'           => 'Зберегти вибір',
			'reopen_label'         => 'Налаштування конфіденційності',
		);
	}

	public static function settings(): array {
		$stored = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	public static function sanitize_settings( array $input ): array {
		$defaults = self::defaults();
		$policy   = sanitize_text_field( (string) ( $input['policy_version'] ?? $defaults['policy_version'] ) );
		$policy   = preg_replace( '/[^A-Za-z0-9._-]/', '', $policy ) ?? '';
		$policy   = trim( $policy, '._-' );
		if ( '' === $policy ) {
			$policy = '1';
		}

		$lifetime = (int) ( $input['cookie_lifetime_days'] ?? $defaults['cookie_lifetime_days'] );
		$lifetime = max( 1, min( 730, $lifetime ) );

		$clean_text = static function ( mixed $value, string $fallback, int $max_length ): string {
			$text = sanitize_textarea_field( (string) $value );
			if ( '' === $text ) {
				$text = $fallback;
			}
			return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max_length ) : substr( $text, 0, $max_length );
		};

		return array(
			'enabled'              => ! empty( $input['enabled'] ),
			'policy_version'       => $policy,
			'cookie_lifetime_days' => $lifetime,
			'debug_mode'           => ! empty( $input['debug_mode'] ),
			'banner_title'         => $clean_text( $input['banner_title'] ?? '', $defaults['banner_title'], 120 ),
			'banner_message'       => $clean_text( $input['banner_message'] ?? '', $defaults['banner_message'], 700 ),
			'accept_label'         => $clean_text( $input['accept_label'] ?? '', $defaults['accept_label'], 80 ),
			'reject_label'         => $clean_text( $input['reject_label'] ?? '', $defaults['reject_label'], 80 ),
			'customize_label'      => $clean_text( $input['customize_label'] ?? '', $defaults['customize_label'], 80 ),
			'save_label'           => $clean_text( $input['save_label'] ?? '', $defaults['save_label'], 80 ),
			'reopen_label'         => $clean_text( $input['reopen_label'] ?? '', $defaults['reopen_label'], 100 ),
		);
	}

	public static function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to change these settings.', 'ua-free-consent-manager' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'uafree_consent_manager_save', 'uafree_consent_manager_nonce' );

		$raw   = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		$clean = self::sanitize_settings( $raw );
		update_option( self::OPTION_NAME, $clean, false );
		self::$status_cache = null;

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function register_admin_page(): void {
		add_submenu_page(
			'uafree-suite',
			__( 'UA FREE Consent Manager', 'ua-free-consent-manager' ),
			__( 'Consent Manager', 'ua-free-consent-manager' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings     = self::settings();
		$integrations = self::public_integrations();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'UA FREE Consent Manager', 'ua-free-consent-manager' ); ?> <code><?php echo esc_html( UAFREE_CONSENT_MANAGER_VERSION ); ?></code></h1>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Settings saved.', 'ua-free-consent-manager' ); ?></p></div>
			<?php endif; ?>
			<p><?php echo esc_html__( 'Optional consent is disabled by default. The plugin stores no IP addresses, User-Agent values, emails or consent event logs.', 'ua-free-consent-manager' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="uafree_consent_manager_save">
				<?php wp_nonce_field( 'uafree_consent_manager_save', 'uafree_consent_manager_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Consent banner', 'ua-free-consent-manager' ); ?></th>
						<td><label><input type="checkbox" name="settings[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php echo esc_html__( 'Enable the frontend banner', 'ua-free-consent-manager' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="uafree-policy-version"><?php echo esc_html__( 'Policy version', 'ua-free-consent-manager' ); ?></label></th>
						<td><input class="regular-text" id="uafree-policy-version" name="settings[policy_version]" value="<?php echo esc_attr( $settings['policy_version'] ); ?>" maxlength="60"><p class="description"><?php echo esc_html__( 'Changing this value asks every visitor for consent again.', 'ua-free-consent-manager' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="uafree-cookie-days"><?php echo esc_html__( 'Cookie lifetime, days', 'ua-free-consent-manager' ); ?></label></th>
						<td><input type="number" min="1" max="730" id="uafree-cookie-days" name="settings[cookie_lifetime_days]" value="<?php echo esc_attr( (string) $settings['cookie_lifetime_days'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Debug mode', 'ua-free-consent-manager' ); ?></th>
						<td><label><input type="checkbox" name="settings[debug_mode]" value="1" <?php checked( ! empty( $settings['debug_mode'] ) ); ?>> <?php echo esc_html__( 'Show browser console messages without persistent logging', 'ua-free-consent-manager' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="uafree-banner-title"><?php echo esc_html__( 'Banner title', 'ua-free-consent-manager' ); ?></label></th>
						<td><input class="large-text" id="uafree-banner-title" name="settings[banner_title]" value="<?php echo esc_attr( $settings['banner_title'] ); ?>" maxlength="120"></td>
					</tr>
					<tr>
						<th scope="row"><label for="uafree-banner-message"><?php echo esc_html__( 'Banner message', 'ua-free-consent-manager' ); ?></label></th>
						<td><textarea class="large-text" rows="4" id="uafree-banner-message" name="settings[banner_message]" maxlength="700"><?php echo esc_textarea( $settings['banner_message'] ); ?></textarea></td>
					</tr>
					<?php
					$labels = array(
						'accept_label'    => __( 'Accept-all button', 'ua-free-consent-manager' ),
						'reject_label'    => __( 'Reject-optional button', 'ua-free-consent-manager' ),
						'customize_label' => __( 'Customize button', 'ua-free-consent-manager' ),
						'save_label'      => __( 'Save-selection button', 'ua-free-consent-manager' ),
						'reopen_label'    => __( 'Reopen-settings button', 'ua-free-consent-manager' ),
					);
					foreach ( $labels as $key => $label ) :
						?>
						<tr>
							<th scope="row"><label for="uafree-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input class="regular-text" id="uafree-<?php echo esc_attr( $key ); ?>" name="settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $settings[ $key ] ); ?>" maxlength="100"></td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php echo esc_html__( 'Registered local integrations', 'ua-free-consent-manager' ); ?></h2>
			<?php if ( empty( $integrations ) ) : ?>
				<p><?php echo esc_html__( 'No optional script integrations are registered on this request.', 'ua-free-consent-manager' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th><?php echo esc_html__( 'ID', 'ua-free-consent-manager' ); ?></th><th><?php echo esc_html__( 'Name', 'ua-free-consent-manager' ); ?></th><th><?php echo esc_html__( 'Category', 'ua-free-consent-manager' ); ?></th><th><?php echo esc_html__( 'Script handles', 'ua-free-consent-manager' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $integrations as $integration ) : ?>
						<tr><td><code><?php echo esc_html( $integration['id'] ); ?></code></td><td><?php echo esc_html( $integration['name'] ); ?></td><td><code><?php echo esc_html( $integration['category'] ); ?></code></td><td><?php echo esc_html( implode( ', ', $integration['script_handles'] ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Public read-only contract', 'ua-free-consent-manager' ); ?></h2>
			<pre><?php echo esc_html( wp_json_encode( self::status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
		</div>
		<?php
	}

	public static function register_integration( array $integration ): bool {
		$allowed_keys = array( 'id', 'name', 'category', 'script_handles' );
		if ( array_diff( array_keys( $integration ), $allowed_keys ) ) {
			return false;
		}

		$id       = sanitize_key( (string) ( $integration['id'] ?? '' ) );
		$name     = sanitize_text_field( (string) ( $integration['name'] ?? $id ) );
		$category = sanitize_key( (string) ( $integration['category'] ?? '' ) );
		$handles  = isset( $integration['script_handles'] ) && is_array( $integration['script_handles'] ) ? $integration['script_handles'] : array();

		if ( '' === $id || ! in_array( $category, self::CATEGORIES, true ) || empty( $handles ) ) {
			return false;
		}

		$clean_handles = array();
		foreach ( $handles as $handle ) {
			$clean = sanitize_key( (string) $handle );
			if ( '' !== $clean ) {
				$clean_handles[] = $clean;
			}
		}
		$clean_handles = array_values( array_unique( $clean_handles ) );
		if ( empty( $clean_handles ) ) {
			return false;
		}

		self::$integrations[ $id ] = array(
			'id'             => $id,
			'name'           => '' !== $name ? $name : $id,
			'category'       => $category,
			'script_handles' => $clean_handles,
		);
		return true;
	}

	private static function normalized_integrations(): array {
		$filtered = apply_filters( 'uafree_consent_manager_integrations', self::$integrations );
		if ( ! is_array( $filtered ) ) {
			$filtered = self::$integrations;
		}

		$normalized = array();
		foreach ( $filtered as $key => $integration ) {
			if ( ! is_array( $integration ) ) {
				continue;
			}
			if ( ! isset( $integration['id'] ) && is_string( $key ) ) {
				$integration['id'] = $key;
			}
			$allowed = array_intersect_key( $integration, array_flip( array( 'id', 'name', 'category', 'script_handles' ) ) );
			$id      = sanitize_key( (string) ( $allowed['id'] ?? '' ) );
			$cat     = sanitize_key( (string) ( $allowed['category'] ?? '' ) );
			$handles = isset( $allowed['script_handles'] ) && is_array( $allowed['script_handles'] ) ? $allowed['script_handles'] : array();
			if ( '' === $id || ! in_array( $cat, self::CATEGORIES, true ) ) {
				continue;
			}
			$clean_handles = array_values(
				array_unique(
					array_filter(
						array_map(
							static fn( mixed $handle ): string => sanitize_key( (string) $handle ),
							$handles
						)
					)
				)
			);
			if ( empty( $clean_handles ) ) {
				continue;
			}
			$normalized[ $id ] = array(
				'id'             => $id,
				'name'           => sanitize_text_field( (string) ( $allowed['name'] ?? $id ) ),
				'category'       => $cat,
				'script_handles' => $clean_handles,
			);
		}
		ksort( $normalized );
		return $normalized;
	}

	public static function public_integrations(): array {
		return array_values( self::normalized_integrations() );
	}

	public static function status(): array {
		if ( null !== self::$status_cache ) {
			return self::$status_cache;
		}

		$policy  = (string) self::settings()['policy_version'];
		$default = array(
			'necessary'      => true,
			'analytics'      => false,
			'advertising'    => false,
			'external_media' => false,
			'policy_version' => $policy,
			'updated_at'     => null,
		);

		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			self::$status_cache = $default;
			return $default;
		}

		$raw = rawurldecode( (string) wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
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
		$status = self::status();
		return null !== $status['updated_at'];
	}

	public static function is_allowed( string $category ): bool {
		$category = sanitize_key( $category );
		if ( 'necessary' === $category ) {
			return true;
		}
		if ( ! in_array( $category, self::CATEGORIES, true ) ) {
			return false;
		}
		$status = self::status();
		return true === $status[ $category ];
	}

	public static function prepare_registered_scripts(): void {
		foreach ( self::normalized_integrations() as $integration ) {
			$scripts = array();
			foreach ( $integration['script_handles'] as $handle ) {
				$record = self::script_record( $handle );
				if ( null !== $record ) {
					$scripts[] = $record;
				}
				wp_dequeue_script( $handle );
			}

			self::$frontend_integrations[ $integration['id'] ] = array(
				'id'       => $integration['id'],
				'name'     => $integration['name'],
				'category' => $integration['category'],
				'scripts'  => $scripts,
			);
		}

		self::localize_frontend();
	}

	private static function script_record( string $handle ): ?array {
		$wp_scripts = wp_scripts();
		if ( ! isset( $wp_scripts->registered[ $handle ] ) ) {
			return null;
		}

		$dependency = $wp_scripts->registered[ $handle ];
		$extra      = is_array( $dependency->extra ?? null ) ? $dependency->extra : array();
		$src        = is_string( $dependency->src ?? null ) ? $dependency->src : '';

		if ( '' !== $src && str_starts_with( $src, '/' ) ) {
			$src = home_url( $src );
		} elseif ( '' !== $src && ! preg_match( '#^(?:https?:)?//#i', $src ) ) {
			$src = rtrim( (string) $wp_scripts->base_url, '/' ) . '/' . ltrim( $src, '/' );
		}
		if ( '' !== $src && false !== ( $dependency->ver ?? false ) && null !== ( $dependency->ver ?? null ) && '' !== (string) $dependency->ver ) {
			$src = add_query_arg( 'ver', (string) $dependency->ver, $src );
		}

		$clean_inline = static function ( mixed $value ): array {
			$values = is_array( $value ) ? $value : ( is_string( $value ) && '' !== $value ? array( $value ) : array() );
			return array_values( array_filter( $values, 'is_string' ) );
		};

		$before = $clean_inline( $extra['before'] ?? array() );
		$data   = $clean_inline( $extra['data'] ?? array() );
		$after  = $clean_inline( $extra['after'] ?? array() );
		if ( '' === $src && empty( $before ) && empty( $data ) && empty( $after ) ) {
			return null;
		}

		return array(
			'handle'        => sanitize_key( $handle ),
			'src'           => '' !== $src ? esc_url_raw( $src ) : '',
			'type'          => 'module' === ( $extra['type'] ?? '' ) ? 'module' : 'text/javascript',
			'inline_before' => array_merge( $data, $before ),
			'inline_after'  => $after,
		);
	}

	private static function localize_frontend(): void {
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) || ! wp_script_is( 'uafree-consent-manager', 'enqueued' ) ) {
			return;
		}

		wp_localize_script(
			'uafree-consent-manager',
			'UAFreeConsentManager',
			array(
				'cookieName'     => self::COOKIE_NAME,
				'cookieLifetime' => (int) $settings['cookie_lifetime_days'],
				'policyVersion'  => (string) $settings['policy_version'],
				'secureCookie'   => is_ssl(),
				'debug'          => ! empty( $settings['debug_mode'] ),
				'integrations'   => array_values( self::$frontend_integrations ),
			)
		);
	}

	public static function filter_script_tag( string $tag, string $handle, string $src = '' ): string {
		unset( $src );
		foreach ( self::normalized_integrations() as $integration ) {
			if ( in_array( $handle, $integration['script_handles'], true ) ) {
				return '';
			}
		}
		return $tag;
	}

	public static function enqueue_assets(): void {
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		wp_enqueue_style(
			'uafree-consent-manager',
			UAFREE_CONSENT_MANAGER_URL . 'assets/consent-manager.css',
			array(),
			UAFREE_CONSENT_MANAGER_VERSION
		);
		wp_enqueue_script(
			'uafree-consent-manager',
			UAFREE_CONSENT_MANAGER_URL . 'assets/consent-manager.js',
			array(),
			UAFREE_CONSENT_MANAGER_VERSION,
			true
		);
	}

	public static function render_frontend(): void {
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		?>
		<div id="uafree-consent" class="uafree-consent"<?php echo self::has_valid_choice() ? ' hidden' : ''; ?>>
			<div class="uafree-consent__panel" role="dialog" aria-labelledby="uafree-consent-title" aria-describedby="uafree-consent-message">
				<h2 id="uafree-consent-title"><?php echo esc_html( $settings['banner_title'] ); ?></h2>
				<p id="uafree-consent-message"><?php echo esc_html( $settings['banner_message'] ); ?></p>
				<div id="uafree-consent-preferences" class="uafree-consent__preferences" hidden>
					<label class="uafree-consent__choice"><input type="checkbox" checked disabled> <span><?php echo esc_html__( 'Necessary', 'ua-free-consent-manager' ); ?></span><small><?php echo esc_html__( 'Always active for core site functions.', 'ua-free-consent-manager' ); ?></small></label>
					<label class="uafree-consent__choice"><input id="uafree-consent-analytics" type="checkbox"> <span><?php echo esc_html__( 'Analytics', 'ua-free-consent-manager' ); ?></span><small><?php echo esc_html__( 'Helps understand site usage.', 'ua-free-consent-manager' ); ?></small></label>
					<label class="uafree-consent__choice"><input id="uafree-consent-advertising" type="checkbox"> <span><?php echo esc_html__( 'Advertising', 'ua-free-consent-manager' ); ?></span><small><?php echo esc_html__( 'Allows advertising and conversion scripts.', 'ua-free-consent-manager' ); ?></small></label>
					<label class="uafree-consent__choice"><input id="uafree-consent-external-media" type="checkbox"> <span><?php echo esc_html__( 'External media', 'ua-free-consent-manager' ); ?></span><small><?php echo esc_html__( 'Allows embedded third-party media adapters.', 'ua-free-consent-manager' ); ?></small></label>
				</div>
				<div class="uafree-consent__actions">
					<button type="button" class="uafree-consent__button uafree-consent__button--primary" data-uafree-consent="accept"><?php echo esc_html( $settings['accept_label'] ); ?></button>
					<button type="button" class="uafree-consent__button" data-uafree-consent="reject"><?php echo esc_html( $settings['reject_label'] ); ?></button>
					<button type="button" class="uafree-consent__button" data-uafree-consent="customize" aria-expanded="false" aria-controls="uafree-consent-preferences"><?php echo esc_html( $settings['customize_label'] ); ?></button>
					<button type="button" class="uafree-consent__button uafree-consent__button--primary" data-uafree-consent="save" hidden><?php echo esc_html( $settings['save_label'] ); ?></button>
				</div>
				<?php if ( function_exists( 'get_privacy_policy_url' ) && get_privacy_policy_url() ) : ?>
					<p class="uafree-consent__privacy"><a href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php echo esc_html__( 'Privacy policy', 'ua-free-consent-manager' ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>
		<button type="button" id="uafree-consent-reopen" class="uafree-consent-reopen" aria-controls="uafree-consent"<?php echo self::has_valid_choice() ? '' : ' hidden'; ?>><?php echo esc_html( $settings['reopen_label'] ); ?></button>
		<?php
	}
}
