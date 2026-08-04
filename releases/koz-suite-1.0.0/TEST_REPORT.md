# KOZ Suite 1.0.0 — Test Report

Date: 2026-08-04

## Final status

- Package count: **12/12**
- Canonical ZIP roots: **PASS**
- ZIP CRC integrity: **PASS**
- Plugin header/version match: **PASS**
- Unsafe paths/traversal: **PASS**
- Bundled `.po` / `.mo` files: **NONE**
- Live functional checks: **12/12 PASS** (confirmed on production WordPress)
- Live WordPress Plugin Check: **12/12 PASS**
- Locales checked during rollout included Ukrainian, Spanish, German and English fallback; multilingual Consent templates were also validated.

## Plugin matrix

| Plugin | Version | ZIP SHA-256 | Live | Plugin Check |
|---|---:|---|---|---|
| KOZ Migration & Cleanup | 0.9.3 | `4f2ec223f6c6e0c3281e6fd18fa90ffda170cfba102b0ac3d2a990ce7eaa7010` | PASS | PASS |
| KOZ Static Translate | 0.9.0 | `f1604cef2d639e51ce28667d07b37cbe7ee545d4e9f5508dc52fa5fe8f063550` | PASS | PASS |
| KOZ Translate Diagnostics | 0.3.1 | `37c2319907d252467222f4de22f455b4add44c97eb88cebeddd325a828c1af2c` | PASS | PASS |
| KOZ SEO Core | 2.1.2 | `299ed6fde4d18a9c198a057ac4b3d0c44e8c046cbe3e9c4767fd114f061b2a5c` | PASS | PASS |
| KOZ 404 Guard & URL Intelligence | 2.1.0 | `999ef8168ec338351ba7c4604d4a63f1c552cea80acfa74d00ce7e452eb0a5ef` | PASS | PASS |
| KOZ Site Bridge | 0.5.0 | `4e34de99d61eb415e9d8b435a80ef97ff4a4322c4ad2517c9e6b349668fa595a` | PASS | PASS |
| KOZ Consent Manager | 0.2.7 | `35d3ddd7d6b248d6df4e69c8602e902e8074785ec00ac938f83265c8f5bc18a4` | PASS | PASS |
| KOZ Donate Stats & Conversions | 1.3.4 | `02d0cf4abac9b91db87a2b3e94f345d1beb29e6d5d3ad57ce96afb3caf9d37be` | PASS | PASS |
| KOZ Google Ads Campaign Builder | 1.4.3 | `5491587b23d1c51b79e00040c7f31ea93558fa51590aa4767a9d915fa2d575ef` | PASS | PASS |
| KOZ Copy Actions | 1.1.7 | `6c6527de71e2eb22237340edc80b19b5f4395809cc3f32692139f92d282e095e` | PASS | PASS |
| KOZ URL-Only Comment Spam | 1.1.5 | `80bf0a9ef0dac86619942f477bd75574bc729598b01169e03b5614a2672202cf` | PASS | PASS |
| KOZ Suite Control Center | 0.4.0 | `915106d4c1b3a22e4f982537c8e13b529f825ff2f27fb5f0bebb4324a022abcf` | PASS | PASS |

## Notes

- Google Ads Editor import remains a separate workflow test outside WordPress Plugin Check.
- KOZ Static Translate diagnostics reported the production translation queue paused at its configured monthly character limit; this does not affect package integrity.
- The private release hub is versioned and distributed separately from this 12-plugin public bundle.
