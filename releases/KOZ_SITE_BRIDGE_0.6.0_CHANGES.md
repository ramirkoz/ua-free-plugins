# KOZ Site Bridge 0.6.0 — changes

**Plugin:** KOZ Site Bridge (wordpress.org slug `koz-site-bridge`, author `ramirkz`, Tony Kozyriev).

**Base:** 0.5.7. The owner's ZIP and the catalogue release had the same code; only the readme differed. The build on uafree.org matched the ZIP.

**Tested with:** WordPress 7.1.3, PHP 8.4, KOZ 404 Guard 2.2.0, KOZ Static Translate 0.10.0, KOZ Consent Manager 0.3.0, KOZ Donate Stats 1.4.0.

## Starting point

**Problems found:**
- `/404-log` read the option `uafree_404_guard_log`. KOZ 404 Guard 2.2.0 moved its log to its own table, so the endpoint returned nothing.
- Only `/wp-sitemap.xml` was read. The 413 language URLs in `/koz-translations-sitemap.xml`, which robots.txt lists, were never inventoried or audited.
- There was one API key, with access to every endpoint, including the PHP error log.
- Endpoints could not be tried from the admin without the key.
- There were no WordPress Abilities API / MCP integration, no security-header, cache or consent checks, and no Site Health test.
- One class had 2,551 lines.
- The support panel listed PayPal and had wrong BTC/USDT addresses.

## Changes

### 1. Support panel
- PayPal removed.
- BTC `bc1q4dn8e7sz2866g7qplqtshh98j54tvuau5ghuuk`
- ETH/USDC `0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229`
- USDT TRC-20 `TYSgyk7K3XB4WPHprf5w8ZodFAfxFfDdbF`

### 2. 404 log
`/404-log` reads `ramirkz\koz404\KOZ404_Log::query()`. It returns, for each row:
- status, kind, source;
- path (when 404 Guard stores paths) and path fingerprint;
- query keys (no values);
- hits, first seen, last seen;
- referrer scope and same-site referrer path.

Rows are sorted by hits and limited to `limit`. The old option is still read as a fallback.

### 3. Sitemaps from robots.txt
- **Sources:** `/wp-sitemap.xml`, plus every same-site `Sitemap:` line in robots.txt.
- **Allowed files:** any same-site `.xml` whose name contains "sitemap", outside system paths.
- **Limit:** raised from 1,000 to 3,000 URLs.
- **New fields in `/sitemap`:** `sources` (URLs per sitemap) and `languages` (URLs per Static Translate language).
- `/rendered-audit` covers the same URLs.
- `/diagnostics` also checks the robots.txt sitemaps and reports any that do not return 200.

### 4. API keys with scopes (`KOZBRIDGE_Auth`)
- **Up to 10 keys.** Each key has:
  - a label;
  - scopes: basic, content, audit, logs, error_log;
  - the creation date and the last-used day (no IP address);
  - an optional expiry (30, 90 or 365 days).
- **Storage:** keys are stored as HMAC-SHA256 digests (`v2:`), and the plain key is shown once.
- **Scope checks:** each route checks its scope. A key without the scope gets 403 `kozbridge_scope`, and an expired key gets 401.
- **New keys:** error_log is unchecked by default.
- **The pre-0.6.0 key** is shown as "Legacy key" with every scope. It was not changed.
- **Rate limit:** still 120 requests per hour per key.

### 5. Abilities API / MCP (WordPress 6.9+)
- **Category:** `koz-site-bridge`.
- **Abilities:**
  - `kozbridge/site-overview`
  - `kozbridge/diagnostics`
  - `kozbridge/page-audit`
  - `kozbridge/sitemap-audit`
  - `kozbridge/404-log`
  - `kozbridge/plugins`
- **Access:** `manage_options` only.
- **Annotations:** readonly, non-destructive, idempotent; `show_in_rest` and `mcp.public` are set, so the MCP Adapter can expose them.
- **Defaults:** input defaults from the schema are applied.
- **Off switch:** a setting can turn them off.

### 6. "Try it" console
- **Where:** in the admin page.
- **How it works:** it runs any endpoint through admin-ajax (`manage_options` + nonce) without an API key and shows the HTTP status, duration and JSON.
- **Parameters:** it uses the same REST argument sanitizing and validation as the REST API (`KOZBRIDGE_Bridge::run()`).

### 7. New diagnostics
- **`/security-headers`:**
  - checks HSTS, X-Content-Type-Options, X-Frame-Options (or CSP frame-ancestors), Referrer-Policy, Permissions-Policy and CSP;
  - returns `issues[]` with a severity for each.
- **`/performance`:** fetches the page twice and reports the duration, size, CDN cache status and page-cache signature.
- **`/consent-check`:** reports:
  - Google, Microsoft, Meta, Hotjar and TikTok tags, and what controls each;
  - whether the Consent Mode default is set;
  - whether the KOZ Consent Manager banner is present;
  - embeds blocked and embeds loading before consent.
- **`/suite`:** now has a `status` section with:
  - 404 Guard and Donate Stats public status;
  - Static Translate status with this month's Azure usage;
  - Consent Manager settings and whether WP Consent API is present.
- **Probes:** now return security and cache headers.

### 8. OpenAPI 3.1
- **New operations:** `/security-headers`, `/performance`, `/consent-check`.
- **New response schemas:** `Issue`, `IssuesResponse`, `Log404Response`, `SitemapResponse`.
- **Responses:** a 403 response was added.

### 9. Site Health
- **Test "KOZ Site Bridge access":** HTTPS, keys older than 180 days without an expiry, and keys with error_log access.
- **Debug information:** version, number of keys, scopes in use and whether abilities are registered.

### 10. Structure
- **New classes:**
  - `KOZBRIDGE_Auth`
  - `KOZBRIDGE_Admin`
  - `KOZBRIDGE_Abilities`
  - `KOZBRIDGE_Site_Health`
- **`KOZBRIDGE_Bridge`:** keeps routes, endpoints and OpenAPI. `tr()`, `routes()` and `run()` are now public.
- **uninstall.php:** also removes `kozbridge_keys`, `kozbridge_settings` and `kozbridge_api_key_last_used`.

### 11. Translations and documentation
- 47 new admin strings in Ukrainian. The plugin has no visitor-facing text.
- **readme:** new short description and tags, a feature list, 3 FAQ entries, changelog and upgrade notice. CHANGELOG.md updated.

## Testing on uafree.org

**Installation**
- Installed twice by upload ("Replace current with uploaded"). The second build fixed two problems found in testing: `limit` on `/404-log` and the ability input defaults.
- No new PHP errors: the error_log was last changed on 2026-10-01.

**API key**
- The "Legacy key" (created 2026-08-11) is listed with every scope. It was not changed, rotated or used.
- `/ping` without a key returns 401.

**"Try it" console: all 17 endpoints returned 200, and an unknown endpoint returned 404**

| Endpoint | Result |
|---|---|
| `/404-log` | source `koz-404-guard`, 618 rows; the top rows are 410 rules for `/is` (136 hits) and `/pl` (127) |
| `/sitemap` | 2,225 URLs: `/wp-sitemap.xml` 1,812 and `/koz-translations-sitemap.xml` 413. By language: en 43, fr 43, id 40, others 41 each |
| `/rendered-audit` | `total_pages` is calculated over all 2,225 URLs |
| `/diagnostics` | `/koz-translations-sitemap.xml` returns 200; no issues |
| `/security-headers` | HSTS, nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy and CSP are all missing. The browser saw the same response headers through Cloudflare |
| `/consent-check` | banner present, Consent Mode default set, gtag/GTM/Ads controlled by Consent Mode, 6 embeds blocked, 0 open, no issues |
| `/performance` | about 200 ms, WP Super Cache, Cloudflare DYNAMIC |
| `/suite` status | 404 Guard 2.2.0 (21 gone rules); Donate Stats 1.4.0; Static Translate 0.10.0 with 10 languages; Consent Manager policy 2 with Consent Mode, media blocking and WP Consent API |

**Static Translate's Azure usage this month:** 1,995,193 of 2,000,000 characters (99.8%).

**Abilities**
- All 6 are listed in `/wp-abilities/v1/abilities` with `readonly: true`.
- Runs via GET with the admin cookie returned 200.
- `page-audit` without input defaults to `/`.
- A path under `/wp-admin/` is rejected with 400.
- POST to a read-only ability returns 405, and an anonymous request returns 401.

**Admin checks**
- Site Health: "KOZ Site Bridge access needs attention", because the Legacy key can read the PHP error log. This is the intended recommendation.
- Plugin Check: "Checks complete. No errors found."

**Not tested live**
- A real request with a scoped key: no key was created, because the existing key must not be touched. Scope enforcement is the same `permission` callback the existing key passes through.
- An MCP client through the MCP Adapter. The adapter is not installed on uafree.org.
