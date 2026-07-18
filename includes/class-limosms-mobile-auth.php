<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LimoSMS_Mobile_Auth {
    private $api;

    public function __construct() {
        $this->api = new LimoSMS_API();

        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_shortcode( 'limoo_sms_auth', array( $this, 'render_shortcode' ) );

        // AJAX Handlers
        add_action( 'wp_ajax_nopriv_limosms_send_otp', array( $this, 'ajax_send_otp' ) );
        add_action( 'wp_ajax_limosms_send_otp', array( $this, 'ajax_send_otp' ) );
    }

    public function register_assets() {
        wp_register_style( 'limosms-mobile-auth', LIMOSMS_URL . 'assets/css/mobile-auth.css', array(), LIMOSMS_VERSION );
        wp_register_script( 'limosms-mobile-auth', LIMOSMS_URL . 'assets/js/mobile-auth.js', array(), LIMOSMS_VERSION, true );
        wp_localize_script( 'limosms-mobile-auth', 'limosmsMobileAuth', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'limosms_mobile_auth_nonce' ),
        ));
    }

    public function render_shortcode( $atts ) {
        wp_enqueue_style( 'limosms-mobile-auth' );
        wp_enqueue_script( 'limosms-mobile-auth' );

        ob_start();
        include LIMOSMS_PATH . 'templates/mobile-auth-form.php';
        return ob_get_clean();
    }

    public function ajax_send_otp() {
        check_ajax_referer( 'limosms_mobile_auth_nonce', 'nonce' );

        $mobile = sanitize_text_field( $_POST['mobile'] ?? '' );

        // ارسال به API
        $response = $this->api->send_verification_code( $mobile );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => 'کد ارسال شد.' ) );
    }
}
