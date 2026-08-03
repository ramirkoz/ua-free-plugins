# KOZ WordPress Suite Rebrand Plan

- Document version: 0.1
- Date: 2026-08-03
- Status: ACTIVE WORKING PLAN
- Owner and developer: Tony Kozyriev
- WordPress.org account: ramirkz
- GitHub account: ramirkoz
- Development contact: ramir@ua.fm

## Brand model

**KOZ WordPress Suite** is an independently developed collection of WordPress plugins by Tony Kozyriev.

The plugins were originally created for the UA FREE charitable foundation. The foundation website became the first production and testing environment and continues to use the plugins. This project history does not transfer plugin ownership or source-code rights to the foundation.

Every plugin may include two clearly separated support options:

1. Support independent plugin development through PayPal or the listed cryptocurrency wallets.
2. Support Ukraine through the UA FREE charitable foundation at https://uafree.org/donate/.

## Plugin naming map

| Current plugin | New plugin | New slug / text domain | Target version |
|---|---|---|---:|
| UA FREE Migration & Cleanup | KOZ Migration & Cleanup | koz-migration-cleanup | 0.9.0 |
| UA FREE Static Translate | KOZ Static Translate | koz-static-translate | 0.9.0 |
| UA FREE Translate Diagnostics | KOZ Translate Diagnostics | koz-translate-diagnostics | 0.3.0 |
| UA FREE SEO Core | KOZ SEO Core | koz-seo-core | 2.1.0 |
| UA FREE 404 Guard & URL Intelligence | KOZ 404 Guard & URL Intelligence | koz-404-guard | 2.1.0 |
| UA FREE Site Bridge | KOZ Site Bridge | koz-site-bridge | 0.5.0 |
| UA FREE Consent Manager | KOZ Consent Manager | koz-consent-manager | 0.2.0 |
| UA FREE Donate Stats & Conversions | KOZ Donate Stats & Conversions | koz-donate-stats | 1.3.0 |
| UA FREE Google Ads Campaign Builder | KOZ Google Ads Campaign Builder | koz-google-ads-campaign-builder | 1.4.0 |
| UA FREE Copy | KOZ Copy Actions | koz-copy-actions | 1.1.0 |
| UA FREE URL-Only Comment Spam | KOZ URL-Only Comment Spam | koz-url-only-comment-spam | 1.1.0 |
| UA FREE Suite Control Center | KOZ Suite Control Center | koz-suite-control-center | 0.4.0 |

## Mandatory package changes

- Replace public names, menu labels, readme titles, text domains, plugin folders and main filenames.
- Set `Author: Tony Kozyriev`, `Contributors: ramirkz`, GitHub links and a working support URL.
- Keep legacy option names, database tables, hooks and public APIs where required for safe upgrades.
- Add KOZ aliases before removing any legacy UA FREE identifiers.
- Move shared support-panel CSS and JavaScript to `wp_enqueue_style()` and `wp_enqueue_script()` assets.
- Remove bundled `.po` and `.mo` files from WordPress.org packages; keep only source POT where useful.
- Rewrite every `readme.txt` with functionality, use cases, privacy, project background and separated support sections.
- Run PHP lint, ZIP integrity, static package checks and live WordPress Plugin Check for every plugin.

## Release sequence

1. Rebrand and locally validate all 12 plugin packages.
2. Run live WordPress Plugin Check for all 12 candidates.
3. Synchronize the approved bytes with the site Plugin Hub, Google Drive and GitHub.
4. Build the KOZ release library, manifest, checksums and sync report.
5. Upload KOZ Copy Actions to the existing WordPress.org submission and reply in the same review email thread.
