<?php
if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Gravity_Forms_SMS_Events
{
    /**
     * دریافت لیست فرم های Gravity Forms
     */
    public static function get_forms()
    {
        if (!class_exists('GFAPI')) {
            return array();
        }

        $forms = array();

        try {
            $gf_forms = GFAPI::get_forms();

            if (is_wp_error($gf_forms) || empty($gf_forms)) {
                return array();
            }

            if (!is_array($gf_forms)) {
                return array();
            }

            foreach ($gf_forms as $form) {
                // تبدیل stdClass به آرایه اگر لازم باشد
                if (is_object($form)) {
                    $form = (array) $form;
                }

                if (!isset($form['id']) || !isset($form['title'])) {
                    continue;
                }

                $form_id = (int)$form['id'];
                $forms[$form_id] = array(
                    'label' => $form['title'],
                    'type' => 'gravity_form',
                    'form_id' => $form_id,
                    'tokens' => self::get_form_fields_from_form($form),
                );
            }
        } catch (Exception $e) {
            error_log('LimoSMS Gravity Forms Error: ' . $e->getMessage());
        }

        return $forms;
    }

    /**
     * دریافت فیلدهای یک فرم - استفاده مستقیم از فرم
     */
    public static function get_form_fields_from_form($form)
    {
        $fields = array();

        if (!isset($form['fields'])) {
            return $fields;
        }

        $form_fields = $form['fields'];

        // اگر stdClass باشد، تبدیل به آرایه
        if (is_object($form_fields)) {
            // اگر traversable است (مثل ArrayObject)
            if (is_iterable($form_fields)) {
                $form_fields = iterator_to_array($form_fields);
            } else {
                $form_fields = (array) $form_fields;
            }
        }

        if (!is_iterable($form_fields)) {
            error_log('GF Form fields is not iterable. Type: ' . gettype($form_fields));
            return $fields;
        }

        foreach ($form_fields as $field) {
            // اگر stdClass باشد، تبدیل به آرایه
            if (is_object($field)) {
                // stdClass Objects
                $field_array = (array) $field;
            } elseif (is_array($field)) {
                $field_array = $field;
            } else {
                continue;
            }

            if (!isset($field_array['id']) || !isset($field_array['label'])) {
                continue;
            }

            $field_id = 'field_' . $field_array['id'];
            $fields[$field_id] = $field_array['label'];
        }

        error_log('GF Form fields extracted: ' . json_encode($fields));
        return $fields;
    }

    /**
     * دریافت تمام توکن های دسترس پذیر برای Gravity Forms
     */
    public static function get_all_form_tokens()
    {
        $all_tokens = array();

        $forms = self::get_forms();

        foreach ($forms as $form_id => $form_data) {
            if (isset($form_data['tokens']) && is_array($form_data['tokens'])) {
                $all_tokens = array_merge($all_tokens, $form_data['tokens']);
            }
        }

        // اضافه کردن توکن های عمومی
        $all_tokens['submission_date'] = 'تاریخ ارسال فرم';
        $all_tokens['user_email'] = 'ایمیل کاربر';
        $all_tokens['user_name'] = 'نام کاربر';

        return $all_tokens;
    }
}
