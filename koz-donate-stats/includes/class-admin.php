<?php
namespace ramirkz\kozdonate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZDONATE_Admin {
	private const FALLBACK_SUITE_PAGE = 'kozdonate-suite-root';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 200 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_' . KOZDONATE_Plugin::EXPORT_CSV_ACTION, array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_' . KOZDONATE_Plugin::EXPORT_JSON_ACTION, array( __CLASS__, 'export_json' ) );
		add_action( 'admin_post_' . KOZDONATE_Plugin::RESET_ACTION, array( __CLASS__, 'reset_stats' ) );
		add_action( 'admin_post_' . KOZDONATE_Plugin::ROTATE_SECRET_ACTION, array( __CLASS__, 'rotate_confirmation_secret' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KOZDONATE_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function register_settings(): void {
		register_setting(
			'kozdonate_settings_group',
			KOZDONATE_Plugin::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( KOZDONATE_Plugin::class, 'sanitize_settings' ),
				'default'           => KOZDONATE_Plugin::defaults(),
			)
		);
	}

	private static function suite_page_slug(): string {
		global $menu;

		foreach ( (array) $menu as $item ) {
			$label = isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : '';
			$slug  = isset( $item[2] ) ? (string) $item[2] : '';
			if ( 'KOZ Suite' === trim( $label ) && '' !== $slug ) {
				return $slug;
			}
		}

		add_menu_page(
			__( 'KOZ WordPress Suite', 'koz-donate-stats' ),
			__( 'KOZ Suite', 'koz-donate-stats' ),
			'manage_options',
			self::FALLBACK_SUITE_PAGE,
			array( __CLASS__, 'suite_page' ),
			'dashicons-layout',
			58
		);

		return self::FALLBACK_SUITE_PAGE;
	}

	public static function menu(): void {
		add_submenu_page(
			self::suite_page_slug(),
			__( 'KOZ Donate Stats & Conversions', 'koz-donate-stats' ),
			__( 'Donate Stats', 'koz-donate-stats' ),
			'manage_options',
			KOZDONATE_Plugin::PAGE_SLUG,
			array( __CLASS__, 'page' )
		);
	}

	public static function action_links( array $links ): array {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . KOZDONATE_Plugin::PAGE_SLUG ) ) . '">' .
			esc_html__( 'Settings', 'koz-donate-stats' ) .
			'</a>'
		);
		return $links;
	}

	public static function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, KOZDONATE_Plugin::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'kozdonate-admin',
			KOZDONATE_URL . 'assets/admin.css',
			array(),
			KOZDONATE_VERSION
		);
		wp_enqueue_script(
			'kozdonate-admin',
			KOZDONATE_URL . 'assets/admin.js',
			array(),
			KOZDONATE_VERSION,
			true
		);
		wp_localize_script(
			'kozdonate-admin',
			'KOZDONATEAdminI18n',
			array( 'copied' => __( 'Copied', 'koz-donate-stats' ) )
		);
	}

	public static function suite_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap kozdonate">
			<h1><?php esc_html_e( 'KOZ WordPress Suite', 'koz-donate-stats' ); ?></h1>
			<p><?php esc_html_e( 'Independent, privacy-conscious WordPress tools originally developed to solve real operational needs of a charitable foundation website.', 'koz-donate-stats' ); ?></p>
			<?php self::suite_table(); ?>
		</div>
		<?php
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		if ( ! in_array( $tab, array( 'overview', 'settings' ), true ) ) {
			$tab = 'overview';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report range.
		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		if ( ! in_array( $days, array( 7, 30, 90 ), true ) ) {
			$days = 30;
		}
		?>
		<div class="wrap kozdonate">
			<h1><?php esc_html_e( 'KOZ Donate Stats & Conversions', 'koz-donate-stats' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Local aggregate donation journey statistics without IP addresses, referrers, fingerprinting, payment values or copied account details.', 'koz-donate-stats' ); ?>
			</p>

			<nav class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'overview' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::tab_url( 'overview', $days ) ); ?>"><?php esc_html_e( 'Overview', 'koz-donate-stats' ); ?></a>
				<a class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::tab_url( 'settings', $days ) ); ?>"><?php esc_html_e( 'Settings', 'koz-donate-stats' ); ?></a>
			</nav>

			<?php
			if ( 'settings' === $tab ) {
				self::settings_tab();
			} else {
				self::overview_tab( $days );
			}
			?>
		</div>
		<?php
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function manager_summary( int $days = 30 ): array {
		return self::manager_summary_from(
			KOZDONATE_Storage::summary( $days ),
			KOZDONATE_Plugin::settings()
		);
	}

	/**
	 * Pure manager-facing interpretation of aggregate numbers and settings.
	 *
	 * @param array<string,mixed> $summary
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	public static function manager_summary_from( array $summary, array $settings ): array {
		$enabled = ! empty( $settings['enabled'] );
		$translated_routes = ! empty( $settings['include_static_translations'] )
			? KOZDONATE_Plugin::translated_routes( (array) ( $settings['tracked_page_ids'] ?? array() ) )
			: array();
		$tracked_targets = count( (array) ( $settings['tracked_page_ids'] ?? array() ) )
			+ count( (array) ( $settings['tracked_paths'] ?? array() ) )
			+ count( $translated_routes );
		$configured = $tracked_targets > 0;

		$sessions = max( 0, (int) ( $summary['sessions'] ?? 0 ) );
		$views = max( 0, (int) ( $summary['views'] ?? 0 ) );
		$donate_clicks = max( 0, (int) ( $summary['donate_clicks'] ?? 0 ) );
		$payment_opens = max( 0, (int) ( $summary['payment_opens'] ?? 0 ) );
		$copy_clicks = max( 0, (int) ( $summary['copy_clicks'] ?? 0 ) );
		$successes = max( 0, (int) ( $summary['successes'] ?? 0 ) );
		$conversion_rate = max( 0.0, min( 100.0, (float) ( $summary['conversion_rate'] ?? 0.0 ) ) );
		$confirmation = KOZDONATE_Plugin::confirmation_status();
		$confirmation_ready = ! empty( $confirmation['ready'] )
			|| ! empty( $confirmation['client_marker_enabled'] );

		$attention = array();
		$actions = array();

		if ( ! $enabled ) {
			$attention[] = array(
				'level' => 'warning',
				'title' => __( 'Donation statistics are disabled', 'koz-donate-stats' ),
				'message' => __( 'The plugin is installed, but page events are not being collected.', 'koz-donate-stats' ),
			);
			$actions[] = array(
				'level' => 'warning',
				'title' => __( 'Enable statistics collection', 'koz-donate-stats' ),
				'description' => __( 'Select a donation page in Settings and enable local collection.', 'koz-donate-stats' ),
			);
		} elseif ( ! $configured ) {
			$attention[] = array(
				'level' => 'warning',
				'title' => __( 'No donation page selected', 'koz-donate-stats' ),
				'message' => __( 'The plugin is enabled, but no pages are configured for tracking.', 'koz-donate-stats' ),
			);
			$actions[] = array(
				'level' => 'warning',
				'title' => __( 'Select a donation page', 'koz-donate-stats' ),
				'description' => __( 'Open Settings and select a page or local path.', 'koz-donate-stats' ),
			);
		}

		if (
			$enabled
			&& $configured
			&& $sessions >= 20
			&& 0 === $donate_clicks
			&& 0 === $payment_opens
			&& 0 === $copy_clicks
		) {
			$attention[] = array(
				'level' => 'warning',
				'title' => __( 'Visitors arrive but do not click support', 'koz-donate-stats' ),
				'message' => __( 'The page receives visits, but no donation-button clicks are recorded.', 'koz-donate-stats' ),
			);
			$actions[] = array(
				'level' => 'warning',
				'title' => __( 'Check the support button', 'koz-donate-stats' ),
				'description' => __( 'Check that the button leads to one of the configured payment domains.', 'koz-donate-stats' ),
			);
		}

		if ( $payment_opens > 0 && 0 === $successes ) {
			$attention[] = array(
				'level' => 'info',
				'title' => __( 'Payments are opened, but no confirmations have arrived', 'koz-donate-stats' ),
				'message' => $confirmation_ready
					? __( 'Confirmation is connected, but no successful payments were received in this period.', 'koz-donate-stats' )
					: __( 'Clicks are counted, but the payment service is not yet sending successful-payment confirmations.', 'koz-donate-stats' ),
			);
			$actions[] = array(
				'level' => 'info',
				'title' => __( 'Check donation confirmation', 'koz-donate-stats' ),
				'description' => $confirmation_ready
					? __( 'Check the latest callback in the payment service.', 'koz-donate-stats' )
					: __( 'In Settings, copy the callback URL and secret to the payment service or connector.', 'koz-donate-stats' ),
			);
		}

		if ( ! empty( $settings['data_layer_enabled'] ) && 'none' === (string) ( $settings['ad_account_mode'] ?? 'none' ) ) {
			$attention[] = array(
				'level' => 'warning',
				'title' => __( 'dataLayer is enabled without an advertising mode', 'koz-donate-stats' ),
				'message' => __( 'Events are prepared for advertising, but the Google account type is not selected.', 'koz-donate-stats' ),
			);
			$actions[] = array(
				'level' => 'warning',
				'title' => __( 'Select Google Ad Grants or Google Ads', 'koz-donate-stats' ),
				'description' => __( 'Choose a mode in Settings or disable dataLayer.', 'koz-donate-stats' ),
			);
		}

		if ( ! $enabled ) {
			$overall = 'disabled';
			$headline = __( 'Setup needs to be completed', 'koz-donate-stats' );
		} elseif ( ! $configured ) {
			$overall = 'attention';
			$headline = __( 'A donation page must be selected', 'koz-donate-stats' );
		} elseif ( 0 === $sessions ) {
			$overall = 'waiting';
			$headline = __( 'Data collection is enabled. Waiting for the first visits', 'koz-donate-stats' );
		} elseif ( $successes > 0 ) {
			$overall = 'good';
			$headline = __( 'The donation funnel is working', 'koz-donate-stats' );
		} elseif ( $payment_opens > 0 ) {
			$overall = 'attention';
			$headline = __( 'Visitors proceed to payment', 'koz-donate-stats' );
		} else {
			$overall = 'good';
			$headline = __( 'Statistics are being collected', 'koz-donate-stats' );
		}

		if ( empty( $actions ) ) {
			$actions[] = array(
				'level' => 'success',
				'title' => __( 'Nothing urgent', 'koz-donate-stats' ),
				'description' => __( 'The funnel is working. Review results after page or advertising changes.', 'koz-donate-stats' ),
			);
		}

		return array(
			'overall' => $overall,
			'headline' => $headline,
			'enabled' => $enabled,
			'configured' => $configured,
			'tracked_targets' => $tracked_targets,
			'views' => $views,
			'sessions' => $sessions,
			'donate_clicks' => $donate_clicks,
			'payment_opens' => $payment_opens,
			'copy_clicks' => $copy_clicks,
			'successes' => $successes,
			'donations_total' => $successes,
			'conversions_total' => $successes,
			'conversion_rate' => $conversion_rate,
			'confirmation_ready' => $confirmation_ready,
			'last_confirmation_at' => (string) ( $confirmation['last_confirmation_at'] ?? '' ),
			'attention' => $attention,
			'actions' => $actions,
		);
	}

	private static function manager_card( string $label, string $value, string $note = '' ): void {
		?>
		<div class="kozdonate-manager-card">
			<span><?php echo esc_html( $label ); ?></span>
			<strong><?php echo esc_html( $value ); ?></strong>
			<?php if ( '' !== $note ) : ?><small><?php echo esc_html( $note ); ?></small><?php endif; ?>
		</div>
		<?php
	}

	private static function manager_items( string $title, array $items, string $empty_message = '' ): void {
		?>
		<section class="kozdonate-manager-section">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( empty( $items ) ) : ?>
				<p class="kozdonate-empty"><?php echo esc_html( $empty_message ); ?></p>
			<?php else : ?>
				<div class="kozdonate-manager-list">
					<?php foreach ( $items as $item ) : ?>
						<article class="kozdonate-manager-item is-<?php echo esc_attr( (string) ( $item['level'] ?? 'info' ) ); ?>">
							<h3><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></h3>
							<p><?php echo esc_html( (string) ( $item['message'] ?? $item['description'] ?? '' ) ); ?></p>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function overview_tab( int $days ): void {
		$summary = KOZDONATE_Storage::summary( $days );
		$settings = KOZDONATE_Plugin::settings();
		$manager = self::manager_summary_from( $summary, $settings );
		$daily = KOZDONATE_Storage::daily_rows( $days );
		$targets = KOZDONATE_Storage::top_targets( $days );
		$contexts = KOZDONATE_Storage::context_rows( $days );
		$languages = KOZDONATE_Storage::language_rows( $days );
		?>
		<div class="kozdonate-manager-hero is-<?php echo esc_attr( (string) $manager['overall'] ); ?>">
			<span><?php esc_html_e( 'Donation funnel status', 'koz-donate-stats' ); ?></span>
			<strong><?php echo esc_html( (string) $manager['headline'] ); ?></strong>
			<p><?php esc_html_e( 'Only decision-useful information is shown. Technical tables are below.', 'koz-donate-stats' ); ?></p>
		</div>

		<div class="kozdonate-manager-cards">
			<?php self::manager_card( __( 'Visited the page', 'koz-donate-stats' ), number_format_i18n( (int) $manager['sessions'] ), __( 'Unique daily sessions', 'koz-donate-stats' ) ); ?>
			<?php self::manager_card( __( 'Clicked support', 'koz-donate-stats' ), number_format_i18n( (int) $manager['donate_clicks'] ) ); ?>
			<?php self::manager_card( __( 'Opened payment', 'koz-donate-stats' ), number_format_i18n( (int) $manager['payment_opens'] ) ); ?>
			<?php self::manager_card( __( 'Copied payment details', 'koz-donate-stats' ), number_format_i18n( (int) $manager['copy_clicks'] ), __( 'Only the fact of a successful copy', 'koz-donate-stats' ) ); ?>
			<?php self::manager_card( __( 'Confirmed donations', 'koz-donate-stats' ), number_format_i18n( (int) $manager['successes'] ), __( 'Not an accounting ledger', 'koz-donate-stats' ) ); ?>
			<?php self::manager_card( __( 'Conversion rate', 'koz-donate-stats' ), number_format_i18n( (float) $manager['conversion_rate'], 2 ) . '%' ); ?>
		</div>

		<section class="kozdonate-card kozdonate-explain">
			<h2><?php esc_html_e( 'How to read these two metrics', 'koz-donate-stats' ); ?></h2>
			<p><strong><?php esc_html_e( 'Confirmed donation', 'koz-donate-stats' ); ?>:</strong> <?php esc_html_e( 'the payment service or your server connector reported that the payment actually succeeded. A click or payment-page opening is not counted as a donation.', 'koz-donate-stats' ); ?></p>
			<p><strong><?php esc_html_e( 'Conversion rate', 'koz-donate-stats' ); ?>:</strong> <?php esc_html_e( 'confirmed donations divided by unique daily sessions on the donation page.', 'koz-donate-stats' ); ?></p>
			<?php if ( ! empty( $manager['last_confirmation_at'] ) ) : ?>
				<p><?php
					/* translators: %s: date and time of the latest confirmed donation. */
					echo esc_html( sprintf( __( 'Latest confirmation: %s', 'koz-donate-stats' ), (string) $manager['last_confirmation_at'] ) );
				?></p>
			<?php endif; ?>
		</section>

		<?php self::manager_items(
			__( 'Needs attention', 'koz-donate-stats' ),
			(array) $manager['attention'],
			__( 'There are no issues requiring attention.', 'koz-donate-stats' )
		); ?>

		<?php self::manager_items(
			__( 'What to do', 'koz-donate-stats' ),
			(array) $manager['actions']
		); ?>

		<div class="kozdonate-toolbar">
			<div>
				<?php foreach ( array( 7, 30, 90 ) as $range ) : ?>
					<a class="button <?php echo $days === $range ? 'button-primary' : ''; ?>" href="<?php echo esc_url( self::tab_url( 'overview', $range ) ); ?>">
						<?php
							/* translators: %d: report period in days. */
							echo esc_html( sprintf( __( '%d days', 'koz-donate-stats' ), $range ) );
						?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>

		<details class="kozdonate-technical">
			<summary><?php esc_html_e( 'Technical data and export', 'koz-donate-stats' ); ?></summary>
			<div class="kozdonate-technical-inner">
				<p>
					<a class="button" href="<?php echo esc_url( self::export_url( KOZDONATE_Plugin::EXPORT_CSV_ACTION, $days ) ); ?>"><?php esc_html_e( 'Export CSV', 'koz-donate-stats' ); ?></a>
					<a class="button" href="<?php echo esc_url( self::export_url( KOZDONATE_Plugin::EXPORT_JSON_ACTION, $days ) ); ?>"><?php esc_html_e( 'Export JSON', 'koz-donate-stats' ); ?></a>
				</p>

				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'A confirmed donation is an event from the page or server integration. It is not a financial statement or proof that funds were received.', 'koz-donate-stats' ); ?></p>
				</div>

				<section class="kozdonate-card">
					<h2><?php esc_html_e( 'Daily activity', 'koz-donate-stats' ); ?></h2>
					<div class="kozdonate-table-wrap">
						<table class="widefat striped">
							<thead><tr>
								<th><?php esc_html_e( 'Date', 'koz-donate-stats' ); ?></th>
								<th><?php esc_html_e( 'Views', 'koz-donate-stats' ); ?></th>
								<th><?php esc_html_e( 'Sessions', 'koz-donate-stats' ); ?></th>
								<th><?php esc_html_e( 'Donate clicks', 'koz-donate-stats' ); ?></th>
								<th><?php esc_html_e( 'Payment opens', 'koz-donate-stats' ); ?></th>
								<th><?php esc_html_e( 'Copies', 'koz-donate-stats' ); ?></th>
								<th><?php esc_html_e( 'Successes', 'koz-donate-stats' ); ?></th>
							</tr></thead>
							<tbody>
								<?php if ( empty( $daily ) ) : ?><tr><td colspan="7"><?php esc_html_e( 'No data yet.', 'koz-donate-stats' ); ?></td></tr><?php endif; ?>
								<?php foreach ( $daily as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row['stat_date'] ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $row['views'] ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $row['sessions'] ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $row['donate_clicks'] ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $row['payment_opens'] ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $row['copy_clicks'] ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $row['successes'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>

				<div class="kozdonate-grid">
					<section class="kozdonate-card">
						<h2><?php esc_html_e( 'Tracked contexts', 'koz-donate-stats' ); ?></h2>
						<table class="widefat striped">
							<thead><tr><th><?php esc_html_e( 'Context', 'koz-donate-stats' ); ?></th><th><?php esc_html_e( 'Sessions', 'koz-donate-stats' ); ?></th><th><?php esc_html_e( 'Engagements', 'koz-donate-stats' ); ?></th><th><?php esc_html_e( 'Successes', 'koz-donate-stats' ); ?></th></tr></thead>
							<tbody>
							<?php if ( empty( $contexts ) ) : ?><tr><td colspan="4"><?php esc_html_e( 'No data yet.', 'koz-donate-stats' ); ?></td></tr><?php endif; ?>
							<?php foreach ( $contexts as $row ) : ?>
								<tr><td><code><?php echo esc_html( $row['context_key'] ); ?></code></td><td><?php echo esc_html( number_format_i18n( $row['sessions'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['engagements'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['successes'] ) ); ?></td></tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</section>
					<section class="kozdonate-card">
						<h2><?php esc_html_e( 'Languages', 'koz-donate-stats' ); ?></h2>
						<table class="widefat striped">
							<thead><tr><th><?php esc_html_e( 'Language', 'koz-donate-stats' ); ?></th><th><?php esc_html_e( 'Sessions', 'koz-donate-stats' ); ?></th><th><?php esc_html_e( 'Engagements', 'koz-donate-stats' ); ?></th><th><?php esc_html_e( 'Successes', 'koz-donate-stats' ); ?></th></tr></thead>
							<tbody>
							<?php if ( empty( $languages ) ) : ?><tr><td colspan="4"><?php esc_html_e( 'No data yet.', 'koz-donate-stats' ); ?></td></tr><?php endif; ?>
							<?php foreach ( $languages as $row ) : ?>
								<tr><td><code><?php echo esc_html( $row['language'] ); ?></code></td><td><?php echo esc_html( number_format_i18n( $row['sessions'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['engagements'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['successes'] ) ); ?></td></tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</section>
				</div>

				<section class="kozdonate-card">
					<h2><?php esc_html_e( 'Top event targets', 'koz-donate-stats' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Event', 'koz-donate-stats' ); ?></th><th><?php esc_html_e( 'Target key', 'koz-donate-stats' ); ?></th><th><?php esc_html_e( 'Count', 'koz-donate-stats' ); ?></th></tr></thead>
						<tbody>
						<?php if ( empty( $targets ) ) : ?><tr><td colspan="3"><?php esc_html_e( 'No data yet.', 'koz-donate-stats' ); ?></td></tr><?php endif; ?>
						<?php foreach ( $targets as $row ) : ?>
							<tr><td><?php echo esc_html( self::event_label( $row['event_type'] ) ); ?></td><td><code><?php echo esc_html( $row['target_key'] ?: '—' ); ?></code></td><td><?php echo esc_html( number_format_i18n( $row['event_count'] ) ); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</section>
			</div>
		</details>
		<?php
	}

	private static function settings_tab(): void {
		$settings = KOZDONATE_Plugin::settings();
		$items = self::trackable_content();
		$translated_routes = ! empty( $settings['include_static_translations'] )
			? KOZDONATE_Plugin::translated_routes( (array) $settings['tracked_page_ids'] )
			: array();
		$routes_by_post = array();
		foreach ( $translated_routes as $route ) {
			$routes_by_post[ (int) $route['post_id'] ][] = $route;
		}
		$tracked_targets = count( (array) $settings['tracked_page_ids'] )
			+ count( (array) $settings['tracked_paths'] )
			+ count( $translated_routes );
		$ready = ! empty( $settings['enabled'] ) && $tracked_targets > 0;
		?>
		<section class="kozdonate-card kozdonate-setup-status <?php echo $ready ? 'is-ready' : 'is-attention'; ?>">
			<h2><?php echo esc_html( $ready ? __( 'Statistics collection is ready', 'koz-donate-stats' ) : __( 'Quick setup must be completed', 'koz-donate-stats' ) ); ?></h2>
			<ol>
				<li><?php echo ! empty( $settings['enabled'] ) ? '✓' : '○'; ?> <?php esc_html_e( 'Enable local statistics collection', 'koz-donate-stats' ); ?></li>
				<li><?php echo $tracked_targets > 0 ? '✓' : '○'; ?> <?php esc_html_e( 'Select a donation page or local path', 'koz-donate-stats' ); ?></li>
				<li><?php echo ! empty( $settings['payment_hosts'] ) ? '✓' : '○'; ?> <?php esc_html_e( 'Add payment domains when external services are used', 'koz-donate-stats' ); ?></li>
				<li><?php echo ! empty( KOZDONATE_Plugin::confirmation_status()['ready'] ) || ! empty( $settings['allow_client_success'] ) ? '✓' : '○'; ?> <?php esc_html_e( 'Connect successful-donation confirmation', 'koz-donate-stats' ); ?></li>
			</ol>
		</section>
		<form method="post" action="options.php" class="kozdonate-settings">
			<?php settings_fields( 'kozdonate_settings_group' ); ?>

			<section class="kozdonate-card">
				<h2><?php esc_html_e( '1. Activation', 'koz-donate-stats' ); ?></h2>
				<label class="kozdonate-check">
					<input type="checkbox" name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION ); ?>[enabled]" value="1" <?php checked( 1, $settings['enabled'] ); ?>>
					<span><?php esc_html_e( 'Enable local statistics collection', 'koz-donate-stats' ); ?></span>
				</label>
				<label class="kozdonate-check">
					<input type="checkbox" name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION ); ?>[exclude_admins]" value="1" <?php checked( 1, $settings['exclude_admins'] ); ?>>
					<span><?php esc_html_e( 'Do not count actions by WordPress administrators', 'koz-donate-stats' ); ?></span>
				</label>
				<p class="description"><?php esc_html_e( 'Collection starts after at least one page or local path is selected.', 'koz-donate-stats' ); ?></p>
			</section>

			<section class="kozdonate-card">
				<h2><?php esc_html_e( '2. Where to track', 'koz-donate-stats' ); ?></h2>
				<p><?php esc_html_e( 'Select the page where people can support you or proceed to payment.', 'koz-donate-stats' ); ?></p>
				<label class="kozdonate-check">
					<input type="checkbox" name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION ); ?>[include_static_translations]" value="1" <?php checked( 1, $settings['include_static_translations'] ); ?>>
					<span><?php esc_html_e( 'Automatically include all active KOZ Static Translate language versions', 'koz-donate-stats' ); ?></span>
				</label>
				<p class="description"><?php esc_html_e( 'After saving, language routes for the selected page appear in this list and are tracked automatically.', 'koz-donate-stats' ); ?></p>
				<div class="kozdonate-scroll-list">
					<?php if ( empty( $items ) ) : ?>
						<p><?php esc_html_e( 'No published public content was found.', 'koz-donate-stats' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $items as $item ) : ?>
						<label class="kozdonate-source-route">
							<input type="checkbox" name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION ); ?>[tracked_page_ids][]" value="<?php echo esc_attr( (string) $item['id'] ); ?>" <?php checked( in_array( $item['id'], $settings['tracked_page_ids'], true ) ); ?>>
							<?php echo esc_html( $item['title'] ); ?>
							<code><?php echo esc_html( $item['type'] . ':' . $item['id'] ); ?></code>
						</label>
						<?php foreach ( (array) ( $routes_by_post[ (int) $item['id'] ] ?? array() ) as $route ) : ?>
							<label class="kozdonate-generated-route">
								<?php /* translators: %s: uppercase language code for an automatically generated route. */ ?>
								<input type="checkbox" checked disabled aria-label="<?php echo esc_attr( sprintf( __( 'Language route %s', 'koz-donate-stats' ), strtoupper( (string) $route['language'] ) ) ); ?>">
								<strong><?php echo esc_html( strtoupper( (string) $route['language'] ) ); ?></strong>
								<?php echo esc_html( $route['title'] ); ?>
								<code><?php echo esc_html( $route['path'] ); ?></code>
							</label>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</div>
				<?php if ( ! empty( $settings['include_static_translations'] ) && empty( KOZDONATE_Plugin::static_translate_languages() ) ) : ?>
					<p class="description kozdonate-warning-text"><?php esc_html_e( 'KOZ Static Translate did not provide active language routes. Only selected WordPress pages and manual paths will be tracked.', 'koz-donate-stats' ); ?></p>
				<?php endif; ?>

				<label class="kozdonate-field">
					<span><?php esc_html_e( 'Additional local paths', 'koz-donate-stats' ); ?></span>
					<textarea name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION ); ?>[tracked_paths]" rows="6" class="large-text code"><?php echo esc_textarea( implode( "\n", $settings['tracked_paths'] ) ); ?></textarea>
				</label>
				<p class="description"><?php esc_html_e( 'One path per line. This field can usually be left empty.', 'koz-donate-stats' ); ?></p>
			</section>

			<section class="kozdonate-card kozdonate-confirmation">
				<h2><?php esc_html_e( '3. Confirmed donations', 'koz-donate-stats' ); ?></h2>
				<p><?php esc_html_e( 'Recommended: after successful payment, the payment service or connector sends one signed callback. The plugin does not receive the amount, name, email or payment details.', 'koz-donate-stats' ); ?></p>

				<label class="kozdonate-field">
					<span><?php esc_html_e( 'Confirmation method', 'koz-donate-stats' ); ?></span>
					<select name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION ); ?>[confirmation_mode]">
						<option value="webhook" <?php selected( 'webhook', $settings['confirmation_mode'] ); ?>><?php esc_html_e( 'Server callback — recommended', 'koz-donate-stats' ); ?></option>
						<option value="client_marker" <?php selected( 'client_marker', $settings['confirmation_mode'] ); ?>><?php esc_html_e( 'Success-page marker — less reliable', 'koz-donate-stats' ); ?></option>
						<option value="none" <?php selected( 'none', $settings['confirmation_mode'] ); ?>><?php esc_html_e( 'Do not count confirmed donations', 'koz-donate-stats' ); ?></option>
					</select>
				</label>

				<div class="kozdonate-copy-row">
					<label class="kozdonate-field">
						<span><?php esc_html_e( 'Callback URL', 'koz-donate-stats' ); ?></span>
						<input id="kozdonate-confirm-url" type="text" readonly class="large-text code" value="<?php echo esc_attr( KOZDONATE_Plugin::confirmation_callback_url() ); ?>">
					</label>
					<button type="button" class="button" data-kozdonate-copy-value="#kozdonate-confirm-url"><?php esc_html_e( 'Copy URL', 'koz-donate-stats' ); ?></button>
				</div>

				<div class="kozdonate-copy-row">
					<label class="kozdonate-field">
						<span><?php esc_html_e( 'Signing secret', 'koz-donate-stats' ); ?></span>
						<input id="kozdonate-confirm-secret" type="password" readonly class="large-text code" value="<?php echo esc_attr( $settings['confirmation_secret'] ); ?>">
					</label>
					<button type="button" class="button" data-kozdonate-copy-value="#kozdonate-confirm-secret"><?php esc_html_e( 'Copy secret', 'koz-donate-stats' ); ?></button>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', KOZDONATE_Plugin::ROTATE_SECRET_ACTION, admin_url( 'admin-post.php' ) ), KOZDONATE_Plugin::ROTATE_SECRET_ACTION ) ); ?>"><?php esc_html_e( 'Create a new secret', 'koz-donate-stats' ); ?></a>
				</div>

				<ol>
					<li><?php esc_html_e( 'Copy the callback URL.', 'koz-donate-stats' ); ?></li>
					<li><?php esc_html_e( 'Copy the secret.', 'koz-donate-stats' ); ?></li>
					<li><?php esc_html_e( 'Add them to the payment service or connector that is called only after successful payment.', 'koz-donate-stats' ); ?></li>
				</ol>

				<details>
					<summary><?php esc_html_e( 'Technical callback format', 'koz-donate-stats' ); ?></summary>
					<p><?php esc_html_e( 'POST JSON with the header X-KOZDONATE-Signature = HMAC-SHA256(raw body, secret).', 'koz-donate-stats' ); ?></p>
					<pre>{"event":"donation_success","reference":"unique-provider-reference","provider":"payment","language":"uk"}</pre>
					<p><?php esc_html_e( 'reference is used only to prevent duplicate counting and is stored only as an HMAC hash.', 'koz-donate-stats' ); ?></p>
				</details>

				<label class="kozdonate-check">
					<input type="checkbox" name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION ); ?>[allow_client_success]" value="1" <?php checked( 1, $settings['allow_client_success'] ); ?>>
					<span><?php esc_html_e( 'Allow a success-page marker when the less reliable mode is selected', 'koz-donate-stats' ); ?></span>
				</label>
			</section>

			<details class="kozdonate-card kozdonate-technical-settings">
				<summary><strong><?php esc_html_e( 'Additional technical button settings', 'koz-donate-stats' ); ?></strong></summary>
				<div class="kozdonate-technical-settings-inner">
				<?php self::selector_field( 'donate_selector', __( 'Donate action selector', 'koz-donate-stats' ), $settings ); ?>
				<?php self::selector_field( 'payment_selector', __( 'Payment link selector', 'koz-donate-stats' ), $settings ); ?>
				<?php self::selector_field( 'copy_selector', __( 'Copy action selector', 'koz-donate-stats' ), $settings ); ?>
				<?php self::selector_field( 'success_selector', __( 'Reported success marker selector', 'koz-donate-stats' ), $settings ); ?>

				<label class="kozdonate-field">
					<span><?php esc_html_e( 'Allowed payment hostnames', 'koz-donate-stats' ); ?></span>
					<textarea name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION ); ?>[payment_hosts]" rows="5" class="large-text code"><?php echo esc_textarea( implode( "\n", $settings['payment_hosts'] ) ); ?></textarea>
				</label>
				<p class="description"><?php esc_html_e( 'One hostname per line. The plugin stores only the hostname, never a full external URL.', 'koz-donate-stats' ); ?></p>

				<p class="description"><?php esc_html_e( 'CSS selectors are needed only when automatic theme detection did not work.', 'koz-donate-stats' ); ?></p>
				</div>
			</details>

			<section class="kozdonate-card">
				<h2><?php esc_html_e( '4. Google Ads — optional', 'koz-donate-stats' ); ?></h2>
				<label class="kozdonate-field">
					<span><?php esc_html_e( 'Advertising account type', 'koz-donate-stats' ); ?></span>
					<select name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION ); ?>[ad_account_mode]">
						<option value="none" <?php selected( 'none', $settings['ad_account_mode'] ); ?>><?php esc_html_e( 'Google Ads is not used', 'koz-donate-stats' ); ?></option>
						<option value="ad_grants" <?php selected( 'ad_grants', $settings['ad_account_mode'] ); ?>><?php esc_html_e( 'Google Ad Grants', 'koz-donate-stats' ); ?></option>
						<option value="google_ads" <?php selected( 'google_ads', $settings['ad_account_mode'] ); ?>><?php esc_html_e( 'Standard Google Ads', 'koz-donate-stats' ); ?></option>
					</select>
				</label>

				<label class="kozdonate-check">
					<input type="checkbox" name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION ); ?>[data_layer_enabled]" value="1" <?php checked( 1, $settings['data_layer_enabled'] ); ?>>
					<span><?php esc_html_e( 'Send safe events to dataLayer', 'koz-donate-stats' ); ?></span>
				</label>

				<label class="kozdonate-field">
					<span><?php esc_html_e( 'dataLayer event name', 'koz-donate-stats' ); ?></span>
					<input type="text" name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION ); ?>[data_layer_event]" value="<?php echo esc_attr( $settings['data_layer_event'] ); ?>" class="regular-text code">
				</label>

				<label class="kozdonate-field">
					<span><?php esc_html_e( 'Consent gate', 'koz-donate-stats' ); ?></span>
					<select name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION ); ?>[consent_gate]">
						<option value="none" <?php selected( 'none', $settings['consent_gate'] ); ?>><?php esc_html_e( 'Do not gate events', 'koz-donate-stats' ); ?></option>
						<option value="external_only" <?php selected( 'external_only', $settings['consent_gate'] ); ?>><?php esc_html_e( 'Gate dataLayer only', 'koz-donate-stats' ); ?></option>
						<option value="all" <?php selected( 'all', $settings['consent_gate'] ); ?>><?php esc_html_e( 'Gate local and dataLayer events', 'koz-donate-stats' ); ?></option>
					</select>
				</label>

				<label class="kozdonate-field">
					<span><?php esc_html_e( 'Consent category', 'koz-donate-stats' ); ?></span>
					<input type="text" name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION ); ?>[consent_category]" value="<?php echo esc_attr( $settings['consent_category'] ); ?>" class="regular-text code">
				</label>
				<p class="description"><?php esc_html_e( 'KOZ Consent Manager controls this category. With analytics consent, the Google Ads event is sent; without consent, it remains blocked.', 'koz-donate-stats' ); ?></p>
			</section>

			<section class="kozdonate-card">
				<h2><?php esc_html_e( '5. Retention', 'koz-donate-stats' ); ?></h2>
				<label class="kozdonate-field">
					<span><?php esc_html_e( 'Number of days to retain statistics', 'koz-donate-stats' ); ?></span>
					<input type="number" min="30" max="730" name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION ); ?>[retention_days]" value="<?php echo esc_attr( (string) $settings['retention_days'] ); ?>">
				</label>
				<label class="kozdonate-field">
					<span><?php esc_html_e( 'When to consider a visit finished, minutes', 'koz-donate-stats' ); ?></span>
					<input type="number" min="5" max="120" name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION ); ?>[session_minutes]" value="<?php echo esc_attr( (string) $settings['session_minutes'] ); ?>">
				</label>
				<p class="description"><?php esc_html_e( 'The browser identifier is kept in sessionStorage and the database receives only a daily HMAC hash.', 'koz-donate-stats' ); ?></p>
			</section>

			<?php submit_button( __( 'Save settings', 'koz-donate-stats' ) ); ?>
		</form>

		<section class="kozdonate-card kozdonate-danger">
			<h2><?php esc_html_e( 'Delete aggregate statistics', 'koz-donate-stats' ); ?></h2>
			<p><?php esc_html_e( 'This truncates only the two statistics tables. Settings and plugin files remain.', 'koz-donate-stats' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( KOZDONATE_Plugin::RESET_ACTION ); ?>">
				<?php wp_nonce_field( KOZDONATE_Plugin::RESET_ACTION ); ?>
				<label class="kozdonate-field">
					<span><?php
						/* translators: %s: confirmation phrase required before deleting statistics. */
						echo esc_html( sprintf( __( 'Type %s to confirm', 'koz-donate-stats' ), KOZDONATE_Plugin::RESET_PHRASE ) );
					?></span>
					<input type="text" name="confirm_phrase" class="regular-text" autocomplete="off">
				</label>
				<button class="button" type="submit"><?php esc_html_e( 'Delete all statistics', 'koz-donate-stats' ); ?></button>
			</form>
		</section>
		<?php
	}

	private static function suite_table(): void {
		$items = KOZDONATE_Suite::status();
		?>
		<section class="kozdonate-card">
			<h2><?php esc_html_e( 'KOZ WordPress Suite', 'koz-donate-stats' ); ?></h2>
			<div class="kozdonate-suite">
				<?php foreach ( $items as $item ) : ?>
					<article>
						<h3><?php echo esc_html( $item['name'] ); ?></h3>
						<p><?php echo esc_html( $item['description'] ); ?></p>
						<p>
							<?php if ( $item['active'] ) : ?>
								<span class="kozdonate-status active"><?php esc_html_e( 'Active', 'koz-donate-stats' ); ?></span>
							<?php elseif ( $item['installed'] ) : ?>
								<span class="kozdonate-status installed"><?php esc_html_e( 'Installed', 'koz-donate-stats' ); ?></span>
							<?php else : ?>
								<span class="kozdonate-status"><?php esc_html_e( 'Available separately', 'koz-donate-stats' ); ?></span>
							<?php endif; ?>
							<?php if ( $item['version'] ) : ?>
								<code><?php echo esc_html( $item['version'] ); ?></code>
							<?php endif; ?>
						</p>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	private static function selector_field( string $key, string $label, array $settings ): void {
		?>
		<label class="kozdonate-field">
			<span><?php echo esc_html( $label ); ?></span>
			<input type="text" name="<?php echo esc_attr( KOZDONATE_Plugin::OPTION . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $settings[ $key ] ); ?>" class="large-text code">
		</label>
		<?php
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function trackable_content(): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );

		$posts = get_posts(
			array(
				'post_type'      => array_values( $post_types ),
				'post_status'    => 'publish',
				'posts_per_page' => 250,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$result = array();
		foreach ( $posts as $post ) {
			$result[] = array(
				'id'    => (int) $post->ID,
				'title' => get_the_title( $post ) ?: __( '(no title)', 'koz-donate-stats' ),
				'type'  => (string) $post->post_type,
			);
		}
		return $result;
	}

	private static function metric_card( string $label, $value, bool $decimal = false, string $suffix = '' ): void {
		$formatted = $decimal
			? number_format_i18n( (float) $value, 2 )
			: number_format_i18n( (int) $value );
		?>
		<div class="kozdonate-metric">
			<span><?php echo esc_html( $label ); ?></span>
			<strong><?php echo esc_html( $formatted . $suffix ); ?></strong>
		</div>
		<?php
	}

	private static function event_label( string $event_type ): string {
		$labels = array(
			'page_view'        => __( 'Page view', 'koz-donate-stats' ),
			'donate_click'     => __( 'Donate click', 'koz-donate-stats' ),
			'payment_open'     => __( 'Payment open', 'koz-donate-stats' ),
			'external_click'   => __( 'Legacy external click', 'koz-donate-stats' ),
			'copy_click'       => __( 'Copy click', 'koz-donate-stats' ),
			'donation_success' => __( 'Reported success', 'koz-donate-stats' ),
		);
		return $labels[ $event_type ] ?? $event_type;
	}

	private static function tab_url( string $tab, int $days ): string {
		return add_query_arg(
			array(
				'page' => KOZDONATE_Plugin::PAGE_SLUG,
				'tab'  => $tab,
				'days' => $days,
			),
			admin_url( 'admin.php' )
		);
	}

	private static function export_url( string $action, int $days ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => $action,
					'days'   => $days,
				),
				admin_url( 'admin-post.php' )
			),
			$action
		);
	}


	public static function rotate_confirmation_secret(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to rotate this secret.', 'koz-donate-stats' ) );
		}
		check_admin_referer( KOZDONATE_Plugin::ROTATE_SECRET_ACTION );
		KOZDONATE_Plugin::rotate_confirmation_secret();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => KOZDONATE_Plugin::PAGE_SLUG,
					'tab' => 'settings',
					'secret-rotated' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}


	/**
	 * Build a privacy-safe instrumentation diagnostic for manager/live validation.
	 *
	 * @return array<string,mixed>
	 */
	public static function instrumentation_diagnostic( int $days = 30 ): array {
		$settings = KOZDONATE_Plugin::settings();
		$manager = self::manager_summary( $days );
		$confirmation = KOZDONATE_Plugin::confirmation_status();
		$translated_routes = ! empty( $settings['include_static_translations'] )
			? KOZDONATE_Plugin::translated_routes( (array) $settings['tracked_page_ids'] )
			: array();

		$consent_active = class_exists( '\\ramirkz\\kozconsent\\KOZCONSENT_Plugin' );
		$copy_active = class_exists( '\\ramirkz\\kozcopyactions\\KOZCOAC_Plugin' );
		$translate_active = class_exists( '\\ramirkz\\kozstatictranslate\\KOZSTX_Plugin' )
			|| class_exists( '\\ramirkz\\kozstatictranslate\\KOZSTX_Translator' );

		return array(
			'plugin' => array(
				'name' => 'KOZ Donate Stats & Conversions',
				'version' => KOZDONATE_VERSION,
			),
			'environment' => array(
				'wordpress' => get_bloginfo( 'version' ),
				'php' => PHP_VERSION,
			),
			'period_days' => $days,
			'configuration' => array(
				'enabled' => ! empty( $settings['enabled'] ),
				'exclude_admins' => ! empty( $settings['exclude_admins'] ),
				'tracked_page_count' => count( (array) $settings['tracked_page_ids'] ),
				'tracked_path_count' => count( (array) $settings['tracked_paths'] ),
				'translated_route_count' => count( $translated_routes ),
				'payment_host_count' => count( (array) $settings['payment_hosts'] ),
				'confirmation_mode' => (string) $confirmation['mode'],
				'confirmation_ready' => ! empty( $confirmation['ready'] ),
				'client_marker_enabled' => ! empty( $confirmation['client_marker_enabled'] ),
				'last_confirmation_at' => (string) $confirmation['last_confirmation_at'],
				'ad_account_mode' => (string) $settings['ad_account_mode'],
				'data_layer_enabled' => ! empty( $settings['data_layer_enabled'] ),
				'data_layer_event' => (string) $settings['data_layer_event'],
				'consent_gate' => (string) $settings['consent_gate'],
				'consent_category' => (string) $settings['consent_category'],
			),
			'integrations' => array(
				'consent_manager_active' => $consent_active,
				'copy_actions_active' => $copy_active,
				'static_translate_active' => $translate_active,
			),
			'funnel_signals' => array(
				'sessions' => (int) $manager['sessions'],
				'donate_clicks' => (int) $manager['donate_clicks'],
				'payment_opens' => (int) $manager['payment_opens'],
				'copy_clicks' => (int) $manager['copy_clicks'],
				'confirmed_donations' => (int) $manager['successes'],
				'conversion_rate' => (float) $manager['conversion_rate'],
			),
			'preflight' => array(
				'local_collection_configured' => ! empty( $manager['enabled'] ) && ! empty( $manager['configured'] ),
				'confirmation_configured' => ! empty( $manager['confirmation_ready'] ),
				'data_layer_configuration_valid' => empty( $settings['data_layer_enabled'] ) || 'none' !== (string) $settings['ad_account_mode'],
				'consent_integration_available' => 'none' === (string) $settings['consent_gate'] || $consent_active,
				'browser_runtime' => 'NOT VERIFIED BY SERVER-SIDE EXPORT',
			),
			'privacy' => array(
				'confirmation_secret_exported' => false,
				'visitor_identifiers_exported' => false,
				'raw_payment_details_exported' => false,
			),
		);
	}

	public static function export_json(): void {
		self::authorize_export( KOZDONATE_Plugin::EXPORT_JSON_ACTION );
		$days = self::requested_days();
		$payload = KOZDONATE_Storage::export_payload( $days );
		$payload['instrumentation_diagnostic'] = self::instrumentation_diagnostic( $days );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="koz-donate-stats-' . gmdate( 'Ymd-His' ) . '.json"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	public static function export_csv(): void {
		self::authorize_export( KOZDONATE_Plugin::EXPORT_CSV_ACTION );
		$days = self::requested_days();
		$payload = KOZDONATE_Storage::export_payload( $days );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="koz-donate-stats-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$output = fopen( 'php://output', 'wb' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Could not open export stream.', 'koz-donate-stats' ) );
		}
		echo "\xEF\xBB\xBF";
		fputcsv( $output, array( 'section', 'dimension', 'metric', 'target', 'value' ), ';' );

		foreach ( $payload['daily'] as $row ) {
			foreach ( array( 'views', 'sessions', 'donate_clicks', 'payment_opens', 'copy_clicks', 'successes' ) as $metric ) {
				fputcsv( $output, array( 'daily', $row['stat_date'], $metric, '', $row[ $metric ] ), ';' );
			}
		}
		foreach ( $payload['contexts'] as $row ) {
			foreach ( array( 'views', 'sessions', 'engagements', 'successes', 'conversion_rate' ) as $metric ) {
				fputcsv( $output, array( 'context', $row['context_key'], $metric, '', $row[ $metric ] ), ';' );
			}
		}
		foreach ( $payload['languages'] as $row ) {
			foreach ( array( 'views', 'sessions', 'engagements', 'successes' ) as $metric ) {
				fputcsv( $output, array( 'language', $row['language'], $metric, '', $row[ $metric ] ), ';' );
			}
		}
		foreach ( $payload['targets'] as $row ) {
			fputcsv( $output, array( 'target', '', $row['event_type'], $row['target_key'], $row['event_count'] ), ';' );
		}
		exit;
	}

	private static function authorize_export( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export this data.', 'koz-donate-stats' ) );
		}
		check_admin_referer( $action );
	}

	private static function requested_days(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report range.
		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		return in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30;
	}

	public static function reset_stats(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete this data.', 'koz-donate-stats' ) );
		}
		check_admin_referer( KOZDONATE_Plugin::RESET_ACTION );

		$phrase = isset( $_POST['confirm_phrase'] )
			? sanitize_text_field( wp_unslash( $_POST['confirm_phrase'] ) )
			: '';

		if ( KOZDONATE_Plugin::RESET_PHRASE !== $phrase ) {
			wp_die( esc_html__( 'The confirmation phrase does not match.', 'koz-donate-stats' ) );
		}

		KOZDONATE_Storage::reset();
		wp_safe_redirect( self::tab_url( 'overview', 30 ) );
		exit;
	}
}
