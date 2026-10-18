<?php
/**
 * Admin screen: General, Banner texts (all languages), Signals and services, Consent records.
 *
 * @package KOZ_Consent_Manager
 */

namespace ramirkz\kozconsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZCONSENT_Admin {
	private const SAVE_ACTION = 'kozconsent_save';
	private const NONCE_NAME  = 'kozconsent_nonce';
	private const TABS        = array( 'general', 'banner', 'integrations', 'log' );

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( __CLASS__, 'save' ) );
		add_action( 'admin_post_kozconsent_scan', array( __CLASS__, 'handle_scan' ) );
		add_action( 'admin_post_kozconsent_log_export', array( __CLASS__, 'handle_log_export' ) );
		add_action( 'admin_post_kozconsent_log_clear', array( __CLASS__, 'handle_log_clear' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KOZCONSENT_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu(): void {
		add_submenu_page(
			KOZCONSENT_Suite_Registry::suite_page(),
			__( 'KOZ Consent Manager', 'koz-consent-manager' ),
			__( 'Consent Manager', 'koz-consent-manager' ),
			'manage_options',
			KOZCONSENT_Manager::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'koz-consent-manager' ) . '</a>' );
		return $links;
	}

	private static function url( string $tab = 'general', array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => KOZCONSENT_Manager::PAGE_SLUG, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	private static function get_key( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen parameters.
		return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	private static function authorise(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'koz-consent-manager' ), '', array( 'response' => 403 ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Saving
	 * ---------------------------------------------------------------- */

	public static function save(): void {
		self::authorise();
		check_admin_referer( self::SAVE_ACTION, self::NONCE_NAME );
		$raw  = filter_input( INPUT_POST, 'settings', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$raw  = is_array( $raw ) ? wp_unslash( $raw ) : array();
		$tab  = sanitize_key( (string) filter_input( INPUT_POST, 'tab', FILTER_UNSAFE_RAW ) );
		$tab  = in_array( $tab, self::TABS, true ) ? $tab : 'general';
		$s    = KOZCONSENT_Settings::stored();
		$flag = static fn( string $key ): bool => ! empty( $raw[ $key ] );

		if ( 'general' === $tab ) {
			$s['enabled']              = $flag( 'enabled' );
			$s['policy_version']       = KOZCONSENT_Settings::sanitize_policy( (string) ( $raw['policy_version'] ?? '1' ) );
			$s['cookie_lifetime_days'] = max( 1, min( 730, (int) ( $raw['cookie_lifetime_days'] ?? 180 ) ) );
			$s['debug_mode']           = $flag( 'debug_mode' );
			$s['layout']               = in_array( $raw['layout'] ?? '', KOZCONSENT_Settings::LAYOUTS, true ) ? (string) $raw['layout'] : 'bar';
			$s['accent_color']         = KOZCONSENT_Settings::sanitize_color( (string) ( $raw['accent_color'] ?? '' ) );
			$s['reopen']               = 'hidden' === ( $raw['reopen'] ?? '' ) ? 'hidden' : 'floating';
			$s['honor_gpc']            = $flag( 'honor_gpc' );
			$s['block_media']          = $flag( 'block_media' );
			$s['youtube_nocookie']     = $flag( 'youtube_nocookie' );
		} elseif ( 'banner' === $tab ) {
			$language = sanitize_key( (string) filter_input( INPUT_POST, 'template_language', FILTER_UNSAFE_RAW ) );
			$language = in_array( $language, KOZCONSENT_Settings::SUPPORTED_LANGUAGES, true ) ? $language : KOZCONSENT_Settings::site_language();
			$s['templates'][ $language ] = KOZCONSENT_Settings::sanitize_template( $raw, $language );
			KOZCONSENT_Settings::save( $s );
			wp_safe_redirect( self::url( 'banner', array( 'lang' => $language, 'updated' => '1' ) ) );
			exit;
		} elseif ( 'integrations' === $tab ) {
			$s['google_consent_mode'] = $flag( 'google_consent_mode' );
			$s['gcm_region']          = 'eea' === ( $raw['gcm_region'] ?? '' ) ? 'eea' : 'all';
			$s['gcm_wait']            = max( 0, min( 5000, (int) ( $raw['gcm_wait'] ?? 500 ) ) );
			$s['gcm_url_passthrough'] = $flag( 'gcm_url_passthrough' );
			$s['gcm_ads_redaction']   = $flag( 'gcm_ads_redaction' );
			$s['microsoft_consent']   = $flag( 'microsoft_consent' );
			$s['datalayer_events']    = $flag( 'datalayer_events' );
			$s['wp_consent_api']      = $flag( 'wp_consent_api' );
			$s['handle_map']          = KOZCONSENT_Settings::parse_handle_map( sanitize_textarea_field( (string) ( $raw['handle_map'] ?? '' ) ) );
		} elseif ( 'log' === $tab ) {
			$s['log_enabled']   = $flag( 'log_enabled' );
			$s['log_retention'] = max( 30, min( 1825, (int) ( $raw['log_retention'] ?? 365 ) ) );
		}
		KOZCONSENT_Settings::save( $s );
		wp_safe_redirect( self::url( $tab, array( 'updated' => '1' ) ) );
		exit;
	}

	public static function handle_scan(): void {
		self::authorise();
		check_admin_referer( 'kozconsent_scan' );
		KOZCONSENT_Scanner::scan();
		wp_safe_redirect( self::url( 'integrations', array( 'scanned' => '1' ) ) );
		exit;
	}

	public static function handle_log_export(): void {
		self::authorise();
		check_admin_referer( 'kozconsent_log_export' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="koz-consent-records-' . gmdate( 'Ymd-His' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo KOZCONSENT_Log::export_csv(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, not HTML.
		exit;
	}

	public static function handle_log_clear(): void {
		self::authorise();
		check_admin_referer( 'kozconsent_log_clear' );
		KOZCONSENT_Log::clear();
		wp_safe_redirect( self::url( 'log', array( 'updated' => '1' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Rendering
	 * ---------------------------------------------------------------- */

	private static function form_open( string $tab ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
			<?php wp_nonce_field( self::SAVE_ACTION, self::NONCE_NAME ); ?>
		<?php
	}

	private static function checkbox( string $key, string $label, array $s ): void {
		printf(
			'<label><input type="checkbox" name="settings[%1$s]" value="1" %2$s> %3$s</label><br>',
			esc_attr( $key ),
			checked( ! empty( $s[ $key ] ), true, false ),
			esc_html( $label )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab    = self::get_key( 'tab' );
		$tab    = in_array( $tab, self::TABS, true ) ? $tab : 'general';
		$labels = array(
			'general'      => __( 'General', 'koz-consent-manager' ),
			'banner'       => __( 'Banner texts', 'koz-consent-manager' ),
			'integrations' => __( 'Signals and services', 'koz-consent-manager' ),
			'log'          => __( 'Consent records', 'koz-consent-manager' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'KOZ Consent Manager', 'koz-consent-manager' ); ?> <code><?php echo esc_html( KOZCONSENT_VERSION ); ?></code></h1>
			<?php if ( '1' === self::get_key( 'updated' ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'koz-consent-manager' ); ?></p></div>
			<?php endif; ?>
			<?php if ( '1' === self::get_key( 'scanned' ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The home page was scanned.', 'koz-consent-manager' ); ?></p></div>
			<?php endif; ?>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $labels as $key => $label ) : ?>
					<a class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::url( $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			if ( 'banner' === $tab ) {
				self::tab_banner();
			} elseif ( 'integrations' === $tab ) {
				self::tab_integrations();
			} elseif ( 'log' === $tab ) {
				self::tab_log();
			} else {
				self::tab_general();
			}
			?>
		</div>
		<?php
	}

	private static function tab_general(): void {
		$s = KOZCONSENT_Settings::stored();
		self::form_open( 'general' );
		?>
		<p><?php esc_html_e( 'Optional consent is off until the visitor allows it. No IP addresses, User-Agent values or emails are stored.', 'koz-consent-manager' ); ?></p>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><?php esc_html_e( 'Consent banner', 'koz-consent-manager' ); ?></th><td><?php self::checkbox( 'enabled', __( 'Enable the frontend banner', 'koz-consent-manager' ), $s ); ?></td></tr>
			<tr><th scope="row"><label for="kozconsent-policy-version"><?php esc_html_e( 'Policy version', 'koz-consent-manager' ); ?></label></th><td><input class="regular-text" id="kozconsent-policy-version" name="settings[policy_version]" value="<?php echo esc_attr( $s['policy_version'] ); ?>" maxlength="60"><p class="description"><?php esc_html_e( 'Changing this value asks every visitor for consent again.', 'koz-consent-manager' ); ?></p></td></tr>
			<tr><th scope="row"><label for="kozconsent-cookie-days"><?php esc_html_e( 'Cookie lifetime, days', 'koz-consent-manager' ); ?></label></th><td><input type="number" min="1" max="730" id="kozconsent-cookie-days" name="settings[cookie_lifetime_days]" value="<?php echo esc_attr( (string) $s['cookie_lifetime_days'] ); ?>"></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Layout', 'koz-consent-manager' ); ?></th><td>
				<?php
				$layouts = array(
					'bar'   => __( 'Bar at the bottom', 'koz-consent-manager' ),
					'box'   => __( 'Box in the corner', 'koz-consent-manager' ),
					'modal' => __( 'Centred dialog (the visitor must choose before using the site)', 'koz-consent-manager' ),
				);
				foreach ( $layouts as $key => $label ) {
					printf( '<label><input type="radio" name="settings[layout]" value="%1$s" %2$s> %3$s</label><br>', esc_attr( $key ), checked( $s['layout'], $key, false ), esc_html( $label ) );
				}
				?>
			</td></tr>
			<tr><th scope="row"><label for="kozconsent-accent"><?php esc_html_e( 'Accent colour', 'koz-consent-manager' ); ?></label></th><td><input type="color" id="kozconsent-accent" name="settings[accent_color]" value="<?php echo esc_attr( $s['accent_color'] ); ?>"><p class="description"><?php esc_html_e( 'Used for both "Accept" and "Reject": they look the same, as European data protection authorities require.', 'koz-consent-manager' ); ?></p></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Reopen settings', 'koz-consent-manager' ); ?></th><td>
				<label><input type="radio" name="settings[reopen]" value="floating" <?php checked( $s['reopen'], 'floating' ); ?>> <?php esc_html_e( 'Floating button', 'koz-consent-manager' ); ?></label><br>
				<label><input type="radio" name="settings[reopen]" value="hidden" <?php checked( $s['reopen'], 'hidden' ); ?>> <?php esc_html_e( 'No button: I use a link or the shortcode', 'koz-consent-manager' ); ?></label>
				<p class="description"><?php esc_html_e( 'Any link to #kozconsent (for example in a footer menu) and the [kozconsent_settings] shortcode open the settings.', 'koz-consent-manager' ); ?></p>
			</td></tr>
			<tr><th scope="row"><?php esc_html_e( 'External media', 'koz-consent-manager' ); ?></th><td>
				<?php self::checkbox( 'block_media', __( 'Block YouTube, Vimeo, Google Maps and other embeds until consent', 'koz-consent-manager' ), $s ); ?>
				<?php self::checkbox( 'youtube_nocookie', __( 'Load YouTube from youtube-nocookie.com', 'koz-consent-manager' ), $s ); ?>
				<p class="description"><?php esc_html_e( 'A blocked embed keeps its size and shows a notice with "Load content" and "Always allow external media".', 'koz-consent-manager' ); ?></p>
			</td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Global Privacy Control', 'koz-consent-manager' ); ?></th><td><?php self::checkbox( 'honor_gpc', __( 'When the browser sends GPC, keep advertising off even after "Accept all"', 'koz-consent-manager' ), $s ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Debug mode', 'koz-consent-manager' ); ?></th><td><?php self::checkbox( 'debug_mode', __( 'Show browser console messages without persistent logging', 'koz-consent-manager' ), $s ); ?></td></tr>
		</table>
		<?php submit_button(); ?>
		</form>
		<?php
	}

	private static function tab_banner(): void {
		$language = self::get_key( 'lang' );
		$language = in_array( $language, KOZCONSENT_Settings::SUPPORTED_LANGUAGES, true ) ? $language : KOZCONSENT_Settings::site_language();
		$t        = KOZCONSENT_Settings::template( $language );
		$custom   = KOZCONSENT_Settings::stored()['templates'];
		?>
		<p><?php esc_html_e( 'The banner uses the language of the page. Each language has a ready text; change it only where needed.', 'koz-consent-manager' ); ?></p>
		<ul class="subsubsub">
			<?php
			$items = array();
			foreach ( KOZCONSENT_Settings::SUPPORTED_LANGUAGES as $code ) {
				$items[] = '<li><a href="' . esc_url( self::url( 'banner', array( 'lang' => $code ) ) ) . '"' . ( $code === $language ? ' class="current"' : '' ) . ' lang="' . esc_attr( $code ) . '">' . esc_html( KOZCONSENT_Settings::language_name( $code ) ) . ( isset( $custom[ $code ] ) ? ' *' : '' ) . '</a>';
			}
			echo wp_kses_post( implode( ' |</li>', $items ) . '</li>' );
			?>
		</ul>
		<div style="clear:both"></div>
		<?php self::form_open( 'banner' ); ?>
		<input type="hidden" name="template_language" value="<?php echo esc_attr( $language ); ?>">
		<table class="form-table" role="presentation"<?php echo 'ar' === $language ? ' dir="rtl"' : ''; ?> lang="<?php echo esc_attr( $language ); ?>">
			<tr><th scope="row"><label for="kozconsent-banner-title"><?php esc_html_e( 'Banner title', 'koz-consent-manager' ); ?></label></th><td><input class="large-text" id="kozconsent-banner-title" name="settings[banner_title]" value="<?php echo esc_attr( $t['banner_title'] ); ?>" maxlength="120"></td></tr>
			<tr><th scope="row"><label for="kozconsent-banner-message"><?php esc_html_e( 'Banner message', 'koz-consent-manager' ); ?></label></th><td><textarea class="large-text" rows="4" id="kozconsent-banner-message" name="settings[banner_message]" maxlength="700"><?php echo esc_textarea( $t['banner_message'] ); ?></textarea></td></tr>
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
				<tr><th scope="row"><label for="kozconsent-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input class="regular-text" id="kozconsent-<?php echo esc_attr( $key ); ?>" name="settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $t[ $key ] ); ?>" maxlength="100"></td></tr>
			<?php endforeach; ?>
		</table>
		<p class="description"><?php esc_html_e( '* marks languages with your own text. Empty fields use the ready text.', 'koz-consent-manager' ); ?></p>
		<?php submit_button(); ?>
		</form>
		<?php
	}

	private static function tab_integrations(): void {
		$s        = KOZCONSENT_Settings::stored();
		$scan     = KOZCONSENT_Scanner::last();
		$api      = KOZCONSENT_WP_Consent_API::is_active();
		$site_kit = KOZCONSENT_Consent_Mode::site_kit_consent_mode();
		$controls = array(
			'google'    => __( 'Google Consent Mode', 'koz-consent-manager' ),
			'microsoft' => __( 'Microsoft consent signal', 'koz-consent-manager' ),
			'media'     => __( 'Blocked until consent', 'koz-consent-manager' ),
			'none'      => __( 'Not controlled', 'koz-consent-manager' ),
		);
		$categories = array(
			'analytics'      => __( 'Analytics', 'koz-consent-manager' ),
			'advertising'    => __( 'Advertising', 'koz-consent-manager' ),
			'external_media' => __( 'External media', 'koz-consent-manager' ),
		);
		$map_text = '';
		foreach ( (array) $s['handle_map'] as $handle => $category ) {
			$map_text .= $handle . '=' . $category . "\n";
		}
		?>
		<h2><?php esc_html_e( 'Detected', 'koz-consent-manager' ); ?></h2>
		<table class="widefat striped" style="max-width:900px"><tbody>
			<tr><td><?php esc_html_e( 'WP Consent API plugin', 'koz-consent-manager' ); ?></td><td><strong><?php echo esc_html( $api ? __( 'Active', 'koz-consent-manager' ) : __( 'Not active', 'koz-consent-manager' ) ); ?></strong></td></tr>
			<tr><td><?php esc_html_e( 'Site Kit Consent Mode', 'koz-consent-manager' ); ?></td><td><strong><?php echo esc_html( $site_kit ? __( 'On (EEA, UK and Switzerland)', 'koz-consent-manager' ) : __( 'Off', 'koz-consent-manager' ) ); ?></strong><?php if ( $site_kit && ! $api ) : ?> — <?php esc_html_e( 'Site Kit needs the WP Consent API plugin to receive the visitor\'s choice.', 'koz-consent-manager' ); ?><?php endif; ?></td></tr>
		</tbody></table>

		<h2><?php esc_html_e( 'Services on the home page', 'koz-consent-manager' ); ?></h2>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kozconsent_scan' ), 'kozconsent_scan' ) ); ?>"><?php esc_html_e( 'Scan the home page', 'koz-consent-manager' ); ?></a>
			<?php if ( ! empty( $scan['time'] ) ) : ?>
				<span class="description"><?php echo esc_html( sprintf( /* translators: %s: date and time of the last scan. */ __( 'Last scan: %s', 'koz-consent-manager' ), wp_date( 'Y-m-d H:i', (int) $scan['time'] ) ) ); ?></span>
			<?php endif; ?>
		</p>
		<?php if ( ! empty( $scan['error'] ) ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'The home page could not be loaded for the scan.', 'koz-consent-manager' ); ?></p></div>
		<?php elseif ( ! empty( $scan['time'] ) ) : ?>
			<table class="widefat striped" style="max-width:900px"><thead><tr><th><?php esc_html_e( 'Service', 'koz-consent-manager' ); ?></th><th><?php esc_html_e( 'Category', 'koz-consent-manager' ); ?></th><th><?php esc_html_e( 'How consent applies', 'koz-consent-manager' ); ?></th></tr></thead><tbody>
			<?php if ( empty( $scan['services'] ) ) : ?><tr><td colspan="3"><?php esc_html_e( 'No known third-party services were found.', 'koz-consent-manager' ); ?></td></tr><?php endif; ?>
			<?php foreach ( (array) $scan['services'] as $service ) : ?>
				<tr>
					<td><?php echo esc_html( $service['name'] . ( (int) $service['count'] > 1 ? ' ×' . (int) $service['count'] : '' ) ); ?></td>
					<td><?php echo esc_html( $categories[ $service['category'] ] ?? $service['category'] ); ?></td>
					<td><?php echo 'none' === $service['control'] ? '<strong>' . esc_html( $controls['none'] ) . '</strong>' : esc_html( $controls[ $service['control'] ] ?? $service['control'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>

		<?php self::form_open( 'integrations' ); ?>
		<h2><?php esc_html_e( 'Consent signals', 'koz-consent-manager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><?php esc_html_e( 'Google Consent Mode v2', 'koz-consent-manager' ); ?></th><td>
				<?php self::checkbox( 'google_consent_mode', __( 'Synchronize consent with Google tags', 'koz-consent-manager' ), $s ); ?>
				<p class="description"><?php esc_html_e( 'Analytics maps to analytics_storage and personalization_storage. Advertising maps to ad_storage, ad_user_data and ad_personalization. Functionality and security storage are always granted.', 'koz-consent-manager' ); ?></p>
				<p><label><input type="radio" name="settings[gcm_region]" value="all" <?php checked( $s['gcm_region'], 'all' ); ?>> <?php esc_html_e( 'Denied by default for every visitor', 'koz-consent-manager' ); ?></label><br>
				<label><input type="radio" name="settings[gcm_region]" value="eea" <?php checked( $s['gcm_region'], 'eea' ); ?>> <?php esc_html_e( 'Denied by default only in the EEA, the UK and Switzerland', 'koz-consent-manager' ); ?></label></p>
				<p><label><?php esc_html_e( 'Wait for the banner, ms', 'koz-consent-manager' ); ?> <input type="number" min="0" max="5000" step="100" name="settings[gcm_wait]" value="<?php echo esc_attr( (string) $s['gcm_wait'] ); ?>" style="width:90px"></label></p>
				<?php self::checkbox( 'gcm_ads_redaction', __( 'Redact ad data while advertising is denied (ads_data_redaction)', 'koz-consent-manager' ), $s ); ?>
				<?php self::checkbox( 'gcm_url_passthrough', __( 'Pass click identifiers in links while cookies are denied (url_passthrough)', 'koz-consent-manager' ), $s ); ?>
			</td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Microsoft', 'koz-consent-manager' ); ?></th><td><?php self::checkbox( 'microsoft_consent', __( 'Send consent to Microsoft Clarity (consentv2) and Microsoft Advertising UET', 'koz-consent-manager' ), $s ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Google Tag Manager', 'koz-consent-manager' ); ?></th><td><?php self::checkbox( 'datalayer_events', __( 'Push kozconsent_default and kozconsent_update events to the dataLayer', 'koz-consent-manager' ), $s ); ?><p class="description"><?php esc_html_e( 'Use them as triggers so tags that ignore Consent Mode (Meta Pixel, Hotjar and others) fire only after consent.', 'koz-consent-manager' ); ?></p></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'WP Consent API', 'koz-consent-manager' ); ?></th><td><?php self::checkbox( 'wp_consent_api', __( 'Pass choices to plugins that use WP Consent API (Site Kit, WooCommerce, WP Statistics…)', 'koz-consent-manager' ), $s ); ?><p class="description"><?php esc_html_e( 'Necessary → functional; Analytics → statistics, statistics-anonymous, preferences; Advertising → marketing.', 'koz-consent-manager' ); ?></p></td></tr>
			<tr><th scope="row"><label for="kozconsent-handles"><?php esc_html_e( 'Script handles', 'koz-consent-manager' ); ?></label></th><td>
				<textarea id="kozconsent-handles" class="large-text code" rows="4" name="settings[handle_map]" placeholder="my-tracker=analytics"><?php echo esc_textarea( $map_text ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One per line: handle=category (analytics, advertising or external_media). These WordPress scripts load only after the visitor allows the category.', 'koz-consent-manager' ); ?></p>
			</td></tr>
		</table>
		<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Registered local integrations', 'koz-consent-manager' ); ?></h2>
		<?php $integrations = KOZCONSENT_Manager::public_integrations(); ?>
		<?php if ( empty( $integrations ) ) : ?>
			<p><?php esc_html_e( 'No optional script integrations are registered on this request.', 'koz-consent-manager' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:900px"><thead><tr><th><?php esc_html_e( 'ID', 'koz-consent-manager' ); ?></th><th><?php esc_html_e( 'Name', 'koz-consent-manager' ); ?></th><th><?php esc_html_e( 'Category', 'koz-consent-manager' ); ?></th><th><?php esc_html_e( 'Script handles', 'koz-consent-manager' ); ?></th></tr></thead><tbody>
			<?php foreach ( $integrations as $integration ) : ?>
				<tr><td><code><?php echo esc_html( $integration['id'] ); ?></code></td><td><?php echo esc_html( $integration['name'] ); ?></td><td><code><?php echo esc_html( $integration['category'] ); ?></code></td><td><?php echo esc_html( implode( ', ', $integration['script_handles'] ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Shortcodes', 'koz-consent-manager' ); ?></h2>
		<p><code>[kozconsent_settings]</code> — <?php esc_html_e( 'a button that opens the consent settings.', 'koz-consent-manager' ); ?><br>
		<code>[kozconsent_services]</code> — <?php esc_html_e( 'the consent categories and the services that depend on them, for the privacy policy page.', 'koz-consent-manager' ); ?></p>

		<h2><?php esc_html_e( 'Public read-only contract', 'koz-consent-manager' ); ?></h2>
		<pre><?php echo esc_html( (string) wp_json_encode( KOZCONSENT_Manager::status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
		<?php
	}

	private static function tab_log(): void {
		$s     = KOZCONSENT_Settings::stored();
		$stats = KOZCONSENT_Log::stats();
		self::form_open( 'log' );
		?>
		<p><?php esc_html_e( 'Consent records help you show when and how a visitor chose (GDPR Article 7). Each record has a random ID from the visitor\'s browser, the latest choice, the policy version, the time, the language and whether Global Privacy Control was sent. No IP address, User-Agent or page address is stored.', 'koz-consent-manager' ); ?></p>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><?php esc_html_e( 'Consent records', 'koz-consent-manager' ); ?></th><td><?php self::checkbox( 'log_enabled', __( 'Store consent records', 'koz-consent-manager' ), $s ); ?><p class="description"><?php esc_html_e( 'Off by default. Mention the records in your privacy policy when you turn them on.', 'koz-consent-manager' ); ?></p></td></tr>
			<tr><th scope="row"><label for="kozconsent-retention"><?php esc_html_e( 'Keep records, days', 'koz-consent-manager' ); ?></label></th><td><input type="number" min="30" max="1825" id="kozconsent-retention" name="settings[log_retention]" value="<?php echo esc_attr( (string) $s['log_retention'] ); ?>"></td></tr>
		</table>
		<?php submit_button(); ?>
		</form>
		<h2><?php esc_html_e( 'Last 30 days', 'koz-consent-manager' ); ?></h2>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: total, 2: accepted all, 3: rejected optional, 4: customized, 5: with GPC. */
					__( 'Records: %1$d. Accepted all: %2$d. Rejected optional: %3$d. Customized: %4$d. With Global Privacy Control: %5$d.', 'koz-consent-manager' ),
					$stats['total'],
					$stats['accept'],
					$stats['reject'],
					$stats['custom'],
					$stats['gpc']
				)
			);
			?>
		</p>
		<?php $rows = KOZCONSENT_Log::recent( 50 ); ?>
		<table class="widefat striped" style="max-width:1100px"><thead><tr><th><?php esc_html_e( 'Consent ID', 'koz-consent-manager' ); ?></th><th><?php esc_html_e( 'Updated (UTC)', 'koz-consent-manager' ); ?></th><th><?php esc_html_e( 'Choice', 'koz-consent-manager' ); ?></th><th><?php esc_html_e( 'Analytics', 'koz-consent-manager' ); ?></th><th><?php esc_html_e( 'Advertising', 'koz-consent-manager' ); ?></th><th><?php esc_html_e( 'External media', 'koz-consent-manager' ); ?></th><th><?php esc_html_e( 'Policy version', 'koz-consent-manager' ); ?></th><th>GPC</th></tr></thead><tbody>
		<?php if ( empty( $rows ) ) : ?><tr><td colspan="8"><?php esc_html_e( 'No records.', 'koz-consent-manager' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $rows as $row ) : ?>
			<tr><td><code><?php echo esc_html( substr( (string) $row['consent_id'], 0, 12 ) ); ?>…</code></td><td><?php echo esc_html( (string) $row['updated_at'] ); ?></td><td><?php echo esc_html( (string) $row['choice'] ); ?></td><td><?php echo $row['analytics'] ? '✓' : '—'; ?></td><td><?php echo $row['advertising'] ? '✓' : '—'; ?></td><td><?php echo $row['external_media'] ? '✓' : '—'; ?></td><td><?php echo esc_html( (string) $row['policy_version'] ); ?></td><td><?php echo $row['gpc'] ? '✓' : '—'; ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kozconsent_log_export' ), 'kozconsent_log_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'koz-consent-manager' ); ?></a>
			<a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kozconsent_log_clear' ), 'kozconsent_log_clear' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete all consent records?', 'koz-consent-manager' ) ); ?>')"><?php esc_html_e( 'Delete all records', 'koz-consent-manager' ); ?></a>
		</p>
		<?php
	}
}
