<?php

if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Admin
{
    private $admin_sms;

    public function __construct() {
        // نمونه‌سازی بخش پیامک مدیر
        $this->admin_sms = new LimoSMS_Admin_SMS();

        // هوک‌های اصلی منو و اسکریپت‌های مدیریت
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // هوک‌های AJAX مشترک و عمومی بخش مدیریت
        add_action( 'wp_ajax_limosms_load_tab', array( $this, 'ajax_load_tab' ) );
        add_action( 'wp_ajax_limosms_send_sms', array( $this, 'send_sms_handler' ) );

    }

    /**
     * دریافت لیست کامل و جامع تمام توکن‌های پیامک ووکامرس
     *
     * @return array
     */
    public static function get_available_sms_tokens() {
        return array(
            // اطلاعات کلی سفارش
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

            // اطلاعات خریدار (Billing)
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

            // اطلاعات ارسال (Shipping)
            'shipping_first_name'      => 'نام مشتری (حمل و نقل)',
            'shipping_last_name'       => 'نام خانوادگی مشتری (حمل و نقل)',
            'shipping_company'         => 'نام شرکت (حمل و نقل)',
            'shipping_country'         => 'کشور (حمل و نقل)',
            'shipping_state'           => 'ایالت/استان (حمل و نقل)',
            'shipping_city'            => 'شهر (حمل و نقل)',
            'shipping_address_1'       => 'آدرس 1 (حمل و نقل)',
            'shipping_address_2'       => 'آدرس 2 (حمل و نقل)',
            'shipping_postcode'        => 'کد پستی (حمل و نقل)',

            // اطلاعات محصولات سفارش
            'order_items'              => 'محصولات سفارش',
            'order_items_full'         => 'محصولات سفارش با نام کامل متغیر',
            'order_items_with_qty'     => 'محصولات سفارش بهمراه تعداد',
            'order_items_count'        => 'تعداد محصولات سفارش',

            // اطلاعات تک تک محصولات (برای کاهش موجودی یا رویدادهای تک محصول)
            'product_id'               => 'آیدی محصول',
            'product_url'              => 'لینک محصول',
            'product_sku'              => 'شناسه محصول',
            'product_name'             => 'عنوان محصول',
            'product_name_with_attr'   => 'عنوان محصول با متغیر',
            'product_stock_quantity'   => 'موجودی انبار',

            // اطلاعات رهگیری پستی
            'tracking_code'            => 'کد رهگیری پستی',
            'tracking_url'             => 'آدرس اینترنتی رهگیری پستی',
        );
    }

    /**
     * Register admin menu
     */
    public function register_menu()
    {
        add_menu_page(
            'لیمو اس ام اس',
            'لیمو اس ام اس',
            'manage_options',
            'limosms',
            array($this, 'render_dashboard'),
            'dashicons-email-alt2',
            56
        );
    }

    /**
     * Load admin assets
     */
    public function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, 'limosms' ) ) {
            return;
        }

        wp_enqueue_style(
            'limosms-admin-css',
            LIMOSMS_URL . 'assets/css/admin.css',
            array(),
            LIMOSMS_VERSION
        );

        wp_enqueue_script(
            'limosms-admin-js',
            LIMOSMS_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            LIMOSMS_VERSION,
            true
        );

        $admin_ajax_data = array(
            'url'   => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'limosms_admin_nonce' ),
        );

        wp_localize_script( 'limosms-admin-js', 'limosms_ajax', $admin_ajax_data );

        $tabs = array(
            'connection-settings',
            'send-test-sms',
            'admin-sms',
            'seller-sms',
            'customer-sms',
        );

        $all_tokens = self::get_available_sms_tokens();

        foreach ( $tabs as $tab ) {
            $js_handle  = 'limosms-' . $tab . '-js';
            $css_handle = 'limosms-' . $tab . '-css';

            wp_enqueue_style(
                $css_handle,
                LIMOSMS_URL . 'assets/css/tabs/' . $tab . '.css',
                array( 'limosms-admin-css' ),
                LIMOSMS_VERSION
            );

            wp_enqueue_script(
                $js_handle,
                LIMOSMS_URL . 'assets/js/tabs/' . $tab . '.js',
                array( 'jquery', 'limosms-admin-js' ),
                LIMOSMS_VERSION,
                true
            );

            wp_localize_script( $js_handle, 'limosms_ajax', $admin_ajax_data );

            // داده اختصاصی تب admin-sms (تزریق توکن‌های متمرکز جدید)
            if ( 'admin-sms' === $tab ) {
                $events = LimoSMS_Admin_SMS_Events::get_events();
                $tokens = array();

                foreach ( $events as $key => $event ) {
                    $tokens[ $key ] = $all_tokens; // اختصاص تمام توکن‌ها به همه رویدادها
                }

                wp_localize_script( $js_handle, 'limosmsTokens', $tokens );
            }

            // داده اختصاصی تب customer-sms (تزریق توکن‌های متمرکز جدید)
            if ( 'customer-sms' === $tab ) {
                wp_localize_script(
                    $js_handle,
                    'limosmsCustomerSmsData',
                    array(
                        'ajax_url' => admin_url( 'admin-ajax.php' ),
                        'nonce'    => wp_create_nonce( 'limosms_customer_sms_nonce' ),
                        'events'   => LimoSMS_Customer_SMS_Events::get_events(),
                        'tokens'   => array(
                            'common' => $all_tokens
                        )
                    )
                );
            }
        }

        // Pattern Management assets
        wp_enqueue_style(
            'limosms-pattern-management',
            LIMOSMS_URL . 'assets/css/tabs/pattern-management.css',
            array( 'limosms-admin-css' ),
            LIMOSMS_VERSION
        );

        wp_enqueue_script(
            'limosms-pattern-management',
            LIMOSMS_URL . 'assets/js/tabs/pattern-management.js',
            array( 'jquery', 'limosms-admin-js' ),
            LIMOSMS_VERSION,
            true
        );

        wp_localize_script(
            'limosms-pattern-management',
            'limosmsPatternManagement',
            array(
                'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
                'nonce'             => wp_create_nonce( 'limosms_admin_nonce' ),
                'loadingText'       => 'در حال دریافت پترن‌ها...',
                'loadErrorText'     => 'دریافت لیست پترن‌ها ناموفق بود.',
                'emptyText'         => 'هیچ پترنی یافت نشد.',
                'copySuccessText'   => 'کد پترن کپی شد.',
                'clipboardFailText' => 'کپی خودکار انجام نشد.',
            )
        );
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard()
    {
        require LIMOSMS_PATH . 'admin/pages/dashboard.php';
    }

    /**
     * AJAX load tabs
     */
    public function ajax_load_tab()
    {
        check_ajax_referer('limosms_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز.'));
        }

        $tab = isset($_POST['tab'])
            ? sanitize_key(wp_unslash($_POST['tab']))
            : 'connection-settings';

        $allowed_tabs = array(
            'connection-settings',
            'send-test-sms',
            'admin-sms',
            'seller-sms',
            'customer-sms',
            'sms-pattern-management'
        );

        if (!in_array($tab, $allowed_tabs, true)) {
            $tab = 'connection-settings';
        }

        $file_path = LIMOSMS_PATH . 'templates/admin-tabs/' . $tab . '.php';

        if (file_exists($file_path)) {
            if ($tab === 'connection-settings') {
                require_once LIMOSMS_PATH . 'includes/admin/tabs/class-connection-settings-tab.php';
            }
            include $file_path;
        } else {
            echo '<p style="padding:20px;">' .
                esc_html__('فایل تب موردنظر پیدا نشد.', 'limosms') .
                '</p>';
        }

        wp_die();
    }

    /**
     * Send test SMS
     */
    public function send_sms_handler()
    {
        check_ajax_referer('limosms_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'شما دسترسی لازم را ندارید.',
            ));
        }

        $number = isset($_POST['reciverNumber'])
            ? sanitize_text_field(wp_unslash($_POST['reciverNumber']))
            : '';

        $message = isset($_POST['message'])
            ? sanitize_textarea_field(wp_unslash($_POST['message']))
            : '';

        if (empty($number) || empty($message)) {
            wp_send_json_error(array(
                'message' => 'شماره گیرنده و متن پیام الزامی هستند.',
            ));
        }

        if (method_exists($this, 'send_sms')) {
            $result = $this->send_sms($number, $message);
        } elseif (class_exists('LimoSMS_Sender')) {
            $sender = new LimoSMS_Sender();
            $result = $sender->send($number, $message);
        } else {
            wp_send_json_error(array('message' => 'سیستم ارسال پیامک در دسترس نیست.'));
        }

        if (!empty($result['success'])) {
            wp_send_json_success(array(
                'message' => $result['message'] ?? 'پیامک با موفقیت ارسال شد.',
                'result'  => $result,
            ));
        }

        wp_send_json_error(array(
            'message' => $result['message'] ?? 'ارسال پیامک ناموفق بود.',
            'result'  => $result,
        ));
    }
}
