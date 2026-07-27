<?php

$connection_tab = new LimoSMS_Connection_Settings();
$status = $connection_tab->get_connection_status();

$is_connected = !empty($status['success']);

$first_name = isset($status['result']['firstName']) ? $status['result']['firstName'] : '';
$last_name  = isset($status['result']['lastName']) ? $status['result']['lastName'] : '';

$fullname = trim($first_name . ' ' . $last_name);
$fullname = !empty($fullname) ? $fullname : '-';

$credit    = isset($status['result']['credit']) ? $status['result']['credit'] : 0;
$sms_count = isset($status['result']['smsCount']) ? $status['result']['smsCount'] : 0;

$api_key = get_option('limosms_api_key', '');
$woocommerce_sms_enabled = LimoSMS_Connection_Settings::normalize_enabled_setting( get_option('limosms_woocommerce_sms_enabled', 'yes') );
$digits_sms_enabled = LimoSMS_Connection_Settings::normalize_enabled_setting( get_option('limosms_digits_sms_enabled', 'yes') );
$gravity_forms_sms_enabled = LimoSMS_Connection_Settings::normalize_enabled_setting( get_option('limosms_gravity_forms_sms_enabled', 'yes') );

?>

<div class="limosms-dashboard-stats">

    <div class="limosms-stat-card">
        <div class="limosms-stat-card__content">
            <span class="limosms-stat-card__label">
                <?php echo esc_html__('وضعیت اتصال', 'limosms'); ?>
            </span>

            <strong class="limosms-stat-card__value <?php echo $is_connected ? 'limosms-stat-card__value--success' : 'limosms-stat-card__value--danger'; ?>">
                <?php echo $is_connected ? esc_html__('متصل شده', 'limosms') : esc_html__('متصل نیست', 'limosms'); ?>
            </strong>
        </div>

        <span class="dashicons <?php echo $is_connected ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?> limosms-stat-card__icon"></span>
    </div>


    <div class="limosms-stat-card">
        <div class="limosms-stat-card__content">
            <span class="limosms-stat-card__label">
                <?php echo esc_html__('نام و نام خانوادگی', 'limosms'); ?>
            </span>

            <strong class="limosms-stat-card__value">
                <?php echo esc_html($fullname); ?>
            </strong>
        </div>

        <span class="dashicons dashicons-admin-users limosms-stat-card__icon"></span>
    </div>


    <div class="limosms-stat-card">
        <div class="limosms-stat-card__content">
            <span class="limosms-stat-card__label">
                <?php echo esc_html__('میزان اعتبار (ریال)', 'limosms'); ?>
            </span>

            <strong class="limosms-stat-card__value">
                <?php echo esc_html(number_format($credit)); ?>
            </strong>
        </div>

        <span class="dashicons dashicons-money-alt limosms-stat-card__icon"></span>
    </div>


    <div class="limosms-stat-card">
        <div class="limosms-stat-card__content">
            <span class="limosms-stat-card__label">
                <?php echo esc_html__('تعداد پیامک', 'limosms'); ?>
            </span>

            <strong class="limosms-stat-card__value">
                <?php echo esc_html(number_format($sms_count)); ?>
            </strong>
        </div>

        <span class="dashicons dashicons-email limosms-stat-card__icon"></span>
    </div>

</div>


<form id="limosms-settings-form" method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">

    <label for="limosms_api_key">کلید API</label>

    <input
            type="text"
            id="limosms_api_key"
            name="limosms_api_key"
            value="<?php echo esc_attr($api_key); ?>"
            maxlength="50"
            data-initial="<?php echo esc_attr($api_key); ?>"
            dir="ltr"
            inputmode="latin"
            autocomplete="off"
    >

    <div class="limosms-toggle-group" style="width:100%;margin-top:20px;padding:16px;border:1px solid #dcdcde;background:#fafafa;border-radius:8px;">
        <label style="display:block;margin-bottom:10px;font-weight:700;">وضعیت سرویس‌های فعال</label>

        <label style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <input type="checkbox" id="limosms_woocommerce_sms_enabled" name="limosms_woocommerce_sms_enabled" value="1" data-initial="<?php echo esc_attr( $woocommerce_sms_enabled === 'yes' ? '1' : '0' ); ?>" <?php checked( $woocommerce_sms_enabled, 'yes' ); ?>>
            <span>فعال‌سازی پیامک ووکامرس (پیامک مدیر / مشتری / فروشنده)</span>
        </label>

        <label style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <input type="checkbox" id="limosms_digits_sms_enabled" name="limosms_digits_sms_enabled" value="1" data-initial="<?php echo esc_attr( $digits_sms_enabled === 'yes' ? '1' : '0' ); ?>" <?php checked( $digits_sms_enabled, 'yes' ); ?>>
            <span>فعال‌سازی ورود و عضویت دیجیتس</span>
        </label>

        <label style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" id="limosms_gravity_forms_sms_enabled" name="limosms_gravity_forms_sms_enabled" value="1" data-initial="<?php echo esc_attr( $gravity_forms_sms_enabled === 'yes' ? '1' : '0' ); ?>" <?php checked( $gravity_forms_sms_enabled, 'yes' ); ?>>
            <span>فعال‌سازی پیامک Gravity Forms</span>
        </label>
    </div>

    <div style="margin-top:16px;display:flex;justify-content:flex-end;">
        <button type="submit" class="button button-primary" disabled>
            ذخیره تنظیمات
        </button>
    </div>

    <?php wp_nonce_field('limosms_admin_nonce', 'security'); ?>

    <input type="hidden" name="action" value="limosms_save_connection_settings">

    <div id="limosms-settings-result" style="margin-top:15px;"></div>

</form>
