(function ($) {
    "use strict";

    // بررسی تغییرات فیلد API Key جهت فعال/غیرفعال کردن دکمه ذخیره
    function checkChanges($apiKey, $button) {
        const initialValue = ($apiKey.data("initial") || "").toString().trim();
        const currentValue = ($apiKey.val() || "").toString().trim();

        // دکمه فقط زمانی فعال است که فیلد خالی نبوده و مقدار آن با مقدار ذخیره‌شده اولیه متفاوت باشد
        $button.prop("disabled", !(currentValue && currentValue !== initialValue));
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
        checkChanges($apiKey, $button);
    });

    // مدیریت ارسال فرم به صورت AJAX
    $(document).on("submit", "#limosms-settings-form", function (e) {
        e.preventDefault();

        const $form = $(this);
        const $button = $form.find("button[type='submit']");

        if ($button.prop("disabled")) return;

        const apiKeyVal = $("#limosms_api_key").val();
        const senderNumVal = $("#limosms_sender_number").val();
        const originalText = $button.text();

        // تغییر وضعیت دکمه به حالت ذخیره‌سازی
        $button.prop("disabled", true).text("در حال ذخیره...");

        // ایجاد شیء داده ارسالی
        const formData = {
            action: "limosms_save_connection_settings",
            security: limosms_ajax.nonce,
            limosms_api_key: apiKeyVal,
            limosms_sender_number: senderNumVal
        };

        $.post(limosms_ajax.url, formData, function (response) {
            if (response.success) {
                // نمایش توست موفقیت‌آمیز
                window.LimoSMS.showToast(response.data.message || 'تنظیمات با موفقیت ذخیره شد.', 'success');

                // بروزرسانی مقدار اولیه محلی برای مدیریت وضعیت دکمه
                $("#limosms_api_key").data("initial", apiKeyVal);

                // لود مجدد محتوای تب اتصال جهت همگام‌سازی بخش‌های دیگر (مانند وضعیت اتصال پنل)
                setTimeout(function () {
                    $.post(limosms_ajax.url, {
                        action: "limosms_load_tab",
                        tab: "connection-settings",
                        nonce: limosms_ajax.nonce
                    }, function (html) {
                        $("#limosms-tab-content").html(html);
                        $(document).trigger("limosms:tab-loaded", ["connection-settings"]);
                        // بعد از لود مجدد تب، بررسی وضعیت اتصال از سرور و بروزرسانی وضعیت کلاینت
                        $.post(limosms_ajax.url, { action: 'limosms_check_connection', nonce: limosms_ajax.nonce }, function (res) {
                            if (res && res.success) {
                                window.limosms_connection_status = !!res.data.connected;
                                console.log('limosms:checkedConnection', window.limosms_connection_status);

                                if (window.limosms_connection_status) {
                                    window.LimoSMS.showToast('اتصال برقرار شد.', 'success');
                                    // حذف اورلِی در صورت وجود
                                    var node = document.querySelector('.limosms-connection-required-overlay');
                                    if (node) node.remove();
                                }
                            }
                        });
                    });
                }, 1000);
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
