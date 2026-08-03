# Changelog

## 1.2.10

- Added automatic discovery and tracking of active UA FREE Static Translate routes for selected source pages.
- Shows generated language routes directly below the selected WordPress page in the settings list.
- Keeps manually entered local paths separate and preserves existing settings.

## 1.2.9

- Fixed the browser contract with UA FREE Consent Manager.
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

- Kept the canonical product name UA FREE Donate Stats & Conversions untranslated in WordPress plugin metadata.
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
