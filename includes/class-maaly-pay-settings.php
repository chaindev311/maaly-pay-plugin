<?php
if (! defined('ABSPATH')) {
    exit;
}

class Maaly_Pay_Settings
{

    const OPTION_KEY = 'maaly_api_key';

    public static function init()
    {
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('admin_menu', [__CLASS__, 'menu']);
    }

    public static function register()
    {
        register_setting('maaly_pay_settings', self::OPTION_KEY, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        // New options to save payment default parameters
        register_setting('maaly_pay_settings', 'maaly_merchant_id', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting('maaly_pay_settings', 'maaly_fiat_amount', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting('maaly_pay_settings', 'maaly_currency', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'USD',
        ]);
        register_setting('maaly_pay_settings', 'maaly_description', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting('maaly_pay_settings', 'maaly_merchant_callback', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ]);
        register_setting('maaly_pay_settings', 'maaly_open_checkout', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'newtab',
        ]);

        add_settings_section(
            'maaly_pay_settings_section',
            __('Maaly Pay API Settings', 'maaly-pay'),
            function () {
                echo '<p>' . esc_html__('Enter your Maaly Pay API key and default payment parameters.', 'maaly-pay') . '</p>';
            },
            'maaly_pay_settings'
        );

        add_settings_field(
            self::OPTION_KEY,
            __('API Key', 'maaly-pay'),
            [__CLASS__, 'render_api_key_field'],
            'maaly_pay_settings',
            'maaly_pay_settings_section'
        );

        add_settings_field(
            'maaly_merchant_id',
            __('Merchant ID', 'maaly-pay'),
            [__CLASS__, 'render_merchant_id_field'],
            'maaly_pay_settings',
            'maaly_pay_settings_section'
        );

        add_settings_field(
            'maaly_fiat_amount',
            __('Fiat Amount', 'maaly-pay'),
            [__CLASS__, 'render_fiat_amount_field'],
            'maaly_pay_settings',
            'maaly_pay_settings_section'
        );

        add_settings_field(
            'maaly_currency',
            __('Currency', 'maaly-pay'),
            [__CLASS__, 'render_currency_field'],
            'maaly_pay_settings',
            'maaly_pay_settings_section'
        );

        add_settings_field(
            'maaly_description',
            __('Description', 'maaly-pay'),
            [__CLASS__, 'render_description_field'],
            'maaly_pay_settings',
            'maaly_pay_settings_section'
        );

        add_settings_field(
            'maaly_merchant_callback',
            __('Merchant Callback URL', 'maaly-pay'),
            [__CLASS__, 'render_merchant_callback_field'],
            'maaly_pay_settings',
            'maaly_pay_settings_section'
        );

        add_settings_field(
            'maaly_open_checkout',
            __('Open Checkout', 'maaly-pay'),
            [__CLASS__, 'render_open_checkout_field'],
            'maaly_pay_settings',
            'maaly_pay_settings_section'
        );
    }


    public static function render_api_key_field()
    {
        $val = get_option(self::OPTION_KEY, '');
        printf(
            '<input type="password" name="%1$s" value="%2$s" class="regular-text" placeholder="sk_live_xxx..." autocomplete="new-password" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($val)
        );
    }

    public static function render_merchant_id_field()
    {
        $val = get_option('maaly_merchant_id', '');
        printf(
            '<input type="text" name="maaly_merchant_id" value="%s" class="regular-text" />',
            esc_attr($val)
        );
    }

    public static function render_fiat_amount_field()
    {
        $val = get_option('maaly_fiat_amount', '');
        printf(
            '<input type="text" name="maaly_fiat_amount" value="%s" class="regular-text" />',
            esc_attr($val)
        );
    }

    public static function render_currency_field()
    {
        $val = get_option('maaly_currency', 'USD');
        echo '<select name="maaly_currency">';
        foreach (maaly_pay_supported_currencies() as $c) {
            printf(
                '<option value="%1$s" %2$s>%1$s</option>',
                esc_attr($c),
                selected($val, $c, false)
            );
        }
        echo '</select>';
    }

    public static function render_description_field()
    {
        $val = get_option('maaly_description', '');
        printf(
            '<input type="text" name="maaly_description" value="%s" class="regular-text" />',
            esc_attr($val)
        );
    }

    public static function render_merchant_callback_field()
    {
        $val = get_option('maaly_merchant_callback', '');
        printf(
            '<input type="url" name="maaly_merchant_callback" value="%s" class="regular-text" placeholder="https://example.com/callback" />',
            esc_attr($val)
        );
    }

    public static function render_open_checkout_field()
    {
        $val = get_option('maaly_open_checkout', 'newtab');
?>
        <label><input type="radio" name="maaly_open_checkout" value="newtab" <?php checked($val, 'newtab'); ?> /> <?php esc_html_e('Open in new tab', 'maaly-pay'); ?></label><br>
        <label><input type="radio" name="maaly_open_checkout" value="iframe" <?php checked($val, 'iframe'); ?> /> <?php esc_html_e('Embed in iframe', 'maaly-pay'); ?></label>
    <?php
    }

    public static function menu()
    {
        add_menu_page(
            __('Maaly Pay', 'maaly-pay'),
            __('Maaly Pay', 'maaly-pay'),
            'manage_options',
            'maaly-pay-create',
            ['Maaly_Pay_Admin', 'render_create_page'],
            'dashicons-tickets',
            56
        );

        add_submenu_page(
            'maaly-pay-create',
            __('Create Payment', 'maaly-pay'),
            __('Create Payment', 'maaly-pay'),
            'manage_options',
            'maaly-pay-create',
            ['Maaly_Pay_Admin', 'render_create_page']
        );

        add_submenu_page(
            'maaly-pay-create',
            __('Check Status', 'maaly-pay'),
            __('Check Status', 'maaly-pay'),
            'manage_options',
            'maaly-pay-status',
            ['Maaly_Pay_Admin', 'render_status_page']
        );

        add_submenu_page(
            'maaly-pay-create',
            __('Settings', 'maaly-pay'),
            __('Settings', 'maaly-pay'),
            'manage_options',
            'maaly-pay-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function render_settings_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }
    ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Maaly Pay Settings', 'maaly-pay'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('maaly_pay_settings');
                do_settings_sections('maaly_pay_settings');
                submit_button(__('Save Changes', 'maaly-pay'));
                ?>
            </form>
        </div>
<?php
    }
}

Maaly_Pay_Settings::init();
