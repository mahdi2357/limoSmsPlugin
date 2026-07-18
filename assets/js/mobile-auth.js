(function () {

    function initMobileAuth() {
        const authBox = document.querySelector('.limosms-mobile-auth');

        if (!authBox) {
            return;
        }

        const mobileStep = authBox.querySelector('[data-step="mobile"]');
        const codeStep = authBox.querySelector('[data-step="code"]');
        const mobileInput = authBox.querySelector('#limosms_mobile');
        const mobileConfirmInput = authBox.querySelector('#limosms_mobile_confirm');
        const sendCodeButton = authBox.querySelector('#limosms-send-code');
        const editMobileButton = authBox.querySelector('#limosms-edit-mobile');
        const messageBox = authBox.querySelector('.limosms-mobile-auth__message');

        if (!mobileStep || !codeStep || !mobileInput || !mobileConfirmInput || !sendCodeButton || !editMobileButton || !messageBox) {
            return;
        }

        sendCodeButton.addEventListener('click', function (event) {
            event.preventDefault();
            const mobile = mobileInput.value.trim();

            if (!mobile) {
                messageBox.textContent = 'شماره موبایل را وارد کنید.';
                return;
            }

            messageBox.textContent = 'در حال ارسال...';

            // لاگ برای چک کردن URL
            console.log('Sending to:', limosmsMobileAuth.ajaxUrl);

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
                .then(response => {
                    console.log('Response Status:', response.status); // این را در کنسول ببینید
                    return response.json();
                })
                .then(data => {
                    console.log('Response Data:', data); // دیتا اینجا چاپ می‌شود
                    if (!data.success) {
                        messageBox.textContent = data.data.message;
                    } else {
                        messageBox.textContent = 'کد ارسال شد.';
                        // مرحله بعد...
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error); // ارور احتمالی اینجا چاپ می‌شود
                    messageBox.textContent = 'خطا در ارتباط با سرور.';
                });
        });


        editMobileButton.addEventListener('click', function (event) {
            event.preventDefault();

            codeStep.hidden = true;
            mobileStep.hidden = false;
            messageBox.textContent = '';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileAuth);
    } else {
        initMobileAuth();
    }
})();
