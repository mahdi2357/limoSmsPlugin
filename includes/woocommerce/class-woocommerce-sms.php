<?php

class LimoSMS_WooCommerce_SMS {

    public function __construct() {

        add_action(
            'woocommerce_checkout_order_processed',
            [$this,'send_admin_sms_on_new_order'],
            10,
            1
        );

        add_action('woocommerce_order_status_pending', [$this,'send_admin_sms_on_status_pending'],10,1);
        add_action('woocommerce_order_status_processing', [$this,'send_admin_sms_on_status_processing'],10,1);
        add_action('woocommerce_order_status_on-hold', [$this,'send_admin_sms_on_status_on_hold'],10,1);
        add_action('woocommerce_order_status_completed', [$this,'send_admin_sms_on_status_completed'],10,1);
        add_action('woocommerce_order_status_cancelled', [$this,'send_admin_sms_on_status_cancelled'],10,1);
        add_action('woocommerce_order_status_refunded', [$this,'send_admin_sms_on_status_refunded'],10,1);
        add_action('woocommerce_order_status_failed', [$this,'send_admin_sms_on_status_failed'],10,1);

        add_action('woocommerce_low_stock', [$this,'send_admin_sms_on_low_stock'],10,1);
        add_action('woocommerce_no_stock', [$this,'send_admin_sms_on_out_of_stock'],10,1);
    }

    public function send_admin_sms_on_new_order($order_id){
        $this->send_order_event_sms($order_id,'new_order');
    }

    public function send_admin_sms_on_status_pending($order_id){
        $this->send_order_event_sms($order_id,'new_order');
    }

    public function send_admin_sms_on_status_processing($order_id){
        $this->send_order_event_sms($order_id,'new_order');
    }

    public function send_admin_sms_on_status_on_hold($order_id){
        $this->send_order_event_sms($order_id,'order_on_hold');
    }

    public function send_admin_sms_on_status_completed($order_id){
        $this->send_order_event_sms($order_id,'order_completed');
    }

    public function send_admin_sms_on_status_cancelled($order_id){
        $this->send_order_event_sms($order_id,'order_cancelled');
    }

    public function send_admin_sms_on_status_refunded($order_id){
        $this->send_order_event_sms($order_id,'order_refunded');
    }

    public function send_admin_sms_on_status_failed($order_id){
        $this->send_order_event_sms($order_id,'order_failed');
    }

    private function send_order_event_sms($order_id,$event_key){
        $events_settings = get_option('limosms_admin_sms_events',[]);

        if(!$order_id || !function_exists('wc_get_order')){
            return;
        }

        $order = wc_get_order($order_id);

        if(!$order){
            return;
        }

        $sent_meta_key = '_limosms_admin_sms_sent_'.sanitize_key($event_key);

        if($order->get_meta($sent_meta_key) === 'yes'){
            return;
        }

        if(!isset($events_settings[$event_key])){
            return;
        }

        if(($events_settings[$event_key]['enabled'] ?? 'no') !== 'yes'){
            return;
        }

        $pattern_id = absint($events_settings[$event_key]['otp_id'] ?? 0);

        if(!$pattern_id){
            return;
        }

        $pattern_map = $events_settings[$event_key]['pattern_map'] ?? [];

        if(empty($pattern_map) || !is_array($pattern_map)){
            return;
        }

        $phones = get_option('limosms_admin_phones',[]);

        if(!is_array($phones)){
            $phones = explode(',',$phones);
        }

        $phones = array_filter(array_map('trim',$phones));

        if(empty($phones)){
            return;
        }

        $data = $this->get_order_sms_data_source($order,$event_key);

        ksort($pattern_map,SORT_NUMERIC);

        $values = [];

        foreach($pattern_map as $index=>$token){
            $token = trim((string) $token, "{} \t\n\r\0\x0B");

            $value = $data[$token] ?? '-';

            if($value === ''){
                $value='-';
            }

            $values[(int)$index] = (string) $value;
        }

        ksort($values, SORT_NUMERIC);
        $values = array_values($values);

        $sent = false;

        foreach($phones as $phone){
            $phone = LimoSMS_Sender::normalize_mobile_number($phone);

            if(!$phone){
                continue;
            }

            $result = LimoSMS_Sender::send_pattern_sms($phone,$pattern_id,$values);

            if(is_array($result) && !empty($result['success'])){
                $sent = true;
            }
        }

        if($sent){
            $order->update_meta_data($sent_meta_key,'yes');
            $order->save();
        }
    }

    private function get_order_sms_data_source($order, $event_key){
        $items = [];
        $items_with_qty = [];
        $items_full = [];
        $count = 0;

        foreach ($order->get_items() as $item) {
            $qty  = (int) $item->get_quantity();
            $name = $item->get_name();

            $count += $qty;
            $items[] = $name;
            $items_with_qty[] = $name . ' x' . $qty;

            $product = $item->get_product();
            if ($product) {
                $items_full[] = $product->get_formatted_name() . ' x' . $qty;
            } else {
                $items_full[] = $name . ' x' . $qty;
            }
        }

        $date = $order->get_date_created();
        $order_date = $date ? $date->date_i18n('Y/m/d H:i') : current_time('Y/m/d H:i');

        $billing_first_name = (string) $order->get_billing_first_name();
        $billing_last_name  = (string) $order->get_billing_last_name();
        $billing_phone_raw  = (string) $order->get_billing_phone();
        $billing_phone_norm = LimoSMS_Sender::normalize_mobile_number($billing_phone_raw);
        $customer_name      = trim($billing_first_name . ' ' . $billing_last_name);

        $payment_method_title  = (string) $order->get_payment_method_title();
        $shipping_method_title = (string) $order->get_shipping_method();
        $status_name           = wc_get_order_status_name($order->get_status());

        $payment_url = '';
        if ($order->needs_payment()) {
            $payment_url = $order->get_checkout_payment_url();
        }

        // در صورت وجود افزونه رهگیری، بعداً میتونی از meta واقعی بخونی
        $tracking_code = (string) $order->get_meta('_tracking_code');
        $tracking_url  = (string) $order->get_meta('_tracking_url');

        return [
            // عمومی
            'site_name'            => get_bloginfo('name'),
            'customer_name'        => $customer_name,
            'first_name'           => $billing_first_name,
            'last_name'            => $billing_last_name,
            'phone'                => $billing_phone_norm,

            // سفارش
            'order_id'             => (string) $order->get_id(),
            'order_number'         => (string) $order->get_order_number(),
            'order_parent_id'      => (string) $order->get_parent_id(),
            'order_status'         => (string) $status_name,
            'order_date'           => (string) $order_date,
            'transaction_id'       => (string) $order->get_transaction_id(),
            'customer_note'        => (string) $order->get_customer_note(),
            'payment_method'       => $payment_method_title,
            'shipping_method'      => $shipping_method_title,
            'payment_url'          => (string) $payment_url,

            // قیمت/آیتم
            'price'                => wp_strip_all_tags($order->get_formatted_order_total()),
            'order_total'          => wp_strip_all_tags($order->get_formatted_order_total()),
            'items_count'          => (string) $count,
            'order_items_count'    => (string) $count,
            'all_items'            => implode('، ', $items_with_qty),
            'order_items'          => implode('، ', $items),
            'order_items_with_qty' => implode('، ', $items_with_qty),
            'order_items_full'     => implode('، ', $items_full),

            // Billing
            'billing_first_name'   => $billing_first_name,
            'billing_last_name'    => $billing_last_name,
            'billing_phone'        => $billing_phone_norm,
            'billing_mobile'       => $billing_phone_norm,
            'billing_email'        => (string) $order->get_billing_email(),
            'billing_company'      => (string) $order->get_billing_company(),
            'billing_country'      => (string) $order->get_billing_country(),
            'billing_state'        => (string) $order->get_billing_state(),
            'billing_city'         => (string) $order->get_billing_city(),
            'billing_address_1'    => (string) $order->get_billing_address_1(),
            'billing_address_2'    => (string) $order->get_billing_address_2(),
            'billing_postcode'     => (string) $order->get_billing_postcode(),

            // Shipping
            'shipping_first_name'  => (string) $order->get_shipping_first_name(),
            'shipping_last_name'   => (string) $order->get_shipping_last_name(),
            'shipping_company'     => (string) $order->get_shipping_company(),
            'shipping_country'     => (string) $order->get_shipping_country(),
            'shipping_state'       => (string) $order->get_shipping_state(),
            'shipping_city'        => (string) $order->get_shipping_city(),
            'shipping_address_1'   => (string) $order->get_shipping_address_1(),
            'shipping_address_2'   => (string) $order->get_shipping_address_2(),
            'shipping_postcode'    => (string) $order->get_shipping_postcode(),

            // Tracking
            'tracking_code'        => $tracking_code,
            'tracking_url'         => $tracking_url,
        ];
    }


    public function send_admin_sms_on_low_stock($product){
        $this->send_stock_event_sms($product,'low_stock');
    }

    public function send_admin_sms_on_out_of_stock($product){
        $this->send_stock_event_sms($product,'out_of_stock');
    }

    private function send_stock_event_sms($product,$event_key){

        if(!$product instanceof WC_Product){
            return;
        }

        $events_settings = get_option('limosms_admin_sms_events',[]);

        if(empty($events_settings[$event_key]['enabled'])){
            return;
        }

        $pattern_id = absint($events_settings[$event_key]['otp_id'] ?? 0);

        if(!$pattern_id){
            return;
        }

        $pattern_map = $events_settings[$event_key]['pattern_map'] ?? [];

        if(empty($pattern_map)){
            return;
        }

        $phones = get_option('limosms_admin_phones',[]);

        if(!is_array($phones)){
            $phones = explode(',',$phones);
        }

        $phones = array_filter(array_map('trim',$phones));

        $data = $this->get_stock_sms_data_source($product,$event_key);

        $values=[];

        foreach($pattern_map as $index=>$token){
            $token = trim((string) $token, "{} \t\n\r\0\x0B");
            $values[(int)$index] = (string) ($data[$token] ?? '-');
        }

        ksort($values, SORT_NUMERIC);
        $values = array_values($values);

        foreach($phones as $phone){
            $phone = LimoSMS_Sender::normalize_mobile_number($phone);

            if(!$phone){
                continue;
            }

            LimoSMS_Sender::send_pattern_sms($phone,$pattern_id,$values);
        }
    }

    private function get_stock_sms_data_source($product,$event_key){
        return [
            'product_name'   => $product->get_name(),
            'sku'            => $product->get_sku(),
            'stock_quantity' => $product->get_stock_quantity(),
            'product_id'     => $product->get_id(),
            'product_type'   => $product->get_type(),
            'site_name'      => get_bloginfo('name')
        ];
    }
}
