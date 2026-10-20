<?php
/**
 * Google Ads Editor CSV files, their validation and the text guides in the ZIP.
 *
 * Column names follow the Google Ads Editor CSV headers. Campaign negative
 * keywords use "Negative Phrase" with an empty Ad Group column. Files are UTF-8
 * with BOM and CRLF line endings so that Editor keeps non-Latin text.
 *
 * @package KOZ_Google_Ads_Campaign_Builder
 */

namespace ramirkz\kozgads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZGADS_Export {
	private const CAMPAIGN_HEADERS = array( 'Campaign', 'Campaign Type', 'Networks', 'Budget', 'Budget type', 'Bid Strategy Type', 'Languages', 'Campaign Status', 'Final URL suffix' );
	private const LOCATION_HEADERS = array( 'Campaign', 'Location', 'Location ID' );
	private const GROUP_HEADERS    = array( 'Campaign', 'Ad Group', 'Max CPC', 'Ad Group Status' );
	private const KEYWORD_HEADERS  = array( 'Campaign', 'Ad Group', 'Keyword', 'Criterion Type', 'Status' );
	private const SITELINK_HEADERS = array( 'Campaign', 'Sitelink text', 'Final URL', 'Status' );
	private const CALLOUT_HEADERS  = array( 'Campaign', 'Callout text', 'Status' );

	private static function ad_headers(): array {
		$headers = array( 'Campaign', 'Ad Group', 'Ad type', 'Final URL', 'Path 1', 'Path 2', 'Status' );
		for ( $i = 1; $i <= KOZGADS_Copy::MAX_HEADLINES; $i++ ) {
			$headers[] = 'Headline ' . $i;
		}
		for ( $i = 1; $i <= KOZGADS_Copy::MAX_DESCRIPTIONS; $i++ ) {
			$headers[] = 'Description ' . $i;
		}
		return $headers;
	}

	/** @return array<string,array{headers:string[],rows:array<int,array<string,mixed>>}> */
	public static function editor_files( array $plan, array $settings ): array {
		$campaigns = $locations = $groups = $keywords = $ads = $sitelinks = $callouts = array();
		$networks  = ! empty( $settings['include_search_partners'] ) ? 'Google search;Search Partners' : 'Google search';
		$bid       = (string) $plan['bid_strategy'];
		$max_cpc   = ( 'Manual CPC' === $bid ) ? ( 'ad_grants' === $plan['account_mode'] ? '2.00' : '1.00' ) : '';
		foreach ( $plan['campaigns'] as $campaign ) {
			$name        = (string) $campaign['name'];
			$campaigns[] = array(
				'Campaign'          => $name,
				'Campaign Type'     => 'Search',
				'Networks'          => $networks,
				'Budget'            => number_format( (float) $campaign['daily_budget'], 2, '.', '' ),
				'Budget type'       => 'Daily',
				'Bid Strategy Type' => $bid,
				'Languages'         => (string) $campaign['ads_language_code'],
				'Campaign Status'   => 'Paused',
				'Final URL suffix'  => (string) $settings['final_url_suffix'],
			);
			foreach ( $campaign['locations'] as $location ) {
				$locations[] = array( 'Campaign' => $name, 'Location' => $location['name'], 'Location ID' => $location['id'] );
			}
			foreach ( $campaign['ad_groups'] as $group ) {
				$groups[] = array( 'Campaign' => $name, 'Ad Group' => $group['name'], 'Max CPC' => $max_cpc, 'Ad Group Status' => 'Enabled' );
				foreach ( $group['keywords'] as $keyword ) {
					$keywords[] = array( 'Campaign' => $name, 'Ad Group' => $group['name'], 'Keyword' => $keyword, 'Criterion Type' => 'Phrase', 'Status' => 'Enabled' );
				}
				$ad = array( 'Campaign' => $name, 'Ad Group' => $group['name'], 'Ad type' => 'Responsive search ad', 'Final URL' => $group['final_url'], 'Path 1' => $group['path_1'], 'Path 2' => $group['path_2'], 'Status' => 'Enabled' );
				foreach ( array_values( $group['headlines'] ) as $i => $value ) {
					$ad[ 'Headline ' . ( $i + 1 ) ] = $value;
				}
				foreach ( array_values( $group['descriptions'] ) as $i => $value ) {
					$ad[ 'Description ' . ( $i + 1 ) ] = $value;
				}
				$ads[] = $ad;
			}
			foreach ( $campaign['negative_keywords'] as $keyword ) {
				$keywords[] = array( 'Campaign' => $name, 'Ad Group' => '', 'Keyword' => $keyword, 'Criterion Type' => 'Negative Phrase', 'Status' => 'Enabled' );
			}
			foreach ( $campaign['sitelinks'] as $link ) {
				$sitelinks[] = array( 'Campaign' => $name, 'Sitelink text' => $link['text'], 'Final URL' => $link['final_url'], 'Status' => 'Enabled' );
			}
			foreach ( $campaign['callouts'] as $callout ) {
				$callouts[] = array( 'Campaign' => $name, 'Callout text' => $callout, 'Status' => 'Enabled' );
			}
		}
		$all = array_values( array_unique( array_merge( self::CAMPAIGN_HEADERS, self::LOCATION_HEADERS, self::GROUP_HEADERS, self::KEYWORD_HEADERS, self::ad_headers(), self::SITELINK_HEADERS, self::CALLOUT_HEADERS ) ) );
		return array(
			'00-IMPORT-ALL.csv'             => array( 'headers' => $all, 'rows' => array_merge( $campaigns, $locations, $groups, $keywords, $ads, $sitelinks, $callouts ) ),
			'01-campaigns.csv'              => array( 'headers' => self::CAMPAIGN_HEADERS, 'rows' => $campaigns ),
			'02-locations.csv'              => array( 'headers' => self::LOCATION_HEADERS, 'rows' => $locations ),
			'03-ad-groups.csv'              => array( 'headers' => self::GROUP_HEADERS, 'rows' => $groups ),
			'04-keywords-and-negatives.csv' => array( 'headers' => self::KEYWORD_HEADERS, 'rows' => $keywords ),
			'05-responsive-search-ads.csv'  => array( 'headers' => self::ad_headers(), 'rows' => $ads ),
			'06-sitelinks.csv'              => array( 'headers' => self::SITELINK_HEADERS, 'rows' => $sitelinks ),
			'07-campaign-callouts.csv'      => array( 'headers' => self::CALLOUT_HEADERS, 'rows' => $callouts ),
		);
	}

	/**
	 * Checks the generated rows against Editor limits and Ad Grants rules.
	 *
	 * @return array{issues:string[],warnings:string[]}
	 */
	public static function validate( array $plan ): array {
		$issues   = array();
		$warnings = array();
		$grants   = 'ad_grants' === $plan['account_mode'];
		$seen     = array();
		foreach ( $plan['campaigns'] as $campaign ) {
			$name = (string) $campaign['name'];
			if ( isset( $seen[ $name ] ) ) {
				$issues[] = $name . ': duplicate campaign name.';
			}
			$seen[ $name ] = true;
			if ( empty( $campaign['ads_language_code'] ) ) {
				$issues[] = $name . ': no language code.';
			}
			if ( $grants && count( $campaign['ad_groups'] ) < 2 ) {
				$issues[] = $name . ': Ad Grants campaigns need at least 2 ad groups.';
			}
			if ( $grants && count( $campaign['sitelinks'] ) < 2 ) {
				$issues[] = $name . ': Ad Grants campaigns need at least 2 sitelinks.';
			}
			foreach ( $campaign['ad_groups'] as $group ) {
				$label = $name . ' / ' . $group['name'];
				if ( count( $group['headlines'] ) < 3 || count( $group['descriptions'] ) < 2 ) {
					$issues[] = $label . ': a responsive search ad needs at least 3 headlines and 2 descriptions.';
				}
				if ( empty( $group['keywords'] ) ) {
					$issues[] = $label . ': no keywords.';
				}
				foreach ( $group['headlines'] as $value ) {
					if ( KOZGADS_Copy::length( (string) $value ) > 30 ) {
						$issues[] = $label . ': headline longer than 30 characters.';
					}
				}
				foreach ( $group['descriptions'] as $value ) {
					if ( KOZGADS_Copy::length( (string) $value ) > 90 ) {
						$issues[] = $label . ': description longer than 90 characters.';
					}
				}
				foreach ( $group['keywords'] as $keyword ) {
					$words = preg_split( '/\s+/u', trim( (string) $keyword ), -1, PREG_SPLIT_NO_EMPTY ) ?: array();
					if ( $grants && count( $words ) < 2 ) {
						$issues[] = $label . ': single-word keyword "' . $keyword . '" is not allowed in Ad Grants.';
					}
				}
				if ( 0 !== strpos( (string) $group['final_url'], 'https://' ) ) {
					$issues[] = $label . ': Final URL is not HTTPS.';
				}
				if ( count( $group['headlines'] ) < 8 ) {
					$warnings[] = $label . ': fewer than 8 headlines, ad strength is likely to be Poor.';
				}
				if ( ! empty( $group['low_volume_removed'] ) ) {
					$warnings[] = $label . ': ' . count( $group['low_volume_removed'] ) . ' keyword(s) left out because the imported report marks them as low search volume.';
				}
			}
		}
		if ( $grants && 'Maximize conversions' === $plan['bid_strategy'] && empty( $plan['conversion_confirmed'] ) ) {
			$issues[] = 'Maximize conversions is selected, but no real conversion is confirmed. Without conversions the campaigns will barely serve.';
		}
		return array( 'issues' => array_values( array_unique( $issues ) ), 'warnings' => array_values( array_unique( $warnings ) ) );
	}

	public static function csv_content( array $headers, array $rows ): string {
		$lines = array( self::csv_row( $headers ) );
		foreach ( $rows as $row ) {
			$values = array();
			foreach ( $headers as $header ) {
				$values[] = $row[ $header ] ?? '';
			}
			$lines[] = self::csv_row( $values );
		}
		return "\xEF\xBB\xBF" . implode( "\r\n", $lines ) . "\r\n";
	}

	private static function csv_row( array $values ): string {
		return implode(
			',',
			array_map(
				static function ( $value ): string {
					$value = (string) $value;
					// Neutralise spreadsheet formulas.
					if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) {
						$value = "'" . $value;
					}
					return preg_match( '/[",\r\n\t]/', $value ) ? '"' . str_replace( '"', '""', $value ) . '"' : $value;
				},
				$values
			)
		);
	}

	/** Step-by-step conversion setup for the donation event. */
	public static function conversion_guide( array $donate, array $settings ): string {
		$event = '' !== $donate['event'] ? $donate['event'] : (string) $settings['primary_conversion_name'];
		$lines = array(
			'CONVERSION SETUP (do this before launching the campaigns)',
			'',
			'Without a real conversion, "Maximize conversions" has no signal and the campaigns barely serve.',
			'',
			'1. Google Ads > Goals > Conversions > New conversion action > Website > set up manually.',
			'   Category: Donate (or Purchase). Value: use the transaction value or a fixed value. Count: One.',
			'   Copy the Conversion ID (AW-...) and the Conversion label.',
			'2. Google Tag Manager > Triggers > New > Custom Event.',
			'   Event name: ' . $event,
			'3. Tags > New > Google Ads Conversion Tracking with the ID and label from step 1, fired by the trigger from step 2.',
			'   Keep consent settings: the tag must respect Consent Mode (ad_storage / ad_user_data).',
			'4. Publish the GTM container, make a test donation and check Tag Assistant.',
			'5. Google Ads > Goals: make the donation conversion Primary. Make "Outbound click" and "Page view" Secondary,',
			'   otherwise bidding optimises for clicks on payment links instead of donations.',
			'6. When at least one donation conversion is recorded, switch the campaigns to "Maximize conversions"',
			'   and tick "A real conversion is recorded in Google Ads" in the plugin settings.',
			'',
		);
		if ( $donate['available'] ) {
			$lines[] = 'KOZ Donate Stats: ' . ( $donate['ready'] ? 'tracking is active' : 'tracking is not ready' ) . ', dataLayer ' . ( $donate['data_layer'] ? 'on (event "' . $donate['event'] . '")' : 'off — turn it on in Donate Stats' ) . '.';
			$lines[] = 'Confirmed donations recorded by Donate Stats: ' . (int) $donate['confirmations'] . '.';
		} else {
			$lines[] = 'KOZ Donate Stats is not active. Use the event of your own donation or contact form.';
		}
		return implode( "\r\n", $lines ) . "\r\n";
	}

	/** Checklist for the person who imports the package. */
	public static function checklist( array $plan ): string {
		$lines = array(
			'POST-IMPORT CHECKLIST',
			'',
			'[ ] Import only 00-IMPORT-ALL.csv in Google Ads Editor (Account > Import > From file), review, keep the changes, then Post.',
			'[ ] Compare the counts in package-manifest.json with what Editor shows (campaigns, ad groups, keywords, negatives, sitelinks, callouts).',
			'[ ] Google Ads > Recommendations > Auto-apply: turn OFF, otherwise Google rewrites keywords (for example to broad match) and URLs.',
			'[ ] Goals: the donation conversion is Primary; "Outbound click" and "Page view" are Secondary (see CONVERSION-SETUP.txt).',
			'[ ] Bid strategy: ' . $plan['bid_strategy'] . ( 'ad_grants' === $plan['account_mode'] ? ' (Ad Grants: manual bids and Maximize clicks are limited to 2.00 USD per click).' : '.' ),
			'[ ] Locations: "Presence: people in or regularly in your targeted locations".',
			'[ ] Languages and Final URLs of every campaign open the right language version of the site.',
			'[ ] After 7 days: Keywords tab > status "Low search volume" or "Eligible, not serving". Download the keyword report and import it in the plugin; the next package replaces these keywords.',
		);
		if ( 'ad_grants' === $plan['account_mode'] ) {
			$lines[] = '[ ] Ad Grants: account CTR must stay at or above 5% each month; pause keywords with many impressions and no clicks.';
		}
		$lines[] = '[ ] Unpause the campaigns only after all items above are done.';
		return implode( "\r\n", $lines ) . "\r\n";
	}
}
