# Changelog

## 0.5.6
- Updated WordPress compatibility metadata for WordPress 7.1.
- No functional or API behavior changes.

## 0.5.5
- Replaced direct PHP `fopen` / `fclose` log access with the WordPress Filesystem API.
- Preserved the fixed-path read-only error-log diagnostic and privacy redaction.
- No API-key, route, or existing diagnostic behavior changes.

## 0.5.4
- Added authenticated read-only admin error-log diagnostics.
- Reads only fixed local WordPress/PHP log candidates; no caller-supplied filesystem paths.
- Redacts credentials, cookies, email addresses and local filesystem prefixes before returning recent lines.
- Preserved all existing endpoints and API-key compatibility.

## 0.5.3

- Added safe rendered-page audit for public same-site frontend paths.
- Added sitemap inventory and batched sitemap audit with concrete per-URL issues.
- Added rendered SEO signals: title, description, canonical, robots, hreflang, headings, image alt state, link counts, schema types and document language.
- Expanded HTTP probes from a fixed system allowlist to safe public frontend routes while continuing to block WordPress admin, REST, login, executable and private system paths.
- Added non-DOM HTML parsing fallback for hosts without the PHP DOM extension.
- Preserved read-only operation, existing API keys, rate limits and legacy API compatibility.

## 0.5.2

- Fixed admin submenu registration under the dynamically detected KOZ Suite parent.
- Preserved the existing `admin.php?page=koz-site-bridge` settings URL.
- Removed the duplicate Site Bridge suite-catalog entry.

## 0.5.1

- WordPress.org strict prefix re-audit: canonical `kozbridge / KOZBRIDGE` identifiers.
- Existing API-key settings migrate automatically from historical option names; old values are not deleted during upgrade.
- Added canonical `kozbridge/v1` REST routes while preserving `koz-site-bridge/v1` and `uafree-bridge/v1` compatibility.
- Plugin-specific runtime, suite fallback, admin assets, nonces, error identifiers and support UI identifiers.
- Preserved the read-only, privacy-safe and same-origin safety contracts.

## 0.5.0

- Rebranded as KOZ Site Bridge.
- Added KOZ Suite integration and multilingual administration.
- Added canonical `koz-site-bridge/v1` REST routes and `X-KOZ-Key` authentication.
- Preserved legacy API keys, `uafree-bridge/v1` routes and `X-UAFree-Key` during migration.
- Replaced site-specific safe-probe paths with a generic allowlist.
- Added enqueued admin CSS/JavaScript and the shared KOZ support panel.
- Preserved the read-only, privacy-safe and same-origin safety contracts.
