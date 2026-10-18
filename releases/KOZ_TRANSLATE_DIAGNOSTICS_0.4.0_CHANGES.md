# KOZ Translate Diagnostics 0.4.0: changes

Plugin: KOZ Translate Diagnostics (wordpress.org slug `koz-translate-diagnostics`, author `ramirkz`, Tony Kozyriev).
Previous public version: 0.3.6 (catalog); 0.3.7 only changed the support panel (PayPal removed, wallets fixed).
Base for 0.4.0: the build installed on uafree.org, labelled 0.3.6. It differs from the catalog build: the JS is moved to an enqueued asset and the legacy plugin is no longer auto-deactivated. Byte-for-byte reconstruction was verified by SHA-256.
Tested with: KOZ Static Translate 0.10.0, WordPress 7.1.3, PHP 8.4.26.

The plugin is still strictly read-only:
- no database writes;
- no cron changes;
- no Azure or other external requests;
- nothing is stored on the server.

## 1. Critical fix (P0)

- **The plugin did nothing on uafree.org.** The main file of the installed 0.3.6 build was missing `KOZTDIAG_Translate_Diagnostics::init()`. As a result there was no admin menu item, no "Diagnostics" action link and no export, even though the plugin was active.
  - 0.4.0 restores the call.
  - The former auto-deactivation of `ua-free-translate-diagnostics` is not restored. Instead, an admin notice asks to deactivate that plugin manually.
- The support panel from 0.3.7 is included:
  - PayPal removed;
  - BTC `bc1q4dn8e7sz2866g7qplqtshh98j54tvuau5ghuuk`;
  - ETH/USDC `0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229`;
  - USDT TRC-20 `TYSgyk7K3XB4WPHprf5w8ZodFAfxFfDdbF`.

## 2. Support for KOZ Static Translate 0.10.0 data (P1)

New read-only module `includes/class-koztdiag-budget.php` (`KOZTDIAG_Budget`). It uses `KOZSTX_Quota` when that class exists, and otherwise reads the options directly. Its `snapshot()` (cached per request) contains:

- **monthly**: limit, used, remaining, percent, reset time (from `KOZSTX_Quota::current_usage()`).
- **daily**: budget (`daily_limit`, or monthly / 30 when unset), used today (UTC, from `kozstx_usage_daily`), remaining, reset time.
- **available_now**, plus **blocked_by** (`monthly` / `daily` / empty), from `KOZSTX_Quota::available()` / `blocked_reason()`.
- **usage_by_source_30_days**: queue, background, dynamic and test, from `kozstx_usage_daily`.
- **worker**:
  - `background_worker` setting;
  - whether `kozstx_cron` is scheduled and when it runs next;
  - overdue flag (more than 15 minutes late);
  - `DISABLE_WP_CRON`;
  - leftover `uafree_st_auto_cron` events.
- **residual_queue**: page text waiting for the worker (`kozstx_residual_queue`), total and by language.
- **rejected_segments**: tracked count, and the count blocked after 2 rejections (`kozstx_rejected_segments`).
- **sitemap**:
  - whether `^koz-translations-sitemap\.xml$` and the legacy `uafree-…` rule are in `rewrite_rules`;
  - whether pretty permalinks are on.

  This is a local check with no HTTP request.
- **settings**: only flags and numbers. That is `daily_limit`, `reserve_chars`, `background_worker`, the count of `protected_terms` and whether `report_title_prefix` is set. The term text itself is not shown.

New findings (`KOZTDIAG_Budget::findings()`, each with good/recommended/critical status):

- monthly budget used up, or 80% / 95% of the monthly limit reached;
- daily budget used up;
- page text waiting while the worker is off;
- page text queue full (3,000 items, the translator's own cap);
- worker enabled but its event is missing;
- worker run overdue (with a separate text when WP-Cron is disabled);
- leftover UA FREE cron event;
- segments no longer sent after two rejections;
- translation sitemap rule missing (translator 0.10.0+ only).

Other report changes:

- Settings keys added in STX 0.10.0 (`daily_limit`, `background_worker`, `protected_terms`, `report_title_prefix`, `switcher_position`, …) no longer count as `unknown_key_count`.
- Known runtime keys are also not counted as unknown:
  - the report cursors (`report_cursor_*`, `report_bootstrap_complete`), which are known but not exported;
  - `last_cache_purge_at`, `last_log_cleanup_at`, `last_recent_reports_at`.
- Pause reason `daily_limit`:
  - gets its own hint;
  - is kept as-is in strict exports (before, only `monthly_limit` was kept).
- When the budget module reports that the monthly limit is used up, the older duplicate hint "paused because the monthly limit was reached" is dropped.
- The report gets a new `budget` key. `public_status()` gets new keys, all additive (contract version stays 2):
  - `budget_blocked_by`;
  - `budget_monthly_percent`;
  - `worker_enabled`;
  - `page_text_waiting`.
- Snapshot keys are named so the existing privacy filters keep them: `blocked_by`, `event`. The sitemap path is not stored in the report, because the filter redacts values that look like paths.

## 3. Site Health integration (P2)

`includes/class-koztdiag-site-health.php` (`KOZTDIAG_Site_Health`):

- **Direct tests** (`site_status_tests`):
  - `koztdiag_translator`: checks that the translator is active, the Azure key is set, there is no migration freeze and target languages are selected;
  - `koztdiag_budget`: registered only when `KOZSTX_Quota` exists. Its status is the worst status among the budget findings, and its description shows month and today usage.
- **Site Health > Info** (`debug_information`): new section `koz-translate` with:
  - versions;
  - source and target languages;
  - Azure key yes/no;
  - number of sources;
  - monthly and daily usage;
  - worker state;
  - page text waiting;
  - blocked segments;
  - sitemap rule.

  Count values in the `debug` field are strings, because WordPress prints a numeric 0 as `undefined` in the copied text.

## 4. Architecture: the 1650-line class split (first step)

| File | Responsibility |
|---|---|
| `class-koztdiag-translate-diagnostics.php` | Collects the report, privacy filters, `public_status()`, `init()` wires the modules |
| `class-koztdiag-budget.php` | STX 0.10 budget, worker, queues, sitemap, findings |
| `class-koztdiag-export.php` | JSON download (admin-post) and the short text report |
| `class-koztdiag-site-health.php` | Site Health tests and Info section |
| `class-koztdiag-admin-page.php` | Menu, action link, assets, admin screen |

New public helpers on the main class:
- `settings_snapshot()`;
- `runtime_snapshot()`;
- `is_migration_pause_reason()`;
- `translated_route_url()`;
- `readiness_blocker_label()`;
- `privacy_safe_report()`;
- the public constants `PAGE_SLUG`, `EXPORT_ACTION`, `DEEP_SCAN_QUERY`, `DEEP_SCAN_NONCE`.

The report format is unchanged apart from the new `budget` key.

## 5. Server-side JSON export (P2)

- Before: the deep-scan page embedded the whole strict JSON in a hidden `<textarea>`, and JS created a Blob for download.
- Now: `admin-post.php?action=koztdiag_export` (nonce plus `manage_options`) runs one deep scan, adds headers and sends the file, without storing anything on the server. The headers are:
  - `Content-Type: application/json`;
  - `Content-Disposition: attachment`;
  - `nosniff`;
  - `nocache`.
- The download button is available on both the quick and deep pages. The deep page no longer carries the large payload.
- On uafree.org (1,656 sources) the deep export took about 80 s and produced a 162 KB file. That is close to Cloudflare's 100-second limit, so it is worth watching as the site grows.

## 6. Short report for support (P2)

- `KOZTDIAG_Export::summary_text()` produces a plain-text summary in a read-only textarea, with a "Copy short report" button. The copy uses `navigator.clipboard` with an `execCommand` fallback, and the status shows in an `aria-live` region.
- The summary includes:
  - versions, WordPress/PHP/locale;
  - translator active and API available;
  - languages;
  - Azure key yes/no;
  - pause state;
  - number of sources;
  - monthly and daily budget;
  - usage by source;
  - worker state;
  - queue and blocked segments;
  - sitemap rule;
  - all findings.
- It contains no content, addresses, keys or identifiers.
- `&nbsp;` from `number_format_i18n()` (thousands separator in the uk locale) is converted to a normal space.
- `assets/koz-translate-diagnostics-admin.js` now handles only the copy button. The strings come from `wp_localize_script`.

## 7. Translations and documentation

- `assets/i18n/uk.json`: about 104 new strings. These are all new strings plus readiness and blocker strings that had no Ukrainian translation before. The plugin is admin-only, so nothing was added for visitors.
- readme.txt:
  - Stable tag 0.4.0;
  - tags include "site health";
  - updated description and features;
  - new FAQ "Does it use the Azure Translator budget? No";
  - updated installation steps;
  - changelog and upgrade notice for 0.4.0.
- CHANGELOG.md: 0.4.0 entry. The .pot Project-Id-Version is set to 0.4.0.

## 8. Testing on uafree.org

- Installed by upload with "Replace current with uploaded" (0.3.6 → 0.4.0).
- The "Translation Diagnostics" menu and the "Діагностика" action link appear again.
- Quick scan, budget section with real data:
  - this month 1,995,193 of 2,000,000 (99.8%), resets on 2026-11-01;
  - today 0 of 66,667 (automatic);
  - available now: 0;
  - stopped by: monthly limit;
  - worker on, next run shown;
  - page text waiting: 3,000 (AR 372, DE 282, EN 476 …);
  - rejected segments: 0;
  - sitemap `/koz-translations-sitemap.xml` registered;
  - do-not-translate terms: 25.
- Findings:
  - monthly budget used up;
  - page text queue full;
  - temporary pause;
  - quick-scan note.

  No duplicates.
- Short report: correct; the copy button shows "Скопійовано.".
- Deep JSON export:
  - HTTP 200, `application/json`, `attachment; filename="koz-translate-diagnostics-YYYYMMDD-HHMMSS.json"`, about 80 s, 162 KB;
  - `diagnostic_mode: deep`;
  - the `budget` block is present;
  - no domain, table names, emails or wallets in the file;
  - unknown settings/runtime keys: 1/3 before the allowlist fix, fixed in the final build.
- Site Health:
  - "KOZ Static Translate is active and configured": good;
  - "Azure Translator budget or background worker needs attention": critical, correct because the month is used up and the queue is full;
  - the Info section shows all fields, and the copied text has correct values (`rejected_segments: 0`).
- Deep-scan page (`?koztdiag_deep=1`): HTTP 200, no PHP errors, all 8 sections render, the short report shows `(deep)`. It took about 124 s with a cold database cache and about 14 s with a warm one. The deep queries cost the same as in 0.3.x.
- Plugin Check (Tools > Plugin Check): "Checks complete. No errors found."
- Bugs found during testing and fixed in the same version:
  - a fatal TypeError on the deep page: the readiness template reused the variable name `$summary`, so the short report was overwritten by an array before `esc_textarea()`. The variable is now `$short_report`;
  - the privacy filter redacted the sitemap path;
  - `&nbsp;` appeared in the short report;
  - the monthly-limit hint was duplicated;
  - known translator keys were counted as unknown;
  - 0 was printed as `undefined` in the Site Health copy text.
- No Azure quota was used.
