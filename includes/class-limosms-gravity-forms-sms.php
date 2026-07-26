<?php

if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Gravity_Forms_SMS
{
    public function __construct()
    {
        // Gravity Forms form submission hook
        add_action('gform_after_submission', array($this, 'send_gravity_forms_sms'), 10, 2);
    }

    /**
     * ارسال پیامک پس از ارسال فرم
     *
     * @param array $entry
     * @param array $form
     */
    public function send_gravity_forms_sms($entry, $form)
    {
        // بررسی اینکه پیامک Gravity Forms فعال است یا نه
        if (!$this->is_gravity_forms_sms_enabled()) {
            return;
        }

        $form_id = (int)($form['id'] ?? 0);
        if ($form_id > 0 && class_exists('GFAPI')) {
            $gf_form = GFAPI::get_form($form_id);
            if (is_array($gf_form) && !empty($gf_form)) {
                $form = $gf_form;
            }
        }

        $settings = get_option('limosms_gravity_forms_sms_settings', array());

        if (empty($settings[$form_id]) || !is_array($settings[$form_id])) {
            return;
        }

        $form_settings = $settings[$form_id];

        if (($form_settings['enabled'] ?? 'no') !== 'yes') {
            return;
        }

        $pattern_id = absint($form_settings['otp_id'] ?? 0);

        if (!$pattern_id) {
            return;
        }

        $pattern_map = $form_settings['pattern_map'] ?? array();

        if (!is_array($pattern_map)) {
            $pattern_map = array();
        }

        $entry_array = $this->normalize_entry($entry);

        // دریافت شماره تلفن
        $phone = $this->get_phone_number_from_entry($entry_array, $form);

        if (!$phone) {
            return;
        }

        $phone = LimoSMS_Sender::normalize_mobile_number($phone);

        if (!$phone) {
            return;
        }

        // دریافت داده های فرم
        $data = $this->get_form_sms_data_source($entry_array, $form);

        $values = array();

        foreach ($pattern_map as $index => $token) {
            $token = trim((string)$token, "{} \t\n\r\0\x0B");
            $value = $data[$token] ?? '-';

            if ($value === '') {
                $value = '-';
            }

            $values[(int)$index] = (string)$value;
        }

        // ارسال پیامک
        LimoSMS_Sender::send_pattern_sms($phone, $pattern_id, $values);

        // ارسال پیامک به ادمین‌های Gravity Forms
        $admin_phones = get_option('limosms_gravity_forms_admin_phones', array());
        if (!empty($admin_phones) && is_array($admin_phones)) {
            foreach ($admin_phones as $admin_phone) {
                $admin_phone = LimoSMS_Sender::normalize_mobile_number($admin_phone);
                if ($admin_phone) {
                    LimoSMS_Sender::send_pattern_sms($admin_phone, $pattern_id, $values);
                }
            }
        }
    }

    /**
     * بررسی فعال بودن پیامک Gravity Forms
     */
    private function is_gravity_forms_sms_enabled()
    {
        $gravity_forms_enabled = get_option('limosms_gravity_forms_sms_enabled', 'yes');
        return ('yes' === $gravity_forms_enabled);
    }

    /**
     * دریافت شماره تلفن از entry
     */
    private function get_phone_number_from_entry($entry, $form)
    {
        $fields = $this->normalize_form_fields($form);

        foreach ($fields as $field) {
            $field_type = isset($field['type']) ? strtolower((string) $field['type']) : '';
            $field_id = $field['id'] ?? '';
            $field_label = isset($field['label']) ? strtolower((string) $field['label']) : '';

            if (in_array($field_type, array('phone', 'telephone'), true) || strpos($field_label, 'phone') !== false || strpos($field_label, 'mobile') !== false || strpos($field_label, 'تلفن') !== false || strpos($field_label, 'موبایل') !== false) {
                $phone = rgar($entry, $field_id);
                if (!empty($phone)) {
                    return $phone;
                }
            }
        }

        foreach ($entry as $key => $value) {
            if (is_scalar($value) && preg_match('/^(\+98|98|0)?9\d{9}$/', (string) $value)) {
                return (string) $value;
            }
        }

        return '';
    }

    /**
     * دریافت داده های فرم برای جایگزینی پارامترها
     */
    private function get_form_sms_data_source($entry, $form)
    {
        $data = array();
        $fields = $this->normalize_form_fields($form);

        foreach ($fields as $field) {
            $field_id = $field['id'] ?? '';
            $field_label = $field['label'] ?? '';

            if (empty($field_id)) {
                continue;
            }

            $value = rgar($entry, $field_id);

            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $data['field_' . $field_id] = (string)$value;
            $data['field_' . $field_label] = (string)$value;
        }

        // اضافه کردن توکن های عمومی
        $data['submission_date'] = date('Y/m/d H:i');

        $user_id = (int)rgar($entry, 'created_by');
        if ($user_id > 0) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                $data['user_email'] = $user->user_email;
                $data['user_name'] = $user->display_name;
            }
        } else {
            $data['user_email'] = '';
            $data['user_name'] = '';
        }

        return $data;
    }

    private function normalize_entry($entry)
    {
        if (is_object($entry)) {
            $entry = (array) $entry;
        }

        if (!is_array($entry)) {
            return array();
        }

        return $entry;
    }

    private function normalize_form_fields($form)
    {
        $fields = array();

        if (is_object($form)) {
            $form = (array) $form;
        }

        if (empty($form['fields']) || !is_iterable($form['fields'])) {
            return $fields;
        }

        foreach ($form['fields'] as $field) {
            if (is_object($field)) {
                $field = (array) $field;
            }

            if (is_array($field)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }
}
