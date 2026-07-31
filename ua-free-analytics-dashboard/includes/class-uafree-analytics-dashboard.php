<?php

declare(strict_types=1);

namespace UAFree\AnalyticsDashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dashboard {
	private const PAGE = 'ua-free-analytics-dashboard';
	private const EXPORT_ACTION = 'uafree_analytics_dashboard_export';
	private const MIGRATION_OPTION = 'uafree_suite_control_center_migration_version';
	private const LEGACY_HEARTBEAT_HOOK = 'uafree_suite_daily_heartbeat';
	private const PERIODS = array( 7, 30, 90 );

	/** @var array<int,array<string,mixed>> */
	private static array $cache = array();

	/** @var array<string,array{label:string,unit:string,source:string,type:string}> */
	private const METRIC_DEFINITIONS = array(
		'suite.providers_available' => array(
			'label' => 'Providers available',
			'unit' => 'count',
			'source' => 'suite',
			'type' => 'integer',
		),
		'suite.providers_total' => array(
			'label' => 'Providers total',
			'unit' => 'count',
			'source' => 'suite',
			'type' => 'integer',
		),
		'suite.findings_total' => array(
			'label' => 'Findings total',
			'unit' => 'count',
			'source' => 'suite',
			'type' => 'integer',
		),
		'consent.analytics_enabled' => array(
			'label' => 'Analytics consent enabled',
			'unit' => 'boolean',
			'source' => 'consent',
			'type' => 'boolean',
		),
		'consent.advertising_enabled' => array(
			'label' => 'Advertising consent enabled',
			'unit' => 'boolean',
			'source' => 'consent',
			'type' => 'boolean',
		),
		'consent.integrations_count' => array(
			'label' => 'Consent integrations',
			'unit' => 'count',
			'source' => 'consent',
			'type' => 'integer',
		),
		'guard.log_entries' => array(
			'label' => '404 journal entries',
			'unit' => 'count',
			'source' => 'guard',
			'type' => 'integer',
		),
		'guard.redirect_rules' => array(
			'label' => 'Redirect rules',
			'unit' => 'count',
			'source' => 'guard',
			'type' => 'integer',
		),
		'guard.gone_rules' => array(
			'label' => 'Gone rules',
			'unit' => 'count',
			'source' => 'guard',
			'type' => 'integer',
		),
		'seo.organization_configured' => array(
			'label' => 'Organization configured',
			'unit' => 'boolean',
			'source' => 'seo',
			'type' => 'boolean',
		),
		'seo.output_conflict' => array(
			'label' => 'SEO output conflict',
			'unit' => 'boolean',
			'source' => 'seo',
			'type' => 'boolean',
		),
		'translate.source_count' => array(
			'label' => 'Translation sources',
			'unit' => 'count',
			'source' => 'translate',
			'type' => 'integer',
		),
		'translate.hint_count' => array(
			'label' => 'Translation hints',
			'unit' => 'count',
			'source' => 'translate',
			'type' => 'integer',
		),
		'translate.language_count' => array(
			'label' => 'Translation languages',
			'unit' => 'count',
			'source' => 'translate',
			'type' => 'integer',
		),
		'static_translate.target_language_count' => array(
			'label' => 'Target languages',
			'unit' => 'count',
			'source' => 'static_translate',
			'type' => 'integer',
		),
		'static_translate.auto_enabled' => array(
			'label' => 'Automatic translation enabled',
			'unit' => 'boolean',
			'source' => 'static_translate',
			'type' => 'boolean',
		),
		'donate.donations_total' => array(
			'label' => 'Donations total',
			'unit' => 'count',
			'source' => 'donate',
			'type' => 'integer',
		),
		'donate.conversions_total' => array(
			'label' => 'Conversions total',
			'unit' => 'count',
			'source' => 'donate',
			'type' => 'integer',
		),
		'donate.conversion_rate' => array(
			'label' => 'Conversion rate',
			'unit' => 'percent',
			'source' => 'donate',
			'type' => 'percent',
		),
	);

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 35 );
		add_action( 'admin_menu', array( __CLASS__, 'replace_suite_root_menu' ), 10000 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'remove_legacy_telemetry' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'export_json' ) );
		add_filter( 'all_plugins', array( __CLASS__, 'normalize_plugin_headers' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( UAFREE_ANALYTICS_DASHBOARD_FILE ),
			array( __CLASS__, 'action_links' )
		);
	}

	public static function activate(): void {
		self::remove_legacy_telemetry();
	}

	public static function deactivate(): void {
		self::unschedule_legacy_heartbeat();
	}

	public static function remove_legacy_telemetry(): void {
		if (
			UAFREE_ANALYTICS_DASHBOARD_VERSION
			=== (string) get_option(
				self::MIGRATION_OPTION,
				''
			)
		) {
			return;
		}

		self::unschedule_legacy_heartbeat();

		foreach (
			array(
				'uafree_suite_support_settings',
				'uafree_suite_install_id',
				'uafree_suite_heartbeat_token',
				'uafree_suite_heartbeat_token_expiry',
			)
			as $option
		) {
			delete_option( $option );
		}

		update_option(
			self::MIGRATION_OPTION,
			UAFREE_ANALYTICS_DASHBOARD_VERSION,
			false
		);
	}

	private static function unschedule_legacy_heartbeat(): void {
		while (
			false !== (
				$timestamp = wp_next_scheduled(
					self::LEGACY_HEARTBEAT_HOOK
				)
			)
		) {
			if (
				false === wp_unschedule_event(
					$timestamp,
					self::LEGACY_HEARTBEAT_HOOK
				)
			) {
				break;
			}
		}
	}


	public static function register_menu(): void {
		add_submenu_page(
			'uafree-suite',
			__( 'Центр керування', 'ua-free-analytics-dashboard' ),
			__( 'Центр керування', 'ua-free-analytics-dashboard' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function enqueue_assets( string $hook ): void {
		wp_enqueue_style(
			'uafree-analytics-dashboard',
			UAFREE_ANALYTICS_DASHBOARD_URL . 'assets/admin.css',
			array(),
			UAFREE_ANALYTICS_DASHBOARD_VERSION
		);
	}

	public static function replace_suite_root_menu(): void {
		/*
		 * Several older Suite plugins register their own callback for the same
		 * top-level slug. Removing only the menu item leaves those callbacks
		 * attached, so WordPress prints multiple plugin catalogs on one page.
		 */
		remove_menu_page( 'uafree-suite' );
		remove_all_actions( 'toplevel_page_uafree-suite' );
		add_menu_page(
			'UA FREE Plugin Suite',
			'UA FREE',
			'manage_options',
			'uafree-suite',
			array( __CLASS__, 'render_suite_page' ),
			'none',
			58
		);
	}

	/**
	 * Correct stale public plugin headers without replacing a working legacy
	 * component whose exact source artifact is not part of this release.
	 *
	 * @param array<string,array<string,mixed>> $plugins
	 * @return array<string,array<string,mixed>>
	 */
	public static function normalize_plugin_headers( array $plugins ): array {
		foreach ( $plugins as $file => &$data ) {
			if ( str_starts_with( (string) $file, 'ua-free-migration-cleanup/' ) ) {
				$data['Description'] = 'Контрольовані snapshots, міграція та очищення залишків плагінів.';
			}
			if ( plugin_basename( UAFREE_ANALYTICS_DASHBOARD_FILE ) === (string) $file ) {
				$data['Name'] = 'UA FREE Suite Control Center';
				$data['Description'] = 'Зрозумілий центр стану, навігації та підтримки UA FREE Plugin Suite.';
			}
		}
		unset( $data );
		return $plugins;
	}

	/** @return array<string,array<string,string>> */
	private static function suite_catalog_full(): array {
		return array(
			'ua-free-migration-cleanup' => array( 'name' => 'UA FREE Migration & Cleanup', 'description' => 'Сканування, snapshots, міграція та контрольоване очищення.', 'page' => '', 'mode' => 'background' ),
			'ua-free-static-translate' => array( 'name' => 'UA FREE Static Translate', 'description' => 'Автоматичний статичний переклад WordPress.', 'page' => 'uafree-static-translate-auto', 'mode' => 'page' ),
			'ua-free-translate-diagnostics' => array( 'name' => 'UA FREE Translate Diagnostics', 'description' => 'Діагностика перекладача зрозумілими статусами.', 'page' => 'ua-free-translate-diagnostics', 'mode' => 'page' ),
			'ua-free-seo-core' => array( 'name' => 'UA FREE SEO Core', 'description' => 'SEO, sitemap, schema, AI discovery та accessibility.', 'page' => 'uafree-seo-core', 'mode' => 'page' ),
			'ua-free-404-guard' => array( 'name' => 'UA FREE 404 Guard & URL Intelligence', 'description' => '404, 410, redirects та аналіз URL.', 'page' => 'uafree-404-guard', 'mode' => 'page' ),
			'ua-free-site-bridge' => array( 'name' => 'UA FREE Site Bridge', 'description' => 'Захищена read-only діагностика сайту.', 'page' => 'uafree-site-bridge', 'mode' => 'page' ),
			'ua-free-google-ads-campaign-builder' => array( 'name' => 'UA FREE Google Ads Campaign Builder', 'description' => 'Автоматичні пакети Google Ad Grants і Google Ads.', 'page' => 'ua-free-google-ads-campaign-builder', 'mode' => 'page' ),
			'ua-free-donate-stats' => array( 'name' => 'UA FREE Donate Stats & Conversions', 'description' => 'Воронка донатів, підтвердження та конверсія.', 'page' => 'uafree-donate-stats', 'mode' => 'page' ),
			'ua-free-copy' => array( 'name' => 'UA FREE Copy', 'description' => 'Копіювання реквізитів та інших дозволених значень.', 'page' => 'uafree-copy', 'mode' => 'page' ),
			'ua-free-url-only-comment-spam' => array( 'name' => 'UA FREE URL-Only Comment Spam', 'description' => 'Автоматичний захист від коментарів, що складаються лише з URL.', 'page' => 'uafree-url-only-comment-spam', 'mode' => 'page' ),
			'ua-free-analytics-dashboard' => array( 'name' => 'UA FREE Suite Control Center', 'description' => 'Стан усієї збірки та наступні дії.', 'page' => self::PAGE, 'mode' => 'page' ),
			'ua-free-consent-manager' => array( 'name' => 'UA FREE Consent Manager', 'description' => 'Керування згодою та зовнішніми скриптами.', 'page' => 'ua-free-consent-manager', 'mode' => 'page' ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	private static function suite_status(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed = get_plugins();
		$active = (array) get_option( 'active_plugins', array() );
		$network = is_multisite()
			? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) )
			: array();
		$result = array();

		foreach ( self::suite_catalog_full() as $slug => $item ) {
			$matches = array();
			$active_matches = array();
			foreach ( $installed as $file => $data ) {
				$match = str_starts_with( (string) $file, $slug . '/' );
				if ( 'ua-free-site-bridge' === $slug && str_contains( (string) $file, 'site-bridge' ) ) {
					$match = true;
				}
				if ( 'ua-free-google-ads-campaign-builder' === $slug && str_contains( (string) $file, 'google-ads-campaign-builder' ) ) {
					$match = true;
				}
				if ( ! $match ) {
					continue;
				}
				$matches[] = array(
					'file' => (string) $file,
					'version' => sanitize_text_field( (string) ( $data['Version'] ?? '' ) ),
				);
				if ( in_array( $file, $active, true ) || in_array( $file, $network, true ) ) {
					$active_matches[] = (string) $file;
				}
			}

			$version = '';
			if ( ! empty( $active_matches ) ) {
				foreach ( $matches as $match ) {
					if ( in_array( $match['file'], $active_matches, true ) ) {
						$version = (string) $match['version'];
						break;
					}
				}
			} elseif ( ! empty( $matches ) ) {
				$version = (string) $matches[0]['version'];
			}

			$result[] = array(
				'slug' => $slug,
				'name' => (string) $item['name'],
				'description' => (string) $item['description'],
				'page' => (string) $item['page'],
				'mode' => (string) $item['mode'],
				'installed' => ! empty( $matches ),
				'active' => ! empty( $active_matches ),
				'version' => $version,
				'instances' => count( $matches ),
				'active_instances' => count( $active_matches ),
			);
		}
		return $result;
	}

	public static function render_suite_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap uafree-suite-shell"><h1>UA FREE Plugin Suite</h1>';
		echo '<p>Усі інструменти збірки в одному місці. Кнопка відсутня лише у службових компонентів, які працюють у фоні.</p>';
		echo '<div class="uafree-suite-grid">';
		foreach ( self::suite_status() as $component ) {
			$state = $component['active'] ? 'active' : ( $component['installed'] ? 'installed' : 'missing' );
			echo '<article class="uafree-suite-card is-' . esc_attr( $state ) . '">';
			echo '<h2>' . esc_html( (string) $component['name'] ) . '</h2>';
			echo '<p>' . esc_html( (string) $component['description'] ) . '</p>';
			echo '<p class="uafree-suite-state"><strong>';
			if ( $component['active'] ) {
				echo 'Активний';
			} elseif ( $component['installed'] ) {
				echo 'Встановлений, але вимкнений';
			} else {
				echo 'Не встановлений';
			}
			echo '</strong>';
			if ( '' !== (string) $component['version'] ) {
				echo ' <code>' . esc_html( (string) $component['version'] ) . '</code>';
			}
			echo '</p>';

			if ( (int) $component['instances'] > 1 ) {
				echo '<p class="uafree-suite-warning">Знайдено версій: ' . esc_html( (string) $component['instances'] ) . '. Активних: ' . esc_html( (string) $component['active_instances'] ) . '.</p>';
			}

			if ( $component['active'] && '' !== (string) $component['page'] ) {
				echo '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=' . $component['page'] ) ) . '">Відкрити</a>';
			} elseif ( $component['active'] ) {
				echo '<span class="uafree-background-badge">Працює у фоні</span>';
			}
			echo '</article>';
		}
		echo '</div>';
		echo '</div>';
	}

	private static function current_is_uafree_page(): bool {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		$screen_id = sanitize_key( (string) $screen->id );
		return str_contains( $screen_id, 'uafree' )
			|| str_contains( $screen_id, 'ua-free' );
	}

	public static function render_global_support(): void {
		// Support footer is embedded in every current UA FREE plugin.
	}

	private static function render_support_block( bool $inside_wrap ): void {
		unset( $inside_wrap );
	}

	/** @param array<int,string> $links @return array<int,string> */
	public static function action_links( array $links ): array {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' .
			esc_html__( 'Open dashboard', 'ua-free-analytics-dashboard' ) .
			'</a>'
		);
		return $links;
	}

	/** @return array<string,mixed> */
	public static function metric_schema(): array {
		$metrics = array();
		foreach ( self::METRIC_DEFINITIONS as $id => $definition ) {
			$metrics[ $id ] = array(
				'label' => $definition['label'],
				'unit' => $definition['unit'],
				'source' => $definition['source'],
				'type' => $definition['type'],
			);
		}

		return array(
			'contract_version' => 2.2,
			'extension_mode' => 'disabled',
			'period_days_allowed' => self::PERIODS,
			'metrics' => $metrics,
			'storage' => 'none',
			'personal_data' => false,
		);
	}

	/** @return array<string,mixed> */
	public static function report( int $days = 30 ): array {
		$days = self::normalize_days( $days );
		if ( isset( self::$cache[ $days ] ) ) {
			return self::$cache[ $days ];
		}

		$providers = array(
			'consent' => self::consent_provider(),
			'url_guard' => self::guard_provider(),
			'seo' => self::seo_provider(),
			'translate_diagnostics' => self::translate_diagnostics_provider(),
			'static_translate' => self::static_translate_provider(),
			'site_bridge' => self::version_provider( array( 'UAFREE_SITE_BRIDGE_VERSION' ) ),
			'donate_stats' => self::donate_provider( $days ),
			'google_ads' => self::version_provider( array( 'UAFREE_GOOGLE_ADS_CAMPAIGN_BUILDER_VERSION', 'UAFREE_AGB_VERSION' ) ),
		);

		$findings = self::findings( $providers );
		$metrics = self::metrics( $providers, $findings, $days );
		$suite = self::suite_summary();

		$report = array(
			'plugin' => array(
				'name' => 'UA FREE Suite Control Center',
				'version' => UAFREE_ANALYTICS_DASHBOARD_VERSION,
				'read_only' => true,
				'contract_version' => 2.2,
				'extension_mode' => 'disabled',
			),
			'generated_at' => current_time( 'c' ),
			'period_days' => $days,
			'locale' => self::locale(),
			'overview' => array(
				'providers_available' => self::available_count( $providers ),
				'providers_total' => count( $providers ),
				'metrics_total' => count( $metrics ),
				'findings_total' => count( $findings ),
			),
			'findings' => $findings,
			'metrics' => $metrics,
			'providers' => $providers,
			'suite' => $suite,
			'privacy' => array(
				'external_requests' => false,
				'tracking_cookies' => false,
				'frontend_hooks' => false,
				'new_tables' => false,
				'event_storage' => false,
				'arbitrary_provider_payloads' => false,
				'metric_extension_filter' => false,
				'raw_urls' => false,
				'query_values' => false,
				'referrers' => false,
				'ip_addresses' => false,
				'user_agents' => false,
				'user_identifiers' => false,
				'secrets' => false,
			),
		);

		self::$cache[ $days ] = $report;
		return $report;
	}

	private static function normalize_days( int $days ): int {
		return in_array( $days, self::PERIODS, true ) ? $days : 30;
	}

	private static function locale(): string {
		$locale = strtolower( (string) determine_locale() );
		$locale = (string) preg_replace( '/[^a-z0-9_-]+/', '', $locale );
		return substr( $locale, 0, 16 );
	}

	/** @return array<string,mixed> */
	private static function base_provider( bool $available, string $state, string $version = '' ): array {
		return array(
			'available' => $available,
			'state' => self::provider_state( $state ),
			'version' => self::version_value( $version ),
		);
	}
	private static function provider_state( string $state ): string {
		return in_array( $state, array( 'ok', 'not_detected', 'provider_error' ), true )
			? $state
			: 'provider_error';
	}

	private static function version_value( string $value ): string {
		$value = trim( $value );
		if ( ! preg_match( '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-([0-9A-Za-z.-]+))?$/', $value, $matches ) ) {
			return '';
		}

		$prerelease = (string) ( $matches[4] ?? '' );
		if ( '' === $prerelease ) {
			return $value;
		}

		$allowed = array(
			'dev',
			'foundation-safe-dev',
		);
		if ( in_array( $prerelease, $allowed, true ) ) {
			return $value;
		}

		if ( preg_match( '/^(?:alpha|beta|rc)(?:\.[1-9]\d*)?$/', $prerelease ) ) {
			return $value;
		}

		return '';
	}
	private static function bool_value( $value ): ?bool {
		if ( is_bool( $value ) ) return $value;
		if ( is_int( $value ) ) { if ( 0 === $value ) return false; if ( 1 === $value ) return true; return null; }
		if ( is_string( $value ) ) { $v = strtolower( trim( $value ) ); if ( in_array( $v, array( '0', 'false' ), true ) ) return false; if ( in_array( $v, array( '1', 'true' ), true ) ) return true; }
		return null;
	}
	private static function int_value( $value ): ?int {
		if ( is_int( $value ) ) {
			return $value >= 0 ? $value : null;
		}
		if ( is_string( $value ) && preg_match( '/^\\d{1,18}$/', $value ) ) {
			$number = (int) $value;
			return $number >= 0 ? $number : null;
		}
		return null;
	}

	private static function percent_value( $value ): ?float {
		if ( ! is_int( $value ) && ! is_float( $value ) && ! is_string( $value ) ) {
			return null;
		}
		if ( is_string( $value ) && ! preg_match( '/^\\d+(?:\\.\\d+)?$/', $value ) ) {
			return null;
		}
		$number = (float) $value;
		if ( ! is_finite( $number ) || $number < 0 || $number > 100 ) {
			return null;
		}
		return $number;
	}

	private static function path_available( $value ): ?bool {
		if ( ! is_string( $value ) ) {
			return null;
		}
		return '' !== trim( $value );
	}

	/** @return array{ok:bool,value:mixed} */
	private static function safe_call( $callback, array $args ): array {
		if ( ! is_callable( $callback ) ) {
			return array( 'ok' => false, 'value' => null );
		}
		try {
			return array(
				'ok' => true,
				'value' => call_user_func_array( $callback, $args ),
			);
		} catch ( Throwable $error ) {
			return array( 'ok' => false, 'value' => null );
		}
	}

	/** @param array<string,mixed> $target */
	private static function set_bool( array &$target, string $key, $value ): void {
		$normalized = self::bool_value( $value );
		if ( null !== $normalized ) {
			$target[ $key ] = $normalized;
		}
	}

	/** @param array<string,mixed> $target */
	private static function set_int( array &$target, string $key, $value ): void {
		$normalized = self::int_value( $value );
		if ( null !== $normalized ) {
			$target[ $key ] = $normalized;
		}
	}

	/** @param array<string,mixed> $target */
	private static function set_percent( array &$target, string $key, $value ): void {
		$normalized = self::percent_value( $value );
		if ( null !== $normalized ) {
			$target[ $key ] = $normalized;
		}
	}

	/** @return array<string,mixed> */
	private static function consent_provider(): array {
		if ( ! function_exists( 'uafree_consent_manager_get_status' ) ) {
			return self::base_provider( false, 'not_detected' );
		}

		$call = self::safe_call( 'uafree_consent_manager_get_status', array() );
		if ( ! $call['ok'] || ! is_array( $call['value'] ) ) {
			return self::base_provider( false, 'provider_error' );
		}

		$raw = $call['value'];
		$result = self::base_provider(
			true,
			'ok',
			defined( 'UAFREE_CONSENT_MANAGER_VERSION' )
				? (string) constant( 'UAFREE_CONSENT_MANAGER_VERSION' )
				: ''
		);

		foreach ( array( 'necessary', 'analytics', 'advertising', 'external_media' ) as $key ) {
			self::set_bool( $result, $key, $raw[ $key ] ?? null );
		}
		self::set_int( $result, 'policy_version', $raw['policy_version'] ?? null );

		$integration_count = 0;
		if ( function_exists( 'uafree_consent_manager_get_integrations' ) ) {
			$integration_call = self::safe_call(
				'uafree_consent_manager_get_integrations',
				array()
			);
			if ( ! $integration_call['ok'] || ! is_array( $integration_call['value'] ) ) {
				return self::base_provider( false, 'provider_error' );
			}
			$integration_count = count( array_slice( $integration_call['value'], 0, 100 ) );
		}
		$result['integration_count'] = $integration_count;

		return $result;
	}

	/** @return array<string,mixed> */
	private static function guard_provider(): array {
		$class = '\\UAFree\\Guard404\\Guard';
		if ( ! class_exists( $class ) || ! is_callable( array( $class, 'public_status' ) ) ) {
			return self::base_provider( false, 'not_detected' );
		}

		$call = self::safe_call( array( $class, 'public_status' ), array() );
		if ( ! $call['ok'] || ! is_array( $call['value'] ) ) {
			return self::base_provider( false, 'provider_error' );
		}

		$raw = $call['value'];
		$result = self::base_provider(
			true,
			'ok',
			(string) ( $raw['version'] ?? '' )
		);
		foreach ( array( 'enabled', 'capture_active' ) as $key ) {
			self::set_bool( $result, $key, $raw[ $key ] ?? null );
		}
		foreach ( array( 'log_entries', 'redirect_rules', 'gone_rules' ) as $key ) {
			self::set_int( $result, $key, $raw[ $key ] ?? null );
		}
		return $result;
	}

	/** @return array<string,mixed> */
	private static function seo_provider(): array {
		$class = '\\UAFree_SEO_Core';
		if ( ! class_exists( $class ) || ! is_callable( array( $class, 'public_status' ) ) ) {
			return self::base_provider( false, 'not_detected' );
		}

		$call = self::safe_call( array( $class, 'public_status' ), array() );
		if ( ! $call['ok'] || ! is_array( $call['value'] ) ) {
			return self::base_provider( false, 'provider_error' );
		}

		$raw = $call['value'];
		$result = self::base_provider(
			true,
			'ok',
			(string) ( $raw['version'] ?? '' )
		);
		foreach ( array( 'enabled', 'organization_configured', 'output_conflict' ) as $key ) {
			self::set_bool( $result, $key, $raw[ $key ] ?? null );
		}
		foreach (
			array(
				'sitemap_available' => 'sitemap_path',
				'llms_txt_available' => 'llms_txt_path',
				'ai_manifest_available' => 'ai_manifest_path',
			)
			as $target => $source
		) {
			$available = self::path_available( $raw[ $source ] ?? null );
			if ( null !== $available ) {
				$result[ $target ] = $available;
			}
		}
		return $result;
	}

	/** @return array<string,mixed> */
	private static function translate_diagnostics_provider(): array {
		$class = '\\UAFree_Translate_Diagnostics';
		if ( ! class_exists( $class ) || ! is_callable( array( $class, 'public_status' ) ) ) {
			return self::base_provider( false, 'not_detected' );
		}

		$call = self::safe_call( array( $class, 'public_status' ), array() );
		if ( ! $call['ok'] || ! is_array( $call['value'] ) ) {
			return self::base_provider( false, 'provider_error' );
		}

		$raw = $call['value'];
		$result = self::base_provider(
			true,
			'ok',
			(string) ( $raw['version'] ?? '' )
		);
		foreach ( array( 'translator_detected', 'translator_active', 'migration_frozen' ) as $key ) {
			self::set_bool( $result, $key, $raw[ $key ] ?? null );
		}
		self::set_int( $result, 'source_count', $raw['source_count'] ?? null );
		self::set_int( $result, 'hint_count', $raw['basic_hint_count'] ?? null );

		if ( isset( $raw['languages'] ) && is_array( $raw['languages'] ) ) {
			$result['language_count'] = count( array_slice( $raw['languages'], 0, 100 ) );
		} elseif ( isset( $raw['language_count'] ) ) {
			self::set_int( $result, 'language_count', $raw['language_count'] );
		}
		return $result;
	}

	/** @return array<string,mixed> */
	private static function static_translate_provider(): array {
		if ( function_exists( 'uafree_static_translate_get_status' ) ) {
			$call = self::safe_call( 'uafree_static_translate_get_status', array() );
		} elseif (
			class_exists( '\\UAFree_Static_Translate_Autonomous' )
			&& is_callable( array( '\\UAFree_Static_Translate_Autonomous', 'public_status' ) )
		) {
			$call = self::safe_call(
				array( '\\UAFree_Static_Translate_Autonomous', 'public_status' ),
				array()
			);
		} else {
			return self::base_provider( false, 'not_detected' );
		}

		if ( ! $call['ok'] || ! is_array( $call['value'] ) ) {
			return self::base_provider( false, 'provider_error' );
		}

		$raw = $call['value'];
		$result = self::base_provider(
			true,
			'ok',
			(string) ( $raw['version'] ?? '' )
		);
		foreach ( array( 'auto_enabled', 'routes_enabled', 'switcher_enabled' ) as $key ) {
			self::set_bool( $result, $key, $raw[ $key ] ?? null );
		}
		if ( isset( $raw['target_languages'] ) && is_array( $raw['target_languages'] ) ) {
			$result['target_language_count'] = count(
				array_slice( $raw['target_languages'], 0, 100 )
			);
		} elseif ( isset( $raw['target_language_count'] ) ) {
			self::set_int(
				$result,
				'target_language_count',
				$raw['target_language_count']
			);
		}
		return $result;
	}

	/** @return array<string,mixed> */
	private static function donate_provider( int $days ): array {
		if ( ! function_exists( 'uafree_donate_stats_get_summary' ) ) {
			return self::base_provider( false, 'not_detected' );
		}

		$call = self::safe_call(
			'uafree_donate_stats_get_summary',
			array( $days )
		);
		if ( ! $call['ok'] || ! is_array( $call['value'] ) ) {
			return self::base_provider( false, 'provider_error' );
		}

		$raw = $call['value'];
		$result = self::base_provider(
			true,
			'ok',
			defined( 'UAFREE_DONATE_STATS_VERSION' )
				? (string) constant( 'UAFREE_DONATE_STATS_VERSION' )
				: ''
		);
		self::set_int(
			$result,
			'donations_total',
			$raw['donations_total'] ?? ( $raw['donations'] ?? null )
		);
		self::set_int(
			$result,
			'conversions_total',
			$raw['conversions_total'] ?? ( $raw['conversions'] ?? null )
		);
		self::set_percent(
			$result,
			'conversion_rate',
			$raw['conversion_rate'] ?? null
		);
		return $result;
	}

	/** @return array<string,mixed> */
	private static function version_provider( array $constant_names ): array {
		$defined = false;
		foreach ( $constant_names as $constant_name ) {
			if ( ! defined( $constant_name ) ) {
				continue;
			}
			$defined = true;
			$version = self::version_value( (string) constant( $constant_name ) );
			if ( '' !== $version ) {
				return self::base_provider( true, 'ok', $version );
			}
		}
		return $defined
			? self::base_provider( false, 'provider_error' )
			: self::base_provider( false, 'not_detected' );
	}

	/**
	 * @param array<string,array<string,mixed>> $providers
	 * @return array<int,array{code:string,level:string,message:string}>
	 */
	private static function findings( array $providers ): array {
		$findings = array();

		if ( empty( $providers['consent']['available'] ) ) {
			$findings[] = array(
				'code' => 'consent_unavailable',
				'level' => 'warning',
				'message' => __( 'Consent Manager is unavailable. Optional analytics must remain disabled.', 'ua-free-analytics-dashboard' ),
			);
		}
		if ( ! empty( $providers['seo']['output_conflict'] ) ) {
			$findings[] = array(
				'code' => 'seo_output_conflict',
				'level' => 'warning',
				'message' => __( 'SEO Core reports another active output provider.', 'ua-free-analytics-dashboard' ),
			);
		}
		if ( ! empty( $providers['translate_diagnostics']['hint_count'] ) ) {
			$findings[] = array(
				'code' => 'translation_hints',
				'level' => 'warning',
				'message' => __( 'Translate Diagnostics reports unresolved hints.', 'ua-free-analytics-dashboard' ),
			);
		}
		if (
			! empty( $providers['url_guard']['available'] )
			&& isset( $providers['url_guard']['enabled'] )
			&& false === $providers['url_guard']['enabled']
		) {
			$findings[] = array(
				'code' => 'guard_disabled',
				'level' => 'info',
				'message' => __( '404 Guard is available but disabled.', 'ua-free-analytics-dashboard' ),
			);
		}
		if ( empty( $providers['donate_stats']['available'] ) ) {
			$findings[] = array(
				'code' => 'donate_stats_unavailable',
				'level' => 'info',
				'message' => __( 'Donate Stats is unavailable.', 'ua-free-analytics-dashboard' ),
			);
		}
		if ( empty( $findings ) ) {
			$findings[] = array(
				'code' => 'no_immediate_warnings',
				'level' => 'success',
				'message' => __( 'No immediate Suite warnings were detected.', 'ua-free-analytics-dashboard' ),
			);
		}

		return $findings;
	}

	/**
	 * @param array<string,array<string,mixed>> $providers
	 * @param array<int,array{code:string,level:string,message:string}> $findings
	 * @return array<int,array<string,mixed>>
	 */
	private static function metrics( array $providers, array $findings, int $days ): array {
		$values = array(
			'suite.providers_available' => self::available_count( $providers ),
			'suite.providers_total' => count( $providers ),
			'suite.findings_total' => count( $findings ),
			'consent.analytics_enabled' => $providers['consent']['analytics'] ?? null,
			'consent.advertising_enabled' => $providers['consent']['advertising'] ?? null,
			'consent.integrations_count' => $providers['consent']['integration_count'] ?? null,
			'guard.log_entries' => $providers['url_guard']['log_entries'] ?? null,
			'guard.redirect_rules' => $providers['url_guard']['redirect_rules'] ?? null,
			'guard.gone_rules' => $providers['url_guard']['gone_rules'] ?? null,
			'seo.organization_configured' => $providers['seo']['organization_configured'] ?? null,
			'seo.output_conflict' => $providers['seo']['output_conflict'] ?? null,
			'translate.source_count' => $providers['translate_diagnostics']['source_count'] ?? null,
			'translate.hint_count' => $providers['translate_diagnostics']['hint_count'] ?? null,
			'translate.language_count' => $providers['translate_diagnostics']['language_count'] ?? null,
			'static_translate.target_language_count' => $providers['static_translate']['target_language_count'] ?? null,
			'static_translate.auto_enabled' => $providers['static_translate']['auto_enabled'] ?? null,
			'donate.donations_total' => $providers['donate_stats']['donations_total'] ?? null,
			'donate.conversions_total' => $providers['donate_stats']['conversions_total'] ?? null,
			'donate.conversion_rate' => $providers['donate_stats']['conversion_rate'] ?? null,
		);

		$metrics = array();
		foreach ( self::METRIC_DEFINITIONS as $id => $definition ) {
			if ( ! array_key_exists( $id, $values ) || null === $values[ $id ] ) {
				continue;
			}
			$value = $values[ $id ];
			if ( 'boolean' === $definition['type'] && ! is_bool( $value ) ) {
				continue;
			}
			if ( 'integer' === $definition['type'] && ( ! is_int( $value ) || $value < 0 ) ) {
				continue;
			}
			if (
				'percent' === $definition['type']
				&& (
					( ! is_int( $value ) && ! is_float( $value ) )
					|| ! is_finite( (float) $value )
					|| (float) $value < 0
					|| (float) $value > 100
				)
			) {
				continue;
			}

			$metrics[] = array(
				'id' => $id,
				'label' => $definition['label'],
				'value' => $value,
				'unit' => $definition['unit'],
				'source' => $definition['source'],
				'period_days' => str_starts_with( $id, 'suite.' ) ? 0 : $days,
			);
		}

		return $metrics;
	}

	/** @param array<string,array<string,mixed>> $providers */
	private static function available_count( array $providers ): int {
		$count = 0;
		foreach ( $providers as $provider ) {
			$count += ! empty( $provider['available'] ) ? 1 : 0;
		}
		return $count;
	}

	/** @return array<string,array<string,mixed>> */
	private static function suite_summary(): array {
		$catalog = array(
			'ua-free-static-translate' => 'UA FREE Static Translate',
			'ua-free-translate-diagnostics' => 'UA FREE Translate Diagnostics',
			'ua-free-seo-core' => 'UA FREE SEO Core',
			'ua-free-404-guard' => 'UA FREE 404 Guard',
			'ua-free-site-bridge' => 'UA FREE Site Bridge',
			'ua-free-consent-manager' => 'UA FREE Consent Manager',
			'ua-free-analytics-dashboard' => 'UA FREE Suite Control Center',
		);

		$status = \UAFree\Suite\Registry::status();
		$result = array();
		foreach ( $catalog as $slug => $label ) {
			$row = isset( $status[ $slug ] ) && is_array( $status[ $slug ] )
				? $status[ $slug ]
				: array();

			$result[ $slug ] = array(
				'label' => $label,
				'installed' => true === self::bool_value( $row['installed'] ?? false ),
				'active' => true === self::bool_value( $row['active'] ?? false ),
				'version' => self::version_value( (string) ( $row['version'] ?? '' ) ),
			);
		}
		return $result;
	}

	/**
	 * Manager-facing summary. No technical IDs are shown on the main screen.
	 *
	 * @return array<string,mixed>
	 */
	public static function control_summary( int $days = 30 ): array {
		$report = self::report( $days );
		$providers = isset( $report['providers'] ) && is_array( $report['providers'] )
			? $report['providers']
			: array();

		$attention = array();
		$actions = array();
		$critical_count = 0;

		foreach ( $providers as $provider_key => $provider ) {
			if ( ! is_array( $provider ) ) {
				continue;
			}
			$state = (string) ( $provider['state'] ?? 'provider_error' );
			if ( 'provider_error' === $state ) {
				++$critical_count;
				$attention[] = array(
					'level' => 'critical',
					'title' => self::provider_label( (string) $provider_key ) . ' не відповідає',
					'message' => 'Компонент повернув помилку. Інші частини сайту продовжують працювати.',
				);
				$actions[] = array(
					'level' => 'critical',
					'title' => 'Перевірити ' . self::provider_label( (string) $provider_key ),
					'description' => 'Відкрити сторінку цього плагіна та перевірити його статус.',
				);
			}
		}

		$translation_hints = (int) ( $providers['translate_diagnostics']['hint_count'] ?? 0 );
		if ( $translation_hints > 0 ) {
			$attention[] = array(
				'level' => 'warning',
				'title' => 'Є підказки щодо перекладу',
				'message' => sprintf(
					'Translate Diagnostics знайшов %d підказку(и), які варто переглянути.',
					$translation_hints
				),
			);
			$actions[] = array(
				'level' => 'warning',
				'title' => 'Перевірити підказки перекладу',
				'description' => 'Відкрити Translate Diagnostics і переглянути знайдені підказки.',
			);
		}

		if ( empty( $providers['donate_stats']['available'] ) ) {
			$attention[] = array(
				'level' => 'info',
				'title' => 'Статистика донатів ще не підключена',
				'message' => 'Це не заважає роботі сайту. Дані про донати з’являться після підключення Donate Stats.',
			);
			$actions[] = array(
				'level' => 'info',
				'title' => 'Підключити Donate Stats пізніше',
				'description' => 'Не терміново. Це окремий наступний продукт.',
			);
		}

		if ( ! empty( $providers['seo']['output_conflict'] ) ) {
			++$critical_count;
			$attention[] = array(
				'level' => 'critical',
				'title' => 'Конфлікт SEO-виводу',
				'message' => 'Інший SEO-модуль може дублювати метадані.',
			);
			$actions[] = array(
				'level' => 'critical',
				'title' => 'Прибрати SEO-конфлікт',
				'description' => 'Перевірити, чи не активний сторонній SEO-плагін.',
			);
		}

		if (
			! empty( $providers['url_guard']['available'] )
			&& isset( $providers['url_guard']['enabled'] )
			&& false === $providers['url_guard']['enabled']
		) {
			$attention[] = array(
				'level' => 'warning',
				'title' => '404 Guard вимкнений',
				'message' => 'Захист 404 доступний, але зараз не активний.',
			);
			$actions[] = array(
				'level' => 'warning',
				'title' => 'Перевірити 404 Guard',
				'description' => 'Увімкнути плагін, якщо його вимкнули не навмисно.',
			);
		}

		$healthy = array();

		if (
			! empty( $providers['seo']['available'] )
			&& empty( $providers['seo']['output_conflict'] )
		) {
			$healthy[] = array(
				'title' => 'SEO працює',
				'message' => 'Сайтмап, файл для AI та SEO-дані доступні.',
			);
		}

		if (
			! empty( $providers['translate_diagnostics']['available'] )
			&& ! empty( $providers['static_translate']['available'] )
		) {
			$language_count = (int) (
				$providers['static_translate']['target_language_count']
				?? $providers['translate_diagnostics']['language_count']
				?? 0
			);
			$healthy[] = array(
				'title' => 'Переклади працюють',
				'message' => $language_count > 0
					? sprintf( 'Активно %d мов.', $language_count )
					: 'Перекладач і діагностика доступні.',
			);
		}

		if ( ! empty( $providers['url_guard']['available'] ) ) {
			$healthy[] = array(
				'title' => '404 Guard працює',
				'message' => 'Захист помилкових адрес активний.',
			);
		}

		if ( ! empty( $providers['consent']['available'] ) ) {
			$healthy[] = array(
				'title' => 'Consent Manager працює',
				'message' => 'Керування згодою відвідувачів доступне.',
			);
		}

		if ( ! empty( $providers['google_ads']['available'] ) ) {
			$healthy[] = array(
				'title' => 'Google Ads Builder доступний',
				'message' => 'Компонент рекламних кампаній підключений.',
			);
		}

		if ( empty( $actions ) ) {
			$actions[] = array(
				'level' => 'success',
				'title' => 'Нічого термінового',
				'description' => 'Усі ключові компоненти працюють. Наступна перевірка — після змін на сайті.',
			);
		}

		$available_count = (int) ( $report['overview']['providers_available'] ?? 0 );
		$total_count = (int) ( $report['overview']['providers_total'] ?? 0 );
		$attention_count = count( $attention );

		if ( $critical_count > 0 ) {
			$overall = 'critical';
			$headline = 'Є проблема, яку треба виправити';
		} elseif ( $attention_count > 0 ) {
			$overall = 'attention';
			$headline = 'Сайт працює, але є кілька справ';
		} else {
			$overall = 'good';
			$headline = 'Усе працює нормально';
		}

		return array(
			'overall' => $overall,
			'headline' => $headline,
			'available_count' => $available_count,
			'total_count' => $total_count,
			'attention_count' => $attention_count,
			'critical_count' => $critical_count,
			'healthy' => $healthy,
			'attention' => $attention,
			'actions' => $actions,
			'report' => $report,
		);
	}

	private static function provider_label( string $key ): string {
		$labels = array(
			'consent' => 'Consent Manager',
			'url_guard' => '404 Guard',
			'seo' => 'SEO Core',
			'translate_diagnostics' => 'Translate Diagnostics',
			'static_translate' => 'Static Translate',
			'site_bridge' => 'Site Bridge',
			'donate_stats' => 'Donate Stats',
			'google_ads' => 'Google Ads Builder',
		);
		return $labels[ $key ] ?? $key;
	}

	private static function render_manager_cards( array $summary ): void {
		$cards = array(
			array(
				'label' => 'Працює компонентів',
				'value' => (int) ( $summary['available_count'] ?? 0 ) . ' / ' . (int) ( $summary['total_count'] ?? 0 ),
			),
			array(
				'label' => 'Потрібна увага',
				'value' => (int) ( $summary['attention_count'] ?? 0 ),
			),
			array(
				'label' => 'Критичні проблеми',
				'value' => (int) ( $summary['critical_count'] ?? 0 ),
			),
		);

		echo '<div class="uafree-manager-cards">';
		foreach ( $cards as $card ) {
			echo '<div class="uafree-manager-card"><span>' .
				esc_html( (string) $card['label'] ) .
				'</span><strong>' .
				esc_html( (string) $card['value'] ) .
				'</strong></div>';
		}
		echo '</div>';
	}

	private static function render_manager_list( string $title, array $items, string $empty_message = '' ): void {
		echo '<section class="uafree-manager-section"><h2>' . esc_html( $title ) . '</h2>';
		if ( empty( $items ) ) {
			echo '<p class="uafree-empty">' . esc_html( $empty_message ) . '</p></section>';
			return;
		}
		echo '<div class="uafree-manager-list">';
		foreach ( $items as $item ) {
			$level = (string) ( $item['level'] ?? 'success' );
			echo '<article class="uafree-manager-item is-' . esc_attr( $level ) . '">';
			echo '<h3>' . esc_html( (string) ( $item['title'] ?? '' ) ) . '</h3>';
			echo '<p>' . esc_html( (string) ( $item['message'] ?? $item['description'] ?? '' ) ) . '</p>';
			echo '</article>';
		}
		echo '</div></section>';
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__(
					'You do not have permission to view this dashboard.',
					'ua-free-analytics-dashboard'
				)
			);
		}

		$days = 30;
		if ( isset( $_GET['days'], $_GET['_wpnonce'] ) ) {
			$period_nonce = sanitize_text_field(
				wp_unslash( (string) $_GET['_wpnonce'] )
			);
			if ( wp_verify_nonce( $period_nonce, 'uafree_control_center_period' ) ) {
				$days = absint( wp_unslash( $_GET['days'] ) );
			}
		}
		$days = self::normalize_days( $days );
		$summary = self::control_summary( $days );
		$report = (array) ( $summary['report'] ?? array() );
		$overall = (string) ( $summary['overall'] ?? 'attention' );

		echo '<div class="wrap uafree-analytics uafree-control-center">';
		echo '<h1>UA FREE Suite Control Center</h1>';
		echo '<div class="uafree-manager-hero is-' . esc_attr( $overall ) . '">';
		echo '<span class="uafree-manager-kicker">Стан сайту</span>';
		echo '<strong>' . esc_html( (string) ( $summary['headline'] ?? '' ) ) . '</strong>';
		echo '<p>Тут показано тільки те, що потребує рішення. Технічні дані заховані нижче.</p>';
		echo '</div>';

		self::render_manager_cards( $summary );
		self::render_manager_list(
			'Все добре',
			(array) ( $summary['healthy'] ?? array() ),
			'Поки немає підтверджених справних компонентів.'
		);
		self::render_manager_list(
			'Потрібна увага',
			(array) ( $summary['attention'] ?? array() ),
			'Нічого не потребує уваги.'
		);
		self::render_manager_list(
			'Що зробити',
			(array) ( $summary['actions'] ?? array() )
		);


		echo '<details class="uafree-technical-details">';
		echo '<summary>Технічні дані та JSON</summary>';
		echo '<div class="uafree-technical-inner">';
		self::render_periods( $days );
		self::render_export( $days );
		self::render_metrics( (array) ( $report['metrics'] ?? array() ) );
		self::render_providers( (array) ( $report['providers'] ?? array() ) );
		self::render_privacy( (array) ( $report['privacy'] ?? array() ) );
		echo '</div></details>';
		echo '</div>';
	}

	private static function render_periods( int $days ): void {
		echo '<div class="uafree-toolbar"><strong>' .
			esc_html__( 'Period:', 'ua-free-analytics-dashboard' ) .
			'</strong>';
		foreach ( self::PERIODS as $period ) {
			$class = $period === $days ? 'button button-primary' : 'button';
			$url = wp_nonce_url(
				add_query_arg(
					array( 'page' => self::PAGE, 'days' => $period ),
					admin_url( 'admin.php' )
				),
				'uafree_control_center_period'
			);
			echo '<a class="' . esc_attr( $class ) . '" href="' .
				esc_url( $url ) . '">' .
				esc_html(
					sprintf(
						/* translators: %d: number of days in the report period. */
						__( '%d days', 'ua-free-analytics-dashboard' ),
						$period
					)
				) .
				'</a>';
		}
		echo '</div>';
	}

	private static function render_export( int $days ): void {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::EXPORT_ACTION,
					'days' => $days,
				),
				admin_url( 'admin-post.php' )
			),
			self::EXPORT_ACTION
		);
		echo '<p><a class="button" href="' . esc_url( $url ) . '">' .
			esc_html__(
				'Export privacy-safe JSON',
				'ua-free-analytics-dashboard'
			) .
			'</a></p>';
	}

	/** @param array<string,mixed> $overview */
	private static function render_cards( array $overview ): void {
		$cards = array(
			__( 'Providers available', 'ua-free-analytics-dashboard' ) =>
				(int) ( $overview['providers_available'] ?? 0 ) .
				' / ' .
				(int) ( $overview['providers_total'] ?? 0 ),
			__( 'Metrics', 'ua-free-analytics-dashboard' ) =>
				(int) ( $overview['metrics_total'] ?? 0 ),
			__( 'Findings', 'ua-free-analytics-dashboard' ) =>
				(int) ( $overview['findings_total'] ?? 0 ),
		);

		echo '<div class="uafree-cards">';
		foreach ( $cards as $label => $value ) {
			echo '<div class="uafree-card"><span>' .
				esc_html( (string) $label ) .
				'</span><strong>' .
				esc_html( (string) $value ) .
				'</strong></div>';
		}
		echo '</div>';
	}

	/** @param array<int,array<string,string>> $findings */
	private static function render_findings( array $findings ): void {
		echo '<h2>' . esc_html__( 'Findings', 'ua-free-analytics-dashboard' ) . '</h2>';
		echo '<ul class="uafree-findings">';
		foreach ( $findings as $finding ) {
			echo '<li class="is-' .
				esc_attr( (string) ( $finding['level'] ?? 'info' ) ) .
				'"><strong>' .
				esc_html( (string) ( $finding['code'] ?? '' ) ) .
				'</strong> ' .
				esc_html( (string) ( $finding['message'] ?? '' ) ) .
				'</li>';
		}
		echo '</ul>';
	}

	/** @param array<int,array<string,mixed>> $metrics */
	private static function render_metrics( array $metrics ): void {
		echo '<h2>' . esc_html__( 'Metrics', 'ua-free-analytics-dashboard' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' .
			esc_html__( 'Metric', 'ua-free-analytics-dashboard' ) .
			'</th><th>' .
			esc_html__( 'Value', 'ua-free-analytics-dashboard' ) .
			'</th><th>' .
			esc_html__( 'Unit', 'ua-free-analytics-dashboard' ) .
			'</th></tr></thead><tbody>';
		foreach ( $metrics as $metric ) {
			echo '<tr><td><code>' .
				esc_html( (string) ( $metric['id'] ?? '' ) ) .
				'</code></td><td>' .
				esc_html( (string) ( $metric['label'] ?? '' ) ) .
				'</td><td>' .
				esc_html( self::display_value( $metric['value'] ?? null ) ) .
				'</td><td>' .
				esc_html( (string) ( $metric['unit'] ?? '' ) ) .
				'</td></tr>';
		}
		echo '</tbody></table>';
	}

	/** @param array<string,array<string,mixed>> $providers */
	private static function render_providers( array $providers ): void {
		$labels = array( 'consent'=>'UA FREE Consent Manager','url_guard'=>'UA FREE 404 Guard','seo'=>'UA FREE SEO Core','translate_diagnostics'=>'UA FREE Translate Diagnostics','static_translate'=>'UA FREE Static Translate','site_bridge'=>'UA FREE Site Bridge','donate_stats'=>'UA FREE Donate Stats & Conversions','google_ads'=>'UA FREE Google Ads Campaign Builder' );
		echo '<h2>' . esc_html__( 'Providers', 'ua-free-analytics-dashboard' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Provider', 'ua-free-analytics-dashboard' ) . '</th><th>' . esc_html__( 'State', 'ua-free-analytics-dashboard' ) . '</th><th>' . esc_html__( 'Version', 'ua-free-analytics-dashboard' ) . '</th></tr></thead><tbody>';
		foreach ( $providers as $key => $provider ) echo '<tr><td>' . esc_html( (string) ( $labels[$key] ?? $key ) ) . '</td><td>' . esc_html( (string) ( $provider['state'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $provider['version'] ?? '' ) ) . '</td></tr>';
		echo '</tbody></table>';
	}

	/** @param array<string,bool> $privacy */
	private static function render_privacy( array $privacy ): void {
		echo '<h2>' .
			esc_html__( 'Privacy boundary', 'ua-free-analytics-dashboard' ) .
			'</h2><ul class="uafree-privacy">';
		foreach ( $privacy as $key => $value ) {
			echo '<li><code>' .
				esc_html( (string) $key ) .
				'</code>: <strong>' .
				esc_html( $value ? 'true' : 'false' ) .
				'</strong></li>';
		}
		echo '</ul>';
	}

	private static function display_value( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}
		return '';
	}

	public static function export_json(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__(
					'You do not have permission to export this report.',
					'ua-free-analytics-dashboard'
				)
			);
		}
		check_admin_referer( self::EXPORT_ACTION );

		$days = 30;
		if ( isset( $_GET['days'], $_GET['_wpnonce'] ) ) {
			$period_nonce = sanitize_text_field(
				wp_unslash( (string) $_GET['_wpnonce'] )
			);
			if ( wp_verify_nonce( $period_nonce, 'uafree_control_center_period' ) ) {
				$days = absint( wp_unslash( $_GET['days'] ) );
			}
		}
		$days = self::normalize_days( $days );
		$report = self::report( $days );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ua-free-analytics-dashboard-' . $days . '-days.json"' );
		header( 'X-Content-Type-Options: nosniff' );

		echo wp_json_encode(
			$report,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		exit;
	}
}
