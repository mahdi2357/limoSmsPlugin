<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LimoSMS_Sent_SMS_Tab {

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
        $api_key = get_option( 'limosms_api_key', '' );
        $api_key = is_string( $api_key ) ? trim( $api_key ) : '';

        if ( empty( $api_key ) ) {
            return new WP_Error(
                'limosms_missing_api_key',
                __( 'کلید API تنظیم نشده است.', 'limosms' )
            );
        }

        $response = wp_remote_post(
            'https://api.limosms.com/api/getsinglesms',
            array(
                'timeout' => 20,
                'headers' => array(
                    'ApiKey' => $api_key,
                    'Accept' => 'application/json',
                ),
            )
        );


        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );

        if ( $status_code < 200 || $status_code >= 300 ) {
            return new WP_Error(
                'limosms_api_error',
                sprintf(
                /* translators: %s: HTTP status code */
                    __( 'خطا در دریافت اطلاعات از API. کد وضعیت: %s', 'limosms' ),
                    $status_code
                )
            );
        }

        $data = json_decode( $body, true );

        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return new WP_Error(
                'limosms_invalid_json',
                __( 'پاسخ API معتبر نیست.', 'limosms' )
            );
        }

        return $data;
    }
}
