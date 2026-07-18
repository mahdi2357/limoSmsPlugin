<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LimoSMS_Mobile_Auth {

    private $api;

    public function __construct() {
        $this->api = new LimoSMS_API();

        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_shortcode( 'limoo_sms_auth', array( $this, 'render_shortcode' ) );

        add_action( 'wp_ajax_nopriv_limosms_send_otp', array( $this, 'ajax_send_otp' ) );
        add_action( 'wp_ajax_limosms_send_otp', array( $this, 'ajax_send_otp' ) );

        add_action( 'wp_ajax_nopriv_limosms_verify_otp', array( $this, 'ajax_verify_otp' ) );
        add_action( 'wp_ajax_limosms_verify_otp', array( $this, 'ajax_verify_otp' ) );
    }

    public function register_assets() {
        wp_register_style(
            'limosms-mobile-auth',
            LIMOSMS_URL . 'assets/css/mobile-auth.css',
            array(),
            LIMOSMS_VERSION
        );

        wp_register_script(
            'limosms-mobile-auth',
            LIMOSMS_URL . 'assets/js/mobile-auth.js',
            array(),
            LIMOSMS_VERSION,
            true
        );

        wp_localize_script(
            'limosms-mobile-auth',
            'limosmsMobileAuth',
            array(
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'limosms_mobile_auth_nonce' ),
                'redirectUrl' => home_url( '/' ),
            )
        );
    }

    public function render_shortcode( $atts ) {
        wp_enqueue_style( 'limosms-mobile-auth' );
        wp_enqueue_script( 'limosms-mobile-auth' );

        ob_start();
        include LIMOSMS_PATH . 'templates/mobile-auth-form.php';
        return ob_get_clean();
    }

    public function ajax_send_otp() {
        check_ajax_referer( 'limosms_mobile_auth_nonce', 'nonce' );

        $mobile = sanitize_text_field( $_POST['mobile'] ?? '' );
        $mobile = $this->api->normalize_mobile( $mobile );

        if ( '' === $mobile ) {
            wp_send_json_error(
                array(
                    'message' => 'شماره موبایل نامعتبر است.',
                )
            );
        }

        $rate_limit_key = 'limosms_otp_' . md5( $mobile );

        if ( get_transient( $rate_limit_key ) ) {
            wp_send_json_error(
                array(
                    'message' => 'لطفا کمی بعد دوباره تلاش کنید.',
                )
            );
        }

        $response = $this->api->send_verification_code( $mobile );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => $response->get_error_message(),
                )
            );
        }

        set_transient( $rate_limit_key, 1, MINUTE_IN_SECONDS );

        wp_send_json_success(
            array(
                'message' => 'کد ارسال شد.',
            )
        );
    }

    public function ajax_verify_otp() {
        check_ajax_referer( 'limosms_mobile_auth_nonce', 'nonce' );

        $mobile = sanitize_text_field( $_POST['mobile'] ?? '' );
        $code   = sanitize_text_field( $_POST['code'] ?? '' );

        $mobile = $this->api->normalize_mobile( $mobile );

        if ( '' === $mobile ) {
            wp_send_json_error(
                array(
                    'message' => 'شماره موبایل نامعتبر است.',
                )
            );
        }

        if ( '' === $code ) {
            wp_send_json_error(
                array(
                    'message' => 'کد تایید را وارد کنید.',
                )
            );
        }

        $response = $this->api->check_verification_code( $mobile, $code );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => $response->get_error_message(),
                )
            );
        }

        if ( ! $this->api->is_verification_successful( $response ) ) {
            wp_send_json_error(
                array(
                    'message'  => $this->api->get_response_message( $response, 'کد تایید صحیح نیست.' ),
                    'response' => $response,
                )
            );
        }


        $user = $this->get_or_create_user_by_mobile( $mobile );

        if ( is_wp_error( $user ) ) {
            wp_send_json_error(
                array(
                    'message' => $user->get_error_message(),
                )
            );
        }

        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );

        do_action( 'wp_login', $user->user_login, $user );

        wp_send_json_success(
            array(
                'message'     => 'ورود با موفقیت انجام شد.',
                'redirectUrl' => home_url( '/' ),
            )
        );
    }

    public function is_verification_successful( $response ) {
        if ( ! is_array( $response ) || empty( $response ) ) {
            return false;
        }

        // حالت هاي متداول براي پاسخ موفق API
        if ( isset( $response['success'] ) ) {
            return true === $response['success'] || 'true' === $response['success'] || 1 === (int) $response['success'];
        }

        if ( isset( $response['Success'] ) ) {
            return true === $response['Success'] || 'true' === $response['Success'] || 1 === (int) $response['Success'];
        }

        if ( isset( $response['status'] ) ) {
            return in_array( $response['status'], array( 'success', 'ok', 1, '1' ), true );
        }

        if ( isset( $response['Status'] ) ) {
            return in_array( $response['Status'], array( 'success', 'ok', 1, '1' ), true );
        }

        if ( isset( $response['result'] ) ) {
            return true === $response['result'] || 'true' === $response['result'] || 1 === (int) $response['result'];
        }

        if ( isset( $response['Result'] ) ) {
            return true === $response['Result'] || 'true' === $response['Result'] || 1 === (int) $response['Result'];
        }

        return false;
    }

    public function get_response_message( $response, $default = 'عملیات ناموفق بود.' ) {
        if ( ! is_array( $response ) ) {
            return $default;
        }

        $keys = array( 'message', 'Message', 'error', 'Error', 'detail', 'Detail' );

        foreach ( $keys as $key ) {
            if ( isset( $response[ $key ] ) && '' !== (string) $response[ $key ] ) {
                return (string) $response[ $key ];
            }
        }

        return $default;
    }


    private function get_or_create_user_by_mobile( $mobile ) {
        $user = $this->find_user_by_mobile( $mobile );

        if ( $user instanceof WP_User ) {
            return $user;
        }

        $username = $this->generate_username_from_mobile( $mobile );
        $email    = $username . '@limosms.local';
        $password = wp_generate_password( 20, true, true );

        $user_id = wp_insert_user(
            array(
                'user_login'   => $username,
                'user_pass'    => $password,
                'user_email'   => $email,
                'display_name' => $mobile,
                'nickname'     => $mobile,
                'role'         => get_option( 'default_role', 'subscriber' ),
            )
        );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        update_user_meta( $user_id, 'limosms_mobile', $mobile );
        update_user_meta( $user_id, 'billing_phone', $mobile );

        return get_user_by( 'id', $user_id );
    }

    private function find_user_by_mobile( $mobile ) {
        $users = get_users(
            array(
                'number'      => 1,
                'count_total' => false,
                'meta_query'  => array(
                    'relation' => 'OR',
                    array(
                        'key'   => 'limosms_mobile',
                        'value' => $mobile,
                    ),
                    array(
                        'key'   => 'billing_phone',
                        'value' => $mobile,
                    ),
                ),
            )
        );

        if ( ! empty( $users ) && $users[0] instanceof WP_User ) {
            return $users[0];
        }

        return false;
    }

    private function generate_username_from_mobile( $mobile ) {
        $base_username = 'mobile_' . preg_replace( '/[^0-9]/', '', $mobile );
        $username      = $base_username;
        $suffix        = 1;

        while ( username_exists( $username ) ) {
            $username = $base_username . '_' . $suffix;
            $suffix++;
        }

        return $username;
    }
}
