<?php
if ( ! defined('ABSPATH') ) { exit; }

class Maaly_Pay_API {

    const BASE = 'https://maalyportal.com/api/omerch';

    public static function create_payment_request( $payload, $api_key ) {
        $url = self::BASE . '/create-payment-request';

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 60,
            'body'    => wp_json_encode( $payload ),
        );

        $res = wp_remote_post( $url, $args );

        if ( is_wp_error( $res ) ) {
            return array( 'error' => $res->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $res );
        $body = json_decode( wp_remote_retrieve_body( $res ), true );

        if ( $code >= 200 && $code < 300 && isset( $body['CheckoutUrl'] ) ) {
            return array( 'CheckoutUrl' => esc_url_raw( $body['CheckoutUrl'] ) );
        }

        return array( 'error' => 'Invalid response from API', 'raw' => $body, 'status' => $code );
    }

    public static function check_transaction_status( $merchant_tx_id, $api_key ) {
        $url = self::BASE . '/check-online-transaction-merch/' . rawurlencode( $merchant_tx_id );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 60,
        );

        $res = wp_remote_get( $url, $args );

        if ( is_wp_error( $res ) ) {
            return array( 'error' => $res->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $res );
        $body = json_decode( wp_remote_retrieve_body( $res ), true );

        if ( $code >= 200 && $code < 300 && is_array( $body ) ) {
            return $body;
        }

        return array( 'error' => 'Invalid response from API', 'raw' => $body, 'status' => $code );
    }
}
