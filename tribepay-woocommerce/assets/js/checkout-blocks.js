/**
 * Author: Salman | Version: 1.2.0
 * Description: Registers the TribePay payment option with WooCommerce Checkout Blocks.
 * Copyright (C) 2026 Salman. SPDX-License-Identifier: GPL-2.0-or-later
 */
/* global wc, wp */
(function () {
    'use strict';
    const settings = wc.wcSettings.getSetting('tribepay_data', {});
    const decode = wp.htmlEntities.decodeEntities;
    const title = decode(settings.title || 'TribePay');
    const content = wp.element.createElement('div', null, decode(settings.description || 'Pay securely on the TribePay payment page.'));
    wc.wcBlocksRegistry.registerPaymentMethod({
        name: 'tribepay', label: wp.element.createElement('span', null, title),
        content: content, edit: content, ariaLabel: title,
        canMakePayment: function () { return true; },
        supports: { features: settings.supports || ['products'] }
    });
}());
