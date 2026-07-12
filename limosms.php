<?php
/**
 * Plugin Name: لیمو اس ام اس
 * Description: اتصال وردپرس به سامانه پیامکی لیمو اس ام اس
 * Version: 1.0.0
 * Author: LimoSMS
 * Author URI: https://limosms.com
 * Text Domain: limosms
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LIMOSMS_VERSION', '1.0.0');
define('LIMOSMS_PATH', plugin_dir_path(__FILE__));
define('LIMOSMS_URL', plugin_dir_url(__FILE__));

// لود کلاس اصلی افزونه
require_once LIMOSMS_PATH . 'includes/class-limosms.php';

// لود کردن کتابخانه آپدیت‌کننده با آدرس دامنه اصلی
if (file_exists(LIMOSMS_PATH . 'includes/libs/plugin-update-checker/plugin-update-checker.php')) {
    require_once LIMOSMS_PATH . 'includes/libs/plugin-update-checker/plugin-update-checker.php';

    $limoSmsUpdater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://limosms.com/updates/limoSmsPlugin.json', // آدرس جدید روی دامنه اصلی
        __FILE__,
        'limoSmsPlugin'
    );
}

function limosms_run()
{
    $plugin = new LimoSMS();
    $plugin->run();
}

limosms_run();
