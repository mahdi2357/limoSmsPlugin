<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LimoSMS_Admin {
    private $admin_sms;
    private $sent_sms_tab;

    public function __construct() {
        $this->admin_sms    = new LimoSMS_Admin_SMS();
        $this->sent_sms_tab = new LimoSMS_Sent_SMS_Tab();

        if ( method_exists( $this->sent_sms_tab, 'register_hooks' ) ) {
            $this->sent_sms_tab->register_hooks();
        }

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        add_action( 'wp_ajax_limosms_load_tab', array( $this, 'ajax_load_tab' ) );
        add_action( 'wp_ajax_limosms_send_sms', array( $this, 'send_sms_handler' ) );
    }

    public function register_menu() {
        add_menu_page(
            'لیمو اس ام اس',
            'لیمو اس ام اس',
            'manage_options',
            'limosms',
            array( $this, 'render_dashboard' ),
            'dashicons-email-alt2',
            56
        );
    }

    public function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, 'limosms' ) ) {
            return;
        }

        // Base admin assets.
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

        // Shared ajax data for generic tab loading/admin actions.
        $admin_ajax_data = array(
            'url'   => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'limosms_admin_nonce' ),

        );

        wp_localize_script( 'limosms-admin-js', 'limosms_ajax', $admin_ajax_data );

        $tabs       = array(
            'connection-settings',
            'admin-sms',
            'customer-sms',
            'seller-sms',
            'sent-sms',
            'send-test-sms',
            'pattern-management',
            'login-register'
        );
        $all_tokens = self::get_available_sms_tokens();

        foreach ( $tabs as $tab ) {
            $js_handle  = 'limosms-' . $tab . '-js';
            $css_handle = 'limosms-' . $tab . '-css';

            $css_file = LIMOSMS_PATH . 'assets/css/tabs/' . $tab . '.css';
            $js_file  = LIMOSMS_PATH . 'assets/js/tabs/' . $tab . '.js';

            if ( file_exists( $css_file ) ) {
                wp_enqueue_style(
                    $css_handle,
                    LIMOSMS_URL . 'assets/css/tabs/' . $tab . '.css',
                    array( 'limosms-admin-css' ),
                    LIMOSMS_VERSION
                );
            }

            if ( file_exists( $js_file ) ) {
                wp_enqueue_script(
                    $js_handle,
                    LIMOSMS_URL . 'assets/js/tabs/' . $tab . '.js',
                    array( 'jquery', 'limosms-admin-js' ),
                    LIMOSMS_VERSION,
                    true
                );

                // Generic ajax object for tabs that use limosms_ajax.
                wp_localize_script( $js_handle, 'limosms_ajax', $admin_ajax_data );
            }

            // Admin SMS tab data.
            if ( 'admin-sms' === $tab && wp_script_is( $js_handle, 'enqueued' ) ) {
                $events = LimoSMS_Admin_SMS_Events::get_events();
                $tokens = array();

                foreach ( $events as $key => $event ) {
                    $tokens[ $key ] = $all_tokens;
                }

                wp_localize_script( $js_handle, 'limosmsTokens', $tokens );
            }

            // Customer SMS tab data.
            if ( 'customer-sms' === $tab && wp_script_is( $js_handle, 'enqueued' ) ) {
                wp_localize_script(
                    $js_handle,
                    'limosmsCustomerSmsData',
                    array(
                        'ajax_url' => admin_url( 'admin-ajax.php' ),
                        'nonce'    => wp_create_nonce( 'limosms_customer_sms_nonce' ),
                        'events'   => LimoSMS_Customer_SMS_Events::get_events(),
                        'tokens'   => array(
                            'common' => $all_tokens,
                        ),
                    )
                );
            }

            // Sent SMS tab data (important: dedicated nonce).
            if ( 'sent-sms' === $tab && wp_script_is( $js_handle, 'enqueued' ) ) {
                wp_localize_script(
                    $js_handle,
                    'limosmsSentSMS',
                    array(
                        'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                        'nonce'         => wp_create_nonce( 'limosms_sent_sms_nonce' ),
                        'loadingText'   => __( 'در حال دریافت پیام ها...', 'limosms' ),
                        'loadErrorText' => __( 'دریافت لیست پیام ها ناموفق بود.', 'limosms' ),
                        'emptyText'     => __( 'پیامی یافت نشد.', 'limosms' ),
                    )
                );
            }
        }

        // Pattern management (separate naming kept as-is for compatibility).
        $pattern_css_file = LIMOSMS_PATH . 'assets/css/tabs/pattern-management.css';
        $pattern_js_file  = LIMOSMS_PATH . 'assets/js/tabs/pattern-management.js';

        if ( file_exists( $pattern_css_file ) ) {
            wp_enqueue_style(
                'limosms-pattern-management',
                LIMOSMS_URL . 'assets/css/tabs/pattern-management.css',
                array( 'limosms-admin-css' ),
                LIMOSMS_VERSION
            );
        }

        if ( file_exists( $pattern_js_file ) ) {
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
//                    'nonce'             => wp_create_nonce( 'limosms_patterns_nonce' ),
                    'nonce' => wp_create_nonce( 'limosms_admin_nonce' ),
                    'loadingText'       => __( 'در حال دریافت پترن ها...', 'limosms' ),
                    'loadErrorText'     => __( 'دریافت لیست پترن ها ناموفق بود.', 'limosms' ),
                    'emptyText'         => __( 'هیچ پترنی یافت نشد.', 'limosms' ),
                    'copySuccessText'   => __( 'کد پترن کپی شد.', 'limosms' ),
                    'clipboardFailText' => __( 'کپی خودکار انجام نشد.', 'limosms' ),
                )
            );
        }
    }

    public function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'limosms' ) );
        }

        $active_tab = isset( $_GET['tab'] )
            ? sanitize_key( wp_unslash( $_GET['tab'] ) )
            : 'connection-settings';

        $template_path = LIMOSMS_PATH . 'templates/admin-dashboard-view.php';

        if ( file_exists( $template_path ) ) {
            include $template_path;
            return;
        }

        echo '<div class="wrap"><p>' . esc_html__( 'Dashboard template not found.', 'limosms' ) . '</p></div>';
    }

    public function ajax_load_tab() {
        check_ajax_referer( 'limosms_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'دسترسی غیرمجاز.', 'limosms' ),
                )
            );
        }

        $tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'connection-settings';

        $allowed_tabs = array(
            'connection-settings',
            'admin-sms',
            'customer-sms',
            'seller-sms',
            'sent-sms',
            'send-test-sms',
            'sms-pattern-management',
            'login-register'
        );

        if ( ! in_array( $tab, $allowed_tabs, true ) ) {
            $tab = 'connection-settings';
        }

        $file_path = LIMOSMS_PATH . 'templates/admin-tabs/' . $tab . '-view.php';

        if ( file_exists( $file_path ) ) {
            if ( 'connection-settings' === $tab ) {
                require_once LIMOSMS_PATH . 'includes/admin/tabs/class-connection-settings-tab.php';
            }

            include $file_path;
        } else {
            echo '<p style="padding:20px;">' . esc_html__( 'فایل تب موردنظر پیدا نشد.', 'limosms' ) . '</p>';
        }

        wp_die();
    }

    public function send_sms_handler() {
        check_ajax_referer( 'limosms_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'شما دسترسی لازم را ندارید.', 'limosms' ),
                )
            );
        }

        $number  = isset( $_POST['reciverNumber'] ) ? sanitize_text_field( wp_unslash( $_POST['reciverNumber'] ) ) : '';
        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

        if ( empty( $number ) || empty( $message ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'شماره گیرنده و متن پیام الزامی هستند.', 'limosms' ),
                )
            );
        }

        if ( method_exists( $this, 'send_sms' ) ) {
            $result = $this->send_sms( $number, $message );
        } elseif ( class_exists( 'LimoSMS_Sender' ) ) {
            $sender = new LimoSMS_Sender();
            $result = $sender->send( $number, $message );
        } else {
            wp_send_json_error(
                array(
                    'message' => __( 'سیستم ارسال پیامک در دسترس نیست.', 'limosms' ),
                )
            );
        }

        if ( ! empty( $result['success'] ) ) {
            wp_send_json_success(
                array(
                    'message' => isset( $result['message'] ) ? $result['message'] : __( 'پیامک با موفقیت ارسال شد.', 'limosms' ),
                    'result'  => $result,
                )
            );
        }

        wp_send_json_error(
            array(
                'message' => isset( $result['message'] ) ? $result['message'] : __( 'ارسال پیامک ناموفق بود.', 'limosms' ),
                'result'  => $result,
            )
        );
    }

    public static function get_available_sms_tokens() {
        return array(
            'order_id'               => 'شناسه سفارش',
            'order_number'           => 'شماره سفارش',
            'order_parent_id'        => 'شماره سفارش اصلی',
            'order_status'           => 'وضعیت سفارش',
            'order_total'            => 'مبلغ سفارش',
            'order_date'             => 'تاریخ سفارش',
            'transaction_id'         => 'شماره تراکنش',
            'customer_note'          => 'توضیحات مشتری',
            'payment_method'         => 'روش پرداخت',
            'shipping_method'        => 'روش ارسال',
            'payment_url'            => 'لینک پرداخت سفارش',

            'billing_first_name'     => 'نام مشتری',
            'billing_last_name'      => 'نام خانوادگی مشتری',
            'billing_phone'          => 'شماره تلفن مشتری',
            'billing_mobile'         => 'شماره موبایل مشتری',
            'billing_email'          => 'ایمیل مشتری',
            'billing_company'        => 'نام شرکت',
            'billing_country'        => 'کشور',
            'billing_state'          => 'ایالت/استان',
            'billing_city'           => 'شهر',
            'billing_address_1'      => 'آدرس 1',
            'billing_address_2'      => 'آدرس 2',
            'billing_postcode'       => 'کد پستی',

            'shipping_first_name'    => 'نام مشتری (حمل و نقل)',
            'shipping_last_name'     => 'نام خانوادگی مشتری (حمل و نقل)',
            'shipping_company'       => 'نام شرکت (حمل و نقل)',
            'shipping_country'       => 'کشور (حمل و نقل)',
            'shipping_state'         => 'ایالت/استان (حمل و نقل)',
            'shipping_city'          => 'شهر (حمل و نقل)',
            'shipping_address_1'     => 'آدرس 1 (حمل و نقل)',
            'shipping_address_2'     => 'آدرس 2 (حمل و نقل)',
            'shipping_postcode'      => 'کد پستی (حمل و نقل)',

            'order_items'            => 'محصولات سفارش',
            'order_items_full'       => 'محصولات سفارش با نام کامل متغیر',
            'order_items_with_qty'   => 'محصولات سفارش بهمراه تعداد',
            'order_items_count'      => 'تعداد محصولات سفارش',

            'product_id'             => 'آیدی محصول',
            'product_url'            => 'لینک محصول',
            'product_sku'            => 'شناسه محصول',
            'product_name'           => 'عنوان محصول',
            'product_name_with_attr' => 'عنوان محصول با متغیر',
            'product_stock_quantity' => 'موجودی انبار',

            'tracking_code'          => 'کد رهگیری پستی',
            'tracking_url'           => 'آدرس اینترنتی رهگیری پستی',
        );
    }

}
