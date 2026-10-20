<?php
namespace ramirkz\kozgads;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds reviewable Google Ads Editor packages from published WordPress content.
 *
 * The plugin never creates, uploads, posts or enables campaigns automatically.
 */
final class KOZGADS_Plugin {
	private const PAGE_SLUG       = 'koz-google-ads-campaign-builder';
	private const FALLBACK_SUITE_PAGE = 'kozgads-suite';
	private const SETTINGS_OPTION = 'kozgads_settings';
	private const PACKAGE_OPTION  = 'kozgads_last_package';
	private const ACTION_SAVE     = 'kozgads_save';
	private const ACTION_GENERATE = 'kozgads_generate';
	private const ACTION_DOWNLOAD = 'kozgads_download';
	private const ACTION_DELETE   = 'kozgads_delete';
	private const PACKAGE_DIR     = 'koz-google-ads-packages';
	private const LEGACY_PACKAGE_DIR = 'ua-free-google-ads-packages';

	/** @var array<string,array{name:string,ads_code:string,azure_code:string}> */
	private static array $languages = array(
		'uk' => array( 'name' => 'Українська', 'ads_code' => 'uk', 'azure_code' => 'uk' ),
		'en' => array( 'name' => 'English', 'ads_code' => 'en', 'azure_code' => 'en' ),
		'de' => array( 'name' => 'Deutsch', 'ads_code' => 'de', 'azure_code' => 'de' ),
		'fr' => array( 'name' => 'Français', 'ads_code' => 'fr', 'azure_code' => 'fr' ),
		'es' => array( 'name' => 'Español', 'ads_code' => 'es', 'azure_code' => 'es' ),
		'pt' => array( 'name' => 'Português', 'ads_code' => 'pt', 'azure_code' => 'pt' ),
		'it' => array( 'name' => 'Italiano', 'ads_code' => 'it', 'azure_code' => 'it' ),
		'pl' => array( 'name' => 'Polski', 'ads_code' => 'pl', 'azure_code' => 'pl' ),
		'nl' => array( 'name' => 'Nederlands', 'ads_code' => 'nl', 'azure_code' => 'nl' ),
		'ja' => array( 'name' => '日本語', 'ads_code' => 'ja', 'azure_code' => 'ja' ),
		'ar' => array( 'name' => 'العربية', 'ads_code' => 'ar', 'azure_code' => 'ar' ),
		'id' => array( 'name' => 'Bahasa Indonesia', 'ads_code' => 'id', 'azure_code' => 'id' ),
		'hi' => array( 'name' => 'हिन्दी', 'ads_code' => 'hi', 'azure_code' => 'hi' ),
		'zh' => array( 'name' => '简体中文', 'ads_code' => 'zh_CN', 'azure_code' => 'zh-Hans' ),
	);

	public static function init(): void {
		self::migrate_legacy_data();
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_' . self::ACTION_GENERATE, array( __CLASS__, 'generate_package' ) );
		add_action( 'admin_post_' . self::ACTION_DOWNLOAD, array( __CLASS__, 'download_package' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( __CLASS__, 'delete_package' ) );
		KOZGADS_Checks::init();
		KOZGADS_Report::init();
		KOZGADS_Health::init();
		add_filter( 'plugin_action_links_' . plugin_basename( KOZGADS_FILE ), array( __CLASS__, 'action_links' ) );
	}

	private static function existing_suite_page(): string {
		global $menu;

		foreach ( (array) $menu as $item ) {
			$label = isset( $item[0] ) ? trim( wp_strip_all_tags( (string) $item[0] ) ) : '';
			$slug  = isset( $item[2] ) ? (string) $item[2] : '';
			if ( 'KOZ Suite' === $label && '' !== $slug ) {
				return $slug;
			}
		}

		return '';
	}

	private static function suite_page(): string {
		$existing = self::existing_suite_page();
		if ( '' !== $existing ) {
			return $existing;
		}

		add_menu_page(
			__( 'KOZ WordPress Suite', 'koz-google-ads-campaign-builder' ),
			__( 'KOZ Suite', 'koz-google-ads-campaign-builder' ),
			'manage_options',
			self::FALLBACK_SUITE_PAGE,
			array( __CLASS__, 'admin_page' ),
			'dashicons-layout',
			58
		);

		return self::FALLBACK_SUITE_PAGE;
	}

	public static function admin_menu(): void {
		$parent_slug = self::suite_page();

		add_submenu_page(
			$parent_slug,
			__( 'KOZ Google Ads Campaign Builder', 'koz-google-ads-campaign-builder' ),
			__( 'Google Ads Builder', 'koz-google-ads-campaign-builder' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'admin_page' )
		);
	}


	public static function admin_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'kozgads-admin',
			KOZGADS_URL . 'assets/admin.css',
			array(),
			KOZGADS_VERSION
		);
	}

	public static function action_links( array $links ): array {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' .
			esc_html__( 'Open', 'koz-google-ads-campaign-builder' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Privacy-safe, read-only state for other KOZ components.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_status(): array {
		$settings = self::settings();
		$package  = get_option( self::PACKAGE_OPTION, array() );
		$landings = self::selected_landing_pages( $settings );
		$preflight = self::landing_preflight( $settings, $landings );
		$live_check = get_option( KOZGADS_Checks::OPTION, array() );
		$azure = KOZGADS_Integrations::azure_credentials();
		$report = KOZGADS_Report::summary();
		$summary = self::manager_summary_from(
			$settings,
			$landings,
			$azure,
			is_array( $package ) ? $package : array(),
			(string) self::detected_site_language()['slug']
		);

		return array(
			'version'                => defined( 'KOZGADS_VERSION' ) ? KOZGADS_VERSION : '',
			'account_mode'           => self::account_mode( $settings ),
			'configured'             => ! empty( $summary['generation_ready'] ),
			'generation_ready'       => ! empty( $summary['generation_ready'] ),
			'launch_ready'           => ! empty( $summary['launch_ready'] ),
			'candidate_landing_pages'=> (int) $summary['landing_count'],
			'landing_preflight_ready'=> ! empty( $preflight['ready'] ),
			'landing_preflight_issues'=> (int) ( $preflight['issue_count'] ?? 0 ),
			'landing_preflight_warnings'=> (int) ( $preflight['warning_count'] ?? 0 ),
			'live_destination_checked'=> is_array( $live_check ) && ! empty( $live_check['checked_at'] ),
			'live_destination_ready'=> is_array( $live_check ) && ! empty( $live_check['ready'] ),
			'live_destination_issues'=> is_array( $live_check ) ? (int) ( $live_check['issue_count'] ?? 0 ) : 0,
			'live_destination_warnings'=> is_array( $live_check ) ? (int) ( $live_check['warning_count'] ?? 0 ) : 0,
			'live_destination_checked_at'=> is_array( $live_check ) ? (string) ( $live_check['checked_at'] ?? '' ) : '',
			'bid_strategy'           => self::effective_bid_strategy( $settings ),
			'conversion_source'      => self::conversion_source(),
			'report_keywords'        => (int) $report['total'],
			'report_low_volume'      => (int) $report['low'],
			'selected_language_count'=> (int) $summary['language_count'],
			'selected_languages'     => self::campaign_languages( $settings ),
			'target_location_count'  => (int) $summary['location_count'],
			'conversion_confirmed'   => ! empty( $settings['conversion_tracking_confirmed'] ),
			'last_package_available' => ! empty( $summary['package_available'] ),
			'last_package_created'   => is_array( $package ) ? (string) ( $package['created_at'] ?? '' ) : '',
			'uploads_campaigns'      => false,
			'generated_status'       => 'Paused',
		);
	}

	private static function legacy_settings_option(): string {
		return implode( '_', array( 'uafree', 'agb', 'settings' ) );
	}

	private static function legacy_package_option(): string {
		return implode( '_', array( 'uafree', 'agb', 'last', 'package' ) );
	}

	private static function migrate_legacy_data(): void {
		if ( false === get_option( self::SETTINGS_OPTION, false ) ) {
			$legacy_settings = get_option( self::legacy_settings_option(), false );
			if ( is_array( $legacy_settings ) ) {
				update_option( self::SETTINGS_OPTION, $legacy_settings, false );
			}
		}

		if ( false === get_option( self::PACKAGE_OPTION, false ) ) {
			$legacy_package = get_option( self::legacy_package_option(), false );
			if ( is_array( $legacy_package ) ) {
				update_option( self::PACKAGE_OPTION, $legacy_package, false );
			}
		}
	}

	/** @return array<string,array{label:string,description:string}> */
	private static function account_modes(): array {
		return array(
			'ad_grants' => array(
				'label'       => 'Google Ad Grants',
				'description' => __( 'For approved nonprofit organisations. The package uses stricter keyword checks and treats confirmed meaningful conversion tracking as a launch requirement.', 'koz-google-ads-campaign-builder' ),
			),
			'standard_ads' => array(
				'label'       => 'Standard Google Ads',
				'description' => __( 'For a standard paid Google Ads account. Conversion tracking is recommended but does not block package generation.', 'koz-google-ads-campaign-builder' ),
			),
		);
	}

	private static function account_mode( array $settings ): string {
		$mode = sanitize_key( (string) ( $settings['account_mode'] ?? 'ad_grants' ) );
		return isset( self::account_modes()[ $mode ] ) ? $mode : 'ad_grants';
	}

	private static function mode_label( array $settings ): string {
		return (string) self::account_modes()[ self::account_mode( $settings ) ]['label'];
	}

	private static function detected_site_language(): array {
		$slug = KOZGADS_Integrations::source_language();
		if ( isset( self::$languages[ $slug ] ) ) {
			return array( 'slug' => $slug, 'source' => 'KOZ Static Translate' );
		}

		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$locale = strtolower( str_replace( '-', '_', (string) $locale ) );
		$slug   = sanitize_key( (string) strtok( $locale, '_' ) );
		$aliases = array( 'ua' => 'uk', 'ukr' => 'uk', 'zh_cn' => 'zh', 'zh_hans' => 'zh' );
		$slug = $aliases[ $slug ] ?? $slug;
		if ( ! isset( self::$languages[ $slug ] ) ) {
			$slug = 'en';
		}
		return array( 'slug' => $slug, 'source' => 'WordPress locale' );
	}

	/** @return array<string,mixed> */
	private static function defaults(): array {
		$source = self::detected_site_language();
		return array(
			'account_mode'                  => 'ad_grants',
			'organisation_name'             => (string) get_bloginfo( 'name' ),
			'monthly_grant'                 => 10000,
			'currency'                      => 'USD',
			'language_mode'                 => 'automatic',
			'selected_languages'            => array_values( array_unique( array( (string) $source['slug'], 'en' ) ) ),
			'location_mode'                 => 'automatic',
			'target_locations'              => array(),
			'translated_url_pattern'        => '/{language}{path}',
			'final_url_suffix'              => 'utm_source=google&utm_medium=cpc&utm_campaign={campaignid}&utm_content={creative}&utm_term={keyword}',
			'include_search_partners'       => 0,
			'conversion_tracking_confirmed' => 0,
			'primary_conversion_name'       => 'primary_conversion',
			'allow_azure_copy_translation'  => 0,
			'max_campaigns'                 => 4,
			'bid_strategy'                  => 'auto',
			'landing_page_ids'              => array(),
			'generate_source_language'      => 1,
			'min_searches'                  => 10,
			'negative_keywords'             => array(),
			'callouts'                      => array(),
			'delete_data_on_uninstall'      => 0,
		);
	}

	/** @return array<string,mixed> */
	public static function settings(): array {
		$saved    = get_option( self::SETTINGS_OPTION, array() );
		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		$settings['account_mode']       = self::account_mode( $settings );
		$settings['language_mode']      = 'manual' === sanitize_key( (string) ( $settings['language_mode'] ?? 'automatic' ) ) ? 'manual' : 'automatic';
		$settings['location_mode']      = 'manual' === sanitize_key( (string) ( $settings['location_mode'] ?? 'automatic' ) ) ? 'manual' : 'automatic';
		$settings['selected_languages'] = self::campaign_languages( $settings );
		$settings['target_locations']   = self::sanitize_location_targets( $settings['target_locations'] ?? array() );
		$settings['negative_keywords']  = self::sanitize_lines( $settings['negative_keywords'] ?? array(), 100, 80 );
		$settings['callouts']           = self::sanitize_lines( $settings['callouts'] ?? array(), 20, 25 );
		$settings['max_campaigns']      = min( 10, max( 1, absint( $settings['max_campaigns'] ?? 4 ) ) );
		$settings['monthly_grant']      = max( 1, (float) ( $settings['monthly_grant'] ?? 10000 ) );
		$settings['currency']           = strtoupper( substr( sanitize_text_field( (string) ( $settings['currency'] ?? 'USD' ) ), 0, 6 ) );
		$settings['organisation_name']  = sanitize_text_field( (string) ( $settings['organisation_name'] ?? get_bloginfo( 'name' ) ) );
		$settings['translated_url_pattern'] = self::sanitize_url_pattern( (string) ( $settings['translated_url_pattern'] ?? '/{language}{path}' ) );
		$settings['final_url_suffix']   = self::sanitize_suffix( (string) ( $settings['final_url_suffix'] ?? '' ) );
		$settings['primary_conversion_name'] = sanitize_key( (string) ( $settings['primary_conversion_name'] ?? 'primary_conversion' ) );
		if ( '' === $settings['primary_conversion_name'] ) {
			$settings['primary_conversion_name'] = 'primary_conversion';
		}
		if ( ! in_array( (string) $settings['bid_strategy'], self::bid_strategies(), true ) ) {
			$settings['bid_strategy'] = 'auto';
		}
		$settings['landing_page_ids'] = array_slice( array_values( array_unique( array_map( 'absint', (array) ( $settings['landing_page_ids'] ?? array() ) ) ) ), 0, 10 );
		$settings['min_searches']     = min( 1000, max( 0, absint( $settings['min_searches'] ?? 10 ) ) );
		foreach ( array( 'include_search_partners', 'conversion_tracking_confirmed', 'allow_azure_copy_translation', 'delete_data_on_uninstall', 'generate_source_language' ) as $flag ) {
			$settings[ $flag ] = ! empty( $settings[ $flag ] ) ? 1 : 0;
		}
		return $settings;
	}


	/** @return string[] */
	private static function bid_strategies(): array {
		return array( 'auto', 'Maximize conversions', 'Maximize clicks', 'Manual CPC' );
	}

	public static function min_searches(): int {
		return (int) self::settings()['min_searches'];
	}

	/** Where the real conversion comes from, for display and status. */
	private static function conversion_source(): string {
		$donate = KOZGADS_Integrations::donate_stats();
		if ( $donate['available'] && $donate['data_layer'] && '' !== $donate['event'] ) {
			return 'KOZ Donate Stats: ' . $donate['event'];
		}
		return '';
	}

	/**
	 * Bid strategy written to the package. "auto" and Ad Grants without a recorded
	 * conversion use Maximize clicks (capped at 2.00 USD per click by Ad Grants),
	 * because Maximize conversions has no signal without conversions.
	 */
	public static function effective_bid_strategy( array $settings ): string {
		$chosen    = (string) $settings['bid_strategy'];
		$confirmed = ! empty( $settings['conversion_tracking_confirmed'] );
		if ( 'auto' === $chosen ) {
			return $confirmed ? 'Maximize conversions' : 'Maximize clicks';
		}
		if ( 'Maximize conversions' === $chosen && ! $confirmed && 'ad_grants' === self::account_mode( $settings ) ) {
			return 'Maximize clicks';
		}
		return $chosen;
	}

	/** @return string[] */
	public static function campaign_languages( ?array $settings = null ): array {
		$source = (string) self::detected_site_language()['slug'];
		$mode   = is_array( $settings )
			? sanitize_key( (string) ( $settings['language_mode'] ?? 'automatic' ) )
			: 'automatic';

		if ( 'manual' !== $mode ) {
			$languages = array( $source, 'en' );
			if ( is_array( $settings ) && empty( $settings['generate_source_language'] ) && 'en' !== $source ) {
				$languages = array( 'en' );
			}
			return array_values(
				array_unique(
					array_filter(
						$languages,
						static fn( string $slug ): bool => isset( self::$languages[ $slug ] )
					)
				)
			);
		}

		$selected = is_array( $settings )
			? (array) ( $settings['selected_languages'] ?? array() )
			: array();
		$selected = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $selected ),
					static fn( string $slug ): bool => isset( self::$languages[ $slug ] )
				)
			)
		);

		return ! empty( $selected )
			? array_slice( $selected, 0, 10 )
			: array_values( array_unique( array( $source, 'en' ) ) );
	}


	/**
	 * Manager-facing country picker.
	 *
	 * IDs are Google Ads country geo target criterion IDs.
	 *
	 * @return array<string,array{id:int,name:string,label:string}>
	 */
	private static function country_targets(): array {
		return array(
			'UA' => array( 'id' => 2804, 'name' => 'Ukraine', 'label' => self::ui( 'Україна', 'Ukraine' ) ),
			'PL' => array( 'id' => 2616, 'name' => 'Poland', 'label' => self::ui( 'Польща', 'Poland' ) ),
			'DE' => array( 'id' => 2276, 'name' => 'Germany', 'label' => self::ui( 'Німеччина', 'Germany' ) ),
			'FR' => array( 'id' => 2250, 'name' => 'France', 'label' => self::ui( 'Франція', 'France' ) ),
			'GB' => array( 'id' => 2826, 'name' => 'United Kingdom', 'label' => self::ui( 'Велика Британія', 'United Kingdom' ) ),
			'US' => array( 'id' => 2840, 'name' => 'United States', 'label' => self::ui( 'США', 'United States' ) ),
			'CA' => array( 'id' => 2124, 'name' => 'Canada', 'label' => self::ui( 'Канада', 'Canada' ) ),
			'AU' => array( 'id' => 2036, 'name' => 'Australia', 'label' => self::ui( 'Австралія', 'Australia' ) ),
			'AT' => array( 'id' => 2040, 'name' => 'Austria', 'label' => self::ui( 'Австрія', 'Austria' ) ),
			'BE' => array( 'id' => 2056, 'name' => 'Belgium', 'label' => self::ui( 'Бельгія', 'Belgium' ) ),
			'BG' => array( 'id' => 2100, 'name' => 'Bulgaria', 'label' => self::ui( 'Болгарія', 'Bulgaria' ) ),
			'HR' => array( 'id' => 2191, 'name' => 'Croatia', 'label' => self::ui( 'Хорватія', 'Croatia' ) ),
			'CY' => array( 'id' => 2196, 'name' => 'Cyprus', 'label' => self::ui( 'Кіпр', 'Cyprus' ) ),
			'CZ' => array( 'id' => 2203, 'name' => 'Czechia', 'label' => self::ui( 'Чехія', 'Czechia' ) ),
			'DK' => array( 'id' => 2208, 'name' => 'Denmark', 'label' => self::ui( 'Данія', 'Denmark' ) ),
			'EE' => array( 'id' => 2233, 'name' => 'Estonia', 'label' => self::ui( 'Естонія', 'Estonia' ) ),
			'FI' => array( 'id' => 2246, 'name' => 'Finland', 'label' => self::ui( 'Фінляндія', 'Finland' ) ),
			'GR' => array( 'id' => 2300, 'name' => 'Greece', 'label' => self::ui( 'Греція', 'Greece' ) ),
			'HU' => array( 'id' => 2348, 'name' => 'Hungary', 'label' => self::ui( 'Угорщина', 'Hungary' ) ),
			'IE' => array( 'id' => 2372, 'name' => 'Ireland', 'label' => self::ui( 'Ірландія', 'Ireland' ) ),
			'IT' => array( 'id' => 2380, 'name' => 'Italy', 'label' => self::ui( 'Італія', 'Italy' ) ),
			'LV' => array( 'id' => 2428, 'name' => 'Latvia', 'label' => self::ui( 'Латвія', 'Latvia' ) ),
			'LT' => array( 'id' => 2440, 'name' => 'Lithuania', 'label' => self::ui( 'Литва', 'Lithuania' ) ),
			'LU' => array( 'id' => 2442, 'name' => 'Luxembourg', 'label' => self::ui( 'Люксембург', 'Luxembourg' ) ),
			'MD' => array( 'id' => 2498, 'name' => 'Moldova', 'label' => self::ui( 'Молдова', 'Moldova' ) ),
			'NL' => array( 'id' => 2528, 'name' => 'Netherlands', 'label' => self::ui( 'Нідерланди', 'Netherlands' ) ),
			'NO' => array( 'id' => 2578, 'name' => 'Norway', 'label' => self::ui( 'Норвегія', 'Norway' ) ),
			'PT' => array( 'id' => 2620, 'name' => 'Portugal', 'label' => self::ui( 'Португалія', 'Portugal' ) ),
			'RO' => array( 'id' => 2642, 'name' => 'Romania', 'label' => self::ui( 'Румунія', 'Romania' ) ),
			'SK' => array( 'id' => 2703, 'name' => 'Slovakia', 'label' => self::ui( 'Словаччина', 'Slovakia' ) ),
			'SI' => array( 'id' => 2705, 'name' => 'Slovenia', 'label' => self::ui( 'Словенія', 'Slovenia' ) ),
			'ES' => array( 'id' => 2724, 'name' => 'Spain', 'label' => self::ui( 'Іспанія', 'Spain' ) ),
			'SE' => array( 'id' => 2752, 'name' => 'Sweden', 'label' => self::ui( 'Швеція', 'Sweden' ) ),
			'CH' => array( 'id' => 2756, 'name' => 'Switzerland', 'label' => self::ui( 'Швейцарія', 'Switzerland' ) ),
			'GE' => array( 'id' => 2268, 'name' => 'Georgia', 'label' => self::ui( 'Грузія', 'Georgia' ) ),
			'IL' => array( 'id' => 2376, 'name' => 'Israel', 'label' => self::ui( 'Ізраїль', 'Israel' ) ),
			'JP' => array( 'id' => 2392, 'name' => 'Japan', 'label' => self::ui( 'Японія', 'Japan' ) ),
			'TR' => array( 'id' => 2792, 'name' => 'Türkiye', 'label' => self::ui( 'Туреччина', 'Türkiye' ) ),
			'NZ' => array( 'id' => 2554, 'name' => 'New Zealand', 'label' => self::ui( 'Нова Зеландія', 'New Zealand' ) ),
			'BR' => array( 'id' => 2076, 'name' => 'Brazil', 'label' => self::ui( 'Бразилія', 'Brazil' ) ),
			'MX' => array( 'id' => 2484, 'name' => 'Mexico', 'label' => self::ui( 'Мексика', 'Mexico' ) ),
			'IN' => array( 'id' => 2356, 'name' => 'India', 'label' => self::ui( 'Індія', 'India' ) ),
			'ID' => array( 'id' => 2360, 'name' => 'Indonesia', 'label' => self::ui( 'Індонезія', 'Indonesia' ) ),
			'CN' => array( 'id' => 2156, 'name' => 'China', 'label' => self::ui( 'Китай', 'China' ) ),
			'AE' => array( 'id' => 2784, 'name' => 'United Arab Emirates', 'label' => self::ui( 'Об’єднані Арабські Емірати', 'United Arab Emirates' ) ),
			'SG' => array( 'id' => 2702, 'name' => 'Singapore', 'label' => self::ui( 'Сінгапур', 'Singapore' ) ),
		);
	}


	/** @return array{code:string,source:string} */
	private static function detected_site_country(): array {
		$catalog = self::country_targets();
		$locale  = function_exists( 'get_locale' ) ? (string) get_locale() : '';
		$locale  = strtoupper( str_replace( '-', '_', $locale ) );
		$parts   = array_values( array_filter( explode( '_', $locale ) ) );
		$region  = count( $parts ) > 1 ? (string) end( $parts ) : '';
		$aliases = array( 'UK' => 'GB' );
		$region  = $aliases[ $region ] ?? $region;

		if ( isset( $catalog[ $region ] ) ) {
			return array( 'code' => $region, 'source' => 'WordPress locale' );
		}

		$language = (string) self::detected_site_language()['slug'];
		$defaults = array(
			'uk' => 'UA', 'en' => 'GB', 'de' => 'DE', 'fr' => 'FR',
			'es' => 'ES', 'pt' => 'PT', 'it' => 'IT', 'pl' => 'PL',
			'nl' => 'NL', 'ja' => 'JP', 'ar' => 'AE', 'id' => 'ID',
			'hi' => 'IN', 'zh' => 'CN',
		);
		$code = (string) ( $defaults[ $language ] ?? 'GB' );

		return array(
			'code'   => isset( $catalog[ $code ] ) ? $code : 'GB',
			'source' => 'site language',
		);
	}

	/** @return string[] */
	private static function english_market_country_codes(): array {
		return array( 'GB', 'US', 'CA', 'AU', 'IE', 'NZ' );
	}

	/** @return string[] */
	private static function automatic_country_codes_for_language( string $language ): array {
		$source       = (string) self::detected_site_language()['slug'];
		$site_country = (string) self::detected_site_country()['code'];

		if ( 'en' === $language ) {
			$codes = self::english_market_country_codes();
			if ( 'en' === $source && '' !== $site_country ) {
				array_unshift( $codes, $site_country );
			}
			return array_values( array_unique( $codes ) );
		}

		if ( $language === $source && '' !== $site_country ) {
			return array( $site_country );
		}

		$defaults = array(
			'uk' => 'UA', 'de' => 'DE', 'fr' => 'FR', 'es' => 'ES',
			'pt' => 'PT', 'it' => 'IT', 'pl' => 'PL', 'nl' => 'NL',
			'ja' => 'JP', 'ar' => 'AE', 'id' => 'ID', 'hi' => 'IN',
			'zh' => 'CN',
		);

		return isset( $defaults[ $language ] )
			? array( $defaults[ $language ] )
			: array( $site_country );
	}

	/**
	 * @param string[] $codes
	 * @return array<int,array{language:string,id:int,name:string}>
	 */
	private static function location_rows_from_country_codes( array $codes, string $language ): array {
		$countries = self::country_targets();
		$out       = array();

		foreach ( array_values( array_unique( $codes ) ) as $code ) {
			if ( ! isset( $countries[ $code ] ) ) {
				continue;
			}
			$out[] = array(
				'language' => $language,
				'id'       => (int) $countries[ $code ]['id'],
				'name'     => (string) $countries[ $code ]['name'],
			);
		}
		return $out;
	}

	/** @return string[] */
	private static function selected_country_codes( array $settings ): array {
		$by_id = array();
		foreach ( self::country_targets() as $code => $country ) {
			$by_id[ (int) $country['id'] ] = $code;
		}

		$selected = array();
		foreach ( (array) ( $settings['target_locations'] ?? array() ) as $location ) {
			$id = (int) ( $location['id'] ?? 0 );
			if ( isset( $by_id[ $id ] ) ) {
				$selected[] = $by_id[ $id ];
			}
		}
		return array_values( array_unique( $selected ) );
	}

	/** @return array<int,array{language:string,id:int,name:string}> */
	private static function locations_from_country_codes( $value ): array {
		$countries = self::country_targets();
		$codes = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( $code ): string => strtoupper( sanitize_key( (string) $code ) ),
						(array) $value
					),
					static fn( string $code ): bool => isset( $countries[ $code ] )
				)
			)
		);

		$out = array();
		foreach ( $codes as $code ) {
			$country = $countries[ $code ];
			$out[] = array(
				'language' => '*',
				'id'       => (int) $country['id'],
				'name'     => (string) $country['name'],
			);
		}
		return $out;
	}

	/** @return array<int,array{language:string,id:int,name:string}> */
	private static function sanitize_location_targets( $value ): array {
		$rows = is_array( $value ) ? $value : preg_split( '/\r\n|\r|\n/', (string) $value );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			if ( is_array( $row ) ) {
				$lang = sanitize_key( (string) ( $row['language'] ?? '*' ) );
				$id   = absint( $row['id'] ?? 0 );
				$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
			} else {
				$parts = array_map( 'trim', explode( '|', (string) $row, 3 ) );
				$lang  = sanitize_key( (string) ( $parts[0] ?? '*' ) );
				$id    = absint( $parts[1] ?? 0 );
				$name  = sanitize_text_field( (string) ( $parts[2] ?? '' ) );
			}
			if ( '' === $lang ) {
				$lang = '*';
			}
			if ( '*' !== $lang && ! isset( self::$languages[ $lang ] ) ) {
				continue;
			}
			if ( $id <= 0 || '' === $name ) {
				continue;
			}
			$out[ $lang . ':' . $id ] = array( 'language' => $lang, 'id' => $id, 'name' => $name );
			if ( count( $out ) >= 100 ) {
				break;
			}
		}
		return array_values( $out );
	}

	private static function locations_text( array $locations ): string {
		$rows = array();
		foreach ( $locations as $location ) {
			$rows[] = (string) $location['language'] . '|' . (int) $location['id'] . '|' . (string) $location['name'];
		}
		return implode( "\n", $rows );
	}

	/** @return array<int,array{language:string,id:int,name:string}> */
	private static function geo_targets_for_language( string $language, array $settings ): array {
		$mode = sanitize_key( (string) ( $settings['location_mode'] ?? 'automatic' ) );

		if ( 'manual' === $mode ) {
			return array_values(
				array_filter(
					(array) ( $settings['target_locations'] ?? array() ),
					static fn( array $row ): bool =>
						'*' === (string) ( $row['language'] ?? '' )
						|| $language === (string) ( $row['language'] ?? '' )
				)
			);
		}

		return self::location_rows_from_country_codes(
			self::automatic_country_codes_for_language( $language ),
			$language
		);
	}

	private static function automatic_location_count( array $settings ): int {
		$count = 0;
		foreach ( self::campaign_languages( $settings ) as $language ) {
			$count += count( self::geo_targets_for_language( $language, $settings ) );
		}
		return $count;
	}

	/** @return string[] */
	private static function sanitize_lines( $value, int $max_rows, int $max_length ): array {
		$rows = is_array( $value ) ? $value : preg_split( '/\r\n|\r|\n/', (string) $value );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$row = self::clip( self::normalise_text( sanitize_text_field( (string) $row ) ), $max_length );
			if ( '' !== $row ) {
				$out[ self::lower( $row ) ] = $row;
			}
			if ( count( $out ) >= $max_rows ) {
				break;
			}
		}
		return array_values( $out );
	}

	private static function sanitize_url_pattern( string $pattern ): string {
		$pattern = trim( wp_strip_all_tags( $pattern ) );
		if ( '' === $pattern ) {
			return '';
		}
		if ( false === strpos( $pattern, '{language}' ) || false === strpos( $pattern, '{path}' ) ) {
			return '/{language}{path}';
		}
		$pattern = (string) preg_replace( '/[^A-Za-z0-9_\-\/{}.]/', '', $pattern );
		return '/' . ltrim( $pattern, '/' );
	}

	private static function sanitize_suffix( string $suffix ): string {
		$suffix = ltrim( trim( wp_strip_all_tags( $suffix ) ), '?' );
		return substr( $suffix, 0, 1500 );
	}

	public static function save_settings(): void {
		self::require_admin();
		check_admin_referer( self::ACTION_SAVE );
		$mode = sanitize_key( (string) wp_unslash( $_POST['account_mode'] ?? 'ad_grants' ) );
		if ( ! isset( self::account_modes()[ $mode ] ) ) {
			$mode = 'ad_grants';
		}
		$selected_languages = isset( $_POST['selected_languages'] )
			? array_map( 'sanitize_key', (array) wp_unslash( $_POST['selected_languages'] ) )
			: array();
		$languages = array_values(
			array_unique(
				array_filter(
					$selected_languages,
					static fn( string $slug ): bool => isset( self::$languages[ $slug ] )
				)
			)
		);
		if ( empty( $languages ) ) {
			$languages = array( (string) self::detected_site_language()['slug'] );
		}
		$bid = sanitize_text_field( (string) wp_unslash( $_POST['bid_strategy'] ?? 'auto' ) );
		if ( ! in_array( $bid, self::bid_strategies(), true ) ) {
			$bid = 'auto';
		}
		$landing_ids = isset( $_POST['landing_page_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['landing_page_ids'] ) ) : array();

		$language_mode = 'manual' === sanitize_key( (string) wp_unslash( $_POST['language_mode'] ?? 'automatic' ) ) ? 'manual' : 'automatic';
		$location_mode = 'manual' === sanitize_key( (string) wp_unslash( $_POST['location_mode'] ?? 'automatic' ) ) ? 'manual' : 'automatic';
		$monthly_grant = sanitize_text_field( (string) wp_unslash( $_POST['monthly_grant'] ?? '10000' ) );
		$target_countries = isset( $_POST['target_countries'] )
			? array_map( 'sanitize_key', (array) wp_unslash( $_POST['target_countries'] ) )
			: array();
		$translated_url_pattern = sanitize_text_field( (string) wp_unslash( $_POST['translated_url_pattern'] ?? '/{language}{path}' ) );
		$final_url_suffix = sanitize_text_field( (string) wp_unslash( $_POST['final_url_suffix'] ?? '' ) );
		$negative_keywords = sanitize_textarea_field( (string) wp_unslash( $_POST['negative_keywords'] ?? '' ) );
		$callouts = sanitize_textarea_field( (string) wp_unslash( $_POST['callouts'] ?? '' ) );

		$settings = array(
			'account_mode'                  => $mode,
			'language_mode'                 => $language_mode,
			'organisation_name'             => sanitize_text_field( (string) wp_unslash( $_POST['organisation_name'] ?? get_bloginfo( 'name' ) ) ),
			'monthly_grant'                 => max( 1, (float) $monthly_grant ),
			'currency'                      => strtoupper( substr( sanitize_text_field( (string) wp_unslash( $_POST['currency'] ?? 'USD' ) ), 0, 6 ) ),
			'selected_languages'            => array_slice( $languages, 0, 10 ),
			'location_mode'                 => $location_mode,
			'target_locations'              => self::locations_from_country_codes( $target_countries ),
			'translated_url_pattern'        => self::sanitize_url_pattern( $translated_url_pattern ),
			'final_url_suffix'              => self::sanitize_suffix( $final_url_suffix ),
			'include_search_partners'       => ! empty( $_POST['include_search_partners'] ) ? 1 : 0,
			'conversion_tracking_confirmed' => ! empty( $_POST['conversion_tracking_confirmed'] ) ? 1 : 0,
			'primary_conversion_name'       => sanitize_key( (string) wp_unslash( $_POST['primary_conversion_name'] ?? 'primary_conversion' ) ),
			'allow_azure_copy_translation'  => ! empty( $_POST['allow_azure_copy_translation'] ) ? 1 : 0,
			'max_campaigns'                 => min( 10, max( 1, absint( $_POST['max_campaigns'] ?? 4 ) ) ),
			'bid_strategy'                  => $bid,
			'negative_keywords'             => self::sanitize_lines( $negative_keywords, 100, 80 ),
			'callouts'                      => self::sanitize_lines( $callouts, 20, 25 ),
			'delete_data_on_uninstall'      => ! empty( $_POST['delete_data_on_uninstall'] ) ? 1 : 0,
			'generate_source_language'      => ! empty( $_POST['generate_source_language'] ) ? 1 : 0,
			'landing_page_ids'              => array_slice( array_values( array_unique( $landing_ids ) ), 0, 10 ),
			'min_searches'                  => min( 1000, absint( $_POST['min_searches'] ?? 10 ) ),
		);
		if ( '' === $settings['primary_conversion_name'] ) {
			$settings['primary_conversion_name'] = 'primary_conversion';
		}
		update_option( self::SETTINGS_OPTION, $settings, false );
		self::redirect_notice( 'settings_saved' );
	}

	private static function require_admin(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this plugin.', 'koz-google-ads-campaign-builder' ) );
		}
	}

	public static function redirect_notice( string $notice, string $message = '' ): void {
		$args = array( 'page' => self::PAGE_SLUG, 'kozgads_notice' => sanitize_key( $notice ) );
		if ( '' !== $message ) {
			$args['message'] = rawurlencode( $message );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/** @return array<string,mixed> */
	private static function normalise_post( \WP_Post $post, bool $front ): array {
		$title   = self::normalise_text( wp_strip_all_tags( get_the_title( $post ) ) );
		$content = self::normalise_text( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
		$excerpt = self::normalise_text( wp_strip_all_tags( (string) $post->post_excerpt ) );
		$url     = $front ? home_url( '/' ) : get_permalink( $post );
		$path    = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( '' === $path ) {
			$path = '/';
		}
		$text = trim( $title . ' ' . ( '' !== $excerpt ? $excerpt : $content ) );
		return array(
			'id'         => (int) $post->ID,
			'type'       => (string) $post->post_type,
			'title'      => '' !== $title ? $title : (string) get_bloginfo( 'name' ),
			'url'        => esc_url_raw( $url ),
			'path'       => '/' . ltrim( $path, '/' ),
			'word_count' => self::word_count( $text ),
			'text'       => self::clip( $text, 1200 ),
			'updated_at' => get_post_modified_time( 'c', true, $post ),
		);
	}

	private static function word_count( string $text ): int {
		$words = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $words ) ? count( $words ) : 0;
	}

	/** Page IDs used in the site's navigation menus. */
	private static function menu_page_ids(): array {
		$ids = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
				if ( isset( $item->object, $item->object_id ) && 'page' === $item->object ) {
					$ids[] = (int) $item->object_id;
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/** Pages the administrator can pick in the settings: menu pages first, then other pages. */
	private static function landing_choices(): array {
		$ids   = self::menu_page_ids();
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 60, 'orderby' => 'menu_order title', 'order' => 'ASC', 'fields' => 'ids', 'no_found_rows' => true ) );
		$ids   = array_values( array_unique( array_merge( $ids, array_map( 'intval', (array) $pages ) ) ) );
		$out   = array();
		foreach ( array_slice( $ids, 0, 60 ) as $id ) {
			$post = get_post( $id );
			if ( $post instanceof \WP_Post && 'publish' === $post->post_status ) {
				$out[ $id ] = self::normalise_text( wp_strip_all_tags( get_the_title( $post ) ) );
			}
		}
		return $out;
	}

	/**
	 * Landing pages for the campaign: the pages chosen in the settings, or
	 * automatically the home page, the donation page known to KOZ Donate Stats,
	 * pages from the navigation menus and then the longest other pages. Blog posts
	 * are used only when chosen explicitly.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function selected_landing_pages( array $settings ): array {
		$limit    = (int) $settings['max_campaigns'];
		$front_id = absint( get_option( 'page_on_front' ) );
		$ordered  = array();
		$manual   = (array) $settings['landing_page_ids'];
		if ( ! empty( $manual ) ) {
			foreach ( $manual as $id ) {
				$ordered[] = (int) $id;
			}
		} else {
			$ordered[] = $front_id;
			foreach ( KOZGADS_Integrations::donate_stats()['page_ids'] as $id ) {
				$ordered[] = (int) $id;
			}
			foreach ( self::menu_page_ids() as $id ) {
				$ordered[] = (int) $id;
			}
			$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 40, 'no_found_rows' => true ) );
			$pages = array_map( static fn( \WP_Post $post ): array => self::normalise_post( $post, false ), array_filter( (array) $pages, static fn( $post ): bool => $post instanceof \WP_Post ) );
			usort( $pages, static fn( array $a, array $b ): int => (int) $b['word_count'] <=> (int) $a['word_count'] );
			foreach ( $pages as $page ) {
				$ordered[] = (int) $page['id'];
			}
		}
		$selected = array();
		$seen     = array();
		foreach ( $ordered as $id ) {
			if ( count( $selected ) >= $limit ) {
				break;
			}
			if ( 0 === $id || $id === $front_id ) {
				if ( isset( $seen['home'] ) ) {
					continue;
				}
				$seen['home'] = true;
				$front = $front_id > 0 ? get_post( $front_id ) : null;
				if ( $front instanceof \WP_Post && 'publish' === $front->post_status ) {
					$selected[] = self::normalise_post( $front, true );
				} else {
					$selected[] = array( 'id' => 0, 'type' => 'home', 'title' => (string) get_bloginfo( 'name' ), 'url' => home_url( '/' ), 'path' => '/', 'word_count' => self::word_count( (string) get_bloginfo( 'description' ) ), 'text' => self::normalise_text( (string) get_bloginfo( 'description' ) ), 'updated_at' => '' );
				}
				continue;
			}
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$post        = get_post( $id );
			if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status || ( empty( $manual ) && 'page' !== $post->post_type ) ) {
				continue;
			}
			$page = self::normalise_post( $post, false );
			if ( empty( $manual ) && (int) $page['word_count'] < 20 ) {
				continue;
			}
			$selected[] = $page;
		}
		$result = array();
		foreach ( $selected as $index => $page ) {
			$result[ 'landing-' . ( $index + 1 ) ] = $page;
		}
		return $result;
	}

	/** True for the donation page (Donate Stats) or a path that looks like one. */
	private static function is_donation_page( array $page ): bool {
		if ( in_array( (int) ( $page['id'] ?? 0 ), KOZGADS_Integrations::donate_stats()['page_ids'], true ) ) {
			return true;
		}
		return (bool) preg_match( '#/(donat|donate|spend|pozhert|donation)#i', (string) ( $page['path'] ?? '' ) );
	}

	public static function localised_url( string $url, string $language, array $settings ): string {
		$source = (string) self::detected_site_language()['slug'];
		if ( $language === $source ) {
			return $url;
		}
		$pattern = (string) $settings['translated_url_pattern'];
		if ( '' === $pattern ) {
			return $url;
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = '/' . ltrim( $path, '/' );
		$path = '/' === $path ? '/' : untrailingslashit( $path ) . '/';
		$local_path = str_replace( array( '{language}', '{path}' ), array( rawurlencode( $language ), $path ), $pattern );
		$local_path = '/' . ltrim( $local_path, '/' );
		$local_path = (string) preg_replace( '#/+#', '/', $local_path );
		return home_url( $local_path );
	}


	/**
	 * Read-only landing-page preflight for selected campaign destinations.
	 *
	 * This intentionally performs no HTTP requests. It validates the local WordPress
	 * selection and generated same-site URLs, while leaving browser/runtime checks
	 * explicit as not verified.
	 *
	 * @param array<string,mixed> $settings
	 * @param array<string,array<string,mixed>> $landings
	 * @return array<string,mixed>
	 */
	private static function landing_preflight( array $settings, array $landings ): array {
		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$home_scheme = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_SCHEME ) );
		$languages = self::campaign_languages( $settings );
		$issues = array();
		$warnings = array();
		$rows = array();
		$generated_url_count = 0;

		foreach ( $landings as $key => $page ) {
			$url = esc_url_raw( (string) ( $page['url'] ?? '' ) );
			$title = trim( (string) ( $page['title'] ?? '' ) );
			$word_count = max( 0, (int) ( $page['word_count'] ?? 0 ) );
			$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
			$same_site = '' !== $host && $host === $home_host;
			$https_ok = 'https' !== $home_scheme || 'https' === $scheme;
			$title_ok = '' !== $title;
			$content_ok = $word_count >= 20;

			if ( '' === $url || ! $same_site ) {
				$issues[] = (string) $key . ': source landing URL is missing or not on the current site.';
			}
			if ( ! $https_ok ) {
				$issues[] = (string) $key . ': source landing URL does not use HTTPS.';
			}
			if ( ! $title_ok ) {
				$issues[] = (string) $key . ': landing page title is empty.';
			}
			if ( ! $content_ok ) {
				$issues[] = (string) $key . ': landing page has fewer than 20 words of local source content.';
			} elseif ( $word_count < 50 ) {
				$warnings[] = (string) $key . ': landing page is relatively thin (under 50 words).';
			}

			$generated = array();
			foreach ( $languages as $language ) {
				$final_url = self::localised_url( $url, $language, $settings );
				$generated_url_count++;
				$final_host = strtolower( (string) wp_parse_url( $final_url, PHP_URL_HOST ) );
				$final_scheme = strtolower( (string) wp_parse_url( $final_url, PHP_URL_SCHEME ) );
				$valid = '' !== $final_url
					&& '' !== $final_host
					&& $final_host === $home_host
					&& in_array( $final_scheme, array( 'http', 'https' ), true )
					&& ( 'https' !== $home_scheme || 'https' === $final_scheme );
				if ( ! $valid ) {
					$issues[] = (string) $key . '/' . $language . ': generated landing URL is invalid or leaves the current site.';
				}
				$generated[] = array(
					'language' => $language,
					'url' => $final_url,
					'same_site' => $valid,
				);
			}

			$rows[] = array(
				'key' => (string) $key,
				'title' => $title,
				'url' => $url,
				'word_count' => $word_count,
				'same_site' => $same_site,
				'https_ok' => $https_ok,
				'title_present' => $title_ok,
				'content_minimum_met' => $content_ok,
				'generated_urls' => $generated,
			);
		}

		$issues = array_values( array_unique( $issues ) );
		$warnings = array_values( array_unique( $warnings ) );

		return array(
			'ready' => ! empty( $rows ) && empty( $issues ),
			'page_count' => count( $rows ),
			'generated_url_count' => $generated_url_count,
			'issue_count' => count( $issues ),
			'warning_count' => count( $warnings ),
			'issues' => $issues,
			'warnings' => $warnings,
			'pages' => $rows,
			'external_requests' => false,
			'browser_runtime' => 'NOT VERIFIED BY SERVER-SIDE PREFLIGHT',
			'checked_at' => gmdate( 'c' ),
		);
	}



	/** Page signals for the source language, from the rendered page or local content. */
	private static function source_signals( array $page ): array {
		$signals = KOZGADS_Copy::signals( (string) $page['url'] );
		if ( empty( $signals['ok'] ) ) {
			$signals = array_merge(
				$signals,
				array( 'ok' => true, 'title' => (string) $page['title'], 'h1' => (string) $page['title'], 'description' => self::first_words( (string) $page['text'], 25 ), 'text' => (string) $page['text'] )
			);
		}
		return $signals;
	}

	/**
	 * Copy for every landing page in one language.
	 *
	 * Other languages use the translated page published on the site. Azure is used
	 * only when that page is missing and the administrator allowed translation.
	 *
	 * @return array{groups:array<string,array>,warnings:string[]}
	 */
	private static function language_copy( array $landings, string $language, string $source, array $settings ): array {
		$groups       = array();
		$warnings     = array();
		$organisation = (string) $settings['organisation_name'];
		foreach ( $landings as $key => $page ) {
			$url     = self::localised_url( (string) $page['url'], $language, $settings );
			$kind    = self::is_donation_page( $page ) ? 'donate' : 'general';
			$is_home = '/' === (string) $page['path'];
			if ( $language === $source ) {
				$signals = self::source_signals( $page );
			} else {
				$signals = KOZGADS_Copy::signals( $url );
				$native  = ! empty( $signals['ok'] )
					&& ( '' === $signals['lang'] || 0 === strpos( (string) $signals['lang'], $language ) )
					&& ! KOZGADS_Copy::script_mismatch( (string) $signals['text'], $language )
					// A partly translated page keeps the source-language title.
					&& ! KOZGADS_Copy::script_mismatch( str_replace( KOZGADS_Copy::brand( (string) $settings['organisation_name'] ), '', (string) $signals['title'] ), $language, 3, 0.5 );
				if ( ! $native ) {
					$translated = self::azure_signals( self::source_signals( $page ), $source, $language, $settings );
					if ( is_wp_error( $translated ) ) {
						$warnings[] = $language . ' / ' . (string) $page['title'] . ': ' . $translated->get_error_message();
						continue;
					}
					$signals = $translated;
				}
			}
			$group = KOZGADS_Copy::group( $signals, $language, $organisation, $kind, $is_home );
			$words = KOZGADS_Copy::words( $group['name'] . ' ' . (string) $signals['h1'] . ' ' . implode( ' ', (array) $signals['h2'] ) );
			$demand = KOZGADS_Report::apply( $group['keywords'], $words, KOZGADS_Copy::MAX_KEYWORDS );
			$group['keywords']           = array_values( array_filter( $demand['keywords'], static fn( string $keyword ): bool => self::valid_keyword( $keyword, self::account_mode( $settings ) ) ) );
			$group['low_volume_removed'] = $demand['removed'];
			$group['planner_added']      = $demand['added'];
			$group['final_url']          = $url;
			$group['h2']                 = (array) $signals['h2'];
			foreach ( $group['warnings'] as $warning ) {
				$warnings[] = $language . ' / ' . $group['name'] . ': ' . $warning;
			}
			$groups[ $key ] = $group;
		}
		return array( 'groups' => $groups, 'warnings' => $warnings );
	}

	/** Source signals translated with Azure, within the Static Translate quota. @return array|\WP_Error */
	private static function azure_signals( array $signals, string $source, string $target, array $settings ) {
		if ( empty( $settings['allow_azure_copy_translation'] ) ) {
			return new \WP_Error( 'no_translated_page', __( 'No translated page was found and Azure translation is disabled.', 'koz-google-ads-campaign-builder' ) );
		}
		$strings = array_merge( array( (string) $signals['title'], (string) $signals['h1'], (string) $signals['description'] ), array_slice( (array) $signals['h2'], 0, 6 ) );
		$out     = self::translate_strings( $strings, $source, $target, KOZGADS_Integrations::azure_credentials(), KOZGADS_Copy::brand( (string) $settings['organisation_name'] ) );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		return array_merge(
			$signals,
			array( 'ok' => true, 'lang' => $target, 'title' => $out[0], 'h1' => $out[1], 'description' => $out[2], 'h2' => array_slice( $out, 3 ), 'text' => $out[2] )
		);
	}

	private static function first_words( string $text, int $count ): string {
		$words = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $words ) ? implode( ' ', array_slice( $words, 0, $count ) ) : '';
	}

	/**
	 * Translate a flat string list through the Static Translate Azure credentials.
	 * External requests happen only while an administrator explicitly generates a package.
	 *
	 * @return string[]|\WP_Error
	 */
	private static function translate_strings( array $strings, string $source, string $target, array $credentials, string $brand ) {
		if ( $source === $target ) {
			return array_values( array_map( 'strval', $strings ) );
		}
		if ( empty( $credentials['configured'] ) ) {
			return new \WP_Error( 'azure_not_configured', __( 'Azure Translator is not configured.', 'koz-google-ads-campaign-builder' ) );
		}
		$characters = array_sum( array_map( static fn( $value ): int => KOZGADS_Copy::length( (string) $value ), $strings ) );
		$available  = KOZGADS_Integrations::quota_available();
		if ( null !== $available && $available < $characters ) {
			return new \WP_Error( 'azure_quota', __( 'The KOZ Static Translate monthly Azure quota is used up.', 'koz-google-ads-campaign-builder' ) );
		}
		$from = self::$languages[ $source ]['azure_code'] ?? $source;
		$to   = self::$languages[ $target ]['azure_code'] ?? $target;
		$url  = add_query_arg(
			array( 'api-version' => '3.0', 'from' => $from, 'to' => $to, 'textType' => 'html' ),
			(string) $credentials['endpoint'] . '/translate'
		);
		$body = array();
		foreach ( $strings as $string ) {
			$value = (string) $string;
			if ( '' !== $brand ) {
				$value = str_replace( $brand, '<span class="notranslate">' . esc_html( $brand ) . '</span>', $value );
			}
			$body[] = array( 'Text' => $value );
		}
		$headers = array(
			'Content-Type'                => 'application/json; charset=utf-8',
			'Ocp-Apim-Subscription-Key'  => (string) $credentials['key'],
			'X-ClientTraceId'             => wp_generate_uuid4(),
		);
		if ( '' !== (string) $credentials['region'] ) {
			$headers['Ocp-Apim-Subscription-Region'] = (string) $credentials['region'];
		}
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 45,
				'headers' => $headers,
				'body'    => wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $data ) || count( $data ) !== count( $strings ) ) {
			/* translators: %d: HTTP status code returned by Azure Translator. */
			return new \WP_Error( 'azure_error', sprintf( __( 'Azure Translator returned an invalid response (HTTP %d).', 'koz-google-ads-campaign-builder' ), $status ) );
		}
		KOZGADS_Integrations::record_usage( $target, $characters );
		$out = array();
		foreach ( $strings as $index => $original ) {
			$value = (string) ( $data[ $index ]['translations'][0]['text'] ?? '' );
			$value = self::normalise_text( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
			$out[] = '' !== $value ? $value : (string) $original;
		}
		return $out;
	}

	private static function generation_id(): string {
		$uuid = strtolower(
			(string) preg_replace(
				'/[^a-f0-9]/i',
				'',
				wp_generate_uuid4()
			)
		);
		$token = substr( $uuid, 0, 8 );
		if ( 8 !== strlen( $token ) ) {
			$token = substr( hash( 'sha256', $uuid . microtime( true ) ), 0, 8 );
		}
		return gmdate( 'Ymd-His' ) . '-' . $token;
	}

	private static function campaign_name(
		string $organisation,
		string $language_name,
		string $generation_id
	): string {
		$suffix = ' | ' . sanitize_key( $generation_id );
		$base   = trim( $organisation . ' | ' . $language_name . ' | Search' );
		$limit  = max( 1, 128 - self::length( $suffix ) );

		return self::clip( $base, $limit ) . $suffix;
	}

	/** @return array<string,mixed>|\WP_Error */
	private static function build_plan( array $settings, string $generation_id ) {
		$landings = self::selected_landing_pages( $settings );
		if ( empty( $landings ) ) {
			return new \WP_Error( 'no_landings', __( 'No suitable published landing pages were found.', 'koz-google-ads-campaign-builder' ) );
		}
		$source      = (string) self::detected_site_language()['slug'];
		$campaigns   = array();
		$warnings    = array();
		foreach ( self::campaign_languages( $settings ) as $language ) {
			$locations = self::geo_targets_for_language( $language, $settings );
			if ( empty( $locations ) ) {
				/* translators: %s: campaign language name. */
				$warnings[] = sprintf( __( '%s: no target locations are configured.', 'koz-google-ads-campaign-builder' ), $language );
				continue;
			}
			$copy     = self::language_copy( $landings, $language, $source, $settings );
			$warnings = array_merge( $warnings, $copy['warnings'] );
			$language_info = self::$languages[ $language ];
			$campaign = array(
				'name'                  => self::campaign_name( KOZGADS_Copy::brand( (string) $settings['organisation_name'] ), (string) $language_info['name'], $generation_id ),
				'language'              => $language,
				'language_name'         => $language_info['name'],
				'ads_language_code'     => $language_info['ads_code'],
				'locations'             => $locations,
				'daily_budget'          => 0,
				'status'                => 'Paused',
				'ad_groups'             => array(),
				'sitelinks'             => array(),
				'callouts'              => array(),
				'negative_keywords'     => array(),
			);
			$callouts = array();
			foreach ( $copy['groups'] as $group_key => $group ) {
				if ( empty( $group['keywords'] ) || count( $group['headlines'] ) < 3 || count( $group['descriptions'] ) < 2 ) {
					/* translators: 1: campaign language name, 2: ad group name. */
					$warnings[] = sprintf( __( '%1$s / %2$s was skipped because it did not contain enough valid ad copy.', 'koz-google-ads-campaign-builder' ), $language, (string) $group['name'] );
					continue;
				}
				$campaign['ad_groups'][] = array(
					'name'               => self::clip( (string) $group['name'], 255 ),
					'final_url'          => (string) $group['final_url'],
					'keywords'           => $group['keywords'],
					'headlines'          => $group['headlines'],
					'descriptions'       => $group['descriptions'],
					'path_1'             => self::display_path( (string) $group['name'] ),
					'path_2'             => '',
					'low_volume_removed' => $group['low_volume_removed'],
					'planner_added'      => $group['planner_added'],
				);
				if ( '' !== $group['sitelink'] && count( $campaign['sitelinks'] ) < 6 ) {
					$campaign['sitelinks'][] = array( 'text' => $group['sitelink'], 'final_url' => (string) $group['final_url'] );
				}
				foreach ( $group['h2'] as $h2 ) {
					$callout = KOZGADS_Copy::fit( (string) $h2, 25 );
					// Whole headings only, in the campaign language.
					if ( '' !== $callout && KOZGADS_Copy::length( $callout ) === KOZGADS_Copy::length( KOZGADS_Copy::fit( (string) $h2, 200 ) ) && ! KOZGADS_Copy::script_mismatch( $callout, $language, 3, 0.5 ) ) {
						$callouts[ KOZGADS_Copy::lower( $callout ) ] = $callout;
					}
				}
			}
			foreach ( (array) $settings['callouts'] as $callout ) {
				$callouts[ KOZGADS_Copy::lower( (string) $callout ) ] = (string) $callout;
			}
			$sitelink_texts = array_map( static fn( array $link ): string => KOZGADS_Copy::lower( (string) $link['text'] ), $campaign['sitelinks'] );
			$campaign['callouts']          = array_slice( array_values( array_diff_key( $callouts, array_flip( $sitelink_texts ) ) ), 0, 8 );
			$campaign['negative_keywords'] = (array) $settings['negative_keywords'];
			if ( empty( $campaign['ad_groups'] ) ) {
				/* translators: %s: campaign language name. */
				$warnings[] = sprintf( __( '%s: no complete ad groups were generated.', 'koz-google-ads-campaign-builder' ), $language );
				continue;
			}
			$campaigns[] = $campaign;
		}
		if ( empty( $campaigns ) ) {
			return new \WP_Error( 'no_campaigns', __( 'No campaign could be generated. Check the selected languages, target locations and translation settings.', 'koz-google-ads-campaign-builder' ), array( 'warnings' => $warnings ) );
		}
		$daily_budget = round( ( (float) $settings['monthly_grant'] / 30.4 ) / count( $campaigns ), 2 );
		foreach ( $campaigns as &$campaign ) {
			$campaign['daily_budget'] = max( 1, $daily_budget );
		}
		unset( $campaign );
		return array(
			'account_mode'            => self::account_mode( $settings ),
			'bid_strategy'            => self::effective_bid_strategy( $settings ),
			'conversion_confirmed'    => ! empty( $settings['conversion_tracking_confirmed'] ),
			'source_language'         => $source,
			'campaigns'               => $campaigns,
			'warnings'                => $warnings,
			'estimated_monthly_budget'=> (float) $settings['monthly_grant'],
			'estimated_daily_budget'  => array_sum( array_column( $campaigns, 'daily_budget' ) ),
			'landing_pages'           => $landings,
		);
	}

	/** Display path from the first words of the ad group name (letters and digits, up to 15 characters). */
	private static function display_path( string $name ): string {
		foreach ( array( 2, 1 ) as $count ) {
			$path = trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', '-', KOZGADS_Copy::lower( self::first_words( $name, $count ) ) ), '-' );
			if ( '' !== $path && KOZGADS_Copy::length( $path ) <= 15 ) {
				return $path;
			}
		}
		return '';
	}

	private static function valid_keyword( string $keyword, string $mode ): bool {
		$keyword = trim( $keyword );
		if ( '' === $keyword ) {
			return false;
		}
		if ( 'standard_ads' === $mode ) {
			return true;
		}
		$words = preg_split( '/\s+/u', $keyword, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $words ) && count( $words ) >= 2;
	}

	/** @return array<string,mixed> */
	private static function readiness_report( array $plan, array $settings ): array {
		$issues   = array();
		$warnings = (array) $plan['warnings'];
		$landing_preflight = self::landing_preflight( $settings, (array) ( $plan['landing_pages'] ?? array() ) );
		if ( empty( $landing_preflight['ready'] ) ) {
			$issues[] = __( 'Landing-page preflight found one or more blocking issues.', 'koz-google-ads-campaign-builder' );
		}
		foreach ( (array) ( $landing_preflight['warnings'] ?? array() ) as $landing_warning ) {
			$warnings[] = (string) $landing_warning;
		}
		if ( 'ad_grants' === self::account_mode( $settings ) && empty( $settings['conversion_tracking_confirmed'] ) ) {
			$issues[] = __( 'No real conversion is recorded in Google Ads yet. Set it up with CONVERSION-SETUP.txt before launching.', 'koz-google-ads-campaign-builder' );
		} elseif ( empty( $settings['conversion_tracking_confirmed'] ) ) {
			$warnings[] = __( 'Conversion tracking has not been confirmed.', 'koz-google-ads-campaign-builder' );
		}
		if ( self::effective_bid_strategy( $settings ) !== (string) $settings['bid_strategy'] && 'auto' !== (string) $settings['bid_strategy'] ) {
			$warnings[] = __( 'Maximize conversions was replaced by Maximize clicks because no real conversion is recorded yet.', 'koz-google-ads-campaign-builder' );
		}
		$validation = KOZGADS_Export::validate( $plan );
		$issues     = array_merge( $issues, $validation['issues'] );
		$warnings   = array_merge( $warnings, $validation['warnings'] );
		foreach ( $plan['campaigns'] as $campaign ) {
			if ( empty( $campaign['callouts'] ) ) {
				$warnings[] = sprintf(
					/* translators: %s: campaign name. */
					__( '%s: no callouts. Add callouts in the settings.', 'koz-google-ads-campaign-builder' ),
					(string) $campaign['name']
				);
			}
			if ( 'Paused' !== (string) $campaign['status'] ) {
				/* translators: %s: generated campaign name. */
				$issues[] = sprintf( __( 'Campaign %s is not paused.', 'koz-google-ads-campaign-builder' ), (string) $campaign['name'] );
			}
			if ( empty( $campaign['locations'] ) ) {
				/* translators: %s: generated campaign name. */
				$issues[] = sprintf( __( 'Campaign %s has no target location.', 'koz-google-ads-campaign-builder' ), (string) $campaign['name'] );
			}
		}
		return array(
			'launch_ready' => empty( $issues ),
			'issues'       => array_values( array_unique( $issues ) ),
			'warnings'     => array_values( array_unique( $warnings ) ),
			'mode'         => self::account_mode( $settings ),
			'checked_at'   => gmdate( 'c' ),
			'landing_preflight' => $landing_preflight,
			'note'         => __( 'This is a technical preflight report, not a guarantee of Google policy approval or campaign performance.', 'koz-google-ads-campaign-builder' ),
		);
	}

	public static function generate_package(): void {
		self::require_admin();
		check_admin_referer( self::ACTION_GENERATE );
		if ( ! class_exists( 'ZipArchive' ) ) {
			self::redirect_notice( 'error', __( 'The PHP ZipArchive extension is unavailable.', 'koz-google-ads-campaign-builder' ) );
		}
		$settings      = self::settings();
		$generation_id = self::generation_id();
		$plan          = self::build_plan( $settings, $generation_id );
		if ( is_wp_error( $plan ) ) {
			self::redirect_notice( 'error', $plan->get_error_message() );
		}
		$readiness = self::readiness_report( $plan, $settings );
		$files     = KOZGADS_Export::editor_files( $plan, $settings );
		$dir       = self::package_dir();
		if ( is_wp_error( $dir ) ) {
			self::redirect_notice( 'error', $dir->get_error_message() );
		}
		self::remove_old_packages( $dir );
		$prefix   = 'ad_grants' === self::account_mode( $settings ) ? 'koz-ad-grants-' : 'koz-google-ads-';
		$filename = $prefix . $generation_id . '-' . strtolower( wp_generate_password( 10, false, false ) ) . '.zip';
		$path     = trailingslashit( $dir ) . $filename;
		$zip      = new \ZipArchive();
		if ( true !== $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			self::redirect_notice( 'error', __( 'The ZIP package could not be created.', 'koz-google-ads-campaign-builder' ) );
		}
		foreach ( $files as $name => $file ) {
			$zip->addFromString( $name, KOZGADS_Export::csv_content( $file['headers'], $file['rows'] ) );
		}
		$manifest = array(
			'generator' => array( 'name' => 'KOZ Google Ads Campaign Builder', 'version' => KOZGADS_VERSION, 'generated_at_utc' => gmdate( 'c' ), 'package_id' => $generation_id ),
			'account_mode' => self::account_mode( $settings ),
			'language_mode' => (string) $settings['language_mode'],
			'location_mode' => (string) $settings['location_mode'],
			'site_language' => (string) self::detected_site_language()['slug'],
			'site_country' => (string) self::detected_site_country()['code'],
			'organisation' => (string) $settings['organisation_name'],
			'domain' => home_url( '/' ),
			'campaigns' => count( $plan['campaigns'] ),
			'ad_groups' => array_sum( array_map( static fn( array $campaign ): int => count( $campaign['ad_groups'] ), $plan['campaigns'] ) ),
			'locations' => array_sum( array_map( static fn( array $campaign ): int => count( $campaign['locations'] ), $plan['campaigns'] ) ),
			'positive_keywords' => array_sum(
				array_map(
					static fn( array $campaign ): int => array_sum(
						array_map(
							static fn( array $group ): int => count( $group['keywords'] ),
							$campaign['ad_groups']
						)
					),
					$plan['campaigns']
				)
			),
			'campaign_negative_keywords' => array_sum( array_map( static fn( array $campaign ): int => count( $campaign['negative_keywords'] ), $plan['campaigns'] ) ),
			'callouts' => array_sum( array_map( static fn( array $campaign ): int => count( $campaign['callouts'] ), $plan['campaigns'] ) ),
			'sitelinks' => array_sum( array_map( static fn( array $campaign ): int => count( $campaign['sitelinks'] ), $plan['campaigns'] ) ),
			'bid_strategy' => (string) $plan['bid_strategy'],
			'status' => 'Paused',
			'launch_ready' => (bool) $readiness['launch_ready'],
			'files' => array_merge( array_keys( $files ), array( 'campaign-plan.json', 'landing-page-inventory.json', 'readiness-report.json', 'conversion-plan.json', 'package-manifest.json', 'IMPORT-INSTRUCTIONS.txt', 'CONVERSION-SETUP.txt', 'POST-IMPORT-CHECKLIST.txt' ) ),
		);
		$conversion_plan = array(
			'primary_conversion_name' => (string) $settings['primary_conversion_name'],
			'confirmed' => ! empty( $settings['conversion_tracking_confirmed'] ),
			'counting' => 'One',
			'include_in_conversions' => true,
			'note' => __( 'Configure the actual conversion source in Google Ads or the connected analytics/tagging system. This package does not create tracking tags.', 'koz-google-ads-campaign-builder' ),
		);
		$instructions = implode(
			"\n",
			array(
				__( 'GOOGLE ADS EDITOR IMPORT PACKAGE', 'koz-google-ads-campaign-builder' ),
				'',
				/* translators: %s: selected Google Ads account mode label. */
				/* translators: %s: advertising account mode label. */
				sprintf( __( 'Mode: %s', 'koz-google-ads-campaign-builder' ), self::mode_label( $settings ) ),
				__( 'All campaigns are generated as Paused.', 'koz-google-ads-campaign-builder' ),
				'',
				__( 'Manager workflow:', 'koz-google-ads-campaign-builder' ),
				'1. Import only 00-IMPORT-ALL.csv.',
				'2. Review the proposed campaigns and keep the imported changes.',
				'3. Follow CONVERSION-SETUP.txt and POST-IMPORT-CHECKLIST.txt before enabling any campaign.',
				'',
				__( 'The numbered CSV files contain the same data split by entity and are only for diagnostics.', 'koz-google-ads-campaign-builder' ),
				__( 'Campaign negatives, locations and direct campaign-level callout assets are included in the single master import.', 'koz-google-ads-campaign-builder' ),
				__( 'Open package-manifest.json before import and compare its campaign_negative_keywords and callouts counts with the Editor result.', 'koz-google-ads-campaign-builder' ),
				'',
				__( 'Review imported changes, target locations, budgets, language targeting, landing URLs, ad copy and conversion tracking before posting anything.', 'koz-google-ads-campaign-builder' ),
				__( 'Google Ads Editor formats can change. Treat these CSV files as a reviewable starting point, not an automatic launch instruction.', 'koz-google-ads-campaign-builder' ),
			)
		);
		$zip->addFromString( 'campaign-plan.json', wp_json_encode( $plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		$zip->addFromString( 'landing-page-inventory.json', wp_json_encode( $plan['landing_pages'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		$zip->addFromString( 'readiness-report.json', wp_json_encode( $readiness, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		$zip->addFromString( 'conversion-plan.json', wp_json_encode( $conversion_plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		$zip->addFromString( 'package-manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		$zip->addFromString( 'IMPORT-INSTRUCTIONS.txt', $instructions );
		$zip->addFromString( 'CONVERSION-SETUP.txt', KOZGADS_Export::conversion_guide( KOZGADS_Integrations::donate_stats(), $settings ) );
		$zip->addFromString( 'POST-IMPORT-CHECKLIST.txt', KOZGADS_Export::checklist( $plan ) );
		$zip->close();

		$package = array(
			'package_id'         => $generation_id,
			'account_mode'       => self::account_mode( $settings ),
			'account_mode_label' => self::mode_label( $settings ),
			'filename'           => $filename,
			'path'               => $path,
			'created_at'         => current_time( 'c' ),
			'size'               => is_file( $path ) ? filesize( $path ) : 0,
			'sha256'             => is_file( $path ) ? hash_file( 'sha256', $path ) : '',
			'campaigns'          => count( $plan['campaigns'] ),
			'ad_groups'          => $manifest['ad_groups'],
			'launch_ready'       => (bool) $readiness['launch_ready'],
			'warnings'           => count( $readiness['warnings'] ),
			'issues'             => count( $readiness['issues'] ),
			'issue_list'         => array_slice( (array) $readiness['issues'], 0, 20 ),
			'warning_list'       => array_slice( (array) $readiness['warnings'], 0, 30 ),
			'bid_strategy'       => (string) $plan['bid_strategy'],
			'preview'            => self::preview( $plan ),
		);
		update_option( self::PACKAGE_OPTION, $package, false );
		self::redirect_notice( 'package_ready' );
	}

	/**
	 * Return an initialized WordPress filesystem instance.
	 *
	 * @return \WP_Filesystem_Base|\WP_Error
	 */
	/** First ad of every ad group, for the preview in the admin. */
	private static function preview( array $plan ): array {
		$out = array();
		foreach ( $plan['campaigns'] as $campaign ) {
			foreach ( $campaign['ad_groups'] as $group ) {
				$out[] = array(
					'campaign'     => (string) $campaign['name'],
					'group'        => (string) $group['name'],
					'url'          => (string) $group['final_url'],
					'headlines'    => array_slice( $group['headlines'], 0, 15 ),
					'descriptions' => array_slice( $group['descriptions'], 0, 4 ),
					'keywords'     => array_slice( $group['keywords'], 0, 15 ),
					'removed'      => array_slice( (array) $group['low_volume_removed'], 0, 15 ),
					'added'        => array_slice( (array) $group['planner_added'], 0, 5 ),
				);
				if ( count( $out ) >= 24 ) {
					return $out;
				}
			}
		}
		return $out;
	}

	public static function filesystem() {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! WP_Filesystem() || ! is_object( $wp_filesystem ) ) {
			return new \WP_Error(
				'filesystem_unavailable',
				__( 'The private package directory could not be created.', 'koz-google-ads-campaign-builder' )
			);
		}
		return $wp_filesystem;
	}

	/** @return string|\WP_Error */
	private static function package_dir() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error( 'upload_error', (string) $upload['error'] );
		}
		$dir = trailingslashit( (string) $upload['basedir'] ) . self::PACKAGE_DIR;
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'directory_error', __( 'The private package directory could not be created.', 'koz-google-ads-campaign-builder' ) );
		}
		$filesystem = self::filesystem();
		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}
		$index_path = trailingslashit( $dir ) . 'index.php';
		if ( ! $filesystem->exists( $index_path ) ) {
			$filesystem->put_contents( $index_path, "<?php\n// Silence is golden.\n", FS_CHMOD_FILE );
		}
		$htaccess_path = trailingslashit( $dir ) . '.htaccess';
		if ( ! $filesystem->exists( $htaccess_path ) ) {
			$filesystem->put_contents( $htaccess_path, "Deny from all\n", FS_CHMOD_FILE );
		}
		return $dir;
	}

	private static function remove_old_packages( string $dir ): void {
		$files = glob( trailingslashit( $dir ) . 'koz-*.zip' );
		if ( ! is_array( $files ) ) {
			return;
		}
		$threshold = time() - ( 7 * DAY_IN_SECONDS );
		foreach ( $files as $file ) {
			if ( is_file( $file ) && filemtime( $file ) < $threshold ) {
				wp_delete_file( $file );
			}
		}
	}

	public static function download_package(): void {
		self::require_admin();
		check_admin_referer( self::ACTION_DOWNLOAD );
		$package = get_option( self::PACKAGE_OPTION, array() );
		$path    = is_array( $package ) ? (string) ( $package['path'] ?? '' ) : '';
		if ( ! self::package_path_allowed( $path ) ) {
			wp_die( esc_html__( 'The generated package is unavailable.', 'koz-google-ads-campaign-builder' ) );
		}
		$filesystem = self::filesystem();
		if ( is_wp_error( $filesystem ) ) {
			wp_die( esc_html( $filesystem->get_error_message() ) );
		}
		$content = $filesystem->get_contents( $path );
		if ( false === $content ) {
			wp_die( esc_html__( 'The generated package is unavailable.', 'koz-google-ads-campaign-builder' ) );
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( basename( $path ) ) . '"' );
		header( 'Content-Length: ' . (string) strlen( $content ) );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated ZIP download bytes.
		exit;
	}

	public static function delete_package(): void {
		self::require_admin();
		check_admin_referer( self::ACTION_DELETE );
		$package = get_option( self::PACKAGE_OPTION, array() );
		$path    = is_array( $package ) ? (string) ( $package['path'] ?? '' ) : '';
		if ( self::package_path_allowed( $path ) ) {
			wp_delete_file( $path );
		}
		delete_option( self::PACKAGE_OPTION );
		self::redirect_notice( 'package_deleted' );
	}

	/** The package lives in the current or the pre-1.5.0 package folder. */
	private static function package_path_allowed( string $path ): bool {
		$upload = wp_upload_dir();
		if ( '' === $path || ! empty( $upload['error'] ) || ! is_file( $path ) ) {
			return false;
		}
		foreach ( array( self::PACKAGE_DIR, self::LEGACY_PACKAGE_DIR ) as $folder ) {
			if ( self::path_is_inside( $path, trailingslashit( (string) $upload['basedir'] ) . $folder ) ) {
				return true;
			}
		}
		return false;
	}

	private static function path_is_inside( string $path, string $directory ): bool {
		$real_path = realpath( $path );
		$real_dir  = realpath( $directory );
		return is_string( $real_path ) && is_string( $real_dir ) && str_starts_with( $real_path, trailingslashit( $real_dir ) );
	}

	private static function normalise_text( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = (string) preg_replace( '/\s+/u', ' ', $text );
		return trim( $text );
	}

	private static function lower( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	private static function length( string $text ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $text, 'UTF-8' );
		}
		if ( function_exists( 'iconv_strlen' ) ) {
			$length = iconv_strlen( $text, 'UTF-8' );
			if ( false !== $length ) { return $length; }
		}
		return preg_match_all( '/./us', $text, $matches ) ?: 0;
	}

	private static function unicode_substr( string $text, int $start, int $length ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $text, $start, $length, 'UTF-8' );
		}
		if ( function_exists( 'iconv_substr' ) ) {
			$value = iconv_substr( $text, $start, $length, 'UTF-8' );
			if ( false !== $value ) { return (string) $value; }
		}
		$characters = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $characters ) ? implode( '', array_slice( $characters, $start, $length ) ) : substr( $text, $start, $length );
	}

	private static function clip( string $text, int $limit ): string {
		$text = self::normalise_text( $text );
		if ( self::length( $text ) <= $limit ) {
			return $text;
		}
		$cut = self::unicode_substr( $text, 0, $limit );
		$word_cut = (string) preg_replace( '/\s+\S*$/u', '', $cut );
		return '' !== trim( $word_cut ) ? trim( $word_cut ) : trim( $cut );
	}

	private static function admin_action_url( string $action ): string {
		return add_query_arg( 'action', $action, admin_url( 'admin-post.php' ) );
	}


	private static function ui( string $uk, string $en ): string {
		$translated = KOZGADS_Runtime_I18n::gettext(
			$en,
			$en,
			'koz-google-ads-campaign-builder'
		);
		if ( $translated !== $en ) {
			return $translated;
		}

		return 'uk' === KOZGADS_Runtime_I18n::language() ? $uk : $en;
	}

	/**
	 * Pure manager-facing readiness interpretation.
	 *
	 * @param array<string,mixed> $settings
	 * @param array<string,array<string,mixed>> $landings
	 * @param array<string,mixed> $azure
	 * @param array<string,mixed> $package
	 * @return array<string,mixed>
	 */
	public static function manager_summary_from(
		array $settings,
		array $landings,
		array $azure,
		array $package,
		string $source_slug
	): array {
		$mode = isset( $settings['account_mode'] ) && 'standard_ads' === $settings['account_mode']
			? 'standard_ads'
			: 'ad_grants';
		$languages = self::campaign_languages( $settings );
		$location_count = self::automatic_location_count( $settings );
		$landing_count = count( $landings );
		$needs_translation = false;
		foreach ( $languages as $language ) {
			if ( $language !== $source_slug ) {
				$needs_translation = true;
				break;
			}
		}
		$published = KOZGADS_Integrations::translated_languages();
		$missing   = array_values( array_filter( $languages, static fn( string $language ): bool => $language !== $source_slug && ! in_array( $language, $published, true ) ) );
		$translation_ready = empty( $missing )
			|| ( ! empty( $settings['allow_azure_copy_translation'] ) && ! empty( $azure['configured'] ) );
		$conversion_confirmed = ! empty( $settings['conversion_tracking_confirmed'] );
		$package_available = ! empty( $package['path'] );
		$generation_ready = $landing_count > 0 && $location_count > 0 && $translation_ready;
		$launch_ready = $generation_ready && ( 'standard_ads' === $mode || $conversion_confirmed );

		$healthy = array();
		$attention = array();
		$actions = array();

		if ( $landing_count > 0 ) {
			$healthy[] = array(
				'title' => self::ui( 'Сторінки для реклами знайдено', 'Landing pages found' ),
				'message' => sprintf(
					self::ui( 'Плагін вибрав %d сторінку(и) з опублікованого контенту.', 'The plugin selected %d published landing page(s).' ),
					$landing_count
				),
			);
		} else {
			$attention[] = array(
				'level' => 'critical',
				'title' => self::ui( 'Немає сторінок для кампанії', 'No landing pages available' ),
				'message' => self::ui( 'Потрібна хоча б одна опублікована змістовна сторінка.', 'At least one meaningful published page is required.' ),
			);
			$actions[] = array(
				'level' => 'critical',
				'title' => self::ui( 'Опублікувати або доповнити сторінку', 'Publish or improve a page' ),
				'description' => self::ui( 'Після цього оновити огляд плагіна.', 'Then refresh the plugin overview.' ),
			);
		}

		$healthy[] = array(
			'title' => self::ui( 'Кампанії залишаються вимкненими', 'Campaigns remain paused' ),
			'message' => self::ui( 'Плагін створює лише ZIP для перевірки та ручного імпорту.', 'The plugin creates only a review ZIP for manual import.' ),
		);

		if ( $location_count <= 0 ) {
			$attention[] = array(
				'level' => 'warning',
				'title' => self::ui( 'Не вдалося визначити географію', 'Target geography could not be determined' ),
				'message' => self::ui( 'Автоматична географія порожня, а ручний override не задано.', 'Automatic geography is empty and no manual override is set.' ),
			);
			$actions[] = array(
				'level' => 'warning',
				'title' => self::ui( 'Перевірити мову й країну сайту', 'Check site language and country' ),
				'description' => self::ui( 'Плагін використовує WordPress locale; ручний override доступний у налаштуваннях.', 'The plugin uses the WordPress locale; a manual override is available in Settings.' ),
			);
		} else {
			$healthy[] = array(
				'title' => self::ui( 'Географію визначено', 'Target geography determined' ),
				'message' => sprintf(
					self::ui( 'Плагін сформував %d мовно-географічних цілей.', 'The plugin created %d language-location targets.' ),
					$location_count
				),
			);
		}

		if ( ! $translation_ready ) {
			$attention[] = array(
				'level' => 'warning',
				'title' => self::ui( 'Переклад рекламних текстів не готовий', 'Ad-copy translation is not ready' ),
				'message' => sprintf( self::ui( 'На сайті немає перекладених сторінок для: %s, а переклад Azure вимкнено.', 'The site has no translated pages for: %s, and Azure translation is off.' ), implode( ', ', $missing ) ),
			);
			$actions[] = array(
				'level' => 'warning',
				'title' => self::ui( 'Залишити одну мову або дозволити переклад', 'Use one language or enable translation' ),
				'description' => self::ui( 'Змінити це у налаштуваннях пакета.', 'Change this in package settings.' ),
			);
		}

		if ( ! $conversion_confirmed ) {
			$donate = KOZGADS_Integrations::donate_stats();
			$attention[] = array(
				'level' => 'ad_grants' === $mode ? 'critical' : 'warning',
				'title' => self::ui( 'Немає реальної конверсії в Google Ads', 'No real conversion in Google Ads' ),
				'message' => self::ui( 'Без конверсії стратегія «Максимум конверсій» не має сигналу й кампанії майже не показуються. Пакет використає «Максимум кліків».', 'Without a conversion, Maximize conversions has no signal and campaigns barely serve. The package uses Maximize clicks.' ),
			);
			$actions[] = array(
				'level' => 'warning',
				'title' => self::ui( 'Налаштувати конверсію донату', 'Set up the donation conversion' ),
				'description' => $donate['data_layer'] && '' !== $donate['event']
					? sprintf( self::ui( 'Подія Donate Stats «%s» → тригер GTM → тег конверсії Google Ads, зробити її основною. Покроково — у CONVERSION-SETUP.txt у пакеті.', 'Donate Stats event "%s" → GTM trigger → Google Ads conversion tag, make it primary. Step by step in CONVERSION-SETUP.txt in the package.' ), $donate['event'] )
					: self::ui( 'Увімкніть подію dataLayer у KOZ Donate Stats або використайте власну подію донату. Покроково — у CONVERSION-SETUP.txt.', 'Turn on the dataLayer event in KOZ Donate Stats or use your own donation event. Step by step in CONVERSION-SETUP.txt.' ),
			);
		}
		$report = KOZGADS_Report::summary();
		if ( $report['total'] > 0 && $report['low'] > 0 ) {
			$attention[] = array(
				'level' => $report['low'] * 2 > $report['total'] ? 'critical' : 'warning',
				'title' => self::ui( 'Ключові слова без попиту', 'Keywords without search demand' ),
				'message' => sprintf( self::ui( 'У завантаженому звіті %1$d з %2$d ключових слів мають низький обсяг пошуку. Новий пакет їх не міститиме.', '%1$d of %2$d keywords in the imported report have low search volume. A new package leaves them out.' ), $report['low'], $report['total'] ),
			);
		} elseif ( 0 === $report['total'] ) {
			$actions[] = array(
				'level' => 'success',
				'title' => self::ui( 'Завантажити звіт ключових слів', 'Import a keyword report' ),
				'description' => self::ui( 'Експорт із Google Ads або Keyword Planner допоможе відсіяти слова з низьким обсягом пошуку.', 'An export from Google Ads or Keyword Planner filters out low-search-volume keywords.' ),
			);
		}

		if ( ! $package_available && $generation_ready ) {
			$actions[] = array(
				'level' => 'success',
				'title' => self::ui( 'Створити перший пакет', 'Generate the first package' ),
				'description' => self::ui( 'ZIP залишиться локальним і всі кампанії будуть Paused.', 'The ZIP stays local and every campaign remains Paused.' ),
			);
		}

		if ( empty( $actions ) ) {
			$actions[] = array(
				'level' => 'success',
				'title' => self::ui( 'Завантажити та перевірити пакет', 'Download and review the package' ),
				'description' => self::ui( 'Перед імпортом перевірити тексти, URL, бюджет і географію.', 'Review copy, URLs, budget and geography before import.' ),
			);
		}

		if ( ! $generation_ready ) {
			$overall = 'attention';
			$headline = self::ui( 'Потрібно завершити налаштування', 'Setup is incomplete' );
		} elseif ( ! $launch_ready ) {
			$overall = 'attention';
			$headline = self::ui( 'Пакет можна створити, запуск ще не готовий', 'Package generation is ready, launch is not' );
		} elseif ( $package_available ) {
			$overall = 'good';
			$headline = self::ui( 'Пакет створено і готовий до перевірки', 'Package is ready for review' );
		} else {
			$overall = 'good';
			$headline = self::ui( 'Можна створювати пакет кампаній', 'Campaign package can be generated' );
		}

		return array(
			'overall' => $overall,
			'headline' => $headline,
			'account_mode' => $mode,
			'mode_label' => 'ad_grants' === $mode ? 'Google Ad Grants' : 'Standard Google Ads',
			'landing_count' => $landing_count,
			'language_count' => count( $languages ),
			'location_count' => $location_count,
			'translation_ready' => $translation_ready,
			'conversion_confirmed' => $conversion_confirmed,
			'generation_ready' => $generation_ready,
			'launch_ready' => $launch_ready,
			'package_available' => $package_available,
			'healthy' => $healthy,
			'attention' => $attention,
			'actions' => $actions,
		);
	}

	/** @return array<string,mixed> */
	public static function manager_summary(): array {
		$settings = self::settings();
		$package = get_option( self::PACKAGE_OPTION, array() );
		return self::manager_summary_from(
			$settings,
			self::selected_landing_pages( $settings ),
			KOZGADS_Integrations::azure_credentials(),
			is_array( $package ) ? $package : array(),
			(string) self::detected_site_language()['slug']
		);
	}

	private static function manager_items( string $title, array $items, string $empty = '' ): void {
		?>
		<section class="kozgads-section">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( empty( $items ) ) : ?>
				<p class="kozgads-empty"><?php echo esc_html( $empty ); ?></p>
			<?php else : ?>
				<div class="kozgads-list">
					<?php foreach ( $items as $item ) : ?>
						<article class="kozgads-item is-<?php echo esc_attr( (string) ( $item['level'] ?? 'success' ) ); ?>">
							<h3><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></h3>
							<p><?php echo esc_html( (string) ( $item['message'] ?? $item['description'] ?? '' ) ); ?></p>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function render_overview_tab(
		array $settings,
		array $source,
		array $landings,
		array $azure,
		array $package
	): void {
		$summary = self::manager_summary_from(
			$settings,
			$landings,
			$azure,
			$package,
			(string) $source['slug']
		);
		?>
		<div class="kozgads-hero is-<?php echo esc_attr( (string) $summary['overall'] ); ?>">
			<span><?php echo esc_html( self::ui( 'Стан рекламного пакета', 'Campaign package status' ) ); ?></span>
			<strong><?php echo esc_html( (string) $summary['headline'] ); ?></strong>
			<p><?php echo esc_html( self::ui( 'Плагін нічого не публікує у Google. Він готує локальний ZIP для перевірки.', 'The plugin publishes nothing to Google. It prepares a local ZIP for review.' ) ); ?></p>
		</div>

		<div class="kozgads-cards">
			<div><span><?php echo esc_html( self::ui( 'Режим', 'Mode' ) ); ?></span><strong><?php echo esc_html( (string) $summary['mode_label'] ); ?></strong></div>
			<div><span><?php echo esc_html( self::ui( 'Сторінки', 'Landing pages' ) ); ?></span><strong><?php echo esc_html( (string) $summary['landing_count'] ); ?></strong></div>
			<div><span><?php echo esc_html( self::ui( 'Мови', 'Languages' ) ); ?></span><strong><?php echo esc_html( (string) $summary['language_count'] ); ?></strong></div>
			<div><span><?php echo esc_html( self::ui( 'Локації', 'Locations' ) ); ?></span><strong><?php echo esc_html( (string) $summary['location_count'] ); ?></strong></div>
			<div><span><?php echo esc_html( self::ui( 'Конверсія', 'Conversion' ) ); ?></span><strong><?php echo esc_html( ! empty( $summary['conversion_confirmed'] ) ? self::ui( 'Підтверджена', 'Confirmed' ) : self::ui( 'Не підтверджена', 'Not confirmed' ) ); ?></strong></div>
			<div><span><?php echo esc_html( self::ui( 'Останній ZIP', 'Last ZIP' ) ); ?></span><strong><?php echo esc_html( ! empty( $summary['package_available'] ) ? self::ui( 'Є', 'Available' ) : self::ui( 'Немає', 'None' ) ); ?></strong></div>
		</div>

		<?php self::manager_items(
			self::ui( 'Все добре', 'Ready' ),
			(array) $summary['healthy'],
			self::ui( 'Поки немає підтверджених готових частин.', 'No ready items yet.' )
		); ?>
		<?php self::manager_items(
			self::ui( 'Потрібна увага', 'Needs attention' ),
			(array) $summary['attention'],
			self::ui( 'Нічого не потребує уваги.', 'Nothing needs attention.' )
		); ?>
		<?php self::manager_items(
			self::ui( 'Що зробити', 'Next actions' ),
			(array) $summary['actions']
		); ?>

		<?php $landing_preflight = self::landing_preflight( $settings, $landings ); ?>
		<section class="kozgads-generate">
			<h2><?php echo esc_html( self::ui( 'Перевірка цільових сторінок', 'Landing-page preflight' ) ); ?></h2>
			<p><strong><?php echo esc_html( ! empty( $landing_preflight['ready'] ) ? self::ui( 'PASS — базові URL і контент придатні для пакета.', 'PASS — basic URLs and local content are suitable for the package.' ) : self::ui( 'Потрібна увага — знайдено блокуючі проблеми.', 'Needs attention — blocking issues were found.' ) ); ?></strong></p>
			<p><?php echo esc_html( sprintf( self::ui( 'Сторінок: %1$d; згенерованих URL: %2$d; проблем: %3$d; попереджень: %4$d.', 'Pages: %1$d; generated URLs: %2$d; issues: %3$d; warnings: %4$d.' ), (int) $landing_preflight['page_count'], (int) $landing_preflight['generated_url_count'], (int) $landing_preflight['issue_count'], (int) $landing_preflight['warning_count'] ) ); ?></p>
			<p class="description"><?php echo esc_html( self::ui( 'Перевірка не робить зовнішніх HTTP-запитів і не підтверджує browser runtime, Google policy або фактичну доступність перекладених маршрутів.', 'The preflight makes no external HTTP requests and does not verify browser runtime, Google policy, or actual translated-route availability.' ) ); ?></p>
			<?php if ( ! empty( $landing_preflight['issues'] ) ) : ?>
				<ul class="ul-disc"><?php foreach ( (array) $landing_preflight['issues'] as $preflight_issue ) : ?><li><?php echo esc_html( (string) $preflight_issue ); ?></li><?php endforeach; ?></ul>
			<?php endif; ?>
		</section>


		<?php self::render_conversion_section( $settings ); ?>
		<?php self::render_report_section(); ?>

		<?php $live_destination_check = get_option( KOZGADS_Checks::OPTION, array() ); ?>
		<section class="kozgads-generate">
			<h2><?php echo esc_html( self::ui( 'Жива перевірка Google AdsBot', 'Live Google AdsBot destination check' ) ); ?></h2>
			<p class="description"><?php echo esc_html( self::ui( 'Робить лише запити до свого сайту з user agent AdsBot-Google (комп’ютер і мобільний) та мовою кампанії. Перевіряє HTTP і таймаут, robots.txt, noindex і мову сторінки.', 'Makes requests to this site only, with the AdsBot-Google user agent (desktop and mobile) and the campaign language. Checks HTTP and timeouts, robots.txt, noindex and the page language.' ) ); ?></p>
			<?php if ( is_array( $live_destination_check ) && ! empty( $live_destination_check['checked_at'] ) ) : ?>
				<p><strong><?php echo esc_html( ! empty( $live_destination_check['ready'] ) ? self::ui( 'PASS — destination доступні для AdsBot-перевірки.', 'PASS — destinations are reachable in the AdsBot check.' ) : self::ui( 'FAIL — є недоступні destination.', 'FAIL — one or more destinations are not reachable.' ) ); ?></strong></p>
				<p><?php echo esc_html( sprintf( self::ui( 'URL: %1$d; проблем: %2$d; попереджень: %3$d.', 'URLs: %1$d; issues: %2$d; warnings: %3$d.' ), (int) ( $live_destination_check['target_count'] ?? 0 ), (int) ( $live_destination_check['issue_count'] ?? 0 ), (int) ( $live_destination_check['warning_count'] ?? 0 ) ) ); ?></p>
				<?php if ( ! empty( $live_destination_check['issues'] ) ) : ?><ul class="ul-disc"><?php foreach ( (array) $live_destination_check['issues'] as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?></ul><?php endif; ?>
				<?php if ( ! empty( $live_destination_check['warnings'] ) ) : ?><ul class="ul-disc"><?php foreach ( (array) $live_destination_check['warnings'] as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?></ul><?php endif; ?>
				<table class="widefat striped"><thead><tr><th><?php echo esc_html( self::ui( 'Сторінка', 'Page' ) ); ?></th><th><?php echo esc_html( self::ui( 'Мова', 'Language' ) ); ?></th><th><?php echo esc_html( self::ui( 'Пристрій', 'Device' ) ); ?></th><th>HTTP</th><th><?php echo esc_html( self::ui( 'Час', 'Time' ) ); ?></th><th>HTML lang</th><th>URL</th></tr></thead><tbody>
				<?php foreach ( (array) ( $live_destination_check['rows'] ?? array() ) as $row ) : ?>
				<tr><td><?php echo esc_html( (string) ( $row['title'] ?? '' ) ); ?></td><td><?php echo esc_html( strtoupper( (string) ( $row['language'] ?? '' ) ) ); ?></td><td><?php echo esc_html( (string) ( $row['device'] ?? 'desktop' ) ); ?></td><td><?php echo esc_html( (string) ( $row['http_code'] ?? 0 ) ); ?><?php if ( ! empty( $row['error'] ) ) : ?><br><small><?php echo esc_html( (string) $row['error'] ); ?></small><?php endif; ?></td><td><?php echo esc_html( (string) ( $row['elapsed_ms'] ?? 0 ) ); ?> ms</td><td><?php echo esc_html( (string) ( $row['html_lang'] ?? '' ) ); ?></td><td><code><?php echo esc_html( (string) ( $row['url'] ?? '' ) ); ?></code></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( self::admin_action_url( KOZGADS_Checks::ACTION ) ); ?>">
				<?php wp_nonce_field( KOZGADS_Checks::ACTION ); ?>
				<p><button type="submit" class="button button-secondary"><?php echo esc_html( self::ui( 'Запустити живу перевірку AdsBot', 'Run live AdsBot destination check' ) ); ?></button></p>
			</form>
		</section>

		<section class="kozgads-generate">
			<h2><?php echo esc_html( self::ui( 'Створити пакет', 'Generate package' ) ); ?></h2>
			<p><?php echo esc_html( self::ui( 'Результат буде локальним ZIP. Кампанії всередині завжди мають статус Paused.', 'The result is a local ZIP. Campaigns inside always have Paused status.' ) ); ?></p>
			<form method="post" action="<?php echo esc_url( self::admin_action_url( self::ACTION_GENERATE ) ); ?>">
				<?php wp_nonce_field( self::ACTION_GENERATE ); ?>
				<?php submit_button(
					sprintf( self::ui( 'Створити пакет %s', 'Generate %s package' ), self::mode_label( $settings ) ),
					'primary',
					'submit',
					false
				); ?>
			</form>
		</section>

		<?php if ( is_array( $package ) && ! empty( $package['path'] ) && is_file( (string) $package['path'] ) ) : ?>
			<section class="kozgads-last-package">
				<h2><?php echo esc_html( self::ui( 'Останній пакет', 'Last package' ) ); ?></h2>
				<div class="kozgads-package-grid">
					<div><span><?php echo esc_html( self::ui( 'Створено', 'Created' ) ); ?></span><strong><?php echo esc_html( (string) $package['created_at'] ); ?></strong></div>
					<div><span><?php echo esc_html( self::ui( 'Кампанії', 'Campaigns' ) ); ?></span><strong><?php echo esc_html( (string) $package['campaigns'] ); ?></strong></div>
					<div><span><?php echo esc_html( self::ui( 'Групи оголошень', 'Ad groups' ) ); ?></span><strong><?php echo esc_html( (string) $package['ad_groups'] ); ?></strong></div>
					<div><span><?php echo esc_html( self::ui( 'Проблеми / попередження', 'Issues / warnings' ) ); ?></span><strong><?php echo esc_html( (string) $package['issues'] . ' / ' . (string) $package['warnings'] ); ?></strong></div>
				</div>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( self::admin_action_url( self::ACTION_DOWNLOAD ), self::ACTION_DOWNLOAD ) ); ?>"><?php echo esc_html( self::ui( 'Завантажити ZIP', 'Download ZIP' ) ); ?></a>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( self::admin_action_url( self::ACTION_DELETE ), self::ACTION_DELETE ) ); ?>"><?php echo esc_html( self::ui( 'Видалити ZIP із сервера', 'Delete ZIP from server' ) ); ?></a>
				</p>
				<?php self::render_package_details( $package ); ?>
				<details>
					<summary>SHA-256</summary>
					<code><?php echo esc_html( (string) $package['sha256'] ); ?></code>
				</details>
			</section>
		<?php endif; ?>

		<details class="kozgads-technical">
			<summary><?php echo esc_html( self::ui( 'Технічні дані та вибрані сторінки', 'Technical details and selected pages' ) ); ?></summary>
			<div>
				<table class="widefat striped">
					<tbody>
						<tr><th><?php echo esc_html( self::ui( 'Вихідна мова', 'Source language' ) ); ?></th><td><?php echo esc_html( self::$languages[ $source['slug'] ]['name'] . ' (' . $source['slug'] . ')' ); ?></td></tr>
						<tr><th><?php echo esc_html( self::ui( 'Переклад Azure', 'Azure translation' ) ); ?></th><td><?php echo esc_html( ! empty( $azure['configured'] ) ? self::ui( 'Налаштований', 'Configured' ) . ' (' . $azure['source'] . ')' : self::ui( 'Не налаштований', 'Not configured' ) ); ?><?php $usage = KOZGADS_Integrations::quota_usage(); if ( ! empty( $usage ) ) : ?> · <?php echo esc_html( sprintf( self::ui( 'квота Static Translate: %1$s з %2$s символів (%3$s%%)', 'Static Translate quota: %1$s of %2$s characters (%3$s%%)' ), number_format_i18n( $usage['characters'] ), number_format_i18n( $usage['limit'] ), (string) $usage['percent'] ) ); ?><?php endif; ?></td></tr>
						<tr><th><?php echo esc_html( self::ui( 'Перекладені сторінки', 'Translated pages' ) ); ?></th><td><?php echo esc_html( implode( ', ', KOZGADS_Integrations::translated_languages() ) ?: '—' ); ?></td></tr>
					</tbody>
				</table>
				<table class="widefat striped">
					<thead><tr><th><?php echo esc_html( self::ui( 'Сторінка', 'Page' ) ); ?></th><th>URL</th><th><?php echo esc_html( self::ui( 'Слів', 'Words' ) ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $landings as $page ) : ?>
						<tr><td><?php echo esc_html( (string) $page['title'] ); ?></td><td><code><?php echo esc_html( (string) $page['url'] ); ?></code></td><td><?php echo esc_html( number_format_i18n( (int) $page['word_count'] ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</details>
		<?php
	}

	private static function render_conversion_section( array $settings ): void {
		$donate = KOZGADS_Integrations::donate_stats();
		$bid    = self::effective_bid_strategy( $settings );
		?>
		<section class="kozgads-generate">
			<h2><?php echo esc_html( self::ui( 'Конверсія і ставки', 'Conversion and bidding' ) ); ?></h2>
			<table class="widefat striped"><tbody>
				<tr><th><?php echo esc_html( self::ui( 'Реальна конверсія в Google Ads', 'Real conversion in Google Ads' ) ); ?></th><td><?php echo esc_html( ! empty( $settings['conversion_tracking_confirmed'] ) ? self::ui( 'Так (підтверджено в налаштуваннях)', 'Yes (confirmed in the settings)' ) : self::ui( 'Ні', 'No' ) ); ?></td></tr>
				<tr><th>KOZ Donate Stats</th><td><?php echo esc_html( $donate['available'] ? ( $donate['data_layer'] && '' !== $donate['event'] ? sprintf( self::ui( 'подія dataLayer «%1$s»; підтверджених донатів: %2$d', 'dataLayer event "%1$s"; confirmed donations: %2$d' ), $donate['event'], $donate['confirmations'] ) : self::ui( 'активний, але подію dataLayer вимкнено', 'active, but the dataLayer event is off' ) ) : self::ui( 'не встановлено', 'not installed' ) ); ?></td></tr>
				<tr><th><?php echo esc_html( self::ui( 'Стратегія ставок у пакеті', 'Bid strategy in the package' ) ); ?></th><td><?php echo esc_html( $bid ); ?><?php if ( 'ad_grants' === self::account_mode( $settings ) && 'Maximize conversions' !== $bid ) : ?> — <?php echo esc_html( self::ui( 'Ad Grants обмежує ціну кліку до 2,00 USD', 'Ad Grants caps the cost per click at 2.00 USD' ) ); ?><?php endif; ?></td></tr>
			</tbody></table>
			<p class="description"><?php echo esc_html( self::ui( 'Після появи першої конверсії донату позначте її в налаштуваннях: пакет перейде на «Максимум конверсій».', 'Once the first donation conversion is recorded, tick it in the settings: the package switches to Maximize conversions.' ) ); ?></p>
		</section>
		<?php
	}

	private static function render_report_section(): void {
		$summary = KOZGADS_Report::summary();
		?>
		<section class="kozgads-generate">
			<h2><?php echo esc_html( self::ui( 'Попит на ключові слова', 'Keyword search demand' ) ); ?></h2>
			<p class="description"><?php echo esc_html( self::ui( 'Завантажте звіт ключових слів із Google Ads (Ключові слова → Завантажити → CSV) або експорт Keyword Planner. Слова з низьким обсягом пошуку не потраплять у новий пакет, а ідеї з Keyword Planner, що збігаються зі сторінками, буде додано.', 'Upload a keyword report from Google Ads (Keywords → Download → CSV) or a Keyword Planner export. Low-search-volume keywords are left out of the next package, and Keyword Planner ideas that match the pages are added.' ) ); ?></p>
			<?php if ( $summary['total'] > 0 ) : ?>
				<p><strong><?php echo esc_html( sprintf( self::ui( 'Ключових слів: %1$d; низький обсяг пошуку: %2$d; без показів: %3$d; з даними Keyword Planner: %4$d.', 'Keywords: %1$d; low search volume: %2$d; no impressions: %3$d; with Keyword Planner data: %4$d.' ), $summary['total'], $summary['low'], $summary['zero_impressions'], $summary['with_searches'] ) ); ?></strong> <span class="description"><?php echo esc_html( $summary['filename'] . ' · ' . $summary['imported_at'] ); ?></span></p>
			<?php endif; ?>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( self::admin_action_url( KOZGADS_Report::ACTION_IMPORT ) ); ?>">
				<?php wp_nonce_field( KOZGADS_Report::ACTION_IMPORT ); ?>
				<input type="file" name="kozgads_report" accept=".csv,.tsv,.txt" required>
				<button type="submit" class="button"><?php echo esc_html( self::ui( 'Імпортувати звіт', 'Import report' ) ); ?></button>
			</form>
			<?php if ( $summary['total'] > 0 ) : ?>
				<form method="post" action="<?php echo esc_url( self::admin_action_url( KOZGADS_Report::ACTION_CLEAR ) ); ?>">
					<?php wp_nonce_field( KOZGADS_Report::ACTION_CLEAR ); ?>
					<p><button type="submit" class="button-link"><?php echo esc_html( self::ui( 'Видалити імпортовані дані', 'Delete imported data' ) ); ?></button></p>
				</form>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function render_package_details( array $package ): void {
		$issues   = (array) ( $package['issue_list'] ?? array() );
		$warnings = (array) ( $package['warning_list'] ?? array() );
		?>
		<?php if ( ! empty( $issues ) ) : ?>
			<h3><?php echo esc_html( self::ui( 'Блокує запуск', 'Blocks launch' ) ); ?></h3>
			<ul class="ul-disc"><?php foreach ( $issues as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?></ul>
		<?php endif; ?>
		<?php if ( ! empty( $warnings ) ) : ?>
			<details><summary><?php echo esc_html( sprintf( self::ui( 'Попередження (%d)', 'Warnings (%d)' ), count( $warnings ) ) ); ?></summary><ul class="ul-disc"><?php foreach ( $warnings as $item ) : ?><li><?php echo esc_html( (string) $item ); ?></li><?php endforeach; ?></ul></details>
		<?php endif; ?>
		<?php if ( ! empty( $package['preview'] ) ) : ?>
			<details class="kozgads-preview" open><summary><?php echo esc_html( self::ui( 'Попередній перегляд оголошень', 'Ad preview' ) ); ?></summary>
				<?php foreach ( (array) $package['preview'] as $ad ) : ?>
					<div class="kozgads-ad">
						<small><?php echo esc_html( (string) $ad['campaign'] . ' › ' . (string) $ad['group'] ); ?></small>
						<div class="kozgads-ad__title"><?php echo esc_html( implode( ' | ', array_slice( (array) $ad['headlines'], 0, 3 ) ) ); ?></div>
						<div class="kozgads-ad__url"><?php echo esc_html( (string) $ad['url'] ); ?></div>
						<div><?php echo esc_html( implode( ' ', array_slice( (array) $ad['descriptions'], 0, 2 ) ) ); ?></div>
						<details><summary><?php echo esc_html( sprintf( self::ui( 'Усі заголовки (%1$d), описи (%2$d), ключові слова (%3$d)', 'All headlines (%1$d), descriptions (%2$d), keywords (%3$d)' ), count( (array) $ad['headlines'] ), count( (array) $ad['descriptions'] ), count( (array) $ad['keywords'] ) ) ); ?></summary>
							<p><?php echo esc_html( implode( ' · ', (array) $ad['headlines'] ) ); ?></p>
							<p><?php echo esc_html( implode( ' · ', (array) $ad['descriptions'] ) ); ?></p>
							<p><code><?php echo esc_html( implode( ', ', (array) $ad['keywords'] ) ); ?></code></p>
							<?php if ( ! empty( $ad['removed'] ) ) : ?><p><?php echo esc_html( self::ui( 'Прибрано (низький обсяг пошуку):', 'Removed (low search volume):' ) . ' ' . implode( ', ', (array) $ad['removed'] ) ); ?></p><?php endif; ?>
							<?php if ( ! empty( $ad['added'] ) ) : ?><p><?php echo esc_html( self::ui( 'Додано з Keyword Planner:', 'Added from Keyword Planner:' ) . ' ' . implode( ', ', (array) $ad['added'] ) ); ?></p><?php endif; ?>
						</details>
					</div>
				<?php endforeach; ?>
			</details>
		<?php endif; ?>
		<?php
	}

	private static function render_settings_tab( array $settings ): void {
		?>
		<div class="kozgads-settings-intro">
			<h2><?php echo esc_html( self::ui( 'Налаштування пакета', 'Package settings' ) ); ?></h2>
			<p><?php echo esc_html( self::ui( 'Мінімум для створення ZIP: режим, мова, бюджет і хоча б одна цільова локація.', 'Minimum for a ZIP: mode, language, budget and at least one target location.' ) ); ?></p>
		</div>

			<form method="post" action="<?php echo esc_url( self::admin_action_url( self::ACTION_SAVE ) ); ?>">
				<?php wp_nonce_field( self::ACTION_SAVE ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="kozgads-mode"><?php esc_html_e( 'Advertising account type', 'koz-google-ads-campaign-builder' ); ?></label></th><td><select id="kozgads-mode" name="account_mode"><?php foreach ( self::account_modes() as $key => $mode ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( self::account_mode( $settings ), $key ); ?>><?php echo esc_html( $mode['label'] ); ?></option><?php endforeach; ?></select><p class="description"><?php echo esc_html( self::account_modes()[ self::account_mode( $settings ) ]['description'] ); ?></p></td></tr>
					<tr><th><label for="kozgads-org"><?php esc_html_e( 'Organisation name', 'koz-google-ads-campaign-builder' ); ?></label></th><td><input id="kozgads-org" class="regular-text" name="organisation_name" value="<?php echo esc_attr( (string) $settings['organisation_name'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Monthly budget', 'koz-google-ads-campaign-builder' ); ?></th><td><input type="number" min="1" step="0.01" name="monthly_grant" value="<?php echo esc_attr( (string) $settings['monthly_grant'] ); ?>"> <input name="currency" size="6" value="<?php echo esc_attr( (string) $settings['currency'] ); ?>"></td></tr>
					<tr>
						<th><?php echo esc_html( self::ui( 'Мови кампаній', 'Campaign languages' ) ); ?></th>
						<td>
							<label style="display:block;margin-bottom:8px"><input type="radio" name="language_mode" value="automatic" <?php checked( 'automatic', (string) $settings['language_mode'] ); ?>> <strong><?php echo esc_html( self::ui( 'Автоматично: мова сайту + English', 'Automatic: site language + English' ) ); ?></strong></label>
							<p class="description"><?php echo esc_html( sprintf( self::ui( 'Визначено: %s. English додається як універсальна кампанія.', 'Detected: %s. English is added as the universal campaign.' ), implode( ', ', self::campaign_languages( array_merge( $settings, array( 'language_mode' => 'automatic' ) ) ) ) ) ); ?></p>
							<label style="display:block;margin:12px 0 8px"><input type="radio" name="language_mode" value="manual" <?php checked( 'manual', (string) $settings['language_mode'] ); ?>> <?php echo esc_html( self::ui( 'Ручна корекція мов', 'Manual language override' ) ); ?></label>
							<fieldset style="columns:3;max-width:900px"><?php foreach ( self::$languages as $slug => $language ) : ?><label style="display:block"><input type="checkbox" name="selected_languages[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $settings['selected_languages'], true ) ); ?>> <?php echo esc_html( $language['name'] . ' (' . $slug . ')' ); ?></label><?php endforeach; ?></fieldset>
							<p class="description"><?php esc_html_e( 'A non-source language requires Azure translation unless you generate that language separately.', 'koz-google-ads-campaign-builder' ); ?></p>
						</td>
					</tr>
					<?php
					$selected_countries = self::selected_country_codes( $settings );
					$site_language = (string) self::detected_site_language()['slug'];
					$source_auto   = implode( ', ', self::automatic_country_codes_for_language( $site_language ) );
					$english_auto  = implode( ', ', self::automatic_country_codes_for_language( 'en' ) );
					?>
					<tr>
						<th><?php echo esc_html( self::ui( 'Країни показу реклами', 'Countries to target' ) ); ?></th>
						<td>
							<label style="display:block;margin-bottom:8px"><input type="radio" name="location_mode" value="automatic" <?php checked( 'automatic', (string) $settings['location_mode'] ); ?>> <strong><?php echo esc_html( self::ui( 'Автоматична географія', 'Automatic geography' ) ); ?></strong></label>
							<p class="description"><?php echo esc_html( sprintf( self::ui( 'Основна кампанія %1$s → %2$s. English → англомовні ринки: %3$s.', 'Primary campaign %1$s → %2$s. English → English-speaking markets: %3$s.' ), $site_language, $source_auto, $english_auto ) ); ?></p>
							<label style="display:block;margin:12px 0 8px"><input type="radio" name="location_mode" value="manual" <?php checked( 'manual', (string) $settings['location_mode'] ); ?>> <?php echo esc_html( self::ui( 'Ручна корекція: однакові країни для всіх кампаній', 'Manual override: same countries for all campaigns' ) ); ?></label>
							<fieldset class="kozgads-country-grid">
								<?php foreach ( self::country_targets() as $country_code => $country ) : ?>
									<label>
										<input
											type="checkbox"
											name="target_countries[]"
											value="<?php echo esc_attr( $country_code ); ?>"
											<?php checked( in_array( $country_code, $selected_countries, true ) ); ?>
										>
										<?php echo esc_html( (string) $country['label'] ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php echo esc_html( self::ui( 'Checkbox-и використовуються лише в режимі ручної корекції.', 'Checkboxes are used only in manual override mode.' ) ); ?></p>
						</td>
					</tr>
					<tr><th><label for="kozgads-pattern"><?php esc_html_e( 'Translated URL pattern', 'koz-google-ads-campaign-builder' ); ?></label></th><td><input id="kozgads-pattern" class="regular-text code" name="translated_url_pattern" value="<?php echo esc_attr( (string) $settings['translated_url_pattern'] ); ?>"><p class="description"><?php esc_html_e( 'Use {language} and {path}. Leave empty to reuse source URLs. Verify every generated landing URL before import.', 'koz-google-ads-campaign-builder' ); ?></p></td></tr>
					<tr><th><label for="kozgads-max"><?php esc_html_e( 'Maximum landing pages per campaign', 'koz-google-ads-campaign-builder' ); ?></label></th><td><input id="kozgads-max" type="number" min="1" max="10" name="max_campaigns" value="<?php echo esc_attr( (string) $settings['max_campaigns'] ); ?>"></td></tr>
					<tr><th><?php echo esc_html( self::ui( 'Цільові сторінки', 'Landing pages' ) ); ?></th><td>
						<p class="description"><?php echo esc_html( self::ui( 'Нічого не вибрано — автоматично: головна, сторінка донату з Donate Stats, сторінки з меню, потім інші сторінки. Записи блогу — лише якщо вибрати їх тут.', 'Nothing selected — automatic: home page, the Donate Stats donation page, menu pages, then other pages. Blog posts are used only when selected here.' ) ); ?></p>
						<fieldset class="kozgads-country-grid">
							<label><input type="checkbox" name="landing_page_ids[]" value="0" <?php checked( in_array( 0, (array) $settings['landing_page_ids'], true ) ); ?>> <?php echo esc_html( self::ui( 'Головна сторінка', 'Home page' ) ); ?></label>
							<?php foreach ( self::landing_choices() as $page_id => $page_title ) : ?>
								<?php if ( (int) $page_id === absint( get_option( 'page_on_front' ) ) ) { continue; } ?>
								<label><input type="checkbox" name="landing_page_ids[]" value="<?php echo esc_attr( (string) $page_id ); ?>" <?php checked( in_array( (int) $page_id, (array) $settings['landing_page_ids'], true ) ); ?>> <?php echo esc_html( $page_title ); ?></label>
							<?php endforeach; ?>
						</fieldset>
					</td></tr>
					<tr><th><label for="kozgads-bid"><?php esc_html_e( 'Bid strategy', 'koz-google-ads-campaign-builder' ); ?></label></th><td><select id="kozgads-bid" name="bid_strategy"><?php foreach ( self::bid_strategies() as $strategy ) : ?><option value="<?php echo esc_attr( $strategy ); ?>" <?php selected( (string) $settings['bid_strategy'], $strategy ); ?>><?php echo esc_html( 'auto' === $strategy ? self::ui( 'Автоматично (Максимум кліків, доки немає конверсій)', 'Automatic (Maximize clicks until conversions exist)' ) : $strategy ); ?></option><?php endforeach; ?></select><p class="description"><?php echo esc_html( self::ui( 'У режимі Ad Grants «Максимум конверсій» без зафіксованої конверсії замінюється на «Максимум кліків».', 'In Ad Grants mode, Maximize conversions without a recorded conversion is replaced by Maximize clicks.' ) ); ?></p></td></tr>
					<tr><th><label for="kozgads-min-searches"><?php echo esc_html( self::ui( 'Мінімум пошуків на місяць', 'Minimum monthly searches' ) ); ?></label></th><td><input id="kozgads-min-searches" type="number" min="0" max="1000" name="min_searches" value="<?php echo esc_attr( (string) $settings['min_searches'] ); ?>"><p class="description"><?php echo esc_html( self::ui( 'Для імпорту з Keyword Planner: слова з меншою кількістю пошуків не потраплять у пакет.', 'For Keyword Planner imports: keywords with fewer searches are left out of the package.' ) ); ?></p></td></tr>
					<tr><th><label for="kozgads-conversion"><?php esc_html_e( 'Primary conversion key', 'koz-google-ads-campaign-builder' ); ?></label></th><td><input id="kozgads-conversion" class="regular-text code" name="primary_conversion_name" value="<?php echo esc_attr( (string) $settings['primary_conversion_name'] ); ?>"><p class="description"><?php esc_html_e( 'A neutral internal key for the review checklist. The plugin does not create a Google Ads conversion action.', 'koz-google-ads-campaign-builder' ); ?></p></td></tr>
					<tr><th><label for="kozgads-negatives"><?php esc_html_e( 'Negative keywords', 'koz-google-ads-campaign-builder' ); ?></label></th><td><textarea id="kozgads-negatives" class="large-text" rows="5" name="negative_keywords"><?php echo esc_textarea( implode( "\n", (array) $settings['negative_keywords'] ) ); ?></textarea><p class="description"><?php esc_html_e( 'Optional additions, one phrase per line. The plugin does not guess universal negative keywords.', 'koz-google-ads-campaign-builder' ); ?></p></td></tr>
					<tr><th><label for="kozgads-callouts"><?php esc_html_e( 'Callouts', 'koz-google-ads-campaign-builder' ); ?></label></th><td><textarea id="kozgads-callouts" class="large-text" rows="4" name="callouts"><?php echo esc_textarea( implode( "\n", (array) $settings['callouts'] ) ); ?></textarea><p class="description"><?php esc_html_e( 'Optional additions, one callout per line. Automatic callouts are derived from the selected landing pages.', 'koz-google-ads-campaign-builder' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Options', 'koz-google-ads-campaign-builder' ); ?></th><td>
						<label><input type="checkbox" name="include_search_partners" value="1" <?php checked( ! empty( $settings['include_search_partners'] ) ); ?>> <?php esc_html_e( 'Include Search Partners', 'koz-google-ads-campaign-builder' ); ?></label><br>
						<label><input type="checkbox" name="conversion_tracking_confirmed" value="1" <?php checked( ! empty( $settings['conversion_tracking_confirmed'] ) ); ?>> <?php echo esc_html( self::ui( 'Реальна конверсія (донат) налаштована як основна в Google Ads і вже зафіксована', 'A real conversion (donation) is primary in Google Ads and has been recorded' ) ); ?></label><br>
						<label><input type="checkbox" name="generate_source_language" value="1" <?php checked( ! empty( $settings['generate_source_language'] ) ); ?>> <?php echo esc_html( self::ui( 'Створювати кампанію мовою сайту (вимкніть, якщо цією мовою майже немає пошукового попиту)', 'Create a campaign in the site language (turn off when there is almost no search demand in that language)' ) ); ?></label><br>
						<label><input type="checkbox" name="allow_azure_copy_translation" value="1" <?php checked( ! empty( $settings['allow_azure_copy_translation'] ) ); ?>> <?php esc_html_e( 'Allow Azure to translate generated ad copy during package generation', 'koz-google-ads-campaign-builder' ); ?></label><br>
						<label><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?>> <?php esc_html_e( 'Delete settings and the last package when the plugin is uninstalled', 'koz-google-ads-campaign-builder' ); ?></label>
					</td></tr>
					<tr><th><label for="kozgads-suffix"><?php esc_html_e( 'Final URL suffix', 'koz-google-ads-campaign-builder' ); ?></label></th><td><input id="kozgads-suffix" class="large-text code" name="final_url_suffix" value="<?php echo esc_attr( (string) $settings['final_url_suffix'] ); ?>"><p class="description"><?php esc_html_e( 'Do not include the initial question mark.', 'koz-google-ads-campaign-builder' ); ?></p></td></tr>
				</table>
				<?php submit_button( __( 'Save settings', 'koz-google-ads-campaign-builder' ) ); ?>
			</form>
		<?php
	}


	public static function admin_page(): void {
		self::require_admin();
		$settings = self::settings();
		$source   = self::detected_site_language();
		$landings = self::selected_landing_pages( $settings );
		$azure    = KOZGADS_Integrations::azure_credentials();
		$package  = get_option( self::PACKAGE_OPTION, array() );
		$package  = is_array( $package ) ? $package : array();
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin tab selection.
		if ( ! in_array( $tab, array( 'overview', 'settings' ), true ) ) {
			$tab = 'overview';
		}
		$notice = isset( $_GET['kozgads_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['kozgads_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect notice code.
		$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect notice message.
		self::render_notice( $notice, $message );
		?>
		<div class="wrap kozgads">
			<h1><?php esc_html_e( 'KOZ Google Ads Campaign Builder', 'koz-google-ads-campaign-builder' ); ?></h1>
			<p><?php echo esc_html( self::ui( 'Готує перевірювані пакети для Google Ad Grants або Standard Google Ads. Нічого не запускає автоматично.', 'Builds reviewable packages for Google Ad Grants or Standard Google Ads. Nothing is launched automatically.' ) ); ?></p>
			<nav class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'overview' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'overview' ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( self::ui( 'Огляд', 'Overview' ) ); ?></a>
				<a class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'settings' ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( self::ui( 'Налаштування', 'Settings' ) ); ?></a>
			</nav>
			<?php
			if ( 'settings' === $tab ) {
				self::render_settings_tab( $settings );
			} else {
				self::render_overview_tab( $settings, $source, $landings, $azure, $package );
			}
			?>
		</div>
		<?php
	}

	private static function render_notice( string $notice, string $message ): void {
		$map = array(
			'settings_saved'  => array( 'success', __( 'Settings saved.', 'koz-google-ads-campaign-builder' ) ),
			'package_ready'   => array( 'success', __( 'Google Ads package generated.', 'koz-google-ads-campaign-builder' ) ),
			'package_deleted' => array( 'success', __( 'Package deleted from the server.', 'koz-google-ads-campaign-builder' ) ),
			'report_imported' => array( 'success', __( 'Keyword report imported.', 'koz-google-ads-campaign-builder' ) ),
			'report_cleared'  => array( 'success', __( 'Imported keyword data deleted.', 'koz-google-ads-campaign-builder' ) ),
			'live_check_complete' => array( 'success', __( 'AdsBot destination check finished.', 'koz-google-ads-campaign-builder' ) ),
			'error'           => array( 'error', '' !== $message ? $message : __( 'An error occurred.', 'koz-google-ads-campaign-builder' ) ),
		);
		if ( ! isset( $map[ $notice ] ) ) {
			return;
		}
		echo '<div class="notice notice-' . esc_attr( $map[ $notice ][0] ) . ' is-dismissible"><p>' . esc_html( $map[ $notice ][1] ) . '</p></div>';
	}
}
