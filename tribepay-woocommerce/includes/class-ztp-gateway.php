<?php
/**
 * Author: Salman | Version: 1.2.0
 * Description: WooCommerce gateway settings and hosted checkout initiation.
 * Copyright (C) 2026 Salman. SPDX-License-Identifier: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) { exit; }
class ZTP_Gateway extends WC_Payment_Gateway {
    public function __construct() {
        $this->id = 'tribepay';
        $this->method_title = 'TribePay';
        $this->method_description = 'Custom hosted checkout integration. Verify your assigned gateway and GBP support before enabling live payments.';
        $this->has_fields = false;
        $this->supports = array('products');
        $this->init_form_fields(); $this->init_settings();
        $this->title = $this->get_option('title', 'Pay by card with TribePay');
        $this->description = $this->get_option('description', 'Pay securely on the TribePay payment page.');
        $this->enabled = $this->get_option('enabled', 'no');
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array('title' => 'Enable TribePay', 'type' => 'checkbox', 'label' => 'Offer TribePay at checkout', 'default' => 'no'),
            'title' => array('title' => 'Checkout title', 'type' => 'text', 'default' => 'Pay by card with TribePay'),
            'description' => array('title' => 'Checkout description', 'type' => 'textarea', 'default' => 'Pay securely on the TribePay payment page.'),
            'live_notice' => array('title' => 'Live payments', 'type' => 'title', 'description' => 'This build uses live credentials only. Successful customer payments charge real money. Existing sandbox settings are ignored.'),
            'api_key' => array('title' => 'API key', 'type' => 'password', 'description' => 'X-API-Key. Or define ZTP_API_KEY in wp-config.php.'),
            'secret_key' => array('title' => 'Secret key', 'type' => 'password', 'description' => 'X-Secret-Key. Or define ZTP_SECRET_KEY in wp-config.php.'),
            'webhook_secret' => array('title' => 'Webhook signing secret', 'type' => 'password', 'description' => 'Optional for initial checkout. Without it, server status checks verify payments; unsigned webhooks are rejected. Separate secret provided when creating the webhook. Or define ZTP_WEBHOOK_SECRET in wp-config.php.'),
            'currencies' => array('title' => 'Allowed checkout currencies', 'type' => 'text', 'default' => '', 'description' => 'Optional comma separated list, for example GBP. Blank uses the WooCommerce store currency. This setting does not enable currencies at TribePay; unsupported currencies are rejected by the provider. No conversion is performed.')
        );
    }
    public function validate_password_field($key, $value) {
        // Do not erase a saved secret when a password manager leaves a field blank.
        return $value === '' ? $this->get_option($key, '') : trim((string) wp_unslash($value));
    }
    public function allowed_currencies() {
        $value = strtoupper(trim((string) $this->get_option('currencies', '')));
        return $value === '' ? array(get_woocommerce_currency()) : array_filter(array_map('trim', explode(',', $value)));
    }
    public function admin_options() {
        parent::admin_options();
        echo '<h3>Webhook setup</h3><p>Install the plugin first, then add this endpoint in TribePay Developer &rarr; Webhooks:</p><p><code>' . esc_html(ZTP_Service::endpoint('webhook')) . '</code></p>';
        echo '<p>Subscribe to payment.completed, payment.failed and payment.refunded. Save the signing secret above. Keep WP Cron working; a real server cron is recommended for reliable background verification.</p>';
        echo '<h3>Configuration status</h3>';
        $errors = ZTP_API::configuration_errors();
        if (!in_array(get_woocommerce_currency(), $this->allowed_currencies(), true)) { $errors[] = 'The store currency is excluded from Allowed checkout currencies.'; }
        if ($this->enabled !== 'yes') { $errors[] = 'Enable TribePay to offer it at checkout.'; }
        foreach ($errors as $error) { echo '<p><strong>' . esc_html($error) . '</strong></p>'; }
        if (!$errors) { echo '<p>Local configuration is ready for a live checkout attempt. Provider acceptance has not been verified by this screen.</p>'; }
        if (!ZTP_API::value('webhook_secret')) { echo '<p>Webhook not configured. Checkout remains available; customer returns and scheduled server checks verify payment. Configure signed webhooks for reliable notifications, including refunds.</p>'; }
        echo '<p>Currency used at checkout: <strong>' . esc_html(get_woocommerce_currency()) . '</strong>. Live processing still depends on the assigned TribePay gateway accepting this currency.</p>';
    }
    public function is_available() {
        if (!parent::is_available() || !ZTP_API::ready()) { return false; }
        $currencies = $this->allowed_currencies();
        return in_array(get_woocommerce_currency(), $currencies, true);
    }
    public function process_payment($order_id) {
        if (!ZTP_API::ready()) { wc_add_notice('TribePay is not configured. Please choose another payment method.', 'error'); return array('result' => 'failure'); }
        if (!ZTP_Service::lock($order_id)) { wc_add_notice('This payment is already being processed. Please wait before retrying.', 'error'); return array('result' => 'failure'); }
        try {
            $order = wc_get_order($order_id);
            if (!$order || $order->get_payment_method() !== $this->id) { throw new Exception('The order could not be verified.'); }
            if ($order->is_paid()) { return array('result' => 'success', 'redirect' => $this->get_return_url($order)); }
            if ($order->has_status(array('cancelled', 'refunded'))) { throw new Exception('This order cannot accept another payment.'); }
            $allowed = $this->allowed_currencies();
            if (!in_array($order->get_currency(), $allowed, true)) { throw new Exception('This currency is not enabled for TribePay.'); }
            $total = (float) $order->get_total();
            if ($total < 1 || $total > 1000000) { throw new Exception('The order amount is outside the supported TribePay range.'); }
            $attempts = (array) $order->get_meta('_ztp_attempts');
            $ref = $order->get_meta('_ztp_current');
            if ($ref && isset($attempts[$ref])) {
                $state = ZTP_API::status($ref);
                if (is_wp_error($state)) {
                    $error_data = (array) $state->get_error_data();
                    // Only a definitive not-found permits retrying the SAME reference.
                    if (($error_data['status'] ?? 0) !== 404 || !empty($attempts[$ref]['checkout_url'])) {
                        throw new Exception('We could not confirm the previous payment. Check your order before retrying.');
                    }
                } else {
                    $status = ZTP_Service::apply($order, $ref, $state);
                    if (is_wp_error($status)) { throw new Exception($status->get_error_message()); }
                    if ($status === 'completed') { return array('result' => 'success', 'redirect' => $this->get_return_url($order)); }
                    if (in_array($status, array('pending', 'processing'), true)) {
                        $url = $attempts[$ref]['checkout_url'] ?? ($state['checkout_url'] ?? '');
                        if (ZTP_API::checkout_url($url)) { $attempts = (array) $order->get_meta('_ztp_attempts'); $attempts[$ref]['checkout_url'] = esc_url_raw($url); $order->update_meta_data('_ztp_attempts', $attempts); $order->save(); }
                        if (!ZTP_API::checkout_url($url)) { throw new Exception('The existing payment is awaiting confirmation. Please check the order before retrying.'); }
                        return array('result' => 'success', 'redirect' => $url);
                    }
                    if ($status === 'refunded') { throw new Exception('This payment was refunded. Please contact the store.'); }
                    $ref = '';
                    $attempts = (array) $order->get_meta('_ztp_attempts');
                }
            }
            if (!$ref || !isset($attempts[$ref])) {
                $ref = 'ztp-' . $order->get_id() . '-' . substr(str_replace('-', '', wp_generate_uuid4()), 0, 20);
                $attempts[$ref] = array('amount' => wc_format_decimal($order->get_total(), wc_get_price_decimals()), 'currency' => $order->get_currency(), 'decimals' => wc_get_price_decimals(), 'mode' => ZTP_API::value('mode') === 'live' ? 'live' : 'sandbox', 'status' => 'creating');
                $order->update_meta_data('_ztp_attempts', $attempts); $order->update_meta_data('_ztp_current', $ref); $order->save();
            }
            $attempt = $attempts[$ref];
            if ($attempt['amount'] !== wc_format_decimal($order->get_total(), $attempt['decimals']) || $attempt['currency'] !== $order->get_currency()) { throw new Exception('The order changed after a payment was started. Please contact the store.'); }
            $return = ZTP_Service::return_url($order);
            $payload = array('order_id' => $ref, 'amount' => (float) $attempt['amount'], 'currency' => $attempt['currency'],
                'customer_name' => $order->get_formatted_billing_full_name(), 'customer_email' => $order->get_billing_email(),
                'customer_phone' => $order->get_billing_phone(), 'description' => 'Order ' . $order->get_order_number(),
                'return_url' => $return, 'cancel_url' => $return);
            if (ZTP_API::value('webhook_secret') !== '') { $payload['webhook_url'] = ZTP_Service::endpoint('webhook'); }
            ZTP_Service::schedule($order->get_id(), $ref);
            $result = ZTP_API::request('POST', '/api/pay/initiate', $payload);
            if (is_wp_error($result)) { throw new Exception($result->get_error_message()); }
            if (!ZTP_Service::matches($result, $attempt, $ref) || empty($result['checkout_url']) || !ZTP_API::checkout_url($result['checkout_url'])) {
                throw new Exception('TribePay returned an unexpected checkout response. Please contact the store before retrying.');
            }
            $attempts[$ref]['checkout_url'] = esc_url_raw($result['checkout_url']);
            $attempts[$ref]['reference'] = isset($result['reference']) ? sanitize_text_field($result['reference']) : '';
            $attempts[$ref]['status'] = 'pending';
            $order->update_meta_data('_ztp_attempts', $attempts); $order->save();
            if ($order->has_status('failed')) { $order->update_status('pending', 'New TribePay payment session created.'); }
            $order->add_order_note('TribePay checkout created. Awaiting server verified payment. Order reference: ' . $ref);
            return array('result' => 'success', 'redirect' => $result['checkout_url']);
        } catch (Exception $e) {
            if (isset($order) && $order) { $order->add_order_note('TribePay checkout error: ' . $e->getMessage()); }
            wc_add_notice($e->getMessage(), 'error');
            return array('result' => 'failure');
        } finally { ZTP_Service::unlock($order_id); }
    }
}
