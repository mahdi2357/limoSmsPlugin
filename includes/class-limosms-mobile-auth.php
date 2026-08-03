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

        add_action( 'wp_ajax_nopriv_limosms_password_login', array( $this, 'ajax_password_login' ) );
        add_action( 'wp_ajax_limosms_password_login', array( $this, 'ajax_password_login' ) );

        add_action( 'wp_ajax_nopriv_limosms_password_reset_request', array( $this, 'ajax_password_reset_request' ) );
        add_action( 'wp_ajax_limosms_password_reset_request', array( $this, 'ajax_password_reset_request' ) );

        add_action( 'wp_ajax_nopriv_limosms_password_reset_confirm', array( $this, 'ajax_password_reset_confirm' ) );
        add_action( 'wp_ajax_limosms_password_reset_confirm', array( $this, 'ajax_password_reset_confirm' ) );

        add_action( 'login_init', array( $this, 'block_default_login_page' ) );
        add_action( 'template_redirect', array( $this, 'block_woocommerce_account_access' ) );
        add_filter( 'authenticate', array( $this, 'block_default_authentication' ), 100, 3 );
        add_filter( 'registration_errors', array( $this, 'block_default_registration' ), 10, 3 );
        add_filter( 'woocommerce_process_login_errors', array( $this, 'block_woocommerce_login' ), 10, 3 );
        add_filter( 'woocommerce_registration_errors', array( $this, 'block_woocommerce_registration' ), 10, 3 );
    }

    private function is_login_register_enabled() {
        if ( ! LimoSMS_Connection_Settings::is_digits_sms_enabled() ) {
            return false;
        }

        $settings = $this->get_settings();

        return ! empty( $settings['login_register_otp_enabled'] ) && '1' === (string) $settings['login_register_otp_enabled'];
    }

    private function authenticate_with_password( $identifier, $password, $remember = false ) {
        $user = $this->get_user_by_identifier( $identifier );

        if ( ! $user instanceof WP_User ) {
            return new WP_Error( 'limosms_invalid_identifier', __( 'کاربر با این شماره یا نام کاربری یافت نشد.', 'limosms' ) );
        }

        remove_filter( 'authenticate', array( $this, 'block_default_authentication' ), 100 );

        $authenticated_user = wp_authenticate_username_password( null, $user->user_login, $password );

        add_filter( 'authenticate', array( $this, 'block_default_authentication' ), 100, 3 );

        if ( is_wp_error( $authenticated_user ) ) {
            return $authenticated_user;
        }

        if ( ! $authenticated_user instanceof WP_User ) {
            return new WP_Error( 'limosms_invalid_password', __( 'نام کاربری یا رمز عبور اشتباه است.', 'limosms' ) );
        }

        wp_clear_auth_cookie();
        wp_set_current_user( $authenticated_user->ID );
        wp_set_auth_cookie( $authenticated_user->ID, (bool) $remember );
        do_action( 'wp_login', $authenticated_user->user_login, $authenticated_user );

        return $authenticated_user;
    }

    private function get_user_by_identifier( $identifier ) {
        $identifier = trim( (string) $identifier );

        if ( '' === $identifier ) {
            return false;
        }

        $user = get_user_by( 'email', $identifier );
        if ( $user instanceof WP_User ) {
            return $user;
        }

        $user = get_user_by( 'login', $identifier );
        if ( $user instanceof WP_User ) {
            return $user;
        }

        $normalized_mobile = $this->api->normalize_mobile( $identifier, $this->get_allowed_country_codes() );
        if ( '' !== $normalized_mobile ) {
            return $this->find_user_by_mobile( $normalized_mobile );
        }

        return false;
    }

    private function should_disable_default_auth() {
        if ( ! $this->is_login_register_enabled() ) {
            return false;
        }

        $settings = $this->get_settings();

        return ! empty( $settings['login_register_disable_default_auth'] ) && '1' === (string) $settings['login_register_disable_default_auth'];
    }

    public function block_default_authentication( $user, $username, $password ) {
        if ( ! $this->should_disable_default_auth() ) {
            return $user;
        }

        if ( is_a( $user, 'WP_User' ) ) {
            return $user;
        }

        if ( is_admin() || is_network_admin() || defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            return $user;
        }

        if ( empty( $username ) && empty( $password ) ) {
            return $user;
        }

        return new WP_Error(
            'limosms_default_auth_disabled',
            __( 'ورود با حساب وردپرس/ووکامرس غیرفعال است. لطفاً از فرم ورود با کد تأیید استفاده کنید.', 'limosms' )
        );
    }

    public function block_default_registration( $errors, $sanitized_user_login, $user_email ) {
        if ( ! $this->should_disable_default_auth() ) {
            return $errors;
        }

        if ( is_admin() || is_network_admin() ) {
            return $errors;
        }

        if ( ! $errors instanceof WP_Error ) {
            $errors = new WP_Error();
        }

        $errors->add(
            'limosms_default_registration_disabled',
            __( 'ثبت‌نام با حساب وردپرس غیرفعال است. لطفاً از فرم ورود و عضویت با کد تأیید استفاده کنید.', 'limosms' )
        );

        return $errors;
    }

    public function block_default_login_page() {
        if ( ! $this->should_disable_default_auth() || is_user_logged_in() ) {
            return;
        }

        if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
            return;
        }

        $request_uri = wp_unslash( (string) $_SERVER['REQUEST_URI'] );
        $is_login_page = false !== strpos( $request_uri, 'wp-login.php' ) || false !== strpos( $request_uri, '/wp-admin/' );

        if ( ! $is_login_page ) {
            return;
        }

        $redirect_to = $this->get_redirect_url();

        wp_safe_redirect( $redirect_to );
        exit;
    }

    public function block_woocommerce_login( $errors, $username, $password ) {
        if ( ! $this->should_disable_default_auth() ) {
            return $errors;
        }

        if ( is_admin() || is_network_admin() ) {
            return $errors;
        }

        if ( ! $errors instanceof WP_Error ) {
            $errors = new WP_Error();
        }

        $errors->add(
            'limosms_woocommerce_login_disabled',
            __( 'ورود ووکامرس غیرفعال است. لطفاً از فرم ورود با کد تأیید استفاده کنید.', 'limosms' )
        );

        return $errors;
    }

    public function block_woocommerce_registration( $errors, $username, $email ) {
        if ( ! $this->should_disable_default_auth() ) {
            return $errors;
        }

        if ( is_admin() || is_network_admin() ) {
            return $errors;
        }

        if ( ! $errors instanceof WP_Error ) {
            $errors = new WP_Error();
        }

        $errors->add(
            'limosms_woocommerce_registration_disabled',
            __( 'ثبت‌نام ووکامرس غیرفعال است. لطفاً از فرم ورود و عضویت با کد تأیید استفاده کنید.', 'limosms' )
        );

        return $errors;
    }

    public function block_woocommerce_account_access() {
        if ( ! $this->should_disable_default_auth() || is_user_logged_in() ) {
            return;
        }

        if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
            return;
        }

        $redirect_to = $this->get_redirect_url();

        if ( ! empty( $redirect_to ) ) {
            wp_safe_redirect( $redirect_to );
            exit;
        }

        wp_safe_redirect( home_url( '/' ) );
        exit;
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

    private function get_allowed_country_codes() {
        $codes = $this->get_setting( 'login_register_otp_allowed_country_codes', array() );

        if ( ! is_array( $codes ) ) {
            $codes = array();
        }

        $normalized = array();
        foreach ( $codes as $code ) {
            $clean_code = preg_replace( '/[^0-9]/', '', (string) $code );
            if ( '' !== $clean_code ) {
                $normalized[] = $clean_code;
            }
        }

        $normalized = array_values( array_unique( $normalized ) );

        if ( empty( $normalized ) ) {
            $normalized = array( '98' );
        }

        return $normalized;
    }

    private function is_mobile_allowed_for_selected_countries( $mobile ) {
        $allowed_codes = $this->get_allowed_country_codes();

        if ( empty( $allowed_codes ) ) {
            return '' !== $this->api->normalize_mobile( $mobile );
        }

        $digits = preg_replace( '/[^0-9]/', '', (string) $mobile );

        if ( '' === $digits ) {
            return false;
        }

        if ( in_array( '98', $allowed_codes, true ) && preg_match( '/^09\d{9}$/', $digits ) ) {
            return true;
        }

        foreach ( $allowed_codes as $code ) {
            if ( 0 === strpos( $digits, $code ) ) {
                return true;
            }
        }

        return false;
    }

    private function get_custom_css() {
        return sanitize_textarea_field( (string) $this->get_setting( 'login_register_otp_custom_css', '' ) );
    }

    private function get_form_font_family() {
        $font_family = sanitize_text_field( (string) $this->get_setting( 'login_register_otp_font_family', 'Vazirmatn, Tahoma, Arial, sans-serif' ) );
        $allowed_font_families = array(
            'Vazirmatn, Tahoma, Arial, sans-serif',
            'IRANSans, Tahoma, Arial, sans-serif',
            'Tahoma, Arial, sans-serif',
            'Arial, sans-serif',
            'Segoe UI, Tahoma, sans-serif',
            'Yekan, Tahoma, Arial, sans-serif',
            'Times New Roman, serif',
        );

        return in_array( $font_family, $allowed_font_families, true ) ? $font_family : 'Vazirmatn, Tahoma, Arial, sans-serif';
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

    private function get_accent_secondary_color() {
        $primary = $this->get_accent_color();
        $secondary = sanitize_hex_color( $this->get_setting( 'login_register_otp_accent_secondary_color', $primary ) );
        return $secondary ? $secondary : $primary;
    }

    private function get_new_user_role() {
        $role = sanitize_text_field( (string) $this->get_setting( 'login_register_otp_role', get_option( 'default_role', 'subscriber' ) ) );
        $roles = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array();

        return isset( $roles[ $role ] ) ? $role : get_option( 'default_role', 'subscriber' );
    }

    private function get_registration_fields_config() {
        $defaults = array(
            'username' => array(
                'label' => __( 'نام کاربری', 'limosms' ),
                'type' => 'text',
                'placeholder' => __( 'نام کاربری', 'limosms' ),
            ),
            'password' => array(
                'label' => __( 'رمز عبور', 'limosms' ),
                'type' => 'password',
                'placeholder' => __( 'رمز عبور', 'limosms' ),
            ),
            'email' => array(
                'label' => __( 'ایمیل', 'limosms' ),
                'type' => 'email',
                'placeholder' => 'name@example.com',
            ),
            'first_name' => array(
                'label' => __( 'نام', 'limosms' ),
                'type' => 'text',
                'placeholder' => __( 'نام', 'limosms' ),
            ),
            'last_name' => array(
                'label' => __( 'نام خانوادگی', 'limosms' ),
                'type' => 'text',
                'placeholder' => __( 'نام خانوادگی', 'limosms' ),
            ),
            'address' => array(
                'label' => __( 'آدرس', 'limosms' ),
                'type' => 'text',
                'placeholder' => __( 'آدرس', 'limosms' ),
            ),
            'city' => array(
                'label' => __( 'شهر', 'limosms' ),
                'type' => 'text',
                'placeholder' => __( 'شهر', 'limosms' ),
            ),
            'postcode' => array(
                'label' => __( 'کد پستی', 'limosms' ),
                'type' => 'text',
                'placeholder' => __( 'کد پستی', 'limosms' ),
            ),
        );

        $configured = $this->get_setting( 'login_register_otp_registration_fields', array() );
        if ( ! is_array( $configured ) ) {
            $configured = array();
        }

        $fields = array();

        foreach ( $defaults as $key => $field ) {
            $field_config = isset( $configured[ $key ] ) && is_array( $configured[ $key ] ) ? $configured[ $key ] : array();
            $enabled = ! empty( $field_config['enabled'] ) && '1' === (string) $field_config['enabled'];
            if ( ! $enabled ) {
                continue;
            }

            $field['enabled'] = true;
            $field['required'] = ! empty( $field_config['required'] ) && '1' === (string) $field_config['required'];
            $field['key'] = $key;
            $fields[ $key ] = $field;
        }

        return $fields;
    }

    private function get_registration_fields_for_form() {
        $fields = $this->get_registration_fields_config();
        $prepared = array();

        foreach ( $fields as $key => $field ) {
            $prepared[ $key ] = array(
                'key' => $key,
                'label' => isset( $field['label'] ) ? $field['label'] : '',
                'type' => isset( $field['type'] ) ? $field['type'] : 'text',
                'placeholder' => isset( $field['placeholder'] ) ? $field['placeholder'] : '',
                'required' => ! empty( $field['required'] ),
            );
        }

        return $prepared;
    }

    private function get_registration_fields_from_request() {
        $raw_fields = isset( $_POST['registration_fields'] ) && is_array( $_POST['registration_fields'] ) ? wp_unslash( $_POST['registration_fields'] ) : array();
        $fields = array();

        foreach ( $this->get_registration_fields_config() as $key => $field ) {
            if ( ! isset( $raw_fields[ $key ] ) ) {
                continue;
            }

            $value = $raw_fields[ $key ];
            switch ( $key ) {
                case 'username':
                    $fields[ $key ] = sanitize_user( (string) $value );
                    break;
                case 'email':
                    $fields[ $key ] = sanitize_email( (string) $value );
                    break;
                case 'password':
                    $fields[ $key ] = sanitize_text_field( (string) $value );
                    break;
                default:
                    $fields[ $key ] = sanitize_text_field( (string) $value );
                    break;
            }
        }

        return $fields;
    }

    private function validate_registration_fields( $fields ) {
        foreach ( $this->get_registration_fields_config() as $key => $field ) {
            if ( empty( $field['required'] ) ) {
                continue;
            }

            $value = isset( $fields[ $key ] ) ? trim( (string) $fields[ $key ] ) : '';

            if ( '' === $value ) {
                return sprintf( __( 'فیلد %s الزامی است.', 'limosms' ), $field['label'] );
            }

            if ( 'email' === $key && ! is_email( $value ) ) {
                return __( 'ایمیل وارد شده معتبر نیست.', 'limosms' );
            }
        }

        return '';
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

    private function get_activity_logs() {
        $logs = get_option( 'limoo_sms_auth_logs', array() );
        return is_array( $logs ) ? $logs : array();
    }

    private function add_activity_log( $type, $mobile = '', $message = '', $details = array() ) {
        $logs = $this->get_activity_logs();

        $entry = array(
            'timestamp' => current_time( 'timestamp' ),
            'type'      => sanitize_key( (string) $type ),
            'mobile'    => sanitize_text_field( (string) $mobile ),
            'message'   => sanitize_text_field( (string) $message ),
            'ip'        => sanitize_text_field( (string) $this->get_client_ip() ),
            'details'   => array(),
        );

        if ( is_array( $details ) ) {
            foreach ( $details as $key => $value ) {
                if ( is_scalar( $value ) ) {
                    $entry['details'][ sanitize_key( (string) $key ) ] = sanitize_text_field( (string) $value );
                }
            }
        }

        array_unshift( $logs, $entry );
        $logs = array_slice( $logs, 0, 50 );

        update_option( 'limoo_sms_auth_logs', $logs );
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
                'accentSecondaryColor' => $this->get_accent_secondary_color(),
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
            'accent_secondary_color' => $this->get_accent_secondary_color(),
            'form_align'           => $this->get_form_align(),
            'form_direction'       => $this->get_form_direction(),
            'font_family'          => $this->get_form_font_family(),
            'custom_css'          => $this->get_custom_css(),
        );

        $registration_fields = $this->get_registration_fields_for_form();
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

    public function ajax_password_login() {
        check_ajax_referer( 'limosms_mobile_auth_nonce', 'nonce' );

        if ( ! $this->is_login_register_enabled() ) {
            wp_send_json_error(
                array(
                    'message' => 'ورود و ثبت‌نام با کد تایید غیرفعال است.',
                ),
                403
            );
        }

        $identifier = trim( wp_unslash( $_POST['identifier'] ?? '' ) );
        $password = wp_unslash( $_POST['password'] ?? '' );
        $remember = ! empty( $_POST['remember'] );

        if ( '' === $identifier || '' === $password ) {
            wp_send_json_error(
                array(
                    'message' => 'شماره موبایل/نام کاربری و رمز عبور را وارد کنید.',
                ),
                400
            );
        }

        $user = $this->authenticate_with_password( $identifier, $password, $remember );

        if ( is_wp_error( $user ) ) {
            wp_send_json_error(
                array(
                    'message' => $user->get_error_message(),
                ),
                401
            );
        }

        wp_send_json_success(
            array(
                'message'     => 'ورود با موفقیت انجام شد.',
                'redirectUrl' => $this->get_redirect_url(),
            )
        );
    }

    public function ajax_password_reset_request() {
        check_ajax_referer( 'limosms_mobile_auth_nonce', 'nonce' );

        if ( ! $this->is_login_register_enabled() ) {
            wp_send_json_error(
                array(
                    'message' => 'ورود و ثبت‌نام با کد تایید غیرفعال است.',
                ),
                403
            );
        }

        $mobile_raw = wp_unslash( $_POST['mobile'] ?? '' );
        $mobile = $this->api->normalize_mobile( $mobile_raw, $this->get_allowed_country_codes() );

        if ( '' === $mobile ) {
            wp_send_json_error(
                array(
                    'message' => 'شماره موبایل معتبر نیست.',
                ),
                400
            );
        }

        $user = $this->find_user_by_mobile( $mobile );

        if ( ! $user instanceof WP_User ) {
            wp_send_json_error(
                array(
                    'message' => 'کاربری با این شماره یافت نشد.',
                ),
                404
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
                    'message' => $this->api->get_response_message( $response, 'ارسال کد بازنشانی انجام نشد.' ),
                ),
                400
            );
        }

        $challenge_token = wp_generate_password( 40, false, false );
        $challenge_data = array(
            'mobile'     => $mobile,
            'created_at' => time(),
            'attempts'   => 0,
            'user_id'    => $user->ID,
        );

        set_transient(
            $this->get_password_reset_key( $challenge_token ),
            $challenge_data,
            $this->get_otp_expiry_seconds()
        );

        wp_send_json_success(
            array(
                'message'        => 'کد بازیابی ارسال شد. لطفاً کد را وارد کنید.',
                'challengeToken' => $challenge_token,
                'expiresIn'      => $this->get_otp_expiry_seconds(),
            )
        );
    }

    public function ajax_password_reset_confirm() {
        check_ajax_referer( 'limosms_mobile_auth_nonce', 'nonce' );

        if ( ! $this->is_login_register_enabled() ) {
            wp_send_json_error(
                array(
                    'message' => 'ورود و ثبت‌نام با کد تایید غیرفعال است.',
                ),
                403
            );
        }

        $mobile_raw      = wp_unslash( $_POST['mobile'] ?? '' );
        $mobile          = $this->api->normalize_mobile( $mobile_raw, $this->get_allowed_country_codes() );
        $code            = $this->api->normalize_code( wp_unslash( $_POST['code'] ?? '' ) );
        $challenge_token = sanitize_text_field( wp_unslash( $_POST['challenge_token'] ?? '' ) );
        $new_password    = wp_unslash( $_POST['new_password'] ?? '' );
        $confirm_password = wp_unslash( $_POST['confirm_password'] ?? '' );

        if ( '' === $mobile || '' === $code || '' === $challenge_token ) {
            wp_send_json_error(
                array(
                    'message' => 'اطلاعات بازیابی ناقص است.',
                ),
                400
            );
        }

        if ( '' === $new_password || $new_password !== $confirm_password ) {
            wp_send_json_error(
                array(
                    'message' => 'رمز عبور جدید و تکرار آن باید یکسان باشد.',
                ),
                400
            );
        }

        if ( strlen( $new_password ) < 6 ) {
            wp_send_json_error(
                array(
                    'message' => 'رمز عبور باید حداقل 6 کاراکتر باشد.',
                ),
                400
            );
        }

        $challenge = get_transient( $this->get_password_reset_key( $challenge_token ) );

        if ( ! is_array( $challenge ) || empty( $challenge['mobile'] ) || empty( $challenge['created_at'] ) ) {
            wp_send_json_error(
                array(
                    'message' => 'کد بازیابی منقضی شده یا نامعتبر است.',
                ),
                400
            );
        }

        if ( $mobile !== $challenge['mobile'] ) {
            wp_send_json_error(
                array(
                    'message' => 'شماره موبایل با کد بازیابی مطابقت ندارد.',
                ),
                400
            );
        }

        if ( time() - absint( $challenge['created_at'] ) > $this->get_otp_expiry_seconds() ) {
            delete_transient( $this->get_password_reset_key( $challenge_token ) );
            wp_send_json_error(
                array(
                    'message' => 'زمان کد بازیابی به پایان رسیده است.',
                ),
                400
            );
        }

        $response = $this->api->check_verification_code( $mobile, $code );

        if ( is_wp_error( $response ) || ! $this->api->is_verification_successful( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => $this->api->get_response_message( $response, 'کد بازیابی معتبر نیست.' ),
                ),
                400
            );
        }

        $user = get_user_by( 'id', absint( $challenge['user_id'] ) );

        if ( ! $user instanceof WP_User ) {
            wp_send_json_error(
                array(
                    'message' => 'کاربر مربوطه یافت نشد.',
                ),
                404
            );
        }

        wp_set_password( $new_password, $user->ID );
        delete_transient( $this->get_password_reset_key( $challenge_token ) );

        wp_clear_auth_cookie();
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );
        do_action( 'wp_login', $user->user_login, $user );

        wp_send_json_success(
            array(
                'message'     => 'رمز عبور با موفقیت تغییر کرد.',
                'redirectUrl' => $this->get_redirect_url(),
            )
        );
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

        $mobile = wp_unslash( $_POST['mobile'] ?? '' );
        $mobile = $this->api->normalize_mobile( $mobile, $this->get_allowed_country_codes() );
        $mode = $this->get_requested_mode();
        $registration_fields = $this->get_registration_fields_from_request();
        $registration_error = 'register' === $mode ? $this->validate_registration_fields( $registration_fields ) : '';

        if ( '' !== $registration_error ) {
            wp_send_json_error(
                array(
                    'message' => $registration_error,
                ),
                400
            );
        }

        if ( $this->is_captcha_enabled() ) {
            $captcha_token = sanitize_text_field( wp_unslash( $_POST['captcha_token'] ?? '' ) );
            $captcha_answer = sanitize_text_field( wp_unslash( $_POST['captcha_answer'] ?? '' ) );

            if ( ! $this->validate_captcha( $captcha_token, $captcha_answer ) ) {
                $this->add_activity_log( 'captcha_failed', $mobile, 'تلاش ناموفق برای کپچا', array( 'token' => $captcha_token ) );
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
                    'message' => 'شماره موبایل معتبر نیست یا برای کشور انتخابی مجاز نیست.',
                ),
                400
            );
        }

        if ( ! $this->is_mobile_allowed_for_selected_countries( $mobile ) ) {
            wp_send_json_error(
                array(
                    'message' => 'این شماره موبایل برای کشورهای مجاز انتخابی قابل استفاده نیست.',
                ),
                400
            );
        }

        if ( $this->is_send_rate_limited( $mobile ) ) {
            $this->add_activity_log( 'rate_limited', $mobile, 'ارسال کد مسدود شد؛ نرخ بیش از حد', array( 'reason' => 'rate_limit' ) );
            wp_send_json_error(
                array(
                    'message' => 'تعداد درخواست‌ها بیش از حد مجاز است. لطفا کمی بعد دوباره تلاش کنید.',
                ),
                429
            );
        }

        if ( $this->is_verify_locked( $mobile ) ) {
            $this->add_activity_log( 'verify_locked', $mobile, 'ارسال کد متوقف شد؛ کاربر قفل شده است', array( 'reason' => 'verify_lock' ) );
            wp_send_json_error(
                array(
                    'message' => 'به دلیل تلاش‌های ناموفق، موقتا امکان دریافت کد وجود ندارد. کمی بعد دوباره تلاش کنید.',
                ),
                429
            );
        }

        $response = $this->api->send_verification_code( $mobile );

        if ( is_wp_error( $response ) ) {
            $this->add_activity_log( 'otp_send_failed', $mobile, 'ارسال کد با خطا مواجه شد', array( 'error' => $response->get_error_message() ) );
            wp_send_json_error(
                array(
                    'message' => $response->get_error_message(),
                ),
                400
            );
        }

        if ( ! $this->api->is_send_successful( $response ) ) {
            $this->add_activity_log( 'otp_send_failed', $mobile, 'ارسال کد توسط API ناموفق بود', array( 'response' => $this->api->get_response_message( $response, 'ارسال کد انجام نشد.' ) ) );
            wp_send_json_error(
                array(
                    'message' => $this->api->get_response_message( $response, 'ارسال کد انجام نشد.' ),
                ),
                400
            );
        }

        $challenge_token = wp_generate_password( 40, false, false );
        $challenge_data  = array(
            'mobile'             => $mobile,
            'created_at'         => time(),
            'attempts'           => 0,
            'mode'               => $mode,
            'registration_fields'=> $registration_fields,
        );

        $challenge_ttl = $this->get_otp_expiry_seconds();

        set_transient(
            $this->get_challenge_key( $challenge_token ),
            $challenge_data,
            $challenge_ttl
        );

        $this->mark_send_request( $mobile );
        $this->clear_verify_lock( $mobile );
        $this->add_activity_log( 'otp_send_success', $mobile, 'کد تایید با موفقیت ارسال شد' );

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

        $mobile_raw      = wp_unslash( $_POST['mobile'] ?? '' );
        $mobile          = $this->api->normalize_mobile( $mobile_raw, $this->get_allowed_country_codes() );
        $code            = $this->api->normalize_code( wp_unslash( $_POST['code'] ?? '' ) );
        $challenge_token  = sanitize_text_field( wp_unslash( $_POST['challenge_token'] ?? '' ) );
        $mode            = $this->get_requested_mode();

        if ( '' === $mobile ) {
            wp_send_json_error(
                array(
                    'message' => 'شماره موبایل معتبر نیست یا برای کشور انتخابی مجاز نیست.',
                ),
                400
            );
        }

        if ( ! $this->is_mobile_allowed_for_selected_countries( $mobile ) ) {
            wp_send_json_error(
                array(
                    'message' => 'این شماره موبایل برای کشورهای مجاز انتخابی قابل استفاده نیست.',
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
            $this->add_activity_log( 'verify_locked', $mobile, 'تلاش برای ورود مسدود شد؛ کاربر قفل شده است', array( 'reason' => 'verify_lock' ) );
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
            $this->add_activity_log( 'verify_failed', $mobile, 'کد تایید نامعتبر بود', array( 'attempt' => $challenge['attempts'] ) );

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
        $this->add_activity_log( 'verify_success', $mobile, 'ورود با کد تایید موفقیت‌آمیز بود' );

        $challenge_mode = isset( $challenge['mode'] ) ? sanitize_key( (string) $challenge['mode'] ) : 'login';
        $user = $this->get_or_create_user_by_mobile( $mobile, isset( $challenge['registration_fields'] ) && is_array( $challenge['registration_fields'] ) ? $challenge['registration_fields'] : array(), 'register' === $challenge_mode );

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

    private function get_password_reset_key( $token ) {
        return 'limosms_password_reset_' . md5( $token );
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

    private function get_requested_mode() {
        $mode = sanitize_key( wp_unslash( $_POST['mode'] ?? '' ) );
        return 'register' === $mode ? 'register' : 'login';
    }

    private function get_or_create_user_by_mobile( $mobile, $registration_fields = array(), $allow_registration = false ) {
        $user = $this->find_user_by_mobile( $mobile );

        if ( $user instanceof WP_User ) {
            return $this->ensure_mobile_user_meta( $user, $mobile );
        }

        if ( ! $allow_registration ) {
            return new WP_Error( 'user_not_found', __( 'کاربر با این شماره یافت نشد. برای ثبت‌نام، حالت ثبت‌نام را انتخاب کنید.', 'limosms' ) );
        }

        $username = isset( $registration_fields['username'] ) && '' !== trim( (string) $registration_fields['username'] )
            ? trim( (string) $registration_fields['username'] )
            : $this->generate_username_from_mobile( $mobile );
        $email    = isset( $registration_fields['email'] ) && '' !== trim( (string) $registration_fields['email'] )
            ? trim( (string) $registration_fields['email'] )
            : $username . '@limosms.local';
        $first_name = isset( $registration_fields['first_name'] ) ? trim( (string) $registration_fields['first_name'] ) : '';
        $last_name = isset( $registration_fields['last_name'] ) ? trim( (string) $registration_fields['last_name'] ) : '';
        $password = isset( $registration_fields['password'] ) ? trim( (string) $registration_fields['password'] ) : '';
        if ( '' === $password ) {
            $password = wp_generate_password( 20, true, true );
        }

        $username = sanitize_user( $username, true );
        $email    = sanitize_email( $email );

        if ( '' === $username ) {
            $username = $this->generate_username_from_mobile( $mobile );
        }

        if ( '' === $email ) {
            $email = $username . '@limosms.local';
        }

        if ( username_exists( $username ) ) {
            $username = $this->generate_username_from_mobile( $mobile );
        }

        if ( email_exists( $email ) ) {
            return new WP_Error( 'email_exists', __( 'این ایمیل قبلاً ثبت شده است.', 'limosms' ) );
        }

        $user_id = wp_insert_user(
            array(
                'user_login'   => $username,
                'user_pass'    => $password,
                'user_email'   => $email,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => '' !== $first_name || '' !== $last_name ? trim( $first_name . ' ' . $last_name ) : $mobile,
                'nickname'     => '' !== $first_name || '' !== $last_name ? trim( $first_name . ' ' . $last_name ) : $mobile,
                'role'         => $this->get_new_user_role(),
            )
        );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        update_user_meta( $user_id, 'limosms_mobile', $mobile );
        update_user_meta( $user_id, 'billing_phone', $mobile );

        if ( '' !== $first_name ) {
            update_user_meta( $user_id, 'billing_first_name', $first_name );
            update_user_meta( $user_id, 'shipping_first_name', $first_name );
        }

        if ( '' !== $last_name ) {
            update_user_meta( $user_id, 'billing_last_name', $last_name );
            update_user_meta( $user_id, 'shipping_last_name', $last_name );
        }

        if ( isset( $registration_fields['address'] ) && '' !== trim( (string) $registration_fields['address'] ) ) {
            $address = trim( (string) $registration_fields['address'] );
            update_user_meta( $user_id, 'billing_address_1', $address );
            update_user_meta( $user_id, 'shipping_address_1', $address );
        }

        if ( isset( $registration_fields['city'] ) && '' !== trim( (string) $registration_fields['city'] ) ) {
            $city = trim( (string) $registration_fields['city'] );
            update_user_meta( $user_id, 'billing_city', $city );
            update_user_meta( $user_id, 'shipping_city', $city );
        }

        if ( isset( $registration_fields['postcode'] ) && '' !== trim( (string) $registration_fields['postcode'] ) ) {
            $postcode = trim( (string) $registration_fields['postcode'] );
            update_user_meta( $user_id, 'billing_postcode', $postcode );
            update_user_meta( $user_id, 'shipping_postcode', $postcode );
        }

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
