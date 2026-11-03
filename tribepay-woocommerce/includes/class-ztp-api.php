<?php
/**
 * Author: Salman | Version: 1.2.0
 * Description: Authenticated TribePay API client and checkout response validation.
 * Copyright (C) 2026 Salman. SPDX-License-Identifier: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) { exit; }
final class ZTP_API {
    public static function settings() { return (array) get_option('woocommerce_tribepay_settings', array()); }
    public static function value($name) {
        if ($name === 'mode') { return 'live'; }
        $constant = array('api_key' => 'ZTP_API_KEY', 'secret_key' => 'ZTP_SECRET_KEY', 'webhook_secret' => 'ZTP_WEBHOOK_SECRET');
        if (isset($constant[$name]) && defined($constant[$name])) { return trim((string) constant($constant[$name])); }
        $s = self::settings(); return isset($s[$name]) ? trim((string) $s[$name]) : '';
    }
    public static function configuration_errors() {
        $errors = array();
        if (wp_parse_url(home_url(), PHP_URL_SCHEME) !== 'https') { $errors[] = 'The website address must use HTTPS with a valid certificate.'; }
        if (strpos(self::value('api_key'), 'pk_live_') !== 0) { $errors[] = 'Enter a live API key starting with pk_live_.'; }
        if (strpos(self::value('secret_key'), 'sk_live_') !== 0) { $errors[] = 'Enter a live secret key starting with sk_live_.'; }
        return $errors;
    }
    public static function ready() { return count(self::configuration_errors()) === 0; }
    public static function normalize($data) {
        // Accept the two checkout field names published by the provider. Never treat a URL as proof of payment.
        if (!is_array($data)) { return $data; }
        if (isset($data['data']) && is_array($data['data'])) {
            $nested = $data['data']; unset($data['data']); $data = array_merge($data, $nested);
        }
        if (empty($data['checkout_url']) && !empty($data['payment_url'])) { $data['checkout_url'] = $data['payment_url']; }
        return $data;
    }
    public static function request($method, $path, $body = null) {
        if (self::value('api_key') === '' || self::value('secret_key') === '') { return new WP_Error('ztp_config', 'TribePay credentials are missing.'); }
        $args = array('method' => $method, 'timeout' => 20, 'redirection' => 0, 'sslverify' => true,
            'headers' => array('X-API-Key' => self::value('api_key'), 'X-Secret-Key' => self::value('secret_key'), 'Content-Type' => 'application/json', 'Accept' => 'application/json'));
        if ($body !== null) { $args['body'] = wp_json_encode($body); }
        $response = wp_remote_request('https://api.tribe-pay.com' . $path, $args);
        if (is_wp_error($response)) { return new WP_Error('ztp_network', 'TribePay could not be reached. Check the order before retrying.'); }
        $code = (int) wp_remote_retrieve_response_code($response);
        $data = self::normalize(json_decode(wp_remote_retrieve_body($response), true));
        if ($code < 200 || $code > 299 || !is_array($data) || empty($data['success'])) {
            $messages = array(401 => 'The live API key pair was rejected.', 403 => 'The merchant gateway denied access.', 404 => 'The payment reference was not found.', 409 => 'This payment reference already exists. Verify its status before retrying.', 422 => 'The gateway rejected the payment parameters. Check currency, amount and merchant configuration.', 429 => 'TribePay is rate limiting requests. Please wait before retrying.');
            $message = isset($messages[$code]) ? $messages[$code] : 'TribePay could not confirm this request.';
            return new WP_Error('ztp_api', $message . ' HTTP ' . $code . '.', array('status' => $code));
        }
        return $data;
    }
    public static function status($ref) { return self::request('GET', '/api/pay/status/' . rawurlencode($ref)); }
    public static function checkout_url($url) {
        // Do not let an API error or unexpected response redirect shoppers to another host.
        return is_string($url) && wp_parse_url($url, PHP_URL_SCHEME) === 'https'
            && strtolower((string) wp_parse_url($url, PHP_URL_HOST)) === 'api.tribe-pay.com'
            && strpos((string) wp_parse_url($url, PHP_URL_PATH), '/checkout/') === 0
            && !wp_parse_url($url, PHP_URL_USER) && !wp_parse_url($url, PHP_URL_PASS)
            && (!wp_parse_url($url, PHP_URL_PORT) || wp_parse_url($url, PHP_URL_PORT) === 443);
    }
}
