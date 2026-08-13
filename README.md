# KOZ WordPress Plugin Suite

A practical collection of 12 privacy-first WordPress plugins created from real production work and independently maintained by Tony Kozyriev (`ramirkz`). Each plugin is independently installable; the suite bundle is provided for synchronized deployment and archival use.

## Current release status

- Suite bundle: **1.0.14**
- Public plugins: **12**
- Live functional baseline: **12/12 PASS**
- WordPress Plugin Check baseline: **12/12 PASS**
- Current changed component: **KOZ Suite Control Center 0.4.6**
- Private Hub: **KOZ Suite Hub — Private 0.4.5** — live + Plugin Check + security remediation PASS
- Administration languages: Ukrainian, English, Chinese, Spanish, Arabic, Indonesian, Portuguese, French, Japanese, German and Hindi
- License: **GPL-2.0-or-later**

## Included plugins

| Plugin | Version |
|---|---:|
| KOZ Migration & Cleanup | 0.9.6 |
| KOZ Static Translate | 0.9.7 |
| KOZ Translate Diagnostics | 0.3.5 |
| KOZ SEO Core | 2.1.14 |
| KOZ 404 Guard & URL Intelligence | 2.1.2 |
| KOZ Site Bridge | 0.5.3 |
| KOZ Consent Manager | 0.2.11 |
| KOZ Donate Stats & Conversions | 1.3.9 |
| KOZ Google Ads Campaign Builder | 1.4.5 |
| KOZ Copy Actions | 1.1.12 |
| KOZ URL-Only Comment Spam | 1.1.9 |
| KOZ Suite Control Center | 0.4.6 |

## Current bundle

**KOZ Suite Bundle 1.0.14** contains the 12 verified plugin ZIP packages. Release metadata and SHA-256 checksums are stored under `releases/`.

## Privacy and security

- No suite-wide telemetry or hidden tracking.
- No custom updater that bypasses WordPress.org.
- Administrative actions use WordPress capabilities, nonces and sanitization.
- KOZ Site Bridge remains read-only and limits probes to safe same-site public routes.
- KOZ SEO Core AI Vision ALT analysis is review-first; analysis does not write ALT values until explicitly approved.
- KOZ Suite Control Center includes a privacy-safe public exposure scanner that redacts secret values.
- The `/koz-plugins/` reusable private-download token exposure detected by Control Center 0.4.6 was remediated in **KOZ Suite Hub — Private 0.4.5**; live downloads and Plugin Check passed without exposing token/nonce values.

## Project background and ownership

The suite grew from practical work on the UA FREE charitable foundation website, which remains the live production and testing environment. The plugins and source code are independently owned and maintained by Tony Kozyriev. UA FREE does not own the plugins.

## License

GPL-2.0-or-later. See the license information inside each plugin.
