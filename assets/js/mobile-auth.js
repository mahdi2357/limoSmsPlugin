(function () {
    function initMobileAuth() {
        const authBox = document.querySelector('.limosms-mobile-auth');

        if (!authBox || !window.limosmsMobileAuth) {
            return;
        }

        const mobileStep = authBox.querySelector('[data-step="mobile"]');
        const codeStep = authBox.querySelector('[data-step="code"]');
        const mobileInput = authBox.querySelector('#limosms_mobile');
        const mobileConfirmInput = authBox.querySelector('#limosms_mobile_confirm');
        const codeInput = authBox.querySelector('#limosms_code');
        const sendCodeButton = authBox.querySelector('#limosms-send-code');
        const verifyCodeButton = authBox.querySelector('#limosms-verify-code');
        const editMobileButton = authBox.querySelector('#limosms-edit-mobile');
        const messageBox = authBox.querySelector('.limosms-mobile-auth__message');

        if (
            !mobileStep ||
            !codeStep ||
            !mobileInput ||
            !mobileConfirmInput ||
            !codeInput ||
            !sendCodeButton ||
            !verifyCodeButton ||
            !editMobileButton ||
            !messageBox
        ) {
            return;
        }

        function setMessage(message, type) {
            messageBox.textContent = message || '';
            messageBox.classList.remove('is-error', 'is-success', 'is-info');

            if (type) {
                messageBox.classList.add(type);
            }
        }

        function setButtonLoading(button, isLoading, defaultText, loadingText) {
            if (!button) {
                return;
            }

            button.disabled = isLoading;
            button.textContent = isLoading ? loadingText : defaultText;
        }

        function goToCodeStep(mobile) {
            mobileConfirmInput.value = mobile;
            mobileStep.hidden = true;
            codeStep.hidden = false;
            codeInput.focus();
        }

        function goToMobileStep() {
            codeStep.hidden = true;
            mobileStep.hidden = false;
            codeInput.value = '';
            messageBox.textContent = '';
            mobileInput.focus();
        }

        sendCodeButton.addEventListener('click', function (event) {
            event.preventDefault();

            const mobile = mobileInput.value.trim();

            if (!mobile) {
                setMessage('شماره موبایل را وارد کنید.', 'is-error');
                return;
            }

            setMessage('در حال ارسال...', 'is-info');
            setButtonLoading(sendCodeButton, true, 'دریافت کد', 'در حال ارسال...');

            fetch(limosmsMobileAuth.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: new URLSearchParams({
                    action: 'limosms_send_otp',
                    nonce: limosmsMobileAuth.nonce,
                    mobile: mobile
                })
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (!data.success) {
                        const message = data.data && data.data.message ? data.data.message : 'خطا در ارسال کد.';
                        setMessage(message, 'is-error');
                        return;
                    }

                    setMessage('کد ارسال شد.', 'is-success');
                    goToCodeStep(mobile);
                })
                .catch(function () {
                    setMessage('خطا در ارتباط با سرور.', 'is-error');
                })
                .finally(function () {
                    setButtonLoading(sendCodeButton, false, 'دریافت کد', 'در حال ارسال...');
                });
        });

        verifyCodeButton.addEventListener('click', function (event) {
            event.preventDefault();

            const mobile = mobileConfirmInput.value.trim();
            const code = codeInput.value.trim();

            if (!mobile) {
                setMessage('شماره موبایل نامعتبر است.', 'is-error');
                goToMobileStep();
                return;
            }

            if (!code) {
                setMessage('کد تایید را وارد کنید.', 'is-error');
                return;
            }

            setMessage('در حال تایید...', 'is-info');
            setButtonLoading(verifyCodeButton, true, 'تایید کد', 'در حال تایید...');

            fetch(limosmsMobileAuth.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: new URLSearchParams({
                    action: 'limosms_verify_otp',
                    nonce: limosmsMobileAuth.nonce,
                    mobile: mobile,
                    code: code
                })
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (!data.success) {
                        const message = data.data && data.data.message ? data.data.message : 'کد تایید نامعتبر است.';
                        setMessage(message, 'is-error');
                        return;
                    }

                    const message = data.data && data.data.message ? data.data.message : 'ورود با موفقیت انجام شد.';
                    const redirectUrl = data.data && data.data.redirectUrl ? data.data.redirectUrl : '';

                    setMessage(message, 'is-success');

                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    }
                })
                .catch(function () {
                    setMessage('خطا در ارتباط با سرور.', 'is-error');
                })
                .finally(function () {
                    setButtonLoading(verifyCodeButton, false, 'تایید کد', 'در حال تایید...');
                });
        });

        editMobileButton.addEventListener('click', function (event) {
            event.preventDefault();
            goToMobileStep();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileAuth);
    } else {
        initMobileAuth();
    }
})();
