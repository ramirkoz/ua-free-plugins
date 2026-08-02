=== UA FREE SEO Core ===
Contributors: uafree
Donate link: https://uafree.org/plugins/support-development/
Tags: seo, schema, sitemap, metadata, accessibility
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 2.0.8
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight SEO metadata, schema, sitemap integration and accessibility diagnostics.

== Description ==

UA FREE SEO Core was originally created to solve real operational needs of a charitable foundation website and was later rebuilt as a universal WordPress plugin.

Features:

* title, description and canonical generation;
* Open Graph and Twitter metadata;
* Organization, WebSite, WebPage and Article schema;
* WordPress core sitemap integration;
* optional llms.txt and public AI manifest;
* per-post SEO fields and noindex control;
* read-only detection of previous SEO plugins and their stored metadata;
* lightweight accessibility audit;
* Ukrainian and English administration interfaces;
* no telemetry and no external requests.

The plugin does not silently delete data from previous SEO plugins. Import and cleanup require an explicit migration workflow with a snapshot and dry run.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open the plugin settings or UA FREE Suite Control Center when configuration is required.

== Changelog ==

= 2.0.8 =
* Redesigned the shared UA FREE support panel.
* Moved the panel into the WordPress admin content area to prevent overlap with the core footer.
* Added compact wallet rows and accessible copy buttons.
* Added PayPal developer donations via kozyriev@uafree.org.
* Plugin-specific functionality is unchanged.

= 2.0.7 =
* Fixed Plugin Check findings for request sanitization, translator context and the explicit deep SEO inventory query.
* Removed manual translation loading and updated WordPress compatibility metadata.
* Replaced the shared administration support footer with the responsive card layout.

= 2.0.6 =
* Final stable packaging for repository publication and WordPress.org submission.
* Updated plugin metadata and WordPress compatibility information.
* No custom update checker or UA FREE usage telemetry was added.

== Upgrade Notice ==

= 2.0.8 =
Shared admin support panel redesign; plugin-specific functionality is unchanged.

= 2.0.7 =
WordPress.org compliance and administration support layout update without changing public SEO behavior.
