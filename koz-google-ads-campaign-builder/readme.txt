=== KOZ Google Ads Campaign Builder ===
Contributors: ramirkz
Donate link: https://github.com/ramirkoz/ua-free-plugins#support-plugin-development
Tags: google ads, ad grants, campaigns, keywords, export
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.4.13
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Builds reviewable Google Ad Grants and standard Google Ads Editor import packages.

== Description ==

KOZ Google Ads Campaign Builder is an independently installable WordPress plugin built for practical production use. It keeps its main workflow local and under administrator control instead of requiring a paid suite core, hidden telemetry or a custom update service.

The plugin was originally developed for the UA FREE charitable foundation. The foundation website became the first production and testing environment and continues to use the plugin. The public KOZ edition preserves that operational experience while separating the software brand and ownership from the foundation.

The plugin is developed, owned and maintained by Tony Kozyriev (`ramirkz`). UA FREE does not own the plugin or its source code. Support for the developer and charitable donations to the foundation are presented as separate optional destinations.

= Main features =

* Campaign, ad group, keyword, negative keyword and asset package generation.
* Separate Google Ad Grants and standard Google Ads workflows.
* Reviewable CSV output for Google Ads Editor instead of direct account modification.
* Language, location and landing-page configuration checks.
* No Google Ads account credentials stored by the plugin.

= Example use cases =

* Prepare a nonprofit Search campaign package before importing it into Google Ads Editor.
* Generate repeatable campaign structures for review by a manager or agency.
* Keep campaign building separate from direct publication and billing access.

= Privacy =

The plugin builds local files and does not require direct access to a Google Ads account.

= Support =

* Support independent plugin development through PayPal or the cryptocurrency wallets listed in the administration support panel and project repository.
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

= Does the plugin require the whole KOZ suite? =

No. Every public KOZ plugin is independently installable unless its own description explicitly identifies an optional companion integration.

== Changelog ==

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

= 1.4.13 =
WordPress 7.1 compatibility metadata update; plugin behavior remains unchanged.

= 1.4.0 =
The plugin has a new public folder and slug. Deactivate the former UA FREE-branded package before activating this KOZ package, then verify the existing settings and workflow.
