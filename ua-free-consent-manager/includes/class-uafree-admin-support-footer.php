<?php
/**
 * Shared UA FREE admin support panel.
 *
 * The component is copied into every UA FREE plugin so each package remains
 * self-contained. Compatibility logic suppresses the legacy footer when an
 * older Suite plugin is still active during a staggered upgrade.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'UAFree_Admin_Support_Footer', false ) ) {
	remove_action( 'admin_footer', array( 'UAFree_Admin_Support_Footer', 'render' ), 50 );
}

if ( ! class_exists( 'UAFree_Admin_Support_Panel_V2', false ) ) {
	final class UAFree_Admin_Support_Panel_V2 {
		private static bool $initialized = false;
		private static bool $rendered    = false;

		public static function init(): void {
			if ( self::$initialized || ! is_admin() ) {
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
			<section id="uafree-admin-support-panel-v2" class="uafree-support-panel" aria-label="UA FREE support" hidden>
				<div class="uafree-support-panel__header">
					<div>
						<span class="uafree-support-panel__eyebrow">UA FREE Plugin Suite</span>
						<h2>Support the work behind these plugins</h2>
						<p>Built from real work for a Ukrainian charitable foundation and shared as free WordPress tools.</p>
					</div>
					<span class="uafree-support-panel__badge">Free &amp; privacy-conscious</span>
				</div>

				<div class="uafree-support-panel__grid">
					<article class="uafree-support-card uafree-support-card--foundation">
						<div class="uafree-support-card__title">
							<span class="dashicons dashicons-heart" aria-hidden="true"></span>
							<div>
								<h3>Support UA FREE</h3>
								<p>Help the foundation continue its work in Ukraine.</p>
							</div>
						</div>
						<div class="uafree-support-card__actions">
							<a class="button button-primary" href="https://uafree.org/donate/" target="_blank" rel="noopener noreferrer">Donate to UA FREE</a>
							<a class="button" href="https://uafree.org/" target="_blank" rel="noopener noreferrer">About the foundation</a>
						</div>
					</article>

					<article class="uafree-support-card uafree-support-card--developer">
						<div class="uafree-support-card__title">
							<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
							<div>
								<h3>Support development</h3>
								<p>Crypto and PayPal donations help maintain and improve the free plugin suite.</p>
							</div>
						</div>

						<div class="uafree-support-wallets">
							<div class="uafree-support-wallet">
								<span class="uafree-support-wallet__network">BTC</span>
								<code>bc1q4dn8e7sz2866g7qp1qtshh98j54tvuau5ghuuk</code>
								<button type="button" class="button button-small uafree-support-wallet__copy" data-uafree-copy="bc1q4dn8e7sz2866g7qp1qtshh98j54tvuau5ghuuk" aria-label="Copy BTC address">Copy</button>
							</div>
							<div class="uafree-support-wallet">
								<span class="uafree-support-wallet__network">ETH / USDC</span>
								<code>0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229</code>
								<button type="button" class="button button-small uafree-support-wallet__copy" data-uafree-copy="0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229" aria-label="Copy ETH and USDC address">Copy</button>
							</div>
							<div class="uafree-support-wallet">
								<span class="uafree-support-wallet__network">USDT TRC-20</span>
								<code>TYsGyK7K3XB4NPHprf5w8ZodFafxFfDdbP</code>
								<button type="button" class="button button-small uafree-support-wallet__copy" data-uafree-copy="TYsGyK7K3XB4NPHprf5w8ZodFafxFfDdbP" aria-label="Copy USDT address">Copy</button>
							</div>
							<div class="uafree-support-wallet uafree-support-wallet--paypal">
								<span class="uafree-support-wallet__network">PayPal</span>
								<code>kozyriev@uafree.org</code>
								<div class="uafree-support-wallet__actions">
									<a class="button button-small button-primary" href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=kozyriev%40uafree.org&amp;item_name=Support+UA+FREE+plugin+development&amp;currency_code=USD" target="_blank" rel="noopener noreferrer">Donate</a>
									<button type="button" class="button button-small uafree-support-wallet__copy" data-uafree-copy="kozyriev@uafree.org" aria-label="Copy PayPal email">Copy</button>
								</div>
							</div>
						</div>
						<p class="screen-reader-text" id="uafree-support-copy-status" aria-live="polite"></p>
					</article>
				</div>
			</section>

			<style>
			.uafree-support-panel{width:calc(100% - 20px);max-width:1180px;margin:28px 20px 52px 0;padding:20px;border:1px solid #dcdcde;border-radius:12px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.06);box-sizing:border-box}
			.uafree-support-panel[hidden]{display:none!important}
			.uafree-support-panel__header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:18px;padding-bottom:16px;border-bottom:1px solid #e2e4e7}
			.uafree-support-panel__eyebrow{display:block;margin-bottom:4px;color:#3858e9;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
			.uafree-support-panel__header h2{margin:0 0 5px;font-size:20px;line-height:1.3}
			.uafree-support-panel__header p{max-width:720px;margin:0;color:#50575e;font-size:13px}
			.uafree-support-panel__badge{flex:0 0 auto;padding:6px 10px;border:1px solid #c3c4c7;border-radius:999px;background:#f6f7f7;color:#3c434a;font-size:11px;font-weight:600;white-space:nowrap}
			.uafree-support-panel__grid{display:grid;grid-template-columns:minmax(0,.85fr) minmax(0,1.35fr);gap:16px}
			.uafree-support-card{min-width:0;padding:16px;border:1px solid #dcdcde;border-radius:10px;background:#f9f9f9;box-sizing:border-box}
			.uafree-support-card--foundation{border-left:4px solid #2271b1}
			.uafree-support-card--developer{border-left:4px solid #dba617}
			.uafree-support-card__title{display:flex;align-items:flex-start;gap:10px;margin-bottom:14px}
			.uafree-support-card__title .dashicons{flex:0 0 24px;width:24px;height:24px;margin-top:1px;font-size:24px;color:#1d2327}
			.uafree-support-card__title h3{margin:0 0 3px;font-size:15px;line-height:1.35}
			.uafree-support-card__title p{margin:0;color:#646970;font-size:12px}
			.uafree-support-card__actions{display:flex;flex-wrap:wrap;gap:8px}
			.uafree-support-wallets{display:grid;gap:8px}
			.uafree-support-wallet{display:grid;grid-template-columns:98px minmax(0,1fr) auto;align-items:center;gap:10px;padding:9px 10px;border:1px solid #dcdcde;border-radius:8px;background:#fff}
			.uafree-support-wallet__network{font-size:11px;font-weight:700;color:#3c434a;white-space:nowrap}
			.uafree-support-wallet code{display:block;min-width:0;padding:0;border:0;background:transparent;color:#1d2327;font-size:11px;line-height:1.5;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;user-select:all}
			.uafree-support-wallet__actions{display:flex;align-items:center;justify-content:flex-end;gap:6px}.uafree-support-wallet__copy{min-width:56px;text-align:center}
			.uafree-support-wallet__copy.is-copied{border-color:#00a32a;color:#008a20}
			@media(max-width:960px){.uafree-support-panel__grid{grid-template-columns:1fr}.uafree-support-panel__header{flex-direction:column}.uafree-support-panel__badge{white-space:normal}.uafree-support-wallet{grid-template-columns:90px minmax(0,1fr) auto}}
			@media(max-width:600px){.uafree-support-panel{width:calc(100% - 10px);margin-right:10px;padding:14px}.uafree-support-wallet{grid-template-columns:1fr auto}.uafree-support-wallet code{grid-column:1/-1;grid-row:2;white-space:normal;overflow-wrap:anywhere}.uafree-support-wallet>.uafree-support-wallet__copy{grid-column:2;grid-row:1}.uafree-support-wallet__actions{grid-column:2;grid-row:1}.uafree-support-card__actions .button{width:100%;text-align:center}}
			</style>

			<script>
			(function(){
				var panel=document.getElementById('uafree-admin-support-panel-v2');
				var container=document.getElementById('wpbody-content');
				if(!panel){return;}
				if(container){container.appendChild(panel);}
				panel.hidden=false;
				panel.addEventListener('click',function(event){
					var button=event.target.closest('[data-uafree-copy]');
					if(!button){return;}
					var value=button.getAttribute('data-uafree-copy')||'';
					var status=document.getElementById('uafree-support-copy-status');
					var done=function(){
						button.textContent='Copied';
						button.classList.add('is-copied');
						if(status){status.textContent='Wallet address copied.';}
						window.setTimeout(function(){button.textContent='Copy';button.classList.remove('is-copied');},1600);
					};
					if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(value).then(done);return;}
					var input=document.createElement('textarea');
					input.value=value;
					input.setAttribute('readonly','readonly');
					input.style.position='fixed';
					input.style.opacity='0';
					document.body.appendChild(input);
					input.select();
					document.execCommand('copy');
					document.body.removeChild(input);
					done();
				});
			}());
			</script>
			<?php
		}
	}
}

if ( ! class_exists( 'UAFree_Admin_Support_Footer', false ) ) {
	final class UAFree_Admin_Support_Footer {
		public static function init(): void {
			UAFree_Admin_Support_Panel_V2::init();
		}

		public static function render(): void {
			// Compatibility no-op. Rendering is handled by the versioned panel.
		}
	}
}

UAFree_Admin_Support_Panel_V2::init();
