=== KOZ Site Bridge ===
Contributors: ramirkz
Donate link: https://github.com/ramirkoz/ua-free-plugins
Tags: diagnostics, rest api, automation, googlebot, adsbot
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 0.5.7
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure read-only diagnostics API with controlled same-site HTTP probes.

== Description ==

KOZ Site Bridge is an independently installable WordPress plugin developed and maintained by Tony Kozyriev. It provides authenticated read-only diagnostics and controlled same-site HTTP probes for private site analysis and automation. The plugin grew from production tooling used on the UA FREE charitable foundation website.

= Part of the KOZ Suite =

KOZ Site Bridge is **one focused module of the broader KOZ Suite**, a family of independent WordPress plugins that share a common KOZ administration experience and visual identity.

Each KOZ plugin remains independently installable, so site owners can use only the modules they need or combine several modules into one toolkit.

Current public KOZ plugins:

* [KOZ SEO Core](https://wordpress.org/plugins/koz-seo-core/) — technical SEO, schema, sitemaps, AI discovery and OpenAI Vision image ALT.
* [KOZ Copy Actions](https://wordpress.org/plugins/koz-copy-actions/) — accessible copy-to-clipboard actions for selected WordPress content.
* [KOZ URL-Only Comment Spam](https://wordpress.org/plugins/koz-url-only-comment-spam/) — privacy-first local moderation for URL-only comment spam.
* [KOZ Static Translate](https://wordpress.org/plugins/koz-static-translate/) — AI-powered multilingual translation with Microsoft Azure AI Translator and static language routes.
* [KOZ Translate Diagnostics](https://wordpress.org/plugins/koz-translate-diagnostics/) — read-only health checks and privacy-safe diagnostic reports for KOZ Static Translate.
* [KOZ 404 Guard & URL Intelligence](https://wordpress.org/plugins/koz-404-guard/) — privacy-safe 404/410 diagnostics, controlled same-site redirects and URL intelligence.
* [KOZ Consent Manager](https://wordpress.org/plugins/koz-consent-manager/) — privacy-first visitor consent, optional script control and Google Consent Mode v2 synchronization.
* [KOZ Donate Stats & Conversions](https://wordpress.org/plugins/koz-donate-stats/) — local donation-journey analytics and consent-aware conversion events for WordPress.
* KOZ Site Bridge — secure read-only WordPress diagnostics, controlled same-site probes and authenticated REST automation.

= Main features =

* authenticated read-only REST diagnostics;
* canonical KOZ REST namespace and X-KOZ-Key authentication;
* backward-compatible legacy endpoint and header during migration;
* HMAC API keys with one-time display;
* no persistent API request log, IP storage or User-Agent storage;
* safe same-site public frontend HTTP probes without query strings;
* allowlisted Googlebot and AdsBot User-Agent profiles;
* redirect-chain and selected Cloudflare/server headers;
* rendered public-page audit for title, description, canonical, robots, hreflang, headings, image alt state, links, schema and document language;
* sitemap inventory and batched sitemap audit with concrete per-URL findings;
* privacy-safe KOZ 404 Guard integration;
* KOZ Suite inventory and navigation;
* multilingual administration with English fallback;
* no arbitrary URL input and no content, plugin, theme or user modifications.

Existing Site Bridge API keys are preserved. The old REST namespace and X-UAFree-Key header remain accepted during migration, while new integrations should use /wp-json/kozbridge/v1 and X-KOZ-Key. The previous /wp-json/koz-site-bridge/v1 route also remains available.

= Access and security =

* The X-KOZ-Key is generated locally by the site administrator and is used only to authenticate the read-only REST API. It does not control feature availability.
* Every built-in plugin feature is available to every installation.
* The authenticated API has a security/abuse throttle of 120 successful requests per hour per key. This protects the WordPress site and applies equally to all authenticated clients.
* The OpenAPI schema endpoint is intentionally public so an administrator can import the schema into a REST client. It exposes only the API contract and does not return private site data, credentials, settings, content, or logs. All diagnostic data endpoints require X-KOZ-Key authentication.
* KOZ Site Bridge does not connect to an external processing service. Its HTTP probes are restricted to the current WordPress site. External REST clients or automation tools are optional and chosen by the site administrator.

== Installation ==

1. If the legacy UA FREE Site Bridge plugin is active, deactivate it manually and keep it installed until verification is complete. KOZ Site Bridge does not deactivate other plugins automatically.
2. Upload and activate the KOZ plugin ZIP.
3. Open KOZ Suite > Site Bridge.
4. Verify the existing key or rotate it, then test Ping and OpenAPI.

== Changelog ==

= 0.5.7 =
* Removed automatic deactivation of the legacy UA FREE Site Bridge plugin; activation state remains under administrator control.
* Replaced the generic public REST permission callback on the OpenAPI schema with an explicit plugin callback documenting the intentionally public, schema-only endpoint.
* Clarified that X-KOZ-Key is a local authentication credential, does not control feature availability, and that the hourly request throttle is security/abuse protection only.
* No diagnostic endpoint was made public; all site-data endpoints remain authenticated.

= 0.5.6 =
* Updated WordPress compatibility metadata for WordPress 7.1.
* No functional or API behavior changes.

= 0.5.5 =
* Replaced direct PHP log file handles with the WordPress Filesystem API for Plugin Check compliance.
* Preserved the read-only admin error-log endpoint, fixed candidate allowlist and privacy redaction.
* No changes to API keys, routes or existing diagnostics behavior.

= 0.5.4 =
* Added an authenticated read-only admin error-log diagnostic endpoint for private troubleshooting.
* Reads only fixed WordPress/PHP log candidates, never an arbitrary path supplied by the caller.
* Redacts credentials, cookies, email addresses and local filesystem prefixes before returning recent log lines.
* Preserved all existing Site Bridge endpoints, API-key compatibility and read-only behavior.

= 0.5.3 =
* Added rendered public-page audit and sitemap inventory/batch audit.
* Expanded probes to safe public frontend routes while keeping WordPress administration, REST, login and executable paths blocked.
* Added DOM-independent parsing fallback and preserved the read-only/privacy-safe contract.

= 0.5.2 =
* Fixed KOZ Suite submenu registration so the existing Site Bridge admin URL remains accessible when another KOZ plugin owns the suite menu.
* Removed a duplicate Site Bridge catalog entry.

= 0.5.1 =
* WordPress.org strict prefix re-audit with canonical `kozbridge / KOZBRIDGE` identifiers.
* Existing API-key options migrate automatically without deleting historical values.
* Added canonical `/wp-json/kozbridge/v1` routes while preserving both earlier REST namespaces.
* Plugin-specific runtime, suite fallback, admin assets and support identifiers.

= 0.5.0 =
* Rebranded as KOZ Site Bridge.
* Added KOZ Suite integration and multilingual administration.
* Added canonical KOZ REST namespace and X-KOZ-Key authentication.
* Preserved legacy API keys, endpoint and header compatibility.
* Replaced site-specific probe paths with a generic safe allowlist.
* Added a dedicated enqueued admin interface and shared KOZ support panel.

== Upgrade Notice ==

= 0.5.7 =
Keeps legacy-plugin activation under administrator control and clarifies the security/authentication model for WordPress.org review.

= 0.5.6 =
WordPress 7.1 compatibility metadata update; no functional changes.

= 0.5.5 =
Uses the WordPress Filesystem API for the read-only error-log diagnostic while preserving endpoint behavior.

= 0.5.4 =
Adds a privacy-redacted read-only PHP/WordPress error-log diagnostic endpoint for private troubleshooting.

= 0.5.3 =
Adds safe rendered SEO/page auditing and sitemap-driven per-URL diagnostics for private automation.

= 0.5.2 =
Fixes Site Bridge admin-page access under the shared KOZ Suite menu.

= 0.5.1 =
Strict-prefix re-audit with preserved API keys and REST compatibility.

= 0.5.0 =
KOZ rebrand with preserved API-key compatibility and a new canonical endpoint/header.
