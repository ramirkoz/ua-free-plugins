=== KOZ Static Translate ===
Contributors: ramirkz
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=kozyriev%40uafree.org&item_name=Support+KOZ+WordPress+plugin+development&currency_code=USD
Tags: translation, multilingual, azure, static translation, language switcher
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 0.9.36
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Queued static WordPress translation with Azure Translator, translation memory and public language routes.

== Description ==

KOZ Static Translate scans published WordPress content, stores translation segments in plugin-owned tables, processes them through Azure Translator and serves completed language routes without changing the original posts.

The plugin preserves the proven database schema, queue, cron hooks, public URLs and stored Azure settings from the former UA FREE package. It also supports dynamic content added after page load, a floating language selector, a language sitemap and controlled cleanup of legacy translator leftovers.

The plugin was originally developed for the UA FREE charitable foundation website, which remains its primary production environment. The software is independently owned and maintained by Tony Kozyriev (`ramirkz`).

= Part of the KOZ Suite =

KOZ Static Translate is **one focused module of the broader KOZ Suite**, a family of WordPress plugins that share a common KOZ administration experience and visual identity.

Each KOZ plugin remains independent, so site owners can install only the modules they need or combine several modules into one toolkit.

Current public KOZ plugins:

* [KOZ SEO Core](https://wordpress.org/plugins/koz-seo-core/) — technical SEO, schema, sitemaps, AI discovery and OpenAI Vision image ALT.
* [KOZ Copy Actions](https://wordpress.org/plugins/koz-copy-actions/) — accessible copy-to-clipboard actions for selected WordPress content.
* [KOZ URL-Only Comment Spam](https://wordpress.org/plugins/koz-url-only-comment-spam/) — privacy-first local moderation for URL-only comment spam.

Additional KOZ Suite modules are used in production and may be published separately as they are prepared for public distribution.

= Main features =

* Queued Azure Translator processing with monthly character controls.
* Translation memory to avoid paying repeatedly for identical text.
* Public language routes enabled only after a page is complete.
* Dynamic text, SVG and Chart.js translation support.
* Language switcher and translation sitemap.
* Backup-first cleanup and reset tools.
* Ten bundled administration languages with English fallback.

= Privacy =

The plugin does not add analytics or advertising telemetry. Content selected for translation is sent to the configured Azure Translator endpoint. Azure credentials are encrypted in the WordPress database using keys derived from WordPress salts.

== External services ==

KOZ Static Translate optionally uses Microsoft Azure Translator when the site administrator configures Azure Translator credentials and enables translation processing.

When translation processing runs, text segments selected from published WordPress content are sent to the configured Azure Translator endpoint so Microsoft can return translated text. The plugin does not send visitor analytics or advertising telemetry to the developer. Azure Translator credentials are stored in the WordPress database in encrypted form using keys derived from WordPress salts.

The external service is not required for WordPress administration, cleanup tools, translation-memory storage, language routing logic or KOZ Suite navigation, but new machine translations cannot be produced without a configured translation provider.

Azure Translator:
https://azure.microsoft.com/products/ai-services/ai-translator/

Microsoft Azure Translator data, privacy and security information:
https://learn.microsoft.com/en-us/azure/foundry/responsible-ai/translator/data-privacy-security

Microsoft Privacy Statement:
https://privacy.microsoft.com/en-us/privacystatement

Microsoft Azure legal information:
https://azure.microsoft.com/support/legal/

== Installation ==

1. Deactivate the former UA FREE Static Translate package, but keep it installed until verification is complete.
2. Upload and activate KOZ Static Translate.
3. Open KOZ Suite > Static Translate.
4. Verify that the previous Azure settings, queue and language routes are present.
5. Test the administration page, one existing language route and Plugin Check.
6. Remove the former package only after the KOZ version works correctly.

== Frequently Asked Questions ==

= Will my existing translation data be preserved? =

Yes. Version 0.9.2 automatically migrates the established settings and translation data into the canonical KOZSTX storage while leaving the former tables available as a rollback copy.

= Does activation translate content immediately? =

No. Existing settings determine whether the queue and public routes are enabled. A manual pause remains respected.

= Is this an official UA FREE foundation plugin? =

No. It originated from production work for the foundation website but is independently owned and maintained by Tony Kozyriev.

== Changelog ==

= 0.9.36 =
* Makes meta_description, meta_og_description and meta_twitter_description universally non-blocking for page readiness.
* Makes image alt-text segments non-blocking for readiness while keeping them available for later translation.
* Removes the site-specific text exceptions previously used for readiness and replaces consent/privacy handling with generic structural context detection.
* No Azure request, scan, rebuild, cron or automatic worker is introduced.


= 0.9.35 =
* Adds a universal manual action that prepares exactly one pending Priority Core source per click.
* The next page is selected from dynamically detected internal navigation; no site-specific paths, titles or slugs are used.
* Preparation performs one local source scan and hydrates existing Translation Memory only; no Azure request, full inventory rebuild, cron or automatic loop.


= 0.9.34 =
* Adds a read-only Priority Core discovery diagnostic showing each detected internal navigation path and whether it exists in source inventory and core queue.
* Diagnostic performs no writes, scans, rebuilds, cron work or Azure requests.
* Priority Core processing behavior remains unchanged from 0.9.33.


= 0.9.33 =
* Priority Core now merges internal links from all assigned primary/main/header navigation locations, the richest unassigned classic-menu fallback, and block-theme Navigation.
* External URLs are discarded and duplicate internal paths are removed.
* No site-specific page names, titles or URL paths are used.


= 0.9.32 =
* Expands universal Priority Core menu detection for page builders and block themes.
* Detection now checks assigned classic menu locations, unassigned classic nav menus and block-theme wp_navigation menus, then selects the strongest internal navigation set.
* No site-specific page names, titles or URL paths are used.


= 0.9.31 =
* Removes all site-specific Priority Core page names, titles and URL paths.
* Priority Core is now detected universally from the WordPress home page plus the active primary/main navigation menu.
* Matching uses exact internal URL paths only, preventing legacy pages from entering Priority Core because of similar titles.
* Processing remains manual, local-only and limited to 4 pages per click.


= 0.9.30 =
* Replaces broad core batching with an explicit Priority Core set: Home, Projects, Donate, Reports, About, Gallery, Partners and Financial report.
* Secondary/legacy core URLs and report posts are excluded from this batch entirely.
* Batch output now shows the Priority Core label and the maximum number of source segments still missing for an incomplete page.
* Processing remains manual, local-only and limited to 4 pages per click.


= 0.9.29 =
* Core readiness batches now use explicit page priority instead of source/database ID order.
* First wave: Home, UA FREE, Donate, About and Reports landing; then remaining top-level core pages; nested core pages last.
* Reports remain excluded from the core batch. Processing stays manual and limited to 4 pages per click.


= 0.9.28 =
* Adds a manual bounded core-readiness batch: maximum 4 core pages per click.
* Uses only existing local translations and Translation Memory; no Azure, scan, rebuild, cron or automatic loop.
* Incomplete pages remain pending and are reported without being forced ready.


= 0.9.27 =
* Treats two consent-component descriptions and the UA FREE logo alt text as non-blocking readiness segments.
* One-page reconcile safely promotes only those matching segments to protected status, then recalculates readiness from existing local translations/Translation Memory.
* No scan, queue rebuild, automatic worker or Azure request is performed.


= 0.9.26 =
* Adds a read-only one-page diagnostic that lists source segments missing from Translation Memory/ready translations.
* Diagnostic is capped at 25 rows and performs no writes, scans, rebuilds, cron work or Azure requests.
* Safety-mode behavior remains unchanged.


= 0.9.25 =
* Plugin Check compliance fix for the one-page readiness action: direct nonce verification, sanitized path input, and translator comments.
* No change to the 0.9.24 safety-mode runtime behavior.


= 0.9.24 =
* Adds a bounded one-page readiness reconciliation using existing Translation Memory only.
* The reconciliation performs no source scan, queue rebuild or Azure request.
* Admin status now accurately reports that the automatic worker is disabled in safety mode.
* Opening the Static Translate admin screen no longer runs hidden upgrade/repair maintenance.


= 0.9.23 =
* Stability release: activation performs no inventory scan, rebuild, readiness repair or Azure work.
* Automatic cron and shutdown workers are disabled.
* Manual Run/Rebuild actions are temporarily disabled to protect production stability.
* Existing translated routes, language switching and stored translation data remain available.


= 0.9.19 =
* Keep the language switcher visible on translated provisional routes.
* Provisional routes show only the source language and the current language, without advertising other unfinished translations.


= 0.9.18 =
* The floating language switcher now advertises only fully translated routes for the current page and stays hidden when none are ready.
* Provisional translated routes no longer render the language switcher, preventing unfinished language URLs from being exposed through navigation.
* No changes to Azure budget limits, translation memory, queue contents or public URL structure.

= 0.9.17 =
* Updated WordPress compatibility metadata for WordPress 7.1.
* No functional changes.

= 0.9.16 =
* Fixes retired-language queue cleanup SQL for WordPress 6.0+ without the WP 6.2-only %i identifier placeholder.
* No change to languages, Azure usage logic, translation memory or readiness behavior.

= 0.9.15 =
* Fix Plugin Check prepared-SQL placeholder warning in retired-language queue cleanup.


= 0.9.14 =
* Finalizes the supported public language set: EN/ZH/ES/AR/ID/PT/FR/JA/DE/HI; Russian is forbidden.
* Stops accidental PL/IT/CS/RO Azure queue work without deleting existing translation memory.
* Keeps incomplete routes noindex with blank translation-dependent SEO fields until fully ready.
* Shows Azure characters and requests for every billed language code, including inactive/legacy codes.
* Exposes an optional provider contract for multilingual SEO integration without requiring KOZ SEO Core.

= 0.9.9 =
* Adds bounded transient render caching for source and translated HTML to reduce repeated same-site rendering work.
* Caches translated HTML by source render hash and language while keeping translation data and URLs unchanged.

= 0.9.8 =
* Stops cache-busting the canonical source page during translated-route rendering.
* Uses a normal browser-like source fetch with a bounded timeout to reduce intermittent translated-route timeouts seen by crawlers and ad destinations.
* Translation data, queue state, readiness rules and public route structure remain unchanged.

= 0.9.7 =
* Fix the remaining English About H1 language mismatch with an exact route-scoped fallback.
* Preserve existing SEO, canonical, Open Graph, robots and hreflang behavior.

= 0.9.6 =
* Fixes missing H1 semantics on provisional `/en/donate/` and `/en/about/` when an upstream PageLayer/cache fragment still exposes the primary heading as H2/H3.
* Promotes only an existing matching translated heading; does not invent or duplicate heading text.
* Leaves canonical, og:url, robots, hreflang and metadata behavior unchanged.

= 0.9.5 =
* Fixes Ukrainian metadata leaking into the provisional English home and reports routes.
* Keeps canonical, localized og:url, noindex and hreflang safeguards unchanged.
* Applies the fallback only when known UA FREE metadata remains in Ukrainian.

= 0.9.4 =
* Rewrites og:url to the localized self URL so social metadata matches the translated canonical.
* Keeps translated-route hreflang checks read-only during frontend rendering.
* Keeps provisional noindex routes out of hreflang until translation coverage is complete.

= 0.9.3 =
* Adds reciprocal source-page hreflang integration with KOZ SEO Core.
* Exposes only fully translated language routes in source-page hreflang output.
* Keeps provisional monthly-limit routes safely noindex until every required translation segment is ready.

= 0.9.2 =
* Moved plugin-owned runtime identifiers to the unique `kozstx / KOZSTX` prefix and `ramirkz\kozstx` namespace.
* Added automatic migration of existing settings, runtime state and translation tables from the former `uafree_st_*` storage.
* Preserved the existing public language sitemap URL and former Azure configuration constants as compatibility inputs.
* Normalized KOZ Suite navigation and standalone support UI without duplicate support panels.

= 0.9.1 =
* Preserved JSON-LD and other JSON script blocks byte-for-byte during translated-page DOM processing.
* Rewrote localized JSON-LD URLs before script protection to prevent invalid structured data.
* Fixed Google Search Console parse errors caused by quotes inside translated site names.

= 0.9.0 =
* Rebranded the public package as KOZ Static Translate.
* Added KOZ Suite navigation and current package status detection.
* Added ten runtime administration languages with English fallback.
* Rebuilt the administration screen and moved scripts and styles to enqueued assets.
* Preserved existing translation tables, options, queue, cron hooks and public language routes.
* Replaced bundled PO/MO files with source-only translation metadata.
* Added safe deactivation of the former package after KOZ activation.

= 0.8.7 =
* Last stable release under the former UA FREE public branding.

== Upgrade Notice ==

= 0.9.18 =
The language switcher now exposes only fully translated routes for each page; unfinished routes are no longer advertised through navigation.

= 0.9.17 =
WordPress 7.1 compatibility metadata update; plugin behavior remains unchanged.

= 0.9.7 =
* Fix the remaining English About H1 language mismatch with an exact route-scoped fallback.
* Preserve existing SEO, canonical, Open Graph, robots and hreflang behavior.

= 0.9.6 =
Repairs English Donate/About H1 semantics without changing translation readiness or SEO indexing safeguards.

= 0.9.5 =
Corrects English-route title/description language without weakening provisional-route indexing safeguards.

= 0.9.4 =
Corrects localized Open Graph URLs without weakening translation-completeness or noindex safeguards.

= 0.9.3 =
Improves multilingual SEO linking without changing translation tables or weakening provisional-route indexing safeguards.

= 0.9.2 =
Strict WordPress.org prefix re-audit release with automatic migration of previous translation settings and data.

= 0.9.1 =
Regenerate translated pages after updating so cached JSON-LD is rebuilt safely.

= 0.9.0 =
The public plugin folder and slug changed. Deactivate the former package first, then verify existing settings and language routes before deleting it.
