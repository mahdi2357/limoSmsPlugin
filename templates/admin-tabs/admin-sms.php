<?php
if (!defined('ABSPATH')) {
    exit;
}

$admin_numbers = get_option('limosms_admin_phones', array());

if (!is_array($admin_numbers)) {
    $admin_numbers = array();
}

$admin_numbers_value = implode(',', $admin_numbers);

$events   = LimoSMS_Admin_SMS_Events::get_events();
$settings = LimoSMS_Admin_SMS_Settings::get_events_settings();
?>

<div class="limosms-settings-wrapper">

    <div class="limosms-card">
        <h3>شماره موبایل مدیران کل</h3>

        <input
                type="text"
                id="limosms_admin_phones"
                name="limosms_admin_phones"
                value="<?php echo esc_attr($admin_numbers_value); ?>"
                class="regular-text limosms-admin-phones"
                placeholder="09123456789,09350000000"
                maxlength="130"
        >

        <p class="description">شماره‌ها را با کاما (,) جدا کنید.</p>
    </div>

    <?php foreach ($events as $key => $event) : ?>
        <?php
        $event_settings = isset($settings[$key]) && is_array($settings[$key])
                ? $settings[$key]
                : array();

        $enabled      = isset($event_settings['enabled']) ? $event_settings['enabled'] : 'no';
        $pattern_id   = isset($event_settings['otp_id']) ? $event_settings['otp_id'] : '';
        $pattern_title = isset($event_settings['title']) ? $event_settings['title'] : '';
        $pattern_text = isset($event_settings['pattern_text']) ? $event_settings['pattern_text'] : '';

        $pattern_map = isset($event_settings['pattern_map']) && is_array($event_settings['pattern_map'])
                ? $event_settings['pattern_map']
                : array();
        ?>

        <div
                class="limosms-card limosms-event-card"
                data-event="<?php echo esc_attr($key); ?>"
        >

            <h3 class="limosms-event-title">
                <?php echo esc_html($event['label']); ?>
            </h3>

            <label class="limosms-event-toggle">
                <input
                        type="checkbox"
                        class="limosms-event-enabled"
                        data-event="<?php echo esc_attr($key); ?>"
                        <?php checked($enabled, 'yes'); ?>
                >
                <span>فعال باشد</span>
            </label>

            <div class="limosms-event-fields">

                <div class="limosms-field limosms-field-pattern-id">
                    <label
                            class="limosms-label"
                            for="limosms-pattern-selector-<?php echo esc_attr($key); ?>"
                    >
                        انتخاب Pattern از لیموSMS
                    </label>

                    <select
                            id="limosms-pattern-selector-<?php echo esc_attr($key); ?>"
                            class="limosms-pattern-selector"
                            data-event="<?php echo esc_attr($key); ?>"
                    >
                        <option value="">ابتدا لیست را دریافت کنید</option>
                    </select>

                    <input
                            type="hidden"
                            class="limosms-event-otp-id"
                            data-event="<?php echo esc_attr($key); ?>"
                            value="<?php echo esc_attr($pattern_id) ; ?>"
                    >
                    <input
                            type="hidden"
                            class="limosms-event-pattern-title"
                            data-event="<?php echo esc_attr($key); ?>"
                            value="<?php echo esc_attr($pattern_title); ?>"
                    >

                </div>

                <div class="limosms-field limosms-field-pattern-text">
                    <label
                            class="limosms-label"
                            for="limosms-pattern-text-<?php echo esc_attr($key); ?>"
                    >
                        متن Pattern
                    </label>

                    <div class="limosms-pattern-box">
                        <pre
                                id="limosms-pattern-text-<?php echo esc_attr($key); ?>"
                                class="limosms-pattern-text"
                                data-event="<?php echo esc_attr($key); ?>"
                        ><?php echo esc_html($pattern_text); ?></pre>
                    </div>
                </div>

                <div class="limosms-field limosms-field-pattern-map">
                    <label class="limosms-field-title">
                        اتصال پارامترها به توکن‌ها
                    </label>

                    <div
                            class="limosms-pattern-mapping"
                            data-event="<?php echo esc_attr($key); ?>"
                            data-saved-map="<?php echo esc_attr(wp_json_encode($pattern_map)); ?>"
                    ></div>
                </div>

            </div>

        </div>
    <?php endforeach; ?>

    <button
            type="button"
            id="limosms-save-otp-settings"
            class="button button-primary"
            disabled
    >
        ذخیره تغییرات
    </button>

</div>
