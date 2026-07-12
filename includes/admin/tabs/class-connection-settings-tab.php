<?php
if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Connection_Settings
{
    public function __construct()
    {
        // ثبت اکشن AJAX ذخیره تنظیمات اتصال
        add_action('wp_ajax_limosms_save_connection_settings', array($this, 'save_connection_settings'));
    }

    /**
     * ذخیره تنظیمات اتصال
     */
    public function save_connection_settings()
    {
        // ۱. بررسی نانس (تغییر پارامتر دوم از nonce به security جهت انطباق با کلاینت)
        check_ajax_referer('limosms_admin_nonce', 'security');

        // ۲. بررسی دسترسی کاربر
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'شما دسترسی لازم را ندارید.',
            ));
        }

        // ۳. دریافت و پاکسازی ورودی‌ها (مطابق با نام فیلدهای فرم)
        $api_key = isset($_POST['limosms_api_key']) ? sanitize_text_field(wp_unslash($_POST['limosms_api_key'])) : '';
        $sender  = isset($_POST['limosms_sender_number']) ? sanitize_text_field(wp_unslash($_POST['limosms_sender_number'])) : '';

        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => 'وارد کردن کلید API الزامی است.',
            ));
        }

        // ۴. اعمال محدودیت طول روی API Key
        $api_key = substr($api_key, 0, 50);

        // ۵. ذخیره‌سازی تنظیمات
        update_option('limosms_api_key', $api_key);
        update_option('limosms_sender_number', $sender);

        // ۶. حذف کش یا ترنزینت‌های مرتبط
        delete_transient('limosms_connection_status');

        // ۷. پاسخ موفقیت‌آمیز
        wp_send_json_success(array(
            'message' => 'تنظیمات با موفقیت ذخیره شد.',
        ));
    }

    /**
     * دریافت وضعیت اتصال
     */
    public function get_connection_status()
    {
        $cached = get_transient('limosms_connection_status');

        if ($cached !== false) {
            return $cached;
        }

        $api_key = get_option('limosms_api_key', '');

        if (empty($api_key)) {
            return array(
                'success' => false
            );
        }

        $response = wp_remote_post(
            'https://api.limosms.com/api/getcurrentcredit',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'ApiKey'       => $api_key,
                ),
            )
        );

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!is_array($result)) {
            return array(
                'success' => false
            );
        }

        // کش برای ۵ دقیقه
        set_transient('limosms_connection_status', $result, 300);

        return $result;
    }
}
