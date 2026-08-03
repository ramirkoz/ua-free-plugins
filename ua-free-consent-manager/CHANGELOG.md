# Changelog

## 0.1.7

- Added `UAFreeConsentManager.allows()` and `getStatus()` browser APIs.
- Added compatibility aliases for local UA FREE integrations.
- Emits the canonical `uafree:consent-updated` event and preserves the legacy event.
- Fixed interoperability with UA FREE Donate Stats & Conversions and guarded duplicate browser initialization.

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
- Додано UA FREE Suite Registry.
- Persistent consent logging, telemetry та remote configuration відсутні.
