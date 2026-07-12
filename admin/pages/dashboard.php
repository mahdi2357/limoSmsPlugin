<?php

if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

$tabs = [
    'connection-settings' => 'تنظیمات اتصال',
    'sms-pattern-management' => 'الگوهای پیامک',
    'send-test-sms' => ' ارسال پیامک تست',
    'admin-sms' => 'پیامک مدیر',
    'customer-sms' => 'پیامک مشتری',
//    'seller-sms' => 'پیامک فروشنده',
];

$template_path = LIMOSMS_PATH . 'templates/admin-dashboard.php';

if (file_exists($template_path)) {
    include $template_path;
}
