<?php
if (!defined('ABSPATH')) {
    exit;
}
class LimoSMS_Admin_SMS_Settings
{
    public function __construct()
    {
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

        $api_key = get_option('limosms_api_key');

        if(empty($api_key)){
            wp_send_json_error([
                'message'=>'API Key تنظیم نشده است'
            ]);
        }

        $response = wp_remote_post(
            'https://api.limosms.com/api/getpatterns',
            [
                'timeout'=>10,
                'headers'=>[
                    'Content-Type'=>'application/json',
                    'ApiKey'=>$api_key
                ]
            ]
        );

        if(is_wp_error($response)){
            wp_send_json_error([
                'message'=>$response->get_error_message()
            ],500);
        }

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body,true);

        if(!is_array($data)){
            wp_send_json_error([
                'message'=>'پاسخ نامعتبر از API'
            ]);
        }

        wp_send_json_success($data);
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

        $api_key = get_option('limosms_api_key', '');

        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => 'API Key تنظیم نشده است.',
            ), 400);
        }

        $response = wp_remote_post(
            'https://api.limosms.com/api/getpattern',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                    'ApiKey'       => $api_key,
                ),
                'body' => wp_json_encode(array(
                    'patterncode' => $pattern_code,
                )),
            )
        );


        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => $response->get_error_message(),
            ), 500);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body        = wp_remote_retrieve_body($response);
        $data        = json_decode($body, true);

        if ($status_code < 200 || $status_code >= 300) {
            wp_send_json_error(array(
                'message' => 'خطا در دریافت جزئیات Pattern از سرویس لیمو پیامک.',
                'status'  => $status_code,
                'raw'     => $body,
            ), $status_code);
        }

        if (!is_array($data)) {
            wp_send_json_error(array(
                'message' => 'پاسخ دریافتی از سرویس لیمو پیامک نامعتبر است.',
                'raw'     => $body,
            ), 500);
        }

        wp_send_json_success($data);
    }

}
