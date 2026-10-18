# KOZ 404 Guard & URL Intelligence 2.2.0: changes

**Plugin:** KOZ 404 Guard & URL Intelligence. It is published on wordpress.org as `koz-404-guard` by author `ramirkz` (Tony Kozyriev).

**Previous version:** 2.1.4 in the catalog. 2.1.5 only changed the support panel.

**Base for this release:** the catalog 2.1.4. The build installed on uafree.org was the same code; this was verified by SHA-256 of the PHP files.

**Tested with:** WordPress 7.1.3, PHP 8.4.26, KOZ Static Translate 0.10.0.

## Why this release

In 2.1.x the plugin was installed on uafree.org but gave nothing useful:

- **Logging was almost always off.** It ran only during a ten-minute window that an administrator had to start by hand. Inside that window it sampled one request in 128 and wrote at most once every 30 seconds. As a result, the log on uafree.org was empty.
- **Even a captured row was useless.** It held only a salted hash of the path, so the administrator could not see which address failed.
- **Non-Latin addresses were destroyed.** All paths went through `sanitize_text_field()`, which strips percent-encoded octets. Cyrillic addresses became dashes: `/bg/звіт-на-25-05-2022-…` turned into `/bg/--25-05-2022-93----`.

## 1. Support panel

Carried over from 2.1.5:

- PayPal removed.
- BTC: `bc1q4dn8e7sz2866g7qplqtshh98j54tvuau5ghuuk`
- ETH/USDC: `0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229`
- USDT TRC-20: `TYSgyk7K3XB4WPHprf5w8ZodFAfxFfDdbF`

## 2. Visible paths in the log; the export stays fingerprint-only

New setting **"Show paths"** (`log_paths`), on by default. The log stores:

- the normalised path;
- query parameter **names** (never values);
- the same-site **linking page** (referrer path, only when the referrer is this site).

What is never stored:

- IP address;
- User-Agent;
- query values;
- external referrer (only a host fingerprint is kept).

The JSON export contains counters and salted fingerprints only. The privacy contract moves to version 4.

Turning "Show paths" off empties the `path`, `referrer_path` and `query_keys` columns (`update_option_` hook → `KOZ404_Log::forget_paths()`). After that only fingerprints remain, which matches the 2.1.x behaviour.

## 3. Continuous log in its own table

New table `{prefix}koz404_log`:

- Columns: `row_hash` (unique), status, kind, source, path, `path_fingerprint`, `query_keys`, `query_key_count`, `referrer_scope`, `referrer_path`, `referrer_host_fingerprint`, `rule_id`, hits, `first_seen`, `last_seen`, ignored.
- Indexes: `last_seen`, (status, hits), `rule_id`.

How it works:

- **One query per hit:** `INSERT … ON DUPLICATE KEY UPDATE hits = hits + 1`. With a persistent object cache, writes for the same row are throttled to once per 10 seconds.
- **Cleanup:** the daily cron `koz404_cleanup` deletes rows by age (setting, default 90 days) and by size (default 2,000 rows).
- **Migration** (`maybe_upgrade`, DB version 2):
  - creates the table;
  - moves the old `koz404_log` option into it (fingerprints only), then removes the option;
  - removes `koz404_capture` and `koz404_capture_lock`.
- The capture window and the sampling are gone.
- **Redirect hits are logged too**, with `rule_id`. This gives per-rule hit counts and the time of the last hit.

## 4. Actions in the 404 log

- Every row has these buttons:
  - **Redirect**: opens the redirect form with the source and a suggested target filled in.
  - **410**: creates an exact-path 410 rule. Existing 404 rows for that path are removed (`resolve_path`).
  - **Ignore / Restore**.
  - **Delete**.
- **Suggested target:** the last path segment is matched as a slug against published content of public post types (an exact match first, then a prefix match). A KOZ Static Translate language prefix is kept.
- **Filter views:**
  - open 404 (with count);
  - reached from this site (with count);
  - 410;
  - redirected;
  - all.

  The log can also be filtered by visitors or bots, searched by path, sorted by most hits or most recent, and can show ignored rows. 50 rows per page.

## 5. Redirects on address changes

New class `KOZ404_Permalink_Watch`:

- **How a change is detected:** `pre_post_update` stores the old path of a published entry of a public post type (not attachments). `post_updated` then compares it with the new one.
- **Setting `permalink_watch`:**
  - `suggest` (default): the old → new pair goes into the "Suggested after address changes" list, with **Create redirect** and **Dismiss** buttons.
  - `auto`: a 301 is created right away.
  - `off`: nothing happens.
- **When content moves back to an earlier address:** an automatic (origin `permalink`) redirect away from the new address is removed, so it cannot hide live content.

## 6. Rules

New class `KOZ404_Rules`. Rules are still stored in the options `koz404_redirects` and `koz404_gone_rules`, so existing rules carry over.

Redirect fields:

- `type`: `exact` or `prefix`;
- `preserve_query`;
- `enabled`;
- `origin`: manual, log, permalink or import;
- `created`.

What rules can do:

- **Prefix rule:** `/old/a/b` → target + `/a/b`.
- **Keep query string:** appends the original query to the target.
- **Management:** rules can be edited and turned on or off. Hits and the last hit come from the log. 410 rules can also be edited and turned on or off.
- **Validation:**
  - protected paths are rejected;
  - external targets are rejected;
  - duplicates are rejected, including a percent-encoded copy of a Cyrillic source;
  - cycles between exact rules are rejected;
  - a prefix rule whose target lies inside its own source is rejected.
- **CSV import and export** with columns source, target, status, type, preserve_query, enabled. Invalid, duplicate and looping rows are skipped.

**Performance:** the autoloaded option `koz404_rules_index` holds only the enabled rules, already prepared for matching. It is rebuilt on every change, and lazily on the first request after an update. The settings are autoloaded too. Without rules, a front-end request makes **no** extra queries; before, it made up to 3.

**Targets** follow the permalink trailing-slash setting (`user_trailingslashit`), which avoids a second canonical redirect.

## 7. Lightweight 404 for missing files

Paths with static-file extensions now get a plain 404 for every visitor, without the theme. Setting `static_404`, on by default. The extension list:

- images: jpg, jpeg, png, gif, webp, avif, svg, ico, bmp;
- code: css, js, mjs, map;
- fonts: woff, woff2, ttf, otf, eot;
- media: mp4, webm, mp3, ogg, wav;
- documents and archives: pdf, zip, gz, rar, 7z.

It can be changed with the `koz404_static_extensions` filter. The response:

- `404 Not Found`, `text/plain`, `Cache-Control: public, max-age=300`;
- log source `static_404`.

On uafree.org a missing image used to cost **110 KB** of HTML from a full theme render; now the response is **14 bytes**.

## 8. Language guard by real languages

- **When KOZ Static Translate is active**, a language prefix is any slug from `KOZSTX_Static_Translate::languages()`.
- **Otherwise**, only two-letter codes and codes with a region count (`en`, `zh-cn`). Three-letter words such as `/faq/` and `/app/` no longer block WordPress canonical guessing.

## 9. Internal link check without false positives

`KOZ404_Environment_Scanner::route_status()` treats the following as existing:

- the home page;
- `/page/N`, feeds, `/amp`;
- date archives;
- posts and pages;
- terms (category base, tag base, rewrite slugs of public taxonomies);
- authors;
- post type archives;
- KOZ Static Translate language routes.

Addresses that a rule handles are shown as "redirect" or "410" instead of "missing". Each link shows how many 404 hits it had in the log, plus a **Redirect** button.

## 10. Site Health and dashboard

- **Test `koz404_internal_404`:** lists the top 5 addresses with 404 reached from this site in the last 30 days, with the linking page. Status is `recommended` when there are any. If the log is off, the test recommends turning it on.
- **Site Health > Info:** a "KOZ 404 Guard" section with version, request handling, the log, open 404, reached from this site, redirect rules and 410 rules.
- **Dashboard widget** "404 in the last 7 days": the top 8 addresses.

## 11. Architecture

The 1066-line class is split into:

| Class | Responsibility |
|---|---|
| `KOZ404_Guard` | settings, helpers, path normalisation, privacy, migration, `public_status()` |
| `KOZ404_Rules` | rule storage, validation, index, matching, CSV, export |
| `KOZ404_Log` | table, recording, queries, cleanup, export |
| `KOZ404_Request` | request handling, 404 and 410 responses, canonical guard, data layer |
| `KOZ404_Permalink_Watch` | redirects on address changes |
| `KOZ404_Site_Health` | Site Health test, Info section, dashboard widget |
| `KOZ404_Admin` | tabs: 404 log, Redirects, 410 rules, Settings, Tools |

Compatibility:

- `KOZ404_Guard::redirects()` and `gone_rules()` remain as wrappers.
- The `koz404_public_status` filter keeps working. It gains `logging_enabled`, `open_404` and `internal_404`; `log_schema` is now 5.

## 12. Fixes

- **Cyrillic and other non-Latin addresses.** In `request_uri()` and `normalise_path()`, valid UTF-8 percent-encoding is decoded. Segments are cleaned without removing letters of other scripts. Admin path fields are read without `sanitize_text_field()`: only control characters and invalid UTF-8 are removed. The log now shows, for example, `/az/звіт-на-30-11-2023-645-день-війни-офіційна-версія`.
- **410 rules by query key or key=value work on the home page** (e.g. `?pagelayer-template=`). Only system routes are excluded. Before, `/` counted as a protected path, so these rules never matched there.
- **Bot markers:** added curl, wget, python-requests, go-http-client, okhttp and scrapy.

## 13. Translations and documentation

- `uk.json`: 131 new admin strings. The visitor-facing strings (the lightweight 404 and 410 pages) already existed in all 10 languages; the plain 404 for files is not translated.
- `readme.txt` was rewritten. It had no feature description before. It now has:
  - a full description of what the plugin does;
  - a privacy section;
  - an installation section;
  - a FAQ with 5 questions;
  - new tags;
  - a changelog and upgrade notice for 2.2.0.
- `CHANGELOG.md`, `README.md` (safety model) and the `.pot` version are updated.

## 14. Testing on uafree.org

**Installation and checks**

- Installed by upload with "Replace current with uploaded" (2.1.4 → 2.2.0). The table and migration ran on the first admin load; there were no PHP errors.
- Plugin Check: "Checks complete. No errors found." The first run gave one warning, `ReplacementsWrongNumber`, about an array of replacements; it was fixed with an ignore comment.

**Anonymous visitor**

- Missing page: 404 with the theme. The log has the path, query names (`id, utm_source`) and the linking page `/about-us`.
- Missing `.png`: plain 404, 14 bytes.

**Rules created through the admin forms, then requested as an anonymous visitor**

- exact `/koz404-test-old` → `/`: works;
- prefix `/koz404-test-section/a/b/` → `/koz404-test-dest/a/b/`: works;
- Cyrillic exact `/koz404-тест-старе/` → `/` (302): works;
- the percent-encoded copy of the same source: rejected as `duplicate_redirect`;
- keep query string: `/koz404-test-q/?utm_source=t&x=1` → `/?utm_source=t&x=1`;
- cycle a→b, b→a: rejected (`redirect_loop`);
- protected path `/wp-admin/x`: rejected;
- external target: rejected;
- 410 exact path: HTTP 410;
- 410 query pair on the home page: `?koz404probe=1` → 410, `?koz404probe=2` → 200;
- Cyrillic 404: recorded correctly.

**Admin actions**

- Rule hits are counted (1 each) and the last hit time is shown.
- From the log: the "Redirect" button fills in the source; "410" creates the rule and removes the 404 rows for that path; "Ignore" hides the row from open 404.

**CSV and link check**

- CSV export is correct. An import of 4 rows added 2 rules (one of them disabled) and skipped 2 (a duplicate and `/wp-admin`).
- Internal link check: 200 entries, 292 links, 0 false positives, about 1.2 s.

**Site Health and dashboard**

- The Site Health test lists internal 404s with the linking page.
- The Info section shows all fields.
- The dashboard widget shows the top 404s.

**Real traffic and cleanup**

- Within about 10 minutes the log recorded 20 real 404s. Most were old daily reports under language prefixes that no longer exist: `/bs/`, `/vi/`, `/el/`, `/hu/`, `/az/`, `/fa/`.
- The test data was removed: 7 redirects, 3 410 rules and 11 test log rows. Real rows were kept.

**Not tested live**

- Redirects on address changes. A test would have meant publishing and editing a public entry on the production site, which KOZ Static Translate could have queued for paid translation. The logic is covered by the code review only.
