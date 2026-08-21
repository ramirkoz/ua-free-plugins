<?php
/**
 * KOZ URL-Only Comment Spam admin support panel.
 *
 * @package KOZ_URL_Only_Comment_Spam
 */

declare(strict_types=1);

namespace ramirkz\kozurlspam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZURLSPAM_Admin_Support_Panel {
	private static bool $initialized = false;
	private static bool $rendered = false;

	public static function init(): void {
		if ( self::$initialized || ! is_admin() ) {
			return;
		}

		self::$initialized = true;
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( __CLASS__, 'render' ), 50 );
	}

	private static function is_plugin_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! is_object( $screen ) ) {
			return false;
		}

		return str_contains( strtolower( (string) $screen->id ), 'koz-url-only-comment-spam' );
	}

	private static function shared_panel_available(): bool {
		return class_exists( 'KOZ_Admin_Support_Panel_V1', false );
	}

	public static function enqueue_assets(): void {
		if ( self::shared_panel_available() || ! current_user_can( 'manage_options' ) || ! self::is_plugin_screen() ) {
			return;
		}

		wp_enqueue_style(
			'kozurlspam-support-panel',
			plugins_url( '../assets/koz-admin-support-panel.css', __FILE__ ),
			array(),
			KOZURLSPAM_VERSION
		);
		wp_enqueue_script(
			'kozurlspam-support-panel',
			plugins_url( '../assets/koz-admin-support-panel.js', __FILE__ ),
			array(),
			KOZURLSPAM_VERSION,
			true
		);
		wp_localize_script(
			'kozurlspam-support-panel',
			'KOZURLSPAMI18n',
			array(
				'copy'   => __( 'Copy', 'koz-url-only-comment-spam' ),
				'copied' => __( 'Copied', 'koz-url-only-comment-spam' ),
				'status' => __( 'Address copied.', 'koz-url-only-comment-spam' ),
			)
		);
	}

	public static function render(): void {
		if ( self::$rendered || self::shared_panel_available() || ! current_user_can( 'manage_options' ) || ! self::is_plugin_screen() ) {
			return;
		}

		self::$rendered = true;
		?>
		<section id="kozurlspam-support-panel" class="koz-support-panel" aria-label="<?php echo esc_attr__( 'KOZ project support', 'koz-url-only-comment-spam' ); ?>" hidden>
			<div class="koz-support-panel__header">
				<div>
					<span class="koz-support-panel__eyebrow"><?php echo esc_html__( 'KOZ WordPress Suite', 'koz-url-only-comment-spam' ); ?></span>
					<h2><?php echo esc_html__( 'Support independent development and Ukraine', 'koz-url-only-comment-spam' ); ?></h2>
					<p><?php echo esc_html__( 'Developed and maintained by Tony Kozyriev. The suite began as production tooling for the UA FREE charitable foundation and remains in active use on its website.', 'koz-url-only-comment-spam' ); ?></p>
				</div>
				<span class="koz-support-panel__badge"><?php echo esc_html__( 'Free, open source and privacy-conscious', 'koz-url-only-comment-spam' ); ?></span>
			</div>
			<div class="koz-support-panel__grid">
				<article class="koz-support-card koz-support-card--developer">
					<div class="koz-support-card__title">
						<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
						<div>
							<h3><?php echo esc_html__( 'Support the developer', 'koz-url-only-comment-spam' ); ?></h3>
							<p><?php echo esc_html__( 'PayPal and crypto donations support maintenance, testing and new free releases.', 'koz-url-only-comment-spam' ); ?></p>
						</div>
					</div>
					<div class="koz-support-wallets">
						<div class="koz-support-wallet"><span class="koz-support-wallet__network">BTC</span><code>bc1q4dn8e7sz2866g7qp1qtshh98j54tvuau5ghuuk</code><button type="button" class="button button-small koz-support-wallet__copy" data-koz-copy="bc1q4dn8e7sz2866g7qp1qtshh98j54tvuau5ghuuk"><?php echo esc_html__( 'Copy', 'koz-url-only-comment-spam' ); ?></button></div>
						<div class="koz-support-wallet"><span class="koz-support-wallet__network">ETH / USDC</span><code>0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229</code><button type="button" class="button button-small koz-support-wallet__copy" data-koz-copy="0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229"><?php echo esc_html__( 'Copy', 'koz-url-only-comment-spam' ); ?></button></div>
						<div class="koz-support-wallet"><span class="koz-support-wallet__network">USDT TRC-20</span><code>TYsGyK7K3XB4NPHprf5w8ZodFafxFfDdbP</code><button type="button" class="button button-small koz-support-wallet__copy" data-koz-copy="TYsGyK7K3XB4NPHprf5w8ZodFafxFfDdbP"><?php echo esc_html__( 'Copy', 'koz-url-only-comment-spam' ); ?></button></div>
						<div class="koz-support-wallet koz-support-wallet--paypal"><span class="koz-support-wallet__network">PayPal</span><code>kozyriev@uafree.org</code><div class="koz-support-wallet__actions"><a class="button button-small button-primary" href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=kozyriev%40uafree.org&amp;item_name=Support+KOZ+WordPress+plugin+development&amp;currency_code=USD" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Donate', 'koz-url-only-comment-spam' ); ?></a><button type="button" class="button button-small koz-support-wallet__copy" data-koz-copy="kozyriev@uafree.org"><?php echo esc_html__( 'Copy', 'koz-url-only-comment-spam' ); ?></button></div></div>
					</div>
				</article>
				<article class="koz-support-card koz-support-card--foundation">
					<div class="koz-support-card__title">
						<span class="dashicons dashicons-heart" aria-hidden="true"></span>
						<div>
							<h3><?php echo esc_html__( 'Support Ukraine through UA FREE', 'koz-url-only-comment-spam' ); ?></h3>
							<p><?php echo esc_html__( 'The foundation was the first production environment for these plugins. Its charitable work is separate from plugin ownership and developer donations.', 'koz-url-only-comment-spam' ); ?></p>
						</div>
					</div>
					<div class="koz-support-card__actions"><a class="button button-primary" href="https://uafree.org/donate/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Donate to UA FREE', 'koz-url-only-comment-spam' ); ?></a><a class="button" href="https://uafree.org/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'About the foundation', 'koz-url-only-comment-spam' ); ?></a></div>
				</article>
			</div>
			<p class="screen-reader-text" id="kozurlspam-support-copy-status" aria-live="polite"></p>
		</section>
		<?php
	}
}

