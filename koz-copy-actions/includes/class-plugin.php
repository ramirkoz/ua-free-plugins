<?php
namespace ramirkz\kozcopyactions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZCOAC_Plugin {
	public const OPTION = 'kozcoac_copy_settings';
	public const SCHEMA_VERSION = 4;
	public const HANDLE = 'kozcoac-copy';

	/**
	 * Selectors rendered by the shortcode and block. They are always active,
	 * independently of the selector settings, because the editor placed them explicitly.
	 */
	public const BUILTIN_SELECTORS = array( '.kozcoac-copy-button', '.kozcoac-copy-inline' );

	private const MAX_SELECTORS = 50;
	private const MAX_INJECT_SELECTORS = 20;
	private const MAX_PATHS = 100;
	private const MAX_POST_IDS = 200;
	private const INJECT_TAGS = array( 'pre', 'code', 'blockquote', 'kbd', 'samp' );

	public static function init(): void {
		// Settings migration only needs to run where settings are managed; the frontend reads
		// settings through wp_parse_args() and stays correct before the migration runs.
		if ( is_admin() || wp_doing_cron() ) {
			self::maybe_migrate();
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend' ), 30 );
		add_action( 'wp_footer', array( __CLASS__, 'localize_frontend' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy_content' ) );
	}

	public static function defaults(): array {
		return array(
			'schema_version'          => self::SCHEMA_VERSION,
			'enabled'                 => 0,
			'selectors'               => ".uafree-copy\n[data-uafree-copy]",
			'inject_selectors'        => '',
			'path_rules'              => '',
			'post_types'              => array(),
			'post_ids'                => '',
			'whitespace'              => 'collapse',
			'copy_format'             => 'text',
			'show_icon'               => 1,
			'show_toast'              => 1,
			'toast_duration'          => 1800,
			'toast_position'          => 'bottom-center',
			'prevent_link_navigation' => 0,
			'decorate_targets'        => 1,
		);
	}

	private static function legacy_option_name(): string {
		// Compatibility only. The legacy key is read once and never used as the current option.
		return implode( '_', array( 'uafree', 'copy', 'settings' ) );
	}

	public static function activate(): void {
		self::maybe_migrate();
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		}
	}

	public static function maybe_migrate(): void {
		$current = get_option( self::OPTION, false );

		if ( false === $current ) {
			$legacy = get_option( self::legacy_option_name(), false );
			if ( is_array( $legacy ) ) {
				$migrated = wp_parse_args( $legacy, self::defaults() );
				$migrated['schema_version'] = self::SCHEMA_VERSION;
				add_option( self::OPTION, self::sanitize_settings( $migrated ), '', false );
				$current = get_option( self::OPTION, false );
			}
		}

		if ( false === $current || ! is_array( $current ) ) {
			return;
		}

		$schema = isset( $current['schema_version'] ) ? (int) $current['schema_version'] : 0;
		if ( $schema >= self::SCHEMA_VERSION ) {
			return;
		}

		$migrated = wp_parse_args( $current, self::defaults() );
		$migrated['schema_version'] = self::SCHEMA_VERSION;
		update_option( self::OPTION, self::sanitize_settings( $migrated ), false );
	}

	public static function settings(): array {
		$current = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $current ) ? $current : array(), self::defaults() );
	}

	public static function sanitize_settings( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$defaults = self::defaults();

		return array(
			'schema_version'          => self::SCHEMA_VERSION,
			'enabled'                 => empty( $input['enabled'] ) ? 0 : 1,
			'selectors'               => self::sanitize_selectors( (string) ( $input['selectors'] ?? $defaults['selectors'] ) ),
			'inject_selectors'        => self::sanitize_inject_selectors( (string) ( $input['inject_selectors'] ?? '' ) ),
			'path_rules'              => self::sanitize_paths( (string) ( $input['path_rules'] ?? '' ) ),
			'post_types'              => self::sanitize_post_types( $input['post_types'] ?? array() ),
			'post_ids'                => self::sanitize_post_ids( (string) ( $input['post_ids'] ?? '' ) ),
			'whitespace'              => in_array( (string) ( $input['whitespace'] ?? '' ), array( 'collapse', 'preserve' ), true ) ? (string) $input['whitespace'] : 'collapse',
			'copy_format'             => in_array( (string) ( $input['copy_format'] ?? '' ), array( 'text', 'html' ), true ) ? (string) $input['copy_format'] : 'text',
			'show_icon'               => empty( $input['show_icon'] ) ? 0 : 1,
			'show_toast'              => empty( $input['show_toast'] ) ? 0 : 1,
			'toast_duration'          => max( 500, min( 5000, absint( $input['toast_duration'] ?? 1800 ) ) ),
			'toast_position'          => in_array( (string) ( $input['toast_position'] ?? '' ), array( 'bottom-center', 'bottom-left', 'bottom-right' ), true ) ? (string) $input['toast_position'] : 'bottom-center',
			'prevent_link_navigation' => empty( $input['prevent_link_navigation'] ) ? 0 : 1,
			'decorate_targets'        => empty( $input['decorate_targets'] ) ? 0 : 1,
		);
	}

	private static function sanitize_lines( string $raw, callable $is_valid, int $max ): string {
		$valid = array();
		$lines = preg_split( '/\R/u', $raw ) ?: array();

		foreach ( $lines as $line ) {
			$line = trim( wp_strip_all_tags( (string) $line ) );
			if ( '' === $line ) {
				continue;
			}
			if ( $is_valid( $line ) ) {
				$valid[ $line ] = true;
			}
			if ( count( $valid ) >= $max ) {
				break;
			}
		}

		return implode( "\n", array_keys( $valid ) );
	}

	private static function sanitize_selectors( string $raw ): string {
		return self::sanitize_lines( $raw, array( __CLASS__, 'is_allowed_selector' ), self::MAX_SELECTORS );
	}

	private static function is_allowed_selector( string $selector ): bool {
		if ( preg_match( '/^[.#][A-Za-z0-9_-]{1,100}$/', $selector ) ) {
			return true;
		}

		return 1 === preg_match(
			'/^\[(data-(?:uafree-copy|copy-value|copy-target|copy-key))(?:=(?:"[A-Za-z0-9_-]{1,80}"|\'[A-Za-z0-9_-]{1,80}\'))?\]$/',
			$selector
		);
	}

	private static function sanitize_inject_selectors( string $raw ): string {
		return self::sanitize_lines( $raw, array( __CLASS__, 'is_allowed_inject_selector' ), self::MAX_INJECT_SELECTORS );
	}

	/**
	 * Inject mode accepts one approved tag, a class or ID, or a tag combined with a class or ID.
	 */
	private static function is_allowed_inject_selector( string $selector ): bool {
		if ( ! preg_match( '/^([a-z]+)?([.#][A-Za-z0-9_-]{1,100})?$/', $selector, $matches ) || '' === $selector ) {
			return false;
		}

		$tag = $matches[1] ?? '';
		return '' === $tag || in_array( $tag, self::INJECT_TAGS, true );
	}

	private static function sanitize_paths( string $raw ): string {
		return self::sanitize_lines(
			$raw,
			static function ( string $line ): bool {
				if ( ! str_starts_with( $line, '/' ) || str_contains( $line, '?' ) || str_contains( $line, '#' ) ) {
					return false;
				}
				return 1 === preg_match( '#^/[A-Za-z0-9/_\-.~%]*\*?$#', $line );
			},
			self::MAX_PATHS
		);
	}

	private static function sanitize_post_types( $raw ): array {
		$valid = array();
		foreach ( (array) $raw as $post_type ) {
			$post_type = sanitize_key( (string) $post_type );
			if ( '' !== $post_type && post_type_exists( $post_type ) ) {
				$valid[ $post_type ] = true;
			}
		}
		return array_keys( $valid );
	}

	private static function sanitize_post_ids( string $raw ): string {
		return implode( ', ', self::parse_post_ids( $raw ) );
	}

	private static function parse_post_ids( string $raw ): array {
		$ids = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $raw ) ?: array() ) );
		return array_slice( array_values( array_unique( $ids ) ), 0, self::MAX_POST_IDS );
	}

	private static function lines( string $raw ): array {
		$lines = preg_split( '/\R/u', $raw ) ?: array();
		return array_values( array_filter( array_map( 'trim', $lines ) ) );
	}

	public static function selectors(): array {
		return self::lines( (string) self::settings()['selectors'] );
	}

	public static function inject_selectors(): array {
		return self::lines( (string) self::settings()['inject_selectors'] );
	}

	private static function current_path(): string {
		$request = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '/';
		$path = wp_parse_url( $request, PHP_URL_PATH );
		return is_string( $path ) && '' !== $path ? $path : '/';
	}

	private static function path_matches( string $path, array $rules ): bool {
		foreach ( $rules as $rule ) {
			if ( str_ends_with( $rule, '*' ) ) {
				if ( str_starts_with( $path, substr( $rule, 0, -1 ) ) ) {
					return true;
				}
			} elseif ( $path === $rule ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Display conditions: with no paths, post types or IDs configured every public page is allowed;
	 * otherwise the page is allowed when any configured condition matches.
	 */
	private static function conditions_allow( string $path, array $settings ): bool {
		$paths = self::lines( (string) $settings['path_rules'] );
		$post_types = array_values( array_filter( (array) $settings['post_types'], 'is_string' ) );
		$post_ids = self::parse_post_ids( (string) $settings['post_ids'] );

		if ( empty( $paths ) && empty( $post_types ) && empty( $post_ids ) ) {
			return true;
		}

		if ( ! empty( $paths ) && self::path_matches( $path, $paths ) ) {
			return true;
		}

		if ( ! empty( $post_types ) && is_singular( $post_types ) ) {
			return true;
		}

		return ! empty( $post_ids ) && is_singular() && in_array( (int) get_queried_object_id(), $post_ids, true );
	}

	public static function should_load(): bool {
		$settings = self::settings();
		$path = self::current_path();
		$forced = (bool) apply_filters( 'kozcoac_copy_force_load', false, $path, $settings );

		if ( ! $forced && ( empty( $settings['enabled'] ) || ( empty( self::selectors() ) && empty( self::inject_selectors() ) ) ) ) {
			return false;
		}

		$allowed = self::conditions_allow( $path, $settings );
		return $forced ? true : (bool) apply_filters( 'kozcoac_copy_should_load', $allowed, $path, $settings );
	}

	public static function register_assets(): void {
		if ( wp_script_is( self::HANDLE, 'registered' ) ) {
			return;
		}

		$base = plugin_dir_url( KOZCOAC_FILE );
		wp_register_style( self::HANDLE, $base . 'assets/frontend.css', array(), KOZCOAC_VERSION );
		// WordPress 6.3+ adds the defer attribute; WordPress 6.2 treats the array as in_footer = true.
		wp_register_script(
			self::HANDLE,
			$base . 'assets/frontend.js',
			array(),
			KOZCOAC_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	public static function enqueue_frontend(): void {
		if ( self::should_load() ) {
			self::ensure_assets();
		}
	}

	/**
	 * Enqueue the frontend assets. Also called by the shortcode and block while content renders.
	 */
	public static function ensure_assets(): void {
		self::register_assets();
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Configuration is attached late so it is correct whether the assets were enqueued by the
	 * settings or later by a shortcode or block rendered inside the content.
	 */
	public static function localize_frontend(): void {
		if ( ! wp_script_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}

		$settings = self::settings();
		$full = self::should_load();

		$selectors = $full ? (array) apply_filters( 'kozcoac_copy_selectors', self::selectors(), $settings ) : array();
		$selectors = array_values( array_unique( array_filter( array_map( 'strval', array_merge( $selectors, self::BUILTIN_SELECTORS ) ) ) ) );

		$config = array(
			'selectors'             => $selectors,
			'injectSelectors'       => $full ? self::inject_selectors() : array(),
			'collapseWhitespace'    => 'collapse' === $settings['whitespace'],
			'copyFormat'            => (string) $settings['copy_format'],
			'showIcon'              => ! empty( $settings['show_icon'] ),
			'showToast'             => ! empty( $settings['show_toast'] ),
			'toastDuration'         => (int) $settings['toast_duration'],
			'toastPosition'         => (string) $settings['toast_position'],
			'preventLinkNavigation' => ! empty( $settings['prevent_link_navigation'] ),
			'decorateTargets'       => ! empty( $settings['decorate_targets'] ),
			'messages'              => array(
				'copied' => __( 'Copied', 'koz-copy-actions' ),
				'error'  => __( 'Could not copy', 'koz-copy-actions' ),
				'hint'   => __( 'Copy to clipboard', 'koz-copy-actions' ),
				'copy'   => __( 'Copy', 'koz-copy-actions' ),
			),
		);

		$config = (array) apply_filters( 'kozcoac_copy_frontend_config', $config, $settings );
		wp_localize_script( self::HANDLE, 'KOZCOACConfig', $config );
	}

	public static function public_status(): array {
		$settings = self::settings();

		return array(
			'plugin'               => 'koz-copy-actions',
			'version'              => KOZCOAC_VERSION,
			'enabled'              => ! empty( $settings['enabled'] ),
			'selector_count'       => count( self::selectors() ),
			'inject_selector_count' => count( self::inject_selectors() ),
			'path_restricted'      => '' !== trim( (string) $settings['path_rules'] ) || ! empty( $settings['post_types'] ) || '' !== trim( (string) $settings['post_ids'] ),
			'custom_event'         => 'kozcoac:copy-success',
			'copied_values_stored' => false,
			'external_requests'    => false,
			'telemetry'            => false,
		);
	}

	public static function privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . esc_html__( 'KOZ Copy Actions performs clipboard actions in the visitor browser. It does not store copied values, IP addresses, cookies, identifiers or analytics data, and it makes no external requests.', 'koz-copy-actions' ) . '</p>';
		wp_add_privacy_policy_content( 'KOZ Copy Actions', wp_kses_post( wpautop( $content ) ) );
	}
}
