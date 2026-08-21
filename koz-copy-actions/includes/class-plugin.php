<?php
namespace ramirkz\kozcopyactions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZCOAC_Plugin {
	public const OPTION = 'kozcoac_copy_settings';
	public const SCHEMA_VERSION = 3;

	private const MAX_SELECTORS = 50;
	private const MAX_PATHS = 100;

	public static function init(): void {
		self::maybe_migrate();
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend' ), 30 );
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy_content' ) );
	}

	public static function defaults(): array {
		return array(
			'schema_version'          => self::SCHEMA_VERSION,
			'enabled'                 => 0,
			'selectors'               => ".uafree-copy\n[data-uafree-copy]",
			'path_rules'              => '',
			'whitespace'              => 'collapse',
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
			'path_rules'              => self::sanitize_paths( (string) ( $input['path_rules'] ?? '' ) ),
			'whitespace'              => in_array( (string) ( $input['whitespace'] ?? '' ), array( 'collapse', 'preserve' ), true ) ? (string) $input['whitespace'] : 'collapse',
			'show_icon'               => empty( $input['show_icon'] ) ? 0 : 1,
			'show_toast'              => empty( $input['show_toast'] ) ? 0 : 1,
			'toast_duration'          => max( 500, min( 5000, absint( $input['toast_duration'] ?? 1800 ) ) ),
			'toast_position'          => in_array( (string) ( $input['toast_position'] ?? '' ), array( 'bottom-center', 'bottom-left', 'bottom-right' ), true ) ? (string) $input['toast_position'] : 'bottom-center',
			'prevent_link_navigation' => empty( $input['prevent_link_navigation'] ) ? 0 : 1,
			'decorate_targets'        => empty( $input['decorate_targets'] ) ? 0 : 1,
		);
	}

	private static function sanitize_selectors( string $raw ): string {
		$valid = array();
		$lines = preg_split( '/\R/u', $raw ) ?: array();

		foreach ( $lines as $line ) {
			$line = trim( wp_strip_all_tags( (string) $line ) );
			if ( '' === $line ) {
				continue;
			}
			if ( self::is_allowed_selector( $line ) ) {
				$valid[ $line ] = true;
			}
			if ( count( $valid ) >= self::MAX_SELECTORS ) {
				break;
			}
		}

		return implode( "\n", array_keys( $valid ) );
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

	private static function sanitize_paths( string $raw ): string {
		$valid = array();
		$lines = preg_split( '/\R/u', $raw ) ?: array();

		foreach ( $lines as $line ) {
			$line = trim( wp_strip_all_tags( (string) $line ) );
			if ( '' === $line ) {
				continue;
			}
			if ( ! str_starts_with( $line, '/' ) || str_contains( $line, '?' ) || str_contains( $line, '#' ) ) {
				continue;
			}
			if ( preg_match( '#^/[A-Za-z0-9/_\-.~%]*\*?$#', $line ) ) {
				$valid[ $line ] = true;
			}
			if ( count( $valid ) >= self::MAX_PATHS ) {
				break;
			}
		}

		return implode( "\n", array_keys( $valid ) );
	}

	public static function selectors(): array {
		$lines = preg_split( '/\R/u', (string) self::settings()['selectors'] ) ?: array();
		return array_values( array_filter( array_map( 'trim', $lines ) ) );
	}

	private static function current_path(): string {
		$request = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '/';
		$path = wp_parse_url( $request, PHP_URL_PATH );
		return is_string( $path ) && '' !== $path ? $path : '/';
	}

	private static function path_allowed( string $path, string $rules ): bool {
		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', $rules ) ?: array() ) ) );
		if ( empty( $lines ) ) {
			return true;
		}

		foreach ( $lines as $rule ) {
			if ( str_ends_with( $rule, '*' ) ) {
				$prefix = substr( $rule, 0, -1 );
				if ( str_starts_with( $path, $prefix ) ) {
					return true;
				}
			} elseif ( $path === $rule ) {
				return true;
			}
		}

		return false;
	}

	public static function should_load(): bool {
		$settings = self::settings();
		$path = self::current_path();
		$forced = (bool) apply_filters( 'kozcoac_copy_force_load', false, $path, $settings );

		if ( ! $forced && ( empty( $settings['enabled'] ) || empty( self::selectors() ) ) ) {
			return false;
		}

		$allowed = self::path_allowed( $path, (string) $settings['path_rules'] );
		return $forced ? true : (bool) apply_filters( 'kozcoac_copy_should_load', $allowed, $path, $settings );
	}

	public static function enqueue_frontend(): void {
		if ( ! self::should_load() ) {
			return;
		}

		$settings = self::settings();
		$selectors = (array) apply_filters( 'kozcoac_copy_selectors', self::selectors(), $settings );
		$selectors = array_values( array_filter( array_map( 'strval', $selectors ) ) );
		if ( empty( $selectors ) ) {
			return;
		}

		$base = plugin_dir_url( KOZCOAC_FILE );
		wp_enqueue_style( 'kozcoac-copy', $base . 'assets/frontend.css', array(), KOZCOAC_VERSION );
		wp_enqueue_script( 'kozcoac-copy', $base . 'assets/frontend.js', array(), KOZCOAC_VERSION, true );

		$config = array(
			'selectors'             => $selectors,
			'collapseWhitespace'    => 'collapse' === $settings['whitespace'],
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
			),
		);

		$config = (array) apply_filters( 'kozcoac_copy_frontend_config', $config, $settings );
		wp_localize_script( 'kozcoac-copy', 'KOZCOACConfig', $config );
	}

	public static function public_status(): array {
		$settings = self::settings();

		return array(
			'plugin'               => 'koz-copy-actions',
			'version'              => KOZCOAC_VERSION,
			'enabled'              => ! empty( $settings['enabled'] ),
			'selector_count'       => count( self::selectors() ),
			'path_restricted'      => '' !== trim( (string) $settings['path_rules'] ),
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
