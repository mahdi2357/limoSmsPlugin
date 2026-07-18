<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LimoSMS_Mobile_Auth {

    const SEND_COOLDOWN_SECONDS      = 60;
    const SEND_MAX_PER_HOUR_MOBILE   = 5;
    const SEND_MAX_PER_HOUR_IP       = 20;
    const VERIFY_MAX_ATTEMPTS        = 5;
    const VERIFY_LOCKOUT_SECONDS     = 15 * MINUTE_IN_SECONDS;
    const CHALLENGE_TTL_SECONDS      = 10 * MINUTE_IN_SECONDS;

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
                'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
                'nonce'           => wp_create_nonce( 'limosms_mobile_auth_nonce' ),
                'redirectUrl'     => home_url( '/' ),
                'otpLength'       => 6,
                'sendCooldown'    => self::SEND_COOLDOWN_SECONDS,
                'challengeExpiry' => self::CHALLENGE_TTL_SECONDS,
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

        $mobile = $this->api->normalize_mobile( wp_unslash( $_POST['mobile'] ?? '' ) );

        if ( '' === $mobile ) {
            wp_send_json_error(
                array(
                    'message' => 'شماره موبایل معتبر نیست.',
                ),
                400
            );
        }

        if ( $this->is_send_rate_limited( $mobile ) ) {
            wp_send_json_error(
                array(
                    'message' => 'تعداد درخواست‌ها بیش از حد مجاز است. لطفا کمی بعد دوباره تلاش کنید.',
                ),
                429
            );
        }

        if ( $this->is_verify_locked( $mobile ) ) {
            wp_send_json_error(
                array(
                    'message' => 'به دلیل تلاش‌های ناموفق، موقتا امکان دریافت کد وجود ندارد. کمی بعد دوباره تلاش کنید.',
                ),
                429
            );
        }

        $response = $this->api->send_verification_code( $mobile );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => $response->get_error_message(),
                ),
                400
            );
        }

        if ( ! $this->api->is_send_successful( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => $this->api->get_response_message( $response, 'ارسال کد انجام نشد.' ),
                ),
                400
            );
        }

        $challenge_token = wp_generate_password( 40, false, false );
        $challenge_data  = array(
            'mobile'     => $mobile,
            'created_at' => time(),
            'attempts'   => 0,
        );

        set_transient(
            $this->get_challenge_key( $challenge_token ),
            $challenge_data,
            self::CHALLENGE_TTL_SECONDS
        );

        $this->mark_send_request( $mobile );
        $this->clear_verify_lock( $mobile );

        wp_send_json_success(
            array(
                'message'        => 'کد تایید ارسال شد.',
                'challengeToken' => $challenge_token,
                'expiresIn'      => self::CHALLENGE_TTL_SECONDS,
            )
        );
    }

    public function ajax_verify_otp() {
        check_ajax_referer( 'limosms_mobile_auth_nonce', 'nonce' );

        $mobile          = $this->api->normalize_mobile( wp_unslash( $_POST['mobile'] ?? '' ) );
        $code            = $this->api->normalize_code( wp_unslash( $_POST['code'] ?? '' ) );
        $challenge_token = sanitize_text_field( wp_unslash( $_POST['challenge_token'] ?? '' ) );

        if ( '' === $mobile ) {
            wp_send_json_error(
                array(
                    'message' => 'شماره موبایل معتبر نیست.',
                ),
                400
            );
        }

        if ( '' === $code ) {
            wp_send_json_error(
                array(
                    'message' => 'کد تایید باید 6 رقم باشد.',
                ),
                400
            );
        }

        if ( '' === $challenge_token ) {
            wp_send_json_error(
                array(
                    'message' => 'درخواست نامعتبر است. دوباره کد دریافت کنید.',
                ),
                400
            );
        }

        if ( $this->is_verify_locked( $mobile ) ) {
            wp_send_json_error(
                array(
                    'message' => 'تعداد تلاش‌های ناموفق بیش از حد مجاز است. لطفا دوباره کد دریافت کنید.',
                ),
                429
            );
        }

        $challenge = get_transient( $this->get_challenge_key( $challenge_token ) );

        if ( ! is_array( $challenge ) || empty( $challenge['mobile'] ) || empty( $challenge['created_at'] ) ) {
            wp_send_json_error(
                array(
                    'message' => 'کد منقضی شده یا درخواست معتبر نیست. دوباره کد دریافت کنید.',
                ),
                400
            );
        }

        if ( $mobile !== $challenge['mobile'] ) {
            wp_send_json_error(
                array(
                    'message' => 'اطلاعات ارسالی معتبر نیست. دوباره کد دریافت کنید.',
                ),
                400
            );
        }

        if ( time() - absint( $challenge['created_at'] ) > self::CHALLENGE_TTL_SECONDS ) {
            delete_transient( $this->get_challenge_key( $challenge_token ) );

            wp_send_json_error(
                array(
                    'message' => 'زمان اعتبار کد به پایان رسیده است. دوباره کد دریافت کنید.',
                ),
                400
            );
        }

        $attempts = isset( $challenge['attempts'] ) ? absint( $challenge['attempts'] ) : 0;

        if ( $attempts >= self::VERIFY_MAX_ATTEMPTS ) {
            delete_transient( $this->get_challenge_key( $challenge_token ) );
            $this->set_verify_lock( $mobile );

            wp_send_json_error(
                array(
                    'message' => 'تعداد دفعات ورود کد بیش از حد مجاز است. دوباره کد دریافت کنید.',
                ),
                429
            );
        }

        $response = $this->api->check_verification_code( $mobile, $code );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => $response->get_error_message(),
                ),
                400
            );
        }

        if ( ! $this->api->is_verification_successful( $response ) ) {
            $challenge['attempts'] = $attempts + 1;
            set_transient(
                $this->get_challenge_key( $challenge_token ),
                $challenge,
                self::CHALLENGE_TTL_SECONDS
            );

            if ( $challenge['attempts'] >= self::VERIFY_MAX_ATTEMPTS ) {
                delete_transient( $this->get_challenge_key( $challenge_token ) );
                $this->set_verify_lock( $mobile );

                wp_send_json_error(
                    array(
                        'message' => 'تعداد دفعات ورود کد بیش از حد مجاز است. دوباره کد دریافت کنید.',
                    ),
                    429
                );
            }

            wp_send_json_error(
                array(
                    'message' => $this->api->get_response_message( $response, 'کد تایید صحیح نیست.' ),
                ),
                400
            );
        }

        delete_transient( $this->get_challenge_key( $challenge_token ) );
        $this->clear_verify_lock( $mobile );

        $user = $this->get_or_create_user_by_mobile( $mobile );

        if ( is_wp_error( $user ) ) {
            wp_send_json_error(
                array(
                    'message' => $user->get_error_message(),
                ),
                400
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

    private function is_send_rate_limited( $mobile ) {
        $ip = $this->get_client_ip();

        if ( get_transient( $this->get_send_cooldown_key( 'mobile', $mobile ) ) ) {
            return true;
        }

        if ( $ip && get_transient( $this->get_send_cooldown_key( 'ip', $ip ) ) ) {
            return true;
        }

        $mobile_count = (int) get_transient( $this->get_send_hourly_key( 'mobile', $mobile ) );
        if ( $mobile_count >= self::SEND_MAX_PER_HOUR_MOBILE ) {
            return true;
        }

        if ( $ip ) {
            $ip_count = (int) get_transient( $this->get_send_hourly_key( 'ip', $ip ) );
            if ( $ip_count >= self::SEND_MAX_PER_HOUR_IP ) {
                return true;
            }
        }

        return false;
    }

    private function mark_send_request( $mobile ) {
        $ip = $this->get_client_ip();

        set_transient( $this->get_send_cooldown_key( 'mobile', $mobile ), 1, self::SEND_COOLDOWN_SECONDS );

        if ( $ip ) {
            set_transient( $this->get_send_cooldown_key( 'ip', $ip ), 1, self::SEND_COOLDOWN_SECONDS );
        }

        $mobile_hourly_key   = $this->get_send_hourly_key( 'mobile', $mobile );
        $mobile_hourly_count = (int) get_transient( $mobile_hourly_key );
        set_transient( $mobile_hourly_key, $mobile_hourly_count + 1, HOUR_IN_SECONDS );

        if ( $ip ) {
            $ip_hourly_key   = $this->get_send_hourly_key( 'ip', $ip );
            $ip_hourly_count = (int) get_transient( $ip_hourly_key );
            set_transient( $ip_hourly_key, $ip_hourly_count + 1, HOUR_IN_SECONDS );
        }
    }

    private function is_verify_locked( $mobile ) {
        $ip = $this->get_client_ip();

        if ( get_transient( $this->get_verify_lock_key( 'mobile', $mobile ) ) ) {
            return true;
        }

        if ( $ip && get_transient( $this->get_verify_lock_key( 'ip', $ip ) ) ) {
            return true;
        }

        return false;
    }

    private function set_verify_lock( $mobile ) {
        $ip = $this->get_client_ip();

        set_transient( $this->get_verify_lock_key( 'mobile', $mobile ), 1, self::VERIFY_LOCKOUT_SECONDS );

        if ( $ip ) {
            set_transient( $this->get_verify_lock_key( 'ip', $ip ), 1, self::VERIFY_LOCKOUT_SECONDS );
        }
    }

    private function clear_verify_lock( $mobile ) {
        $ip = $this->get_client_ip();

        delete_transient( $this->get_verify_lock_key( 'mobile', $mobile ) );

        if ( $ip ) {
            delete_transient( $this->get_verify_lock_key( 'ip', $ip ) );
        }
    }

    private function get_challenge_key( $token ) {
        return 'limosms_otp_challenge_' . md5( $token );
    }

    private function get_send_cooldown_key( $type, $value ) {
        return 'limosms_send_cd_' . $type . '_' . md5( $value );
    }

    private function get_send_hourly_key( $type, $value ) {
        return 'limosms_send_hr_' . $type . '_' . md5( $value );
    }

    private function get_verify_lock_key( $type, $value ) {
        return 'limosms_verify_lock_' . $type . '_' . md5( $value );
    }

    private function get_client_ip() {
        $keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        );

        foreach ( $keys as $key ) {
            if ( empty( $_SERVER[ $key ] ) ) {
                continue;
            }

            $raw_ip = wp_unslash( $_SERVER[ $key ] );
            $parts  = explode( ',', $raw_ip );
            $ip     = trim( $parts[0] );

            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }

        return '';
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
