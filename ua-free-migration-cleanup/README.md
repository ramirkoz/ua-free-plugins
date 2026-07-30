# UA FREE Migration & Cleanup 0.8.9

A universal, privacy-conscious WordPress environment inventory and migration foundation.

## Design principles

- Scan the actual site instead of assuming a fixed plugin list.
- Read-only by default.
- No cleanup based solely on heuristics.
- Dedicated adapters, dry runs, snapshots and explicit confirmations for destructive work.
- English and Ukrainian interfaces.
- Minimal code and no external runtime dependencies.
- No telemetry and no external requests.
- No bundled site-specific or obsolete compatibility modules.

## Migration from older internal builds

Older site-specific builds are handled by separate temporary bridge packages. A bridge is installed only on the site that needs it, performs a verified migration, and is then removed. The public plugin remains lean for ordinary users.
