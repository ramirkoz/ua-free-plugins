# KOZ WordPress Plugin Suite

A practical collection of 12 privacy-first WordPress plugins created from real production work and independently maintained by Tony Kozyriev (`ramirkz`). Use one plugin, several plugins, or the complete suite. There is no paid core, hidden telemetry, or custom updater.

## Current release status

- Suite bundle: **KOZ Suite 1.0.7**
- Public plugins: **12**
- Strict re-audit: **12/12 PASS**
- Live functional baseline: **12/12 PASS**
- WordPress Plugin Check baseline: **12/12 PASS**
- Latest changed component: **KOZ Site Bridge 0.5.3 — PASS**
- WordPress.org live: **KOZ Copy Actions 1.1.12**
- WordPress.org review pending: **KOZ URL-Only Comment Spam 1.1.9**
- Administration languages: Ukrainian, English, Chinese, Spanish, Arabic, Indonesian, Portuguese, French, Japanese, German, and Hindi
- License: **GPL-2.0-or-later**

## Included plugins

| Plugin | Version | Purpose |
|---|---:|---|
| KOZ Migration & Cleanup | 0.9.4 | Environment inventory, migration snapshots and controlled cleanup assistance. |
| KOZ Static Translate | 0.9.2 | Frontend static translations with language routes, queue, translation memory and protected JSON-LD output. |
| KOZ Translate Diagnostics | 0.3.2 | Read-only diagnostics and privacy-safe reports for translation infrastructure. |
| KOZ SEO Core | 2.1.3 | Metadata, schema, hreflang, sitemap and AI-discovery support. |
| KOZ 404 Guard & URL Intelligence | 2.1.1 | Privacy-safe 404/410 intelligence and controlled same-site redirects. |
| KOZ Site Bridge | 0.5.3 | Secure read-only diagnostics API, sitemap inventory and privacy-safe rendered public-page audits. |
| KOZ Consent Manager | 0.2.11 | Consent banner, Google Consent Mode integration, allowlist and multilingual defaults. |
| KOZ Donate Stats & Conversions | 1.3.9 | Local donation journey and consent-aware conversion reporting. |
| KOZ Google Ads Campaign Builder | 1.4.5 | Reviewable Google Ads campaign packages for Google Ads Editor. |
| KOZ Copy Actions | 1.1.12 | Accessible privacy-safe copy-to-clipboard actions. |
| KOZ URL-Only Comment Spam | 1.1.9 | Moderation of comments containing only one or more URLs. |
| KOZ Suite Control Center | 0.4.5 | Status, navigation and diagnostics for the complete KOZ Suite. |

## KOZ Suite Bundle 1.0.7

Bundle 1.0.7 updates only KOZ Site Bridge from 0.5.2 to 0.5.3. The other 11 public plugin packages are unchanged from the verified 1.0.6 baseline.

KOZ Site Bridge 0.5.3 adds read-only sitemap inventory, concrete public same-site URL auditing and batched rendered audits with safe extraction of SEO metadata, headings, image ALT summaries, links, hreflang and JSON-LD types. Administrative, login, private REST and other protected paths remain outside the audit scope.

Release metadata and integrity files are stored under [`releases/`](releases/).

## Installation

Each plugin is independently installable. Upload its ZIP through **WordPress Admin → Plugins → Add New Plugin → Upload Plugin**, activate it and review its settings. During migration, deactivate the corresponding former UA FREE package before activating the KOZ replacement. Existing compatible settings are reused where applicable.

## Privacy and security

- No suite-wide telemetry, fingerprinting or remote usage reporting.
- No hidden tracking pixels.
- No custom update channel that bypasses WordPress.org.
- Administrative actions use WordPress capabilities, nonces and sanitization.
- Read-only audit tools do not expose credentials, private content, administrator cookies or write operations.
- Each plugin can be installed and removed independently.

## Project background and ownership

The suite grew from practical work on the UA FREE charitable foundation website, which remains the live production and testing environment. The plugins and source code are independently owned and maintained by Tony Kozyriev. UA FREE does not own the plugins.

Developer support and charitable donations are separate. See [SUPPORT.md](SUPPORT.md).

## Contributing

Focused bug reports and pull requests are welcome. Include the WordPress version, PHP version, steps to reproduce and expected result.

## License

GPL-2.0-or-later. See the license information inside each plugin.
