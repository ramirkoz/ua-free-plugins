=== KOZ Migration & Cleanup ===
Contributors: ramirkz
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=kozyriev%40uafree.org&item_name=Support+KOZ+WordPress+plugin+development&currency_code=USD
Tags: migration, cleanup, diagnostics, database, snapshot
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 0.9.7
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-safe WordPress environment snapshots and conservative inspection of likely plugin leftovers.

== Description ==

KOZ Migration & Cleanup inventories installed plugins, WordPress database tables, scheduled hooks and autoload size. It can export privacy-safe JSON snapshots, inspect likely plugin-related leftovers, and identify migration candidates such as placeholder pages, legacy attachment links and old slugs.

The scanner is read-only. Candidate matches are not treated as proof of ownership, and this release does not delete data or change plugin state during inspection.

The plugin was originally developed for the UA FREE charitable foundation website, which became its first production and testing environment. The software is independently owned and maintained by Tony Kozyriev (`ramirkz`).

= Main features =

* Read-only inventory of installed plugins, database tables, cron hooks and autoload size.
* Environment snapshot export without option values or personal data.
* Conservative per-plugin candidate inspection for options, metadata, tables and scheduled hooks.
* Snapshot hashes for comparing exported reports.
* Read-only placeholder-page inventory without exporting page content.
* Privacy-safe detection of legacy `?attachment_id=` links with hashed attachment identifiers.
* Suggested old-slug to current-path redirect map from WordPress `_wp_old_slug` history; no redirects are created automatically.
* Independent operation without telemetry, remote services or a required suite framework.

= Privacy =

Exports omit option values and personal data. The plugin does not add telemetry or external requests.

= Support =

Developer support and charitable donations to UA FREE are displayed as separate optional destinations in the administration panel. No feature is restricted by payment.

== Installation ==

1. Deactivate the former UA FREE Migration & Cleanup package, but keep it installed until verification is complete.
2. Upload and activate KOZ Migration & Cleanup.
3. Open KOZ Suite > Migration & Cleanup.
4. Verify the environment inventory and export a snapshot.
5. Remove the former package only after the KOZ version works correctly.

== Frequently Asked Questions ==

= Does this version delete plugin data? =

No. Version 0.9.5 scans and exports reports. Matches and redirect mappings are candidates that require separate verification.

= Are option values or personal data included in exports? =

No. The exported inventory contains names, counts, sizes, status information and hashes, but not option values or personal data.

= Is this an official UA FREE foundation plugin? =

No. It originated from production work for the foundation website but is independently owned and maintained by Tony Kozyriev.

== Changelog ==

= 0.9.7 =
* Updated WordPress compatibility metadata for WordPress 7.1.
* No functional changes.

= 0.9.5 =
* Added read-only placeholder-page detection for migration cleanup.
* Added privacy-safe inventory of legacy `?attachment_id=` links without exporting raw attachment IDs.
* Added a suggested old-slug redirect map based on WordPress `_wp_old_slug` history; automatic redirects remain disabled.
* Added migration-candidate counts to the Overview screen and environment snapshot.

= 0.9.4 =
* Moved plugin-owned PHP symbols, admin actions, cache keys, asset handles and JavaScript globals to the unique `kozmig / KOZMIG` prefix.
* Added the `ramirkz\kozmig` namespace and a plugin-specific KOZ Suite fallback menu identifier.
* Preserved the stable `koz-migration-cleanup` admin page and legacy package detection without changing scan or export behavior.
* Normalized the standalone support panel and prevented duplication when a shared KOZ support panel is already active.

= 0.9.3 =
* Removed the redundant About & Support tab.
* Kept the shared support panel below the functional tabs.
* Removed obsolete tab-only scripts and styles.

= 0.9.2 =
* Rebuilt the About & Support tab as a single compact WordPress-native panel.
* Removed duplicated support blocks and the extra shared footer on that tab.
* Added localized copy controls through enqueued assets.

= 0.9.1 =
* Rebranded the public package as KOZ Migration & Cleanup.
* Added KOZ Suite navigation and current package status detection.
* Added bundled runtime administration dictionaries for ten languages with English fallback.
* Preserved legacy internal identifiers required for migration compatibility.
* Moved administration styling and the shared support panel to enqueued assets.
* Added safe deactivation of the former package without deleting its files or data.

= 0.8.10 =
* Last stable release under the former UA FREE public branding.

== Upgrade Notice ==

= 0.9.7 =
WordPress 7.1 compatibility metadata update; plugin behavior remains unchanged.

= 0.9.5 =
Adds read-only legacy content and redirect-map diagnostics; no content or redirects are changed automatically.

= 0.9.4 =
Strict WordPress.org prefix re-audit release; scan and export behavior remains read-only.

= 0.9.3 =
Removes the duplicate support tab without changing scan data or settings.

= 0.9.2 =
Updates the About & Support layout without changing scan data or settings.

= 0.9.1 =
The public plugin folder and slug changed. Verify the KOZ package before removing the former UA FREE package.
