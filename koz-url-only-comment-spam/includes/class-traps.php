<?php
/**
 * Local bot traps: a honeypot field and a signed form-timing token.
 *
 * Nothing is stored and nothing identifies the visitor. The timing token only
 * contains the time the form was rendered and an HMAC made with the site's salts.
 *
 * @package KOZ_URL_Only_Comment_Spam
 */

declare(strict_types=1);

namespace ramirkz\kozurlspam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Traps {
	private const TOKEN_FIELD = 'kozurlspam_ts';

	private static bool $form_submission = false;

	public static function boot(): void {
		add_action( 'comment_form', array( __CLASS__, 'render_fields' ) );
		// Fires only inside wp_handle_comment_submission(), i.e. for wp-comments-post.php.
		add_action( 'pre_comment_on_post', array( __CLASS__, 'mark_form_submission' ) );
	}

	public static function mark_form_submission(): void {
		self::$form_submission = true;
	}

	/**
	 * Honeypot field name. It is stable for the site but not guessable across sites.
	 */
	public static function honeypot_field(): string {
		return 'kozurlspam_' . substr( wp_hash( 'kozurlspam-honeypot' ), 0, 10 );
	}

	public static function render_fields(): void {
		$settings = Plugin::instance()->get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$time = time();
		$token = $time . '.' . self::sign( $time );

		if ( 'off' !== $settings['honeypot_action'] ) {
			$field = self::honeypot_field();
			// The wrapper is moved off-screen rather than hidden so that simple bots still fill it in.
			printf(
				'<p class="kozurlspam-hp" aria-hidden="true" style="position:absolute!important;left:-10000px!important;top:auto!important;width:1px!important;height:1px!important;overflow:hidden!important;"><label for="%1$s">%2$s</label><input type="text" id="%1$s" name="%1$s" value="" tabindex="-1" autocomplete="off"></p>',
				esc_attr( $field ),
				esc_html__( 'Leave this field empty', 'koz-url-only-comment-spam' )
			);
		}

		printf( '<input type="hidden" name="%1$s" value="%2$s">', esc_attr( self::TOKEN_FIELD ), esc_attr( $token ) );
	}

	/**
	 * Inspect the current comment form submission.
	 *
	 * The comment form has no nonce in WordPress core; these fields are anti-bot signals,
	 * not authorization, so reading them without a nonce is intended.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return string Rule key (honeypot, too_fast, missing_token) or an empty string.
	 */
	public static function check( array $settings ): string {
		if ( ! self::$form_submission ) {
			return '';
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Core comment form has no nonce; see method docblock.
		$honeypot = isset( $_POST[ self::honeypot_field() ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::honeypot_field() ] ) ) : '';
		$token = isset( $_POST[ self::TOKEN_FIELD ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::TOKEN_FIELD ] ) ) : null;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( 'off' !== $settings['honeypot_action'] && '' !== $honeypot ) {
			return 'honeypot';
		}

		if ( null === $token ) {
			return 'off' !== $settings['missing_token_action'] ? 'missing_token' : '';
		}

		$parts = explode( '.', $token, 2 );
		$time = isset( $parts[0] ) && ctype_digit( $parts[0] ) ? (int) $parts[0] : 0;
		if ( 0 === $time || ! isset( $parts[1] ) || ! hash_equals( self::sign( $time ), $parts[1] ) ) {
			return 'off' !== $settings['missing_token_action'] ? 'missing_token' : '';
		}

		// Old tokens from cached pages are fine; only implausibly fast submissions are flagged.
		$min_seconds = (int) $settings['min_seconds'];
		if ( $min_seconds > 0 && 'off' !== $settings['too_fast_action'] && ( time() - $time ) < $min_seconds ) {
			return 'too_fast';
		}

		return '';
	}

	private static function sign( int $time ): string {
		return substr( hash_hmac( 'sha256', 'kozurlspam|' . $time, wp_salt( 'nonce' ) ), 0, 20 );
	}
}
