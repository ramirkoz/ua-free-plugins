<?php
/**
 * URL-only comment detector.
 *
 * @package KOZ_URL_Only_Comment_Spam
 */

declare(strict_types=1);

namespace ramirkz\kozurlspam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Detector {
	/**
	 * Top-level domains that count as a link when a value has no scheme and no "www.".
	 * Short code-like suffixes (md, py, rs, sh, pl and similar) are deliberately left out,
	 * so that "Node.js" or "main.rs" written as a whole comment is not treated as a URL.
	 */
	private const BARE_DOMAIN_TLDS = array(
		'com', 'net', 'org', 'info', 'biz', 'xyz', 'top', 'site', 'online', 'shop', 'store', 'club', 'live', 'life',
		'pro', 'link', 'click', 'win', 'bid', 'vip', 'icu', 'buzz', 'fun', 'space', 'website', 'tech', 'app', 'dev',
		'blog', 'news', 'today', 'world', 'email', 'agency', 'digital', 'media', 'network', 'solutions', 'services',
		'group', 'company', 'center', 'casino', 'bet', 'poker', 'loan', 'loans', 'money', 'finance', 'trade', 'market',
		'deals', 'sale', 'review', 'reviews', 'guru', 'expert', 'cloud', 'host', 'press', 'monster', 'rest', 'cyou',
		'cfd', 'sbs', 'mom', 'lol', 'quest', 'beauty', 'hair', 'skin', 'makeup', 'boutique', 'fashion', 'dating',
		'io', 'co', 'me', 'tv', 'cc', 'ws', 'to', 'ly', 'gl', 'gg', 'su', 'us', 'uk', 'ca', 'au', 'nz', 'ie', 'de',
		'fr', 'es', 'it', 'nl', 'be', 'ch', 'at', 'se', 'no', 'dk', 'fi', 'cz', 'sk', 'hu', 'ro', 'bg', 'gr', 'pt',
		'ru', 'ua', 'by', 'kz', 'lt', 'lv', 'ee', 'tr', 'il', 'in', 'pk', 'bd', 'cn', 'hk', 'tw', 'jp', 'kr', 'vn',
		'th', 'id', 'my', 'sg', 'ph', 'br', 'ar', 'mx', 'cl', 'pe', 'za', 'ng', 'ke', 'eg', 'ae', 'sa', 'ir', 'eu',
	);

	/**
	 * Analyze comment text without storing it.
	 *
	 * @param string              $content  Raw comment content.
	 * @param array<string,mixed> $settings Sanitized plugin settings.
	 * @return array{is_url_only:bool,url_count:int,trusted_count:int,external_count:int,text_chars:int,reason:string}
	 */
	public static function analyze( string $content, array $settings ): array {
		$normalized = self::normalize_content( $content );

		if ( '' === $normalized ) {
			return self::result( false, 0, 0, 0, 'empty' );
		}

		$matches = array();
		$remainder = preg_replace_callback(
			self::url_pattern(),
			static function ( array $match ) use ( &$matches ): string {
				if ( self::counts_as_url( (string) $match[1] ) ) {
					$matches[] = (string) $match[1];
					return ' ';
				}
				return (string) $match[0];
			},
			$normalized
		);

		$text_chars = null === $remainder ? 0 : self::text_length( $remainder );

		if ( null === $remainder || array() === $matches ) {
			return self::result( false, 0, 0, $text_chars, 'no_urls' );
		}

		$url_count = count( $matches );
		$trusted_count = self::count_trusted_urls( $matches, $settings );
		// Links to this site are never a spam signal for the low-text rules, whatever trust_same_site says.
		$external_count = $url_count - self::count_trusted_urls( $matches, array_merge( $settings, array( 'trust_same_site' => true ) ) );

		if ( 0 !== $text_chars ) {
			return self::result( false, $url_count, $trusted_count, $text_chars, 'contains_text', $external_count );
		}

		$minimum = isset( $settings['minimum_urls'] ) ? max( 1, (int) $settings['minimum_urls'] ) : 1;
		if ( $url_count < $minimum ) {
			return self::result( false, $url_count, $trusted_count, 0, 'below_threshold' );
		}

		if ( $trusted_count === $url_count ) {
			return self::result( false, $url_count, $trusted_count, 0, 'all_trusted' );
		}

		$result = self::result( true, $url_count, $trusted_count, 0, 'url_only' );

		/**
		 * Filter the final URL-only decision.
		 *
		 * No comment text or URL is passed to preserve privacy.
		 *
		 * @param bool                 $is_url_only Final decision.
		 * @param array<string,mixed>  $summary     Privacy-safe summary.
		 */
		$result['is_url_only'] = (bool) apply_filters(
			'kozurlspam_is_url_only',
			$result['is_url_only'],
			$result
		);

		return $result;
	}

	/**
	 * Whether a URL value (for example the comment author's website field) points to a trusted host.
	 *
	 * @param array<string,mixed> $settings Sanitized settings.
	 */
	public static function is_trusted_url( string $url, array $settings ): bool {
		$settings = array_merge( $settings, array( 'trust_same_site' => true ) );
		return '' !== trim( $url ) && 1 === self::count_trusted_urls( array( $url ), $settings );
	}

	/**
	 * Visible characters left after URLs, whitespace, punctuation and symbols are removed.
	 */
	private static function text_length( string $text ): int {
		$letters = preg_replace( '/[\s\p{P}\p{S}]+/u', '', $text );
		return null === $letters ? 0 : (int) mb_strlen( $letters );
	}

	private static function normalize_content( string $content ): string {
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$content = self::reveal_markup_links( $content );
		$content = preg_replace( '/<(?:br|\/p|\/div|\/li)\b[^>]*>/i', "\n", $content );
		$content = wp_strip_all_tags( (string) $content, false );
		$content = preg_replace( '/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', ' ', (string) $content );
		$content = self::deobfuscate( (string) $content );
		$content = preg_replace( '/\s+/u', ' ', (string) $content );
		return trim( (string) $content );
	}

	/**
	 * Turn BBCode and Markdown links into visible text plus the link target, so that
	 * "[url=https://spam.example]Click[/url]" is analysed like "Click https://spam.example".
	 */
	private static function reveal_markup_links( string $content ): string {
		$content = (string) preg_replace( '~\[url=([^\]\s]+)\](.*?)\[/url\]~is', ' $2 $1 ', $content );
		$content = (string) preg_replace( '~\[url\](.*?)\[/url\]~is', ' $1 ', $content );
		$content = (string) preg_replace( '~\[([^\]]*)\]\(((?:https?:)?//[^)\s]+)\)~i', ' $1 $2 ', $content );
		// HTML anchors keep their href visible to the detector even when the anchor text is not a URL.
		// When the anchor text is the link itself, the link is counted once.
		return (string) preg_replace_callback(
			'~<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>~is',
			static function ( array $match ): string {
				$href = trim( (string) $match[2] );
				$text = trim( wp_strip_all_tags( (string) $match[3] ) );
				$bare = static fn( string $value ): string => rtrim( (string) preg_replace( '~^(?:https?:)?//(?:www\.)?~i', '', $value ), '/' );
				$same = '' === $text || $bare( $text ) === $bare( $href );
				return $same ? ' ' . $href . ' ' : ' ' . $text . ' ' . $href . ' ';
			},
			$content
		);
	}

	/**
	 * Undo common link obfuscation: hxxp://, example[.]com, example(dot)com.
	 */
	private static function deobfuscate( string $content ): string {
		$content = (string) preg_replace( '~\bh(?:xx|\*\*|tt)p(s?)(?::|\[:\])//~i', 'http$1://', $content );
		$content = (string) preg_replace( '~\s*(?:\[\s*(?:\.|dot)\s*\]|\(\s*(?:\.|dot)\s*\)|\{\s*(?:\.|dot)\s*\})\s*~i', '.', $content );
		return $content;
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
	 * Values with a scheme, "//" or "www." always count. Bare domains count when they have a
	 * path, an IDN suffix or a known top-level domain.
	 */
	private static function counts_as_url( string $value ): bool {
		if ( preg_match( '~^(?:(?:https?|ftp)://|//|www\.)~i', $value ) ) {
			return true;
		}

		if ( preg_match( '~[/?#:]~', $value ) ) {
			return true;
		}

		$host = strtolower( rtrim( $value, '.' ) );
		$tld = (string) substr( (string) strrchr( $host, '.' ), 1 );
		if ( str_starts_with( $tld, 'xn--' ) ) {
			return true;
		}

		/**
		 * Filter the top-level domains that make a bare "name.tld" value count as a URL.
		 *
		 * @param string[] $tlds Lower-case top-level domains without a leading dot.
		 */
		$tlds = (array) apply_filters( 'kozurlspam_bare_domain_tlds', self::BARE_DOMAIN_TLDS );
		return in_array( $tld, $tlds, true );
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
				$trusted_domains[] = strtolower( (string) preg_replace( '/^www\./', '', $home_host ) );
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
	 * @return array{is_url_only:bool,url_count:int,trusted_count:int,external_count:int,text_chars:int,reason:string}
	 */
	private static function result( bool $is_url_only, int $url_count, int $trusted_count, int $text_chars, string $reason, ?int $external_count = null ): array {
		return array(
			'is_url_only'    => $is_url_only,
			'url_count'      => $url_count,
			'trusted_count'  => $trusted_count,
			'external_count' => max( 0, $external_count ?? ( $url_count - $trusted_count ) ),
			'text_chars'     => $text_chars,
			'reason'         => $reason,
		);
	}
}
