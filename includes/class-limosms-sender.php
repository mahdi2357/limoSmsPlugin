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
     * متد سازگار با کدهای قدیمی که روی نمونه‌ی کلاس فراخوانی می‌شوند.
     *
     * @param string           $number
     * @param string|array     $message_data
     * @param string|int|null  $otp_id
     * @return array
     */
    public function send($number, $message_data, $otp_id = null)
    {
        return self::send_sms($number, $message_data, $otp_id);
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
    public static function normalize_mobile_number($number)
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
        unset($endpoint, $api_key);

        if (empty($body['MobileNumber']) || empty($body['OtpId'])) {
            return array(
                'success' => false,
                'message' => 'اطلاعات ارسال پیامک ناقص است.',
            );
        }

        $api = new LimoSMS_API();
        $response = $api->send_pattern_message($body['MobileNumber'], $body['OtpId'], $body['ReplaceToken'] ?? array());

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'status_code' => isset($response->get_error_data()['status']) ? (int) $response->get_error_data()['status'] : 0,
                'body' => $response->get_error_data(),
            );
        }

        return array(
            'success' => true,
            'message' => 'پیامک با موفقیت ارسال شد.',
            'status_code' => 200,
            'body' => $response,
        );
    }
}
