# Changelog

## 0.2.13

- Resolved Plugin Check findings for dynamic read-only database diagnostics.
- Switched table and column identifiers to prepared `%i` placeholders.
- Documented intentional live read-only database queries.
- Added missing translator comments and WordPress-safe JSON export.
- Removed manual translation loading and normalized compatibility metadata.
- Reworked the shared UA FREE support block into responsive cards.

## 0.2.12

- Final repository and WordPress.org packaging release.
- Updated version, readme metadata, compatibility fields and license headers.
- No custom update checker or UA FREE usage telemetry added.

## 0.2.9

- Replaced the broad version character allowlist with a strict numeric version grammar and controlled release labels.
- Rejected arbitrary words, opaque tokens, credentials, hexadecimal keys and values with surrounding whitespace in version-option fields.
- Removed the generic sensitive-key exemption for version options; both recursive privacy passes now revalidate only the two exact report fields.
- Added negative version fixtures and ZIP-to-reviewed-source byte identity validation for every packaged file.

## 0.2.7-dev

- Preserved safe database and rewrite version values in privacy-filtered reports.
- Normalized both version-option values through the strict version allowlist.
- Added UNC and backslash-relative path redaction coverage.
- Added regression tests for version preservation and the extra Windows path forms.

## 0.2.6-dev

- Fixed Windows path redaction with a PHP/PCRE-safe pattern.
- Redacted complete Authorization Bearer and Basic credential values.
- Expanded privacy handling for arbitrary table identifiers and plugin-relative paths.
- Restricted third-party report filters to the existing report shape and numeric/boolean values.
- Removed the stale undefined API-status reference from the lightweight public contract.
- Added an export-only strict boundary that fingerprints all free-text diagnostic fields.
- Increased the privacy boundary contract to version 3.

## 0.2.5-dev

- Rebuilt quick mode to avoid full-table aggregates and hash joins.
- Added request-local table metadata caches to reduce repeated schema queries.
- Added an allowlisted translator public-status boundary.
- Removed raw plugin-relative paths, raw table names, raw cron hooks and raw transient names from reports.
- Strengthened redaction for URLs, domains, filesystem paths, international IBANs, formatted card numbers and credential-like values.
- Added a final privacy pass after third-party report filters.
- Versioned the lightweight Suite status contract and marked estimated fields explicitly.

## 0.2.4-dev

- Added quick and explicit deep diagnostic modes.
- Removed the expensive hash join from the default administration screen.
- Made the Suite `public_status()` lightweight instead of generating a full report.
- Added compatibility fields for migration freeze, content scope and pause reasons.
- Replaced raw site host, table names and source paths in reports with salted fingerprints.
- Escaped table names used in `SHOW ... LIKE` checks.
- Kept the plugin strictly read-only with no external requests or persistent data.

## 0.2.3-dev

- Unified the Suite menu registration through the canonical Registry.
- Passed the shared behavioral integration harness for all 12 components.

## 0.2.2-dev

- Added canonical package identity, cross-plugin integration fixes and collision prevention.
- Added the shared UA FREE Suite registry implementation.
