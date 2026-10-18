# KOZ Consent Manager 0.3.0 — changes

**Plugin:** KOZ Consent Manager (wordpress.org slug `koz-consent-manager`, author `ramirkz`, Tony Kozyriev).

**Version history:**
- previous public release: 0.2.12;
- 0.2.13: support panel only.

**Base for 0.3.0:** catalogue 0.2.12. The build installed on uafree.org was identical (SHA-256 of the main files checked).

**Tested with:**
- WordPress 7.1.3;
- PHP 8.4.26;
- Site Kit by Google with Consent Mode on;
- WP Consent API 2.1.0, installed from wordpress.org during this work.

## What was wrong on uafree.org with 0.2.12

- Site Kit loads gtag (GT-P35J5SQ3 and Google Ads AW-16498596579), GTM (GTM-WLXHJC6C) and AdSense.
- **Site Kit's own Consent Mode** printed a second `consent default`, for the EEA, the UK and Switzerland only, with `wait_for_update: 500`.
- Site Kit reads the visitor's choice from **WP Consent API**. That plugin was not installed, and Consent Manager did not support it, so Site Kit never received the choice.
- **Six YouTube embeds on the home page** (`youtube.com/embed`) loaded before any consent. The "External media" category existed but blocked nothing.
- Consent Mode had only 4 signals. There was no `wait_for_update`, no region scope and no dataLayer event for GTM.
- There was no Microsoft Clarity or UET consent.
- "Accept" was a primary button and "Reject" a secondary one, which goes against the EDPB guidance on equal prominence.
- Banner texts could be edited only in the administrator's own profile language.
- Settings were not autoloaded.
- Only script handles registered in code could be blocked.

## Comparison with similar plugins

| Plugin | Active installs |
|---|---|
| Complianz | 1M+ |
| CookieYes | 1M+ |
| Real Cookie Banner | 100k+ |
| WP Consent API (standard) | 200k+ |

- **Standard features of these plugins:**
  - WP Consent API support;
  - blocked YouTube, Vimeo and Maps embeds with placeholders;
  - consent records;
  - several banner layouts;
  - Microsoft consent.
- **GPC and geo targeting** are mostly paid features.
- **Clarity** has required a consentv2 signal for EEA/UK/CH visitors since 31.10.2025.

## Changes

### 1. Support panel
- PayPal removed.
- BTC `bc1q4dn8e7sz2866g7qplqtshh98j54tvuau5ghuuk`
- ETH/USDC `0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229`
- USDT TRC-20 `TYSgyk7K3XB4WPHprf5w8ZodFAfxFfDdbF`

### 2. WP Consent API (`class-kozconsent-wp-consent-api.php`)
- The `wp_get_consent_type` filter returns `optin` while the banner is on.
- The plugin registers itself through `wp_consent_api_registered_{basename}`.
- In JS, every choice goes to `wp_set_consent()`. On page load the stored choice is synced if the API cookies differ.
- Mapping:

| WP Consent API category | Consent Manager category |
|---|---|
| functional | necessary (always allow) |
| statistics | analytics |
| statistics-anonymous | analytics |
| preferences | analytics |
| marketing | advertising |

- Effect: Site Kit's Consent Mode, WooCommerce, WP Statistics and similar plugins follow the visitor's choice.

### 3. Blocked embeds (`class-kozconsent-media.php`)
- **How the page is rewritten:**
  - an output buffer runs on `template_redirect` (not in admin, feeds, REST, AJAX, cron or the customizer);
  - the `<iframe>` element itself is kept, so its size, classes and theme CSS do not change;
  - `src` moves to `data-kozconsent-src`;
  - a local notice is shown through `srcdoc`, with the buttons "Load content" (this embed only) and "Always allow external media".
- **Services:** YouTube, Vimeo, Google Maps, Spotify, SoundCloud, Facebook, Instagram, TikTok and X. The list can be changed with the `kozconsent_media_services` filter. GTM `<noscript>` iframes are skipped.
- **Page caching:** pages are always rewritten the same way, and JS restores the embeds after consent. Cached pages therefore stay correct for every visitor.
- **youtube-nocookie.com:** optional, on by default.

### 4. Google Consent Mode v2 (`class-kozconsent-consent-mode.php`)
- An inline script in `wp_head` at priority -1000 (`wp_print_inline_script_tag`) reads the cookie in the browser.
- All 7 consent types are set:
  - `analytics_storage` and `personalization_storage` follow analytics;
  - the `ad_*` types follow advertising;
  - `functionality_storage` and `security_storage` are always granted.
- `wait_for_update` defaults to 500 ms.
- Optional `ads_data_redaction` (on) and `url_passthrough` (off).
- **Region setting:**
  - "every visitor": denied by default for everyone;
  - "EEA/UK/CH only": a regional default plus a global default that is granted until the visitor chooses.
- Site Kit's Consent Mode is detected and shown in the admin screen.

### 5. Microsoft and GTM
- **UET:** `uetq.push('consent','default'|'update',{ad_storage})`.
- **Clarity:** a queue stub plus `clarity('consentv2',{ad_Storage, analytics_Storage})`.
- **dataLayer events:**
  - `kozconsent_default` on page load;
  - `kozconsent_update` on every change;
  - both carry `kozconsent: {analytics, advertising, external_media, choice}`.

### 6. Global Privacy Control
- When `navigator.globalPrivacyControl === true`, advertising stays off even after "Accept all".
- The banner shows a notice about this.
- The visitor can still turn advertising on in the settings.

### 7. Banner
- **Layouts:** bar, box (in the corner), modal (centred, page scroll locked, focus trapped).
- **Buttons:** accent colour setting; "Accept" and "Reject" look identical.
- **Arabic:** `dir="rtl"`.
- **Opening the settings:**
  - the floating button can be turned off;
  - any link to `#kozconsent` opens the settings;
  - so do the `[kozconsent_settings]` shortcode and `KOZCONSENT.open()`.
- **Rendering:** the banner is always rendered `hidden`, and JS shows it only when there is no valid choice. Cached pages therefore never carry another visitor's state.
- WCAG basics: 44 px buttons, `focus-visible` outlines, `prefers-reduced-motion`.

### 8. Consent records (`class-kozconsent-log.php`, off by default)
- **Storage:** table `{prefix}kozconsent_log`, one row per random 32-hex ID created in the browser and stored in the consent cookie.
- **Each row holds:**
  - the latest choice (accept/reject/custom) and the three categories;
  - policy version, created and updated time (UTC);
  - GPC flag, language, number of changes.
- No IP address, User-Agent or page address is stored.
- **REST endpoint:** `POST kozconsent/v1/log`. It is public by design, because visitors are not logged in and pages are cached. Inputs are validated against a schema. Records are written only while the option is on and the policy version matches.
- **Admin screen:** 30-day statistics, the last 50 records, CSV export and "Delete all".
- **Cleanup:** a daily cron removes records older than the retention period (default 365 days, at most 200,000 rows).

### 9. Service scan, Site Health, shortcodes
- **Home-page scan** (an admin-started `wp_remote_get` to the site itself) finds:
  - Google tag, GTM, Google Ads, AdSense;
  - Clarity, UET;
  - Meta Pixel, Hotjar, TikTok, LinkedIn, Yandex;
  - embeds.
- For each service it shows how consent applies: Google Consent Mode, Microsoft signal, blocked until consent, or not controlled.
- **Site Health test `kozconsent_setup`:**
  - banner off;
  - Site Kit Consent Mode on without WP Consent API;
  - uncontrolled services found by the scan.
- **`[kozconsent_services]`:** a list of categories and the services in each, for the privacy policy page.

### 10. Admin screen
- **Tabs:**
  - General;
  - Banner texts, with a tab for each of the 11 languages (an asterisk marks your own text);
  - Signals and services;
  - Consent records.
- **Script handles:** the "handle=category" field ties any registered WordPress script to a category.

### 11. Architecture and performance
- **Classes:** Settings, Manager (public API and consent state), Consent_Mode, WP_Consent_API, Frontend, Media, Log, Scanner, Site_Health, Admin, Suite_Registry.
- **Compatibility:** the public API (`register_integration`, `is_allowed`, `status`, `public_integrations`, `settings`, the legacy class alias, JS globals and events) is unchanged.
- **Settings:** the option is autoloaded (schema 3) and migrated once; existing settings and banner texts are kept.
- **Script data:** `KOZCONSENTConfig` is set with `add_data` instead of being appended 3 times.

### 12. Translations and readme
- **Visitor strings:** 5 new ones, translated into all 11 languages.
- **Admin strings:** 85 new ones, translated into Ukrainian.
- **readme:**
  - new short description and tags;
  - feature list;
  - privacy section;
  - 4 new FAQ entries (Site Kit, YouTube, data, changing the choice);
  - changelog and upgrade notice.

## Testing on uafree.org

**Installation**
- WP Consent API 2.1.0 was installed from wordpress.org and activated. Between activating it and installing Consent Manager 0.3.0 the consent type was empty for a few minutes; 0.3.0 set it to `optin`.
- Consent Manager was installed by upload ("Replace current with uploaded"); the settings migrated without PHP errors.

**Home page for an anonymous visitor**
- `consent_type: "optin"`.
- Our Consent Mode script comes before Site Kit's.
- 6 YouTube iframes in the HTML (12 in the DOM) are blocked, 0 load.
- The banner is rendered `hidden` and shown by JS.

**Built-in browser, no sign-in**
- **First visit:**
  - the banner is visible;
  - the consent defaults are denied;
  - the `kozconsent_default` event is in the dataLayer.
- **"Reject":**
  - the banner is hidden and the cookie saved;
  - `wp_has_consent('marketing')` = false and `functional` = true;
  - 5 `consent update` calls are sent;
  - the `kozconsent_update` event is sent.
- **"Load content" in one video:**
  - only that iframe loads, from `www.youtube-nocookie.com`;
  - 11 stay blocked;
  - the choice does not change.
- **"Accept all" from the floating button:**
  - all categories granted, 32-hex consent ID;
  - all embeds load from youtube-nocookie;
  - `wp_has_consent('marketing'|'statistics')` = true.

**Layouts and admin**
- The modal is centred with a dimmed background; the box is 420 px in the bottom-right corner; the bar is the default.
- Scan results: Google tag, GTM and Google Ads under Google Consent Mode; YouTube ×6 blocked until consent; nothing uncontrolled.
- Site Health: "Visitor consent is set up" (good).
- Plugin Check: "Checks complete. No errors found."

**Production settings**
- No settings were changed on the site. The layout stays "bar" and consent records stay off.
- The test choices exist only in the cookies of the built-in test browser.
