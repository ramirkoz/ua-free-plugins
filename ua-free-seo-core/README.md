# UA FREE SEO Core 2.0.3

Universal development build of the SEO plugin originally created for a charitable foundation website.

## Safety status

- no cleanup or deletion of previous SEO-plugin data;
- no automatic import from previous SEO plugins;
- no telemetry or external requests;
- output is disabled automatically on first activation when another known SEO plugin is active;
- every public output path respects the master `enabled` switch;
- quick environment scan performs no postmeta aggregate query;
- exact metadata counts require an explicit nonce-protected deep scan;
- privacy-safe JSON omits plugin file paths, option names/values, post titles, post URLs and post content;
- uninstall keeps settings and metadata.

## 2.0.3 changes

- replaced the plugin-local Suite menu with the canonical shared `UAFree\Suite\Registry`;
- split SEO-provider detection into lightweight inventory and explicit deep metadata counting;
- reduced deep metadata counting to one grouped postmeta query;
- removed plugin-relative paths and option names from exported diagnostics;
- removed post IDs, titles and URLs from exported accessibility results;
- fixed false positives for empty links that have `aria-label`, `title` or useful image alt text;
- made the master output switch cover sitemap filtering, robots.txt and discovery endpoints;
- removed Host-header input from generated canonical URLs;
- normalized filtered SEO context before rendering.

## Migration

The public migration workflow will use provider adapters, a snapshot, dry run and explicit confirmation. Site-specific migration from the previous UA FREE build belongs in the private UA FREE Suite Migration Bridge and is not included here.


## 2.0.3 hardening

- Context and hreflang filters reject objects, arrays and invalid field types without frontend fatals.
- Disabled discovery routes are marked as controlled WordPress 404 responses.
- Manual canonical values are limited to validated HTTP/HTTPS URLs.
- Accessibility attributes support quoted and unquoted values, reject whitespace-only names and cap each audited content item at 1 MB.
