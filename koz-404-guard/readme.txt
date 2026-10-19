=== KOZ 404 Guard & URL Intelligence ===
Contributors: ramirkz
Donate link: https://github.com/ramirkoz/ua-free-plugins
Tags: 404, redirect, broken links, 410, 404 monitor
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 2.2.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

404 log with one-click fixes, same-site redirects, 410 rules and redirects on address changes. No IP addresses stored.

== Description ==

KOZ 404 Guard & URL Intelligence finds the addresses on your site that return 404 and helps you fix them, without collecting personal data.

= What it does =

* **404 log with actions.** Every missing address is recorded once and counted. You see the path, how often it is requested, whether a visitor or a bot asked, and which page of your site links to it. One click creates a redirect (with a suggested target), a 410 rule, or hides the row.
* **Redirects.** Same-site 301, 302, 307 and 308 redirects for exact paths or whole sections (path prefix), optionally keeping the query string. Hit counters, editing, turning rules off, CSV import and export. Loops, duplicates and redirects of WordPress system paths are rejected.
* **Address changes.** When a published post or page changes its slug, parent or date, the plugin suggests a 301 redirect from the old address, or creates it automatically if you prefer.
* **410 rules.** Tell search engines that content was removed on purpose: exact path, path prefix, query key or query key=value.
* **Lightweight 404 responses.** Missing images, scripts, styles and fonts get a plain 404 instead of a full theme page. Obvious bots get a small page without the theme.
* **Internal link check.** Scans recently changed content for links to addresses that do not exist and shows which of them already have 404 hits.
* **Site Health and dashboard.** A Site Health test lists internal links that lead to 404; a dashboard widget shows the top 404 addresses of the week.
* **Multilingual sites.** Works with KOZ Static Translate language routes and stops WordPress from guessing /de/old-page/ into an unrelated page.

= Privacy =

* No IP address, User-Agent, query value or external referrer is ever stored.
* Paths and the linking page of your own site are stored only while "Show paths" is on (default). Turning it off removes them and keeps salted fingerprints only.
* The JSON report contains counters and salted fingerprints only.
* No external requests, no telemetry. Data is kept when the plugin is deleted.

KOZ 404 Guard & URL Intelligence is an independent WordPress plugin developed and maintained by Tony Kozyriev. It grew from production tooling used on the UA FREE charitable foundation website.

= Part of the KOZ Suite =

KOZ 404 Guard & URL Intelligence is **one focused module of the broader KOZ Suite**, a family of independent WordPress plugins that share a common KOZ administration experience and visual identity.

Each KOZ plugin remains independently installable, so site owners can use only the modules they need or combine several modules into one toolkit.

Current public KOZ plugins:

* [KOZ SEO Core](https://wordpress.org/plugins/koz-seo-core/) — technical SEO, schema, sitemaps, AI discovery and OpenAI Vision image ALT.
* [KOZ Copy Actions](https://wordpress.org/plugins/koz-copy-actions/) — accessible copy-to-clipboard actions for selected WordPress content.
* [KOZ URL-Only Comment Spam](https://wordpress.org/plugins/koz-url-only-comment-spam/) — privacy-first local moderation for URL-only comment spam.
* [KOZ Static Translate](https://wordpress.org/plugins/koz-static-translate/) — AI-powered multilingual translation with Microsoft Azure AI Translator and static language routes.
* [KOZ Translate Diagnostics](https://wordpress.org/plugins/koz-translate-diagnostics/) — read-only health checks and privacy-safe diagnostic reports for KOZ Static Translate.
* KOZ 404 Guard & URL Intelligence — privacy-safe 404/410 diagnostics, controlled same-site redirects and URL intelligence.
* [KOZ Consent Manager](https://wordpress.org/plugins/koz-consent-manager/) — privacy-first visitor consent, optional script control and Google Consent Mode v2 synchronization.
* [KOZ Donate Stats & Conversions](https://wordpress.org/plugins/koz-donate-stats/) — local donation-journey analytics and consent-aware conversion events for WordPress.
* [KOZ Site Bridge](https://wordpress.org/plugins/koz-site-bridge/) — secure read-only WordPress diagnostics, controlled same-site probes and authenticated REST automation.

== Installation ==

1. Install and activate the plugin.
2. Open KOZ Suite > 404 Guard.
3. Check the Settings tab: the log, lightweight responses and address-change redirects are on by default.
4. Come back to the 404 log after a few days and fix the most requested addresses.

== Frequently Asked Questions ==

= Does it create redirects automatically? =

Only if you choose "Create the 301 redirect automatically" for address changes. By default, redirects for changed addresses are suggested and wait for your approval; nothing else is created automatically.

= Does it slow down the site? =

Settings and the enabled rules are loaded with the other autoloaded options, so normal page views need no extra query. A 404 hit is one database query. Missing files are answered without rendering the theme.

= Why are logged-in administrators not redirected or logged? =

So that you can open and fix pages that have rules without being redirected away. Check rules in a private window.

= What does the log store? =

Status, path (while "Show paths" is on), query parameter names, visitor or bot, page type, the page of this site that linked to it, counters and dates. Never IP addresses, User-Agents, query values or other sites' addresses.

= Can I move my redirects from another plugin? =

Export them as CSV with the columns source, target, status, type, preserve_query, enabled and import the file on the Redirects tab.

== Changelog ==

= 2.2.0 =
* Fixed: 410 rules for a query key or key=value now also apply to the home page (for example ?pagelayer-template=…); only WordPress system routes are excluded.
* Fixed: addresses in Cyrillic and other non-Latin scripts were cut to dashes (/звіт-на/ became /--/) because percent-encoded characters were stripped. Paths are now decoded, so rules and the log match and show the real address.
* New: continuous 404 log in its own table, grouped by path. One database query per hit, daily cleanup by age (default 90 days) and size (default 2,000 rows). The ten-minute sampled capture window is removed.
* New: "Show paths" (on by default): the log shows the requested path, query parameter names and the page of this site that linked to it. IP addresses, User-Agents, query values and external referrers are still never stored; the JSON export still contains fingerprints only. Turning the option off removes stored paths.
* New: actions in the log: create a redirect (with a suggested target found by slug), add a 410 rule, ignore, delete. Filters: open 404, reached from this site, 410, redirected, visitors or bots, search, sort.
* New: redirects when a published entry changes its address (slug, parent or date): suggested for approval by default, or created automatically, or off.
* New: redirect rules can be edited and turned off, show hits and last hit, support path-prefix rules (moving a whole section) and keeping the query string, and can be imported and exported as CSV. 410 rules can be edited and turned off.
* New: missing static files (images, CSS, JS, fonts, media) get a plain 404 without rendering the theme.
* New: Site Health test for internal links that lead to 404, a KOZ 404 Guard section in Site Health info and a dashboard widget with the top 404 addresses of the last 7 days.
* Changed: the language guard for canonical redirects uses the languages of KOZ Static Translate when it is active; otherwise only two-letter codes (and codes with a region) count, so /faq/ or /app/ are no longer treated as languages.
* Changed: the internal link check no longer reports archives, categories, tags, authors, KOZ Static Translate language routes and addresses handled by a rule as broken, and shows how many 404 hits each link had.
* Changed: settings and a compact index of enabled rules are autoloaded; front-end requests need no extra database query when there are no rules.
* Changed: the admin screen is split into tabs (404 log, Redirects, 410 rules, Settings, Tools) and the main class into request handling, rules, log, address-change watch and Site Health classes.
* Changed: redirect targets follow the permalink trailing-slash setting, avoiding a second canonical redirect.
* Added Ukrainian translations for all new admin strings.
* Removed PayPal from the developer support panel and corrected the BTC and USDT TRC-20 developer wallet addresses (from 2.1.5).

= 2.1.5 =
* Removed PayPal from the developer support panel and corrected the BTC and USDT TRC-20 developer wallet addresses.

= 2.1.4 =
* Updated KOZ Suite documentation to include all eight current public plugins.
* Updated WordPress compatibility metadata for WordPress 7.1.
* No functional changes.

= 2.1.3 =
* Prevents WordPress canonical guessing from dropping a language-like namespace on 404 requests.
* Keeps stale translated routes as real 404 responses unless an explicit KOZ redirect rule exists.
* Preserves same-namespace canonical fixes.

= 2.1.2 =
* Separates Cloudflare/system-route 404s from real content misses.
* Classifies legacy ?attachment_id= 404s without storing the attachment ID.
* Shows the privacy-safe source classification in the grouped diagnostics table and export.
* Keeps system routes protected from redirect and 410 rules.

= 2.1.1 =
* Rebranded as KOZ 404 Guard & URL Intelligence.
* Added KOZ Suite integration and multilingual runtime administration.
* Preserved legacy settings, redirects, 410 rules and privacy-safe log data.
* Replaced the legacy support footer with the shared KOZ support panel.
* Kept the production safety and privacy contracts unchanged.

== Upgrade Notice ==

= 2.2.0 =
The 404 log now records continuously and shows paths with one-click redirects and 410 rules; adds redirects on address changes, prefix rules, CSV and Site Health. Existing rules are kept.

= 2.1.5 =
Developer support details updated: PayPal removed, BTC and USDT TRC-20 addresses corrected.

= 2.1.4 =
WordPress 7.1 compatibility metadata update; plugin behavior remains unchanged.

= 2.1.3 =
Stops stale language-prefixed 404 URLs from being guessed into source-language pages.

= 2.1.2 =
Adds privacy-safe system-route and legacy attachment 404 classification.

= 2.1.1 =
KOZ rebrand with preserved legacy data and multilingual administration.
