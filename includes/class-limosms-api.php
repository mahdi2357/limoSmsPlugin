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

        $timeout = apply_filters( 'limosms_api_request_timeout', 45, $method, $endpoint );
        $args = array(
            'method'      => strtoupper( $method ),
            'timeout'     => $timeout,
            'redirection' => 5,
            'headers'     => $this->get_headers(),
            'httpversion' => '1.1',
        );

        if ( ! empty( $payload ) && in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
            $args['body'] = wp_json_encode( $payload );
        }

        $args = apply_filters( 'limosms_api_request_args', $args, $method, $endpoint, $payload );
        $retries = max( 0, (int) apply_filters( 'limosms_api_request_retries', 1, $method, $endpoint ) );
        $attempts = 0;
        $response = null;

        do {
            $response = wp_remote_request( $url, $args );
            $attempts++;
        } while (
            $attempts <= $retries &&
            is_wp_error( $response ) &&
            false !== stripos( $response->get_error_message(), 'cURL error 28' )
        );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            error_log( 'LimoSMS API WP Error => ' . $error_message );

            if ( false !== stripos( $error_message, 'cURL error 28' ) ) {
                return new WP_Error(
                    'limosms_api_timeout',
                    'درخواست به سرویس پیامکی با تاخیر مواجه شد. لطفا دوباره تلاش کنید.',
                    array( 'raw' => $error_message )
                );
            }

            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );
        $json   = json_decode( $body, true );

        if ( $status < 200 || $status >= 300 ) {
            error_log( 'LimoSMS API Error => URL: ' . $url . ' | Status: ' . $status . ' | Body: ' . $body );

            $message = is_array( $json ) ? ( $json['message'] ?? $json['Message'] ?? $json['error'] ?? $json['Error'] ?? 'API Error' ) : 'API Error';

            return new WP_Error(
                'limosms_api_error',
                $message,
                array( 'status' => $status, 'raw' => $body )
            );
        }

        return is_array( $json ) ? $json : array();
    }

    public function send_pattern_message( $number, $otp_id, $tokens = array() ) {
        $body = array(
            'OtpId'        => sanitize_text_field( (string) $otp_id ),
            'ReplaceToken' => array_values( array_map( 'strval', (array) $tokens ) ),
            'MobileNumber' => sanitize_text_field( (string) $number ),
        );

        return $this->request( 'POST', 'sendpatternmessage', $body );
    }

    public function get_patterns() {
        return $this->request( 'POST', 'getpatterns', array() );
    }

    public function get_pattern_detail( $pattern_code ) {
        return $this->request(
            'POST',
            'getpattern',
            array(
                'patterncode' => sanitize_text_field( (string) $pattern_code ),
            )
        );
    }

    public function get_sent_sms() {
        return $this->request( 'POST', 'getsinglesms', array() );
    }

    public function get_current_credit() {
        return $this->request( 'POST', 'getcurrentcredit', array() );
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
        $code   = $this->normalize_code( $code );

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

    public function is_send_successful( $response ) {
        return $this->is_success_response( $response );
    }

    public function is_verification_successful( $response ) {
        return $this->is_success_response( $response );
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
            if ( isset( $response[ $key ] ) && '' !== trim( (string) $response[ $key ] ) ) {
                return sanitize_text_field( wp_unslash( (string) $response[ $key ] ) );
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

    public function normalize_code( $code ) {
        $code = preg_replace( '/[^0-9]/', '', (string) $code );
        return preg_match( '/^\d{6}$/', $code ) ? $code : '';
    }

    private function is_success_response( $response ) {
        if ( ! is_array( $response ) || empty( $response ) ) {
            return false;
        }

        $truthy_keys = array(
            'success',
            'Success',
            'isSuccess',
            'IsSuccess',
            'result',
            'Result',
        );

        foreach ( $truthy_keys as $key ) {
            if ( isset( $response[ $key ] ) ) {
                return $this->is_truthy_flag( $response[ $key ] );
            }
        }

        $status_keys = array( 'status', 'Status' );

        foreach ( $status_keys as $key ) {
            if ( isset( $response[ $key ] ) ) {
                return in_array( $response[ $key ], array( 'success', 'Success', 'ok', 'OK', 1, '1', 200, '200' ), true );
            }
        }

        $code_keys = array( 'code', 'Code' );

        foreach ( $code_keys as $key ) {
            if ( isset( $response[ $key ] ) ) {
                return in_array( $response[ $key ], array( 1, '1', 200, '200' ), true );
            }
        }

        return false;
    }

    private function is_truthy_flag( $value ) {
        return true === $value || 'true' === $value || 1 === (int) $value;
    }
}
