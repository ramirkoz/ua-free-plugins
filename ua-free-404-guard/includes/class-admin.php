<?php
namespace UAFree\Guard404;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	private const PAGE = 'uafree-404-guard';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_uafree_404_add_redirect', array( __CLASS__, 'add_redirect' ) );
		add_action( 'admin_post_uafree_404_delete_redirect', array( __CLASS__, 'delete_redirect' ) );
		add_action( 'admin_post_uafree_404_add_gone', array( __CLASS__, 'add_gone' ) );
		add_action( 'admin_post_uafree_404_delete_gone', array( __CLASS__, 'delete_gone' ) );
		add_action( 'admin_post_uafree_404_clear_log', array( __CLASS__, 'clear_log' ) );
		add_action( 'admin_post_uafree_404_sanitize_log', array( __CLASS__, 'sanitize_log' ) );
		add_action( 'admin_post_uafree_404_start_capture', array( __CLASS__, 'start_capture' ) );
		add_action( 'admin_post_uafree_404_stop_capture', array( __CLASS__, 'stop_capture' ) );
		add_action( 'admin_post_uafree_404_export', array( __CLASS__, 'export_report' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( UAFREE_404_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu(): void {
		add_submenu_page(
			'uafree-suite',
			__( '404 Guard', 'ua-free-404-guard' ),
			__( '404 Guard', 'ua-free-404-guard' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'uafree_404_guard',
			Guard::SETTINGS_OPTION,
			array(
				'sanitize_callback' => array( Guard::class, 'sanitize_settings' ),
				'default'           => Guard::defaults(),
			)
		);
	}

	public static function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'ua-free-404-guard' ) . '</a>' );
		return $links;
	}

	public static function page(): void {
		self::authorise();
		self::render_notice();
		$settings   = Guard::settings();
		$capture    = Guard::capture_status();
		$capture_ready = Guard::capture_ready();
		$logs       = Guard::logs();
		$redirects  = Guard::redirects();
		$gone_rules = Guard::gone_rules();
		$scan       = Environment_Scanner::scan();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The deep-scan nonce is checked below before any scan runs.
		$deep_value = isset( $_GET['uafree_404_deep'] ) && is_string( $_GET['uafree_404_deep'] )
			? sanitize_key( wp_unslash( $_GET['uafree_404_deep'] ) )
			: '';
		$deep = '1' === $deep_value;
		if ( $deep ) {
			check_admin_referer( 'uafree_404_deep_scan' );
		}
		$link_audit = $deep ? Environment_Scanner::internal_link_audit( 100, true ) : Environment_Scanner::internal_link_not_performed();
		usort( $logs, static fn( array $a, array $b ): int => ( absint( $b['count'] ) <=> absint( $a['count'] ) ) ?: strcmp( (string) $b['last_seen'], (string) $a['last_seen'] ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'UA FREE 404 Guard', 'ua-free-404-guard' ); ?> <small style="font-size:14px"><?php echo esc_html( UAFREE_404_VERSION ); ?></small></h1>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No automatic redirect or 410 rules are created.', 'ua-free-404-guard' ); ?></p></div>

			<h2><?php esc_html_e( 'General settings', 'ua-free-404-guard' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'uafree_404_guard' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Protection', 'ua-free-404-guard' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( Guard::SETTINGS_OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Enable request handling', 'ua-free-404-guard' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Bot 404 response', 'ua-free-404-guard' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( Guard::SETTINGS_OPTION ); ?>[minimal_bot_404]" value="1" <?php checked( ! empty( $settings['minimal_bot_404'] ) ); ?>> <?php esc_html_e( 'Return a lightweight page without the theme for obvious bots', 'ua-free-404-guard' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Diagnostic capture', 'ua-free-404-guard' ); ?></th><td>
						<label><input type="checkbox" name="<?php echo esc_attr( Guard::SETTINGS_OPTION ); ?>[log_humans]" value="1" <?php checked( ! empty( $settings['log_humans'] ) ); ?>> <?php esc_html_e( 'Include human and unknown requests during an explicit capture window', 'ua-free-404-guard' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( Guard::SETTINGS_OPTION ); ?>[log_bots]" value="1" <?php checked( ! empty( $settings['log_bots'] ) ); ?>> <?php esc_html_e( 'Include obvious bots during an explicit capture window', 'ua-free-404-guard' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( Guard::SETTINGS_OPTION ); ?>[log_query_keys]" value="1" <?php checked( ! empty( $settings['log_query_keys'] ) ); ?>> <?php esc_html_e( 'Store salted query-key fingerprints, never names or values', 'ua-free-404-guard' ); ?></label>
						<p class="description"><?php esc_html_e( 'Persistent logging is disabled unless an administrator starts a ten-minute capture window.', 'ua-free-404-guard' ); ?></p>
					</td></tr>
					<tr><th><?php esc_html_e( 'Log size', 'ua-free-404-guard' ); ?></th><td><input type="number" min="50" max="1000" name="<?php echo esc_attr( Guard::SETTINGS_OPTION ); ?>[log_limit]" value="<?php echo esc_attr( (string) $settings['log_limit'] ); ?>"> <span class="description"><?php esc_html_e( 'Maximum grouped captured entries.', 'ua-free-404-guard' ); ?></span></td></tr>
					<tr><th><?php esc_html_e( 'Data layer', 'ua-free-404-guard' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( Guard::SETTINGS_OPTION ); ?>[emit_datalayer]" value="1" <?php checked( ! empty( $settings['emit_datalayer'] ) ); ?>> <?php esc_html_e( 'Emit a local event with a salted path fingerprint', 'ua-free-404-guard' ); ?></label><p class="description"><?php esc_html_e( 'No raw URL, query value or referrer is added to the event.', 'ua-free-404-guard' ); ?></p></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Bounded diagnostic capture', 'ua-free-404-guard' ); ?></h2>
			<?php if ( ! empty( $capture['active'] ) ) : ?>
				<?php
				$capture_message = sprintf(
					/* translators: 1: seconds remaining, 2: sampling denominator, 3: minimum seconds between grouped log writes. */
					__( 'Capture is active for approximately %1$d more seconds. Requests are sampled 1 in %2$d and the grouped log is written at most once every %3$d seconds.', 'ua-free-404-guard' ),
					$capture['seconds_remaining'],
					$capture['sample_denominator'],
					$capture['write_interval']
				);
				?>
				<div class="notice notice-info inline"><p><?php echo esc_html( $capture_message ); ?></p></div>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=uafree_404_stop_capture' ), 'uafree_404_stop_capture' ) ); ?>"><?php esc_html_e( 'Stop capture now', 'ua-free-404-guard' ); ?></a></p>
			<?php else : ?>
				<p><?php esc_html_e( 'No public request writes are performed while capture is inactive. A capture automatically expires after ten minutes.', 'ua-free-404-guard' ); ?></p>
				<?php if ( $capture_ready ) : ?>
					<p><a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=uafree_404_start_capture' ), 'uafree_404_start_capture' ) ); ?>"><?php esc_html_e( 'Start ten-minute sampled capture', 'ua-free-404-guard' ); ?></a></p>
				<?php else : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'Capture cannot start until the legacy or oversized log is explicitly sanitized.', 'ua-free-404-guard' ); ?></p></div>
				<?php endif; ?>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Redirect rules', 'ua-free-404-guard' ); ?></h2>
			<p><?php esc_html_e( 'Exact same-site rules only. Duplicate sources, protected paths and redirect cycles are rejected.', 'ua-free-404-guard' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
				<input type="hidden" name="action" value="uafree_404_add_redirect"><?php wp_nonce_field( 'uafree_404_add_redirect' ); ?>
				<label><?php esc_html_e( 'Source path', 'ua-free-404-guard' ); ?><br><input class="regular-text" name="source" placeholder="/old-page" required></label>
				<label><?php esc_html_e( 'Target path or same-site URL', 'ua-free-404-guard' ); ?><br><input class="regular-text" name="target" placeholder="/new-page" required></label>
				<label><?php esc_html_e( 'Status', 'ua-free-404-guard' ); ?><br><select name="status"><?php foreach ( array( 301, 302, 307, 308 ) as $status ) : ?><option value="<?php echo esc_attr( (string) $status ); ?>"><?php echo esc_html( (string) $status ); ?></option><?php endforeach; ?></select></label>
				<button class="button button-primary"><?php esc_html_e( 'Add redirect', 'ua-free-404-guard' ); ?></button>
			</form>
			<?php self::redirect_table( $redirects ); ?>

			<h2><?php esc_html_e( '410 rules', 'ua-free-404-guard' ); ?></h2>
			<p><?php esc_html_e( 'Rules are exact or boundary-aware. Query values are never written to the 404 log.', 'ua-free-404-guard' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
				<input type="hidden" name="action" value="uafree_404_add_gone"><?php wp_nonce_field( 'uafree_404_add_gone' ); ?>
				<label><?php esc_html_e( 'Match type', 'ua-free-404-guard' ); ?><br><select name="type"><option value="exact_path"><?php esc_html_e( 'Exact path', 'ua-free-404-guard' ); ?></option><option value="path_prefix"><?php esc_html_e( 'Path prefix', 'ua-free-404-guard' ); ?></option><option value="query_key"><?php esc_html_e( 'Query key', 'ua-free-404-guard' ); ?></option><option value="query_pair"><?php esc_html_e( 'Query key=value', 'ua-free-404-guard' ); ?></option></select></label>
				<label><?php esc_html_e( 'Value', 'ua-free-404-guard' ); ?><br><input class="regular-text" name="value" placeholder="/removed or key=value" required></label>
				<button class="button button-primary"><?php esc_html_e( 'Add 410 rule', 'ua-free-404-guard' ); ?></button>
			</form>
			<?php self::gone_table( $gone_rules ); ?>

			<h2><?php esc_html_e( 'Detected environment', 'ua-free-404-guard' ); ?></h2>
			<p><?php esc_html_e( 'The quick scanner reads plugin headers only and creates no rules.', 'ua-free-404-guard' ); ?></p>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;max-width:1100px">
				<?php self::detection_table( __( 'Page builders', 'ua-free-404-guard' ), $scan['page_builders'] ); ?>
				<?php self::detection_table( __( 'URL and redirect plugins', 'ua-free-404-guard' ), $scan['url_plugins'] ); ?>
			</div>
			<?php if ( ! empty( $scan['suggestions'] ) ) : ?><h3><?php esc_html_e( 'Review-only suggestions', 'ua-free-404-guard' ); ?></h3><ul><?php foreach ( $scan['suggestions'] as $suggestion ) : ?><li><code><?php echo esc_html( $suggestion['type'] . ': ' . $suggestion['value'] ); ?></code> — <?php echo esc_html( $suggestion['note'] ); ?></li><?php endforeach; ?></ul><?php endif; ?>

			<h2><?php esc_html_e( 'Internal link candidates', 'ua-free-404-guard' ); ?></h2>
			<?php if ( ! $deep ) : ?>
				<p><?php esc_html_e( 'Content is not scanned during a normal settings-page load.', 'ua-free-404-guard' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'uafree_404_deep', '1', admin_url( 'admin.php?page=' . self::PAGE ) ), 'uafree_404_deep_scan' ) ); ?>"><?php esc_html_e( 'Run explicit content scan', 'ua-free-404-guard' ); ?></a></p>
			<?php else : ?>
				<?php
				$audit_message = sprintf(
					/* translators: 1: posts checked, 2: links checked, 3: candidate links found. */
					__( 'Checked %1$d posts and %2$d links. Candidates: %3$d.', 'ua-free-404-guard' ),
					$link_audit['posts_checked'],
					$link_audit['links_checked'],
					$link_audit['candidate_count']
				);
				?>
				<p><?php echo esc_html( $audit_message ); ?></p>
				<?php self::candidate_table( $link_audit['items'] ); ?>
			<?php endif; ?>

			<h2><?php esc_html_e( '404/410 intelligence log', 'ua-free-404-guard' ); ?></h2>
			<p><?php esc_html_e( 'The sampled capture log stores no IP address, raw path, raw User-Agent, raw referrer, query name or query value.', 'ua-free-404-guard' ); ?></p>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=uafree_404_export' ), 'uafree_404_export' ) ); ?>"><?php esc_html_e( 'Export privacy-safe JSON report', 'ua-free-404-guard' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=uafree_404_sanitize_log' ), 'uafree_404_sanitize_log' ) ); ?>"><?php esc_html_e( 'Sanitize legacy log now', 'ua-free-404-guard' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=uafree_404_clear_log' ), 'uafree_404_clear_log' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Clear the local log?', 'ua-free-404-guard' ) ); ?>')"><?php esc_html_e( 'Clear log', 'ua-free-404-guard' ); ?></a>
			</p>
			<?php self::log_table( $logs ); ?>
		</div>
		<?php
	}

	private static function redirect_table( array $rules ): void {
		?><table class="widefat striped" style="margin-top:12px;max-width:1100px"><thead><tr><th><?php esc_html_e( 'Source', 'ua-free-404-guard' ); ?></th><th><?php esc_html_e( 'Target', 'ua-free-404-guard' ); ?></th><th><?php esc_html_e( 'Status', 'ua-free-404-guard' ); ?></th><th></th></tr></thead><tbody><?php if ( empty( $rules ) ) : ?><tr><td colspan="4"><?php esc_html_e( 'No redirect rules.', 'ua-free-404-guard' ); ?></td></tr><?php else : foreach ( $rules as $rule ) : ?><tr><td><code><?php echo esc_html( $rule['source'] ); ?></code></td><td><code><?php echo esc_html( $rule['target'] ); ?></code></td><td><?php echo esc_html( (string) $rule['status'] ); ?></td><td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=uafree_404_delete_redirect&id=' . rawurlencode( $rule['id'] ) ), 'uafree_404_delete_redirect_' . $rule['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'ua-free-404-guard' ); ?></a></td></tr><?php endforeach; endif; ?></tbody></table><?php
	}

	private static function gone_table( array $rules ): void {
		?><table class="widefat striped" style="margin-top:12px;max-width:1100px"><thead><tr><th><?php esc_html_e( 'Type', 'ua-free-404-guard' ); ?></th><th><?php esc_html_e( 'Value', 'ua-free-404-guard' ); ?></th><th></th></tr></thead><tbody><?php if ( empty( $rules ) ) : ?><tr><td colspan="3"><?php esc_html_e( 'No 410 rules.', 'ua-free-404-guard' ); ?></td></tr><?php else : foreach ( $rules as $rule ) : ?><tr><td><?php echo esc_html( $rule['type'] ); ?></td><td><code><?php echo esc_html( $rule['value'] ); ?></code></td><td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=uafree_404_delete_gone&id=' . rawurlencode( $rule['id'] ) ), 'uafree_404_delete_gone_' . $rule['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'ua-free-404-guard' ); ?></a></td></tr><?php endforeach; endif; ?></tbody></table><?php
	}

	private static function detection_table( string $title, array $items ): void {
		?><div><h3><?php echo esc_html( $title ); ?></h3><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Plugin', 'ua-free-404-guard' ); ?></th><th><?php esc_html_e( 'Installed', 'ua-free-404-guard' ); ?></th><th><?php esc_html_e( 'Active', 'ua-free-404-guard' ); ?></th></tr></thead><tbody><?php foreach ( $items as $item ) : ?><tr><td><?php echo esc_html( $item['name'] ); ?></td><td><?php echo esc_html( $item['installed'] ? __( 'Yes', 'ua-free-404-guard' ) : __( 'No', 'ua-free-404-guard' ) ); ?></td><td><?php echo esc_html( $item['active'] ? __( 'Yes', 'ua-free-404-guard' ) : __( 'No', 'ua-free-404-guard' ) ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php
	}

	private static function candidate_table( array $items ): void {
		?><table class="widefat striped" style="max-width:1100px"><thead><tr><th><?php esc_html_e( 'Content', 'ua-free-404-guard' ); ?></th><th><?php esc_html_e( 'Candidate URL', 'ua-free-404-guard' ); ?></th></tr></thead><tbody><?php if ( empty( $items ) ) : ?><tr><td colspan="2"><?php esc_html_e( 'No candidates found in the checked content.', 'ua-free-404-guard' ); ?></td></tr><?php else : foreach ( $items as $candidate ) : ?><tr><td><?php echo esc_html( $candidate['post_title'] ); ?> <code>#<?php echo esc_html( (string) $candidate['post_id'] ); ?></code></td><td><code><?php echo esc_html( $candidate['url'] ); ?></code></td></tr><?php endforeach; endif; ?></tbody></table><?php
	}

	private static function log_table( array $logs ): void {
		?><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Status', 'ua-free-404-guard' ); ?></th><th><?php esc_html_e( 'Type', 'ua-free-404-guard' ); ?></th><th><?php esc_html_e( 'Captured count', 'ua-free-404-guard' ); ?></th><th><?php esc_html_e( 'Sample', 'ua-free-404-guard' ); ?></th><th><?php esc_html_e( 'Path fingerprint', 'ua-free-404-guard' ); ?></th><th><?php esc_html_e( 'Query-key fingerprints', 'ua-free-404-guard' ); ?></th><th><?php esc_html_e( 'Last seen', 'ua-free-404-guard' ); ?></th><th><?php esc_html_e( 'Referrer scope', 'ua-free-404-guard' ); ?></th><th><?php esc_html_e( 'Recommendation', 'ua-free-404-guard' ); ?></th></tr></thead><tbody><?php if ( empty( $logs ) ) : ?><tr><td colspan="9"><?php esc_html_e( 'No captured entries yet.', 'ua-free-404-guard' ); ?></td></tr><?php else : foreach ( array_slice( $logs, 0, 500 ) as $row ) : ?><tr><td><?php echo esc_html( $row['status'] ); ?></td><td><?php echo esc_html( $row['kind'] ); ?></td><td><?php echo esc_html( (string) $row['count'] ); ?></td><td>1/<?php echo esc_html( (string) $row['sample_denominator'] ); ?></td><td><code><?php echo esc_html( $row['path_fingerprint'] ); ?></code></td><td><code><?php echo esc_html( implode( ', ', $row['query_key_fingerprints'] ) ); ?></code></td><td><?php echo esc_html( $row['last_seen'] ); ?></td><td><?php echo esc_html( $row['referrer_scope'] ); ?></td><td><?php echo esc_html( Guard::suggestion( $row ) ); ?></td></tr><?php endforeach; endif; ?></tbody></table><?php
	}

	public static function add_redirect(): void {
		self::authorise();
		check_admin_referer( 'uafree_404_add_redirect' );
		$source_raw = isset( $_POST['source'] ) && is_string( $_POST['source'] )
			? sanitize_text_field( wp_unslash( $_POST['source'] ) )
			: '';
		$target_raw = isset( $_POST['target'] ) && is_string( $_POST['target'] )
			? sanitize_text_field( wp_unslash( $_POST['target'] ) )
			: '';
		$status = isset( $_POST['status'] ) && is_scalar( $_POST['status'] )
			? absint( wp_unslash( $_POST['status'] ) )
			: 301;
		$source = Guard::normalise_path( $source_raw );
		$target = Guard::normalise_same_site_url( $target_raw );
		if ( Guard::is_protected_path( $source ) || '' === $target || ! in_array( $status, array( 301, 302, 307, 308 ), true ) ) {
			self::redirect_back( 'invalid_redirect' );
		}
		if ( Guard::has_redirect_source( $source ) ) {
			self::redirect_back( 'duplicate_redirect' );
		}
		if ( Guard::would_create_redirect_cycle( $source, $target ) ) {
			self::redirect_back( 'redirect_loop' );
		}
		$rules   = Guard::redirects();
		$rules[] = array( 'id' => wp_generate_uuid4(), 'source' => $source, 'target' => $target, 'status' => $status, 'enabled' => 1 );
		update_option( Guard::REDIRECT_OPTION, $rules, false );
		self::redirect_back( 'redirect_added' );
	}

	public static function delete_redirect(): void {
		self::authorise();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce action contains this record ID and is checked immediately below.
		$id = isset( $_GET['id'] ) && is_string( $_GET['id'] )
			? sanitize_text_field( wp_unslash( $_GET['id'] ) )
			: '';
		check_admin_referer( 'uafree_404_delete_redirect_' . $id );
		$rules = array_values( array_filter( Guard::redirects(), static fn( array $rule ): bool => (string) $rule['id'] !== $id ) );
		update_option( Guard::REDIRECT_OPTION, $rules, false );
		self::redirect_back( 'redirect_deleted' );
	}

	public static function add_gone(): void {
		self::authorise();
		check_admin_referer( 'uafree_404_add_gone' );
		$type = isset( $_POST['type'] ) && is_string( $_POST['type'] )
			? sanitize_key( wp_unslash( $_POST['type'] ) )
			: '';
		$value_raw = isset( $_POST['value'] ) && is_string( $_POST['value'] )
			? sanitize_text_field( wp_unslash( $_POST['value'] ) )
			: '';
		$value = Guard::normalise_gone_value( $type, $value_raw );
		if ( '' === $value ) {
			self::redirect_back( 'invalid_gone' );
		}
		$rules   = Guard::gone_rules();
		$rules[] = array( 'id' => wp_generate_uuid4(), 'type' => $type, 'value' => $value, 'enabled' => 1 );
		update_option( Guard::GONE_OPTION, $rules, false );
		self::redirect_back( 'gone_added' );
	}

	public static function delete_gone(): void {
		self::authorise();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce action contains this record ID and is checked immediately below.
		$id = isset( $_GET['id'] ) && is_string( $_GET['id'] )
			? sanitize_text_field( wp_unslash( $_GET['id'] ) )
			: '';
		check_admin_referer( 'uafree_404_delete_gone_' . $id );
		$rules = array_values( array_filter( Guard::gone_rules(), static fn( array $rule ): bool => (string) $rule['id'] !== $id ) );
		update_option( Guard::GONE_OPTION, $rules, false );
		self::redirect_back( 'gone_deleted' );
	}

	public static function start_capture(): void {
		self::authorise();
		check_admin_referer( 'uafree_404_start_capture' );
		if ( ! Guard::capture_ready() ) {
			self::redirect_back( 'capture_requires_sanitization' );
		}
		Guard::start_capture();
		self::redirect_back( 'capture_started' );
	}

	public static function stop_capture(): void {
		self::authorise();
		check_admin_referer( 'uafree_404_stop_capture' );
		Guard::stop_capture();
		self::redirect_back( 'capture_stopped' );
	}

	public static function clear_log(): void {
		self::authorise();
		check_admin_referer( 'uafree_404_clear_log' );
		update_option( Guard::LOG_OPTION, array(), false );
		self::redirect_back( 'log_cleared' );
	}

	public static function sanitize_log(): void {
		self::authorise();
		check_admin_referer( 'uafree_404_sanitize_log' );
		Guard::sanitize_legacy_log();
		self::redirect_back( 'log_sanitized' );
	}

	public static function export_report(): void {
		self::authorise();
		check_admin_referer( 'uafree_404_export' );
		$link_audit = Environment_Scanner::internal_link_audit( 200, false );
		$report     = array(
			'generated_at'        => gmdate( 'c' ),
			'component'           => array(
				'name'             => 'UA FREE 404 Guard',
				'version'          => UAFREE_404_VERSION,
				'log_schema'       => 3,
				'privacy_contract' => 3,
			),
			'redirect_rules'      => Guard::export_redirects(),
			'gone_rules'          => Guard::export_gone_rules(),
			'grouped_log'         => Guard::export_logs(),
			'environment'         => Environment_Scanner::privacy_summary(),
			'internal_link_audit' => $link_audit,
			'privacy_contract'    => 3,
			'privacy_note'        => 'No IP address, raw path, query name, query value, raw referrer, User-Agent, post identifier, title, content, plugin file path, option name or option value is exported.',
		);
		$json = wp_json_encode( $report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		if ( ! is_string( $json ) ) {
			wp_die( esc_html__( 'The JSON report could not be generated.', 'ua-free-404-guard' ) );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ua-free-404-report-' . gmdate( 'Ymd-His' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download after wp_json_encode().
		exit;
	}

	private static function redirect_back( string $notice ): void {
		wp_safe_redirect( add_query_arg( 'uafree_notice', $notice, admin_url( 'admin.php?page=' . self::PAGE ) ) );
		exit;
	}

	private static function render_notice(): void {
		$code = filter_input( INPUT_GET, 'uafree_notice', FILTER_SANITIZE_SPECIAL_CHARS );
		$code = is_string( $code ) ? sanitize_key( $code ) : '';
		$messages = array(
			'redirect_added'    => array( 'success', __( 'Redirect rule added.', 'ua-free-404-guard' ) ),
			'redirect_deleted'  => array( 'success', __( 'Redirect rule deleted.', 'ua-free-404-guard' ) ),
			'gone_added'        => array( 'success', __( '410 rule added.', 'ua-free-404-guard' ) ),
			'gone_deleted'      => array( 'success', __( '410 rule deleted.', 'ua-free-404-guard' ) ),
			'log_cleared'       => array( 'success', __( 'Local log cleared.', 'ua-free-404-guard' ) ),
			'log_sanitized'     => array( 'success', __( 'Legacy log rows were rewritten using the privacy-safe schema.', 'ua-free-404-guard' ) ),
			'capture_started'   => array( 'success', __( 'A ten-minute sampled capture window was started.', 'ua-free-404-guard' ) ),
			'capture_stopped'   => array( 'success', __( 'The sampled capture window was stopped.', 'ua-free-404-guard' ) ),
			'capture_requires_sanitization' => array( 'error', __( 'Sanitize or reduce the stored log before starting capture.', 'ua-free-404-guard' ) ),
			'invalid_redirect'  => array( 'error', __( 'The redirect was not saved. Use a non-system source path and a valid same-site HTTP/HTTPS target.', 'ua-free-404-guard' ) ),
			'duplicate_redirect'=> array( 'error', __( 'A redirect already exists for this source path.', 'ua-free-404-guard' ) ),
			'redirect_loop'     => array( 'error', __( 'The redirect was not saved because it creates a redirect cycle.', 'ua-free-404-guard' ) ),
			'invalid_gone'      => array( 'error', __( 'The 410 rule was not saved. Check the type, value and protected paths.', 'ua-free-404-guard' ) ),
		);
		if ( isset( $messages[ $code ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $code ][0] ), esc_html( $messages[ $code ][1] ) );
		}
	}

	private static function authorise(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'ua-free-404-guard' ) );
		}
	}
}
