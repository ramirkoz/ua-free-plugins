<?php
/**
 * Ad copy and keywords built from the rendered pages of the site.
 *
 * Each language version of a landing page is fetched from the current site
 * (title, meta description, H1, H2 and a text sample). Translated pages
 * published by KOZ Static Translate therefore give native copy without any
 * Azure request. Headlines and descriptions are filled up to the Google limits
 * (15 x 30 and 4 x 90 characters) with neutral calls to action.
 *
 * @package KOZ_Google_Ads_Campaign_Builder
 */

namespace ramirkz\kozgads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZGADS_Copy {
	public const MAX_HEADLINES    = 15;
	public const MAX_DESCRIPTIONS = 4;
	public const MAX_KEYWORDS     = 12;

	/** Neutral calls to action: language => kind => [headlines, descriptions]. */
	private static function templates(): array {
		return array(
			'en' => array(
				'donate'  => array( array( 'Donate Online', 'Make A Secure Donation', 'Support Our Work', 'Every Donation Helps', 'Give Today' ), array( 'Make a secure online donation to {org}. Every gift supports our work.' ) ),
				'general' => array( array( 'Official Website', 'Learn About Our Work', 'See Our Projects', 'Get Involved Today' ), array( 'Learn about {org}, our projects and how you can help. Visit the official website.' ) ),
			),
			'uk' => array(
				'donate'  => array( array( 'Зробити донат онлайн', 'Безпечний донат', 'Підтримайте нашу роботу', 'Кожен внесок важливий', 'Допоможіть сьогодні' ), array( 'Зробіть безпечний онлайн-донат для {org}. Кожен внесок підтримує нашу роботу.' ) ),
				'general' => array( array( 'Офіційний сайт', 'Дізнайтеся про нашу роботу', 'Наші проєкти', 'Долучайтеся сьогодні' ), array( 'Дізнайтеся про {org}, наші проєкти та як можна допомогти. Відвідайте офіційний сайт.' ) ),
			),
			'de' => array(
				'donate'  => array( array( 'Jetzt online spenden', 'Sicher spenden', 'Unterstützen Sie uns', 'Jede Spende hilft' ), array( 'Spenden Sie sicher online an {org}. Jede Spende unterstützt unsere Arbeit.' ) ),
				'general' => array( array( 'Offizielle Website', 'Mehr über unsere Arbeit', 'Unsere Projekte' ), array( 'Erfahren Sie mehr über {org}, unsere Projekte und wie Sie helfen können.' ) ),
			),
			'fr' => array(
				'donate'  => array( array( 'Faire un don en ligne', 'Don sécurisé', 'Soutenez notre action', 'Chaque don compte' ), array( 'Faites un don sécurisé en ligne à {org}. Chaque don soutient notre action.' ) ),
				'general' => array( array( 'Site officiel', 'Découvrez notre action', 'Nos projets' ), array( 'Découvrez {org}, nos projets et comment vous pouvez aider.' ) ),
			),
			'es' => array(
				'donate'  => array( array( 'Dona en línea', 'Donación segura', 'Apoya nuestro trabajo', 'Cada donación ayuda' ), array( 'Haz una donación segura en línea a {org}. Cada aporte apoya nuestro trabajo.' ) ),
				'general' => array( array( 'Sitio oficial', 'Conoce nuestro trabajo', 'Nuestros proyectos' ), array( 'Conoce {org}, nuestros proyectos y cómo puedes ayudar.' ) ),
			),
			'pl' => array(
				'donate'  => array( array( 'Wpłać darowiznę online', 'Bezpieczna darowizna', 'Wesprzyj naszą pracę', 'Każda wpłata pomaga' ), array( 'Przekaż bezpieczną darowiznę online dla {org}. Każda wpłata wspiera naszą pracę.' ) ),
				'general' => array( array( 'Oficjalna strona', 'Poznaj naszą pracę', 'Nasze projekty' ), array( 'Poznaj {org}, nasze projekty i dowiedz się, jak możesz pomóc.' ) ),
			),
			'pt' => array(
				'donate'  => array( array( 'Doe online', 'Doação segura', 'Apoie o nosso trabalho', 'Cada doação ajuda' ), array( 'Faça uma doação segura online para {org}. Cada doação apoia o nosso trabalho.' ) ),
				'general' => array( array( 'Site oficial', 'Conheça o nosso trabalho', 'Os nossos projetos' ), array( 'Conheça {org}, os nossos projetos e como pode ajudar.' ) ),
			),
			'it' => array(
				'donate'  => array( array( 'Dona online', 'Donazione sicura', 'Sostieni il nostro lavoro', 'Ogni dono aiuta' ), array( 'Fai una donazione sicura online a {org}. Ogni dono sostiene il nostro lavoro.' ) ),
				'general' => array( array( 'Sito ufficiale', 'Scopri il nostro lavoro', 'I nostri progetti' ), array( 'Scopri {org}, i nostri progetti e come puoi aiutare.' ) ),
			),
		);
	}

	public static function has_templates( string $language ): bool {
		return isset( self::templates()[ $language ] );
	}

	private static function stopwords(): array {
		return array_flip(
			array(
				'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'how', 'in', 'is', 'it', 'its', 'of', 'on', 'or', 'our', 'the', 'this', 'to', 'we', 'with', 'you', 'your', 'us', 'all', 'more', 'page', 'home', 'official', 'website', 'site', 'right', 'now', 'here', 'today', 'through', 'via', 'are', 'possible', 'зараз', 'прямо', 'тут', 'сьогодні', 'через', 'можливе', 'можна',
				'і', 'й', 'та', 'в', 'у', 'на', 'з', 'із', 'зі', 'до', 'для', 'про', 'це', 'що', 'як', 'ми', 'наш', 'наша', 'наші', 'наших', 'ваш', 'від', 'за', 'по', 'або', 'не', 'сайт', 'офіційний', 'сторінка', 'головна',
				'der', 'die', 'das', 'und', 'für', 'mit', 'von', 'le', 'la', 'les', 'des', 'et', 'pour', 'el', 'los', 'las', 'y', 'para', 'con', 'del', 'i', 'w', 'na', 'dla', 'o', 'e', 'per', 'il', 'di', 'da', 'do', 'em',
			)
		);
	}

	/**
	 * Title, description, headings and text of one same-site page.
	 *
	 * @return array{ok:bool,status:int,lang:string,title:string,description:string,h1:string,h2:string[],text:string,noindex:bool}
	 */
	public static function signals( string $url ): array {
		$empty = array( 'ok' => false, 'status' => 0, 'lang' => '', 'title' => '', 'description' => '', 'h1' => '', 'h2' => array(), 'text' => '', 'noindex' => false );
		$home  = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( '' === $url || strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) !== $home ) {
			return $empty;
		}
		$cache_key = 'kozgads_sig_' . md5( $url );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['ok'] ) ) {
			return $cached;
		}
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 12,
				'redirection'         => 3,
				'limit_response_size' => 2 * MB_IN_BYTES,
				'user-agent'          => 'Mozilla/5.0 (compatible; KOZ-Google-Ads-Builder/' . KOZGADS_VERSION . '; +' . home_url( '/' ) . ')',
			)
		);
		if ( is_wp_error( $response ) ) {
			return $empty;
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$html   = (string) wp_remote_retrieve_body( $response );
		$result = self::parse( $html );
		$result['status'] = $status;
		$result['ok']     = 200 === $status && '' !== $result['title'];
		set_transient( $cache_key, $result, 10 * MINUTE_IN_SECONDS );
		return $result;
	}

	public static function parse( string $html ): array {
		$text = static fn( string $value ): string => self::clean( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$out  = array( 'ok' => false, 'status' => 0, 'lang' => '', 'title' => '', 'description' => '', 'h1' => '', 'h2' => array(), 'text' => '', 'noindex' => false );
		if ( preg_match( '/<html\b[^>]*\blang=["\']([^"\']+)["\']/i', $html, $m ) ) {
			$out['lang'] = strtolower( sanitize_text_field( $m[1] ) );
		}
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $m ) ) {
			$out['title'] = $text( $m[1] );
		}
		if ( preg_match( '/<meta\b[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m ) || preg_match( '/<meta\b[^>]*content=["\']([^"\']*)["\'][^>]*name=["\']description["\']/i', $html, $m ) ) {
			$out['description'] = $text( $m[1] );
		}
		if ( preg_match( '/<meta\b[^>]*name=["\']robots["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m ) ) {
			$out['noindex'] = str_contains( strtolower( $m[1] ), 'noindex' );
		}
		$body = (string) preg_replace( '/<(script|style|noscript|svg|nav|footer|header)\b[^>]*>.*?<\/\1>/is', ' ', $html );
		if ( preg_match( '/<h1\b[^>]*>(.*?)<\/h1>/is', $body, $m ) ) {
			$out['h1'] = $text( $m[1] );
		}
		if ( preg_match_all( '/<h2\b[^>]*>(.*?)<\/h2>/is', $body, $m ) ) {
			$out['h2'] = array_slice( array_values( array_unique( array_filter( array_map( $text, $m[1] ), static fn( string $heading ): bool => '' !== $heading && ! self::is_ui_text( $heading ) ) ) ), 0, 10 );
		}
		$out['text'] = self::truncate( $text( $body ), 4000 );
		return $out;
	}

	/** Consent, cookie, login and similar interface text. */
	public static function is_ui_text( string $value ): bool {
		return (bool) preg_match( '/privacy|cookie|consent|newsletter|log ?in|sign ?in|search|menu|конфіденц|згод|кукі|файли cookie|пошук|меню|datenschutz|confidentialit|privacidad|prywatno/iu', $value );
	}

	private static function clean( string $value ): string {
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', str_replace( "\xC2\xA0", ' ', $value ) ) );
	}

	public static function length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : (int) preg_match_all( '/./us', $value );
	}

	private static function truncate( string $value, int $limit ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $value, 0, $limit, 'UTF-8' );
		}
		return preg_match( '/^.{0,' . max( 0, $limit ) . '}/us', $value, $m ) ? $m[0] : '';
	}

	/** Text segments split at title separators and sentence punctuation. */
	private static function segments( string $value ): array {
		$parts = preg_split( '/\s*(?:[|–—•·;:!?]|\s-\s|\.(?:\s|$)|,\s)\s*/u', $value ) ?: array();
		return array_values( array_filter( array_map( 'trim', $parts ) ) );
	}

	/** Removes quotation marks so that a clipped brand name never leaves an unpaired quote. */
	private static function unquote( string $value ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', str_replace( array( '"', '“', '”', '«', '»', '„' ), ' ', $value ) ) );
	}

	/** Trims spaces and separators at both ends (multibyte-safe, unlike trim()). */
	private static function trim_marks( string $value ): string {
		return (string) preg_replace( '/^[\s\-–—|:·•,;…]+|[\s\-–—|:·•,;…]+$/u', '', $value );
	}

	public static function lower( string $value ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	/** Text that fits the limit without cutting a word, or '' when it does not fit. */
	public static function fit( string $value, int $limit ): string {
		$value = self::trim_marks( self::unquote( self::clean( $value ) ) );
		if ( '' === $value ) {
			return '';
		}
		if ( self::length( $value ) <= $limit ) {
			return $value;
		}
		$cut = self::truncate( $value, $limit + 1 );
		$cut = (string) preg_replace( '/\s+\S*$/u', '', $cut );
		$cut = self::trim_marks( $cut );
		return self::length( $cut ) >= 0.6 * $limit && self::length( $cut ) <= $limit ? $cut : '';
	}

	/** Brand part of the organisation name: the quoted part when present. */
	public static function brand( string $organisation ): string {
		$organisation = self::clean( $organisation );
		if ( preg_match( '/["«“„]([^"»”“]+)["»”“]/u', $organisation, $m ) ) {
			return trim( $m[1] );
		}
		return trim( $organisation );
	}

	/** Page title without the site name and separators. */
	public static function core_title( string $title, string $organisation ): string {
		$parts = preg_split( '/\s+[|–—\-:·•]\s+/u', $title );
		$parts = is_array( $parts ) ? array_values( array_filter( array_map( 'trim', $parts ) ) ) : array( $title );
		$brand = self::lower( self::brand( $organisation ) );
		$site  = self::lower( (string) get_bloginfo( 'name' ) );
		$keep  = array();
		foreach ( $parts as $part ) {
			$lower = self::lower( $part );
			if ( '' !== $brand && ( $lower === $brand || ( str_contains( $lower, $brand ) && self::length( $lower ) <= self::length( $brand ) + 12 ) ) ) {
				continue;
			}
			if ( '' !== $site && str_contains( $site, $lower ) ) {
				continue;
			}
			$keep[] = $part;
		}
		return ! empty( $keep ) ? $keep[0] : ( $parts[0] ?? $title );
	}

	/** @return string[] lower-case words without stopwords */
	public static function words( string $text ): array {
		preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}\'’-]*/u', self::lower( $text ), $m );
		$stop = self::stopwords();
		return array_values( array_filter( $m[0], static fn( string $word ): bool => self::length( $word ) > 1 && ! isset( $stop[ $word ] ) ) );
	}

	/**
	 * Phrase keywords from page headings, 2–4 words each, scored by how often
	 * their words appear on the page. Brand combinations are kept to one.
	 *
	 * @return string[]
	 */
	/** Most frequent capitalised word of the page (a country, city or cause), lower-case. */
	private static function topic( string $text, array $brand_words ): string {
		preg_match_all( '/\b(\p{Lu}\p{Ll}{3,})\b/u', $text, $m );
		$counts = array_count_values( array_map( array( __CLASS__, 'lower' ), $m[1] ) );
		$stop   = self::stopwords() + array_flip( array( 'donate', 'donation', 'donations', 'charity', 'charitable', 'foundation', 'support', 'help', 'about', 'news', 'reports', 'projects', 'contact', 'contacts', 'team' ) );
		foreach ( array_keys( $counts ) as $word ) {
			if ( isset( $brand_words[ $word ] ) || isset( $stop[ $word ] ) || $counts[ $word ] < 2 || self::is_ui_text( (string) $word ) ) {
				unset( $counts[ $word ] );
			}
		}
		arsort( $counts );
		return (string) ( array_key_first( $counts ) ?? '' );
	}

	/** Typical donation searches; English ones also combine the page topic. */
	private static function donation_keywords( string $language, string $topic ): array {
		$base = array(
			'en' => array( 'donate online', 'make a donation', 'online donation' ),
			'uk' => array( 'зробити донат', 'донат онлайн', 'благодійний внесок' ),
			'de' => array( 'online spenden', 'jetzt spenden' ),
			'fr' => array( 'faire un don', 'don en ligne' ),
			'es' => array( 'donar en línea', 'hacer una donación' ),
			'pl' => array( 'wpłać darowiznę', 'darowizna online' ),
			'pt' => array( 'doar online', 'fazer uma doação' ),
			'it' => array( 'donare online', 'fare una donazione' ),
		)[ $language ] ?? array();
		if ( 'en' === $language && '' !== $topic ) {
			$base = array_merge( array( 'donate to ' . $topic, $topic . ' donation', $topic . ' charity' ), $base );
		}
		return $base;
	}

	public static function keywords( array $signals, string $core_title, string $organisation, bool $is_home, string $kind = 'general', string $language = '' ): array {
		$brand       = self::lower( self::unquote( self::brand( $organisation ) ) );
		$brand_words = array_flip( self::words( $brand ) );
		$text_words  = array_count_values( self::words( (string) $signals['text'] . ' ' . (string) $signals['description'] ) );
		$segments    = array();
		foreach ( array_merge( array( $core_title, (string) $signals['title'], (string) $signals['h1'] ), (array) $signals['h2'] ) as $index => $source ) {
			foreach ( self::segments( (string) $source ) as $segment ) {
				$segments[] = array( $segment, $index < 3 ? 30 : 15 );
			}
		}
		foreach ( self::segments( (string) $signals['description'] ) as $segment ) {
			if ( count( self::words( $segment ) ) <= 4 ) {
				$segments[] = array( $segment, 0 );
			}
		}
		$candidates = array();
		foreach ( $segments as $item ) {
			$words = array_values( array_filter( self::words( $item[0] ), static fn( string $word ): bool => ! isset( $brand_words[ $word ] ) ) );
			$count = count( $words );
			if ( $count < 2 || $count > 7 ) {
				continue;
			}
			if ( $count <= 4 ) {
				$candidates[ implode( ' ', $words ) ] = max( $candidates[ implode( ' ', $words ) ] ?? 0, 40 + $item[1] );
			}
			// Shorter phrases only from the title and headings, not from the description.
			for ( $size = $item[1] > 0 ? min( 3, $count ) : 0; $size >= 2; $size-- ) {
				for ( $start = 0; $start + $size <= $count; $start++ ) {
					$slice = array_slice( $words, $start, $size );
					if ( count( array_unique( $slice ) ) !== $size ) {
						continue;
					}
					$score = $item[1];
					foreach ( $slice as $word ) {
						$score += min( 10, (int) ( $text_words[ $word ] ?? 0 ) );
					}
					$phrase = implode( ' ', $slice );
					$candidates[ $phrase ] = max( $candidates[ $phrase ] ?? 0, $score );
				}
			}
		}
		foreach ( array_keys( $candidates ) as $phrase ) {
			$words = explode( ' ', (string) $phrase );
			if ( count( array_unique( $words ) ) !== count( $words ) || self::is_ui_text( (string) $phrase ) || preg_match( '/monobank|privat|приват|paypal|liqpay|google|apple|visa|mastercard|crypto|крипто/iu', (string) $phrase ) ) {
				unset( $candidates[ $phrase ] );
			}
		}
		arsort( $candidates );
		$out = array_slice( array_map( 'strval', array_keys( $candidates ) ), 0, self::MAX_KEYWORDS );
		// Common donation searches for donation pages.
		if ( 'donate' === $kind ) {
			$generic = self::donation_keywords( $language, self::topic( (string) $signals['description'] . ' ' . (string) $signals['text'], $brand_words ) );
			$out     = array_merge( array_slice( $out, 0, self::MAX_KEYWORDS - count( $generic ) ), $generic );
		}
		// One brand keyword, on the home page group only.
		if ( $is_home && count( $brand_words ) >= 2 ) {
			array_unshift( $out, $brand );
		}
		return array_slice( array_values( array_unique( $out ) ), 0, self::MAX_KEYWORDS );
	}

	/**
	 * Headlines, descriptions, keywords and sitelink text for one page in one language.
	 *
	 * @return array{name:string,headlines:string[],descriptions:string[],keywords:string[],sitelink:string,warnings:string[]}
	 */
	public static function group( array $signals, string $language, string $organisation, string $kind, bool $is_home ): array {
		$brand     = self::unquote( self::brand( $organisation ) );
		$core      = self::unquote( self::core_title( (string) $signals['title'], $organisation ) );
		$templates = self::templates()[ $language ][ 'donate' === $kind ? 'donate' : 'general' ] ?? array( array(), array() );
		$headlines = array();
		$add       = static function ( string $value ) use ( &$headlines, $language, $brand ): void {
			$value = self::fit( $value, 30 );
			if ( '' !== $language && self::script_mismatch( str_replace( $brand, '', $value ), $language, 3, 0.5 ) ) {
				return;
			}
			if ( '' !== $value && ! isset( $headlines[ self::lower( $value ) ] ) && count( $headlines ) < self::MAX_HEADLINES ) {
				$headlines[ self::lower( $value ) ] = $value;
			}
		};
		$add( $core );
		foreach ( array_merge( self::segments( (string) $signals['h1'] ), self::segments( (string) $signals['title'] ) ) as $segment ) {
			$add( $segment );
		}
		$add( $brand );
		// Headings and description parts only when they fit whole.
		foreach ( (array) $signals['h2'] as $h2 ) {
			if ( self::length( self::unquote( (string) $h2 ) ) <= 30 ) {
				$add( (string) $h2 );
			}
		}
		foreach ( $templates[0] as $template ) {
			$add( (string) $template );
		}
		if ( '' !== $brand && self::length( $brand . ' – ' . $core ) <= 30 ) {
			$add( $brand . ' – ' . $core );
		}
		foreach ( self::segments( (string) $signals['description'] ) as $segment ) {
			if ( self::length( self::unquote( $segment ) ) <= 30 && count( self::words( $segment ) ) >= 2 ) {
				$add( $segment );
			}
		}

		$descriptions = array();
		$add_d        = static function ( string $value ) use ( &$descriptions, $language, $brand ): void {
			if ( '' !== $language && self::script_mismatch( str_replace( $brand, '', $value ), $language, 3, 0.5 ) ) {
				return;
			}
			$value = trim( (string) preg_replace( '/^.*\|\s*/u', '', $value ) );
			if ( preg_match( '/\d.*\d.*\d/u', $value ) || count( preg_split( '/\s+/u', $value ) ?: array() ) < 6 ) {
				return;
			}
			$value = self::fit( $value, 90 );
			if ( '' !== $value && self::length( $value ) >= 35 && ! isset( $descriptions[ self::lower( $value ) ] ) && count( $descriptions ) < self::MAX_DESCRIPTIONS ) {
				$descriptions[ self::lower( $value ) ] = $value;
			}
		};
		$add_d( (string) $signals['description'] );
		foreach ( preg_split( '/(?<=[.!?])\s+/u', (string) $signals['description'] ) ?: array() as $sentence ) {
			$add_d( (string) $sentence );
		}
		foreach ( $templates[1] as $template ) {
			$add_d( str_replace( '{org}', $brand, (string) $template ) );
		}
		// Body sentences only to reach two descriptions, and only complete sentences.
		foreach ( preg_split( '/(?<=[.!?])\s+/u', self::truncate( (string) $signals['text'], 2000 ) ) ?: array() as $sentence ) {
			$sentence = trim( (string) $sentence );
			if ( count( $descriptions ) < 2 && preg_match( '/^\p{Lu}.{30,140}[.!]$/u', $sentence ) ) {
				$add_d( $sentence );
			}
		}

		$warnings = array();
		if ( count( $headlines ) < 8 ) {
			$warnings[] = sprintf( 'only %d headlines (Google recommends at least 8; ad strength may be Poor)', count( $headlines ) );
		}
		if ( count( $descriptions ) < 2 ) {
			$warnings[] = 'fewer than 2 descriptions';
		}
		return array(
			'name'         => '' !== $core ? $core : (string) $signals['title'],
			'headlines'    => array_values( $headlines ),
			'descriptions' => array_values( $descriptions ),
			// Keywords in another script (an untranslated part of the page) are left out; the brand is kept.
			'keywords'     => array_values(
				array_filter(
					self::keywords( $signals, $core, $organisation, $is_home, $kind, $language ),
					static fn( string $keyword ): bool => '' === $language || self::lower( $brand ) === $keyword || ! self::script_mismatch( $keyword, $language, 3, 0.5 )
				)
			),
			'sitelink'     => self::sitelink( array( $core, trim( str_replace( $brand, '', $core ) ), (string) $signals['h1'] ) ),
			'warnings'     => $warnings,
		);
	}

	/** First candidate that fits 25 characters whole, otherwise the first one clipped. */
	private static function sitelink( array $candidates ): string {
		foreach ( $candidates as $candidate ) {
			$candidate = self::trim_marks( self::unquote( self::clean( (string) $candidate ) ) );
			if ( '' !== $candidate && self::length( $candidate ) <= 25 && count( self::words( $candidate ) ) >= 1 ) {
				return $candidate;
			}
		}
		return self::fit( (string) ( $candidates[0] ?? '' ), 25 );
	}

	/** True when the visible text is clearly written in another script than the campaign language. */
	public static function script_mismatch( string $text, string $language, int $min_letters = 200, float $share = 0.4 ): bool {
		$counts = array(
			'latin'      => (int) preg_match_all( '/\p{Latin}/u', $text ),
			'cyrillic'   => (int) preg_match_all( '/\p{Cyrillic}/u', $text ),
			'arabic'     => (int) preg_match_all( '/\p{Arabic}/u', $text ),
			'devanagari' => (int) preg_match_all( '/\p{Devanagari}/u', $text ),
			'cjk'        => (int) preg_match_all( '/[\p{Han}\p{Hiragana}\p{Katakana}]/u', $text ),
		);
		$expected = array( 'uk' => 'cyrillic', 'ar' => 'arabic', 'hi' => 'devanagari', 'zh' => 'cjk', 'ja' => 'cjk' )[ $language ] ?? 'latin';
		$total    = array_sum( $counts );
		return $total > $min_letters && $counts[ $expected ] < $share * $total;
	}
}
