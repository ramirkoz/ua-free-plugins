# UA FREE WordPress Plugin Suite

**A practical collection of 12 privacy-first WordPress plugins created from real operational work for the UA FREE charitable foundation and released for everyone to use.**

Use one plugin, several plugins, or the whole suite. There is no required framework, paid core, hidden telemetry or custom updater.

[Download the latest release](https://github.com/ramirkoz/ua-free-plugins/releases/latest) · [Visit the plugin landing page](https://uafree.org/ua-free-plugins/) · [Support UA FREE](https://uafree.org/donate/)

> If these plugins save you time, please star the repository. It helps other WordPress administrators, nonprofits and developers find the project.

## Release status

- Suite release: **3.1**
- Public plugins: **12**
- Live WordPress Plugin Check: **12/12 PASS**
- Shared interface: **UA FREE Support Panel v2.1**
- License: **GPL-2.0-or-later**
- Update policy: no custom updater; WordPress.org updates will be used after directory approval

## Download the plugin you need

Each plugin is independently installable. Download an individual ZIP from the latest GitHub Release, then upload it through WordPress.

| Plugin | Version | Best for | Download |
|---|---:|---|---|
| UA FREE Migration & Cleanup | 0.8.10 | Environment checks, controlled snapshots, migration assistance and cleanup of old plugin leftovers. | [ZIP](https://github.com/ramirkoz/ua-free-plugins/releases/download/v3.1/ua-free-migration-cleanup-0.8.10.zip) |
| UA FREE Static Translate | 0.8.7 | Frontend-only static translations without rewriting original WordPress content. | [ZIP](https://github.com/ramirkoz/ua-free-plugins/releases/download/v3.1/ua-free-static-translate-0.8.7.zip) |
| UA FREE Translate Diagnostics | 0.2.14 | Read-only translation status, configuration checks and troubleshooting. | [ZIP](https://github.com/ramirkoz/ua-free-plugins/releases/download/v3.1/ua-free-translate-diagnostics-0.2.14.zip) |
| UA FREE SEO Core | 2.0.8 | Lightweight metadata, schema, sitemap, AI-discovery and accessibility support. | [ZIP](https://github.com/ramirkoz/ua-free-plugins/releases/download/v3.1/ua-free-seo-core-2.0.8.zip) |
| UA FREE 404 Guard & URL Intelligence | 2.0.8 | Privacy-safe 404/410 reporting, broken-link analysis and controlled same-site redirects. | [ZIP](https://github.com/ramirkoz/ua-free-plugins/releases/download/v3.1/ua-free-404-guard-2.0.8.zip) |
| UA FREE Site Bridge | 0.4.10 | Secure read-only diagnostics API and controlled same-site HTTP probes. | [ZIP](https://github.com/ramirkoz/ua-free-plugins/releases/download/v3.1/ua-free-site-bridge-0.4.10.zip) |
| UA FREE Consent Manager | 0.1.7 | Visitor consent and a local allowlist for optional analytics, advertising and other scripts. | [ZIP](https://github.com/ramirkoz/ua-free-plugins/releases/download/v3.1/ua-free-consent-manager-0.1.7.zip) |
| UA FREE Donate Stats & Conversions | 1.2.10 | Local donation-journey and conversion reporting with limited personal-data collection. | [ZIP](https://github.com/ramirkoz/ua-free-plugins/releases/download/v3.1/ua-free-donate-stats-1.2.10.zip) |
| UA FREE Google Ads Campaign Builder | 1.3.7 | Reviewable Google Ad Grants and standard Google Ads packages for Google Ads Editor. | [ZIP](https://github.com/ramirkoz/ua-free-plugins/releases/download/v3.1/ua-free-google-ads-campaign-builder-1.3.7.zip) |
| UA FREE Copy | 1.0.7 | Accessible copy-to-clipboard actions for links, text blocks and useful content. | [ZIP](https://github.com/ramirkoz/ua-free-plugins/releases/download/v3.1/ua-free-copy-1.0.7.zip) |
| UA FREE URL-Only Comment Spam | 1.0.7 | Moderation of comments containing only one or more URLs. | [ZIP](https://github.com/ramirkoz/ua-free-plugins/releases/download/v3.1/ua-free-url-only-comment-spam-1.0.7.zip) |
| UA FREE Suite Control Center | 0.3.13 | Status, navigation, diagnostics and support links for installed UA FREE plugins. | [ZIP](https://github.com/ramirkoz/ua-free-plugins/releases/download/v3.1/ua-free-suite-control-center-0.3.13.zip) |

## Install in WordPress

1. Download the ZIP for the plugin you need.
2. Open **WordPress Admin → Plugins → Add New Plugin → Upload Plugin**.
3. Select the ZIP and choose **Install Now**.
4. Activate the plugin.
5. Open its settings page and review the available options before enabling production features.

For a manual update, upload the newer ZIP through the same screen and choose **Replace current with uploaded** when WordPress offers the replacement.

## Full suite library

The latest release also includes:

- `UA_FREE_HUB_RELEASE_LIBRARY_FINAL_2026-08-03_v3.1.zip`
- `manifest.json`
- `SHA256SUMS.txt`
- Separate SHA-256 files for every downloadable ZIP

The full library is intended for maintainers, archives and bulk distribution. **It is not a WordPress plugin.** Extract it first, then install the individual plugin ZIP files inside it.

The Git tag `v3.1` identifies the complete suite release. Every plugin retains its own version number.

## Privacy and security principles

- No Suite-wide telemetry, fingerprinting or remote usage reporting.
- No hidden tracking pixels.
- No custom update channel that bypasses WordPress.org.
- No unnecessary cookies or personal-data storage.
- Administrative actions use WordPress capabilities, nonces and sanitization.
- Each plugin can be installed and removed independently.
- Every v3.1 plugin package passed live WordPress Plugin Check.

## Shared support panel

All v3.0 plugins include the responsive **UA FREE Support Panel v2.1**. It clearly separates support for the charitable foundation from optional support for ongoing plugin development.

### Support the UA FREE foundation

- [Donate to UA FREE](https://uafree.org/donate/)
- [Learn about the foundation](https://uafree.org/)

### Support plugin development

Developer donations are separate from donations to the UA FREE charitable foundation.

- **PayPal:** `kozyriev@uafree.org`
- [Donate via PayPal](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=kozyriev%40uafree.org&item_name=Support+UA+FREE+plugin+development&currency_code=USD)
- **BTC:** `bc1q4dn8e7sz2866g7qp1qtshh98j54tvuau5ghuuk`
- **ETH / USDC ERC-20:** `0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229`
- **USDT TRC-20:** `TYsGyK7K3XB4NPHprf5w8ZodFafxFfDdbP`

Use only the network shown next to each crypto address.

## Verify downloads

Download `SHA256SUMS.txt` from the release and run:

```bash
sha256sum -c SHA256SUMS.txt
```

On Windows PowerShell:

```powershell
Get-FileHash .\ua-free-copy-1.0.7.zip -Algorithm SHA256
```

Compare the output with the matching value in `SHA256SUMS.txt`.

## Contributing

Focused bug reports and pull requests are welcome. Please describe the WordPress version, PHP version, steps to reproduce and the expected result.

The Google Ads Editor import remains a separate functional workflow test and is not part of WordPress Plugin Check.

## Project background

The suite grew from practical work on the UA FREE foundation website rather than from a theoretical plugin checklist. The public versions preserve that operational experience while removing foundation-specific assumptions wherever possible.

Development, review and test preparation were assisted by OpenAI ChatGPT using the GPT-5.6 Thinking model. Final scope, release decisions and live-site validation remained under human control.

## Repository topics

WordPress · privacy · nonprofit · Ukraine · accessibility · SEO · consent management · migration · anti-spam · donations · Google Ads

## License

GPL-2.0-or-later. See the license information inside each plugin.
