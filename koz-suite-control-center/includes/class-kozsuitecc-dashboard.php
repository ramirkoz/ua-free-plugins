<?php
/**
 * KOZ Suite status and navigation dashboard.
 */

declare(strict_types=1);

namespace ramirkz\kozsuitecc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZSUITECC_Dashboard {
	private const ROOT_PAGE = 'kozsuitecc-suite';
	private const CONTROL_PAGE = 'kozsuitecc-control-center';
	private const LEGACY_CONTROL_PAGE = 'koz-suite-control-center';
	private const EXPORT_ACTION = 'kozsuitecc_export';
	private const PERIODS = array( 7, 30, 90 );
	private const LEGACY_HEARTBEAT_HOOK = 'uafree_suite_daily_heartbeat';
	private const MIGRATION_OPTION = 'kozsuitecc_migration_version';
	private const LEGACY_MIGRATION_OPTION = 'kozsuitecontrolcenter_migration_version';
	private const EXPOSURE_SCAN_NONCE = 'kozsuitecc_public_exposure_scan';
	private const EXPOSURE_SCAN_LIMIT = 6;

	private static array $cache = array();

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'ensure_root_menu' ), 1 );
		add_action( 'admin_menu', array( __CLASS__, 'register_submenu' ), 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'remove_legacy_telemetry' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'export_json' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( KOZSUITECC_FILE ),
			array( __CLASS__, 'action_links' )
		);
	}

	public static function activate(): void {
		self::deactivate_legacy_plugin();
		self::remove_legacy_telemetry();
	}

	public static function deactivate(): void {
		self::unschedule_legacy_heartbeat();
	}

	private static function deactivate_legacy_plugin(): void {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach (
			array(
				'ua-free-analytics-dashboard/ua-free-analytics-dashboard.php',
				'ua-free-suite-control-center/ua-free-suite-control-center.php',
			)
			as $legacy_file
		) {
			if ( is_plugin_active( $legacy_file ) ) {
				deactivate_plugins( $legacy_file, true );
			}
		}
	}

	public static function remove_legacy_telemetry(): void {
		if ( KOZSUITECC_VERSION === (string) get_option( self::MIGRATION_OPTION, '' ) ) {
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
		update_option( self::MIGRATION_OPTION, KOZSUITECC_VERSION, false );
		delete_option( self::LEGACY_MIGRATION_OPTION );
	}

	private static function unschedule_legacy_heartbeat(): void {
		while ( false !== ( $timestamp = wp_next_scheduled( self::LEGACY_HEARTBEAT_HOOK ) ) ) {
			if ( false === wp_unschedule_event( $timestamp, self::LEGACY_HEARTBEAT_HOOK ) ) {
				break;
			}
		}
	}

	public static function ensure_root_menu(): void {
		add_menu_page(
			__( 'KOZ WordPress Suite', 'koz-suite-control-center' ),
			__( 'KOZ Suite', 'koz-suite-control-center' ),
			'manage_options',
			self::ROOT_PAGE,
			array( __CLASS__, 'render_suite_page' ),
			'dashicons-layout',
			58
		);
	}

	public static function register_submenu(): void {
		add_submenu_page(
			self::ROOT_PAGE,
			__( 'Suite Control Center', 'koz-suite-control-center' ),
			__( 'Control Center', 'koz-suite-control-center' ),
			'manage_options',
			self::CONTROL_PAGE,
			array( __CLASS__, 'render_dashboard' )
		);

		// Preserve the previous Control Center direct URL without exposing a duplicate menu item.
		add_submenu_page(
			self::ROOT_PAGE,
			__( 'Suite Control Center', 'koz-suite-control-center' ),
			__( 'Control Center', 'koz-suite-control-center' ),
			'manage_options',
			self::LEGACY_CONTROL_PAGE,
			array( __CLASS__, 'render_dashboard' )
		);
		remove_submenu_page( self::ROOT_PAGE, self::LEGACY_CONTROL_PAGE );
	}

	public static function enqueue_assets(): void {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_koz_screen() ) {
			return;
		}
		wp_enqueue_style(
			'kozsuitecc-admin',
			KOZSUITECC_URL . 'assets/admin.css',
			array(),
			KOZSUITECC_VERSION
		);
	}

	private static function is_koz_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! is_object( $screen ) ) {
			return false;
		}

		$screen_id = strtolower( (string) $screen->id );
		return str_contains( $screen_id, self::ROOT_PAGE )
			|| str_contains( $screen_id, self::CONTROL_PAGE )
			|| str_contains( $screen_id, self::LEGACY_CONTROL_PAGE );
	}

	/** @return array<string,array<string,mixed>> */
	private static function catalog(): array {
		return array(
			'koz-migration-cleanup' => array(
				'name' => 'KOZ Migration & Cleanup',
				'description' => __( 'Scanning, snapshots, migration and controlled cleanup.', 'koz-suite-control-center' ),
				'page' => 'koz-migration-cleanup',
				'legacy' => array( 'ua-free-migration-cleanup' ),
			),
			'koz-static-translate' => array(
				'name' => 'KOZ Static Translate',
				'description' => __( 'Automatic static WordPress translation.', 'koz-suite-control-center' ),
				'page' => 'koz-static-translate',
				'legacy' => array( 'ua-free-static-translate' ),
			),
			'koz-translate-diagnostics' => array(
				'name' => 'KOZ Translate Diagnostics',
				'description' => __( 'Read-only translation diagnostics.', 'koz-suite-control-center' ),
				'page' => 'koz-translate-diagnostics',
				'legacy' => array( 'ua-free-translate-diagnostics' ),
			),
			'koz-seo-core' => array(
				'name' => 'KOZ SEO Core',
				'description' => __( 'SEO, sitemaps, schema, AI discovery and accessibility.', 'koz-suite-control-center' ),
				'page' => 'koz-seo-core',
				'legacy' => array( 'ua-free-seo-core' ),
			),
			'koz-404-guard' => array(
				'name' => 'KOZ 404 Guard & URL Intelligence',
				'description' => __( '404, 410, redirects and URL intelligence.', 'koz-suite-control-center' ),
				'page' => 'koz-404-guard',
				'legacy' => array( 'ua-free-404-guard' ),
			),
			'koz-site-bridge' => array(
				'name' => 'KOZ Site Bridge',
				'description' => __( 'Secure read-only site diagnostics.', 'koz-suite-control-center' ),
				'page' => 'koz-site-bridge',
				'legacy' => array( 'ua-free-site-bridge' ),
			),
			'koz-google-ads-campaign-builder' => array(
				'name' => 'KOZ Google Ads Campaign Builder',
				'description' => __( 'Google Ad Grants and Standard Google Ads packages.', 'koz-suite-control-center' ),
				'page' => 'koz-google-ads-campaign-builder',
				'legacy' => array( 'ua-free-google-ads-campaign-builder' ),
			),
			'koz-donate-stats' => array(
				'name' => 'KOZ Donate Stats & Conversions',
				'description' => __( 'Privacy-safe donation statistics and conversions.', 'koz-suite-control-center' ),
				'page' => 'koz-donate-stats',
				'legacy' => array( 'ua-free-donate-stats' ),
			),
			'koz-copy-actions' => array(
				'name' => 'KOZ Copy Actions',
				'description' => __( 'Accessible copying of configured values.', 'koz-suite-control-center' ),
				'page' => 'koz-copy-actions',
				'legacy' => array( 'ua-free-copy' ),
			),
			'koz-url-only-comment-spam' => array(
				'name' => 'KOZ URL-Only Comment Spam',
				'description' => __( 'Filter for comments containing only URLs.', 'koz-suite-control-center' ),
				'page' => 'koz-url-only-comment-spam',
				'legacy' => array( 'ua-free-url-only-comment-spam' ),
			),
			'koz-suite-control-center' => array(
				'name' => 'KOZ Suite Control Center',
				'description' => __( 'Unified suite status, navigation and reporting.', 'koz-suite-control-center' ),
				'page' => self::CONTROL_PAGE,
				'legacy' => array( 'ua-free-analytics-dashboard', 'ua-free-suite-control-center' ),
			),
			'koz-consent-manager' => array(
				'name' => 'KOZ Consent Manager',
				'description' => __( 'Consent management and controlled external scripts.', 'koz-suite-control-center' ),
				'page' => 'koz-consent-manager',
				'legacy' => array( 'ua-free-consent-manager' ),
			),
		);
	}

	/** @return array<int,string> */
	private static function matching_files( array $installed, array $prefixes ): array {
		$matches = array();
		foreach ( array_keys( $installed ) as $candidate ) {
			foreach ( $prefixes as $prefix ) {
				if ( str_starts_with( (string) $candidate, (string) $prefix . '/' ) ) {
					$matches[] = (string) $candidate;
					break;
				}
			}
		}
		return array_values( array_unique( $matches ) );
	}

	/** @return array<string,array<string,mixed>> */
	public static function status(): array {
		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed = get_plugins();
		$result = array();
		foreach ( self::catalog() as $slug => $item ) {
			$koz_files = self::matching_files( $installed, array( $slug ) );
			$legacy_files = self::matching_files( $installed, (array) $item['legacy'] );
			$active_file = '';
			foreach ( $koz_files as $file ) {
				if ( is_plugin_active( $file ) || ( is_multisite() && is_plugin_active_for_network( $file ) ) ) {
					$active_file = $file;
					break;
				}
			}
			$legacy_active = false;
			foreach ( $legacy_files as $file ) {
				if ( is_plugin_active( $file ) || ( is_multisite() && is_plugin_active_for_network( $file ) ) ) {
					$legacy_active = true;
					break;
				}
			}
			$version_file = '' !== $active_file ? $active_file : ( $koz_files[0] ?? '' );
			$result[ $slug ] = array(
				'slug' => $slug,
				'name' => (string) $item['name'],
				'description' => (string) $item['description'],
				'page' => sanitize_key( (string) $item['page'] ),
				'installed' => ! empty( $koz_files ),
				'active' => '' !== $active_file,
				'version' => '' !== $version_file ? sanitize_text_field( (string) ( $installed[ $version_file ]['Version'] ?? '' ) ) : '',
				'legacy_active' => $legacy_active,
				'instances' => count( $koz_files ),
			);
		}
		return $result;
	}

	/** @return array<string,mixed> */
	private static function report( int $days = 30 ): array {
		$days = self::normalize_days( $days );
		if ( isset( self::$cache[ $days ] ) ) {
			return self::$cache[ $days ];
		}
		$status = self::status();
		$active = count( array_filter( $status, static fn( array $row ): bool => ! empty( $row['active'] ) ) );
		$installed = count( array_filter( $status, static fn( array $row ): bool => ! empty( $row['installed'] ) ) );
		$legacy_active = count( array_filter( $status, static fn( array $row ): bool => ! empty( $row['legacy_active'] ) ) );
		$report = array(
			'plugin' => array(
				'name' => 'KOZ Suite Control Center',
				'version' => KOZSUITECC_VERSION,
				'read_only' => true,
				'contract_version' => 1,
			),
			'generated_at' => current_time( 'c' ),
			'period_days' => $days,
			'locale' => sanitize_text_field( (string) determine_locale() ),
			'summary' => array(
				'active' => $active,
				'installed' => $installed,
				'missing' => count( $status ) - $installed,
				'inactive' => $installed - $active,
				'legacy_active' => $legacy_active,
				'total' => count( $status ),
			),
			'suite' => $status,
			'privacy' => array(
				'external_requests' => false,
				'tracking_cookies' => false,
				'frontend_hooks' => false,
				'new_tables' => false,
				'event_storage' => false,
				'personal_data' => false,
				'secrets' => false,
			),
		);
		self::$cache[ $days ] = $report;
		return $report;
	}

	private static function normalize_days( int $days ): int {
		return in_array( $days, self::PERIODS, true ) ? $days : 30;
	}

	public static function render_suite_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$status = self::status();
		$active_count = count( array_filter( $status, static fn( array $row ): bool => ! empty( $row['active'] ) ) );
		?>
		<div class="wrap kozsuitecc-suite">
			<div class="kozsuitecc-title-row">
				<div>
					<h1><?php echo esc_html__( 'KOZ WordPress Suite', 'koz-suite-control-center' ); ?></h1>
					<p><?php echo esc_html__( 'Independent WordPress tools shaped by production use and maintained as one coherent suite.', 'koz-suite-control-center' ); ?></p>
				</div>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::CONTROL_PAGE ) ); ?>"><?php echo esc_html__( 'Open Control Center', 'koz-suite-control-center' ); ?></a>
			</div>
			<p class="kozsuitecc-count"><strong><?php echo esc_html( (string) $active_count ); ?> / <?php echo esc_html( (string) count( $status ) ); ?></strong> <?php echo esc_html__( 'plugins active', 'koz-suite-control-center' ); ?></p>
			<div class="kozsuitecc-grid">
				<?php foreach ( $status as $component ) : ?>
					<?php $state = $component['active'] ? 'active' : ( $component['installed'] ? 'installed' : 'missing' ); ?>
					<article class="kozsuitecc-card is-<?php echo esc_attr( $state ); ?>">
						<h2><?php echo esc_html( (string) $component['name'] ); ?></h2>
						<p><?php echo esc_html( (string) $component['description'] ); ?></p>
						<p class="kozsuitecc-state"><strong>
							<?php
							if ( $component['active'] ) {
								echo esc_html__( 'Active', 'koz-suite-control-center' );
							} elseif ( $component['installed'] ) {
								echo esc_html__( 'Installed but inactive', 'koz-suite-control-center' );
							} elseif ( $component['legacy_active'] ) {
								echo esc_html__( 'Legacy package active', 'koz-suite-control-center' );
							} else {
								echo esc_html__( 'Not installed', 'koz-suite-control-center' );
							}
							?>
						</strong><?php if ( '' !== $component['version'] ) : ?> <code><?php echo esc_html( (string) $component['version'] ); ?></code><?php endif; ?></p>
						<?php if ( $component['active'] && '' !== $component['page'] ) : ?>
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $component['page'] ) ); ?>"><?php echo esc_html__( 'Open', 'koz-suite-control-center' ); ?></a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	public static function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this dashboard.', 'koz-suite-control-center' ) );
		}
		$days = self::requested_days();
		$report = self::report( $days );
		$summary = (array) $report['summary'];
		$all_active = (int) $summary['active'] === (int) $summary['total'];
		?>
		<div class="wrap kozsuitecc-dashboard">
			<h1><?php echo esc_html__( 'KOZ Suite Control Center', 'koz-suite-control-center' ); ?></h1>
			<div class="kozsuitecc-hero <?php echo $all_active ? 'is-good' : 'is-attention'; ?>">
				<span><?php echo esc_html__( 'Suite status', 'koz-suite-control-center' ); ?></span>
				<strong><?php echo esc_html( $all_active ? __( 'Everything is active', 'koz-suite-control-center' ) : __( 'The suite needs attention', 'koz-suite-control-center' ) ); ?></strong>
				<p><?php echo esc_html__( 'This screen reports installed packages and does not send data outside WordPress.', 'koz-suite-control-center' ); ?></p>
			</div>
			<div class="kozsuitecc-metrics">
				<div><span><?php echo esc_html__( 'Active', 'koz-suite-control-center' ); ?></span><strong><?php echo esc_html( (string) $summary['active'] ); ?> / <?php echo esc_html( (string) $summary['total'] ); ?></strong></div>
				<div><span><?php echo esc_html__( 'Inactive', 'koz-suite-control-center' ); ?></span><strong><?php echo esc_html( (string) $summary['inactive'] ); ?></strong></div>
				<div><span><?php echo esc_html__( 'Missing', 'koz-suite-control-center' ); ?></span><strong><?php echo esc_html( (string) $summary['missing'] ); ?></strong></div>
			</div>

			<section class="kozsuitecc-section">
				<h2><?php echo esc_html__( 'Needs attention', 'koz-suite-control-center' ); ?></h2>
				<div class="kozsuitecc-list">
				<?php
				$attention = 0;
				foreach ( (array) $report['suite'] as $component ) {
					if ( $component['active'] && ! $component['legacy_active'] ) {
						continue;
					}
					++$attention;
					$level = $component['installed'] ? 'warning' : 'info';
					$message = $component['installed']
						? __( 'The KOZ package is installed but inactive.', 'koz-suite-control-center' )
						: __( 'The KOZ package is not installed.', 'koz-suite-control-center' );
					if ( $component['legacy_active'] ) {
						$message = __( 'A legacy package is still active. Finish the migration before removing it.', 'koz-suite-control-center' );
						$level = 'warning';
					}
					?>
					<article class="kozsuitecc-item is-<?php echo esc_attr( $level ); ?>">
						<h3><?php echo esc_html( (string) $component['name'] ); ?></h3>
						<p><?php echo esc_html( $message ); ?></p>
						<?php if ( $component['active'] && '' !== $component['page'] ) : ?><a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $component['page'] ) ); ?>"><?php echo esc_html__( 'Open', 'koz-suite-control-center' ); ?></a><?php endif; ?>
					</article>
					<?php
				}
				if ( 0 === $attention ) {
					echo '<p class="kozsuitecc-empty">' . esc_html__( 'Nothing requires attention.', 'koz-suite-control-center' ) . '</p>';
				}
				?>
				</div>
			</section>


			<section class="kozsuitecc-section">
				<h2><?php echo esc_html__( 'Public exposure scan', 'koz-suite-control-center' ); ?></h2>
				<p><?php echo esc_html__( 'Read-only check of likely public KOZ/plugin download pages for tokenized private-download or sensitive admin-post URLs. Secret values are never displayed or stored.', 'koz-suite-control-center' ); ?></p>
				<?php
				$exposure_scan = self::requested_exposure_scan();
				if ( null === $exposure_scan ) :
					?>
					<p><a class="button" href="<?php echo esc_url( self::exposure_scan_url( $days ) ); ?>"><?php echo esc_html__( 'Run public exposure scan', 'koz-suite-control-center' ); ?></a></p>
				<?php else : ?>
					<p><strong><?php echo esc_html__( 'Result:', 'koz-suite-control-center' ); ?></strong>
						<?php
						printf(
							/* translators: 1: scanned page count, 2: finding count. */
							esc_html__( '%1$d public pages checked; %2$d sensitive URL findings.', 'koz-suite-control-center' ),
							(int) $exposure_scan['scanned'],
							(int) $exposure_scan['finding_count']
						);
						?>
					</p>
					<?php if ( empty( $exposure_scan['findings'] ) ) : ?>
						<p class="kozsuitecc-empty"><?php echo esc_html__( 'No tokenized private-download URLs were detected on the scanned pages.', 'koz-suite-control-center' ); ?></p>
					<?php else : ?>
						<div class="kozsuitecc-list">
							<?php foreach ( (array) $exposure_scan['findings'] as $finding ) : ?>
								<article class="kozsuitecc-item is-warning">
									<h3><?php echo esc_html( (string) $finding['page_path'] ); ?></h3>
									<p><?php echo esc_html( (string) $finding['type'] ); ?> · <?php echo esc_html( (string) $finding['target_path'] ); ?></p>
									<p><strong><?php echo esc_html__( 'Sensitive parameters:', 'koz-suite-control-center' ); ?></strong> <?php echo esc_html( implode( ', ', (array) $finding['parameter_names'] ) ); ?></p>
								</article>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<p><a class="button" href="<?php echo esc_url( self::exposure_scan_url( $days ) ); ?>"><?php echo esc_html__( 'Run scan again', 'koz-suite-control-center' ); ?></a></p>
				<?php endif; ?>
			</section>

			<details class="kozsuitecc-technical">
				<summary><?php echo esc_html__( 'Technical data and JSON', 'koz-suite-control-center' ); ?></summary>
				<div>
					<?php self::render_periods( $days ); ?>
					<p><a class="button" href="<?php echo esc_url( self::export_url( $days ) ); ?>"><?php echo esc_html__( 'Export privacy-safe JSON', 'koz-suite-control-center' ); ?></a></p>
					<table class="widefat striped">
						<thead><tr><th><?php echo esc_html__( 'Plugin', 'koz-suite-control-center' ); ?></th><th><?php echo esc_html__( 'State', 'koz-suite-control-center' ); ?></th><th><?php echo esc_html__( 'Version', 'koz-suite-control-center' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( (array) $report['suite'] as $component ) : ?>
							<tr><td><?php echo esc_html( (string) $component['name'] ); ?></td><td><?php echo esc_html( $component['active'] ? __( 'Active', 'koz-suite-control-center' ) : ( $component['installed'] ? __( 'Inactive', 'koz-suite-control-center' ) : __( 'Missing', 'koz-suite-control-center' ) ) ); ?></td><td><?php echo esc_html( (string) $component['version'] ); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</details>
		</div>
		<?php
	}


	/** @return array<string,mixed>|null */
	private static function requested_exposure_scan(): ?array {
		if ( ! isset( $_GET['exposure_scan'], $_GET['_kozsuitecc_scan_nonce'] ) ) {
			return null;
		}
		$nonce = sanitize_text_field( wp_unslash( (string) $_GET['_kozsuitecc_scan_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::EXPOSURE_SCAN_NONCE ) ) {
			return null;
		}
		return self::public_exposure_scan();
	}

	private static function exposure_scan_url( int $days ): string {
		return add_query_arg(
			array(
				'page' => self::CONTROL_PAGE,
				'days' => self::normalize_days( $days ),
				'exposure_scan' => 1,
				'_kozsuitecc_scan_nonce' => wp_create_nonce( self::EXPOSURE_SCAN_NONCE ),
			),
			admin_url( 'admin.php' )
		);
	}

	/** @return array<string,mixed> */
	private static function public_exposure_scan(): array {
		$urls = self::public_exposure_candidate_urls();
		$findings = array();
		$scanned = 0;
		$errors = 0;
		foreach ( $urls as $page_url ) {
			$response = wp_safe_remote_get(
				$page_url,
				array(
					'timeout' => 3,
					'redirection' => 2,
					'headers' => array( 'Accept' => 'text/html' ),
				)
			);
			if ( is_wp_error( $response ) ) {
				++$errors;
				continue;
			}
			$status = (int) wp_remote_retrieve_response_code( $response );
			if ( $status < 200 || $status >= 400 ) {
				++$errors;
				continue;
			}
			++$scanned;
			$body = (string) wp_remote_retrieve_body( $response );
			foreach ( self::extract_public_sensitive_urls( $body, $page_url ) as $finding ) {
				$findings[] = $finding;
			}
		}

		$unique = array();
		foreach ( $findings as $finding ) {
			$key = md5( wp_json_encode( $finding ) );
			$unique[ $key ] = $finding;
		}
		$findings = array_values( $unique );

		return array(
			'scanned' => $scanned,
			'errors' => $errors,
			'finding_count' => count( $findings ),
			'findings' => array_slice( $findings, 0, 50 ),
			'read_only' => true,
			'secret_values_exported' => false,
		);
	}

	/** @return array<int,string> */
	private static function public_exposure_candidate_urls(): array {
		$urls = array();
		$known_page = get_page_by_path( 'koz-plugins' );
		if ( is_object( $known_page ) && 'publish' === (string) ( $known_page->post_status ?? '' ) ) {
			$known_url = get_permalink( $known_page );
			if ( is_string( $known_url ) && '' !== $known_url ) {
				$urls[ $known_url ] = $known_url;
			}
		}

		$pages = get_pages(
			array(
				'post_status' => 'publish',
				'number' => 100,
				'sort_column' => 'post_modified',
				'sort_order' => 'DESC',
			)
		);
		foreach ( $pages as $page ) {
			if ( ! is_object( $page ) ) {
				continue;
			}
			$slug = strtolower( (string) ( $page->post_name ?? '' ) );
			$title = strtolower( wp_strip_all_tags( (string) ( $page->post_title ?? '' ) ) );
			$content = strtolower( (string) ( $page->post_content ?? '' ) );
			$haystack = $slug . ' ' . $title . ' ' . substr( $content, 0, 12000 );
			if ( ! preg_match( '/(?:koz|plugin|download|завантаж|плагін)/u', $haystack ) ) {
				continue;
			}
			$url = get_permalink( $page );
			if ( is_string( $url ) && '' !== $url ) {
				$urls[ $url ] = $url;
			}
			if ( count( $urls ) >= self::EXPOSURE_SCAN_LIMIT ) {
				break;
			}
		}
		return array_slice( array_values( $urls ), 0, self::EXPOSURE_SCAN_LIMIT );
	}

	/** @return array<int,array<string,mixed>> */
	private static function extract_public_sensitive_urls( string $html, string $page_url ): array {
		$candidates = array();
		if ( preg_match_all( '/(?:href|src)\\s*=\\s*(["\\\'])(.*?)\\1/isu', $html, $matches ) ) {
			foreach ( (array) $matches[2] as $value ) {
				$candidates[] = html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}
		if ( preg_match_all( '~https?://[^\\s"\\\'<>]+~iu', $html, $raw_matches ) ) {
			foreach ( (array) $raw_matches[0] as $value ) {
				$candidates[] = html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}

		$page_path = (string) ( wp_parse_url( $page_url, PHP_URL_PATH ) ?? '/' );
		$findings = array();
		foreach ( array_unique( $candidates ) as $candidate ) {
			$finding = self::classify_sensitive_public_url( (string) $candidate, $page_path );
			if ( null !== $finding ) {
				$findings[] = $finding;
			}
		}
		return $findings;
	}

	/** @return array<string,mixed>|null */
	private static function classify_sensitive_public_url( string $url, string $page_path ): ?array {
		$url = trim( $url );
		if ( '' === $url || str_starts_with( $url, '#' ) || str_starts_with( $url, 'javascript:' ) ) {
			return null;
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return null;
		}
		$path = (string) ( $parts['path'] ?? '' );
		$query = (string) ( $parts['query'] ?? '' );
		if ( '' === $query ) {
			return null;
		}
		$params = array();
		parse_str( $query, $params );
		if ( empty( $params ) ) {
			return null;
		}
		$parameter_names = array_map( 'sanitize_key', array_keys( $params ) );
		$hard_sensitive = array_values(
			array_intersect(
				$parameter_names,
				array( 'token', 'secret', 'access_token', 'download_token' )
			)
		);
		$nonce_names = array_values( array_intersect( $parameter_names, array( 'nonce', '_wpnonce' ) ) );
		$action = isset( $params['action'] ) && is_scalar( $params['action'] ) ? sanitize_key( (string) $params['action'] ) : '';
		$is_admin_post = str_ends_with( strtolower( $path ), '/wp-admin/admin-post.php' ) || str_ends_with( strtolower( $path ), 'admin-post.php' );
		$is_private_download = '' !== $action && ( str_contains( $action, 'private' ) || str_contains( $action, 'download' ) );
		$sensitive_names = $hard_sensitive;
		if ( $is_admin_post || $is_private_download ) {
			$sensitive_names = array_values( array_unique( array_merge( $sensitive_names, $nonce_names ) ) );
		}
		if ( empty( $hard_sensitive ) && empty( $sensitive_names ) && ! ( $is_admin_post && $is_private_download ) ) {
			return null;
		}

		$type = $is_admin_post && $is_private_download
			? 'private admin-post download URL exposed publicly'
			: 'sensitive query parameter exposed publicly';
		$target_path = '' !== $path ? $path : '/';
		return array(
			'page_path' => sanitize_text_field( $page_path ),
			'target_path' => sanitize_text_field( $target_path ),
			'type' => $type,
			'action' => $action,
			'parameter_names' => $sensitive_names,
		);
	}

	private static function requested_days(): int {
		$days = 30;
		if ( isset( $_GET['days'], $_GET['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) );
			if ( wp_verify_nonce( $nonce, 'kozsuitecc_period' ) ) {
				$days = absint( wp_unslash( $_GET['days'] ) );
			}
		}
		return self::normalize_days( $days );
	}

	private static function render_periods( int $days ): void {
		echo '<div class="kozsuitecc-toolbar"><strong>' . esc_html__( 'Period:', 'koz-suite-control-center' ) . '</strong>';
		foreach ( self::PERIODS as $period ) {
			$url = wp_nonce_url(
				add_query_arg( array( 'page' => self::CONTROL_PAGE, 'days' => $period ), admin_url( 'admin.php' ) ),
				'kozsuitecc_period'
			);
			$class = $period === $days ? 'button button-primary' : 'button';
			$label = sprintf(
				/* translators: %d: number of days in the report period. */
				__( '%d days', 'koz-suite-control-center' ),
				$period
			);
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</div>';
	}

	private static function export_url( int $days ): string {
		return wp_nonce_url(
			add_query_arg( array( 'action' => self::EXPORT_ACTION, 'days' => $days ), admin_url( 'admin-post.php' ) ),
			self::EXPORT_ACTION
		);
	}

	public static function export_json(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export this report.', 'koz-suite-control-center' ) );
		}
		check_admin_referer( self::EXPORT_ACTION );
		$days = isset( $_GET['days'] ) ? self::normalize_days( absint( wp_unslash( $_GET['days'] ) ) ) : 30;
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="koz-suite-control-center-' . $days . '-days.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo wp_json_encode( self::report( $days ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/** @param array<int,string> $links @return array<int,string> */
	public static function action_links( array $links ): array {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::CONTROL_PAGE ) ) . '">' . esc_html__( 'Open dashboard', 'koz-suite-control-center' ) . '</a>'
		);
		return $links;
	}
}
