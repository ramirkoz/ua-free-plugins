<?php
/**
 * KOZ Consent Manager runtime.
 */

namespace ramirkz\kozconsent;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZCONSENT_Manager {
	private const OPTION_NAME = 'kozconsent_settings';
	private const COOKIE_NAME = 'kozconsent';
	private const PAGE_SLUG   = 'koz-consent-manager';
	private const FALLBACK_SUITE_PAGE = 'kozconsent-suite';
	private const SAVE_ACTION = 'kozconsent_save';
	private const NONCE_NAME = 'kozconsent_nonce';
	private const CATEGORIES  = array( 'analytics', 'advertising', 'external_media' );
	private const TEMPLATE_FIELDS = array(
		'banner_title',
		'banner_message',
		'accept_label',
		'reject_label',
		'customize_label',
		'save_label',
		'reopen_label',
	);
	private const SUPPORTED_LANGUAGES = array( 'uk', 'en', 'zh', 'es', 'ar', 'id', 'pt', 'fr', 'ja', 'de', 'hi' );

	private static bool $booted = false;
	private static array $integrations = array();
	private static array $frontend_integrations = array();
	private static ?array $status_cache = null;
	private static ?array $template_defaults_cache = null;

	public static function init(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 20 );
		self::maybe_migrate();
		add_action( 'admin_post_' . self::SAVE_ACTION, array( __CLASS__, 'save_settings' ) );
		add_action( 'wp_head', array( __CLASS__, 'render_google_consent_mode' ), -1000 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'prepare_registered_scripts' ), PHP_INT_MAX );
		add_action( 'wp_print_scripts', array( __CLASS__, 'prepare_registered_scripts' ), PHP_INT_MAX );
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'prepare_registered_scripts' ), 1 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'filter_script_tag' ), PHP_INT_MAX, 3 );
		add_action( 'wp_footer', array( __CLASS__, 'render_frontend' ), 100 );
	}

	private static function legacy_option_name(): string {
		return implode( '_', array( 'ua', 'free', 'consent', 'manager', 'settings' ) );
	}

	private static function legacy_cookie_name(): string {
		return implode( '_', array( 'ua', 'free', 'consent' ) );
	}

	public static function activate(): void {
		self::maybe_migrate();
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::storage_defaults(), '', false );
		}
	}

	private static function maybe_migrate(): void {
		if ( false !== get_option( self::OPTION_NAME, false ) ) {
			return;
		}

		$legacy = get_option( self::legacy_option_name(), false );
		if ( is_array( $legacy ) ) {
			add_option( self::OPTION_NAME, self::normalized_option( $legacy ), '', false );
		}
	}

	private static function language_from_locale( string $locale ): string {
		$locale   = strtolower( str_replace( '-', '_', $locale ) );
		$language = strtok( $locale, '_' );
		return is_string( $language ) && in_array( $language, self::SUPPORTED_LANGUAGES, true ) ? $language : 'en';
	}

	private static function context_language(): string {
		if ( is_admin() && function_exists( 'get_user_locale' ) ) {
			return self::language_from_locale( (string) get_user_locale() );
		}
		if ( function_exists( 'determine_locale' ) ) {
			return self::language_from_locale( (string) determine_locale() );
		}
		return self::language_from_locale( (string) get_locale() );
	}

	private static function site_language(): string {
		return self::language_from_locale( (string) get_locale() );
	}

	private static function language_name( string $language ): string {
		$names = array(
			'uk' => 'Українська',
			'en' => 'English',
			'zh' => '中文',
			'es' => 'Español',
			'ar' => 'العربية',
			'id' => 'Bahasa Indonesia',
			'pt' => 'Português',
			'fr' => 'Français',
			'ja' => '日本語',
			'de' => 'Deutsch',
			'hi' => 'हिन्दी',
		);
		return $names[ $language ] ?? 'English';
	}

	private static function storage_defaults(): array {
		return array(
			'schema_version'       => 2,
			'enabled'              => true,
			'policy_version'       => '1',
			'cookie_lifetime_days' => 180,
			'debug_mode'           => false,
			'google_consent_mode'  => false,
			'templates'            => array(),
		);
	}

	private static function all_template_defaults(): array {
		if ( null !== self::$template_defaults_cache ) {
			return self::$template_defaults_cache;
		}

		$file = KOZCONSENT_DIR . 'assets/template-defaults.json';
		$data = is_readable( $file ) ? wp_json_file_decode( $file, array( 'associative' => true ) ) : null;
		self::$template_defaults_cache = is_array( $data ) ? $data : array();
		return self::$template_defaults_cache;
	}

	private static function template_defaults( string $language ): array {
		$language = in_array( $language, self::SUPPORTED_LANGUAGES, true ) ? $language : 'en';
		$all      = self::all_template_defaults();
		$english  = isset( $all['en'] ) && is_array( $all['en'] ) ? $all['en'] : array(
			'banner_title'    => 'Privacy settings',
			'banner_message'  => 'We use necessary technologies to operate the site. Analytics, advertising and external media are enabled only after your consent.',
			'accept_label'    => 'Accept all',
			'reject_label'    => 'Reject optional',
			'customize_label' => 'Customize',
			'save_label'      => 'Save choices',
			'reopen_label'    => 'Privacy settings',
		);
		$template = isset( $all[ $language ] ) && is_array( $all[ $language ] ) ? $all[ $language ] : $english;

		$clean = array();
		foreach ( self::TEMPLATE_FIELDS as $field ) {
			$value = isset( $template[ $field ] ) && is_string( $template[ $field ] ) && '' !== $template[ $field ]
				? $template[ $field ]
				: (string) ( $english[ $field ] ?? '' );
			$clean[ $field ] = $value;
		}
		return $clean;
	}

	private static function sanitize_template( array $input, string $language ): array {
		$defaults = self::template_defaults( $language );
		$limits   = array(
			'banner_title'    => 120,
			'banner_message'  => 700,
			'accept_label'    => 80,
			'reject_label'    => 80,
			'customize_label' => 80,
			'save_label'      => 80,
			'reopen_label'    => 100,
		);
		$clean = array();
		foreach ( self::TEMPLATE_FIELDS as $field ) {
			$text = sanitize_textarea_field( (string) ( $input[ $field ] ?? '' ) );
			if ( '' === $text ) {
				$text = $defaults[ $field ];
			}
			$limit = $limits[ $field ];
			$clean[ $field ] = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit ) : substr( $text, 0, $limit );
		}
		return $clean;
	}

	private static function normalized_option( mixed $stored ): array {
		$normalized = self::storage_defaults();
		if ( ! is_array( $stored ) ) {
			return $normalized;
		}

		$normalized['enabled']              = array_key_exists( 'enabled', $stored ) ? ! empty( $stored['enabled'] ) : true;
		$normalized['policy_version']       = isset( $stored['policy_version'] ) ? sanitize_text_field( (string) $stored['policy_version'] ) : '1';
		$normalized['cookie_lifetime_days'] = isset( $stored['cookie_lifetime_days'] ) ? max( 1, min( 730, (int) $stored['cookie_lifetime_days'] ) ) : 180;
		$normalized['debug_mode']           = ! empty( $stored['debug_mode'] );
		$normalized['google_consent_mode']   = ! empty( $stored['google_consent_mode'] );

		if ( isset( $stored['templates'] ) && is_array( $stored['templates'] ) ) {
			foreach ( self::SUPPORTED_LANGUAGES as $language ) {
				if ( isset( $stored['templates'][ $language ] ) && is_array( $stored['templates'][ $language ] ) ) {
					$normalized['templates'][ $language ] = self::sanitize_template( $stored['templates'][ $language ], $language );
				}
			}
			return $normalized;
		}

		$legacy = array_intersect_key( $stored, array_flip( self::TEMPLATE_FIELDS ) );
		if ( ! empty( $legacy ) ) {
			$language = self::site_language();
			$normalized['templates'][ $language ] = self::sanitize_template( $legacy, $language );
		}
		return $normalized;
	}

	private static function option_settings(): array {
		return self::normalized_option( get_option( self::OPTION_NAME, array() ) );
	}

	public static function defaults(): array {
		$language = self::context_language();
		return array_merge( self::storage_defaults(), self::template_defaults( $language ) );
	}

	public static function settings(): array {
		$language   = self::context_language();
		$normalized = self::option_settings();
		$template   = isset( $normalized['templates'][ $language ] )
			? $normalized['templates'][ $language ]
			: self::template_defaults( $language );

		unset( $normalized['templates'] );
		return array_merge( $normalized, $template );
	}

	public static function sanitize_settings( array $input ): array {
		$language = self::context_language();
		$defaults = array_merge( self::storage_defaults(), self::template_defaults( $language ) );
		$policy   = sanitize_text_field( (string) ( $input['policy_version'] ?? $defaults['policy_version'] ) );
		$policy   = preg_replace( '/[^A-Za-z0-9._-]/', '', $policy ) ?? '';
		$policy   = trim( $policy, '._-' );
		if ( '' === $policy ) {
			$policy = '1';
		}

		$lifetime = (int) ( $input['cookie_lifetime_days'] ?? $defaults['cookie_lifetime_days'] );
		$lifetime = max( 1, min( 730, $lifetime ) );
		$template = self::sanitize_template( $input, $language );

		return array_merge(
			array(
				'enabled'              => ! empty( $input['enabled'] ),
				'policy_version'       => $policy,
				'cookie_lifetime_days' => $lifetime,
				'debug_mode'           => ! empty( $input['debug_mode'] ),
				'google_consent_mode'  => ! empty( $input['google_consent_mode'] ),
			),
			$template
		);
	}

	public static function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to change these settings.', 'koz-consent-manager' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::SAVE_ACTION, self::NONCE_NAME );

		$raw = filter_input( INPUT_POST, 'settings', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$raw = is_array( $raw ) ? $raw : array();
		$language_input = filter_input( INPUT_POST, 'template_language', FILTER_SANITIZE_SPECIAL_CHARS );
		$language = is_string( $language_input ) && in_array( sanitize_key( $language_input ), self::SUPPORTED_LANGUAGES, true )
			? sanitize_key( $language_input )
			: self::context_language();

		$clean      = self::sanitize_settings( $raw );
		$normalized = self::option_settings();
		$normalized['schema_version']       = 2;
		$normalized['enabled']              = $clean['enabled'];
		$normalized['policy_version']       = $clean['policy_version'];
		$normalized['cookie_lifetime_days'] = $clean['cookie_lifetime_days'];
		$normalized['debug_mode']           = $clean['debug_mode'];
		$normalized['google_consent_mode']   = $clean['google_consent_mode'];
		$normalized['templates'][ $language ] = self::sanitize_template( $raw, $language );

		update_option( self::OPTION_NAME, $normalized, false );
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

	private static function find_existing_suite_page(): string {
		global $menu;

		foreach ( (array) $menu as $item ) {
			$label = isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : '';
			$slug  = isset( $item[2] ) ? (string) $item[2] : '';
			if ( 'KOZ Suite' === trim( $label ) && '' !== $slug ) {
				return $slug;
			}
		}

		return '';
	}

	private static function suite_page(): string {
		$existing = self::find_existing_suite_page();
		if ( '' !== $existing ) {
			return $existing;
		}

		add_menu_page(
			__( 'KOZ WordPress Suite', 'koz-consent-manager' ),
			__( 'KOZ Suite', 'koz-consent-manager' ),
			'manage_options',
			self::FALLBACK_SUITE_PAGE,
			array( __CLASS__, 'render_admin_page' ),
			'dashicons-layout',
			58
		);

		return self::FALLBACK_SUITE_PAGE;
	}

	public static function register_admin_page(): void {
		add_submenu_page(
			self::suite_page(),
			__( 'KOZ Consent Manager', 'koz-consent-manager' ),
			__( 'Consent Manager', 'koz-consent-manager' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings          = self::settings();
		$template_language = self::context_language();
		$integrations      = self::public_integrations();
		$updated           = filter_input( INPUT_GET, 'updated', FILTER_SANITIZE_SPECIAL_CHARS );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'KOZ Consent Manager', 'koz-consent-manager' ); ?> <code><?php echo esc_html( KOZCONSENT_VERSION ); ?></code></h1>
			<?php if ( '1' === $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Settings saved.', 'koz-consent-manager' ); ?></p></div>
			<?php endif; ?>
			<p><?php echo esc_html__( 'Optional consent is disabled by default. The plugin stores no IP addresses, User-Agent values, emails or consent event logs.', 'koz-consent-manager' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
				<input type="hidden" name="template_language" value="<?php echo esc_attr( $template_language ); ?>">
				<?php wp_nonce_field( self::SAVE_ACTION, self::NONCE_NAME ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Consent banner', 'koz-consent-manager' ); ?></th>
						<td><label><input type="checkbox" name="settings[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php echo esc_html__( 'Enable the frontend banner', 'koz-consent-manager' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="kozconsent-policy-version"><?php echo esc_html__( 'Policy version', 'koz-consent-manager' ); ?></label></th>
						<td><input class="regular-text" id="kozconsent-policy-version" name="settings[policy_version]" value="<?php echo esc_attr( $settings['policy_version'] ); ?>" maxlength="60"><p class="description"><?php echo esc_html__( 'Changing this value asks every visitor for consent again.', 'koz-consent-manager' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="kozconsent-cookie-days"><?php echo esc_html__( 'Cookie lifetime, days', 'koz-consent-manager' ); ?></label></th>
						<td><input type="number" min="1" max="730" id="kozconsent-cookie-days" name="settings[cookie_lifetime_days]" value="<?php echo esc_attr( (string) $settings['cookie_lifetime_days'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Debug mode', 'koz-consent-manager' ); ?></th>
						<td><label><input type="checkbox" name="settings[debug_mode]" value="1" <?php checked( ! empty( $settings['debug_mode'] ) ); ?>> <?php echo esc_html__( 'Show browser console messages without persistent logging', 'koz-consent-manager' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Google Consent Mode v2', 'koz-consent-manager' ); ?></th>
						<td>
							<label><input type="checkbox" name="settings[google_consent_mode]" value="1" <?php checked( ! empty( $settings['google_consent_mode'] ) ); ?>> <?php echo esc_html__( 'Synchronize consent with Google tags', 'koz-consent-manager' ); ?></label>
							<p class="description"><?php echo esc_html__( 'Analytics maps to analytics_storage. Advertising maps to ad_storage, ad_user_data and ad_personalization. All four default to denied until the visitor grants the matching category.', 'koz-consent-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Banner template language', 'koz-consent-manager' ); ?></th>
						<td><strong lang="<?php echo esc_attr( $template_language ); ?>"><?php echo esc_html( self::language_name( $template_language ) ); ?></strong></td>
					</tr>
					<tr>
						<th scope="row"><label for="kozconsent-banner-title"><?php echo esc_html__( 'Banner title', 'koz-consent-manager' ); ?></label></th>
						<td><input class="large-text" id="kozconsent-banner-title" name="settings[banner_title]" value="<?php echo esc_attr( $settings['banner_title'] ); ?>" maxlength="120"></td>
					</tr>
					<tr>
						<th scope="row"><label for="kozconsent-banner-message"><?php echo esc_html__( 'Banner message', 'koz-consent-manager' ); ?></label></th>
						<td><textarea class="large-text" rows="4" id="kozconsent-banner-message" name="settings[banner_message]" maxlength="700"><?php echo esc_textarea( $settings['banner_message'] ); ?></textarea></td>
					</tr>
					<?php
					$labels = array(
						'accept_label'    => __( 'Accept-all button', 'koz-consent-manager' ),
						'reject_label'    => __( 'Reject-optional button', 'koz-consent-manager' ),
						'customize_label' => __( 'Customize button', 'koz-consent-manager' ),
						'save_label'      => __( 'Save-selection button', 'koz-consent-manager' ),
						'reopen_label'    => __( 'Reopen-settings button', 'koz-consent-manager' ),
					);
					foreach ( $labels as $key => $label ) :
						?>
						<tr>
							<th scope="row"><label for="kozconsent-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input class="regular-text" id="kozconsent-<?php echo esc_attr( $key ); ?>" name="settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $settings[ $key ] ); ?>" maxlength="100"></td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php echo esc_html__( 'Registered local integrations', 'koz-consent-manager' ); ?></h2>
			<?php if ( empty( $integrations ) ) : ?>
				<p><?php echo esc_html__( 'No optional script integrations are registered on this request.', 'koz-consent-manager' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th><?php echo esc_html__( 'ID', 'koz-consent-manager' ); ?></th><th><?php echo esc_html__( 'Name', 'koz-consent-manager' ); ?></th><th><?php echo esc_html__( 'Category', 'koz-consent-manager' ); ?></th><th><?php echo esc_html__( 'Script handles', 'koz-consent-manager' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $integrations as $integration ) : ?>
						<tr><td><code><?php echo esc_html( $integration['id'] ); ?></code></td><td><?php echo esc_html( $integration['name'] ); ?></td><td><code><?php echo esc_html( $integration['category'] ); ?></code></td><td><?php echo esc_html( implode( ', ', $integration['script_handles'] ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Public read-only contract', 'koz-consent-manager' ); ?></h2>
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
		$filtered = apply_filters( 'kozconsent_integrations', self::$integrations );
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

		$cookie_value = filter_input( INPUT_COOKIE, self::COOKIE_NAME, FILTER_UNSAFE_RAW );
		if ( ! is_string( $cookie_value ) ) {
			$cookie_value = filter_input( INPUT_COOKIE, self::legacy_cookie_name(), FILTER_UNSAFE_RAW );
		}
		if ( ! is_string( $cookie_value ) ) {
			self::$status_cache = $default;
			return $default;
		}

		$raw = rawurldecode( sanitize_text_field( $cookie_value ) );
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
		if ( empty( $settings['enabled'] ) || ! wp_script_is( 'kozconsent-consent', 'enqueued' ) ) {
			return;
		}

		wp_localize_script(
			'kozconsent-consent',
			'KOZCONSENTConfig',
			array(
				'cookieName'       => self::COOKIE_NAME,
				'legacyCookieName' => self::legacy_cookie_name(),
				'cookieLifetime' => (int) $settings['cookie_lifetime_days'],
				'policyVersion'  => (string) $settings['policy_version'],
				'secureCookie'   => is_ssl(),
				'debug'             => ! empty( $settings['debug_mode'] ),
				'googleConsentMode' => ! empty( $settings['google_consent_mode'] ),
				'integrations'      => array_values( self::$frontend_integrations ),
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

	public static function render_google_consent_mode(): void {
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) || empty( $settings['google_consent_mode'] ) ) {
			return;
		}

		$cookie_name        = wp_json_encode( self::COOKIE_NAME );
		$legacy_cookie_name = wp_json_encode( self::legacy_cookie_name() );
		$policy_version     = wp_json_encode( (string) $settings['policy_version'] );
		?>
		<script id="kozconsent-google-consent-mode">
		(function () {
			'use strict';
			window.dataLayer = window.dataLayer || [];
			window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };

			var cookieNames = [<?php echo $cookie_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>, <?php echo $legacy_cookie_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>];
			var policyVersion = <?php echo $policy_version; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
			var analytics = false;
			var advertising = false;
			var parts = document.cookie ? document.cookie.split(';') : [];

			for (var nameIndex = 0; nameIndex < cookieNames.length; nameIndex += 1) {
				var prefix = String(cookieNames[nameIndex] || '') + '=';
				for (var index = 0; index < parts.length; index += 1) {
					var item = parts[index].trim();
					if (item.indexOf(prefix) !== 0) continue;
					try {
						var parsed = JSON.parse(decodeURIComponent(item.slice(prefix.length)));
						var valid = parsed && parsed.schema_version === 1 && String(parsed.policy_version || '') === String(policyVersion || '') && typeof parsed.updated_at === 'string';
						if (valid) {
							analytics = parsed.analytics === true;
							advertising = parsed.advertising === true;
						}
					} catch (error) {}
					nameIndex = cookieNames.length;
					break;
				}
			}

			window.gtag('consent', 'default', {
				'ad_storage': advertising ? 'granted' : 'denied',
				'ad_user_data': advertising ? 'granted' : 'denied',
				'ad_personalization': advertising ? 'granted' : 'denied',
				'analytics_storage': analytics ? 'granted' : 'denied'
			});
		}());
		</script>
		<?php
	}

	public static function enqueue_assets(): void {
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		wp_enqueue_style(
			'kozconsent-consent',
			KOZCONSENT_URL . 'assets/consent-manager.css',
			array(),
			KOZCONSENT_VERSION
		);
		wp_enqueue_script(
			'kozconsent-consent',
			KOZCONSENT_URL . 'assets/consent-manager.js',
			array(),
			KOZCONSENT_VERSION,
			true
		);
	}

	public static function render_frontend(): void {
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		?>
		<div id="kozconsent" class="kozconsent"<?php echo self::has_valid_choice() ? ' hidden' : ''; ?>>
			<div class="kozconsent__panel" role="dialog" aria-labelledby="kozconsent-title" aria-describedby="kozconsent-message">
				<h2 id="kozconsent-title"><?php echo esc_html( $settings['banner_title'] ); ?></h2>
				<p id="kozconsent-message"><?php echo esc_html( $settings['banner_message'] ); ?></p>
				<div id="kozconsent-preferences" class="kozconsent__preferences" hidden>
					<label class="kozconsent__choice"><input type="checkbox" checked disabled> <span><?php echo esc_html__( 'Necessary', 'koz-consent-manager' ); ?></span><small><?php echo esc_html__( 'Always active for core site functions.', 'koz-consent-manager' ); ?></small></label>
					<label class="kozconsent__choice"><input id="kozconsent-analytics" type="checkbox"> <span><?php echo esc_html__( 'Analytics', 'koz-consent-manager' ); ?></span><small><?php echo esc_html__( 'Helps understand site usage.', 'koz-consent-manager' ); ?></small></label>
					<label class="kozconsent__choice"><input id="kozconsent-advertising" type="checkbox"> <span><?php echo esc_html__( 'Advertising', 'koz-consent-manager' ); ?></span><small><?php echo esc_html__( 'Allows advertising and conversion scripts.', 'koz-consent-manager' ); ?></small></label>
					<label class="kozconsent__choice"><input id="kozconsent-external-media" type="checkbox"> <span><?php echo esc_html__( 'External media', 'koz-consent-manager' ); ?></span><small><?php echo esc_html__( 'Allows embedded third-party media adapters.', 'koz-consent-manager' ); ?></small></label>
				</div>
				<div class="kozconsent__actions">
					<button type="button" class="kozconsent__button kozconsent__button--primary" data-kozconsent="accept"><?php echo esc_html( $settings['accept_label'] ); ?></button>
					<button type="button" class="kozconsent__button" data-kozconsent="reject"><?php echo esc_html( $settings['reject_label'] ); ?></button>
					<button type="button" class="kozconsent__button" data-kozconsent="customize" aria-expanded="false" aria-controls="kozconsent-preferences"><?php echo esc_html( $settings['customize_label'] ); ?></button>
					<button type="button" class="kozconsent__button kozconsent__button--primary" data-kozconsent="save" hidden><?php echo esc_html( $settings['save_label'] ); ?></button>
				</div>
				<?php if ( function_exists( 'get_privacy_policy_url' ) && get_privacy_policy_url() ) : ?>
					<p class="kozconsent__privacy"><a href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php echo esc_html__( 'Privacy policy', 'koz-consent-manager' ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>
		<button type="button" id="kozconsent-reopen" class="kozconsent-reopen" aria-controls="kozconsent"<?php echo self::has_valid_choice() ? '' : ' hidden'; ?>><?php echo esc_html( $settings['reopen_label'] ); ?></button>
		<?php
	}
}
