<?php
if ( ! defined('ABSPATH') ) { exit; }

class Maaly_Pay_Public {

    public static function init() {
        // Shortcodes
        add_shortcode('maaly_pay_create', [__CLASS__, 'shortcode_create_payment']);
        add_shortcode('maaly_pay_status', [__CLASS__, 'shortcode_check_status']);

        // REST API
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    /* --------------------------
     * SHORTCODES
     * -------------------------- */

    public static function shortcode_create_payment($atts = []) {
        $atts = shortcode_atts(['autoredirect' => 'false'], $atts);
        $autoredirect = filter_var($atts['autoredirect'], FILTER_VALIDATE_BOOLEAN);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_maaly_nonce'])) {
            if (!wp_verify_nonce($_POST['_maaly_nonce'], 'maaly_front_create')) {
                echo '<div class="maaly-error">Nonce verification failed.</div>';
            } else {
                $api_key = sanitize_text_field($_POST['apiKey'] ?? '');
                $merchantId = sanitize_text_field($_POST['merchantId'] ?? '');
                $fiatAmount = sanitize_text_field($_POST['fiatAmount'] ?? '');
                $currency = sanitize_text_field($_POST['currency'] ?? '');
                $description = sanitize_text_field($_POST['description'] ?? '');
                $merchantTxId = sanitize_text_field($_POST['merchantTxId'] ?? '');
                $merchantCallback = esc_url_raw($_POST['merchantCallback'] ?? '');

                // fallback to site default API key
                if (empty($api_key)) {
                    $api_key = get_option('maaly_api_key', '');
                }

                $payload = [
                    'merchantId' => $merchantId,
                    'fiatAmount' => $fiatAmount,
                    'currency' => $currency,
                    'description' => $description,
                    'merchantTxId' => $merchantTxId,
                    'merchantCallback' => $merchantCallback,
                ];

                $res = Maaly_Pay_API::create_payment_request($payload, $api_key);

                if (isset($res['CheckoutUrl'])) {
                    echo '<div class="maaly-success">Payment request created.</div>';
                    $url = esc_url($res['CheckoutUrl']);
                    if ($autoredirect) {
                        echo "<script>window.open('$url', '_blank');</script>";
                    } else {
                        echo '<p><a href="' . $url . '" target="_blank" class="button">Open Checkout</a></p>';
                        echo '<div class="maaly-iframe-wrap"><iframe src="' . $url . '" style="width:100%;height:600px;border:1px solid #ddd;"></iframe></div>';
                    }
                } else {
                    echo '<div class="maaly-error">Error: ' . esc_html($res['error'] ?? 'Unknown error') . '</div>';
                    if (isset($res['raw'])) {
                        echo '<pre class="maaly-pre">' . esc_html(json_encode($res['raw'], JSON_PRETTY_PRINT)) . '</pre>';
                    }
                }

                // Optionally save API key & merchantId for logged-in user
                if (is_user_logged_in() && !empty($_POST['save_user'])) {
                    update_user_meta(get_current_user_id(), 'maaly_user_api_key', $api_key);
                    update_user_meta(get_current_user_id(), 'maaly_user_merchant_id', $merchantId);
                }
            }
        }

        // Load saved user meta if logged in
        $saved_api_key = '';
        $saved_merchant_id = '';
        if (is_user_logged_in()) {
            $saved_api_key = get_user_meta(get_current_user_id(), 'maaly_user_api_key', true);
            $saved_merchant_id = get_user_meta(get_current_user_id(), 'maaly_user_merchant_id', true);
        }

        $currencies = maaly_pay_supported_currencies();

        ob_start();
        ?>
        <div class="maaly-pay-wrap">
            <form method="POST" class="maaly-form">
                <?php wp_nonce_field('maaly_front_create', '_maaly_nonce'); ?>
                <label>API Key (optional)<input type="text" name="apiKey" value="<?php echo esc_attr($saved_api_key); ?>" /></label>
                <label>Merchant ID<input type="text" name="merchantId" value="<?php echo esc_attr($saved_merchant_id); ?>" required/></label>
                <label>Fiat Amount<input type="text" name="fiatAmount" value="" required/></label>
                <label>Currency
                    <select name="currency" required>
                        <?php foreach($currencies as $c): ?>
                            <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Description<input type="text" name="description" value="" /></label>
                <label>Merchant Tx ID<input type="text" name="merchantTxId" value="<?php echo 'tx-' . time(); ?>" required/></label>
                <label>Merchant Callback<input type="url" name="merchantCallback" value="" /></label>
                <?php if (is_user_logged_in()): ?>
                    <label><input type="checkbox" name="save_user" value="1" /> Save API Key & Merchant ID to my account</label>
                <?php endif; ?>
                <button type="submit" class="button">Create Payment</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function shortcode_check_status() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_maaly_status_nonce'])) {
            if (!wp_verify_nonce($_POST['_maaly_status_nonce'], 'maaly_front_status')) {
                echo '<div class="maaly-error">Nonce verification failed.</div>';
            } else {
                $merchant_tx_id = sanitize_text_field($_POST['merchant_tx_id']);
                $api_key = sanitize_text_field($_POST['apiKey'] ?? '');
                if (empty($api_key)) $api_key = get_option('maaly_api_key', '');
                $res = Maaly_Pay_API::check_transaction_status($merchant_tx_id, $api_key);
                echo '<pre class="maaly-pre">' . esc_html(json_encode($res, JSON_PRETTY_PRINT)) . '</pre>';
            }
        }

        ob_start();
        ?>
        <div class="maaly-pay-wrap">
            <form method="POST" class="maaly-form">
                <?php wp_nonce_field('maaly_front_status', '_maaly_status_nonce'); ?>
                <label>API Key (optional)<input type="text" name="apiKey" value="" /></label>
                <label>Merchant Tx ID<input type="text" name="merchant_tx_id" value="" required/></label>
                <button type="submit" class="button">Check Status</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /* --------------------------
     * REST API
     * -------------------------- */

    public static function register_rest_routes() {
        register_rest_route('maaly/v1', '/create-payment', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_create_payment'],
            'permission_callback' => [__CLASS__, 'rest_permission_callback'],
        ]);

        register_rest_route('maaly/v1', '/status/(?P<merchant_tx_id>[\w\-]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_check_status'],
            'permission_callback' => [__CLASS__, 'rest_permission_callback'],
        ]);
    }

    public static function rest_permission_callback($request) {
        $enabled = get_option('maaly_rest_public', false);
        if ($enabled) {
            $token = $request->get_header('X-Maaly-Token');
            $saved_token = get_option('maaly_rest_token', '');
            if (empty($saved_token) || $token === $saved_token) return true;
        }
        return current_user_can('manage_options');
    }

    public static function rest_create_payment($request) {
        $body = $request->get_json_params();
        $api_key = $body['apiKey'] ?? $request->get_header('X-Maaly-Api-Key') ?? get_option('maaly_api_key', '');
        $payload = [
            'merchantId' => $body['merchantId'] ?? '',
            'fiatAmount' => $body['fiatAmount'] ?? '',
            'currency' => $body['currency'] ?? '',
            'description' => $body['description'] ?? '',
            'merchantTxId' => $body['merchantTxId'] ?? '',
            'merchantCallback' => $body['merchantCallback'] ?? '',
        ];
        return Maaly_Pay_API::create_payment_request($payload, $api_key);
    }

    public static function rest_check_status($request) {
        $merchant_tx_id = sanitize_text_field($request['merchant_tx_id']);
        $api_key = $request->get_header('X-Maaly-Api-Key') ?? get_option('maaly_api_key', '');
        return Maaly_Pay_API::check_transaction_status($merchant_tx_id, $api_key);
    }
}

Maaly_Pay_Public::init();
