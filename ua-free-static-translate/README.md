# UA FREE Static Translate 0.8.6

## Language-aware navigation

Translated pages now keep visitors inside the selected language across the
site menu and other internal page links.

The link is localized when the destination route is available either as:

- a fully ready translation; or
- an allowed provisional route during the confirmed Azure `monthly_limit`.

The runtime guard also corrects source-language URLs restored later by
PageLayer or another frontend builder. External links, email, phone,
fragments and unsupported internal routes remain unchanged.

This release does not add Azure requests, database migrations or admin-side
translation.
