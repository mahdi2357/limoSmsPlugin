<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LimoSMS_Mobile_Auth {

    const SEND_COOLDOWN_SECONDS    = 60;
    const SEND_MAX_PER_HOUR_MOBILE = 5;
    const SEND_MAX_PER_HOUR_IP     = 20;
    const VERIFY_MAX_ATTEMPTS      = 5;
    const VERIFY_LOCKOUT_SECONDS   = 15 * MINUTE_IN_SECONDS;
    const CHALLENGE_TTL_SECONDS    = 10 * MINUTE_IN_SECONDS;

    private $api;

    public function __construct() {
        $this->api = new LimoSMS_API();

        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_shortcode( 'limo_sms_auth', array( $this, 'render_shortcode' ) );

        add_action( 'wp_ajax_nopriv_limosms_send_otp', array( $this, 'ajax_send_otp' ) );
        add_action( 'wp_ajax_limosms_send_otp', array( $this, 'ajax_send_otp' ) );

        add_action( 'wp_ajax_nopriv_limosms_verify_otp', array( $this, 'ajax_verify_otp' ) );
        add_action( 'wp_ajax_limosms_verify_otp', array( $this, 'ajax_verify_otp' ) );

        add_action( 'wp_ajax_nopriv_limosms_refresh_captcha', array( $this, 'ajax_refresh_captcha' ) );
        add_action( 'wp_ajax_limosms_refresh_captcha', array( $this, 'ajax_refresh_captcha' ) );
    }

    private function is_login_register_enabled() {
        $settings = $this->get_settings();

        return ! empty( $settings['login_register_otp_enabled'] ) && '1' === (string) $settings['login_register_otp_enabled'];
    }

    private function get_settings() {
        $settings = get_option( 'limoo_sms_settings', array() );
        return is_array( $settings ) ? $settings : array();
    }

    private function get_setting( $key, $default = null ) {
        $settings = $this->get_settings();

        if ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
            return $settings[ $key ];
        }

        return $default;
    }

    private function get_redirect_url() {
        $redirect = trim( (string) $this->get_setting( 'login_register_otp_redirect_url', '' ) );

        if ( '' === $redirect ) {
            return home_url( '/' );
        }

        if ( preg_match( '#^(https?:)?//#i', $redirect ) ) {
            return esc_url_raw( $redirect );
        }

        if ( strpos( $redirect, '/' ) !== 0 ) {
            $redirect = '/' . ltrim( $redirect, '/' );
        }

        return home_url( $redirect );
    }

    private function get_otp_expiry_seconds() {
        return max( 60, absint( $this->get_setting( 'login_register_otp_expiry_minutes', 10 ) ) * MINUTE_IN_SECONDS );
    }

    private function get_resend_cooldown_seconds() {
        return max( 10, absint( $this->get_setting( 'login_register_otp_resend_seconds', self::SEND_COOLDOWN_SECONDS ) ) );
    }

    private function get_verify_lockout_seconds() {
        return max( 60, absint( $this->get_setting( 'login_register_otp_lockout_minutes', 15 ) ) * MINUTE_IN_SECONDS );
    }

    private function get_verify_max_attempts() {
        return max( 1, absint( $this->get_setting( 'login_register_otp_max_attempts', self::VERIFY_MAX_ATTEMPTS ) ) );
    }

    private function get_form_align() {
        $align = sanitize_text_field( (string) $this->get_setting( 'login_register_otp_form_align', 'center' ) );
        return in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'center';
    }

    private function get_form_direction() {
        $direction = sanitize_text_field( (string) $this->get_setting( 'login_register_otp_form_direction', 'rtl' ) );
        return in_array( $direction, array( 'rtl', 'ltr' ), true ) ? $direction : 'rtl';
    }

    private function get_logo_url() {
        return esc_url_raw( $this->get_setting( 'login_register_otp_logo_url', '' ) );
    }

    private function get_background_image_url() {
        return esc_url_raw( $this->get_setting( 'login_register_otp_background_image_url', '' ) );
    }

    private function get_background_color() {
        return sanitize_hex_color( $this->get_setting( 'login_register_otp_background_color', '#ffffff' ) );
    }

    private function get_form_background_color() {
        return sanitize_hex_color( $this->get_setting( 'login_register_otp_form_background_color', '#ffffff' ) );
    }

    private function get_accent_color() {
        return sanitize_hex_color( $this->get_setting( 'login_register_otp_accent_color', '#2563eb' ) );
    }

    private function get_new_user_role() {
        $role = sanitize_text_field( (string) $this->get_setting( 'login_register_otp_role', get_option( 'default_role', 'subscriber' ) ) );
        $roles = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array();

        return isset( $roles[ $role ] ) ? $role : get_option( 'default_role', 'subscriber' );
    }

    private function ensure_mobile_user_meta( WP_User $user, $mobile ) {
        if ( '' === $mobile ) {
            return $user;
        }

        if ( get_user_meta( $user->ID, 'limosms_mobile', true ) !== $mobile ) {
            update_user_meta( $user->ID, 'limosms_mobile', $mobile );
        }

        if ( get_user_meta( $user->ID, 'billing_phone', true ) !== $mobile ) {
            update_user_meta( $user->ID, 'billing_phone', $mobile );
        }

        return $user;
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
            array( 'jquery' ),
            LIMOSMS_VERSION,
            true
        );

        wp_localize_script(
            'limosms-mobile-auth',
            'limosmsMobileAuth',
            array(
                'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
                'nonce'           => wp_create_nonce( 'limosms_mobile_auth_nonce' ),
                'redirectUrl'     => $this->get_redirect_url(),
                'otpLength'       => 6,
                'sendCooldown'    => $this->get_resend_cooldown_seconds(),
                'challengeExpiry' => $this->get_otp_expiry_seconds(),
                'verifyMaxAttempts' => $this->get_verify_max_attempts(),
                'lockoutSeconds'  => $this->get_verify_lockout_seconds(),
                'formAlign'       => $this->get_form_align(),
                'formDirection'   => $this->get_form_direction(),
                'logoUrl'         => $this->get_logo_url(),
                'backgroundImageUrl' => $this->get_background_image_url(),
                'backgroundColor' => $this->get_background_color(),
                'formBackgroundColor' => $this->get_form_background_color(),
                'accentColor'     => $this->get_accent_color(),
                'captchaEnabled'  => $this->is_captcha_enabled(),
            )
        );
    }

    public function render_shortcode( $atts ) {
        if ( ! $this->is_login_register_enabled() ) {
            return '';
        }

        $atts = shortcode_atts(
            array(
                'title' => '',
            ),
            $atts,
            'limo_sms_auth'
        );

        $title = sanitize_text_field( $atts['title'] );

        if ( is_user_logged_in() ) {
            $current_user = wp_get_current_user();

            ob_start();
            include LIMOSMS_PATH . 'templates/mobile-auth-logged-in.php';
            return ob_get_clean();
        }

        wp_enqueue_style( 'limosms-mobile-auth' );
        wp_enqueue_script( 'limosms-mobile-auth' );

        $form_style = array(
            'logo_url'             => $this->get_logo_url(),
            'background_image_url' => $this->get_background_image_url(),
            'background_color'     => $this->get_background_color(),
            'form_background_color'=> $this->get_form_background_color(),
            'accent_color'         => $this->get_accent_color(),
            'form_align'           => $this->get_form_align(),
            'form_direction'       => $this->get_form_direction(),
        );

        $captcha = array(
            'enabled'  => $this->is_captcha_enabled(),
            'token'    => '',
            'question' => '',
        );

        if ( $captcha['enabled'] ) {
            $captcha_data = $this->generate_captcha_data();
            $captcha['token'] = $captcha_data['token'];
            $captcha['question'] = $captcha_data['question'];
        }

        ob_start();
        include LIMOSMS_PATH . 'templates/mobile-auth-form.php';
        return ob_get_clean();
    }

    public function ajax_refresh_captcha() {
        check_ajax_referer( 'limosms_mobile_auth_nonce', 'nonce' );

        if ( ! $this->is_login_register_enabled() ) {
            wp_send_json_error(
                array(
                    'message' => 'ورود و ثبت‌نام با کد تایید غیرفعال است.',
                ),
                403
            );
        }

        if ( ! $this->is_captcha_enabled() ) {
            wp_send_json_error(
                array(
                    'message' => 'کپچا فعال نیست.',
                ),
                400
            );
        }

        $captcha_data = $this->generate_captcha_data();

        wp_send_json_success(
            array(
                'question' => $captcha_data['question'],
                'token'    => $captcha_data['token'],
            )
        );
    }

    private function is_captcha_enabled() {
        $settings = $this->get_settings();
        return ! empty( $settings['login_register_otp_captcha_enabled'] ) && '1' === (string) $settings['login_register_otp_captcha_enabled'];
    }

    private function get_captcha_key( $token ) {
        return 'limosms_mobile_auth_captcha_' . md5( $token );
    }

    private function generate_captcha_data() {
        $first = rand( 1, 9 );
        $second = rand( 1, 9 );
        $operators = array(
            '+' => $first + $second,
            '-' => $first - $second,
            '*' => $first * $second,
        );
        $operator = array_rand( $operators );
        $question = sprintf( '%d %s %d = ?', $first, $operator, $second );
        $token = wp_generate_password( 40, false, false );

        set_transient(
            $this->get_captcha_key( $token ),
            (string) $operators[ $operator ],
            10 * MINUTE_IN_SECONDS
        );

        return array(
            'token'    => $token,
            'question' => $question,
        );
    }

    private function validate_captcha( $token, $answer ) {
        if ( '' === $token || '' === $answer ) {
            return false;
        }

        $expected = get_transient( $this->get_captcha_key( $token ) );

        if ( false === $expected ) {
            return false;
        }

        $is_valid = sanitize_text_field( $answer ) === trim( $expected );

        if ( $is_valid ) {
            delete_transient( $this->get_captcha_key( $token ) );
        }

        return $is_valid;
    }


    public function ajax_send_otp() {
        check_ajax_referer( 'limosms_mobile_auth_nonce', 'nonce' );

        if ( ! $this->is_login_register_enabled() ) {
            wp_send_json_error(
                array(
                    'message' => 'ورود و ثبت‌نام با کد تایید غیرفعال است.',
                ),
                403
            );
        }

        $mobile = $this->api->normalize_mobile( wp_unslash( $_POST['mobile'] ?? '' ) );

        if ( $this->is_captcha_enabled() ) {
            $captcha_token = sanitize_text_field( wp_unslash( $_POST['captcha_token'] ?? '' ) );
            $captcha_answer = sanitize_text_field( wp_unslash( $_POST['captcha_answer'] ?? '' ) );

            if ( ! $this->validate_captcha( $captcha_token, $captcha_answer ) ) {
                wp_send_json_error(
                    array(
                        'message' => 'کپچا صحیح نیست. لطفا دوباره تلاش کنید.',
                    ),
                    400
                );
            }
        }

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

        $challenge_ttl = $this->get_otp_expiry_seconds();

        set_transient(
            $this->get_challenge_key( $challenge_token ),
            $challenge_data,
            $challenge_ttl
        );

        $this->mark_send_request( $mobile );
        $this->clear_verify_lock( $mobile );

        wp_send_json_success(
            array(
                'message'        => 'کد تایید ارسال شد.',
                'challengeToken' => $challenge_token,
                'expiresIn'      => $challenge_ttl,
            )
        );
    }

    public function ajax_verify_otp() {
        check_ajax_referer( 'limosms_mobile_auth_nonce', 'nonce' );

        if ( ! $this->is_login_register_enabled() ) {
            wp_send_json_error(
                array(
                    'message' => 'ورود و ثبت‌نام با کد تایید غیرفعال است.',
                ),
                403
            );
        }

        $mobile          = $this->api->normalize_mobile( wp_unslash( $_POST['mobile'] ?? '' ) );
        $code            = $this->api->normalize_code( wp_unslash( $_POST['code'] ?? '' ) );
        $challenge_token  = sanitize_text_field( wp_unslash( $_POST['challenge_token'] ?? '' ) );

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

        if ( time() - absint( $challenge['created_at'] ) > $this->get_otp_expiry_seconds() ) {
            delete_transient( $this->get_challenge_key( $challenge_token ) );

            wp_send_json_error(
                array(
                    'message' => 'زمان اعتبار کد به پایان رسیده است. دوباره کد دریافت کنید.',
                ),
                400
            );
        }

        $attempts = isset( $challenge['attempts'] ) ? absint( $challenge['attempts'] ) : 0;

        $max_attempts = $this->get_verify_max_attempts();

        if ( $attempts >= $max_attempts ) {
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

            if ( $challenge['attempts'] >= $max_attempts ) {
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
                'redirectUrl' => $this->get_redirect_url(),
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
        $cooldown = $this->get_resend_cooldown_seconds();

        set_transient( $this->get_send_cooldown_key( 'mobile', $mobile ), 1, $cooldown );

        if ( $ip ) {
            set_transient( $this->get_send_cooldown_key( 'ip', $ip ), 1, $cooldown );
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
        $lockout = $this->get_verify_lockout_seconds();

        set_transient( $this->get_verify_lock_key( 'mobile', $mobile ), 1, $lockout );

        if ( $ip ) {
            set_transient( $this->get_verify_lock_key( 'ip', $ip ), 1, $lockout );
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
            return $this->ensure_mobile_user_meta( $user, $mobile );
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
                'role'         => $this->get_new_user_role(),
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
