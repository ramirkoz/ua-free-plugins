# Changelog

## 2.1.22
- Updated WordPress.org `Tested up to` metadata to WordPress 7.1.

## 2.1.21
- Harden JSON-LD output for WordPress.org review by relying on script-safe default `wp_json_encode()` escaping.
- Remove `JSON_UNESCAPED_*` flags from generated JSON output paths while keeping valid JSON responses.
- Preserve SEO, sitemap, translation, AI Vision and saved-metadata behavior.

## 2.1.20
- Do not activate or deactivate any other plugin; legacy-package deactivation remains an explicit administrator action.
- Document the optional OpenAI service used by administrator-triggered AI Vision ALT analysis.
- Preserve SEO, sitemap, translator integration and metadata behavior from 2.1.19.

## 2.1.19
- Excludes the `pagelayer-template` technical post type from WordPress XML sitemaps and forces direct template views to `noindex, follow`.
- Removes the WordPress `users` sitemap provider when author archives are configured `noindex`, aligning sitemap output with the existing robots setting.
- Preserves normal posts, pages, public gallery post types and translator-managed routes.

## 2.1.18
- Finalized translator independence: SEO Core works normally with no translator installed.
- Accepts an optional provider-neutral multilingual contract through `kozseo_translation_contract`; KOZ Static Translate is only one possible provider.
- Does not invent language namespaces or force KOZ language settings when another translator manages multilingual routing/SEO.
- KOZ Static Translate integration remains automatic through filters for ready-only hreflang and translated sitemap discovery.

## 2.1.15
- Added browser-rendered accessibility audit for published pages.
- Checks same-site rendered H1, ALT, empty links, meta description and noindex state without database writes.
- Added client-side privacy-safe JSON export for rendered audit results.


## 2.1.14
- Strip WordPress block comments and JSON configuration fragments before building automatic descriptions.
- Use a clean page-title/site-name fallback for gallery pages that do not contain enough natural text.
- Preserve explicitly saved manual SEO descriptions.

## 2.1.13
- Keep AI Vision controls active after Apply and after transient connector readiness checks.
- Applying selected ALT candidates updates the page in place instead of forcing a reload.

## 2.1.12

- Fix Plugin Check escaping and sanitization findings in the AI Vision ALT workflow.
- Preserve editable ALT, approve/skip/re-analyze, and unprocessed-media queue behavior.


## 2.1.11
- Adds inline manual editing for every AI-generated ALT candidate before approval.
- Adds explicit approve/apply, skip, and per-image re-analysis controls.
- Adds a default “Empty ALT, not analyzed” queue plus an optional all-unprocessed queue for reviewing existing ALT values.
- Adds a cached pending counter so newly uploaded images without ALT are visible for periodic follow-up batches.
- Keeps prior Vision analysis state across plugin upgrades instead of re-queuing already reviewed images solely because the plugin version changed.
- Preserves existing WordPress ALT values unless replacement is explicitly enabled.

## 2.1.10
- Hardens AI Vision batches against intermittent `No models found ... text_generation` failures from dynamic provider/model discovery.
- Retries each image with OpenAI model preferences first, then OpenAI automatic model selection.
- Requests strict JSON in the prompt and validates it locally instead of requiring structured-output capability during model matching.
- Keeps analysis read-only and preserves the explicit review/apply gate for ALT changes.

## 2.1.9
- Moves AI Vision batch execution to an authenticated REST endpoint instead of admin-ajax.
- Fixes the false-negative AI readiness check observed in admin-ajax after the OpenAI Connector was already configured and detected.
- Leaves the actual vision prompt, review workflow, and explicit ALT apply behavior unchanged.

## 2.1.8

- Raises `Requires at least` to WordPress 7.0, matching the native AI Client functions used by the Vision ALT workflow.
- Removes the Plugin Check nonce/input/compatibility and database warnings reported for `class-kozseo-alt-manager.php`.
- Uses a lightweight attachment cursor instead of a `meta_query` to find the next image.
- Stores recent analyzed IDs and incremental Vision statistics in options instead of querying postmeta for every admin refresh.
- Clears AI analysis metadata through WordPress metadata APIs instead of direct SQL.
- Prefers `gpt-5.6-luna`, then `gpt-5.6-terra`, then `gpt-5.4-nano` for cost-controlled image analysis.

## 2.1.7

- Replaces the 2.1.6 metadata/title heuristic workflow with actual multimodal image analysis through the native WordPress 7.0 AI Client.
- Uses the official OpenAI connector configured under Settings → Connectors; KOZ SEO Core does not store or expose the API key.
- Processes images progressively in batches of 5/10/25/50, one server-side AI request at a time, to avoid long PHP requests.
- Stores AI candidates separately from WordPress ALT and never writes during analysis.
- Classifies results as content, decorative, or uncertain with high/medium/low confidence and a short reason.
- Adds thumbnail-based review and explicit selected-row apply; existing ALT is preserved unless replacement is explicitly enabled.
- Does not use filenames, attachment titles or URLs as evidence for AI ALT generation.

## 2.1.6

- Adds an explicit Media ALT dry-run scanner for up to 10,000 image attachments.
- Generates safe candidates only from existing attachment caption, description or clearly human-authored media titles.
- Keeps filename-only, generic and page-context-only cases in review instead of writing guessed ALT text.
- Never overwrites an existing `_wp_attachment_image_alt` value.
- Applies approved safe candidates in AJAX batches of 100 to reduce timeout risk.

## 2.1.5

- Resolves the requested public permalink before trusting front-page conditionals, preventing page-builder routes such as `/zvit/` from inheriting homepage SEO context.
- Keeps the real site root on the existing homepage metadata path and does not alter saved SEO fields.

## 2.1.4

- Resolves valid custom-rendered/page-builder permalinks to their published WordPress post before building SEO context, preventing fallback to homepage title/canonical/OG metadata.
- Uses the configured organization description as the clean homepage description fallback.

## 2.1.3

- Completed strict WordPress.org prefix re-audit with canonical `kozseo / KOZSEO` identifiers.
- Migrates legacy `uafree_seo_core_settings` into `kozseo_settings` without deleting the old option.
- Reads historical `uafree_*` post metadata as a fallback and writes new edits to canonical `kozseo_*` metadata.
- Preserves the legacy AI manifest route and pre-re-audit public filter hooks for compatibility.
- Replaced shared runtime, registry and support identifiers with plugin-specific implementations.

## 2.1.1

- Aligned public filter hooks with the plugin prefix accepted by WordPress Plugin Check.

## 2.1.0

- KOZ public rebrand and KOZ Suite integration.
- Ten runtime administration languages plus English fallback.
- KOZ AI manifest endpoint with legacy route compatibility.
- Existing settings and post metadata preserved.
- Bundled PO/MO files removed.

## 2.0.8

- Last stable UA FREE-branded release.
