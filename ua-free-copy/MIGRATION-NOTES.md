# Migration notes: UA FREE Copy 0.1.0 to 1.0.0

## Preserved data

The existing option name remains:

```text
uafree_copy_settings
```

The previous fields are preserved:

- `enabled`
- `selectors`

New fields are merged without deleting the existing selector list. Existing installations preserve the old behaviour that prevented navigation on matched links.

## Site-specific selectors

Version 1.0.0 does not ship any site-specific selector. Selectors already saved by an existing site remain in that site's option and continue to work after upgrade. This is normal configuration migration, not a hidden legacy module.

## Rollback

Before production deployment, export the current `uafree_copy_settings` option. A rollback only requires restoring the previous plugin files and the option backup.
