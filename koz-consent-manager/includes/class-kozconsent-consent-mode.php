<?php
/**
 * Default consent signals printed first in <head>: Google Consent Mode v2,
 * Microsoft UET consent and a dataLayer event for Google Tag Manager.
 *
 * The defaults are read from the consent cookie in the browser, so cached pages
 * stay correct for every visitor.
 *
 * @package KOZ_Consent_Manager
 */

namespace ramirkz\kozconsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZCONSENT_Consent_Mode {
	/** EEA, the United Kingdom and Switzerland (the regions Google requires consent for). */
	public const EEA_REGIONS = array( 'AT', 'BE', 'BG', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GR', 'HR', 'HU', 'IE', 'IS', 'IT', 'LI', 'LT', 'LU', 'LV', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK' );

	public static function init(): void {
		add_action( 'wp_head', array( __CLASS__, 'render' ), -1000 );
	}

	/** Whether Site Kit prints its own Consent Mode defaults. */
	public static function site_kit_consent_mode(): bool {
		$settings = get_option( 'googlesitekit_consent_mode', array() );
		return is_array( $settings ) && ! empty( $settings['enabled'] );
	}

	public static function config(): array {
		$s = KOZCONSENT_Settings::stored();
		return array(
			'cookieNames'   => array( KOZCONSENT_Manager::COOKIE_NAME, KOZCONSENT_Manager::legacy_cookie_name() ),
			'policyVersion' => (string) $s['policy_version'],
			'google'        => ! empty( $s['google_consent_mode'] ),
			'region'        => 'eea' === $s['gcm_region'] ? self::EEA_REGIONS : array(),
			'wait'          => (int) $s['gcm_wait'],
			'urlPassthrough' => ! empty( $s['gcm_url_passthrough'] ),
			'adsRedaction'  => ! empty( $s['gcm_ads_redaction'] ),
			'microsoft'     => ! empty( $s['microsoft_consent'] ),
			'events'        => ! empty( $s['datalayer_events'] ),
			'gpc'           => ! empty( $s['honor_gpc'] ),
		);
	}

	public static function render(): void {
		$s = KOZCONSENT_Settings::stored();
		if ( empty( $s['enabled'] ) || is_admin() ) {
			return;
		}
		$config = self::config();
		if ( ! $config['google'] && ! $config['microsoft'] && ! $config['events'] ) {
			return;
		}
		$js = 'window.KOZCONSENTHead=' . wp_json_encode( $config ) . ';' . self::script();
		wp_print_inline_script_tag( $js, array( 'id' => 'kozconsent-google-consent-mode' ) );
	}

	private static function script(): string {
		return <<<'JS'
(function(){'use strict';
var c=window.KOZCONSENTHead||{},w=window,s={analytics:false,advertising:false,external_media:false,choice:false};
var parts=document.cookie?document.cookie.split(';'):[];
outer:for(var n=0;n<(c.cookieNames||[]).length;n++){var p=String(c.cookieNames[n])+'=';for(var i=0;i<parts.length;i++){var it=parts[i].trim();if(it.indexOf(p)!==0)continue;try{var v=JSON.parse(decodeURIComponent(it.slice(p.length)));if(v&&v.schema_version===1&&String(v.policy_version||'')===String(c.policyVersion||'')&&typeof v.updated_at==='string'){s={analytics:v.analytics===true,advertising:v.advertising===true,external_media:v.external_media===true,choice:true};}}catch(e){}break outer;}}
if(c.gpc&&navigator.globalPrivacyControl===true&&!s.choice){s.advertising=false;}
w.KOZCONSENTInitial=s;
w.dataLayer=w.dataLayer||[];
if(c.google){
w.gtag=w.gtag||function(){w.dataLayer.push(arguments);};
var g=function(a,ad){return{ad_storage:ad,ad_user_data:ad,ad_personalization:ad,analytics_storage:a,functionality_storage:'granted',personalization_storage:a,security_storage:'granted'};};
var A=s.analytics?'granted':'denied',D=s.advertising?'granted':'denied';
if(c.region&&c.region.length){var r=g(A,D);r.region=c.region;if(c.wait>0)r.wait_for_update=c.wait;w.gtag('consent','default',r);
var o=g(s.choice?A:'granted',s.choice?D:'granted');w.gtag('consent','default',o);}
else{var d=g(A,D);if(c.wait>0)d.wait_for_update=c.wait;w.gtag('consent','default',d);}
if(c.adsRedaction)w.gtag('set','ads_data_redaction',!s.advertising);
if(c.urlPassthrough)w.gtag('set','url_passthrough',true);
}
if(c.microsoft){w.uetq=w.uetq||[];w.uetq.push('consent','default',{ad_storage:s.advertising?'granted':'denied'});
w.clarity=w.clarity||function(){(w.clarity.q=w.clarity.q||[]).push(arguments);};
if(s.choice)w.clarity('consentv2',{ad_Storage:s.advertising?'granted':'denied',analytics_Storage:s.analytics?'granted':'denied'});}
if(c.events)w.dataLayer.push({event:'kozconsent_default',kozconsent:{analytics:s.analytics,advertising:s.advertising,external_media:s.external_media,choice:s.choice}});
}());
JS;
	}
}
