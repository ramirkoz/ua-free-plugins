# Changelog

## 0.8.7

- Redesigned the shared UA FREE support panel.
- Moved the panel into the WordPress admin content area to prevent overlap with the core footer.
- Added compact wallet rows and accessible copy buttons.
- Added PayPal developer donations via `kozyriev@uafree.org`.
- Preserved all plugin-specific functionality.

## 0.8.6

- Resolved Plugin Check findings for plugin-owned translation database workflows.
- Sanitized request URI handling and used WordPress-recognized XML escaping.
- Removed manual text-domain loading and normalized `Tested up to` metadata.
- Reworked the shared UA FREE support block into responsive cards.
- Translation behavior, routes and database schema remain unchanged.

## 0.8.5

- Final repository and WordPress.org packaging release.
- Updated version, readme metadata, compatibility fields and license headers.
- No custom update checker or UA FREE usage telemetry added.

## 0.8.2

- Audited internal navigation on translated pages.
- Rewrites menu links for both strict-ready and allowed provisional routes.
- Generalized the previous Donate-only PageLayer guard to all known internal
  page routes present in the rendered document.
- Preserves query strings and fragments.
- Leaves external links and unavailable translated routes unchanged.
- Preserves modified-click behavior for opening links in a new tab.
- Added per-request availability caching to avoid duplicate readiness checks.
- No schema change and no new Azure request path.

## 0.8.0-foundation-safe-dev

- Moved the administration page from Tools to UA FREE.
- Kept translated routes available during a confirmed monthly limit.
