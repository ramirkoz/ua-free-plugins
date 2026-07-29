<?php
namespace UAFree\DonateStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	public const MENU_SLUG = 'uafree-suite';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_' . Plugin::EXPORT_CSV_ACTION, array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_' . Plugin::EXPORT_JSON_ACTION, array( __CLASS__, 'export_json' ) );
		add_action( 'admin_post_' . Plugin::RESET_ACTION, array( __CLASS__, 'reset_stats' ) );
		add_action( 'admin_post_' . Plugin::ROTATE_SECRET_ACTION, array( __CLASS__, 'rotate_confirmation_secret' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( UAFREE_DONATE_STATS_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function register_settings(): void {
		register_setting(
			'uafree_donate_stats_group',
			Plugin::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Plugin::class, 'sanitize_settings' ),
				'default'           => Plugin::defaults(),
			)
		);
	}

	public static function menu(): void {
		global $admin_page_hooks;

		if ( empty( $admin_page_hooks[ self::MENU_SLUG ] ) ) {
			add_menu_page(
				'UA FREE',
				'UA FREE',
				'manage_options',
				self::MENU_SLUG,
				array( __CLASS__, 'suite_page' ),
				'none',
				58
			);
		}

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Donate Stats & Conversions', 'ua-free-donate-stats' ),
			__( 'Donate Stats', 'ua-free-donate-stats' ),
			'manage_options',
			Plugin::PAGE_SLUG,
			array( __CLASS__, 'page' )
		);
	}

	public static function action_links( array $links ): array {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . Plugin::PAGE_SLUG ) ) . '">' .
			esc_html__( 'Settings', 'ua-free-donate-stats' ) .
			'</a>'
		);
		return $links;
	}

	public static function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, Plugin::PAGE_SLUG ) && false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'ua-free-donate-stats-admin',
			UAFREE_DONATE_STATS_URL . 'assets/admin.css',
			array(),
			UAFREE_DONATE_STATS_VERSION
		);
		wp_enqueue_script(
			'ua-free-donate-stats-admin',
			UAFREE_DONATE_STATS_URL . 'assets/admin.js',
			array(),
			UAFREE_DONATE_STATS_VERSION,
			true
		);
	}

	public static function suite_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap uafree-ds">
			<h1><?php esc_html_e( 'UA FREE Plugin Suite', 'ua-free-donate-stats' ); ?></h1>
			<p><?php esc_html_e( 'Independent, privacy-conscious WordPress tools originally developed to solve real operational needs of a charitable foundation website.', 'ua-free-donate-stats' ); ?></p>
			<?php self::suite_table(); ?>
		</div>
		<?php
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		if ( ! in_array( $tab, array( 'overview', 'settings' ), true ) ) {
			$tab = 'overview';
		}
		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		if ( ! in_array( $days, array( 7, 30, 90 ), true ) ) {
			$days = 30;
		}
		?>
		<div class="wrap uafree-ds">
			<h1><?php esc_html_e( 'UA FREE Donate Stats & Conversions', 'ua-free-donate-stats' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Local aggregate donation journey statistics without IP addresses, referrers, fingerprinting, payment values or copied account details.', 'ua-free-donate-stats' ); ?>
			</p>

			<nav class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'overview' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::tab_url( 'overview', $days ) ); ?>"><?php esc_html_e( 'Overview', 'ua-free-donate-stats' ); ?></a>
				<a class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::tab_url( 'settings', $days ) ); ?>"><?php esc_html_e( 'Settings', 'ua-free-donate-stats' ); ?></a>
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
			Storage::summary( $days ),
			Plugin::settings()
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
		$tracked_targets = count( (array) ( $settings['tracked_page_ids'] ?? array() ) )
			+ count( (array) ( $settings['tracked_paths'] ?? array() ) );
		$configured = $tracked_targets > 0;

		$sessions = max( 0, (int) ( $summary['sessions'] ?? 0 ) );
		$views = max( 0, (int) ( $summary['views'] ?? 0 ) );
		$donate_clicks = max( 0, (int) ( $summary['donate_clicks'] ?? 0 ) );
		$payment_opens = max( 0, (int) ( $summary['payment_opens'] ?? 0 ) );
		$copy_clicks = max( 0, (int) ( $summary['copy_clicks'] ?? 0 ) );
		$successes = max( 0, (int) ( $summary['successes'] ?? 0 ) );
		$conversion_rate = max( 0.0, min( 100.0, (float) ( $summary['conversion_rate'] ?? 0.0 ) ) );
		$confirmation = Plugin::confirmation_status();
		$confirmation_ready = ! empty( $confirmation['ready'] )
			|| ! empty( $confirmation['client_marker_enabled'] );

		$attention = array();
		$actions = array();

		if ( ! $enabled ) {
			$attention[] = array(
				'level' => 'warning',
				'title' => __( 'Статистика донатів вимкнена', 'ua-free-donate-stats' ),
				'message' => __( 'Плагін встановлено, але події на сторінках не збираються.', 'ua-free-donate-stats' ),
			);
			$actions[] = array(
				'level' => 'warning',
				'title' => __( 'Увімкнути збір статистики', 'ua-free-donate-stats' ),
				'description' => __( 'У налаштуваннях виберіть сторінку донатів і ввімкніть локальний збір.', 'ua-free-donate-stats' ),
			);
		} elseif ( ! $configured ) {
			$attention[] = array(
				'level' => 'warning',
				'title' => __( 'Не вибрано сторінку донатів', 'ua-free-donate-stats' ),
				'message' => __( 'Плагін увімкнений, але не знає, на яких сторінках рахувати дії.', 'ua-free-donate-stats' ),
			);
			$actions[] = array(
				'level' => 'warning',
				'title' => __( 'Вибрати сторінку донатів', 'ua-free-donate-stats' ),
				'description' => __( 'Відкрийте налаштування та позначте сторінку або локальний шлях.', 'ua-free-donate-stats' ),
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
				'title' => __( 'Люди заходять, але не натискають підтримати', 'ua-free-donate-stats' ),
				'message' => __( 'Сторінку відвідують, але кнопка донату не дає зафіксованих натискань.', 'ua-free-donate-stats' ),
			);
			$actions[] = array(
				'level' => 'warning',
				'title' => __( 'Перевірити кнопку підтримки', 'ua-free-donate-stats' ),
				'description' => __( 'Перевірте, чи веде кнопка на один із налаштованих платіжних доменів.', 'ua-free-donate-stats' ),
			);
		}

		if ( $payment_opens > 0 && 0 === $successes ) {
			$attention[] = array(
				'level' => 'info',
				'title' => __( 'Оплату відкривають, але підтверджень ще немає', 'ua-free-donate-stats' ),
				'message' => $confirmation_ready
					? __( 'Підтвердження підключене, але успішних оплат за цей період ще не отримано.', 'ua-free-donate-stats' )
					: __( 'Кліки рахуються, але платіжний сервіс ще не надсилає підтвердження успішної оплати.', 'ua-free-donate-stats' ),
			);
			$actions[] = array(
				'level' => 'info',
				'title' => __( 'Перевірити підтвердження донату', 'ua-free-donate-stats' ),
				'description' => $confirmation_ready
					? __( 'Перевірте останній callback у платіжному сервісі.', 'ua-free-donate-stats' )
					: __( 'У вкладці «Налаштування» скопіюйте callback URL і секрет у платіжний сервіс або connector.', 'ua-free-donate-stats' ),
			);
		}

		if ( ! empty( $settings['data_layer_enabled'] ) && 'none' === (string) ( $settings['ad_account_mode'] ?? 'none' ) ) {
			$attention[] = array(
				'level' => 'warning',
				'title' => __( 'dataLayer увімкнений без рекламного режиму', 'ua-free-donate-stats' ),
				'message' => __( 'Події готуються для реклами, але тип Google-акаунта не вибрано.', 'ua-free-donate-stats' ),
			);
			$actions[] = array(
				'level' => 'warning',
				'title' => __( 'Вибрати Google Ad Grants або Google Ads', 'ua-free-donate-stats' ),
				'description' => __( 'Задайте режим у налаштуваннях або вимкніть dataLayer.', 'ua-free-donate-stats' ),
			);
		}

		if ( ! $enabled ) {
			$overall = 'disabled';
			$headline = __( 'Потрібно завершити налаштування', 'ua-free-donate-stats' );
		} elseif ( ! $configured ) {
			$overall = 'attention';
			$headline = __( 'Потрібно вибрати сторінку донатів', 'ua-free-donate-stats' );
		} elseif ( 0 === $sessions ) {
			$overall = 'waiting';
			$headline = __( 'Збір даних увімкнено. Чекаємо перші відвідування', 'ua-free-donate-stats' );
		} elseif ( $successes > 0 ) {
			$overall = 'good';
			$headline = __( 'Воронка донатів працює', 'ua-free-donate-stats' );
		} elseif ( $payment_opens > 0 ) {
			$overall = 'attention';
			$headline = __( 'Люди переходять до оплати', 'ua-free-donate-stats' );
		} else {
			$overall = 'good';
			$headline = __( 'Статистика збирається', 'ua-free-donate-stats' );
		}

		if ( empty( $actions ) ) {
			$actions[] = array(
				'level' => 'success',
				'title' => __( 'Нічого термінового', 'ua-free-donate-stats' ),
				'description' => __( 'Воронка працює. Перевіряйте результати після змін сторінки або реклами.', 'ua-free-donate-stats' ),
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
		<div class="uafree-ds-manager-card">
			<span><?php echo esc_html( $label ); ?></span>
			<strong><?php echo esc_html( $value ); ?></strong>
			<?php if ( '' !== $note ) : ?><small><?php echo esc_html( $note ); ?></small><?php endif; ?>
		</div>
		<?php
	}

	private static function manager_items( string $title, array $items, string $empty_message = '' ): void {
		?>
		<section class="uafree-ds-manager-section">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( empty( $items ) ) : ?>
				<p class="uafree-ds-empty"><?php echo esc_html( $empty_message ); ?></p>
			<?php else : ?>
				<div class="uafree-ds-manager-list">
					<?php foreach ( $items as $item ) : ?>
						<article class="uafree-ds-manager-item is-<?php echo esc_attr( (string) ( $item['level'] ?? 'info' ) ); ?>">
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
		$summary = Storage::summary( $days );
		$settings = Plugin::settings();
		$manager = self::manager_summary_from( $summary, $settings );
		$daily = Storage::daily_rows( $days );
		$targets = Storage::top_targets( $days );
		$contexts = Storage::context_rows( $days );
		$languages = Storage::language_rows( $days );
		?>
		<div class="uafree-ds-manager-hero is-<?php echo esc_attr( (string) $manager['overall'] ); ?>">
			<span><?php esc_html_e( 'Стан воронки донатів', 'ua-free-donate-stats' ); ?></span>
			<strong><?php echo esc_html( (string) $manager['headline'] ); ?></strong>
			<p><?php esc_html_e( 'Показуємо лише те, що допомагає прийняти рішення. Технічні таблиці заховані нижче.', 'ua-free-donate-stats' ); ?></p>
		</div>

		<div class="uafree-ds-manager-cards">
			<?php self::manager_card( __( 'Відвідали сторінку', 'ua-free-donate-stats' ), number_format_i18n( (int) $manager['sessions'] ), __( 'Унікальні денні сесії', 'ua-free-donate-stats' ) ); ?>
			<?php self::manager_card( __( 'Натиснули підтримати', 'ua-free-donate-stats' ), number_format_i18n( (int) $manager['donate_clicks'] ) ); ?>
			<?php self::manager_card( __( 'Відкрили оплату', 'ua-free-donate-stats' ), number_format_i18n( (int) $manager['payment_opens'] ) ); ?>
			<?php self::manager_card( __( 'Скопіювали реквізити', 'ua-free-donate-stats' ), number_format_i18n( (int) $manager['copy_clicks'] ), __( 'Тільки факт успішного копіювання', 'ua-free-donate-stats' ) ); ?>
			<?php self::manager_card( __( 'Підтверджені донати', 'ua-free-donate-stats' ), number_format_i18n( (int) $manager['successes'] ), __( 'Не бухгалтерський реєстр', 'ua-free-donate-stats' ) ); ?>
			<?php self::manager_card( __( 'Конверсія', 'ua-free-donate-stats' ), number_format_i18n( (float) $manager['conversion_rate'], 2 ) . '%' ); ?>
		</div>

		<section class="uafree-ds-card uafree-ds-explain">
			<h2><?php esc_html_e( 'Як читати ці два показники', 'ua-free-donate-stats' ); ?></h2>
			<p><strong><?php esc_html_e( 'Підтверджений донат', 'ua-free-donate-stats' ); ?>:</strong> <?php esc_html_e( 'платіжний сервіс або ваш server connector повідомив, що оплата справді успішна. Клік чи відкриття платіжної сторінки не вважається донатом.', 'ua-free-donate-stats' ); ?></p>
			<p><strong><?php esc_html_e( 'Конверсія', 'ua-free-donate-stats' ); ?>:</strong> <?php esc_html_e( 'підтверджені донати, поділені на унікальні денні сесії сторінки донатів.', 'ua-free-donate-stats' ); ?></p>
			<?php if ( ! empty( $manager['last_confirmation_at'] ) ) : ?>
				<p><?php echo esc_html( sprintf( __( 'Останнє підтвердження: %s', 'ua-free-donate-stats' ), (string) $manager['last_confirmation_at'] ) ); ?></p>
			<?php endif; ?>
		</section>

		<?php self::manager_items(
			__( 'Потрібна увага', 'ua-free-donate-stats' ),
			(array) $manager['attention'],
			__( 'Проблем, що потребують уваги, немає.', 'ua-free-donate-stats' )
		); ?>

		<?php self::manager_items(
			__( 'Що зробити', 'ua-free-donate-stats' ),
			(array) $manager['actions']
		); ?>

		<div class="uafree-ds-toolbar">
			<div>
				<?php foreach ( array( 7, 30, 90 ) as $range ) : ?>
					<a class="button <?php echo $days === $range ? 'button-primary' : ''; ?>" href="<?php echo esc_url( self::tab_url( 'overview', $range ) ); ?>">
						<?php echo esc_html( sprintf( __( '%d days', 'ua-free-donate-stats' ), $range ) ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>

		<details class="uafree-ds-technical">
			<summary><?php esc_html_e( 'Технічні дані та експорт', 'ua-free-donate-stats' ); ?></summary>
			<div class="uafree-ds-technical-inner">
				<p>
					<a class="button" href="<?php echo esc_url( self::export_url( Plugin::EXPORT_CSV_ACTION, $days ) ); ?>"><?php esc_html_e( 'Export CSV', 'ua-free-donate-stats' ); ?></a>
					<a class="button" href="<?php echo esc_url( self::export_url( Plugin::EXPORT_JSON_ACTION, $days ) ); ?>"><?php esc_html_e( 'Export JSON', 'ua-free-donate-stats' ); ?></a>
				</p>

				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'Підтверджений донат — це подія від сторінки або server integration. Це не фінансова виписка і не доказ зарахування коштів.', 'ua-free-donate-stats' ); ?></p>
				</div>

				<section class="uafree-ds-card">
					<h2><?php esc_html_e( 'Daily activity', 'ua-free-donate-stats' ); ?></h2>
					<div class="uafree-ds-table-wrap">
						<table class="widefat striped">
							<thead><tr>
								<th><?php esc_html_e( 'Date', 'ua-free-donate-stats' ); ?></th>
								<th><?php esc_html_e( 'Views', 'ua-free-donate-stats' ); ?></th>
								<th><?php esc_html_e( 'Sessions', 'ua-free-donate-stats' ); ?></th>
								<th><?php esc_html_e( 'Donate clicks', 'ua-free-donate-stats' ); ?></th>
								<th><?php esc_html_e( 'Payment opens', 'ua-free-donate-stats' ); ?></th>
								<th><?php esc_html_e( 'Copies', 'ua-free-donate-stats' ); ?></th>
								<th><?php esc_html_e( 'Successes', 'ua-free-donate-stats' ); ?></th>
							</tr></thead>
							<tbody>
								<?php if ( empty( $daily ) ) : ?><tr><td colspan="7"><?php esc_html_e( 'No data yet.', 'ua-free-donate-stats' ); ?></td></tr><?php endif; ?>
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

				<div class="uafree-ds-grid">
					<section class="uafree-ds-card">
						<h2><?php esc_html_e( 'Tracked contexts', 'ua-free-donate-stats' ); ?></h2>
						<table class="widefat striped">
							<thead><tr><th><?php esc_html_e( 'Context', 'ua-free-donate-stats' ); ?></th><th><?php esc_html_e( 'Sessions', 'ua-free-donate-stats' ); ?></th><th><?php esc_html_e( 'Engagements', 'ua-free-donate-stats' ); ?></th><th><?php esc_html_e( 'Successes', 'ua-free-donate-stats' ); ?></th></tr></thead>
							<tbody>
							<?php if ( empty( $contexts ) ) : ?><tr><td colspan="4"><?php esc_html_e( 'No data yet.', 'ua-free-donate-stats' ); ?></td></tr><?php endif; ?>
							<?php foreach ( $contexts as $row ) : ?>
								<tr><td><code><?php echo esc_html( $row['context_key'] ); ?></code></td><td><?php echo esc_html( number_format_i18n( $row['sessions'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['engagements'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['successes'] ) ); ?></td></tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</section>
					<section class="uafree-ds-card">
						<h2><?php esc_html_e( 'Languages', 'ua-free-donate-stats' ); ?></h2>
						<table class="widefat striped">
							<thead><tr><th><?php esc_html_e( 'Language', 'ua-free-donate-stats' ); ?></th><th><?php esc_html_e( 'Sessions', 'ua-free-donate-stats' ); ?></th><th><?php esc_html_e( 'Engagements', 'ua-free-donate-stats' ); ?></th><th><?php esc_html_e( 'Successes', 'ua-free-donate-stats' ); ?></th></tr></thead>
							<tbody>
							<?php if ( empty( $languages ) ) : ?><tr><td colspan="4"><?php esc_html_e( 'No data yet.', 'ua-free-donate-stats' ); ?></td></tr><?php endif; ?>
							<?php foreach ( $languages as $row ) : ?>
								<tr><td><code><?php echo esc_html( $row['language'] ); ?></code></td><td><?php echo esc_html( number_format_i18n( $row['sessions'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['engagements'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['successes'] ) ); ?></td></tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</section>
				</div>

				<section class="uafree-ds-card">
					<h2><?php esc_html_e( 'Top event targets', 'ua-free-donate-stats' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Event', 'ua-free-donate-stats' ); ?></th><th><?php esc_html_e( 'Target key', 'ua-free-donate-stats' ); ?></th><th><?php esc_html_e( 'Count', 'ua-free-donate-stats' ); ?></th></tr></thead>
						<tbody>
						<?php if ( empty( $targets ) ) : ?><tr><td colspan="3"><?php esc_html_e( 'No data yet.', 'ua-free-donate-stats' ); ?></td></tr><?php endif; ?>
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
		$settings = Plugin::settings();
		$items = self::trackable_content();
		$tracked_targets = count( (array) $settings['tracked_page_ids'] ) + count( (array) $settings['tracked_paths'] );
		$ready = ! empty( $settings['enabled'] ) && $tracked_targets > 0;
		?>
		<section class="uafree-ds-card uafree-ds-setup-status <?php echo $ready ? 'is-ready' : 'is-attention'; ?>">
			<h2><?php echo esc_html( $ready ? __( 'Збір статистики готовий', 'ua-free-donate-stats' ) : __( 'Потрібно завершити швидке налаштування', 'ua-free-donate-stats' ) ); ?></h2>
			<ol>
				<li><?php echo ! empty( $settings['enabled'] ) ? '✓' : '○'; ?> <?php esc_html_e( 'Увімкнути локальний збір статистики', 'ua-free-donate-stats' ); ?></li>
				<li><?php echo $tracked_targets > 0 ? '✓' : '○'; ?> <?php esc_html_e( 'Вибрати сторінку донатів або локальний шлях', 'ua-free-donate-stats' ); ?></li>
				<li><?php echo ! empty( $settings['payment_hosts'] ) ? '✓' : '○'; ?> <?php esc_html_e( 'Додати платіжні домени, якщо використовуються зовнішні сервіси', 'ua-free-donate-stats' ); ?></li>
				<li><?php echo ! empty( Plugin::confirmation_status()['ready'] ) || ! empty( $settings['allow_client_success'] ) ? '✓' : '○'; ?> <?php esc_html_e( 'Підключити підтвердження успішного донату', 'ua-free-donate-stats' ); ?></li>
			</ol>
		</section>
		<form method="post" action="options.php" class="uafree-ds-settings">
			<?php settings_fields( 'uafree_donate_stats_group' ); ?>

			<section class="uafree-ds-card">
				<h2><?php esc_html_e( '1. Увімкнення', 'ua-free-donate-stats' ); ?></h2>
				<label class="uafree-ds-check">
					<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[enabled]" value="1" <?php checked( 1, $settings['enabled'] ); ?>>
					<span><?php esc_html_e( 'Увімкнути локальний збір статистики', 'ua-free-donate-stats' ); ?></span>
				</label>
				<label class="uafree-ds-check">
					<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[exclude_admins]" value="1" <?php checked( 1, $settings['exclude_admins'] ); ?>>
					<span><?php esc_html_e( 'Не рахувати дії адміністраторів WordPress', 'ua-free-donate-stats' ); ?></span>
				</label>
				<p class="description"><?php esc_html_e( 'Збір почнеться після вибору хоча б однієї сторінки або локального шляху.', 'ua-free-donate-stats' ); ?></p>
			</section>

			<section class="uafree-ds-card">
				<h2><?php esc_html_e( '2. Де рахувати', 'ua-free-donate-stats' ); ?></h2>
				<p><?php esc_html_e( 'Позначте сторінку, де люди можуть підтримати вас або перейти до оплати.', 'ua-free-donate-stats' ); ?></p>
				<div class="uafree-ds-scroll-list">
					<?php if ( empty( $items ) ) : ?>
						<p><?php esc_html_e( 'No published public content was found.', 'ua-free-donate-stats' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $items as $item ) : ?>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[tracked_page_ids][]" value="<?php echo esc_attr( (string) $item['id'] ); ?>" <?php checked( in_array( $item['id'], $settings['tracked_page_ids'], true ) ); ?>>
							<?php echo esc_html( $item['title'] ); ?>
							<code><?php echo esc_html( $item['type'] . ':' . $item['id'] ); ?></code>
						</label>
					<?php endforeach; ?>
				</div>

				<label class="uafree-ds-field">
					<span><?php esc_html_e( 'Додаткові локальні шляхи', 'ua-free-donate-stats' ); ?></span>
					<textarea name="<?php echo esc_attr( Plugin::OPTION ); ?>[tracked_paths]" rows="6" class="large-text code"><?php echo esc_textarea( implode( "\n", $settings['tracked_paths'] ) ); ?></textarea>
				</label>
				<p class="description"><?php esc_html_e( 'Один шлях у рядку. Зазвичай це поле можна залишити порожнім.', 'ua-free-donate-stats' ); ?></p>
			</section>

			<section class="uafree-ds-card uafree-ds-confirmation">
				<h2><?php esc_html_e( '3. Підтверджені донати', 'ua-free-donate-stats' ); ?></h2>
				<p><?php esc_html_e( 'Рекомендовано: після успішної оплати платіжний сервіс або connector надсилає один підписаний callback. Плагін не отримує суму, ім’я, email чи реквізити.', 'ua-free-donate-stats' ); ?></p>

				<label class="uafree-ds-field">
					<span><?php esc_html_e( 'Спосіб підтвердження', 'ua-free-donate-stats' ); ?></span>
					<select name="<?php echo esc_attr( Plugin::OPTION ); ?>[confirmation_mode]">
						<option value="webhook" <?php selected( 'webhook', $settings['confirmation_mode'] ); ?>><?php esc_html_e( 'Server callback — рекомендовано', 'ua-free-donate-stats' ); ?></option>
						<option value="client_marker" <?php selected( 'client_marker', $settings['confirmation_mode'] ); ?>><?php esc_html_e( 'Позначка на сторінці успіху — менш надійно', 'ua-free-donate-stats' ); ?></option>
						<option value="none" <?php selected( 'none', $settings['confirmation_mode'] ); ?>><?php esc_html_e( 'Не рахувати підтверджені донати', 'ua-free-donate-stats' ); ?></option>
					</select>
				</label>

				<div class="uafree-ds-copy-row">
					<label class="uafree-ds-field">
						<span><?php esc_html_e( 'Callback URL', 'ua-free-donate-stats' ); ?></span>
						<input id="uafree-confirm-url" type="text" readonly class="large-text code" value="<?php echo esc_attr( Plugin::confirmation_callback_url() ); ?>">
					</label>
					<button type="button" class="button" data-uafree-copy-value="#uafree-confirm-url"><?php esc_html_e( 'Копіювати URL', 'ua-free-donate-stats' ); ?></button>
				</div>

				<div class="uafree-ds-copy-row">
					<label class="uafree-ds-field">
						<span><?php esc_html_e( 'Секрет підпису', 'ua-free-donate-stats' ); ?></span>
						<input id="uafree-confirm-secret" type="password" readonly class="large-text code" value="<?php echo esc_attr( $settings['confirmation_secret'] ); ?>">
					</label>
					<button type="button" class="button" data-uafree-copy-value="#uafree-confirm-secret"><?php esc_html_e( 'Копіювати секрет', 'ua-free-donate-stats' ); ?></button>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', Plugin::ROTATE_SECRET_ACTION, admin_url( 'admin-post.php' ) ), Plugin::ROTATE_SECRET_ACTION ) ); ?>"><?php esc_html_e( 'Створити новий секрет', 'ua-free-donate-stats' ); ?></a>
				</div>

				<ol>
					<li><?php esc_html_e( 'Скопіюйте Callback URL.', 'ua-free-donate-stats' ); ?></li>
					<li><?php esc_html_e( 'Скопіюйте секрет.', 'ua-free-donate-stats' ); ?></li>
					<li><?php esc_html_e( 'Додайте їх у платіжний сервіс або connector, який викликається тільки після успішної оплати.', 'ua-free-donate-stats' ); ?></li>
				</ol>

				<details>
					<summary><?php esc_html_e( 'Технічний формат callback', 'ua-free-donate-stats' ); ?></summary>
					<p><?php esc_html_e( 'POST JSON із заголовком X-UAFree-Signature = HMAC-SHA256(raw body, secret).', 'ua-free-donate-stats' ); ?></p>
					<pre>{"event":"donation_success","reference":"unique-provider-reference","provider":"payment","language":"uk"}</pre>
					<p><?php esc_html_e( 'reference використовується лише для захисту від повторного зарахування і зберігається тільки як HMAC-хеш.', 'ua-free-donate-stats' ); ?></p>
				</details>

				<label class="uafree-ds-check">
					<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[allow_client_success]" value="1" <?php checked( 1, $settings['allow_client_success'] ); ?>>
					<span><?php esc_html_e( 'Дозволити позначку успіху на сторінці, якщо обрано менш надійний режим', 'ua-free-donate-stats' ); ?></span>
				</label>
			</section>

			<details class="uafree-ds-card uafree-ds-technical-settings">
				<summary><strong><?php esc_html_e( 'Додаткові технічні налаштування кнопок', 'ua-free-donate-stats' ); ?></strong></summary>
				<div class="uafree-ds-technical-settings-inner">
				<?php self::selector_field( 'donate_selector', __( 'Donate action selector', 'ua-free-donate-stats' ), $settings ); ?>
				<?php self::selector_field( 'payment_selector', __( 'Payment link selector', 'ua-free-donate-stats' ), $settings ); ?>
				<?php self::selector_field( 'copy_selector', __( 'Copy action selector', 'ua-free-donate-stats' ), $settings ); ?>
				<?php self::selector_field( 'success_selector', __( 'Reported success marker selector', 'ua-free-donate-stats' ), $settings ); ?>

				<label class="uafree-ds-field">
					<span><?php esc_html_e( 'Allowed payment hostnames', 'ua-free-donate-stats' ); ?></span>
					<textarea name="<?php echo esc_attr( Plugin::OPTION ); ?>[payment_hosts]" rows="5" class="large-text code"><?php echo esc_textarea( implode( "\n", $settings['payment_hosts'] ) ); ?></textarea>
				</label>
				<p class="description"><?php esc_html_e( 'One hostname per line. The plugin stores only the hostname, never a full external URL.', 'ua-free-donate-stats' ); ?></p>

				<p class="description"><?php esc_html_e( 'CSS-селектори потрібні лише тоді, коли автоматичне розпізнавання вашої теми не спрацювало.', 'ua-free-donate-stats' ); ?></p>
				</div>
			</details>

			<section class="uafree-ds-card">
				<h2><?php esc_html_e( '4. Google Ads — необов’язково', 'ua-free-donate-stats' ); ?></h2>
				<label class="uafree-ds-field">
					<span><?php esc_html_e( 'Тип рекламного акаунта', 'ua-free-donate-stats' ); ?></span>
					<select name="<?php echo esc_attr( Plugin::OPTION ); ?>[ad_account_mode]">
						<option value="none" <?php selected( 'none', $settings['ad_account_mode'] ); ?>><?php esc_html_e( 'Google Ads не використовується', 'ua-free-donate-stats' ); ?></option>
						<option value="ad_grants" <?php selected( 'ad_grants', $settings['ad_account_mode'] ); ?>><?php esc_html_e( 'Google Ad Grants', 'ua-free-donate-stats' ); ?></option>
						<option value="google_ads" <?php selected( 'google_ads', $settings['ad_account_mode'] ); ?>><?php esc_html_e( 'Standard Google Ads', 'ua-free-donate-stats' ); ?></option>
					</select>
				</label>

				<label class="uafree-ds-check">
					<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[data_layer_enabled]" value="1" <?php checked( 1, $settings['data_layer_enabled'] ); ?>>
					<span><?php esc_html_e( 'Передавати безпечні події в dataLayer', 'ua-free-donate-stats' ); ?></span>
				</label>

				<label class="uafree-ds-field">
					<span><?php esc_html_e( 'dataLayer event name', 'ua-free-donate-stats' ); ?></span>
					<input type="text" name="<?php echo esc_attr( Plugin::OPTION ); ?>[data_layer_event]" value="<?php echo esc_attr( $settings['data_layer_event'] ); ?>" class="regular-text code">
				</label>

				<label class="uafree-ds-field">
					<span><?php esc_html_e( 'Consent gate', 'ua-free-donate-stats' ); ?></span>
					<select name="<?php echo esc_attr( Plugin::OPTION ); ?>[consent_gate]">
						<option value="none" <?php selected( 'none', $settings['consent_gate'] ); ?>><?php esc_html_e( 'Do not gate events', 'ua-free-donate-stats' ); ?></option>
						<option value="external_only" <?php selected( 'external_only', $settings['consent_gate'] ); ?>><?php esc_html_e( 'Gate dataLayer only', 'ua-free-donate-stats' ); ?></option>
						<option value="all" <?php selected( 'all', $settings['consent_gate'] ); ?>><?php esc_html_e( 'Gate local and dataLayer events', 'ua-free-donate-stats' ); ?></option>
					</select>
				</label>

				<label class="uafree-ds-field">
					<span><?php esc_html_e( 'Consent category', 'ua-free-donate-stats' ); ?></span>
					<input type="text" name="<?php echo esc_attr( Plugin::OPTION ); ?>[consent_category]" value="<?php echo esc_attr( $settings['consent_category'] ); ?>" class="regular-text code">
				</label>
				<p class="description"><?php esc_html_e( 'The future UA FREE Consent Manager can answer whether this category is allowed. Until a compatible manager is present, gated events remain blocked.', 'ua-free-donate-stats' ); ?></p>
			</section>

			<section class="uafree-ds-card">
				<h2><?php esc_html_e( '5. Зберігання', 'ua-free-donate-stats' ); ?></h2>
				<label class="uafree-ds-field">
					<span><?php esc_html_e( 'Скільки днів зберігати статистику', 'ua-free-donate-stats' ); ?></span>
					<input type="number" min="30" max="730" name="<?php echo esc_attr( Plugin::OPTION ); ?>[retention_days]" value="<?php echo esc_attr( (string) $settings['retention_days'] ); ?>">
				</label>
				<label class="uafree-ds-field">
					<span><?php esc_html_e( 'Коли вважати візит завершеним, хвилин', 'ua-free-donate-stats' ); ?></span>
					<input type="number" min="5" max="120" name="<?php echo esc_attr( Plugin::OPTION ); ?>[session_minutes]" value="<?php echo esc_attr( (string) $settings['session_minutes'] ); ?>">
				</label>
				<p class="description"><?php esc_html_e( 'The browser identifier is kept in sessionStorage and the database receives only a daily HMAC hash.', 'ua-free-donate-stats' ); ?></p>
			</section>

			<?php submit_button( __( 'Зберегти налаштування', 'ua-free-donate-stats' ) ); ?>
		</form>

		<section class="uafree-ds-card uafree-ds-danger">
			<h2><?php esc_html_e( 'Delete aggregate statistics', 'ua-free-donate-stats' ); ?></h2>
			<p><?php esc_html_e( 'This truncates only the two statistics tables. Settings and plugin files remain.', 'ua-free-donate-stats' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( Plugin::RESET_ACTION ); ?>">
				<?php wp_nonce_field( Plugin::RESET_ACTION ); ?>
				<label class="uafree-ds-field">
					<span><?php echo esc_html( sprintf( __( 'Type %s to confirm', 'ua-free-donate-stats' ), Plugin::RESET_PHRASE ) ); ?></span>
					<input type="text" name="confirm_phrase" class="regular-text" autocomplete="off">
				</label>
				<button class="button" type="submit"><?php esc_html_e( 'Delete all statistics', 'ua-free-donate-stats' ); ?></button>
			</form>
		</section>
		<?php
	}

	private static function suite_table(): void {
		$items = Suite::status();
		?>
		<section class="uafree-ds-card">
			<h2><?php esc_html_e( 'UA FREE Plugin Suite', 'ua-free-donate-stats' ); ?></h2>
			<div class="uafree-ds-suite">
				<?php foreach ( $items as $item ) : ?>
					<article>
						<h3><?php echo esc_html( $item['name'] ); ?></h3>
						<p><?php echo esc_html( $item['description'] ); ?></p>
						<p>
							<?php if ( $item['active'] ) : ?>
								<span class="uafree-ds-status active"><?php esc_html_e( 'Active', 'ua-free-donate-stats' ); ?></span>
							<?php elseif ( $item['installed'] ) : ?>
								<span class="uafree-ds-status installed"><?php esc_html_e( 'Installed', 'ua-free-donate-stats' ); ?></span>
							<?php else : ?>
								<span class="uafree-ds-status"><?php esc_html_e( 'Available separately', 'ua-free-donate-stats' ); ?></span>
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
		<label class="uafree-ds-field">
			<span><?php echo esc_html( $label ); ?></span>
			<input type="text" name="<?php echo esc_attr( Plugin::OPTION . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $settings[ $key ] ); ?>" class="large-text code">
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
				'title' => get_the_title( $post ) ?: __( '(no title)', 'ua-free-donate-stats' ),
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
		<div class="uafree-ds-metric">
			<span><?php echo esc_html( $label ); ?></span>
			<strong><?php echo esc_html( $formatted . $suffix ); ?></strong>
		</div>
		<?php
	}

	private static function event_label( string $event_type ): string {
		$labels = array(
			'page_view'        => __( 'Page view', 'ua-free-donate-stats' ),
			'donate_click'     => __( 'Donate click', 'ua-free-donate-stats' ),
			'payment_open'     => __( 'Payment open', 'ua-free-donate-stats' ),
			'external_click'   => __( 'Legacy external click', 'ua-free-donate-stats' ),
			'copy_click'       => __( 'Copy click', 'ua-free-donate-stats' ),
			'donation_success' => __( 'Reported success', 'ua-free-donate-stats' ),
		);
		return $labels[ $event_type ] ?? $event_type;
	}

	private static function tab_url( string $tab, int $days ): string {
		return add_query_arg(
			array(
				'page' => Plugin::PAGE_SLUG,
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
			wp_die( esc_html__( 'You do not have permission to rotate this secret.', 'ua-free-donate-stats' ) );
		}
		check_admin_referer( Plugin::ROTATE_SECRET_ACTION );
		Plugin::rotate_confirmation_secret();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => Plugin::PAGE_SLUG,
					'tab' => 'settings',
					'secret-rotated' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}


	public static function export_json(): void {
		self::authorize_export( Plugin::EXPORT_JSON_ACTION );
		$days = self::requested_days();

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ua-free-donate-stats-' . gmdate( 'Ymd-His' ) . '.json"' );
		echo wp_json_encode( Storage::export_payload( $days ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	public static function export_csv(): void {
		self::authorize_export( Plugin::EXPORT_CSV_ACTION );
		$days = self::requested_days();
		$payload = Storage::export_payload( $days );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ua-free-donate-stats-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$output = fopen( 'php://output', 'wb' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Could not open export stream.', 'ua-free-donate-stats' ) );
		}
		fwrite( $output, "\xEF\xBB\xBF" );
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
		fclose( $output );
		exit;
	}

	private static function authorize_export( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export this data.', 'ua-free-donate-stats' ) );
		}
		check_admin_referer( $action );
	}

	private static function requested_days(): int {
		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		return in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30;
	}

	public static function reset_stats(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete this data.', 'ua-free-donate-stats' ) );
		}
		check_admin_referer( Plugin::RESET_ACTION );

		$phrase = isset( $_POST['confirm_phrase'] )
			? sanitize_text_field( wp_unslash( $_POST['confirm_phrase'] ) )
			: '';

		if ( Plugin::RESET_PHRASE !== $phrase ) {
			wp_die( esc_html__( 'The confirmation phrase does not match.', 'ua-free-donate-stats' ) );
		}

		Storage::reset();
		wp_safe_redirect( self::tab_url( 'overview', 30 ) );
		exit;
	}
}
