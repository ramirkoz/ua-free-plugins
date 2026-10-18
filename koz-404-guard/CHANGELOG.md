## 2.2.0

- Fixed: 410 rules for a query key or key=value now also apply to the home page (for example ?pagelayer-template=…); only WordPress system routes are excluded.
- Fixed: addresses in Cyrillic and other non-Latin scripts were cut to dashes (/звіт-на/ became /--/) because percent-encoded characters were stripped. Paths are now decoded, so rules and the log match and show the real address.
- New: continuous 404 log in its own table, grouped by path. One database query per hit, daily cleanup by age (default 90 days) and size (default 2,000 rows). The ten-minute sampled capture window is removed.
- New: "Show paths" (on by default): the log shows the requested path, query parameter names and the page of this site that linked to it. IP addresses, User-Agents, query values and external referrers are still never stored; the JSON export still contains fingerprints only. Turning the option off removes stored paths.
- New: actions in the log: create a redirect (with a suggested target found by slug), add a 410 rule, ignore, delete. Filters: open 404, reached from this site, 410, redirected, visitors or bots, search, sort.
- New: redirects when a published entry changes its address (slug, parent or date): suggested for approval by default, or created automatically, or off.
- New: redirect rules can be edited and turned off, show hits and last hit, support path-prefix rules (moving a whole section) and keeping the query string, and can be imported and exported as CSV. 410 rules can be edited and turned off.
- New: missing static files (images, CSS, JS, fonts, media) get a plain 404 without rendering the theme.
- New: Site Health test for internal links that lead to 404, a KOZ 404 Guard section in Site Health info and a dashboard widget with the top 404 addresses of the last 7 days.
- Changed: the language guard for canonical redirects uses the languages of KOZ Static Translate when it is active; otherwise only two-letter codes (and codes with a region) count, so /faq/ or /app/ are no longer treated as languages.
- Changed: the internal link check no longer reports archives, categories, tags, authors, KOZ Static Translate language routes and addresses handled by a rule as broken, and shows how many 404 hits each link had.
- Changed: settings and a compact index of enabled rules are autoloaded; front-end requests need no extra database query when there are no rules.
- Changed: the admin screen is split into tabs (404 log, Redirects, 410 rules, Settings, Tools) and the main class into request handling, rules, log, address-change watch and Site Health classes.
- Changed: redirect targets follow the permalink trailing-slash setting, avoiding a second canonical redirect.
- Added Ukrainian translations for all new admin strings.
- Removed PayPal from the developer support panel and corrected the BTC and USDT TRC-20 developer wallet addresses (from 2.1.5).

## 2.1.5

- Removed PayPal from the developer support panel and corrected the BTC and USDT TRC-20 developer wallet addresses.

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
