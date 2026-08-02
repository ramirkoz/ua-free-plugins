# Changelog

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
