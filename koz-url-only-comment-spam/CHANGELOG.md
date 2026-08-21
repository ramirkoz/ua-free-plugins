# Changelog

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
