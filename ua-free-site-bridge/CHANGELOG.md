# Changelog

## 0.4.8

- Final repository and WordPress.org packaging release.
- Updated version, readme metadata, compatibility fields and license headers.
- No custom update checker or UA FREE usage telemetry added.

## 0.4.5
- Revalidated every redirect target against the fixed safe probe allowlist.
- Protected `/links` with the same safe-path policy.
- Disabled automatic redirects while fetching pages for link extraction.
- Replaced accumulating hourly options with expiring transients.
- Made the rate counter saturating: no writes after request 120.
- Added focused tests for redirect, link and 10,000 over-limit requests.

## 0.3.0
- OpenAPI 3.1.0 compatibility.
- Concrete schema object for GPT Actions.

## 0.2.0
- Explicit response schemas.

## 0.1.0
- Initial read-only release.
