<?php
namespace ramirkz\kozmig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZMIG_Suite_Registry {
	private const FALLBACK_PAGE = 'kozmig-suite';
	private static array $registered = array();

	public static function register( array $component ): void {
		$slug = isset( $component['slug'] ) ? sanitize_key( (string) $component['slug'] ) : '';
		if ( '' === $slug ) {
			return;
		}
		self::$registered[ $slug ] = array(
			'slug'          => $slug,
			'name'          => sanitize_text_field( (string) ( $component['name'] ?? $slug ) ),
			'version'       => sanitize_text_field( (string) ( $component['version'] ?? '' ) ),
			'settings_page' => sanitize_key( (string) ( $component['settings_page'] ?? '' ) ),
		);
	}

	public static function fallback_page(): string {
		return self::FALLBACK_PAGE;
	}

	private static function existing_suite_page(): string {
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

	public static function suite_page(): string {
		$existing = self::existing_suite_page();
		if ( '' !== $existing ) {
			return $existing;
		}

		add_menu_page(
			__( 'KOZ WordPress Suite', 'koz-migration-cleanup' ),
			__( 'KOZ Suite', 'koz-migration-cleanup' ),
			'manage_options',
			self::FALLBACK_PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-layout',
			58
		);
		return self::FALLBACK_PAGE;
	}

	public static function catalog(): array {
		return array(
			'koz-migration-cleanup' => array( 'name' => 'KOZ Migration & Cleanup', 'legacy' => array( 'ua-free-migration-cleanup' ), 'settings_page' => 'koz-migration-cleanup', 'description' => __( 'Scanning, snapshots, migration and controlled cleanup.', 'koz-migration-cleanup' ) ),
			'koz-static-translate' => array( 'name' => 'KOZ Static Translate', 'legacy' => array( 'ua-free-static-translate' ), 'settings_page' => 'koz-static-translate', 'description' => __( 'Automatic static WordPress translation.', 'koz-migration-cleanup' ) ),
			'koz-translate-diagnostics' => array( 'name' => 'KOZ Translate Diagnostics', 'legacy' => array( 'ua-free-translate-diagnostics' ), 'settings_page' => 'koz-translate-diagnostics', 'description' => __( 'Read-only translation diagnostics.', 'koz-migration-cleanup' ) ),
			'koz-seo-core' => array( 'name' => 'KOZ SEO Core', 'legacy' => array( 'ua-free-seo-core' ), 'settings_page' => 'koz-seo-core', 'description' => __( 'SEO, sitemaps, schema, AI discovery and accessibility.', 'koz-migration-cleanup' ) ),
			'koz-404-guard' => array( 'name' => 'KOZ 404 Guard & URL Intelligence', 'legacy' => array( 'ua-free-404-guard' ), 'settings_page' => 'koz-404-guard', 'description' => __( '404, 410, redirects and URL intelligence.', 'koz-migration-cleanup' ) ),
			'koz-site-bridge' => array( 'name' => 'KOZ Site Bridge', 'legacy' => array( 'ua-free-site-bridge' ), 'settings_page' => 'koz-site-bridge', 'description' => __( 'Secure read-only site diagnostics.', 'koz-migration-cleanup' ) ),
			'koz-google-ads-campaign-builder' => array( 'name' => 'KOZ Google Ads Campaign Builder', 'legacy' => array( 'ua-free-google-ads-campaign-builder' ), 'settings_page' => 'koz-google-ads-campaign-builder', 'description' => __( 'Google Ad Grants and Standard Google Ads packages.', 'koz-migration-cleanup' ) ),
			'koz-donate-stats' => array( 'name' => 'KOZ Donate Stats & Conversions', 'legacy' => array( 'ua-free-donate-stats' ), 'settings_page' => 'koz-donate-stats', 'description' => __( 'Privacy-safe statistics and conversions.', 'koz-migration-cleanup' ) ),
			'koz-copy-actions' => array( 'name' => 'KOZ Copy Actions', 'legacy' => array( 'ua-free-copy' ), 'settings_page' => 'koz-copy-actions', 'description' => __( 'Accessible copying of configured values.', 'koz-migration-cleanup' ) ),
			'koz-url-only-comment-spam' => array( 'name' => 'KOZ URL-Only Comment Spam', 'legacy' => array( 'ua-free-url-only-comment-spam' ), 'settings_page' => 'koz-url-only-comment-spam', 'description' => __( 'Filter for comments containing only URLs.', 'koz-migration-cleanup' ) ),
			'koz-suite-control-center' => array( 'name' => 'KOZ Suite Control Center', 'legacy' => array( 'ua-free-analytics-dashboard', 'ua-free-suite-control-center' ), 'settings_page' => 'koz-suite-control-center', 'description' => __( 'Unified suite status and navigation dashboard.', 'koz-migration-cleanup' ) ),
			'koz-consent-manager' => array( 'name' => 'KOZ Consent Manager', 'legacy' => array( 'ua-free-consent-manager' ), 'settings_page' => 'koz-consent-manager', 'description' => __( 'Consent management and controlled external scripts.', 'koz-migration-cleanup' ) ),
		);
	}

	private static function matching_plugin_file( array $installed, array $prefixes ): string {
		foreach ( array_keys( $installed ) as $candidate ) {
			foreach ( $prefixes as $prefix ) {
				if ( str_starts_with( (string) $candidate, (string) $prefix . '/' ) ) {
					return (string) $candidate;
				}
			}
		}
		return '';
	}

	public static function status(): array {
		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed = get_plugins();
		$result = array();

		foreach ( self::catalog() as $slug => $item ) {
			$koz_file    = self::matching_plugin_file( $installed, array( $slug ) );
			$legacy_file = self::matching_plugin_file( $installed, (array) ( $item['legacy'] ?? array() ) );
			$registered  = self::$registered[ $slug ] ?? array();
			$version     = '';

			if ( '' !== $koz_file ) {
				$version = (string) ( $installed[ $koz_file ]['Version'] ?? '' );
			}
			if ( '' !== (string) ( $registered['version'] ?? '' ) ) {
				$version = (string) $registered['version'];
			}

			$result[ $slug ] = array(
				'slug'          => $slug,
				'name'          => (string) $item['name'],
				'description'   => (string) $item['description'],
				'installed'     => '' !== $koz_file,
				'active'        => '' !== $koz_file && is_plugin_active( $koz_file ),
				'legacy_active' => '' !== $legacy_file && is_plugin_active( $legacy_file ),
				'version'       => $version,
				'settings_page' => sanitize_key( (string) ( $registered['settings_page'] ?? $item['settings_page'] ?? '' ) ),
			);
		}
		return $result;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap kozmig-suite-overview">
			<h1><?php echo esc_html__( 'KOZ WordPress Suite', 'koz-migration-cleanup' ); ?></h1>
			<p><?php echo esc_html__( 'Independent WordPress tools shaped by real production use on the UA FREE charitable foundation website.', 'koz-migration-cleanup' ); ?></p>
			<div class="kozmig-suite-grid">
			<?php foreach ( self::status() as $component ) : ?>
				<div class="kozmig-suite-card">
					<h2><?php echo esc_html( $component['name'] ); ?></h2>
					<p><?php echo esc_html( $component['description'] ); ?></p>
					<p><strong><?php
					if ( $component['active'] ) {
						echo esc_html__( 'Active', 'koz-migration-cleanup' );
					} elseif ( $component['installed'] ) {
						echo esc_html__( 'Installed but inactive', 'koz-migration-cleanup' );
					} elseif ( $component['legacy_active'] ) {
						echo esc_html__( 'Legacy UA FREE version active', 'koz-migration-cleanup' );
					} else {
						echo esc_html__( 'Not installed', 'koz-migration-cleanup' );
					}
					?></strong><?php if ( '' !== $component['version'] ) : ?> <code><?php echo esc_html( $component['version'] ); ?></code><?php endif; ?></p>
					<?php if ( $component['active'] && '' !== $component['settings_page'] ) : ?>
						<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $component['settings_page'] ) ); ?>"><?php echo esc_html__( 'Open', 'koz-migration-cleanup' ); ?></a></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
