<?php
if ( ! defined('ABSPATH') ) { exit; }

class Maaly_Pay_Admin {

    public static function render_create_page() {
        if ( ! current_user_can('manage_options') ) { return; }
        $api_key = get_option( Maaly_Pay_Settings::OPTION_KEY, '' );
        ?>
        <div class="wrap maaly-pay-wrap">
            <h1>Maaly Pay — Create Payment Request</h1>

            <?php if ( empty( $api_key ) ) : ?>
                <div class="notice notice-warning"><p><strong>API Key missing.</strong> Go to <a href="<?php echo admin_url('admin.php?page=maaly-pay-settings'); ?>">Settings</a> and save your API key.</p></div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'maaly_create_payment', '_maaly_nonce' ); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="merchantId">Merchant ID</label></th>
                            <td><input name="merchantId" id="merchantId" type="text" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="fiatAmount">Fiat Amount</label></th>
                            <td><input name="fiatAmount" id="fiatAmount" type="text" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="currency">Currency</label></th>
                            <td>
                                <select name="currency" id="currency" required>
                                    <?php foreach ( maaly_pay_supported_currencies() as $c ) : ?>
                                        <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="description">Description</label></th>
                            <td><input name="description" id="description" type="text" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="merchantTxId">Merchant Tx ID</label></th>
                            <td><input name="merchantTxId" id="merchantTxId" type="text" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="merchantCallback">Merchant Callback URL</label></th>
                            <td><input name="merchantCallback" id="merchantCallback" type="url" class="regular-text code" placeholder="https://example.com/callback" required></td>
                        </tr>
                        <tr>
                            <th scope="row">Open Checkout</th>
                            <td>
                                <label><input type="radio" name="open_mode" value="newtab" checked> Open in new tab</label>
                                <label style="margin-left: 1rem;"><input type="radio" name="open_mode" value="iframe"> Embed in iframe</label>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button( 'Create Payment', 'primary', 'maaly_create_payment' ); ?>
            </form>

            <?php
            if ( isset($_POST['maaly_create_payment']) && check_admin_referer('maaly_create_payment', '_maaly_nonce') ) {
                $payload = array(
                    'merchantId'      => absint( $_POST['merchantId'] ?? 0 ),
                    'fiatAmount'      => sanitize_text_field( $_POST['fiatAmount'] ?? '' ),
                    'currency'        => sanitize_text_field( $_POST['currency'] ?? '' ),
                    'description'     => sanitize_text_field( $_POST['description'] ?? '' ),
                    'merchantTxId'    => sanitize_text_field( $_POST['merchantTxId'] ?? '' ),
                    'merchantCallback'=> esc_url_raw( $_POST['merchantCallback'] ?? '' ),
                );

                if ( empty($api_key) ) {
                    echo '<div class="notice notice-error"><p>API key is required. Save it in Settings first.</p></div>';
                } else {
                    $res = Maaly_Pay_API::create_payment_request( $payload, $api_key );

                    if ( isset($res['error']) ) {
                        echo '<div class="notice notice-error"><p><strong>Error:</strong> ' . esc_html($res['error']) . '</p></div>';
                        if ( isset($res['status']) ) {
                            echo '<p>Status Code: ' . esc_html($res['status']) . '</p>';
                        }
                        if ( isset($res['raw']) ) {
                            echo '<pre class="maaly-pre">'; print_r($res['raw']); echo '</pre>';
                        }
                    } else {
                        $checkout = $res['CheckoutUrl'];
                        echo '<div class="notice notice-success"><p>Payment request created.</p></div>';
                        echo '<div class="notice notice-success"><p>Checkout URL: ' . esc_url($checkout) . '</p></div>';
                        echo '<p><a class="button button-secondary" target="_blank" href="' . esc_url($checkout) . '">Open Checkout in New Tab</a></p>';

                        $open_mode = sanitize_text_field( $_POST['open_mode'] ?? 'iframe' );
                        if ( $open_mode === 'iframe' ) {
                            echo '<div class="maaly-iframe-wrap"><iframe src="' . esc_url($checkout) . '" width="100%" height="650" loading="lazy"></iframe></div>';
                            echo '<p class="description">If the page does not load, your browser or the remote site may block iframes. Use the "Open in New Tab" button above.</p>';
                        }
                    }
                }
            }
            ?>
        </div>
        <?php
    }

    public static function render_status_page() {
        if ( ! current_user_can('manage_options') ) { return; }
        $api_key = get_option( Maaly_Pay_Settings::OPTION_KEY, '' );
        ?>
        <div class="wrap maaly-pay-wrap">
            <h1>Maaly Pay — Check Transaction Status</h1>

            <?php if ( empty( $api_key ) ) : ?>
                <div class="notice notice-warning"><p><strong>API Key missing.</strong> Go to <a href="<?php echo admin_url('admin.php?page=maaly-pay-settings'); ?>">Settings</a> and save your API key.</p></div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'maaly_check_status', '_maaly_nonce' ); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="merchant_tx_id">Merchant Tx ID</label></th>
                            <td><input name="merchant_tx_id" id="merchant_tx_id" type="text" class="regular-text" required></td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button( 'Check Status', 'primary', 'maaly_check_status' ); ?>
            </form>

            <?php
            if ( isset($_POST['maaly_check_status']) && check_admin_referer('maaly_check_status', '_maaly_nonce') ) {
                $merchant_tx_id = sanitize_text_field( $_POST['merchant_tx_id'] ?? '' );

                if ( empty($api_key) ) {
                    echo '<div class="notice notice-error"><p>API key is required. Save it in Settings first.</p></div>';
                } else {
                    $res = Maaly_Pay_API::check_transaction_status( $merchant_tx_id, $api_key );

                    if ( isset($res['error']) ) {
                        echo '<div class="notice notice-error"><p><strong>Error:</strong> ' . esc_html($res['error']) . '</p></div>';
                        if ( isset($res['status']) ) {
                            echo '<p>Status Code: ' . esc_html($res['status']) . '</p>';
                        }
                        if ( isset($res['raw']) ) {
                            echo '<pre class="maaly-pre">'; print_r($res['raw']); echo '</pre>';
                        }
                    } else {
                        $filledAmount = isset($res['filledAmount']) ? $res['filledAmount'] : '—';
                        $requestedAmount = isset($res['requestedAmount']) ? $res['requestedAmount'] : '—';
                        $status = isset($res['status']) ? ( $res['status'] ? '✅ Completed' : '⏳ Pending/Failed' ) : 'Unknown';

                        echo '<h2>Result</h2>';
                        echo '<table class="widefat striped" style="max-width:600px">';
                        echo '<tbody>';
                        echo '<tr><th scope="row">Filled Amount</th><td>' . esc_html($filledAmount) . '</td></tr>';
                        echo '<tr><th scope="row">Requested Amount</th><td>' . esc_html($requestedAmount) . '</td></tr>';
                        echo '<tr><th scope="row">Status</th><td>' . esc_html($status) . '</td></tr>';
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
