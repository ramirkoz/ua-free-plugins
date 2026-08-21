# Changelog

## 0.3.6
- Updated WordPress compatibility metadata for WordPress 7.1.
- No functional changes.

## 0.3.5

- Prevents Cloudflare/origin timeout during JSON download by reusing the already-computed deep-scan result.
- Deep-scan JSON is downloaded directly by the browser; the download button no longer starts a second heavy report query.
- Legacy export links redirect to the deep scan instead of recomputing the report.
- Preserves the read-only contract: no database writes, report files, external requests, queue execution or robots changes.

## 0.3.4

- Fixes page-level readiness to match KOZ Static Translate effective rendering: current ready translation rows first, translation-memory fallback second.
- Readiness no longer treats queue state as translation completeness.
- Critical title/meta/OG/H1 checks now evaluate the effective translated text actually available to public rendering.
- Deep language summaries now expose effective ready-segment and memory-fallback counts so missing estimates are not distorted by raw translation-table row counts.
- Preserves source-scan freshness as a separate blocker when the source inventory itself is not ready.
- Remains read-only with no external requests, queue execution or robots changes.

## 0.3.3

- Adds page-level deep translation readiness based on current source hashes and ready translation rows.
- Checks translated document title, meta description, Open Graph title/description and H1 when present in the source.
- Flags likely source-language leakage and mixed-language critical fields with script-aware heuristics.
- Adds administrator summaries for ready-for-indexing versus keep-noindex pages plus actionable blockers.
- Preserves the read-only contract, performs no external requests and redacts post/source identifiers from strict JSON exports.

## 0.3.2

- Unique `koztdiag / KOZTDIAG` plugin-owned identifiers.
- Current KOZ Static Translate 0.9.2 API, option, table, cron and transient diagnostics.
- Legacy UA FREE translator data remains readable as a compatibility fallback.
- Read-only and privacy-safe export behavior preserved.

## 0.3.1

- KOZ public rebrand and current KOZ Suite integration.
- Ten runtime administration languages plus English fallback.
- KOZ and former UA FREE translator detection during migration.
- Read-only diagnostic and privacy boundaries preserved.
- Bundled PO/MO files removed.

## 0.2.14

- Last stable UA FREE-branded release.
