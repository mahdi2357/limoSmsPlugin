<?php
if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Seller_SMS
{
    public function __construct()
    {
        add_action('wp_ajax_limosms_save_seller_sms_settings', array($this, 'save_seller_sms_settings'));
    }

    public function save_seller_sms_settings()
    {
        check_ajax_referer('limosms_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'), 403);
        }

        $sms_events = isset($_POST['smsEvents'])
            ? json_decode(wp_unslash($_POST['smsEvents']), true)
            : array();

        if (!is_array($sms_events)) {
            wp_send_json_error(array('message' => 'ساختار داده تنظیمات نامعتبر است.'), 400);
        }

        $events_settings = array();

        foreach ($sms_events as $event_key => $event_data) {
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

            if ($enabled === 'yes' && empty($otp_id)) {
                wp_send_json_error(array(
                    'message' => sprintf('برای رویداد "%s" انتخاب Pattern الزامی است.', $event_key),
                ), 400);
            }

            $clean_map = array();
            foreach ($pattern_map as $param => $token) {
                $param = absint($param);
                $token = sanitize_text_field($token);
                if ($token !== '') {
                    $clean_map[$param] = $token;
                }
            }

            if ($enabled === 'yes' && !empty($otp_id) && empty($clean_map)) {
                wp_send_json_error(array(
                    'message' => sprintf('اتصال پارامترهای Pattern برای رویداد "%s" الزامی است.', $event_key),
                ), 400);
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

        wp_send_json_success(array(
            'message' => 'تنظیمات پیامک فروشنده با موفقیت ذخیره شد',
        ));
    }
}
