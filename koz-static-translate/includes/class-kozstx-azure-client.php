<?php
/**
 * Microsoft Azure AI Translator HTTP client.
 *
 * Every call here is billed by Azure. Callers must check KOZSTX_Quota::available()
 * first and record the result with KOZSTX_Quota::increment_usage().
 *
 * @package KOZ_Static_Translate
 */

namespace ramirkz\kozstx;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZSTX_Azure_Client {
	public const API_VERSION = '2026-06-06';

	/**
	 * Terms kept untranslated when the do-not-translate setting has never been saved
	 * (installations created before 0.10.0).
	 */
	public const LEGACY_PROTECTED_TERMS = array(
		'UA FREE', 'PayPal', 'Monobank', 'MONOBANK', 'PrivatBank',
		'LiqPay', 'LIQPAY', 'Google Pay', 'Apple Pay', 'BTC', 'ETH', 'BNB',
		'USDT', 'USDC', 'XRP', 'Dogecoin', 'Bitcoin Cash', 'Stellar', 'ADA',
		'ERC-20', 'TRC-20', 'BEP-20', 'Solana', 'TGE', 'Maecenata',
	);

	/** Default do-not-translate terms for new installations: payment and crypto names only. */
	public const DEFAULT_PROTECTED_TERMS = array(
		'PayPal', 'Google Pay', 'Apple Pay', 'BTC', 'ETH', 'BNB', 'USDT', 'USDC',
		'XRP', 'Dogecoin', 'Bitcoin Cash', 'Stellar', 'ADA', 'ERC-20', 'TRC-20', 'BEP-20', 'Solana',
	);

	/**
	 * Translate a batch of segments with the 2026-06-06 API, falling back to v3.0.
	 *
	 * @param array<int,array<string,mixed>> $segments    Rows with a source_text key.
	 * @param array<string,mixed>            $credentials Decrypted credentials.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function translate_batch( array $segments, string $target_code, array $credentials ) {
		$inputs            = array();
		$source_characters = 0;
		$source_code       = KOZSTX_Static_Translate::source_language_code();
		foreach ( $segments as $segment ) {
			$text = (string) $segment['source_text'];
			$source_characters += KOZSTX_Static_Translate::string_length( $text );
			$inputs[] = array(
				'text'     => self::prepare_text( $text ),
				'language' => $source_code,
				'textType' => 'html',
				'targets'  => array(
					array( 'language' => $target_code ),
				),
			);
		}

		$response = wp_remote_post(
			self::new_api_url( (string) $credentials['endpoint'] ),
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => self::headers( $credentials ),
				'body'        => wp_json_encode( array( 'inputs' => $inputs ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( in_array( $status, array( 400, 404, 405 ), true ) ) {
			return self::translate_v3( $segments, $target_code, $credentials, $source_characters );
		}
		if ( $status < 200 || $status >= 300 ) {
			return self::http_error( $response, $status );
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! isset( $data['value'] ) || ! is_array( $data['value'] ) ) {
			return new WP_Error( 'azure_invalid_response', 'Azure returned an invalid 2026-06-06 response.' );
		}

		$translations   = array();
		$reported_chars = 0;
		foreach ( $data['value'] as $item ) {
			$text = '';
			if ( isset( $item['translations'][0]['text'] ) ) {
				$text = (string) $item['translations'][0]['text'];
				$reported_chars += (int) ( $item['translations'][0]['sourceCharacters'] ?? 0 );
			}
			$translations[] = $text;
		}
		if ( count( $translations ) !== count( $segments ) ) {
			return new WP_Error( 'azure_response_count', 'Azure returned a different number of translations than were sent.' );
		}

		return array(
			'translations'      => $translations,
			'source_characters' => $reported_chars > 0 ? $reported_chars : $source_characters,
			'api_version'       => self::API_VERSION,
			'request_id'        => (string) wp_remote_retrieve_header( $response, 'x-requestid' ),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $segments
	 * @param array<string,mixed>            $credentials
	 * @return array<string,mixed>|WP_Error
	 */
	private static function translate_v3( array $segments, string $target_code, array $credentials, int $source_characters ) {
		$body = array();
		foreach ( $segments as $segment ) {
			$body[] = array( 'Text' => self::prepare_text( (string) $segment['source_text'] ) );
		}
		$response = wp_remote_post(
			self::v3_api_url( (string) $credentials['endpoint'], $target_code ),
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => self::headers( $credentials ),
				'body'        => wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'data_format' => 'body',
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return self::http_error( $response, $status );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'azure_invalid_response_v3', 'Azure returned an invalid v3.0 response.' );
		}
		$translations = array();
		foreach ( $data as $item ) {
			$translations[] = (string) ( $item['translations'][0]['text'] ?? '' );
		}
		if ( count( $translations ) !== count( $segments ) ) {
			return new WP_Error( 'azure_response_count_v3', 'Azure v3.0 returned a different number of translations.' );
		}
		return array(
			'translations'      => $translations,
			'source_characters' => $source_characters,
			'api_version'       => '3.0 fallback',
			'request_id'        => (string) wp_remote_retrieve_header( $response, 'x-requestid' ),
		);
	}

	/** @param array<string,mixed> $credentials */
	private static function headers( array $credentials ): array {
		$headers = array(
			'Content-Type'              => 'application/json; charset=utf-8',
			'Ocp-Apim-Subscription-Key' => (string) $credentials['key'],
			'X-ClientTraceId'           => wp_generate_uuid4(),
		);
		if ( '' !== (string) $credentials['region'] ) {
			$headers['Ocp-Apim-Subscription-Region'] = (string) $credentials['region'];
		}
		return $headers;
	}

	private static function new_api_url( string $endpoint ): string {
		$parts = wp_parse_url( $endpoint );
		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path  = rtrim( (string) ( $parts['path'] ?? '' ), '/' );
		if ( '' === $path ) {
			$path = str_ends_with( $host, '.cognitiveservices.azure.com' ) ? '/translator/text/translate' : '/translate';
		} elseif ( ! str_ends_with( $path, '/translate' ) ) {
			$path .= '/translate';
		}
		return add_query_arg( 'api-version', self::API_VERSION, 'https://' . $host . $path );
	}

	private static function v3_api_url( string $endpoint, string $target_code ): string {
		$parts = wp_parse_url( $endpoint );
		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
		return add_query_arg(
			array(
				'api-version' => '3.0',
				'from'        => KOZSTX_Static_Translate::source_language_code(),
				'to'          => $target_code,
				'textType'    => 'html',
			),
			'https://' . $host . '/translate'
		);
	}

	/** @param array|WP_Error $response */
	private static function http_error( $response, int $status ): WP_Error {
		$body    = (string) wp_remote_retrieve_body( $response );
		$data    = json_decode( $body, true );
		$message = is_array( $data ) ? (string) ( $data['error']['message'] ?? $data['message'] ?? '' ) : '';
		$message = $message ?: 'Azure Translator HTTP ' . $status;
		$retry_header = wp_remote_retrieve_header( $response, 'retry-after' );
		$retry_after  = is_numeric( $retry_header ) ? (int) $retry_header : 0;
		if ( 429 === $status ) {
			$lower = strtolower( $message );
			$code  = false !== strpos( $lower, 'quota' ) || false !== strpos( $lower, 'monthly' ) ? 'azure_quota' : 'azure_rate_limit';
			return new WP_Error( $code, $message, array( 'status' => $status, 'retry_after' => $retry_after ) );
		}
		if ( in_array( $status, array( 401, 403 ), true ) ) {
			return new WP_Error( 'azure_auth', $message, array( 'status' => $status ) );
		}
		return new WP_Error( 'azure_http_' . $status, $message, array( 'status' => $status, 'retry_after' => $retry_after ) );
	}

	/**
	 * Terms that must stay untranslated: the do-not-translate setting, filterable.
	 *
	 * @return string[]
	 */
	public static function protected_terms(): array {
		$settings = KOZSTX_Static_Translate::settings();
		$terms    = array_key_exists( 'protected_terms', $settings ) && is_array( $settings['protected_terms'] )
			? $settings['protected_terms']
			: self::LEGACY_PROTECTED_TERMS;

		/**
		 * Filter the terms wrapped in translate="no" before text is sent to Azure.
		 *
		 * @param string[] $terms
		 */
		$terms = (array) apply_filters( 'kozstx_protected_terms', $terms );
		return array_values( array_filter( array_map( 'strval', $terms ), static fn( string $term ): bool => '' !== trim( $term ) ) );
	}

	public static function prepare_text( string $text ): string {
		$escaped = esc_html( $text );
		$terms   = self::protected_terms();
		usort( $terms, static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );
		foreach ( $terms as $term ) {
			$quoted  = preg_quote( esc_html( $term ), '/' );
			$escaped = (string) preg_replace( '/' . $quoted . '/iu', '<span class="notranslate" translate="no">$0</span>', $escaped );
		}
		return '<div>' . $escaped . '</div>';
	}

	public static function clean_translation( string $translation ): string {
		$translation = preg_replace( '/<\/?(?:div|span)\b[^>]*>/iu', '', $translation );
		$translation = preg_replace( '/<\/?mstrans:dictionary\b[^>]*>/iu', '', (string) $translation );
		$translation = html_entity_decode( (string) $translation, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$translation = preg_replace( '/\s+/u', ' ', (string) $translation );
		return trim( (string) $translation );
	}
}
