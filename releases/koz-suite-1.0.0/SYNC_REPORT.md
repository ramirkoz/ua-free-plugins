# KOZ Suite 1.0.0 — Final Synchronization Report

Date: 2026-08-04

## Artifact status

- KOZ Suite Bundle 1.0.0: ZIP / CRC / SHA / manifest / 12 embedded packages PASS
- Bundle SHA-256: `27fd424bc837c5f2286123ffc69c12eafac67ac1e0991a91704ebd9a1e050ebc`
- One canonical ZIP per plugin: PASS
- Google Drive bundle and GitHub release metadata: MATCH
- KOZ Copy Actions 1.1.7 live functionality and Plugin Check: PASS
- WordPress.org package set: READY / NOT SENT

## Private Hub

- KOZ Suite Hub — Private 0.4.1 package: DRIVE SYNC PASS
- SHA-256: `5fee2f71f680feae51dd86e0f76a06b46be8c4973b3e505b88f719143b1ab080`
- Three escaping errors from 0.4.0: FIXED
- Production screenshots still show active Hub version 0.4.0
- Production Hub catalog still contains hashes from the superseded bundle import

## Production alignment required

1. Update KOZ Suite Hub — Private from 0.4.0 to 0.4.1.
2. Import the current KOZ Suite Bundle 1.0.0 with SHA-256 `27fd424b...50ebc`.
3. Update KOZ Google Ads Campaign Builder from 1.4.2 to 1.4.3.
4. Confirm the Hub catalog displays the current canonical hashes.
5. Run the final Hub Plugin Check and smoke test.

## WordPress.org

- Canonical submission file: `koz-copy-actions-1.1.7.zip`
- Requested slug: `koz-copy-actions`
- Existing application/email thread must be used.
- Reviewer reply is prepared and remains unsent until production alignment is complete.
