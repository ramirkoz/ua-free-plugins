=== KOZ Consent Manager ===
Contributors: ramirkz
Donate link: https://github.com/ramirkoz/ua-free-plugins#support-plugin-development
Tags: cookie consent, gdpr, consent mode, cookie banner, privacy
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 0.3.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie consent banner with Google Consent Mode v2, WP Consent API, Microsoft consent and blocked YouTube embeds. No IP addresses stored.

== Description ==

KOZ Consent Manager is an independently installable WordPress plugin built for practical production use. It keeps its main workflow local and under administrator control instead of requiring a paid suite core, hidden telemetry or a custom update service.

The plugin was originally developed for the UA FREE charitable foundation. The foundation website became the first production and testing environment and continues to use the plugin. The public KOZ edition preserves that operational experience while separating the software brand and ownership from the foundation.

The plugin is developed, owned and maintained by Tony Kozyriev (`ramirkz`). UA FREE does not own the plugin or its source code. Support for the developer and charitable donations to the foundation are presented as separate optional destinations.

= Part of the KOZ Suite =

KOZ Consent Manager is **one focused module of the broader KOZ Suite**, a family of independent WordPress plugins that share a common KOZ administration experience and visual identity.

Each KOZ plugin remains independently installable, so site owners can use only the modules they need or combine several modules into one toolkit.

Current public KOZ plugins:

* [KOZ SEO Core](https://wordpress.org/plugins/koz-seo-core/) — technical SEO, schema, sitemaps, AI discovery and OpenAI Vision image ALT.
* [KOZ Copy Actions](https://wordpress.org/plugins/koz-copy-actions/) — accessible copy-to-clipboard actions for selected WordPress content.
* [KOZ URL-Only Comment Spam](https://wordpress.org/plugins/koz-url-only-comment-spam/) — privacy-first local moderation for URL-only comment spam.
* [KOZ Static Translate](https://wordpress.org/plugins/koz-static-translate/) — AI-powered multilingual translation with Microsoft Azure AI Translator and static language routes.
* [KOZ Translate Diagnostics](https://wordpress.org/plugins/koz-translate-diagnostics/) — read-only health checks and privacy-safe diagnostic reports for KOZ Static Translate.
* [KOZ 404 Guard & URL Intelligence](https://wordpress.org/plugins/koz-404-guard/) — privacy-safe 404/410 diagnostics, controlled same-site redirects and URL intelligence.
* KOZ Consent Manager — privacy-first visitor consent, optional script control and Google Consent Mode v2 synchronization.
* [KOZ Donate Stats & Conversions](https://wordpress.org/plugins/koz-donate-stats/) — local donation-journey analytics and consent-aware conversion events for WordPress.

= Main features =

* Necessary consent always on; analytics, advertising and external media off until the visitor allows them.
* Bottom bar, corner box or centred dialog; Accept and Reject look the same.
* Google Consent Mode v2 with all consent types, region scope, wait_for_update, ads_data_redaction and url_passthrough.
* WP Consent API provider: Site Kit, WooCommerce, WP Statistics and other plugins follow the visitor's choice.
* Microsoft Clarity consentv2 and Microsoft Advertising UET consent.
* dataLayer events for Google Tag Manager triggers.
* YouTube, Vimeo, Google Maps and other embeds blocked until consent, with a local notice and "Load content".
* Global Privacy Control support.
* Banner texts in 11 languages: Ukrainian, English, Chinese, Spanish, Arabic, Indonesian, Portuguese, French, Japanese, German, Hindi.
* Optional consent records without IP addresses, with CSV export.
* Home-page service scan, Site Health test and shortcodes for the settings link and the services list.
* Works with page caching: the consent state is applied in the browser.

= Example use cases =

* Delay analytics or advertising scripts until a visitor grants the matching consent.
* Keep consent handling local while using ordinary WordPress script registration.
* Provide a clear baseline consent layer for a privacy-conscious site.

= Privacy =

The plugin sends no telemetry, has no remote configuration and does not bundle analytics or advertising services. Consent records are optional and off by default; they never contain IP addresses or User-Agent values.

= Support =

* Support independent plugin development through the cryptocurrency wallets listed in the administration support panel and project repository.
* Support Ukraine through the UA FREE charitable foundation: https://uafree.org/donate/
* Development contact: ramir@ua.fm
* Developer profile: https://www.linkedin.com/in/tonykoz/
* Source and issue tracker: https://github.com/ramirkoz/ua-free-plugins

Donations are optional. No plugin feature is restricted or unlocked by payment.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New Plugin > Upload Plugin.
2. Activate KOZ Consent Manager.
3. Open the KOZ administration menu and review the plugin settings.
4. Test the configured workflow before enabling it on a production site.

The KOZ rebrand changes the public plugin folder and slug. Legacy internal option names and data structures are retained where required for migration compatibility.

== Frequently Asked Questions ==

= Does it work with Google Site Kit? =

Yes. Install the free WP Consent API plugin: Site Kit reads the visitor's choice from it, and KOZ Consent Manager passes every choice there. The plugin also sends Google Consent Mode updates directly.

= Are YouTube videos blocked? =

Yes, when "Block embeds until consent" is on (default). The video keeps its place and shows a notice. The visitor can load one video or allow external media for the whole site.

= Does the plugin store personal data? =

The consent choice is stored in a first-party cookie in the visitor's browser. Consent records are off by default; when turned on they keep a random ID, the choice, the policy version, the time, the language and the GPC flag, never an IP address or User-Agent.

= How do I let visitors change their choice? =

Use the floating button, add a link to #kozconsent to a menu, or place the [kozconsent_settings] shortcode.

= Is this an official UA FREE foundation plugin? =

No. The plugin was originally developed for and tested on the foundation website, but it is independently owned and maintained by Tony Kozyriev.

= Is a donation required? =

No. All included features are free and available under the GPL license.

= Does the plugin require the whole KOZ suite? =

No. Every public KOZ plugin is independently installable unless its own description explicitly identifies an optional companion integration.

== Changelog ==

= 0.3.0 =
* New: WP Consent API support. The plugin declares an opt-in consent type, registers as a consent provider and passes every choice to wp_set_consent(), so Site Kit, WooCommerce, WP Statistics and other API-aware plugins follow the visitor's choice (necessary → functional; analytics → statistics, statistics-anonymous, preferences; advertising → marketing).
* New: YouTube, Vimeo, Google Maps, Spotify, SoundCloud, Facebook, Instagram, TikTok and X embeds are blocked until "External media" is allowed. The embed keeps its size and shows a local notice with "Load content" and "Always allow external media"; YouTube can load from youtube-nocookie.com. Works with page caching.
* New: Google Consent Mode v2 sends all seven consent types, wait_for_update, optional ads_data_redaction and url_passthrough, and can deny by default for every visitor or only in the EEA, the UK and Switzerland.
* New: Microsoft Clarity consentv2 and Microsoft Advertising UET consent signals.
* New: kozconsent_default and kozconsent_update dataLayer events for Google Tag Manager triggers.
* New: Global Privacy Control: when the browser sends GPC, advertising stays off even after "Accept all" and the banner says so.
* New: banner layouts (bottom bar, corner box, centred dialog with focus trap), accent colour, equal-prominence Accept and Reject buttons, right-to-left layout for Arabic, and the option to hide the floating button and open settings from any #kozconsent link or the [kozconsent_settings] shortcode.
* New: optional consent records (off by default): random consent ID, latest choice, policy version, time, language and GPC flag, without IP address or User-Agent; 30-day statistics, CSV export and automatic deletion after the retention period.
* New: home-page service scan showing which services wait for consent (Consent Mode, Microsoft signal, blocked embed or not controlled), a Site Health test and a [kozconsent_services] shortcode for the privacy policy page.
* New: script handles can be assigned to a category in the admin screen.
* Changed: banner texts of all 11 languages can be edited from language tabs; the admin screen is split into General, Banner texts, Signals and services and Consent records.
* Changed: the banner is always rendered hidden and shown by JavaScript, so cached pages never carry another visitor's state; settings are autoloaded.
* Changed: the main class is split into settings, consent state, Consent Mode, WP Consent API, front end, media, records, scanner, Site Health and admin classes; the KOZ Suite menu uses the shared registry.
* Added translations for the new visitor texts in all 11 languages and Ukrainian admin translations.
* Removed PayPal from the developer support panel and corrected the BTC and USDT TRC-20 developer wallet addresses (from 0.2.13).

= 0.2.13 =
* Removed PayPal from the developer support panel and corrected the BTC and USDT TRC-20 developer wallet addresses.

= 0.2.12 =
* Updated KOZ Suite documentation to include all eight current public plugins.
* Updated WordPress compatibility metadata for WordPress 7.1.
* No functional changes.

= 0.2.11 =
* Added optional Google Consent Mode v2 synchronization for Google tags and Google Tag Manager.
* Google consent defaults are written before normal page tags, with analytics and advertising denied until the matching visitor choice is granted.
* Advertising consent maps to ad_storage, ad_user_data and ad_personalization; analytics maps to analytics_storage.
* Consent updates are sent immediately when visitors accept, reject or save customized choices.

= 0.2.10 =
* Admin-only support UI fix: prevent duplicate support panels when the shared KOZ Suite panel is active.
* Standalone support fallback now uses the current KOZ layout and includes both UA FREE actions: Donate and About the foundation.
* No changes to consent logic, stored settings, cookies or integrations.


= 0.2.9 =
* Removed the dynamic legacy integration filter invocation flagged by Plugin Check.
* Canonical kozconsent_integrations filter remains unchanged.

= 0.2.8 =
* Removed the dynamic legacy integration hook flagged by WordPress Plugin Check; integrations now use only the canonical kozconsent_integrations hook.
* Hardened current plugin-owned identifiers to the unique kozconsent / KOZCONSENT prefix.
* Added safe migration from the former settings key and legacy consent-cookie fallback.
* Removed shared suite registry identifiers from the standalone package.


= 0.2.7 =
* Added separate consent-banner templates for Ukrainian, English, Chinese, Spanish, Arabic, Indonesian, Portuguese, French, Japanese, German and Hindi.
* The frontend now selects the banner template from the current WordPress page locale, with English fallback for unsupported locales.
* Existing single-language banner text is preserved under the site language during migration.
* The settings screen edits only the template matching the current WordPress user language.

= 0.2.3 =
* Corrected KOZ Suite status detection and navigation for active KOZ plugins.
* Legacy UA FREE packages are reported separately instead of appearing as active KOZ plugins.
* Changed the KOZ Suite menu icon to the WordPress layout icon.
* Updated PHP namespaces to use the full plugin-specific prefix required by WordPress Plugin Check.

= 0.2.1 =
* Added a dedicated KOZ Suite overview and separate Consent Manager submenu.
* Removed legacy global PHP helper functions to satisfy WordPress naming rules.
* Added the developer LinkedIn profile to metadata, documentation and the administration support panel.
* Rebranded the plugin as KOZ Consent Manager with the new `koz-consent-manager` slug and text domain.
* Declared Tony Kozyriev / `ramirkz` as the independent developer and owner.
* Replaced inline shared support-panel CSS and JavaScript with properly enqueued assets.
* Separated developer support from donations to the UA FREE charitable foundation.
* Removed bundled PO and MO translation files for WordPress.org translation handling.
* Rewrote public documentation with functionality, use cases, privacy and ownership details.
* Preserved legacy internal data identifiers where required for migration compatibility.

= 0.1.7 =
* Last stable release under the former UA FREE public branding.

== Upgrade Notice ==

= 0.3.0 =
Adds WP Consent API support (for Site Kit), blocked YouTube and map embeds until consent, Microsoft consent, GPC, banner layouts and optional consent records. Existing settings and banner texts are kept.

= 0.2.13 =
Developer support details updated: PayPal removed, BTC and USDT TRC-20 addresses corrected.

= 0.2.12 =
WordPress 7.1 compatibility metadata update; plugin behavior remains unchanged.

= 0.2.7 =
Consent banner text is now stored separately by language. Existing text is preserved for the site language; review other language templates after updating.

= 0.2.3 =
This maintenance release fixes WordPress naming checks and updates the KOZ Suite menu icon.

= 0.2.1 =
The plugin has a new public folder and slug. Deactivate the former UA FREE-branded package before activating this KOZ package, then verify the existing settings and workflow.
