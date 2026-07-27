<?php
/**
 * Plugin Name: لیمو اس ام اس
 * Description: اتصال وردپرس به سامانه پیامکی لیمو اس ام اس
 * Version: 1.1.0
 * Author: LimoSMS
 * Author URI: https://limosms.com
 * Text Domain: limosms
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LIMOSMS_VERSION', '1.1.0');
define('LIMOSMS_PATH', plugin_dir_path(__FILE__));
define('LIMOSMS_URL', plugin_dir_url(__FILE__));

function limosms_register_gravity_forms_submission_hook()
{
    if (!class_exists('GFAPI')) {
        return;
    }

    static $registered = false;
    if ($registered) {
        return;
    }

    $registered = true;
    error_log('LimoSMS Gravity Forms: top-level hook registered');
    add_action('gform_after_submission', 'limosms_handle_gravity_forms_submission', 10, 2);
}

function limosms_handle_gravity_forms_submission($entry, $form)
{
    error_log('LimoSMS Gravity Forms: top-level hook fired for form ' . ($form['id'] ?? 0));

    if (class_exists('LimoSMS_Gravity_Forms_SMS')) {
        $handler = new LimoSMS_Gravity_Forms_SMS();
        $handler->send_gravity_forms_sms($entry, $form);
    }
}

add_action('plugins_loaded', 'limosms_register_gravity_forms_submission_hook', 20);
add_action('init', 'limosms_register_gravity_forms_submission_hook', 20);
add_action('wp_loaded', 'limosms_register_gravity_forms_submission_hook', 20);

// لود کلاس اصلی افزونه
require_once LIMOSMS_PATH . 'includes/class-limosms.php';

// لود کردن کتابخانه آپدیت‌کننده با آدرس دامنه اصلی
if (file_exists(LIMOSMS_PATH . 'includes/libs/plugin-update-checker/plugin-update-checker.php')) {
    require_once LIMOSMS_PATH . 'includes/libs/plugin-update-checker/plugin-update-checker.php';

    $limoSmsUpdater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://pluginupdate.limosms.com/limoSmsPlugin.json',
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
