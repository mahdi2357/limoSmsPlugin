<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LimoSMS_API {

    private $base_url = 'https://api.limosms.com/api';

    private function get_api_key() {
        $api_key = get_option( 'limosms_api_key', '' );
        return is_string( $api_key ) ? trim( $api_key ) : '';
    }

    private function get_headers() {
        return array(
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'Apikey'       => $this->get_api_key(),
        );
    }

    private function request( $method, $endpoint, $payload = array() ) {
        $api_key = $this->get_api_key();

        if ( empty( $api_key ) ) {
            return new WP_Error( 'limosms_no_api_key', 'API Key تنظیم نشده است.' );
        }

        $url = untrailingslashit( $this->base_url ) . '/' . ltrim( $endpoint, '/' );

        $args = array(
            'method'  => strtoupper( $method ),
            'timeout' => 20,
            'headers' => $this->get_headers(),
        );

        if ( ! empty( $payload ) && in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
            $args['body'] = wp_json_encode( $payload );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'LimoSMS API WP Error => ' . $response->get_error_message() );
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );
        $json   = json_decode( $body, true );

        if ( $status < 200 || $status >= 300 ) {
            error_log( 'LimoSMS API Error => URL: ' . $url . ' | Status: ' . $status . ' | Body: ' . $body );

            $message = is_array( $json ) ? ( $json['message'] ?? $json['Message'] ?? $json['error'] ?? 'API Error' ) : 'API Error';

            return new WP_Error(
                'limosms_api_error',
                $message,
                array( 'status' => $status )
            );
        }

        return is_array( $json ) ? $json : array();
    }

    public function send_verification_code( $mobile, $footer = '' ) {
        $mobile = $this->normalize_mobile( $mobile );

        if ( '' === $mobile ) {
            return new WP_Error( 'invalid_mobile', 'شماره موبایل نامعتبر است.' );
        }

        return $this->request(
            'POST',
            'sendcode',
            array(
                'Mobile' => $mobile,
                'Footer' => sanitize_text_field( $footer ),
            )
        );
    }

    public function check_verification_code( $mobile, $code ) {
        $mobile = $this->normalize_mobile( $mobile );
        $code   = sanitize_text_field( $code );

        if ( '' === $mobile || '' === $code ) {
            return new WP_Error( 'invalid_data', 'اطلاعات نامعتبر است.' );
        }

        return $this->request(
            'POST',
            'checkcode',
            array(
                'Mobile' => $mobile,
                'Code'   => $code,
            )
        );
    }

    public function is_verification_successful( $response ) {
        if ( ! is_array( $response ) || empty( $response ) ) {
            return false;
        }

        if ( isset( $response['success'] ) ) {
            return true === $response['success'] || 'true' === $response['success'] || 1 === (int) $response['success'];
        }

        if ( isset( $response['Success'] ) ) {
            return true === $response['Success'] || 'true' === $response['Success'] || 1 === (int) $response['Success'];
        }

        if ( isset( $response['isSuccess'] ) ) {
            return true === $response['isSuccess'] || 'true' === $response['isSuccess'] || 1 === (int) $response['isSuccess'];
        }

        if ( isset( $response['IsSuccess'] ) ) {
            return true === $response['IsSuccess'] || 'true' === $response['IsSuccess'] || 1 === (int) $response['IsSuccess'];
        }

        if ( isset( $response['status'] ) ) {
            return in_array( $response['status'], array( 'success', 'ok', 'Success', 'OK', 1, '1' ), true );
        }

        if ( isset( $response['Status'] ) ) {
            return in_array( $response['Status'], array( 'success', 'ok', 'Success', 'OK', 1, '1' ), true );
        }

        if ( isset( $response['result'] ) ) {
            return true === $response['result'] || 'true' === $response['result'] || 1 === (int) $response['result'];
        }

        if ( isset( $response['Result'] ) ) {
            return true === $response['Result'] || 'true' === $response['Result'] || 1 === (int) $response['Result'];
        }

        if ( isset( $response['code'] ) ) {
            return in_array( $response['code'], array( 200, '200', 1, '1' ), true );
        }

        if ( isset( $response['Code'] ) ) {
            return in_array( $response['Code'], array( 200, '200', 1, '1' ), true );
        }

        return false;
    }

    public function get_response_message( $response, $default = 'عملیات ناموفق بود.' ) {
        if ( ! is_array( $response ) ) {
            return $default;
        }

        $keys = array(
            'message',
            'Message',
            'error',
            'Error',
            'detail',
            'Detail',
            'description',
            'Description',
        );

        foreach ( $keys as $key ) {
            if ( isset( $response[ $key ] ) && '' !== (string) $response[ $key ] ) {
                return (string) $response[ $key ];
            }
        }

        return $default;
    }

    public function normalize_mobile( $mobile ) {
        $mobile = preg_replace( '/[^0-9]/', '', (string) $mobile );

        if ( 0 === strpos( $mobile, '98' ) ) {
            $mobile = '0' . substr( $mobile, 2 );
        }

        if ( 10 === strlen( $mobile ) && '9' === substr( $mobile, 0, 1 ) ) {
            $mobile = '0' . $mobile;
        }

        return preg_match( '/^09\d{9}$/', $mobile ) ? $mobile : '';
    }
}
