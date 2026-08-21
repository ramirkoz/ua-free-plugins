=== KOZ 404 Guard & URL Intelligence ===
Contributors: ramirkz
Donate link: https://github.com/ramirkoz/ua-free-plugins
Tags: 404, redirects, broken links, 410, privacy
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 2.1.4
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-safe 404/410 diagnostics, controlled same-site redirects and URL intelligence.

== Description ==

KOZ 404 Guard & URL Intelligence is an independent WordPress plugin developed and maintained by Tony Kozyriev. It grew from production tooling used on the UA FREE charitable foundation website.

Features:

* administrator-started, ten-minute sampled 404/410 diagnostics without IP addresses, raw paths, query names, query values, raw User-Agent strings or raw referrers;
* salted path, query-key and external-referrer fingerprints;
* fixed 1-in-128 request sampling and a global maximum of one grouped log write every 30 seconds;
* optional lightweight bot 404 pages;
* exact same-origin redirects with duplicate-source and full cycle detection;
* boundary-aware 410 path-prefix rules and strict query-rule parsing;
* KOZ Suite status and navigation;
* quick environment inventory without content scanning;
* explicit nonce-protected internal-link deep scan with byte and link budgets;
* privacy-safe JSON export;
* explicit legacy-log sanitization;
* no direct external requests or telemetry.

Existing UA FREE 404 Guard settings, rules and logs are preserved during migration. No redirect or 410 rule is created automatically. Uninstalling the plugin does not delete data.

== Installation ==

1. Deactivate the legacy UA FREE 404 Guard plugin, but keep it installed until verification is complete.
2. Upload and activate the KOZ plugin ZIP.
3. Open KOZ Suite > 404 Guard.
4. Verify settings, redirect rules, 410 rules and the privacy-safe export.

== Changelog ==

= 2.1.4 =
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

= 2.1.4 =
WordPress 7.1 compatibility metadata update; plugin behavior remains unchanged.

= 2.1.3 =
Stops stale language-prefixed 404 URLs from being guessed into source-language pages.

= 2.1.2 =
Adds privacy-safe system-route and legacy attachment 404 classification.

= 2.1.1 =
KOZ rebrand with preserved legacy data and multilingual administration.
