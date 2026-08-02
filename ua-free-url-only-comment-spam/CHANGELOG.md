# Changelog

## 1.0.7

- Redesigned the shared UA FREE support panel.
- Moved the panel into the WordPress admin content area to prevent overlap with the core footer.
- Added compact wallet rows and accessible copy buttons.
- Added PayPal developer donations via `kozyriev@uafree.org`.
- Preserved all plugin-specific functionality.

## 1.0.6

- Resolved Plugin Check findings for readme metadata and request handling.
- Removed the obsolete manual translation loader.
- Sanitized the detector sample after nonce verification.
- Prefixed the uninstall settings variable.
- Reworked the shared UA FREE support block into responsive cards.
- Preserved URL-only comment detection and moderation behavior.

## 1.0.5

- Final repository and WordPress.org packaging release.
- Updated version, readme metadata, compatibility fields and license headers.
- No custom update checker or UA FREE usage telemetry added.

## 1.0.2
- Removes any legacy Tools or Settings menu entry.
- Keeps the canonical UA FREE → URL Comment Spam page.
- Preserves the existing option and frontend filtering behavior.

# Changelog

## 1.0.2

- Rebuilt the plugin with namespaced classes.
- Preserved the original aggregate counter and latest-detection option keys.
- Added enable/disable control.
- Added `spam` and `hold for moderation` actions.
- Added configurable minimum URL count.
- Added optional exemption for all logged-in users.
- Added same-site and trusted-domain exemptions.
- Added a no-storage detector test.
- Added English source strings and Ukrainian localization files.
- Added UA FREE Suite registry and read-only status API.
- Changed uninstall behavior to keep data unless explicit deletion is enabled.
- Added privacy-safe integration hook for the future Analytics Dashboard.
