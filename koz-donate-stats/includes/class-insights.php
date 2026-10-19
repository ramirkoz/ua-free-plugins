<?php
/**
 * Report views (funnel, daily chart, payment services, amounts), the dashboard
 * widget and the Site Health test.
 *
 * @package KOZ_Donate_Stats
 */

namespace ramirkz\kozdonate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZDONATE_Insights {
	public static function init(): void {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard' ) );
		add_filter( 'site_status_tests', array( __CLASS__, 'tests' ) );
	}

	private static function change( int $current, int $previous ): string {
		if ( 0 === $previous ) {
			return $current > 0 ? '+' . number_format_i18n( $current ) : '—';
		}
		$percent = ( ( $current - $previous ) / $previous ) * 100;
		return ( $percent >= 0 ? '+' : '' ) . number_format_i18n( $percent, 0 ) . '%';
	}

	/** Funnel with the change against the previous period of the same length. */
	public static function render_funnel( int $days ): void {
		$now  = KOZDONATE_Storage::period_totals( $days );
		$prev = KOZDONATE_Storage::period_totals( $days, $days );
		$steps = array(
			'sessions'      => __( 'Visited the page', 'koz-donate-stats' ),
			'donate_clicks' => __( 'Clicked support', 'koz-donate-stats' ),
			'payment_opens' => __( 'Opened payment', 'koz-donate-stats' ),
			'successes'     => __( 'Confirmed donations', 'koz-donate-stats' ),
		);
		$top = max( 1, (int) $now['sessions'] );
		?>
		<section class="kozdonate-card kozdonate-funnel">
			<h2><?php esc_html_e( 'Donation funnel', 'koz-donate-stats' ); ?></h2>
			<p class="description">
				<?php
				/* translators: %d: number of days in the comparison period. */
				echo esc_html( sprintf( __( 'Compared with the previous %d days.', 'koz-donate-stats' ), $days ) );
				?>
			</p>
			<?php foreach ( $steps as $key => $label ) :
				$value = (int) $now[ $key ];
				$width = min( 100, max( 2, (int) round( $value / $top * 100 ) ) );
				?>
				<div class="kozdonate-funnel-row">
					<span class="kozdonate-funnel-label"><?php echo esc_html( $label ); ?></span>
					<span class="kozdonate-funnel-bar"><span style="width:<?php echo esc_attr( (string) $width ); ?>%"></span></span>
					<strong><?php echo esc_html( number_format_i18n( $value ) ); ?></strong>
					<span class="kozdonate-funnel-change"><?php echo esc_html( self::change( $value, (int) $prev[ $key ] ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</section>
		<?php
	}

	/** Daily sessions, payment openings and confirmations as an inline SVG bar chart. */
	public static function render_chart( int $days ): void {
		$rows   = array_reverse( KOZDONATE_Storage::daily_rows( $days ) );
		$by_day = array();
		foreach ( $rows as $row ) {
			$by_day[ $row['stat_date'] ] = $row;
		}
		$series = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date     = wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
			$series[] = array(
				'date'     => $date,
				'sessions' => (int) ( $by_day[ $date ]['sessions'] ?? 0 ),
				'opens'    => (int) ( $by_day[ $date ]['payment_opens'] ?? 0 ),
				'success'  => (int) ( $by_day[ $date ]['successes'] ?? 0 ),
			);
		}
		$max   = max( 1, ...array_map( static fn( array $d ): int => max( $d['sessions'], $d['opens'] ), $series ) );
		$w     = 720;
		$h     = 180;
		$slot  = $w / count( $series );
		$bar   = max( 1, $slot / 2 - 1 );
		?>
		<section class="kozdonate-card">
			<h2><?php esc_html_e( 'Daily activity', 'koz-donate-stats' ); ?></h2>
			<svg class="kozdonate-chart" viewBox="0 0 <?php echo esc_attr( (string) $w ); ?> <?php echo esc_attr( (string) ( $h + 20 ) ); ?>" role="img" aria-label="<?php esc_attr_e( 'Sessions and payment openings per day', 'koz-donate-stats' ); ?>">
				<?php foreach ( $series as $index => $day ) :
					$x  = $index * $slot;
					$hs = $day['sessions'] / $max * $h;
					$ho = $day['opens'] / $max * $h;
					?>
					<g>
						<title><?php echo esc_html( $day['date'] . ': ' . $day['sessions'] . ' / ' . $day['opens'] . ' / ' . $day['success'] ); ?></title>
						<rect x="<?php echo esc_attr( (string) round( $x, 1 ) ); ?>" y="<?php echo esc_attr( (string) round( $h - $hs, 1 ) ); ?>" width="<?php echo esc_attr( (string) round( $bar, 1 ) ); ?>" height="<?php echo esc_attr( (string) round( $hs, 1 ) ); ?>" class="kozdonate-chart-sessions"></rect>
						<rect x="<?php echo esc_attr( (string) round( $x + $bar + 1, 1 ) ); ?>" y="<?php echo esc_attr( (string) round( $h - $ho, 1 ) ); ?>" width="<?php echo esc_attr( (string) round( $bar, 1 ) ); ?>" height="<?php echo esc_attr( (string) round( $ho, 1 ) ); ?>" class="kozdonate-chart-opens"></rect>
						<?php if ( $day['success'] > 0 ) : ?><circle cx="<?php echo esc_attr( (string) round( $x + $slot / 2, 1 ) ); ?>" cy="<?php echo esc_attr( (string) ( $h + 10 ) ); ?>" r="4" class="kozdonate-chart-success"></circle><?php endif; ?>
					</g>
				<?php endforeach; ?>
			</svg>
			<p class="kozdonate-legend">
				<span class="kozdonate-chart-sessions"></span> <?php esc_html_e( 'Sessions', 'koz-donate-stats' ); ?>
				<span class="kozdonate-chart-opens"></span> <?php esc_html_e( 'Payment opens', 'koz-donate-stats' ); ?>
				<span class="kozdonate-chart-success"></span> <?php esc_html_e( 'Day with confirmed donations', 'koz-donate-stats' ); ?>
			</p>
		</section>
		<?php
	}

	public static function render_providers( int $days ): void {
		$providers = KOZDONATE_Storage::provider_rows( $days );
		$amounts   = ! empty( KOZDONATE_Plugin::settings()['store_amounts'] ) ? KOZDONATE_Storage::amount_totals( $days ) : array();
		?>
		<div class="kozdonate-grid">
			<section class="kozdonate-card">
				<h2><?php esc_html_e( 'Payment services', 'koz-donate-stats' ); ?></h2>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Service', 'koz-donate-stats' ); ?></th><th><?php esc_html_e( 'Payment opens', 'koz-donate-stats' ); ?></th><th><?php esc_html_e( 'Confirmed donations', 'koz-donate-stats' ); ?></th></tr></thead>
					<tbody>
					<?php if ( empty( $providers ) ) : ?><tr><td colspan="3"><?php esc_html_e( 'No data yet.', 'koz-donate-stats' ); ?></td></tr><?php endif; ?>
					<?php foreach ( $providers as $label => $row ) : ?>
						<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( number_format_i18n( $row['opens'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['successes'] ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</section>
			<?php if ( ! empty( KOZDONATE_Plugin::settings()['store_amounts'] ) ) : ?>
				<section class="kozdonate-card">
					<h2><?php esc_html_e( 'Confirmed amounts', 'koz-donate-stats' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Currency', 'koz-donate-stats' ); ?></th><th><?php esc_html_e( 'Total', 'koz-donate-stats' ); ?></th><th><?php esc_html_e( 'Donations', 'koz-donate-stats' ); ?></th></tr></thead>
						<tbody>
						<?php if ( empty( $amounts ) ) : ?><tr><td colspan="3"><?php esc_html_e( 'No confirmed amounts yet.', 'koz-donate-stats' ); ?></td></tr><?php endif; ?>
						<?php foreach ( $amounts as $row ) : ?>
							<tr><td><?php echo esc_html( $row['currency'] ); ?></td><td><?php echo esc_html( number_format_i18n( $row['total'], 2 ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['count'] ) ); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description"><?php esc_html_e( 'Amounts reported by payment services. Not an accounting ledger.', 'koz-donate-stats' ); ?></p>
				</section>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function dashboard(): void {
		if ( current_user_can( 'manage_options' ) && ! empty( KOZDONATE_Plugin::settings()['enabled'] ) ) {
			wp_add_dashboard_widget( 'kozdonate_dashboard', __( 'Donations, last 7 days', 'koz-donate-stats' ), array( __CLASS__, 'render_dashboard' ) );
		}
	}

	public static function render_dashboard(): void {
		$now  = KOZDONATE_Storage::period_totals( 7 );
		$prev = KOZDONATE_Storage::period_totals( 7, 7 );
		$rows = array(
			'sessions'      => __( 'Visited the page', 'koz-donate-stats' ),
			'donate_clicks' => __( 'Clicked support', 'koz-donate-stats' ),
			'payment_opens' => __( 'Opened payment', 'koz-donate-stats' ),
			'successes'     => __( 'Confirmed donations', 'koz-donate-stats' ),
		);
		echo '<table class="widefat striped"><tbody>';
		foreach ( $rows as $key => $label ) {
			echo '<tr><td>' . esc_html( $label ) . '</td><td style="text-align:right"><strong>' . esc_html( number_format_i18n( (int) $now[ $key ] ) ) . '</strong></td><td style="text-align:right;color:#646970">' . esc_html( self::change( (int) $now[ $key ], (int) $prev[ $key ] ) ) . '</td></tr>';
		}
		echo '</tbody></table><p><a href="' . esc_url( admin_url( 'admin.php?page=' . KOZDONATE_Plugin::PAGE_SLUG ) ) . '">' . esc_html__( 'Open Donate Stats', 'koz-donate-stats' ) . '</a></p>';
	}

	public static function tests( array $tests ): array {
		$tests['direct']['kozdonate_tracking'] = array(
			'label' => __( 'Donation statistics', 'koz-donate-stats' ),
			'test'  => array( __CLASS__, 'test' ),
		);
		return $tests;
	}

	public static function test(): array {
		$settings = KOZDONATE_Plugin::settings();
		$status   = KOZDONATE_Tracker::public_status();
		$messages = array();
		$result   = 'good';
		if ( ! empty( $settings['enabled'] ) && empty( $status['configured'] ) ) {
			$result     = 'recommended';
			$messages[] = __( 'Statistics are enabled, but no donation page is selected.', 'koz-donate-stats' );
		}
		if ( ! empty( $status['tracking_ready'] ) && 0 === KOZDONATE_Storage::recent_event_count( 7 ) ) {
			$result     = 'recommended';
			$messages[] = __( 'No donation-page events were recorded in the last 7 days. Check that the selected page is the one visitors use.', 'koz-donate-stats' );
		}
		$week = KOZDONATE_Storage::period_totals( 30 );
		if ( $week['payment_opens'] > 0 && 0 === $week['successes'] && empty( $settings['liqpay_enabled'] ) && empty( $settings['paypal_enabled'] ) && 'webhook' !== $settings['confirmation_mode'] ) {
			$result     = 'recommended';
			$messages[] = __( 'Visitors open payment pages, but no confirmations are connected, so donations cannot be counted. Connect LiqPay, PayPal or a signed callback.', 'koz-donate-stats' );
		}
		$description = '';
		foreach ( empty( $messages ) ? array( __( 'Donation statistics are collected and confirmations are connected.', 'koz-donate-stats' ) ) : $messages as $message ) {
			$description .= '<p>' . esc_html( $message ) . '</p>';
		}
		return array(
			'label'       => 'good' === $result ? __( 'Donation statistics are working', 'koz-donate-stats' ) : __( 'Donation statistics need attention', 'koz-donate-stats' ),
			'status'      => empty( $settings['enabled'] ) ? 'good' : $result,
			'badge'       => array( 'label' => __( 'Donations', 'koz-donate-stats' ), 'color' => 'blue' ),
			'description' => empty( $settings['enabled'] ) ? '<p>' . esc_html__( 'Donation statistics are turned off.', 'koz-donate-stats' ) . '</p>' : $description,
			'actions'     => sprintf( '<p><a href="%1$s">%2$s</a></p>', esc_url( admin_url( 'admin.php?page=' . KOZDONATE_Plugin::PAGE_SLUG . '&tab=settings' ) ), esc_html__( 'Open Donate Stats settings', 'koz-donate-stats' ) ),
			'test'        => 'kozdonate_tracking',
		);
	}
}
