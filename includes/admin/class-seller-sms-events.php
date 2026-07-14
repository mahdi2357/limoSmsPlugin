<?php
if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Seller_SMS_Events
{
    public static function get_events()
    {
        $order_tokens = self::order_tokens();

        return array(
            'seller_new_order' => array(
                'label' => 'ثبت سفارش جدید برای فروشنده',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'seller_name',
                    1 => 'order_id',
                    2 => 'order_total',
                ),
            ),
            'seller_order_processing' => array(
                'label' => 'پردازش سفارش فروشنده',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'seller_name',
                    1 => 'order_id',
                    2 => 'order_total',
                ),
            ),
            'seller_order_completed' => array(
                'label' => 'تکمیل سفارش فروشنده',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'seller_name',
                    1 => 'order_id',
                    2 => 'order_total',
                ),
            ),
            'seller_order_cancelled' => array(
                'label' => 'لغو سفارش فروشنده',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'seller_name',
                    1 => 'order_id',
                    2 => 'order_total',
                ),
            ),
            'seller_order_refunded' => array(
                'label' => 'مرجوع/مسترد سفارش فروشنده',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'seller_name',
                    1 => 'order_id',
                    2 => 'order_total',
                ),
            ),
            'seller_order_failed' => array(
                'label' => 'ناموفق شدن سفارش فروشنده',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'seller_name',
                    1 => 'order_id',
                    2 => 'order_total',
                ),
            ),
        );
    }

    public static function order_tokens()
    {
        return array(
            'seller_id'           => 'شناسه فروشنده',
            'seller_name'         => 'نام فروشنده',
            'seller_store_name'   => 'نام فروشگاه',
            'seller_phone'        => 'شماره موبایل فروشنده',
            'order_id'            => 'شناسه سفارش',
            'order_number'        => 'شماره سفارش',
            'order_status'        => 'وضعیت سفارش',
            'order_total'         => 'مبلغ سفارش',
            'order_date'          => 'تاریخ سفارش',
            'customer_name'       => 'نام مشتری',
            'customer_phone'      => 'شماره مشتری',
            'items_count'         => 'تعداد اقلام سفارش',
            'order_items'         => 'اقلام سفارش',
            'payment_method'      => 'روش پرداخت',
            'shipping_method'     => 'روش ارسال',
        );
    }
}
