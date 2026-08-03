=== UA FREE Donate Stats & Conversions ===
Contributors: uafree
Donate link: https://uafree.org/plugins/support-development/
Tags: donations, analytics, conversions, fundraising, privacy
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 1.2.10
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-conscious local donation journey analytics and conversion tracking.

== Description ==

Privacy-conscious local donation journey analytics and conversion tracking.

The plugin can be used independently or together with other UA FREE plugins.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open the plugin settings or UA FREE Suite Control Center when configuration is required.

== Changelog ==

= 1.2.10 =
* Added automatic tracking for active UA FREE Static Translate routes linked to selected source pages.
* Shows generated language routes in the page-selection list after saving.
* Preserves manual local paths as a separate advanced option.

= 1.2.10 =
* Fixed Consent Manager browser API compatibility.
* Sends one Google Ads dataLayer event per payment-provider opening.
* Prevents page views and internal journey events from triggering the outbound-click conversion.
* Added page-level duplicate runtime initialization protection.

= 1.2.8 =
* Redesigned the shared UA FREE support panel.
* Moved the panel into the WordPress admin content area to prevent overlap with the core footer.
* Added compact wallet rows and accessible copy buttons.
* Added PayPal developer donations via kozyriev@uafree.org.
* Plugin-specific functionality is unchanged.

= 1.2.7 =
* Kept the canonical product name UA FREE Donate Stats & Conversions untranslated in the WordPress plugin list.
* Ukrainian translations inside the plugin remain available.

= 1.2.6 =
* Fixed the final Plugin Check warnings for read-only admin routing and prefetch request headers.

= 1.2.5 =
* Prepared every dynamic table identifier with the WordPress %i placeholder.
* Added explicit handling for legitimate direct queries against plugin-owned aggregate tables.
* Sanitized request metadata, clarified read-only admin routing, and removed discouraged textdomain loading.
* Fixed CSV streaming and translator comments; cleaned the WordPress.org package.

= 1.2.4 =
* Final stable packaging for repository publication and WordPress.org submission.
* Updated plugin metadata and WordPress compatibility information.
* No custom update checker or UA FREE usage telemetry was added.

== Upgrade Notice ==

= 1.2.10 =
Adds automatic multilingual donation-route discovery and tracking.

= 1.2.10 =
Fixes consent-gated Google Ads conversion events and prevents overcounting.

= 1.2.8 =
Shared admin support panel redesign; plugin-specific functionality is unchanged.

= 1.2.7 =
Canonical product-name synchronization only; tracking behaviour and stored data are unchanged.

= 1.2.6 =
Final Plugin Check cleanup for request handling.

= 1.2.5 =
Security and Plugin Check compatibility cleanup without changing stored statistics.

= 1.2.4 =
Final stable release prepared for repository and WordPress.org distribution.
