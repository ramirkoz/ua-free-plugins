=== KOZ Copy Actions ===
Contributors: ramirkz
Tags: clipboard, copy, accessibility, privacy, tools
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add accessible copy-to-clipboard actions to selected WordPress content without telemetry, cookies or external services.

== Description ==

KOZ Copy Actions lets you turn selected text, values, buttons, links or other frontend elements into copy-to-clipboard targets.

You choose what can be copied by adding simple CSS selectors in the plugin settings. The plugin then handles mouse clicks and keyboard activation, shows optional feedback, and copies the value directly in the visitor's browser.

No copied content is sent to the developer or to an external service.

= Part of the KOZ Suite =

KOZ Copy Actions is **one focused module of the broader KOZ Suite**, a family of independent WordPress plugins that share a common KOZ administration experience and visual identity.

Each KOZ plugin remains independently installable, so site owners can use only the modules they need or combine several modules into one toolkit.

Current public KOZ plugins:

* [KOZ SEO Core](https://wordpress.org/plugins/koz-seo-core/) — technical SEO, schema, sitemaps, AI discovery and OpenAI Vision image ALT.
* KOZ Copy Actions — accessible copy-to-clipboard actions for selected WordPress content.
* [KOZ URL-Only Comment Spam](https://wordpress.org/plugins/koz-url-only-comment-spam/) — privacy-first local moderation for URL-only comment spam.
* [KOZ Static Translate](https://wordpress.org/plugins/koz-static-translate/) — AI-powered multilingual translation with Microsoft Azure AI Translator and static language routes.
* [KOZ Translate Diagnostics](https://wordpress.org/plugins/koz-translate-diagnostics/) — read-only health checks and privacy-safe diagnostic reports for KOZ Static Translate.
* [KOZ 404 Guard & URL Intelligence](https://wordpress.org/plugins/koz-404-guard/) — privacy-safe 404/410 diagnostics, controlled same-site redirects and URL intelligence.
* [KOZ Consent Manager](https://wordpress.org/plugins/koz-consent-manager/) — privacy-first visitor consent, optional script control and Google Consent Mode v2 synchronization.
* [KOZ Donate Stats & Conversions](https://wordpress.org/plugins/koz-donate-stats/) — local donation-journey analytics and consent-aware conversion events for WordPress.
* [KOZ Site Bridge](https://wordpress.org/plugins/koz-site-bridge/) — secure read-only WordPress diagnostics, controlled same-site probes and authenticated REST automation.

= Typical uses =

* Copy coupon codes, reference numbers, wallet addresses or contact details.
* Add a copy action to code snippets, IDs or short text values.
* Copy the current value of an input, textarea or select field.
* Copy a value stored in a data attribute.
* Copy text or a form value from another element on the same page.
* Limit copy actions to selected paths or sections of the site.
* Use the same configuration on multilingual WordPress sites.

= Quick start =

1. Install and activate KOZ Copy Actions.
2. Open **KOZ Suite -> Copy Actions** in the WordPress admin.
3. Enable **Copy actions**.
4. In **CSS selectors**, add one selector per line for the elements visitors should be able to copy.
5. Save the settings.
6. Open a public page and click or keyboard-activate a matching element.

Example selector:

` .copy-me `

If your page contains:

`<span class="copy-me">PROMO-2026</span>`

the visitor can copy `PROMO-2026` by activating that element.

= Choosing what gets copied =

The plugin uses the first applicable source below.

**1. Explicit value with `data-copy-value`**

Use this when the visible label is different from the value that should be copied.

`<button class="copy-me" data-copy-value="PROMO-2026">Copy promo code</button>`

Copied value: `PROMO-2026`

**2. Another element with `data-copy-target`**

Use a local ID reference beginning with `#`.

`<input id="account-number" value="UA123456789">`
`<button class="copy-me" data-copy-target="#account-number">Copy account number</button>`

Copied value: the current value of `#account-number`.

**3. Form controls**

If a matching element is an input, textarea or select field, the plugin copies its current value.

**4. Normal page content**

For other matching elements, the plugin copies the element's visible text content.

= Shortcode and block =

Place a copy button anywhere, without configuring selectors:

`[koz_copy text="PROMO-2026" label="Copy code" /]`

Show the value itself as clickable inline text:

`[koz_copy display="inline"]UA123456789[/koz_copy]`

Copy the current value of another element:

`[koz_copy target="#account-number" label="Copy account number" /]`

Close single shortcodes with ` /]`. Without it, WordPress treats a later `[/koz_copy]` on the same page as the end of the earlier shortcode and the enclosed shortcodes disappear.

Attributes: `text`, `target`, `label`, `display` (`button` or `inline`), `format` (`text` or `html`), `key` (sent with the copy event) and `class`.

In the block editor, add the **Copy button** block and set the value, label and display in the block settings.

The shortcode and block work on every page where they are placed, independently of the selector settings and display conditions.

= Copy buttons for code blocks =

In **Add a copy button to**, list elements that should receive a separate Copy button, for example:

`pre`

Allowed entries: `pre`, `code`, `blockquote`, `kbd`, `samp`, a class or ID, or one of these tags combined with a class or ID (for example `pre.wp-block-code`). Whitespace inside these elements is always preserved, so copied code keeps its indentation.

= Formatted copying =

With **Copy format** set to **Formatted (HTML and text)**, element content is copied as HTML and plain text together, so links, bold text and lists survive when pasting into editors that support formatting. Explicit values and form fields are always plain text. Override one element with `data-copy-format="text"` or `data-copy-format="html"`.

= Supported selectors =

For safety and predictable behaviour, settings accept simple selectors only:

* A class selector such as `.copy-me`
* An ID selector such as `#copy-code`
* Supported data-attribute selectors such as `[data-copy-value]`, `[data-copy-target]`, `[data-copy-key]` and `[data-uafree-copy]`

Add one selector per line. Duplicate entries are removed automatically.

= Limit copy actions to selected pages =

The **Allowed paths** field is optional.

Leave it empty to enable matching copy targets on all public paths.

Add one local path per line to restrict where the frontend handler runs.

Examples:

`/contact/`
`/donate/`
`/products/*`

An ending `*` matches all paths beginning with that prefix.

You can also limit the handler to single views of selected **post types**, or to specific **post or page IDs**. When paths, post types or IDs are set, the handler loads where any of them matches.

For multilingual sites with language-specific URLs, add each required language path, for example:

`/en/contact/`
`/uk/kontakty/`

= Accessibility and feedback =

KOZ Copy Actions can:

* Add keyboard focus to matching non-interactive elements.
* Add button semantics and an accessible label where appropriate.
* Support activation with Enter or Space.
* Show an optional copy icon.
* Show localized success or error messages.
* Position feedback at the bottom centre, bottom left or bottom right.
* Preserve formatting or collapse repeated whitespace.
* Prevent navigation when a matching link is used as a copy action.

= Multilingual sites and translations =

The plugin is internationalized with the `koz-copy-actions` text domain.

Admin labels and frontend messages such as **Copied**, **Could not copy** and **Copy to clipboard** use WordPress translation functions and remain compatible with the standard WordPress.org translation system.

Version 1.1.13 also includes the KOZ Suite runtime language baseline: Ukrainian, Chinese, Spanish, Arabic, Indonesian, Portuguese, French, Japanese, German and Hindi, with English fallback. The WordPress user locale controls the administration interface. On the frontend, supported language-prefixed routes use the matching bundled copy feedback language.

The copy logic itself is language-independent: it works with the rendered content of the current page, so the same selector configuration can be used with multilingual plugins and translated pages.

If your multilingual setup uses different URL paths for each language and you use **Allowed paths**, add the corresponding path for each language.

KOZ Copy Actions does not translate the content being copied. It copies the value or text that is already rendered on the current page.

= Privacy =

Clipboard operations happen in the visitor's browser.

The plugin does not:

* Store copied values.
* Store IP addresses or visitor identifiers.
* Set tracking cookies.
* Send analytics or telemetry.
* Make external requests for copy functionality.

= Compatibility notes =

KOZ Copy Actions can detect another active clipboard plugin and warns administrators before two copy handlers are enabled on the same elements.

Compatibility with historical UA FREE Copy settings and frontend data attributes is retained for existing installations, but new installations do not need to configure or know about the former package.

== Installation ==

1. Install KOZ Copy Actions from the WordPress Plugin Directory or upload the plugin ZIP through **Plugins -> Add New Plugin -> Upload Plugin**.
2. Activate the plugin.
3. Open **KOZ Suite -> Copy Actions**.
4. Enable copy actions.
5. Add one or more CSS selectors for the elements that should be copyable.
6. Optionally restrict the feature to selected paths.
7. Configure the copy icon, feedback message, whitespace and accessibility options.
8. Click **Save settings**.
9. Test the selected elements on a public page with both mouse and keyboard.

== Frequently Asked Questions ==

= Do I need to edit theme PHP files? =

No. If your page builder, block editor or theme lets you add a CSS class or supported data attribute to an element, you can normally configure the copy action without editing PHP.

= What happens if I use `.copy-me` as a selector? =

Every public element with the class `copy-me` becomes a copy target on allowed pages. Normal text elements copy their rendered text. Form controls copy their current value.

= Can the copied value be different from the visible text? =

Yes. Add `data-copy-value` to the matching element.

= Can one button copy text from another element? =

Yes. Use `data-copy-target="#element-id"` and make sure the referenced element has that ID.

= Does it work with WPML, Polylang or other multilingual setups? =

The plugin does not require a specific multilingual integration. It operates on the rendered frontend content and simple selectors, so it can work on translated pages. If you restrict copy actions by URL path, configure the required path for each language.

= Does the plugin translate copied content? =

No. It copies the content already displayed on the current page.

= Are the plugin interface and copy messages translatable? =

Yes. User-facing strings are internationalized with the `koz-copy-actions` text domain and are compatible with the WordPress.org translation system.

= Does the plugin send copied values anywhere? =

No. Clipboard operations are performed locally in the browser and copied values are not sent to the developer or an external service.

= Can I disable navigation on links used for copying? =

Yes. Enable **Prevent navigation when a matching link is clicked** in the plugin settings.

== Changelog ==

= 1.2.0 =
* New: `[koz_copy]` shortcode and **Copy button** block (Block API v3, compatible with the iframe editor) for copy buttons or inline copy text without CSS selectors.
* New: "Add a copy button to" mode adds a separate Copy button to matching elements such as `pre` code blocks.
* New: optional formatted copying (HTML and plain text together) with a per-element `data-copy-format` override.
* New: display conditions by post type and post or page ID, combined with the existing allowed paths.
* Performance: dynamic content is scanned only where new nodes were added, and click targets are matched with one native `closest()` call.
* Performance: the frontend script loads with the `defer` strategy on WordPress 6.3+, and settings migration runs only in the administration area.
* Accessibility: Space now activates copy targets on key release, matching native button behaviour.
* Fixed: the plugin's own support panel now appears on its settings page and steps aside when the shared KOZ support panel is present, so it is never shown twice.
* Removed PayPal from the developer support panel and corrected the BTC and USDT TRC-20 developer wallet addresses.

= 1.1.14 =
* Updated KOZ Suite documentation to include all eight current public plugins.
* Updated WordPress compatibility metadata for WordPress 7.1.
* No changes to clipboard, selector, path, privacy or multilingual runtime behavior.

= 1.1.13 =
* Added bundled runtime interface translations for Ukrainian, Chinese, Spanish, Arabic, Indonesian, Portuguese, French, Japanese, German and Hindi, with English fallback.
* Frontend copy feedback follows supported language-prefixed routes while WordPress user locale controls the administration interface.
* Preserved all clipboard, selector, path, privacy and compatibility behaviour from 1.1.12.

= 1.1.12 =
* Expanded the WordPress.org documentation with a complete quick-start guide and copy examples.
* Added clear documentation for multilingual WordPress sites and translation support.
* Added FAQ entries explaining selectors, data attributes, path restrictions and privacy.
* Moved historical UA FREE migration information out of the main feature list.
* No frontend or settings behaviour changed in this release.

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
