<?php

if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Sender
{
    /**
     * ارسال پیامک پترنی
     *
     * @param string     $number
     * @param string|int $otp_id
     * @param array      $tokens
     * @return array
     */
    public static function send_pattern_sms($number, $otp_id, $tokens = array())
    {
        $api_key = self::get_api_key();
        $number  = self::normalize_mobile_number($number);
        $otp_id  = sanitize_text_field((string) $otp_id);
        $tokens  = self::sanitize_tokens($tokens);

        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => 'کلید API تنظیم نشده است.',
            );
        }

        if (empty($number)) {
            return array(
                'success' => false,
                'message' => 'شماره گیرنده معتبر نیست.',
            );
        }

        if (empty($otp_id)) {
            return array(
                'success' => false,
                'message' => 'شناسه الگو (OTP ID) وارد نشده است.',
            );
        }

        $body = array(
            'OtpId'        => $otp_id,
            'ReplaceToken' => array_values($tokens),
            'MobileNumber' => $number,
        );

//        delete_transient('limosms_connection_status');

        return self::request('/api/sendpatternmessage', $api_key, $body);
    }



    /**
     * متد سازگار با کدهای قبلی
     * در وضعیت فعلی، ارسال از طریق الگو انجام می‌شود.
     *
     * @param string           $number
     * @param string|array     $message_data
     * @param string|int|null  $otp_id
     * @return array
     */
    public static function send_sms($number, $message_data, $otp_id = null)
    {
        if (empty($otp_id)) {
            $otp_id = get_option('limosms_admin_sms_otp_id', '');
        }

        if (empty($otp_id)) {
            return array(
                'success' => false,
                'message' => 'شناسه الگوی پیامک تنظیم نشده است.',
            );
        }
        return self::send_pattern_sms($number, $otp_id, $message_data);
    }

    /**
     * دریافت API key
     *
     * @return string
     */
    private static function get_api_key()
    {
        return sanitize_text_field((string) get_option('limosms_api_key', ''));
    }

    /**
     * نرمال‌سازی شماره موبایل
     *
     * @param string $number
     * @return string
     */
    public  static function normalize_mobile_number($number)
    {
        $number = wp_unslash((string) $number);
        $number = preg_replace('/[^0-9]/', '', $number);

        if (empty($number)) {
            return '';
        }

        if (0 === strpos($number, '98')) {
            return $number;
        }

        if (0 === strpos($number, '09')) {
            return '98' . substr($number, 1);
        }

        if (0 === strpos($number, '9') && 10 === strlen($number)) {
            return '98' . $number;
        }

        return $number;
    }

    /**
     * پاکسازی و یک‌دست‌سازی توکن‌ها
     *
     * @param string|array $tokens
     * @return array
     */
    private static function sanitize_tokens($tokens)
    {
        if (!is_array($tokens)) {
            $tokens = array($tokens);
        }

        $tokens = array_map(
            static function ($token) {
                return sanitize_text_field((string) wp_unslash($token));
            },
            $tokens
        );

        return array_values(
            array_filter(
                $tokens,
                static function ($token) {
                    return '' !== $token;
                }
            )
        );
    }

    /**
     * اجرای درخواست HTTP به API لیمو اس‌ام‌اس
     *
     * @param string $endpoint
     * @param string $api_key
     * @param array  $body
     * @return array
     */
    private static function request($endpoint, $api_key, $body)
    {
        $url = 'https://api.limosms.com/' . ltrim($endpoint, '/');


        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8',
                    'ApiKey'       => $api_key,
                ),
                'body'    => wp_json_encode($body),
            )
        );

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        $status_code  = wp_remote_retrieve_response_code($response);
        $raw_body     = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($raw_body, true);

        if ($status_code >= 200 && $status_code < 300) {
            return array(
                'success'     => true,
                'message'     => 'پیامک با موفقیت ارسال شد.',
                'status_code' => $status_code,
                'body'        => is_array($decoded_body) ? $decoded_body : $raw_body,
            );
        }

        $error_message = 'ارسال پیامک ناموفق بود.';

        if (is_array($decoded_body)) {
            if (!empty($decoded_body['message'])) {
                $error_message = $decoded_body['message'];
            } elseif (!empty($decoded_body['Message'])) {
                $error_message = $decoded_body['Message'];
            } elseif (!empty($decoded_body['error'])) {
                $error_message = $decoded_body['error'];
            }
        }

        return array(
            'success'     => false,
            'message'     => $error_message,
            'status_code' => $status_code,
            'body'        => is_array($decoded_body) ? $decoded_body : $raw_body,
        );
    }
}
