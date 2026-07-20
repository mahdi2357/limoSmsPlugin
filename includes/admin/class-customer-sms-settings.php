<?php
if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_Customer_SMS_Settings
{
    /**
     * @var LimoSMS_API
     */
    private $api;

    public function __construct()
    {
        $this->api = new LimoSMS_API();

        add_action('wp_ajax_limosms_customer_get_patterns', array($this, 'ajax_get_patterns'));
        add_action('wp_ajax_limosms_customer_get_pattern_detail', array($this, 'ajax_get_pattern_detail'));
    }

    public static function get_events_settings($event_key = '')
    {
        $options = get_option('limosms_customer_sms_events', array());

        if (!is_array($options)) {
            $options = array();
        }

        if ($event_key === '') {
            return $options;
        }

        return isset($options[$event_key]) && is_array($options[$event_key])
            ? $options[$event_key]
            : array();
    }

    public function ajax_get_patterns()
    {
        check_ajax_referer('limosms_customer_sms_nonce', 'nonce');

        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'), 403);
        }

        $response = $this->api->get_patterns();

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()), 500);
        }

        if (!is_array($response)) {
            wp_send_json_error(array('message' => 'پاسخ نامعتبر از API'), 500);
        }

        $items = array();
        if (isset($response['data']['data']) && is_array($response['data']['data'])) {
            $items = $response['data']['data'];
        } elseif (isset($response['data']) && is_array($response['data'])) {
            $items = $response['data'];
        } elseif (is_array($response)) {
            $items = $response;
        }

        $normalized = array();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $pattern_code = isset($item['patternCode']) ? (string) $item['patternCode'] : '';
            if ($pattern_code === '' && isset($item['pattern_id'])) {
                $pattern_code = (string) $item['pattern_id'];
            }
            if ($pattern_code === '' && isset($item['id'])) {
                $pattern_code = (string) $item['id'];
            }
            if ($pattern_code === '') {
                continue;
            }

            $pattern_title = '';
            if (isset($item['patternName'])) {
                $pattern_title = (string) $item['patternName'];
            } elseif (isset($item['title'])) {
                $pattern_title = (string) $item['title'];
            } elseif (isset($item['pattern_title'])) {
                $pattern_title = (string) $item['pattern_title'];
            } elseif (isset($item['name'])) {
                $pattern_title = (string) $item['name'];
            } elseif (isset($item['pattern_name'])) {
                $pattern_title = (string) $item['pattern_name'];
            }

            $pattern_text = '';
            if (isset($item['message'])) {
                $pattern_text = (string) $item['message'];
            } elseif (isset($item['text'])) {
                $pattern_text = (string) $item['text'];
            } elseif (isset($item['pattern'])) {
                $pattern_text = (string) $item['pattern'];
            }

            $normalized[] = array(
                'patternCode'  => $pattern_code,
                'patternTitle' => $pattern_title,
                'message'      => $pattern_text,
            );
        }

        wp_send_json_success($normalized);
    }


    public function ajax_get_pattern_detail()
    {
        check_ajax_referer('limosms_customer_sms_nonce', 'nonce');

        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'), 403);
        }

        $pattern_code = isset($_POST['pattern_code']) ? sanitize_text_field(wp_unslash($_POST['pattern_code'])) : '';

        if ($pattern_code === '') {
            wp_send_json_error(array('message' => 'کد پترن ارسال نشده'), 400);
        }

        $response = $this->api->get_pattern_detail($pattern_code);

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()), 500);
        }

        if (!is_array($response)) {
            wp_send_json_error(array('message' => 'پترن یافت نشد'), 404);
        }

        $items = array();
        if (isset($response['data']['data']) && is_array($response['data']['data'])) {
            $items = $response['data']['data'];
        } elseif (isset($response['data']) && is_array($response['data'])) {
            $items = $response['data'];
        }

        foreach ($items as $item) {
            $code = isset($item['patternCode']) ? (string) $item['patternCode'] : (isset($item['id']) ? (string) $item['id'] : '');

            if ($code === $pattern_code) {
                $message = isset($item['message']) ? (string) $item['message'] : '';

                wp_send_json_success(array(
                    'id'      => $code,
                    'message' => $message,
                ));
            }
        }

        wp_send_json_error(array('message' => 'پترن یافت نشد'), 404);
    }



}
