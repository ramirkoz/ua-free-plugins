<?php
/**
 * Tools → Site Health tests and the "KOZ Translate" section of Site Health info.
 *
 * @package KOZ_Translate_Diagnostics
 */

namespace ramirkz\koztdiag;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZTDIAG_Site_Health {
	private const RANK = array(
		'good' => 0,
		'recommended' => 1,
		'critical' => 2,
	);

	public static function init(): void {
		add_filter( 'site_status_tests', array( __CLASS__, 'tests' ) );
		add_filter( 'debug_information', array( __CLASS__, 'debug_information' ) );
	}

	public static function tests( array $tests ): array {
		$tests['direct']['koztdiag_translator'] = array(
			'label' => __( 'KOZ Static Translate setup', 'koz-translate-diagnostics' ),
			'test' => array( __CLASS__, 'test_translator' ),
		);
		if ( KOZTDIAG_Budget::is_supported() ) {
			$tests['direct']['koztdiag_budget'] = array(
				'label' => __( 'Azure Translator budget and background worker', 'koz-translate-diagnostics' ),
				'test' => array( __CLASS__, 'test_budget' ),
			);
		}
		return $tests;
	}

	private static function result( string $test, string $status, string $label, array $messages ): array {
		$description = '';
		foreach ( $messages as $message ) {
			$description .= '<p>' . esc_html( $message ) . '</p>';
		}
		return array(
			'label' => $label,
			'status' => $status,
			'badge' => array(
				'label' => __( 'Translation', 'koz-translate-diagnostics' ),
				'color' => 'blue',
			),
			'description' => $description,
			'actions' => sprintf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url( admin_url( 'admin.php?page=' . KOZTDIAG_Translate_Diagnostics::PAGE_SLUG ) ),
				esc_html__( 'Open Translation Diagnostics', 'koz-translate-diagnostics' )
			),
			'test' => $test,
		);
	}

	public static function test_translator(): array {
		$status = KOZTDIAG_Translate_Diagnostics::public_status();
		$settings = KOZTDIAG_Translate_Diagnostics::settings_snapshot();

		if ( empty( $status['translator_detected'] ) || empty( $status['translator_active'] ) ) {
			return self::result(
				'koztdiag_translator',
				'recommended',
				__( 'KOZ Static Translate is not active', 'koz-translate-diagnostics' ),
				array( __( 'KOZ Translate Diagnostics checks KOZ Static Translate. Install and activate it, or deactivate the diagnostics plugin.', 'koz-translate-diagnostics' ) )
			);
		}
		$messages = array();
		if ( empty( $settings['azure_key_configured'] ) ) {
			$messages[] = __( 'The Azure Translator key is not configured.', 'koz-translate-diagnostics' );
		}
		if ( ! empty( $status['migration_frozen'] ) ) {
			$messages[] = __( 'The translator reports an active migration freeze.', 'koz-translate-diagnostics' );
		}
		if ( empty( $settings['target_languages'] ) ) {
			$messages[] = __( 'No target languages are selected.', 'koz-translate-diagnostics' );
		}
		if ( ! empty( $messages ) ) {
			return self::result( 'koztdiag_translator', 'recommended', __( 'KOZ Static Translate needs attention', 'koz-translate-diagnostics' ), $messages );
		}
		return self::result(
			'koztdiag_translator',
			'good',
			__( 'KOZ Static Translate is active and configured', 'koz-translate-diagnostics' ),
			array(
				sprintf(
					/* translators: 1: translator version, 2: number of target languages. */
					__( 'Version %1$s, %2$d target languages, Azure key configured.', 'koz-translate-diagnostics' ),
					(string) $status['translator_version'],
					count( (array) $settings['target_languages'] )
				),
			)
		);
	}

	public static function test_budget(): array {
		$snapshot = KOZTDIAG_Budget::snapshot();
		$findings = KOZTDIAG_Budget::findings();
		$worst = 'good';
		$messages = array();
		foreach ( $findings as $finding ) {
			if ( self::RANK[ $finding['status'] ] > self::RANK[ $worst ] ) {
				$worst = $finding['status'];
			}
			$messages[] = $finding['message'];
		}
		array_unshift(
			$messages,
			sprintf(
				/* translators: 1: used characters, 2: monthly limit, 3: characters used today, 4: daily budget. */
				__( 'This month: %1$s of %2$s characters. Today: %3$s of %4$s characters.', 'koz-translate-diagnostics' ),
				number_format_i18n( (int) $snapshot['monthly']['used'] ),
				number_format_i18n( (int) $snapshot['monthly']['limit'] ),
				number_format_i18n( (int) $snapshot['daily']['used'] ),
				number_format_i18n( (int) $snapshot['daily']['limit'] )
			)
		);
		$label = 'good' === $worst
			? __( 'Azure Translator budget and background worker are fine', 'koz-translate-diagnostics' )
			: __( 'Azure Translator budget or background worker needs attention', 'koz-translate-diagnostics' );
		return self::result( 'koztdiag_budget', $worst, $label, $messages );
	}

	private static function yes_no( bool $value ): string {
		return $value ? __( 'Yes', 'koz-translate-diagnostics' ) : __( 'No', 'koz-translate-diagnostics' );
	}

	public static function debug_information( array $info ): array {
		$status = KOZTDIAG_Translate_Diagnostics::public_status();
		$settings = KOZTDIAG_Translate_Diagnostics::settings_snapshot();
		$fields = array(
			'diagnostics_version' => array(
				'label' => __( 'Diagnostics version', 'koz-translate-diagnostics' ),
				'value' => KOZTDIAG_VERSION,
			),
			'translator_version' => array(
				'label' => __( 'KOZ Static Translate version', 'koz-translate-diagnostics' ),
				'value' => '' !== (string) $status['translator_version'] ? (string) $status['translator_version'] : '—',
			),
			'translator_active' => array(
				'label' => __( 'KOZ Static Translate active', 'koz-translate-diagnostics' ),
				'value' => self::yes_no( ! empty( $status['translator_active'] ) ),
				'debug' => ! empty( $status['translator_active'] ),
			),
			'source_language' => array(
				'label' => __( 'Source language', 'koz-translate-diagnostics' ),
				'value' => '' !== (string) $settings['source_language'] ? (string) $settings['source_language'] : '—',
			),
			'target_languages' => array(
				'label' => __( 'Target languages', 'koz-translate-diagnostics' ),
				'value' => empty( $settings['target_languages'] ) ? '—' : implode( ', ', (array) $settings['target_languages'] ),
			),
			'azure_key' => array(
				'label' => __( 'Azure key configured', 'koz-translate-diagnostics' ),
				'value' => self::yes_no( ! empty( $settings['azure_key_configured'] ) ),
				'debug' => ! empty( $settings['azure_key_configured'] ),
			),
			'sources' => array(
				'label' => __( 'Translation sources (estimate)', 'koz-translate-diagnostics' ),
				'value' => number_format_i18n( (int) $status['source_count'] ),
				'debug' => (string) (int) $status['source_count'],
			),
		);

		if ( KOZTDIAG_Budget::is_supported() ) {
			$s = KOZTDIAG_Budget::snapshot();
			$fields['monthly_usage'] = array(
				'label' => __( 'Azure characters this month', 'koz-translate-diagnostics' ),
				'value' => sprintf( '%s / %s (%s%%)', number_format_i18n( (int) $s['monthly']['used'] ), number_format_i18n( (int) $s['monthly']['limit'] ), number_format_i18n( (float) $s['monthly']['percent'], 1 ) ),
				'debug' => (int) $s['monthly']['used'] . '/' . (int) $s['monthly']['limit'],
			);
			$fields['daily_usage'] = array(
				'label' => __( 'Azure characters today (UTC)', 'koz-translate-diagnostics' ),
				'value' => sprintf( '%s / %s', number_format_i18n( (int) $s['daily']['used'] ), number_format_i18n( (int) $s['daily']['limit'] ) ),
				'debug' => (int) $s['daily']['used'] . '/' . (int) $s['daily']['limit'],
			);
			if ( empty( $s['worker']['enabled'] ) ) {
				$worker = __( 'Off', 'koz-translate-diagnostics' );
			} elseif ( '' === $s['worker']['next_run_utc'] ) {
				$worker = __( 'On, not scheduled', 'koz-translate-diagnostics' );
			} else {
				/* translators: %s: date and time of the next run in UTC. */
				$worker = sprintf( __( 'On, next run %s', 'koz-translate-diagnostics' ), gmdate( 'Y-m-d H:i', (int) strtotime( $s['worker']['next_run_utc'] ) ) . ' UTC' );
			}
			$fields['background_worker'] = array(
				'label' => __( 'Background worker', 'koz-translate-diagnostics' ),
				'value' => $worker,
				'debug' => empty( $s['worker']['enabled'] ) ? 'off' : ( '' !== $s['worker']['next_run_utc'] ? 'on ' . $s['worker']['next_run_utc'] : 'on unscheduled' ),
			);
			$fields['page_text_waiting'] = array(
				'label' => __( 'Page text waiting for the worker', 'koz-translate-diagnostics' ),
				'value' => number_format_i18n( (int) $s['residual_queue']['total'] ),
				'debug' => (string) (int) $s['residual_queue']['total'],
			);
			$fields['rejected_segments'] = array(
				'label' => __( 'Segments no longer sent (rejected twice)', 'koz-translate-diagnostics' ),
				'value' => number_format_i18n( (int) $s['rejected_segments']['blocked'] ),
				'debug' => (string) (int) $s['rejected_segments']['blocked'],
			);
			$fields['sitemap'] = array(
				'label' => __( 'Translation sitemap registered', 'koz-translate-diagnostics' ),
				'value' => self::yes_no( ! empty( $s['sitemap']['rule_registered'] ) ),
				'debug' => ! empty( $s['sitemap']['rule_registered'] ),
			);
		}

		$info['koz-translate'] = array(
			'label' => __( 'KOZ Translate', 'koz-translate-diagnostics' ),
			'fields' => $fields,
		);
		return $info;
	}
}
