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
