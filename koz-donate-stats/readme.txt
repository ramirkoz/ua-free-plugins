=== KOZ Donate Stats & Conversions ===
Contributors: ramirkz
Donate link: https://github.com/ramirkoz/ua-free-plugins#support-plugin-development
Tags: donations, analytics, conversions, fundraising, privacy
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.4.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Donation funnel analytics with confirmed LiqPay and PayPal donations and consent-aware Google Ads events. No personal data.

== Description ==

KOZ Donate Stats & Conversions is an independently installable WordPress plugin built for practical production use. It keeps its main workflow local and under administrator control instead of requiring a paid suite core, hidden telemetry or a custom update service.

The plugin was originally developed for the UA FREE charitable foundation. The foundation website became the first production and testing environment and continues to use the plugin. The public KOZ edition preserves that operational experience while separating the software brand and ownership from the foundation.

The plugin is developed, owned and maintained by Tony Kozyriev (`ramirkz`). UA FREE does not own the plugin or its source code. Support for the developer and charitable donations to the foundation are presented as separate optional destinations.

= Part of the KOZ Suite =

KOZ Donate Stats & Conversions is **one focused module of the broader KOZ Suite**, a family of independent WordPress plugins that share a common KOZ administration experience and visual identity.

Each KOZ plugin remains independently installable, so site owners can use only the modules they need or combine several modules into one toolkit.

Current public KOZ plugins:

* [KOZ SEO Core](https://wordpress.org/plugins/koz-seo-core/) — technical SEO, schema, sitemaps, AI discovery and OpenAI Vision image ALT.
* [KOZ Copy Actions](https://wordpress.org/plugins/koz-copy-actions/) — accessible copy-to-clipboard actions for selected WordPress content.
* [KOZ URL-Only Comment Spam](https://wordpress.org/plugins/koz-url-only-comment-spam/) — privacy-first local moderation for URL-only comment spam.
* [KOZ Static Translate](https://wordpress.org/plugins/koz-static-translate/) — AI-powered multilingual translation with Microsoft Azure AI Translator and static language routes.
* [KOZ Translate Diagnostics](https://wordpress.org/plugins/koz-translate-diagnostics/) — read-only health checks and privacy-safe diagnostic reports for KOZ Static Translate.
* [KOZ 404 Guard & URL Intelligence](https://wordpress.org/plugins/koz-404-guard/) — privacy-safe 404/410 diagnostics, controlled same-site redirects and URL intelligence.
* [KOZ Consent Manager](https://wordpress.org/plugins/koz-consent-manager/) — privacy-first visitor consent, optional script control and Google Consent Mode v2 synchronization.
* KOZ Donate Stats & Conversions — local donation-journey analytics and consent-aware conversion events for WordPress.
* [KOZ Site Bridge](https://wordpress.org/plugins/koz-site-bridge/) — secure read-only WordPress diagnostics, controlled same-site probes and authenticated REST automation.

= Main features =

* Donation funnel: page visits, support clicks, payment openings, copied payment details and confirmed donations, compared with the previous period.
* Confirmed donations from LiqPay and PayPal, or from any service through a signed callback.
* Known payment services recognised automatically; languages of KOZ Static Translate tracked as one page.
* Optional amounts per currency, off by default.
* Consent-aware Google Ads and GA4 events for Google Tag Manager or gtag.
* Daily chart, payment services, languages, dashboard widget, Site Health test, CSV and JSON export.
* No IP addresses, referrers, fingerprinting or donor data.

= Example use cases =

* Measure which donation routes and payment choices visitors use.
* Send a reviewed donation-intent event to Google Tag Manager without claiming payment completion.
* Compare multilingual fundraising journeys while keeping reporting inside WordPress.

= Privacy =

The plugin is designed for limited local reporting. It does not confirm completed payments unless a separate verified payment integration supplies that fact.

= Support =

* Support independent plugin development through the cryptocurrency wallets listed in the administration support panel and project repository.
* Support Ukraine through the UA FREE charitable foundation: https://uafree.org/donate/
* Development contact: ramir@ua.fm
* Source and issue tracker: https://github.com/ramirkoz/ua-free-plugins
* Developer profile: https://www.linkedin.com/in/tonykoz/

Donations are optional. No plugin feature is restricted or unlocked by payment.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New Plugin > Upload Plugin.
2. Activate KOZ Donate Stats & Conversions.
3. Open the KOZ administration menu and review the plugin settings.
4. Test the configured workflow before enabling it on a production site.

The KOZ rebrand changes the public plugin folder and slug. Legacy internal option names and data structures are retained where required for migration compatibility.

== Frequently Asked Questions ==

= Does it count real donations? =

Clicks and payment-page openings are not donations. Connect LiqPay (server callback with signature check), PayPal (IPN) or your own signed callback; only payments the service reports as successful are counted.

= Are donation amounts stored? =

Only when you turn on "Store donation amounts" (off by default). The plugin keeps the amount and currency, never the donor's name, email or card.

= Does it need a consent banner? =

It works with KOZ Consent Manager and with any plugin that supports WP Consent API. Before analytics consent the plugin uses no browser storage; Google Ads and GA4 events are sent only with consent.

= Is this an official UA FREE foundation plugin? =

No. The plugin was originally developed for and tested on the foundation website, but it is independently owned and maintained by Tony Kozyriev.

= Is a donation required? =

No. All included features are free and available under the GPL license.

= Does the plugin require the whole KOZ suite? =

No. Every public KOZ plugin is independently installable unless its own description explicitly identifies an optional companion integration.

== Changelog ==

= 1.4.0 =
* New: confirmed donations straight from LiqPay (server callback verified with the private key; only status "success" counts) and PayPal (IPN verified with PayPal; only "Completed" counts). Only a hash of the payment reference is stored.
* New: optional donation amounts and currencies from confirmations (off by default; turning it off deletes them), shown as totals per currency.
* New: report with a donation funnel and the change against the previous period, a daily chart, payment services and confirmed amounts; dashboard widget for the last 7 days; Site Health test.
* New: known payment services are recognised without configuration (LiqPay, monobank, PayPal, WayForPay, Fondy, Privat24, Stripe, Buy Me a Coffee, Ko-fi, Patreon, Open Collective, Donorbox).
* New: optional GA4 recommended events: begin_checkout when a payment page opens and purchase on the success marker, sent only with consent.
* Changed: before analytics consent the visit identifier stays in the open page only (no sessionStorage); consent is read from KOZ Consent Manager or, if absent, WP Consent API.
* Changed: abuse limits allow 60 new sessions and 600 events per IP address and hour (mobile networks share addresses) and write two counters instead of four per event, using the object cache when available.
* Changed: language versions are taken from KOZ Static Translate (kozstx_get_status) first; the former UA FREE function is only a fallback.
* Changed: new installations use neutral data-kozdonate-* attributes; data-uafree-* attributes keep working and existing settings are kept.
* Changed: the signed callback accepts optional amount and currency fields.
* Changed: payment providers and reports are separate classes (KOZDONATE_Providers, KOZDONATE_Insights).
* Added Ukrainian translations for the new admin strings.
* Removed PayPal from the developer support panel and readme and corrected the BTC and USDT TRC-20 developer wallet addresses (from 1.3.12).

= 1.3.12 =
* Removed PayPal from the developer support panel and corrected the BTC and USDT TRC-20 developer wallet addresses.

= 1.3.11 =
* Updated KOZ Suite documentation to include all eight current public plugins.
* Updated WordPress compatibility metadata for WordPress 7.1.
* No functional changes.

= 1.3.10 =
* Added privacy-safe instrumentation diagnostics to the existing JSON export.
* Reports tracking configuration, integration availability and funnel signals without exporting confirmation secrets or visitor identifiers.
* Does not change event collection or production write behavior.

= 1.3.9 =
* Fixed Plugin Check prepared-SQL validation for the three legacy-table migration queries without changing migration behavior or stored data.

= 1.3.8 =
* Fixed migration from the exact historical `uafree_donate_stats_*` option and table names.
* Existing legacy statistics are merged once into the current `kozdonate_*` tables without deleting the legacy source tables.
* Restored historical settings when the 1.3.5-1.3.7 migration created a default current option.
* Replaced the old standalone support footer with the current KOZ support fallback and duplicate-panel guard.

= 1.3.7 =
* Restored Donate Stats as a submenu under the existing KOZ Suite administration menu.
* Preserved the stable `koz-donate-stats` page slug and current plugin-specific identifiers.

= 1.3.6 =
* Restored the stable `koz-donate-stats` administration page slug for existing links and bookmarks.

= 1.3.5 =
* WordPress.org re-audit: all plugin-owned identifiers now use the unique `kozdonate` / `KOZDONATE` family.
* Existing settings and aggregate tables are migrated automatically from the previous identifiers.
* Updated integration with KOZ Copy Actions and KOZ Consent Manager canonical APIs/events.
* Removed shared Suite registry/runtime/support identifiers from this package.

= 1.3.4 =
* Added runtime localization for ten supported non-English WordPress locales with English fallback.
* Localized the administration interface, Suite screen, support panel and clipboard feedback.

= 1.3.2 =
* Corrected KOZ Suite status detection and navigation for active KOZ plugins.
* Legacy UA FREE packages are reported separately instead of appearing as active KOZ plugins.

= 1.3.0 =
* Rebranded the plugin as KOZ Donate Stats & Conversions with the new `koz-donate-stats` slug and text domain.
* Declared Tony Kozyriev / `ramirkz` as the independent developer and owner.
* Replaced inline shared support-panel CSS and JavaScript with properly enqueued assets.
* Separated developer support from donations to the UA FREE charitable foundation.
* Removed bundled PO and MO translation files for WordPress.org translation handling.
* Rewrote public documentation with functionality, use cases, privacy and ownership details.
* Preserved legacy internal data identifiers where required for migration compatibility.

= 1.2.10 =
* Last stable release under the former UA FREE public branding.

== Upgrade Notice ==

= 1.4.0 =
Adds confirmed LiqPay and PayPal donations, a funnel report with period comparison, automatic payment-service detection and storage-free tracking before consent. Existing settings are kept.

= 1.3.12 =
Developer support details updated: PayPal removed, BTC and USDT TRC-20 addresses corrected.

= 1.3.11 =
WordPress 7.1 compatibility metadata update; plugin behavior remains unchanged.

= 1.3.10 =
Adds a privacy-safe instrumentation diagnostic to JSON export for live validation.

= 1.3.9 =
Fixes WordPress.org prepared-SQL validation for legacy data migration.

= 1.3.8 =
Restores historical settings/statistics from the exact pre-rebrand identifiers and removes duplicate support UI.

= 1.3.7 =
Restores KOZ Suite menu placement while preserving existing settings, statistics and page links.
