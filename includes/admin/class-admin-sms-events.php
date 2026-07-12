<?php
if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Admin_SMS_Events {

    public static function get_events(){
        $order_tokens = self::order_tokens();
        $product_tokens = self::product_tokens();

        return array(
            'new_order' => array(
                'label' => 'ثبت سفارش جدید',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'billing_first_name',
                    1 => 'order_id',
                    2 => 'order_total',
                )
            ),
            'order_cancelled' => array(
                'label' => 'لغو سفارش',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'billing_first_name',
                    1 => 'order_id',
                    2 => 'order_total',
                )
            ),
            'order_failed' => array(
                'label' => 'سفارش ناموفق',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'billing_first_name',
                    1 => 'order_id',
                    2 => 'order_total',
                )
            ),
            'order_refunded' => array(
                'label' => 'مسترد شده',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'billing_first_name',
                    1 => 'order_id',
                    2 => 'order_total',
                )
            ),
            'order_on_hold' => array(
                'label' => 'در انتظار بررسی',
                'type'  => 'order',
                'tokens' => $order_tokens,
                'default_pattern_map' => array(
                    0 => 'billing_first_name',
                    1 => 'order_id',
                    2 => 'order_total',
                )
            ),
            'low_stock' => array(
                'label' => 'کمبود موجودی',
                'type'  => 'product',
                'tokens' => $product_tokens,
                'default_pattern_map' => array(
                    0 => 'product_name',
                    1 => 'product_stock_quantity',
                )
            ),
            'out_of_stock' => array(
                'label' => 'اتمام موجودی',
                'type'  => 'product',
                'tokens' => $product_tokens,
                'default_pattern_map' => array(
                    0 => 'product_name',
                    1 => 'product_stock_quantity',
                )
            )
        );
    }

    /**
     * لیست کامل و جامع توکن‌های سفارش به همراه نام فارسی مخصوص مدیر
     */
    public static function order_tokens(){
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
        );
    }

    /**
     * لیست توکن‌های مربوط به رویدادهای کالا و انبار
     */
    public static function product_tokens(){
        return array(
            'product_id'               => 'آیدی محصول',
            'product_url'              => 'لینک محصول',
            'product_sku'              => 'شناسه محصول (SKU)',
            'product_name'             => 'عنوان محصول',
            'product_name_with_attr'   => 'عنوان محصول با متغیر',
            'product_stock_quantity'   => 'موجودی انبار',
        );
    }
}
