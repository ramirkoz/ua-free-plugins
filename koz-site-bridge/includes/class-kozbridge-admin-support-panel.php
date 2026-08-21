<?php
namespace ramirkz\kozbridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZBRIDGE_Admin_Support_Panel {
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
		return str_contains( $id, 'koz-site-bridge' ) || str_contains( $id, 'kozbridge-suite' );
	}

	private static function shared_panel_available(): bool {
		return class_exists( 'KOZ_Admin_Support_Panel_V1', false );
	}

	public static function enqueue_assets(): void {
		if ( self::shared_panel_available() || ! current_user_can( 'manage_options' ) || ! self::is_plugin_screen() ) {
			return;
		}

		wp_enqueue_style(
			'kozbridge-support-panel',
			KOZBRIDGE_URL . 'assets/kozbridge-admin-support-panel.css',
			array(),
			KOZBRIDGE_VERSION
		);
		wp_enqueue_script(
			'kozbridge-support-panel',
			KOZBRIDGE_URL . 'assets/kozbridge-admin-support-panel.js',
			array(),
			KOZBRIDGE_VERSION,
			true
		);
		wp_localize_script(
			'kozbridge-support-panel',
			'KOZBRIDGEI18n',
			array(
				'copy'   => __( 'Copy', 'koz-site-bridge' ),
				'copied' => __( 'Copied', 'koz-site-bridge' ),
				'status' => __( 'Address copied.', 'koz-site-bridge' ),
			)
		);
	}

	public static function render(): void {
		if ( self::$rendered || self::shared_panel_available() || ! current_user_can( 'manage_options' ) || ! self::is_plugin_screen() ) {
			return;
		}

		self::$rendered = true;
		?>
		<section id="kozbridge-support-panel" class="kozbridge-support-panel" aria-label="<?php echo esc_attr__( 'KOZ project support', 'koz-site-bridge' ); ?>" hidden>
			<div class="kozbridge-support-panel__header">
				<div>
					<span class="kozbridge-support-panel__eyebrow"><?php echo esc_html__( 'KOZ WordPress Suite', 'koz-site-bridge' ); ?></span>
					<h2><?php echo esc_html__( 'Support independent development and Ukraine', 'koz-site-bridge' ); ?></h2>
					<p><?php echo esc_html__( 'Developed and maintained by Tony Kozyriev. The suite began as production tooling for the UA FREE charitable foundation and remains in active use on its website.', 'koz-site-bridge' ); ?></p>
				</div>
				<span class="kozbridge-support-panel__badge"><?php echo esc_html__( 'Free, open source and privacy-conscious', 'koz-site-bridge' ); ?></span>
			</div>
			<div class="kozbridge-support-panel__grid">
				<article class="kozbridge-support-card kozbridge-support-card--developer">
					<div class="kozbridge-support-card__title">
						<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
						<div>
							<h3><?php echo esc_html__( 'Support the developer', 'koz-site-bridge' ); ?></h3>
							<p><?php echo esc_html__( 'PayPal and crypto donations support maintenance, testing and new free releases.', 'koz-site-bridge' ); ?></p>
						</div>
					</div>
					<div class="kozbridge-support-wallets">
						<div class="kozbridge-support-wallet"><span class="kozbridge-support-wallet__network">BTC</span><code>bc1q4dn8e7sz2866g7qp1qtshh98j54tvuau5ghuuk</code><button type="button" class="button button-small kozbridge-support-wallet__copy" data-kozbridge-copy="bc1q4dn8e7sz2866g7qp1qtshh98j54tvuau5ghuuk"><?php echo esc_html__( 'Copy', 'koz-site-bridge' ); ?></button></div>
						<div class="kozbridge-support-wallet"><span class="kozbridge-support-wallet__network">ETH / USDC</span><code>0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229</code><button type="button" class="button button-small kozbridge-support-wallet__copy" data-kozbridge-copy="0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229"><?php echo esc_html__( 'Copy', 'koz-site-bridge' ); ?></button></div>
						<div class="kozbridge-support-wallet"><span class="kozbridge-support-wallet__network">USDT TRC-20</span><code>TYsGyK7K3XB4NPHprf5w8ZodFafxFfDdbP</code><button type="button" class="button button-small kozbridge-support-wallet__copy" data-kozbridge-copy="TYsGyK7K3XB4NPHprf5w8ZodFafxFfDdbP"><?php echo esc_html__( 'Copy', 'koz-site-bridge' ); ?></button></div>
						<div class="kozbridge-support-wallet kozbridge-support-wallet--paypal"><span class="kozbridge-support-wallet__network">PayPal</span><code>kozyriev@uafree.org</code><div class="kozbridge-support-wallet__actions"><a class="button button-small button-primary" href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=kozyriev%40uafree.org&amp;item_name=Support+KOZ+WordPress+plugin+development&amp;currency_code=USD" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Donate', 'koz-site-bridge' ); ?></a><button type="button" class="button button-small kozbridge-support-wallet__copy" data-kozbridge-copy="kozyriev@uafree.org"><?php echo esc_html__( 'Copy', 'koz-site-bridge' ); ?></button></div></div>
					</div>
					<p class="kozbridge-support-contact"><?php echo esc_html__( 'Developer contact:', 'koz-site-bridge' ); ?> <a href="mailto:ramir@ua.fm">ramir@ua.fm</a> · <a href="https://github.com/ramirkoz" target="_blank" rel="noopener noreferrer">GitHub</a> · <a href="https://www.linkedin.com/in/tonykoz/" target="_blank" rel="noopener noreferrer">LinkedIn</a></p>
				</article>
				<article class="kozbridge-support-card kozbridge-support-card--foundation">
					<div class="kozbridge-support-card__title">
						<span class="dashicons dashicons-heart" aria-hidden="true"></span>
						<div>
							<h3><?php echo esc_html__( 'Support Ukraine through UA FREE', 'koz-site-bridge' ); ?></h3>
							<p><?php echo esc_html__( 'The foundation was the first production environment for these plugins. Its charitable work is separate from plugin ownership and developer donations.', 'koz-site-bridge' ); ?></p>
						</div>
					</div>
					<div class="kozbridge-support-card__actions"><a class="button button-primary" href="https://uafree.org/donate/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Donate to UA FREE', 'koz-site-bridge' ); ?></a><a class="button" href="https://uafree.org/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'About the foundation', 'koz-site-bridge' ); ?></a></div>
				</article>
			</div>
			<p class="screen-reader-text" id="kozbridge-support-copy-status" aria-live="polite"></p>
		</section>
		<?php
	}
}
