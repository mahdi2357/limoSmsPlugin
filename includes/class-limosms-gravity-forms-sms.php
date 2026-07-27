<?php

if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Gravity_Forms_SMS
{
    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'register_gravity_forms_hooks'), 20);
        add_action('init', array($this, 'register_gravity_forms_hooks'), 20);
        add_action('wp_loaded', array($this, 'register_gravity_forms_hooks'), 20);
    }

    public function register_gravity_forms_hooks()
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        $registered = true;
        error_log('LimoSMS Gravity Forms SMS: registering submission hook');
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
        $form_id = (int)($form['id'] ?? 0);
        error_log('LimoSMS Gravity Forms SMS hook triggered for form ' . $form_id);

        // بررسی اینکه پیامک Gravity Forms فعال است یا نه
        if (!$this->is_gravity_forms_sms_enabled()) {
            error_log('LimoSMS Gravity Forms SMS: feature disabled');
            return;
        }

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

        $pattern_id = sanitize_text_field((string)($form_settings['otp_id'] ?? ''));

        if ('' === $pattern_id) {
            error_log('LimoSMS Gravity Forms SMS: no pattern selected for form ' . $form_id);
            return;
        }

        $pattern_map = $form_settings['pattern_map'] ?? array();

        if (!is_array($pattern_map)) {
            $pattern_map = array();
        }

        $entry_array = $this->normalize_entry($entry);

        error_log('LimoSMS Gravity Forms SMS: raw entry => ' . wp_json_encode($entry_array));

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

        $phone = $this->get_phone_number_from_entry($entry_array, $form);

        if (!$phone) {
            error_log('LimoSMS Gravity Forms SMS: no phone number found for form ' . $form_id . '; using configured admin phones instead');

            $admin_phones = get_option('limosms_gravity_forms_admin_phones', array());
            if (!empty($admin_phones) && is_array($admin_phones)) {
                foreach ($admin_phones as $admin_phone) {
                    $admin_phone = LimoSMS_Sender::normalize_mobile_number($admin_phone);
                    if ($admin_phone) {
                        $admin_result = LimoSMS_Sender::send_pattern_sms($admin_phone, $pattern_id, $values);
                        error_log('LimoSMS Gravity Forms SMS: admin fallback result => ' . wp_json_encode($admin_result));
                    }
                }
            }

            return;
        }

        $phone = LimoSMS_Sender::normalize_mobile_number($phone);

        if (!$phone) {
            return;
        }

        error_log('LimoSMS Gravity Forms SMS: sending pattern ' . $pattern_id . ' to ' . $phone . ' for form ' . $form_id);

        $result = LimoSMS_Sender::send_pattern_sms($phone, $pattern_id, $values);
        error_log('LimoSMS Gravity Forms SMS: recipient result => ' . wp_json_encode($result));

        $admin_phones = get_option('limosms_gravity_forms_admin_phones', array());
        if (!empty($admin_phones) && is_array($admin_phones)) {
            foreach ($admin_phones as $admin_phone) {
                $admin_phone = LimoSMS_Sender::normalize_mobile_number($admin_phone);
                if ($admin_phone) {
                    $admin_result = LimoSMS_Sender::send_pattern_sms($admin_phone, $pattern_id, $values);
                    error_log('LimoSMS Gravity Forms SMS: admin result => ' . wp_json_encode($admin_result));
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

            if (in_array($field_type, array('phone', 'telephone'), true) || strpos($field_label, 'phone') !== false || strpos($field_label, 'mobile') !== false || strpos($field_label, 'tel') !== false || strpos($field_label, 'cell') !== false || strpos($field_label, 'تلفن') !== false || strpos($field_label, 'موبایل') !== false) {
                foreach ($this->get_candidate_entry_keys((string) $field_id) as $candidate_key) {
                    if (isset($entry[$candidate_key]) && !empty($entry[$candidate_key])) {
                        $phone = $this->extract_phone_value($entry[$candidate_key]);
                        if ($phone) {
                            return $phone;
                        }
                    }
                }
            }
        }

        foreach ($entry as $key => $value) {
            if (is_string($key) && preg_match('/phone|mobile|tel|cell/i', $key)) {
                $phone = $this->extract_phone_value($value);
                if ($phone) {
                    return $phone;
                }
            }

            $phone = $this->extract_phone_value($value);
            if ($phone) {
                return $phone;
            }
        }

        return '';
    }

    private function get_candidate_entry_keys($field_id)
    {
        $keys = array();
        $field_id = (string) $field_id;

        if ($field_id !== '') {
            $keys[] = $field_id;
            $keys[] = 'input_' . $field_id;
            $keys[] = 'field_' . $field_id;
            $keys[] = 'input_' . ltrim($field_id, '0');
            $keys[] = '1.' . $field_id;
            $keys[] = 'input_' . $field_id . '_1';
        }

        return array_values(array_unique($keys));
    }

    private function normalize_persian_digits($value)
    {
        $persian = array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹');
        $arabic = array('٠','١','٢','٣','٤','٥','٦','٧','٨','٩');
        $replace = array('0','1','2','3','4','5','6','7','8','9');

        return str_replace($persian + $arabic, $replace + $replace, (string) $value);
    }

    private function extract_phone_value($value)
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $phone = $this->extract_phone_value($item);
                if ($phone) {
                    return $phone;
                }
            }
            return '';
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return $this->extract_phone_value((string) $value);
            }
            return '';
        }

        if (!is_scalar($value)) {
            return '';
        }

        $string_value = trim((string) $value);
        $normalized_value = $this->normalize_persian_digits($string_value);
        $digits = preg_replace('/[^0-9]/', '', $normalized_value);

        if (preg_match('/^09\d{9}$/', $digits)) {
            return $digits;
        }

        if (preg_match('/^9\d{9}$/', $digits)) {
            return '98' . $digits;
        }

        if (preg_match('/^98\d{10}$/', $digits)) {
            return $digits;
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
