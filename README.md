# UA FREE WordPress Plugin Suite

A public collection of privacy-conscious WordPress plugins created from real operational work for the UA FREE charitable foundation and released for reuse by other websites.

## Current public release

**UA FREE Plugin Suite v3.0** contains 12 independently installable plugins.

- Release status: **FINAL**
- Live WordPress Plugin Check: **12/12 PASS**
- Shared support interface: **Support Panel v2.1**
- Update policy: no custom updater or hidden telemetry; WordPress.org updates will be used after directory approval
- Release package and checksum: [GitHub Releases](https://github.com/ramirkoz/ua-free-plugins/releases/latest)

## Included plugins

| Plugin | Version | Purpose |
|---|---:|---|
| UA FREE Migration & Cleanup | 0.8.10 | Controlled snapshots, environment checks, migration assistance and verified cleanup of old plugin leftovers. |
| UA FREE Static Translate | 0.8.7 | Frontend-only static translations that preserve original WordPress content and existing workflows. |
| UA FREE Translate Diagnostics | 0.2.14 | Read-only configuration, translation-status and troubleshooting diagnostics. |
| UA FREE SEO Core | 2.0.8 | Lightweight metadata, schema, sitemap, AI-discovery and accessibility support. |
| UA FREE 404 Guard & URL Intelligence | 2.0.8 | Privacy-safe 404/410 reporting, broken-link analysis and controlled same-site redirects. |
| UA FREE Site Bridge | 0.4.10 | Secure read-only diagnostics API and controlled same-site HTTP probes. |
| UA FREE Consent Manager | 0.1.6 | Visitor consent and a local allowlist for optional analytics, advertising and other scripts. |
| UA FREE Donate Stats & Conversions | 1.2.8 | Local donation-journey and conversion reporting without unnecessary personal-data collection. |
| UA FREE Google Ads Campaign Builder | 1.3.7 | Reviewable Google Ad Grants and standard Google Ads packages for Google Ads Editor. |
| UA FREE Copy | 1.0.7 | Accessible copy-to-clipboard actions for links, text blocks and other useful content. |
| UA FREE URL-Only Comment Spam | 1.0.7 | Moderation of comments that contain only one or more URLs. |
| UA FREE Suite Control Center | 0.3.13 | Status, navigation, diagnostics and support links for installed UA FREE plugins. |

## Design principles

- Each plugin can be installed and used independently.
- No Suite-wide telemetry, fingerprinting or external usage reporting.
- No custom update channel that bypasses WordPress.org.
- No unnecessary cookies or personal-data storage.
- Administrative actions use WordPress capabilities, nonces and sanitization.
- Every v3.0 package passed live Plugin Check on the foundation website.

## Installation

Download the current ZIP packages from the [UA FREE plugin landing page](https://uafree.org/ua-free-plugins/) or use the consolidated library from [GitHub Releases](https://github.com/ramirkoz/ua-free-plugins/releases/latest).

Install individual plugins through **WordPress Admin → Plugins → Add Plugin → Upload Plugin**.

## Shared support panel

All v3.0 plugins include the responsive **UA FREE Support Panel v2.1**. It clearly separates support for the charitable foundation from optional support for ongoing plugin development.

### Support the UA FREE foundation

- [Donate to UA FREE](https://uafree.org/donate/)
- [About the foundation](https://uafree.org/)

### Support plugin development

Developer donations are separate from donations to the UA FREE charitable foundation.

- **PayPal:** `kozyriev@uafree.org`
- **PayPal donation page:** [Donate via PayPal](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=kozyriev%40uafree.org&item_name=Support+UA+FREE+plugin+development&currency_code=USD)
- **BTC:** `bc1q4dn8e7sz2866g7qp1qtshh98j54tvuau5ghuuk`
- **ETH / USDC ERC-20:** `0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229`
- **USDT TRC-20:** `TYsGyK7K3XB4NPHprf5w8ZodFafxFfDdbP`

Use only the network shown next to each crypto address.

## Release verification

The v3.0 release includes a SHA-256 checksum file. The library contains a manifest with the version, size and SHA-256 value of every embedded plugin package.

The Google Ads Editor import remains a separate functional workflow test and is not part of WordPress Plugin Check.

## Development background

The suite grew from practical work on the UA FREE foundation website rather than from a theoretical plugin checklist. The public versions retain that experience while removing foundation-specific assumptions wherever possible.

Development, review and test preparation were assisted by OpenAI ChatGPT using the GPT-5.6 Thinking model. Final scope, release decisions and live-site validation remained under human control.

## License

GPL-2.0-or-later. See the license information inside each plugin.
