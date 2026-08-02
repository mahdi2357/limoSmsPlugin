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

        <div class="limosms-admin-phones-wrap">
            <div class="limosms-admin-phone-entry">
                <input
                        type="text"
                        id="limosms_admin_phone_entry"
                        class="regular-text limosms-admin-phone-entry"
                        placeholder="09123456789"
                        maxlength="11"
                >

                <button type="button" id="limosms-add-admin-phone" class="button">افزودن</button>
            </div>

            <input
                    type="hidden"
                    id="limosms_admin_phones"
                    name="limosms_admin_phones"
                    value="<?php echo esc_attr($admin_numbers_value); ?>"
            >

            <div id="limosms-admin-phones-tags" class="limosms-admin-phones-tags"></div>
            <div id="limosms-admin-phones-error" class="limosms-admin-phones-error" style="color:#ef4444;font-size:12px;margin-top:5px;display:none;"></div>

            <p class="description">شماره‌ها را اضافه کنید؛ برای حذف از ضربدر کنار هر شماره استفاده کنید.</p>
        </div>
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

<script>
(function ($) {
    'use strict';

    function parsePhonesRaw(raw) {
        if (!raw) return [];
        return String(raw).split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    }

    function renderTags($container, phones) {
        $container.empty();
        phones.forEach(function (p) {
            var $chip = $('<div class="limosms-admin-phone-chip" data-phone="' + p + '"></div>');
            $chip.append($('<span class="limosms-phone-value"></span>').text(p));
            $chip.append($('<button type="button" class="limosms-remove-admin-phone" aria-label="حذف">×</button>'));
            $container.append($chip);
        });
    }

    const MAX_ADMIN_PHONES = 10;

    function updateHidden($hidden, phones) {
        $hidden.val(phones.join(','));
        $hidden.trigger('input');
    }

    function setAdminPhonesInlineError(message) {
        var $error = $('#limosms-admin-phones-error');

        if (!$error.length) {
            return;
        }

        if (message) {
            $error.text(message).show();
        } else {
            $error.text('').hide();
        }
    }

    $(document).ready(function () {
        var $entry = $('#limosms_admin_phone_entry');
        var $addBtn = $('#limosms-add-admin-phone');
        var $hidden = $('#limosms_admin_phones');
        var $tags = $('#limosms-admin-phones-tags');

        if (!$hidden.length || !$tags.length || !$entry.length) return;

        var phones = parsePhonesRaw($hidden.val());
        renderTags($tags, phones);

        function addPhone(raw) {
            var value = String(raw || '').trim();
            if (!value) return;
            var normalized = value.replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); });
            if (!/^09\d{9}$/.test(normalized)) {
                $entry.addClass('limosms-input-error');
                setTimeout(function () { $entry.removeClass('limosms-input-error'); }, 1200);
                return;
            }

            if (phones.indexOf(normalized) === -1) {
                if (phones.length >= MAX_ADMIN_PHONES) {
                    $entry.addClass('limosms-input-error');
                    setAdminPhonesInlineError('حداکثر ۱۰ شماره موبایل قابل اضافه شدن است.');
                    setTimeout(function () { $entry.removeClass('limosms-input-error'); }, 1200);
                    return;
                }

                phones.push(normalized);
                renderTags($tags, phones);
                updateHidden($hidden, phones);
            }
            setAdminPhonesInlineError('');
            $entry.val('');
        }

        $addBtn.on('click', function (e) {
            e.preventDefault();
            addPhone($entry.val());
        });

        $entry.on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                addPhone($entry.val());
            }
        });

        $tags.on('click', '.limosms-remove-admin-phone', function (e) {
            e.preventDefault();
            var $chip = $(this).closest('.limosms-admin-phone-chip');
            var val = $chip.data('phone');
            phones = phones.filter(function (p) { return p !== val; });
            renderTags($tags, phones);
            updateHidden($hidden, phones);
            setAdminPhonesInlineError('');
        });
    });

})(jQuery);
</script>
