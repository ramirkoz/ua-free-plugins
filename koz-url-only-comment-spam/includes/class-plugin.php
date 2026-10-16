<?php
/**
 * Main plugin runtime.
 *
 * @package KOZ_URL_Only_Comment_Spam
 */

declare(strict_types=1);

namespace ramirkz\kozurlspam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	public const SETTINGS_OPTION = 'kozurlspam_settings';
	public const TOTAL_OPTION    = 'kozurlspam_total';
	public const LAST_OPTION     = 'kozurlspam_last';

	/** Actions each rule may use. "off" disables the rule. */
	public const RULE_ACTIONS = array(
		'url_only'      => array( 'spam', 'hold', 'reject' ),
		'low_text'      => array( 'off', 'hold', 'spam', 'reject' ),
		'author_url'    => array( 'off', 'hold', 'spam' ),
		'honeypot'      => array( 'off', 'spam', 'reject' ),
		'too_fast'      => array( 'off', 'hold', 'spam' ),
		'missing_token' => array( 'off', 'hold', 'spam' ),
	);

	private static ?self $instance = null;
	private bool $booted = false;

	/** Rule applied to the comment currently being inserted, for the reason meta. */
	private string $pending_rule = '';

	/** @var array<string,array<string,mixed>|null> Decisions already made in this request, by comment fingerprint. */
	private array $decided = array();

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private static function legacy_option( string $suffix ): string {
		return implode( '_', array( 'uafree', 'url', 'only', 'spam', $suffix ) );
	}

	private static function migrate_legacy_options(): void {
		$map = array(
			self::SETTINGS_OPTION => self::legacy_option( 'settings' ),
			self::TOTAL_OPTION    => self::legacy_option( 'total' ),
			self::LAST_OPTION     => self::legacy_option( 'last' ),
		);

		foreach ( $map as $current => $legacy ) {
			if ( false !== get_option( $current, false ) ) {
				continue;
			}
			$value = get_option( $legacy, false );
			if ( false !== $value ) {
				add_option( $current, $value, '', false );
			}
		}
	}

	public static function activate(): void {
		self::migrate_legacy_options();
		if ( false === get_option( self::SETTINGS_OPTION, false ) ) {
			add_option( self::SETTINGS_OPTION, self::defaults(), '', false );
		}
		if ( false === get_option( self::TOTAL_OPTION, false ) ) {
			add_option( self::TOTAL_OPTION, 0, '', false );
		}
		Maintenance::sync_schedule();
	}

	public static function deactivate(): void {
		Maintenance::unschedule();
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// Legacy UA FREE options only need to be copied once; activation and the admin area cover it.
		if ( is_admin() ) {
			self::migrate_legacy_options();
		}

		add_filter( 'pre_comment_approved', array( $this, 'filter_comment_approval' ), 20, 2 );
		add_action( 'comment_post', array( $this, 'store_reason' ), 10, 1 );

		Traps::boot();
		Maintenance::boot();

		if ( is_admin() ) {
			Admin::instance()->boot();
			Comments_UI::boot();
		}
	}

	/**
	 * @param mixed               $approved    WordPress approval result.
	 * @param array<string,mixed> $commentdata Comment data.
	 * @return mixed
	 */
	public function filter_comment_approval( $approved, array $commentdata ) {
		$settings = $this->get_settings();
		if ( empty( $settings['enabled'] ) || 'spam' === $approved || 'trash' === $approved || is_wp_error( $approved ) ) {
			return $approved;
		}
		$this->pending_rule = '';

		$comment_type = isset( $commentdata['comment_type'] ) ? (string) $commentdata['comment_type'] : '';
		if ( '' !== $comment_type && ! in_array( $comment_type, (array) $settings['comment_types'], true ) ) {
			return $approved;
		}

		$user_id = isset( $commentdata['user_id'] ) ? absint( $commentdata['user_id'] ) : 0;
		if ( $user_id > 0 ) {
			if ( user_can( $user_id, 'moderate_comments' ) ) {
				return $approved;
			}
			if ( ! empty( $settings['exempt_logged_in'] ) ) {
				return $approved;
			}
		}

		// pre_comment_approved can run more than once for the same comment in one request (for example
		// when another component re-checks comment data). Decide and count each comment only once.
		$fingerprint = md5(
			(string) ( $commentdata['comment_post_ID'] ?? '' ) . '|' .
			(string) ( $commentdata['comment_author_email'] ?? '' ) . '|' .
			(string) ( $commentdata['comment_content'] ?? '' )
		);
		if ( array_key_exists( $fingerprint, $this->decided ) ) {
			$cached = $this->decided[ $fingerprint ];
			if ( null === $cached ) {
				return $approved;
			}
			$this->pending_rule = (string) $cached['rule'];
			return $this->apply_action( $cached );
		}

		$decision = $this->evaluate( $commentdata, $settings );
		if ( null === $decision ) {
			$this->decided[ $fingerprint ] = null;
			return $approved;
		}

		/**
		 * Filter the rule decision before it is applied. Return null to let the comment through.
		 *
		 * The summary never contains comment text, URLs or visitor data.
		 *
		 * @param array{rule:string,action:string,summary:array<string,mixed>}|null $decision
		 */
		$decision = apply_filters( 'kozurlspam_decision', $decision );
		if ( ! is_array( $decision ) || empty( $decision['rule'] ) || empty( $decision['action'] ) ) {
			$this->decided[ $fingerprint ] = null;
			return $approved;
		}

		$this->decided[ $fingerprint ] = $decision;
		$this->record_hit( $commentdata, $decision );
		$this->pending_rule = (string) $decision['rule'];
		return $this->apply_action( $decision );
	}

	/**
	 * @param array<string,mixed> $decision Rule decision.
	 * @return int|string|\WP_Error
	 */
	private function apply_action( array $decision ) {
		if ( 'reject' === $decision['action'] ) {
			$this->pending_rule = '';
			return new \WP_Error(
				'kozurlspam_rejected',
				__( 'Your comment could not be accepted. Please write a few words of your own and try again.', 'koz-url-only-comment-spam' ),
				403
			);
		}

		return 'hold' === $decision['action'] ? 0 : 'spam';
	}

	/**
	 * Run the rules in priority order. Bot traps come first because they do not depend on the text.
	 *
	 * @param array<string,mixed> $commentdata Comment data.
	 * @param array<string,mixed> $settings    Plugin settings.
	 * @return array{rule:string,action:string,summary:array<string,mixed>}|null
	 */
	public function evaluate( array $commentdata, array $settings ): ?array {
		$trap = Traps::check( $settings );
		if ( '' !== $trap ) {
			return $this->decision( $trap, $settings, array() );
		}

		$content = isset( $commentdata['comment_content'] ) ? (string) $commentdata['comment_content'] : '';
		$result = Detector::analyze( $content, $settings );

		if ( ! empty( $result['is_url_only'] ) ) {
			return $this->decision( 'url_only', $settings, $result );
		}

		$author_url = isset( $commentdata['comment_author_url'] ) ? trim( (string) $commentdata['comment_author_url'] ) : '';
		return $this->evaluate_text_rules( $result, $author_url, $settings );
	}

	/**
	 * Rules that need only the detector summary and the website field. Used by the admin tester too.
	 *
	 * @param array<string,mixed> $result   Detector summary.
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return array{rule:string,action:string,summary:array<string,mixed>}|null
	 */
	public function evaluate_text_rules( array $result, string $author_url, array $settings ): ?array {
		if ( ! empty( $result['is_url_only'] ) ) {
			return $this->decision( 'url_only', $settings, $result );
		}

		$text_chars = (int) ( $result['text_chars'] ?? 0 );

		if ( 'off' !== $settings['low_text_action']
			&& (int) ( $result['external_count'] ?? 0 ) > 0
			&& $text_chars <= (int) $settings['low_text_max_chars'] ) {
			return $this->decision( 'low_text', $settings, $result );
		}

		if ( 'off' !== $settings['author_url_action']
			&& '' !== $author_url
			&& 0 === (int) ( $result['url_count'] ?? 0 )
			&& $text_chars <= (int) $settings['author_url_max_chars']
			&& ! Detector::is_trusted_url( $author_url, $settings ) ) {
			return $this->decision( 'author_url', $settings, $result );
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $settings Plugin settings.
	 * @param array<string,mixed> $result   Detector summary.
	 * @return array{rule:string,action:string,summary:array<string,mixed>}
	 */
	private function decision( string $rule, array $settings, array $result ): array {
		$key = 'url_only' === $rule ? 'action' : $rule . '_action';
		$action = (string) ( $settings[ $key ] ?? 'hold' );

		return array(
			'rule'    => $rule,
			'action'  => $action,
			'summary' => array(
				'url_count'      => (int) ( $result['url_count'] ?? 0 ),
				'trusted_count'  => (int) ( $result['trusted_count'] ?? 0 ),
				'external_count' => (int) ( $result['external_count'] ?? 0 ),
				'text_chars'     => (int) ( $result['text_chars'] ?? 0 ),
			),
		);
	}

	public function store_reason( $comment_id ): void {
		if ( '' === $this->pending_rule ) {
			return;
		}

		add_comment_meta( (int) $comment_id, Comments_UI::META_KEY, $this->pending_rule, true );
		$this->pending_rule = '';
	}

	/** @return array<string,mixed> */
	public function get_settings(): array {
		$stored   = get_option( self::SETTINGS_OPTION, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$settings = array_merge( self::defaults(), $stored );
		$settings = apply_filters( 'kozurlspam_settings', $settings );
		return is_array( $settings ) ? array_merge( self::defaults(), $settings ) : self::defaults();
	}

	/** @return array<string,mixed> */
	public static function defaults(): array {
		return array(
			'enabled'                  => true,
			'action'                   => 'spam',
			'minimum_urls'             => 1,
			'exempt_logged_in'         => false,
			'trust_same_site'          => false,
			'trusted_domains'          => array(),
			'comment_types'            => array( 'comment', 'review' ),
			'low_text_action'          => 'hold',
			'low_text_max_chars'       => 40,
			'author_url_action'        => 'hold',
			'author_url_max_chars'     => 40,
			'honeypot_action'          => 'spam',
			'too_fast_action'          => 'hold',
			'min_seconds'              => 3,
			'missing_token_action'     => 'off',
			'auto_delete_days'         => 0,
			'delete_data_on_uninstall' => false,
		);
	}

	/** @return array<string,mixed> */
	public function public_status(): array {
		$settings = $this->get_settings();
		$last     = get_option( self::LAST_OPTION, array() );
		return array(
			'plugin'         => 'KOZ URL-Only Comment Spam',
			'version'        => KOZURLSPAM_VERSION,
			'enabled'        => (bool) $settings['enabled'],
			'action'         => (string) $settings['action'],
			'minimum_urls'   => (int) $settings['minimum_urls'],
			'rules'          => array(
				'low_text'      => (string) $settings['low_text_action'],
				'author_url'    => (string) $settings['author_url_action'],
				'honeypot'      => (string) $settings['honeypot_action'],
				'too_fast'      => (string) $settings['too_fast_action'],
				'missing_token' => (string) $settings['missing_token_action'],
			),
			'total_caught'   => (int) get_option( self::TOTAL_OPTION, 0 ),
			'last_caught_at' => is_array( $last ) && isset( $last['caught_at'] ) ? (string) $last['caught_at'] : '',
			'privacy'        => array(
				'stores_ip'            => false,
				'stores_user_agent'    => false,
				'stores_comment_text'  => false,
				'stores_detected_urls' => false,
			),
		);
	}

	/**
	 * @param array<string,mixed> $commentdata Comment data.
	 * @param array<string,mixed> $decision    Applied rule decision.
	 */
	private function record_hit( array $commentdata, array $decision ): void {
		$total = (int) get_option( self::TOTAL_OPTION, 0 );
		update_option( self::TOTAL_OPTION, $total + 1, false );

		$summary = (array) $decision['summary'];
		$last = array(
			'caught_at'     => current_time( 'c' ),
			'post_id'       => isset( $commentdata['comment_post_ID'] ) ? absint( $commentdata['comment_post_ID'] ) : 0,
			'rule'          => (string) $decision['rule'],
			'url_count'     => (int) ( $summary['url_count'] ?? 0 ),
			'trusted_count' => (int) ( $summary['trusted_count'] ?? 0 ),
			'action'        => (string) $decision['action'],
		);
		update_option( self::LAST_OPTION, $last, false );
		Maintenance::record( (string) $decision['rule'] );

		$event = array(
			'post_id'       => $last['post_id'],
			'rule'          => $last['rule'],
			'url_count'     => $last['url_count'],
			'trusted_count' => $last['trusted_count'],
			'action'        => $last['action'],
		);
		do_action( 'kozurlspam_caught', $event );
	}
}
