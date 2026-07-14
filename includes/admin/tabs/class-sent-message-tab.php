<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LimoSMS_Sent_SMS_Tab {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_limosms_get_sent_sms', array( $this, 'ajax_get_sent_sms' ) );
    }

    public function render() {
        $template_path = LIMOSMS_PLUGIN_DIR . 'templates/admin-tabs/sent-sms.php';

        if ( file_exists( $template_path ) ) {
            include $template_path;
            return;
        }

        echo '<div class="notice notice-error"><p>فایل قالب پیام‌های ارسال‌شده پیدا نشد.</p></div>';
    }

    public function enqueue_assets( $hook_suffix ) {
        if ( ! $this->should_load_assets( $hook_suffix ) ) {
            return;
        }

        wp_enqueue_style(
            'limosms-sent-sms',
            LIMOSMS_PLUGIN_URL . 'assets/css/admin/pattern-management.css',
            array(),
            defined( 'LIMOSMS_VERSION' ) ? LIMOSMS_VERSION : '1.0.0'
        );

        wp_enqueue_script(
            'limosms-sent-sms',
            LIMOSMS_PLUGIN_URL . 'assets/js/admin/sent-sms.js',
            array( 'jquery' ),
            defined( 'LIMOSMS_VERSION' ) ? LIMOSMS_VERSION : '1.0.0',
            true
        );

        wp_localize_script(
            'limosms-sent-sms',
            'limosmsSentSMS',
            array(
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'limosms_admin_nonce' ),
                'loadingText'   => __( 'در حال دریافت پیام‌ها...', 'limosms' ),
                'loadErrorText' => __( 'دریافت لیست پیام‌ها ناموفق بود.', 'limosms' ),
                'emptyText'     => __( 'پیامی یافت نشد.', 'limosms' ),
            )
        );
    }

    public function ajax_get_sent_sms() {
        check_ajax_referer( 'limosms_admin_nonce', 'nonce' );

        $api_key = trim( (string) get_option( 'limosms_api_key', '' ) );
        if ( '' === $api_key ) {
            $api_key = 'e137df4a-2ea0-40b9-904c-169966d4849d';
        }

        $response = wp_remote_post(
            'https://api.limosms.com/api/getsinglesms',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8',
                    'ApiKey'       => $api_key,
                ),
                'body' => wp_json_encode( array() ),
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => $response->get_error_message(),
                )
            );
        }

        wp_send_json_success(
            array(
                'status' => wp_remote_retrieve_response_code( $response ),
                'body'   => json_decode( wp_remote_retrieve_body( $response ), true ),
                'raw'    => wp_remote_retrieve_body( $response ),
            )
        );
    }


    private function normalize_response( $data ) {
        if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
            return $data['data'];
        }

        if ( $this->is_list( $data ) ) {
            return $data;
        }

        return array();
    }

    private function is_list( $data ) {
        if ( ! is_array( $data ) || empty( $data ) ) {
            return false;
        }

        return array_keys( $data ) === range( 0, count( $data ) - 1 );
    }

    private function should_load_assets( $hook_suffix ) {
        if ( ! is_admin() ) {
            return false;
        }

        if ( isset( $_GET['page'] ) ) {
            $page = sanitize_key( wp_unslash( $_GET['page'] ) );

            if ( 'limosms' === $page || 'limosms-settings' === $page ) {
                return true;
            }
        }

        return false;
    }
}
