# UA FREE URL-Only Comment Spam 1.0.2

A lightweight, privacy-first WordPress moderation plugin for comments whose visible content consists only of one or more URLs.

## Core behavior

- Marks matching comments as spam or holds them for moderation.
- Does not process pingbacks, trackbacks, administrators, or comment moderators.
- Can exempt all logged-in users.
- Can trust same-site links and selected domains.
- Stores no IP address, user agent, comment text, detected URL, cookie, or fingerprint.

## Compatibility with 0.1.0

The existing options are preserved:

- `uafree_url_only_spam_total`
- `uafree_url_only_spam_last`

The new settings option is:

- `uafree_url_only_spam_settings`

No private migration bridge is required for this plugin.

## Integration

Read-only status:

```php
$status = uafree_url_only_comment_spam_get_status();
```

Privacy-safe event hook:

```php
add_action( 'uafree_url_only_comment_spam_caught', function ( array $event ): void {
    // No comment text, URL, IP, email or user agent is included.
} );
```

## Development status

This is a development build. It has passed static syntax checks and detector-focused tests, but has not yet completed the final full-suite integration test inside a live WordPress installation.
