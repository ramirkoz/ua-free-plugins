<?php
/**
 * Administration UI.
 *
 * @package UAFree_URL_Only_Comment_Spam
 */

declare(strict_types=1);

namespace UAFree\URLSpam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	private const PAGE_SLUG    = 'uafree-url-only-comment-spam';
	private const RESET_ACTION = 'uafree_url_only_spam_reset';
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 40 );
		add_action( 'admin_menu', array( $this, 'remove_legacy_tools_menu' ), 10000 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_' . self::RESET_ACTION, array( $this, 'reset_counter' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( UAFREE_URL_SPAM_FILE ), array( $this, 'action_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function admin_menu(): void {
		global $menu;
		$has_parent = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && 'uafree-suite' === $item[2] ) {
					$has_parent = true;
					break;
				}
			}
		}

		if ( ! $has_parent ) {
			add_menu_page(
				__( 'UA FREE Suite', 'ua-free-url-only-comment-spam' ),
				'UA FREE',
				'manage_options',
				'uafree-suite',
				array( $this, 'render_suite_page' ),
				'dashicons-shield-alt',
				81
			);
		}

		add_submenu_page(
			'uafree-suite',
			__( 'URL-only comment spam', 'ua-free-url-only-comment-spam' ),
			__( 'URL Comment Spam', 'ua-free-url-only-comment-spam' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function remove_legacy_tools_menu(): void {
		remove_submenu_page( 'tools.php', self::PAGE_SLUG );
		remove_submenu_page( 'options-general.php', self::PAGE_SLUG );
	}

	public function register_settings(): void {
		register_setting(
			'uafree_url_only_spam_group',
			Plugin::SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => Plugin::defaults(),
			)
		);
	}

	/**
	 * @param mixed $input Raw form data.
	 * @return array<string,mixed>
	 */
	public function sanitize_settings( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$action = isset( $input['action'] ) && 'hold' === $input['action'] ? 'hold' : 'spam';
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
		if ( false === strpos( $hook, self::PAGE_SLUG ) && false === strpos( $hook, 'uafree-suite' ) ) {
			return;
		}
		wp_enqueue_style(
			'uafree-url-spam-admin',
			UAFREE_URL_SPAM_URL . 'assets/admin.css',
			array(),
			UAFREE_URL_SPAM_VERSION
		);
	}

	/**
	 * @param string[] $links Plugin action links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'Settings', 'ua-free-url-only-comment-spam' ) . '</a>'
		);
		return $links;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugin   = Plugin::instance();
		$settings = $plugin->get_settings();
		$total    = (int) get_option( Plugin::TOTAL_OPTION, 0 );
		$last     = get_option( Plugin::LAST_OPTION, array() );
		$last     = is_array( $last ) ? $last : array();
		$test_result = $this->get_test_result( $settings );
		?>
		<div class="wrap uafree-url-spam-wrap">
			<h1><?php esc_html_e( 'UA FREE URL-Only Comment Spam', 'ua-free-url-only-comment-spam' ); ?></h1>
			<p class="description"><?php esc_html_e( 'A small privacy-first filter for comments that contain only one or more URLs.', 'ua-free-url-only-comment-spam' ); ?></p>

			<div class="uafree-grid">
				<div class="uafree-card">
					<h2><?php esc_html_e( 'Current status', 'ua-free-url-only-comment-spam' ); ?></h2>
					<table class="widefat striped">
						<tbody>
							<tr><th><?php esc_html_e( 'Filtering', 'ua-free-url-only-comment-spam' ); ?></th><td><?php echo esc_html( $settings['enabled'] ? __( 'Enabled', 'ua-free-url-only-comment-spam' ) : __( 'Disabled', 'ua-free-url-only-comment-spam' ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Handled comments', 'ua-free-url-only-comment-spam' ); ?></th><td><?php echo esc_html( number_format_i18n( $total ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Last detection', 'ua-free-url-only-comment-spam' ); ?></th><td><?php echo ! empty( $last['caught_at'] ) ? esc_html( (string) $last['caught_at'] ) : esc_html__( 'None yet', 'ua-free-url-only-comment-spam' ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Stored personal data', 'ua-free-url-only-comment-spam' ); ?></th><td><?php esc_html_e( 'None. No IP, user agent, comment text or detected URL is stored.', 'ua-free-url-only-comment-spam' ); ?></td></tr>
						</tbody>
					</table>
				</div>

				<div class="uafree-card">
					<h2><?php esc_html_e( 'How detection works', 'ua-free-url-only-comment-spam' ); ?></h2>
					<p><?php esc_html_e( 'HTML and invisible spacing characters are removed. If the remaining text contains only URLs and punctuation, the configured action is applied.', 'ua-free-url-only-comment-spam' ); ?></p>
					<p><?php esc_html_e( 'Pingbacks, trackbacks, administrators and comment moderators are never processed.', 'ua-free-url-only-comment-spam' ); ?></p>
				</div>
			</div>

			<form method="post" action="options.php" class="uafree-card">
				<?php settings_fields( 'uafree_url_only_spam_group' ); ?>
				<h2><?php esc_html_e( 'Settings', 'ua-free-url-only-comment-spam' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable filtering', 'ua-free-url-only-comment-spam' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Plugin::SETTINGS_OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Process new standard comments.', 'ua-free-url-only-comment-spam' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="uafree-url-spam-action"><?php esc_html_e( 'Action', 'ua-free-url-only-comment-spam' ); ?></label></th>
						<td><select id="uafree-url-spam-action" name="<?php echo esc_attr( Plugin::SETTINGS_OPTION ); ?>[action]">
							<option value="spam" <?php selected( $settings['action'], 'spam' ); ?>><?php esc_html_e( 'Mark as spam', 'ua-free-url-only-comment-spam' ); ?></option>
							<option value="hold" <?php selected( $settings['action'], 'hold' ); ?>><?php esc_html_e( 'Hold for moderation', 'ua-free-url-only-comment-spam' ); ?></option>
						</select></td>
					</tr>
					<tr>
						<th scope="row"><label for="uafree-url-spam-minimum"><?php esc_html_e( 'Minimum URL count', 'ua-free-url-only-comment-spam' ); ?></label></th>
						<td><input id="uafree-url-spam-minimum" type="number" min="1" max="10" name="<?php echo esc_attr( Plugin::SETTINGS_OPTION ); ?>[minimum_urls]" value="<?php echo esc_attr( (string) $settings['minimum_urls'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Logged-in users', 'ua-free-url-only-comment-spam' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Plugin::SETTINGS_OPTION ); ?>[exempt_logged_in]" value="1" <?php checked( ! empty( $settings['exempt_logged_in'] ) ); ?>> <?php esc_html_e( 'Do not process comments from any logged-in user.', 'ua-free-url-only-comment-spam' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Trusted destinations', 'ua-free-url-only-comment-spam' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( Plugin::SETTINGS_OPTION ); ?>[trust_same_site]" value="1" <?php checked( ! empty( $settings['trust_same_site'] ) ); ?>> <?php esc_html_e( 'Do not flag comments when every URL points to this site.', 'ua-free-url-only-comment-spam' ); ?></label>
							<p><textarea class="large-text code" rows="4" name="<?php echo esc_attr( Plugin::SETTINGS_OPTION ); ?>[trusted_domains]" placeholder="example.org&#10;docs.example.org"><?php echo esc_textarea( implode( "\n", (array) $settings['trusted_domains'] ) ); ?></textarea></p>
							<p class="description"><?php esc_html_e( 'Optional trusted domains, one per line. A comment is exempt only when every detected URL is trusted.', 'ua-free-url-only-comment-spam' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Uninstall', 'ua-free-url-only-comment-spam' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Plugin::SETTINGS_OPTION ); ?>[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?>> <?php esc_html_e( 'Delete settings and aggregate counters when the plugin is uninstalled.', 'ua-free-url-only-comment-spam' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<div class="uafree-grid">
				<div class="uafree-card">
					<h2><?php esc_html_e( 'Test the detector', 'ua-free-url-only-comment-spam' ); ?></h2>
					<p><?php esc_html_e( 'The sample is analyzed in this request only and is never saved.', 'ua-free-url-only-comment-spam' ); ?></p>
					<form method="post">
						<?php wp_nonce_field( 'uafree_url_spam_test', 'uafree_url_spam_test_nonce' ); ?>
						<textarea class="large-text" rows="4" name="uafree_url_spam_test_text" placeholder="https://spam.example/"><?php echo isset( $_POST['uafree_url_spam_test_text'] ) ? esc_textarea( wp_unslash( (string) $_POST['uafree_url_spam_test_text'] ) ) : ''; ?></textarea>
						<p><button type="submit" class="button button-secondary"><?php esc_html_e( 'Analyze sample', 'ua-free-url-only-comment-spam' ); ?></button></p>
					</form>
					<?php if ( null !== $test_result ) : ?>
						<div class="notice inline <?php echo ! empty( $test_result['is_url_only'] ) ? 'notice-warning' : 'notice-success'; ?>"><p>
							<?php
							echo ! empty( $test_result['is_url_only'] )
								? esc_html__( 'Result: the comment would be handled by the plugin.', 'ua-free-url-only-comment-spam' )
								: esc_html__( 'Result: the comment would continue through normal WordPress moderation.', 'ua-free-url-only-comment-spam' );
							?>
						</p></div>
					<?php endif; ?>
				</div>

				<div class="uafree-card">
					<h2><?php esc_html_e( 'Counter', 'ua-free-url-only-comment-spam' ); ?></h2>
					<p><?php esc_html_e( 'Resetting the counter does not move or delete any WordPress comments.', 'ua-free-url-only-comment-spam' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::RESET_ACTION ); ?>">
						<?php wp_nonce_field( self::RESET_ACTION ); ?>
						<button class="button" type="submit"><?php esc_html_e( 'Reset aggregate counter', 'ua-free-url-only-comment-spam' ); ?></button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_suite_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap uafree-url-spam-wrap">
			<h1><?php esc_html_e( 'UA FREE Plugin Suite', 'ua-free-url-only-comment-spam' ); ?></h1>
			<p><?php esc_html_e( 'These lightweight plugins were originally created to solve practical needs of a charitable foundation website and are being generalized for other WordPress sites.', 'ua-free-url-only-comment-spam' ); ?></p>
			<div class="uafree-suite-list">
				<?php foreach ( Suite_Registry::statuses() as $component ) : ?>
					<div class="uafree-card">
						<h2><?php echo esc_html( $component['name'] ); ?></h2>
						<p><?php echo esc_html( $component['description'] ); ?></p>
						<span class="uafree-status uafree-status-<?php echo esc_attr( $component['status'] ); ?>"><?php echo esc_html( $this->status_label( $component['status'] ) ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="uafree-card">
				<h2><?php esc_html_e( 'Origin and support', 'ua-free-url-only-comment-spam' ); ?></h2>
				<p><?php esc_html_e( 'Support for the charitable foundation and support for plugin development are separate destinations. No credit link is inserted into the public site.', 'ua-free-url-only-comment-spam' ); ?></p>
			</div>
		</div>
		<?php
	}

	public function reset_counter(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ua-free-url-only-comment-spam' ) );
		}
		check_admin_referer( self::RESET_ACTION );
		update_option( Plugin::TOTAL_OPTION, 0, false );
		delete_option( Plugin::LAST_OPTION );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&counter-reset=1' ) );
		exit;
	}

	/**
	 * @param array<string,mixed> $settings Current settings.
	 * @return array<string,mixed>|null
	 */
	private function get_test_result( array $settings ): ?array {
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || ! isset( $_POST['uafree_url_spam_test_nonce'] ) ) {
			return null;
		}
		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['uafree_url_spam_test_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'uafree_url_spam_test' ) ) {
			return null;
		}
		$text = isset( $_POST['uafree_url_spam_test_text'] ) ? wp_unslash( (string) $_POST['uafree_url_spam_test_text'] ) : '';
		return Detector::analyze( $text, $settings );
	}

	/**
	 * @return string[]
	 */
	private function sanitize_domains( string $raw ): array {
		$lines = preg_split( '/[\r\n,]+/', strtolower( $raw ) );
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

	private function status_label( string $status ): string {
		if ( 'active' === $status ) {
			return __( 'Active', 'ua-free-url-only-comment-spam' );
		}
		if ( 'installed' === $status ) {
			return __( 'Installed', 'ua-free-url-only-comment-spam' );
		}
		return __( 'Available separately', 'ua-free-url-only-comment-spam' );
	}
}
