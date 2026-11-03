<?php
/**
 * Plugin Name: TribePay Gateway for WooCommerce by Salman
 * Description: WooCommerce hosted card checkout integration developed by Salman, with server-side payment verification, signed webhooks and secure return redirects.
 * Version: 1.2.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.3
 * Author: Salman
 * Copyright: 2026 Salman
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * License: GPL-2.0-or-later
 * Text Domain: tribepay-woocommerce
 */
if (!defined('ABSPATH')) { exit; }
define('ZTP_VERSION', '1.2.0');
define('ZTP_FILE', __FILE__);
add_action('before_woocommerce_init', static function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});
add_action('plugins_loaded', static function () {
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', static function () {
            if (current_user_can('activate_plugins')) { echo '<div class="notice notice-error"><p>TribePay requires WooCommerce.</p></div>'; }
        });
        return;
    }
    require_once __DIR__ . '/includes/class-ztp-api.php';
    require_once __DIR__ . '/includes/class-ztp-service.php';
    require_once __DIR__ . '/includes/class-ztp-gateway.php';
    add_filter('woocommerce_payment_gateways', static function ($gateways) { $gateways[] = 'ZTP_Gateway'; return $gateways; });
    add_action('woocommerce_api_tribepay_return', array('ZTP_Service', 'handle_return'));
    add_action('woocommerce_api_tribepay_webhook', array('ZTP_Service', 'handle_webhook'));
    add_action('ztp_verify_payment', array('ZTP_Service', 'poll'), 10, 3);
    add_action('woocommerce_order_action_ztp_verify', static function ($order) {
        $ref = $order->get_meta('_ztp_current');
        if ($ref) { ZTP_Service::synchronize($order->get_id(), $ref); }
    });
    add_filter('woocommerce_order_actions', static function ($actions, $order) {
        if ($order && 'tribepay' === $order->get_payment_method()) { $actions['ztp_verify'] = 'Verify TribePay payment status'; }
        return $actions;
    }, 10, 2);
});
add_action('woocommerce_blocks_loaded', static function () {
    if (class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
        require_once __DIR__ . '/includes/class-ztp-blocks.php';
        add_action('woocommerce_blocks_payment_method_type_registration', static function ($registry) { $registry->register(new ZTP_Blocks()); });
    }
});
register_deactivation_hook(__FILE__, static function () { wp_clear_scheduled_hook('ztp_verify_payment'); });
