<?php
/**
 * Admin screen with tabs: 404 log, redirects, 410 rules, settings and tools.
 *
 * @package KOZ_404_Guard
 */

namespace ramirkz\koz404;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZ404_Admin {
	private const PAGE = 'koz-404-guard';
	private const TABS = array( 'log', 'redirects', 'gone', 'settings', 'tools' );

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'update_option_' . KOZ404_Guard::SETTINGS_OPTION, array( __CLASS__, 'settings_changed' ), 10, 2 );
		foreach ( array( 'save_redirect', 'redirect_action', 'save_gone', 'gone_action', 'log_action', 'suggestion_action', 'clear_log', 'forget_paths', 'export', 'export_csv', 'import_csv' ) as $action ) {
			add_action( 'admin_post_koz404_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
		add_filter( 'plugin_action_links_' . plugin_basename( KOZ404_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu(): void {
		add_submenu_page(
			KOZ404_Suite_Registry::suite_page(),
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
				'sanitize_callback' => array( KOZ404_Guard::class, 'sanitize_settings' ),
				'default'           => KOZ404_Guard::defaults(),
			)
		);
	}

	/** Turning "Show paths" off removes the stored paths from the log. */
	public static function settings_changed( $old, $new ): void {
		if ( is_array( $old ) && is_array( $new ) && ! empty( $old['log_paths'] ) && empty( $new['log_paths'] ) ) {
			KOZ404_Log::forget_paths();
		}
	}

	public static function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'koz-404-guard' ) . '</a>' );
		return $links;
	}

	private static function url( string $tab = 'log', array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::PAGE, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	private static function action_url( string $action, array $args, string $nonce ): string {
		return wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'koz404_' . $action ), $args ), admin_url( 'admin-post.php' ) ), $nonce );
	}

	private static function get_string( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen parameters.
		return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	private static function post_string( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Every handler checks its nonce before calling this.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/**
	 * Path or URL field. sanitize_text_field() would strip percent-encoded octets, so only
	 * control characters and invalid UTF-8 are removed; the value is then normalised by
	 * KOZ404_Guard::normalise_path() or normalise_same_site_url().
	 */
	private static function post_path( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked by the caller; cleaned below.
		$value = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : '';
		$value = preg_replace( '/[\x00-\x1F\x7F]/', '', $value ) ?? '';
		return trim( wp_check_invalid_utf8( $value, true ) );
	}

	private static function authorise(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'koz-404-guard' ), '', array( 'response' => 403 ) );
		}
	}

	private static function redirect_back( string $notice, string $tab, array $args = array() ): void {
		wp_safe_redirect( self::url( $tab, array_merge( $args, array( 'koz404_notice' => $notice ) ) ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Page
	 * ---------------------------------------------------------------- */

	public static function page(): void {
		self::authorise();
		$tab = self::get_string( 'tab' );
		$tab = in_array( $tab, self::TABS, true ) ? $tab : 'log';
		$labels = array(
			'log'       => __( '404 log', 'koz-404-guard' ),
			'redirects' => __( 'Redirects', 'koz-404-guard' ),
			'gone'      => __( '410 rules', 'koz-404-guard' ),
			'settings'  => __( 'Settings', 'koz-404-guard' ),
			'tools'     => __( 'Tools', 'koz-404-guard' ),
		);
		$suggested = count( KOZ404_Permalink_Watch::suggestions() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'KOZ 404 Guard & URL Intelligence', 'koz-404-guard' ); ?> <small class="koz404-version"><?php echo esc_html( KOZ404_VERSION ); ?></small></h1>
			<?php self::render_notice(); ?>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $labels as $key => $label ) : ?>
					<a class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::url( $key ) ); ?>"><?php echo esc_html( $label ); ?><?php if ( 'redirects' === $key && $suggested > 0 ) : ?> <span class="awaiting-mod"><?php echo esc_html( number_format_i18n( $suggested ) ); ?></span><?php endif; ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			switch ( $tab ) {
				case 'redirects':
					self::tab_redirects();
					break;
				case 'gone':
					self::tab_gone();
					break;
				case 'settings':
					self::tab_settings();
					break;
				case 'tools':
					self::tab_tools();
					break;
				default:
					self::tab_log();
			}
			?>
		</div>
		<?php
	}

	private static function display_path( array $row ): string {
		return '' !== (string) $row['path'] ? (string) $row['path'] : '/[path:' . (string) $row['path_fingerprint'] . ']';
	}

	private static function kind_label( string $kind ): string {
		$labels = array(
			'human'   => __( 'Visitor', 'koz-404-guard' ),
			'bot'     => __( 'Bot', 'koz-404-guard' ),
			'unknown' => __( 'Unknown', 'koz-404-guard' ),
		);
		return $labels[ $kind ] ?? $kind;
	}

	private static function source_label( string $source ): string {
		$labels = array(
			'wordpress_404'    => __( 'Page', 'koz-404-guard' ),
			'static_404'       => __( 'File', 'koz-404-guard' ),
			'attachment_404'   => __( 'Attachment link', 'koz-404-guard' ),
			'system_route_404' => __( 'System route', 'koz-404-guard' ),
			'redirect'         => __( 'Redirect rule', 'koz-404-guard' ),
			'gone_rule'        => __( '410 rule', 'koz-404-guard' ),
			'legacy'           => __( 'Before 2.2.0', 'koz-404-guard' ),
		);
		return $labels[ $source ] ?? $source;
	}

	/**
	 * Published content whose slug matches the last segment of a missing path,
	 * keeping a KOZ Static Translate language prefix.
	 */
	private static function suggest_target( string $path ): string {
		global $wpdb;
		$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ), 'strlen' ) );
		if ( empty( $segments ) ) {
			return '';
		}
		$prefix = '';
		if ( count( $segments ) > 1 && in_array( strtolower( $segments[0] ), KOZ404_Request::translator_languages(), true ) ) {
			$prefix = '/' . strtolower( array_shift( $segments ) );
		}
		$slug = sanitize_title( preg_replace( '/\.[a-z0-9]{2,5}$/i', '', (string) end( $segments ) ) ?? '' );
		if ( strlen( $slug ) < 3 ) {
			return '';
		}
		$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
		if ( empty( $types ) ) {
			return '';
		}
		$in = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Read-only lookup; $in holds only %s placeholders.
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status = 'publish' AND post_type IN ({$in}) LIMIT 1", array_merge( array( $slug ), $types ) ) );
		if ( 0 === $id && strlen( $slug ) >= 4 ) {
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name LIKE %s AND post_status = 'publish' AND post_type IN ({$in}) ORDER BY CHAR_LENGTH(post_name) ASC LIMIT 1", array_merge( array( $wpdb->esc_like( $slug ) . '%' ), $types ) ) );
		}
		// phpcs:enable
		if ( 0 === $id ) {
			return '';
		}
		$url = get_permalink( $id );
		if ( ! is_string( $url ) ) {
			return '';
		}
		$target = KOZ404_Guard::normalise_path( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$target = '' !== $prefix ? KOZ404_Guard::normalise_path( $prefix . $target ) : $target;
		return $target === $path ? '' : $target;
	}

	/* ------------------------------------------------------------------
	 * Log tab
	 * ---------------------------------------------------------------- */

	private static function tab_log(): void {
		$settings = KOZ404_Guard::settings();
		$view     = self::get_string( 'view' );
		$view     = in_array( $view, array( '404', 'internal', '410', 'redirect', 'all' ), true ) ? $view : '404';
		$kind     = self::get_string( 'kind' );
		$search   = self::get_string( 's' );
		$orderby  = 'last' === self::get_string( 'orderby' ) ? 'last' : 'hits';
		$ignored  = '1' === self::get_string( 'ignored' );
		$paged    = max( 1, absint( self::get_string( 'paged' ) ) );
		$result   = KOZ404_Log::query(
			array(
				'view'         => $view,
				'kind'         => $kind,
				'search'       => $search,
				'orderby'      => $orderby,
				'page'         => $paged,
				'per_page'     => 50,
				'show_ignored' => $ignored,
			)
		);
		$counts = KOZ404_Log::summary_counts();
		$views  = array(
			'404'      => __( 'Open 404', 'koz-404-guard' ),
			'internal' => __( 'From this site', 'koz-404-guard' ),
			'410'      => __( '410', 'koz-404-guard' ),
			'redirect' => __( 'Redirected', 'koz-404-guard' ),
			'all'      => __( 'All', 'koz-404-guard' ),
		);
		?>
		<?php if ( empty( $settings['log_enabled'] ) ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'The 404 log is off. Turn it on in Settings to record missing addresses.', 'koz-404-guard' ); ?></p></div>
		<?php endif; ?>
		<p class="description">
			<?php
			echo esc_html(
				empty( $settings['log_paths'] )
					? __( 'Paths are not stored: only salted fingerprints. Turn on "Show paths" in Settings to see and fix the addresses.', 'koz-404-guard' )
					: __( 'Stored: path, query parameter names (never values), visitor type, the page of this site that linked here and counters. No IP address, User-Agent or external referrer is stored. The JSON export contains fingerprints only.', 'koz-404-guard' )
			);
			?>
		</p>
		<ul class="subsubsub">
			<?php
			$links = array();
			foreach ( $views as $key => $label ) {
				$key   = (string) $key; // PHP turns the keys '404' and '410' into integers.
				$count = '404' === $key ? $counts['open_404'] : ( 'internal' === $key ? $counts['internal_404'] : null );
				$links[] = '<li><a href="' . esc_url( self::url( 'log', array( 'view' => $key ) ) ) . '"' . ( $view === $key ? ' class="current"' : '' ) . '>' . esc_html( $label ) . ( null !== $count ? ' <span class="count">(' . esc_html( number_format_i18n( $count ) ) . ')</span>' : '' ) . '</a>';
			}
			echo wp_kses_post( implode( ' |</li>', $links ) . '</li>' );
			?>
		</ul>
		<form method="get" style="clear:both;display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:8px 0">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
			<input type="hidden" name="tab" value="log">
			<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search path', 'koz-404-guard' ); ?>">
			<select name="kind">
				<option value=""><?php esc_html_e( 'All visitors', 'koz-404-guard' ); ?></option>
				<option value="human" <?php selected( 'human', $kind ); ?>><?php esc_html_e( 'Visitors', 'koz-404-guard' ); ?></option>
				<option value="bot" <?php selected( 'bot', $kind ); ?>><?php esc_html_e( 'Bots', 'koz-404-guard' ); ?></option>
			</select>
			<select name="orderby">
				<option value="hits" <?php selected( 'hits', $orderby ); ?>><?php esc_html_e( 'Most hits', 'koz-404-guard' ); ?></option>
				<option value="last" <?php selected( 'last', $orderby ); ?>><?php esc_html_e( 'Most recent', 'koz-404-guard' ); ?></option>
			</select>
			<label><input type="checkbox" name="ignored" value="1" <?php checked( $ignored ); ?>> <?php esc_html_e( 'Show ignored', 'koz-404-guard' ); ?></label>
			<button class="button"><?php esc_html_e( 'Filter', 'koz-404-guard' ); ?></button>
		</form>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Path', 'koz-404-guard' ); ?></th>
				<th><?php esc_html_e( 'Hits', 'koz-404-guard' ); ?></th>
				<th><?php esc_html_e( 'Type', 'koz-404-guard' ); ?></th>
				<th><?php esc_html_e( 'Linked from', 'koz-404-guard' ); ?></th>
				<th><?php esc_html_e( 'Last seen', 'koz-404-guard' ); ?></th>
				<th><?php esc_html_e( 'Recommendation', 'koz-404-guard' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'koz-404-guard' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( empty( $result['rows'] ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No entries.', 'koz-404-guard' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $result['rows'] as $row ) :
				$id      = (int) $row['id'];
				$path    = (string) $row['path'];
				$status  = (int) $row['status'];
				$target  = ( 404 === $status && '' !== $path ) ? self::suggest_target( $path ) : '';
				$from    = 'same_site' === $row['referrer_scope']
					? ( '' !== (string) $row['referrer_path'] ? (string) $row['referrer_path'] : __( 'This site', 'koz-404-guard' ) )
					: ( 'external' === $row['referrer_scope'] ? __( 'Another site', 'koz-404-guard' ) : '—' );
				?>
				<tr<?php echo ! empty( $row['ignored'] ) ? ' style="opacity:.6"' : ''; ?>>
					<td>
						<?php if ( 404 !== $status ) : ?><strong><?php echo esc_html( (string) $status ); ?></strong> <?php endif; ?>
						<code style="word-break:break-all"><?php echo esc_html( self::display_path( $row ) ); ?></code>
						<?php if ( '' !== (string) $row['query_keys'] ) : ?><br><span class="description">?<?php echo esc_html( str_replace( ',', ', ', (string) $row['query_keys'] ) ); ?></span><?php endif; ?>
					</td>
					<td><?php echo esc_html( number_format_i18n( (int) $row['hits'] ) ); ?></td>
					<td><?php echo esc_html( self::kind_label( (string) $row['kind'] ) . ', ' . self::source_label( (string) $row['source'] ) ); ?></td>
					<td>
						<?php if ( 'same_site' === $row['referrer_scope'] && '' !== (string) $row['referrer_path'] ) : ?>
							<a href="<?php echo esc_url( KOZ404_Guard::path_url( (string) $row['referrer_path'] ) ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( $from ); ?></code></a>
						<?php else : ?>
							<?php echo esc_html( $from ); ?>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( (string) $row['last_seen'] ); ?></td>
					<td><?php echo esc_html( KOZ404_Guard::suggestion( $row ) ); ?></td>
					<td style="white-space:nowrap">
						<?php if ( 404 === $status && '' !== $path && ! KOZ404_Guard::is_protected_path( $path ) ) : ?>
							<a class="button button-small button-primary" href="<?php echo esc_url( self::url( 'redirects', array( 'source' => $path, 'target' => $target ) ) . '#koz404-redirect-form' ); ?>"><?php esc_html_e( 'Redirect', 'koz-404-guard' ); ?></a>
							<a class="button button-small" href="<?php echo esc_url( self::action_url( 'log_action', array( 'op' => 'gone', 'id' => $id ), 'koz404_log_gone_' . $id ) ); ?>"><?php esc_html_e( '410', 'koz-404-guard' ); ?></a>
						<?php endif; ?>
						<?php if ( empty( $row['ignored'] ) ) : ?>
							<a class="button button-small" href="<?php echo esc_url( self::action_url( 'log_action', array( 'op' => 'ignore', 'id' => $id ), 'koz404_log_ignore_' . $id ) ); ?>"><?php esc_html_e( 'Ignore', 'koz-404-guard' ); ?></a>
						<?php else : ?>
							<a class="button button-small" href="<?php echo esc_url( self::action_url( 'log_action', array( 'op' => 'unignore', 'id' => $id ), 'koz404_log_unignore_' . $id ) ); ?>"><?php esc_html_e( 'Restore', 'koz-404-guard' ); ?></a>
						<?php endif; ?>
						<a class="button button-small button-link-delete" href="<?php echo esc_url( self::action_url( 'log_action', array( 'op' => 'delete', 'id' => $id ), 'koz404_log_delete_' . $id ) ); ?>"><?php esc_html_e( 'Delete', 'koz-404-guard' ); ?></a>
						<?php if ( '' !== $target ) : ?><br><span class="description"><?php echo esc_html( sprintf( /* translators: %s: suggested target path. */ __( 'Suggested: %s', 'koz-404-guard' ), $target ) ); ?></span><?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$pages = (int) ceil( $result['total'] / 50 );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post(
				(string) paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					)
				)
			);
			echo '</div></div>';
		}
		?>
		<p style="margin-top:16px">
			<a class="button" href="<?php echo esc_url( self::action_url( 'export', array(), 'koz404_export' ) ); ?>"><?php esc_html_e( 'Export privacy-safe JSON report', 'koz-404-guard' ); ?></a>
			<a class="button button-link-delete" href="<?php echo esc_url( self::action_url( 'clear_log', array(), 'koz404_clear_log' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Clear the local log?', 'koz-404-guard' ) ); ?>')"><?php esc_html_e( 'Clear log', 'koz-404-guard' ); ?></a>
		</p>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Redirects tab
	 * ---------------------------------------------------------------- */

	private static function tab_redirects(): void {
		$rules   = KOZ404_Rules::redirects();
		$hits    = KOZ404_Log::rule_hits();
		$edit_id = self::get_string( 'edit' );
		$editing = '' !== $edit_id ? KOZ404_Rules::redirect( $edit_id ) : null;
		$source  = $editing ? $editing['source'] : self::get_string( 'source' );
		$target  = $editing ? $editing['target'] : self::get_string( 'target' );
		$status  = $editing ? (int) $editing['status'] : 301;
		$type    = $editing ? $editing['type'] : 'exact';
		$keep_q  = $editing ? ! empty( $editing['preserve_query'] ) : false;
		$suggestions = KOZ404_Permalink_Watch::suggestions();
		?>
		<?php if ( ! empty( $suggestions ) ) : ?>
			<h2><?php esc_html_e( 'Suggested after address changes', 'koz-404-guard' ); ?></h2>
			<p class="description"><?php esc_html_e( 'These published entries changed their address. Create a 301 redirect so old links keep working.', 'koz-404-guard' ); ?></p>
			<table class="widefat striped" style="max-width:1100px"><thead><tr><th><?php esc_html_e( 'Old address', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'New address', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Changed', 'koz-404-guard' ); ?></th><th></th></tr></thead><tbody>
			<?php foreach ( $suggestions as $item ) : ?>
				<tr>
					<td><code><?php echo esc_html( $item['source'] ); ?></code></td>
					<td><code><?php echo esc_html( $item['target'] ); ?></code></td>
					<td><?php echo esc_html( $item['created'] ); ?></td>
					<td style="white-space:nowrap">
						<a class="button button-small button-primary" href="<?php echo esc_url( self::action_url( 'suggestion_action', array( 'op' => 'accept', 'id' => $item['id'] ), 'koz404_suggestion_accept_' . $item['id'] ) ); ?>"><?php esc_html_e( 'Create redirect', 'koz-404-guard' ); ?></a>
						<a class="button button-small" href="<?php echo esc_url( self::action_url( 'suggestion_action', array( 'op' => 'dismiss', 'id' => $item['id'] ), 'koz404_suggestion_dismiss_' . $item['id'] ) ); ?>"><?php esc_html_e( 'Dismiss', 'koz-404-guard' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>

		<h2 id="koz404-redirect-form"><?php echo esc_html( $editing ? __( 'Edit redirect', 'koz-404-guard' ) : __( 'Add redirect', 'koz-404-guard' ) ); ?></h2>
		<p class="description"><?php esc_html_e( 'Same-site targets only. Protected paths, duplicates and redirect cycles are rejected. A prefix rule moves a whole section: /old/a/b goes to the target followed by /a/b.', 'koz-404-guard' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
			<input type="hidden" name="action" value="koz404_save_redirect"><?php wp_nonce_field( 'koz404_save_redirect' ); ?>
			<?php if ( $editing ) : ?><input type="hidden" name="id" value="<?php echo esc_attr( $editing['id'] ); ?>"><?php endif; ?>
			<label><?php esc_html_e( 'Source path', 'koz-404-guard' ); ?><br><input class="regular-text" name="source" value="<?php echo esc_attr( $source ); ?>" placeholder="/old-page" required></label>
			<label><?php esc_html_e( 'Target path or same-site URL', 'koz-404-guard' ); ?><br><input class="regular-text" name="target" value="<?php echo esc_attr( $target ); ?>" placeholder="/new-page" required></label>
			<label><?php esc_html_e( 'Match', 'koz-404-guard' ); ?><br><select name="type"><option value="exact" <?php selected( 'exact', $type ); ?>><?php esc_html_e( 'Exact path', 'koz-404-guard' ); ?></option><option value="prefix" <?php selected( 'prefix', $type ); ?>><?php esc_html_e( 'Path prefix', 'koz-404-guard' ); ?></option></select></label>
			<label><?php esc_html_e( 'Status', 'koz-404-guard' ); ?><br><select name="status"><?php foreach ( array( 301, 302, 307, 308 ) as $code ) : ?><option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( $code, $status ); ?>><?php echo esc_html( (string) $code ); ?></option><?php endforeach; ?></select></label>
			<label style="padding-bottom:6px"><input type="checkbox" name="preserve_query" value="1" <?php checked( $keep_q ); ?>> <?php esc_html_e( 'Keep query string', 'koz-404-guard' ); ?></label>
			<button class="button button-primary"><?php echo esc_html( $editing ? __( 'Save redirect', 'koz-404-guard' ) : __( 'Add redirect', 'koz-404-guard' ) ); ?></button>
			<?php if ( $editing ) : ?><a class="button" href="<?php echo esc_url( self::url( 'redirects' ) ); ?>"><?php esc_html_e( 'Cancel', 'koz-404-guard' ); ?></a><?php endif; ?>
		</form>

		<h2><?php esc_html_e( 'Redirect rules', 'koz-404-guard' ); ?></h2>
		<table class="widefat striped" style="margin-top:12px">
			<thead><tr>
				<th><?php esc_html_e( 'Source', 'koz-404-guard' ); ?></th>
				<th><?php esc_html_e( 'Target', 'koz-404-guard' ); ?></th>
				<th><?php esc_html_e( 'Status', 'koz-404-guard' ); ?></th>
				<th><?php esc_html_e( 'Hits', 'koz-404-guard' ); ?></th>
				<th><?php esc_html_e( 'Last hit', 'koz-404-guard' ); ?></th>
				<th><?php esc_html_e( 'Created by', 'koz-404-guard' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php if ( empty( $rules ) ) : ?><tr><td colspan="7"><?php esc_html_e( 'No redirect rules.', 'koz-404-guard' ); ?></td></tr><?php endif; ?>
			<?php
			$origins = array(
				'manual'    => __( 'Manual', 'koz-404-guard' ),
				'log'       => __( '404 log', 'koz-404-guard' ),
				'permalink' => __( 'Address change', 'koz-404-guard' ),
				'import'    => __( 'CSV import', 'koz-404-guard' ),
			);
			foreach ( $rules as $rule ) :
				$rule_hits = $hits[ $rule['id'] ] ?? array( 'hits' => 0, 'last' => '' );
				?>
				<tr<?php echo empty( $rule['enabled'] ) ? ' style="opacity:.6"' : ''; ?>>
					<td><code><?php echo esc_html( $rule['source'] . ( 'prefix' === $rule['type'] ? '/*' : '' ) ); ?></code><?php if ( empty( $rule['enabled'] ) ) : ?> <em><?php esc_html_e( '(off)', 'koz-404-guard' ); ?></em><?php endif; ?></td>
					<td><code style="word-break:break-all"><?php echo esc_html( $rule['target'] ); ?></code><?php if ( ! empty( $rule['preserve_query'] ) ) : ?> <span class="description">+?</span><?php endif; ?></td>
					<td><?php echo esc_html( (string) $rule['status'] ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $rule_hits['hits'] ) ); ?></td>
					<td><?php echo esc_html( '' !== $rule_hits['last'] ? $rule_hits['last'] : '—' ); ?></td>
					<td><?php echo esc_html( $origins[ $rule['origin'] ] ?? $rule['origin'] ); ?></td>
					<td style="white-space:nowrap">
						<a href="<?php echo esc_url( self::url( 'redirects', array( 'edit' => $rule['id'] ) ) . '#koz404-redirect-form' ); ?>"><?php esc_html_e( 'Edit', 'koz-404-guard' ); ?></a> |
						<a href="<?php echo esc_url( self::action_url( 'redirect_action', array( 'op' => 'toggle', 'id' => $rule['id'] ), 'koz404_redirect_toggle_' . $rule['id'] ) ); ?>"><?php echo esc_html( empty( $rule['enabled'] ) ? __( 'Enable', 'koz-404-guard' ) : __( 'Disable', 'koz-404-guard' ) ); ?></a> |
						<a class="submitdelete" href="<?php echo esc_url( self::action_url( 'redirect_action', array( 'op' => 'delete', 'id' => $rule['id'] ), 'koz404_redirect_delete_' . $rule['id'] ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this rule?', 'koz-404-guard' ) ); ?>')"><?php esc_html_e( 'Delete', 'koz-404-guard' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Import and export', 'koz-404-guard' ); ?></h2>
		<p class="description"><?php esc_html_e( 'CSV columns: source, target, status, type (exact or prefix), preserve_query (0/1), enabled (0/1). The first line may be a header. Invalid, duplicate and looping rows are skipped.', 'koz-404-guard' ); ?></p>
		<p><a class="button" href="<?php echo esc_url( self::action_url( 'export_csv', array(), 'koz404_export_csv' ) ); ?>"><?php esc_html_e( 'Export redirects (CSV)', 'koz-404-guard' ); ?></a></p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
			<input type="hidden" name="action" value="koz404_import_csv"><?php wp_nonce_field( 'koz404_import_csv' ); ?>
			<input type="file" name="koz404_csv" accept=".csv,text/csv" required>
			<button class="button"><?php esc_html_e( 'Import CSV', 'koz-404-guard' ); ?></button>
		</form>
		<?php
	}

	/* ------------------------------------------------------------------
	 * 410 tab
	 * ---------------------------------------------------------------- */

	private static function tab_gone(): void {
		$rules   = KOZ404_Rules::gone_rules();
		$hits    = KOZ404_Log::rule_hits();
		$edit_id = self::get_string( 'edit' );
		$editing = null;
		foreach ( $rules as $rule ) {
			if ( '' !== $edit_id && $rule['id'] === $edit_id ) {
				$editing = $rule;
			}
		}
		$types = array(
			'exact_path'  => __( 'Exact path', 'koz-404-guard' ),
			'path_prefix' => __( 'Path prefix', 'koz-404-guard' ),
			'query_key'   => __( 'Query key', 'koz-404-guard' ),
			'query_pair'  => __( 'Query key=value', 'koz-404-guard' ),
		);
		?>
		<h2><?php echo esc_html( $editing ? __( 'Edit 410 rule', 'koz-404-guard' ) : __( 'Add 410 rule', 'koz-404-guard' ) ); ?></h2>
		<p class="description"><?php esc_html_e( '410 tells search engines the content was removed on purpose. Rules are exact or boundary-aware. Query values are never written to the log.', 'koz-404-guard' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
			<input type="hidden" name="action" value="koz404_save_gone"><?php wp_nonce_field( 'koz404_save_gone' ); ?>
			<?php if ( $editing ) : ?><input type="hidden" name="id" value="<?php echo esc_attr( $editing['id'] ); ?>"><?php endif; ?>
			<label><?php esc_html_e( 'Match type', 'koz-404-guard' ); ?><br><select name="type"><?php foreach ( $types as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $editing['type'] ?? 'exact_path' ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><?php esc_html_e( 'Value', 'koz-404-guard' ); ?><br><input class="regular-text" name="value" value="<?php echo esc_attr( $editing['value'] ?? '' ); ?>" placeholder="/removed or key=value" required></label>
			<button class="button button-primary"><?php echo esc_html( $editing ? __( 'Save 410 rule', 'koz-404-guard' ) : __( 'Add 410 rule', 'koz-404-guard' ) ); ?></button>
			<?php if ( $editing ) : ?><a class="button" href="<?php echo esc_url( self::url( 'gone' ) ); ?>"><?php esc_html_e( 'Cancel', 'koz-404-guard' ); ?></a><?php endif; ?>
		</form>
		<table class="widefat striped" style="margin-top:12px;max-width:1100px">
			<thead><tr><th><?php esc_html_e( 'Type', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Value', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Hits', 'koz-404-guard' ); ?></th><th></th></tr></thead>
			<tbody>
			<?php if ( empty( $rules ) ) : ?><tr><td colspan="4"><?php esc_html_e( 'No 410 rules.', 'koz-404-guard' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $rules as $rule ) : ?>
				<tr<?php echo empty( $rule['enabled'] ) ? ' style="opacity:.6"' : ''; ?>>
					<td><?php echo esc_html( $types[ $rule['type'] ] ?? $rule['type'] ); ?></td>
					<td><code><?php echo esc_html( $rule['value'] ); ?></code><?php if ( empty( $rule['enabled'] ) ) : ?> <em><?php esc_html_e( '(off)', 'koz-404-guard' ); ?></em><?php endif; ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) ( $hits[ $rule['id'] ]['hits'] ?? 0 ) ) ); ?></td>
					<td style="white-space:nowrap">
						<a href="<?php echo esc_url( self::url( 'gone', array( 'edit' => $rule['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'koz-404-guard' ); ?></a> |
						<a href="<?php echo esc_url( self::action_url( 'gone_action', array( 'op' => 'toggle', 'id' => $rule['id'] ), 'koz404_gone_toggle_' . $rule['id'] ) ); ?>"><?php echo esc_html( empty( $rule['enabled'] ) ? __( 'Enable', 'koz-404-guard' ) : __( 'Disable', 'koz-404-guard' ) ); ?></a> |
						<a class="submitdelete" href="<?php echo esc_url( self::action_url( 'gone_action', array( 'op' => 'delete', 'id' => $rule['id'] ), 'koz404_gone_delete_' . $rule['id'] ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this rule?', 'koz-404-guard' ) ); ?>')"><?php esc_html_e( 'Delete', 'koz-404-guard' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Settings tab
	 * ---------------------------------------------------------------- */

	private static function tab_settings(): void {
		$settings = KOZ404_Guard::settings();
		$name     = KOZ404_Guard::SETTINGS_OPTION;
		$checkbox = static function ( string $key, string $label ) use ( $settings, $name ): void {
			printf(
				'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label><br>',
				esc_attr( $name ),
				esc_attr( $key ),
				checked( ! empty( $settings[ $key ] ), true, false ),
				esc_html( $label )
			);
		};
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'koz404_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Protection', 'koz-404-guard' ); ?></th><td><?php $checkbox( 'enabled', __( 'Enable request handling (redirects, 410 rules, 404 responses)', 'koz-404-guard' ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Lightweight responses', 'koz-404-guard' ); ?></th><td>
					<?php $checkbox( 'minimal_bot_404', __( 'Return a lightweight page without the theme for obvious bots', 'koz-404-guard' ) ); ?>
					<?php $checkbox( 'static_404', __( 'Answer missing files (images, CSS, JS, fonts, media) with a plain 404 for everyone', 'koz-404-guard' ) ); ?>
					<p class="description"><?php esc_html_e( 'Without this, WordPress renders the whole theme for every missing image.', 'koz-404-guard' ); ?></p>
				</td></tr>
				<tr><th><?php esc_html_e( '404 log', 'koz-404-guard' ); ?></th><td>
					<?php $checkbox( 'log_enabled', __( 'Record 404, 410 and redirect hits', 'koz-404-guard' ) ); ?>
					<?php $checkbox( 'log_humans', __( 'Include visitors', 'koz-404-guard' ) ); ?>
					<?php $checkbox( 'log_bots', __( 'Include obvious bots', 'koz-404-guard' ) ); ?>
					<?php $checkbox( 'log_paths', __( 'Show paths: store the requested path and the linking page of this site', 'koz-404-guard' ) ); ?>
					<?php $checkbox( 'log_query_keys', __( 'Store query parameter names (never values)', 'koz-404-guard' ) ); ?>
					<p class="description"><?php esc_html_e( 'No IP address, User-Agent, query value or external referrer is stored. Turning "Show paths" off removes stored paths and keeps only fingerprints.', 'koz-404-guard' ); ?></p>
				</td></tr>
				<tr><th><?php esc_html_e( 'Log retention', 'koz-404-guard' ); ?></th><td>
					<input type="number" min="7" max="730" name="<?php echo esc_attr( $name ); ?>[log_retention]" value="<?php echo esc_attr( (string) $settings['log_retention'] ); ?>"> <?php esc_html_e( 'days', 'koz-404-guard' ); ?>
					&nbsp; <input type="number" min="100" max="20000" name="<?php echo esc_attr( $name ); ?>[log_limit]" value="<?php echo esc_attr( (string) $settings['log_limit'] ); ?>"> <?php esc_html_e( 'rows at most', 'koz-404-guard' ); ?>
					<p class="description"><?php esc_html_e( 'Old rows are removed once a day.', 'koz-404-guard' ); ?></p>
				</td></tr>
				<tr><th><?php esc_html_e( 'Address changes', 'koz-404-guard' ); ?></th><td>
					<?php
					$watch = array(
						'suggest' => __( 'Suggest a 301 redirect for approval', 'koz-404-guard' ),
						'auto'    => __( 'Create the 301 redirect automatically', 'koz-404-guard' ),
						'off'     => __( 'Do nothing', 'koz-404-guard' ),
					);
					foreach ( $watch as $key => $label ) {
						printf( '<label><input type="radio" name="%1$s[permalink_watch]" value="%2$s" %3$s> %4$s</label><br>', esc_attr( $name ), esc_attr( $key ), checked( $settings['permalink_watch'], $key, false ), esc_html( $label ) );
					}
					?>
					<p class="description"><?php esc_html_e( 'When the slug, parent or date of a published entry changes, its old address would return 404.', 'koz-404-guard' ); ?></p>
				</td></tr>
				<tr><th><?php esc_html_e( 'Data layer', 'koz-404-guard' ); ?></th><td><?php $checkbox( 'emit_datalayer', __( 'Emit a local event with a salted path fingerprint', 'koz-404-guard' ) ); ?><p class="description"><?php esc_html_e( 'No raw URL, query value or referrer is added to the event.', 'koz-404-guard' ); ?></p></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Tools tab
	 * ---------------------------------------------------------------- */

	private static function tab_tools(): void {
		$scan = KOZ404_Environment_Scanner::scan();
		$deep = '1' === self::get_string( 'koz404_deep' );
		if ( $deep ) {
			check_admin_referer( 'koz404_deep_scan' );
		}
		$audit  = $deep ? KOZ404_Environment_Scanner::internal_link_audit( 200, true ) : null;
		$labels = array(
			'missing'  => __( 'Missing', 'koz-404-guard' ),
			'redirect' => __( 'Redirect rule', 'koz-404-guard' ),
			'gone'     => __( '410 rule', 'koz-404-guard' ),
		);
		?>
		<h2><?php esc_html_e( 'Internal link check', 'koz-404-guard' ); ?></h2>
		<p><?php esc_html_e( 'Checks links in the 200 most recently changed published entries. Pages, archives, terms, authors and KOZ Static Translate language routes count as existing. Nothing is changed.', 'koz-404-guard' ); ?></p>
		<?php if ( null === $audit ) : ?>
			<p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( self::url( 'tools', array( 'koz404_deep' => '1' ) ), 'koz404_deep_scan' ) ); ?>"><?php esc_html_e( 'Check internal links', 'koz-404-guard' ); ?></a></p>
		<?php else : ?>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: posts checked, 2: links checked, 3: links found. */
						__( 'Checked %1$d posts and %2$d links. Found: %3$d.', 'koz-404-guard' ),
						$audit['posts_checked'],
						$audit['links_checked'],
						$audit['candidate_count']
					)
				);
				?>
			</p>
			<table class="widefat striped" style="max-width:1100px"><thead><tr><th><?php esc_html_e( 'Content', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Link', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Result', 'koz-404-guard' ); ?></th><th><?php esc_html_e( '404 hits', 'koz-404-guard' ); ?></th><th></th></tr></thead><tbody>
			<?php if ( empty( $audit['items'] ) ) : ?><tr><td colspan="5"><?php esc_html_e( 'No missing internal links found in the checked content.', 'koz-404-guard' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $audit['items'] as $item ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( (string) get_edit_post_link( (int) $item['post_id'] ) ); ?>"><?php echo esc_html( '' !== $item['post_title'] ? $item['post_title'] : '#' . $item['post_id'] ); ?></a></td>
					<td><code style="word-break:break-all"><?php echo esc_html( $item['path'] ); ?></code></td>
					<td><?php echo esc_html( $labels[ $item['status'] ] ?? $item['status'] ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) $item['log_hits'] ) ); ?></td>
					<td><?php if ( 'missing' === $item['status'] ) : ?><a class="button button-small" href="<?php echo esc_url( self::url( 'redirects', array( 'source' => $item['path'], 'target' => self::suggest_target( $item['path'] ) ) ) . '#koz404-redirect-form' ); ?>"><?php esc_html_e( 'Redirect', 'koz-404-guard' ); ?></a><?php endif; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Detected environment', 'koz-404-guard' ); ?></h2>
		<p><?php esc_html_e( 'Reads plugin headers only and creates no rules.', 'koz-404-guard' ); ?></p>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;max-width:1100px">
			<?php self::detection_table( __( 'Page builders', 'koz-404-guard' ), $scan['page_builders'] ); ?>
			<?php self::detection_table( __( 'URL and redirect plugins', 'koz-404-guard' ), $scan['url_plugins'] ); ?>
		</div>
		<?php if ( ! empty( $scan['suggestions'] ) ) : ?><h3><?php esc_html_e( 'Review-only suggestions', 'koz-404-guard' ); ?></h3><ul><?php foreach ( $scan['suggestions'] as $suggestion ) : ?><li><code><?php echo esc_html( $suggestion['type'] . ': ' . $suggestion['value'] ); ?></code> — <?php echo esc_html( $suggestion['note'] ); ?></li><?php endforeach; ?></ul><?php endif; ?>

		<h2><?php esc_html_e( 'Privacy-safe report', 'koz-404-guard' ); ?></h2>
		<p><?php esc_html_e( 'The JSON report contains counters and salted fingerprints only: no paths, query names or values, IP addresses, User-Agents, referrers, titles or content.', 'koz-404-guard' ); ?></p>
		<p><a class="button" href="<?php echo esc_url( self::action_url( 'export', array(), 'koz404_export' ) ); ?>"><?php esc_html_e( 'Export privacy-safe JSON report', 'koz-404-guard' ); ?></a></p>
		<?php
	}

	private static function detection_table( string $title, array $items ): void {
		?><div><h3><?php echo esc_html( $title ); ?></h3><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Plugin', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Installed', 'koz-404-guard' ); ?></th><th><?php esc_html_e( 'Active', 'koz-404-guard' ); ?></th></tr></thead><tbody><?php foreach ( $items as $item ) : ?><tr><td><?php echo esc_html( $item['name'] ); ?></td><td><?php echo esc_html( $item['installed'] ? __( 'Yes', 'koz-404-guard' ) : __( 'No', 'koz-404-guard' ) ); ?></td><td><?php echo esc_html( $item['active'] ? __( 'Yes', 'koz-404-guard' ) : __( 'No', 'koz-404-guard' ) ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php
	}

	/* ------------------------------------------------------------------
	 * Handlers
	 * ---------------------------------------------------------------- */

	public static function handle_save_redirect(): void {
		self::authorise();
		check_admin_referer( 'koz404_save_redirect' );
		$id   = self::post_string( 'id' );
		$data = array(
			'source'         => self::post_path( 'source' ),
			'target'         => self::post_path( 'target' ),
			'status'         => self::post_string( 'status' ),
			'type'           => self::post_string( 'type' ),
			'preserve_query' => self::post_string( 'preserve_query' ),
			'origin'         => 'manual',
		);
		$error = KOZ404_Rules::save_redirect( $data, $id );
		if ( '' !== $error ) {
			self::redirect_back( $error, 'redirects', array( 'source' => $data['source'], 'target' => $data['target'] ) );
		}
		if ( 'exact' === $data['type'] || '' === $data['type'] ) {
			KOZ404_Log::resolve_path( KOZ404_Guard::normalise_path( $data['source'] ) );
		}
		self::redirect_back( '' !== $id ? 'redirect_saved' : 'redirect_added', 'redirects' );
	}

	public static function handle_redirect_action(): void {
		self::authorise();
		$op = self::get_string( 'op' );
		$id = self::get_string( 'id' );
		check_admin_referer( 'koz404_redirect_' . $op . '_' . $id );
		if ( 'delete' === $op ) {
			KOZ404_Rules::delete_redirect( $id );
			self::redirect_back( 'redirect_deleted', 'redirects' );
		}
		$error = KOZ404_Rules::toggle_redirect( $id );
		self::redirect_back( '' !== $error ? $error : 'redirect_saved', 'redirects' );
	}

	public static function handle_save_gone(): void {
		self::authorise();
		check_admin_referer( 'koz404_save_gone' );
		$id    = self::post_string( 'id' );
		$error = KOZ404_Rules::save_gone( self::post_string( 'type' ), self::post_path( 'value' ), $id );
		self::redirect_back( '' !== $error ? $error : ( '' !== $id ? 'gone_saved' : 'gone_added' ), 'gone' );
	}

	public static function handle_gone_action(): void {
		self::authorise();
		$op = self::get_string( 'op' );
		$id = self::get_string( 'id' );
		check_admin_referer( 'koz404_gone_' . $op . '_' . $id );
		if ( 'delete' === $op ) {
			KOZ404_Rules::delete_gone( $id );
			self::redirect_back( 'gone_deleted', 'gone' );
		}
		$error = KOZ404_Rules::toggle_gone( $id );
		self::redirect_back( '' !== $error ? $error : 'gone_saved', 'gone' );
	}

	public static function handle_log_action(): void {
		self::authorise();
		$op = self::get_string( 'op' );
		$id = absint( self::get_string( 'id' ) );
		check_admin_referer( 'koz404_log_' . $op . '_' . $id );
		$row = KOZ404_Log::row( $id );
		if ( null === $row ) {
			self::redirect_back( 'rule_not_found', 'log' );
		}
		if ( 'ignore' === $op || 'unignore' === $op ) {
			KOZ404_Log::set_ignored( $id, 'ignore' === $op );
			self::redirect_back( 'log_updated', 'log' );
		}
		if ( 'gone' === $op ) {
			$error = KOZ404_Rules::save_gone( 'exact_path', (string) $row['path'] );
			if ( '' === $error ) {
				KOZ404_Log::resolve_path( (string) $row['path'] );
			}
			self::redirect_back( '' !== $error ? $error : 'gone_added', 'log' );
		}
		KOZ404_Log::delete_row( $id );
		self::redirect_back( 'log_updated', 'log' );
	}

	public static function handle_suggestion_action(): void {
		self::authorise();
		$op = self::get_string( 'op' );
		$id = self::get_string( 'id' );
		check_admin_referer( 'koz404_suggestion_' . $op . '_' . $id );
		if ( 'accept' === $op ) {
			$error = KOZ404_Permalink_Watch::accept( $id );
			self::redirect_back( '' !== $error && 'duplicate_redirect' !== $error ? $error : 'redirect_added', 'redirects' );
		}
		KOZ404_Permalink_Watch::dismiss( $id );
		self::redirect_back( 'suggestion_dismissed', 'redirects' );
	}

	public static function handle_clear_log(): void {
		self::authorise();
		check_admin_referer( 'koz404_clear_log' );
		KOZ404_Log::clear();
		self::redirect_back( 'log_cleared', 'log' );
	}

	public static function handle_forget_paths(): void {
		self::authorise();
		check_admin_referer( 'koz404_forget_paths' );
		KOZ404_Log::forget_paths();
		self::redirect_back( 'log_updated', 'log' );
	}

	public static function handle_export_csv(): void {
		self::authorise();
		check_admin_referer( 'koz404_export_csv' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="koz-404-redirects-' . gmdate( 'Ymd-His' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo KOZ404_Rules::export_csv(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, not HTML.
		exit;
	}

	public static function handle_import_csv(): void {
		self::authorise();
		check_admin_referer( 'koz404_import_csv' );
		$tmp  = isset( $_FILES['koz404_csv']['tmp_name'] ) && is_string( $_FILES['koz404_csv']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['koz404_csv']['tmp_name'] ) ) : '';
		$size = isset( $_FILES['koz404_csv']['size'] ) ? absint( $_FILES['koz404_csv']['size'] ) : 0;
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) || $size <= 0 || $size > MB_IN_BYTES ) {
			self::redirect_back( 'import_failed', 'redirects' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$csv = '';
		if ( WP_Filesystem() ) {
			global $wp_filesystem;
			$csv = (string) $wp_filesystem->get_contents( $tmp );
		}
		if ( '' === $csv ) {
			self::redirect_back( 'import_failed', 'redirects' );
		}
		$result = KOZ404_Rules::import_csv( $csv );
		self::redirect_back( 'imported', 'redirects', array( 'added' => $result['added'], 'skipped' => $result['skipped'] ) );
	}

	public static function handle_export(): void {
		self::authorise();
		check_admin_referer( 'koz404_export' );
		$report = array(
			'generated_at'        => gmdate( 'c' ),
			'component'           => array(
				'name'             => 'KOZ 404 Guard & URL Intelligence',
				'version'          => KOZ404_VERSION,
				'log_schema'       => 5,
				'privacy_contract' => 4,
			),
			'settings'            => KOZ404_Guard::settings(),
			'redirect_rules'      => KOZ404_Rules::export_redirects(),
			'gone_rules'          => KOZ404_Rules::export_gone_rules(),
			'grouped_log'         => KOZ404_Log::export(),
			'environment'         => KOZ404_Environment_Scanner::privacy_summary(),
			'internal_link_audit' => KOZ404_Environment_Scanner::internal_link_audit( 100, false ),
			'privacy_contract'    => 4,
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

	private static function render_notice(): void {
		$code     = self::get_string( 'koz404_notice' );
		$code     = sanitize_key( $code );
		$messages = array(
			'redirect_added'       => array( 'success', __( 'Redirect rule added.', 'koz-404-guard' ) ),
			'redirect_saved'       => array( 'success', __( 'Redirect rule saved.', 'koz-404-guard' ) ),
			'redirect_deleted'     => array( 'success', __( 'Redirect rule deleted.', 'koz-404-guard' ) ),
			'gone_added'           => array( 'success', __( '410 rule added.', 'koz-404-guard' ) ),
			'gone_saved'           => array( 'success', __( '410 rule saved.', 'koz-404-guard' ) ),
			'gone_deleted'         => array( 'success', __( '410 rule deleted.', 'koz-404-guard' ) ),
			'log_cleared'          => array( 'success', __( 'Local log cleared.', 'koz-404-guard' ) ),
			'log_updated'          => array( 'success', __( 'Log updated.', 'koz-404-guard' ) ),
			'suggestion_dismissed' => array( 'success', __( 'Suggestion dismissed.', 'koz-404-guard' ) ),
			'invalid_redirect'     => array( 'error', __( 'The redirect was not saved. Use a non-system source path and a valid same-site HTTP/HTTPS target.', 'koz-404-guard' ) ),
			'duplicate_redirect'   => array( 'error', __( 'A redirect already exists for this source path.', 'koz-404-guard' ) ),
			'redirect_loop'        => array( 'error', __( 'The redirect was not saved because it creates a redirect cycle.', 'koz-404-guard' ) ),
			'too_many_rules'       => array( 'error', __( 'The rule limit was reached.', 'koz-404-guard' ) ),
			'rule_not_found'       => array( 'error', __( 'The item was not found.', 'koz-404-guard' ) ),
			'invalid_gone'         => array( 'error', __( 'The 410 rule was not saved. Check the type, value and protected paths.', 'koz-404-guard' ) ),
			'duplicate_gone'       => array( 'error', __( 'This 410 rule already exists.', 'koz-404-guard' ) ),
			'import_failed'        => array( 'error', __( 'The CSV file could not be read. Upload a CSV file up to 1 MB.', 'koz-404-guard' ) ),
		);
		if ( 'imported' === $code ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: imported rules, 2: skipped rows. */
						__( 'Imported %1$d redirect rules, skipped %2$d rows.', 'koz-404-guard' ),
						absint( self::get_string( 'added' ) ),
						absint( self::get_string( 'skipped' ) )
					)
				)
			);
			return;
		}
		if ( isset( $messages[ $code ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $code ][0] ), esc_html( $messages[ $code ][1] ) );
		}
	}
}
