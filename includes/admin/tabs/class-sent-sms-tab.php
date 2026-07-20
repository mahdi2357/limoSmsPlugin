<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LimoSMS_Sent_SMS_Tab {

    /**
     * @var LimoSMS_API
     */
    private $api;

    public function __construct() {
        $this->api = new LimoSMS_API();
    }

    public function register_hooks() {
        add_action( 'wp_ajax_limosms_get_sent_sms', array( $this, 'ajax_get_sent_sms' ) );
    }

    public function ajax_get_sent_sms() {
        check_ajax_referer( 'limosms_sent_sms_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array( 'message' => __( 'شما دسترسی لازم را ندارید.', 'limosms' ) ),
                403
            );
        }

        $response = $this->request_sent_sms();

        if ( is_wp_error( $response ) ) {
            wp_send_json_error(
                array( 'message' => $response->get_error_message() ),
                500
            );
        }

        wp_send_json_success( $response );
    }

    private function request_sent_sms() {
        $response = $this->api->get_sent_sms();

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! is_array( $response ) ) {
            return new WP_Error(
                'limosms_invalid_json',
                __( 'پاسخ API معتبر نیست.', 'limosms' )
            );
        }

        return $response;
    }
}
