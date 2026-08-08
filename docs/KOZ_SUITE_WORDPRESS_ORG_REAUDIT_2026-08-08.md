# KOZ Suite — WordPress.org re-audit

Date: 2026-08-08

## Reason

The manual WordPress.org review of KOZ Copy Actions introduced a stricter acceptance baseline than the earlier automated Plugin Check gate. A previous PASS does not prove that plugin-owned names are sufficiently unique.

## Mandatory baseline

- Every plugin-owned function, class, constant, namespace, option, setting group, AJAX action, hook/filter, script/style handle, localized JS object and internal admin page identifier must be plugin-specific and collision-safe.
- Prefixes must be at least four characters and distinct to the plugin. Shared `uafree`, generic `koz`, `koz-suite`, `KOZSupportI18n`, shared support classes and shared runtime classes are not accepted as canonical identifiers.
- WordPress core translation functions such as `__()` and `_n()` are not renamed.
- Translation strings with placeholders require `translators:` comments where required.
- `Domain Path` is declared only when the referenced directory exists.
- Backward compatibility may read/migrate historical options/hooks, but the current canonical identifiers must use the plugin-specific prefix.
- Live functional testing and Plugin Check are performed on the UA FREE foundation site only.

## Audit matrix

| Plugin | Live | Next | Canonical prefix | Status | Known remediation |
|---|---:|---:|---|---|---|
| KOZ Migration & Cleanup | 0.9.3 | 0.9.4 | kozmig / KOZMIG | RE-AUDIT REQUIRED | shared KOZ/UA FREE runtime, support/registry identifiers; legacy uafree names |
| KOZ Static Translate | 0.9.1 | 0.9.2 | kozstx / KOZSTX | RE-AUDIT REQUIRED | shared KOZ/UA FREE runtime, support/registry identifiers; legacy uafree names |
| KOZ Translate Diagnostics | 0.3.1 | 0.3.2 | koztdiag / KOZTDIAG | RE-AUDIT REQUIRED | shared KOZ/UA FREE runtime, support/registry identifiers; legacy uafree names |
| KOZ SEO Core | 2.1.2 | 2.1.3 | kozseo / KOZSEO | RE-AUDIT REQUIRED | shared KOZ/UA FREE runtime, support/registry identifiers; legacy uafree names |
| KOZ 404 Guard & URL Intelligence | 2.1.0 | 2.1.1 | koz404 / KOZ404 | RE-AUDIT REQUIRED | shared KOZ/UA FREE runtime, support/registry identifiers; legacy uafree names |
| KOZ Site Bridge | 0.5.0 | 0.5.1 | kozbridge / KOZBRIDGE | RE-AUDIT REQUIRED | shared KOZ/UA FREE runtime, support/registry identifiers; legacy uafree names |
| KOZ Consent Manager | 0.2.7 | 0.2.8 | kozconsent / KOZCONSENT | RE-AUDIT REQUIRED | shared KOZ/UA FREE runtime, support/registry identifiers; legacy uafree names |
| KOZ Donate Stats & Conversions | 1.3.4 | 1.3.5 | kozdonate / KOZDONATE | RE-AUDIT REQUIRED | shared KOZ/UA FREE runtime, support/registry identifiers; legacy uafree names |
| KOZ Google Ads Campaign Builder | 1.4.3 | 1.4.4 | kozgads / KOZGADS | RE-AUDIT REQUIRED | shared KOZ/UA FREE runtime, support/registry identifiers; legacy uafree names |
| KOZ Copy Actions | 1.1.11 | 1.1.11 | kozcoac / KOZCOAC | PASS / UNDER REVIEW | WordPress.org prefix fix + Plugin Check + live test PASS |
| KOZ URL-Only Comment Spam | 1.1.6 | 1.1.6 | kozurlspam / KOZURLSPAM | PASS | Live functional test + WordPress Plugin Check PASS on UA FREE foundation site; canonical package approved |
| KOZ Suite Control Center | 0.4.0 | 0.4.1 | kozsuitecc / KOZSUITECC | RE-AUDIT REQUIRED | shared KOZ/UA FREE runtime/support identifiers; suite-wide generic names |

## Release workflow

1. Refactor one plugin to its canonical prefix/namespace.
2. Preserve old settings/integrations through explicit migration or compatibility aliases only where required.
3. PHP lint + static prefix audit.
4. Build ZIP using only `plugin-name-version.zip`.
5. Install/update on the UA FREE foundation site.
6. Run WordPress Plugin Check until zero errors/warnings.
7. Run live functional test.
8. After PASS, sync GitHub source, Google Drive package/report and central version registry.
9. After all 12 plugins pass, rebuild the KOZ Suite download library, manifest and SHA-256 catalog.

## Current gate

KOZ Copy Actions 1.1.11 is the reference implementation and remains frozen while WordPress.org review is pending. KOZ URL-Only Comment Spam 1.1.6 has also completed the stricter re-audit with live functional and Plugin Check PASS. The remaining 10 plugins are not considered current WordPress.org-ready until this re-audit is complete.
