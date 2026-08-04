# KOZ Suite 1.0.0 — Test Report

Date: 2026-08-04

## Final status

- Package count: **12/12**
- Canonical ZIP roots: **PASS**
- ZIP CRC integrity: **PASS**
- Plugin header/version match: **PASS**
- Unsafe paths/traversal: **PASS**
- Bundled `.po` / `.mo` files: **NONE**
- Live functional checks for plugin code: **12/12 PASS**
- Live WordPress Plugin Check before readme-only canonicalization: **12/12 PASS**
- Canonical WordPress.org-ready ZIP local package checks: **12/12 PASS**
- Standalone release ZIP = bundle ZIP = WordPress.org set ZIP: **12/12 SHA-256 PASS**

## Plugin matrix

| Plugin | Version | Canonical ZIP SHA-256 | Runtime | Package |
|---|---:|---|---|---|
| KOZ Migration & Cleanup | 0.9.3 | `518a7bb38a66c4b8a89831360be9a7714c707540f161db0465c0fe2a3493f1b4` | PASS | PASS |
| KOZ Static Translate | 0.9.0 | `5ef6aa4dcce10107cbdd1c5f0a088603d9322e8e95de363030baf85654a55f33` | PASS | PASS |
| KOZ Translate Diagnostics | 0.3.1 | `d481919a07d2070fffd8346686e44532b110fe79ffe19f59b74c08212b24e229` | PASS | PASS |
| KOZ SEO Core | 2.1.2 | `324033fb23acc176bdf8c7858e48a490213f83beb9b220c66114f2511abf073a` | PASS | PASS |
| KOZ 404 Guard & URL Intelligence | 2.1.0 | `4dde0e9c6926d2c5c45b26eeff9509cbba78608978f2ee7d15f3052e913a116f` | PASS | PASS |
| KOZ Site Bridge | 0.5.0 | `9fd25590de9677fd347d968f94364a23345d60863981f502a377c54c14cb122d` | PASS | PASS |
| KOZ Consent Manager | 0.2.7 | `e56305578d1c2231a487cf0f8c2aeb6a054ddb57448a011f8d4ff4fa5babdd67` | PASS | PASS |
| KOZ Donate Stats & Conversions | 1.3.4 | `450ec4d492467db75806857fcf7a6f09841b041d75da5350fa3086d68b093bcb` | PASS | PASS |
| KOZ Google Ads Campaign Builder | 1.4.3 | `76274b0cb23c70fe263980bcc9517175dd9f7a8ea9404b492cce0c94e7ab6854` | PASS | PASS |
| KOZ Copy Actions | 1.1.7 | `33469531b2922b7f5604ed435e6ada91a62a2f6bdaa2990b0d4c08c559496704` | PASS | PASS |
| KOZ URL-Only Comment Spam | 1.1.5 | `e2e9cb9be29c8891243074d53278bf3a8fa7afa2e9e9e29d43ae85b24c2122ce` | PASS | PASS |
| KOZ Suite Control Center | 0.4.0 | `c450c921d7944ffd3f340ed634e7327269c88e52c859619f9443db93bbb32e57` | PASS | PASS |

## Notes

- Canonical repack changes are limited to `readme.txt`; PHP, JavaScript, CSS and runtime settings are unchanged.
- KOZ Copy Actions 1.1.7 should be replaced once on the live site with the canonical ZIP, then Plugin Check rerun on that exact package.
- Google Ads Editor import remains a separate workflow test outside WordPress Plugin Check.
- The private release hub is versioned and distributed separately from this 12-plugin public bundle.
