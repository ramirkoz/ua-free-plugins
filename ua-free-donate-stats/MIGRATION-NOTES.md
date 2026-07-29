# Migration notes for 1.2.1

- Database version changes from `1.0.0` to `1.1.0`.
- Existing daily and session tables are not deleted or rewritten.
- New table: `{prefix}uafree_donate_confirmations`.
- The new table stores only a 64-character HMAC reference hash, provider key, language, context key and timestamp.
- Existing statistics remain readable.
- Existing settings receive `confirmation_mode=webhook` and a generated random secret.
- Rollback to `1.1.2-dev` leaves the confirmation table unused but does not destroy it.
