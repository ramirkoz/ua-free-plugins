## 1.3.11
- Updated WordPress compatibility metadata for WordPress 7.1.
- No functional changes.

## 1.3.10 - 2026-08-13
- Added privacy-safe instrumentation diagnostics to the existing JSON export.
- Export now reports tracking configuration, integrations, funnel signal counts and browser-runtime verification boundary without exposing confirmation secrets or visitor identifiers.
- No tracking behavior or production data-writing logic changed.

## 1.3.9

- Fixed WordPress.org Plugin Check prepared-SQL validation for all three legacy-table migration queries.
- No changes to migration sources, stored settings, aggregate data, menu placement or support UI.

## 1.3.8

- Restore historical settings from the exact `uafree_donate_stats_settings` option.
- Merge historical aggregate tables (`uafree_donate_daily`, `uafree_donate_sessions`, `uafree_donate_confirmations`) into current `kozdonate_*` tables once and idempotently.
- Preserve current post-upgrade data while restoring legacy totals.
- Use the current KOZ support layout only as a standalone fallback; suppress it when the shared KOZ Suite panel is already active.
- Include both UA FREE actions: Donate and About the foundation.

# Changelog

## 1.3.7
- Restored KOZ Suite menu integration: Donate Stats is registered as a submenu of the existing KOZ Suite root instead of creating a separate top-level menu.
- Added a plugin-specific fallback Suite root only when no KOZ Suite menu exists.
- Preserved the stable `koz-donate-stats` page slug and existing settings links.

## 1.3.6
- Restored the stable `koz-donate-stats` admin page slug so existing Settings links and bookmarks remain valid after the WordPress.org prefix re-audit.
- Kept plugin-owned PHP/JS/runtime identifiers on the canonical `kozdonate` / `KOZDONATE` prefix.

## 1.3.5

- WordPress.org re-audit: moved plugin-owned namespaces, classes, constants, hooks, options, REST namespace, handles, localized objects, transients and admin identifiers to the unique `kozdonate` / `KOZDONATE` family.
- Preserved existing settings and aggregate data through explicit legacy option and database-table migration.
- Updated current integrations to the canonical KOZ Copy Actions and KOZ Consent Manager APIs/events.
- Removed the shared Suite registry/runtime/support identifiers from the package.

## 1.3.4

- Added runtime localization for ten supported non-English WordPress locales with English fallback.
- Localized the complete administration interface, Suite screen, support panel and clipboard feedback.

## 1.3.2

- Corrected the KOZ Suite overview to detect only KOZ packages as KOZ plugins.
- Added working Open buttons for every active KOZ plugin with a settings page.
- Legacy UA FREE packages are now reported separately instead of appearing as active KOZ plugins.
- Added the shared KOZ Suite menu with the `dashicons-layout` icon.
- Updated namespaces to the full plugin-specific `KOZDonateStats` prefix.
- Removed global helper functions that conflict with WordPress Plugin Check naming rules.
- Added KOZ Consent Manager browser API compatibility.
- Added the developer LinkedIn profile to plugin metadata, documentation and the support panel.

## 1.3.0

- Rebranded as KOZ Donate Stats & Conversions.
- Changed the public slug and text domain to `koz-donate-stats`.
- Declared Tony Kozyriev / `ramirkz` as independent developer and owner.
- Replaced inline support-panel assets with WordPress enqueue functions.
- Removed bundled PO and MO translation files.
- Rewrote public documentation and separated developer support from UA FREE charitable donations.

## 1.2.10

- Added automatic discovery and tracking of active KOZ Static Translate routes for selected source pages.
- Shows generated language routes directly below the selected WordPress page in the settings list.
- Keeps manually entered local paths separate and preserves existing settings.

## 1.2.9

- Fixed the browser contract with KOZ Consent Manager.
- Restricts the Google Ads dataLayer conversion event to `payment_open`.
- Prevents page views and local journey events from firing the outbound-click conversion.
- Added a page-level runtime initialization guard and compatible consent-update listeners.

## 1.2.8

- Redesigned the shared UA FREE support panel.
- Moved the panel into the WordPress admin content area to prevent overlap with the core footer.
- Added compact wallet rows and accessible copy buttons.
- Added PayPal developer donations via `kozyriev@uafree.org`.
- Preserved all plugin-specific functionality.

## 1.2.7

- Kept the canonical product name KOZ Donate Stats & Conversions untranslated in WordPress plugin metadata.
- Ukrainian interface translations remain available.
- No tracking or storage behaviour changed.

## 1.2.6

- Fixed the final Plugin Check warnings in admin screen detection and prefetch header handling.

## 1.2.5

- Prepared dynamic database identifiers with `%i` and documented legitimate plugin-table queries.
- Sanitized request metadata and clarified nonce handling for read-only admin filters.
- Fixed CSV streaming, translator comments, WordPress metadata and package contents.

## 1.2.4

- Final repository and WordPress.org packaging release.
- Updated version, readme metadata, compatibility fields and license headers.
- No custom update checker or UA FREE usage telemetry added.

## 1.2.1

- Added a signed privacy-safe `/confirm` REST callback.
- Added idempotency table storing only HMAC reference hashes.
- Added callback URL, secret, rotate button and three-step setup instructions.
- Added plain-language confirmation and conversion explanations.
- Reorganized settings into five simple sections.
- Moved CSS selectors into a collapsed technical block.
- Added Latin `UA` menu mark.
- Existing aggregate statistics and historical data remain intact.

## 1.1.2-dev

- Added payment/copy event tracking and removed the duplicate Suite tab.
