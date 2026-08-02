<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LimoSMS_Login_Register_Tab {

    public function register_hooks() {
        add_action( 'wp_ajax_limosms_save_login_register_settings', array( $this, 'ajax_save_settings' ) );
    }

    public function ajax_save_settings() {
        check_ajax_referer( 'limosms_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'دسترسی غیرمجاز.', 'limosms' ),
                ),
                403
            );
        }

        $settings = get_option( 'limoo_sms_settings', array() );

        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $settings['login_register_otp_enabled'] = (
            isset( $_POST['login_register_otp_enabled'] ) &&
            '1' === sanitize_text_field( wp_unslash( $_POST['login_register_otp_enabled'] ) )
        ) ? '1' : '0';

        $settings['login_register_otp_redirect_url'] = esc_url_raw( wp_unslash( $_POST['login_register_otp_redirect_url'] ?? '' ) );
        $settings['login_register_otp_role'] = sanitize_text_field( wp_unslash( $_POST['login_register_otp_role'] ?? get_option( 'default_role', 'subscriber' ) ) );
        $settings['login_register_otp_expiry_minutes'] = max( 1, absint( $_POST['login_register_otp_expiry_minutes'] ?? 10 ) );
        $settings['login_register_otp_resend_seconds'] = max( 10, absint( $_POST['login_register_otp_resend_seconds'] ?? 60 ) );
        $settings['login_register_otp_max_attempts'] = max( 1, absint( $_POST['login_register_otp_max_attempts'] ?? 5 ) );
        $settings['login_register_otp_lockout_minutes'] = max( 1, absint( $_POST['login_register_otp_lockout_minutes'] ?? 15 ) );

        $settings['login_register_otp_captcha_enabled'] = (
            isset( $_POST['login_register_otp_captcha_enabled'] ) &&
            '1' === sanitize_text_field( wp_unslash( $_POST['login_register_otp_captcha_enabled'] ) )
        ) ? '1' : '0';

        $settings['login_register_disable_default_auth'] = (
            isset( $_POST['login_register_disable_default_auth'] ) &&
            '1' === sanitize_text_field( wp_unslash( $_POST['login_register_disable_default_auth'] ) )
        ) ? '1' : '0';

        $registration_fields = array();
        if ( isset( $_POST['login_register_otp_registration_fields'] ) && is_array( $_POST['login_register_otp_registration_fields'] ) ) {
            foreach ( wp_unslash( $_POST['login_register_otp_registration_fields'] ) as $field_key => $field_settings ) {
                $field_key = sanitize_key( (string) $field_key );
                if ( '' === $field_key ) {
                    continue;
                }

                $is_enabled = isset( $field_settings['enabled'] ) && '1' === sanitize_text_field( (string) $field_settings['enabled'] );
                $registration_fields[ $field_key ] = array(
                    'enabled' => $is_enabled ? '1' : '0',
                    'required' => $is_enabled && isset( $field_settings['required'] ) && '1' === sanitize_text_field( (string) $field_settings['required'] ) ? '1' : '0',
                );
            }
        }
        $settings['login_register_otp_registration_fields'] = $registration_fields;

        $form_align = sanitize_text_field( wp_unslash( $_POST['login_register_otp_form_align'] ?? 'center' ) );
        $settings['login_register_otp_form_align'] = in_array( $form_align, array( 'left', 'center', 'right' ), true ) ? $form_align : 'center';

        $form_direction = sanitize_text_field( wp_unslash( $_POST['login_register_otp_form_direction'] ?? 'rtl' ) );
        $settings['login_register_otp_form_direction'] = in_array( $form_direction, array( 'rtl', 'ltr' ), true ) ? $form_direction : 'rtl';

        $font_family = sanitize_text_field( wp_unslash( $_POST['login_register_otp_font_family'] ?? 'Vazirmatn, Tahoma, Arial, sans-serif' ) );
        $allowed_font_families = array(
            'Vazirmatn, Tahoma, Arial, sans-serif',
            'IRANSans, Tahoma, Arial, sans-serif',
            'Tahoma, Arial, sans-serif',
            'Arial, sans-serif',
            'Segoe UI, Tahoma, sans-serif',
            'Yekan, Tahoma, Arial, sans-serif',
            'Times New Roman, serif',
        );
        $settings['login_register_otp_font_family'] = in_array( $font_family, $allowed_font_families, true ) ? $font_family : 'Vazirmatn, Tahoma, Arial, sans-serif';

        $allowed_country_codes = array();
        if ( isset( $_POST['login_register_otp_allowed_country_codes'] ) && is_array( wp_unslash( $_POST['login_register_otp_allowed_country_codes'] ) ) ) {
            foreach ( wp_unslash( $_POST['login_register_otp_allowed_country_codes'] ) as $country_code ) {
                $country_code = sanitize_text_field( (string) $country_code );
                $country_code = preg_replace( '/[^0-9]/', '', $country_code );
                if ( '' !== $country_code ) {
                    $allowed_country_codes[] = $country_code;
                }
            }
        }
        if ( empty( $allowed_country_codes ) ) {
            $allowed_country_codes = array( '98' );
        }
        $settings['login_register_otp_allowed_country_codes'] = array_values( array_unique( $allowed_country_codes ) );

        $settings['login_register_otp_logo_url'] = esc_url_raw( wp_unslash( $_POST['login_register_otp_logo_url'] ?? '' ) );
        $settings['login_register_otp_background_image_url'] = esc_url_raw( wp_unslash( $_POST['login_register_otp_background_image_url'] ?? '' ) );
        $settings['login_register_otp_background_color'] = sanitize_hex_color( wp_unslash( $_POST['login_register_otp_background_color'] ?? '#ffffff' ) );
        $settings['login_register_otp_form_background_color'] = sanitize_hex_color( wp_unslash( $_POST['login_register_otp_form_background_color'] ?? '#ffffff' ) );
        $settings['login_register_otp_accent_color'] = sanitize_hex_color( wp_unslash( $_POST['login_register_otp_accent_color'] ?? '#2563eb' ) );
        $settings['login_register_otp_custom_css'] = sanitize_textarea_field( wp_unslash( $_POST['login_register_otp_custom_css'] ?? '' ) );

        $updated = update_option( 'limoo_sms_settings', $settings );

        wp_send_json_success(
            array(
                'message' => $updated
                    ? __( 'تنظیمات با موفقیت ذخیره شد.', 'limosms' )
                    : __( 'تنظیمات بدون تغییر باقی ماند.', 'limosms' ),
                'settings' => $settings,
            )
        );
    }
}
