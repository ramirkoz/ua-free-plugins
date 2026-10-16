<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZSEO_Core {
	public const OPTION = 'kozseo_settings';
	private const LEGACY_OPTION = 'uafree_seo_core_settings';

	private static array $context_cache = array();

	/** Sanitized settings for the current request. Reset when the option changes. */
	private static ?array $settings_cache = null;

	/** Attachment ID behind the social image of each request context, for og:image dimensions. */
	private static array $image_id_cache = array();


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
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'filter_sitemap_posts_query' ), 10, 2 );
		add_action( 'wp', array( __CLASS__, 'remove_core_canonical' ) );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'flush_settings_cache' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush_settings_cache' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_editor_assets' ) );
	}

	public static function flush_settings_cache(): void {
		self::$settings_cache = null;
		self::$context_cache  = array();
	}

	/**
	 * WordPress core prints its own rel=canonical on singular views. KOZ SEO Core prints the
	 * canonical for every view, so the core tag is removed to avoid two canonical links.
	 */
	public static function remove_core_canonical(): void {
		if ( self::is_enabled() && ! is_admin() ) {
			remove_action( 'wp_head', 'rel_canonical' );
		}
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
			'social_profiles'         => array(),
			'contact_email'           => '',
			'contact_phone'           => '',
			'breadcrumbs_schema'      => 1,
			'llms_page_ids'           => array(),
			'alt_language'            => '',
		);
	}

	public static function settings(): array {
		if ( null !== self::$settings_cache ) {
			return self::$settings_cache;
		}
		$saved  = get_option( self::OPTION, array() );
		$merged = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		self::$settings_cache = self::sanitize_settings( $merged );
		return self::$settings_cache;
	}

	private static function input_string( $value, string $fallback = '' ): string {
		return is_scalar( $value ) ? (string) $value : $fallback;
	}

	/**
	 * Current request URI. sanitize_text_field() must not be used here: it strips
	 * percent-encoded octets and turns /category/%d0%b7.../ into /category//.
	 */
	private static function request_uri(): string {
		$raw = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by esc_url_raw() below.
		$raw = (string) preg_replace( '/[\r\n\t].*/s', '', $raw );
		$raw = esc_url_raw( $raw );
		return '' !== $raw ? $raw : '/';
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
			'social_profiles'          => self::sanitize_url_list( $input['social_profiles'] ?? array() ),
			'contact_email'            => sanitize_email( self::input_string( $input['contact_email'] ?? '' ) ),
			'contact_phone'            => (string) preg_replace( '/[^0-9+()\-\s]/', '', sanitize_text_field( self::input_string( $input['contact_phone'] ?? '' ) ) ),
			'breadcrumbs_schema'       => array_key_exists( 'breadcrumbs_schema', $input ) && empty( $input['breadcrumbs_schema'] ) ? 0 : 1,
			'llms_page_ids'            => self::sanitize_id_list( $input['llms_page_ids'] ?? array() ),
			'alt_language'             => array_key_exists( self::input_string( $input['alt_language'] ?? '' ), self::alt_languages() ) ? self::input_string( $input['alt_language'] ?? '' ) : '',
		);
	}

	/**
	 * Languages offered for AI ALT text. The empty key follows the site language.
	 *
	 * @return array<string,string> Locale prefix => English language name.
	 */
	public static function alt_languages(): array {
		return array(
			''   => '',
			'uk' => 'Ukrainian',
			'en' => 'English',
			'de' => 'German',
			'es' => 'Spanish',
			'fr' => 'French',
			'pt' => 'Portuguese',
			'id' => 'Indonesian',
			'ja' => 'Japanese',
			'zh' => 'Chinese',
			'hi' => 'Hindi',
			'ar' => 'Arabic',
		);
	}

	/** @param mixed $value Textarea string or array. @return string[] */
	private static function sanitize_url_list( $value ): array {
		$items = is_array( $value ) ? $value : preg_split( '/[\r\n,]+/', self::input_string( $value ) );
		$urls  = array();
		foreach ( (array) $items as $item ) {
			$url = self::normalize_http_url_candidate( trim( self::input_string( $item ) ) );
			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}
		return array_slice( array_values( array_unique( $urls ) ), 0, 20 );
	}

	/** @param mixed $value Comma separated IDs or array. @return int[] */
	private static function sanitize_id_list( $value ): array {
		$items = is_array( $value ) ? $value : preg_split( '/[\s,]+/', self::input_string( $value ) );
		$ids   = array_filter( array_map( 'absint', (array) $items ) );
		return array_slice( array_values( array_unique( $ids ) ), 0, 50 );
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

		$pages = self::llms_key_pages();
		if ( $pages ) {
			$lines[] = '';
			$lines[] = '## ' . __( 'Key pages', 'koz-seo-core' );
			$lines[] = '';
			foreach ( $pages as $page ) {
				$line = '- [' . str_replace( array( '[', ']' ), '', $page['title'] ) . '](' . $page['url'] . ')';
				if ( '' !== $page['description'] ) {
					$line .= ': ' . $page['description'];
				}
				$lines[] = $line;
			}
		}

		return implode( "\n", array_map( 'strval', $lines ) ) . "\n";
	}

	/**
	 * Pages listed in llms.txt: the configured page IDs, otherwise the top-level items of the
	 * first assigned navigation menu. Only published, indexable, same-site pages are listed.
	 *
	 * @return array<int,array{title:string,url:string,description:string}>
	 */
	private static function llms_key_pages(): array {
		$ids = self::settings()['llms_page_ids'];
		if ( ! $ids ) {
			foreach ( (array) get_nav_menu_locations() as $menu_id ) {
				$items = $menu_id ? wp_get_nav_menu_items( (int) $menu_id ) : false;
				if ( ! is_array( $items ) || ! $items ) {
					continue;
				}
				foreach ( $items as $item ) {
					if ( 0 === (int) $item->menu_item_parent && 'post_type' === $item->type ) {
						$ids[] = (int) $item->object_id;
					}
				}
				break;
			}
		}

		$pages = array();
		foreach ( array_slice( array_values( array_unique( $ids ) ), 0, 20 ) as $id ) {
			$post = get_post( $id );
			if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! empty( $post->post_password ) || (bool) self::post_meta_value( $post->ID, 'kozseo_noindex' ) ) {
				continue;
			}
			$description = trim( self::input_string( self::post_meta_value( $post->ID, 'kozseo_description' ) ) );
			$pages[] = array(
				'title'       => wp_strip_all_tags( (string) get_the_title( $post ) ),
				'url'         => (string) get_permalink( $post ),
				'description' => self::truncate( '' !== $description ? $description : self::excerpt( $post ), 160 ),
			);
		}
		return $pages;
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
		echo '<meta property="og:locale" content="' . esc_attr( self::og_locale() ) . '">' . "\n";
		if ( $context['image'] ) {
			echo '<meta property="og:image" content="' . esc_url( $context['image'] ) . '">' . "\n";
			foreach ( self::image_meta( $context['image'] ) as $property => $value ) {
				echo '<meta property="og:image:' . esc_attr( $property ) . '" content="' . esc_attr( (string) $value ) . '">' . "\n";
			}
		}
		$post = self::request_post();
		if ( 'article' === $context['og_type'] && $post instanceof WP_Post ) {
			echo '<meta property="article:published_time" content="' . esc_attr( (string) get_the_date( DATE_W3C, $post ) ) . '">' . "\n";
			echo '<meta property="article:modified_time" content="' . esc_attr( (string) get_the_modified_date( DATE_W3C, $post ) ) . '">' . "\n";
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

	/**
	 * Open Graph locale of the page being rendered, for example uk_UA or en_US.
	 * It follows the document language, so translated routes report their own language.
	 */
	private static function og_locale(): string {
		$language = str_replace( '-', '_', (string) get_bloginfo( 'language' ) );
		if ( ! preg_match( '/^[a-z]{2,3}(?:_[A-Z]{2})?$/', $language ) ) {
			$language = (string) determine_locale();
		}
		if ( ! str_contains( $language, '_' ) ) {
			$defaults = array( 'uk' => 'uk_UA', 'en' => 'en_US', 'de' => 'de_DE', 'es' => 'es_ES', 'fr' => 'fr_FR', 'pt' => 'pt_PT', 'id' => 'id_ID', 'ja' => 'ja_JP', 'zh' => 'zh_CN', 'hi' => 'hi_IN', 'ar' => 'ar_AR' );
			$language = $defaults[ $language ] ?? $language;
		}
		return $language;
	}

	/**
	 * Width, height and alt text of the social image when it is a known attachment.
	 *
	 * @return array<string,string|int>
	 */
	private static function image_meta( string $image_url ): array {
		$key = md5( self::request_uri() );
		$id  = (int) ( self::$image_id_cache[ $key ] ?? 0 );
		if ( $id <= 0 || self::attachment_url( $id ) !== $image_url ) {
			return array();
		}
		$meta = array();
		$src  = wp_get_attachment_image_src( $id, 'full' );
		if ( is_array( $src ) && ! empty( $src[1] ) && ! empty( $src[2] ) ) {
			$meta['width']  = (int) $src[1];
			$meta['height'] = (int) $src[2];
		}
		$alt = trim( wp_strip_all_tags( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) );
		if ( '' !== $alt ) {
			$meta['alt'] = $alt;
		}
		return $meta;
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
		$image_id = (int) $settings['default_social_image_id'];
		$image = self::attachment_url( $image_id );
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
			$core_canonical = wp_get_canonical_url( $post );
			$canonical = $manual_can ?: self::normalize_http_url_candidate( is_string( $core_canonical ) ? $core_canonical : get_permalink( $post ), home_url( '/' ) );
			$noindex = (bool) self::post_meta_value( $post->ID, 'kozseo_noindex' );
			if ( $noindex || in_array( $post->post_type, self::technical_noindex_post_types(), true ) ) {
				$robots = array( 'noindex', 'follow' );
			}
			$featured = get_post_thumbnail_id( $post );
			$social_meta = self::post_meta_value( $post->ID, 'kozseo_social_image_id' );
			$social      = absint( is_scalar( $social_meta ) ? $social_meta : 0 );
			$image_id = $social ?: ( $featured ?: (int) $settings['default_social_image_id'] );
			$image = self::attachment_url( $image_id );
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
			$canonical = self::archive_canonical();
			if ( ! empty( $settings['noindex_author'] ) ) {
				$robots = array( 'noindex', 'follow' );
			}
		} elseif ( is_date() ) {
			$title = get_the_archive_title() . $separator . $site_name;
			$canonical = self::archive_canonical();
			if ( ! empty( $settings['noindex_date'] ) ) {
				$robots = array( 'noindex', 'follow' );
			}
		} elseif ( is_archive() ) {
			$title = wp_strip_all_tags( get_the_archive_title() ) . $separator . $site_name;
			$description = wp_strip_all_tags( get_the_archive_description() ) ?: $description;
			$canonical = self::archive_canonical();
		} elseif ( is_404() ) {
			$title = __( 'Page not found', 'koz-seo-core' ) . $separator . $site_name;
			$description = __( 'The requested page could not be found.', 'koz-seo-core' );
			$canonical = '';
			$robots = array( 'noindex', 'follow' );
		} elseif ( is_front_page() || is_home() ) {
			$title = get_bloginfo( 'name' );
			$description = $settings['organization_description'] ?: get_bloginfo( 'description' );
			$posts_page = (int) get_option( 'page_for_posts' );
			$canonical = is_home() && ! is_front_page() && $posts_page > 0 ? (string) get_permalink( $posts_page ) : home_url( '/' );
			$canonical = self::with_page_number( $canonical );
		}

		$context = array(
			'title'       => trim( wp_strip_all_tags( (string) $title ) ),
			'description' => self::truncate( trim( wp_strip_all_tags( (string) $description ) ), 170 ),
			'canonical'   => esc_url_raw( (string) $canonical ),
			'robots'      => $robots,
			'image'       => esc_url_raw( (string) $image ),
			'og_type'     => $og_type,
		);
		self::$image_id_cache[ $key ] = $image_id;
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
		static $resolved = array();
		$cache_key = md5( self::request_uri() );
		if ( ! array_key_exists( $cache_key, $resolved ) ) {
			$resolved[ $cache_key ] = self::resolve_request_post();
		}
		return $resolved[ $cache_key ];
	}

	private static function resolve_request_post(): ?WP_Post {
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
		$settings        = self::settings();
		$organization_id = home_url( '/#organization' );
		$website_id      = home_url( '/#website' );
		$page_url        = $context['canonical'] ?: self::current_url();
		$webpage_id      = $page_url . '#webpage';
		$language        = str_replace( '_', '-', self::og_locale() );

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
		if ( $settings['social_profiles'] ) {
			$organization['sameAs'] = array_values( $settings['social_profiles'] );
		}
		if ( '' !== $settings['contact_email'] ) {
			$organization['email'] = $settings['contact_email'];
		}
		if ( '' !== $settings['contact_phone'] ) {
			$organization['telephone'] = $settings['contact_phone'];
		}

		$webpage = array(
			'@type'       => 'WebPage',
			'@id'         => $webpage_id,
			'url'         => $page_url,
			'name'        => $context['title'],
			'description' => $context['description'],
			'inLanguage'  => $language,
			'isPartOf'    => array( '@id' => $website_id ),
		);
		if ( $context['image'] ) {
			$webpage['primaryImageOfPage'] = array( '@type' => 'ImageObject', 'url' => $context['image'] );
		}

		$graph = array(
			$organization,
			array(
				'@type'      => 'WebSite',
				'@id'        => $website_id,
				'url'        => home_url( '/' ),
				'name'       => get_bloginfo( 'name' ),
				'inLanguage' => $language,
				'publisher'  => array( '@id' => $organization_id ),
			),
		);

		// The same post resolution as the meta tags, so page-builder pages get dates too.
		$post = self::request_post();
		if ( $post instanceof WP_Post ) {
			$webpage['datePublished'] = get_the_date( DATE_W3C, $post );
			$webpage['dateModified']  = get_the_modified_date( DATE_W3C, $post );
		}

		$breadcrumbs = ! empty( $settings['breadcrumbs_schema'] ) ? self::breadcrumb_items( $context, $post ) : array();
		if ( count( $breadcrumbs ) > 1 ) {
			$webpage['breadcrumb'] = array( '@id' => $page_url . '#breadcrumb' );
		}
		$graph[] = $webpage;

		if ( $post instanceof WP_Post && 'post' === $post->post_type ) {
			$article = array(
				'@type'            => 'Article',
				'@id'              => $page_url . '#article',
				'headline'         => self::truncate( wp_strip_all_tags( (string) get_the_title( $post ) ), 110 ),
				'description'      => $context['description'],
				'datePublished'    => get_the_date( DATE_W3C, $post ),
				'dateModified'     => get_the_modified_date( DATE_W3C, $post ),
				'inLanguage'       => $language,
				'mainEntityOfPage' => array( '@id' => $webpage_id ),
				'isPartOf'         => array( '@id' => $webpage_id ),
				'publisher'        => array( '@id' => $organization_id ),
			);
			// A display name equal to the login (for example "admin") is not a real author name,
			// so the organization is named as the author instead.
			$author = trim( (string) get_the_author_meta( 'display_name', (int) $post->post_author ) );
			$login  = (string) get_the_author_meta( 'user_login', (int) $post->post_author );
			$article['author'] = '' !== $author && $author !== $login
				? array( '@type' => 'Person', 'name' => wp_strip_all_tags( $author ) )
				: array( '@id' => $organization_id );
			if ( $context['image'] ) {
				$article['image'] = array( '@type' => 'ImageObject', 'url' => $context['image'] );
			}
			$graph[] = $article;
		}

		if ( count( $breadcrumbs ) > 1 ) {
			$list = array();
			foreach ( array_values( $breadcrumbs ) as $index => $crumb ) {
				$list[] = array(
					'@type'    => 'ListItem',
					'position' => $index + 1,
					'name'     => $crumb['name'],
					'item'     => $crumb['url'],
				);
			}
			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $page_url . '#breadcrumb',
				'itemListElement' => $list,
			);
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
	}

	private static function term_url( WP_Term $term ): string {
		$link = get_term_link( $term );
		return is_string( $link ) ? $link : '';
	}

	/**
	 * Breadcrumb trail for schema: home, then parents (page ancestors, the primary category
	 * of a post or parent terms), then the current item. Empty on the front page.
	 *
	 * @return array<int,array{name:string,url:string}>
	 */
	private static function breadcrumb_items( array $context, ?WP_Post $post ): array {
		if ( is_front_page() || is_404() || is_search() ) {
			return array();
		}

		$items   = array( array( 'name' => __( 'Home', 'koz-seo-core' ), 'url' => home_url( '/' ) ) );
		$current = wp_strip_all_tags( (string) $context['title'] );

		if ( $post instanceof WP_Post ) {
			if ( is_post_type_hierarchical( $post->post_type ) ) {
				foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
					$items[] = array( 'name' => wp_strip_all_tags( (string) get_the_title( $ancestor_id ) ), 'url' => (string) get_permalink( $ancestor_id ) );
				}
			} elseif ( 'post' === $post->post_type ) {
				$categories = get_the_category( $post->ID );
				if ( $categories ) {
					$primary = $categories[0];
					foreach ( array_reverse( get_ancestors( $primary->term_id, 'category', 'taxonomy' ) ) as $parent_id ) {
						$parent = get_term( $parent_id, 'category' );
						if ( $parent instanceof WP_Term ) {
							$items[] = array( 'name' => $parent->name, 'url' => self::term_url( $parent ) );
						}
					}
					$items[] = array( 'name' => $primary->name, 'url' => self::term_url( $primary ) );
				}
			}
			$current = wp_strip_all_tags( (string) get_the_title( $post ) );
		} elseif ( ( is_category() || is_tag() || is_tax() ) && get_queried_object() instanceof WP_Term ) {
			$term = get_queried_object();
			foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $parent_id ) {
				$parent = get_term( $parent_id, $term->taxonomy );
				if ( $parent instanceof WP_Term ) {
					$items[] = array( 'name' => $parent->name, 'url' => self::term_url( $parent ) );
				}
			}
			$current = $term->name;
		}

		$items[] = array( 'name' => $current, 'url' => (string) ( $context['canonical'] ?: self::current_url() ) );

		return array_values(
			array_filter(
				$items,
				static fn( array $item ): bool => '' !== trim( $item['name'] ) && '' !== $item['url']
			)
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

	/**
	 * Media library, counters and the search preview for the KOZ SEO meta box.
	 */
	public static function enqueue_editor_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'kozseo-meta-box', KOZSEO_URL . 'assets/kozseo-meta-box.js', array(), KOZSEO_VERSION, true );
		wp_localize_script(
			'kozseo-meta-box',
			'kozseoMetaBox',
			array(
				'chooseImage' => __( 'Choose social image', 'koz-seo-core' ),
				'useImage'    => __( 'Use this image', 'koz-seo-core' ),
				'characters'  => __( 'characters', 'koz-seo-core' ),
				'tooLong'     => __( 'may be shortened in search results', 'koz-seo-core' ),
			)
		);
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'kozseo_meta_save', 'kozseo_meta_nonce' );

		$settings  = self::settings();
		$site_name = $settings['organization_name'] ?: get_bloginfo( 'name' );
		$title     = self::input_string( self::post_meta_value( $post->ID, 'kozseo_title' ) );
		$desc      = self::input_string( self::post_meta_value( $post->ID, 'kozseo_description' ) );
		$canonical = self::input_string( self::post_meta_value( $post->ID, 'kozseo_canonical' ) );
		$image_id  = absint( self::input_string( self::post_meta_value( $post->ID, 'kozseo_social_image_id' ) ) );
		$noindex   = (bool) self::post_meta_value( $post->ID, 'kozseo_noindex' );

		$auto_title = wp_strip_all_tags( (string) get_the_title( $post ) );
		if ( '' !== $auto_title && ! str_contains( mb_strtolower( $auto_title ), mb_strtolower( $site_name ) ) ) {
			$auto_title .= ' ' . $settings['separator'] . ' ' . $site_name;
		}
		$auto_desc = self::excerpt( $post, $site_name );
		$permalink = (string) get_permalink( $post );
		$image_url = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		?>
		<div class="kozseo-meta" data-auto-title="<?php echo esc_attr( $auto_title ); ?>" data-auto-description="<?php echo esc_attr( $auto_desc ); ?>">
			<div class="kozseo-preview" aria-hidden="true" style="max-width:600px;margin:4px 0 16px;padding:12px 14px;border:1px solid #dcdcde;border-radius:8px;background:#fff;font-family:Arial,sans-serif">
				<div style="color:#202124;font-size:13px;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo esc_html( $permalink ); ?></div>
				<div class="kozseo-preview-title" style="color:#1a0dab;font-size:20px;line-height:1.3;margin:2px 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></div>
				<div class="kozseo-preview-description" style="color:#4d5156;font-size:14px;line-height:1.58"></div>
			</div>
			<p><label for="kozseo_title"><strong><?php esc_html_e( 'SEO title', 'koz-seo-core' ); ?></strong></label><br>
				<input class="widefat" type="text" id="kozseo_title" name="kozseo_title" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php echo esc_attr( $auto_title ); ?>" data-kozseo-limit="60">
				<span class="description kozseo-count" data-for="kozseo_title"></span></p>
			<p><label for="kozseo_description"><strong><?php esc_html_e( 'Meta description', 'koz-seo-core' ); ?></strong></label><br>
				<textarea class="widefat" rows="3" id="kozseo_description" name="kozseo_description" placeholder="<?php echo esc_attr( $auto_desc ); ?>" data-kozseo-limit="160"><?php echo esc_textarea( $desc ); ?></textarea>
				<span class="description kozseo-count" data-for="kozseo_description"></span></p>
			<p class="description"><?php esc_html_e( 'Leave empty to use the automatic value shown in grey.', 'koz-seo-core' ); ?></p>
			<p><label for="kozseo_canonical"><strong><?php esc_html_e( 'Canonical URL', 'koz-seo-core' ); ?></strong></label><br>
				<input class="widefat" type="url" id="kozseo_canonical" name="kozseo_canonical" value="<?php echo esc_attr( $canonical ); ?>" placeholder="<?php echo esc_attr( $permalink ); ?>"></p>
			<p><strong><?php esc_html_e( 'Social image', 'koz-seo-core' ); ?></strong><br>
				<input type="hidden" id="kozseo_social_image_id" name="kozseo_social_image_id" value="<?php echo esc_attr( $image_id ? (string) $image_id : '' ); ?>">
				<span class="kozseo-image-preview"><?php if ( $image_url ) : ?><img src="<?php echo esc_url( $image_url ); ?>" alt="" style="max-width:240px;height:auto;display:block;margin:6px 0"><?php endif; ?></span>
				<button type="button" class="button kozseo-image-select"><?php esc_html_e( 'Choose image', 'koz-seo-core' ); ?></button>
				<button type="button" class="button-link kozseo-image-remove" <?php echo $image_id ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'koz-seo-core' ); ?></button>
				<br><span class="description"><?php esc_html_e( 'Optional. Without it the featured image or the default social image is used.', 'koz-seo-core' ); ?></span></p>
			<p><label><input type="checkbox" name="kozseo_noindex" value="1" <?php checked( $noindex ); ?>> <?php esc_html_e( 'Prevent search engine indexing', 'koz-seo-core' ); ?></label>
				<br><span class="description"><?php esc_html_e( 'The page is also removed from the XML sitemap.', 'koz-seo-core' ); ?></span></p>
		</div>
		<?php
	}

	public static function save_meta_box( int $post_id, WP_Post $post ): void {
		$nonce = isset( $_POST['kozseo_meta_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['kozseo_meta_nonce'] ) )
			: '';
		if ( ! wp_verify_nonce( $nonce, 'kozseo_meta_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		wp_cache_delete( 'noindex_' . $post->post_type, 'kozseo' );

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
		if ( $noindex ) {
			update_post_meta( $post_id, 'kozseo_noindex', 1 );
		} elseif ( metadata_exists( 'post', $post_id, 'uafree_seo_noindex' ) ) {
			// An explicit 0 is still needed while a legacy UA FREE flag exists, otherwise it would apply again.
			update_post_meta( $post_id, 'kozseo_noindex', 0 );
		} else {
			delete_post_meta( $post_id, 'kozseo_noindex' );
		}
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

	/**
	 * Keep posts marked "Prevent search engine indexing" out of the XML sitemap.
	 *
	 * @param array<string,mixed> $args      WP_Query arguments of the sitemap provider.
	 * @param string              $post_type Post type of the sitemap page.
	 * @return array<string,mixed>
	 */
	public static function filter_sitemap_posts_query( array $args, string $post_type ): array {
		if ( ! self::is_enabled() ) {
			return $args;
		}
		$excluded = self::noindexed_post_ids( $post_type );
		if ( $excluded ) {
			$args['post__not_in'] = array_values( array_unique( array_merge( (array) ( $args['post__not_in'] ?? array() ), $excluded ) ) ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Small, sitemap-only exclusion list.
		}
		return $args;
	}

	/** @return int[] IDs of published posts of this type with the noindex flag (current or legacy key). */
	private static function noindexed_post_ids( string $post_type ): array {
		$cache_key = 'noindex_' . $post_type;
		$cached    = wp_cache_get( $cache_key, 'kozseo' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 2000,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Runs only while building sitemaps.
					'relation' => 'OR',
					array( 'key' => 'kozseo_noindex', 'value' => '1' ),
					array(
						'relation' => 'AND',
						array( 'key' => 'kozseo_noindex', 'compare' => 'NOT EXISTS' ),
						array( 'key' => 'uafree_seo_noindex', 'value' => '1' ),
					),
				),
			)
		);
		$ids = array_map( 'intval', (array) $ids );
		wp_cache_set( $cache_key, $ids, 'kozseo', HOUR_IN_SECONDS );
		return $ids;
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

	/**
	 * Current URL without any query string, so tracking parameters (utm_*, fbclid, gclid)
	 * never reach canonical URLs or schema identifiers.
	 */
	private static function current_url(): string {
		$parts = wp_parse_url( self::request_uri() );
		$path  = '/' . ltrim( (string) ( $parts['path'] ?? '/' ), '/' );
		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $home_path && '/' !== $home_path && str_starts_with( $path, $home_path ) ) {
			$path = '/' . ltrim( substr( $path, strlen( rtrim( $home_path, '/' ) ) ), '/' );
		}
		return esc_url_raw( home_url( $path ) );
	}

	/**
	 * Add the archive page number to a canonical archive URL.
	 */
	private static function with_page_number( string $url ): string {
		$paged = max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		if ( $paged < 2 || '' === $url ) {
			return $url;
		}
		global $wp_rewrite;
		if ( is_object( $wp_rewrite ) && $wp_rewrite->using_permalinks() ) {
			return user_trailingslashit( trailingslashit( $url ) . $wp_rewrite->pagination_base . '/' . $paged, 'paged' );
		}
		return add_query_arg( 'paged', $paged, $url );
	}

	/**
	 * Canonical URL of an archive view, built from WordPress links rather than the request.
	 */
	private static function archive_canonical(): string {
		$object = get_queried_object();
		$url    = '';
		if ( ( is_category() || is_tag() || is_tax() ) && $object instanceof WP_Term ) {
			$link = get_term_link( $object );
			$url  = is_string( $link ) ? $link : '';
		} elseif ( is_post_type_archive() ) {
			$type = get_query_var( 'post_type' );
			$type = is_array( $type ) ? (string) reset( $type ) : (string) $type;
			$link = '' !== $type ? get_post_type_archive_link( $type ) : false;
			$url  = is_string( $link ) ? $link : '';
		} elseif ( is_author() ) {
			$url = get_author_posts_url( (int) get_queried_object_id() );
		} elseif ( is_day() ) {
			$url = get_day_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ), (int) get_query_var( 'day' ) );
		} elseif ( is_month() ) {
			$url = get_month_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ) );
		} elseif ( is_year() ) {
			$url = get_year_link( (int) get_query_var( 'year' ) );
		}
		if ( '' === $url ) {
			$url = self::current_url();
		}
		return self::with_page_number( $url );
	}
}
