# Changelog

## 0.8.8

- Final repository and WordPress.org packaging release.
- Updated version, readme metadata, compatibility fields and license headers.
- No custom update checker or UA FREE usage telemetry added.

## 0.8.5
- Keeps the plugin only under UA FREE.
- Removes legacy Tools/Settings entries and the duplicate Plugin Suite tab.
- Replaces the stale Simplest Analytics wording with the actual product purpose.
- Snapshot and cleanup logic is unchanged.

# Changelog

## 0.8.5

- Removed the complete 0.7 site-specific implementation from the public package.
- Removed automatic legacy loading and the legacy enable constant.
- Kept the universal scanner read-only and independent.
- Defined a separate temporary bridge package for migrations from older internal builds.

## 0.8.0

- Rebuilt the public plugin as a universal read-only environment scanner.
- Added installed plugin, database table, autoload and cron inventory.
- Added per-plugin heuristic inspection for likely options, metadata, tables and hooks.
- Added privacy-safe JSON snapshot export.
- Added the UA FREE Plugin Suite registry.
- Added standard WordPress internationalization with English source strings and Ukrainian translation.
- No destructive operation is exposed through the new universal interface.

## 0.7.0

- Internal site-specific build. Not included in the public package.

## 0.8.5
- Suite compatibility audit patch: canonical package identity, cross-plugin integration fixes, and collision prevention.

## 0.8.5
- Added the canonical shared UA FREE Suite registry/menu implementation.
