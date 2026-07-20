<?php
if (!defined('ABSPATH')) {
    exit;
}
class LimoSMS_Admin_SMS_Settings
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
        $options = get_option('limosms_admin_sms_events', array());

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
        check_ajax_referer('limosms_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => 'شما دسترسی لازم را ندارید.',
            ],403);
        }

        $response = $this->api->get_patterns();

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => $response->get_error_message(),
            ], 500);
        }

        if (!is_array($response)) {
            wp_send_json_error([
                'message' => 'پاسخ نامعتبر از API',
            ]);
        }

        wp_send_json_success($response);
    }





    public function ajax_get_pattern_detail()
    {
        check_ajax_referer('limosms_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'شما دسترسی لازم را ندارید.',
            ), 403);
        }

        $pattern_code = isset($_POST['pattern_code'])
            ? sanitize_text_field(wp_unslash($_POST['pattern_code']))
            : '';

        if ($pattern_code === '') {
            wp_send_json_error(array(
                'message' => 'کد Pattern ارسال نشده است.',
            ), 400);
        }

        $response = $this->api->get_pattern_detail($pattern_code);

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => $response->get_error_message(),
            ), 500);
        }

        if (!is_array($response)) {
            wp_send_json_error(array(
                'message' => 'پاسخ دریافتی از سرویس لیمو پیامک نامعتبر است.',
            ), 500);
        }

        wp_send_json_success($response);
    }

}
