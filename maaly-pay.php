<?php
/*
Plugin Name: Maaly Pay
Description: Accept cryptocurrency payments via Maaly Pay API (custom, no WooCommerce required).
Version: 1.0.0
Author: 
Plugin URI: 
Requires at least: 5.2
Tested up to: 6.8
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: maaly-pay
*/

if (! defined('ABSPATH')) {
    exit;
}

define('MAALY_PAY_VERSION', '1.0.0');
define('MAALY_PAY_PLUGIN_FILE', __FILE__);
define('MAALY_PAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAALY_PAY_PLUGIN_URL', plugin_dir_url(__FILE__));

// Note: Since WP 4.6 on WordPress.org, translations load automatically. No manual loader needed.

// Includes
require_once MAALY_PAY_PLUGIN_DIR . 'includes/currencies.php';
require_once MAALY_PAY_PLUGIN_DIR . 'includes/class-maaly-pay-api.php';
require_once MAALY_PAY_PLUGIN_DIR . 'includes/class-maaly-pay-settings.php';
require_once MAALY_PAY_PLUGIN_DIR . 'includes/class-maaly-pay-admin.php';

add_action('admin_enqueue_scripts', function ($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $is_maaly = false;
    if (isset($_GET['page']) && strpos(sanitize_text_field(wp_unslash($_GET['page'])), 'maaly-pay') !== false) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
