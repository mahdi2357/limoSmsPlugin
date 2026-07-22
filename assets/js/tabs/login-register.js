jQuery(function ($) {
    function getOtpElements() {
        return {
            toggle: document.getElementById('limoo-login-register-otp-enabled'),
            notice: document.getElementById('limoo-otp-shortcode-notice'),
            settings: document.getElementById('limoo-otp-settings')
        };
    }

    function syncOtpNotice(enabled) {
        var elements = getOtpElements();

        if (!elements.notice) {
            return;
        }

        elements.notice.hidden = !enabled;
        elements.notice.classList.toggle('is-visible', enabled);

        if (elements.settings) {
            elements.settings.hidden = !enabled;
            elements.settings.classList.toggle('is-visible', enabled);
        }
    }

    $(document).on('change', '#limoo-login-register-otp-enabled', function () {
        syncOtpNotice(this.checked);
    });

    $(document).on('submit', '#limoo-login-register-form', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('#limoo-login-register-save');
        var data = $form.serializeArray();

        data.push({ name: 'action', value: 'limosms_save_login_register_settings' });
        data.push({ name: 'nonce', value: limosms_ajax.nonce });

        $button.prop('disabled', true).addClass('is-busy');

        $.ajax({
            url: limosms_ajax.url,
            type: 'POST',
            dataType: 'json',
            data: data
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
        var fallbackMessage = toastType === 'error' ? 'عملیات با خطا مواجه شد.' : 'عملیات با موفقیت انجام شد.';
        var safeMessage = message;

        if (safeMessage === null || safeMessage === undefined || safeMessage === '') {
            safeMessage = fallbackMessage;
        } else if (typeof safeMessage === 'object') {
            if (safeMessage.message && typeof safeMessage.message === 'string' && safeMessage.message.trim()) {
                safeMessage = safeMessage.message.trim();
            } else if (safeMessage.error && typeof safeMessage.error === 'string' && safeMessage.error.trim()) {
                safeMessage = safeMessage.error.trim();
            } else {
                safeMessage = fallbackMessage;
            }
        } else if (typeof safeMessage === 'string') {
            safeMessage = safeMessage.trim() || fallbackMessage;
        } else {
            safeMessage = String(safeMessage);
        }

        var $toast = $('<div class="limosms-toast limosms-toast-' + toastType + '"></div>');

        $toast.text(safeMessage).appendTo('body');

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

    $(document).on('click', '#limoo-otp-shortcode-value', function () {
        var shortcodeElement = this;
        var shortcodeText = shortcodeElement.getAttribute('data-shortcode') || $.trim(shortcodeElement.textContent);
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
    });

    $(document).on('keydown', '#limoo-otp-shortcode-value', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            $(this).trigger('click');
        }
    });

    var initialElements = getOtpElements();
    if (initialElements.toggle) {
        syncOtpNotice(initialElements.toggle.checked);
    }
});
