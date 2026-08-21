<?php
namespace ramirkz\koz404;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZ404_Admin_Support_Panel {
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

		$id = strtolower( (string) $screen->id );
		return str_contains( $id, 'koz-404-guard' ) || str_contains( $id, 'koz404-suite' );
	}

	private static function shared_panel_available(): bool {
		return class_exists( 'KOZ_Admin_Support_Panel_V1', false );
	}

	public static function enqueue_assets(): void {
		if ( self::shared_panel_available() || ! current_user_can( 'manage_options' ) || ! self::is_plugin_screen() ) {
			return;
		}

		wp_enqueue_style(
			'koz404-support-panel',
			KOZ404_URL . 'assets/koz404-admin-support-panel.css',
			array(),
			KOZ404_VERSION
		);
		wp_enqueue_script(
			'koz404-support-panel',
			KOZ404_URL . 'assets/koz404-admin-support-panel.js',
			array(),
			KOZ404_VERSION,
			true
		);
		wp_localize_script(
			'koz404-support-panel',
			'KOZ404I18n',
			array(
				'copy'   => __( 'Copy', 'koz-404-guard' ),
				'copied' => __( 'Copied', 'koz-404-guard' ),
				'status' => __( 'Address copied.', 'koz-404-guard' ),
			)
		);
	}

	public static function render(): void {
		if ( self::$rendered || self::shared_panel_available() || ! current_user_can( 'manage_options' ) || ! self::is_plugin_screen() ) {
			return;
		}

		self::$rendered = true;
		?>
		<section id="koz404-support-panel" class="koz404-support-panel" aria-label="<?php echo esc_attr__( 'KOZ project support', 'koz-404-guard' ); ?>" hidden>
			<div class="koz404-support-panel__header">
				<div>
					<span class="koz404-support-panel__eyebrow"><?php echo esc_html__( 'KOZ WordPress Suite', 'koz-404-guard' ); ?></span>
					<h2><?php echo esc_html__( 'Support independent development and Ukraine', 'koz-404-guard' ); ?></h2>
					<p><?php echo esc_html__( 'Developed and maintained by Tony Kozyriev. The suite began as production tooling for the UA FREE charitable foundation and remains in active use on its website.', 'koz-404-guard' ); ?></p>
				</div>
				<span class="koz404-support-panel__badge"><?php echo esc_html__( 'Free, open source and privacy-conscious', 'koz-404-guard' ); ?></span>
			</div>
			<div class="koz404-support-panel__grid">
				<article class="koz404-support-card koz404-support-card--developer">
					<div class="koz404-support-card__title">
						<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
						<div>
							<h3><?php echo esc_html__( 'Support the developer', 'koz-404-guard' ); ?></h3>
							<p><?php echo esc_html__( 'PayPal and crypto donations support maintenance, testing and new free releases.', 'koz-404-guard' ); ?></p>
						</div>
					</div>
					<div class="koz404-support-wallets">
						<div class="koz404-support-wallet"><span class="koz404-support-wallet__network">BTC</span><code>bc1q4dn8e7sz2866g7qp1qtshh98j54tvuau5ghuuk</code><button type="button" class="button button-small koz404-support-wallet__copy" data-koz404-copy="bc1q4dn8e7sz2866g7qp1qtshh98j54tvuau5ghuuk"><?php echo esc_html__( 'Copy', 'koz-404-guard' ); ?></button></div>
						<div class="koz404-support-wallet"><span class="koz404-support-wallet__network">ETH / USDC</span><code>0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229</code><button type="button" class="button button-small koz404-support-wallet__copy" data-koz404-copy="0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229"><?php echo esc_html__( 'Copy', 'koz-404-guard' ); ?></button></div>
						<div class="koz404-support-wallet"><span class="koz404-support-wallet__network">USDT TRC-20</span><code>TYsGyK7K3XB4NPHprf5w8ZodFafxFfDdbP</code><button type="button" class="button button-small koz404-support-wallet__copy" data-koz404-copy="TYsGyK7K3XB4NPHprf5w8ZodFafxFfDdbP"><?php echo esc_html__( 'Copy', 'koz-404-guard' ); ?></button></div>
						<div class="koz404-support-wallet koz404-support-wallet--paypal"><span class="koz404-support-wallet__network">PayPal</span><code>kozyriev@uafree.org</code><div class="koz404-support-wallet__actions"><a class="button button-small button-primary" href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=kozyriev%40uafree.org&amp;item_name=Support+KOZ+WordPress+plugin+development&amp;currency_code=USD" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Donate', 'koz-404-guard' ); ?></a><button type="button" class="button button-small koz404-support-wallet__copy" data-koz404-copy="kozyriev@uafree.org"><?php echo esc_html__( 'Copy', 'koz-404-guard' ); ?></button></div></div>
					</div>
					<p class="koz404-support-contact"><?php echo esc_html__( 'Developer contact:', 'koz-404-guard' ); ?> <a href="mailto:ramir@ua.fm">ramir@ua.fm</a> · <a href="https://github.com/ramirkoz" target="_blank" rel="noopener noreferrer">GitHub</a> · <a href="https://www.linkedin.com/in/tonykoz/" target="_blank" rel="noopener noreferrer">LinkedIn</a></p>
				</article>
				<article class="koz404-support-card koz404-support-card--foundation">
					<div class="koz404-support-card__title">
						<span class="dashicons dashicons-heart" aria-hidden="true"></span>
						<div>
							<h3><?php echo esc_html__( 'Support Ukraine through UA FREE', 'koz-404-guard' ); ?></h3>
							<p><?php echo esc_html__( 'The foundation was the first production environment for these plugins. Its charitable work is separate from plugin ownership and developer donations.', 'koz-404-guard' ); ?></p>
						</div>
					</div>
					<div class="koz404-support-card__actions"><a class="button button-primary" href="https://uafree.org/donate/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Donate to UA FREE', 'koz-404-guard' ); ?></a><a class="button" href="https://uafree.org/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'About the foundation', 'koz-404-guard' ); ?></a></div>
				</article>
			</div>
			<p class="screen-reader-text" id="koz404-support-copy-status" aria-live="polite"></p>
		</section>
		<?php
	}
}
