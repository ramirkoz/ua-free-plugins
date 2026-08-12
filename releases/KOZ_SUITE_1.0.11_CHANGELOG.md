# KOZ Suite 1.0.11 — Changelog

Date: 2026-08-12

- KOZ Translate Diagnostics: **0.3.2 → 0.3.5**.
- Translation readiness now measures effective current-segment coverage and translation-memory fallback instead of treating queue state as page readiness.
- Deep diagnostics now expose effective ready segments, memory fallback segments, and realistic missing estimates.
- Deep JSON export reuses the completed deep-scan result instead of launching a second heavy scan, preventing the observed Cloudflare 524 export timeout.
- KOZ Translate Diagnostics 0.3.5: live functional **PASS**, WordPress Plugin Check **PASS**, WordPress **7.0.4 PASS**.
- KOZ Suite Control Center remains **0.4.5 unchanged**.
- All other 11 public plugin packages remain unchanged from Suite 1.0.10.
