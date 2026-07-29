=== UA FREE URL-Only Comment Spam ===
Contributors: uafree
Donate link: https://uafree.org/plugins/support-development/
Tags: comments, spam, moderation, url, privacy
Requires at least: 6.0
Tested up to: 7.0.2
Stable tag: 1.0.5
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-first moderation for comments that contain only one or more URLs.

== Description ==

UA FREE URL-Only Comment Spam is a small moderation plugin. It can mark URL-only comments as spam or hold them for moderation.

It does not store comment text, detected URLs, IP addresses, referrers, cookies, fingerprints, or user-agent strings. Only an aggregate counter and a privacy-safe summary of the latest detection are stored.

Features:

* Detect one or more URL-only values after HTML and invisible spacing are removed.
* Preserve ordinary comments that also contain human-readable text.
* Ignore pingbacks, trackbacks, administrators, and comment moderators.
* Optionally exempt all logged-in users.
* Optionally trust same-site links and selected domains.
* Choose between spam and moderation queue.
* Test samples in the administration screen without saving them.
* English and Ukrainian administration interface.
* No external requests, telemetry, cookies, cron jobs, or custom database tables.

The plugin was originally developed to solve a practical need of a charitable foundation website and was generalized for other WordPress installations.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open the plugin settings or UA FREE Suite Control Center when configuration is required.

== Changelog ==

= 1.0.5 =
* Final stable packaging for repository publication and WordPress.org submission.
* Updated plugin metadata and WordPress compatibility information.
* No custom update checker or UA FREE usage telemetry was added.

== Upgrade Notice ==

= 1.0.5 =
Final stable release prepared for repository and WordPress.org distribution.
