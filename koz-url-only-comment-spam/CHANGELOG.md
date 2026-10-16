# Changelog

## 1.2.0

- New rules with their own action: links with very little text, Website field with very little text, hidden honeypot field, submitted too fast and missing comment form token.
- New action "Reject without saving".
- Links detected in HTML anchor targets, BBCode and Markdown; hxxp:// and example[.]com disguises are undone.
- Bare domain names count only with a known top-level domain (no more "Node.js" false positives).
- Spam reason column and filter on the Comments screen; daily statistics per rule and a dashboard widget.
- Optional automatic deletion of old spam (off by default).
- WooCommerce product reviews are checked by default; comment types are configurable.
- Legacy option migration runs only on activation and in the admin area.
- Removed PayPal from the developer support panel and corrected the BTC and USDT TRC-20 developer wallet addresses.

## 1.1.11
- Updated WordPress compatibility metadata for WordPress 7.1.
- No functional changes to spam detection, settings, counters or privacy behavior.

## 1.1.10
- Publish the current production-tested package as the first public SVN release after WordPress.org approval.
- Keep moderation rules, stored settings, aggregate counters and privacy behavior unchanged.
- Refresh public release metadata for WordPress.org.
## 1.1.9
- Fixed WordPress.org Stable tag metadata to match the plugin version.
- No functional changes to spam detection, settings, counters or support-panel behavior.

## 1.1.8
- Prevent duplicate support blocks when the shared KOZ Suite support panel is active.
- Add the About the foundation button to the standalone fallback support panel.

## 1.1.7

- Fixed duplicate KOZ support panel rendering by removing the second initialization path.
- No changes to moderation logic, settings, counters or compatibility behavior.

## 1.1.5

- Unified the namespace, global constants and KOZ public hooks under the exact `KOZURLOnlyCommentSpam` prefix.
- Preserved the original UA FREE compatibility hooks and existing settings.

## 1.1.2

- Aligned KOZ public hook names with the namespace-derived plugin prefix used by WordPress Plugin Check.
- Removed the file-scope uninstall variable by moving uninstall logic into a local closure.

## 1.1.1

- Updated public hook prefixes to the unique developer/plugin prefix required by WordPress Plugin Check.
- Prefixed the uninstall variable to satisfy WordPress global naming conventions.

## 1.1.0

- KOZ rebrand.
- Multilingual administration.
- Shared KOZ Suite navigation and support panel.
- Legacy settings and hooks preserved.
