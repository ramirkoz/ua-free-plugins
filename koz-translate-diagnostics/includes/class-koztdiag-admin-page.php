<?php
/**
 * Translation Diagnostics admin screen.
 *
 * @package KOZ_Translate_Diagnostics
 */

namespace ramirkz\koztdiag;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZTDIAG_Admin_Page {
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KOZTDIAG_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function enqueue_assets(): void {
		if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! is_object( $screen ) || ! str_contains( strtolower( (string) $screen->id ), KOZTDIAG_Translate_Diagnostics::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_script(
			'koztdiag-admin',
			KOZTDIAG_URL . 'assets/koz-translate-diagnostics-admin.js',
			array(),
			KOZTDIAG_VERSION,
			true
		);
		wp_localize_script(
			'koztdiag-admin',
			'koztdiagAdmin',
			array(
				'copied' => __( 'Copied.', 'koz-translate-diagnostics' ),
				'failed' => __( 'Copy failed. Select the text and copy it manually.', 'koz-translate-diagnostics' ),
			)
		);
	}

	public static function admin_menu(): void {
		$parent = KOZTDIAG_Suite_Registry::suite_page();
		add_submenu_page(
			$parent,
			__( 'Translation Diagnostics', 'koz-translate-diagnostics' ),
			__( 'Translation Diagnostics', 'koz-translate-diagnostics' ),
			'manage_options',
			KOZTDIAG_Translate_Diagnostics::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function action_links( array $links ): array {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . KOZTDIAG_Translate_Diagnostics::PAGE_SLUG ) ) . '">' .
			esc_html__( 'Diagnostics', 'koz-translate-diagnostics' ) .
			'</a>'
		);
		return $links;
	}

	public static function deep_scan_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page' => KOZTDIAG_Translate_Diagnostics::PAGE_SLUG,
					KOZTDIAG_Translate_Diagnostics::DEEP_SCAN_QUERY => '1',
				),
				admin_url( 'admin.php' )
			),
			KOZTDIAG_Translate_Diagnostics::DEEP_SCAN_NONCE
		);
	}

	private static function status_label( bool $condition, string $success, string $failure ): string {
		return $condition ? $success : $failure;
	}

	private static function local_time( string $iso ): string {
		$timestamp = '' !== $iso ? strtotime( $iso ) : false;
		return false === $timestamp ? '—' : wp_date( 'Y-m-d H:i', $timestamp );
	}

	private static function render_budget( array $budget ): void {
		if ( empty( $budget['available'] ) ) {
			return;
		}
		$monthly = (array) $budget['monthly'];
		$daily = (array) $budget['daily'];
		$worker = (array) $budget['worker'];
		$sitemap = (array) $budget['sitemap'];
		$settings = (array) $budget['settings'];
		$blocked = array(
			'' => __( 'No', 'koz-translate-diagnostics' ),
			'monthly' => __( 'Monthly limit', 'koz-translate-diagnostics' ),
			'daily' => __( 'Daily budget', 'koz-translate-diagnostics' ),
		);
		if ( empty( $worker['enabled'] ) ) {
			$worker_label = __( 'Off', 'koz-translate-diagnostics' );
		} elseif ( '' === (string) $worker['next_run_utc'] ) {
			$worker_label = __( 'On, not scheduled', 'koz-translate-diagnostics' );
		} else {
			/* translators: %s: local date and time of the next run. */
			$worker_label = sprintf( __( 'On, next run %s', 'koz-translate-diagnostics' ), self::local_time( (string) $worker['next_run_utc'] ) );
		}
		$residual_parts = array();
		foreach ( (array) $budget['residual_queue']['by_language'] as $language => $count ) {
			$residual_parts[] = strtoupper( (string) $language ) . ': ' . number_format_i18n( (int) $count );
		}
		?>
		<h2><?php esc_html_e( 'Azure budget and background worker', 'koz-translate-diagnostics' ); ?></h2>
		<table class="widefat striped" style="max-width:1180px"><tbody>
			<tr><th style="width:330px"><?php esc_html_e( 'This month', 'koz-translate-diagnostics' ); ?></th><td>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: used characters, 2: monthly limit, 3: percent used. */
						__( '%1$s of %2$s characters (%3$s%%)', 'koz-translate-diagnostics' ),
						number_format_i18n( (int) $monthly['used'] ),
						number_format_i18n( (int) $monthly['limit'] ),
						number_format_i18n( (float) $monthly['percent'], 1 )
					)
				);
				?>
				<?php if ( '' !== (string) $monthly['resets_at'] ) : ?>
					<span class="description">
						<?php
						/* translators: %s: local date and time when the monthly limit resets. */
						echo esc_html( sprintf( __( 'resets %s', 'koz-translate-diagnostics' ), self::local_time( (string) $monthly['resets_at'] ) ) );
						?>
					</span>
				<?php endif; ?>
			</td></tr>
			<tr><th><?php esc_html_e( 'Today (UTC day)', 'koz-translate-diagnostics' ); ?></th><td>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: characters used today, 2: daily budget. */
						__( '%1$s of %2$s characters', 'koz-translate-diagnostics' ),
						number_format_i18n( (int) $daily['used'] ),
						number_format_i18n( (int) $daily['limit'] )
					)
				);
				?>
				<?php if ( empty( $settings['daily_limit'] ) ) : ?><span class="description"><?php esc_html_e( '(automatic: monthly limit / 30)', 'koz-translate-diagnostics' ); ?></span><?php endif; ?>
			</td></tr>
			<tr><th><?php esc_html_e( 'Available now', 'koz-translate-diagnostics' ); ?></th><td><strong><?php echo esc_html( number_format_i18n( (int) $budget['available_now'] ) ); ?></strong></td></tr>
			<tr><th><?php esc_html_e( 'Stopped by budget', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( $blocked[ (string) $budget['blocked_by'] ] ?? (string) $budget['blocked_by'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Azure usage by source, last 30 days', 'koz-translate-diagnostics' ); ?></th><td>
				<?php if ( empty( $budget['usage_by_source_30_days'] ) ) : ?>
					<?php esc_html_e( 'No usage recorded by source yet.', 'koz-translate-diagnostics' ); ?>
				<?php else : ?>
					<?php foreach ( (array) $budget['usage_by_source_30_days'] as $kind => $characters ) : ?>
						<?php echo esc_html( KOZTDIAG_Budget::source_label( (string) $kind ) . ': ' . number_format_i18n( (int) $characters ) ); ?><br>
					<?php endforeach; ?>
				<?php endif; ?>
			</td></tr>
			<tr><th><?php esc_html_e( 'Background worker', 'koz-translate-diagnostics' ); ?></th><td>
				<?php echo esc_html( $worker_label ); ?>
				<?php if ( ! empty( $worker['next_run_overdue'] ) ) : ?> <strong><?php esc_html_e( '(overdue)', 'koz-translate-diagnostics' ); ?></strong><?php endif; ?>
				<?php if ( ! empty( $worker['cron_disabled'] ) ) : ?> <span class="description"><?php esc_html_e( 'WP-Cron is disabled; a server cron job is required.', 'koz-translate-diagnostics' ); ?></span><?php endif; ?>
			</td></tr>
			<tr><th><?php esc_html_e( 'Page text waiting for the worker', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $budget['residual_queue']['total'] ) ); ?><?php if ( ! empty( $residual_parts ) ) : ?> <span class="description">(<?php echo esc_html( implode( ', ', $residual_parts ) ); ?>)</span><?php endif; ?></td></tr>
			<tr><th><?php esc_html_e( 'Segments no longer sent (rejected twice)', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $budget['rejected_segments']['blocked'] ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Translation sitemap', 'koz-translate-diagnostics' ); ?></th><td>
				<?php if ( ! empty( $sitemap['rule_registered'] ) && ! empty( $sitemap['pretty_permalinks'] ) ) : ?>
					<a href="<?php echo esc_url( home_url( '/' . KOZTDIAG_Budget::SITEMAP_PATH ) ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( '/' . KOZTDIAG_Budget::SITEMAP_PATH ); ?></code></a>
				<?php elseif ( empty( $sitemap['pretty_permalinks'] ) ) : ?>
					<?php esc_html_e( 'Requires pretty permalinks.', 'koz-translate-diagnostics' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Not registered', 'koz-translate-diagnostics' ); ?>
				<?php endif; ?>
			</td></tr>
			<tr><th><?php esc_html_e( 'Do-not-translate terms', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $settings['protected_terms_count'] ) ); ?></td></tr>
		</tbody></table>
		<?php
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$deep = false;
		if ( isset( $_GET[ KOZTDIAG_Translate_Diagnostics::DEEP_SCAN_QUERY ] ) && '1' === sanitize_text_field( wp_unslash( $_GET[ KOZTDIAG_Translate_Diagnostics::DEEP_SCAN_QUERY ] ) ) ) {
			$deep = (bool) check_admin_referer( KOZTDIAG_Translate_Diagnostics::DEEP_SCAN_NONCE, '_wpnonce' );
		}
		$report = KOZTDIAG_Translate_Diagnostics::report( $deep );
		$translator = $report['translator_plugin'];
		$languages = $report['languages'];
		$short_report = KOZTDIAG_Export::summary_text( $report );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'KOZ Translate Diagnostics', 'koz-translate-diagnostics' ); ?></h1>
			<div class="notice notice-info inline"><p><strong><?php esc_html_e( 'Read-only:', 'koz-translate-diagnostics' ); ?></strong> <?php esc_html_e( 'this plugin does not call Azure, run the translation queue, change options, alter cron, clear caches or modify translation data.', 'koz-translate-diagnostics' ); ?></p></div>
			<p><?php esc_html_e( 'The plugin first uses the translator public diagnostics API. For older translator versions it automatically falls back to a read-only compatibility scan.', 'koz-translate-diagnostics' ); ?></p>
			<?php if ( $deep ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Deep scan completed. It compares source hashes and uses heavier read-only database queries.', 'koz-translate-diagnostics' ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'Quick scan is active. It uses metadata and limited recent-row reads only; full-table aggregates and hash joins are deferred to deep mode.', 'koz-translate-diagnostics' ); ?></p></div>
			<?php endif; ?>
			<p>
				<?php if ( ! $deep ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( self::deep_scan_url() ); ?>"><?php esc_html_e( 'Run deep hash scan', 'koz-translate-diagnostics' ); ?></a>
				<?php endif; ?>
				<a class="button<?php echo $deep ? ' button-primary' : ''; ?>" href="<?php echo esc_url( KOZTDIAG_Export::download_url() ); ?>"><?php esc_html_e( 'Download deep privacy-safe JSON report', 'koz-translate-diagnostics' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>"><?php esc_html_e( 'Site Health', 'koz-translate-diagnostics' ); ?></a>
			</p>
			<p class="description"><?php esc_html_e( 'The JSON report runs one deep scan on the server and is downloaded as a file. Nothing is stored on the site.', 'koz-translate-diagnostics' ); ?></p>

			<h2><?php esc_html_e( 'Translator status', 'koz-translate-diagnostics' ); ?></h2>
			<table class="widefat striped" style="max-width:1180px"><tbody>
				<tr><th style="width:330px"><?php esc_html_e( 'Installed', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ! empty( $translator['matches'] ), __( 'Yes', 'koz-translate-diagnostics' ), __( 'No', 'koz-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Active', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ! empty( array_filter( array_column( $translator['matches'], 'active' ) ) ), __( 'Yes', 'koz-translate-diagnostics' ), __( 'No', 'koz-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Detected version', 'koz-translate-diagnostics' ); ?></th><td><code><?php echo esc_html( (string) ( $translator['constant_version'] ?: ( $translator['matches'][0]['version'] ?? '' ) ) ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Public diagnostics API', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ! empty( $translator['public_api_available'] ), __( 'Available', 'koz-translate-diagnostics' ), __( 'Compatibility mode', 'koz-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Diagnostic mode', 'koz-translate-diagnostics' ); ?></th><td><code><?php echo esc_html( (string) $report['report']['diagnostic_mode'] ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Migration freeze', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ( ! empty( $report['translator_api_status']['migration_frozen'] ) || ( ! empty( $report['runtime']['manual_paused'] ) && KOZTDIAG_Translate_Diagnostics::is_migration_pause_reason( (string) ( $report['runtime']['pause_reason'] ?? '' ) ) ) ), __( 'Active', 'koz-translate-diagnostics' ), __( 'Inactive', 'koz-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Source language', 'koz-translate-diagnostics' ); ?></th><td><code><?php echo esc_html( (string) ( $report['settings']['source_language'] ?? '' ) ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Azure key configured', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ! empty( $report['settings']['azure_key_configured'] ), __( 'Yes', 'koz-translate-diagnostics' ), __( 'No', 'koz-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Manual pause', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( self::status_label( ! empty( $report['runtime']['manual_paused'] ), __( 'Yes', 'koz-translate-diagnostics' ), __( 'No', 'koz-translate-diagnostics' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Pause reason', 'koz-translate-diagnostics' ); ?></th><td><code><?php echo esc_html( (string) ( $report['runtime']['pause_reason'] ?? '' ) ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Last runtime error', 'koz-translate-diagnostics' ); ?></th><td><?php echo esc_html( (string) ( $report['runtime']['last_error'] ?? '' ) ); ?></td></tr>
			</tbody></table>

			<?php self::render_budget( (array) ( $report['budget'] ?? array() ) ); ?>

			<h2><?php esc_html_e( 'Data inventory', 'koz-translate-diagnostics' ); ?></h2>
			<table class="widefat striped" style="max-width:1180px"><thead><tr><th><?php esc_html_e( 'Table', 'koz-translate-diagnostics' ); ?></th><th><?php esc_html_e( 'Exists', 'koz-translate-diagnostics' ); ?></th><th><?php esc_html_e( 'Rows', 'koz-translate-diagnostics' ); ?></th></tr></thead><tbody>
			<?php foreach ( $report['tables'] as $key => $table ) : ?>
				<tr><td><code><?php echo esc_html( $key ); ?></code></td><td><?php echo esc_html( $table['exists'] ? __( 'Yes', 'koz-translate-diagnostics' ) : __( 'No', 'koz-translate-diagnostics' ) ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $table['rows'] ) ); ?><?php if ( ! empty( $table['rows_estimated'] ) ) : ?> <span class="description"><?php esc_html_e( '(estimate)', 'koz-translate-diagnostics' ); ?></span><?php endif; ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<p><strong><?php esc_html_e( 'Translation sources:', 'koz-translate-diagnostics' ); ?></strong> <?php echo esc_html( number_format_i18n( (int) $report['sources']['total'] ) ); ?></p>

			<h2><?php esc_html_e( 'Languages', 'koz-translate-diagnostics' ); ?></h2>
			<table class="widefat striped" style="max-width:1500px"><thead><tr>
				<th><?php esc_html_e( 'Language', 'koz-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Expected segments', 'koz-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Ready', 'koz-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Missing estimate', 'koz-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Stale', 'koz-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Memory', 'koz-translate-diagnostics' ); ?></th>
				<th><?php esc_html_e( 'Queue', 'koz-translate-diagnostics' ); ?></th>
			</tr></thead><tbody>
			<?php if ( empty( $languages ) ) : ?><tr><td colspan="7"><?php esc_html_e( 'No target languages were detected.', 'koz-translate-diagnostics' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $languages as $language => $data ) :
				$queue_total = 0;
				$queue_parts = array();
				foreach ( (array) ( $report['queue'][ $language ] ?? array() ) as $status => $queue_data ) {
					$queue_total += (int) $queue_data['total'];
					$queue_parts[] = $status . ': ' . (int) $queue_data['total'];
				}
				?>
				<tr>
					<td><strong><?php echo esc_html( $data['name'] ); ?></strong><br><code><?php echo esc_html( $language ); ?></code></td>
					<td><?php echo null === $data['expected_translatable_segments'] ? esc_html__( 'Not checked', 'koz-translate-diagnostics' ) : esc_html( number_format_i18n( (int) $data['expected_translatable_segments'] ) ); ?></td>
					<td><?php echo null === $data['exact_ready_rows'] ? esc_html__( 'Not checked', 'koz-translate-diagnostics' ) : esc_html( number_format_i18n( (int) $data['exact_ready_rows'] ) ); ?></td>
					<td><strong><?php echo null === $data['missing_estimate'] ? esc_html__( 'Not checked', 'koz-translate-diagnostics' ) : esc_html( number_format_i18n( (int) $data['missing_estimate'] ) ); ?></strong></td>
					<td><?php echo null === $data['stale_rows'] ? esc_html__( 'Not checked', 'koz-translate-diagnostics' ) : esc_html( number_format_i18n( (int) $data['stale_rows'] ) ); ?></td>
					<td><?php echo null === $data['memory_ready_rows'] ? esc_html__( 'Not checked', 'koz-translate-diagnostics' ) : esc_html( number_format_i18n( (int) $data['memory_ready_rows'] ) ); ?></td>
					<td><?php echo esc_html( $queue_total > 0 ? implode( ', ', $queue_parts ) : __( 'No queue rows', 'koz-translate-diagnostics' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<p class="description"><?php esc_html_e( 'Quick mode does not calculate completeness totals. Deep mode calculates the missing estimate from current translatable segments and exact ready translation rows; translation memory may reduce the work required.', 'koz-translate-diagnostics' ); ?></p>


			<h2><?php esc_html_e( 'Translation index readiness', 'koz-translate-diagnostics' ); ?></h2>
			<?php $readiness = (array) ( $report['translation_readiness'] ?? array() ); ?>
			<?php if ( empty( $readiness['checked'] ) ) : ?>
				<p><?php esc_html_e( 'Run the deep scan to calculate page-level translation coverage, critical metadata/H1 readiness and mixed-language blockers.', 'koz-translate-diagnostics' ); ?></p>
			<?php elseif ( empty( $readiness['available'] ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Translation readiness could not be calculated because the required translator tables or columns were not available.', 'koz-translate-diagnostics' ); ?></p></div>
			<?php else : ?>
				<table class="widefat striped" style="max-width:1180px"><thead><tr>
					<th><?php esc_html_e( 'Language', 'koz-translate-diagnostics' ); ?></th>
					<th><?php esc_html_e( 'Pages checked', 'koz-translate-diagnostics' ); ?></th>
					<th><?php esc_html_e( 'Ready for indexing', 'koz-translate-diagnostics' ); ?></th>
					<th><?php esc_html_e( 'Keep noindex', 'koz-translate-diagnostics' ); ?></th>
					<th><?php esc_html_e( 'Mixed-language critical fields', 'koz-translate-diagnostics' ); ?></th>
				</tr></thead><tbody>
				<?php foreach ( (array) ( $readiness['summary'] ?? array() ) as $language => $summary ) : ?>
					<tr>
						<td><strong><?php echo esc_html( (string) ( $summary['name'] ?? strtoupper( (string) $language ) ) ); ?></strong><br><code><?php echo esc_html( (string) $language ); ?></code></td>
						<td><?php echo esc_html( number_format_i18n( (int) ( $summary['pages_checked'] ?? 0 ) ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) ( $summary['pages_ready_for_indexing'] ?? 0 ) ) ); ?></td>
						<td><strong><?php echo esc_html( number_format_i18n( (int) ( $summary['pages_blocked'] ?? 0 ) ) ); ?></strong></td>
						<td><?php echo esc_html( number_format_i18n( (int) ( $summary['pages_with_mixed_language_critical_fields'] ?? 0 ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
				<p class="description"><?php esc_html_e( '“Ready for indexing” is a diagnostic recommendation only. It requires complete current translation rows plus translated title, meta description, Open Graph title/description and H1 when those fields exist in the source. The plugin does not change robots directives.', 'koz-translate-diagnostics' ); ?></p>

				<?php if ( ! empty( $readiness['blockers'] ) ) : ?>
					<h3><?php esc_html_e( 'Highest-priority blockers', 'koz-translate-diagnostics' ); ?></h3>
					<table class="widefat striped" style="max-width:1500px"><thead><tr>
						<th><?php esc_html_e( 'Page', 'koz-translate-diagnostics' ); ?></th>
						<th><?php esc_html_e( 'Language', 'koz-translate-diagnostics' ); ?></th>
						<th><?php esc_html_e( 'Coverage', 'koz-translate-diagnostics' ); ?></th>
						<th><?php esc_html_e( 'Missing critical fields', 'koz-translate-diagnostics' ); ?></th>
						<th><?php esc_html_e( 'Mixed-language fields', 'koz-translate-diagnostics' ); ?></th>
						<th><?php esc_html_e( 'Reason', 'koz-translate-diagnostics' ); ?></th>
						<th><?php esc_html_e( 'Recommendation', 'koz-translate-diagnostics' ); ?></th>
					</tr></thead><tbody>
					<?php foreach ( (array) $readiness['blockers'] as $blocker ) :
						$post_id = (int) ( $blocker['post_id'] ?? 0 );
						$language = sanitize_key( (string) ( $blocker['language'] ?? '' ) );
						$route = KOZTDIAG_Translate_Diagnostics::translated_route_url( $post_id, $language );
						$title = $post_id > 0 ? get_the_title( $post_id ) : '';
						$reason_labels = array_map( array( KOZTDIAG_Translate_Diagnostics::class, 'readiness_blocker_label' ), (array) ( $blocker['blockers'] ?? array() ) );
						?>
						<tr>
							<td><?php if ( '' !== $route ) : ?><a href="<?php echo esc_url( $route ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( '' !== $title ? $title : '#' . $post_id ); ?></a><?php else : ?><?php echo esc_html( '#' . $post_id ); ?><?php endif; ?></td>
							<td><code><?php echo esc_html( strtoupper( $language ) ); ?></code></td>
							<td><?php echo esc_html( number_format_i18n( (float) ( $blocker['coverage_percent'] ?? 0 ), 1 ) . '%' ); ?></td>
							<td><?php echo esc_html( empty( $blocker['missing_critical_fields'] ) ? '—' : implode( ', ', (array) $blocker['missing_critical_fields'] ) ); ?></td>
							<td><?php echo esc_html( empty( $blocker['mixed_language_fields'] ) ? '—' : implode( ', ', (array) $blocker['mixed_language_fields'] ) ); ?></td>
							<td><?php echo esc_html( empty( $reason_labels ) ? '—' : implode( '; ', $reason_labels ) ); ?></td>
							<td><strong><?php esc_html_e( 'Keep noindex', 'koz-translate-diagnostics' ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php endif; ?>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Diagnostic findings', 'koz-translate-diagnostics' ); ?></h2>
			<ul style="max-width:1150px;background:#fff;border:1px solid #dcdcde;padding:18px 18px 18px 36px">
			<?php if ( empty( $report['hints'] ) ) : ?><li><?php esc_html_e( 'No automatic findings were generated.', 'koz-translate-diagnostics' ); ?></li><?php endif; ?>
			<?php foreach ( $report['hints'] as $hint ) : ?><li><?php echo esc_html( $hint ); ?></li><?php endforeach; ?>
			</ul>

			<h2><?php esc_html_e( 'Short report for support', 'koz-translate-diagnostics' ); ?></h2>
			<p><?php esc_html_e( 'A plain-text summary without content, addresses, keys or identifiers. Paste it into a support chat or ticket.', 'koz-translate-diagnostics' ); ?></p>
			<textarea id="koztdiag-summary" class="large-text code" rows="14" readonly style="max-width:1180px"><?php echo esc_textarea( $short_report ); ?></textarea>
			<p><button type="button" class="button" id="koztdiag-copy-summary"><?php esc_html_e( 'Copy short report', 'koz-translate-diagnostics' ); ?></button> <span id="koztdiag-copy-status" aria-live="polite"></span></p>

			<h2><?php esc_html_e( 'Privacy', 'koz-translate-diagnostics' ); ?></h2>
			<p><?php esc_html_e( 'The exported report uses an allowlisted translator API contract, removes raw plugin paths and table names, fingerprints operational identifiers, and applies a final privacy pass after third-party filters.', 'koz-translate-diagnostics' ); ?></p>
		</div>
		<?php
	}
}
