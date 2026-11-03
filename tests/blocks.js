/**
 * Author: Salman | Version: 1.2.0
 * Description: Offline Checkout Blocks registration tests.
 * Copyright (C) 2026 Salman. SPDX-License-Identifier: GPL-2.0-or-later
 */
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
let registered;
const context = {wc:{wcSettings:{getSetting:()=>({title:'TribePay',description:'Hosted checkout',supports:['products']})},wcBlocksRegistry:{registerPaymentMethod:(value)=>{registered=value;}}},wp:{htmlEntities:{decodeEntities:(s)=>s},element:{createElement:(tag,props,...children)=>({tag,props,children})}}};
vm.runInNewContext(fs.readFileSync(__dirname+'/../tribepay-woocommerce/assets/js/checkout-blocks.js','utf8'),context);
assert.equal(registered.name,'tribepay');
assert.equal(registered.ariaLabel,'TribePay');
assert.equal(registered.content.children[0],'Hosted checkout');
assert.equal(registered.supports.features[0],'products');
assert.equal(registered.canMakePayment(),true);
console.log('PASS 5 Checkout Blocks registration checks');
