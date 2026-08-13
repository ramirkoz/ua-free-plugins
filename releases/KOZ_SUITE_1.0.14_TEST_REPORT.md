# KOZ Suite 1.0.14 Test Report

## Changed component
- KOZ Suite Control Center 0.4.6
- Plugin SHA-256: `dde8fff5cd348ccff389204955adc526a56f6848f3eaeca316086b12e53fd272`

## Production validation
- WordPress 7.0.4: PASS
- Control Center loads and operates: PASS
- Public exposure scan: PASS
- Scan scope: 3 public pages
- Sensitive URL detection: PASS — 1 finding on `/koz-plugins/`
- Secret values redacted/not stored: PASS
- WordPress Plugin Check: PASS

## Bundle validation
- Public packages: 12/12
- Package SHA-256 vs manifest: PASS
- Package sizes vs manifest: PASS
- ZIP CRC: PASS
- Root/traversal safety: PASS
- Private Hub excluded from public bundle: PASS

Bundle SHA-256: `0f9695f8617bd6595bf38ef3c81d57f1ef55c2d63b69e4c897b0f15325a64861`
