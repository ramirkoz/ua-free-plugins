# Changelog
## 1.4.13
- Updated WordPress compatibility metadata for WordPress 7.1.
- No functional changes.

## 1.4.12
- Restored automatic destination discovery: AdsBot validation now uses the same landing pages selected by the campaign generator and their generated language URLs.
- Removed the manual Final URL list introduced in 1.4.11.
- Kept destination discovery fully site-agnostic: no hard-coded page titles, slugs or site-specific route assumptions.
- Replaced fund-specific automatic callouts/negative keywords with content-derived callouts and administrator-controlled negative keywords.

## 1.4.11
- Removed all named-page/slug discovery heuristics from the live AdsBot destination check.
- The live check now uses only plugin-selected landing pages plus exact optional same-site Final URLs supplied by the administrator.
- Added a universal manual destination list so any WordPress site can validate the URLs it actually uses in Google Ads without assumptions about site structure.
- Preserved same-site-only requests, AdsBot user agent emulation, robots.txt checks, timeout diagnostics and language diagnostics.

## 1.4.10
- Resolves canonical top-level sitelink pages directly before any heuristic fallback.
- Prevents recent report posts or monthly photo reports from replacing the actual Reports/Gallery destinations in the live AdsBot check.
- Keeps the check same-site, read-only and independent of Google Ads account credentials.


## 1.4.9
- Expands the live AdsBot check to detected About, Reports, Gallery, Partners and Donate sitelink-style destinations in addition to selected landing pages.
- Keeps all probes same-site, read-only and independent of Google Ads account credentials.
- Preserves the 15-second timeout and slow-response/language diagnostics from 1.4.8.

## 1.4.8
- Makes the live destination diagnostic more tolerant and more informative: 15-second probe timeout, explicit slow-response warnings above 5 seconds, and no misleading language warning when the HTTP request itself failed.
- Keeps same-site-only AdsBot emulation, robots.txt checks and campaign generation unchanged.

## 1.4.7
- Adds a live same-site Google AdsBot destination check.
- Reports HTTP/timeouts, robots.txt blocking and basic rendered-language mismatches before Google Ads import or appeal.
- Does not upload or modify Google Ads campaigns.

## 1.4.4
- Replaced the dynamic WordPress translation call rejected by Plugin Check.
- Kept runtime dictionaries, Ukrainian fallback behavior and existing campaign settings unchanged.

## 1.4.1
- Fixed a critical admin-page error caused by another KOZ plugin loading the shared localization helper first.
- Standardized the shared runtime localization helper contract across KOZ plugins.
- Kept all settings, generated package metadata and legacy migration compatibility unchanged.

## 1.4.0
- Added runtime administration localization for 10 languages with English fallback.
- Integrated the current KOZ Suite menu, overview and support panel.
- Preserved legacy settings and package metadata for seamless migration.

- Rebranded as KOZ Google Ads Campaign Builder.
- Changed the public slug and text domain to `koz-google-ads-campaign-builder`.
- Declared Tony Kozyriev / `ramirkz` as independent developer and owner.
- Replaced inline support-panel assets with WordPress enqueue functions.
- Removed bundled PO and MO translation files.
- Rewrote public documentation and separated developer support from UA FREE charitable donations.

## 1.3.7

- Redesigned the shared UA FREE support panel.
- Moved the panel into the WordPress admin content area to prevent overlap with the core footer.
- Added compact wallet rows and accessible copy buttons.
- Added PayPal developer donations via `kozyriev@uafree.org`.
- Preserved all plugin-specific functionality.

## 1.3.6

- Removed redundant URL decoding from the admin notice message and completed Plugin Check sanitization.

## 1.3.5

- Replaced dynamic translation domains with the required literal text domain.
- Added translators comments for placeholder strings.
- Sanitized form and notice inputs before processing.
- Replaced direct download and CSV stream operations with WordPress-compatible handling.
- Removed discouraged manual textdomain loading and prefixed uninstall globals.
- Updated WordPress compatibility metadata.

## 1.3.4

- Final repository and WordPress.org packaging release.
- Updated version, readme metadata, compatibility fields and license headers.
- No custom update checker or UA FREE usage telemetry added.

## 1.3.1

- Automatic mode now creates the site-language campaign plus one English universal campaign.
- The primary campaign targets the country detected from the WordPress locale.
- The English campaign targets GB, US, CA, AU, IE and NZ.
- Ukraine is no longer hardcoded or silently applied to every language.
- Legacy wildcard countries are ignored unless Manual geography is explicitly selected.
- Manual languages and countries remain optional overrides.
- Settings and manifest show the automatic language/country decision.
- Existing one-file import and automatic negatives/callouts remain unchanged.
- Campaigns remain Paused.

## 1.2.9-dev

- Restored automatic negative keywords and callouts.