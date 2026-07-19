jQuery(function ($) {
    const otpToggle = document.getElementById('limoo-login-register-otp-enabled');
    const otpNotice = document.getElementById('limoo-otp-shortcode-notice');
    const shortcodeElement = document.getElementById('limoo-otp-shortcode-value');

    const syncOtpNotice = function (enabled) {
        if (!otpNotice) {
            return;
        }

        otpNotice.hidden = !enabled;
        otpNotice.classList.toggle('is-visible', enabled);
    };

    $(document).on('submit', '#limoo-login-register-form', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('#limoo-login-register-save');
        var enabled = $('#limoo-login-register-otp-enabled').is(':checked');

        $button.prop('disabled', true).addClass('is-busy');

        $.ajax({
            url: limosms_ajax.url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'limosms_save_login_register_settings',
                nonce: limosms_ajax.nonce,
                login_register_otp_enabled: enabled ? '1' : '0'
            }
        })
            .done(function (response) {
                if (response && response.success) {
                    syncOtpNotice(enabled);

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

    function copyTextToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve, reject) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                document.body.removeChild(textarea);
                resolve();
            } catch (error) {
                document.body.removeChild(textarea);
                reject(error);
            }
        });
    }

    if (shortcodeElement) {
        var copyShortcode = function () {
            var shortcodeText = $.trim(shortcodeElement.textContent);
            var originalText = shortcodeElement.getAttribute('data-shortcode-original') || shortcodeElement.textContent;
            var copiedText = shortcodeElement.getAttribute('data-copied-text') || 'کپی شد';

            if (!shortcodeText) {
                return;
            }

            shortcodeElement.setAttribute('data-shortcode-original', originalText);

            copyTextToClipboard(shortcodeText)
                .then(function () {
                    shortcodeElement.classList.add('is-copied');
                    shortcodeElement.textContent = copiedText;

                    showToast('شورت‌کد کپی شد.', 'success');

                    window.setTimeout(function () {
                        shortcodeElement.textContent = originalText;
                        shortcodeElement.classList.remove('is-copied');
                    }, 1400);
                })
                .catch(function () {
                    showToast('کپی شورت‌کد ناموفق بود.', 'error');
                });
        };

        shortcodeElement.addEventListener('click', copyShortcode);

        shortcodeElement.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                copyShortcode();
            }
        });
    }

    if (otpToggle) {
        syncOtpNotice(otpToggle.checked);
    }
});
