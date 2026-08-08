# KOZ WordPress Plugin Suite

A practical collection of 12 privacy-first WordPress plugins created from real production work and independently maintained by Tony Kozyriev (`ramirkz`). Use one plugin, several plugins or the complete suite. There is no paid core, hidden telemetry or custom updater.

## Release status

- Archived suite baseline: **1.0.1**
- Public plugins: **12**
- Previous live functional baseline: **12/12 PASS**
- Previous WordPress Plugin Check baseline: **12/12 PASS**
- Current WordPress.org manual-review gate: **re-audit in progress**
- Reference package: **KOZ Copy Actions 1.1.11 — live PASS, Plugin Check PASS, submitted for review**
- Remaining packages: **11 require plugin-specific prefix/namespace re-audit before being considered WordPress.org-ready**
- Administration languages: Ukrainian, English, Chinese, Spanish, Arabic, Indonesian, Portuguese, French, Japanese, German and Hindi
- License: **GPL-2.0-or-later**

See [`docs/KOZ_SUITE_WORDPRESS_ORG_REAUDIT_2026-08-08.md`](docs/KOZ_SUITE_WORDPRESS_ORG_REAUDIT_2026-08-08.md) for the current audit matrix and acceptance rules.

## Included plugins

| Plugin | Version | Purpose |
|---|---:|---|
| KOZ Migration & Cleanup | 0.9.3 | Environment inventory, migration snapshots and controlled cleanup assistance. |
| KOZ Static Translate | 0.9.1 | Frontend static translations with language routes, queue, translation memory and protected JSON-LD output. |
| KOZ Translate Diagnostics | 0.3.1 | Read-only diagnostics and privacy-safe reports for translation infrastructure. |
| KOZ SEO Core | 2.1.2 | Metadata, schema, hreflang, sitemap and AI-discovery support. |
| KOZ 404 Guard & URL Intelligence | 2.1.0 | Privacy-safe 404/410 intelligence and controlled same-site redirects. |
| KOZ Site Bridge | 0.5.0 | Secure read-only diagnostics API and controlled same-site probes. |
| KOZ Consent Manager | 0.2.7 | Consent banner, allowlist and multilingual default templates. |
| KOZ Donate Stats & Conversions | 1.3.4 | Local donation journey and conversion reporting. |
| KOZ Google Ads Campaign Builder | 1.4.3 | Reviewable Google Ads campaign packages for Google Ads Editor. |
| KOZ Copy Actions | 1.1.11 | Accessible privacy-safe copy-to-clipboard actions. |
| KOZ URL-Only Comment Spam | 1.1.5 | Moderation of comments containing only one or more URLs. |
| KOZ Suite Control Center | 0.4.0 | Status, navigation and diagnostics for the complete KOZ Suite. |

## Installation

Each plugin is independently installable. Upload its ZIP through **WordPress Admin → Plugins → Add New Plugin → Upload Plugin**, activate it and review its settings. During migration, deactivate the corresponding former UA FREE package before activating the KOZ replacement. Existing compatible settings are reused where applicable.

## KOZ Suite Bundle 1.0.1

Bundle 1.0.1 is retained as the last fully live-tested archive baseline. It is no longer labeled as the current WordPress.org-readiness library because the manual review of KOZ Copy Actions introduced a stricter requirement for plugin-specific collision-safe identifiers. A replacement download library will be rebuilt after all 12 plugins pass the new gate.

## Privacy and security

- No suite-wide telemetry, fingerprinting or remote usage reporting.
- No hidden tracking pixels.
- No custom update channel that bypasses WordPress.org.
- Administrative actions use WordPress capabilities, nonces and sanitization.
- Each plugin can be installed and removed independently.

## Project background and ownership

The suite grew from practical work on the UA FREE charitable foundation website, which remains the live production and testing environment. The plugins and source code are independently owned and maintained by Tony Kozyriev. UA FREE does not own the plugins.

Developer support and charitable donations are separate. See [SUPPORT.md](SUPPORT.md).

## WordPress.org

The first pending submission is **KOZ Copy Actions 1.1.11** with slug `koz-copy-actions`. It has passed live functional testing and WordPress Plugin Check on the UA FREE foundation site after the requested unique-prefix corrections. The other 11 plugins are being re-audited against the same manual-review standard before submission.

## Contributing

Focused bug reports and pull requests are welcome. Include the WordPress version, PHP version, steps to reproduce and expected result.

## License

GPL-2.0-or-later. See the license information inside each plugin.
