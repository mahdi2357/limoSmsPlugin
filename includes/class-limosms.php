<?php

if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS
{
    private $admin;
    private $connection_setting;
    private $admin_sms;
    private $test_sms;
    private $woocommerce;

    // Seller / Dokan
    private $seller_sms;
    private $dokan_sms;

    public function run()
    {
        $this->load_dependencies();

        // ووکامرس/مشتری
        add_action('plugins_loaded', array($this, 'init_woocommerce_customer_sms'), 20);

        // Dokan Seller SMS
        add_action('plugins_loaded', array($this, 'init_dokan_seller_sms'), 21);

        $this->init_classes();
        $this->register_hooks();
    }

    public function init_woocommerce_customer_sms()
    {
        if (class_exists('WooCommerce') && class_exists('LimoSMS_WooCommerce_Customer_SMS')) {
            new LimoSMS_WooCommerce_Customer_SMS();
        }
    }

    public function init_dokan_seller_sms()
    {
        if (
            class_exists('WooCommerce') &&
            class_exists('WeDevs_Dokan') &&
            class_exists('LimoSMS_Dokan_SMS')
        ) {
            $this->dokan_sms = new LimoSMS_Dokan_SMS();

            if (method_exists($this->dokan_sms, 'init')) {
                $this->dokan_sms->init();
            }
        }
    }

    public static function is_dokan_active()
    {
        return class_exists('WeDevs_Dokan');
    }

    private function load_dependencies()
    {
        // Core / Base
        require_once LIMOSMS_PATH . 'includes/class-limosms-sender.php';
        require_once LIMOSMS_PATH . 'includes/class-limosms-api.php';

        // Admin base
        require_once LIMOSMS_PATH . 'includes/class-admin.php';
        require_once LIMOSMS_PATH . 'includes/admin/tabs/class-connection-settings-tab.php';
        require_once LIMOSMS_PATH . 'includes/admin/tabs/class-admin-sms-tab.php';
        require_once LIMOSMS_PATH . 'includes/admin/tabs/class-send-test-sms-tab.php';

        // WooCommerce base
        require_once LIMOSMS_PATH . 'includes/woocommerce/class-woocommerce-sms.php';

        // Admin SMS
        require_once LIMOSMS_PATH . 'includes/admin/class-admin-sms-events.php';
        require_once LIMOSMS_PATH . 'includes/admin/class-admin-sms-settings.php';

        // Customer SMS
        require_once LIMOSMS_PATH . 'includes/admin/class-limosms-customer-sms-events.php';
        require_once LIMOSMS_PATH . 'includes/admin/class-limosms-customer-sms-settings.php';
        require_once LIMOSMS_PATH . 'includes/admin/class-limosms-customer-sms.php';
        require_once LIMOSMS_PATH . 'includes/woocommerce/class-limosms-woocommerce-customer-sms.php';

        // Seller SMS (Dokan)
        $seller_events_file   = LIMOSMS_PATH . 'includes/admin/class-seller-sms-events.php';
        $seller_settings_file = LIMOSMS_PATH . 'includes/admin/class-seller-sms-settings.php';
        $seller_tab_file      = LIMOSMS_PATH . 'includes/admin/tabs/class-seller-sms-tab.php';
        $dokan_sms_file       = LIMOSMS_PATH . 'includes/woocommerce/class-limosms-dokan-sms.php';

        if (file_exists($seller_events_file)) {
            require_once $seller_events_file;
        }

        if (file_exists($seller_settings_file)) {
            require_once $seller_settings_file;
        }

        if (file_exists($seller_tab_file)) {
            require_once $seller_tab_file;
        }

        if (file_exists($dokan_sms_file)) {
            require_once $dokan_sms_file;
        }

        if (is_admin()) {
            new LimoSMS_Admin_SMS_Settings();
            new LimoSMS_Customer_SMS_Settings();
            new LimoSMS_Customer_SMS();

            if (class_exists('LimoSMS_Seller_SMS_Settings')) {
                new LimoSMS_Seller_SMS_Settings();
            }

            if (self::is_dokan_active() && class_exists('LimoSMS_Seller_SMS_Tab')) {
                $this->seller_sms = new LimoSMS_Seller_SMS_Tab();

                if (method_exists($this->seller_sms, 'init')) {
                    $this->seller_sms->init();
                }
            }
        }
    }

    private function init_classes()
    {
        $this->admin              = new LimoSMS_Admin();
        $this->connection_setting = new LimoSMS_Connection_Settings();
        $this->admin_sms          = new LimoSMS_Admin_SMS();
        $this->test_sms           = new LimoSMS_Send_Test_SMS();
        $this->woocommerce        = new LimoSMS_WooCommerce_SMS();
    }

    private function register_hooks()
    {
        // Connection settings
        add_action(
            'wp_ajax_limosms_save_connection_settings',
            array($this->connection_setting, 'save_connection_settings')
        );

        // Seller SMS settings save
        add_action(
            'wp_ajax_limosms_save_seller_sms_settings',
            array($this, 'save_seller_sms_settings_ajax')
        );
    }

    public function save_seller_sms_settings_ajax()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                array('message' => __('دسترسی غیرمجاز.', 'limosms')),
                403
            );
        }

        check_ajax_referer('limosms_ajax_nonce', 'nonce');

        if (!class_exists('LimoSMS_Seller_SMS_Settings')) {
            wp_send_json_error(
                array('message' => __('کلاس تنظیمات فروشنده یافت نشد.', 'limosms'))
            );
        }

        $raw      = isset($_POST['smsEvents']) ? wp_unslash($_POST['smsEvents']) : '';
        $settings = json_decode($raw, true);

        if (!is_array($settings)) {
            wp_send_json_error(
                array('message' => __('داده تنظیمات نامعتبر است.', 'limosms'))
            );
        }

        $saved = LimoSMS_Seller_SMS_Settings::save_events_settings($settings);

        if ($saved) {
            wp_send_json_success(
                array('message' => __('تنظیمات پیامک فروشنده ذخیره شد.', 'limosms'))
            );
        }

        wp_send_json_error(
            array('message' => __('خطا در ذخیره تنظیمات پیامک فروشنده.', 'limosms'))
        );
    }
}
