<?php
if ( ! defined('ABSPATH') ) { exit; }

class Maaly_Pay_Admin {

    public static function render_create_page() {
        if (!current_user_can('manage_options')) return;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_maaly_admin_nonce'])) {
            if (!wp_verify_nonce($_POST['_maaly_admin_nonce'], 'maaly_admin_create')) {
                echo '<div class="maaly-error">Nonce verification failed.</div>';
            } else {
                $api_key = sanitize_text_field($_POST['apiKey'] ?? '');
                $merchantId = sanitize_text_field($_POST['merchantId'] ?? '');
                $fiatAmount = sanitize_text_field($_POST['fiatAmount'] ?? '');
                $currency = sanitize_text_field($_POST['currency'] ?? '');
                $description = sanitize_text_field($_POST['description'] ?? '');
                $merchantTxId = sanitize_text_field($_POST['merchantTxId'] ?? '');
                $merchantCallback = esc_url_raw($_POST['merchantCallback'] ?? '');
                $embed = isset($_POST['embed']) ? true : false;

                if (empty($api_key)) $api_key = get_option('maaly_api_key', '');

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
                    echo '<p><a href="' . $url . '" target="_blank" class="button">Open Checkout in New Tab</a></p>';
                    if ($embed) {
                        echo '<div class="maaly-iframe-wrap"><iframe src="' . $url . '" style="width:100%;height:600px;border:1px solid #ddd;"></iframe></div>';
                    }
                } else {
                    echo '<div class="maaly-error">Error: ' . esc_html($res['error'] ?? 'Unknown error') . '</div>';
                    if (isset($res['raw'])) {
                        echo '<pre class="maaly-pre">' . esc_html(json_encode($res['raw'], JSON_PRETTY_PRINT)) . '</pre>';
                    }
                }
            }
        }

        $currencies = maaly_pay_supported_currencies();

        ?>
        <div class="wrap maaly-pay-wrap">
            <h1>Create Maaly Payment Request</h1>
            <form method="POST">
                <?php wp_nonce_field('maaly_admin_create', '_maaly_admin_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th>API Key (optional)</th>
                        <td><input type="text" name="apiKey" class="regular-text" value="" /></td>
                    </tr>
                    <tr>
                        <th>Merchant ID</th>
                        <td><input type="text" name="merchantId" class="regular-text" value="" required/></td>
                    </tr>
                    <tr>
                        <th>Fiat Amount</th>
                        <td><input type="text" name="fiatAmount" class="regular-text" value="" required/></td>
                    </tr>
                    <tr>
                        <th>Currency</th>
                        <td>
                            <select name="currency">
                                <?php foreach($currencies as $c): ?>
                                    <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><input type="text" name="description" class="regular-text" value="" /></td>
                    </tr>
                    <tr>
                        <th>Merchant Tx ID</th>
                        <td><input type="text" name="merchantTxId" class="regular-text" value="tx-<?php echo time(); ?>" /></td>
                    </tr>
                    <tr>
                        <th>Merchant Callback</th>
                        <td><input type="url" name="merchantCallback" class="regular-text" value="" /></td>
                    </tr>
                    <tr>
                        <th>Embed in iframe</th>
                        <td><input type="checkbox" name="embed" value="1" /></td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary">Create Payment</button></p>
            </form>
        </div>
        <?php
    }

    public static function render_status_page() {
        if (!current_user_can('manage_options')) return;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_maaly_status_nonce'])) {
            if (!wp_verify_nonce($_POST['_maaly_status_nonce'], 'maaly_admin_status')) {
                echo '<div class="maaly-error">Nonce verification failed.</div>';
            } else {
                $merchant_tx_id = sanitize_text_field($_POST['merchant_tx_id'] ?? '');
                $api_key = sanitize_text_field($_POST['apiKey'] ?? '');
                if (empty($api_key)) $api_key = get_option('maaly_api_key', '');
                $res = Maaly_Pay_API::check_transaction_status($merchant_tx_id, $api_key);
                echo '<pre class="maaly-pre">' . esc_html(json_encode($res, JSON_PRETTY_PRINT)) . '</pre>';
            }
        }
        ?>
        <div class="wrap maaly-pay-wrap">
            <h1>Check Payment Status</h1>
            <form method="POST">
                <?php wp_nonce_field('maaly_admin_status', '_maaly_status_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th>API Key (optional)</th>
                        <td><input type="text" name="apiKey" class="regular-text" value="" /></td>
                    </tr>
                    <tr>
                        <th>Merchant Tx ID</th>
                        <td><input type="text" name="merchant_tx_id" class="regular-text" value="" required/></td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary">Check Status</button></p>
            </form>
        </div>
        <?php
    }
}
