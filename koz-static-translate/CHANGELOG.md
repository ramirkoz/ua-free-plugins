# Changelog

## 0.9.17
- Updated WordPress compatibility metadata for WordPress 7.1.
- No functional changes.

## 0.9.16
- Fixed retired-language queue cleanup SQL for WordPress 6.0+ by preparing only language values and avoiding the WP 6.2-only `%i` identifier placeholder.
- No change to supported languages, Azure usage, translation memory or readiness behavior.

## 0.9.15
- Fixed WordPress prepared-SQL placeholder handling for retired-language queue cleanup.
- No change to the canonical language set or translation/readiness behavior.

## 0.9.14
- Finalized the supported public language set: EN, ZH-Hans, ES, AR, ID, PT, FR, JA, DE and HI; Russian remains forbidden.
- Preserves translation memory while stopping accidental PL/IT/CS/RO queue work from the rejected 0.9.12 candidate.
- Keeps incomplete translated routes `noindex, follow` with empty translation-dependent SEO metadata until readiness is complete.
- Exposes Azure usage by every billed language code, including inactive/legacy codes, so accidental spend is visible.
- Exports an optional multilingual contract for SEO consumers while remaining independent of KOZ SEO Core.

## 0.9.9
- Adds bounded transient render caching for translated public routes to avoid repeated same-site source rendering on every request.
- Reuses source HTML captured during successful source scans and caches translated HTML by source render hash and language.
- Keeps cache entries non-autoloaded via the WordPress transient API, bounded by size and short TTL; source changes invalidate the source snapshot.
- Leaves translation data, readiness, URLs, SEO rules and queue state unchanged.

## 0.9.8
- Removes the random cache-busting query from translated-route source rendering.
- Uses a bounded browser-like same-site source request so normal origin/CDN caching can participate.
- Leaves translation tables, queue state, readiness rules and route URLs unchanged.

## 0.9.7

- Fixes the remaining English About H1 language mismatch after the 0.9.6 heading fallback.
- Rewrites only the exact confirmed Ukrainian About H1 to `About the UA FREE Foundation` on `/en/about/`.
- Leaves Donate, reports, SEO metadata, canonical, Open Graph, robots and hreflang behavior unchanged.

## 0.9.6

- Adds a guarded heading-semantics fallback for the UA FREE English Donate and About routes.
- Promotes an existing translated H2/H3 to H1 only when the rendered English DOM has no H1.
- Leaves heading text, SEO metadata, canonical, Open Graph, robots and hreflang logic unchanged.

## 0.9.5

- Adds a guarded English metadata fallback for the UA FREE production routes when provisional translations still expose Ukrainian title/description text.
- Fixes `/en/` meta description and `/en/zvit/` title/description without changing canonical, `og:url`, robots or hreflang safety logic.
- Leaves already translated English metadata untouched.

## 0.9.4

- Rewrites `og:url` to the localized self URL on translated routes, matching the localized canonical.
- Keeps translated-route hreflang discovery read-only during frontend rendering.
- Preserves the 0.9.3 safety rule: only fully translated/indexable routes are advertised via `hreflang`; provisional noindex routes remain omitted.

## 0.9.3

- Integrates source-language pages with KOZ SEO Core hreflang output.
- Advertises only fully translated language routes to crawlers; monthly-limit provisional routes remain protected by `noindex` until complete.
- Uses read-only translation coverage checks when generating source-page hreflang links.

## 0.9.2

- Standardized plugin-owned identifiers under `kozstx / KOZSTX` and `ramirkz\kozstx`.
- Added automatic migration from historical `uafree_st_*` options and translation tables.
- Preserved public sitemap compatibility and legacy Azure configuration inputs.
- Normalized KOZ Suite navigation and support-panel fallback behavior.

## 0.9.1

- Protected JSON-LD and application/json script blocks from DOM reserialization.
- Rewrote localized structured-data URLs before script protection.
- Fixed malformed JSON-LD on translated routes when site names contain quotes.

## 0.9.0

- KOZ public rebrand and KOZ Suite integration.
- Ten runtime administration languages plus English fallback.
- Enqueued administration assets and shared support panel.
- Existing translation data, queue, cron hooks and language routes preserved.
- Bundled PO/MO files removed.

## 0.8.7

- Last stable UA FREE-branded release.
