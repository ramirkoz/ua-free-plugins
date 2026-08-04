# KOZ WordPress Plugin Suite

A practical collection of 12 privacy-first WordPress plugins created from real production work and independently maintained by Tony Kozyriev (`ramirkz`). Use one plugin, several plugins or the complete suite. There is no paid core, hidden telemetry or custom updater.

## Release status

- Suite release: **1.0.0**
- Public plugins: **12**
- Live functional checks: **12/12 PASS**
- Live WordPress Plugin Check: **12/12 PASS**
- Administration languages: Ukrainian, English, Chinese, Spanish, Arabic, Indonesian, Portuguese, French, Japanese, German and Hindi
- License: **GPL-2.0-or-later**

## Included plugins

| Plugin | Version | Purpose |
|---|---:|---|
| KOZ Migration & Cleanup | 0.9.3 | Environment inventory, migration snapshots and controlled cleanup assistance. |
| KOZ Static Translate | 0.9.0 | Frontend static translations with language routes, queue and translation memory. |
| KOZ Translate Diagnostics | 0.3.1 | Read-only diagnostics and privacy-safe reports for translation infrastructure. |
| KOZ SEO Core | 2.1.2 | Metadata, schema, hreflang, sitemap and AI-discovery support. |
| KOZ 404 Guard & URL Intelligence | 2.1.0 | Privacy-safe 404/410 intelligence and controlled same-site redirects. |
| KOZ Site Bridge | 0.5.0 | Secure read-only diagnostics API and controlled same-site probes. |
| KOZ Consent Manager | 0.2.7 | Consent banner, allowlist and multilingual default templates. |
| KOZ Donate Stats & Conversions | 1.3.4 | Local donation journey and conversion reporting. |
| KOZ Google Ads Campaign Builder | 1.4.3 | Reviewable Google Ads campaign packages for Google Ads Editor. |
| KOZ Copy Actions | 1.1.7 | Accessible privacy-safe copy-to-clipboard actions. |
| KOZ URL-Only Comment Spam | 1.1.5 | Moderation of comments containing only one or more URLs. |
| KOZ Suite Control Center | 0.4.0 | Status, navigation and diagnostics for the complete KOZ Suite. |

## Installation

Each plugin is independently installable. Upload its ZIP through **WordPress Admin → Plugins → Add New Plugin → Upload Plugin**, activate it and review its settings. During migration, deactivate the corresponding former UA FREE package before activating the KOZ replacement. Existing compatible settings are reused where applicable.

## KOZ Suite Bundle 1.0.0

The release library contains `manifest.json`, `SHA256SUMS.txt`, changelog, test report and 12 individual plugin ZIP files. The library itself is **not a WordPress plugin**; extract it before installing individual packages.

## Privacy and security

- No suite-wide telemetry, fingerprinting or remote usage reporting.
- No hidden tracking pixels.
- No custom update channel that bypasses WordPress.org.
- Administrative actions use WordPress capabilities, nonces and sanitization.
- Each plugin can be installed and removed independently.

## Project background and ownership

The suite grew from practical work on the UA FREE charitable foundation website, which became the first production and testing environment. The plugins and source code are independently owned and maintained by Tony Kozyriev. UA FREE does not own the plugins.

Developer support and charitable donations are separate. See [SUPPORT.md](SUPPORT.md).

## WordPress.org

The first pending submission is being updated from UA FREE Copy to **KOZ Copy Actions**, with the requested slug `koz-copy-actions`. Other plugins will be submitted independently after approval and final directory checks.

## Contributing

Focused bug reports and pull requests are welcome. Include the WordPress version, PHP version, steps to reproduce and expected result.

## License

GPL-2.0-or-later. See the license information inside each plugin.
