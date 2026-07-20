<?php
if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Seller_SMS_Tab
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

        $saved = LimoSMS_Seller_SMS_Settings::save_events_settings($sms_events);

        if (! $saved) {
            wp_send_json_error(array('message' => 'خطا در ذخیره تنظیمات پیامک فروشنده.'), 500);
        }

        wp_send_json_success(array(
            'message' => 'تنظیمات پیامک فروشنده با موفقیت ذخیره شد',
        ));
    }
}
