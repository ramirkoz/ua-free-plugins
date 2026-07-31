# UA FREE Translate Diagnostics 0.2.13

Universal read-only diagnostics for **UA FREE Static Translate**.

## What it checks

- installed and active translator versions;
- the translator public diagnostics API;
- source, segment, translation, memory, queue, usage and log tables;
- detected target languages;
- current translation completeness estimates;
- stale hashes during an explicit deep scan;
- queue errors, cron state and recent runtime problems;
- migration freeze and pause reasons reported by the translator.

## Quick and deep modes

The administration screen opens in **quick mode**. It uses table metadata plus limited recent-row reads and does not run full-table aggregates or translation-to-segment hash joins.

A deep hash scan is available only after an explicit administrator action. The downloadable JSON report always uses deep mode.

## Privacy and safety

- no Azure calls;
- no external requests;
- no worker execution;
- no option, cron, cache or translation changes;
- no tables or persistent plugin data;
- site host, plugin identifiers, table names, cron hooks, transient names and source paths are represented only by salted fingerprints or omitted;
- the translator public API is reduced to an explicit allowlist;
- extension filters cannot add fields or replace strings in the exported report;
- free-text error, message, reason, name and description fields are fingerprinted in the downloadable JSON;
- a strict export-only privacy pass runs after the constrained filter merge;
- URLs, domains, filesystem paths, common credentials, email addresses, international IBANs, formatted card-like numbers and cryptocurrency addresses are redacted.
- safe database and rewrite version values remain available after privacy filtering.

## Compatibility

The plugin first calls `UAFree_Static_Translate_Autonomous::public_status()` when available. Older translator versions are inspected through a read-only compatibility layer.

It supports the universal Static Translate branch and the separately maintained foundation-safe branch without importing site-specific rendering rules.

## Interfaces

The WordPress administration interface is available in English and Ukrainian. The plugin also registers itself in the shared UA FREE Plugin Suite menu.

## Support

The plugin originated from the operational needs of a charitable foundation website and was rebuilt as a universal open-source tool.

- Foundation: https://uafree.org/
- Development support: https://uafree.org/plugins/support-development/


## Release 0.2.13

Plugin Check compatibility update with prepared identifier queries and the shared responsive UA FREE support block.
