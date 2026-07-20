<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LimoSMS_Pattern_Management_Tab {

    /**
     * @var LimoSMS_API
     */
    private $api;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->api = new LimoSMS_API();

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_limosms_get_patterns', array( $this, 'ajax_get_patterns' ) );
    }

    /**
     * Render tab content.
     *
     * @return void
     */
    public function render() {
        $template_path = LIMOSMS_PATH . 'templates/admin-tabs/sms-pattern-management-view.php';

        if ( file_exists( $template_path ) ) {
            include $template_path;
            return;
        }

        echo '<div class="notice notice-error"><p>فایل قالب مدیریت پترن‌ها پیدا نشد.</p></div>';
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix Current admin page hook.
     * @return void
     */
    public function enqueue_assets( $hook_suffix ) {
        if ( ! $this->should_load_assets( $hook_suffix ) ) {
            return;
        }

        wp_enqueue_style(
            'limosms-pattern-management',
            LIMOSMS_URL  . 'assets/css/tabs/pattern-management.css',
            array(),
            defined( 'LIMOSMS_VERSION' ) ? LIMOSMS_VERSION : '1.0.0'
        );

        wp_enqueue_script(
            'limosms-pattern-management',
            LIMOSMS_URL  . 'assets/js/tabs/pattern-management.js',
            array( 'jquery' ),
            defined( 'LIMOSMS_VERSION' ) ? LIMOSMS_VERSION : '1.0.0',
            true
        );

        wp_localize_script(
            'limosms-pattern-management',
            'limosmsPatternManagement',
            array(
                'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'limosms_patterns_nonce' ),
                'loadingText'       => 'در حال دریافت پترن‌ها...',
                'loadErrorText'     => 'دریافت لیست پترن‌ها ناموفق بود.',
                'emptyText'         => 'هیچ پترنی یافت نشد.',
                'copySuccessText'   => 'کد پترن کپی شد.',
                'clipboardFailText' => 'کپی خودکار انجام نشد.',
            )
        );
    }

    /**
     * Handle AJAX request for patterns list.
     *
     * @return void
     */
    public function ajax_get_patterns() {
        check_ajax_referer( 'limosms_admin_nonce', 'nonce' );


        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => 'شما دسترسی لازم را ندارید.',
                ),
                403
            );
        }

        $response = $this->api->get_patterns();

        if ( is_wp_error( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => $response->get_error_message(),
                ),
                500
            );
        }

        if ( ! is_array( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => 'پاسخ دریافتی از سرویس نامعتبر است.',
                ),
                500
            );
        }

        if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
            wp_send_json_success( $response['data'] );
        }

        if ( isset( $response['patterns'] ) && is_array( $response['patterns'] ) ) {
            wp_send_json_success( $response['patterns'] );
        }

        if ( ! empty( $response ) && array_keys( $response ) === range( 0, count( $response ) - 1 ) ) {
            wp_send_json_success( $response );
        }

        wp_send_json_success( array() );
    }


    /**
     * Normalize API response for the frontend.
     *
     * @param array $data API response.
     * @return array
     */
    private function normalize_patterns_response( $data ) {
        if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
            return $data['data'];
        }

        if ( isset( $data['patterns'] ) && is_array( $data['patterns'] ) ) {
            return $data['patterns'];
        }

        if ( $this->is_patterns_list( $data ) ) {
            return $data;
        }

        return array();
    }

    /**
     * Check if array is a list of patterns.
     *
     * @param array $data Response data.
     * @return bool
     */
    private function is_patterns_list( $data ) {
        if ( ! is_array( $data ) || empty( $data ) ) {
            return false;
        }

        $first_item = reset( $data );

        return is_array( $first_item );
    }

    /**
     * Determine whether assets should load on current admin page.
     *
     * @param string $hook_suffix Current page hook.
     * @return bool
     */
    private function should_load_assets( $hook_suffix ) {
        if ( ! is_admin() ) {
            return false;
        }

        if ( isset( $_GET['page'] ) ) {
            $page = sanitize_key( wp_unslash( $_GET['page'] ) );

            if ( 'limosms' === $page || 'limosms-settings' === $page ) {
                return true;
            }
        }

        return false;
    }
}
