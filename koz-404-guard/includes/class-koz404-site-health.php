<?php
/**
 * Site Health test for internal links that lead to 404 and a dashboard widget
 * with the most frequent 404 addresses of the last seven days.
 *
 * @package KOZ_404_Guard
 */

namespace ramirkz\koz404;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZ404_Site_Health {
	public static function init(): void {
		add_filter( 'site_status_tests', array( __CLASS__, 'tests' ) );
		add_filter( 'debug_information', array( __CLASS__, 'debug_information' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard' ) );
	}

	private static function page_url( string $view = '404' ): string {
		return admin_url( 'admin.php?page=koz-404-guard&tab=log&view=' . rawurlencode( $view ) );
	}

	public static function tests( array $tests ): array {
		$tests['direct']['koz404_internal_404'] = array(
			'label' => __( 'Internal links that lead to 404', 'koz-404-guard' ),
			'test'  => array( __CLASS__, 'test_internal_404' ),
		);
		return $tests;
	}

	public static function test_internal_404(): array {
		$settings = KOZ404_Guard::settings();
		$result   = array(
			'label'       => __( 'No internal links leading to 404 were recorded', 'koz-404-guard' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'SEO', 'koz-404-guard' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'KOZ 404 Guard records 404 responses reached from pages of this site.', 'koz-404-guard' ) . '</p>',
			'actions'     => sprintf( '<p><a href="%1$s">%2$s</a></p>', esc_url( self::page_url( 'internal' ) ), esc_html__( 'Open the 404 log', 'koz-404-guard' ) ),
			'test'        => 'koz404_internal_404',
		);
		if ( empty( $settings['log_enabled'] ) ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'The 404 log is turned off', 'koz-404-guard' );
			$result['description'] = '<p>' . esc_html__( 'Without the log, broken internal links cannot be found. Turn it on in KOZ 404 Guard settings.', 'koz-404-guard' ) . '</p>';
			return $result;
		}
		$rows = KOZ404_Log::top( 5, wp_date( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ), true );
		if ( empty( $rows ) ) {
			return $result;
		}
		$items = '';
		foreach ( $rows as $row ) {
			$path = '' !== (string) $row['path'] ? (string) $row['path'] : '/[path:' . (string) $row['path_fingerprint'] . ']';
			$from = '' !== (string) $row['referrer_path'] ? ' ← ' . (string) $row['referrer_path'] : '';
			$items .= '<li><code>' . esc_html( $path ) . '</code>' . esc_html( $from ) . ' (' . esc_html( number_format_i18n( (int) $row['hits'] ) ) . ')</li>';
		}
		$result['status']      = 'recommended';
		$result['label']       = __( 'Some internal links lead to 404', 'koz-404-guard' );
		$result['description'] = '<p>' . esc_html__( 'Visitors followed links on this site to pages that do not exist (last 30 days). Fix the links or add redirects.', 'koz-404-guard' ) . '</p><ul>' . $items . '</ul>';
		return $result;
	}

	public static function debug_information( array $info ): array {
		$status = KOZ404_Guard::public_status();
		$info['koz-404-guard'] = array(
			'label'  => __( 'KOZ 404 Guard', 'koz-404-guard' ),
			'fields' => array(
				'version'        => array( 'label' => __( 'Version', 'koz-404-guard' ), 'value' => KOZ404_VERSION ),
				'enabled'        => array( 'label' => __( 'Request handling', 'koz-404-guard' ), 'value' => $status['enabled'] ? __( 'On', 'koz-404-guard' ) : __( 'Off', 'koz-404-guard' ), 'debug' => $status['enabled'] ? 'on' : 'off' ),
				'logging'        => array( 'label' => __( '404 log', 'koz-404-guard' ), 'value' => $status['logging_enabled'] ? __( 'On', 'koz-404-guard' ) : __( 'Off', 'koz-404-guard' ), 'debug' => $status['logging_enabled'] ? 'on' : 'off' ),
				'open_404'       => array( 'label' => __( 'Open 404 addresses', 'koz-404-guard' ), 'value' => number_format_i18n( $status['open_404'] ), 'debug' => (string) $status['open_404'] ),
				'internal_404'   => array( 'label' => __( 'Reached from this site', 'koz-404-guard' ), 'value' => number_format_i18n( $status['internal_404'] ), 'debug' => (string) $status['internal_404'] ),
				'redirect_rules' => array( 'label' => __( 'Redirect rules', 'koz-404-guard' ), 'value' => number_format_i18n( $status['redirect_rules'] ), 'debug' => (string) $status['redirect_rules'] ),
				'gone_rules'     => array( 'label' => __( '410 rules', 'koz-404-guard' ), 'value' => number_format_i18n( $status['gone_rules'] ), 'debug' => (string) $status['gone_rules'] ),
			),
		);
		return $info;
	}

	public static function dashboard(): void {
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget( 'koz404_dashboard', __( '404 in the last 7 days', 'koz-404-guard' ), array( __CLASS__, 'render_dashboard' ) );
		}
	}

	public static function render_dashboard(): void {
		$rows = KOZ404_Log::top( 8, wp_date( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) );
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No 404 responses were recorded in the last 7 days.', 'koz-404-guard' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><tbody>';
			foreach ( $rows as $row ) {
				$path = '' !== (string) $row['path'] ? (string) $row['path'] : '/[path:' . (string) $row['path_fingerprint'] . ']';
				echo '<tr><td><code>' . esc_html( $path ) . '</code>' . ( 'same_site' === $row['referrer_scope'] ? ' <span class="dashicons dashicons-admin-links" title="' . esc_attr__( 'Reached from this site', 'koz-404-guard' ) . '"></span>' : '' ) . '</td><td style="text-align:right">' . esc_html( number_format_i18n( (int) $row['hits'] ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<p><a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Open the 404 log', 'koz-404-guard' ) . '</a></p>';
	}
}
