# UA FREE 404 Guard & URL Intelligence 2.0.7

Universal development candidate for privacy-safe 404/410 diagnostics, controlled redirects and URL intelligence.

## Safety model

- no automatic redirect or 410 rules;
- no IP logging;
- no raw query values, User-Agent strings or referrers in new log rows;
- no raw paths, option names, option values, rules, post identifiers or content in JSON export;
- same-origin HTTP/HTTPS redirect targets only;
- protected WordPress system paths cannot be redirected or marked gone;
- duplicate redirect sources and complete redirect cycles are rejected;
- normal admin-page loads do not scan post content;
- deep link scanning requires an explicit nonce-protected action and has request budgets;
- persistent request logging is disabled by default and exists only inside an administrator-started ten-minute capture window;
- capture sampling is fixed at 1 in 128 requests and grouped storage writes are globally limited to one every 30 seconds;
- no direct external requests or telemetry;
- plugin data is preserved on uninstall.

## Legacy log note

The existing `uafree_404_guard_log` option is preserved. Legacy 1.x rows are normalized before display or export. An explicit administrator action rewrites the stored option using the privacy-safe schema. Capture cannot start until legacy or oversized storage has been sanitized. New capture rows store only opaque path and query-key fingerprints.

## Release status

Development candidate. It must pass independent code review and real WordPress/MariaDB/browser testing before production use.
