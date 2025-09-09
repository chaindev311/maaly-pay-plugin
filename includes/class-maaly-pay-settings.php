<?php
if ( ! defined('ABSPATH') ) { exit; }

class Maaly_Pay_Settings {

    const OPTION_KEY = 'maaly_api_key';            // site default (fallback)
    const REST_PUBLIC = 'maaly_rest_public';
    const REST_TOKEN  = 'maaly_rest_token';
    const REST_CORS   = 'maaly_cors_origin';       // e.g. * or https://example.com

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

        register_setting( 'maaly_pay_settings', self::REST_PUBLIC, [
            'type' => 'boolean',
            'sanitize_callback' => [__CLASS__, 'sanitize_boolean'],
            'default' => false,
        ]);

        register_setting( 'maaly_pay_settings', self::REST_TOKEN, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        register_setting( 'maaly_pay_settings', self::REST_CORS, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '*',
        ]);

        add_settings_section(
            'maaly_pay_settings_section',
            'Maaly Pay API Settings',
            function(){
                echo '<p>Enter your Maaly Pay API key for site-default fallback. Configure public access if you want front-end users or external apps to call your site. Individual visitors may instead provide their own API keys at the form.</p>';
            },
            'maaly_pay_settings'
        );

        add_settings_field(
            self::OPTION_KEY,
            'Site Default API Key (optional)',
            [__CLASS__, 'render_api_key_field'],
            'maaly_pay_settings',
            'maaly_pay_settings_section'
        );

        add_settings_field(
            self::REST_PUBLIC,
            'Enable Public REST API',
            [__CLASS__, 'render_public_rest_field'],
            'maaly_pay_settings',
            'maaly_pay_settings_section'
        );

        add_settings_field(
            self::REST_TOKEN,
            'Public REST Token (optional)',
            [__CLASS__, 'render_rest_token_field'],
            'maaly_pay_settings',
            'maaly_pay_settings_section'
        );

        add_settings_field(
            self::REST_CORS,
            'Allowed CORS Origin',
            [__CLASS__, 'render_cors_field'],
            'maaly_pay_settings',
            'maaly_pay_settings_section'
        );
    }

    public static function sanitize_boolean( $value ) {
        // Robust boolean sanitizer compatible with older WP versions
        $true_values = array( '1', 1, 'true', true, 'on', 'yes', 'y' );
        return in_array( $value, $true_values, true );
    }

    public static function render_api_key_field() {
        $val = get_option( self::OPTION_KEY, '' );
        printf(
            '<input type="text" name="%1$s" value="%2$s" class="regular-text" placeholder="sk_live_xxx..." /> <p class=\"description\">Used when a user does not provide their own API key.</p>',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $val )
        );
    }

    public static function render_public_rest_field() {
        $val = (bool) get_option( self::REST_PUBLIC, false );
        printf(
            '<label><input type="checkbox" name="%1$s" value="1" %2$s /> Allow public (unauthenticated) REST endpoints</label><p class=\"description\">WARNING: Anyone who knows your site URL can hit these endpoints. Consider requiring the token below.</p>',
            esc_attr( self::REST_PUBLIC ),
            checked( $val, true, false )
        );
    }

    public static function render_rest_token_field() {
        $val = get_option( self::REST_TOKEN, '' );
        if ( empty($val) ) {
            $val = wp_generate_password( 32, false, false );
            update_option( self::REST_TOKEN, $val );
        }
        printf(
            '<input type="text" name="%1$s" value="%2$s" class="regular-text code" /> <button type="button" class="button" onclick="(function(btn){var input=btn.previousElementSibling; if(input){ input.value=Math.random().toString(36).slice(2)+Math.random().toString(36).slice(2);} })(this)">Regenerate</button><p class=\"description\">If set, clients must send header <code>X-Maaly-Token: &lt;this value&gt;</code>.</p>',
            esc_attr( self::REST_TOKEN ),
            esc_attr( $val )
        );
    }

    public static function render_cors_field() {
        $val = get_option( self::REST_CORS, '*' );
        printf(
            '<input type="text" name="%1$s" value="%2$s" class="regular-text" placeholder="https://example.com or *" /><p class="description">Controls <code>Access-Control-Allow-Origin</code> for the public REST responses.</p>',
            esc_attr( self::REST_CORS ),
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
            <hr />
            <h2>Public REST Endpoints</h2>
            <p>Once enabled, the following endpoints will be available:</p>
            <ul>
                <li><code>POST /wp-json/maaly/v1/create-payment</code></li>
                <li><code>GET  /wp-json/maaly/v1/status/&lt;merchant_tx_id&gt;</code></li>
            </ul>
            <p>Optional header: <code>X-Maaly-Token</code> must match the token above (if set).</p>

            <h3>User API Keys</h3>
            <p>Front-end forms accept a user-provided <strong>API Key</strong> and <strong>Merchant ID</strong>. Users may choose to save these to their account when logged in; saving is optional.</p>
        </div>
        <?php
    }
}

Maaly_Pay_Settings::init();
