<?php
/**
 * Shared, hard-coded UA FREE admin support footer.
 *
 * This file is intentionally copied into every UA FREE plugin so that each
 * plugin remains self-contained. The class guard and init guard ensure the
 * block is printed only once when several Suite plugins are active.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'UAFree_Admin_Support_Footer', false ) ) {
	final class UAFree_Admin_Support_Footer {
		private static bool $initialized = false;
		private static bool $rendered = false;

		public static function init(): void {
			if ( self::$initialized ) {
				return;
			}
			self::$initialized = true;
			add_action( 'admin_footer', array( __CLASS__, 'render' ), 50 );
		}

		private static function is_uafree_screen(): bool {
			$page = isset( $_GET['page'] )
				? strtolower( sanitize_text_field( wp_unslash( (string) $_GET['page'] ) ) )
				: '';
			$screen_id = '';
			if ( function_exists( 'get_current_screen' ) ) {
				$screen = get_current_screen();
				$screen_id = is_object( $screen ) ? strtolower( (string) $screen->id ) : '';
			}

			foreach ( array( $page, $screen_id ) as $value ) {
				if ( str_contains( $value, 'uafree' ) || str_contains( $value, 'ua-free' ) ) {
					return true;
				}
			}
			return false;
		}

		public static function render(): void {
			if ( self::$rendered || ! current_user_can( 'manage_options' ) || ! self::is_uafree_screen() ) {
				return;
			}
			self::$rendered = true;
			?>
			<section class="uafree-admin-support-footer" aria-label="Support UA FREE">
				<div class="uafree-admin-support-main">
					<strong>Support UA FREE</strong>
					<span>These plugins grew out of real work for a Ukrainian charitable foundation.</span>
					<a href="https://uafree.org/donate/" target="_blank" rel="noopener noreferrer">Donate to the foundation</a>
					<a href="https://uafree.org/" target="_blank" rel="noopener noreferrer">Tell others about UA FREE</a>
				</div>
				<div class="uafree-admin-support-wallets">
					<strong>Support the developer</strong>
					<span><b>BTC</b> <code>bc1q4dn8e7sz2866g7qp1qtshh98j54tvuau5ghuuk</code></span>
					<span><b>ETH / USDC ERC-20</b> <code>0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229</code></span>
					<span><b>USDT TRC-20</b> <code>TYsGyK7K3XB4NPHprf5w8ZodFafxFfDdbP</code></span>
				</div>
			</section>
			<style>
			.uafree-admin-support-footer{margin:28px 20px 12px 0;padding:18px 20px;border:1px solid #c3c4c7;border-left:4px solid #2271b1;background:#fff;display:grid;grid-template-columns:minmax(260px,1fr) minmax(360px,1.25fr);gap:18px;box-sizing:border-box}
			.uafree-admin-support-main,.uafree-admin-support-wallets{display:flex;flex-wrap:wrap;align-items:center;gap:8px 14px}
			.uafree-admin-support-main strong,.uafree-admin-support-wallets strong{font-size:15px}
			.uafree-admin-support-main a{font-weight:600}
			.uafree-admin-support-wallets span{display:block;width:100%}
			.uafree-admin-support-wallets code{user-select:all;overflow-wrap:anywhere}
			@media(max-width:960px){.uafree-admin-support-footer{grid-template-columns:1fr}}
			</style>
			<?php
		}
	}
}
