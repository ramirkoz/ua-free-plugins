<?php
/**
 * Canonical UA FREE Plugin Suite registry and admin menu.
 *
 * This file is intentionally byte-identical in every public component so
 * whichever plugin loads first provides the same shared implementation.
 */
namespace UAFree\Suite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Registry', false ) ) {
final class Registry {
	private const MENU_SLUG = 'uafree-suite';
	private static bool $booted = false;
	private static array $registered = array();

	public static function register( array $component ): void {
		$slug = isset( $component['slug'] ) ? sanitize_key( (string) $component['slug'] ) : '';
		if ( '' === $slug ) { return; }
		self::$registered[ $slug ] = array(
			'slug'          => $slug,
			'name'          => sanitize_text_field( (string) ( $component['name'] ?? $slug ) ),
			'version'       => sanitize_text_field( (string) ( $component['version'] ?? '' ) ),
			'settings_page' => sanitize_key( (string) ( $component['settings_page'] ?? '' ) ),
		);
		if ( ! self::$booted ) {
			self::$booted = true;
			add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 0 );
		}
	}

	private static function is_ukrainian(): bool {
		return str_starts_with( strtolower( determine_locale() ), 'uk' );
	}

	private static function t( string $uk, string $en ): string {
		return self::is_ukrainian() ? $uk : $en;
	}

	public static function register_menu(): void {
		global $admin_page_hooks;
		if ( ! empty( $admin_page_hooks[ self::MENU_SLUG ] ) ) { return; }
		add_menu_page(
			'UA FREE Plugin Suite',
			'UA FREE',
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-admin-plugins',
			58
		);
	}

	public static function catalog(): array {
		return array(
			'ua-free-migration-cleanup' => array( 'name' => 'UA FREE Migration & Cleanup', 'uk' => 'Сканування, snapshots, міграція та контрольоване очищення.', 'en' => 'Scanning, snapshots, migration and controlled cleanup.' ),
			'ua-free-static-translate' => array( 'name' => 'UA FREE Static Translate', 'uk' => 'Автоматичний статичний переклад WordPress.', 'en' => 'Automatic static WordPress translation.' ),
			'ua-free-translate-diagnostics' => array( 'name' => 'UA FREE Translate Diagnostics', 'uk' => 'Read-only діагностика системи перекладу.', 'en' => 'Read-only translation diagnostics.' ),
			'ua-free-seo-core' => array( 'name' => 'UA FREE SEO Core', 'uk' => 'SEO, sitemap, schema, AI discovery та accessibility.', 'en' => 'SEO, sitemaps, schema, AI discovery and accessibility.' ),
			'ua-free-404-guard' => array( 'name' => 'UA FREE 404 Guard & URL Intelligence', 'uk' => '404, 410, redirects та аналіз URL.', 'en' => '404, 410, redirects and URL intelligence.' ),
			'ua-free-site-bridge' => array( 'name' => 'UA FREE Site Bridge', 'uk' => 'Захищена read-only діагностика сайту.', 'en' => 'Secure read-only site diagnostics.' ),
			'ua-free-google-ads-campaign-builder' => array( 'name' => 'UA FREE Google Ads Campaign Builder', 'uk' => 'Пакети Google Ad Grants і Standard Google Ads.', 'en' => 'Google Ad Grants and Standard Google Ads packages.' ),
			'ua-free-donate-stats' => array( 'name' => 'UA FREE Donate Stats & Conversions', 'uk' => 'Privacy-safe статистика та конверсії.', 'en' => 'Privacy-safe statistics and conversions.' ),
			'ua-free-copy' => array( 'name' => 'UA FREE Copy', 'uk' => 'Легке копіювання визначених значень.', 'en' => 'Lightweight copying of configured values.' ),
			'ua-free-url-only-comment-spam' => array( 'name' => 'UA FREE URL-Only Comment Spam', 'uk' => 'Фільтр коментарів, що складаються лише з URL.', 'en' => 'Filter for comments containing only URLs.' ),
			'ua-free-analytics-dashboard' => array( 'name' => 'UA FREE Suite Control Center', 'uk' => 'Стан усієї збірки та наступні дії.', 'en' => 'Suite status and next actions.' ),
			'ua-free-consent-manager' => array( 'name' => 'UA FREE Consent Manager', 'uk' => 'Керування згодою та зовнішніми скриптами.', 'en' => 'Consent management and controlled external scripts.' ),
		);
	}

	public static function status(): array {
		if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
		$installed = get_plugins();
		$active = (array) get_option( 'active_plugins', array() );
		$result = array();
		foreach ( self::catalog() as $slug => $item ) {
			$file = '';
			foreach ( array_keys( $installed ) as $candidate ) {
				if ( str_starts_with( (string) $candidate, $slug . '/' ) ) { $file = (string) $candidate; break; }
			}
			$registered = self::$registered[ $slug ] ?? array();
			$result[ $slug ] = array(
				'slug'          => $slug,
				'name'          => (string) $item['name'],
				'description'   => self::is_ukrainian() ? (string) $item['uk'] : (string) $item['en'],
				'installed'     => '' !== $file,
				'active'        => '' !== $file && in_array( $file, $active, true ),
				'version'       => (string) ( $registered['version'] ?? ( '' !== $file ? ( $installed[ $file ]['Version'] ?? '' ) : '' ) ),
				'settings_page' => (string) ( $registered['settings_page'] ?? '' ),
			);
		}
		return $result;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		?>
		<div class="wrap">
		<h1>UA FREE Plugin Suite</h1>
		<p><?php echo esc_html( self::t( 'Інструменти створено для реальних потреб сайту благодійного фонду та перероблено як незалежні універсальні WordPress-плагіни.', 'The tools were created for the real needs of a charitable foundation website and rebuilt as independent universal WordPress plugins.' ) ); ?></p>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:16px;max-width:1400px">
		<?php foreach ( self::status() as $component ) : ?>
			<div style="background:#fff;border:1px solid #dcdcde;padding:16px">
				<h2 style="margin-top:0"><?php echo esc_html( $component['name'] ); ?></h2>
				<p><?php echo esc_html( $component['description'] ); ?></p>
				<p><strong><?php echo esc_html( $component['active'] ? self::t( 'Активний', 'Active' ) : ( $component['installed'] ? self::t( 'Встановлений, але неактивний', 'Installed but inactive' ) : self::t( 'Не встановлений', 'Not installed' ) ) ); ?></strong><?php if ( '' !== $component['version'] ) : ?> <code><?php echo esc_html( $component['version'] ); ?></code><?php endif; ?></p>
				<?php if ( $component['active'] && '' !== $component['settings_page'] ) : ?><p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $component['settings_page'] ) ); ?>"><?php echo esc_html( self::t( 'Відкрити', 'Open' ) ); ?></a></p><?php endif; ?>
			</div>
		<?php endforeach; ?>
		</div></div>
		<?php
	}
}
}
