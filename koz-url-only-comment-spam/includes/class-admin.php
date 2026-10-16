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
		$input    = is_array( $input ) ? $input : array();
		$defaults = Plugin::defaults();
		$domains  = isset( $input['trusted_domains'] ) ? $this->sanitize_domains( (string) $input['trusted_domains'] ) : array();

		$types = array();
		foreach ( (array) ( $input['comment_types'] ?? array() ) as $type ) {
			$type = sanitize_key( (string) $type );
			if ( '' !== $type ) {
				$types[ $type ] = true;
			}
		}
		$types['comment'] = true;

		return array(
			'enabled'                  => ! empty( $input['enabled'] ),
			'action'                   => $this->sanitize_action( 'url_only', $input['action'] ?? null ),
			'minimum_urls'             => $this->sanitize_int( $input['minimum_urls'] ?? null, 1, 10, 1 ),
			'exempt_logged_in'         => ! empty( $input['exempt_logged_in'] ),
			'trust_same_site'          => ! empty( $input['trust_same_site'] ),
			'trusted_domains'          => $domains,
			'comment_types'            => array_keys( $types ),
			'low_text_action'          => $this->sanitize_action( 'low_text', $input['low_text_action'] ?? null ),
			'low_text_max_chars'       => $this->sanitize_int( $input['low_text_max_chars'] ?? null, 0, 500, (int) $defaults['low_text_max_chars'] ),
			'author_url_action'        => $this->sanitize_action( 'author_url', $input['author_url_action'] ?? null ),
			'author_url_max_chars'     => $this->sanitize_int( $input['author_url_max_chars'] ?? null, 0, 500, (int) $defaults['author_url_max_chars'] ),
			'honeypot_action'          => $this->sanitize_action( 'honeypot', $input['honeypot_action'] ?? null ),
			'too_fast_action'          => $this->sanitize_action( 'too_fast', $input['too_fast_action'] ?? null ),
			'min_seconds'              => $this->sanitize_int( $input['min_seconds'] ?? null, 0, 60, (int) $defaults['min_seconds'] ),
			'missing_token_action'     => $this->sanitize_action( 'missing_token', $input['missing_token_action'] ?? null ),
			'auto_delete_days'         => $this->sanitize_int( $input['auto_delete_days'] ?? null, 0, 365, 0 ),
			'delete_data_on_uninstall' => ! empty( $input['delete_data_on_uninstall'] ),
		);
	}

	/** @param mixed $value */
	private function sanitize_action( string $rule, $value ): string {
		$allowed = Plugin::RULE_ACTIONS[ $rule ];
		$value   = is_string( $value ) ? sanitize_key( $value ) : '';
		if ( in_array( $value, $allowed, true ) ) {
			return $value;
		}
		$key = 'url_only' === $rule ? 'action' : $rule . '_action';
		return (string) Plugin::defaults()[ $key ];
	}

	/** @param mixed $value */
	private function sanitize_int( $value, int $min, int $max, int $fallback ): int {
		if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
			return $fallback;
		}
		return min( $max, max( $min, (int) $value ) );
	}

	/** @return array<string,string> */
	private function action_labels(): array {
		return array(
			'off'    => __( 'Off', 'koz-url-only-comment-spam' ),
			'hold'   => __( 'Hold for moderation', 'koz-url-only-comment-spam' ),
			'spam'   => __( 'Mark as spam', 'koz-url-only-comment-spam' ),
			'reject' => __( 'Reject without saving', 'koz-url-only-comment-spam' ),
		);
	}

	/** @param array<string,mixed> $settings */
	private function action_select( string $rule, array $settings, string $id ): void {
		$key = 'url_only' === $rule ? 'action' : $rule . '_action';
		$labels = $this->action_labels();
		printf( '<select id="%1$s" name="%2$s[%3$s]">', esc_attr( $id ), esc_attr( Plugin::SETTINGS_OPTION ), esc_attr( $key ) );
		foreach ( Plugin::RULE_ACTIONS[ $rule ] as $action ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $action ), selected( $settings[ $key ], $action, false ), esc_html( $labels[ $action ] ) );
		}
		echo '</select>';
	}

	/** @return array<string,string> Comment type => label. */
	private function available_comment_types(): array {
		$types = array(
			'comment' => __( 'Comments', 'koz-url-only-comment-spam' ),
			'review'  => __( 'Product reviews (WooCommerce)', 'koz-url-only-comment-spam' ),
		);

		/**
		 * Filter the comment types offered on the settings screen.
		 *
		 * @param array<string,string> $types Comment type => label.
		 */
		return (array) apply_filters( 'kozurlspam_comment_type_choices', $types );
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
		$option      = Plugin::SETTINGS_OPTION;
		$total       = (int) get_option( Plugin::TOTAL_OPTION, 0 );
		$last        = get_option( Plugin::LAST_OPTION, array() );
		$last        = is_array( $last ) ? $last : array();
		$labels      = Maintenance::rule_labels();
		$totals_30   = Maintenance::totals( 30 );
		$test        = $this->get_test_input();
		$test_result = null;
		if ( null !== $test ) {
			$summary     = Detector::analyze( $test['text'], $settings );
			$test_result = $plugin->evaluate_text_rules( $summary, $test['author_url'], $settings );
		}
		$cleanup_now = Maintenance::cleanup_candidates( (int) $settings['auto_delete_days'] );
		$next_run    = wp_next_scheduled( Maintenance::CLEANUP_HOOK );
		?>
		<div class="wrap koz-url-spam-wrap">
			<h1><?php esc_html_e( 'KOZ URL-Only Comment Spam', 'koz-url-only-comment-spam' ); ?> <code><?php echo esc_html( KOZURLSPAM_VERSION ); ?></code></h1>
			<p class="description"><?php esc_html_e( 'Privacy-first local moderation for link-only and low-effort comment spam, with honeypot and form-timing traps.', 'koz-url-only-comment-spam' ); ?></p>

			<div class="koz-grid">
				<div class="koz-card">
					<h2><?php esc_html_e( 'Current status', 'koz-url-only-comment-spam' ); ?></h2>
					<table class="widefat striped"><tbody>
						<tr><th><?php esc_html_e( 'Filtering', 'koz-url-only-comment-spam' ); ?></th><td><?php echo esc_html( $settings['enabled'] ? __( 'Enabled', 'koz-url-only-comment-spam' ) : __( 'Disabled', 'koz-url-only-comment-spam' ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Handled comments', 'koz-url-only-comment-spam' ); ?></th><td><?php echo esc_html( number_format_i18n( $total ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Last detection', 'koz-url-only-comment-spam' ); ?></th><td><?php echo ! empty( $last['caught_at'] ) ? esc_html( (string) $last['caught_at'] ) : esc_html__( 'None yet', 'koz-url-only-comment-spam' ); ?><?php if ( ! empty( $last['rule'] ) && isset( $labels[ $last['rule'] ] ) ) : ?> · <?php echo esc_html( $labels[ $last['rule'] ] ); ?><?php endif; ?></td></tr>
						<tr><th><?php esc_html_e( 'Stored personal data', 'koz-url-only-comment-spam' ); ?></th><td><?php esc_html_e( 'None. No IP, user agent, comment text or detected URL is stored.', 'koz-url-only-comment-spam' ); ?></td></tr>
					</tbody></table>
				</div>
				<div class="koz-card">
					<h2><?php esc_html_e( 'Last 30 days by rule', 'koz-url-only-comment-spam' ); ?></h2>
					<table class="widefat striped"><tbody>
					<?php foreach ( $totals_30 as $rule => $count ) : ?>
						<tr><th><?php echo esc_html( $labels[ $rule ] ); ?></th><td><?php echo esc_html( number_format_i18n( $count ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
					<p class="description"><?php esc_html_e( 'Daily counts per rule only. Each handled comment also gets its rule in the Spam reason column on the Comments screen.', 'koz-url-only-comment-spam' ); ?></p>
				</div>
			</div>

			<form method="post" action="options.php" class="koz-card">
				<?php settings_fields( 'kozurlspam_group' ); ?>
				<h2><?php esc_html_e( 'Settings', 'koz-url-only-comment-spam' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Enable filtering', 'koz-url-only-comment-spam' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Process new comments of the selected types.', 'koz-url-only-comment-spam' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Comment types', 'koz-url-only-comment-spam' ); ?></th><td>
						<?php foreach ( $this->available_comment_types() as $type => $label ) : ?>
							<label class="koz-inline-check"><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[comment_types][]" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, (array) $settings['comment_types'], true ) ); ?> <?php disabled( 'comment' === $type ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
						<input type="hidden" name="<?php echo esc_attr( $option ); ?>[comment_types][]" value="comment">
						<p class="description"><?php esc_html_e( 'Pingbacks, trackbacks, administrators and comment moderators are never processed.', 'koz-url-only-comment-spam' ); ?></p>
					</td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Logged-in users', 'koz-url-only-comment-spam' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[exempt_logged_in]" value="1" <?php checked( ! empty( $settings['exempt_logged_in'] ) ); ?>> <?php esc_html_e( 'Do not process comments from any logged-in user.', 'koz-url-only-comment-spam' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Trusted destinations', 'koz-url-only-comment-spam' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[trust_same_site]" value="1" <?php checked( ! empty( $settings['trust_same_site'] ) ); ?>> <?php esc_html_e( 'Do not flag comments when every URL points to this site.', 'koz-url-only-comment-spam' ); ?></label><p><textarea class="large-text code" rows="3" name="<?php echo esc_attr( $option ); ?>[trusted_domains]" placeholder="example.org&#10;docs.example.org"><?php echo esc_textarea( implode( "\n", (array) $settings['trusted_domains'] ) ); ?></textarea></p><p class="description"><?php esc_html_e( 'Optional trusted domains, one per line. A comment is exempt only when every detected URL is trusted. Trusted domains also apply to the website field.', 'koz-url-only-comment-spam' ); ?></p></td></tr>
				</table>

				<h3><?php esc_html_e( 'Content rules', 'koz-url-only-comment-spam' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Links are found in text, HTML, BBCode and Markdown, including simple disguises such as hxxp:// and example[.]com. Bare names like example.com count only with a known top-level domain, so "Node.js" alone is not a link.', 'koz-url-only-comment-spam' ); ?></p>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="koz-url-spam-action"><?php esc_html_e( 'Only links', 'koz-url-only-comment-spam' ); ?></label></th><td><?php $this->action_select( 'url_only', $settings, 'koz-url-spam-action' ); ?> <label for="koz-url-spam-minimum"><?php esc_html_e( 'Minimum URL count', 'koz-url-only-comment-spam' ); ?></label> <input id="koz-url-spam-minimum" class="small-text" type="number" min="1" max="10" name="<?php echo esc_attr( $option ); ?>[minimum_urls]" value="<?php echo esc_attr( (string) $settings['minimum_urls'] ); ?>"><p class="description"><?php esc_html_e( 'The visible content is nothing but one or more links.', 'koz-url-only-comment-spam' ); ?></p></td></tr>
					<tr><th scope="row"><label for="koz-url-spam-low"><?php esc_html_e( 'Links with very little text', 'koz-url-only-comment-spam' ); ?></label></th><td><?php $this->action_select( 'low_text', $settings, 'koz-url-spam-low' ); ?> <label for="koz-url-spam-low-chars"><?php esc_html_e( 'Up to', 'koz-url-only-comment-spam' ); ?></label> <input id="koz-url-spam-low-chars" class="small-text" type="number" min="0" max="500" name="<?php echo esc_attr( $option ); ?>[low_text_max_chars]" value="<?php echo esc_attr( (string) $settings['low_text_max_chars'] ); ?>"> <?php esc_html_e( 'letters', 'koz-url-only-comment-spam' ); ?><p class="description"><?php esc_html_e( 'At least one untrusted link plus a short phrase such as "Great post!". Letters and digits are counted; spaces, punctuation and emoji are not.', 'koz-url-only-comment-spam' ); ?></p></td></tr>
					<tr><th scope="row"><label for="koz-url-spam-author"><?php esc_html_e( 'Website field with very little text', 'koz-url-only-comment-spam' ); ?></label></th><td><?php $this->action_select( 'author_url', $settings, 'koz-url-spam-author' ); ?> <label for="koz-url-spam-author-chars"><?php esc_html_e( 'Up to', 'koz-url-only-comment-spam' ); ?></label> <input id="koz-url-spam-author-chars" class="small-text" type="number" min="0" max="500" name="<?php echo esc_attr( $option ); ?>[author_url_max_chars]" value="<?php echo esc_attr( (string) $settings['author_url_max_chars'] ); ?>"> <?php esc_html_e( 'letters', 'koz-url-only-comment-spam' ); ?><p class="description"><?php esc_html_e( 'No links in the text, but the Website field points to an untrusted site and the text is very short.', 'koz-url-only-comment-spam' ); ?></p></td></tr>
				</table>

				<h3><?php esc_html_e( 'Bot traps', 'koz-url-only-comment-spam' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Applies to the standard comment form. Nothing is stored and no cookies are set. Pages served from a cache keep working.', 'koz-url-only-comment-spam' ); ?></p>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="koz-url-spam-hp"><?php esc_html_e( 'Hidden trap field filled', 'koz-url-only-comment-spam' ); ?></label></th><td><?php $this->action_select( 'honeypot', $settings, 'koz-url-spam-hp' ); ?><p class="description"><?php esc_html_e( 'An invisible field that people never see; automated scripts often fill it in.', 'koz-url-only-comment-spam' ); ?></p></td></tr>
					<tr><th scope="row"><label for="koz-url-spam-fast"><?php esc_html_e( 'Submitted too fast', 'koz-url-only-comment-spam' ); ?></label></th><td><?php $this->action_select( 'too_fast', $settings, 'koz-url-spam-fast' ); ?> <label for="koz-url-spam-seconds"><?php esc_html_e( 'Faster than', 'koz-url-only-comment-spam' ); ?></label> <input id="koz-url-spam-seconds" class="small-text" type="number" min="0" max="60" name="<?php echo esc_attr( $option ); ?>[min_seconds]" value="<?php echo esc_attr( (string) $settings['min_seconds'] ); ?>"> <?php esc_html_e( 'seconds after the page was generated', 'koz-url-only-comment-spam' ); ?></td></tr>
					<tr><th scope="row"><label for="koz-url-spam-token"><?php esc_html_e( 'Comment form token missing', 'koz-url-only-comment-spam' ); ?></label></th><td><?php $this->action_select( 'missing_token', $settings, 'koz-url-spam-token' ); ?><p class="description"><?php esc_html_e( 'The submission did not come from this site\'s comment form, typical for bots posting directly. Keep this off if your theme builds its own comment form without the comment_form action.', 'koz-url-only-comment-spam' ); ?></p></td></tr>
				</table>

				<h3><?php esc_html_e( 'Maintenance', 'koz-url-only-comment-spam' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="koz-url-spam-cleanup"><?php esc_html_e( 'Delete old spam', 'koz-url-only-comment-spam' ); ?></label></th><td><input id="koz-url-spam-cleanup" class="small-text" type="number" min="0" max="365" name="<?php echo esc_attr( $option ); ?>[auto_delete_days]" value="<?php echo esc_attr( (string) $settings['auto_delete_days'] ); ?>"> <?php esc_html_e( 'days (0 = never)', 'koz-url-only-comment-spam' ); ?><p class="description"><?php esc_html_e( 'Once a day, permanently deletes comments that have been in the Spam folder longer than this. Other comments are never touched.', 'koz-url-only-comment-spam' ); ?>
					<?php if ( (int) $settings['auto_delete_days'] > 0 ) : ?>
						<?php echo esc_html( sprintf( /* translators: %s: number of comments. */ __( 'Comments that would be deleted now: %s.', 'koz-url-only-comment-spam' ), number_format_i18n( $cleanup_now ) ) ); ?>
						<?php if ( $next_run ) : ?><?php echo esc_html( sprintf( /* translators: %s: date and time. */ __( 'Next run: %s.', 'koz-url-only-comment-spam' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $next_run ) ) ); ?><?php endif; ?>
					<?php endif; ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Uninstall', 'koz-url-only-comment-spam' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?>> <?php esc_html_e( 'Delete settings, aggregate counters and spam reasons when the plugin is uninstalled.', 'koz-url-only-comment-spam' ); ?></label></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<div class="koz-grid">
				<div class="koz-card">
					<h2><?php esc_html_e( 'Test the content rules', 'koz-url-only-comment-spam' ); ?></h2>
					<p><?php esc_html_e( 'The sample is analyzed in this request only and is never saved. Bot traps are not part of this test.', 'koz-url-only-comment-spam' ); ?></p>
					<form method="post">
						<?php wp_nonce_field( 'koz_url_spam_test', 'koz_url_spam_test_nonce' ); ?>
						<textarea class="large-text" rows="4" name="koz_url_spam_test_text" placeholder="https://spam.example/"><?php echo esc_textarea( $test['text'] ?? '' ); ?></textarea>
						<p><label for="koz-url-spam-test-author"><?php esc_html_e( 'Website field (optional)', 'koz-url-only-comment-spam' ); ?></label><br><input id="koz-url-spam-test-author" class="regular-text" type="url" name="koz_url_spam_test_author_url" value="<?php echo esc_attr( $test['author_url'] ?? '' ); ?>" placeholder="https://"></p>
						<p><button type="submit" class="button button-secondary"><?php esc_html_e( 'Analyze sample', 'koz-url-only-comment-spam' ); ?></button></p>
					</form>
					<?php if ( null !== $test ) : ?>
						<?php if ( null !== $test_result ) : ?>
							<div class="notice inline notice-warning"><p><?php echo esc_html( sprintf( /* translators: 1: rule name, 2: action. */ __( 'Result: rule "%1$s" would apply: %2$s.', 'koz-url-only-comment-spam' ), $labels[ $test_result['rule'] ], $this->action_labels()[ $test_result['action'] ] ) ); ?></p></div>
						<?php else : ?>
							<div class="notice inline notice-success"><p><?php esc_html_e( 'Result: the comment would continue through normal WordPress moderation.', 'koz-url-only-comment-spam' ); ?></p></div>
						<?php endif; ?>
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

	/** @return array{text:string,author_url:string}|null */
	private function get_test_input(): ?array {
		if ( ! isset( $_POST['koz_url_spam_test_nonce'] ) ) {
			return null;
		}
		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['koz_url_spam_test_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'koz_url_spam_test' ) ) {
			return null;
		}
		return array(
			// Comment-level HTML is kept, exactly as WordPress keeps it in a comment, so links in anchors are tested too.
			'text'       => isset( $_POST['koz_url_spam_test_text'] ) ? wp_kses_data( wp_unslash( (string) $_POST['koz_url_spam_test_text'] ) ) : '',
			'author_url' => isset( $_POST['koz_url_spam_test_author_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['koz_url_spam_test_author_url'] ) ) : '',
		);
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
