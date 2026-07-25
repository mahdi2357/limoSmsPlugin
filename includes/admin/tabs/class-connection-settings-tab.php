<?php
if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Connection_Settings
{
    /**
     * @var LimoSMS_API
     */
    private $api;

    public function __construct()
    {
        $this->api = new LimoSMS_API();

        if ( method_exists( $this, 'register_hooks' ) ) {
            $this->register_hooks();
        }
    }

    public function register_hooks()
    {
        add_action('wp_ajax_limosms_save_connection_settings', array($this, 'save_connection_settings'));
        add_action('wp_ajax_limosms_check_connection', array($this, 'ajax_check_connection'));
    }

    public static function normalize_enabled_setting( $value, $default = 'yes' )
    {
        if ( is_bool( $value ) ) {
            return $value ? 'yes' : 'no';
        }

        if ( is_numeric( $value ) ) {
            return (int) $value > 0 ? 'yes' : 'no';
        }

        $clean_value = sanitize_text_field( (string) $value );
        $enabled_values = array( '1', 'yes', 'on', 'true' );

        return in_array( $clean_value, $enabled_values, true ) ? 'yes' : 'no';
    }

    public static function is_woocommerce_sms_enabled()
    {
        return self::normalize_enabled_setting( get_option( 'limosms_woocommerce_sms_enabled', 'yes' ), 'yes' ) === 'yes';
    }

    public static function is_digits_sms_enabled()
    {
        return self::normalize_enabled_setting( get_option( 'limosms_digits_sms_enabled', 'yes' ), 'yes' ) === 'yes';
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
        $woocommerce_enabled = isset($_POST['limosms_woocommerce_sms_enabled']) && '1' === sanitize_text_field(wp_unslash($_POST['limosms_woocommerce_sms_enabled'])) ? 'yes' : 'no';
        $digits_enabled = isset($_POST['limosms_digits_sms_enabled']) && '1' === sanitize_text_field(wp_unslash($_POST['limosms_digits_sms_enabled'])) ? 'yes' : 'no';

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
        update_option('limosms_woocommerce_sms_enabled', $woocommerce_enabled);
        update_option('limosms_digits_sms_enabled', $digits_enabled);

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

        $response = $this->api->get_current_credit();

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        if (!is_array($response)) {
            return array(
                'success' => false
            );
        }

        // کش برای ۵ دقیقه
        set_transient('limosms_connection_status', $response, 300);

        return $response;
    }

    /**
     * AJAX: بازگشت وضعیت اتصال فعلی (برای بروزرسانی سمت کلاینت)
     */
    public function ajax_check_connection()
    {
        check_ajax_referer('limosms_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'));
        }

        $status = $this->get_connection_status();

        $connected = !empty($status['success']);

        wp_send_json_success(array(
            'connected' => $connected,
            'status'    => $status,
        ));
    }
}
