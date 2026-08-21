<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZSEO_Core {
	public const OPTION = 'kozseo_settings';
	private const LEGACY_OPTION = 'uafree_seo_core_settings';

	private static array $context_cache = array();


	public static function migrate_legacy_settings(): void {
		if ( false !== get_option( self::OPTION, false ) ) {
			return;
		}
		$legacy = get_option( self::LEGACY_OPTION, null );
		if ( ! is_array( $legacy ) ) {
			return;
		}
		$settings = self::sanitize_settings( wp_parse_args( $legacy, self::defaults() ) );
		add_option( self::OPTION, $settings, '', false );
	}

	private static function legacy_meta_key( string $canonical ): string {
		$map = array(
			'kozseo_title'           => 'uafree_seo_title',
			'kozseo_description'     => 'uafree_seo_description',
			'kozseo_canonical'       => 'uafree_seo_canonical',
			'kozseo_social_image_id' => 'uafree_social_image_id',
			'kozseo_noindex'         => 'uafree_seo_noindex',
		);
		return (string) ( $map[ $canonical ] ?? '' );
	}

	private static function post_meta_value( int $post_id, string $canonical ) {
		if ( metadata_exists( 'post', $post_id, $canonical ) ) {
			return get_post_meta( $post_id, $canonical, true );
		}
		$legacy = self::legacy_meta_key( $canonical );
		return '' !== $legacy ? get_post_meta( $post_id, $legacy, true ) : '';
	}

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_rewrites' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'serve_discovery_files' ), 0 );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_document_title' ), 99 );
		add_filter( 'wp_robots', array( __CLASS__, 'filter_wp_robots' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'render_head' ), 1 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ), 10, 2 );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'filter_sitemap_post_types' ) );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'filter_sitemap_provider' ), 10, 2 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 20, 2 );
	}

	public static function activate(): void {
		self::migrate_legacy_settings();
		$settings = get_option( self::OPTION, null );
		if ( ! is_array( $settings ) ) {
			$settings = self::defaults();
			$settings['enabled'] = KOZSEO_Scanner::conflicting_active_plugin() ? 0 : 1;
			add_option( self::OPTION, $settings, '', false );
		}
		self::register_rewrites();
		flush_rewrite_rules( false );
	}

	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}

	public static function defaults(): array {
		return array(
			'enabled'                 => 1,
			'separator'               => '|',
			'organization_name'       => get_bloginfo( 'name' ),
			'organization_type'       => 'Organization',
			'organization_description'=> get_bloginfo( 'description' ),
			'organization_logo_id'    => 0,
			'default_social_image_id' => 0,
			'noindex_search'          => 1,
			'noindex_author'          => 1,
			'noindex_date'            => 1,
			'llms_txt'                => 1,
			'ai_manifest'             => 1,
			'excluded_post_types'     => array( 'attachment' ),
		);
	}

	public static function settings(): array {
		self::migrate_legacy_settings();
		$saved  = get_option( self::OPTION, array() );
		$merged = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		return self::sanitize_settings( $merged );
	}

	private static function input_string( $value, string $fallback = '' ): string {
		return is_scalar( $value ) ? (string) $value : $fallback;
	}

	private static function request_uri(): string {
		$raw = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '/';
		return is_string( $raw ) && '' !== $raw ? $raw : '/';
	}

	public static function sanitize_settings( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$types = array_keys( get_post_types( array( 'public' => true ), 'names' ) );
		$excluded_input = isset( $input['excluded_post_types'] ) && is_array( $input['excluded_post_types'] )
			? $input['excluded_post_types']
			: array();
		$excluded = array();
		foreach ( $excluded_input as $post_type ) {
			if ( ! is_string( $post_type ) ) {
				continue;
			}
			$post_type = sanitize_key( $post_type );
			if ( in_array( $post_type, $types, true ) ) {
				$excluded[] = $post_type;
			}
		}

		$type = sanitize_text_field( self::input_string( $input['organization_type'] ?? 'Organization', 'Organization' ) );
		if ( ! in_array( $type, array( 'Organization', 'NGO', 'EducationalOrganization', 'GovernmentOrganization' ), true ) ) {
			$type = 'Organization';
		}
		$separator = self::input_string( $input['separator'] ?? '|', '|' );
		if ( ! in_array( $separator, array( '|', '-', '•', '·' ), true ) ) {
			$separator = '|';
		}

		return array(
			'enabled'                  => empty( $input['enabled'] ) ? 0 : 1,
			'separator'                => $separator,
			'organization_name'        => sanitize_text_field( self::input_string( $input['organization_name'] ?? '' ) ),
			'organization_type'        => $type,
			'organization_description' => sanitize_textarea_field( self::input_string( $input['organization_description'] ?? '' ) ),
			'organization_logo_id'     => absint( is_scalar( $input['organization_logo_id'] ?? null ) ? $input['organization_logo_id'] : 0 ),
			'default_social_image_id'  => absint( is_scalar( $input['default_social_image_id'] ?? null ) ? $input['default_social_image_id'] : 0 ),
			'noindex_search'           => empty( $input['noindex_search'] ) ? 0 : 1,
			'noindex_author'           => empty( $input['noindex_author'] ) ? 0 : 1,
			'noindex_date'             => empty( $input['noindex_date'] ) ? 0 : 1,
			'llms_txt'                 => empty( $input['llms_txt'] ) ? 0 : 1,
			'ai_manifest'              => empty( $input['ai_manifest'] ) ? 0 : 1,
			'excluded_post_types'      => array_values( array_unique( $excluded ) ),
		);
	}

	public static function is_enabled(): bool {
		return ! empty( self::settings()['enabled'] );
	}

	private static function technical_noindex_post_types(): array {
		return array( 'pagelayer-template' );
	}

	public static function register_rewrites(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?kozseo_llms=1', 'top' );
		add_rewrite_rule( '^\.well-known/koz-ai-manifest\.json$', 'index.php?kozseo_ai_manifest=1', 'top' );
		add_rewrite_rule( '^\.well-known/uafree-ai-manifest\.json$', 'index.php?kozseo_ai_manifest=1', 'top' );
	}

	public static function query_vars( array $vars ): array {
		$vars[] = 'kozseo_llms';
		$vars[] = 'kozseo_ai_manifest';
		// Backward compatibility for rewrite rules cached before 2.1.3.
		$vars[] = 'uafree_seo_llms';
		$vars[] = 'uafree_seo_ai_manifest';
		return array_values( array_unique( $vars ) );
	}

	public static function serve_discovery_files(): void {
		$llms_requested     = (bool) get_query_var( 'kozseo_llms' ) || (bool) get_query_var( 'uafree_seo_llms' );
		$manifest_requested = (bool) get_query_var( 'kozseo_ai_manifest' ) || (bool) get_query_var( 'uafree_seo_ai_manifest' );
		if ( ! $llms_requested && ! $manifest_requested ) {
			return;
		}

		$settings = self::settings();
		$allowed  = self::is_enabled()
			&& ! ( $llms_requested && $manifest_requested )
			&& ( ! $llms_requested || ! empty( $settings['llms_txt'] ) )
			&& ( ! $manifest_requested || ! empty( $settings['ai_manifest'] ) );
		if ( ! $allowed ) {
			self::mark_discovery_not_found();
			return;
		}

		if ( $llms_requested ) {
			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'X-Content-Type-Options: nosniff' );
			echo self::llms_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text response built from sanitized settings.
			exit;
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		echo wp_json_encode( self::ai_manifest(), JSON_PRETTY_PRINT );
		exit;
	}

	private static function mark_discovery_not_found(): void {
		global $wp_query;
		if ( is_object( $wp_query ) && method_exists( $wp_query, 'set_404' ) ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		nocache_headers();
	}

	private static function llms_content(): string {
		$settings = self::settings();
		$lines = array(
			'# ' . ( $settings['organization_name'] ?: get_bloginfo( 'name' ) ),
			'',
			$settings['organization_description'] ?: get_bloginfo( 'description' ),
			'',
			'- Website: ' . home_url( '/' ),
		);
		foreach ( self::sitemaps() as $sitemap ) {
			$lines[] = '- Sitemap: ' . $sitemap;
		}
		return implode( "\n", array_map( 'strval', $lines ) ) . "\n";
	}

	private static function ai_manifest(): array {
		$settings = self::settings();
		$sitemaps = self::sitemaps();
		return array(
			'name'         => $settings['organization_name'] ?: get_bloginfo( 'name' ),
			'description'  => $settings['organization_description'] ?: get_bloginfo( 'description' ),
			'url'          => home_url( '/' ),
			'sitemap'      => $sitemaps[0] ?? home_url( '/wp-sitemap.xml' ),
			'sitemaps'     => $sitemaps,
			'generated_by' => 'KOZ SEO Core ' . KOZSEO_VERSION,
		);
	}


	private static function sitemaps(): array {
		$sitemaps = array( home_url( '/wp-sitemap.xml' ) );
		$extra = apply_filters( 'kozseo_additional_sitemaps', array() );
		foreach ( is_array( $extra ) ? $extra : array() as $url ) {
			if ( ! is_string( $url ) ) {
				continue;
			}
			$url = self::normalize_http_url_candidate( $url );
			if ( '' !== $url ) {
				$sitemaps[] = $url;
			}
		}
		return array_values( array_unique( $sitemaps ) );
	}

	public static function filter_wp_robots( array $robots ): array {
		if ( ! self::is_enabled() || is_admin() || is_feed() || wp_is_json_request() ) {
			return $robots;
		}

		foreach ( array( 'index', 'noindex', 'follow', 'nofollow', 'noarchive', 'nosnippet', 'max-image-preview' ) as $key ) {
			unset( $robots[ $key ] );
		}

		foreach ( self::context()['robots'] as $directive ) {
			if ( ! is_string( $directive ) || '' === $directive ) {
				continue;
			}
			if ( str_starts_with( $directive, 'max-image-preview:' ) ) {
				$robots['max-image-preview'] = substr( $directive, strlen( 'max-image-preview:' ) );
				continue;
			}
			$robots[ $directive ] = true;
		}

		return $robots;
	}

	public static function filter_document_title( string $title ): string {
		if ( ! self::is_enabled() || is_admin() || is_feed() ) {
			return $title;
		}
		$context = self::context();
		return $context['title'] ?: $title;
	}

	public static function render_head(): void {
		if ( ! self::is_enabled() || is_admin() || is_feed() || wp_is_json_request() ) {
			return;
		}
		$context = self::context();
		if ( $context['description'] ) {
			echo '<meta name="description" content="' . esc_attr( $context['description'] ) . '">' . "\n";
		}
		if ( $context['canonical'] ) {
			echo '<link rel="canonical" href="' . esc_url( $context['canonical'] ) . '">' . "\n";
		}
		echo '<meta property="og:type" content="' . esc_attr( $context['og_type'] ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $context['title'] ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $context['description'] ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $context['canonical'] ) . '">' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
		if ( $context['image'] ) {
			echo '<meta property="og:image" content="' . esc_url( $context['image'] ) . '">' . "\n";
		}
		echo '<meta name="twitter:card" content="' . esc_attr( $context['image'] ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $context['title'] ) . '">' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $context['description'] ) . '">' . "\n";
		if ( $context['image'] ) {
			echo '<meta name="twitter:image" content="' . esc_url( $context['image'] ) . '">' . "\n";
		}
		foreach ( self::hreflang_links( $context['canonical'] ) as $language => $url ) {
			echo '<link rel="alternate" hreflang="' . esc_attr( $language ) . '" href="' . esc_url( $url ) . '">' . "\n";
		}
		echo '<script type="application/ld+json">' . wp_json_encode( self::schema( $context ) ) . '</script>' . "\n";
	}

	private static function context(): array {
		$key = md5( self::request_uri() );
		if ( isset( self::$context_cache[ $key ] ) ) {
			return self::$context_cache[ $key ];
		}
		$settings = self::settings();
		$site_name = $settings['organization_name'] ?: get_bloginfo( 'name' );
		$separator = ' ' . $settings['separator'] . ' ';
		$title = get_bloginfo( 'name' );
		$description = $settings['organization_description'] ?: get_bloginfo( 'description' );
		$canonical = home_url( '/' );
		$robots = array( 'index', 'follow', 'max-image-preview:large' );
		$image = self::attachment_url( (int) $settings['default_social_image_id'] );
		$og_type = 'website';

		$post = self::request_post();
		if ( $post instanceof WP_Post ) {
			$manual_title = trim( self::input_string( self::post_meta_value( $post->ID, 'kozseo_title' ) ) );
			$manual_desc  = trim( self::input_string( self::post_meta_value( $post->ID, 'kozseo_description' ) ) );
			$manual_can   = self::normalize_http_url_candidate( self::post_meta_value( $post->ID, 'kozseo_canonical' ) );
			$title = $manual_title ?: wp_strip_all_tags( get_the_title( $post ) );
			if ( ! str_contains( mb_strtolower( $title ), mb_strtolower( $site_name ) ) ) {
				$title .= $separator . $site_name;
			}
			$description = $manual_desc ?: self::excerpt( $post, $site_name );
			$canonical = $manual_can ?: self::normalize_http_url_candidate( get_permalink( $post ), home_url( '/' ) );
			$noindex = (bool) self::post_meta_value( $post->ID, 'kozseo_noindex' );
			if ( $noindex || in_array( $post->post_type, self::technical_noindex_post_types(), true ) ) {
				$robots = array( 'noindex', 'follow' );
			}
			$featured = get_post_thumbnail_id( $post );
			$social_meta = self::post_meta_value( $post->ID, 'kozseo_social_image_id' );
			$social      = absint( is_scalar( $social_meta ) ? $social_meta : 0 );
			$image = self::attachment_url( $social ?: ( $featured ?: (int) $settings['default_social_image_id'] ) );
			$og_type = 'post' === $post->post_type ? 'article' : 'website';		} elseif ( is_search() ) {
			/* translators: %s: search query. */
			$title = sprintf( __( 'Search results for “%s”', 'koz-seo-core' ), get_search_query() ) . $separator . $site_name;
			/* translators: %s: website name. */
			$description = sprintf( __( 'Search results on %s.', 'koz-seo-core' ), $site_name );
			$canonical = get_search_link();
			if ( ! empty( $settings['noindex_search'] ) ) {
				$robots = array( 'noindex', 'follow' );
			}
		} elseif ( is_author() ) {
			$title = get_the_archive_title() . $separator . $site_name;
			$canonical = get_author_posts_url( (int) get_queried_object_id() );
			if ( ! empty( $settings['noindex_author'] ) ) {
				$robots = array( 'noindex', 'follow' );
			}
		} elseif ( is_date() ) {
			$title = get_the_archive_title() . $separator . $site_name;
			$canonical = self::current_url();
			if ( ! empty( $settings['noindex_date'] ) ) {
				$robots = array( 'noindex', 'follow' );
			}
		} elseif ( is_archive() ) {
			$title = wp_strip_all_tags( get_the_archive_title() ) . $separator . $site_name;
			$description = wp_strip_all_tags( get_the_archive_description() ) ?: $description;
			$canonical = self::current_url();
		} elseif ( is_404() ) {
			$title = __( 'Page not found', 'koz-seo-core' ) . $separator . $site_name;
			$description = __( 'The requested page could not be found.', 'koz-seo-core' );
			$canonical = '';
			$robots = array( 'noindex', 'follow' );
		} elseif ( is_front_page() || is_home() ) {
			$title = get_bloginfo( 'name' );
			$description = $settings['organization_description'] ?: get_bloginfo( 'description' );
			$canonical = home_url( '/' );
		}

		$context = array(
			'title'       => trim( wp_strip_all_tags( (string) $title ) ),
			'description' => self::truncate( trim( wp_strip_all_tags( (string) $description ) ), 170 ),
			'canonical'   => esc_url_raw( (string) $canonical ),
			'robots'      => $robots,
			'image'       => esc_url_raw( (string) $image ),
			'og_type'     => $og_type,
		);
		$filtered = apply_filters( 'kozseo_context', $context );
		// Backward compatibility for the 2.1.x pre-re-audit public hook.
		$filtered = apply_filters( 'kozseocore_context', is_array( $filtered ) ? $filtered : $context );
		self::$context_cache[ $key ] = self::normalize_context( is_array( $filtered ) ? $filtered : $context, $context );
		return self::$context_cache[ $key ];
	}

	/**
	 * Resolve the current public request to a post even when a theme or page
	 * builder renders a valid permalink without setting WordPress singular
	 * conditionals. This prevents custom-rendered pages from inheriting the
	 * homepage SEO context.
	 */
	private static function request_post(): ?WP_Post {
		if ( is_search() || is_author() || is_date() || is_archive() || is_404() ) {
			return null;
		}

		$uri   = self::request_uri();
		$parts = wp_parse_url( $uri );
		$path  = '/' . ltrim( (string) ( $parts['path'] ?? '/' ), '/' );

		/*
		 * Some page builders can leave is_front_page()/is_home() true while
		 * serving another published permalink. Trust those conditionals only
		 * for the real site root; otherwise resolve the requested path first.
		 */
		$home_path = '/' . ltrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		$home_path = '/' === $home_path ? '/' : trailingslashit( $home_path );
		$request_path = '/' === $path ? '/' : trailingslashit( $path );
		if ( $request_path === $home_path && ( is_front_page() || is_home() ) ) {
			return null;
		}

		if ( is_singular() ) {
			$queried = get_queried_object();
			if ( $queried instanceof WP_Post ) {
				return $queried;
			}
		}

		$url = home_url( $path );
		$post_id = (int) url_to_postid( $url );

		if ( $post_id <= 0 ) {
			$slug_path = trim( rawurldecode( $path ), '/' );
			$home_slug = trim( rawurldecode( $home_path ), '/' );
			if ( '' !== $home_slug && str_starts_with( $slug_path . '/', $home_slug . '/' ) ) {
				$slug_path = trim( substr( $slug_path, strlen( $home_slug ) ), '/' );
			}
			if ( '' !== $slug_path ) {
				$resolved = get_page_by_path( $slug_path, OBJECT, get_post_types( array( 'public' => true ), 'names' ) );
				if ( $resolved instanceof WP_Post ) {
					$post_id = (int) $resolved->ID;
				}
			}
		}

		if ( $post_id <= 0 ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return null;
		}

		$post_type = get_post_type_object( $post->post_type );
		return $post_type && ! empty( $post_type->public ) ? $post : null;
	}

	private static function normalize_context( array $candidate, array $fallback ): array {
		$fallback_robots  = isset( $fallback['robots'] ) && is_array( $fallback['robots'] ) ? $fallback['robots'] : array( 'index', 'follow' );
		$candidate_robots = isset( $candidate['robots'] ) && is_array( $candidate['robots'] ) ? $candidate['robots'] : $fallback_robots;
		$robots = array();
		foreach ( $candidate_robots as $directive ) {
			if ( ! is_string( $directive ) ) {
				continue;
			}
			$directive = strtolower( sanitize_text_field( $directive ) );
			if ( preg_match( '/^(?:index|noindex|follow|nofollow|noarchive|nosnippet|max-image-preview:(?:none|standard|large))$/', $directive ) ) {
				$robots[] = $directive;
			}
		}
		if ( ! $robots ) {
			$robots = $fallback_robots;
		}

		$title       = self::context_string( $candidate, 'title', $fallback );
		$description = self::context_string( $candidate, 'description', $fallback );
		$canonical   = self::normalize_http_url_candidate(
			$candidate['canonical'] ?? null,
			self::context_string( $fallback, 'canonical', array( 'canonical' => '' ) )
		);
		$image = self::normalize_http_url_candidate(
			$candidate['image'] ?? null,
			self::context_string( $fallback, 'image', array( 'image' => '' ) )
		);

		$og_type_value = self::context_string( $candidate, 'og_type', $fallback );
		$og_type       = sanitize_key( $og_type_value );
		if ( ! in_array( $og_type, array( 'website', 'article' ), true ) ) {
			$og_type = self::context_string( $fallback, 'og_type', array( 'og_type' => 'website' ) );
		}

		return array(
			'title'       => trim( wp_strip_all_tags( $title ) ),
			'description' => self::truncate( trim( wp_strip_all_tags( $description ) ), 170 ),
			'canonical'   => $canonical,
			'robots'      => array_values( array_unique( $robots ) ),
			'image'       => $image,
			'og_type'     => $og_type,
		);
	}

	private static function context_string( array $source, string $key, array $fallback ): string {
		if ( array_key_exists( $key, $source ) && is_string( $source[ $key ] ) ) {
			return $source[ $key ];
		}
		return isset( $fallback[ $key ] ) && is_string( $fallback[ $key ] ) ? $fallback[ $key ] : '';
	}

	private static function normalize_http_url_candidate( $value, string $fallback = '' ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}
		if ( '' === trim( $value ) ) {
			return '';
		}
		$url    = esc_url_raw( $value, array( 'http', 'https' ) );
		$scheme = is_string( $url ) ? strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) : '';
		if ( ! $url || ! in_array( $scheme, array( 'http', 'https' ), true ) || ! wp_http_validate_url( $url ) ) {
			return $fallback;
		}
		return $url;
	}

	private static function schema( array $context ): array {
		$settings = self::settings();
		$organization_id = home_url( '/#organization' );
		$website_id      = home_url( '/#website' );
		$organization = array(
			'@type'       => $settings['organization_type'],
			'@id'         => $organization_id,
			'name'        => $settings['organization_name'] ?: get_bloginfo( 'name' ),
			'url'         => home_url( '/' ),
			'description' => $settings['organization_description'] ?: get_bloginfo( 'description' ),
		);
		$logo = self::attachment_url( (int) $settings['organization_logo_id'] );
		if ( $logo ) {
			$organization['logo'] = array( '@type' => 'ImageObject', 'url' => $logo );
		}
		$webpage = array(
			'@type'       => is_singular( 'post' ) ? 'Article' : 'WebPage',
			'@id'         => ( $context['canonical'] ?: self::current_url() ) . '#webpage',
			'url'         => $context['canonical'] ?: self::current_url(),
			'name'        => $context['title'],
			'description' => $context['description'],
			'isPartOf'    => array( '@id' => $website_id ),
		);
		if ( $context['image'] ) {
			$webpage['primaryImageOfPage'] = array( '@type' => 'ImageObject', 'url' => $context['image'] );
		}
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$webpage['datePublished'] = get_the_date( DATE_W3C, $post );
				$webpage['dateModified']  = get_the_modified_date( DATE_W3C, $post );
			}
		}
		return array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				$organization,
				array(
					'@type'     => 'WebSite',
					'@id'       => $website_id,
					'url'       => home_url( '/' ),
					'name'      => get_bloginfo( 'name' ),
					'publisher' => array( '@id' => $organization_id ),
				),
				$webpage,
			),
		);
	}

	/**
	 * Read an optional translation contract. SEO Core is fully standalone and
	 * never requires a translator. Any translator/adapter may expose a contract
	 * through this filter; KOZ Static Translate is only one possible provider.
	 *
	 * @return array<string,mixed>
	 */
	private static function translation_contract(): array {
		$contract = apply_filters( 'kozseo_translation_contract', array() );
		return is_array( $contract ) ? $contract : array();
	}

	/** @return array<int,string> */
	private static function translation_hreflang_allowlist(): array {
		$contract = self::translation_contract();
		if ( empty( $contract ) ) {
			return array();
		}

		$allowed = array( 'x-default' );
		if ( ! empty( $contract['source_code'] ) && is_string( $contract['source_code'] ) ) {
			$allowed[] = trim( $contract['source_code'] );
		}

		foreach ( (array) ( $contract['target_languages'] ?? array() ) as $key => $language ) {
			if ( is_array( $language ) && ! empty( $language['code'] ) && is_string( $language['code'] ) ) {
				$allowed[] = trim( $language['code'] );
			} elseif ( is_string( $language ) ) {
				$allowed[] = trim( $language );
			} elseif ( is_string( $key ) && '' !== trim( $key ) ) {
				$allowed[] = trim( $key );
			}
		}

		return array_values( array_unique( array_filter( $allowed ) ) );
	}

	private static function hreflang_links( string $canonical ): array {
		$links = apply_filters( 'kozseo_hreflang_links', array(), $canonical );
		// Backward compatibility for integrations registered before the strict prefix re-audit.
		$links = apply_filters( 'kozseocore_hreflang_links', is_array( $links ) ? $links : array(), $canonical );
		if ( ! is_array( $links ) ) {
			return array();
		}
		$result = array();
		$translation_allowlist = self::translation_hreflang_allowlist();
		foreach ( $links as $language => $url ) {
			if ( ! is_string( $language ) || ! is_string( $url ) ) {
				continue;
			}
			$language = trim( $language );
			if ( ! preg_match( '/^(?:x-default|[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*)$/', $language ) ) {
				continue;
			}
			if ( ! empty( $translation_allowlist ) && ! in_array( $language, $translation_allowlist, true ) ) {
				continue;
			}
			$url = self::normalize_http_url_candidate( $url );
			if ( $url ) {
				$result[ $language ] = $url;
			}
		}
		return $result;
	}

	public static function add_meta_box(): void {
		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
			if ( 'attachment' !== $post_type ) {
				add_meta_box( 'kozseo-meta', __( 'KOZ SEO', 'koz-seo-core' ), array( __CLASS__, 'render_meta_box' ), $post_type, 'normal', 'default' );
			}
		}
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'kozseo_meta_save', 'kozseo_meta_nonce' );
		$fields = array(
			'kozseo_title'       => __( 'SEO title', 'koz-seo-core' ),
			'kozseo_description' => __( 'Meta description', 'koz-seo-core' ),
			'kozseo_canonical'   => __( 'Canonical URL', 'koz-seo-core' ),
			'kozseo_social_image_id' => __( 'Social image attachment ID', 'koz-seo-core' ),
		);
		foreach ( $fields as $key => $label ) {
			$value = self::input_string( self::post_meta_value( $post->ID, $key ) );
			echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><br>';
			if ( 'kozseo_description' === $key ) {
				echo '<textarea class="widefat" rows="3" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
			} else {
				echo '<input class="widefat" type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '">';
			}
			echo '</p>';
		}
		$noindex = (bool) self::post_meta_value( $post->ID, 'kozseo_noindex' );
		echo '<p><label><input type="checkbox" name="kozseo_noindex" value="1" ' . checked( $noindex, true, false ) . '> ' . esc_html__( 'Prevent search engine indexing', 'koz-seo-core' ) . '</label></p>';
	}

	public static function save_meta_box( int $post_id, WP_Post $post ): void {
		$nonce = isset( $_POST['kozseo_meta_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['kozseo_meta_nonce'] ) )
			: '';
		if ( ! wp_verify_nonce( $nonce, 'kozseo_meta_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$submitted = array(
			'kozseo_title'       => isset( $_POST['kozseo_title'] )
				? sanitize_text_field( wp_unslash( $_POST['kozseo_title'] ) )
				: '',
			'kozseo_description' => isset( $_POST['kozseo_description'] )
				? sanitize_textarea_field( wp_unslash( $_POST['kozseo_description'] ) )
				: '',
			'kozseo_social_image_id' => isset( $_POST['kozseo_social_image_id'] )
				? absint( wp_unslash( $_POST['kozseo_social_image_id'] ) )
				: 0,
		);
		foreach ( $submitted as $key => $value ) {
			if ( '' === $value || 0 === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}

		$canonical_raw = isset( $_POST['kozseo_canonical'] )
			? sanitize_text_field( wp_unslash( $_POST['kozseo_canonical'] ) )
			: '';
		$canonical = self::normalize_http_url_candidate( $canonical_raw );
		if ( '' === $canonical ) {
			delete_post_meta( $post_id, 'kozseo_canonical' );
		} else {
			update_post_meta( $post_id, 'kozseo_canonical', $canonical );
		}

		$noindex = isset( $_POST['kozseo_noindex'] )
			? absint( wp_unslash( $_POST['kozseo_noindex'] ) )
			: 0;
		update_post_meta( $post_id, 'kozseo_noindex', $noindex ? 1 : 0 );
	}

	public static function filter_sitemap_post_types( array $post_types ): array {
		if ( ! self::is_enabled() ) {
			return $post_types;
		}
		$excluded = array_merge( self::settings()['excluded_post_types'], self::technical_noindex_post_types() );
		foreach ( array_unique( $excluded ) as $type ) {
			unset( $post_types[ $type ] );
		}
		return $post_types;
	}

	public static function filter_sitemap_provider( $provider, string $name ) {
		if ( ! self::is_enabled() ) {
			return $provider;
		}
		if ( 'users' === $name && ! empty( self::settings()['noindex_author'] ) ) {
			return false;
		}
		return $provider;
	}

	public static function robots_txt( string $output, bool $public ): string {
		if ( ! self::is_enabled() ) {
			return $output;
		}
		if ( $public && ! str_contains( $output, 'wp-sitemap.xml' ) ) {
			$output .= "\nSitemap: " . home_url( '/wp-sitemap.xml' ) . "\n";
		}
		return $output;
	}

	public static function accessibility_audit( int $limit = 200, bool $include_items = true ): array {
		$posts = get_posts(
			array(
				'post_type'      => array_values( array_diff( get_post_types( array( 'public' => true ), 'names' ), array( 'attachment' ) ) ),
				'post_status'    => 'publish',
				'posts_per_page' => min( 500, max( 1, $limit ) ),
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		$result = array(
			'checked'                 => 0,
			'missing_h1_count'        => 0,
			'missing_h1'              => array(),
			'images_without_alt'      => 0,
			'empty_links'             => 0,
			'content_truncated_count' => 0,
			'items_included'          => $include_items,
		);
		foreach ( $posts as $post ) {
			$result['checked']++;
			$content = (string) $post->post_content;
			if ( strlen( $content ) > 1000000 ) {
				$content = substr( $content, 0, 1000000 );
				$result['content_truncated_count']++;
			}
			if ( ! preg_match( '/<h1\b/i', $content ) ) {
				$result['missing_h1_count']++;
				if ( $include_items ) {
					$result['missing_h1'][] = array( 'id' => $post->ID, 'title' => get_the_title( $post ), 'url' => get_permalink( $post ) );
				}
			}
			preg_match_all( '/<img\b[^>]*>/i', $content, $images );
			foreach ( $images[0] as $image ) {
				$alt = self::html_attribute_value( $image, 'alt' );
				if ( null === $alt || '' === $alt ) {
					$result['images_without_alt']++;
				}
			}
			preg_match_all( '/<a\b([^>]*)>(.*?)<\/a\s*>/is', $content, $links, PREG_SET_ORDER );
			foreach ( $links as $link ) {
				$attributes = (string) ( $link[1] ?? '' );
				$body       = (string) ( $link[2] ?? '' );
				$link_text  = trim( wp_strip_all_tags( $body ) );
				$has_label  = self::has_nonempty_attribute( $attributes, array( 'aria-label', 'title' ) );
				$has_image_alt = false;
				preg_match_all( '/<img\b[^>]*>/i', $body, $link_images );
				foreach ( $link_images[0] as $link_image ) {
					$alt = self::html_attribute_value( $link_image, 'alt' );
					if ( null !== $alt && '' !== $alt ) {
						$has_image_alt = true;
						break;
					}
				}
				if ( '' === $link_text && ! $has_label && ! $has_image_alt ) {
					$result['empty_links']++;
				}
			}
		}
		return $result;
	}

	private static function has_nonempty_attribute( string $html, array $names ): bool {
		foreach ( $names as $name ) {
			if ( ! is_string( $name ) ) {
				continue;
			}
			$value = self::html_attribute_value( $html, $name );
			if ( null !== $value && '' !== $value ) {
				return true;
			}
		}
		return false;
	}

	private static function html_attribute_value( string $html, string $name ): ?string {
		$pattern = '~\b' . preg_quote( $name, '~' ) . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))~i';
		if ( ! preg_match( $pattern, $html, $match ) ) {
			return null;
		}
		$value = '';
		foreach ( array( 1, 2, 3 ) as $index ) {
			if ( isset( $match[ $index ] ) && '' !== $match[ $index ] ) {
				$value = $match[ $index ];
				break;
			}
		}
		return trim( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	public static function public_status(): array {
		$settings = self::settings();
		$translation_contract = self::translation_contract();
		return array(
			'contract_version'       => 4,
			'version'                => KOZSEO_VERSION,
			'enabled'                => ! empty( $settings['enabled'] ),
			'sitemap_path'           => '/wp-sitemap.xml',
			'llms_txt_path'          => ! empty( $settings['enabled'] ) && ! empty( $settings['llms_txt'] ) ? '/llms.txt' : '',
			'ai_manifest_path'       => ! empty( $settings['enabled'] ) && ! empty( $settings['ai_manifest'] ) ? '/.well-known/koz-ai-manifest.json' : '',
			'organization_configured'=> '' !== trim( (string) $settings['organization_name'] ),
			'organization_type'      => (string) $settings['organization_type'],
			'output_conflict'        => KOZSEO_Scanner::conflicting_active_plugin(),
			'translation_provider'   => (string) ( $translation_contract['provider'] ?? '' ),
			'translation_version'    => (string) ( $translation_contract['version'] ?? '' ),
		);
	}

	private static function excerpt( WP_Post $post, string $site_name = '' ): string {
		$has_excerpt = has_excerpt( $post );
		$raw         = $has_excerpt ? (string) $post->post_excerpt : (string) $post->post_content;
		$text        = self::description_source_text( $raw );

		if ( ! $has_excerpt && self::is_gallery_content( (string) $post->post_content ) && ! self::has_natural_description_text( $text ) ) {
			$title = trim( wp_strip_all_tags( (string) get_the_title( $post ) ) );
			$site  = trim( wp_strip_all_tags( $site_name ?: (string) get_bloginfo( 'name' ) ) );
			if ( '' !== $title && '' !== $site && ! str_contains( mb_strtolower( $title ), mb_strtolower( $site ) ) ) {
				$text = $title . ' — ' . $site . '.';
			} elseif ( '' !== $title ) {
				$text = $title;
			} elseif ( '' !== $site ) {
				$text = $site;
			}
		}

		return self::truncate( $text, 170 );
	}

	private static function description_source_text( string $text ): string {
		$text = strip_shortcodes( $text );
		// WordPress block comments may contain serialized JSON attributes that are not human-readable copy.
		$text = (string) preg_replace( '/<!--.*?-->/su', ' ', $text );
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ?: 'UTF-8' );
		$text = self::strip_json_fragments( $text );
		return (string) preg_replace( '/\s+/u', ' ', trim( $text ) );
	}

	private static function strip_json_fragments( string $text ): string {
		$length = strlen( $text );
		$result = '';

		for ( $i = 0; $i < $length; $i++ ) {
			if ( '{' !== $text[ $i ] ) {
				$result .= $text[ $i ];
				continue;
			}

			$depth     = 0;
			$in_string = false;
			$escaped   = false;
			$end       = -1;
			$limit     = min( $length, $i + 4096 );

			for ( $j = $i; $j < $limit; $j++ ) {
				$char = $text[ $j ];
				if ( $in_string ) {
					if ( $escaped ) {
						$escaped = false;
					} elseif ( '\\' === $char ) {
						$escaped = true;
					} elseif ( '"' === $char ) {
						$in_string = false;
					}
					continue;
				}

				if ( '"' === $char ) {
					$in_string = true;
				} elseif ( '{' === $char ) {
					$depth++;
				} elseif ( '}' === $char ) {
					$depth--;
					if ( 0 === $depth ) {
						$end = $j;
						break;
					}
				}
			}

			if ( $end < 0 ) {
				$result .= $text[ $i ];
				continue;
			}

			$candidate = substr( $text, $i, $end - $i + 1 );
			$decoded    = json_decode( $candidate, true );
			$looks_json = is_array( $decoded ) || 1 === preg_match( '/^\{\s*"[^"\r\n]+"\s*:/s', $candidate );
			if ( $looks_json ) {
				$result .= ' ';
				$i = $end;
				continue;
			}

			$result .= $candidate;
			$i = $end;
		}

		return $result;
	}

	private static function is_gallery_content( string $content ): bool {
		return 1 === preg_match( '/(?:<!--\s*wp:(?:gallery|pgc\/simply-gallery)|\bgalleryId\b|\[gallery\b|pgcsimplygalleryblock|simply-gallery)/i', $content );
	}

	private static function has_natural_description_text( string $text ): bool {
		if ( '' === $text ) {
			return false;
		}
		$words = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		$plain = (string) preg_replace( '/[^\p{L}\p{N}]+/u', '', $text );
		return is_array( $words ) && count( $words ) >= 4 && mb_strlen( $plain ) >= 30;
	}

	private static function truncate( string $text, int $length ): string {
		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}
		return rtrim( mb_substr( $text, 0, $length - 1 ) ) . '…';
	}

	private static function attachment_url( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $attachment_id, 'full' );
		return is_string( $url ) ? $url : '';
	}

	private static function current_url(): string {
		$uri = self::request_uri();
		$uri = preg_replace( '/[\r\n].*/', '', $uri );
		$parts = wp_parse_url( $uri );
		$path = '/' . ltrim( (string) ( $parts['path'] ?? '/' ), '/' );
		$url  = home_url( $path );
		if ( ! empty( $parts['query'] ) ) {
			parse_str( (string) $parts['query'], $query );
			if ( is_array( $query ) && $query ) {
				$url = add_query_arg( $query, $url );
			}
		}
		return esc_url_raw( $url );
	}
}
