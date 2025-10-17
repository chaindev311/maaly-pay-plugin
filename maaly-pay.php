<?php
/*
Plugin Name: Maaly Pay
Description: Accept cryptocurrency payments via Maaly Pay API (WooCommerce gateway integration).
Version: 1.1.1
Author: Your Name
Requires at least: 5.2
Tested up to: 6.8
Requires PHP: 7.4
License: GPLv2 or later
Text Domain: maaly-pay
WC requires at least: 6.0
WC tested up to: 8.0
Requires Plugins: woocommerce
*/

if (!defined('ABSPATH')) {
    exit;
}

// -----------------------------------------------------------------------------
// CONSTANTS
// -----------------------------------------------------------------------------
define('MAALY_PAY_VERSION', '1.1.1');
define('MAALY_PAY_PLUGIN_FILE', __FILE__);
define('MAALY_PAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAALY_PAY_PLUGIN_URL', plugin_dir_url(__FILE__));

// -----------------------------------------------------------------------------
// INCLUDES
// -----------------------------------------------------------------------------
require_once MAALY_PAY_PLUGIN_DIR . 'includes/currencies.php';
require_once MAALY_PAY_PLUGIN_DIR . 'includes/class-maaly-pay-api.php';
require_once MAALY_PAY_PLUGIN_DIR . 'includes/class-maaly-pay-settings.php';
require_once MAALY_PAY_PLUGIN_DIR . 'includes/class-maaly-pay-admin.php';
require_once MAALY_PAY_PLUGIN_DIR . 'includes/class-maaly-pay-frontend.php';

// Initialize frontend
Maaly_Pay_Frontend::init();

// Fix checkout page template issues
add_action('template_redirect', function () {
    if (is_checkout() && !is_admin()) {
        // Ensure WooCommerce checkout template is loaded
        if (!function_exists('woocommerce_checkout')) {
            return;
        }

        // Force load WooCommerce checkout scripts and styles
        if (function_exists('wc_enqueue_js')) {
            wc_enqueue_js('
                jQuery(document).ready(function($) {
                    if (typeof wc_checkout_params !== "undefined") {
                        $("body").trigger("update_checkout");
                    }
                });
            ');
        }
    }
});

// Ensure checkout page has proper content
add_action('wp', function () {
    if (is_checkout() && !is_admin()) {
        // Check if checkout page has content
        global $post;
        if ($post && (empty($post->post_content) || strpos($post->post_content, '[woocommerce_checkout]') === false)) {
            // Add checkout shortcode if page is empty or doesn't have the shortcode
            $post->post_content = '[woocommerce_checkout]';
        }
    }
});

// Force display checkout form if theme doesn't support it
add_action('woocommerce_before_checkout_form', function () {
    if (is_checkout() && !is_admin()) {
        // This ensures the checkout form is displayed even if theme doesn't support it properly
        echo '<div id="woocommerce-checkout-wrapper">';
    }
});

add_action('woocommerce_after_checkout_form', function () {
    if (is_checkout() && !is_admin()) {
        echo '</div>';
    }
});



// Ensure Maaly Pay is not filtered out by other plugins
add_filter('woocommerce_available_payment_gateways', function ($gateways) {
    // Force include Maaly Pay if it exists
    if (!isset($gateways['maaly_pay'])) {
        // Try to get it from the payment gateways object
        if (WC() && WC()->payment_gateways()) {
            $all_gateways = WC()->payment_gateways()->payment_gateways();
            if (isset($all_gateways['maaly_pay'])) {
                $gateways['maaly_pay'] = $all_gateways['maaly_pay'];
            }
        }
    }

    return $gateways;
}, 999);



// -----------------------------------------------------------------------------
// ADMIN ASSETS
// -----------------------------------------------------------------------------
// Declare WooCommerce HPOS compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});


add_action('admin_enqueue_scripts', function ($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $is_maaly = false;

    if (isset($_GET['page']) && strpos(sanitize_text_field(wp_unslash($_GET['page'])), 'maaly-pay') !== false) {
        $is_maaly = true;
    }
    if ($screen && strpos($screen->id, 'maaly-pay') !== false) {
        $is_maaly = true;
    }

    if ($is_maaly) {
        wp_enqueue_style('maaly-pay-admin', MAALY_PAY_PLUGIN_URL . 'assets/css/admin.css', [], MAALY_PAY_VERSION);
        wp_enqueue_script('maaly-pay-admin', MAALY_PAY_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], MAALY_PAY_VERSION, true);
    }
});

// -----------------------------------------------------------------------------
// FRONTEND ASSETS
// -----------------------------------------------------------------------------
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('maaly-pay-frontend-style', MAALY_PAY_PLUGIN_URL . 'assets/css/frontend.css', [], MAALY_PAY_VERSION);
});

// -----------------------------------------------------------------------------
// REGISTER PAYMENT GATEWAY (FIXED)
// -----------------------------------------------------------------------------
// add_action('plugins_loaded', 'maaly_pay_init_gateway', 0);
add_action('woocommerce_loaded', 'maaly_pay_init_gateway', 0);

function maaly_pay_init_gateway()
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once MAALY_PAY_PLUGIN_DIR . 'includes/class-maaly-pay-gateway.php';
    add_filter('woocommerce_payment_gateways', 'maaly_pay_add_gateway_class');

    $initialized = true;
}

function maaly_pay_add_gateway_class($gateways)
{
    // Only add if not already present
    if (!isset($gateways['maaly_pay'])) {
        $gateways['maaly_pay'] = 'WC_Gateway_Maaly_Pay';
    }

    return $gateways;
}

// -----------------------------------------------------------------------------
// CALLBACK ENDPOINT FOR MAALY NOTIFICATIONS
// -----------------------------------------------------------------------------
add_action('woocommerce_api_maaly_pay_callback', function () {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $merchantTxId = isset($data['merchantTxId'])
        ? sanitize_text_field($data['merchantTxId'])
        : (isset($data['merchant_tx_id'])
            ? sanitize_text_field($data['merchant_tx_id'])
            : '');

    $status = isset($data['status']) ? $data['status'] : null;

    if (empty($merchantTxId)) {
        status_header(400);
        echo 'Missing merchantTxId';
        exit;
    }

    $orders = wc_get_orders([
        'limit' => 1,
        'meta_key' => 'maaly_merchant_tx_id',
        'meta_value' => $merchantTxId,
    ]);

    if (empty($orders)) {
        status_header(404);
        echo 'Order not found';
        exit;
    }

    $order = $orders[0];
    $order->add_order_note('Maaly callback received: ' . wp_json_encode($data));

    if (!is_null($status)) {
        $completed = false;
        if (is_string($status)) {
            $s = strtolower($status);
            $completed = in_array($s, ['1', 'true', 'completed', 'success']);
        } elseif (is_bool($status)) {
            $completed = $status;
        } elseif (is_numeric($status)) {
            $completed = intval($status) === 1;
        }

        if ($completed) {
            $order->payment_complete();
            $order->add_order_note('Payment completed via Maaly.');
        } else {
            $order->update_status('failed', 'Payment not completed according to Maaly callback.');
        }
    }

    status_header(200);
    echo 'OK';
    exit;
});
