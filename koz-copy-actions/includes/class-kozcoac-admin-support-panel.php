<?php
namespace ramirkz\kozcopyactions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZCOAC_Admin_Support_Panel {
	private static bool $initialized = false;
	private static bool $rendered = false;

	public static function init(): void {
		if ( self::$initialized || ! is_admin() ) {
			return;
		}

		self::$initialized = true;
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

		// The settings page slug is koz-copy-actions (screen ID "koz-suite_page_koz-copy-actions");
		// kozcoac-suite is the fallback top-level page when no other KOZ plugin provides the menu.
		$id = strtolower( (string) $screen->id );
		return str_contains( $id, 'koz-copy-actions' ) || str_contains( $id, 'kozcoac-suite' );
	}

	/**
	 * The shared KOZ panel (for example from KOZ Suite Control Center) replaces this one,
	 * matching the other KOZ plugins, so the support block is never shown twice.
	 */
	private static function shared_panel_available(): bool {
		return class_exists( 'KOZ_Admin_Support_Panel_V1', false );
	}

	public static function render(): void {
		if ( self::$rendered || self::shared_panel_available() || ! current_user_can( 'manage_options' ) || ! self::is_plugin_screen() ) {
			return;
		}

		self::$rendered = true;
		wp_enqueue_script( 'clipboard' );
		?>
		<section id="kozcoac-support-panel" class="kozcoac-support-panel" aria-label="<?php echo esc_attr__( 'Plugin support', 'koz-copy-actions' ); ?>">
			<div class="kozcoac-support-grid">
				<article>
					<h2><?php esc_html_e( 'Support UA FREE', 'koz-copy-actions' ); ?></h2>
					<p><?php esc_html_e( 'Help the charitable foundation continue its work in Ukraine.', 'koz-copy-actions' ); ?></p>
					<p><a class="button button-primary" href="https://uafree.org/donate/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Donate to UA FREE', 'koz-copy-actions' ); ?></a></p>
				</article>
				<article>
					<h2><?php esc_html_e( 'Support development', 'koz-copy-actions' ); ?></h2>
					<p><?php esc_html_e( 'Developer support is separate from donations to the foundation.', 'koz-copy-actions' ); ?></p>
					<p><a href="https://www.linkedin.com/in/tonykoz/" target="_blank" rel="noopener noreferrer">LinkedIn: Tony Kozyriev</a></p>
					<p><strong>BTC:</strong> <code>bc1q4dn8e7sz2866g7qplqtshh98j54tvuau5ghuuk</code></p>
					<p><strong>ETH / USDC:</strong> <code>0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229</code></p>
					<p><strong>USDT TRC-20:</strong> <code>TYSgyk7K3XB4WPHprf5w8ZodFAfxFfDdbF</code></p>
				</article>
			</div>
		</section>
		<?php
	}
}
