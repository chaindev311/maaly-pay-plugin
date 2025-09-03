<?php
if ( ! defined('ABSPATH') ) { exit; }

class Maaly_Pay_Settings {

    const OPTION_KEY = 'maaly_api_key';

    public static function init() {
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('admin_menu', [__CLASS__, 'menu']);
    }

    public static function register() {
        register_setting( 'maaly_pay_settings', self::OPTION_KEY, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        add_settings_section(
            'maaly_pay_settings_section',
            'Maaly Pay API Settings',
            function(){ echo '<p>Enter your Maaly Pay API key for Bearer authentication.</p>'; },
            'maaly_pay_settings'
        );

        add_settings_field(
            self::OPTION_KEY,
            'API Key',
            [__CLASS__, 'render_api_key_field'],
            'maaly_pay_settings',
            'maaly_pay_settings_section'
        );
    }

    public static function render_api_key_field() {
        $val = get_option( self::OPTION_KEY, '' );
        printf(
            '<input type="text" name="%1$s" value="%2$s" class="regular-text" placeholder="sk_live_xxx..." />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $val )
        );
    }

    public static function menu() {
        add_menu_page(
            'Maaly Pay',
            'Maaly Pay',
            'manage_options',
            'maaly-pay-create',
            ['Maaly_Pay_Admin', 'render_create_page'],
            'dashicons-tickets',
            56
        );

        add_submenu_page(
            'maaly-pay-create',
            'Create Payment',
            'Create Payment',
            'manage_options',
            'maaly-pay-create',
            ['Maaly_Pay_Admin', 'render_create_page']
        );

        add_submenu_page(
            'maaly-pay-create',
            'Check Status',
            'Check Status',
            'manage_options',
            'maaly-pay-status',
            ['Maaly_Pay_Admin', 'render_status_page']
        );

        add_submenu_page(
            'maaly-pay-create',
            'Settings',
            'Settings',
            'manage_options',
            'maaly-pay-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function render_settings_page() {
        if ( ! current_user_can('manage_options') ) { return; }
        ?>
        <div class="wrap">
            <h1>Maaly Pay Settings</h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'maaly_pay_settings' );
                do_settings_sections( 'maaly_pay_settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

Maaly_Pay_Settings::init();
