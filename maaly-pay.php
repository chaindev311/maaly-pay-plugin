<?php
/*
Plugin Name: Maaly Pay Integration
Description: Accept cryptocurrency payments via Maaly Pay API (custom, no WooCommerce required).
Version: 1.0.0
Author: Chain Dev
Requires at least: 5.2
Tested up to: 6.6
*/

if ( ! defined('ABSPATH') ) { exit; }

define('MAALY_PAY_VERSION', '1.0.0');
define('MAALY_PAY_PLUGIN_FILE', __FILE__);
define('MAALY_PAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAALY_PAY_PLUGIN_URL', plugin_dir_url(__FILE__));

// Includes
require_once MAALY_PAY_PLUGIN_DIR . 'includes/currencies.php';
require_once MAALY_PAY_PLUGIN_DIR . 'includes/class-maaly-pay-api.php';
require_once MAALY_PAY_PLUGIN_DIR . 'includes/class-maaly-pay-settings.php';
require_once MAALY_PAY_PLUGIN_DIR . 'includes/class-maaly-pay-admin.php';

add_action('admin_enqueue_scripts', function($hook){
    if ( strpos($hook, 'maaly-pay') !== false ) {
        wp_enqueue_style('maaly-pay-admin', MAALY_PAY_PLUGIN_URL . 'assets/css/admin.css', [], MAALY_PAY_VERSION);
        wp_enqueue_script('maaly-pay-admin', MAALY_PAY_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], MAALY_PAY_VERSION, true);
    }
});
