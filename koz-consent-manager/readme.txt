=== KOZ Consent Manager ===
Contributors: ramirkz
Donate link: https://github.com/ramirkoz/ua-free-plugins#support-plugin-development
Tags: consent, privacy, cookies, analytics, advertising
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 0.2.12
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-first visitor consent and a local allowlist for optional WordPress scripts.

== Description ==

KOZ Consent Manager is an independently installable WordPress plugin built for practical production use. It keeps its main workflow local and under administrator control instead of requiring a paid suite core, hidden telemetry or a custom update service.

The plugin was originally developed for the UA FREE charitable foundation. The foundation website became the first production and testing environment and continues to use the plugin. The public KOZ edition preserves that operational experience while separating the software brand and ownership from the foundation.

The plugin is developed, owned and maintained by Tony Kozyriev (`ramirkz`). UA FREE does not own the plugin or its source code. Support for the developer and charitable donations to the foundation are presented as separate optional destinations.

= Main features =

* Necessary consent always enabled.
* Analytics, advertising and external-media categories disabled by default.
* Keyboard-accessible consent banner.
* Versioned first-party consent cookie without personal identifiers.
* Cache-safe client-side activation of locally registered optional script handles.

= Example use cases =

* Delay analytics or advertising scripts until a visitor grants the matching consent.
* Keep consent handling local while using ordinary WordPress script registration.
* Provide a clear baseline consent layer for a privacy-conscious site.

= Privacy =

The plugin keeps no persistent consent log, telemetry or remote configuration and does not bundle analytics or advertising services.

= Support =

* Support independent plugin development through PayPal or the cryptocurrency wallets listed in the administration support panel and project repository.
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

= Is this an official UA FREE foundation plugin? =

No. The plugin was originally developed for and tested on the foundation website, but it is independently owned and maintained by Tony Kozyriev.

= Is a donation required? =

No. All included features are free and available under the GPL license.

= Does the plugin require the whole KOZ suite? =

No. Every public KOZ plugin is independently installable unless its own description explicitly identifies an optional companion integration.

== Changelog ==

= 0.2.12 =
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

= 0.2.12 =
WordPress 7.1 compatibility metadata update; plugin behavior remains unchanged.

= 0.2.7 =
Consent banner text is now stored separately by language. Existing text is preserved for the site language; review other language templates after updating.

= 0.2.3 =
This maintenance release fixes WordPress naming checks and updates the KOZ Suite menu icon.

= 0.2.1 =
The plugin has a new public folder and slug. Deactivate the former UA FREE-branded package before activating this KOZ package, then verify the existing settings and workflow.
