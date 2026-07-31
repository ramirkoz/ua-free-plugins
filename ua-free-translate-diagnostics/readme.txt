=== UA FREE Translate Diagnostics ===
Contributors: uafree
Donate link: https://uafree.org/plugins/support-development/
Tags: translation, diagnostics, multilingual, azure, privacy
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 0.2.13
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Read-only diagnostics for UA FREE Static Translate with privacy-safe reports.

== Description ==

Version-option diagnostics use a strict version grammar. Arbitrary text, tokens, credentials, paths, URLs and whitespace-padded values are never exported as versions.

UA FREE Translate Diagnostics checks the operational state of UA FREE Static Translate without changing translation data, options, cron events or caches.

The plugin was originally created to diagnose a multilingual charitable foundation website and was rebuilt as a universal WordPress tool.

Features:

* Uses the translator public diagnostics API when available.
* Automatically falls back to a read-only database compatibility scan for older versions.
* Opens in a lighter quick mode that avoids the translation-to-segment hash join.
* Runs stale-hash verification only after an explicit administrator action or JSON export.
* Detects configured and historically used languages.
* Summarizes sources, segments, translations, translation memory, queue states, usage, cron and recent errors.
* Reports migration freeze and runtime pause reasons when the translator exposes them.
* Exports a privacy-safe JSON report.
* Uses salted fingerprints for the site host, database table names and source paths.
* Redacts Azure credentials, email addresses, IBANs, payment-card-like numbers and common cryptocurrency addresses.
* Provides Ukrainian and English administration interfaces.
* Shows the other available UA FREE Plugin Suite components.
* Does not create tables, options or scheduled events.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open the plugin settings or UA FREE Suite Control Center when configuration is required.

== Changelog ==

= 0.2.13 =
* Resolved Plugin Check findings for read-only database diagnostics.
* Added prepared identifier handling and documented intentional live read-only queries.
* Added translator comments and WordPress-safe JSON export.
* Removed manual translation loading and normalized compatibility metadata.
* Redesigned the shared UA FREE support block.

= 0.2.12 =
* Final stable packaging for repository publication and WordPress.org submission.
* Updated plugin metadata and WordPress compatibility information.
* No custom update checker or UA FREE usage telemetry was added.

== Upgrade Notice ==

= 0.2.13 =
Plugin Check compatibility and shared support-block update.
