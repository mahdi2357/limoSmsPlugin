<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LimoSMS_Customer_SMS {

    /**
     * @var LimoSMS_API
     */
    private $api;

    public function __construct() {
        $this->api = new LimoSMS_API();

        add_action( 'wp_ajax_limosms_save_customer_sms_settings', array( $this, 'save_customer_sms_settings' ) );
    }

    public function save_customer_sms_settings() {
        check_ajax_referer( 'limosms_customer_sms_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => 'دسترسی غیرمجاز',
                ),
                403
            );
        }

        $sms_events = isset( $_POST['smsEvents'] )
            ? json_decode( wp_unslash( $_POST['smsEvents'] ), true )
            : array();

        if ( ! is_array( $sms_events ) ) {
            wp_send_json_error(
                array(
                    'message' => 'ساختار داده نامعتبر است.',
                ),
                400
            );
        }

        $events_settings = array();

        foreach ( $sms_events as $event_key => $event_data ) {
            $event_key = sanitize_key( $event_key );

            if ( empty( $event_key ) || ! is_array( $event_data ) ) {
                continue;
            }

            $enabled          = ( isset( $event_data['enabled'] ) && 'yes' === $event_data['enabled'] ) ? 'yes' : 'no';
            $otp_id           = isset( $event_data['otp_id'] ) ? sanitize_text_field( $event_data['otp_id'] ) : '';
            $pattern_selector = isset( $event_data['pattern_selector'] ) ? sanitize_text_field( $event_data['pattern_selector'] ) : '';
            $pattern_text     = isset( $event_data['pattern_text'] ) ? sanitize_textarea_field( $event_data['pattern_text'] ) : '';
            $pattern_map      = ( isset( $event_data['pattern_map'] ) && is_array( $event_data['pattern_map'] ) )
                ? $event_data['pattern_map']
                : array();

            if ( 'yes' === $enabled && '' === $otp_id ) {
                wp_send_json_error(
                    array(
                        'message' => sprintf( 'برای رویداد "%s" انتخاب Pattern الزامی است.', $event_key ),
                    ),
                    400
                );
            }

            $clean_map = array();

            foreach ( $pattern_map as $param => $token ) {
                $param = absint( $param );
                $token = sanitize_text_field( $token );

                if ( '' !== $token ) {
                    $clean_map[ $param ] = $token;
                }
            }

            $events_settings[ $event_key ] = array(
                'enabled'          => $enabled,
                'otp_id'           => $otp_id,
                'pattern_selector' => $pattern_selector,
                'pattern_text'     => $pattern_text,
                'pattern_map'      => $clean_map,
            );
        }

        update_option( 'limosms_customer_sms_events', $events_settings );

        wp_send_json_success(
            array(
                'message' => 'تنظیمات پیامک مشتری با موفقیت ذخیره شد.',
            )
        );
    }

    public function handle_pending_order( $order_id, $order = false ) {
        $this->send_customer_order_sms_by_event( 'order_pending', $order_id, $order );
    }

    public function handle_processing_order( $order_id, $order = false ) {
        $this->send_customer_order_sms_by_event( 'order_processing', $order_id, $order );
    }

    public function handle_on_hold_order( $order_id, $order = false ) {
        $this->send_customer_order_sms_by_event( 'order_on_hold', $order_id, $order );
    }

    public function handle_completed_order( $order_id, $order = false ) {
        $this->send_customer_order_sms_by_event( 'order_completed', $order_id, $order );
    }

    public function handle_cancelled_order( $order_id, $order = false ) {
        $this->send_customer_order_sms_by_event( 'order_cancelled', $order_id, $order );
    }

    public function handle_refunded_order( $order_id, $order = false ) {
        $this->send_customer_order_sms_by_event( 'order_refunded', $order_id, $order );
    }

    public function handle_failed_order( $order_id, $order = false ) {
        $this->send_customer_order_sms_by_event( 'order_failed', $order_id, $order );
    }


    private function send_customer_order_sms_by_event( $event_key, $order_id, $order = false ) {

        $settings = get_option( 'limosms_customer_sms_events', array() );

        if ( empty( $settings[ $event_key ] ) || ! is_array( $settings[ $event_key ] ) ) {
            return;
        }

        $event_settings = $settings[ $event_key ];

        if ( empty( $event_settings['enabled'] ) || 'yes' !== $event_settings['enabled'] ) {
            return;
        }

        $otp_id = isset( $event_settings['otp_id'] ) ? sanitize_text_field( $event_settings['otp_id'] ) : '';
        if ( '' === $otp_id ) {
            return;
        }

        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! $order ) {
            return;
        }

        $mobile = $order->get_billing_phone();
        if ( empty( $mobile ) ) {
            return;
        }

        $pattern_map = isset( $event_settings['pattern_map'] ) && is_array( $event_settings['pattern_map'] )
            ? $event_settings['pattern_map']
            : array();

        $tokens = $this->build_customer_pattern_tokens( $order, $pattern_map );



        if ( empty( $tokens ) ) {
//            error_log( 'LIMOSMS CUSTOMER: tokens empty for order ' . $order_id );
        }

        $response = $this->api->send_pattern( $mobile, $otp_id, $tokens );
    }

    private function build_customer_pattern_tokens( WC_Order $order, $pattern_map ) {
        $items = array();
        $items_with_qty = array();
        $items_count = 0;

        foreach ( $order->get_items() as $item ) {
            $qty  = (int) $item->get_quantity();
            $name = $item->get_name();

            $items[] = $name;
            $items_with_qty[] = $name . ' x' . $qty;
            $items_count += $qty;
        }

        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name  = $order->get_billing_last_name();
        $billing_phone      = $order->get_billing_phone();

        $available_tokens = array(
            'order_id'             => (string) $order->get_id(),
            'order_number'         => (string) $order->get_order_number(),
            'order_status'         => wc_get_order_status_name( $order->get_status() ),
            'order_total'          => wp_strip_all_tags( $order->get_formatted_order_total() ),
            'total'                => wp_strip_all_tags( $order->get_formatted_order_total() ),
            'order_date'           => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y/m/d H:i' ) : '',
            'payment_method'       => $order->get_payment_method_title(),

            'customer_name'        => trim( $billing_first_name . ' ' . $billing_last_name ),
            'first_name'           => $billing_first_name,
            'last_name'            => $billing_last_name,
            'phone'                => LimoSMS_Sender::normalize_mobile_number( $billing_phone ),

            'billing_first_name'   => $billing_first_name,
            'billing_last_name'    => $billing_last_name,
            'billing_phone'        => LimoSMS_Sender::normalize_mobile_number( $billing_phone ),
            'billing_mobile'       => LimoSMS_Sender::normalize_mobile_number( $billing_phone ),

            'items_count'          => (string) $items_count,
            'order_items_count'    => (string) $items_count,
            'all_items'            => implode( '، ', $items_with_qty ),
            'order_items'          => implode( '، ', $items ),
            'order_items_with_qty' => implode( '، ', $items_with_qty ),

            'site_name'            => get_bloginfo( 'name' ),
        );

        $result = array();

        foreach ( $pattern_map as $param => $token_key ) {
            $param = absint( $param );
            $token_key = sanitize_text_field( $token_key );
            $token_key = trim( $token_key );
            $token_key = trim( $token_key, '{}' );

            if ( $param && isset( $available_tokens[ $token_key ] ) ) {
                $result[ $param ] = (string) $available_tokens[ $token_key ];
            }
        }

        ksort( $result, SORT_NUMERIC );

        return array_values( $result );
    }

}
