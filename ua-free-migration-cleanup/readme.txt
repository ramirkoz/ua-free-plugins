=== UA FREE Migration & Cleanup ===
Contributors: uafree
Donate link: https://uafree.org/plugins/support-development/
Tags: migration, cleanup, diagnostics, database, snapshot
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 0.8.10
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Controlled snapshots, environment checks, migration and verified cleanup of plugin leftovers.

== Description ==

UA FREE Migration & Cleanup scans the actual WordPress environment instead of assuming that a fixed list of plugins or database objects exists.

The universal interface is read-only by default. It inventories installed plugins, database tables, autoload size and cron hooks. An administrator can inspect one installed plugin to find likely options, metadata, tables and scheduled hooks based on conservative naming heuristics.

Candidate matches are not treated as proof of ownership. Destructive cleanup requires a dedicated adapter, a dry run, a verified snapshot and explicit confirmation.

The plugin was originally created to solve real operational needs of a charitable foundation website and was later rebuilt as a universal WordPress tool.

= Main features =

* Universal installed-plugin inventory.
* Read-only environment snapshot export.
* Per-plugin candidate inspection.
* No option values or personal data in exported reports.
* Bilingual English and Ukrainian admin interface.
* Built-in overview of the wider UA FREE Plugin Suite.
* No bundled site-specific cleanup profiles or obsolete compatibility code.

= Support =

Support the charitable foundation: https://uafree.org/donate/

Support plugin development with cryptocurrency: https://uafree.org/plugins/support-development/

Development support is separate from charitable donations.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open the plugin settings or UA FREE Suite Control Center when configuration is required.

== Changelog ==

= 0.8.10 =
* Redesigned the shared UA FREE support panel.
* Moved the panel into the WordPress admin content area to prevent overlap with the core footer.
* Added compact wallet rows and accessible copy buttons.
* Added PayPal developer donations via kozyriev@uafree.org.
* Plugin-specific functionality is unchanged.

= 0.8.9 =
* Replaced variable translation domains with the literal plugin text domain required by Plugin Check.
* Added nonce validation or documented read-only request handling.
* Clarified direct read-only database queries for automated code analysis.
* Removed discouraged manual translation loading.
* Redesigned the shared UA FREE support block for desktop and mobile layouts.
* Normalized Tested up to metadata for WordPress.org.

= 0.8.8 =
* Final stable packaging for repository publication and WordPress.org submission.
* Updated plugin metadata and WordPress compatibility information.
* No custom update checker or UA FREE usage telemetry was added.

== Upgrade Notice ==

= 0.8.10 =
Shared admin support panel redesign; plugin-specific functionality is unchanged.

= 0.8.9 =
Plugin Check compatibility and shared support-block layout update.
