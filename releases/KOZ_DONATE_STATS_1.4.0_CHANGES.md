# KOZ Donate Stats & Conversions 1.4.0 — changes

**Plugin:** KOZ Donate Stats & Conversions (wordpress.org slug `koz-donate-stats`, author `ramirkz`, Tony Kozyriev).

**Version history:**
- previous public release: 1.3.11;
- 1.3.12: support panel only.

**Base for 1.4.0:** catalogue 1.3.11. The build installed on uafree.org was identical (SHA-256 of all main files checked).

**Tested with:** WordPress 7.1.3, PHP 8.4.26, KOZ Consent Manager 0.3.0, WP Consent API 2.1.0, KOZ Static Translate 0.10.0.

## Starting point on uafree.org

**Configuration:**
- tracked: page "Донат" (ID 50), the path `/*/donate/` and language versions;
- payment hosts: `liqpay.ua`, `send.monobank.ua`, `paypal.com`;
- Ad Grants mode with dataLayer event `uafree_donation_event`, consent category advertising;
- confirmations: `none`.

**30-day data:**

| Metric | Value |
|---|---|
| Visits | 123 |
| "Support" clicks | 69 |
| Payment opens | 77 |
| Copies | 3 |
| **Confirmed donations** | **0** |

Payment services never send the plugin's own HMAC webhook format, so confirmations were never possible.

**Problems found:**
- Language versions were detected only through the former UA FREE function `uafree_static_translate_get_status`. KOZ Static Translate provides `kozstx_get_status()`.
- The rate limit allowed only 4 new sessions per IP address per hour. Ukrainian mobile networks share IP addresses (CGNAT), so real visitors were dropped. Each event also wrote 3–4 transients.
- The `sessionStorage` session ID was created before any consent.
- Site-specific defaults (`data-uafree-*`, `uafree_donation_event`) were baked into the public plugin.
- Reports had no funnel, chart, payment-service breakdown or period comparison.
- The readme had no FAQ and still mentioned PayPal in Support. The admin class is 1,005 lines.

## Changes

### 1. Support panel and readme
- PayPal removed from the panel and the readme.
- BTC `bc1q4dn8e7sz2866g7qplqtshh98j54tvuau5ghuuk`
- ETH/USDC `0x3aE3b23A7BD94b8a65A7E8Ca205A4e29BEF7c229`
- USDT TRC-20 `TYSgyk7K3XB4WPHprf5w8ZodFAfxFfDdbF`

### 2. Language versions
- `static_translate_languages()` calls `kozstx_get_status()` first; the UA FREE function is now only a fallback.

### 3. Rate limits
- **Per session:** 180 events per hour (unchanged).
- **Per IP + context:** 600 events and 60 new sessions per hour, stored in one counter (the bucket keeps a list of short session prefixes).
- **Writes:** 2 per event instead of 3–4.
- **Storage:** the object cache when available, otherwise transients.

### 4. Confirmed donations from payment services (`class-providers.php`)
- **`POST /wp-json/kozdonate/v1/liqpay`:**
  - checks the signature `base64(sha1(private_key . data . private_key))`;
  - counts only `status = success`; sandbox, failed and reversed payments are acknowledged but not counted;
  - the reference is `payment_id` or `order_id`;
  - the amount and currency are taken from the callback.
- **`POST /wp-json/kozdonate/v1/paypal`:**
  - verifies the IPN with a postback to `ipnpb.paypal.com` (sandbox when `test_ipn=1`, never counted);
  - counts only `payment_status = Completed`;
  - optionally checks `receiver_email`;
  - the reference is `txn_id`.
- **What is stored:** only an HMAC hash of `provider:reference`, which prevents duplicates. No name, email or card data.
- **Settings:**
  - "Accept LiqPay callbacks";
  - LiqPay private key: a password field, never printed back; an empty field keeps the saved key, and there is a "Delete the saved private key" checkbox;
  - "Accept PayPal IPN" and an optional receiver email;
  - copyable callback URLs.
- **When nothing is configured** both endpoints answer 403.
- **The existing signed `/confirm` callback** now also accepts optional `amount` and `currency`.

### 5. Amounts (off by default)
- **Storage:** new `amount decimal(14,2) NULL` and `currency char(3)` columns in the confirmations table (DB 1.2.0). They are written only while "Store donation amounts and currencies" is on.
- **Turning it off** deletes the stored amounts.
- **Report:** "Confirmed amounts" by currency, with a note that this is not an accounting ledger.

### 6. Consent and storage (`frontend.js`)
- **Setting:** "Use browser storage only after analytics consent", on by default.
- **Before consent:** the session ID lives only in the page's memory (`runtime.memoryId`), so a page reload counts as a new visit.
- **After consent:** the ID is kept in `sessionStorage`.
- **Where consent is read from:** KOZ Consent Manager, then WP Consent API (analytics → statistics, advertising → marketing). Without any consent system the previous behaviour is kept.

### 7. Reports (`class-insights.php`)
- **Funnel:** visits → "Support" clicks → payment opens → confirmed donations, with the change against the previous period of the same length.
- **Daily chart:** inline SVG, sessions and payment opens, with a marker on days with confirmations.
- **Payment services:** opens and confirmations per provider (LiqPay, monobank, PayPal, …), mapped from host or element ID.
- **Confirmed amounts:** shown when the option is on.
- **Dashboard widget:** "Donations, last 7 days", with the change against the previous 7 days.
- **Site Health test `kozdonate_tracking`:**
  - no page selected;
  - no events in 7 days;
  - payment opens but no confirmations connected.

### 8. GA4 (optional, off)
- When "Also send GA4 recommended events" is on, the plugin sends `begin_checkout` on a payment open and `purchase` on the success marker.
- Events go through `gtag`, or to the dataLayer if gtag is absent.
- They are sent only when `dataLayerAllowed()` returns true (consent).
- The existing Google Ads dataLayer event is unchanged.

### 9. Neutral defaults and automatic detection
- **New installs:** `data-kozdonate-donate|payment|copy|donation-success|target`. The `data-uafree-*` attributes still work, and existing settings (uafree.org) are untouched.
- **Known payment hosts recognised without configuration** (setting on by default, list filterable with `kozdonate_known_payment_hosts`):
  - LiqPay, monobank (send.monobank.ua, pay.mbnk.biz), Privat24;
  - PayPal, Stripe;
  - WayForPay, Fondy;
  - Buy Me a Coffee, Ko-fi, Patreon, Open Collective, Donorbox.

### 10. Architecture
- New classes `KOZDONATE_Providers` (payment services) and `KOZDONATE_Insights` (reports, widget, Site Health).
- Settings version 7: new keys are added and saved values are kept.
- `update_option` hook for deleting amounts.

### 11. Translations and documentation
- 46 new admin strings in Ukrainian; the plugin has no visitor-facing text.
- **readme:**
  - new short description;
  - feature list;
  - 3 FAQ entries (real donations, amounts, consent);
  - changelog and upgrade notice;
  - PayPal removed from Support.

## Testing on uafree.org

**Installation**
- Installed by upload ("Replace current with uploaded"); no PHP errors.
- Settings migrated: selectors, hosts, Ad Grants, event name, consent category and confirmation mode are all unchanged. The new options take their defaults.

**Report (30 days)**
- Funnel:

| Step | Value | vs previous 30 days |
|---|---|---|
| Visited | 123 | +108% |
| Support | 69 | +214% |
| Payment opens | 77 | +250% |
| Confirmed | 0 | — |

- Chart: 60 bars (30 days × 2).
- Services: LiqPay 46, monobank 29, PayPal 2.

**Front end**
- `/donate/` and `/en/donate/` both have context `post:50`, 20 known hosts and `storageConsent` on.

**Endpoints, not yet configured**
- `/liqpay`: 403 "LiqPay confirmations are not configured".
- `/paypal`: 403.
- `/confirm`: 403 "Confirmation webhook is disabled".

**Built-in browser, no sign-in**
- **Before consent:** the banner is shown, nothing is in `sessionStorage`, and an in-memory session ID is used.
- **After "Accept all":** the ID is in `sessionStorage` and the `kozconsent_update` event is in the dataLayer.
- These two test visits added 2 page-view events to the statistics.

**Admin checks**
- Site Health: "Donation statistics need attention", because payment pages are opened but no confirmations are connected. This is correct.
- Dashboard widget: 7-day numbers with the change against the previous 7 days.
- Plugin Check: "Checks complete. No errors found."

**Not tested live**
- A valid LiqPay or PayPal confirmation. The site owner enters the LiqPay private key and sets the callback URL in the LiqPay and PayPal accounts. A real or sandbox payment is then needed to see the first confirmed donation.
