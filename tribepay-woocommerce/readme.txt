=== TribePay Gateway for WooCommerce by Salman ===
Author: Salman
Tags: woocommerce, payments, hosted checkout
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Description: Live hosted checkout integration developed by Salman for WooCommerce.
Copyright (C) 2026 Salman. Not an official TribePay plugin.

== Description ==
Hosted checkout, server verified payment status, optional signed webhooks,
background reconciliation, classic checkout and Checkout Blocks registration.
Supports HPOS through WooCommerce CRUD APIs. Live keys only; real successful
payments charge money. No credentials are included.

Webhook configuration no longer blocks the initial checkout. Without a signing
secret, return and background status queries verify payments; unsigned webhooks
are always rejected. Blank allowed currencies uses the store currency.
Provider currency acceptance still depends on the assigned merchant gateway.

See README.md for installation, live validation and limitations.

== External service ==
The plugin sends initiation and payment status requests to https://api.tribe-pay.com.
Initiation shares order reference, amount, currency, billing name, email, phone,
description, return URL and (when configured) webhook URL. API credentials are
sent in HTTPS request headers. The return URL includes the specific order key.
Cards are entered on the provider's hosted checkout, never on this plugin.
Documentation: https://docs.tribe-pay.com/
Merchant portal: https://merchant.tribe-pay.com/
Review your merchant agreement, applicable provider terms and privacy policy.
Update the store privacy notice to describe the payment provider.

== Installation ==
1. Back up the website and database.
2. Upload the ZIP. Choose Replace current with uploaded if updating version 1.0.0.
3. Open WooCommerce > Settings > Payments > TribePay.
4. Enter matching live keys, configure allowed currencies or use the store default.
5. Enable, save and review Configuration status.
6. Configure signed webhooks using the endpoint shown in settings.
7. Follow README.md to validate your own live payment and WooCommerce order update.

== Changelog ==
= 1.2.0 =
Public release by Salman. Author attribution, reusable documentation and license packaging. Payment processing behavior unchanged from 1.1.1.

= 1.1.1 =
Fix malformed provider return queries while preserving full order key validation.
Protect new return URLs with a final disposable query parameter. Existing sessions
remain supported. Paid Processing orders redirect without changing fulfillment status.

= 1.1.0 =
Live-only configuration. Explicit diagnostics. Optional signed webhooks with
API verification fallback. Store currency default. Checkout response field
normalization. Safe provider HTTP errors and order notes. Recovery of pending
checkout URLs from status responses when available.

= 1.0.0 =
Initial custom build.
