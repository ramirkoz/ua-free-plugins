<?php
namespace ramirkz\koz404;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZ404_Admin {
	private const PAGE = 'koz-404-guard';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_koz404_add_redirect', array( __CLASS__, 'add_redirect' ) );
		add_action( 'admin_post_koz404_delete_redirect', array( __CLASS__, 'delete_redirect' ) );
		add_action( 'admin_post_koz404_add_gone', array( __CLASS__, 'add_gone' ) );
		add_action( 'admin_post_koz404_delete_gone', array( __CLASS__, 'delete_gone' ) );
		add_action( 'admin_post_koz404_clear_log', array( __CLASS__, 'clear_log' ) );
		add_action( 'admin_post_koz404_sanitize_log', array( __CLASS__, 'sanitize_log' ) );
		add_action( 'admin_post_koz404_start_capture', array( __CLASS__, 'start_capture' ) );
		add_action( 'admin_post_koz404_stop_capture', array( __CLASS__, 'stop_capture' ) );
		add_action( 'admin_post_koz404_export', array( __CLASS__, 'export_report' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KOZ404_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu(): void {
		$parent = KOZ404_Suite_Registry::suite_page();
		add_submenu_page(
			$parent,
			__( '404 Guard', 'koz-404-guard' ),
			__( '404 Guard', 'koz-404-guard' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'koz404_settings',
			KOZ404_Guard::SETTINGS_OPTION,
			array(
				'sanitize_callback' => array( KOZ404_KOZ404_Guard::class, 'sanitize_settings' ),
				'default'           => KOZ404_Guard::defaults(),
			)
		);
	}

	public static function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'koz-404-guard' ) . '</a>' );
		return $links;
	}

	public static function page(): void {
		self::authorise();
		self::render_notice();
		$settings   = KOZ404_Guard::settings();
		$capture    = KOZ404_Guard::capture_status();
		$capture_ready = KOZ404_Guard::capture_ready();
		$logs       = KOZ404_Guard::logs();
		$redirects  = KOZ404_Guard::redirects();
		$gone_rules = KOZ404_Guard::gone_rules();
		$scan       = KOZ404_Environment_Scanner::scan();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The deep-scan nonce is checked below before any scan runs.
		$deep_value = isset( $_GET['koz404_deep'] ) && is_string( $_GET['koz404_deep'] )
			? sanitize_key( wp_unslash( $_GET['koz404_deep'] ) )
			: '';
		$deep = '1' === $deep_value;
		if ( $deep ) {
			check_admin_referer( 'koz404_deep_scan' );
		}
		$link_audit = $deep ? KOZ404_Environment_Scanner::internal_link_audit( 100, true ) : KOZ404_Environment_Scanner::internal_link_not_performed();
		usort( $logs, static fn( array $a, array $b ): int => ( absint( $b['count'] ) <=> absint( $a['count'] ) ) ?: strcmp( (string) $b['last_seen'], (string) $a['last_seen'] ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'KOZ 404 Guard & URL Intelligence', 'koz-404-guard' ); ?> <small class="koz404-version"><?php echo esc_html( KOZ404_VERSION ); ?></small></h1>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No automatic redirect or 410 rules are created.', 'koz-404-guard' ); ?></p></div>

			<h2><?php esc_html_e( 'General settings', 'koz-404-guard' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'koz404_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Protection', 'koz-404-guard' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( KOZ404_Guard::SETTINGS_OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Enable request handling', 'koz-404-guard' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Bot 404 response', 'koz-404-guard' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( KOZ404_Guard::SETTINGS_OPTION ); ?>[minimal_bot_404]" value="1" <?php checked( ! empty( $settings['minimal_bot_404'] ) ); ?>> <?php esc_html_e( 'Return a lightweight page without the theme for obvious bots', 'koz-404-guard' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Diagnostic capture', 'koz-404-guard' ); ?></th><td>
						<label><input type="checkbox" name="<?php echo esc_attr( KOZ404_Guard::SETTINGS_OPTION ); ?>[log_humans]" value="1" <?php checked( ! empty( $settings['log_humans'] ) ); ?>> <?php esc_html_e( 'Include human and unknown requests during an explicit capture window', 'koz-404-guard' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( KOZ404_Guard::SETTINGS_OPTION ); ?>[log_bots]" value="1" <?php checked( ! empty( $settings['log_bots'] ) ); ?>> <?php esc_html_e( 'Include obvious bots during an explicit capture window', 'koz-404-guard' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( KOZ404_Guard::SETTINGS_OPTION ); ?>[log_query_keys]" value="1" <?php checked( ! empty( $settings['log_query_keys'] ) ); ?>> <?php esc_html_e( 'Store salted query-key fingerprints, never names or values', 'koz-404-guard' ); ?></label>
						<p class="description"><?php esc_html_e( 'Persistent logging is disabled unless an administrator starts a ten-minute capture window.', 'koz-404-guard' ); ?></p>
					</td></tr>
					<tr><th><?php esc_html_e( 'Log size', 'koz-404-guard' ); ?></th><td><input type="number" min="50" max="1000" name="<?php echo esc_attr( KOZ404_Guard::SETTINGS_OPTION ); ?>[log_limit]" value="<?php echo esc_attr( (string) $settings['log_limit'] ); ?>"> <span class="description"><?php esc_html_e( 'Maximum grouped captured entries.', 'koz-404-guard' ); ?></span></td></tr>
					<tr><th><?php esc_html_e( 'Data layer', 'koz-404-guard' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( KOZ404_Guard::SETTINGS_OPTION ); ?>[emit_datalayer]" value="1" <?php checked( ! empty( $settings['emit_datalayer'] ) ); ?>> <?php esc_html_e( 'Emit a local event with a salted path fingerprint', 'koz-404-guard' ); ?></label><p class="description"><?php esc_html_e( 'No raw URL, query value or referrer is added to the event.', 'koz-404-guard' ); ?></p></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Bounded diagnostic capture', 'koz-404-guard' ); ?></h2>
			<?php if ( ! empty( $capture['active'] ) ) : ?>
				<?php
				$capture_message = sprintf(
					/* translators: 1: seconds remaining, 2: sampling denominator, 3: minimum seconds between grouped log writes. */
					__( 'Capture is active for approximately %1$d more seconds. Requests are sampled 1 in %2$d and the grouped log is written at most once every %3$d seconds.', 'koz-404-guard' ),
					$capture['seconds_remaining'],
					$capture['sample_denominator'],
					$capture['write_interval']
				);
				?>
				<div class="notice notice-info inline"><p><?php echo esc_html( $capture_message ); ?></p></div>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=koz404_stop_capture' ), 'koz404_stop_capture' ) ); ?>"><?php esc_html_e( 'Stop capture now', 'koz-404-guard' ); ?></a></p>
			<?php else : ?>
				<p><?php esc_html_e( 'No public request writes are performed while capture is inactive. A capture automatically expires after ten minutes.', 'koz-404-guard' ); ?></p>
				<?php if ( $capture_ready ) : ?>
					<p><a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=koz404_start_capture' ), 'koz404_start_capture' ) ); ?>"><?php esc_html_e( 'Start ten-minute sampled capture', 'koz-404-guard' ); ?></a></p>
				<?php else : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'Capture cannot start until the legacy or oversized log is explicitly sanitized.', 'koz-404-guard' ); ?></p></div>
				<?php endif; ?>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Redirect rules', 'koz-404-guard' ); ?></h2>
			<p><?php esc_html_e( 'Exact same-site rules only. Duplicate sources, protected paths and redirect cycles are rejected.', 'koz-404-guard' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
				<input type="hidden" name="action" value="koz404_add_redirect"><?php wp_nonce_field( 'koz404_add_redirect' ); ?>
				<label><?php esc_html_e( 'Source path', 'koz-404-guard' ); ?><br><input class="regular-text" name="source" placeholder="/old-page" required></label>
				<label><?php esc_html_e( 'Target path or same-site URL', 'koz-404-guard' ); ?><br><input class="regular-text" name="target" placeholder="/new-page" required></label>
				<label><?php esc_html_e( 'Status', 'koz-404-guard' ); ?><br><select name="status"><?php foreach ( array( 301, 302, 307, 308 ) as $status ) : ?><option value="<?php echo esc_attr( (string) $status ); ?>"><?php echo esc_html( (string) $status ); ?></option><?php endforeach; ?></select></label>
				<button class="button button-primary"><?php esc_html_e( 'Add redirect', 'koz-404-guard' ); ?></button>
			</form>
			<?php self::redirect_table( $redirects ); ?>

			<h2><?php esc_html_e( '410 rules', 'koz-404-guard' ); ?></h2>
			<p><?php esc_html_e( 'Rules are exact or boundary-aware. Query values are never written to the 404 log.', 'koz-404-guard' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
				<input type="hidden" name="action" value="koz404_add_gone"><?php wp_nonce_field( 'koz404_add_gone' ); ?>
				<label><?php esc_html_e( 'Match type', 'koz-404-guard' ); ?><br><select name="type"><option value="exact_path"><?php esc_html_e( 'Exact path', 'koz-404-guard' ); ?></option><option value="path_prefix"><?php esc_html_e( 'Path prefix', 'koz-404-guard' ); ?></option><option value="query_key"><?php esc_html_e( 'Query key', 'koz-404-guard' ); ?></option><option value="query_pair"><?php esc_html_e( 'Query key=value', 'koz-404-guard' ); ?></option></select></label>
				<label><?php esc_html_e( 'Value', 'koz-404-guard' ); ?><br><input class="regular-text" name="value" placeholder="/removed or key=value" required></label>
				<button class="button button-primary"><?php esc_html_e( 'Add 410 rule', 'koz-404-guard' ); ?></button>
			</form>
			<?php self::gone_table( $gone_rules ); ?>

			<h2><?php esc_html_e( 'Detected environment', 'koz-404-guard' ); ?></h2>
			<p><?php esc_html_e( 'The quick scanner reads plugin headers only and creates no rules.', 'koz-404-guard' ); ?></p>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;max-width:1100px">
				<?php self::detection_table( __( 'Page builders', 'koz-404-guard' ), $scan['page_builders'] ); ?>
				<?php self::detection_table( __( 'URL and redirect plugins', 'koz-404-guard' ), $scan['url_plugins'] ); ?>
			</div>
			<?php if ( ! empty( $scan['suggestions'] ) ) : ?><h3><?php esc_html_e( 'Review-only suggestions', 'koz-404-guard' ); ?></h3><ul><?php foreach ( $scan['suggestions'] as $suggestion ) : ?><li><code><?php echo esc_html( $suggestion['type'] . ': ' . $suggestion['value'] ); ?></code> — <?php echo esc_html( $suggestion['note'] ); ?></li><?php endforeach; ?></ul><?php endif; ?>

			<h2><?php esc_html_e( 'Internal link candidates', 'koz-404-guard' ); ?></h2>
			<?php if ( ! $deep ) : ?>
				<p><?php esc_html_e( 'Content is not scanned during a normal settings-page load.', 'koz-404-guard' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'koz404_deep', '1', admin_url( 'admin.php?page=' . self::PAGE ) ), 'koz404_deep_scan' ) ); ?>"><?php esc_html_e( 'Run explicit content scan', 'koz-404-guard' ); ?></a></p>
			<?php else : ?>
				<?php
				$audit_message = sprintf(
					/* translators: 1: posts checked, 2: links checked, 3: candidate links found. */
					__( 'Checked %1$d posts and %2$d links. Candidates: %3$d.', 'koz-404-guard' ),
					$link_audit['posts_checked'],
					$link_audit['links_checked'],
					$link_audit['candidate_count']
				);
				?>
				<p><?php echo esc_html( $audit_message ); ?></p>
				<?php self::candidate_table( $link_audit['items'] ); ?>
			<?php endif; ?>

			<h2><?php esc_html_e( '404/410 intelligence log', 'koz-404-guard' ); ?></h2>
			<p><?php esc_html_e( 'The sampled capture log stores no IP address, raw path, raw User-Agent, raw referrer, query name or query value.', 'koz-404-guard' ); ?></p>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=koz404_export' ), 'koz404_export' ) ); ?>"><?php esc_html_e( 'Export privacy-safe JSON report', 'koz-404-guard' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=koz404_sanitize_log' ), 'koz404_sanitize_log' ) ); ?>"><?php esc_html_e( 'Sanitize legacy log now', 'koz-404-guard' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=koz404_clear_log' ), 'koz404_clear_log' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Clear the local log?', 'koz-404-guard' ) ); ?>')"><?php esc_html_e( 'Clear log', 'koz-404-guard' ); ?></a>
			</p>
			<?php self::log_table( $logs ); ?>
		</div>
		<?php
	}

	private static function redirect_table( array $rules ): void {
		?><table class="widefat striped" style="margin-top:12px;max-width:1100px"><thead><tr><th><?php esc_html_e( 'Source', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Target', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Status', 'koz-404-guard' ); ?></th><th></th></tr></thead><tbody><?php if ( empty( $rules ) ) : ?><tr><td colspan="4"><?php esc_html_e( 'No redirect rules.', 'koz-404-guard' ); ?></td></tr><?php else : foreach ( $rules as $rule ) : ?><tr><td><code><?php echo esc_html( $rule['source'] ); ?></code></td><td><code><?php echo esc_html( $rule['target'] ); ?></code></td><td><?php echo esc_html( (string) $rule['status'] ); ?></td><td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=koz404_delete_redirect&id=' . rawurlencode( $rule['id'] ) ), 'koz404_delete_redirect_' . $rule['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'koz-404-guard' ); ?></a></td></tr><?php endforeach; endif; ?></tbody></table><?php
	}

	private static function gone_table( array $rules ): void {
		?><table class="widefat striped" style="margin-top:12px;max-width:1100px"><thead><tr><th><?php esc_html_e( 'Type', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Value', 'koz-404-guard' ); ?></th><th></th></tr></thead><tbody><?php if ( empty( $rules ) ) : ?><tr><td colspan="3"><?php esc_html_e( 'No 410 rules.', 'koz-404-guard' ); ?></td></tr><?php else : foreach ( $rules as $rule ) : ?><tr><td><?php echo esc_html( $rule['type'] ); ?></td><td><code><?php echo esc_html( $rule['value'] ); ?></code></td><td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=koz404_delete_gone&id=' . rawurlencode( $rule['id'] ) ), 'koz404_delete_gone_' . $rule['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'koz-404-guard' ); ?></a></td></tr><?php endforeach; endif; ?></tbody></table><?php
	}

	private static function detection_table( string $title, array $items ): void {
		?><div><h3><?php echo esc_html( $title ); ?></h3><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Plugin', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Installed', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Active', 'koz-404-guard' ); ?></th></tr></thead><tbody><?php foreach ( $items as $item ) : ?><tr><td><?php echo esc_html( $item['name'] ); ?></td><td><?php echo esc_html( $item['installed'] ? __( 'Yes', 'koz-404-guard' ) : __( 'No', 'koz-404-guard' ) ); ?></td><td><?php echo esc_html( $item['active'] ? __( 'Yes', 'koz-404-guard' ) : __( 'No', 'koz-404-guard' ) ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php
	}

	private static function candidate_table( array $items ): void {
		?><table class="widefat striped" style="max-width:1100px"><thead><tr><th><?php esc_html_e( 'Content', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Candidate URL', 'koz-404-guard' ); ?></th></tr></thead><tbody><?php if ( empty( $items ) ) : ?><tr><td colspan="2"><?php esc_html_e( 'No candidates found in the checked content.', 'koz-404-guard' ); ?></td></tr><?php else : foreach ( $items as $candidate ) : ?><tr><td><?php echo esc_html( $candidate['post_title'] ); ?> <code>#<?php echo esc_html( (string) $candidate['post_id'] ); ?></code></td><td><code><?php echo esc_html( $candidate['url'] ); ?></code></td></tr><?php endforeach; endif; ?></tbody></table><?php
	}

	private static function log_table( array $logs ): void {
		?><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Status', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Type', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Source', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Captured count', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Sample', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Path fingerprint', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Query-key fingerprints', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Last seen', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Referrer scope', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Recommendation', 'koz-404-guard' ); ?></th></tr></thead><tbody><?php if ( empty( $logs ) ) : ?><tr><td colspan="10"><?php esc_html_e( 'No captured entries yet.', 'koz-404-guard' ); ?></td></tr><?php else : foreach ( array_slice( $logs, 0, 500 ) as $row ) : ?><tr><td><?php echo esc_html( $row['status'] ); ?></td><td><?php echo esc_html( $row['kind'] ); ?></td><td><code><?php echo esc_html( $row['source'] ); ?></code></td><td><?php echo esc_html( (string) $row['count'] ); ?></td><td>1/<?php echo esc_html( (string) $row['sample_denominator'] ); ?></td><td><code><?php echo esc_html( $row['path_fingerprint'] ); ?></code></td><td><code><?php echo esc_html( implode( ', ', $row['query_key_fingerprints'] ) ); ?></code></td><td><?php echo esc_html( $row['last_seen'] ); ?></td><td><?php echo esc_html( $row['referrer_scope'] ); ?></td><td><?php echo esc_html( KOZ404_Guard::suggestion( $row ) ); ?></td></tr><?php endforeach; endif; ?></tbody></table><?php
	}

	public static function add_redirect(): void {
		self::authorise();
		check_admin_referer( 'koz404_add_redirect' );
		$source_raw = isset( $_POST['source'] ) && is_string( $_POST['source'] )
			? sanitize_text_field( wp_unslash( $_POST['source'] ) )
			: '';
		$target_raw = isset( $_POST['target'] ) && is_string( $_POST['target'] )
			? sanitize_text_field( wp_unslash( $_POST['target'] ) )
			: '';
		$status = isset( $_POST['status'] ) && is_scalar( $_POST['status'] )
			? absint( wp_unslash( $_POST['status'] ) )
			: 301;
		$source = KOZ404_Guard::normalise_path( $source_raw );
		$target = KOZ404_Guard::normalise_same_site_url( $target_raw );
		if ( KOZ404_Guard::is_protected_path( $source ) || '' === $target || ! in_array( $status, array( 301, 302, 307, 308 ), true ) ) {
			self::redirect_back( 'invalid_redirect' );
		}
		if ( KOZ404_Guard::has_redirect_source( $source ) ) {
			self::redirect_back( 'duplicate_redirect' );
		}
		if ( KOZ404_Guard::would_create_redirect_cycle( $source, $target ) ) {
			self::redirect_back( 'redirect_loop' );
		}
		$rules   = KOZ404_Guard::redirects();
		$rules[] = array( 'id' => wp_generate_uuid4(), 'source' => $source, 'target' => $target, 'status' => $status, 'enabled' => 1 );
		update_option( KOZ404_Guard::REDIRECT_OPTION, $rules, false );
		self::redirect_back( 'redirect_added' );
	}

	public static function delete_redirect(): void {
		self::authorise();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce action contains this record ID and is checked immediately below.
		$id = isset( $_GET['id'] ) && is_string( $_GET['id'] )
			? sanitize_text_field( wp_unslash( $_GET['id'] ) )
			: '';
		check_admin_referer( 'koz404_delete_redirect_' . $id );
		$rules = array_values( array_filter( KOZ404_Guard::redirects(), static fn( array $rule ): bool => (string) $rule['id'] !== $id ) );
		update_option( KOZ404_Guard::REDIRECT_OPTION, $rules, false );
		self::redirect_back( 'redirect_deleted' );
	}

	public static function add_gone(): void {
		self::authorise();
		check_admin_referer( 'koz404_add_gone' );
		$type = isset( $_POST['type'] ) && is_string( $_POST['type'] )
			? sanitize_key( wp_unslash( $_POST['type'] ) )
			: '';
		$value_raw = isset( $_POST['value'] ) && is_string( $_POST['value'] )
			? sanitize_text_field( wp_unslash( $_POST['value'] ) )
			: '';
		$value = KOZ404_Guard::normalise_gone_value( $type, $value_raw );
		if ( '' === $value ) {
			self::redirect_back( 'invalid_gone' );
		}
		$rules   = KOZ404_Guard::gone_rules();
		$rules[] = array( 'id' => wp_generate_uuid4(), 'type' => $type, 'value' => $value, 'enabled' => 1 );
		update_option( KOZ404_Guard::GONE_OPTION, $rules, false );
		self::redirect_back( 'gone_added' );
	}

	public static function delete_gone(): void {
		self::authorise();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce action contains this record ID and is checked immediately below.
		$id = isset( $_GET['id'] ) && is_string( $_GET['id'] )
			? sanitize_text_field( wp_unslash( $_GET['id'] ) )
			: '';
		check_admin_referer( 'koz404_delete_gone_' . $id );
		$rules = array_values( array_filter( KOZ404_Guard::gone_rules(), static fn( array $rule ): bool => (string) $rule['id'] !== $id ) );
		update_option( KOZ404_Guard::GONE_OPTION, $rules, false );
		self::redirect_back( 'gone_deleted' );
	}

	public static function start_capture(): void {
		self::authorise();
		check_admin_referer( 'koz404_start_capture' );
		if ( ! KOZ404_Guard::capture_ready() ) {
			self::redirect_back( 'capture_requires_sanitization' );
		}
		KOZ404_Guard::start_capture();
		self::redirect_back( 'capture_started' );
	}

	public static function stop_capture(): void {
		self::authorise();
		check_admin_referer( 'koz404_stop_capture' );
		KOZ404_Guard::stop_capture();
		self::redirect_back( 'capture_stopped' );
	}

	public static function clear_log(): void {
		self::authorise();
		check_admin_referer( 'koz404_clear_log' );
		update_option( KOZ404_Guard::LOG_OPTION, array(), false );
		self::redirect_back( 'log_cleared' );
	}

	public static function sanitize_log(): void {
		self::authorise();
		check_admin_referer( 'koz404_sanitize_log' );
		KOZ404_Guard::sanitize_legacy_log();
		self::redirect_back( 'log_sanitized' );
	}

	public static function export_report(): void {
		self::authorise();
		check_admin_referer( 'koz404_export' );
		$link_audit = KOZ404_Environment_Scanner::internal_link_audit( 200, false );
		$report     = array(
			'generated_at'        => gmdate( 'c' ),
			'component'           => array(
				'name'             => 'KOZ 404 Guard & URL Intelligence',
				'version'          => KOZ404_VERSION,
				'log_schema'       => 4,
				'privacy_contract' => 3,
			),
			'redirect_rules'      => KOZ404_Guard::export_redirects(),
			'gone_rules'          => KOZ404_Guard::export_gone_rules(),
			'grouped_log'         => KOZ404_Guard::export_logs(),
			'environment'         => KOZ404_Environment_Scanner::privacy_summary(),
			'internal_link_audit' => $link_audit,
			'privacy_contract'    => 3,
			'privacy_note'        => 'No IP address, raw path, query name, query value, raw referrer, User-Agent, post identifier, title, content, plugin file path, option name or option value is exported.',
		);
		$json = wp_json_encode( $report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		if ( ! is_string( $json ) ) {
			wp_die( esc_html__( 'The JSON report could not be generated.', 'koz-404-guard' ) );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="koz-404-report-' . gmdate( 'Ymd-His' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download after wp_json_encode().
		exit;
	}

	private static function redirect_back( string $notice ): void {
		wp_safe_redirect( add_query_arg( 'koz404_notice', $notice, admin_url( 'admin.php?page=' . self::PAGE ) ) );
		exit;
	}

	private static function render_notice(): void {
		$code = filter_input( INPUT_GET, 'koz404_notice', FILTER_SANITIZE_SPECIAL_CHARS );
		$code = is_string( $code ) ? sanitize_key( $code ) : '';
		$messages = array(
			'redirect_added'    => array( 'success', __( 'Redirect rule added.', 'koz-404-guard' ) ),
			'redirect_deleted'  => array( 'success', __( 'Redirect rule deleted.', 'koz-404-guard' ) ),
			'gone_added'        => array( 'success', __( '410 rule added.', 'koz-404-guard' ) ),
			'gone_deleted'      => array( 'success', __( '410 rule deleted.', 'koz-404-guard' ) ),
			'log_cleared'       => array( 'success', __( 'Local log cleared.', 'koz-404-guard' ) ),
			'log_sanitized'     => array( 'success', __( 'Legacy log rows were rewritten using the privacy-safe schema.', 'koz-404-guard' ) ),
			'capture_started'   => array( 'success', __( 'A ten-minute sampled capture window was started.', 'koz-404-guard' ) ),
			'capture_stopped'   => array( 'success', __( 'The sampled capture window was stopped.', 'koz-404-guard' ) ),
			'capture_requires_sanitization' => array( 'error', __( 'Sanitize or reduce the stored log before starting capture.', 'koz-404-guard' ) ),
			'invalid_redirect'  => array( 'error', __( 'The redirect was not saved. Use a non-system source path and a valid same-site HTTP/HTTPS target.', 'koz-404-guard' ) ),
			'duplicate_redirect'=> array( 'error', __( 'A redirect already exists for this source path.', 'koz-404-guard' ) ),
			'redirect_loop'     => array( 'error', __( 'The redirect was not saved because it creates a redirect cycle.', 'koz-404-guard' ) ),
			'invalid_gone'      => array( 'error', __( 'The 410 rule was not saved. Check the type, value and protected paths.', 'koz-404-guard' ) ),
		);
		if ( isset( $messages[ $code ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $code ][0] ), esc_html( $messages[ $code ][1] ) );
		}
	}

	private static function authorise(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'koz-404-guard' ) );
		}
	}
}
