# Changelog

## 0.9.7
- Updated WordPress compatibility metadata for WordPress 7.1.
- No functional changes.

## 0.9.6

- Normalized WordPress.org `Tested up to` metadata from patch-level `7.0.4` to the accepted major.minor value `7.0`.
- No runtime behavior changes from 0.9.5.

## 0.9.5

- Added read-only placeholder-page detection.
- Added privacy-safe inventory of legacy `?attachment_id=` links without exporting raw attachment IDs.
- Added suggested old-slug redirect mappings from WordPress `_wp_old_slug` history; no redirects are created automatically.
- Added migration-candidate counts to the Overview screen and environment snapshot.

## 0.9.4

- Standardized plugin-owned runtime identifiers under `kozmig / KOZMIG` and `ramirkz\kozmig`.
- Preserved the stable admin page and read-only scanner behavior.
- Added a plugin-specific KOZ Suite fallback page and normalized support-panel fallback behavior.
- Removed obsolete shared runtime/global class definitions from this package.

## 0.9.3

- Removed the redundant About & Support tab.
- Kept the shared support panel below Overview and Environment.
- Removed obsolete tab-only scripts and styles.

## 0.9.2

- Rebuilt the About & Support tab as one compact, native WordPress panel.
- Removed the duplicated three-card section and suppressed the shared footer panel on this tab.
- Added localized copy controls without inline scripts or styles.

## 0.9.1

- KOZ public rebrand and current KOZ Suite integration.
- Ten runtime administration languages plus English fallback.
- Enqueued administration and support-panel assets.
- Legacy internal identifiers preserved for migration compatibility.
- Former package safely deactivated on activation without deleting files or data.

## 0.8.10

- Last stable UA FREE-branded release.
