jQuery(function ($) {
    function getOtpElements() {
        return {
            toggle: document.getElementById('limoo-login-register-otp-enabled'),
            notice: document.getElementById('limoo-otp-shortcode-notice'),
            settings: document.getElementById('limoo-otp-settings')
        };
    }

    function syncOtpNotice(savedEnabled) {
        var elements = getOtpElements();

        if (!elements.notice) {
            return;
        }

        elements.notice.hidden = !savedEnabled;
        elements.notice.classList.toggle('is-visible', savedEnabled);
    }

    var currentSavedEnabled = false;
    var loginRegisterInitialState = '';

    function getLoginRegisterState() {
        var $form = $('#limoo-login-register-form');
        if (!$form.length) {
            return '';
        }

        return $form.serializeArray()
            .map(function (item) {
                return item.name + '=' + item.value;
            })
            .sort()
            .join('&');
    }

    function toggleLoginRegisterSaveWarning(isVisible) {
        if (window.LimoSMS && typeof window.LimoSMS.toggleSaveWarning === 'function') {
            window.LimoSMS.toggleSaveWarning(isVisible);
            return;
        }

        $('#limoo-login-register-save-warning').toggle(isVisible);
    }

    function updateLoginRegisterSaveWarningState() {
        var isDirty = loginRegisterInitialState !== getLoginRegisterState();
        toggleLoginRegisterSaveWarning(isDirty);
        return isDirty;
    }

    function syncOtpSettingsPanel(enabled) {
        var elements = getOtpElements();

        if (!elements.settings) {
            return;
        }

        elements.settings.hidden = !enabled;
        elements.settings.classList.toggle('is-visible', enabled);
    }

    function updateRegistrationFieldRequiredState(row) {
        var $enabled = row.find('input[name^="login_register_otp_registration_fields"][name$="[enabled]"]');
        var $required = row.find('input[name^="login_register_otp_registration_fields"][name$="[required]"]');

        if (!$enabled.length || !$required.length) {
            return;
        }

        var isEnabled = $enabled.is(':checked');
        $required.prop('disabled', !isEnabled);
        if (!isEnabled) {
            $required.prop('checked', false);
        }

        row.find('.limoo-setting-row__inline-checkbox').toggleClass('is-disabled', !isEnabled);
    }

    function refreshAllRegistrationFieldRequiredStates() {
        $('.limoo-setting-row').has('input[name^="login_register_otp_registration_fields"][name$="[required]"]')
            .each(function () {
                updateRegistrationFieldRequiredState($(this));
            });
    }

    function openMediaUploader(button) {
        var target = button.getAttribute('data-target');
        if ( ! target ) {
            return;
        }

        var input = document.getElementById(target);
        if ( ! input ) {
            return;
        }

        var frame = wp.media({
            title: limosms_ajax.mediaTitle || 'انتخاب تصویر',
            button: {
                text: limosms_ajax.mediaButton || 'انتخاب'
            },
            library: {
                type: 'image'
            },
            multiple: false
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            input.value = attachment.url;
            var preview = document.querySelector('[data-preview="' + target + '"]');
            if ( preview ) {
                preview.src = attachment.url;
                preview.hidden = false;
            }
        });

        frame.open();
    }

    function clearMediaField(button) {
        var target = button.getAttribute('data-target');
        if ( ! target ) {
            return;
        }

        var input = document.getElementById(target);
        if ( ! input ) {
            return;
        }

        input.value = '';
        var preview = document.querySelector('[data-preview="' + target + '"]');
        if ( preview ) {
            preview.src = '';
            preview.hidden = true;
        }
    }

    $(document).on('click', '.limoo-media-upload-button', function (e) {
        e.preventDefault();
        openMediaUploader(this);
    });

    $(document).on('click', '.limoo-media-remove-button', function (e) {
        e.preventDefault();
        clearMediaField(this);
    });


    function switchLoginRegisterPanel(targetPanel) {
        $('.limoo-login-register-subtab').each(function () {
            var isActive = $(this).attr('data-panel') === targetPanel;
            $(this).toggleClass('is-active', isActive);
            $(this).attr('aria-selected', isActive ? 'true' : 'false');
        });

        $('.limoo-login-register-panel').each(function () {
            var shouldShow = $(this).attr('id') === 'limoo-login-register-panel-' + targetPanel;
            $(this).toggleClass('is-active', shouldShow);
            $(this).attr('hidden', shouldShow ? null : 'hidden');
        });
    }

    $(document).on('change', '#limoo-login-register-otp-enabled', function () {
        syncOtpSettingsPanel(this.checked);
    });

    $(document).on('change', 'input[name^="login_register_otp_registration_fields"][name$="[enabled]"]', function () {
        var row = $(this).closest('.limoo-setting-row');
        updateRegistrationFieldRequiredState(row);
    });

    $(document).ready(function () {
        refreshAllRegistrationFieldRequiredStates();

        var initialElements = getOtpElements();
        if (initialElements.toggle) {
            syncOtpSettingsPanel(initialElements.toggle.checked);
            currentSavedEnabled = initialElements.toggle.checked;
            syncOtpNotice(currentSavedEnabled);
        }

        loginRegisterInitialState = getLoginRegisterState();
    });

    $(document).on('input change', '#limoo-login-register-form :input', function () {
        updateLoginRegisterSaveWarningState();
    });

    $(document).on('click', '.limoo-login-register-subtab', function () {
        switchLoginRegisterPanel($(this).attr('data-panel'));
    });

    $(document).on('submit', '#limoo-login-register-form', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('#limoo-login-register-save');
        var enabled = $form.find('#limoo-login-register-otp-enabled').is(':checked');
        var customCss = $form.find('#limoo-login-register-custom-css').val();
        if ( ! isLoginRegisterCustomCssValid( customCss ) ) {
            showToast('کد CSS وارد شده معتبر نیست. فقط قوانین CSS معتبر و بدون تگ HTML مجاز است.', 'error');
            $button.prop('disabled', false).removeClass('is-busy');
            return;
        }

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
                    currentSavedEnabled = enabled;
                    syncOtpNotice(currentSavedEnabled);
                    syncOtpSettingsPanel(enabled);
                    loginRegisterInitialState = getLoginRegisterState();
                    toggleLoginRegisterSaveWarning(false);

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

    function isLoginRegisterCustomCssValid(css) {
        if (!css || !css.trim) {
            return true;
        }

        if (/<\s*\/??\s*\w+/i.test(css)) {
            return false;
        }

        if (/@(import|charset|namespace)\b/i.test(css)) {
            return false;
        }

        var balance = 0;
        for (var i = 0; i < css.length; i++) {
            var ch = css[i];
            if (ch === '{') {
                balance++;
            } else if (ch === '}') {
                balance--;
                if (balance < 0) {
                    return false;
                }
            }
        }

        return balance === 0;
    }

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
});
