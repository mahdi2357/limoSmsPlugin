<?php
if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Customer_SMS_Settings
{
    /**
     * @var LimoSMS_API
     */
    private $api;

    public function __construct()
    {
        $this->api = new LimoSMS_API();

        add_action('wp_ajax_limosms_customer_get_patterns', array($this, 'ajax_get_patterns'));
        add_action('wp_ajax_limosms_customer_get_pattern_detail', array($this, 'ajax_get_pattern_detail'));
    }

    public static function get_events_settings($event_key = '')
    {
        $options = get_option('limosms_customer_sms_events', array());

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
        check_ajax_referer('limosms_customer_sms_nonce', 'nonce');

        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'), 403);
        }

        $response = wp_remote_post('https://api.limosms.com/api/getpatterns', array(
            'timeout' => 20,
            'headers' => array(
                'ApiKey'       => get_option('limosms_api_key', ''),
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body' => wp_json_encode(array()),
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()), 500);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($code !== 200 || !is_array($json)) {
            wp_send_json_error(array('message' => 'پاسخ نامعتبر از API'), 500);
        }

        $items = array();
        if (isset($json['data']['data']) && is_array($json['data']['data'])) {
            $items = $json['data']['data'];
        } elseif (isset($json['data']) && is_array($json['data'])) {
            $items = $json['data'];
        } elseif (is_array($json)) {
            $items = $json;
        }

        $normalized = array();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $pattern_code = isset($item['patternCode']) ? (string) $item['patternCode'] : '';
            if ($pattern_code === '' && isset($item['id'])) {
                $pattern_code = (string) $item['id'];
            }
            if ($pattern_code === '') {
                continue;
            }

            $normalized[] = array(
                'patternCode' => $pattern_code,
                'patternName' => isset($item['patternName']) ? (string) $item['patternName'] : '',
                'message'     => isset($item['message']) ? (string) $item['message'] : '',
            );
        }

        wp_send_json_success($normalized);
    }


    public function ajax_get_pattern_detail()
    {
        check_ajax_referer('limosms_customer_sms_nonce', 'nonce');

        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'), 403);
        }

        $pattern_code = isset($_POST['pattern_code']) ? sanitize_text_field(wp_unslash($_POST['pattern_code'])) : '';

        if ($pattern_code === '') {
            wp_send_json_error(array('message' => 'کد پترن ارسال نشده'), 400);
        }

        $response = wp_remote_post(
            'https://api.limosms.com/api/getpattern',
            array(
                'timeout' => 20,
                'headers' => array(
                    'ApiKey'       => get_option('limosms_api_key', ''),
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'patterncode' => $pattern_code,
                )),
            )
        );


        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()), 500);
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        $items = array();
        if (isset($json['data']['data']) && is_array($json['data']['data'])) {
            $items = $json['data']['data'];
        } elseif (isset($json['data']) && is_array($json['data'])) {
            $items = $json['data'];
        }

        foreach ($items as $item) {
            $code = isset($item['patternCode']) ? (string) $item['patternCode'] : (isset($item['id']) ? (string) $item['id'] : '');

            if ($code === $pattern_code) {
                $message = isset($item['message']) ? (string) $item['message'] : '';

                wp_send_json_success(array(
                    'id'      => $code,
                    'message' => $message,
                ));
            }
        }

        wp_send_json_error(array('message' => 'پترن یافت نشد'), 404);
    }



}
