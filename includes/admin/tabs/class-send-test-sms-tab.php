<?php

if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Send_Test_SMS
{
    public function __construct()
    {
        add_action('wp_ajax_limosms_send_test_sms', array($this, 'send_test_sms'));
    }

    public function send_test_sms()
    {
        check_ajax_referer('limosms_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                array(
                    'message' => 'شما دسترسی لازم را ندارید.',
                ),
                403
            );
        }

        $number = isset($_POST['reciverNumber'])
            ? sanitize_text_field(wp_unslash($_POST['reciverNumber']))
            : '';

        $message = isset($_POST['message'])
            ? sanitize_textarea_field(wp_unslash($_POST['message']))
            : '';

        $pattern_id = isset($_POST['patternId'])
            ? sanitize_text_field(wp_unslash($_POST['patternId']))
            : '';

        if (empty($number)) {
            wp_send_json_error(
                array(
                    'message' => 'شماره گیرنده وارد نشده است.',
                ),
                400
            );
        }

        if (empty($message)) {
            wp_send_json_error(
                array(
                    'message' => 'متن پیام یا توکن‌ها وارد نشده است.',
                ),
                400
            );
        }

        if (!empty($pattern_id)) {
            $tokens = array_map('trim', explode(',', $message));
            $tokens = array_values(array_filter($tokens, static function ($token) {
                return '' !== $token;
            }));

            if (empty($tokens)) {
                wp_send_json_error(
                    array(
                        'message' => 'توکن‌های پیام معتبر نیستند.',
                    ),
                    400
                );
            }

            $result = LimoSMS_Sender::send_pattern_sms($number, $pattern_id, $tokens);
        } else {
            $result = LimoSMS_Sender::send_sms($number, $message);
        }

        if (is_array($result) && !empty($result['success'])) {
            wp_send_json_success(
                array(
                    'message'  => !empty($result['message']) ? $result['message'] : 'پیامک با موفقیت ارسال شد.',
                    'response' => $result,
                )
            );
        }

        wp_send_json_error(
            array(
                'message'  => is_array($result) && !empty($result['message'])
                    ? $result['message']
                    : 'ارسال پیامک ناموفق بود.',
                'response' => is_array($result) ? $result : array(),
            ),
            500
        );
    }
}
