<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LimoSMS {

    private $admin;
    private $connection_setting;
    private $admin_sms;
    private $test_sms;
    private $woocommerce;

    // Seller / Dokan
    private $seller_sms;
    private $dokan_sms;

    private $mobile_auth;


    public function run() {
        $this->load_dependencies();

        // ووکامرس/مشتری
        add_action( 'plugins_loaded', array( $this, 'init_woocommerce_customer_sms' ), 20 );

        // Dokan Seller SMS
        add_action( 'plugins_loaded', array( $this, 'init_dokan_seller_sms' ), 21 );

        $this->init_classes();
    }

    public function init_woocommerce_customer_sms() {
        if ( class_exists( 'WooCommerce' ) && class_exists( 'LimoSMS_WooCommerce_Customer_SMS' ) ) {
            new LimoSMS_WooCommerce_Customer_SMS();
        }
    }

    public function init_dokan_seller_sms() {
        if (
            class_exists( 'WooCommerce' ) &&
            class_exists( 'WeDevs_Dokan' ) &&
            class_exists( 'LimoSMS_Dokan_SMS' )
        ) {
            $this->dokan_sms = new LimoSMS_Dokan_SMS();

            if ( method_exists( $this->dokan_sms, 'init' ) ) {
                $this->dokan_sms->init();
            }
        }
    }

    public static function is_dokan_active() {
        return class_exists( 'WeDevs_Dokan' );
    }

    private function load_dependencies() {
        // Core / Base
        require_once LIMOSMS_PATH . 'includes/class-limosms-sender.php';
        require_once LIMOSMS_PATH . 'includes/class-limosms-api.php';

        // Admin base
        require_once LIMOSMS_PATH . 'includes/class-admin.php';
        require_once LIMOSMS_PATH . 'includes/admin/tabs/class-connection-settings-tab.php';
        require_once LIMOSMS_PATH . 'includes/admin/tabs/class-admin-sms-tab.php';
        require_once LIMOSMS_PATH . 'includes/admin/tabs/class-send-test-sms-tab.php';
        require_once LIMOSMS_PATH . 'includes/admin/tabs/class-sent-sms-tab.php';
        require_once LIMOSMS_PATH . 'includes/admin/tabs/class-pattern-management-tab.php';

        // WooCommerce base
        require_once LIMOSMS_PATH . 'includes/woocommerce/class-woocommerce-sms.php';
        require_once LIMOSMS_PATH . 'includes/woocommerce/class-limosms-dokan-sms.php';

        // Admin SMS
        require_once LIMOSMS_PATH . 'includes/admin/class-admin-sms-events.php';
        require_once LIMOSMS_PATH . 'includes/admin/class-admin-sms-settings.php';

        // Customer SMS
        require_once LIMOSMS_PATH . 'includes/admin/class-customer-sms-events.php';
        require_once LIMOSMS_PATH . 'includes/admin/class-customer-sms-settings.php';
        require_once LIMOSMS_PATH . 'includes/admin/tabs/class-customer-sms-tab.php';
        require_once LIMOSMS_PATH . 'includes/woocommerce/class-limosms-woocommerce-customer-sms.php';

        // Seller SMS
        require_once LIMOSMS_PATH . 'includes/admin/class-seller-sms-events.php';
        require_once LIMOSMS_PATH . 'includes/admin/class-seller-sms-settings.php';
        require_once LIMOSMS_PATH . 'includes/admin/tabs/class-seller-sms-tab.php';

        // Gravity Forms SMS
        require_once LIMOSMS_PATH . 'includes/admin/class-gravity-forms-sms-events.php';
        require_once LIMOSMS_PATH . 'includes/admin/class-gravity-forms-sms-settings.php';
        require_once LIMOSMS_PATH . 'includes/admin/tabs/class-gravity-forms-sms-tab.php';
        require_once LIMOSMS_PATH . 'includes/class-limosms-gravity-forms-sms.php';

        require_once LIMOSMS_PATH . 'includes/class-limosms-mobile-auth.php';
        require_once LIMOSMS_PATH . 'includes/admin/tabs/class-login-register-tab.php';



        if ( is_admin() ) {
            new LimoSMS_Admin_SMS_Settings();
            new LimoSMS_Customer_SMS_Settings();
            new LimoSMS_Customer_SMS();
            new LimoSMS_Gravity_Forms_SMS_Settings();
            new LimoSMS_Gravity_Forms_SMS_Tab();

            if ( class_exists( 'LimoSMS_Seller_SMS_Settings' ) ) {
                new LimoSMS_Seller_SMS_Settings();
            }

            if ( self::is_dokan_active() && class_exists( 'LimoSMS_Seller_SMS_Tab' ) ) {
                $this->seller_sms = new LimoSMS_Seller_SMS_Tab();

                if ( method_exists( $this->seller_sms, 'init' ) ) {
                    $this->seller_sms->init();
                }
            }
        } else {
            // Initialize Gravity Forms SMS on frontend
            if ( class_exists( 'LimoSMS_Gravity_Forms_SMS' ) ) {
                new LimoSMS_Gravity_Forms_SMS();
            }
        }
    }

    private function init_classes() {
        $this->admin              = new LimoSMS_Admin();
        $this->connection_setting = new LimoSMS_Connection_Settings();
        $this->test_sms           = new LimoSMS_Send_Test_SMS();
        $this->woocommerce        = new LimoSMS_WooCommerce_SMS();
        $this->mobile_auth        = new LimoSMS_Mobile_Auth();
    }

}
