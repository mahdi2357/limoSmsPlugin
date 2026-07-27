(function ($) {
    "use strict";

    function checkChanges($form, $button) {
        const $apiKey = $form.find("#limosms_api_key");
        const $woocommerceToggle = $form.find("#limosms_woocommerce_sms_enabled");
        const $digitsToggle = $form.find("#limosms_digits_sms_enabled");
        const $gravityFormsToggle = $form.find("#limosms_gravity_forms_sms_enabled");

        const initialApiKey = ($apiKey.data("initial") || "").toString().trim();
        const currentApiKey = ($apiKey.val() || "").toString().trim();
        const initialWooCommerce = ($woocommerceToggle.attr("data-initial") || "0") === "1";
        const currentWooCommerce = $woocommerceToggle.is(":checked");
        const initialDigits = ($digitsToggle.attr("data-initial") || "0") === "1";
        const currentDigits = $digitsToggle.is(":checked");
        const initialGravityForms = ($gravityFormsToggle.attr("data-initial") || "0") === "1";
        const currentGravityForms = $gravityFormsToggle.is(":checked");

        const changed = currentApiKey !== initialApiKey || initialWooCommerce !== currentWooCommerce || initialDigits !== currentDigits || initialGravityForms !== currentGravityForms;
        $button.prop("disabled", !(currentApiKey && changed));
    }

    // کنترل ورودی کاربر بر روی فیلد کلید API
    $(document).on("input", "#limosms_api_key", function () {
        const $apiKey = $(this);
        const $form = $apiKey.closest("form");
        const $button = $form.find("button[type='submit']");

        let value = $apiKey.val();

        // حذف کاراکترهای غیرمجاز
        value = value.replace(/[^a-zA-Z0-9\-_]/g, "");

        if (value.length > 50) {
            value = value.substring(0, 50);
        }

        $apiKey.val(value);
        checkChanges($form, $button);
    });

    $(document).on("change", "#limosms_woocommerce_sms_enabled, #limosms_digits_sms_enabled, #limosms_gravity_forms_sms_enabled", function () {
        const $form = $(this).closest("form");
        const $button = $form.find("button[type='submit']");
        checkChanges($form, $button);
    });

    // مدیریت ارسال فرم به صورت AJAX
    $(document).on("submit", "#limosms-settings-form", function (e) {
        e.preventDefault();

        const $form = $(this);
        const $button = $form.find("button[type='submit']");

        if ($button.prop("disabled")) return;

        const apiKeyVal = $("#limosms_api_key").val();
        const senderNumVal = $("#limosms_sender_number").val();
        const woocommerceEnabledVal = $("#limosms_woocommerce_sms_enabled").is(":checked") ? "1" : "0";
        const digitsEnabledVal = $("#limosms_digits_sms_enabled").is(":checked") ? "1" : "0";
        const gravityFormsEnabledVal = $("#limosms_gravity_forms_sms_enabled").is(":checked") ? "1" : "0";
        const originalText = $button.text();

        // تغییر وضعیت دکمه به حالت ذخیره‌سازی
        $button.prop("disabled", true).text("در حال ذخیره...");

        // ایجاد شیء داده ارسالی
        const formData = {
            action: "limosms_save_connection_settings",
            security: limosms_ajax.nonce,
            limosms_api_key: apiKeyVal,
            limosms_sender_number: senderNumVal,
            limosms_woocommerce_sms_enabled: woocommerceEnabledVal,
            limosms_digits_sms_enabled: digitsEnabledVal,
            limosms_gravity_forms_sms_enabled: gravityFormsEnabledVal
        };

        $.post(limosms_ajax.url, formData, function (response) {
            if (response.success) {
                // نمایش توست موفقیت‌آمیز
                window.LimoSMS.showToast(response.data.message || 'تنظیمات با موفقیت ذخیره شد.', 'success');

                // بروزرسانی مقدار اولیه محلی برای مدیریت وضعیت دکمه
                $("#limosms_api_key").data("initial", apiKeyVal);
                $("#limosms_woocommerce_sms_enabled").attr("data-initial", woocommerceEnabledVal === "1" ? "1" : "0");
                $("#limosms_digits_sms_enabled").attr("data-initial", digitsEnabledVal === "1" ? "1" : "0");
                $("#limosms_gravity_forms_sms_enabled").attr("data-initial", gravityFormsEnabledVal === "1" ? "1" : "0");

                setTimeout(function () {
                    window.location.reload();
                }, 700);
            } else {
                // نمایش توست خطا
                window.LimoSMS.showToast(response.data.message || 'خطا در ذخیره تنظیمات.', 'error');
                $button.prop("disabled", false).text(originalText);
            }
        }).fail(function () {
            // نمایش توست خطای ارتباط با سرور
            window.LimoSMS.showToast('خطا در ارتباط با سرور. لطفا مجددا تلاش کنید.', 'error');
            $button.prop("disabled", false).text(originalText);
        });
    });

})(jQuery);
