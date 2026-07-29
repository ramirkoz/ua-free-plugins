# Changelog

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
