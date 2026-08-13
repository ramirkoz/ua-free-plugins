# KOZ Suite 1.0.14

- Updated KOZ Suite Control Center from 0.4.5 to 0.4.6.
- Added a privacy-safe, read-only public exposure scanner.
- Production scan correctly detected one sensitive private-download URL exposure on `/koz-plugins/`.
- Scanner does not display or store secret token/nonce values.
- KOZ Suite Control Center 0.4.6: LIVE FUNCTIONAL PASS, Plugin Check PASS, WordPress 7.0.4 PASS.
- Other 11 public plugin packages are unchanged from Suite 1.0.13.
- The private Hub token exposure is tracked separately and is not silently remediated by Control Center.
