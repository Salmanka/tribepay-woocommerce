# TribePay Gateway for WooCommerce by Salman

**Author:** Salman  
**Version:** 1.2.0  
**License:** GPL-2.0-or-later

Developed by Salman. An open-source WooCommerce plugin that redirects customers to TribePay hosted card checkout and verifies payment status on the server before marking an order paid. Includes signed webhook handling, background status checks, Checkout Blocks registration, HPOS-compatible order access and return URL compatibility handling.

This is an independent community integration, not an official TribePay or WooCommerce release. No merchant account, keys or customer data are bundled. Merchants must obtain their own valid TribePay credentials and gateway/currency approval. The current build uses live credentials only; successful payments charge real money.

## Install

Upload the installable plugin ZIP through WordPress Plugins > Add New > Upload Plugin. If updating the same plugin, choose Replace current with uploaded. Activate only one copy. For a source checkout, copy the `tribepay-woocommerce` directory to `wp-content/plugins/`.

Open WooCommerce > Settings > Payments > TribePay. Enter your own live keys, configure the allowed currencies or use the store currency, and enable the gateway. Configure the signed webhook endpoint shown in settings. Full configuration and troubleshooting instructions are in [the plugin guide](tribepay-woocommerce/README.md).

The repository source ZIP is for GitHub and development. It is not the WordPress upload ZIP.

## Compatibility and scope

Declared minimums: WordPress 6.5, WooCommerce 8.3, PHP 7.4. These declarations are not a tested version matrix. HTTPS and MySQL/MariaDB advisory lock support are required. Real server cron is recommended. One-time product payments are supported; subscriptions, saved cards and automatic refunds are not.

## Checks

```sh
php tests/run.php
node tests/blocks.js
python3 tests/check-return-http.py
```

The HTTP checks start a temporary localhost server. Tests use mocked WordPress/WooCommerce/provider dependencies and do not contact the live gateway. Local checks do not certify every WordPress theme, server or merchant configuration. Version 1.2.0 changes attribution and release documentation only; it preserves the working 1.1.1 payment logic. See [TEST-RESULTS.md](TEST-RESULTS.md).

## Repository contents

- `tribepay-woocommerce/`: installable plugin source, assets and setup guide.
- `tests/`: offline PHP, JavaScript and local HTTP regression checks.
- `CHANGELOG.md`: release history.
- `LICENSE`: project licensing and full GPL v2 terms.
- `SECURITY.md`: guidance for reporting issues without disclosing credentials.
- `CONTRIBUTING.md`: contribution and test expectations.

## Privacy and external service

Payment initiation sends customer billing name, email, phone, order reference, total, currency, return URL and configured webhook URL to `https://api.tribe-pay.com`. API keys are sent in HTTPS headers. The return URL carries the specific WooCommerce order key. Card details are entered on the provider page. Review your merchant agreement and update your store privacy notice before enabling the gateway.

Official provider API documentation: https://docs.tribe-pay.com/

## License and attribution

Copyright (C) 2026 Salman. You may use, modify and redistribute the project under GPL version 2 or, at your option, any later version. Preserve copyright and license notices. Supplied without warranty. Third-party trademarks remain the property of their owners. No GitHub username, email or author URL has been invented for this release.
