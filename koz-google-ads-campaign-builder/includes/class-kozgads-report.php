<?php
/**
 * Search-demand data imported from Google Ads or Keyword Planner CSV exports.
 *
 * The administrator uploads a keyword report (Google Ads > Keywords > Download)
 * or a Keyword Planner export. Keywords marked "Low search volume" or below the
 * monthly-search threshold are left out of the next package, and planner ideas
 * that match a landing page are added. Only keyword text and numbers are kept.
 *
 * @package KOZ_Google_Ads_Campaign_Builder
 */

namespace ramirkz\kozgads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZGADS_Report {
	public const OPTION        = 'kozgads_keyword_data';
	public const ACTION_IMPORT = 'kozgads_import_report';
	public const ACTION_CLEAR  = 'kozgads_clear_report';
	private const MAX_BYTES    = 2097152;
	private const MAX_ROWS     = 5000;

	public static function init(): void {
		add_action( 'admin_post_' . self::ACTION_IMPORT, array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_' . self::ACTION_CLEAR, array( __CLASS__, 'handle_clear' ) );
	}

	public static function data(): array {
		$data = get_option( self::OPTION, array() );
		return is_array( $data ) && isset( $data['rows'] ) && is_array( $data['rows'] ) ? $data : array( 'rows' => array() );
	}

	public static function normalise( string $keyword ): string {
		$keyword = trim( (string) preg_replace( '/[\[\]"+]/u', ' ', $keyword ) );
		return KOZGADS_Copy::lower( trim( (string) preg_replace( '/\s+/u', ' ', $keyword ) ) );
	}

	private static function number( string $value ): ?int {
		$value = str_replace( array( "\xC2\xA0", ' ', ',' ), '', $value );
		if ( preg_match( '/\d+/', $value, $m ) ) {
			return (int) $m[0];
		}
		return null;
	}

	/** Decodes a Google export (UTF-16 tab-separated or UTF-8 CSV) into rows. */
	public static function rows_from_text( string $raw ): array {
		if ( str_starts_with( $raw, "\xFF\xFE" ) || str_starts_with( $raw, "\xFE\xFF" ) ) {
			$raw = function_exists( 'mb_convert_encoding' ) ? (string) mb_convert_encoding( $raw, 'UTF-8', 'UTF-16' ) : '';
		}
		$raw   = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
		$lines = preg_split( '/\r\n|\r|\n/', $raw ) ?: array();
		$rows  = array();
		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$delimiter = str_contains( $line, "\t" ) ? "\t" : ( substr_count( $line, ';' ) > substr_count( $line, ',' ) ? ';' : ',' );
			$rows[]    = array_map( 'trim', str_getcsv( $line, $delimiter, '"', '' ) );
			if ( count( $rows ) > self::MAX_ROWS + 5 ) {
				break;
			}
		}
		return $rows;
	}

	/** Column index for the first header matching one of the names. */
	private static function column( array $header, array $names ): int {
		foreach ( $header as $index => $cell ) {
			$cell = KOZGADS_Copy::lower( (string) $cell );
			foreach ( $names as $name ) {
				if ( $cell === $name || ( strlen( $name ) > 8 && str_starts_with( $cell, $name ) ) ) {
					return (int) $index;
				}
			}
		}
		return -1;
	}

	/**
	 * Parses rows into keyword facts.
	 *
	 * @return array{source:string,rows:array<string,array{low:bool,impressions:?int,clicks:?int,searches:?int}>}|null
	 */
	public static function parse( array $rows, int $min_searches ): ?array {
		$header_index = -1;
		foreach ( $rows as $index => $row ) {
			if ( self::column( $row, array( 'keyword', 'ключове слово', 'ключові слова', 'search keyword' ) ) >= 0 ) {
				$header_index = (int) $index;
				break;
			}
			if ( $index > 10 ) {
				break;
			}
		}
		if ( $header_index < 0 ) {
			return null;
		}
		$header   = $rows[ $header_index ];
		$keyword  = self::column( $header, array( 'keyword', 'ключове слово', 'ключові слова', 'search keyword' ) );
		$impr     = self::column( $header, array( 'impr.', 'impr', 'impressions', 'покази' ) );
		$clicks   = self::column( $header, array( 'clicks', 'кліки' ) );
		$searches = self::column( $header, array( 'avg. monthly searches', 'avg monthly searches', 'середня кількість пошуків', 'середньомісячна кількість пошуків', 'середня к-сть пошуків' ) );
		$out      = array();
		foreach ( array_slice( $rows, $header_index + 1 ) as $row ) {
			$text = self::normalise( (string) ( $row[ $keyword ] ?? '' ) );
			if ( '' === $text || str_starts_with( $text, 'total' ) || str_starts_with( $text, 'усього' ) || KOZGADS_Copy::length( $text ) > 80 ) {
				continue;
			}
			$joined = KOZGADS_Copy::lower( implode( ' ', $row ) );
			$volume = $searches >= 0 ? self::number( (string) ( $row[ $searches ] ?? '' ) ) : null;
			$low    = (bool) preg_match( '/low search volume|низький обсяг пошуку|rarely served|рідко показується/u', $joined ) || ( null !== $volume && $volume < $min_searches );
			$out[ $text ] = array(
				'low'         => $low,
				'impressions' => $impr >= 0 ? self::number( (string) ( $row[ $impr ] ?? '' ) ) : null,
				'clicks'      => $clicks >= 0 ? self::number( (string) ( $row[ $clicks ] ?? '' ) ) : null,
				'searches'    => $volume,
			);
			if ( count( $out ) >= self::MAX_ROWS ) {
				break;
			}
		}
		return empty( $out ) ? null : array( 'source' => $searches >= 0 ? 'planner' : 'ads', 'rows' => $out );
	}

	public static function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this plugin.', 'koz-google-ads-campaign-builder' ) );
		}
		check_admin_referer( self::ACTION_IMPORT );
		$file = $_FILES['kozgads_report'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The upload array is validated below and only its temporary file is read.
		$tmp  = is_array( $file ) ? (string) ( $file['tmp_name'] ?? '' ) : '';
		$size = is_array( $file ) ? (int) ( $file['size'] ?? 0 ) : 0;
		$name = is_array( $file ) ? sanitize_file_name( (string) ( $file['name'] ?? '' ) ) : '';
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) || $size <= 0 || $size > self::MAX_BYTES || ! preg_match( '/\.(csv|tsv|txt)$/i', $name ) ) {
			KOZGADS_Plugin::redirect_notice( 'error', __( 'Upload a CSV, TSV or TXT export up to 2 MB.', 'koz-google-ads-campaign-builder' ) );
		}
		$filesystem = KOZGADS_Plugin::filesystem();
		$raw        = is_wp_error( $filesystem ) ? false : $filesystem->get_contents( $tmp );
		$parsed     = is_string( $raw ) ? self::parse( self::rows_from_text( $raw ), KOZGADS_Plugin::min_searches() ) : null;
		if ( null === $parsed ) {
			KOZGADS_Plugin::redirect_notice( 'error', __( 'No keyword column was found in the file.', 'koz-google-ads-campaign-builder' ) );
		}
		$current = self::data();
		$rows    = array_merge( (array) $current['rows'], $parsed['rows'] );
		update_option(
			self::OPTION,
			array(
				'imported_at' => current_time( 'mysql' ),
				'filename'    => $name,
				'source'      => $parsed['source'],
				'rows'        => array_slice( $rows, -self::MAX_ROWS, null, true ),
			),
			false
		);
		KOZGADS_Plugin::redirect_notice( 'report_imported' );
	}

	public static function handle_clear(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this plugin.', 'koz-google-ads-campaign-builder' ) );
		}
		check_admin_referer( self::ACTION_CLEAR );
		delete_option( self::OPTION );
		KOZGADS_Plugin::redirect_notice( 'report_cleared' );
	}

	/** @return array{total:int,low:int,zero_impressions:int,with_searches:int,imported_at:string,filename:string} */
	public static function summary(): array {
		$data = self::data();
		$out  = array( 'total' => 0, 'low' => 0, 'zero_impressions' => 0, 'with_searches' => 0, 'imported_at' => (string) ( $data['imported_at'] ?? '' ), 'filename' => (string) ( $data['filename'] ?? '' ) );
		foreach ( (array) $data['rows'] as $row ) {
			$out['total']++;
			$out['low']              += ! empty( $row['low'] ) ? 1 : 0;
			$out['zero_impressions'] += ( isset( $row['impressions'] ) && 0 === $row['impressions'] ) ? 1 : 0;
			$out['with_searches']    += isset( $row['searches'] ) ? 1 : 0;
		}
		return $out;
	}

	public static function is_low( string $keyword ): bool {
		$rows = (array) self::data()['rows'];
		return ! empty( $rows[ self::normalise( $keyword ) ]['low'] );
	}

	/**
	 * Removes keywords known to have no search demand and adds planner ideas that
	 * share a word with the landing page.
	 *
	 * @return array{keywords:string[],removed:string[],added:string[]}
	 */
	public static function apply( array $keywords, array $page_words, int $limit ): array {
		$rows    = (array) self::data()['rows'];
		$kept    = array();
		$removed = array();
		foreach ( $keywords as $keyword ) {
			if ( ! empty( $rows[ self::normalise( $keyword ) ]['low'] ) ) {
				$removed[] = $keyword;
			} else {
				$kept[] = $keyword;
			}
		}
		$ideas = array();
		$page  = array_flip( $page_words );
		foreach ( $rows as $keyword => $row ) {
			if ( ! empty( $row['low'] ) || null === ( $row['searches'] ?? null ) ) {
				continue;
			}
			$words = KOZGADS_Copy::words( (string) $keyword );
			if ( count( $words ) < 2 || empty( array_intersect_key( array_flip( $words ), $page ) ) || in_array( (string) $keyword, $kept, true ) ) {
				continue;
			}
			$ideas[ (string) $keyword ] = (int) $row['searches'];
		}
		arsort( $ideas );
		$added = array_slice( array_keys( $ideas ), 0, max( 0, min( 5, $limit - count( $kept ) + 3 ) ) );
		return array(
			'keywords' => array_slice( array_values( array_unique( array_merge( $kept, $added ) ) ), 0, $limit ),
			'removed'  => $removed,
			'added'    => $added,
		);
	}
}
