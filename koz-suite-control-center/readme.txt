=== KOZ Suite Control Center ===
Contributors: ramirkz
Donate link: https://github.com/ramirkoz/ua-free-plugins#support-plugin-development
Tags: admin, dashboard, diagnostics, plugin suite, privacy
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.4.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Unified KOZ Suite plugin overview, status dashboard, navigation and privacy-safe JSON inventory.

== Description ==

KOZ Suite Control Center is an independently installable WordPress plugin that brings KOZ plugin status and navigation together in one administration dashboard. It shows which KOZ tools are installed and active, opens their administration pages, and exports a privacy-safe JSON inventory.

The plugin makes no third-party requests, stores no visitor data and creates no database tables. Its optional public exposure scan reads only selected same-site public pages and does not submit forms or expose secret values.

The suite began as production tooling for the UA FREE charitable foundation website. This plugin is developed and maintained independently by Tony Kozyriev (`ramirkz`); developer support and charitable donations to the foundation are separate.

= Part of the KOZ Suite =

KOZ Suite Control Center is **the shared overview module of the broader KOZ Suite**, a family of independent WordPress plugins that share a common KOZ administration experience and visual identity.

Each KOZ plugin remains independently installable, so site owners can use only the modules they need or combine several modules into one toolkit. The Control Center does not require every KOZ plugin to be installed.

Current public KOZ plugins:

* [KOZ SEO Core](https://wordpress.org/plugins/koz-seo-core/) — technical SEO, schema, sitemaps, AI discovery and OpenAI Vision image ALT.
* [KOZ Copy Actions](https://wordpress.org/plugins/koz-copy-actions/) — accessible copy-to-clipboard actions for selected WordPress content.
* [KOZ URL-Only Comment Spam](https://wordpress.org/plugins/koz-url-only-comment-spam/) — privacy-first local moderation for URL-only comment spam.
* [KOZ Static Translate](https://wordpress.org/plugins/koz-static-translate/) — AI-powered multilingual translation with Microsoft Azure AI Translator and static language routes.
* [KOZ Translate Diagnostics](https://wordpress.org/plugins/koz-translate-diagnostics/) — read-only health checks and privacy-safe diagnostic reports for KOZ Static Translate.
* [KOZ 404 Guard & URL Intelligence](https://wordpress.org/plugins/koz-404-guard/) — privacy-safe 404/410 diagnostics, controlled same-site redirects and URL intelligence.
* [KOZ Consent Manager](https://wordpress.org/plugins/koz-consent-manager/) — privacy-first visitor consent, optional script control and Google Consent Mode v2 synchronization.
* [KOZ Donate Stats & Conversions](https://wordpress.org/plugins/koz-donate-stats/) — local donation-journey analytics and consent-aware conversion events for WordPress.
* [KOZ Site Bridge](https://wordpress.org/plugins/koz-site-bridge/) — secure read-only WordPress diagnostics, controlled same-site probes and authenticated REST automation.
* KOZ Suite Control Center — shared KOZ status, navigation and privacy-safe inventory.

= Main features =

* KOZ Suite dashboard with plugin cards, installed/active status and links to plugin administration screens.
* Privacy-safe JSON export of the KOZ plugin inventory.
* Optional read-only inspection of selected same-site public pages for exposed private-download tokens or sensitive admin-post URLs.
* No direct updates, activation/deactivation of other plugins, or data modifications from the inventory or scan.
* Multilingual administration with English fallback.
* No visitor tracking, remote configuration or external telemetry.

= Example use cases =

* See which KOZ tools are installed or active without opening every plugin settings screen.
* Navigate between independent KOZ plugin administration pages.
* Export a limited environment overview for private troubleshooting and maintenance.

= Privacy =

Control Center does not store visitor data or create new database tables. The optional public-page scan reads only same-site pages, reports limited paths and parameter names, and never submits forms or discloses secret parameter values.

= Support =

* Support independent plugin development through the project repository: https://github.com/ramirkoz/ua-free-plugins
* Support Ukraine through the UA FREE charitable foundation: https://uafree.org/donate/
* Development contact: ramir@ua.fm
* Developer profile: https://www.linkedin.com/in/tonykoz/

Support is optional. All included plugin features remain available without payment.

== Installation ==

1. Upload KOZ Suite Control Center through Plugins > Add New Plugin > Upload Plugin.
2. Activate the plugin.
3. Open KOZ Suite > Control Center to view the available KOZ tools.
4. Optionally export the privacy-safe inventory or run the controlled public exposure scan.

== Frequently Asked Questions ==

= Do I need to install all KOZ plugins? =

No. KOZ Suite plugins are individually installable. The Control Center shows whichever supported modules are available on your WordPress site.

= Does Control Center change other plugins? =

No. The inventory and public exposure scan are read-only. Control Center provides status and navigation rather than changing plugin settings or activation state.

= Does it send site information to an external service? =

No. The inventory export is downloaded locally by the administrator and the optional exposure scan requests only same-site public pages.

= Is this an official UA FREE foundation plugin? =

No. The suite grew from tools used on the foundation website, but the software is independently owned and maintained by Tony Kozyriev.

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
