=== KOZ Translate Diagnostics ===
Contributors: ramirkz
Donate link: https://github.com/ramirkoz/ua-free-plugins#support-plugin-development
Tags: translation, diagnostics, multilingual, site health, azure
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 0.4.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Read-only diagnostics, Site Health checks and privacy-safe reports for KOZ Static Translate.

== Description ==

KOZ Translate Diagnostics checks the operational state of KOZ Static Translate without changing translation data, options, cron events or caches.

The plugin first uses the translator public diagnostics API. Older installations are inspected through a read-only compatibility layer. Quick mode avoids expensive full-table comparisons; deep mode verifies source hashes. The privacy-safe JSON report is produced on the server in one deep scan and downloaded as a file.

For KOZ Static Translate 0.10.0 and later the plugin also shows the Azure Translator budget (month, today, by source), the background worker, page text waiting for translation, rejected segments and the translation sitemap, and adds two tests to Tools > Site Health.

The plugin was originally developed for the UA FREE charitable foundation website, which remains a production environment for the suite. The software is independently owned and maintained by Tony Kozyriev (`ramirkz`).

= Part of the KOZ Suite =

KOZ Translate Diagnostics is **one focused module of the broader KOZ Suite**, a family of independent WordPress plugins that share a common KOZ administration experience and visual identity.

Each KOZ plugin remains independently installable, so site owners can use only the modules they need or combine several modules into one toolkit.

Current public KOZ plugins:

* [KOZ SEO Core](https://wordpress.org/plugins/koz-seo-core/) — technical SEO, schema, sitemaps, AI discovery and OpenAI Vision image ALT.
* [KOZ Copy Actions](https://wordpress.org/plugins/koz-copy-actions/) — accessible copy-to-clipboard actions for selected WordPress content.
* [KOZ URL-Only Comment Spam](https://wordpress.org/plugins/koz-url-only-comment-spam/) — privacy-first local moderation for URL-only comment spam.
* [KOZ Static Translate](https://wordpress.org/plugins/koz-static-translate/) — AI-powered multilingual translation with Microsoft Azure AI Translator and static language routes.
* KOZ Translate Diagnostics — read-only health checks and privacy-safe diagnostic reports for KOZ Static Translate.
* [KOZ 404 Guard & URL Intelligence](https://wordpress.org/plugins/koz-404-guard/) — privacy-safe 404/410 diagnostics, controlled same-site redirects and URL intelligence.
* [KOZ Consent Manager](https://wordpress.org/plugins/koz-consent-manager/) — privacy-first visitor consent, optional script control and Google Consent Mode v2 synchronization.
* [KOZ Donate Stats & Conversions](https://wordpress.org/plugins/koz-donate-stats/) — local donation-journey analytics and consent-aware conversion events for WordPress.

= Main features =

* Detects KOZ Static Translate and the former UA FREE package during migration.
* Summarizes sources, segments, translations, translation memory, queues, usage, cron and recent errors.
* Provides quick and administrator-triggered deep scans.
* Exports a privacy-safe JSON report with redacted operational identifiers.
* Shows the Azure budget, background worker, page-text queue, rejected segments and translation sitemap of KOZ Static Translate 0.10.0+.
* Adds Site Health tests and a KOZ Translate section to Site Health info.
* Provides a short plain-text report for support chats.
* Makes no database writes and sends no external requests.
* Includes ten administration languages with English fallback.
* Integrates with KOZ Suite while supporting the current translator storage/API with read-only legacy fallback.

== Privacy ==

The plugin is read-only. Exported reports remove raw paths and table names, fingerprint operational identifiers and redact credentials, addresses and common payment identifiers.

== Installation ==

1. If the former UA FREE Translate Diagnostics package is active, deactivate it.
2. Upload and activate KOZ Translate Diagnostics.
3. Open KOZ Suite > Translation Diagnostics.
4. Verify translator detection, quick scan, one deep JSON export and Tools > Site Health.
5. Run Plugin Check.
6. Remove the former package only after the KOZ version works correctly.

== Frequently Asked Questions ==

= Does this plugin change translation data? =

No. It performs read-only diagnostics and does not run the translator queue, alter options, clear caches or change cron events.

= Does it use the Azure Translator budget? =

No. It reads the usage that KOZ Static Translate recorded and never contacts Azure.

= Does it work with the former translator package? =

Yes. It detects both KOZ Static Translate and the former UA FREE package during migration.

= Is this an official UA FREE foundation plugin? =

No. It originated from production work for the foundation website but is independently owned and maintained by Tony Kozyriev.

== Changelog ==

= 0.4.0 =
* Fixed: the plugin registers its admin screen, plugin action link and JSON export again. The 0.3.6 compliance build was missing the start-up call, so the plugin was active but showed nothing.
* New: Azure budget section for KOZ Static Translate 0.10.0 and later: monthly and daily usage, characters available now, which budget stopped translation, and usage by source for the last 30 days.
* New: background worker status (on/off, next run, overdue run, WP-Cron disabled), page text waiting for the worker by language, segments no longer sent after two rejections, and the translation sitemap rewrite rule.
* New: findings for a used-up monthly or daily budget, 80% and 95% of the monthly limit, a missing or overdue worker event, queued page text with the worker off, a leftover UA FREE cron event and a missing sitemap rule.
* New: Tools > Site Health tests (translator setup; Azure budget and background worker) and a KOZ Translate section in Site Health info.
* New: short plain-text report for support chats with a copy button.
* Changed: the deep JSON report is produced on the server in one deep scan and downloaded as a file; the page no longer embeds the whole report.
* Changed: translator settings and runtime keys added in KOZ Static Translate 0.10.0 are recognised and no longer counted as unknown keys; the daily_limit pause reason is kept in strict exports.
* Changed: the main class is split into report, budget, export, Site Health and admin screen classes (first step, no change to the report format).
* Changed: the former UA FREE diagnostics plugin is no longer deactivated automatically; a notice asks to deactivate it manually.
* Added Ukrainian translations for all new and previously untranslated admin strings.
* Removed PayPal from the developer support panel and corrected the BTC and USDT TRC-20 developer wallet addresses (from 0.3.7).

= 0.3.7 =
* Removed PayPal from the developer support panel and corrected the BTC and USDT TRC-20 developer wallet addresses.

= 0.3.6 =
* Updated KOZ Suite documentation to include all eight current public plugins.
* Updated WordPress compatibility metadata for WordPress 7.1.
* No functional changes.

= 0.3.5 =

* Prevents Cloudflare/origin timeout during JSON download by reusing the already-computed deep-scan result.
* Deep-scan JSON is downloaded directly by the browser; the download button no longer starts a second heavy report query.
* Legacy export links redirect to the deep scan instead of recomputing the report.
* Preserves the read-only contract: no database writes, report files, external requests, queue execution or robots changes.

= 0.3.4 =
* Fixes readiness to use the same current-translation plus translation-memory fallback as public KOZ Static Translate rendering.
* Queue status no longer determines page-level translation readiness.
* Critical title/meta/Open Graph/H1 checks now use effective translated text.
* Deep summaries expose effective-ready and memory-fallback counts for consistent missing estimates.
* Source-scan freshness remains a separate safety blocker; the plugin stays fully read-only.

= 0.3.3 =
* Adds deep page-level translation readiness diagnostics without changing robots directives or translation data.
* Detects missing title, meta description, Open Graph title/description and H1 translations when those source fields exist.
* Flags likely mixed-language critical fields using privacy-safe script/language signals, including source-language leakage on translated routes.
* Adds a per-language ready-for-indexing versus keep-noindex summary and actionable blocker table in the administrator deep scan.
* Keeps export identifiers privacy-safe and preserves the read-only/no-external-request contract.

= 0.3.2 =
* Added unique koztDiag / KOZTDIAG identifiers for plugin-owned runtime names.
* Added canonical KOZ Static Translate 0.9.2 API, option, table, cron and transient detection with legacy read fallback.
* Preserved read-only diagnostics, privacy-safe JSON export and KOZ Suite navigation.

= 0.3.1 =
* Added current KOZ Suite navigation and correct KOZ-versus-legacy status detection.
* Added ten runtime administration languages with English fallback.
* Added KOZ Static Translate detection while retaining compatibility with the former package.
* Replaced bundled PO/MO translations with source-only metadata and runtime dictionaries.
* Updated ownership, support and repository metadata.
* Preserved the read-only diagnostics contract and established translator storage identifiers.

= 0.2.14 =
* Last stable release under the former UA FREE public branding.

== Upgrade Notice ==

= 0.4.0 =
Restores the admin screen, adds the Azure budget and background worker view for KOZ Static Translate 0.10.0 and Site Health tests.

= 0.3.7 =
Developer support details updated: PayPal removed, BTC and USDT TRC-20 addresses corrected.

= 0.3.6 =
WordPress 7.1 compatibility metadata update; plugin behavior remains unchanged.

= 0.3.4 =
Corrects index-readiness calculations to follow effective public translation rendering, including translation-memory fallback.

= 0.3.3 =
Adds page-level translation/index-readiness diagnostics and mixed-language critical-field blockers while remaining read-only.

= 0.3.2 =
Updates diagnostics to the current KOZ Static Translate storage/API while keeping legacy read compatibility.

= 0.3.1 =
The public plugin folder and slug changed. Deactivate the former package first, then verify diagnostics before deleting it.
