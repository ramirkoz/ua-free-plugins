=== UA FREE Site Bridge ===
Contributors: uafree
Donate link: https://uafree.org/plugins/support-development/
Tags: diagnostics, rest api, automation, googlebot, adsbot
Requires at least: 6.2
Tested up to: 7.0.2
Stable tag: 0.4.8
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure read-only diagnostics API with controlled same-site HTTP probes.

== Description ==

UA FREE Site Bridge exposes a small authenticated read-only REST API.

Current features:
* UA FREE Plugin Suite integration;
* HMAC API keys with backward compatibility for 0.3 keys;
* no persistent API request log;
* no IP or User-Agent storage;
* explicit allowlist-only HTTP probes without query strings;
* allowlisted Googlebot and AdsBot User-Agent profiles;
* redirect-chain and selected Cloudflare/server headers;
* privacy-safe 404 Guard integration;
* no arbitrary URL input;
* no page, plugin, theme, user or option modifications.

The public OpenAPI schema is available without authentication. Every data endpoint requires the X-UAFree-Key header and HTTPS.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open the plugin settings or UA FREE Suite Control Center when configuration is required.

== Changelog ==

= 0.4.8 =
* Final stable packaging for repository publication and WordPress.org submission.
* Updated plugin metadata and WordPress compatibility information.
* No custom update checker or UA FREE usage telemetry was added.

== Upgrade Notice ==

= 0.4.8 =
Final stable release prepared for repository and WordPress.org distribution.
