# KOZ Suite — WordPress.org re-audit

Date: 2026-08-08

## Mandatory baseline

- Every plugin-owned function, class, constant, namespace, option, setting group, AJAX action, hook/filter, script/style handle, localized JS object and internal admin page identifier must be plugin-specific and collision-safe.
- Canonical identifiers use a distinct plugin prefix of at least four characters.
- Backward compatibility may read/migrate historical options, hooks, URLs and tables, but current canonical identifiers must use the plugin-specific prefix.
- WordPress core translation functions are not renamed.
- Translation strings with placeholders use `translators:` comments where required.
- `Domain Path` is declared only when the referenced directory exists.
- Live functional testing and WordPress Plugin Check are performed on the UA FREE foundation site.

## Current audit matrix

| Plugin | Live/current | Canonical prefix | Strict re-audit status |
|---|---:|---|---|
| KOZ Migration & Cleanup | 0.9.3 | kozmig / KOZMIG | RE-AUDIT REQUIRED |
| KOZ Static Translate | 0.9.1 | kozstx / KOZSTX | RE-AUDIT REQUIRED |
| KOZ Translate Diagnostics | 0.3.1 | koztdiag / KOZTDIAG | RE-AUDIT REQUIRED |
| KOZ SEO Core | 2.1.2 | kozseo / KOZSEO | RE-AUDIT REQUIRED |
| KOZ 404 Guard & URL Intelligence | 2.1.0 | koz404 / KOZ404 | RE-AUDIT REQUIRED |
| KOZ Site Bridge | 0.5.0 | kozbridge / KOZBRIDGE | RE-AUDIT REQUIRED |
| KOZ Consent Manager | 0.2.10 | kozconsent / KOZCONSENT | PASS — live + Plugin Check |
| KOZ Donate Stats & Conversions | 1.3.9 | kozdonate / KOZDONATE | PASS — live + Plugin Check |
| KOZ Google Ads Campaign Builder | 1.4.3 | kozgads / KOZGADS | RE-AUDIT REQUIRED |
| KOZ Copy Actions | 1.1.11 | kozcoac / KOZCOAC | PASS — directory review pending |
| KOZ URL-Only Comment Spam | 1.1.9 | kozurlspam / KOZURLSPAM | PASS — live + Plugin Check |
| KOZ Suite Control Center | 0.4.0 | kozsuitecc / KOZSUITECC | RE-AUDIT REQUIRED |

## Release workflow

1. Refactor one plugin to its canonical prefix/namespace.
2. Preserve old settings/integrations/data through explicit migration or compatibility aliases where required.
3. PHP lint + static prefix audit.
4. Build one canonical `plugin-name-version.zip`.
5. Install/update on the UA FREE foundation site.
6. Run WordPress Plugin Check until zero errors/warnings.
7. Run live functional test and verify preserved data/settings.
8. After PASS, synchronize GitHub, Google Drive package/report and central version registry.
9. Rebuild the KOZ Suite library metadata after synchronization; pending plugins remain explicitly marked as not current WordPress.org-ready.

## Current gate

Four plugins have completed the stricter re-audit: KOZ Copy Actions 1.1.11, KOZ URL-Only Comment Spam 1.1.9, KOZ Consent Manager 0.2.10 and KOZ Donate Stats & Conversions 1.3.9. The remaining eight plugins are not considered current WordPress.org-ready until their re-audit is complete.
