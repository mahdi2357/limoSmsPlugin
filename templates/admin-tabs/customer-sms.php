<?php
if (!defined('ABSPATH')) {
    exit;
}

$events = array();
$settings = array();

if (class_exists('LimoSMS_Customer_SMS_Events') && method_exists('LimoSMS_Customer_SMS_Events', 'get_events')) {
    $events = LimoSMS_Customer_SMS_Events::get_events();
}
if (class_exists('LimoSMS_Customer_SMS_Settings') && method_exists('LimoSMS_Customer_SMS_Settings', 'get_events_settings')) {
    $settings = LimoSMS_Customer_SMS_Settings::get_events_settings();
}
?>

<div class="limosms-settings-wrapper">

    <?php foreach ($events as $key => $event) : ?>
        <?php
        $event_settings = isset($settings[$key]) && is_array($settings[$key]) ? $settings[$key] : array();

        $enabled      = isset($event_settings['enabled']) ? $event_settings['enabled'] : 'no';
        $pattern_id   = isset($event_settings['otp_id']) ? $event_settings['otp_id'] : '';
        $pattern_text = isset($event_settings['pattern_text']) ? $event_settings['pattern_text'] : '';
        $pattern_sel  = isset($event_settings['pattern_selector']) ? $event_settings['pattern_selector'] : '';

        $pattern_map  = isset($event_settings['pattern_map']) && is_array($event_settings['pattern_map'])
                ? $event_settings['pattern_map']
                : array();

        $event_label = isset($event['label']) ? $event['label'] : $key;
        ?>

        <div class="limosms-card limosms-event-card" data-event="<?php echo esc_attr($key); ?>">
            <h3 class="limosms-event-title"><?php echo esc_html($event_label); ?></h3>

            <label class="limosms-event-toggle">
                <input
                        type="checkbox"
                        class="limosms-customer-event-enabled"
                        data-event="<?php echo esc_attr($key); ?>"
                        <?php checked($enabled, 'yes'); ?>
                >
                <span>فعال باشد</span>
            </label>

            <div class="limosms-event-fields">
                <div class="limosms-field limosms-field-pattern-id">
                    <label class="limosms-label" for="limosms-customer-pattern-selector-<?php echo esc_attr($key); ?>">
                        انتخاب Pattern از لیموSMS
                    </label>

                    <select
                            id="limosms-customer-pattern-selector-<?php echo esc_attr($key); ?>"
                            class="limosms-pattern-selector limosms-customer-pattern-selector"
                            data-event="<?php echo esc_attr($key); ?>"
                            data-saved="<?php echo esc_attr($pattern_sel ?: $pattern_id); ?>"
                    >
                        <option value="">ابتدا لیست را دریافت کنید</option>
                    </select>

                    <input
                            type="hidden"
                            class="limosms-event-otp-id limosms-customer-otp-id"
                            data-event="<?php echo esc_attr($key); ?>"
                            value="<?php echo esc_attr($pattern_id); ?>"
                    >
                </div>

                <div class="limosms-field limosms-field-pattern-text">
                    <label class="limosms-label" for="limosms-customer-pattern-text-<?php echo esc_attr($key); ?>">
                        متن Pattern
                    </label>

                    <div class="limosms-pattern-box">
                        <pre
                                id="limosms-customer-pattern-text-<?php echo esc_attr($key); ?>"
                                class="limosms-pattern-text"
                                data-event="<?php echo esc_attr($key); ?>"
                        ><?php echo esc_html($pattern_text); ?></pre>
                    </div>
                </div>

                <div class="limosms-field limosms-field-pattern-map">
                    <label class="limosms-field-title">اتصال پارامترها به توکن‌ها</label>

                    <div
                            class="limosms-pattern-mapping limosms-customer-pattern-mapping-wrap"
                            data-event="<?php echo esc_attr($key); ?>"
                            data-saved-map="<?php echo esc_attr(wp_json_encode($pattern_map)); ?>"
                    ></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <button
            type="button"
            id="limosms-save-customer-settings"
            class="button button-primary"
            disabled
    >
        ذخیره تغییرات
    </button>

</div>
