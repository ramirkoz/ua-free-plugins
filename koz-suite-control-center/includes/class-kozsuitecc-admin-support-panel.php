<?php
/**
 * KOZ Suite Control Center admin support panel.
 *
 * The panel is plugin-specific and renders only on this plugin's screens.
 */

namespace ramirkz\kozsuitecc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'UAFree_Admin_Support_Panel_V2', false ) ) {
	remove_action( 'admin_footer', array( 'UAFree_Admin_Support_Panel_V2', 'render' ), 50 );
}
if ( class_exists( 'UAFree_Admin_Support_Footer', false ) ) {
	remove_action( 'admin_footer', array( 'UAFree_Admin_Support_Footer', 'render' ), 50 );
}

if ( ! class_exists( __NAMESPACE__ . '\\KOZSUITECC_Admin_Support_Panel', false ) ) {
	final class KOZSUITECC_Admin_Support_Panel {
		private static bool $initialized = false;
		private static bool $rendered    = false;

		public static function init(): void {
			if ( self::$initialized || ! is_admin() ) {
				return;
			}

			self::$initialized = true;
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
			add_action( 'admin_footer', array( __CLASS__, 'render' ), 50 );
		}

		private static function is_suite_screen(): bool {
			if ( ! function_exists( 'get_current_screen' ) ) {
				return false;
			}

			$screen = get_current_screen();
			if ( ! is_object( $screen ) ) {
				return false;
			}

			$screen_id = strtolower( (string) $screen->id );
			return str_contains( $screen_id, 'kozsuitecc-control-center' )
				|| str_contains( $screen_id, 'koz-suite-control-center' )
				|| str_contains( $screen_id, 'kozsuitecc-suite' );
		}

		public static function enqueue_assets(): void {
			// Prefer the shared KOZ Suite support renderer when another suite component provides it.
			if ( class_exists( 'KOZ_Admin_Support_Panel_V1', false ) ) {
				return;
			}

			if ( ! current_user_can( 'manage_options' ) || ! self::is_suite_screen() ) {
				return;
			}

			wp_enqueue_style(
				'kozsuitecc-admin-support-panel',
				plugins_url( '../assets/kozsuitecc-admin-support-panel.css', __FILE__ ),
				array(),
				'1.0.0'
			);
			wp_enqueue_script(
				'kozsuitecc-admin-support-panel',
				plugins_url( '../assets/kozsuitecc-admin-support-panel.js', __FILE__ ),
				array(),
				'1.0.0',
				true
			);
			wp_localize_script(
				'kozsuitecc-admin-support-panel',
				'KOZSUITECCSupportI18n',
				array(
					'copy'   => __( 'Copy', 'koz-suite-control-center' ),
					'copied' => __( 'Copied', 'koz-suite-control-center' ),
					'status' => __( 'Address copied.', 'koz-suite-control-center' ),
				)
			);
		}

		public static function render(): void {
			// Do not duplicate the suite-wide panel supplied by KOZ Suite Hub or another shared provider.
			if ( class_exists( 'KOZ_Admin_Support_Panel_V1', false ) ) {
				return;
			}

			if ( self::$rendered || ! current_user_can( 'manage_options' ) || ! self::is_suite_screen() ) {
				return;
			}

			self::$rendered = true;
			?>
			<section id="kozsuitecc-admin-support-panel" class="koz-support-panel" aria-label="<?php echo esc_attr__( 'KOZ project support', 'koz-suite-control-center' ); ?>" hidden>
				<div class="koz-support-panel__header">
					<div>
						<span class="koz-support-panel__eyebrow"><?php echo esc_html__( 'KOZ WordPress Suite', 'koz-suite-control-center' ); ?></span>
						<h2><?php echo esc_html__( 'Support independent development and Ukraine', 'koz-suite-control-center' ); ?></h2>
						<p><?php echo esc_html__( 'Developed and maintained by Tony Kozyriev. The suite began as production tooling for the UA FREE charitable foundation and remains in active use on its website.', 'koz-suite-control-center' ); ?></p>
					</div>
					<span class="koz-support-panel__badge"><?php echo esc_html__( 'Free, open source and privacy-conscious', 'koz-suite-control-center' ); ?></span>
				</div>

				<div class="koz-support-panel__grid">
					<article class="koz-support-card koz-support-card--developer">
						<div class="koz-support-card__title">
							<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
							<div>
								<h3><?php echo esc_html__( 'Support the developer', 'koz-suite-control-center' ); ?></h3>
								<p><?php echo esc_html__( 'PayPal and crypto donations support maintenance, testing and new free releases.', 'koz-suite-control-center' ); ?></p>
							</div>
						</div>

						<div class="koz-support-wallets">
							<div class="koz-support-wallet">
								<span class="koz-support-wallet__network">BTC</span>
								<code>bc1q4dn8e7sz2866g7qp1qtshh98j54tvuau5ghuuk</code>
								<button type="button" class="button button-small koz-support-wallet__copy" data-koz-copy="bc1q4dn8e7sz2866g7qp1qtshh98j54tvuau5ghuuk" aria-label="<?php echo esc_attr__( 'Copy BTC address', 'koz-suite-control-center' ); ?>"><?php echo esc_html__( 'Copy', 'koz-suite-control-center' ); ?></button>
							</div>
							<div class="koz-support-wallet">
								<span class="koz-support-wallet__network">ETH / USDC</span>
								<code>0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229</code>
								<button type="button" class="button button-small koz-support-wallet__copy" data-koz-copy="0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229" aria-label="<?php echo esc_attr__( 'Copy ETH and USDC address', 'koz-suite-control-center' ); ?>"><?php echo esc_html__( 'Copy', 'koz-suite-control-center' ); ?></button>
							</div>
							<div class="koz-support-wallet">
								<span class="koz-support-wallet__network">USDT TRC-20</span>
								<code>TYsGyK7K3XB4NPHprf5w8ZodFafxFfDdbP</code>
								<button type="button" class="button button-small koz-support-wallet__copy" data-koz-copy="TYsGyK7K3XB4NPHprf5w8ZodFafxFfDdbP" aria-label="<?php echo esc_attr__( 'Copy USDT address', 'koz-suite-control-center' ); ?>"><?php echo esc_html__( 'Copy', 'koz-suite-control-center' ); ?></button>
							</div>
							<div class="koz-support-wallet koz-support-wallet--paypal">
								<span class="koz-support-wallet__network">PayPal</span>
								<code>kozyriev@uafree.org</code>
								<div class="koz-support-wallet__actions">
									<a class="button button-small button-primary" href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=kozyriev%40uafree.org&amp;item_name=Support+KOZ+WordPress+plugin+development&amp;currency_code=USD" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Donate', 'koz-suite-control-center' ); ?></a>
									<button type="button" class="button button-small koz-support-wallet__copy" data-koz-copy="kozyriev@uafree.org" aria-label="<?php echo esc_attr__( 'Copy PayPal email', 'koz-suite-control-center' ); ?>"><?php echo esc_html__( 'Copy', 'koz-suite-control-center' ); ?></button>
								</div>
							</div>
						</div>
						<p class="koz-support-contact"><?php echo esc_html__( 'Development contact:', 'koz-suite-control-center' ); ?> <a href="mailto:ramir@ua.fm">ramir@ua.fm</a> · <a href="https://github.com/ramirkoz/" target="_blank" rel="noopener noreferrer">GitHub</a> · <a href="https://www.linkedin.com/in/tonykoz/" target="_blank" rel="noopener noreferrer">LinkedIn</a></p>
						<p class="screen-reader-text" id="kozsuitecc-support-copy-status" aria-live="polite"></p>
					</article>

					<article class="koz-support-card koz-support-card--foundation">
						<div class="koz-support-card__title">
							<span class="dashicons dashicons-heart" aria-hidden="true"></span>
							<div>
								<h3><?php echo esc_html__( 'Support Ukraine through UA FREE', 'koz-suite-control-center' ); ?></h3>
								<p><?php echo esc_html__( 'The foundation was the first production environment for these plugins. Its charitable work is separate from plugin ownership and developer donations.', 'koz-suite-control-center' ); ?></p>
							</div>
						</div>
						<div class="koz-support-card__actions">
							<a class="button button-primary" href="https://uafree.org/donate/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Donate to UA FREE', 'koz-suite-control-center' ); ?></a>
							<a class="button" href="https://uafree.org/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'About the foundation', 'koz-suite-control-center' ); ?></a>
						</div>
					</article>
				</div>
			</section>
			<?php
		}
	}
}

KOZSUITECC_Admin_Support_Panel::init();
