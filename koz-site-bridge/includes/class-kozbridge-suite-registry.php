<?php
namespace ramirkz\kozbridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZBRIDGE_Suite_Registry {
	private const FALLBACK_PAGE = 'kozbridge-suite';
	private static array $registered = array();

	public static function register( array $component ): void {
		$slug = isset( $component['slug'] ) ? sanitize_key( (string) $component['slug'] ) : '';
		if ( '' === $slug ) { return; }
		self::$registered[ $slug ] = array(
			'slug' => $slug,
			'name' => sanitize_text_field( (string) ( $component['name'] ?? $slug ) ),
			'version' => sanitize_text_field( (string) ( $component['version'] ?? '' ) ),
			'settings_page' => sanitize_key( (string) ( $component['settings_page'] ?? '' ) ),
		);
	}

	private static function existing_suite_page(): string {
		global $menu;
		foreach ( (array) $menu as $item ) {
			$label = isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : '';
			$slug = isset( $item[2] ) ? (string) $item[2] : '';
			if ( 'KOZ Suite' === trim( $label ) && '' !== $slug ) { return $slug; }
		}
		return '';
	}

	public static function suite_page(): string {
		$existing = self::existing_suite_page();
		if ( '' !== $existing ) { return $existing; }
		add_menu_page(
			__( 'KOZ WordPress Suite', 'koz-site-bridge' ),
			__( 'KOZ Suite', 'koz-site-bridge' ),
			'manage_options', self::FALLBACK_PAGE, array( __CLASS__, 'render' ), 'dashicons-layout', 58
		);
		return self::FALLBACK_PAGE;
	}

	public static function catalog(): array {
		return array(
			'koz-migration-cleanup' => array( 'name' => 'KOZ Migration & Cleanup', 'legacy' => array( 'ua-free-migration-cleanup' ), 'settings_page' => 'koz-migration-cleanup' ),
			'koz-static-translate' => array( 'name' => 'KOZ Static Translate', 'legacy' => array( 'ua-free-static-translate' ), 'settings_page' => 'koz-static-translate' ),
			'koz-translate-diagnostics' => array( 'name' => 'KOZ Translate Diagnostics', 'legacy' => array( 'ua-free-translate-diagnostics' ), 'settings_page' => 'koz-translate-diagnostics' ),
			'koz-404-guard' => array( 'name' => 'KOZ 404 Guard & URL Intelligence', 'legacy' => array( 'ua-free-404-guard' ), 'settings_page' => 'koz-404-guard' ),
			'koz-site-bridge' => array( 'name' => 'KOZ Site Bridge', 'legacy' => array( 'ua-free-site-bridge' ), 'settings_page' => 'koz-site-bridge' ),
			'koz-google-ads-campaign-builder' => array( 'name' => 'KOZ Google Ads Campaign Builder', 'legacy' => array( 'ua-free-google-ads-campaign-builder' ), 'settings_page' => 'koz-google-ads-campaign-builder' ),
			'koz-donate-stats' => array( 'name' => 'KOZ Donate Stats & Conversions', 'legacy' => array( 'ua-free-donate-stats' ), 'settings_page' => 'koz-donate-stats' ),
			'koz-copy-actions' => array( 'name' => 'KOZ Copy Actions', 'legacy' => array( 'ua-free-copy' ), 'settings_page' => 'koz-copy-actions' ),
			'koz-url-only-comment-spam' => array( 'name' => 'KOZ URL-Only Comment Spam', 'legacy' => array( 'ua-free-url-only-comment-spam' ), 'settings_page' => 'koz-url-only-comment-spam' ),
			'koz-suite-control-center' => array( 'name' => 'KOZ Suite Control Center', 'legacy' => array( 'ua-free-analytics-dashboard', 'ua-free-suite-control-center' ), 'settings_page' => 'koz-suite-control-center' ),
			'koz-consent-manager' => array( 'name' => 'KOZ Consent Manager', 'legacy' => array( 'ua-free-consent-manager' ), 'settings_page' => 'koz-consent-manager' ),
		);
	}

	private static function matching_plugin_file( array $installed, array $prefixes ): string {
		foreach ( array_keys( $installed ) as $candidate ) {
			foreach ( $prefixes as $prefix ) {
				if ( str_starts_with( (string) $candidate, (string) $prefix . '/' ) ) { return (string) $candidate; }
			}
		}
		return '';
	}

	public static function status(): array {
		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
		$installed = get_plugins(); $result = array();
		foreach ( self::catalog() as $slug => $item ) {
			$current_file = self::matching_plugin_file( $installed, array( $slug ) );
			$legacy_file = self::matching_plugin_file( $installed, (array) ( $item['legacy'] ?? array() ) );
			$registered = self::$registered[ $slug ] ?? array();
			$version = '' !== $current_file ? (string) ( $installed[ $current_file ]['Version'] ?? '' ) : '';
			if ( '' !== (string) ( $registered['version'] ?? '' ) ) { $version = (string) $registered['version']; }
			$result[ $slug ] = array(
				'slug' => $slug, 'name' => (string) $item['name'], 'installed' => '' !== $current_file,
				'active' => '' !== $current_file && is_plugin_active( $current_file ),
				'legacy_active' => '' !== $legacy_file && is_plugin_active( $legacy_file ),
				'version' => $version,
				'settings_page' => sanitize_key( (string) ( $registered['settings_page'] ?? $item['settings_page'] ?? '' ) ),
			);
		}
		return $result;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		?>
		<div class="wrap kozbridge-suite-overview"><h1><?php echo esc_html__( 'KOZ WordPress Suite', 'koz-site-bridge' ); ?></h1>
		<p><?php echo esc_html__( 'Independent WordPress tools shaped by real production use on the UA FREE charitable foundation website.', 'koz-site-bridge' ); ?></p>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:16px;max-width:1400px">
		<?php foreach ( self::status() as $component ) : ?><div style="background:#fff;border:1px solid #dcdcde;padding:16px"><h2 style="margin-top:0"><?php echo esc_html( $component['name'] ); ?></h2><p><strong><?php echo $component['active'] ? esc_html__( 'Active', 'koz-site-bridge' ) : ( $component['installed'] ? esc_html__( 'Installed but inactive', 'koz-site-bridge' ) : ( $component['legacy_active'] ? esc_html__( 'Legacy UA FREE version active', 'koz-site-bridge' ) : esc_html__( 'Not installed', 'koz-site-bridge' ) ) ); ?></strong><?php if ( '' !== $component['version'] ) : ?> <code><?php echo esc_html( $component['version'] ); ?></code><?php endif; ?></p><?php if ( $component['active'] && '' !== $component['settings_page'] ) : ?><p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $component['settings_page'] ) ); ?>"><?php echo esc_html__( 'Open', 'koz-site-bridge' ); ?></a></p><?php endif; ?></div><?php endforeach; ?>
		</div></div><?php
	}
}
