=== KOZ Google Ads Campaign Builder ===
Contributors: ramirkz
Donate link: https://github.com/ramirkoz/ua-free-plugins#support-plugin-development
Tags: google ads, ad grants, nonprofit, google ads editor, keywords
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.5.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Google Ads Editor packages for Ad Grants and Google Ads built from your pages, with demand-checked keywords and conversion setup.

== Description ==

KOZ Google Ads Campaign Builder is an independently installable WordPress plugin built for practical production use. It keeps its main workflow local and under administrator control instead of requiring a paid suite core, hidden telemetry or a custom update service.

The plugin was originally developed for the UA FREE charitable foundation. The foundation website became the first production and testing environment and continues to use the plugin. The public KOZ edition preserves that operational experience while separating the software brand and ownership from the foundation.

The plugin is developed, owned and maintained by Tony Kozyriev (`ramirkz`). UA FREE does not own the plugin or its source code. Support for the developer and charitable donations to the foundation are presented as separate optional destinations.

= Part of the KOZ Suite =

KOZ Google Ads Campaign Builder is **one focused module of the broader KOZ Suite**, a family of independent WordPress plugins that share a common KOZ administration experience and visual identity.

Each KOZ plugin remains independently installable, so site owners can use only the modules they need or combine several modules into one toolkit. This plugin does not require a paid suite core.

Current public KOZ plugins:

* [KOZ SEO Core](https://wordpress.org/plugins/koz-seo-core/) — technical SEO, schema, sitemaps, AI discovery and OpenAI Vision image ALT.
* [KOZ Copy Actions](https://wordpress.org/plugins/koz-copy-actions/) — accessible copy-to-clipboard actions for selected WordPress content.
* [KOZ URL-Only Comment Spam](https://wordpress.org/plugins/koz-url-only-comment-spam/) — privacy-first local moderation for URL-only comment spam.
* [KOZ Static Translate](https://wordpress.org/plugins/koz-static-translate/) — multilingual translation with Microsoft Azure AI Translator and static language routes.
* [KOZ Translate Diagnostics](https://wordpress.org/plugins/koz-translate-diagnostics/) — read-only health checks and privacy-safe diagnostics for KOZ Static Translate.
* [KOZ 404 Guard & URL Intelligence](https://wordpress.org/plugins/koz-404-guard/) — privacy-safe 404/410 diagnostics, controlled same-site redirects and URL intelligence.
* [KOZ Consent Manager](https://wordpress.org/plugins/koz-consent-manager/) — privacy-first visitor consent, optional script control and Google Consent Mode v2.
* [KOZ Donate Stats & Conversions](https://wordpress.org/plugins/koz-donate-stats/) — local donation analytics and consent-aware conversion events.
* [KOZ Site Bridge](https://wordpress.org/plugins/koz-site-bridge/) — secure read-only WordPress diagnostics and authenticated REST automation.
* KOZ Google Ads Campaign Builder — reviewable Google Ads Editor campaign packages, conversion-first bidding and keyword demand analysis.

= Main features =

* Campaign, ad group, keyword, negative keyword, sitelink and callout package for Google Ads Editor (CSV, UTF-8 with BOM).
* Ad copy from the rendered pages: up to 15 headlines and 4 descriptions per ad group from the title, meta description and headings, plus neutral calls to action in 8 languages.
* Other languages use the translated pages published by KOZ Static Translate, without Azure. Optional Azure translation counts against the Static Translate monthly quota.
* Keywords from the page headings, with at most one brand keyword. Import a Google Ads keyword report or a Keyword Planner export to drop "Low search volume" keywords and add matching ideas.
* Landing pages: home page, the KOZ Donate Stats donation page and menu pages automatically, or the pages you choose.
* Conversion first: the package is not "ready to launch" in Ad Grants mode until a real conversion is recorded. Until then the bid strategy is Maximize clicks (Ad Grants caps it at 2.00 USD per click). CONVERSION-SETUP.txt explains how to turn the Donate Stats dataLayer event into a Google Ads conversion.
* Validation of Editor limits and Ad Grants rules (single-word keywords, ad groups, sitelinks, headline and description length).
* POST-IMPORT-CHECKLIST.txt: turn off auto-applied recommendations, make the donation conversion primary, check bids, locations and languages.
* Live AdsBot check (desktop and mobile) with the campaign language: HTTP, timeouts, robots.txt, noindex and page language.
* Ad preview in the admin, Site Health test and the `kozgads_public_status` filter.
* No Google Ads account credentials stored by the plugin.

= Example use cases =

* Prepare a nonprofit Search campaign package before importing it into Google Ads Editor.
* Generate repeatable campaign structures for review by a manager or agency.
* Keep campaign building separate from direct publication and billing access.

= Privacy =

The plugin builds local files and does not require direct access to a Google Ads account. It fetches only pages of the current site. An imported keyword report is reduced to keyword text and counts (impressions, clicks, monthly searches).

= Support =

* Support independent plugin development through the cryptocurrency wallets listed in the administration support panel and project repository.
* Support Ukraine through the UA FREE charitable foundation: https://uafree.org/donate/
* Development contact: ramir@ua.fm
* Source and issue tracker: https://github.com/ramirkoz/ua-free-plugins
* Developer profile: https://www.linkedin.com/in/tonykoz/

Donations are optional. No plugin feature is restricted or unlocked by payment.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New Plugin > Upload Plugin.
2. Activate KOZ Google Ads Campaign Builder.
3. Open the KOZ administration menu and review the plugin settings.
4. Test the configured workflow before enabling it on a production site.

The KOZ rebrand changes the public plugin folder and slug. Legacy internal option names and data structures are retained where required for migration compatibility.

== Frequently Asked Questions ==

= Is this an official UA FREE foundation plugin? =

No. The plugin was originally developed for and tested on the foundation website, but it is independently owned and maintained by Tony Kozyriev.

= Is a donation required? =

No. All included features are free and available under the GPL license.

= My imported campaigns get almost no impressions. Why? =

The two usual causes are bidding and keywords. "Maximize conversions" without any recorded conversion has no signal, so set up a real conversion (see CONVERSION-SETUP.txt in the package) and use Maximize clicks until it records. Keywords marked "Low search volume" never serve: download the keyword report in Google Ads, import it in the plugin and generate a new package.

= Does it use Azure Translator? =

Only when a translated page is missing and you allowed Azure in the settings. The characters are added to the KOZ Static Translate monthly quota, and generation stops when the quota is used up.

= Can I upload directly to Google Ads? =

No. The plugin creates a ZIP for Google Ads Editor. All campaigns are Paused; you review and post them yourself.

= Does the plugin require the whole KOZ suite? =

No. Every public KOZ plugin is independently installable unless its own description explicitly identifies an optional companion integration.

== Changelog ==

= 1.5.0 =
* Ad copy from the rendered pages: up to 15 headlines and 4 descriptions, neutral calls to action in 8 languages, length checks.
* Other languages use the translated pages of KOZ Static Translate; Azure is optional and counts against the Static Translate quota.
* Keywords from page headings with one brand keyword; Google Ads / Keyword Planner report import removes low-search-volume keywords and adds matching ideas.
* Conversions: Ad Grants packages are launch-ready only after a real conversion; automatic bid strategy (Maximize clicks until conversions exist); CONVERSION-SETUP.txt for the Donate Stats event.
* Option to skip the site-language campaign when it has no search demand.
* Google Ads Editor headers (Budget, Languages, Ad type, Negative Phrase), validation of Ad Grants rules, POST-IMPORT-CHECKLIST.txt.
* Landing pages: Donate Stats page and menu pages first, or a manual selection.
* AdsBot check for desktop and mobile with the campaign language and noindex detection.
* Ad preview, Site Health test, `kozgads_public_status` filter.
* No automatic deactivation of the former UA FREE plugin (a notice is shown instead).
* New package folder and random file names; code split into Integrations, Copy, Report, Export, Checks and Health classes.
* Support panel without PayPal; corrected BTC and USDT addresses.

= 1.4.13 =
* Updated WordPress compatibility metadata for WordPress 7.1.
* No functional changes.
= 1.4.12 =
* Restored fully automatic landing-page discovery for campaign generation and live AdsBot checks.
* The live check validates the exact source and translated URLs derived from the same automatically selected campaign landing pages.
* Removed the manual destination-URL setting and all named-page/slug assumptions.
* Removed site-specific automatic callout and negative-keyword content; automatic callouts now come from selected landing pages and negative keywords are only added when configured by the administrator.

= 1.4.11 =
* Removed named-page and slug heuristics from the live AdsBot check.
* Live checks now use only plugin-selected landing pages plus exact optional same-site Final URLs entered by the administrator.
* Added a universal Additional destination URLs setting; the plugin no longer assumes any About/Reports/Gallery/Partners/Donate structure.

= 1.4.10 =
* Resolves canonical top-level About, Reports, Gallery, Partners and Donate pages directly before heuristic fallback.
* Prevents recent report posts or monthly photo reports from replacing the actual Reports/Gallery sitelinks in the live AdsBot check.
* Keeps checks same-site and read-only; no Google Ads account connection or campaign write access is added.

= 1.4.7 =
* Adds a live same-site AdsBot destination check for HTTP/timeouts, robots.txt and basic language alignment.
* Keeps the existing static landing-page preflight and campaign generation behavior unchanged.

= 1.4.6 =
* Added a read-only landing-page preflight for selected source pages and generated same-site campaign URLs.
* Added landing preflight details to package readiness-report.json without changing campaign generation or publishing behavior.
* The preflight performs no external requests and explicitly marks browser/runtime and translated-route availability as not verified.


= 1.4.5 =
* Fixed KOZ Suite menu integration so Google Ads Builder no longer appears as a separate top-level menu.

= 1.4.4 =
* Replaced the dynamic translation call with the existing KOZ runtime dictionary API for Plugin Check compliance.

= 1.4.1 =
* Fixed a critical admin-page error when another KOZ plugin loaded the shared localization helper first.
* Standardized localization-helper compatibility across the KOZ suite.

= 1.4.0 =
* Rebranded the plugin as KOZ Google Ads Campaign Builder with the new `koz-google-ads-campaign-builder` slug and text domain.
* Declared Tony Kozyriev / `ramirkz` as the independent developer and owner.
* Replaced inline shared support-panel CSS and JavaScript with properly enqueued assets.
* Separated developer support from donations to the UA FREE charitable foundation.
* Added immediate runtime administration localization for Ukrainian, Chinese, Spanish, Arabic, Indonesian, Portuguese, French, Japanese, German and Hindi, with English fallback.
* Removed bundled PO and MO translation files for WordPress.org translation handling.
* Rewrote public documentation with functionality, use cases, privacy and ownership details.
* Preserved legacy internal data identifiers where required for migration compatibility.

= 1.3.7 =
* Last stable release under the former UA FREE public branding.

== Upgrade Notice ==

= 1.5.0 =
Better ad copy and keywords, conversion-first bidding, report import and a post-import checklist. Generate a new package after updating.

= 1.4.13 =
WordPress 7.1 compatibility metadata update; plugin behavior remains unchanged.

= 1.4.0 =
The plugin has a new public folder and slug. Deactivate the former UA FREE-branded package before activating this KOZ package, then verify the existing settings and workflow.
