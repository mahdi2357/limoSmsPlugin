<?php

if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Gravity_Forms_SMS
{
    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'register_gravity_forms_hooks'), 20);
    }

    public function register_gravity_forms_hooks()
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        $registered = true;
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

        // بررسی اینکه پیامک Gravity Forms فعال است یا نه
        if (!$this->is_gravity_forms_sms_enabled()) {
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
            return;
        }

        $pattern_map = $form_settings['pattern_map'] ?? array();

        if (!is_array($pattern_map)) {
            $pattern_map = array();
        }

        $entry_array = $this->normalize_entry($entry);

        $data = $this->get_form_sms_data_source($entry_array, $form);

        $values = array();

        foreach ($pattern_map as $index => $token) {
            $token = trim((string)$token, "{} \t\n\r\0\x0B");
            $value = $this->resolve_token_value($token, $entry_array, $form, $data);

            if ($value === '') {
                $value = '-';
            }

            $values[(int)$index] = (string)$value;
        }

        $phone = $this->get_phone_number_from_entry($entry_array, $form);

        if (!$phone) {
            $admin_phones = get_option('limosms_gravity_forms_admin_phones', array());
            if (!empty($admin_phones) && is_array($admin_phones)) {
                foreach ($admin_phones as $admin_phone) {
                    $admin_phone = LimoSMS_Sender::normalize_mobile_number($admin_phone);
                    if ($admin_phone) {
                        $admin_result = LimoSMS_Sender::send_pattern_sms($admin_phone, $pattern_id, $values);
                    }
                }
            }

            return;
        }

        $phone = LimoSMS_Sender::normalize_mobile_number($phone);

        if (!$phone) {
            return;
        }

        $result = LimoSMS_Sender::send_pattern_sms($phone, $pattern_id, $values);

        $admin_phones = get_option('limosms_gravity_forms_admin_phones', array());
        if (!empty($admin_phones) && is_array($admin_phones)) {
            foreach ($admin_phones as $admin_phone) {
                $admin_phone = LimoSMS_Sender::normalize_mobile_number($admin_phone);
                if ($admin_phone) {
                    $admin_result = LimoSMS_Sender::send_pattern_sms($admin_phone, $pattern_id, $values);
                }
            }
        }
    }

    /**
     * بررسی فعال بودن پیامک Gravity Forms
     */
    private function is_gravity_forms_sms_enabled()
    {
        if (class_exists('LimoSMS_Connection_Settings')) {
            return LimoSMS_Connection_Settings::is_gravity_forms_sms_enabled();
        }

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
            $this->populate_data_source_from_field($data, $entry, $field);
        }

        $data['submission_date'] = date('Y/m/d H:i');

        $user_id = (int)$this->get_entry_value($entry, 'created_by');
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

    private function resolve_token_value($token, $entry, $form, $data)
    {
        if ($token === '') {
            return '';
        }

        $base_token = trim((string) $token, "{} \t\n\r\0\x0B");
        $candidates = array();
        $candidates[] = $base_token;
        $candidates[] = 'field_' . $base_token;
        $candidates[] = 'field_' . trim($base_token, '{}');
        $candidates[] = trim($base_token, '{}');
        $candidates[] = str_replace('field_', '', $base_token);
        $candidates[] = $this->normalize_token_label($base_token);
        $candidates[] = $this->normalize_token_label('field_' . $base_token);
        $candidates[] = strtolower(str_replace(array(' ', '_', '-'), '', $base_token));
        $candidates[] = strtolower(str_replace(array(' ', '_', '-'), '', 'field_' . $base_token));
        $candidates[] = 'input_' . $base_token;
        $candidates[] = 'input_' . str_replace('field_', '', $base_token);

        $fields = $this->normalize_form_fields($form);
        foreach ($fields as $field) {
            $field_id = (string)($field['id'] ?? '');
            $field_label = (string)($field['label'] ?? '');

            $this->add_resolver_candidate($candidates, $field_id);
            $this->add_resolver_candidate($candidates, 'field_' . $field_id);
            $this->add_resolver_candidate($candidates, 'input_' . $field_id);
            $this->add_resolver_candidate($candidates, $field_label);
            $this->add_resolver_candidate($candidates, 'field_' . $field_label);
            $this->add_resolver_candidate($candidates, $this->normalize_token_label($field_label));
            $this->add_resolver_candidate($candidates, strtolower(str_replace(array(' ', '_', '-'), '', $field_label)));

            if (!empty($field['inputs']) && is_array($field['inputs'])) {
                foreach ($field['inputs'] as $input) {
                    $input_id = (string)($input['id'] ?? '');
                    $input_label = (string)($input['label'] ?? '');

                    $this->add_resolver_candidate($candidates, $input_id);
                    $this->add_resolver_candidate($candidates, 'field_' . $input_id);
                    $this->add_resolver_candidate($candidates, 'input_' . $input_id);
                    $this->add_resolver_candidate($candidates, $input_label);
                    $this->add_resolver_candidate($candidates, 'field_' . $input_label);
                    $this->add_resolver_candidate($candidates, $this->normalize_token_label($input_label));
                    $this->add_resolver_candidate($candidates, strtolower(str_replace(array(' ', '_', '-'), '', $input_label)));
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (isset($data[$candidate]) && $data[$candidate] !== '') {
                return $data[$candidate];
            }
        }

        foreach ($candidates as $candidate) {
            if (isset($entry[$candidate]) && $entry[$candidate] !== '') {
                return $this->stringify_value($entry[$candidate]);
            }
        }

        foreach ($candidates as $candidate) {
            $value = $this->get_entry_value($entry, $candidate);
            if ($value !== '' && $value !== null) {
                return $this->stringify_value($value);
            }
        }

        foreach ($entry as $entry_key => $entry_value) {
            $normalized_entry_key = strtolower(str_replace(array(' ', '_', '-'), '', (string) $entry_key));
            $normalized_candidate = strtolower(str_replace(array(' ', '_', '-'), '', (string) $base_token));
            if ($normalized_entry_key === $normalized_candidate || $normalized_entry_key === $normalized_candidate . 'value' || $normalized_entry_key === 'field' . $normalized_candidate) {
                return $this->stringify_value($entry_value);
            }
        }

        return '';
    }

    private function get_entry_value($entry, $key)
    {
        if (!is_array($entry)) {
            return '';
        }

        $candidates = array();
        $candidates[] = (string) $key;
        $candidates[] = 'input_' . (string) $key;
        $candidates[] = 'field_' . (string) $key;
        $candidates[] = '1.' . (string) $key;
        $candidates[] = 'input_' . ltrim((string) $key, '0');
        $candidates[] = strtolower(str_replace(array(' ', '_', '-'), '', (string) $key));

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $entry)) {
                return $entry[$candidate];
            }
        }

        foreach ($entry as $entry_key => $entry_value) {
            $normalized_entry_key = strtolower(str_replace(array(' ', '_', '-'), '', (string) $entry_key));
            $normalized_key = strtolower(str_replace(array(' ', '_', '-'), '', (string) $key));

            if (is_string($entry_key) && ($normalized_entry_key === $normalized_key || stripos($entry_key, (string) $key) !== false || stripos($normalized_entry_key, $normalized_key) !== false)) {
                return $entry_value;
            }
        }

        return '';
    }

    private function populate_data_source_from_field(&$data, $entry, $field)
    {
        $field_id = (string) ($field['id'] ?? '');
        $field_label = (string) ($field['label'] ?? '');

        if ($field_id === '' && $field_label === '') {
            return;
        }

        $value = $this->get_entry_value($entry, $field_id);
        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        $string_value = (string) $value;

        $this->set_data_source_value($data, $field_id, $string_value);
        $this->set_data_source_value($data, 'field_' . $field_id, $string_value);
        $this->set_data_source_value($data, 'input_' . $field_id, $string_value);
        $this->set_data_source_value($data, $field_label, $string_value);
        $this->set_data_source_value($data, 'field_' . $field_label, $string_value);
        $this->set_data_source_value($data, $this->normalize_token_label($field_label), $string_value);
        $this->set_data_source_value($data, strtolower(str_replace(array(' ', '_', '-'), '', $field_label)), $string_value);

        if (!empty($field['inputs']) && is_array($field['inputs'])) {
            foreach ($field['inputs'] as $input) {
                $input_id = (string) ($input['id'] ?? '');
                $input_label = (string) ($input['label'] ?? '');

                if ($input_id === '' && $input_label === '') {
                    continue;
                }

                $input_value = $this->get_entry_value($entry, $input_id);
                if (is_array($input_value)) {
                    $input_value = implode(', ', $input_value);
                }

                $input_string_value = (string) $input_value;

                $this->set_data_source_value($data, $input_id, $input_string_value);
                $this->set_data_source_value($data, 'field_' . $input_id, $input_string_value);
                $this->set_data_source_value($data, 'input_' . $input_id, $input_string_value);
                $this->set_data_source_value($data, $input_label, $input_string_value);
                $this->set_data_source_value($data, 'field_' . $input_label, $input_string_value);
                $this->set_data_source_value($data, $this->normalize_token_label($input_label), $input_string_value);
                $this->set_data_source_value($data, strtolower(str_replace(array(' ', '_', '-'), '', $input_label)), $input_string_value);
            }
        }
    }

    private function set_data_source_value(&$data, $key, $value)
    {
        if ($key === '') {
            return;
        }

        if ($value === '') {
            if (!isset($data[$key])) {
                $data[$key] = '';
            }
            return;
        }

        $data[$key] = $value;
    }

    private function add_resolver_candidate(array &$candidates, $candidate)
    {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            return;
        }

        if (!in_array($candidate, $candidates, true)) {
            $candidates[] = $candidate;
        }
    }

    private function normalize_token_label($token)
    {
        $token = trim((string) $token, "{} \t\n\r\0\x0B");
        return str_replace(array('field_', 'input_'), '', $token);
    }

    private function stringify_value($value)
    {
        if (is_array($value)) {
            return implode(', ', array_filter(array_map(function ($item) {
                return is_scalar($item) ? (string) $item : '';
            }, $value)));
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return '';
        }

        return (string) $value;
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
