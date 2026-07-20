<?php
if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Seller_SMS_Settings
{
    /**
     * @var LimoSMS_API
     */
    private $api;

    public function __construct()
    {
        $this->api = new LimoSMS_API();

        add_action('wp_ajax_limosms_get_patterns', array($this, 'ajax_get_patterns'));
        add_action('wp_ajax_limosms_get_pattern_detail', array($this, 'ajax_get_pattern_detail'));
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

    public static function save_events_settings(array $settings)
    {
        if (!is_array($settings)) {
            return false;
        }

        $events_settings = array();

        foreach ($settings as $event_key => $event_data) {
            $event_key = sanitize_key($event_key);

            if ($event_key === '' || !is_array($event_data)) {
                continue;
            }

            $enabled = (isset($event_data['enabled']) && $event_data['enabled'] === 'yes') ? 'yes' : 'no';
            $otp_id = isset($event_data['otp_id']) ? sanitize_text_field($event_data['otp_id']) : '';
            $pattern_text = isset($event_data['pattern_text']) ? sanitize_textarea_field($event_data['pattern_text']) : '';
            $title = isset($event_data['title']) ? sanitize_text_field($event_data['title']) : '';

            $pattern_map = (isset($event_data['pattern_map']) && is_array($event_data['pattern_map']))
                ? $event_data['pattern_map']
                : array();

            $clean_map = array();
            foreach ($pattern_map as $param => $token) {
                $param = absint($param);
                $token = sanitize_text_field($token);
                if ($token !== '') {
                    $clean_map[$param] = $token;
                }
            }

            if ($enabled === 'yes' && !empty($pattern_text) && preg_match('/\{(\d+)\}/', $pattern_text) === 1 && empty($clean_map)) {
                // If a pattern has variables but no tokens are mapped, do not save this event.
                continue;
            }

            $events_settings[$event_key] = array(
                'enabled'      => $enabled,
                'otp_id'       => $otp_id,
                'title'        => $title,
                'pattern_text' => $pattern_text,
                'pattern_map'  => $clean_map,
            );
        }

        update_option('limosms_seller_sms_events', $events_settings);

        return true;
    }

    public function ajax_get_patterns()
    {
        // چون admin هم همین action را دارد، این متد را حذف هم می‌توان کرد
        // و فقط از Admin_SMS_Settings استفاده کرد.
        check_ajax_referer('limosms_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'شما دسترسی لازم را ندارید.'), 403);
        }

        $response = $this->api->get_patterns();

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()), 500);
        }

        if (!is_array($response)) {
            wp_send_json_error(array('message' => 'پاسخ نامعتبر از API'));
        }

        wp_send_json_success($response);
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
