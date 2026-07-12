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

    public function run() {
        $this->load_dependencies();
        // ثبت هوک برای لود شدن اینتگریشن‌های ووکامرس در زمان مناسب
        add_action('plugins_loaded', array($this, 'init_woocommerce_customer_sms'), 20);
        $this->init_classes();
        $this->register_hooks();
    }

    public function init_woocommerce_customer_sms() {
        if (class_exists('WooCommerce')) {
            // کلاس اصلی که مسئول ارسال پیامک به مشتری است
            new LimoSMS_WooCommerce_Customer_SMS();
        }
    }



    private function load_dependencies()
    {
        // Core / Base
        require_once LIMOSMS_PATH . 'includes/class-limosms-sender.php';
        require_once LIMOSMS_PATH . 'includes/class-limosms-api.php'; // مهم: قبل از کلاس‌های وابسته

        // Admin base
        require_once LIMOSMS_PATH . 'includes/class-admin.php';
        require_once LIMOSMS_PATH . 'includes/admin/tabs/class-connection-settings-tab.php';
        require_once LIMOSMS_PATH . 'includes/admin/tabs/class-admin-sms-tab.php';
        require_once LIMOSMS_PATH . 'includes/admin/tabs/class-send-test-sms-tab.php';

        // WooCommerce base
        require_once LIMOSMS_PATH . 'includes/woocommerce/class-woocommerce-sms.php';

        // Admin SMS (مدیر)
        require_once LIMOSMS_PATH . 'includes/admin/class-admin-sms-events.php';
        require_once LIMOSMS_PATH . 'includes/admin/class-admin-sms-settings.php';

        // Customer SMS
        require_once LIMOSMS_PATH . 'includes/admin/class-limosms-customer-sms-events.php';
        require_once LIMOSMS_PATH . 'includes/admin/class-limosms-customer-sms-settings.php';
        require_once LIMOSMS_PATH . 'includes/admin/class-limosms-customer-sms.php';
        require_once LIMOSMS_PATH . 'includes/woocommerce/class-limosms-woocommerce-customer-sms.php';

        // Instantiate dependent classes after all requires
        if (is_admin()) {
            new LimoSMS_Admin_SMS_Settings();
            new LimoSMS_Customer_SMS_Settings();
            new LimoSMS_Customer_SMS();
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
        add_action('wp_ajax_limosms_save_connection_settings', array($this->connection_setting, 'save_connection_settings'));
    }
}
