# KOZ 404 Guard & URL Intelligence 2.2.0

Privacy-safe 404/410 diagnostics, controlled same-site redirects and URL intelligence for WordPress.

## Safety model

- no automatic redirect or 410 rules, except optional 301 redirects for changed addresses (suggested for approval by default);
- no IP logging;
- no raw query values, User-Agent strings or external referrers in log rows; paths and same-site linking pages only while "Show paths" is on;
- no raw paths, option names, option values, rules, post identifiers or content in JSON export;
- same-origin HTTP/HTTPS redirect targets only;
- protected WordPress system paths cannot be redirected or marked gone;
- duplicate redirect sources and complete redirect cycles are rejected;
- normal admin-page loads do not scan post content;
- deep link scanning requires an explicit nonce-protected action and has request budgets;
- grouped log in its own table: one query per hit, daily cleanup by age and size;
- no direct external requests or telemetry;
- plugin data is preserved on uninstall.

## Migration compatibility

The legacy `uafree_404_guard_*` options are intentionally retained so settings, rules and privacy-safe logs survive the transition from the former UA FREE-branded package.

## Ownership and origin

Developed and maintained by Tony Kozyriev. The plugin originated as production tooling for the UA FREE charitable foundation website; the foundation remains a production environment but does not own the plugin code.
