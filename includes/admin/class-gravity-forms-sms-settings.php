<?php
if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Gravity_Forms_SMS_Settings
{
    /**
     * @var LimoSMS_API
     */
    private $api;

    public function __construct()
    {
        $this->api = new LimoSMS_API();

        add_action('wp_ajax_limosms_gravity_forms_get_patterns', array($this, 'ajax_get_patterns'));
        add_action('wp_ajax_limosms_gravity_forms_get_pattern_detail', array($this, 'ajax_get_pattern_detail'));
        add_action('wp_ajax_limosms_gravity_forms_get_forms', array($this, 'ajax_get_forms'));
    }

    /**
     * دریافت تنظیمات یک فرم
     */
    public static function get_form_settings($form_id = '')
    {
        $options = get_option('limosms_gravity_forms_sms_settings', array());

        if (!is_array($options)) {
            $options = array();
        }

        if ($form_id === '') {
            return $options;
        }

        return isset($options[$form_id]) && is_array($options[$form_id])
            ? $options[$form_id]
            : array();
    }

    /**
     * AJAX: دریافت الگوهای پیامک
     */
    public function ajax_get_patterns()
    {
        check_ajax_referer('limosms_gravity_forms_sms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'), 403);
        }

        // بررسی API key
        $api_key = get_option('limosms_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API Key تنظیم نشده است. لطفا به تنظیمات اتصال بروید.'), 400);
        }

        $response = $this->api->get_patterns();

        if (is_wp_error($response)) {
            error_log('LimoSMS Gravity Forms Get Patterns Error: ' . $response->get_error_message());
            wp_send_json_error(array('message' => $response->get_error_message()), 500);
        }

        // بررسی اینکه response خالی نیست
        if (empty($response)) {
            wp_send_json_error(array('message' => 'هیچ الگویی از سرور دریافت نشد.'), 500);
        }

        wp_send_json_success($response);
    }

    /**
     * AJAX: دریافت جزئیات الگو
     */
    public function ajax_get_pattern_detail()
    {
        check_ajax_referer('limosms_gravity_forms_sms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'), 403);
        }

        $pattern_id = isset($_POST['pattern_id']) ? sanitize_text_field($_POST['pattern_id']) : '';

        if (empty($pattern_id)) {
            wp_send_json_error(array('message' => 'شناسه الگو وارد نشده است.'), 400);
        }

        $response = $this->api->get_pattern_detail($pattern_id);

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()), 500);
        }

        wp_send_json_success($response);
    }

    /**
     * AJAX: دریافت فرم های Gravity Forms
     */
    public function ajax_get_forms()
    {
        check_ajax_referer('limosms_gravity_forms_sms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'), 403);
        }

        if (!class_exists('GFFormsModel')) {
            wp_send_json_error(array('message' => 'Gravity Forms فعال نشده است.'), 400);
        }

        $forms = LimoSMS_Gravity_Forms_SMS_Events::get_forms();

        wp_send_json_success(array(
            'forms' => $forms,
            'tokens' => LimoSMS_Gravity_Forms_SMS_Events::get_all_form_tokens(),
        ));
    }
}
