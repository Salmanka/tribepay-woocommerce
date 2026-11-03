<?php
/**
 * Author: Salman | Version: 1.2.0
 * Description: Payment verification, signed webhooks and safe customer return redirects.
 * Copyright (C) 2026 Salman. SPDX-License-Identifier: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) { exit; }
final class ZTP_Service {
    public static function endpoint($name) { return WC()->api_request_url('tribepay_' . $name); }
    public static function lock($id) {
        global $wpdb;
        return '1' === (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', 'ztp_' . md5($wpdb->prefix . ':' . $id)));
    }
    public static function unlock($id) {
        global $wpdb;
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', 'ztp_' . md5($wpdb->prefix . ':' . $id)));
    }
    public static function schedule($id, $ref, $count = 0) {
        $args = array((int) $id, $ref, (int) $count);
        if (!wp_next_scheduled('ztp_verify_payment', $args)) { wp_schedule_single_event(time() + 120, 'ztp_verify_payment', $args); }
    }
    public static function poll($id, $ref, $count) {
        $result = self::synchronize($id, $ref);
        if ($count < 19 && (is_wp_error($result) || in_array($result, array('pending', 'processing'), true))) {
            self::schedule($id, $ref, $count + 1);
        }
    }
    public static function matches($data, $attempt, $ref) {
        if (!isset($data['order_id'], $data['amount'], $data['currency'], $data['mode']) || (string) $data['order_id'] !== $ref) { return false; }
        if (!is_numeric($data['amount']) || strtoupper((string) $data['currency']) !== $attempt['currency'] || $data['mode'] !== $attempt['mode']) { return false; }
        $precision = $attempt['decimals'];
        if (abs((float) $data['amount'] - (float) $attempt['amount']) > 0.000001) { return false; }
        if (wc_format_decimal($data['amount'], $precision) !== wc_format_decimal($attempt['amount'], $precision)) { return false; }
        if (!empty($attempt['reference']) && (!isset($data['reference']) || $data['reference'] !== $attempt['reference'])) { return false; }
        return true;
    }
    public static function apply($order, $ref, $data) {
        $attempts = (array) $order->get_meta('_ztp_attempts');
        if (empty($attempts[$ref]) || !self::matches($data, $attempts[$ref], $ref)) {
            return new WP_Error('ztp_mismatch', 'TribePay payment identity, mode, amount or currency did not match.');
        }
        if ($order->get_currency() !== $attempts[$ref]['currency'] || wc_format_decimal($order->get_total(), $attempts[$ref]['decimals']) !== $attempts[$ref]['amount']) {
            return new WP_Error('ztp_changed_order', 'The WooCommerce order total or currency has changed. Manual reconciliation is required.');
        }
        $status = isset($data['status']) ? (string) $data['status'] : '';
        if (!in_array($status, array('pending', 'processing', 'completed', 'failed', 'cancelled', 'expired', 'refunded'), true)) {
            return new WP_Error('ztp_status', 'Unknown TribePay payment status.');
        }
        $old = isset($attempts[$ref]['status']) ? $attempts[$ref]['status'] : '';
        $attempts[$ref]['status'] = $status;
        if (!empty($data['reference'])) { $attempts[$ref]['reference'] = sanitize_text_field($data['reference']); }
        $order->update_meta_data('_ztp_attempts', $attempts);
        $order->save();
        if ('completed' === $status) {
            $transaction = !empty($data['reference']) ? sanitize_text_field($data['reference']) : $ref;
            if ($order->is_paid()) {
                if ($order->get_transaction_id() !== $transaction && $old !== $status) {
                    $order->add_order_note('Another TribePay payment completed for this order. Reconcile duplicate payment manually. Reference: ' . $transaction);
                }
            } elseif (!$order->has_status(array('cancelled', 'refunded'))) {
                $order->payment_complete($transaction);
                $order->add_order_note('TribePay payment verified with the server. Reference: ' . $transaction);
            } elseif ($old !== $status) {
                $order->add_order_note('TribePay reports a completed payment on a cancelled or refunded order. Manual reconciliation required. Reference: ' . $transaction);
            }
        } elseif ($status === 'refunded') {
            if ($old !== $status) { $order->add_order_note('TribePay reports a refund. Review the merchant transaction and reconcile the WooCommerce refund manually.'); }
        } elseif (in_array($status, array('failed', 'cancelled', 'expired'), true) && !$order->is_paid() && $order->get_meta('_ztp_current') === $ref && !$order->has_status(array('cancelled', 'refunded'))) {
            if ($old !== $status) { $order->update_status('failed', 'TribePay verified payment status: ' . $status . '. Customer can retry.'); }
        }
        return $status;
    }
    public static function synchronize($id, $ref) {
        if (!self::lock($id)) { return new WP_Error('ztp_busy', 'Payment verification is already in progress.'); }
        try {
            $order = wc_get_order($id);
            if (!$order || $order->get_payment_method() !== 'tribepay') { return new WP_Error('ztp_order', 'Order not found.'); }
            $attempts = (array) $order->get_meta('_ztp_attempts');
            if (!isset($attempts[$ref])) { return new WP_Error('ztp_attempt', 'Payment attempt not found.'); }
            $data = ZTP_API::status($ref);
            return is_wp_error($data) ? $data : self::apply($order, $ref, $data);
        } finally { self::unlock($id); }
    }
    public static function respond($code, $text) { status_header($code); nocache_headers(); header('Content-Type: text/plain; charset=utf-8'); echo esc_html($text); exit; }
    public static function valid_signature($raw, $signature, $secret) {
        return is_string($raw) && is_string($signature) && $secret !== ''
            && preg_match('/^[a-fA-F0-9]{64}$/D', $signature)
            && hash_equals(hash_hmac('sha256', $raw, $secret), strtolower($signature));
    }
    public static function handle_webhook() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { self::respond(405, 'POST required'); }
        $secret = ZTP_API::value('webhook_secret');
        $sig = isset($_SERVER['HTTP_X_TRIBEPAY_SIGNATURE']) ? trim((string) $_SERVER['HTTP_X_TRIBEPAY_SIGNATURE']) : '';
        $raw = file_get_contents('php://input', false, null, 0, 65537);
        if ($raw === false || strlen($raw) > 65536) { self::respond(413, 'Invalid payload'); }
        if (!self::valid_signature($raw, $sig, $secret)) { self::respond(401, 'Invalid signature'); }
        $event = json_decode($raw, true);
        if (!is_array($event) || !isset($event['event'], $event['data']['order_id']) || !is_string($event['data']['order_id'])) { self::respond(400, 'Invalid event'); }
        if (!in_array($event['event'], array('payment.initiated', 'payment.completed', 'payment.failed', 'payment.refunded'), true)) { self::respond(200, 'Ignored'); }
        $ref = $event['data']['order_id'];
        if (!preg_match('/^ztp-([0-9]+)-[a-f0-9]{20}$/D', $ref, $match)) { self::respond(200, 'Unrelated order'); }
        $id = (int) $match[1];
        $order = wc_get_order($id);
        if (!$order || $order->get_payment_method() !== 'tribepay' || !isset(((array) $order->get_meta('_ztp_attempts'))[$ref])) { self::respond(200, 'Unrelated payment'); }
        // Acknowledge promptly; WP Cron queries the API and performs all state changes.
        // An event, even when signed, never directly marks an order paid.
        self::schedule($id, $ref);
        self::respond(200, 'Accepted');
    }
    public static function return_url($order) {
        // The final disposable parameter absorbs providers that append a second question mark.
        // Keep both authentication fields before it. Browser status is never trusted.
        return add_query_arg(array('ztp_order' => $order->get_id(), 'key' => $order->get_order_key(), 'ztp_return' => '1'), self::endpoint('return'));
    }
    public static function return_order($query) {
        if (!is_array($query) || !isset($query['ztp_order'], $query['key']) || !is_scalar($query['ztp_order']) || !is_string($query['key'])) { return false; }
        $id = (string) $query['ztp_order'];
        if (!preg_match('/^[1-9][0-9]*$/D', $id)) { return false; }
        $order = wc_get_order((int) $id);
        if (!$order || $order->get_payment_method() !== 'tribepay') { return false; }
        $key = wp_unslash($query['key']);
        // Compatibility with already issued v1.0/v1.1 sessions: ?status was appended to key.
        // Strip only that specific provider suffix, then still compare the ENTIRE stored key.
        $key = preg_replace('/\?status=(?:completed|failed|cancelled|expired|pending|processing)(?:&.*)?$/D', '', $key);
        if (!is_string($key) || !hash_equals($order->get_order_key(), $key)) { return false; }
        return $order;
    }
    public static function handle_return() {
        $order = self::return_order($_GET);
        if (!$order) { wp_die('Invalid payment return.', 'TribePay', array('response' => 403)); }
        $id = $order->get_id();
        nocache_headers();
        $ref = $order->get_meta('_ztp_current');
        if ($ref) { self::synchronize($id, $ref); }
        $order = wc_get_order($id);
        if ($order->is_paid()) {
            if (WC()->cart) { WC()->cart->empty_cart(); }
            wp_safe_redirect($order->get_checkout_order_received_url());
        } elseif ($order->has_status(array('cancelled', 'refunded'))) {
            wp_safe_redirect($order->get_checkout_order_received_url());
        } else {
            wc_add_notice('Your payment has not been confirmed yet. Please check the order status before attempting another payment.', 'notice');
            wp_safe_redirect($order->get_checkout_payment_url());
        }
        exit;
    }
}
