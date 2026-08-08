=== KOZ Copy Actions ===
Contributors: ramirkz
Tags: clipboard, copy, accessibility, privacy, tools
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.1.11
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accessible, privacy-safe copy-to-clipboard actions for WordPress.

== Description ==

KOZ Copy Actions adds configurable copy-to-clipboard actions to selected elements on public WordPress pages.

Features:

* Configurable CSS selectors.
* Optional path restrictions.
* Keyboard-accessible copy controls.
* Copy success/error feedback.
* No storage of copied values.
* No telemetry, analytics, cookies or external requests.
* Settings migration from the former UA FREE Copy package.
* Independent operation without a paid framework or external service.

The plugin originated from production work for the UA FREE charitable foundation and is independently maintained by Tony Kozyriev.

== Privacy ==

Copied values stay in the visitor browser. The plugin does not send copied values to the developer or any external service.

== Installation ==

1. Upload the plugin ZIP through Plugins -> Add New Plugin -> Upload Plugin.
2. Activate KOZ Copy Actions.
3. Open KOZ Suite -> Copy Actions.
4. Configure selectors and enable the frontend handler.

== Changelog ==

= 1.1.11 =
* Added the required translators comment for the diagnostics placeholder.
* Removed the Domain Path header because this package does not ship a local translations directory.

= 1.1.10 =
* Restored the real admin settings page to the stable plugin-specific slug koz-copy-actions.
* Removed the hidden compatibility redirect that could leave the WordPress admin content area blank.
* Kept all plugin-owned PHP symbols, options, hooks, handles and JavaScript globals under the unique kozcoac / KOZCOAC prefix and ramirkz\kozcopyactions namespace.

= 1.1.9 =
* Added a backward-compatible admin-page alias so existing links using page=koz-copy-actions open the current KOZ Copy Actions settings page instead of showing an access error.
* Primary internal page slug remains uniquely prefixed as kozcoac-copy-actions.

= 1.1.8 =
* Standardized every plugin-owned PHP symbol, option, hook, script handle, page slug and JavaScript global under the unique kozcoac prefix or ramirkz\kozcopyactions namespace.
* Replaced generic KOZ Suite identifiers inside this plugin with plugin-specific identifiers.
* Preserved migration from previous UA FREE Copy settings.
* Preserved frontend compatibility with existing UA FREE data attributes and copy-success event listeners.
* Updated the developer support block and LinkedIn contact.
