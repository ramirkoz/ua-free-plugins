# 0.9.38

- WordPress.org T3 security/compliance remediation.
- Moves cleanup/reset backups out of the uploads tree into a verified non-public temporary directory and migrates legacy plugin backups when possible.
- Validates backup download paths against the secure directory.
- Adds JSON_HEX escaping to inline JavaScript configuration data and validates the global identifier.
- Translation behavior is otherwise unchanged.

# 0.9.37

- WordPress.org review compliance release.
- Moves plugin-owned frontend CSS/JavaScript to WordPress enqueue-managed assets.
- Restricts dynamic translation REST writes/Azure calls to administrators with `manage_options` and REST nonce authentication.
- Removes automatic deactivation of the legacy plugin.
- Documents public source and Microsoft Azure Translator service terms/privacy.
- Keeps translation readiness and automatic-worker safety behavior unchanged.

## 0.9.36

- Universal readiness rule: description metadata does not block a translated page from becoming ready.
- Image alt text is also non-blocking for readiness.
- Removes site-specific readiness text matching and uses generic consent/privacy component context instead.
- Existing safety mode remains unchanged; Azure usage stays zero for local reconcile actions.

## 0.9.35

- Adds one-click preparation of the next pending dynamically detected Priority Core source.
- Can register one missing internal WordPress page into inventory, then scan only that page.
- Uses local rendered HTML and Translation Memory only; Azure usage remains zero.
- No site-specific rules, bulk rebuilds or background loops.

## 0.9.34

- Adds read-only Priority Core discovery diagnostics: menu path, source match, scan status and queue state.
- No writes or external API calls.
- No change to batch processing semantics.

## 0.9.33

- Merges internal links from multiple active navigation sources instead of selecting only one menu set.
- Keeps only the richest unassigned classic-menu fallback to avoid footer/menu noise.
- External URLs are filtered automatically and duplicates are removed by exact path.
- No UA FREE-specific logic is present.

## 0.9.32

- Universal Priority Core discovery now supports builders/themes that keep the main classic menu unassigned to a WordPress menu location.
- Adds block-theme wp_navigation fallback.
- Still uses exact internal URL paths only and contains no UA FREE-specific rules.

## 0.9.31

- Removes UA FREE-specific Priority Core hardcoding.
- Detects Priority Core from WordPress home + the active primary/main navigation menu.
- Uses exact internal path matching only; no page-title fallback.
- Keeps reports, secondary core, Azure, scans, rebuilds, cron and automatic loops out of the batch.

## 0.9.30

- Introduces explicit Priority Core classification for the eight main navigation areas.
- Secondary and legacy core URLs are no longer processed by the Priority Core batch.
- Adds per-page missing source-segment count to batch output.
- Zero Azure calls, scans, rebuilds, cron jobs or automatic loops.

## 0.9.29

- Replaces source-ID ordering with stable editorial priority for core readiness batches.
- Pinned core pages run first, then other top-level core URLs, then nested core URLs.
- Reports remain excluded; batch size remains four pages per manual click.

## 0.9.28

- Adds manual bounded reconciliation for four core pages per click.
- Cursor advances within the current admin session so incomplete pages cannot trap the batch on the same records.
- Zero Azure calls, scans, rebuilds, cron jobs or automatic batch loops.

## 0.9.27

- Adds narrow non-blocking readiness rules for the two consent descriptions and UA FREE logo alt text identified by diagnostics.
- The one-page reconcile action updates only matching segment flags and queue readiness for the selected page.
- Zero Azure requests, scans, rebuilds or background worker activity.

## 0.9.26

- Adds read-only missing-segment diagnostics for one selected source page.
- No database writes, scans, rebuilds, background workers or Azure calls.
- Diagnostic output is capped at 25 rows.

## 0.9.25

- Plugin Check compliance fixes for the one-page readiness endpoint.
- Safety-mode behavior remains unchanged from 0.9.24.

## 0.9.24

- Adds targeted one-page Translation Memory readiness reconciliation.
- Zero Azure calls, zero source scans, zero queue rebuilds.
- Removes hidden maintenance from the admin screen and makes safety-mode status explicit.

## 0.9.23

- Production stability release.
- Activation is constant-time and does not scan or rebuild translation inventory.
- Automatic and manual background translation processing is temporarily disabled.
- Existing translated routes and stored data remain available.

## 0.9.19

- Keep the language switcher visible on provisional translated routes without advertising unfinished translations.

# Changelog

## 0.9.18
- The floating language switcher now lists only fully translated routes for the current source page and stays hidden when none are ready.
- Provisional translated routes no longer render the language switcher, so unfinished language URLs are not advertised through navigation.
- No changes to Azure budget limits, translation memory, queue contents or public URL structure.

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
