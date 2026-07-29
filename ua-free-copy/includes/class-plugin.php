<?php
namespace UAFree\CopyTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	public const OPTION         = 'uafree_copy_settings';
	public const SCHEMA_VERSION = 2;
	private const MAX_SELECTORS = 50;
	private const MAX_PATHS     = 100;

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

	public static function activate(): void {
		$current = get_option( self::OPTION, false );
		if ( false === $current ) {
			add_option( self::OPTION, self::defaults(), '', false );
			return;
		}
		self::maybe_migrate();
	}

	public static function maybe_migrate(): void {
		$current = get_option( self::OPTION, false );
		if ( false === $current || ! is_array( $current ) ) {
			return;
		}

		$schema = isset( $current['schema_version'] ) ? (int) $current['schema_version'] : 0;
		if ( $schema >= self::SCHEMA_VERSION ) {
			return;
		}

		$migrated = wp_parse_args( $current, self::defaults() );
		$migrated['schema_version'] = self::SCHEMA_VERSION;

		// Version 0.1 always prevented navigation on matching links. Preserve that
		// behavior only for existing installations; new installations default to off.
		if ( 0 === $schema && ! array_key_exists( 'prevent_link_navigation', $current ) ) {
			$migrated['prevent_link_navigation'] = 1;
		}

		update_option( self::OPTION, self::sanitize_settings( $migrated ), false );
	}

	public static function settings(): array {
		$current = get_option( self::OPTION, array() );
		$current = is_array( $current ) ? $current : array();
		return wp_parse_args( $current, self::defaults() );
	}

	public static function sanitize_settings( $input ): array {
		$input    = is_array( $input ) ? $input : array();
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
		$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/';
		$path    = wp_parse_url( $request, PHP_URL_PATH );
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
		$forced = (bool) apply_filters( 'uafree_copy_force_load', false, $path, $settings );

		if ( ! $forced && ( empty( $settings['enabled'] ) || empty( self::selectors() ) ) ) {
			return false;
		}

		$allowed = self::path_allowed( $path, (string) $settings['path_rules'] );
		if ( $forced ) {
			return true;
		}

		return (bool) apply_filters( 'uafree_copy_should_load', $allowed, $path, $settings );
	}

	public static function enqueue_frontend(): void {
		if ( ! self::should_load() ) {
			return;
		}

		$settings  = self::settings();
		$selectors = (array) apply_filters( 'uafree_copy_selectors', self::selectors(), $settings );
		$selectors = array_values( array_filter( array_map( 'strval', $selectors ) ) );
		if ( empty( $selectors ) ) {
			return;
		}

		$base = plugin_dir_url( UAFREE_COPY_FILE );
		wp_enqueue_style( 'uafree-copy', $base . 'assets/frontend.css', array(), UAFREE_COPY_VERSION );
		wp_enqueue_script( 'uafree-copy', $base . 'assets/frontend.js', array(), UAFREE_COPY_VERSION, true );

		$config = array(
			'selectors'              => $selectors,
			'collapseWhitespace'      => 'collapse' === $settings['whitespace'],
			'showIcon'                => ! empty( $settings['show_icon'] ),
			'showToast'               => ! empty( $settings['show_toast'] ),
			'toastDuration'           => (int) $settings['toast_duration'],
			'toastPosition'           => (string) $settings['toast_position'],
			'preventLinkNavigation'   => ! empty( $settings['prevent_link_navigation'] ),
			'decorateTargets'         => ! empty( $settings['decorate_targets'] ),
			'messages'                => array(
				'uk' => array(
					'copied' => __( 'Copied', 'ua-free-copy' ),
					'error'  => __( 'Could not copy', 'ua-free-copy' ),
					'hint'   => __( 'Copy to clipboard', 'ua-free-copy' ),
				),
				'en' => array(
					'copied' => 'Copied',
					'error'  => 'Could not copy',
					'hint'   => 'Copy to clipboard',
				),
			),
		);
		$config = (array) apply_filters( 'uafree_copy_frontend_config', $config, $settings );
		wp_localize_script( 'uafree-copy', 'UAFreeCopyConfig', $config );
	}

	public static function public_status(): array {
		$settings = self::settings();
		return array(
			'plugin'                 => 'ua-free-copy',
			'version'                => UAFREE_COPY_VERSION,
			'enabled'                => ! empty( $settings['enabled'] ),
			'selector_count'         => count( self::selectors() ),
			'path_restricted'        => '' !== trim( (string) $settings['path_rules'] ),
			'custom_event'           => 'uafree:copy-success',
			'copied_values_stored'   => false,
			'external_requests'      => false,
			'telemetry'              => false,
		);
	}

	public static function privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . esc_html__( 'UA FREE Copy performs clipboard actions in the visitor browser. It does not store copied values, IP addresses, cookies, identifiers or analytics data, and it makes no external requests.', 'ua-free-copy' ) . '</p>';
		wp_add_privacy_policy_content( 'UA FREE Copy', wp_kses_post( wpautop( $content ) ) );
	}
}
