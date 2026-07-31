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
			if ( ! function_exists( 'get_current_screen' ) ) {
				return false;
			}

			$screen = get_current_screen();
			if ( ! is_object( $screen ) ) {
				return false;
			}

			$screen_id = strtolower( (string) $screen->id );
			return str_contains( $screen_id, 'uafree' ) || str_contains( $screen_id, 'ua-free' );
		}

		public static function render(): void {
			if ( self::$rendered || ! current_user_can( 'manage_options' ) || ! self::is_uafree_screen() ) {
				return;
			}
			self::$rendered = true;
			?>
			<section class="uafree-admin-support-footer" aria-label="Support UA FREE">
				<div class="uafree-admin-support-card uafree-admin-support-foundation">
					<div class="uafree-admin-support-heading">
						<span class="dashicons dashicons-heart" aria-hidden="true"></span>
						<strong>Support UA FREE</strong>
					</div>
					<p>These plugins grew out of real work for a Ukrainian charitable foundation.</p>
					<div class="uafree-admin-support-actions">
						<a class="button button-primary" href="https://uafree.org/donate/" target="_blank" rel="noopener noreferrer">Donate to the foundation</a>
						<a class="button" href="https://uafree.org/" target="_blank" rel="noopener noreferrer">Tell others about UA FREE</a>
					</div>
				</div>

				<div class="uafree-admin-support-card uafree-admin-support-developer">
					<div class="uafree-admin-support-heading">
						<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
						<strong>Support the developer</strong>
					</div>
					<div class="uafree-admin-support-wallets">
						<div><b>BTC</b><code>bc1q4dn8e7sz2866g7qp1qtshh98j54tvuau5ghuuk</code></div>
						<div><b>ETH / USDC ERC-20</b><code>0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229</code></div>
						<div><b>USDT TRC-20</b><code>TYsGyK7K3XB4NPHprf5w8ZodFafxFfDdbP</code></div>
					</div>
				</div>
			</section>
			<style>
			.uafree-admin-support-footer{clear:both;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.15fr);gap:16px;max-width:1180px;margin:28px 20px 16px 0;box-sizing:border-box}
			.uafree-admin-support-card{min-width:0;padding:18px;border:1px solid #dcdcde;border-radius:8px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04);box-sizing:border-box}
			.uafree-admin-support-foundation{border-top:4px solid #2271b1}
			.uafree-admin-support-developer{border-top:4px solid #00a32a}
			.uafree-admin-support-heading{display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:15px}
			.uafree-admin-support-heading .dashicons{width:20px;height:20px;font-size:20px}
			.uafree-admin-support-card p{margin:0 0 14px;color:#50575e}
			.uafree-admin-support-actions{display:flex;flex-wrap:wrap;gap:8px}
			.uafree-admin-support-wallets{display:grid;gap:8px}
			.uafree-admin-support-wallets>div{display:grid;grid-template-columns:minmax(125px,auto) minmax(0,1fr);align-items:start;gap:10px;padding:8px 10px;border:1px solid #e2e4e7;border-radius:4px;background:#f6f7f7}
			.uafree-admin-support-wallets b{font-size:12px;line-height:1.8}
			.uafree-admin-support-wallets code{display:block;min-width:0;padding:0;background:transparent;white-space:normal;overflow-wrap:anywhere;user-select:all}
			@media(max-width:960px){.uafree-admin-support-footer{grid-template-columns:1fr}.uafree-admin-support-wallets>div{grid-template-columns:1fr}}
			</style>
			<?php
		}
	}
}
