<?php
if (!defined('ABSPATH')) {
    exit;
}

$forms = array();
$settings = array();

if (class_exists('LimoSMS_Gravity_Forms_SMS_Events') && method_exists('LimoSMS_Gravity_Forms_SMS_Events', 'get_forms')) {
    $forms = LimoSMS_Gravity_Forms_SMS_Events::get_forms();
}

if (class_exists('LimoSMS_Gravity_Forms_SMS_Settings') && method_exists('LimoSMS_Gravity_Forms_SMS_Settings', 'get_form_settings')) {
    $settings = LimoSMS_Gravity_Forms_SMS_Settings::get_form_settings();
}

$all_tokens = array();
if (class_exists('LimoSMS_Gravity_Forms_SMS_Events')) {
    $all_tokens = LimoSMS_Gravity_Forms_SMS_Events::get_all_form_tokens();
}

$admin_numbers = get_option('limosms_gravity_forms_admin_phones', array());
if (!is_array($admin_numbers)) {
    $admin_numbers = array();
}
$admin_numbers_value = implode(',', $admin_numbers);
?>

<div class="limosms-settings-wrapper">

    <div class="limosms-card">
        <h3>شماره موبایل مدیران برای Gravity Forms</h3>

        <div class="limosms-admin-phones-wrap">
            <div class="limosms-admin-phone-entry">
                <input
                        type="text"
                        id="limosms_gravity_forms_admin_phone_entry"
                        class="regular-text limosms-admin-phone-entry"
                        placeholder="09123456789"
                        maxlength="11"
                >

                <button type="button" id="limosms-add-gravity-forms-admin-phone" class="button">افزودن</button>
            </div>

            <input
                    type="hidden"
                    id="limosms_gravity_forms_admin_phones"
                    name="limosms_gravity_forms_admin_phones"
                    value="<?php echo esc_attr($admin_numbers_value); ?>"
            >

            <div id="limosms-gravity-forms-admin-phones-tags" class="limosms-admin-phones-tags"></div>
            <div id="limosms-gravity-forms-admin-phones-error" class="limosms-admin-phones-error" style="color:#ef4444;font-size:12px;margin-top:5px;display:none;"></div>

            <p class="description">شماره‌های دریافت‌کننده را اضافه کنید؛ برای حذف از ضربدر کنار هر شماره استفاده کنید.</p>
        </div>
    </div>

    <?php if (empty($forms)) : ?>
        <div class="limosms-notice limosms-notice-info">
            <p>Gravity Forms نصب نشده است یا فرمی موجود نیست.</p>
        </div>
    <?php else : ?>

        <?php foreach ($forms as $form_id => $form) : ?>
            <?php
            $form_settings = isset($settings[$form_id]) && is_array($settings[$form_id]) ? $settings[$form_id] : array();

            $enabled = isset($form_settings['enabled']) ? $form_settings['enabled'] : 'no';
            $pattern_id = isset($form_settings['otp_id']) ? $form_settings['otp_id'] : '';
            $pattern_text = isset($form_settings['pattern_text']) ? $form_settings['pattern_text'] : '';
            $pattern_sel = isset($form_settings['pattern_selector']) ? $form_settings['pattern_selector'] : '';

            $pattern_map = isset($form_settings['pattern_map']) && is_array($form_settings['pattern_map'])
                    ? $form_settings['pattern_map']
                    : array();

            $form_label = isset($form['label']) ? $form['label'] : 'فرم #' . $form_id;
            ?>

            <div class="limosms-card limosms-form-card" data-form="<?php echo esc_attr($form_id); ?>">
                <h3 class="limosms-form-title"><?php echo esc_html($form_label); ?></h3>

                <label class="limosms-form-toggle">
                    <input
                            type="checkbox"
                            class="limosms-gravity-form-enabled"
                            data-form="<?php echo esc_attr($form_id); ?>"
                            <?php checked($enabled, 'yes'); ?>
                    >
                    <span>فعال باشد</span>
                </label>

                <div class="limosms-form-fields">
                    <div class="limosms-field limosms-field-pattern-id">
                        <label class="limosms-label" for="limosms-gravity-pattern-selector-<?php echo esc_attr($form_id); ?>">
                            انتخاب Pattern از لیموSMS
                        </label>

                        <select
                                id="limosms-gravity-pattern-selector-<?php echo esc_attr($form_id); ?>"
                                class="limosms-pattern-selector limosms-gravity-pattern-selector"
                                data-form="<?php echo esc_attr($form_id); ?>"
                                data-saved="<?php echo esc_attr($pattern_sel ?: $pattern_id); ?>"
                        >
                            <option value="">ابتدا لیست را دریافت کنید</option>
                        </select>

                        <input
                                type="hidden"
                                class="limosms-form-otp-id limosms-gravity-otp-id"
                                data-form="<?php echo esc_attr($form_id); ?>"
                                value="<?php echo esc_attr($pattern_id); ?>"
                        >
                    </div>

                    <div class="limosms-field limosms-field-pattern-text">
                        <label class="limosms-label" for="limosms-gravity-pattern-text-<?php echo esc_attr($form_id); ?>">
                            متن Pattern
                        </label>

                        <div class="limosms-pattern-box">
                            <pre
                                    id="limosms-gravity-pattern-text-<?php echo esc_attr($form_id); ?>"
                                    class="limosms-pattern-text"
                                    data-form="<?php echo esc_attr($form_id); ?>"
                            ><?php echo esc_html($pattern_text); ?></pre>
                        </div>
                    </div>

                    <div class="limosms-field limosms-field-pattern-map">
                        <label class="limosms-field-title">اتصال پارامترها به فیلدهای فرم</label>

                        <div
                                class="limosms-pattern-mapping limosms-gravity-pattern-mapping-wrap"
                                data-form="<?php echo esc_attr($form_id); ?>"
                                data-saved-map="<?php echo esc_attr(wp_json_encode($pattern_map)); ?>"
                        ></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <button
                type="button"
                id="limosms-save-gravity-forms-settings"
                class="button button-primary limosms-save-button"
        >
            ذخیره تنظیمات
        </button>

    <?php endif; ?>

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
            const $chip = $('<div class="limosms-admin-phone-chip" data-phone="' + p + '"></div>');
            $chip.append($('<span class="limosms-phone-value"></span>').text(p));
            $chip.append($('<button type="button" class="limosms-remove-admin-phone" aria-label="حذف">×</button>'));
            $container.append($chip);
        });
    }

    const MAX_GRAVITY_FORMS_ADMIN_PHONES = 10;

    function updateHidden($hidden, phones) {
        $hidden.val(phones.join(','));
        $hidden.trigger('input');
    }

    function setGravityFormsAdminPhonesError(message) {
        const $error = $('#limosms-gravity-forms-admin-phones-error');

        if (!$error.length) {
            return;
        }

        if (message) {
            $error.text(message).show();
        } else {
            $error.text('').hide();
        }
    }

    function validateGravityFormsAdminPhones(value) {
        const cleanValue = String(value || '').trim().replace(/^,+|,+$/g, '');

        if (!cleanValue) {
            return {
                valid: true,
                message: ''
            };
        }

        const normalized = cleanValue
            .replace(/[۰-۹]/g, function (digit) {
                return '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit);
            })
            .replace(/[٠-٩]/g, function (digit) {
                return '٠١٢٣٤٥٦٧٨٩'.indexOf(digit);
            });

        if (/[^0-9,]/.test(normalized)) {
            return {
                valid: false,
                message: 'فقط وارد کردن اعداد انگلیسی و کاما (,) مجاز است.'
            };
        }

        if (normalized.startsWith(',') || normalized.endsWith(',')) {
            return {
                valid: false,
                message: 'شماره تلفن نباید با کاما شروع یا تمام شود.'
            };
        }

        if (/,{2,}/.test(normalized)) {
            return {
                valid: false,
                message: 'لطفاً از وارد کردن کامای پشت سر هم خودداری کنید.'
            };
        }

        const phones = normalized.split(',');
        if (phones.length > MAX_GRAVITY_FORMS_ADMIN_PHONES) {
            return {
                valid: false,
                message: 'حداکثر ۱۰ شماره موبایل قابل ذخیره است.'
            };
        }

        for (let index = 0; index < phones.length; index++) {
            const phone = phones[index];

            if (phone && !/^09\d{9}$/.test(phone)) {
                return {
                    valid: false,
                    message: 'هر شماره موبایل وارد شده باید با 09 شروع شده و ۱۱ رقم باشد.'
                };
            }
        }

        return {
            valid: true,
            message: ''
        };
    }

    $(document).ready(function () {
        const $entry = $('#limosms_gravity_forms_admin_phone_entry');
        const $addBtn = $('#limosms-add-gravity-forms-admin-phone');
        const $hidden = $('#limosms_gravity_forms_admin_phones');
        const $tags = $('#limosms-gravity-forms-admin-phones-tags');

        if (!$hidden.length || !$tags.length || !$entry.length) return;

        let phones = parsePhonesRaw($hidden.val());
        renderTags($tags, phones);

        function addPhone(raw) {
            const value = String(raw || '').trim();
            if (!value) return;
            // normalize Persian/Arabic digits to english
            const normalized = value.replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); });
            if (!/^09\d{9}$/.test(normalized)) {
                $entry.addClass('limosms-input-error');
                setTimeout(function () { $entry.removeClass('limosms-input-error'); }, 1200);
                return;
            }

            if (phones.indexOf(normalized) === -1) {
                if (phones.length >= MAX_GRAVITY_FORMS_ADMIN_PHONES) {
                    $entry.addClass('limosms-input-error');
                    setGravityFormsAdminPhonesError('حداکثر ۱۰ شماره موبایل قابل اضافه شدن است.');
                    setTimeout(function () { $entry.removeClass('limosms-input-error'); }, 1200);
                    return;
                }

                phones.push(normalized);
                renderTags($tags, phones);
                updateHidden($hidden, phones);
                setGravityFormsAdminPhonesError('');
            }
            if (window.limosmsGravityFormsSmsData && typeof window.limosmsGravityFormsSmsData === 'object') {
                $('#limosms-save-gravity-forms-settings').prop('disabled', false);
            }
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
            const $chip = $(this).closest('.limosms-admin-phone-chip');
            const val = $chip.data('phone');
            phones = phones.filter(function (p) { return p !== val; });
            renderTags($tags, phones);
            updateHidden($hidden, phones);
            setGravityFormsAdminPhonesError('');
            $('#limosms-save-gravity-forms-settings').prop('disabled', false);
        });

        $hidden.on('input', function () {
            const validation = validateGravityFormsAdminPhones($hidden.val());
            setGravityFormsAdminPhonesError(validation.message);
            $('#limosms-save-gravity-forms-settings').prop('disabled', false);
        });
    });

})(jQuery);
</script>
