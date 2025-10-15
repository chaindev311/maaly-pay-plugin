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

// -----------------------------------------------------------------------------
// ADMIN ASSETS
// -----------------------------------------------------------------------------
add_action('woocommerce_init', function () {
    error_log('>>> WooCommerce initialized, checking Maaly Pay gateway class: ' . (class_exists('WC_Gateway_Maaly_Pay') ? 'YES' : 'NO'));
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
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once MAALY_PAY_PLUGIN_DIR . 'includes/class-maaly-pay-gateway.php';

    add_filter('woocommerce_payment_gateways', 'maaly_pay_add_gateway_class');
}

function maaly_pay_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Gateway_Maaly_Pay';
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

// Diagnostic: log checkout environment & payment gateways
add_action('template_redirect', function () {
    if (! function_exists('is_checkout') || ! is_checkout()) {
        return;
    }

    if (! defined('WP_DEBUG') || ! WP_DEBUG) {
        // Optional: force logging even if WP_DEBUG is off (remove in production)
    }

    // Ensure WooCommerce cart is available
    if (! WC()->cart) {
        error_log('Maaly Debug: WC()->cart is null');
        return;
    }

    $cart_total = WC()->cart->get_total('edit'); // formatted
    $cart_total_raw = WC()->cart->get_total(''); // sometimes formatted; include raw
    $needs_payment = WC()->cart->needs_payment();
    $requires_shipping = WC()->cart->needs_shipping();
    $shipping_packages = WC()->shipping()->get_packages();
    $customer = WC()->customer ? json_encode(array(
        'country' => WC()->customer->get_country(),
        'state' => WC()->customer->get_state(),
        'postcode' => WC()->customer->get_postcode(),
    )) : 'no customer object';

    // Get available payment gateways (the internal list used by checkout)
    $available = array();
    if (class_exists('WC_Payment_Gateways')) {
        $gateways_obj = WC()->payment_gateways();
        $all = $gateways_obj->payment_gateways();
        $avail = $gateways_obj->get_available_payment_gateways();
        foreach ($all as $id => $gw) {
            $available[] = array(
                'id' => $id,
                'class' => is_object($gw) ? get_class($gw) : '',
                'enabled' => property_exists($gw, 'enabled') ? $gw->enabled : null,
                'is_available_result' => method_exists($gw, 'is_available') ? (string) $gw->is_available() : 'n/a',
            );
        }
    }

    error_log(
        'Maaly Debug: checkout env -> cart_total=' . print_r($cart_total, true)
            . ' | needs_payment=' . ($needs_payment ? 'yes' : 'no')
            . ' | requires_shipping=' . ($requires_shipping ? 'yes' : 'no')
            . ' | customer=' . $customer
            . ' | shipping_packages_count=' . count($shipping_packages)
    );

    error_log('Maaly Debug: all gateways -> ' . print_r($available, true));

    // Also log what get_available_payment_gateways() returns
    if (isset($avail)) {
        $avail_ids = array_keys($avail);
        error_log('Maaly Debug: get_available_payment_gateways() => ' . implode(', ', $avail_ids));
    } else {
        error_log('Maaly Debug: get_available_payment_gateways() not available');
    }
}, 5);


// -----------------------------------------------------------------------------
// OPTIONAL DEBUG INFO (enable for testing)
// -----------------------------------------------------------------------------
// add_action('admin_notices', function () {
//     if (!current_user_can('manage_options')) return;
//     $msgs = [];
//     $msgs[] = 'Maaly Pay debug: WooCommerce active? ' . (class_exists('WooCommerce') ? 'YES' : 'NO');
//     $msgs[] = 'WC_Payment_Gateway exists? ' . (class_exists('WC_Payment_Gateway') ? 'YES' : 'NO');
//     $msgs[] = 'Maaly gateway class loaded? ' . (class_exists('WC_Gateway_Maaly_Pay') ? 'YES' : 'NO');
//     echo '<div class="notice notice-info"><p><strong>' . implode('</strong><br><strong>', array_map('esc_html', $msgs)) . '</strong></p></div>';
// });
