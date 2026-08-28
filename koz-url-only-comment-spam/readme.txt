=== KOZ URL-Only Comment Spam ===
Contributors: ramirkz
Donate link: https://github.com/ramirkoz/ua-free-plugins#support-plugin-development
Tags: comments, spam, moderation, url, privacy
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.1.11
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-first moderation for comments whose visible content consists only of URLs.

== Description ==

KOZ URL-Only Comment Spam is a focused moderation tool for a common low-effort spam pattern: comments containing only one or more links. It can send matching comments directly to spam or hold them for manual moderation while ordinary comments continue through the normal WordPress workflow.

The detector removes HTML and invisible spacing characters before checking the visible content. It can exempt logged-in users, trust same-site links and approved domains, and lets an administrator test a sample without saving that sample.

Privacy remains deliberately limited. The plugin stores no comment text, detected URL, IP address, email, referrer, cookie, fingerprint or user-agent value. It stores only aggregate counts and a small summary of the latest match.

Use case: a public website receives repeated comments made entirely of promotional URLs. The plugin removes that noise before it reaches the normal review queue, without sending comment data to an external service.

Use case: a community site permits internal resource links but wants external URL-only comments reviewed. Trusted-domain settings allow internal destinations while suspicious external links are handled automatically.

The plugin was originally developed for the UA FREE charitable foundation website, which remains a production test environment. Development and ownership belong to Tony Kozyriev (`ramirkz`). Foundation donations and developer support are separate.

= Part of the KOZ Suite =

KOZ URL-Only Comment Spam is one focused module of the KOZ Suite, a family of independent WordPress plugins with a shared KOZ administration experience and visual identity.

Each KOZ plugin can be installed independently or combined with other KOZ Suite modules.

Current public KOZ plugins:

* [KOZ SEO Core](https://wordpress.org/plugins/koz-seo-core/) — technical SEO, schema, sitemaps, AI discovery and OpenAI Vision image ALT.
* [KOZ Copy Actions](https://wordpress.org/plugins/koz-copy-actions/) — accessible copy-to-clipboard actions for selected WordPress content.
* KOZ URL-Only Comment Spam — privacy-first local moderation for URL-only comment spam.
* [KOZ Static Translate](https://wordpress.org/plugins/koz-static-translate/) — AI-powered multilingual translation with Microsoft Azure Translator and static language routes.

Additional KOZ Suite modules are used in production and may be published separately as they are prepared for public distribution.

Features:

* Detect one or more URL-only values after HTML and invisible spacing are removed.
* Preserve comments that contain human-readable text.
* Ignore pingbacks, trackbacks, administrators and comment moderators.
* Optionally exempt all logged-in users.
* Optionally trust same-site links and selected domains.
* Choose between spam and moderation queue.
* Test samples without saving them.
* Administration interface in Ukrainian, Chinese, Spanish, Arabic, Indonesian, Portuguese, French, Japanese, German and Hindi; English fallback.
* No external requests, telemetry, cookies, cron jobs or custom database tables.

== Installation ==

1. Deactivate the legacy UA FREE URL-Only Comment Spam plugin if it is installed.
2. Upload the KOZ ZIP through Plugins > Add New > Upload Plugin.
3. Activate the plugin.
4. Open KOZ Suite > URL Comment Spam and review the settings.

Existing UA FREE settings and aggregate counters are preserved automatically.

== Frequently Asked Questions ==

= Does the plugin send comment content to an external service? =

No. Detection runs locally in WordPress. The plugin does not send comment text, URLs, IP addresses, email addresses, referrers or user-agent values to an external service.

= Will ordinary comments be marked as spam? =

The detector targets comments whose visible content consists only of one or more URLs after HTML and invisible spacing are removed. Comments containing ordinary readable text continue through the normal WordPress workflow.

= Can I allow internal or trusted links? =

Yes. You can trust same-site links and add approved domains in the plugin settings.

= Can I test the detector without saving a comment? =

Yes. The administration screen includes a sample test that does not persist the sample text.

== Changelog ==

= 1.1.11 =
* Updated KOZ Suite documentation to include KOZ Static Translate as the fourth public plugin.
* Updated WordPress compatibility metadata for WordPress 7.1.
* No changes to spam detection, moderation settings, counters or privacy behavior.

= 1.1.10 =
* Publish the current production-tested package as the first public SVN release after WordPress.org approval.
* Keep moderation rules, stored settings, aggregate counters and privacy behavior unchanged.
* Refresh public release metadata for WordPress.org.

= 1.1.9 =
* Fixed WordPress.org Stable tag metadata to match the plugin version.
* No changes to spam detection, settings, counters or support-panel behavior.

= 1.1.8 =
* Prevent duplicate support blocks when the shared KOZ Suite panel is active.
* Add the About the foundation button to standalone support fallback.


= 1.1.7 =
* Fixed duplicate KOZ support panel rendering in WordPress admin by ensuring a single panel initialization.
* No changes to spam detection, stored settings, counters or compatibility behavior.

= 1.1.6 =
* Standardized plugin-owned identifiers under the kozurlspam / KOZURLSPAM prefix and ramirkz\\kozurlspam namespace.
* Replaced shared support/runtime identifiers with plugin-specific equivalents.
* Migrates existing UA FREE settings into the canonical KOZ option names without exposing copied comment content.


= 1.1.5 =
* Unified the namespace, global constants and KOZ hooks under the exact KOZURLOnlyCommentSpam prefix.
* Preserved existing settings and UA FREE compatibility hooks.

= 1.1.2 =
* Aligned the KOZ hook names with the plugin namespace prefix used by Plugin Check.
* Removed the global uninstall variable while preserving conditional data deletion.

= 1.1.0 =
* Rebranded the plugin under the independent KOZ suite.
* Added the KOZ Suite menu and shared support panel.
* Added administration localization for ten languages plus English fallback.
* Preserved legacy settings, counters, filters and event hooks.
* Added KOZ-prefixed filters and event hooks.
* Removed bundled PO and MO files for WordPress.org compatibility.

== Upgrade Notice ==

= 1.1.11 =
WordPress 7.1 compatibility metadata update; moderation behavior and stored data remain unchanged.

= 1.1.10 =
Public WordPress.org release of the current production-tested package; moderation behavior and stored data remain unchanged.

= 1.1.7 =
Admin UI fix: removes the duplicate support block without changing moderation behavior or stored data.

= 1.1.6 =
WordPress.org manual-review compliance update with plugin-specific identifiers and preserved settings migration.

= 1.1.5 =
Unified namespace, constants and custom-hook prefix compliance update.

= 1.1.2 =
Prefixing compliance update for custom hooks and uninstall cleanup.

= 1.1.1 =

* Updated hook and uninstall variable prefixes for WordPress Plugin Check compliance.

= 1.1.0 =
KOZ rebrand and multilingual administration update with preserved moderation settings.
