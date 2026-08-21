=== KOZ Translate Diagnostics ===
Contributors: ramirkz
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=kozyriev%40uafree.org&item_name=Support+KOZ+WordPress+plugin+development&currency_code=USD
Tags: translation, diagnostics, multilingual, azure, privacy
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 0.3.6
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Read-only diagnostics and privacy-safe reports for KOZ Static Translate.

== Description ==

KOZ Translate Diagnostics checks the operational state of KOZ Static Translate without changing translation data, options, cron events or caches.

The plugin first uses the translator public diagnostics API. Older installations are inspected through a read-only compatibility layer. Quick mode avoids expensive full-table comparisons; deep mode verifies source hashes and prepares a privacy-safe JSON payload from the same scan result for immediate browser-side download.

The plugin was originally developed for the UA FREE charitable foundation website, which remains a production environment for the suite. The software is independently owned and maintained by Tony Kozyriev (`ramirkz`).

= Main features =

* Detects KOZ Static Translate and the former UA FREE package during migration.
* Summarizes sources, segments, translations, translation memory, queues, usage, cron and recent errors.
* Provides quick and administrator-triggered deep scans.
* Exports a privacy-safe JSON report with redacted operational identifiers without repeating the deep scan during download.
* Makes no database writes and sends no external requests.
* Includes ten administration languages with English fallback.
* Integrates with KOZ Suite while supporting the current translator storage/API with read-only legacy fallback.

== Privacy ==

The plugin is read-only. Exported reports remove raw paths and table names, fingerprint operational identifiers and redact credentials, addresses and common payment identifiers.

== Installation ==

1. Deactivate the former UA FREE Translate Diagnostics package, but keep it installed until verification is complete.
2. Upload and activate KOZ Translate Diagnostics.
3. Open KOZ Suite > Translate Diagnostics.
4. Verify translator detection, quick scan and one deep JSON export.
5. Run Plugin Check.
6. Remove the former package only after the KOZ version works correctly.

== Frequently Asked Questions ==

= Does this plugin change translation data? =

No. It performs read-only diagnostics and does not run the translator queue, alter options, clear caches or change cron events.

= Does it work with the former translator package? =

Yes. It detects both KOZ Static Translate and the former UA FREE package during migration.

= Is this an official UA FREE foundation plugin? =

No. It originated from production work for the foundation website but is independently owned and maintained by Tony Kozyriev.

== Changelog ==

= 0.3.6 =
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
