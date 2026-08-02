=== UA FREE 404 Guard & URL Intelligence ===
Contributors: uafree
Donate link: https://uafree.org/plugins/support-development/
Tags: 404, redirects, broken links, 410, privacy
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 2.0.8
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-safe 404/410 diagnostics, controlled same-site redirects and URL intelligence.

== Description ==

UA FREE 404 Guard & URL Intelligence was originally created for a charitable foundation website and rebuilt as a universal WordPress tool.

Features:

* administrator-started, ten-minute sampled 404/410 diagnostics without IP addresses, raw paths, query names, query values, raw User-Agent strings or raw referrers;
* salted path, query-key and external-referrer fingerprints;
* fixed 1-in-128 request sampling and a global maximum of one grouped log write every 30 seconds;
* optional lightweight bot 404 pages;
* exact same-origin redirects with duplicate-source and full cycle detection;
* boundary-aware 410 path-prefix rules and strict query-rule parsing;
* canonical shared UA FREE Plugin Suite Registry;
* quick environment inventory without content scanning;
* explicit nonce-protected internal-link deep scan with byte and link budgets;
* privacy-safe JSON export without raw paths, option names, option values, rule values, post IDs, titles, URLs or content;
* explicit legacy-log sanitization;
* no direct external requests or telemetry.

No redirect or 410 rule is created automatically. Uninstalling the plugin does not delete data.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open the plugin settings or UA FREE Suite Control Center when configuration is required.

== Changelog ==

= 2.0.8 =
* Redesigned the shared UA FREE support panel.
* Moved the panel into the WordPress admin content area to prevent overlap with the core footer.
* Added compact wallet rows and accessible copy buttons.
* Added PayPal developer donations via kozyriev@uafree.org.
* Plugin-specific functionality is unchanged.

= 2.0.7 =
* Standardized the canonical product name as UA FREE 404 Guard & URL Intelligence across WordPress, the Suite catalog and release metadata.
* No logging, redirect or URL-analysis behaviour changed.

= 2.0.6 =
* Final stable packaging for repository publication and WordPress.org submission.
* Updated plugin metadata and WordPress compatibility information.
* No custom update checker or UA FREE usage telemetry was added.

== Upgrade Notice ==

= 2.0.8 =
Shared admin support panel redesign; plugin-specific functionality is unchanged.

= 2.0.7 =
Canonical product-name synchronization only; functionality is unchanged.

= 2.0.6 =
Final stable release prepared for repository and WordPress.org distribution.
