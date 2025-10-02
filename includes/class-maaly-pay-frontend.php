<?php
if (!defined('ABSPATH')) {
    exit;
}

class Maaly_Pay_Frontend
{

    public static function init()
    {
        // Register shortcodes
        add_shortcode('maaly_create_payment', [__CLASS__, 'render_create_payment_form']);
        add_shortcode('maaly_check_status', [__CLASS__, 'render_check_status_form']);

        // Handle form submissions - both logged-in and guest users
        add_action('admin_post_nopriv_maaly_create_payment', [__CLASS__, 'handle_create_payment']);
        add_action('admin_post_maaly_create_payment', [__CLASS__, 'handle_create_payment']);

        add_action('admin_post_nopriv_maaly_check_status', [__CLASS__, 'handle_check_status']);
        add_action('admin_post_maaly_check_status', [__CLASS__, 'handle_check_status']);
    }

    public static function render_create_payment_form()
    {
        if (isset($_GET['maaly_payment_result'])) {
            $result = sanitize_text_field(wp_unslash($_GET['maaly_payment_result']));
            echo '<div class="maaly-pay-result">' . esc_html($result) . '</div>';
        }

        ob_start();
        $api_key = get_option(Maaly_Pay_Settings::OPTION_KEY, '');
        // You may want to get user-specific keys here if implemented.
?>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
            <?php wp_nonce_field('maaly_create_payment_nonce', 'maaly_nonce'); ?>
            <input type="hidden" name="action" value="maaly_create_payment">

            <p>
                <label for="merchantId"><?php esc_html_e('Merchant ID', 'maaly-pay'); ?></label><br>
                <input type="text" id="merchantId" name="merchantId" required>
            </p>

            <p>
                <label for="fiatAmount"><?php esc_html_e('Fiat Amount', 'maaly-pay'); ?></label><br>
                <input type="text" id="fiatAmount" name="fiatAmount" required>
            </p>

            <p>
                <label for="currency"><?php esc_html_e('Currency', 'maaly-pay'); ?></label><br>
                <select name="currency" id="currency" required>
                    <?php foreach (maaly_pay_supported_currencies() as $c) : ?>
                        <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label for="description"><?php esc_html_e('Description', 'maaly-pay'); ?></label><br>
                <input type="text" id="description" name="description" required>
            </p>

            <p>
                <label for="merchantTxId"><?php esc_html_e('Merchant Tx ID', 'maaly-pay'); ?></label><br>
                <input type="text" id="merchantTxId" name="merchantTxId" required>
            </p>

            <p>
                <label for="merchantCallback"><?php esc_html_e('Merchant Callback URL', 'maaly-pay'); ?></label><br>
                <input type="url" id="merchantCallback" name="merchantCallback" placeholder="https://example.com/callback" required>
            </p>

            <p>
                <?php esc_html_e('Open Checkout', 'maaly-pay'); ?><br>
                <label><input type="radio" name="open_mode" value="newtab" checked> <?php esc_html_e('Open in new tab', 'maaly-pay'); ?></label><br>
                <label><input type="radio" name="open_mode" value="iframe"> <?php esc_html_e('Embed in iframe', 'maaly-pay'); ?></label>
            </p>

            <p><input type="submit" value="<?php esc_attr_e('Create Payment', 'maaly-pay'); ?>"></p>
        </form>
    <?php
        return ob_get_clean();
    }

    public static function handle_create_payment()
    {
        if (!isset($_POST['maaly_nonce']) || !wp_verify_nonce($_POST['maaly_nonce'], 'maaly_create_payment_nonce')) {
            wp_die(__('Nonce verification failed.', 'maaly-pay'));
        }

        // Sanitize input
        $payload = [
            'merchantId'      => absint($_POST['merchantId'] ?? ''),
            'fiatAmount'      => sanitize_text_field($_POST['fiatAmount'] ?? ''),
            'currency'        => sanitize_text_field($_POST['currency'] ?? ''),
            'description'     => sanitize_text_field($_POST['description'] ?? ''),
            'merchantTxId'    => sanitize_text_field($_POST['merchantTxId'] ?? ''),
            'merchantCallback' => esc_url_raw($_POST['merchantCallback'] ?? ''),
        ];

        // Use API key from user meta or fallback admin option
        $api_key = get_option(Maaly_Pay_Settings::OPTION_KEY, '');

        if (empty($api_key)) {
            wp_redirect(add_query_arg('maaly_payment_result', urlencode(__('API key is missing. Please contact site admin.', 'maaly-pay')), wp_get_referer()));
            exit;
        }

        $res = Maaly_Pay_API::create_payment_request($payload, $api_key);

        if (isset($res['error'])) {
            wp_redirect(add_query_arg('maaly_payment_result', urlencode('Error: ' . $res['error']), wp_get_referer()));
            exit;
        } else {
            // Optionally show checkout in iframe or redirect to checkout url
            $checkout = $res['CheckoutUrl'];
            // Store checkout url in transient or redirect with it
            wp_redirect(add_query_arg('maaly_payment_result', urlencode('Payment request created. Checkout URL: ' . $checkout), wp_get_referer()));
            exit;
        }
    }

    public static function render_check_status_form()
    {
        if (isset($_GET['maaly_status_result'])) {
            $result = sanitize_text_field(wp_unslash($_GET['maaly_status_result']));
            echo '<div class="maaly-pay-result">' . esc_html($result) . '</div>';
        }

        ob_start();
    ?>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
            <?php wp_nonce_field('maaly_check_status_nonce', 'maaly_nonce'); ?>
            <input type="hidden" name="action" value="maaly_check_status">

            <p>
                <label for="merchant_tx_id"><?php esc_html_e('Merchant Tx ID', 'maaly-pay'); ?></label><br>
                <input type="text" id="merchant_tx_id" name="merchant_tx_id" required>
            </p>

            <p><input type="submit" value="<?php esc_attr_e('Check Status', 'maaly-pay'); ?>"></p>
        </form>
<?php
        return ob_get_clean();
    }

    public static function handle_check_status()
    {
        if (!isset($_POST['maaly_nonce']) || !wp_verify_nonce($_POST['maaly_nonce'], 'maaly_check_status_nonce')) {
            wp_die(__('Nonce verification failed.', 'maaly-pay'));
        }
        $merchant_tx_id = sanitize_text_field($_POST['merchant_tx_id'] ?? '');

        $api_key = get_option(Maaly_Pay_Settings::OPTION_KEY, '');

        if (empty($api_key)) {
            wp_redirect(add_query_arg('maaly_status_result', urlencode(__('API key is missing. Please contact site admin.', 'maaly-pay')), wp_get_referer()));
            exit;
        }

        $res = Maaly_Pay_API::check_transaction_status($merchant_tx_id, $api_key);

        if (isset($res['error'])) {
            wp_redirect(add_query_arg('maaly_status_result', urlencode('Error: ' . $res['error']), wp_get_referer()));
            exit;
        } else {
            $filledAmount = isset($res['filledAmount']) ? $res['filledAmount'] : '—';
            $requestedAmount = isset($res['requestedAmount']) ? $res['requestedAmount'] : '—';
            $status_text = isset($res['status']) && $res['status'] ?
                __('✅ Completed', 'maaly-pay') : __('⏳ Pending/Failed', 'maaly-pay');

            $result_message = sprintf(
                __('Status: %s | Filled Amount: %s | Requested Amount: %s', 'maaly-pay'),
                $status_text,
                $filledAmount,
                $requestedAmount
            );
            wp_redirect(add_query_arg('maaly_status_result', urlencode($result_message), wp_get_referer()));
            exit;
        }
    }
}

Maaly_Pay_Frontend::init();
