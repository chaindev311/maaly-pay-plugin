<?php
if (!defined('ABSPATH')) exit;

class Maaly_Pay_Frontend
{

  public static function init()
  {
    add_shortcode('maaly_frontend', [__CLASS__, 'render_frontend_page']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);

    add_action('wp_ajax_maaly_create_payment', [__CLASS__, 'handle_create_payment']);
    add_action('wp_ajax_nopriv_maaly_create_payment', [__CLASS__, 'handle_create_payment']);

    add_action('wp_ajax_maaly_check_status', [__CLASS__, 'handle_check_status']);
    add_action('wp_ajax_nopriv_maaly_check_status', [__CLASS__, 'handle_check_status']);
  }

  public static function enqueue_scripts()
  {
    wp_enqueue_script('maaly-pay-frontend', MAALY_PAY_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], MAALY_PAY_VERSION, true);
    wp_localize_script('maaly-pay-frontend', 'maalyPay', [
      'ajaxurl' => admin_url('admin-ajax.php'),
      'nonceCreate' => wp_create_nonce('maaly_create_payment_nonce'),
      'nonceStatus' => wp_create_nonce('maaly_check_status_nonce'),
      'msgPaymentCreated' => __('Payment request created.', 'maaly-pay'),
      'msgCheckoutURL' => __('Checkout URL:', 'maaly-pay'),
      'msgCreatePaymentFirst' => __('Please create a payment first to check its status.', 'maaly-pay'),
    ]);
  }

  public static function render_frontend_page()
  {
    ob_start();
?>
    <div id="maaly-pay-frontend">

      <button id="maaly-create-payment-btn"><?php esc_html_e('Create Payment', 'maaly-pay'); ?></button>
      <div id="maaly-create-payment-result"></div>

      <hr>

      <button id="maaly-check-status-btn"><?php esc_html_e('Check Status', 'maaly-pay'); ?></button>
      <div id="maaly-check-status-result"></div>

    </div>
<?php
    return ob_get_clean();
  }

  public static function handle_create_payment()
  {
    check_ajax_referer('maaly_create_payment_nonce', 'security');

    // Get saved settings
    $merchantId = get_option('maaly_merchant_id', '');
    $fiatAmount = get_option('maaly_fiat_amount', '');
    $currency = get_option('maaly_currency', 'USD');
    $description = get_option('maaly_description', '');
    $merchantCallback = get_option('maaly_merchant_callback', '');
    $openCheckout = get_option('maaly_open_checkout', 'newtab');

    // Generate Merchant Tx ID
    $merchantTxId = 'tx-id-' . time();

    if (empty(get_option(Maaly_Pay_Settings::OPTION_KEY, ''))) {
      wp_send_json_error(__('API key is missing. Please contact admin.', 'maaly-pay'));
    }

    $payload = [
      'merchantId' => (int) $merchantId,
      'fiatAmount' => $fiatAmount,
      'currency' => $currency,
      'description' => $description,
      'merchantTxId' => $merchantTxId,
      'merchantCallback' => $merchantCallback,
    ];

    $api_key = get_option(Maaly_Pay_Settings::OPTION_KEY);

    $res = Maaly_Pay_API::create_payment_request($payload, $api_key);

    if (isset($res['error'])) {
      wp_send_json_error($res['error']);
    } else {
      wp_send_json_success([
        'checkoutUrl' => $res['CheckoutUrl'],
        'merchantTxId' => $merchantTxId,
        'openCheckout' => $openCheckout
      ]);
    }
  }

  public static function handle_check_status()
  {
    check_ajax_referer('maaly_check_status_nonce', 'security');

    $merchantTxId = sanitize_text_field($_POST['merchantTxId'] ?? '');

    if (empty($merchantTxId)) {
      wp_send_json_error(__('Merchant Tx ID is required.', 'maaly-pay'));
    }

    if (empty(get_option(Maaly_Pay_Settings::OPTION_KEY, ''))) {
      wp_send_json_error(__('API key is missing. Please contact admin.', 'maaly-pay'));
    }

    $api_key = get_option(Maaly_Pay_Settings::OPTION_KEY);

    $res = Maaly_Pay_API::check_transaction_status($merchantTxId, $api_key);

    if (isset($res['error'])) {
      wp_send_json_error($res['error']);
    } else {
      wp_send_json_success($res);
    }
  }
}

Maaly_Pay_Frontend::init();
