## 0.4.7
- Updated WordPress compatibility metadata for WordPress 7.1.
- No functional changes.

# KOZ Suite Control Center changelog

## 0.4.6
- Added a read-only public exposure scanner for tokenized private-download and sensitive admin-post URLs.
- Scanner output is privacy-safe: paths, action names, and sensitive parameter names only; no secret values are displayed or stored.

## 0.4.5
- Prevents duplicate support blocks on KOZ Suite screens by deferring to the shared KOZ support renderer when it is present.
- Keeps the Control Center fallback support block for standalone use.

## 0.4.4

- Restored the dedicated KOZ Suite landing interface with plugin cards and Open buttons.
- Top-level KOZ Suite now renders the Suite landing page instead of Control Center.
- Control Center moved to its own canonical submenu route `kozsuitecc-control-center`.
- Preserved the previous `koz-suite-control-center` direct URL as a hidden compatibility route.

## 0.4.3
- Makes `kozsuitecc-suite` the real registered top-level KOZ Suite page and dashboard route.
- Keeps `kozsuitecc-control-center` and `koz-suite-control-center` as compatibility routes.
- Fixes the access-denied screen produced when the visible KOZ Suite menu still points to the established `kozsuitecc-suite` slug.

## 0.4.2
- Makes `kozsuitecc-control-center` the canonical KOZ Suite root and dashboard route.
- Registers legacy routes under the real parent before hiding them to avoid WordPress access-denied screens.
- Preserves a single KOZ Suite top-level menu for all suite plugins.

## 0.4.1
- Owns or reuses a single KOZ Suite top-level menu and no longer replaces an existing suite menu late in admin_menu.
- Uses plugin-specific `kozsuitecc` runtime, support, option and admin identifiers while preserving legacy URL compatibility.
- Rebranded the former suite control center for the KOZ WordPress Suite.
- Added a corrected catalog for all twelve KOZ plugins.
- Added localized suite overview and manager dashboard.
- Added privacy-safe JSON export.
- Preserved removal of obsolete UA FREE heartbeat telemetry.
