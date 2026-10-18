<?php
/**
 * Front end: assets, browser configuration, banner, shortcodes and the delayed
 * loading of registered script handles.
 *
 * The banner is always rendered hidden and shown by JavaScript when no valid choice
 * exists, so cached pages never carry another visitor's state.
 *
 * @package KOZ_Consent_Manager
 */

namespace ramirkz\kozconsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZCONSENT_Frontend {
	private static array $frontend_integrations = array();

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'prepare_registered_scripts' ), PHP_INT_MAX );
		add_action( 'wp_print_scripts', array( __CLASS__, 'prepare_registered_scripts' ), PHP_INT_MAX );
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'prepare_registered_scripts' ), 1 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'filter_script_tag' ), PHP_INT_MAX, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 100 );
		add_shortcode( 'kozconsent_settings', array( __CLASS__, 'shortcode_settings' ) );
		add_shortcode( 'kozconsent_services', array( __CLASS__, 'shortcode_services' ) );
	}

	private static function enabled(): bool {
		return ! empty( KOZCONSENT_Settings::stored()['enabled'] );
	}

	public static function enqueue_assets(): void {
		if ( ! self::enabled() ) {
			return;
		}
		$s = KOZCONSENT_Settings::stored();
		wp_enqueue_style( 'kozconsent-consent', KOZCONSENT_URL . 'assets/consent-manager.css', array(), KOZCONSENT_VERSION );
		wp_add_inline_style( 'kozconsent-consent', ':root{--kozconsent-accent:' . KOZCONSENT_Settings::sanitize_color( (string) $s['accent_color'] ) . ';}' );
		wp_enqueue_script( 'kozconsent-consent', KOZCONSENT_URL . 'assets/consent-manager.js', array(), KOZCONSENT_VERSION, true );
	}

	public static function prepare_registered_scripts(): void {
		foreach ( KOZCONSENT_Manager::normalized_integrations() as $integration ) {
			$scripts = array();
			foreach ( $integration['script_handles'] as $handle ) {
				$record = self::script_record( $handle );
				if ( null !== $record ) {
					$scripts[] = $record;
				}
				wp_dequeue_script( $handle );
			}
			if ( ! empty( $scripts ) || ! isset( self::$frontend_integrations[ $integration['id'] ] ) ) {
				self::$frontend_integrations[ $integration['id'] ] = array(
					'id'       => $integration['id'],
					'name'     => $integration['name'],
					'category' => $integration['category'],
					'scripts'  => $scripts,
				);
			}
		}
		self::localize();
	}

	private static function script_record( string $handle ): ?array {
		$wp_scripts = wp_scripts();
		if ( ! isset( $wp_scripts->registered[ $handle ] ) ) {
			return null;
		}
		$dependency = $wp_scripts->registered[ $handle ];
		$extra      = is_array( $dependency->extra ?? null ) ? $dependency->extra : array();
		$src        = is_string( $dependency->src ?? null ) ? $dependency->src : '';
		if ( '' !== $src && str_starts_with( $src, '/' ) && ! str_starts_with( $src, '//' ) ) {
			$src = home_url( $src );
		} elseif ( '' !== $src && ! preg_match( '#^(?:https?:)?//#i', $src ) ) {
			$src = rtrim( (string) $wp_scripts->base_url, '/' ) . '/' . ltrim( $src, '/' );
		}
		if ( '' !== $src && ! empty( $dependency->ver ) ) {
			$src = add_query_arg( 'ver', (string) $dependency->ver, $src );
		}
		$clean = static function ( mixed $value ): array {
			$values = is_array( $value ) ? $value : ( is_string( $value ) && '' !== $value ? array( $value ) : array() );
			return array_values( array_filter( $values, 'is_string' ) );
		};
		$before = $clean( $extra['before'] ?? array() );
		$data   = $clean( $extra['data'] ?? array() );
		$after  = $clean( $extra['after'] ?? array() );
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

	public static function config(): array {
		$s = KOZCONSENT_Settings::stored();
		return array(
			'cookieName'        => KOZCONSENT_Manager::COOKIE_NAME,
			'legacyCookieName'  => KOZCONSENT_Manager::legacy_cookie_name(),
			'cookieLifetime'    => (int) $s['cookie_lifetime_days'],
			'policyVersion'     => (string) $s['policy_version'],
			'secureCookie'      => is_ssl(),
			'debug'             => ! empty( $s['debug_mode'] ),
			'googleConsentMode' => ! empty( $s['google_consent_mode'] ),
			'microsoft'         => ! empty( $s['microsoft_consent'] ),
			'events'            => ! empty( $s['datalayer_events'] ),
			'wpConsentApi'      => ! empty( $s['wp_consent_api'] ),
			'wpConsentMap'      => KOZCONSENT_WP_Consent_API::mapping(),
			'honorGpc'          => ! empty( $s['honor_gpc'] ),
			'youtubeNocookie'   => ! empty( $s['youtube_nocookie'] ),
			'layout'            => (string) $s['layout'],
			'log'               => array(
				'enabled'  => ! empty( $s['log_enabled'] ),
				'endpoint' => esc_url_raw( rest_url( KOZCONSENT_Log::REST_NAMESPACE . '/log' ) ),
			),
			'integrations'      => array_values( self::$frontend_integrations ),
		);
	}

	private static function localize(): void {
		if ( ! self::enabled() || ! wp_script_is( 'kozconsent-consent', 'enqueued' ) ) {
			return;
		}
		$wp_scripts = wp_scripts();
		$json       = 'var KOZCONSENTConfig = ' . wp_json_encode( self::config() ) . ';';
		// Replace instead of appending: this runs up to three times per request.
		$wp_scripts->add_data( 'kozconsent-consent', 'data', $json );
	}

	public static function filter_script_tag( string $tag, string $handle ): string {
		foreach ( KOZCONSENT_Manager::normalized_integrations() as $integration ) {
			if ( in_array( $handle, $integration['script_handles'], true ) ) {
				return '';
			}
		}
		return $tag;
	}

	public static function render(): void {
		if ( ! self::enabled() ) {
			return;
		}
		$s      = KOZCONSENT_Manager::settings();
		$layout = in_array( $s['layout'], KOZCONSENT_Settings::LAYOUTS, true ) ? $s['layout'] : 'bar';
		$modal  = 'modal' === $layout;
		$rtl    = 'ar' === $s['language'];
		$categories = array(
			'analytics'      => array( __( 'Analytics', 'koz-consent-manager' ), __( 'Helps understand site usage.', 'koz-consent-manager' ) ),
			'advertising'    => array( __( 'Advertising', 'koz-consent-manager' ), __( 'Allows advertising and conversion scripts.', 'koz-consent-manager' ) ),
			'external_media' => array( __( 'External media', 'koz-consent-manager' ), __( 'Loads videos, maps and other content from other services.', 'koz-consent-manager' ) ),
		);
		?>
		<div id="kozconsent" class="kozconsent kozconsent--<?php echo esc_attr( $layout ); ?>" hidden<?php echo $rtl ? ' dir="rtl"' : ''; ?> lang="<?php echo esc_attr( $s['language'] ); ?>">
			<div class="kozconsent__panel" role="dialog" aria-modal="<?php echo $modal ? 'true' : 'false'; ?>" aria-labelledby="kozconsent-title" aria-describedby="kozconsent-message" tabindex="-1">
				<h2 id="kozconsent-title"><?php echo esc_html( $s['banner_title'] ); ?></h2>
				<p id="kozconsent-message"><?php echo esc_html( $s['banner_message'] ); ?></p>
				<p id="kozconsent-gpc" class="kozconsent__gpc" hidden><?php esc_html_e( 'Your browser sends a Global Privacy Control signal, so advertising stays off unless you turn it on below.', 'koz-consent-manager' ); ?></p>
				<div id="kozconsent-preferences" class="kozconsent__preferences" hidden>
					<label class="kozconsent__choice"><input type="checkbox" checked disabled> <span><?php esc_html_e( 'Necessary', 'koz-consent-manager' ); ?></span><small><?php esc_html_e( 'Always active for core site functions.', 'koz-consent-manager' ); ?></small></label>
					<?php foreach ( $categories as $key => $labels ) : ?>
						<label class="kozconsent__choice"><input id="kozconsent-<?php echo esc_attr( str_replace( '_', '-', $key ) ); ?>" type="checkbox"> <span><?php echo esc_html( $labels[0] ); ?></span><small><?php echo esc_html( $labels[1] ); ?></small></label>
					<?php endforeach; ?>
				</div>
				<div class="kozconsent__actions">
					<button type="button" class="kozconsent__button kozconsent__button--primary" data-kozconsent="accept"><?php echo esc_html( $s['accept_label'] ); ?></button>
					<button type="button" class="kozconsent__button kozconsent__button--primary" data-kozconsent="reject"><?php echo esc_html( $s['reject_label'] ); ?></button>
					<button type="button" class="kozconsent__button" data-kozconsent="customize" aria-expanded="false" aria-controls="kozconsent-preferences"><?php echo esc_html( $s['customize_label'] ); ?></button>
					<button type="button" class="kozconsent__button kozconsent__button--primary" data-kozconsent="save" hidden><?php echo esc_html( $s['save_label'] ); ?></button>
				</div>
				<?php if ( function_exists( 'get_privacy_policy_url' ) && get_privacy_policy_url() ) : ?>
					<p class="kozconsent__privacy"><a href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php esc_html_e( 'Privacy policy', 'koz-consent-manager' ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( 'floating' === $s['reopen'] ) : ?>
			<button type="button" id="kozconsent-reopen" class="kozconsent-reopen" aria-controls="kozconsent" hidden><?php echo esc_html( $s['reopen_label'] ); ?></button>
		<?php endif; ?>
		<?php
	}

	/** [kozconsent_settings label="…"]: a button that opens the consent settings. */
	public static function shortcode_settings( $atts ): string {
		$atts  = shortcode_atts( array( 'label' => '' ), is_array( $atts ) ? $atts : array(), 'kozconsent_settings' );
		$label = '' !== trim( (string) $atts['label'] ) ? (string) $atts['label'] : (string) KOZCONSENT_Manager::settings()['reopen_label'];
		return '<button type="button" class="kozconsent-open">' . esc_html( $label ) . '</button>';
	}

	/** [kozconsent_services]: the consent categories and the services that depend on them. */
	public static function shortcode_services(): string {
		$categories = array(
			'necessary'      => __( 'Necessary', 'koz-consent-manager' ),
			'analytics'      => __( 'Analytics', 'koz-consent-manager' ),
			'advertising'    => __( 'Advertising', 'koz-consent-manager' ),
			'external_media' => __( 'External media', 'koz-consent-manager' ),
		);
		$services = array( 'necessary' => array( 'KOZ Consent Manager (kozconsent)' ) );
		foreach ( KOZCONSENT_Scanner::last_services() as $service ) {
			$services[ $service['category'] ][] = $service['name'];
		}
		foreach ( KOZCONSENT_Manager::public_integrations() as $integration ) {
			$services[ $integration['category'] ][] = $integration['name'];
		}
		$html = '<dl class="kozconsent-services">';
		foreach ( $categories as $key => $label ) {
			$names = array_values( array_unique( $services[ $key ] ?? array() ) );
			$html .= '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( empty( $names ) ? '—' : implode( ', ', $names ) ) . '</dd>';
		}
		return $html . '</dl>';
	}
}
