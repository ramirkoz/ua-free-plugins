## 0.2.12
- Updated WordPress compatibility metadata for WordPress 7.1.
- No functional changes.

# KOZ Consent Manager changelog


## 0.2.10

- Prevent duplicate admin support panels when the shared KOZ Suite support panel is active.
- Replace the legacy standalone footer panel with the current KOZ support layout, including Donate to UA FREE and About the foundation actions.
- No changes to consent behavior, stored settings, cookies or integrations.

## 0.2.8

- Removed the dynamic legacy integration hook that triggered WordPress Plugin Check `DynamicHooknameFound`; current integrations use only the canonical `kozconsent_integrations` hook.
- Hardened all current plugin-owned PHP, WordPress, JavaScript and admin identifiers to the plugin-specific `kozconsent` / `KOZCONSENT` prefix.
- Moved PHP classes into the `ramirkz\kozconsent` namespace and removed shared suite registry identifiers from the standalone package.
- Added automatic migration from the former settings key and legacy consent cookie fallback without keeping old names as current identifiers.
- Kept the multilingual runtime dictionaries with a plugin-specific runtime class.

- Added separate consent-banner templates for Ukrainian, English, Chinese, Spanish, Arabic, Indonesian, Portuguese, French, Japanese, German and Hindi.
- The frontend selects the template by current WordPress page locale and falls back to English for unsupported locales.
- Existing single-language settings are preserved under the site language during migration.
- The administration screen edits the template matching the current WordPress user language.

## 0.2.6

- Added runtime localization for ten supported non-English WordPress locales with English fallback.
- Localized new-install consent-banner defaults while preserving existing saved text.
- Retained the 0.2.4 frontend fatal-error fix.

# Changelog

## 0.2.9
- Removed the dynamic legacy integration filter invocation flagged by WordPress Plugin Check.
- Canonical `kozconsent_integrations` filter remains unchanged.

## 0.2.4

- Fixed a frontend fatal error caused by a legacy undefined asset URL constant.


## 0.2.3

- Corrected the KOZ Suite overview to detect only KOZ packages as KOZ plugins.
- Added working Open buttons for every active KOZ plugin with a settings page.
- Legacy UA FREE packages are now reported separately instead of appearing as active KOZ plugins.

- Changed the KOZ Suite menu icon to `dashicons-layout`.
- Updated namespaces to the full plugin-specific `KOZConsentManager` prefix required by WordPress Plugin Check.

## 0.2.1

- Rebranded as KOZ Consent Manager.
- Changed the public slug and text domain to `koz-consent-manager`.
- Declared Tony Kozyriev / `ramirkz` as independent developer and owner.
- Replaced inline support-panel assets with WordPress enqueue functions.
- Removed bundled PO and MO translation files.
- Rewrote public documentation and separated developer support from UA FREE charitable donations.

## 0.1.7

- Added `UAFreeConsentManager.allows()` and `getStatus()` browser APIs.
- Added compatibility aliases for local UA FREE integrations.
- Emits the canonical `uafree:consent-updated` event and preserves the legacy event.
- Fixed interoperability with KOZ Donate Stats & Conversions and guarded duplicate browser initialization.

## 0.1.6

- Redesigned the shared UA FREE support panel.
- Moved the panel into the WordPress admin content area to prevent overlap with the core footer.
- Added compact wallet rows and accessible copy buttons.
- Added PayPal developer donations via `kozyriev@uafree.org`.
- Preserved all plugin-specific functionality.

## 0.1.5

- Final repository and WordPress.org packaging release.
- Updated version, readme metadata, compatibility fields and license headers.
- No custom update checker or UA FREE usage telemetry added.

## 0.1.2
- Fixed frontend initialization when footer scripts are printed before consent markup.
- JavaScript now waits for DOMContentLoaded when required.
- Added server-rendered visibility fallback: fresh visitors see the banner even if JavaScript is delayed.
- No consent schema, cookie, admin settings, integration API or privacy contract changes.

## 0.1.2

- Створено privacy-first consent banner.
- Optional categories за замовчуванням вимкнені.
- Додано versioned first-party cookie без персональних ідентифікаторів.
- Додано local integration allowlist лише для WordPress script handles.
- Додано cache-safe client-side loader: optional handles ніколи не потрапляють у server-rendered output.
- Додано публічний read-only status contract.
- Додано KOZ Suite Registry.
- Persistent consent logging, telemetry та remote configuration відсутні.
