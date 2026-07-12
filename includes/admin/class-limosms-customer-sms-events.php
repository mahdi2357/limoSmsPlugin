<?php
if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Customer_SMS_Events
{
    public static function get_events()
    {
        $order_tokens = self::order_tokens();
        return array(
            'order_pending' => array(
                'label' => 'سفارش در انتظار پرداخت',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'billing_first_name',
                    1 => 'order_id',
                    2 => 'order_total',
                ),
            ),
            'order_processing' => array(
                'label' => 'سفارش در حال انجام',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'billing_first_name',
                    1 => 'order_id',
                    2 => 'order_total',
                ),
            ),
            'order_on_hold' => array(
                'label' => 'سفارش در انتظار بررسی',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'billing_first_name',
                    1 => 'order_id',
                    2 => 'order_total',
                ),
            ),
            'order_completed' => array(
                'label' => 'سفارش تکمیل شد',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'billing_first_name',
                    1 => 'order_id',
                    2 => 'order_total',
                ),
            ),
            'order_cancelled' => array(
                'label' => 'سفارش لغو شد',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'billing_first_name',
                    1 => 'order_id',
                    2 => 'order_total',
                ),
            ),
            'order_refunded' => array(
                'label' => 'سفارش مسترد شد',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'billing_first_name',
                    1 => 'order_id',
                    2 => 'order_total',
                ),
            ),
            'order_failed' => array(
                'label' => 'پرداخت ناموفق',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'billing_first_name',
                    1 => 'order_id',
                    2 => 'order_total',
                ),
            ),
        );
    }

    /**
     * لیست کامل و جامع توکن‌های سفارش به همراه نام فارسی
     */
    public static function order_tokens()
    {
        return array(
            'order_id'                 => 'شناسه سفارش',
            'order_number'             => 'شماره سفارش',
            'order_parent_id'          => 'شماره سفارش اصلی',
            'order_status'             => 'وضعیت سفارش',
            'order_total'              => 'مبلغ سفارش',
            'order_date'               => 'تاریخ سفارش',
            'transaction_id'           => 'شماره تراکنش',
            'customer_note'            => 'توضیحات مشتری',
            'payment_method'           => 'روش پرداخت',
            'shipping_method'          => 'روش ارسال',
            'payment_url'              => 'لینک پرداخت سفارش',

            'customer_name'            => 'نام و نام خانوادگی مشتری',

            'billing_first_name'       => 'نام مشتری',
            'billing_last_name'        => 'نام خانوادگی مشتری',
            'billing_phone'            => 'شماره تلفن مشتری',
            'billing_mobile'           => 'شماره موبایل مشتری',
            'billing_email'            => 'ایمیل مشتری',
            'billing_company'          => 'نام شرکت',
            'billing_country'          => 'کشور',
            'billing_state'            => 'ایالت/استان',
            'billing_city'             => 'شهر',
            'billing_address_1'        => 'آدرس 1',
            'billing_address_2'        => 'آدرس 2',
            'billing_postcode'         => 'کد پستی',

            'shipping_first_name'      => 'نام مشتری (حمل و نقل)',
            'shipping_last_name'       => 'نام خانوادگی مشتری (حمل و نقل)',
            'shipping_company'         => 'نام شرکت (حمل و نقل)',
            'shipping_country'         => 'کشور (حمل و نقل)',
            'shipping_state'           => 'ایالت/استان (حمل و نقل)',
            'shipping_city'            => 'شهر (حمل و نقل)',
            'shipping_address_1'       => 'آدرس 1 (حمل و نقل)',
            'shipping_address_2'       => 'آدرس 2 (حمل و نقل)',
            'shipping_postcode'        => 'کد پستی (حمل و نقل)',

            'order_items'              => 'محصولات سفارش',
            'order_items_full'         => 'محصولات سفارش با نام کامل متغیر',
            'order_items_with_qty'     => 'محصولات سفارش بهمراه تعداد',
            'order_items_count'        => 'تعداد محصولات سفارش',

            'tracking_code'            => 'کد رهگیری پستی',
            'tracking_url'             => 'آدرس اینترنتی رهگیری پستی',

            'site_name'                => 'نام سایت',
        );
    }


    public static function get_tokens() {
        $common_tokens = self::order_tokens();
        $events = self::get_events();
        $tokens = array();

        foreach ($events as $event_key => $event) {
            if (isset($event['tokens']) && is_array($event['tokens'])) {
                $tokens[$event_key] = $event['tokens'];
            } else {
                $tokens[$event_key] = $common_tokens;
            }
        }

        return $tokens;
    }
}
