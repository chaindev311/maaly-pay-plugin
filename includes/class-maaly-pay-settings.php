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

        add_settings_section(
            'maaly_pay_settings_section',
            __('Maaly Pay API Settings', 'maaly-pay'),
            function () {
                echo '<p>' . esc_html__('Enter your Maaly Pay API key for Bearer authentication.', 'maaly-pay') . '</p>';
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
