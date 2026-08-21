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

	private static ?self $instance = null;
	private bool $booted = false;

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
	}

	public function boot(): void {
		self::migrate_legacy_options();
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_filter( 'pre_comment_approved', array( $this, 'filter_comment_approval' ), 20, 2 );

		if ( is_admin() ) {
			Admin::instance()->boot();
		}
	}

	/**
	 * @param mixed               $approved    WordPress approval result.
	 * @param array<string,mixed> $commentdata Comment data.
	 * @return mixed
	 */
	public function filter_comment_approval( $approved, array $commentdata ) {
		$settings = $this->get_settings();
		if ( empty( $settings['enabled'] ) || 'spam' === $approved || 'trash' === $approved ) {
			return $approved;
		}

		$comment_type = isset( $commentdata['comment_type'] ) ? (string) $commentdata['comment_type'] : '';
		if ( '' !== $comment_type && 'comment' !== $comment_type ) {
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

		$content = isset( $commentdata['comment_content'] ) ? (string) $commentdata['comment_content'] : '';
		$result  = Detector::analyze( $content, $settings );
		if ( empty( $result['is_url_only'] ) ) {
			return $approved;
		}

		$action = 'hold' === $settings['action'] ? 0 : 'spam';
		$this->record_hit( $commentdata, $result, $action );
		return $action;
	}

	/** @return array<string,mixed> */
	public function get_settings(): array {
		$stored   = get_option( self::SETTINGS_OPTION, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$settings = array_merge( self::defaults(), $stored );
		$settings = apply_filters( 'kozurlspam_settings', $settings );
		return is_array( $settings ) ? $settings : self::defaults();
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
	 * @param array<string,mixed> $result      Detection summary.
	 * @param string|int          $action      Applied action.
	 */
	private function record_hit( array $commentdata, array $result, $action ): void {
		$total = (int) get_option( self::TOTAL_OPTION, 0 );
		update_option( self::TOTAL_OPTION, $total + 1, false );

		$last = array(
			'caught_at'     => current_time( 'c' ),
			'post_id'       => isset( $commentdata['comment_post_ID'] ) ? absint( $commentdata['comment_post_ID'] ) : 0,
			'url_count'     => isset( $result['url_count'] ) ? absint( $result['url_count'] ) : 0,
			'trusted_count' => isset( $result['trusted_count'] ) ? absint( $result['trusted_count'] ) : 0,
			'action'        => 0 === $action ? 'hold' : 'spam',
		);
		update_option( self::LAST_OPTION, $last, false );

		$event = array(
			'post_id'       => $last['post_id'],
			'url_count'     => $last['url_count'],
			'trusted_count' => $last['trusted_count'],
			'action'        => $last['action'],
		);
		do_action( 'kozurlspam_caught', $event );
	}
}
