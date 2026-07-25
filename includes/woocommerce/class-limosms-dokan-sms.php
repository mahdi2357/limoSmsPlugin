<?php

if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Dokan_SMS {

    public function __construct() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_action('woocommerce_checkout_order_processed', [$this, 'send_seller_sms_on_new_order'], 10, 1);
        add_action('woocommerce_order_status_pending', [$this, 'send_seller_sms_on_new_order'], 10, 1);
        add_action('woocommerce_order_status_processing', [$this, 'send_seller_sms_on_processing'], 10, 1);
        add_action('woocommerce_order_status_completed', [$this, 'send_seller_sms_on_completed'], 10, 1);
        add_action('woocommerce_order_status_cancelled', [$this, 'send_seller_sms_on_cancelled'], 10, 1);
        add_action('woocommerce_order_status_refunded', [$this, 'send_seller_sms_on_refunded'], 10, 1);
        add_action('woocommerce_order_status_failed', [$this, 'send_seller_sms_on_failed'], 10, 1);
    }

    public function send_seller_sms_on_new_order($order_id) {
        $this->send_seller_order_sms($order_id, 'seller_new_order');
    }

    public function send_seller_sms_on_processing($order_id) {
        $this->send_seller_order_sms($order_id, 'seller_order_processing');
    }

    public function send_seller_sms_on_completed($order_id) {
        $this->send_seller_order_sms($order_id, 'seller_order_completed');
    }

    public function send_seller_sms_on_cancelled($order_id) {
        $this->send_seller_order_sms($order_id, 'seller_order_cancelled');
    }

    public function send_seller_sms_on_refunded($order_id) {
        $this->send_seller_order_sms($order_id, 'seller_order_refunded');
    }

    public function send_seller_sms_on_failed($order_id) {
        $this->send_seller_order_sms($order_id, 'seller_order_failed');
    }

    private function send_seller_order_sms($order_id, $event_key) {
        if ( ! LimoSMS_Connection_Settings::is_woocommerce_sms_enabled() ) {
            return;
        }

        if (!$order_id || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $settings = get_option('limosms_seller_sms_events', array());
        if (empty($settings[$event_key]) || !is_array($settings[$event_key])) {
            return;
        }

        $event_settings = $settings[$event_key];
        if (empty($event_settings['enabled']) || $event_settings['enabled'] !== 'yes') {
            return;
        }

        $otp_id = absint($event_settings['otp_id'] ?? 0);
        if (!$otp_id) {
            return;
        }

        $pattern_map = $event_settings['pattern_map'] ?? array();
        $pattern_text = trim((string) ($event_settings['pattern_text'] ?? ''));
        $has_variables = preg_match('/\{(\d+)\}/', $pattern_text) === 1;

        if ($has_variables && empty($pattern_map)) {
            return;
        }

        $seller_ids = $this->get_seller_ids_from_order($order);
        if (empty($seller_ids)) {
            return;
        }

        $sent = false;
        foreach ($seller_ids as $seller_id) {
            $phone = $this->get_seller_phone($seller_id);
            if (!$phone) {
                continue;
            }

            $tokens = $this->build_seller_pattern_tokens($order, $seller_id, $pattern_map);
            if (empty($tokens)) {
                continue;
            }

            $result = LimoSMS_Sender::send_pattern_sms($phone, $otp_id, $tokens);
            if (is_array($result) && !empty($result['success'])) {
                $sent = true;
            }
        }

        if ($sent) {
            $meta_key = '_limosms_seller_sms_sent_' . sanitize_key($event_key);
            $order->update_meta_data($meta_key, 'yes');
            $order->save();
        }
    }

    private function get_seller_ids_from_order($order) {
        $seller_ids = array();

        $order_seller_id = 0;
        if (function_exists('dokan_get_seller_id_by_order')) {
            $order_seller_id = absint(dokan_get_seller_id_by_order($order->get_id()));
            if ($order_seller_id > 0) {
                $seller_ids[] = $order_seller_id;
            }
        }

        foreach ($order->get_items() as $item) {
            if (!is_object($item)) {
                continue;
            }

            $vendor_ids = array();
            $vendor_meta_keys = array(
                '_vendor_id',
                '_vendor_user_id',
                '_dokan_vendor_id',
                'vendor_id',
                'seller_id',
                'user_id',
            );

            foreach ($vendor_meta_keys as $meta_key) {
                $value = $item->get_meta($meta_key, true);
                if ($value !== '' && $value !== false) {
                    $vendor_ids[] = absint($value);
                }
            }

            $product = $item->get_product();
            if ($product) {
                if (function_exists('dokan_get_seller_id_by_product')) {
                    $vendor_id = absint(dokan_get_seller_id_by_product($product->get_id()));
                    if ($vendor_id > 0) {
                        $vendor_ids[] = $vendor_id;
                    }
                }

                if (!$vendor_ids && method_exists($product, 'get_post_data')) {
                    $post = $product->get_post_data();
                    if ($post && !empty($post->post_author)) {
                        $vendor_ids[] = absint($post->post_author);
                    }
                }
            }

            foreach ($vendor_ids as $vendor_id) {
                if ($vendor_id > 0) {
                    $seller_ids[] = $vendor_id;
                }
            }
        }

        return array_values(array_unique(array_filter($seller_ids)));
    }

    private function get_seller_phone($seller_id) {
        $seller_id = absint($seller_id);
        if (!$seller_id) {
            return '';
        }

        $phone_keys = array(
            'billing_phone',
            'billing_mobile',
            'phone',
            'mobile',
            'seller_phone',
            'dokan_phone',
        );

        foreach ($phone_keys as $key) {
            $value = get_user_meta($seller_id, $key, true);
            if (!empty($value)) {
                $value = LimoSMS_Sender::normalize_mobile_number($value);
                if (!empty($value)) {
                    return $value;
                }
            }
        }

        if (function_exists('dokan_get_store_info')) {
            $store_info = dokan_get_store_info($seller_id);
            if (!empty($store_info['phone'])) {
                $value = LimoSMS_Sender::normalize_mobile_number($store_info['phone']);
                if (!empty($value)) {
                    return $value;
                }
            }
        }

        return '';
    }

    private function find_item_seller_id($item, $product) {
        if (!is_object($item)) {
            return 0;
        }

        $vendor_meta_keys = array('_vendor_id', '_vendor_user_id', 'vendor_id', 'seller_id', 'user_id');
        foreach ($vendor_meta_keys as $meta_key) {
            $value = $item->get_meta($meta_key, true);
            if ($value !== '' && $value !== false) {
                return absint($value);
            }
        }

        if ($product) {
            if (function_exists('dokan_get_seller_id_by_product')) {
                $vendor_id = dokan_get_seller_id_by_product($product->get_id());
                if ($vendor_id) {
                    return absint($vendor_id);
                }
            }

            if (method_exists($product, 'get_post_data')) {
                $post = $product->get_post_data();
                if ($post && !empty($post->post_author)) {
                    return absint($post->post_author);
                }
            }
        }

        return 0;
    }

    private function build_seller_pattern_tokens($order, $seller_id, $pattern_map) {
        $seller_id = absint($seller_id);
        $seller = get_userdata($seller_id);
        $seller_name = $seller ? trim($seller->display_name) : '';
        $store_name = '';

        if (function_exists('dokan_get_store_info')) {
            $store_info = dokan_get_store_info($seller_id);
            if (!empty($store_info['store_name'])) {
                $store_name = sanitize_text_field($store_info['store_name']);
            }
        }

        if ($store_name === '') {
            $store_name = $seller_name;
        }

        $seller_phone = $this->get_seller_phone($seller_id);

        $items = array();
        $items_with_qty = array();
        $count = 0;
        $sellers_items_found = false;

        foreach ($order->get_items() as $item) {
            if (!is_object($item)) {
                continue;
            }

            $product = $item->get_product();
            $item_seller_id = $this->find_item_seller_id($item, $product);

            if ($item_seller_id > 0 && $item_seller_id !== $seller_id) {
                continue;
            }

            $qty = (int) $item->get_quantity();
            $name = (string) $item->get_name();
            $count += $qty;
            $items[] = $name;
            $items_with_qty[] = $name . ' x' . $qty;
            $sellers_items_found = true;
        }

        if (!$sellers_items_found) {
            foreach ($order->get_items() as $item) {
                if (!is_object($item)) {
                    continue;
                }

                $qty = (int) $item->get_quantity();
                $name = (string) $item->get_name();
                $count += $qty;
                $items[] = $name;
                $items_with_qty[] = $name . ' x' . $qty;
            }
        }

        $date = $order->get_date_created();
        $order_date = $date ? $date->date_i18n('Y/m/d H:i') : current_time('Y/m/d H:i');
        $billing_first_name = (string) $order->get_billing_first_name();
        $billing_last_name  = (string) $order->get_billing_last_name();
        $billing_phone      = LimoSMS_Sender::normalize_mobile_number($order->get_billing_phone());

        $available_tokens = array(
            'seller_id'           => (string) $seller_id,
            'seller_name'         => $seller_name,
            'seller_store_name'   => $store_name,
            'seller_phone'        => $seller_phone,

            'order_id'            => (string) $order->get_id(),
            'order_number'        => (string) $order->get_order_number(),
            'order_status'        => wc_get_order_status_name($order->get_status()),
            'order_total'         => wp_strip_all_tags($order->get_formatted_order_total()),
            'order_date'          => $order_date,
            'payment_method'      => $order->get_payment_method_title(),
            'shipping_method'     => (string) $order->get_shipping_method(),
            'customer_name'       => trim($billing_first_name . ' ' . $billing_last_name),
            'customer_phone'      => $billing_phone,
            'billing_first_name'  => $billing_first_name,
            'billing_last_name'   => $billing_last_name,
            'billing_phone'       => $billing_phone,
            'billing_mobile'      => $billing_phone,
            'billing_email'       => (string) $order->get_billing_email(),
            'billing_company'     => (string) $order->get_billing_company(),
            'billing_country'     => (string) $order->get_billing_country(),
            'billing_state'       => (string) $order->get_billing_state(),
            'billing_city'        => (string) $order->get_billing_city(),
            'billing_address_1'   => (string) $order->get_billing_address_1(),
            'billing_address_2'   => (string) $order->get_billing_address_2(),
            'billing_postcode'    => (string) $order->get_billing_postcode(),
            'shipping_first_name' => (string) $order->get_shipping_first_name(),
            'shipping_last_name'  => (string) $order->get_shipping_last_name(),
            'shipping_company'    => (string) $order->get_shipping_company(),
            'shipping_country'    => (string) $order->get_shipping_country(),
            'shipping_state'      => (string) $order->get_shipping_state(),
            'shipping_city'       => (string) $order->get_shipping_city(),
            'shipping_address_1'  => (string) $order->get_shipping_address_1(),
            'shipping_address_2'  => (string) $order->get_shipping_address_2(),
            'shipping_postcode'   => (string) $order->get_shipping_postcode(),
            'items_count'         => (string) $count,
            'order_items_count'   => (string) $count,
            'order_items'         => implode('، ', $items),
            'order_items_with_qty'=> implode('، ', $items_with_qty),
            'all_items'           => implode('، ', $items_with_qty),
            'site_name'           => get_bloginfo('name'),
        );

        $result = array();
        foreach ($pattern_map as $index => $token_key) {
            $index = absint($index);
            $token_key = trim((string) $token_key);
            $token_key = trim($token_key, '{} ');
            if ($token_key === '') {
                continue;
            }

            $result[$index] = isset($available_tokens[$token_key]) ? (string) $available_tokens[$token_key] : '-';
        }

        ksort($result, SORT_NUMERIC);
        return array_values($result);
    }
}
