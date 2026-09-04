## 2.1.4
- WordPress.org review remediation: enqueue frontend JS/CSS, preserve user control over legacy plugin activation, and fix settings sanitization callback.
- Requested WordPress.org permalink: `koz-404-guard`, matching the existing plugin text domain.
- Updated WordPress compatibility metadata for WordPress 7.1.
- No functional changes.

# KOZ 404 Guard & URL Intelligence — Changelog

## 2.1.3
- Prevents WordPress automatic canonical guessing from dropping a language-like URL namespace on real 404 requests.
- Keeps stale or mistyped translated routes such as `/pl/about/` as 404 instead of silently redirecting to `/about/`.
- Preserves explicit KOZ redirect rules and same-namespace canonical fixes.

## 2.1.2
- Classifies Cloudflare `/cdn-cgi/` and WordPress infrastructure 404s separately from content misses.
- Classifies legacy `?attachment_id=` 404s without persisting the raw attachment ID.
- Adds the privacy-safe request source to the diagnostics table/export and bumps the log schema to 4.
- Keeps system/infrastructure paths protected from automatic redirect and 410 rules.

## 2.1.1
- WordPress.org strict prefix re-audit: canonical `koz404 / KOZ404` identifiers.
- Migrates legacy `uafree_404_guard_*` settings, rules and diagnostic log without deleting old data.
- Plugin-specific runtime, suite fallback and support identifiers.


## 2.1.1

- Rebranded the plugin as KOZ 404 Guard & URL Intelligence.
- Added KOZ Suite status and navigation.
- Added runtime administration translations for Ukrainian, Chinese, Spanish, Arabic, Indonesian, Portuguese, French, Japanese, German and Hindi, with English fallback.
- Preserved legacy UA FREE option keys, redirect rules, 410 rules and privacy-safe log data.
- Replaced the old support footer with the shared KOZ support panel and external assets.
- Preserved the existing privacy, capture, redirect and export contracts.
