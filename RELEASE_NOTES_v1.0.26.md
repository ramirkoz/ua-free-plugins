# KOZ WordPress Plugin Suite 1.0.26

This release updates KOZ Static Translate from 0.9.19 to 0.9.36 while keeping the other 11 public plugin packages unchanged.

## KOZ Static Translate 0.9.36

- Production safety mode prevents heavy activation, automatic worker, cron, bulk rebuild and Azure work during readiness recovery.
- Priority Core detection is universal and derived dynamically from WordPress navigation, with external URLs excluded and no site-specific hardcoding.
- Bounded local readiness tools use existing Translation Memory without Azure requests.
- Description metadata and image alt text are non-blocking for page readiness while remaining available for later translation.
- Live functional verification, WordPress Plugin Check and WordPress 7.1 compatibility: PASS.
- Google Search Console live test confirmed a recovered English route is indexable.

## Suite verification

- Public plugins: 12/12.
- Other 11 packages: byte-identical to KOZ Suite 1.0.25.
- KOZ Static Translate 0.9.36 SHA-256: `de2a2190b00dfc5b176d918cff3ac9e9a61dab3e6bca2921f615b66d82fae8e9`.
- KOZ Suite 1.0.26 SHA-256: `b4a41f77b01d61d4fe43ebbc32a00180613dd97f9e3bd013c24b2aaa6a75c2c5`.
