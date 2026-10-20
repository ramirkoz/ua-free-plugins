# KOZ Google Ads Campaign Builder 1.5.0 — changes

**Plugin:** KOZ Google Ads Campaign Builder (slug `koz-google-ads-campaign-builder`, author `ramirkz`, Tony Kozyriev). Not in the wordpress.org catalogue; source in github.com/ramirkoz/ua-free-plugins.

**Base:** 1.4.13 from GitHub. uafree.org ran the same 1.4.13.

**Tested with:** WordPress 7.1.3, PHP 8.4, KOZ Static Translate 0.10.0, KOZ Donate Stats 1.4.0, KOZ Site Bridge 0.6.0.

## Why: what the Google Ads account showed (read only, nothing changed)

**Results of the campaigns made with the plugin**
- "UA FREE | English | Search" and "UA FREE | Українська | Search", running since August 2026.
- **52 impressions, 0 clicks, $0 spent** against a grant budget of 2 × $164.47 per day.
- **Earlier campaigns on the same account:** 99,949 impressions, 7,704 clicks, CTR 7.71%, $12,833 spent.

**Causes**
1. **No conversion.** The bid strategy is "Maximize conversions" towards the goal "Outbound click". No conversion has ever been recorded and the tag is inactive. The Donate Stats event `uafree_donation_event` is not connected to Google Ads.
2. **Keywords without search demand.** 50 of 68 keywords have the status "Low search volume". In the Ukrainian campaign it is 30 of 32. Most are brand combinations and long phrases.
3. **Recommendations auto-apply was on.** It added 27 broad-match keywords, removed 27 phrase-match keywords and changed 27 final URLs.
4. **Weak Ukrainian ads.** Three of four have ad strength "Poor" and one has "Average".

## Changes

### 1. Conversion first
- **Launch readiness:** in Ad Grants mode the package is "ready to launch" only after a real conversion is ticked as recorded.
- **Conversion source:** the plugin reads KOZ Donate Stats (dataLayer event name, confirmed donations) and shows them in the "Conversion and bidding" block.
- **CONVERSION-SETUP.txt in the ZIP** explains:
  - how to create the conversion action;
  - how to add a GTM trigger for the Donate Stats event and the conversion tag;
  - how to keep Consent Mode;
  - how to make the donation conversion primary and "Outbound click" and "Page view" secondary.

### 2. Bidding
- **New setting `bid_strategy = auto`:** it uses "Maximize clicks" until a conversion is recorded, then "Maximize conversions".
- **Override in Ad Grants mode:** "Maximize conversions" without a recorded conversion is replaced by "Maximize clicks". Ad Grants caps this strategy at 2.00 USD per click.
- **Manual CPC** writes Max CPC 2.00.

### 3. Keywords with demand
- **Source:** keywords come from title, H1 and H2 segments only, in phrases of 2–4 words.
- **Filtering:**
  - at most one brand keyword, on the home page group only;
  - no phrases containing brand words;
  - no repeated words;
  - no interface text (cookie or privacy banners, menus);
  - no payment-service names.
- **Donation pages** also get typical searches. English adds "donate to {topic}", "{topic} donation" and "{topic} charity", where the topic is the most frequent proper noun on the page. On uafree.org this gives "donate to ukraine", the only keyword that actually served in the account.
- **Report import (`KOZGADS_Report`):**
  - accepts the Google Ads keyword report (UTF-16 tab-separated or CSV) or a Keyword Planner export;
  - "Low search volume" keywords, and those below the monthly-search minimum (default 10), are removed from the next package;
  - matching Keyword Planner ideas are added.

### 4. Language campaigns
- **"Create a campaign in the site language" option:** turn it off when that language has no search demand.
- **Other languages** use the pages translated by KOZ Static Translate. A page with an untranslated title, a different script or a different `lang` is skipped with a warning; Azure is used only if it is allowed.
- **Script filter:** keywords, headlines and descriptions in another script are removed.

### 5. Ad copy (`KOZGADS_Copy`)
- **Source:** the rendered same-site page (title, meta description, H1, H2, text sample), cached for 10 minutes.
- **Limits:** up to 15 headlines (30 characters) and 4 descriptions (90 characters). Words are not cut, headings must fit whole, and quotes and HTML entities are removed.
- **Neutral calls to action** in 8 languages (en, uk, de, fr, es, pl, pt, it), for donation pages and for other pages.
- **Sitelinks:** the whole text up to 25 characters (without the brand when it does not fit).
- **Callouts:** whole H2 headings only.
- **Warning** when there are fewer than 8 headlines.

### 6. Checklist
- **POST-IMPORT-CHECKLIST.txt** covers:
  - import only 00-IMPORT-ALL.csv and compare the counts with package-manifest.json;
  - **turn off auto-applied recommendations**;
  - conversions and bid strategy;
  - locations ("Presence");
  - languages and URLs;
  - after 7 days, export the keyword report and import it into the plugin;
  - Ad Grants CTR of 5%.

### 7. Report analysis
- **Admin block "Keyword search demand":**
  - import and delete the report;
  - counts: keywords, low volume, without impressions, with Keyword Planner data.
- **Ad preview:**
  - the first three headlines, URL and descriptions;
  - all headlines, descriptions and keywords;
  - which keywords were removed and which were added.
- **Overview** shows a warning when more than half of the keywords have low search volume.

### 8. Google Ads Editor format (`KOZGADS_Export`)
- **Columns:** `Budget`, `Budget type`, `Bid Strategy Type`, `Languages`, `Campaign Status`, `Ad Group Status`, `Max CPC`, `Ad type = Responsive search ad`.
- **Campaign negative keywords:** `Negative Phrase` with an empty Ad Group.
- **File format:** UTF-8 with BOM and CRLF line endings; formula characters are neutralised.
- **Validation:**
  - Ad Grants needs at least 2 ad groups and 2 sitelinks;
  - no single-word keywords;
  - at least 3 headlines and 2 descriptions;
  - headline and description length;
  - HTTPS;
  - no duplicate campaign names.
- **Campaign names** use the brand: "UA FREE | English | Search | id".

### 9. Static Translate
- **Languages:** the source and published languages come from `kozstx_get_status()`.
- **Azure key:** read from `kozstx_settings`; the former UA FREE option is a fallback.
- **Quota:** an Azure call checks `KOZSTX_Quota::available()` first and records the characters with `increment_usage()`.

### 10. Technical
- **Landing pages:**
  - automatic: home page, the Donate Stats donation page, menu pages, then the longest other pages;
  - or a manual selection;
  - blog posts only when chosen.
- **AdsBot check (`KOZGADS_Checks`):**
  - desktop and mobile;
  - Accept-Language in the campaign language;
  - noindex shown as a warning;
  - script detection.
- **Site Health:** test `kozgads_package`.
- **Public status:** filter `kozgads_public_status`.
- **Package storage:** folder `koz-google-ads-packages` with random file names; the former folder is still readable.
- **Former UA FREE plugin:** no longer deactivated automatically; a notice is shown instead.
- **Support panel:** PayPal removed; BTC `bc1q4dn8e7sz2866g7qplqtshh98j54tvuau5ghuuk`, USDT `TYSgyk7K3XB4WPHprf5w8ZodFAfxFfDdbF` (both were wrong).
- **Code split:** the 2,514-line class became Plugin (about 2,150 lines) plus Integrations, Copy, Report, Export, Checks and Health.
- **Translations and documentation:**
  - Ukrainian for all new admin strings;
  - the support-panel text in all 10 languages;
  - readme: description, features, 3 FAQ entries, changelog, upgrade notice;
  - CHANGELOG.md.

## Testing

**Before installing**
- Every PHP file passed a PHP 8.3 parse check (php-wasm).
- The copy, report and export classes ran on real uafree.org pages in php-wasm with WordPress stubs. This found and fixed four problems:
  - `trim()` with multibyte characters cut Cyrillic;
  - the consent banner leaked into H2;
  - keywords were joined across sentences;
  - a UTF-16 export was not recognised.

**On uafree.org**
- Installed by upload four times (final build 1.5.0); no PHP errors in the logs.
- **Overview:**
  - "No real conversion in Google Ads" with the Donate Stats event `uafree_donation_event`;
  - bid strategy "Maximize clicks";
  - the translation warning is gone, because Static Translate publishes en.

**AdsBot check**
- 16 URLs (8 × desktop/mobile), all 200, 0 issues.
- 2 warnings: `/en/` and `/en/zvit/` are noindex, because KOZ Static Translate marks partly translated pages.

**Final package**

| Item | Value |
|---|---|
| Campaigns | 2 (uk → Ukraine; en → GB, US, CA, AU, IE, NZ) |
| Ad groups | 7 |
| Keywords | 43 |
| Sitelinks | 7 |
| Callouts | 2 |
| Bid strategy | Maximize clicks |
| Blocking issues | 1: no recorded conversion |

- The English reports page was skipped: its title is still Ukrainian.
- Example, English Donate group: 12 headlines, 3 descriptions; keywords support ukraine, donate to ukraine, ukraine donation, ukraine charity, donate online, make a donation, online donation.

**Report import**
- A test Google Ads export (UTF-16 TSV, 6 keywords) was read as 6 keywords, 4 with low volume.
- The next package left out "зробити донат" and "charitable foundation".
- The test data was then deleted and a clean package generated.

**Admin checks**
- Site Health: "Google Ads package needs attention" (no conversion). This is correct.
- Plugin Check: "Checks complete. No errors found." The first run warned that the short description was over 150 characters; fixed.

## Not tested / to do in Google Ads
- **Import into Google Ads Editor:** needs the Editor application. Column names follow the Editor documentation and exports; check the counts against package-manifest.json.
- **In the account, done by the site owner:**
  - turn off auto-applied recommendations;
  - create the donation conversion from `uafree_donation_event` and make it primary;
  - switch the strategy to "Maximize clicks";
  - after 7 days, import the keyword report into the plugin.
- **Found on the site, separate from this plugin:** `/en/` and other English pages are noindex because the KOZ Static Translate translation is incomplete; the Azure quota is at 99.8%.
