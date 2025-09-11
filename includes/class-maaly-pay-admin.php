<?php
if (! defined('ABSPATH')) {
    exit;
}

class Maaly_Pay_Admin
{

    public static function render_create_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $api_key = get_option(Maaly_Pay_Settings::OPTION_KEY, '');
?>
        <div class="wrap maaly-pay-wrap">
            <h1><?php echo esc_html__('Maaly Pay — Create Payment Request', 'maaly-pay'); ?></h1>

            <?php if (empty($api_key)) : ?>
                <div class="notice notice-warning">
                    <p><strong><?php echo esc_html__('API Key missing.', 'maaly-pay'); ?></strong>
                        <?php
                        /* translators: %s: link to the Settings page. */
                        $maaly_settings_link_text = __('Go to %s and save your API key.', 'maaly-pay');
                        echo wp_kses_post(
                            sprintf(
                                $maaly_settings_link_text,
                                '<a href="' . esc_url(admin_url('admin.php?page=maaly-pay-settings')) . '">' . esc_html__('Settings', 'maaly-pay') . '</a>'
                            )
                        );
                        ?></p>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('maaly_create_payment', '_maaly_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="merchantId"><?php echo esc_html__('Merchant ID', 'maaly-pay'); ?></label></th>
                            <td><input name="merchantId" id="merchantId" type="text" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="fiatAmount"><?php echo esc_html__('Fiat Amount', 'maaly-pay'); ?></label></th>
                            <td><input name="fiatAmount" id="fiatAmount" type="text" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="currency"><?php echo esc_html__('Currency', 'maaly-pay'); ?></label></th>
                            <td>
                                <select name="currency" id="currency" required>
                                    <?php foreach (maaly_pay_supported_currencies() as $c) : ?>
                                        <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="description"><?php echo esc_html__('Description', 'maaly-pay'); ?></label></th>
                            <td><input name="description" id="description" type="text" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="merchantTxId"><?php echo esc_html__('Merchant Tx ID', 'maaly-pay'); ?></label></th>
                            <td><input name="merchantTxId" id="merchantTxId" type="text" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="merchantCallback"><?php echo esc_html__('Merchant Callback URL', 'maaly-pay'); ?></label></th>
                            <td><input name="merchantCallback" id="merchantCallback" type="url" class="regular-text code" placeholder="https://example.com/callback" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Open Checkout', 'maaly-pay'); ?></th>
                            <td>
                                <label><input type="radio" name="open_mode" value="newtab" checked> <?php echo esc_html__('Open in new tab', 'maaly-pay'); ?></label>
                                <label style="margin-left: 1rem;"><input type="radio" name="open_mode" value="iframe"> <?php echo esc_html__('Embed in iframe', 'maaly-pay'); ?></label>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Create Payment', 'maaly-pay'), 'primary', 'maaly_create_payment'); ?>
            </form>

            <?php
            if (isset($_POST['maaly_create_payment']) && check_admin_referer('maaly_create_payment', '_maaly_nonce')) {
                $payload = array(
                    'merchantId'      => absint(isset($_POST['merchantId']) ? wp_unslash($_POST['merchantId']) : 0),
                    'fiatAmount'      => sanitize_text_field(isset($_POST['fiatAmount']) ? wp_unslash($_POST['fiatAmount']) : ''),
                    'currency'        => sanitize_text_field(isset($_POST['currency']) ? wp_unslash($_POST['currency']) : ''),
                    'description'     => sanitize_text_field(isset($_POST['description']) ? wp_unslash($_POST['description']) : ''),
                    'merchantTxId'    => sanitize_text_field(isset($_POST['merchantTxId']) ? wp_unslash($_POST['merchantTxId']) : ''),
                    'merchantCallback' => esc_url_raw(isset($_POST['merchantCallback']) ? wp_unslash($_POST['merchantCallback']) : ''),
                );

                if (empty($api_key)) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('API key is required. Save it in Settings first.', 'maaly-pay') . '</p></div>';
                } else {
                    $res = Maaly_Pay_API::create_payment_request($payload, $api_key);

                    if (isset($res['error'])) {
                        echo '<div class="notice notice-error"><p><strong>' . esc_html__('Error:', 'maaly-pay') . '</strong> ' . esc_html($res['error']) . '</p></div>';
                        if (isset($res['status'])) {
                            echo '<p>' . esc_html__('Status Code:', 'maaly-pay') . ' ' . esc_html($res['status']) . '</p>';
                        }
                        if (isset($res['raw'])) {
                            echo '<pre class="maaly-pre">' . esc_html(wp_json_encode($res['raw'])) . '</pre>';
                        }
                    } else {
                        $checkout = $res['CheckoutUrl'];
                        echo '<div class="notice notice-success"><p>' . esc_html__('Payment request created.', 'maaly-pay') . '</p></div>';
                        echo '<div class="notice notice-success"><p>' . esc_html__('Checkout URL:', 'maaly-pay') . ' ' . esc_url($checkout) . '</p></div>';
                        echo '<p><a class="button button-secondary" target="_blank" href="' . esc_url($checkout) . '">' . esc_html__('Open Checkout in New Tab', 'maaly-pay') . '</a></p>';

                        $open_mode = sanitize_text_field(isset($_POST['open_mode']) ? wp_unslash($_POST['open_mode']) : 'iframe');
                        if ($open_mode === 'iframe') {
                            echo '<div class="maaly-iframe-wrap"><iframe src="' . esc_url($checkout) . '" width="100%" height="650" loading="lazy"></iframe></div>';
                            echo '<p class="description">' . esc_html__('If the page does not load, your browser or the remote site may block iframes. Use the "Open in New Tab" button above.', 'maaly-pay') . '</p>';
                        }
                    }
                }
            }
            ?>
        </div>
    <?php
    }

    public static function render_status_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $api_key = get_option(Maaly_Pay_Settings::OPTION_KEY, '');
    ?>
        <div class="wrap maaly-pay-wrap">
            <h1><?php echo esc_html__('Maaly Pay — Check Transaction Status', 'maaly-pay'); ?></h1>

            <?php if (empty($api_key)) : ?>
                <div class="notice notice-warning">
                    <p><strong><?php echo esc_html__('API Key missing.', 'maaly-pay'); ?></strong>
                        <?php
                        /* translators: %s: link to the Settings page. */
                        $maaly_settings_link_text2 = __('Go to %s and save your API key.', 'maaly-pay');
                        echo wp_kses_post(
                            sprintf(
                                $maaly_settings_link_text2,
                                '<a href="' . esc_url(admin_url('admin.php?page=maaly-pay-settings')) . '">' . esc_html__('Settings', 'maaly-pay') . '</a>'
                            )
                        );
                        ?></p>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('maaly_check_status', '_maaly_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="merchant_tx_id"><?php echo esc_html__('Merchant Tx ID', 'maaly-pay'); ?></label></th>
                            <td><input name="merchant_tx_id" id="merchant_tx_id" type="text" class="regular-text" required></td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Check Status', 'maaly-pay'), 'primary', 'maaly_check_status'); ?>
            </form>

            <?php
            if (isset($_POST['maaly_check_status']) && check_admin_referer('maaly_check_status', '_maaly_nonce')) {
                $merchant_tx_id = sanitize_text_field(isset($_POST['merchant_tx_id']) ? wp_unslash($_POST['merchant_tx_id']) : '');

                if (empty($api_key)) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('API key is required. Save it in Settings first.', 'maaly-pay') . '</p></div>';
                } else {
                    $res = Maaly_Pay_API::check_transaction_status($merchant_tx_id, $api_key);

                    if (isset($res['error'])) {
                        echo '<div class="notice notice-error"><p><strong>' . esc_html__('Error:', 'maaly-pay') . '</strong> ' . esc_html($res['error']) . '</p></div>';
                        if (isset($res['status'])) {
                            echo '<p>' . esc_html__('Status Code:', 'maaly-pay') . ' ' . esc_html($res['status']) . '</p>';
                        }
                        if (isset($res['raw'])) {
                            echo '<pre class="maaly-pre">' . esc_html(wp_json_encode($res['raw'])) . '</pre>';
                        }
                    } else {
                        $filledAmount = isset($res['filledAmount']) ? $res['filledAmount'] : '—';
                        $requestedAmount = isset($res['requestedAmount']) ? $res['requestedAmount'] : '—';
                        $status = isset($res['status']) ? ($res['status'] ? esc_html__('✅ Completed', 'maaly-pay') : esc_html__('⏳ Pending/Failed', 'maaly-pay')) : esc_html__('Unknown', 'maaly-pay');

                        echo '<h2>' . esc_html__('Result', 'maaly-pay') . '</h2>';
                        echo '<table class="widefat striped" style="max-width:600px">';
                        echo '<tbody>';
                        echo '<tr><th scope="row">' . esc_html__('Filled Amount', 'maaly-pay') . '</th><td>' . esc_html($filledAmount) . '</td></tr>';
                        echo '<tr><th scope="row">' . esc_html__('Requested Amount', 'maaly-pay') . '</th><td>' . esc_html($requestedAmount) . '</td></tr>';
                        echo '<tr><th scope="row">' . esc_html__('Status', 'maaly-pay') . '</th><td>' . esc_html($status) . '</td></tr>';
                        echo '</tbody>';
                        echo '</table>';
                    }
                }
            }
            ?>
        </div>
<?php
    }
}
