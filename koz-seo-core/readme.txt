=== KOZ SEO Core ===
Contributors: ramirkz
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=kozyriev%40uafree.org&item_name=Support+KOZ+WordPress+plugin+development&currency_code=USD
Tags: seo, schema, sitemap, llms, accessibility
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 2.1.22
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight SEO metadata, schema, sitemap controls, AI discovery files and accessibility diagnostics.

== Description ==

KOZ SEO Core generates document titles, meta descriptions, canonical URLs, robots directives, Open Graph and Twitter metadata, JSON-LD schema, hreflang links and WordPress sitemap controls.

It also provides `/llms.txt`, `/.well-known/koz-ai-manifest.json`, a read-only inventory of other SEO plugins and a limited accessibility audit. Existing settings and post metadata from the former UA FREE package remain in place and are reused automatically.

The plugin was originally developed for the UA FREE charitable foundation website, which remains a production environment for the suite. The software is independently owned and maintained by Tony Kozyriev (`ramirkz`).

= Part of the KOZ Suite =

KOZ SEO Core is one focused module of the KOZ Suite, a family of independent WordPress plugins with a shared KOZ administration experience and visual identity.

Each KOZ plugin can be installed independently or combined with other KOZ Suite modules.

Current public KOZ plugins:

* KOZ SEO Core — technical SEO, schema, sitemaps, AI discovery and OpenAI Vision image ALT.
* [KOZ Copy Actions](https://wordpress.org/plugins/koz-copy-actions/) — accessible copy-to-clipboard actions for selected WordPress content.
* [KOZ URL-Only Comment Spam](https://wordpress.org/plugins/koz-url-only-comment-spam/) — privacy-first local moderation for URL-only comment spam.
* [KOZ Static Translate](https://wordpress.org/plugins/koz-static-translate/) — AI-powered multilingual translation with Microsoft Azure Translator and static language routes.

Additional KOZ Suite modules are used in production and may be published separately as they are prepared for public distribution.

= Main features =

* SEO title, description, canonical, robots and social metadata.
* Organization and webpage JSON-LD schema.
* WordPress sitemap filtering for public content while excluding technical Pagelayer templates and noindexed author archives.
* `llms.txt` and KOZ AI manifest endpoints.
* Per-post SEO fields using established storage keys.
* Read-only inventory of Rank Math, Yoast, AIOSEO, SEOPress and The SEO Framework.
* Limited accessibility audit and privacy-safe JSON report.
* AI Vision ALT workflow using the native WordPress 7.0 AI Client and Settings → Connectors.
* Ten administration languages with English fallback.
* KOZ Suite navigation and separate developer/foundation support.


== External services ==

The optional AI Vision ALT workflow uses the WordPress 7.0 AI Client with the OpenAI provider configured by the site administrator under Settings → Connectors. KOZ SEO Core does not store an OpenAI API key.

Only when an administrator explicitly starts AI Vision analysis, the selected image file and a short prompt that may include the parent page title are sent through the configured OpenAI connector for image analysis. No visitor data is sent by this feature. The rest of KOZ SEO Core works without this service.

OpenAI service: https://openai.com/
OpenAI Terms of Use: https://openai.com/policies/terms-of-use/
OpenAI Privacy Policy: https://openai.com/policies/privacy-policy/

== Installation ==

1. Deactivate the former UA FREE SEO Core package, but keep it installed during verification.
2. Upload and activate KOZ SEO Core.
3. Open KOZ Suite > SEO Core and confirm the existing settings.
4. Keep SEO output disabled while another SEO plugin is active.
5. Check `/llms.txt`, `/.well-known/koz-ai-manifest.json` and one page source.
6. Run Plugin Check.
7. Remove the former package only after the KOZ version works correctly.

== Changelog ==

= 2.1.22 =
* Updated KOZ Suite documentation to include KOZ Static Translate as the fourth public plugin.
* WordPress.org compatibility metadata: updated Tested up to to WordPress 7.1.

= 2.1.21 =
* Hardens JSON-LD output for WordPress.org review by using script-safe default wp_json_encode() escaping.
* Removes JSON_UNESCAPED_* flags from generated JSON output paths while preserving valid JSON responses and audit data.
* No SEO, sitemap, translation, AI Vision or saved-metadata behavior changes.

= 2.1.20 =
* Stops changing the activation status of the former UA FREE SEO plugin; administrators remain in control of plugin activation and deactivation.
* Documents the optional OpenAI service used only when an administrator explicitly runs AI Vision ALT analysis.
* Keeps SEO output, sitemap cleanup, translation integration and saved metadata behavior unchanged.

= 2.1.19 =
* Excludes Pagelayer technical templates from the WordPress XML sitemap and marks direct template views noindex/follow.
* Removes the WordPress users sitemap whenever author archives are configured noindex, keeping sitemap discovery aligned with robots policy.
* Leaves normal pages, posts, public gallery content and active translator-managed routes unchanged.

= 2.1.18 =
* Keeps all source-language SEO features fully standalone when no translation plugin is installed.
* Accepts an optional provider-neutral translation contract for multilingual hreflang filtering.
* Does not force KOZ language namespaces when another translator owns multilingual routing or SEO.
* Integrates automatically with KOZ Static Translate for ready-only hreflang and translated sitemap discovery.

= 2.1.15 =
* Adds an administrator-triggered browser-rendered accessibility audit for published pages.
* Checks actual same-site rendered HTML for H1 count, empty ALT text, potential empty links, meta description presence and noindex state.
* Runs in the administrator browser to avoid long PHP export requests; no database writes or external HTTP requests are performed.
* Exports a privacy-safe JSON result containing public paths and counts only, without page HTML or visitor data.

= 2.1.14 =
* Prevents WordPress block and gallery configuration JSON from leaking into automatic meta and social descriptions.
* Uses a clean page-title fallback when a gallery page has too little natural text for a useful description.
* Keeps explicitly saved manual SEO descriptions unchanged.

= 2.1.13 =
* Keeps the AI Vision batch controls usable after applying ALT values or after a transient provider readiness check.
* Applying selected ALT values no longer forces a full page reload.

= 2.1.12 =
* Fixed Plugin Check escaping and sanitization findings in AI Vision ALT workflow.
* Preserved editable candidates, approve/skip/re-analyze controls, and new-media queue.

= 2.1.11 =
* Lets administrators edit every AI Vision ALT candidate before approval.
* Adds explicit approve/apply, skip and per-image re-analysis controls.
* Adds an “Empty ALT, not analyzed” routine queue and an all-unprocessed queue for existing ALT review.
* Shows a pending counter for unprocessed images with empty ALT so newly uploaded media can be handled in later batches.
* Keeps Vision review state across plugin upgrades instead of re-queuing already processed images because of a version bump.
* Existing WordPress ALT values remain protected unless replacement is explicitly enabled.

= 2.1.10 =
* Makes Vision batches resilient to intermittent WordPress/OpenAI model-discovery failures.
* Retries multimodal text generation with progressively simpler model-selection constraints.
* Removes structured-output and max-token capability requirements from model discovery; JSON is requested and validated in the prompt instead.
* Does not write ALT during analysis and preserves the explicit review/apply workflow.

= 2.1.9 =
* Moves AI Vision analysis from admin-ajax to a dedicated authenticated REST endpoint, following the WordPress 7.0 AI Client integration guidance.
* Avoids the false “OpenAI is not ready” readiness result seen only inside admin-ajax while the connector is available in the normal admin request.
* Keeps ALT analysis read-only until explicit apply and preserves the existing OpenAI Connector credentials flow.

= 2.1.8 =
* Raises the minimum WordPress version to 7.0 because the AI Vision workflow uses the native WordPress AI Client introduced in 7.0.
* Removes Plugin Check compatibility errors for wp_ai_client_prompt() and wp_supports_ai().
* Tightens nonce/input handling and removes the reported direct SQL/slow meta-query warnings from the AI Vision ALT manager.
* Replaces metadata queries used for queue/preview/statistics with a lightweight cursor, recent-ID list and incremental counters.
* Uses GPT-5.6 Luna first, with Terra and GPT-5.4 nano as fallbacks for OpenAI Vision analysis.

= 2.1.7 =
* Replaces metadata/filename ALT heuristics with actual AI vision analysis.
* Uses the native WordPress 7.0 AI Client and OpenAI connector; KOZ SEO Core no longer stores an OpenAI API key.
* Analyzes images progressively in administrator-controlled batches and never writes ALT during analysis.
* Returns content/decorative/uncertain decisions with confidence and a human-readable reason.
* Shows actual thumbnails, current ALT and AI candidates before any write.
* Preserves existing ALT by default; replacement requires an explicit administrator checkbox.

= 2.1.6 =
* Adds a Media ALT assistant for image attachments with an explicit dry run before any write.
* Auto-applies only high-confidence candidates from existing caption, description or clearly human-authored attachment titles.
* Never overwrites existing ALT values; filename-only, generic and page-context-only cases remain review-only.
* Applies safe candidates in AJAX batches to avoid one large write request.

= 2.1.5 =
* Resolves non-root public permalinks before trusting front-page conditionals, so page-builder routes keep their own title, description, canonical and Open Graph URL.
* Leaves the real homepage behavior and saved SEO metadata unchanged.

= 2.1.5 =
* Fixes SEO context on valid page-builder/custom-rendered permalinks that WordPress does not expose as singular requests.
* Uses the configured organization description for homepage metadata instead of falling back to a polluted site tagline.

= 2.1.3 =
* Strict WordPress.org prefix re-audit with canonical kozseo / KOZSEO identifiers.
* Preserves legacy settings, post metadata, AI manifest route and public filter compatibility.

= 2.1.1 =
* Aligned the public context and hreflang filter hooks with the KOZ SEO prefix required by Plugin Check.

= 2.1.0 =
* Rebranded the public package as KOZ SEO Core.
* Added KOZ Suite navigation, ten runtime administration languages and English fallback.
* Added the KOZ AI manifest endpoint while retaining the former endpoint for compatibility.
* Preserved existing settings, post metadata and migration-safe read-only diagnostics.
* Replaced bundled PO/MO translations with runtime dictionaries and a source POT file.
* Updated ownership, support and repository metadata.

= 2.0.8 =
* Last stable release under the former UA FREE public branding.

== Upgrade Notice ==

= 2.1.8 =
Requires WordPress 7.0+ because AI Vision now relies on the native WordPress AI Client and Connectors API.

= 2.1.6 =
Adds an opt-in dry-run ALT workflow; no image ALT values are changed automatically on upgrade.

= 2.1.5 =
Corrects frontend SEO context for non-root public routes misclassified by page-builder conditionals.

= 2.1.5 =
Corrects metadata resolution for custom-rendered public pages without changing saved SEO fields.

= 2.1.3 =
Updates only public filter hook names; saved SEO settings and metadata remain unchanged.

= 2.1.1 =
Updates the public filter hook prefixes for WordPress.org compliance without changing saved SEO settings.

= 2.1.0 =
The public plugin folder and slug changed. Deactivate the former package first, then verify metadata output before deleting it.
