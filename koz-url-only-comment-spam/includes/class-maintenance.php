<?php
/**
 * Aggregate daily statistics, the dashboard widget and optional spam cleanup.
 *
 * Statistics are counts per day and rule only. No comment, URL or visitor data is stored.
 *
 * @package KOZ_URL_Only_Comment_Spam
 */

declare(strict_types=1);

namespace ramirkz\kozurlspam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Maintenance {
	public const DAILY_OPTION = 'kozurlspam_daily';
	public const CLEANUP_HOOK = 'kozurlspam_cleanup';
	private const KEEP_DAYS = 90;
	private const CLEANUP_BATCH = 200;

	public static function boot(): void {
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'run_cleanup' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_dashboard_widget' ) );
		add_action( 'update_option_' . Plugin::SETTINGS_OPTION, array( __CLASS__, 'sync_schedule' ), 10, 0 );
		add_action( 'add_option_' . Plugin::SETTINGS_OPTION, array( __CLASS__, 'sync_schedule' ), 10, 0 );
	}

	/**
	 * @return array<string,string> Rule key => translated label.
	 */
	public static function rule_labels(): array {
		return array(
			'url_only'      => __( 'Only links', 'koz-url-only-comment-spam' ),
			'low_text'      => __( 'Links with very little text', 'koz-url-only-comment-spam' ),
			'author_url'    => __( 'Website field with very little text', 'koz-url-only-comment-spam' ),
			'honeypot'      => __( 'Hidden trap field filled', 'koz-url-only-comment-spam' ),
			'too_fast'      => __( 'Submitted too fast', 'koz-url-only-comment-spam' ),
			'missing_token' => __( 'Comment form token missing', 'koz-url-only-comment-spam' ),
		);
	}

	public static function record( string $rule ): void {
		$daily = get_option( self::DAILY_OPTION, array() );
		$daily = is_array( $daily ) ? $daily : array();
		$day = wp_date( 'Y-m-d' );

		$daily[ $day ][ $rule ] = (int) ( $daily[ $day ][ $rule ] ?? 0 ) + 1;

		ksort( $daily );
		$daily = array_slice( $daily, -self::KEEP_DAYS, null, true );
		update_option( self::DAILY_OPTION, $daily, false );
	}

	/**
	 * Totals per rule for the last $days days, including today.
	 *
	 * @return array<string,int>
	 */
	public static function totals( int $days ): array {
		$daily = get_option( self::DAILY_OPTION, array() );
		$daily = is_array( $daily ) ? $daily : array();
		$since = wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );

		$totals = array_fill_keys( array_keys( self::rule_labels() ), 0 );
		foreach ( $daily as $day => $rules ) {
			if ( (string) $day < $since || ! is_array( $rules ) ) {
				continue;
			}
			foreach ( $rules as $rule => $count ) {
				if ( isset( $totals[ $rule ] ) ) {
					$totals[ $rule ] += (int) $count;
				}
			}
		}

		return $totals;
	}

	public static function register_dashboard_widget(): void {
		if ( ! current_user_can( 'moderate_comments' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'kozurlspam_dashboard',
			__( 'Comment spam caught (30 days)', 'koz-url-only-comment-spam' ),
			array( __CLASS__, 'render_dashboard_widget' )
		);
	}

	public static function render_dashboard_widget(): void {
		$totals = self::totals( 30 );
		$labels = self::rule_labels();
		$sum = array_sum( $totals );
		?>
		<p><strong><?php echo esc_html( number_format_i18n( $sum ) ); ?></strong> <?php esc_html_e( 'comments handled in the last 30 days.', 'koz-url-only-comment-spam' ); ?></p>
		<?php if ( $sum > 0 ) : ?>
			<table class="widefat striped"><tbody>
			<?php foreach ( $totals as $rule => $count ) : ?>
				<?php if ( $count > 0 ) : ?>
					<tr><td><?php echo esc_html( $labels[ $rule ] ); ?></td><td style="text-align:right"><?php echo esc_html( number_format_i18n( $count ) ); ?></td></tr>
				<?php endif; ?>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=koz-url-only-comment-spam' ) ); ?>"><?php esc_html_e( 'Settings', 'koz-url-only-comment-spam' ); ?></a> · <a href="<?php echo esc_url( admin_url( 'edit-comments.php?comment_status=spam' ) ); ?>"><?php esc_html_e( 'Spam comments', 'koz-url-only-comment-spam' ); ?></a></p>
		<?php
	}

	/**
	 * Keep the daily cron event in line with the auto-delete setting.
	 */
	public static function sync_schedule(): void {
		$days = (int) Plugin::instance()->get_settings()['auto_delete_days'];
		$next = wp_next_scheduled( self::CLEANUP_HOOK );

		if ( $days > 0 && false === $next ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		} elseif ( $days <= 0 && false !== $next ) {
			wp_clear_scheduled_hook( self::CLEANUP_HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}

	/**
	 * Number of spam comments the cleanup would delete now.
	 */
	public static function cleanup_candidates( int $days ): int {
		if ( $days <= 0 ) {
			return 0;
		}

		return (int) get_comments(
			array(
				'status'     => 'spam',
				'count'      => true,
				'date_query' => array( array( 'before' => $days . ' days ago', 'inclusive' => true ) ),
			)
		);
	}

	/**
	 * Permanently delete spam comments older than the configured number of days.
	 * Only comments already in the spam folder are affected.
	 */
	public static function run_cleanup(): void {
		$days = (int) Plugin::instance()->get_settings()['auto_delete_days'];
		if ( $days <= 0 ) {
			return;
		}

		$ids = get_comments(
			array(
				'status'     => 'spam',
				'fields'     => 'ids',
				'number'     => self::CLEANUP_BATCH,
				'date_query' => array( array( 'before' => $days . ' days ago', 'inclusive' => true ) ),
			)
		);

		foreach ( (array) $ids as $id ) {
			wp_delete_comment( (int) $id, true );
		}
	}
}
