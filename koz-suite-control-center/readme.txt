=== KOZ Suite Control Center ===
Contributors: ramirkz
Tags: admin, dashboard, diagnostics, plugin suite, privacy
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.4.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Unified status, navigation and privacy-safe reporting for the KOZ WordPress Suite.

== Description ==

KOZ Suite Control Center provides one overview for the twelve independent KOZ plugins. It reports installation and activation state, links to active plugin pages and exports a privacy-safe JSON inventory.

The plugin makes no third-party requests, stores no visitor data and creates no database tables. The optional public exposure scan requests only same-site public pages and does not submit forms or expose secret values.

The suite began as production tooling for the UA FREE charitable foundation website. Plugin ownership and developer donations are separate from the foundation's charitable work.

== Installation ==

1. Upload the plugin ZIP in Plugins > Add New > Upload Plugin.
2. Activate KOZ Suite Control Center.
3. Open KOZ Suite > Control Center.

== Changelog ==

= 0.4.7 =
* Updated WordPress compatibility metadata for WordPress 7.1.
* No functional changes.

= 0.4.6 =
* Adds a read-only public exposure scan for tokenized private-download and sensitive admin-post URLs on likely public plugin/download pages.
* Reports only paths, action names and sensitive parameter names; secret values are never displayed or stored.

= 0.4.5 =
* Prevents duplicate support blocks when the shared KOZ Suite support renderer is already active.

= 0.4.4 =
* Restores the KOZ Suite landing page with plugin cards and Open buttons as the top-level destination.
* Keeps Control Center as a separate submenu and preserves the previous direct Control Center URL.


= 0.4.3 =
* Uses `kozsuitecc-suite` as the actual registered KOZ Suite top-level page and Control Center route.
* Keeps `kozsuitecc-control-center` and `koz-suite-control-center` as compatibility routes.
* Fixes access-denied when opening the KOZ Suite top-level menu after upgrading from earlier Control Center builds.

= 0.4.2 =
* Makes the Control Center page itself the canonical KOZ Suite root so the top-level menu always opens a registered page.
* Registers legacy Control Center URLs under the real parent before hiding them, preventing WordPress permission errors.
* Keeps one KOZ Suite top-level menu for the suite.

= 0.4.1 =
* Uses or creates a single KOZ Suite top-level menu and prevents duplicate suite roots.
* Moves current internal identifiers to the `kozsuitecc` prefix while retaining the previous dashboard URL as a compatibility route.
* Rebranded the former suite control center as KOZ Suite Control Center.
* Added a corrected twelve-plugin KOZ catalog and reliable active-state detection.
* Added multilingual administration and English fallback.
* Added privacy-safe JSON export and legacy telemetry cleanup.
