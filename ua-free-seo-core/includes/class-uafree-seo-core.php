<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UAFree_SEO_Core {
	const OPTION = 'uafree_seo_core_settings';

	private static array $context_cache = array();

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_rewrites' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'serve_discovery_files' ), 0 );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_document_title' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'render_head' ), 1 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ), 10, 2 );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'filter_sitemap_post_types' ) );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 20, 2 );
	}

	public static function activate(): void {
		$settings = get_option( self::OPTION, null );
		if ( ! is_array( $settings ) ) {
			$settings = self::defaults();
			$settings['enabled'] = UAFree_SEO_Scanner::conflicting_active_plugin() ? 0 : 1;
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

	public static function register_rewrites(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?uafree_seo_llms=1', 'top' );
		add_rewrite_rule( '^\.well-known/uafree-ai-manifest\.json$', 'index.php?uafree_seo_ai_manifest=1', 'top' );
	}

	public static function query_vars( array $vars ): array {
		$vars[] = 'uafree_seo_llms';
		$vars[] = 'uafree_seo_ai_manifest';
		return $vars;
	}

	public static function serve_discovery_files(): void {
		$llms_requested     = (bool) get_query_var( 'uafree_seo_llms' );
		$manifest_requested = (bool) get_query_var( 'uafree_seo_ai_manifest' );
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
		echo wp_json_encode( self::ai_manifest(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
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
			'- Sitemap: ' . home_url( '/wp-sitemap.xml' ),
		);
		return implode( "\n", array_map( 'strval', $lines ) ) . "\n";
	}

	private static function ai_manifest(): array {
		$settings = self::settings();
		return array(
			'name'        => $settings['organization_name'] ?: get_bloginfo( 'name' ),
			'description' => $settings['organization_description'] ?: get_bloginfo( 'description' ),
			'url'         => home_url( '/' ),
			'sitemap'     => home_url( '/wp-sitemap.xml' ),
			'generated_by'=> 'UA FREE SEO Core ' . UAFREE_SEO_VERSION,
		);
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
		echo '<meta name="robots" content="' . esc_attr( implode( ', ', $context['robots'] ) ) . '">' . "\n";
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
		echo '<script type="application/ld+json">' . wp_json_encode( self::schema( $context ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
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
		$description = get_bloginfo( 'description' );
		$canonical = home_url( '/' );
		$robots = array( 'index', 'follow', 'max-image-preview:large' );
		$image = self::attachment_url( (int) $settings['default_social_image_id'] );
		$og_type = 'website';

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$manual_title = trim( self::input_string( get_post_meta( $post->ID, 'uafree_seo_title', true ) ) );
				$manual_desc  = trim( self::input_string( get_post_meta( $post->ID, 'uafree_seo_description', true ) ) );
				$manual_can   = self::normalize_http_url_candidate( get_post_meta( $post->ID, 'uafree_seo_canonical', true ) );
				$title = $manual_title ?: wp_strip_all_tags( get_the_title( $post ) );
				if ( ! str_contains( mb_strtolower( $title ), mb_strtolower( $site_name ) ) ) {
					$title .= $separator . $site_name;
				}
				$description = $manual_desc ?: self::excerpt( $post );
				$canonical = $manual_can ?: self::normalize_http_url_candidate( get_permalink( $post ), home_url( '/' ) );
				$noindex = (bool) get_post_meta( $post->ID, 'uafree_seo_noindex', true );
				if ( $noindex ) {
					$robots = array( 'noindex', 'follow' );
				}
				$featured = get_post_thumbnail_id( $post );
				$social_meta = get_post_meta( $post->ID, 'uafree_social_image_id', true );
				$social      = absint( is_scalar( $social_meta ) ? $social_meta : 0 );
				$image = self::attachment_url( $social ?: ( $featured ?: (int) $settings['default_social_image_id'] ) );
				$og_type = 'post' === $post->post_type ? 'article' : 'website';
			}
		} elseif ( is_search() ) {
			/* translators: %s: search query. */
			$title = sprintf( __( 'Search results for “%s”', 'ua-free-seo-core' ), get_search_query() ) . $separator . $site_name;
			/* translators: %s: website name. */
			$description = sprintf( __( 'Search results on %s.', 'ua-free-seo-core' ), $site_name );
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
			$title = __( 'Page not found', 'ua-free-seo-core' ) . $separator . $site_name;
			$description = __( 'The requested page could not be found.', 'ua-free-seo-core' );
			$canonical = '';
			$robots = array( 'noindex', 'follow' );
		} elseif ( is_front_page() || is_home() ) {
			$title = get_bloginfo( 'name' );
			$description = get_bloginfo( 'description' );
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
		$filtered = apply_filters( 'uafree_seo_context', $context );
		self::$context_cache[ $key ] = self::normalize_context( is_array( $filtered ) ? $filtered : $context, $context );
		return self::$context_cache[ $key ];
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

	private static function hreflang_links( string $canonical ): array {
		$links = apply_filters( 'uafree_seo_hreflang_links', array(), $canonical );
		if ( ! is_array( $links ) ) {
			return array();
		}
		$result = array();
		foreach ( $links as $language => $url ) {
			if ( ! is_string( $language ) || ! is_string( $url ) ) {
				continue;
			}
			$language = trim( $language );
			if ( ! preg_match( '/^(?:x-default|[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*)$/', $language ) ) {
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
				add_meta_box( 'uafree-seo-meta', __( 'UA FREE SEO', 'ua-free-seo-core' ), array( __CLASS__, 'render_meta_box' ), $post_type, 'normal', 'default' );
			}
		}
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'uafree_seo_meta_save', 'uafree_seo_meta_nonce' );
		$fields = array(
			'uafree_seo_title'       => __( 'SEO title', 'ua-free-seo-core' ),
			'uafree_seo_description' => __( 'Meta description', 'ua-free-seo-core' ),
			'uafree_seo_canonical'   => __( 'Canonical URL', 'ua-free-seo-core' ),
			'uafree_social_image_id' => __( 'Social image attachment ID', 'ua-free-seo-core' ),
		);
		foreach ( $fields as $key => $label ) {
			$value = self::input_string( get_post_meta( $post->ID, $key, true ) );
			echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><br>';
			if ( 'uafree_seo_description' === $key ) {
				echo '<textarea class="widefat" rows="3" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
			} else {
				echo '<input class="widefat" type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '">';
			}
			echo '</p>';
		}
		$noindex = (bool) get_post_meta( $post->ID, 'uafree_seo_noindex', true );
		echo '<p><label><input type="checkbox" name="uafree_seo_noindex" value="1" ' . checked( $noindex, true, false ) . '> ' . esc_html__( 'Prevent search engine indexing', 'ua-free-seo-core' ) . '</label></p>';
	}

	public static function save_meta_box( int $post_id, WP_Post $post ): void {
		$nonce = isset( $_POST['uafree_seo_meta_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['uafree_seo_meta_nonce'] ) )
			: '';
		if ( ! wp_verify_nonce( $nonce, 'uafree_seo_meta_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$submitted = array(
			'uafree_seo_title'       => isset( $_POST['uafree_seo_title'] )
				? sanitize_text_field( wp_unslash( $_POST['uafree_seo_title'] ) )
				: '',
			'uafree_seo_description' => isset( $_POST['uafree_seo_description'] )
				? sanitize_textarea_field( wp_unslash( $_POST['uafree_seo_description'] ) )
				: '',
			'uafree_social_image_id' => isset( $_POST['uafree_social_image_id'] )
				? absint( wp_unslash( $_POST['uafree_social_image_id'] ) )
				: 0,
		);
		foreach ( $submitted as $key => $value ) {
			if ( '' === $value || 0 === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}

		$canonical_raw = isset( $_POST['uafree_seo_canonical'] )
			? sanitize_text_field( wp_unslash( $_POST['uafree_seo_canonical'] ) )
			: '';
		$canonical = self::normalize_http_url_candidate( $canonical_raw );
		if ( '' === $canonical ) {
			delete_post_meta( $post_id, 'uafree_seo_canonical' );
		} else {
			update_post_meta( $post_id, 'uafree_seo_canonical', $canonical );
		}

		$noindex = isset( $_POST['uafree_seo_noindex'] )
			? absint( wp_unslash( $_POST['uafree_seo_noindex'] ) )
			: 0;
		update_post_meta( $post_id, 'uafree_seo_noindex', $noindex ? 1 : 0 );
	}

	public static function filter_sitemap_post_types( array $post_types ): array {
		if ( ! self::is_enabled() ) {
			return $post_types;
		}
		$excluded = self::settings()['excluded_post_types'];
		foreach ( $excluded as $type ) {
			unset( $post_types[ $type ] );
		}
		return $post_types;
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
		return array(
			'contract_version'       => 3,
			'version'                => UAFREE_SEO_VERSION,
			'enabled'                => ! empty( $settings['enabled'] ),
			'sitemap_path'           => '/wp-sitemap.xml',
			'llms_txt_path'          => ! empty( $settings['enabled'] ) && ! empty( $settings['llms_txt'] ) ? '/llms.txt' : '',
			'ai_manifest_path'       => ! empty( $settings['enabled'] ) && ! empty( $settings['ai_manifest'] ) ? '/.well-known/uafree-ai-manifest.json' : '',
			'organization_configured'=> '' !== trim( (string) $settings['organization_name'] ),
			'organization_type'      => (string) $settings['organization_type'],
			'output_conflict'        => UAFree_SEO_Scanner::conflicting_active_plugin(),
		);
	}

	private static function excerpt( WP_Post $post ): string {
		$text = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
		$text = strip_shortcodes( (string) $text );
		$text = wp_strip_all_tags( $text );
		return self::truncate( preg_replace( '/\s+/u', ' ', trim( $text ) ), 170 );
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
