<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vision-first ALT workflow for WordPress 7.0 AI Client.
 *
 * Analysis is always non-destructive. ALT text is only written after an
 * administrator explicitly selects analyzed candidates in the preview.
 */
final class KOZSEO_Alt_Manager {
	private const AJAX_ANALYZE = 'kozseo_ai_alt_analyze_next';
	private const AJAX_APPLY   = 'kozseo_ai_alt_apply_selected';
	private const AJAX_SKIP    = 'kozseo_ai_alt_skip_selected';
	private const REST_NAMESPACE = 'kozseo/v1';
	private const REST_ANALYZE   = '/ai-alt/analyze-next';
	private const REST_REANALYZE = '/ai-alt/reanalyze/(?P<id>\d+)';
	private const CLEAR_ACTION = 'kozseo_ai_alt_clear_analysis';
	private const META_PREFIX  = '_kozseo_ai_alt_';
	private const META_VERSION = self::META_PREFIX . 'version';
	private const META_ALT     = self::META_PREFIX . 'candidate';
	private const META_DECISION= self::META_PREFIX . 'decision';
	private const META_CONF    = self::META_PREFIX . 'confidence';
	private const META_REASON  = self::META_PREFIX . 'reason';
	private const META_PROVIDER= self::META_PREFIX . 'provider';
	private const META_MODEL   = self::META_PREFIX . 'model';
	private const META_USAGE   = self::META_PREFIX . 'usage';
	private const META_TIME    = self::META_PREFIX . 'analyzed_at';
	private const META_CURRENT = self::META_PREFIX . 'current_alt_snapshot';
	private const META_STATUS  = self::META_PREFIX . 'status';
	private const CURSOR_OPTION       = 'kozseo_ai_alt_cursor'; // Legacy 2.1.10 all-media cursor.
	private const CURSOR_EMPTY_OPTION = 'kozseo_ai_alt_cursor_empty';
	private const RECENT_OPTION       = 'kozseo_ai_alt_recent_ids';
	private const STATS_OPTION        = 'kozseo_ai_alt_stats';
	private const PENDING_TRANSIENT   = 'kozseo_ai_alt_pending_empty_v1';
	private const ANALYSIS_SCHEMA     = 'vision-v1';
	private const PREVIEW_LIMIT = 50;
	private const MAX_BATCH      = 50;
	private const MAX_IMAGE_BYTES = 20_000_000;

	public static function init(): void {
		add_action( 'wp_ajax_' . self::AJAX_ANALYZE, array( __CLASS__, 'ajax_analyze_next' ) );
		add_action( 'wp_ajax_' . self::AJAX_APPLY, array( __CLASS__, 'ajax_apply_selected' ) );
		add_action( 'wp_ajax_' . self::AJAX_SKIP, array( __CLASS__, 'ajax_skip_selected' ) );
		add_action( 'admin_post_' . self::CLEAR_ACTION, array( __CLASS__, 'handle_clear_analysis' ) );
		add_action( 'add_attachment', array( __CLASS__, 'handle_new_attachment' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets( string $hook ): void {
		unset( $hook );
		$screen = get_current_screen();
		if ( ! $screen || ! str_ends_with( (string) $screen->id, '_page_' . KOZSEO_Admin::PAGE ) ) {
			return;
		}

		wp_enqueue_script(
			'kozseo-alt-manager',
			KOZSEO_URL . 'assets/koz-alt-manager.js',
			array(),
			KOZSEO_VERSION,
			true
		);
		wp_localize_script(
			'kozseo-alt-manager',
			'KOZSEOAltManager',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'kozseo_ai_alt' ),
				'restAnalyzeUrl'   => rest_url( self::REST_NAMESPACE . self::REST_ANALYZE ),
				'restReanalyzeUrl' => rest_url( self::REST_NAMESPACE . '/ai-alt/reanalyze' ),
				'restNonce'        => wp_create_nonce( 'wp_rest' ),
				'applyAction'      => self::AJAX_APPLY,
				'skipAction'       => self::AJAX_SKIP,
				'maxBatch'      => self::MAX_BATCH,
				'labels' => array(
					'analyzing' => __( 'Analyzing images with OpenAI Vision…', 'koz-seo-core' ),
					'done'      => __( 'AI analysis batch completed. Reloading preview…', 'koz-seo-core' ),
					'noMore'    => __( 'No unanalyzed images remain.', 'koz-seo-core' ),
					'applyConfirm' => __( 'Approve and apply the edited ALT text to the selected images? Existing ALT values will be preserved unless the replace option is enabled.', 'koz-seo-core' ),
					'skipConfirm'  => __( 'Mark this analyzed image as skipped? Its current WordPress ALT will not be changed.', 'koz-seo-core' ),
					'reanalyzing'  => __( 'Re-analyzing the selected image with OpenAI Vision…', 'koz-seo-core' ),
					'error'        => __( 'The AI ALT operation stopped because an error was returned.', 'koz-seo-core' ),
				),
			)
		);
	}

	public static function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ANALYZE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_analyze_next' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_REANALYZE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_reanalyze' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	private static function ai_ready(): bool {
		if ( ! function_exists( 'wp_ai_client_prompt' ) || ! function_exists( 'wp_supports_ai' ) || ! wp_supports_ai() ) {
			return false;
		}

		$builder = wp_ai_client_prompt( 'Capability check.' )
			->using_provider( 'openai' )
			->using_model_preference( 'gpt-5.6-luna', 'gpt-5.6-terra', 'gpt-5.4-nano' );

		return $builder->is_supported_for_text_generation();
	}

	private static function is_processed( int $id ): bool {
		$status = (string) get_post_meta( $id, self::META_STATUS, true );
		return in_array( $status, array( 'analyzed', 'applied', 'skipped' ), true );
	}

	private static function cursor_option( bool $only_empty ): string {
		return $only_empty ? self::CURSOR_EMPTY_OPTION : self::CURSOR_OPTION;
	}

	private static function next_unanalyzed_id( bool $only_empty = true ): int {
		$cursor_option = self::cursor_option( $only_empty );
		$offset        = max( 0, (int) get_option( $cursor_option, 0 ) );

		while ( true ) {
			$ids = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'post_mime_type' => 'image',
					'posts_per_page' => 1,
					'offset'         => $offset,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);

			if ( empty( $ids ) ) {
				return 0;
			}

			$id = absint( $ids[0] );
			$offset++;
			update_option( $cursor_option, $offset, false );

			if ( self::is_processed( $id ) ) {
				continue;
			}
			if ( $only_empty && '' !== trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ) {
				continue;
			}

			return $id;
		}
	}

	public static function rest_analyze_next( WP_REST_Request $request ) {
		$only_empty = $request->get_param( 'only_empty' );
		$only_empty = null === $only_empty ? true : rest_sanitize_boolean( $only_empty );

		$id = self::next_unanalyzed_id( $only_empty );
		if ( $id <= 0 ) {
			return rest_ensure_response(
				array(
					'done'    => true,
					'message' => __( 'No unanalyzed images remain in this queue.', 'koz-seo-core' ),
				)
			);
		}

		$result = self::analyze_attachment( $id, false );
		if ( is_wp_error( $result ) ) {
			self::rewind_cursor( $only_empty );
			$data   = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? absint( $data['status'] ) : 400;
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => $status > 0 ? $status : 400 )
			);
		}

		return rest_ensure_response(
			array(
				'done'  => false,
				'id'    => $id,
				'row'   => $result,
				'stats' => self::stats(),
			)
		);
	}

	public static function rest_reanalyze( WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
			return new WP_Error( 'kozseo_invalid_image', __( 'Select a valid image attachment.', 'koz-seo-core' ), array( 'status' => 400 ) );
		}

		$result = self::analyze_attachment( $id, true );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array(
				'id'    => $id,
				'row'   => $result,
				'stats' => self::stats(),
			)
		);
	}

	private static function rewind_cursor( bool $only_empty ): void {
		$cursor_option = self::cursor_option( $only_empty );
		$offset        = max( 0, (int) get_option( $cursor_option, 0 ) );
		if ( $offset > 0 ) {
			update_option( $cursor_option, $offset - 1, false );
		}
	}

	public static function ajax_analyze_next(): void {
		check_ajax_referer( 'kozseo_ai_alt', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'koz-seo-core' ) ), 403 );
		}
		if ( ! self::ai_ready() ) {
			wp_send_json_error(
				array( 'message' => __( 'OpenAI is not ready. On WordPress 7.0+, install/configure the OpenAI provider under Settings → Connectors.', 'koz-seo-core' ) ),
				400
			);
		}

		$id = self::next_unanalyzed_id( true );
		if ( $id <= 0 ) {
			wp_send_json_success( array( 'done' => true, 'message' => __( 'No unanalyzed images remain.', 'koz-seo-core' ) ) );
		}

		$result = self::analyze_attachment( $id, false );
		if ( is_wp_error( $result ) ) {
			update_post_meta( $id, self::META_VERSION, self::ANALYSIS_SCHEMA );
			update_post_meta( $id, self::META_STATUS, 'error' );
			update_post_meta( $id, self::META_REASON, sanitize_text_field( $result->get_error_message() ) );
			update_post_meta( $id, self::META_TIME, time() );
			self::record_analysis_stats( '', '', true );
			self::remember_recent( $id );
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'id'      => $id,
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'done' => false,
				'id'   => $id,
				'row'  => $result,
				'stats'=> self::stats(),
			)
		);
	}

	private static function analyze_attachment( int $id, bool $force_reanalysis = false ) {
		if ( ! wp_attachment_is_image( $id ) ) {
			return new WP_Error( 'kozseo_not_image', __( 'Attachment is not an image.', 'koz-seo-core' ) );
		}

		$file = get_attached_file( $id );
		if ( ! is_string( $file ) || '' === $file || ! is_readable( $file ) ) {
			return new WP_Error( 'kozseo_file_unreadable', __( 'Image file is not readable on the server.', 'koz-seo-core' ) );
		}
		$size = filesize( $file );
		if ( false !== $size && $size > self::MAX_IMAGE_BYTES ) {
			return new WP_Error( 'kozseo_file_too_large', __( 'Image is larger than the 20 MB safety limit.', 'koz-seo-core' ) );
		}

		$mime = (string) get_post_mime_type( $id );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true ) ) {
			return new WP_Error( 'kozseo_mime_unsupported', __( 'Image format is not supported by this AI ALT workflow.', 'koz-seo-core' ) );
		}

		$was_processed       = self::is_processed( $id );
		$previous_decision  = (string) get_post_meta( $id, self::META_DECISION, true );
		$previous_confidence = (string) get_post_meta( $id, self::META_CONF, true );
		if ( $force_reanalysis ) {
			$was_processed = true;
		}

		$post = get_post( $id );
		$parent_title = '';
		if ( $post instanceof WP_Post && $post->post_parent > 0 ) {
			$parent_title = wp_strip_all_tags( (string) get_the_title( $post->post_parent ), true );
		}
		$current_alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );

		$context = '' !== $parent_title
			? sprintf( 'Контекст сторінки: %s. ', self::limit_text( $parent_title, 180 ) )
			: '';

		$prompt = $context . 'Проаналізуй саме візуальний вміст прикріпленого зображення та підготуй accessibility ALT українською мовою для сайту благодійного фонду UA FREE. '
			. 'Не використовуй filename, URL або назву медіафайлу як джерело опису. '
			. 'Для змістового фото опиши лише те, що реально видно, коротко і природно, зазвичай 5–18 слів. '
			. 'Не вигадуй імена людей, посади, локації, події, діагнози чи інші факти, яких не видно. '
			. 'Не починай з фраз «зображення», «фото», «картинка». '
			. 'Якщо це чисто декоративний елемент, фон, spacer або дублюючий UI-елемент, decision=decorative і alt="". '
			. 'Якщо зміст неможливо описати впевнено, decision=uncertain і alt="". '
			. 'confidence=high став лише коли ALT очевидно відповідає видимому змісту і корисний без додаткових припущень.';

		$prompt .= ' Поверни ЛИШЕ один JSON-об’єкт без Markdown і без додаткового тексту у форматі: '
			. '{"alt":"...","decision":"content|decorative|uncertain","confidence":"high|medium|low","reason":"..."}.';

		$result = self::generate_vision_result( $prompt, $file, $mime );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		try {
			$decoded = self::decode_json_object( $result->toText() );
		} catch ( Throwable $e ) {
			return new WP_Error( 'kozseo_ai_parse', sanitize_text_field( $e->getMessage() ) );
		}
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'kozseo_ai_json', __( 'AI returned an invalid structured response.', 'koz-seo-core' ) );
		}

		$decision   = sanitize_key( (string) ( $decoded['decision'] ?? '' ) );
		$confidence = sanitize_key( (string) ( $decoded['confidence'] ?? '' ) );
		$alt         = self::clean_alt( (string) ( $decoded['alt'] ?? '' ) );
		$reason      = self::limit_text( sanitize_text_field( (string) ( $decoded['reason'] ?? '' ) ), 300 );

		if ( ! in_array( $decision, array( 'content', 'decorative', 'uncertain' ), true ) ) {
			$decision = 'uncertain';
		}
		if ( ! in_array( $confidence, array( 'high', 'medium', 'low' ), true ) ) {
			$confidence = 'low';
		}
		if ( 'content' !== $decision || '' === $alt ) {
			$alt = '';
			if ( 'content' === $decision ) {
				$decision = 'uncertain';
				$confidence = 'low';
			}
		}

		$provider = '';
		$model    = '';
		$usage    = array();
		try {
			$provider_meta = $result->getProviderMetadata();
			$model_meta    = $result->getModelMetadata();
			$token_usage   = $result->getTokenUsage();
			if ( method_exists( $provider_meta, 'getId' ) ) {
				$provider = sanitize_text_field( (string) $provider_meta->getId() );
			}
			if ( method_exists( $model_meta, 'getId' ) ) {
				$model = sanitize_text_field( (string) $model_meta->getId() );
			}
			if ( method_exists( $token_usage, 'toArray' ) ) {
				$usage = $token_usage->toArray();
			}
		} catch ( Throwable $e ) {
			// Metadata is informative only; never fail an otherwise valid ALT analysis.
		}

		update_post_meta( $id, self::META_VERSION, self::ANALYSIS_SCHEMA );
		update_post_meta( $id, self::META_STATUS, 'analyzed' );
		update_post_meta( $id, self::META_ALT, $alt );
		update_post_meta( $id, self::META_DECISION, $decision );
		update_post_meta( $id, self::META_CONF, $confidence );
		update_post_meta( $id, self::META_REASON, $reason );
		update_post_meta( $id, self::META_PROVIDER, $provider );
		update_post_meta( $id, self::META_MODEL, $model );
		update_post_meta( $id, self::META_USAGE, wp_json_encode( $usage ) );
		update_post_meta( $id, self::META_TIME, time() );
		update_post_meta( $id, self::META_CURRENT, $current_alt );
		if ( $was_processed ) {
			self::record_reanalysis_stats( $previous_decision, $previous_confidence, $decision, $confidence );
		} else {
			self::record_analysis_stats( $decision, $confidence, false );
		}
		self::remember_recent( $id );
		if ( ! $was_processed && '' === $current_alt ) {
			self::decrement_pending_count();
		}

		return self::row( $id );
	}


	private static function generate_vision_result( string $prompt, string $file, string $mime ) {
		$attempts = array(
			array( 'preferences' => true ),
			array( 'preferences' => false ),
			array( 'preferences' => false ),
		);
		$last_error = null;

		foreach ( $attempts as $index => $attempt ) {
			$builder = wp_ai_client_prompt()
				->with_text( $prompt )
				->with_file( $file, $mime )
				->using_provider( 'openai' );

			if ( $attempt['preferences'] ) {
				$builder->using_model_preference( 'gpt-5.6-luna', 'gpt-5.6-terra', 'gpt-5.4-nano' );
			}

			$result = $builder->generate_text_result();
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}

			$last_error = $result;
			if ( $index < count( $attempts ) - 1 ) {
				usleep( 250000 * ( $index + 1 ) );
			}
		}

		return $last_error instanceof WP_Error
			? $last_error
			: new WP_Error( 'kozseo_ai_generation', __( 'AI Vision generation failed.', 'koz-seo-core' ) );
	}

	private static function decode_json_object( string $text ): array {
		$text = trim( $text );
		if ( str_starts_with( $text, '```' ) ) {
			$text = preg_replace( '/^```(?:json)?\s*/i', '', $text );
			$text = preg_replace( '/\s*```$/', '', is_string( $text ) ? $text : '' );
			$text = trim( is_string( $text ) ? $text : '' );
		}

		$decoded = json_decode( $text, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$decoded = json_decode( substr( $text, $start, $end - $start + 1 ), true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		throw new RuntimeException( esc_html__( 'AI returned an invalid JSON object.', 'koz-seo-core' ) );
	}

	private static function clean_alt( string $alt ): string {
		$alt = html_entity_decode( wp_strip_all_tags( $alt, true ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ?: 'UTF-8' );
		$alt = preg_replace( '/\s+/u', ' ', $alt );
		$alt = trim( is_string( $alt ) ? $alt : '' );
		return self::limit_text( $alt, 220 );
	}

	private static function limit_text( string $text, int $max ): string {
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text, 'UTF-8' ) > $max ) {
			return trim( mb_substr( $text, 0, $max, 'UTF-8' ) );
		}
		if ( strlen( $text ) > $max ) {
			return trim( substr( $text, 0, $max ) );
		}
		return trim( $text );
	}

	public static function ajax_apply_selected(): void {
		check_ajax_referer( 'kozseo_ai_alt', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'koz-seo-core' ) ), 403 );
		}

		$raw_ids = isset( $_POST['ids'] ) ? (array) map_deep( wp_unslash( $_POST['ids'] ), 'sanitize_text_field' ) : array();
		$ids     = array_values( array_unique( array_filter( array_map( 'absint', $raw_ids ) ) ) );
		$ids     = array_slice( $ids, 0, self::PREVIEW_LIMIT );
		$replace_raw = isset( $_POST['replace'] ) ? sanitize_text_field( wp_unslash( $_POST['replace'] ) ) : '';
		$replace = '1' === $replace_raw;

		$manual_alts = array();
		if ( isset( $_POST['alts'] ) && is_array( $_POST['alts'] ) ) {
			$raw_alts = map_deep( wp_unslash( $_POST['alts'] ), 'sanitize_text_field' );
			foreach ( $raw_alts as $raw_id => $raw_alt ) {
				$id = absint( $raw_id );
				if ( $id > 0 ) {
					$manual_alts[ $id ] = self::clean_alt( sanitize_text_field( (string) $raw_alt ) );
				}
			}
		}

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one analyzed image.', 'koz-seo-core' ) ), 400 );
		}

		$updated = 0;
		$skipped = 0;
		foreach ( $ids as $id ) {
			if ( ! wp_attachment_is_image( $id ) || ! self::is_processed( $id ) ) {
				$skipped++;
				continue;
			}

			$stored_candidate = self::clean_alt( (string) get_post_meta( $id, self::META_ALT, true ) );
			$candidate        = array_key_exists( $id, $manual_alts ) ? $manual_alts[ $id ] : $stored_candidate;
			$manually_edited  = $candidate !== $stored_candidate;
			$decision         = (string) get_post_meta( $id, self::META_DECISION, true );
			$confidence       = (string) get_post_meta( $id, self::META_CONF, true );

			if ( '' === $candidate ) {
				$skipped++;
				continue;
			}
			if ( ! $manually_edited && ( 'content' !== $decision || ! in_array( $confidence, array( 'high', 'medium' ), true ) ) ) {
				$skipped++;
				continue;
			}

			$current = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
			if ( '' !== $current && ! $replace ) {
				$skipped++;
				continue;
			}

			if ( $manually_edited ) {
				update_post_meta( $id, self::META_ALT, $candidate );
			}
			update_post_meta( $id, '_wp_attachment_image_alt', $candidate );
			update_post_meta( $id, self::META_STATUS, 'applied' );
			$updated++;
		}
		self::record_applied_stats( $updated );

		wp_send_json_success(
			array(
				'updated' => $updated,
				'skipped' => $skipped,
				'stats'   => self::stats(),
			)
		);
	}

	public static function ajax_skip_selected(): void {
		check_ajax_referer( 'kozseo_ai_alt', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'koz-seo-core' ) ), 403 );
		}

		$raw_ids = isset( $_POST['ids'] ) ? (array) map_deep( wp_unslash( $_POST['ids'] ), 'sanitize_text_field' ) : array();
		$ids     = array_values( array_unique( array_filter( array_map( 'absint', $raw_ids ) ) ) );
		$ids     = array_slice( $ids, 0, self::PREVIEW_LIMIT );
		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one analyzed image.', 'koz-seo-core' ) ), 400 );
		}

		$skipped = 0;
		foreach ( $ids as $id ) {
			if ( ! wp_attachment_is_image( $id ) || ! self::is_processed( $id ) ) {
				continue;
			}
			update_post_meta( $id, self::META_STATUS, 'skipped' );
			self::remember_recent( $id );
			$skipped++;
		}

		wp_send_json_success(
			array(
				'skipped' => $skipped,
				'stats'   => self::stats(),
			)
		);
	}

	public static function handle_clear_analysis(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'koz-seo-core' ) );
		}
		check_admin_referer( self::CLEAR_ACTION );

		$keys = array(
			self::META_VERSION,
			self::META_ALT,
			self::META_DECISION,
			self::META_CONF,
			self::META_REASON,
			self::META_PROVIDER,
			self::META_MODEL,
			self::META_USAGE,
			self::META_TIME,
			self::META_CURRENT,
			self::META_STATUS,
		);
		foreach ( $keys as $key ) {
			delete_post_meta_by_key( $key );
		}
		delete_option( self::CURSOR_OPTION );
		delete_option( self::CURSOR_EMPTY_OPTION );
		delete_option( self::RECENT_OPTION );
		delete_option( self::STATS_OPTION );
		delete_transient( self::PENDING_TRANSIENT );

		wp_safe_redirect( add_query_arg( 'page', KOZSEO_Admin::PAGE, admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function default_stats(): array {
		return array(
			'analyzed'   => 0,
			'applied'    => 0,
			'errors'     => 0,
			'high'       => 0,
			'decorative' => 0,
			'uncertain'  => 0,
		);
	}

	private static function stored_stats(): array {
		$stored = get_option( self::STATS_OPTION, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::default_stats() );
	}

	private static function save_stats( array $stats ): void {
		$clean = self::default_stats();
		foreach ( array_keys( $clean ) as $key ) {
			$clean[ $key ] = max( 0, absint( $stats[ $key ] ?? 0 ) );
		}
		update_option( self::STATS_OPTION, $clean, false );
	}

	private static function record_analysis_stats( string $decision, string $confidence, bool $error = false ): void {
		$stats = self::stored_stats();
		if ( $error ) {
			$stats['errors']++;
		} else {
			$stats['analyzed']++;
			if ( 'content' === $decision && 'high' === $confidence ) {
				$stats['high']++;
			}
			if ( 'decorative' === $decision ) {
				$stats['decorative']++;
			}
			if ( 'uncertain' === $decision ) {
				$stats['uncertain']++;
			}
		}
		self::save_stats( $stats );
	}

	private static function record_reanalysis_stats( string $old_decision, string $old_confidence, string $new_decision, string $new_confidence ): void {
		$stats = self::stored_stats();

		if ( 'content' === $old_decision && 'high' === $old_confidence && $stats['high'] > 0 ) {
			$stats['high']--;
		}
		if ( 'decorative' === $old_decision && $stats['decorative'] > 0 ) {
			$stats['decorative']--;
		}
		if ( 'uncertain' === $old_decision && $stats['uncertain'] > 0 ) {
			$stats['uncertain']--;
		}

		if ( 'content' === $new_decision && 'high' === $new_confidence ) {
			$stats['high']++;
		}
		if ( 'decorative' === $new_decision ) {
			$stats['decorative']++;
		}
		if ( 'uncertain' === $new_decision ) {
			$stats['uncertain']++;
		}

		self::save_stats( $stats );
	}

	private static function record_applied_stats( int $count ): void {
		if ( $count <= 0 ) {
			return;
		}
		$stats = self::stored_stats();
		$stats['applied'] += $count;
		self::save_stats( $stats );
	}

	private static function remember_recent( int $id ): void {
		$recent = get_option( self::RECENT_OPTION, array() );
		$recent = is_array( $recent ) ? array_map( 'absint', $recent ) : array();
		array_unshift( $recent, $id );
		$recent = array_values( array_unique( array_filter( $recent ) ) );
		update_option( self::RECENT_OPTION, array_slice( $recent, 0, self::PREVIEW_LIMIT ), false );
	}

	public static function handle_new_attachment( int $attachment_id ): void {
		if ( wp_attachment_is_image( $attachment_id ) ) {
			self::invalidate_pending_count();
		}
	}

	private static function invalidate_pending_count(): void {
		delete_transient( self::PENDING_TRANSIENT );
	}

	private static function decrement_pending_count(): void {
		$cached = get_transient( self::PENDING_TRANSIENT );
		if ( false !== $cached ) {
			set_transient( self::PENDING_TRANSIENT, max( 0, absint( $cached ) - 1 ), 5 * MINUTE_IN_SECONDS );
		}
	}

	private static function pending_empty_count(): int {
		$cached = get_transient( self::PENDING_TRANSIENT );
		if ( false !== $cached ) {
			return max( 0, absint( $cached ) );
		}

		$count = 0;
		$page  = 1;
		$per_page = 500;
		do {
			$ids = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'post_mime_type' => 'image',
					'posts_per_page' => $per_page,
					'paged'          => $page,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			if ( empty( $ids ) ) {
				break;
			}
			update_meta_cache( 'post', $ids );
			foreach ( $ids as $id ) {
				$id = absint( $id );
				if ( $id > 0 && ! self::is_processed( $id ) && '' === trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ) {
					$count++;
				}
			}
			$page++;
		} while ( count( $ids ) === $per_page );

		set_transient( self::PENDING_TRANSIENT, $count, 5 * MINUTE_IN_SECONDS );
		return $count;
	}

	private static function image_total(): int {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			)
		);
		return max( 0, (int) $query->found_posts );
	}

	private static function stats(): array {
		$stats = self::stored_stats();
		$total = self::image_total();
		$stats['total'] = $total;
		$stats['remaining']     = max( 0, $total - (int) $stats['analyzed'] - (int) $stats['errors'] );
		$stats['pending_empty'] = self::pending_empty_count();
		return $stats;
	}

	private static function preview_rows(): array {
		$ids = get_option( self::RECENT_OPTION, array() );
		if ( ! is_array( $ids ) ) {
			return array();
		}
		$ids = array_slice( array_values( array_filter( array_map( 'absint', $ids ) ) ), 0, self::PREVIEW_LIMIT );
		$ids = array_values(
			array_filter(
				$ids,
				static function ( int $id ): bool {
					return $id > 0 && wp_attachment_is_image( $id ) && 'skipped' !== (string) get_post_meta( $id, self::META_STATUS, true );
				}
			)
		);
		return array_map( array( __CLASS__, 'row' ), $ids );
	}

	private static function row( int $id ): array {
		$file = (string) get_attached_file( $id );
		return array(
			'id'          => $id,
			'filename'    => '' !== $file ? wp_basename( $file ) : '',
			'current_alt' => trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
			'candidate'   => (string) get_post_meta( $id, self::META_ALT, true ),
			'decision'    => (string) get_post_meta( $id, self::META_DECISION, true ),
			'confidence'  => (string) get_post_meta( $id, self::META_CONF, true ),
			'reason'      => (string) get_post_meta( $id, self::META_REASON, true ),
			'provider'    => (string) get_post_meta( $id, self::META_PROVIDER, true ),
			'model'       => (string) get_post_meta( $id, self::META_MODEL, true ),
			'status'      => (string) get_post_meta( $id, self::META_STATUS, true ),
		);
	}

	public static function render_panel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$ready = self::ai_ready();
		$stats = self::stats();
		$rows  = self::preview_rows();
		?>
		<hr style="margin:32px 0">
		<h2><?php esc_html_e( 'AI Vision ALT', 'koz-seo-core' ); ?></h2>
		<p><?php esc_html_e( 'Vision-first workflow for large media libraries. Each image is actually inspected by an AI vision model. Filename/title heuristics from 2.1.6 are no longer used for new candidates.', 'koz-seo-core' ); ?></p>
		<p><strong><?php esc_html_e( 'Safety:', 'koz-seo-core' ); ?></strong> <?php esc_html_e( 'Analysis never changes WordPress ALT values. You can edit every AI candidate before approval, skip a result, or re-analyze an individual image. Existing ALT values are preserved unless you explicitly enable replacement.', 'koz-seo-core' ); ?></p>
		<p><?php esc_html_e( 'Routine use: keep the default queue on “Empty ALT, not analyzed”. After the initial backlog is processed, newly uploaded images without ALT automatically appear in the pending counter and can be analyzed in later batches.', 'koz-seo-core' ); ?></p>

		<?php if ( ! $ready ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'OpenAI Vision is not ready. WordPress 7.0+ must have the “AI Provider for OpenAI” connector installed and configured under Settings → Connectors.', 'koz-seo-core' ); ?></p></div>
		<?php else : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'OpenAI connector detected and a multimodal text-generation model is available.', 'koz-seo-core' ); ?></p></div>
		<?php endif; ?>

		<table class="widefat striped" style="max-width:900px"><tbody>
		<tr><th><?php esc_html_e( 'Images in Media Library', 'koz-seo-core' ); ?></th><td id="kozseo-ai-stat-total"><?php echo esc_html( (string) $stats['total'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Analyzed by Vision', 'koz-seo-core' ); ?></th><td id="kozseo-ai-stat-analyzed"><?php echo esc_html( (string) $stats['analyzed'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'High-confidence content candidates', 'koz-seo-core' ); ?></th><td id="kozseo-ai-stat-high"><?php echo esc_html( (string) $stats['high'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Decorative / keep empty', 'koz-seo-core' ); ?></th><td id="kozseo-ai-stat-decorative"><?php echo esc_html( (string) $stats['decorative'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Uncertain / review', 'koz-seo-core' ); ?></th><td id="kozseo-ai-stat-uncertain"><?php echo esc_html( (string) $stats['uncertain'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Applied by this workflow', 'koz-seo-core' ); ?></th><td id="kozseo-ai-stat-applied"><?php echo esc_html( (string) $stats['applied'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Errors', 'koz-seo-core' ); ?></th><td id="kozseo-ai-stat-errors"><?php echo esc_html( (string) $stats['errors'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Not analyzed yet', 'koz-seo-core' ); ?></th><td id="kozseo-ai-stat-remaining"><?php echo esc_html( (string) $stats['remaining'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Pending empty ALT (new/unprocessed)', 'koz-seo-core' ); ?></th><td id="kozseo-ai-stat-pending_empty"><?php echo esc_html( (string) $stats['pending_empty'] ); ?></td></tr>
		</tbody></table>

		<p style="margin-top:16px">
			<label for="kozseo-ai-scope"><strong><?php esc_html_e( 'Queue', 'koz-seo-core' ); ?></strong></label>
			<select id="kozseo-ai-scope">
				<option value="empty" selected><?php esc_html_e( 'Empty ALT, not analyzed', 'koz-seo-core' ); ?></option>
				<option value="all"><?php esc_html_e( 'All unprocessed images (including existing ALT)', 'koz-seo-core' ); ?></option>
			</select>
			&nbsp;&nbsp;
			<label for="kozseo-ai-batch-size"><strong><?php esc_html_e( 'Batch size', 'koz-seo-core' ); ?></strong></label>
			<select id="kozseo-ai-batch-size">
				<option value="5">5</option>
				<option value="10">10</option>
				<option value="25" selected>25</option>
				<option value="50">50</option>
			</select>
			<button type="button" class="button button-primary" id="kozseo-ai-analyze"><?php esc_html_e( 'Analyze next batch with OpenAI Vision', 'koz-seo-core' ); ?></button>
		</p>
		<div id="kozseo-ai-progress" aria-live="polite"></div>

		<?php if ( ! empty( $rows ) ) : ?>
			<h3><?php esc_html_e( 'Latest AI Vision results', 'koz-seo-core' ); ?></h3>
			<p><?php esc_html_e( 'Review the actual thumbnail and candidate. Edit the ALT text directly before approval. AI high/medium content candidates can be approved unchanged; a manually edited non-empty ALT can also be approved explicitly.', 'koz-seo-core' ); ?></p>
			<table class="widefat striped" id="kozseo-ai-preview"><thead><tr>
				<th style="width:34px"><input type="checkbox" id="kozseo-ai-select-all" aria-label="<?php esc_attr_e( 'Select all applicable rows', 'koz-seo-core' ); ?>"></th>
				<th><?php esc_html_e( 'Image', 'koz-seo-core' ); ?></th>
				<th><?php esc_html_e( 'Current ALT', 'koz-seo-core' ); ?></th>
				<th><?php esc_html_e( 'AI candidate', 'koz-seo-core' ); ?></th>
				<th><?php esc_html_e( 'Decision', 'koz-seo-core' ); ?></th>
				<th><?php esc_html_e( 'Confidence', 'koz-seo-core' ); ?></th>
				<th><?php esc_html_e( 'Reason', 'koz-seo-core' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'koz-seo-core' ); ?></th>
			</tr></thead><tbody>
			<?php foreach ( $rows as $row ) : ?>
			<tr data-kozseo-ai-id="<?php echo esc_attr( (string) $row['id'] ); ?>">
				<td><input class="kozseo-ai-row" type="checkbox" value="<?php echo esc_attr( (string) $row['id'] ); ?>" aria-label="<?php esc_attr_e( 'Approve this ALT row', 'koz-seo-core' ); ?>"></td>
				<td style="min-width:120px"><?php echo wp_kses_post( wp_get_attachment_image( $row['id'], array( 100, 100 ), false, array( 'style' => 'max-width:100px;height:auto;display:block' ) ) ); ?><small>#<?php echo esc_html( (string) $row['id'] ); ?> <?php echo esc_html( $row['filename'] ); ?></small></td>
				<td><?php echo '' !== $row['current_alt'] ? esc_html( $row['current_alt'] ) : '<em>empty</em>'; ?></td>
				<td style="min-width:280px"><input type="text" class="regular-text kozseo-ai-alt-edit" data-id="<?php echo esc_attr( (string) $row['id'] ); ?>" value="<?php echo esc_attr( $row['candidate'] ); ?>" maxlength="220" style="width:100%"><small><?php esc_html_e( 'Editable before approval', 'koz-seo-core' ); ?></small></td>
				<td><?php echo esc_html( $row['decision'] ); ?></td>
				<td><?php echo esc_html( $row['confidence'] ); ?></td>
				<td><?php echo esc_html( $row['reason'] ); ?><br><small><?php echo esc_html( trim( $row['provider'] . ' ' . $row['model'] ) ); ?></small></td>
				<td style="white-space:nowrap"><button type="button" class="button-link kozseo-ai-reanalyze" data-id="<?php echo esc_attr( (string) $row['id'] ); ?>"><?php esc_html_e( 'Re-analyze', 'koz-seo-core' ); ?></button><br><button type="button" class="button-link-delete kozseo-ai-skip" data-id="<?php echo esc_attr( (string) $row['id'] ); ?>"><?php esc_html_e( 'Skip', 'koz-seo-core' ); ?></button></td>
			</tr>
			<?php endforeach; ?>
			</tbody></table>
			<p>
				<label><input type="checkbox" id="kozseo-ai-replace"> <?php esc_html_e( 'Allow replacing an existing ALT for selected rows', 'koz-seo-core' ); ?></label><br>
				<button type="button" class="button" id="kozseo-ai-apply-selected"><?php esc_html_e( 'Approve & apply selected ALT', 'koz-seo-core' ); ?></button>
			</p>
		<?php endif; ?>

		<p style="margin-top:24px"><a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::CLEAR_ACTION ), self::CLEAR_ACTION ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Clear only AI analysis/candidate metadata so images can be analyzed again? Existing WordPress ALT values will NOT be changed.', 'koz-seo-core' ) ); ?>');"><?php esc_html_e( 'Reset AI analysis data', 'koz-seo-core' ); ?></a></p>
		<?php
	}
}
