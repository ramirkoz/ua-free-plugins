<?php
/**
 * Deep JSON report download and the short text report for support chats.
 *
 * @package KOZ_Translate_Diagnostics
 */

namespace ramirkz\koztdiag;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZTDIAG_Export {
	public static function init(): void {
		add_action( 'admin_post_' . KOZTDIAG_Translate_Diagnostics::EXPORT_ACTION, array( __CLASS__, 'download' ) );
	}

	public static function download_url(): string {
		return wp_nonce_url(
			add_query_arg( array( 'action' => KOZTDIAG_Translate_Diagnostics::EXPORT_ACTION ), admin_url( 'admin-post.php' ) ),
			KOZTDIAG_Translate_Diagnostics::EXPORT_ACTION
		);
	}

	/**
	 * Runs one deep scan and sends the strict privacy-safe report as a file.
	 * Nothing is stored on the server.
	 */
	public static function download(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export this report.', 'koz-translate-diagnostics' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( KOZTDIAG_Translate_Diagnostics::EXPORT_ACTION );

		$report = KOZTDIAG_Translate_Diagnostics::report( true, true );
		$json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) ) {
			wp_die( esc_html__( 'The report could not be encoded as JSON.', 'koz-translate-diagnostics' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="koz-translate-diagnostics-' . gmdate( 'Ymd-His' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Length: ' . strlen( $json ) );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file download, not HTML.
		exit;
	}

	private static function yes_no( bool $value ): string {
		return $value ? __( 'yes', 'koz-translate-diagnostics' ) : __( 'no', 'koz-translate-diagnostics' );
	}

	private static function date_utc( string $iso ): string {
		$timestamp = '' !== $iso ? strtotime( $iso ) : false;
		return false === $timestamp ? '—' : gmdate( 'Y-m-d H:i', $timestamp ) . ' UTC';
	}

	/**
	 * Plain-text summary without content, paths, keys or identifiers.
	 */
	public static function summary_text( array $report ): string {
		$translator = (array) ( $report['translator_plugin'] ?? array() );
		$settings = (array) ( $report['settings'] ?? array() );
		$runtime = (array) ( $report['runtime'] ?? array() );
		$budget = (array) ( $report['budget'] ?? array() );
		$version = (string) ( $translator['constant_version'] ?: ( $translator['matches'][0]['version'] ?? '' ) );
		$active = ! empty( array_filter( array_column( (array) ( $translator['matches'] ?? array() ), 'active' ) ) );

		$lines = array();
		$lines[] = sprintf( 'KOZ Translate Diagnostics %s (%s)', KOZTDIAG_VERSION, (string) ( $report['report']['diagnostic_mode'] ?? 'quick' ) );
		$lines[] = sprintf( 'WordPress %s, PHP %s, %s', (string) ( $report['environment']['wordpress'] ?? '' ), (string) ( $report['environment']['php'] ?? '' ), (string) ( $report['environment']['locale'] ?? '' ) );
		$lines[] = sprintf(
			/* translators: 1: translator version, 2: yes/no, 3: yes/no. */
			__( 'KOZ Static Translate: %1$s, active: %2$s, diagnostics API: %3$s', 'koz-translate-diagnostics' ),
			'' !== $version ? $version : '—',
			self::yes_no( $active ),
			self::yes_no( ! empty( $translator['public_api_available'] ) )
		);
		$lines[] = sprintf(
			/* translators: 1: source language code, 2: number of target languages. */
			__( 'Source language: %1$s; target languages: %2$d', 'koz-translate-diagnostics' ),
			'' !== (string) ( $settings['source_language'] ?? '' ) ? (string) $settings['source_language'] : '—',
			count( (array) ( $settings['target_languages'] ?? array() ) )
		);
		$lines[] = sprintf(
			/* translators: %s: yes/no. */
			__( 'Azure key configured: %s', 'koz-translate-diagnostics' ),
			self::yes_no( ! empty( $settings['azure_key_configured'] ) )
		);
		$pause = (string) ( $runtime['pause_reason'] ?? '' );
		$lines[] = sprintf(
			/* translators: 1: yes/no, 2: pause reason code. */
			__( 'Manual pause: %1$s; pause reason: %2$s', 'koz-translate-diagnostics' ),
			self::yes_no( ! empty( $runtime['manual_paused'] ) ),
			'' === $pause ? '—' : ( KOZTDIAG_Translate_Diagnostics::is_migration_pause_reason( $pause ) ? 'migration_bridge' : sanitize_key( $pause ) )
		);
		$lines[] = sprintf(
			/* translators: %s: number of translation sources. */
			__( 'Translation sources: %s', 'koz-translate-diagnostics' ),
			number_format_i18n( (int) ( $report['sources']['total'] ?? 0 ) )
		);

		if ( ! empty( $budget['available'] ) ) {
			$lines[] = sprintf(
				/* translators: 1: used characters, 2: monthly limit, 3: percent, 4: reset date. */
				__( 'Azure this month: %1$s of %2$s characters (%3$s%%), resets %4$s', 'koz-translate-diagnostics' ),
				number_format_i18n( (int) $budget['monthly']['used'] ),
				number_format_i18n( (int) $budget['monthly']['limit'] ),
				number_format_i18n( (float) $budget['monthly']['percent'], 1 ),
				self::date_utc( (string) $budget['monthly']['resets_at'] )
			);
			$lines[] = sprintf(
				/* translators: 1: characters used today, 2: daily budget, 3: characters available now. */
				__( 'Azure today (UTC): %1$s of %2$s characters; available now: %3$s', 'koz-translate-diagnostics' ),
				number_format_i18n( (int) $budget['daily']['used'] ),
				number_format_i18n( (int) $budget['daily']['limit'] ),
				number_format_i18n( (int) $budget['available_now'] )
			);
			$sources = array();
			foreach ( (array) $budget['usage_by_source_30_days'] as $kind => $characters ) {
				$sources[] = KOZTDIAG_Budget::source_label( (string) $kind ) . ' ' . number_format_i18n( (int) $characters );
			}
			$lines[] = sprintf(
				/* translators: %s: comma-separated usage by source. */
				__( 'Azure by source, 30 days: %s', 'koz-translate-diagnostics' ),
				empty( $sources ) ? '—' : implode( ', ', $sources )
			);
			$worker = (array) $budget['worker'];
			$lines[] = sprintf(
				/* translators: 1: yes/no, 2: next run date. */
				__( 'Background worker: %1$s; next run: %2$s', 'koz-translate-diagnostics' ),
				self::yes_no( ! empty( $worker['enabled'] ) ),
				self::date_utc( (string) $worker['next_run_utc'] )
			);
			$lines[] = sprintf(
				/* translators: 1: page text items waiting, 2: blocked rejected segments. */
				__( 'Page text waiting: %1$s; segments blocked after two rejections: %2$s', 'koz-translate-diagnostics' ),
				number_format_i18n( (int) $budget['residual_queue']['total'] ),
				number_format_i18n( (int) $budget['rejected_segments']['blocked'] )
			);
			$lines[] = sprintf(
				/* translators: 1: sitemap path, 2: yes/no. */
				__( 'Translation sitemap %1$s registered: %2$s', 'koz-translate-diagnostics' ),
				'/' . KOZTDIAG_Budget::SITEMAP_PATH,
				self::yes_no( ! empty( $budget['sitemap']['rule_registered'] ) )
			);
		}

		$lines[] = __( 'Findings:', 'koz-translate-diagnostics' );
		$hints = (array) ( $report['hints'] ?? array() );
		if ( empty( $hints ) ) {
			$lines[] = '- ' . __( 'No automatic findings were generated.', 'koz-translate-diagnostics' );
		}
		foreach ( $hints as $hint ) {
			$lines[] = '- ' . wp_strip_all_tags( (string) $hint );
		}
		// number_format_i18n() uses &nbsp; as the thousands separator in some locales.
		$text = html_entity_decode( implode( "\n", $lines ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return str_replace( "\u{00A0}", ' ', $text );
	}
}
