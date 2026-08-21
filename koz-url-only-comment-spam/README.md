# KOZ URL-Only Comment Spam 1.1.11

A privacy-first WordPress moderation plugin for comments whose visible content consists only of one or more URLs.

## What it does

- Marks matching comments as spam or holds them for moderation.
- Preserves comments containing ordinary readable text.
- Can trust same-site links and selected domains.
- Does not store comment text, URLs, IP addresses, email addresses or user-agent values.
- Provides a non-persistent detector test in the administration screen.

## Compatibility

The KOZ package preserves the existing options:

- `uafree_url_only_spam_settings`
- `uafree_url_only_spam_total`
- `uafree_url_only_spam_last`

Legacy hooks remain available. New integrations should use:

- `kozurlspam_settings`
- `kozurlspam_is_url_only`
- `kozurlspam_caught`

A read-only status is available through `ramirkz\kozurlspam\Plugin::instance()->public_status()`.

## Origin and ownership

The plugin was originally developed and production-tested on the UA FREE charitable foundation website. It is independently developed and maintained by Tony Kozyriev (`ramirkz`).

## Support

Developer support: PayPal and crypto details are shown inside the plugin administration panel. Ukraine support: https://uafree.org/donate/

LinkedIn: https://www.linkedin.com/in/tonykoz/

## License

GPL-2.0-or-later.
