<?php

if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Admin_SMS
{
    public function __construct()
    {
        add_action(
            'wp_ajax_limosms_save_admin_sms_settings',
            array($this, 'save_admin_sms_settings')
        );
    }

    /**
     * ذخیره‌سازی داینامیک و امن تنظیمات پیامک ادمین بر اساس رویدادها
     */
    public function save_admin_sms_settings()
    {
        check_ajax_referer('limosms_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'دسترسی غیرمجاز'
            ), 403);
        }

        $sms_events = isset($_POST['smsEvents'])
            ? json_decode(wp_unslash($_POST['smsEvents']), true)
            : array();

        if (!is_array($sms_events)) {
            wp_send_json_error(array(
                'message' => 'ساختار داده تنظیمات نامعتبر است.',
            ), 400);
        }

        $events_settings = array();

        foreach ($sms_events as $event_key => $event_data) {

            $event_key = sanitize_key($event_key);

            if ($event_key === '' || !is_array($event_data)) {
                continue;
            }

            $enabled = (
                isset($event_data['enabled']) &&
                $event_data['enabled'] === 'yes'
            ) ? 'yes' : 'no';

            $otp_id = isset($event_data['otp_id'])
                ? sanitize_text_field($event_data['otp_id'])
                : '';

            $pattern_text = isset($event_data['pattern_text'])
                ? sanitize_textarea_field($event_data['pattern_text'])
                : '';

            $pattern_map = (
                isset($event_data['pattern_map']) &&
                is_array($event_data['pattern_map'])
            ) ? $event_data['pattern_map'] : array();

            /*
             * ==========================================
             * اعتبارسنجی تنظیمات رویداد
             * ==========================================
             */

            // اگر رویداد فعال است، انتخاب پترن اجباری است
            if ($enabled === 'yes' && empty($otp_id)) {

                wp_send_json_error(array(
                    'message' => sprintf(
                        'برای رویداد "%s" انتخاب Pattern الزامی است.',
                        $event_key
                    )
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

            $has_variables = preg_match('/\{(\d+)\}/', $pattern_text) === 1;

            // اگر رویداد فعال و پترن انتخاب شده باشد
            // فقط زمانی که Pattern متغیر دارد، اتصال پارامترها اجباری است
            if (
                $enabled === 'yes' &&
                !empty($otp_id) &&
                $has_variables
            ) {

                if (empty($clean_map)) {

                    wp_send_json_error(array(
                        'message' => sprintf(
                            'اتصال پارامترهای Pattern برای رویداد "%s" الزامی است.',
                            $event_key
                        )
                    ), 400);
                }

                // بررسی وجود مقدار خالی
                foreach ($clean_map as $token) {

                    if (empty($token)) {

                        wp_send_json_error(array(
                            'message' => sprintf(
                                'تمام پارامترهای Pattern برای رویداد "%s" باید تکمیل شوند.',
                                $event_key
                            )
                        ), 400);
                    }
                }
            }

            $events_settings[$event_key] = array(
                'enabled'      => $enabled,
                'otp_id'       => $otp_id,
                'pattern_text' => $pattern_text,
                'pattern_map'  => $clean_map,
            );
        }

        /*
         * ==========================================
         * ذخیره شماره موبایل مدیران
         * ==========================================
         */

        $admin_phones_raw = isset($_POST['admin_phones'])
            ? sanitize_text_field(wp_unslash($_POST['admin_phones']))
            : '';

        // تبدیل اعداد فارسی/عربی به انگلیسی
        $persian_digits = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
        $arabic_digits  = array('٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩');
        $english_digits = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');

        $admin_phones_raw = str_replace(
            $persian_digits,
            $english_digits,
            $admin_phones_raw
        );

        $admin_phones_raw = str_replace(
            $arabic_digits,
            $english_digits,
            $admin_phones_raw
        );

        // تبدیل به آرایه
        $phones_array = array_filter(
            array_map('trim', explode(',', $admin_phones_raw))
        );

        $valid_phones = array();

        foreach ($phones_array as $phone) {

            // حذف کاراکترهای اضافی
            $phone = preg_replace('/[^0-9]/', '', $phone);

            // اعتبارسنجی شماره موبایل ایران
            if (preg_match('/^09[0-9]{9}$/', $phone)) {
                $valid_phones[] = $phone;
            }
        }

        // حذف شماره‌های تکراری
        $valid_phones = array_values(array_unique($valid_phones));

        if (count($valid_phones) > 10) {
            wp_send_json_error(
                array(
                    'message' => 'حداکثر ۱۰ شماره موبایل برای پیامک مدیران قابل ذخیره است.',
                ),
                400
            );
        }

        /*
         * ==========================================
         * ذخیره نهایی
         * ==========================================
         */

        update_option(
            'limosms_admin_phones',
            $valid_phones
        );

        update_option(
            'limosms_admin_sms_events',
            $events_settings
        );

        wp_send_json_success(array(
            'message' => 'تنظیمات با موفقیت ذخیره شد'
        ));
    }
}
