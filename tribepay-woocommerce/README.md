# TribePay Gateway for WooCommerce by Salman

Author: Salman | Version: 1.2.0

Description: Open-source WooCommerce hosted payment gateway with server verification and secure return redirects.

Copyright (C) 2026 Salman. Licensed under GPL-2.0-or-later.

A reusable hosted checkout integration developed by Salman for merchants using TribePay. This is not an official TribePay plugin. This build is live-only. No sandbox or live payment has been executed by the developer. Local tests validate code paths, not your merchant account, hosting or provider acceptance.

## Return URL compatibility

Fixes Invalid payment return when the provider appends a second question mark to an existing return URL query. Already issued sessions remain supported: the handler removes only the recognized appended status suffix before comparing the entire stored order key. New sessions add a disposable final query parameter so both identity fields remain intact with either separator.

Correct order ID, full order key and TribePay payment method are still required. Browser status does not establish payment. The API verification remains in place. Paid Processing and Completed orders redirect to WooCommerce Order received without forcing fulfillment status to Completed.

Local validation passed 62 PHP assertions, 5 Blocks checks and 8 real localhost HTTP checks with mocked WooCommerce/provider dependencies. The HTTP checks verify 302 Location headers for paid and server-verified orders and 403 responses for wrong keys. They also confirm that a browser completed status cannot override a pending API status. This is not a live website or real payment retest.

Upload this ZIP and replace the existing plugin. Do not pay again merely to recheck an already paid order. After updating, the previous return link can be retried if it still contains the correct order ID and key. Treat order return URLs as private. The store owner must confirm the redirect on the actual website.

## Install or update

1. Back up your WordPress files and database.
2. Upload `tribepay-woocommerce.zip` through Plugins > Add New > Upload Plugin.
3. If an earlier version is installed, choose Replace current with uploaded. Do not activate both versions. Existing saved keys and order references are preserved.
4. Open WooCommerce > Settings > Payments > TribePay.
5. Enter your live API key (`pk_live_...`) and matching live secret (`sk_live_...`). Existing saved keys remain available. Blank password fields preserve previous saved values.
6. Leave Allowed checkout currencies blank to use the store currency, or enter `GBP` explicitly. This is a local checkout filter, not a request to enable GBP at TribePay. The provider must accept GBP for the merchant account.
7. Enable TribePay and save. The Configuration status section identifies missing HTTPS, invalid key prefixes, a disabled gateway or an excluded store currency.
8. Visit checkout in a customer session. Select Pay by card with TribePay. Complete the customer and address fields and verify the total before submitting.
9. Submitting checkout creates a real WooCommerce order and a real TribePay checkout session. Complete the payment yourself using your own authorized payment method. Successful payment charges real money.
10. Confirm that the customer returns to the store, the order is marked paid with the correct amount/currency and a provider reference appears in order notes. A redirect alone does not prove payment success.

Version 1.1.0 ignores the old Environment setting and uses live keys only. The website must have a valid HTTPS certificate. No live keys are embedded in this ZIP.

## Webhook setup

The webhook signing secret is optional for the initial live checkout. Without it, the plugin relies on the customer return and background API checks. It never accepts unsigned webhook events.

For notifications when a customer closes the browser, and for refund notices:

1. Copy the exact webhook URL displayed in WooCommerce gateway settings.
2. In TribePay Developer > Webhooks, add that URL.
3. Select payment.completed, payment.failed and payment.refunded.
4. Save the signing secret returned by TribePay in the plugin's Webhook signing secret field, then save settings. It is separate from the API secret.
5. Exempt this endpoint from page caching, authentication challenges and WAF challenges. Do not disable certificate verification or signature verification.

A GET visit to the endpoint returns POST required. This proves the handler is routed; it does not prove webhook delivery or payment verification. Valid signed notifications are acknowledged and queued. A scheduled server-to-server status check verifies the actual order before any payment state change.

## Background checks and reconciliation

The plugin schedules status checks every two minutes, up to 20 checks per chain. WordPress Cron requires traffic; configure a real hosting cron for timely updates. If a check fails persistently, use the WooCommerce order action Verify TribePay payment status and review the provider transaction.

If the customer returns and the payment remains unconfirmed, the plugin shows a notice rather than marking the order paid. Do not repeatedly pay while an earlier attempt is uncertain. Pending checkout sessions are reused; a new reference is created only after the earlier attempt is verified as failed, cancelled or expired.

If payment completes after an order was cancelled or refunded, or an additional payment completes for an already paid order, the plugin adds a reconciliation note. Review and reconcile manually. Merchant refund notifications add a note; they do not automatically create a WooCommerce refund. This plugin never initiates refunds.

## Security and supported behavior

- One-time product orders; classic checkout, Checkout Blocks registration and HPOS-compatible order CRUD.
- Hosted checkout: raw card data never passes through this plugin.
- HTTPS API requests to api.tribe-pay.com with both credentials in request headers.
- Decimal order totals with no multiplication by 100 and no currency conversion.
- Normalizes top-level or data-wrapped responses and accepts checkout_url or payment_url. Redirects remain restricted to HTTPS on api.tribe-pay.com/checkout/. Unexpected formats/domains stop checkout rather than redirecting unsafely.
- Requires matching API order reference, amount, currency, live mode and recorded provider reference before completion. Browser status parameters cannot mark an order paid.
- Per-order MySQL/MariaDB advisory locks serialize initiation and verification. The database connection/proxy must support GET_LOCK and RELEASE_LOCK. Lock failure stops payment initiation.
- Signed webhooks require HMAC SHA256 and cannot directly mark an order paid. Missing or invalid signatures return 401.
- Duplicate payment notifications do not repeat fulfillment. Late failure notifications do not downgrade a paid order.
- No subscriptions, saved cards, recurring billing, tokenization, automatic refunds or multicapture support.

## Credentials

WordPress stores WooCommerce gateway settings in its database. Password inputs mask secrets but do not encrypt the database. Protect database backups and admin access. Optional wp-config.php constants override saved credentials:

```php
define('ZTP_API_KEY', 'YOUR_LIVE_API_KEY');
define('ZTP_SECRET_KEY', 'YOUR_LIVE_SECRET_KEY');
define('ZTP_WEBHOOK_SECRET', 'YOUR_WEBHOOK_SIGNING_SECRET');
```

The Merchant ID and dashboard password are not required by the documented hosted payment API and are not sent by the plugin. Reconcile outstanding payments before rotating keys; old references are checked with the currently configured key pair. Uninstall retains settings and financial metadata, and clears scheduled checks.

## Troubleshooting

- Missing from checkout: check Enable TribePay, Configuration status, live key prefixes, HTTPS and allowed currencies; refresh the customer checkout.
- Authentication rejected / HTTP 401: the API key and secret pair is invalid or revoked. A prefix check cannot establish authenticity.
- HTTP 403: the provider denied access; gateway assignment or account restrictions may require TribePay action.
- HTTP 422: the provider rejected payment parameters, which may include unsupported currency, amount or return URL.
- Unexpected checkout response: order identity, amount, currency, mode or checkout URL differs from the expected provider response. Do not repeatedly pay. Review the provider transaction and the order reference.
- Payment remains pending: verify cron, signed webhook delivery and provider transaction status. Use the order verification action.
- Checkout errors are recorded as safe order notes. Raw provider bodies and credentials are not logged.

## Validation status

The package includes offline PHP logic tests, a Checkout Blocks JavaScript registration test and TEST-RESULTS.md. No actual WordPress activation, server cron, real database lock behavior, hosted checkout redirect, provider charge, settlement or refund has been tested. The merchant's gateway assignment and GBP acceptance remain provider-side requirements. Perform the live validation yourself before describing the integration as payment-tested.

API reference: https://docs.tribe-pay.com/
WooCommerce integration reference: https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/payment-method-integration
License: GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html


## Independent project

Developed and maintained by Salman. This project is not endorsed by TribePay, WooCommerce, Visa or Mastercard. Brand names belong to their respective owners. See the repository README, LICENSE and CHANGELOG for release information.
