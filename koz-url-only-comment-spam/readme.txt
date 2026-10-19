=== KOZ URL-Only Comment Spam ===
Contributors: ramirkz
Donate link: https://github.com/ramirkoz/ua-free-plugins#support-plugin-development
Tags: comments, spam, moderation, url, privacy
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.2.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-first local moderation for link-only and low-effort comment spam, with honeypot and form-timing traps. No external service.

== Description ==

KOZ URL-Only Comment Spam is a focused moderation tool for a common low-effort spam pattern: comments containing only one or more links. It can send matching comments directly to spam or hold them for manual moderation while ordinary comments continue through the normal WordPress workflow.

The detector removes HTML and invisible spacing characters before checking the visible content. It can exempt logged-in users, trust same-site links and approved domains, and lets an administrator test a sample without saving that sample.

Version 1.2.0 adds more local rules, each with its own action (hold, spam, reject without saving or off):

* **Links with very little text**: an untrusted link plus a short phrase such as "Great post!". Links to your own site never trigger it.
* **Website field with very little text**: no links in the text, but the Website field points to an untrusted site (your own site is always trusted here).
* **Hidden trap field**: an invisible honeypot field that people never see and bots often fill in.
* **Submitted too fast**: the form was sent within a few seconds of the page being generated. Works with page caching.
* **Comment form token missing** (off by default): the comment did not come from the site's comment form.

Links are also found in HTML anchors, BBCode and Markdown, and simple disguises such as hxxp:// or example[.]com are undone before checking. Bare names such as example.com count as links only with a known top-level domain, so a comment like "Node.js" is not treated as a link.

Each handled comment gets its rule in a **Spam reason** column with a filter on the Comments screen. A dashboard widget and the settings page show daily counts per rule. Old spam can optionally be deleted automatically after a chosen number of days.

Privacy remains deliberately limited. The plugin stores no comment text, detected URL, IP address, email, referrer, cookie, fingerprint or user-agent value. It stores only aggregate counts per day and rule, a small summary of the latest match and the rule key on handled comments.

Use case: a public website receives repeated comments made entirely of promotional URLs. The plugin removes that noise before it reaches the normal review queue, without sending comment data to an external service.

Use case: a community site permits internal resource links but wants external URL-only comments reviewed. Trusted-domain settings allow internal destinations while suspicious external links are handled automatically.

The plugin was originally developed for the UA FREE charitable foundation website, which remains a production test environment. Development and ownership belong to Tony Kozyriev (`ramirkz`). Foundation donations and developer support are separate.

= Part of the KOZ Suite =

KOZ URL-Only Comment Spam is **one focused module of the broader KOZ Suite**, a family of independent WordPress plugins that share a common KOZ administration experience and visual identity.

Each KOZ plugin remains independently installable, so site owners can use only the modules they need or combine several modules into one toolkit.

Current public KOZ plugins:

* [KOZ SEO Core](https://wordpress.org/plugins/koz-seo-core/) — technical SEO, schema, sitemaps, AI discovery and OpenAI Vision image ALT.
* [KOZ Copy Actions](https://wordpress.org/plugins/koz-copy-actions/) — accessible copy-to-clipboard actions for selected WordPress content.
* KOZ URL-Only Comment Spam — privacy-first local moderation for URL-only comment spam.
* [KOZ Static Translate](https://wordpress.org/plugins/koz-static-translate/) — AI-powered multilingual translation with Microsoft Azure AI Translator and static language routes.
* [KOZ Translate Diagnostics](https://wordpress.org/plugins/koz-translate-diagnostics/) — read-only health checks and privacy-safe diagnostic reports for KOZ Static Translate.
* [KOZ 404 Guard & URL Intelligence](https://wordpress.org/plugins/koz-404-guard/) — privacy-safe 404/410 diagnostics, controlled same-site redirects and URL intelligence.
* [KOZ Consent Manager](https://wordpress.org/plugins/koz-consent-manager/) — privacy-first visitor consent, optional script control and Google Consent Mode v2 synchronization.
* [KOZ Donate Stats & Conversions](https://wordpress.org/plugins/koz-donate-stats/) — local donation-journey analytics and consent-aware conversion events for WordPress.
* [KOZ Site Bridge](https://wordpress.org/plugins/koz-site-bridge/) — secure read-only WordPress diagnostics, controlled same-site probes and authenticated REST automation.

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

= Do the bot traps work with page caching? =

Yes. The timing token contains only the time the page was generated and a signature made with your site's salts. An older cached page simply looks like a slow, human submission. Only implausibly fast submissions are flagged.

= Will the hidden field confuse screen-reader users? =

The trap field is moved off-screen, marked aria-hidden, removed from the keyboard order and labelled "Leave this field empty".

= What happens when a comment is rejected? =

With the "Reject without saving" action WordPress shows a short message asking the visitor to write a few words of their own, and the comment is not stored.

= Does it check WooCommerce reviews? =

Yes, product reviews are included by default. You can turn them off in Comment types.

= Can I test the detector without saving a comment? =

Yes. The administration screen includes a sample test for the content rules, with an optional Website field, that does not persist the sample text.

== Changelog ==

= 1.2.0 =
* New rules with their own action: links with very little text, Website field with very little text, hidden honeypot field, submitted too fast and missing comment form token.
* New action "Reject without saving" for content and honeypot rules.
* Links are now found in HTML anchor targets, BBCode and Markdown, and hxxp:// or example[.]com disguises are undone before checking.
* Bare domain names count only with a known top-level domain, avoiding false positives such as "Node.js".
* Spam reason column and filter on the Comments screen (rule key only, no personal data).
* Daily statistics per rule on the settings page and in a dashboard widget.
* Optional automatic deletion of spam older than a chosen number of days (off by default).
* WooCommerce product reviews are checked by default; comment types are configurable.
* Legacy UA FREE option migration now runs only on activation and in the admin area instead of on every request.
* Removed PayPal from the developer support panel and corrected the BTC and USDT TRC-20 developer wallet addresses.

= 1.1.11 =
* Updated KOZ Suite documentation to include all eight current public plugins.
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
* Aligned KOZ hook names with the plugin namespace prefix used by Plugin Check.
* Removed the global uninstall variable while preserving conditional data deletion.

= 1.1.0 =
* Rebranded the plugin under the independent KOZ suite.
* Added the KOZ Suite menu and shared support panel.
* Added administration localization for ten languages plus English fallback.
* Preserved legacy settings, counters, filters and event hooks.
* Added KOZ-prefixed filters and event hooks.
* Removed bundled PO and MO files for WordPress.org compatibility.

== Upgrade Notice ==

= 1.2.0 =
Adds honeypot, timing and low-text rules. New rules hold comments for moderation by default and the honeypot marks spam; review the settings after updating.

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
