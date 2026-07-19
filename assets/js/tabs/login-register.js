jQuery(function ($) {
    const otpToggle = document.getElementById('limoo-login-register-otp-enabled');
    const otpNotice = document.getElementById('limoo-otp-shortcode-notice');

    const syncOtpNotice = function () {
        if (!otpToggle || !otpNotice) {
            return;
        }

        otpNotice.classList.toggle('is-visible', otpToggle.checked);
    };

    function showToast(message, type) {
        $('.limosms-toast').remove();

        var toastType = type || 'success';
        var $toast = $('<div class="limosms-toast limosms-toast-' + toastType + '"></div>');

        $toast.text(message).appendTo('body');

        setTimeout(function () {
            $toast.addClass('is-visible');
        }, 10);

        setTimeout(function () {
            $toast.removeClass('is-visible');
            setTimeout(function () {
                $toast.remove();
            }, 250);
        }, 3000);
    }

    $(document).on('submit', '#limoo-login-register-form', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('#limoo-login-register-save');
        var enabled = $('#limoo-login-register-otp-enabled').is(':checked') ? '1' : '0';

        $button.prop('disabled', true).addClass('is-busy');

        $.ajax({
            url: limosms_ajax.url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'limosms_save_login_register_settings',
                nonce: limosms_ajax.nonce,
                login_register_otp_enabled: enabled
            }
        })
            .done(function (response) {
                if (response && response.success) {
                    syncOtpNotice();

                    showToast(
                        response.data && response.data.message ? response.data.message : 'تنظیمات با موفقیت ذخیره شد.',
                        'success'
                    );
                    return;
                }

                showToast(
                    response && response.data && response.data.message ? response.data.message : 'ذخیره تنظیمات ناموفق بود.',
                    'error'
                );
            })
            .fail(function () {
                showToast('خطا در ارتباط با سرور.', 'error');
            })
            .always(function () {
                $button.prop('disabled', false).removeClass('is-busy');
            });
    });
});
