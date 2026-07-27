<?php
if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Gravity_Forms_SMS_Tab
{
    private $api;

    public function __construct()
    {
        $this->api = new LimoSMS_API();
        add_action('wp_ajax_limosms_save_gravity_forms_sms_settings', array($this, 'save_gravity_forms_sms_settings'));
    }

    /**
     * ذخیره تنظیمات Gravity Forms SMS
     */
    public function save_gravity_forms_sms_settings()
    {
        check_ajax_referer('limosms_gravity_forms_sms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                array(
                    'message' => 'دسترسی غیرمجاز',
                ),
                403
            );
        }

        $sms_forms = isset($_POST['smsForms'])
            ? json_decode(wp_unslash($_POST['smsForms']), true)
            : array();

        if (!is_array($sms_forms)) {
            wp_send_json_error(
                array(
                    'message' => 'ساختار داده نامعتبر است.',
                ),
                400
            );
        }

        $admin_phones_input = isset($_POST['adminPhones']) ? wp_unslash($_POST['adminPhones']) : '';
        $admin_phone_list = is_array($admin_phones_input)
            ? $admin_phones_input
            : array_filter(array_map('trim', explode(',', (string) $admin_phones_input)));

        $clean_admin_phones = array();
        foreach ($admin_phone_list as $phone) {
            $phone = trim((string) $phone);
            if ($phone === '') {
                continue;
            }

            $phone = str_replace(array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'), array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'), $phone);
            $phone = str_replace(array('٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'), array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'), $phone);

            if (preg_match('/^09\d{9}$/', $phone)) {
                $clean_admin_phones[] = $phone;
            }
        }

        $clean_admin_phones = array_values(array_unique($clean_admin_phones));

        $forms_settings = array();

        foreach ($sms_forms as $form_id => $form_data) {
            $form_id = absint($form_id);

            if ($form_id <= 0 || !is_array($form_data)) {
                continue;
            }

            $enabled = (isset($form_data['enabled']) && 'yes' === $form_data['enabled']) ? 'yes' : 'no';
            $otp_id = isset($form_data['otp_id']) ? sanitize_text_field($form_data['otp_id']) : '';
            $pattern_selector = isset($form_data['pattern_selector']) ? sanitize_text_field($form_data['pattern_selector']) : '';
            $pattern_text = isset($form_data['pattern_text']) ? sanitize_textarea_field($form_data['pattern_text']) : '';
            $pattern_map = (isset($form_data['pattern_map']) && is_array($form_data['pattern_map']))
                ? $form_data['pattern_map']
                : array();

            if ('yes' === $enabled && '' === $otp_id) {
                wp_send_json_error(
                    array(
                        'message' => sprintf('برای فرم "%d" انتخاب Pattern الزامی است.', $form_id),
                    ),
                    400
                );
            }

            $clean_map = array();
            $required_variables = array();

            if (preg_match_all('/\{(\d+)\}/', $pattern_text, $matches)) {
                $required_variables = array_unique(array_map('absint', $matches[1]));
            }

            foreach ($pattern_map as $param => $token) {
                $param = absint($param);
                $token = sanitize_text_field($token);
                $token = trim($token, "{} \t\n\r\0\x0B");

                if ('' !== $token) {
                    $clean_map[$param] = $token;
                }
            }

            $has_variables = !empty($required_variables);
            if ('yes' === $enabled && '' !== $otp_id && $has_variables) {
                $missing = array_diff($required_variables, array_keys($clean_map));
                if (!empty($missing)) {
                    wp_send_json_error(
                        array(
                            'message' => sprintf('اتصال پارامترهای Pattern برای فرم "%d" الزامی است.', $form_id),
                        ),
                        400
                    );
                }
            }

            $forms_settings[$form_id] = array(
                'enabled' => $enabled,
                'otp_id' => $otp_id,
                'pattern_selector' => $pattern_selector,
                'pattern_text' => $pattern_text,
                'pattern_map' => $clean_map,
            );
        }

        update_option('limosms_gravity_forms_sms_settings', $forms_settings);
        update_option('limosms_gravity_forms_admin_phones', $clean_admin_phones);

        wp_send_json_success(
            array(
                'message' => 'تنظیمات Gravity Forms SMS با موفقیت ذخیره شد.',
            )
        );
    }
}
