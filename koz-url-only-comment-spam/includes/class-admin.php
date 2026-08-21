<?php
/**
 * Administration UI.
 *
 * @package KOZ_URL_Only_Comment_Spam
 */

declare(strict_types=1);

namespace ramirkz\kozurlspam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	private const PAGE_SLUG    = 'koz-url-only-comment-spam';
	private const FALLBACK_SUITE_PAGE = 'kozurlspam-suite';
	private const RESET_ACTION = 'kozurlspam_reset';
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_' . self::RESET_ACTION, array( $this, 'reset_counter' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KOZURLSPAM_FILE ), array( $this, 'action_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function admin_menu(): void {
		$parent = $this->suite_page();

		add_submenu_page(
			$parent,
			__( 'KOZ URL-Only Comment Spam', 'koz-url-only-comment-spam' ),
			__( 'URL Comment Spam', 'koz-url-only-comment-spam' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	private function suite_page(): string {
		global $menu;

		foreach ( (array) $menu as $item ) {
			$label = isset( $item[0] ) ? trim( wp_strip_all_tags( (string) $item[0] ) ) : '';
			$slug  = isset( $item[2] ) ? (string) $item[2] : '';
			if ( 'KOZ Suite' === $label && '' !== $slug ) {
				return $slug;
			}
		}

		add_menu_page(
			__( 'KOZ WordPress Suite', 'koz-url-only-comment-spam' ),
			__( 'KOZ Suite', 'koz-url-only-comment-spam' ),
			'manage_options',
			self::FALLBACK_SUITE_PAGE,
			array( $this, 'render_suite_page' ),
			'dashicons-layout',
			58
		);

		return self::FALLBACK_SUITE_PAGE;
	}

	/** @return array<string,array<string,mixed>> */
	private function suite_catalog(): array {
		return array(
			'koz-migration-cleanup' => array( 'name' => 'KOZ Migration & Cleanup', 'legacy' => array( 'ua-free-migration-cleanup' ), 'settings_page' => 'koz-migration-cleanup', 'description' => __( 'Scanning, snapshots, migration and controlled cleanup.', 'koz-url-only-comment-spam' ) ),
			'koz-static-translate' => array( 'name' => 'KOZ Static Translate', 'legacy' => array( 'ua-free-static-translate' ), 'settings_page' => 'koz-static-translate', 'description' => __( 'Automatic static WordPress translation.', 'koz-url-only-comment-spam' ) ),
			'koz-translate-diagnostics' => array( 'name' => 'KOZ Translate Diagnostics', 'legacy' => array( 'ua-free-translate-diagnostics' ), 'settings_page' => 'koz-translate-diagnostics', 'description' => __( 'Read-only translation diagnostics.', 'koz-url-only-comment-spam' ) ),
			'koz-seo-core' => array( 'name' => 'KOZ SEO Core', 'legacy' => array( 'ua-free-seo-core' ), 'settings_page' => 'koz-seo-core', 'description' => __( 'SEO, sitemaps, schema, AI discovery and accessibility.', 'koz-url-only-comment-spam' ) ),
			'koz-404-guard' => array( 'name' => 'KOZ 404 Guard & URL Intelligence', 'legacy' => array( 'ua-free-404-guard' ), 'settings_page' => 'koz-404-guard', 'description' => __( '404, 410, redirects and URL intelligence.', 'koz-url-only-comment-spam' ) ),
			'koz-site-bridge' => array( 'name' => 'KOZ Site Bridge', 'legacy' => array( 'ua-free-site-bridge' ), 'settings_page' => 'koz-site-bridge', 'description' => __( 'Secure read-only site diagnostics.', 'koz-url-only-comment-spam' ) ),
			'koz-google-ads-campaign-builder' => array( 'name' => 'KOZ Google Ads Campaign Builder', 'legacy' => array( 'ua-free-google-ads-campaign-builder' ), 'settings_page' => 'koz-google-ads-campaign-builder', 'description' => __( 'Google Ad Grants and Standard Google Ads packages.', 'koz-url-only-comment-spam' ) ),
			'koz-donate-stats' => array( 'name' => 'KOZ Donate Stats & Conversions', 'legacy' => array( 'ua-free-donate-stats' ), 'settings_page' => 'koz-donate-stats', 'description' => __( 'Privacy-safe statistics and conversions.', 'koz-url-only-comment-spam' ) ),
			'koz-copy-actions' => array( 'name' => 'KOZ Copy Actions', 'legacy' => array( 'ua-free-copy' ), 'settings_page' => 'koz-copy-actions', 'description' => __( 'Accessible copying of configured values.', 'koz-url-only-comment-spam' ) ),
			'koz-url-only-comment-spam' => array( 'name' => 'KOZ URL-Only Comment Spam', 'legacy' => array( 'ua-free-url-only-comment-spam' ), 'settings_page' => 'koz-url-only-comment-spam', 'description' => __( 'Filter for comments containing only URLs.', 'koz-url-only-comment-spam' ) ),
			'koz-suite-control-center' => array( 'name' => 'KOZ Suite Control Center', 'legacy' => array( 'ua-free-analytics-dashboard', 'ua-free-suite-control-center' ), 'settings_page' => 'koz-suite-control-center', 'description' => __( 'Unified suite status and navigation dashboard.', 'koz-url-only-comment-spam' ) ),
			'koz-consent-manager' => array( 'name' => 'KOZ Consent Manager', 'legacy' => array( 'ua-free-consent-manager' ), 'settings_page' => 'koz-consent-manager', 'description' => __( 'Consent management and controlled external scripts.', 'koz-url-only-comment-spam' ) ),
		);
	}

	private function matching_plugin_file( array $installed, array $prefixes ): string {
		foreach ( array_keys( $installed ) as $candidate ) {
			foreach ( $prefixes as $prefix ) {
				if ( str_starts_with( (string) $candidate, (string) $prefix . '/' ) ) {
					return (string) $candidate;
				}
			}
		}
		return '';
	}

	/** @return array<string,array<string,mixed>> */
	private function suite_status(): array {
		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed = get_plugins();
		$result = array();
		foreach ( $this->suite_catalog() as $slug => $item ) {
			$koz_file    = $this->matching_plugin_file( $installed, array( $slug ) );
			$legacy_file = $this->matching_plugin_file( $installed, (array) ( $item['legacy'] ?? array() ) );
			$result[ $slug ] = array(
				'name'          => (string) $item['name'],
				'description'   => (string) $item['description'],
				'installed'     => '' !== $koz_file,
				'active'        => '' !== $koz_file && is_plugin_active( $koz_file ),
				'legacy_active' => '' !== $legacy_file && is_plugin_active( $legacy_file ),
				'version'       => '' !== $koz_file ? (string) ( $installed[ $koz_file ]['Version'] ?? '' ) : '',
				'settings_page' => sanitize_key( (string) ( $item['settings_page'] ?? '' ) ),
			);
		}
		return $result;
	}

	public function render_suite_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'KOZ WordPress Suite', 'koz-url-only-comment-spam' ); ?></h1>
			<p><?php esc_html_e( 'Independent WordPress tools shaped by real production use on the UA FREE charitable foundation website.', 'koz-url-only-comment-spam' ); ?></p>
			<div class="koz-suite-grid">
			<?php foreach ( $this->suite_status() as $component ) : ?>
				<div class="koz-card">
					<h2><?php echo esc_html( $component['name'] ); ?></h2>
					<p><?php echo esc_html( $component['description'] ); ?></p>
					<p><strong><?php
					if ( $component['active'] ) {
						esc_html_e( 'Active', 'koz-url-only-comment-spam' );
					} elseif ( $component['installed'] ) {
						esc_html_e( 'Installed but inactive', 'koz-url-only-comment-spam' );
					} elseif ( $component['legacy_active'] ) {
						esc_html_e( 'Legacy UA FREE version active', 'koz-url-only-comment-spam' );
					} else {
						esc_html_e( 'Not installed', 'koz-url-only-comment-spam' );
					}
					?></strong><?php if ( '' !== $component['version'] ) : ?> <code><?php echo esc_html( $component['version'] ); ?></code><?php endif; ?></p>
					<?php if ( $component['active'] && '' !== $component['settings_page'] ) : ?>
						<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $component['settings_page'] ) ); ?>"><?php esc_html_e( 'Open', 'koz-url-only-comment-spam' ); ?></a></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	public function register_settings(): void {
		register_setting(
			'kozurlspam_group',
			Plugin::SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => Plugin::defaults(),
			)
		);
	}

	/** @param mixed $input Raw form data. @return array<string,mixed> */
	public function sanitize_settings( $input ): array {
		$input   = is_array( $input ) ? $input : array();
		$action  = isset( $input['action'] ) && 'hold' === $input['action'] ? 'hold' : 'spam';
		$domains = isset( $input['trusted_domains'] ) ? $this->sanitize_domains( (string) $input['trusted_domains'] ) : array();
		return array(
			'enabled'                  => ! empty( $input['enabled'] ),
			'action'                   => $action,
			'minimum_urls'             => min( 10, max( 1, isset( $input['minimum_urls'] ) ? absint( $input['minimum_urls'] ) : 1 ) ),
			'exempt_logged_in'         => ! empty( $input['exempt_logged_in'] ),
			'trust_same_site'          => ! empty( $input['trust_same_site'] ),
			'trusted_domains'          => $domains,
			'delete_data_on_uninstall' => ! empty( $input['delete_data_on_uninstall'] ),
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, self::PAGE_SLUG ) && ! str_contains( $hook, self::FALLBACK_SUITE_PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'kozurlspam-admin', KOZURLSPAM_URL . 'assets/admin.css', array(), KOZURLSPAM_VERSION );
	}

	/** @param string[] $links Plugin action links. @return string[] */
	public function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'Settings', 'koz-url-only-comment-spam' ) . '</a>' );
		return $links;
	}


	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$plugin      = Plugin::instance();
		$settings    = $plugin->get_settings();
		$total       = (int) get_option( Plugin::TOTAL_OPTION, 0 );
		$last        = get_option( Plugin::LAST_OPTION, array() );
		$last        = is_array( $last ) ? $last : array();
		$test_text   = $this->get_test_text();
		$test_result = null !== $test_text ? Detector::analyze( $test_text, $settings ) : null;
		?>
		<div class="wrap koz-url-spam-wrap">
			<h1><?php esc_html_e( 'KOZ URL-Only Comment Spam', 'koz-url-only-comment-spam' ); ?> <code><?php echo esc_html( KOZURLSPAM_VERSION ); ?></code></h1>
			<p class="description"><?php esc_html_e( 'Privacy-first moderation for comments whose visible content consists only of one or more URLs.', 'koz-url-only-comment-spam' ); ?></p>

			<div class="koz-grid">
				<div class="koz-card">
					<h2><?php esc_html_e( 'Current status', 'koz-url-only-comment-spam' ); ?></h2>
					<table class="widefat striped"><tbody>
						<tr><th><?php esc_html_e( 'Filtering', 'koz-url-only-comment-spam' ); ?></th><td><?php echo esc_html( $settings['enabled'] ? __( 'Enabled', 'koz-url-only-comment-spam' ) : __( 'Disabled', 'koz-url-only-comment-spam' ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Handled comments', 'koz-url-only-comment-spam' ); ?></th><td><?php echo esc_html( number_format_i18n( $total ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Last detection', 'koz-url-only-comment-spam' ); ?></th><td><?php echo ! empty( $last['caught_at'] ) ? esc_html( (string) $last['caught_at'] ) : esc_html__( 'None yet', 'koz-url-only-comment-spam' ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Stored personal data', 'koz-url-only-comment-spam' ); ?></th><td><?php esc_html_e( 'None. No IP, user agent, comment text or detected URL is stored.', 'koz-url-only-comment-spam' ); ?></td></tr>
					</tbody></table>
				</div>
				<div class="koz-card">
					<h2><?php esc_html_e( 'How detection works', 'koz-url-only-comment-spam' ); ?></h2>
					<p><?php esc_html_e( 'HTML and invisible spacing characters are removed. If the remaining text contains only URLs and punctuation, the configured action is applied.', 'koz-url-only-comment-spam' ); ?></p>
					<p><?php esc_html_e( 'Pingbacks, trackbacks, administrators and comment moderators are never processed.', 'koz-url-only-comment-spam' ); ?></p>
				</div>
			</div>

			<form method="post" action="options.php" class="koz-card">
				<?php settings_fields( 'kozurlspam_group' ); ?>
				<h2><?php esc_html_e( 'Settings', 'koz-url-only-comment-spam' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Enable filtering', 'koz-url-only-comment-spam' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( Plugin::SETTINGS_OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Process new standard comments.', 'koz-url-only-comment-spam' ); ?></label></td></tr>
					<tr><th scope="row"><label for="koz-url-spam-action"><?php esc_html_e( 'Action', 'koz-url-only-comment-spam' ); ?></label></th><td><select id="koz-url-spam-action" name="<?php echo esc_attr( Plugin::SETTINGS_OPTION ); ?>[action]"><option value="spam" <?php selected( $settings['action'], 'spam' ); ?>><?php esc_html_e( 'Mark as spam', 'koz-url-only-comment-spam' ); ?></option><option value="hold" <?php selected( $settings['action'], 'hold' ); ?>><?php esc_html_e( 'Hold for moderation', 'koz-url-only-comment-spam' ); ?></option></select></td></tr>
					<tr><th scope="row"><label for="koz-url-spam-minimum"><?php esc_html_e( 'Minimum URL count', 'koz-url-only-comment-spam' ); ?></label></th><td><input id="koz-url-spam-minimum" type="number" min="1" max="10" name="<?php echo esc_attr( Plugin::SETTINGS_OPTION ); ?>[minimum_urls]" value="<?php echo esc_attr( (string) $settings['minimum_urls'] ); ?>"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Logged-in users', 'koz-url-only-comment-spam' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( Plugin::SETTINGS_OPTION ); ?>[exempt_logged_in]" value="1" <?php checked( ! empty( $settings['exempt_logged_in'] ) ); ?>> <?php esc_html_e( 'Do not process comments from any logged-in user.', 'koz-url-only-comment-spam' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Trusted destinations', 'koz-url-only-comment-spam' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( Plugin::SETTINGS_OPTION ); ?>[trust_same_site]" value="1" <?php checked( ! empty( $settings['trust_same_site'] ) ); ?>> <?php esc_html_e( 'Do not flag comments when every URL points to this site.', 'koz-url-only-comment-spam' ); ?></label><p><textarea class="large-text code" rows="4" name="<?php echo esc_attr( Plugin::SETTINGS_OPTION ); ?>[trusted_domains]" placeholder="example.org&#10;docs.example.org"><?php echo esc_textarea( implode( "\n", (array) $settings['trusted_domains'] ) ); ?></textarea></p><p class="description"><?php esc_html_e( 'Optional trusted domains, one per line. A comment is exempt only when every detected URL is trusted.', 'koz-url-only-comment-spam' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Uninstall', 'koz-url-only-comment-spam' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( Plugin::SETTINGS_OPTION ); ?>[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?>> <?php esc_html_e( 'Delete settings and aggregate counters when the plugin is uninstalled.', 'koz-url-only-comment-spam' ); ?></label></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<div class="koz-grid">
				<div class="koz-card">
					<h2><?php esc_html_e( 'Test the detector', 'koz-url-only-comment-spam' ); ?></h2>
					<p><?php esc_html_e( 'The sample is analyzed in this request only and is never saved.', 'koz-url-only-comment-spam' ); ?></p>
					<form method="post">
						<?php wp_nonce_field( 'koz_url_spam_test', 'koz_url_spam_test_nonce' ); ?>
						<textarea class="large-text" rows="4" name="koz_url_spam_test_text" placeholder="https://spam.example/"><?php echo esc_textarea( $test_text ?? '' ); ?></textarea>
						<p><button type="submit" class="button button-secondary"><?php esc_html_e( 'Analyze sample', 'koz-url-only-comment-spam' ); ?></button></p>
					</form>
					<?php if ( null !== $test_result ) : ?>
						<div class="notice inline <?php echo ! empty( $test_result['is_url_only'] ) ? 'notice-warning' : 'notice-success'; ?>"><p><?php echo ! empty( $test_result['is_url_only'] ) ? esc_html__( 'Result: the comment would be handled by the plugin.', 'koz-url-only-comment-spam' ) : esc_html__( 'Result: the comment would continue through normal WordPress moderation.', 'koz-url-only-comment-spam' ); ?></p></div>
					<?php endif; ?>
				</div>
				<div class="koz-card">
					<h2><?php esc_html_e( 'Counter', 'koz-url-only-comment-spam' ); ?></h2>
					<p><?php esc_html_e( 'Resetting the counter does not move or delete any WordPress comments.', 'koz-url-only-comment-spam' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="<?php echo esc_attr( self::RESET_ACTION ); ?>"><?php wp_nonce_field( self::RESET_ACTION ); ?><button class="button" type="submit"><?php esc_html_e( 'Reset aggregate counter', 'koz-url-only-comment-spam' ); ?></button></form>
				</div>
			</div>
		</div>
		<?php
	}

	public function reset_counter(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'koz-url-only-comment-spam' ) );
		}
		check_admin_referer( self::RESET_ACTION );
		update_option( Plugin::TOTAL_OPTION, 0, false );
		delete_option( Plugin::LAST_OPTION );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&counter-reset=1' ) );
		exit;
	}

	private function get_test_text(): ?string {
		if ( ! isset( $_POST['koz_url_spam_test_nonce'] ) ) {
			return null;
		}
		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['koz_url_spam_test_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'koz_url_spam_test' ) ) {
			return null;
		}
		return isset( $_POST['koz_url_spam_test_text'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['koz_url_spam_test_text'] ) ) : '';
	}

	/** @return string[] */
	private function sanitize_domains( string $raw ): array {
		$lines   = preg_split( '/[\r\n,]+/', strtolower( $raw ) );
		$domains = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( str_contains( $line, '://' ) ) {
				$line = (string) wp_parse_url( $line, PHP_URL_HOST );
			}
			$line = preg_replace( '/^www\./', '', rtrim( $line, './' ) );
			if ( is_string( $line ) && preg_match( '/^(?:xn--)?[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?\.(?:xn--)?[a-z]{2,63}$/i', $line ) ) {
				$domains[] = $line;
			}
			if ( count( $domains ) >= 50 ) {
				break;
			}
		}
		return array_values( array_unique( $domains ) );
	}
}
