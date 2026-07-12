<?php

class LimoSMS_WooCommerce_Customer_SMS {

    public function __construct() {
        add_action('woocommerce_order_status_pending', [$this, 'send_customer_sms_pending'], 10, 1);
        add_action('woocommerce_order_status_processing', [$this, 'send_customer_sms_processing'], 10, 1);
        add_action('woocommerce_order_status_on-hold', [$this, 'send_customer_sms_on_hold'], 10, 1);
        add_action('woocommerce_order_status_completed', [$this, 'send_customer_sms_completed'], 10, 1);
        add_action('woocommerce_order_status_cancelled', [$this, 'send_customer_sms_cancelled'], 10, 1);
        add_action('woocommerce_order_status_refunded', [$this, 'send_customer_sms_refunded'], 10, 1);
        add_action('woocommerce_order_status_failed', [$this, 'send_customer_sms_failed'], 10, 1);
    }

    public function send_customer_sms_pending($order_id) {
        $this->send_customer_order_sms($order_id, 'order_pending');
    }

    public function send_customer_sms_processing($order_id) {
        $this->send_customer_order_sms($order_id, 'order_processing');
    }

    public function send_customer_sms_on_hold($order_id) {
        $this->send_customer_order_sms($order_id, 'order_on_hold');
    }

    public function send_customer_sms_completed($order_id) {
        $this->send_customer_order_sms($order_id, 'order_completed');
    }

    public function send_customer_sms_cancelled($order_id) {
        $this->send_customer_order_sms($order_id, 'order_cancelled');
    }

    public function send_customer_sms_refunded($order_id) {
        $this->send_customer_order_sms($order_id, 'order_refunded');
    }

    public function send_customer_sms_failed($order_id) {
        $this->send_customer_order_sms($order_id, 'order_failed');
    }

    private function send_customer_order_sms($order_id, $event_key) {
        if (!$order_id || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $settings = get_option('limosms_customer_sms_events', []);

        if (empty($settings[$event_key]) || !is_array($settings[$event_key])) {
            return;
        }

        $event_settings = $settings[$event_key];

        if (($event_settings['enabled'] ?? 'no') !== 'yes') {
            return;
        }

        $pattern_id = absint($event_settings['otp_id'] ?? 0);

        if (!$pattern_id) {
            return;
        }

        $pattern_map = $event_settings['pattern_map'] ?? [];

        if (empty($pattern_map) || !is_array($pattern_map)) {
            return;
        }

        $phone = LimoSMS_Sender::normalize_mobile_number($order->get_billing_phone());

        if (!$phone) {
            return;
        }

        $data = $this->get_order_sms_data_source($order);

        $values = [];

        foreach ($pattern_map as $index => $token) {
            $token = trim((string) $token, "{} \t\n\r\0\x0B");
            $value = $data[$token] ?? '-';

            if ($value === '') {
                $value = '-';
            }

            $values[(int) $index] = (string) $value;
        }

        ksort($values, SORT_NUMERIC);
        $values = array_values($values);

        LimoSMS_Sender::send_pattern_sms($phone, $pattern_id, $values);
    }

    private function get_order_sms_data_source($order) {
        $items = [];
        $items_with_qty = [];
        $count = 0;

        foreach ($order->get_items() as $item) {
            $qty = (int) $item->get_quantity();
            $name = $item->get_name();

            $count += $qty;
            $items[] = $name;
            $items_with_qty[] = $name . ' x' . $qty;
        }

        $date = $order->get_date_created();
        $order_date = $date ? $date->date_i18n('Y/m/d H:i') : current_time('Y/m/d H:i');

        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name  = $order->get_billing_last_name();
        $billing_phone      = $order->get_billing_phone();
        $customer_name      = trim($billing_first_name . ' ' . $billing_last_name);

        return [
            'customer_name'        => $customer_name,
            'first_name'           => $billing_first_name,
            'last_name'            => $billing_last_name,
            'phone'                => LimoSMS_Sender::normalize_mobile_number($billing_phone),

            'billing_first_name'   => $billing_first_name,
            'billing_last_name'    => $billing_last_name,
            'billing_phone'        => LimoSMS_Sender::normalize_mobile_number($billing_phone),
            'billing_mobile'       => LimoSMS_Sender::normalize_mobile_number($billing_phone),

            'order_id'             => (string) $order->get_id(),
            'order_number'         => (string) $order->get_order_number(),
            'order_date'           => $order_date,
            'items_count'          => (string) $count,
            'order_items_count'    => (string) $count,
            'all_items'            => implode('، ', $items_with_qty),
            'order_items'          => implode('، ', $items),
            'order_items_with_qty' => implode('، ', $items_with_qty),
            'price'                => wp_strip_all_tags($order->get_formatted_order_total()),
            'order_total'          => wp_strip_all_tags($order->get_formatted_order_total()),
            'site_name'            => get_bloginfo('name'),
        ];
    }
}
