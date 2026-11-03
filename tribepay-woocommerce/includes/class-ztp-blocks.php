<?php
/**
 * Author: Salman | Version: 1.2.0
 * Description: WooCommerce Checkout Blocks integration.
 * Copyright (C) 2026 Salman. SPDX-License-Identifier: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) { exit; }
final class ZTP_Blocks extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
    protected $name = 'tribepay';
    public function initialize() { $this->settings = (array) get_option('woocommerce_tribepay_settings', array()); }
    private function gateway() { $gateways = WC()->payment_gateways()->payment_gateways(); return $gateways['tribepay'] ?? null; }
    public function is_active() { $gateway = $this->gateway(); return $gateway && $gateway->is_available(); }
    public function get_payment_method_script_handles() {
        wp_register_script('ztp-checkout-blocks', plugins_url('assets/js/checkout-blocks.js', ZTP_FILE), array('wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'), ZTP_VERSION, true);
        return array('ztp-checkout-blocks');
    }
    public function get_payment_method_data() {
        $g = $this->gateway();
        return array('title' => $g ? $g->title : 'TribePay', 'description' => $g ? wp_strip_all_tags($g->description) : '', 'supports' => array('products'));
    }
}
