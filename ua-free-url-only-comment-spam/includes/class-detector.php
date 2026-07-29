<?php
/**
 * URL-only comment detector.
 *
 * @package UAFree_URL_Only_Comment_Spam
 */

declare(strict_types=1);

namespace UAFree\URLSpam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Detector {
	/**
	 * Analyze comment text without storing it.
	 *
	 * @param string              $content  Raw comment content.
	 * @param array<string,mixed> $settings Sanitized plugin settings.
	 * @return array{is_url_only:bool,url_count:int,trusted_count:int,reason:string}
	 */
	public static function analyze( string $content, array $settings ): array {
		$normalized = self::normalize_content( $content );

		if ( '' === $normalized ) {
			return self::result( false, 0, 0, 'empty' );
		}

		$matches = array();
		$pattern = self::url_pattern();
		$remainder = preg_replace_callback(
			$pattern,
			static function ( array $match ) use ( &$matches ): string {
				$matches[] = (string) $match[1];
				return ' ';
			},
			$normalized
		);

		if ( null === $remainder || array() === $matches ) {
			return self::result( false, 0, 0, 'no_urls' );
		}

		$remainder = preg_replace( '/[\s\p{P}\p{S}]+/u', '', $remainder );
		if ( null === $remainder || '' !== $remainder ) {
			return self::result( false, count( $matches ), 0, 'contains_text' );
		}

		$url_count = count( $matches );
		$minimum   = isset( $settings['minimum_urls'] ) ? max( 1, (int) $settings['minimum_urls'] ) : 1;
		if ( $url_count < $minimum ) {
			return self::result( false, $url_count, 0, 'below_threshold' );
		}

		$trusted_count = self::count_trusted_urls( $matches, $settings );
		if ( $trusted_count === $url_count ) {
			return self::result( false, $url_count, $trusted_count, 'all_trusted' );
		}

		$result = self::result( true, $url_count, $trusted_count, 'url_only' );

		/**
		 * Filter the final URL-only decision.
		 *
		 * No comment text or URL is passed to preserve privacy.
		 *
		 * @param bool                 $is_url_only Final decision.
		 * @param array<string,mixed>  $summary     Privacy-safe summary.
		 */
		$result['is_url_only'] = (bool) apply_filters(
			'uafree_url_only_comment_spam_is_url_only',
			$result['is_url_only'],
			$result
		);

		return $result;
	}

	private static function normalize_content( string $content ): string {
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$content = preg_replace( '/<(?:br|\/p|\/div|\/li)\b[^>]*>/i', "\n", $content );
		$content = wp_strip_all_tags( (string) $content, false );
		$content = preg_replace( '/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', ' ', (string) $content );
		$content = preg_replace( '/\s+/u', ' ', (string) $content );
		return trim( (string) $content );
	}

	private static function url_pattern(): string {
		return <<<'REGEX'
~(?<![@\p{L}\p{N}_-])(
	(?:
		(?:https?|ftp):\/\/
		|\/\/
		|www\.
	)
	[^\s<>"']+
	|
	(?:
		(?:xn--)?[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.
	)+
	(?:[a-z]{2,63}|xn--[a-z0-9-]{2,59})
	(?:
		[\/:?#][^\s<>"']*
	)?
)(?![\p{L}\p{N}_-])~iux
REGEX;
	}

	/**
	 * @param string[]            $urls     Detected URL-like values.
	 * @param array<string,mixed> $settings Sanitized settings.
	 */
	private static function count_trusted_urls( array $urls, array $settings ): int {
		$trusted_domains = isset( $settings['trusted_domains'] ) && is_array( $settings['trusted_domains'] )
			? $settings['trusted_domains']
			: array();

		if ( ! empty( $settings['trust_same_site'] ) ) {
			$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
			if ( is_string( $home_host ) && '' !== $home_host ) {
				$trusted_domains[] = strtolower( $home_host );
			}
		}

		$trusted_domains = array_values( array_unique( array_filter( array_map( 'strtolower', $trusted_domains ) ) ) );
		if ( array() === $trusted_domains ) {
			return 0;
		}

		$count = 0;
		foreach ( $urls as $url ) {
			$host = self::extract_host( $url );
			if ( '' === $host ) {
				continue;
			}

			foreach ( $trusted_domains as $trusted_domain ) {
				if ( $host === $trusted_domain || str_ends_with( $host, '.' . $trusted_domain ) ) {
					$count++;
					break;
				}
			}
		}

		return $count;
	}

	private static function extract_host( string $url ): string {
		$url = trim( $url, " \t\n\r\0\x0B.,;:!?()[]{}<>\"'" );
		if ( str_starts_with( $url, '//' ) ) {
			$url = 'https:' . $url;
		} elseif ( ! preg_match( '~^[a-z][a-z0-9+.-]*://~i', $url ) ) {
			$url = 'https://' . $url;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) ) {
			return '';
		}

		$host = strtolower( rtrim( $host, '.' ) );
		return str_starts_with( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}

	/**
	 * @return array{is_url_only:bool,url_count:int,trusted_count:int,reason:string}
	 */
	private static function result( bool $is_url_only, int $url_count, int $trusted_count, string $reason ): array {
		return array(
			'is_url_only'  => $is_url_only,
			'url_count'    => $url_count,
			'trusted_count'=> $trusted_count,
			'reason'       => $reason,
		);
	}
}
