<?php
if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Seller_SMS_Settings
{
    public function __construct()
    {
        add_action('wp_ajax_limosms_get_patterns', array($this, 'ajax_get_patterns'));
        add_action('wp_ajax_limosms_get_pattern_detail', array($this, 'ajax_get_pattern_detail'));
        // Seller SMS settings save
        add_action(
            'wp_ajax_limosms_save_seller_sms_settings',
            array( $this, 'save_seller_sms_settings_ajax' )
        );
    }

    public static function get_events_settings($event_key = '')
    {
        $options = get_option('limosms_seller_sms_events', array());

        if (!is_array($options)) {
            $options = array();
        }

        if ($event_key === '') {
            return $options;
        }

        return isset($options[$event_key]) && is_array($options[$event_key])
            ? $options[$event_key]
            : array();
    }

    public function ajax_get_patterns()
    {
        // چون admin هم همین action را دارد، این متد را حذف هم می‌توان کرد
        // و فقط از Admin_SMS_Settings استفاده کرد.
        check_ajax_referer('limosms_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'شما دسترسی لازم را ندارید.'), 403);
        }

        $api_key = get_option('limosms_api_key');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API Key تنظیم نشده است'));
        }

        $response = wp_remote_post('https://api.limosms.com/api/getpatterns', array(
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json',
                'ApiKey' => $api_key,
            ),
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()), 500);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($data)) {
            wp_send_json_error(array('message' => 'پاسخ نامعتبر از API'));
        }

        wp_send_json_success($data);
    }

    public function ajax_get_pattern_detail()
    {
        // اختیاری: مشابه admin
        wp_send_json_error(array('message' => 'نیازی نیست در seller استفاده شود.'), 400);
    }
    public function save_seller_sms_settings_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'دسترسی غیرمجاز.', 'limosms' ),
                ),
                403
            );
        }

        check_ajax_referer( 'limosms_ajax_nonce', 'nonce' );

        if ( ! class_exists( 'LimoSMS_Seller_SMS_Settings' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'کلاس تنظیمات فروشنده یافت نشد.', 'limosms' ),
                )
            );
        }

        $raw      = isset( $_POST['smsEvents'] ) ? wp_unslash( $_POST['smsEvents'] ) : '';
        $settings = json_decode( $raw, true );

        if ( ! is_array( $settings ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'داده تنظیمات نامعتبر است.', 'limosms' ),
                )
            );
        }

        $saved = LimoSMS_Seller_SMS_Settings::save_events_settings( $settings );

        if ( $saved ) {
            wp_send_json_success(
                array(
                    'message' => __( 'تنظیمات پیامک فروشنده ذخیره شد.', 'limosms' ),
                )
            );
        }

        wp_send_json_error(
            array(
                'message' => __( 'خطا در ذخیره تنظیمات پیامک فروشنده.', 'limosms' ),
            )
        );
    }

}
