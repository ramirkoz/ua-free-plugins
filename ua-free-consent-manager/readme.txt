=== UA FREE Consent Manager ===
Contributors: uafree
Donate link: https://uafree.org/plugins/support-development/
Tags: consent, privacy, cookies, analytics, advertising
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 0.1.7
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-first consent manager and local allowlist for optional WordPress scripts.

== Description ==

UA FREE Consent Manager provides:

* necessary consent always enabled;
* analytics, advertising and external-media consent disabled by default;
* a keyboard-accessible Ukrainian-first frontend banner;
* a versioned first-party cookie without personal identifiers;
* a cache-safe client-side loader for locally registered WordPress script handles;
* no persistent consent log, telemetry or remote configuration;
* UA FREE Suite Registry integration.

Registered optional handles are always removed from server-rendered output and are activated in the browser only after the first-party consent cookie is validated. This prevents a cached consented page from exposing optional scripts to a visitor without consent.

The plugin does not include GA4, Google Ads, Google Consent Mode v2 or external-media adapters in 0.1.2.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open the plugin settings or UA FREE Suite Control Center when configuration is required.

== Changelog ==

= 0.1.7 =
* Added a stable browser consent API for local plugins.
* Added canonical consent update events while preserving the legacy event name.
* Fixed interoperability with UA FREE Donate Stats & Conversions and duplicate browser initialization.

= 0.1.6 =
* Redesigned the shared UA FREE support panel.
* Moved the panel into the WordPress admin content area to prevent overlap with the core footer.
* Added compact wallet rows and accessible copy buttons.
* Added PayPal developer donations via kozyriev@uafree.org.
* Plugin-specific functionality is unchanged.

= 0.1.5 =
* Final stable packaging for repository publication and WordPress.org submission.
* Updated plugin metadata and WordPress compatibility information.
* No custom update checker or UA FREE usage telemetry was added.

== Upgrade Notice ==

= 0.1.7 =
Adds the browser consent contract required by local analytics and conversion integrations.

= 0.1.6 =
Shared admin support panel redesign; plugin-specific functionality is unchanged.

= 0.1.5 =
Final stable release prepared for repository and WordPress.org distribution.
