<?php
if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('WC_Payment_Gateway')) {
    return;
}

class WC_Gateway_Maaly_Pay extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'maaly_pay';
        $this->icon = MAALY_PAY_PLUGIN_URL . 'assets/images/maaly-icon.png';
        $this->has_fields = false;
        $this->method_title = __('Maaly Pay', 'maaly-pay');
        $this->method_description = __('Accept cryptocurrency payments via Maaly Pay.', 'maaly-pay');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', __('Maaly Pay', 'maaly-pay'));
        $this->description = $this->get_option('description', __('Pay with Maaly Pay', 'maaly-pay'));
        $this->enabled = $this->get_option('enabled', 'yes');
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function is_available()
    {
        if ($this->enabled !== 'yes') {
            return false;
        }

        // Check if WooCommerce is available
        if (!WC() || !WC()->cart) {
            return false;
        }

        // Check if cart needs payment
        if (!WC()->cart->needs_payment()) {
            return false;
        }

        return true;
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'maaly-pay'),
                'type' => 'checkbox',
                'label' => __('Enable Maaly Pay', 'maaly-pay'),
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('Title', 'maaly-pay'),
                'type' => 'text',
                'default' => __('Maaly Pay', 'maaly-pay'),
                'description' => __('This controls the title shown during checkout.', 'maaly-pay'),
            ],
            'description' => [
                'title' => __('Description', 'maaly-pay'),
                'type' => 'textarea',
                'default' => __('Pay with cryptocurrency via Maaly Pay.', 'maaly-pay'),
            ],
        ];
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $merchantId = get_option('maaly_merchant_id', '');
        $api_key = get_option(Maaly_Pay_Settings::OPTION_KEY, '');
        $currency = $order->get_currency();
        $amount = $order->get_total();
        $merchantTxId = 'order-' . $order_id . '-' . time();
        $callback_url = add_query_arg('wc-api', 'maaly_pay_callback', home_url('/'));

        if (empty($api_key) || empty($merchantId)) {
            wc_add_notice(__('Payment error: API key or Merchant ID not configured.', 'maaly-pay'), 'error');
            return ['result' => 'failure'];
        }

        $payload = [
            'merchantId' => (int) $merchantId,
            'fiatAmount' => $amount,
            'currency' => $currency,
            'description' => 'Order #' . $order_id,
            'merchantTxId' => $merchantTxId,
            'merchantCallback' => $callback_url,
        ];

        $res = Maaly_Pay_API::create_payment_request($payload, $api_key);

        if (isset($res['error'])) {
            wc_add_notice(__('Payment gateway error: ', 'maaly-pay') . $res['error'], 'error');
            return ['result' => 'failure'];
        }

        $order->update_meta_data('maaly_merchant_tx_id', $merchantTxId);
        $order->save();
        $order->update_status('on-hold', __('Awaiting Maaly payment', 'maaly-pay'));

        $checkout_url = isset($res['CheckoutUrl']) ? esc_url_raw($res['CheckoutUrl']) : '';
        if (! $checkout_url) {
            wc_add_notice(__('Payment gateway returned invalid checkout URL.', 'maaly-pay'), 'error');
            return ['result' => 'failure'];
        }

        $order->add_order_note('Customer redirected to Maaly checkout: ' . $checkout_url);

        return [
            'result' => 'success',
            'redirect' => $checkout_url,
        ];
    }
}
