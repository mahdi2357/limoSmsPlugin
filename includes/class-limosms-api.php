<?php
if (!defined('ABSPATH')) {
    exit;
}

class LimoSMS_API
{
    private $base_url = 'https://api.limosms.com/v1/';

    private function get_api_key()
    {
        $api_key = get_option('limosms_api_key', '');
        return is_string($api_key) ? trim($api_key) : '';
    }

    private function get_headers()
    {
        return array(
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'Apikey'       => $this->get_api_key(),
        );
    }

    private function request($method, $endpoint, $payload = array())
    {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            return new WP_Error('limosms_no_api_key', 'API Key تنظیم نشده است.');
        }

        $url = trailingslashit($this->base_url) . ltrim($endpoint, '/');

        $args = array(
            'method'  => strtoupper($method),
            'timeout' => 20,
            'headers' => $this->get_headers(),
        );

        if (!empty($payload) && in_array(strtoupper($method), array('POST', 'PUT', 'PATCH'), true)) {
            $args['body'] = wp_json_encode($payload);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);
        $json   = json_decode($body, true);

        if ($status < 200 || $status >= 300) {
            $message = is_array($json) ? ($json['message'] ?? $json['error'] ?? 'API Error') : 'API Error';
            return new WP_Error('limosms_api_error', $message, array(
                'status' => $status,
                'body'   => $body,
            ));
        }

        return is_array($json) ? $json : array();
    }

    public function get_patterns() {
        $api_key = get_option('limosms_api_key', '');

        if (empty($api_key)) {
            return new WP_Error('limosms_no_api_key', 'API Key تنظیم نشده است');
        }

        $response = wp_remote_post(
            'https://api.limosms.com/api/getpatterns',
            array(
                'timeout' => 10,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'ApiKey'       => trim($api_key), // دقیقاً همین
                ),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            return new WP_Error('limosms_invalid_response', 'پاسخ نامعتبر از API', array('body' => $body));
        }

        return $data;
    }

    public function get_pattern($pattern_code)
    {
        $pattern_code = sanitize_text_field((string) $pattern_code);
        if ($pattern_code === '') {
            return new WP_Error('limosms_invalid_pattern', 'Pattern code نامعتبر است.');
        }

        $result = $this->request('GET', 'patterns/' . rawurlencode($pattern_code));
        if (is_wp_error($result)) {
            return $result;
        }

        $data = isset($result['data']) && is_array($result['data']) ? $result['data'] : $result;

        return array(
            'pattern_code' => $pattern_code,
            'text'         => (string) ($data['text'] ?? $data['body'] ?? $data['message'] ?? ''),
            'parameters'   => is_array($data['parameters'] ?? null) ? $data['parameters'] : array(),
            'raw'          => $data,
        );
    }
}
