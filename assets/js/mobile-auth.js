(function () {
    "use strict";

    function initMobileAuth() {
        var authBox = document.querySelector(".limosms-mobile-auth");

        if (!authBox || !window.limosmsMobileAuth) {
            return;
        }

        var mobileStep = authBox.querySelector('[data-step="mobile"]');
        var codeStep = authBox.querySelector('[data-step="code"]');
        var mobileInput = authBox.querySelector("#limosms_mobile");
        var countryCodeInput = authBox.querySelector("#limosms_country_code");
        var mobileConfirmInput = authBox.querySelector("#limosms_mobile_confirm");
        var authModeInput = authBox.querySelector("#limosms_auth_mode");
        var modeButtons = authBox.querySelectorAll(".limosms-mobile-auth__mode-button");
        var registrationFieldsContainer = authBox.querySelector("#limosms_register_fields");
        var registrationFieldInputs = authBox.querySelectorAll(".limosms-mobile-auth__registration-field");
        var captchaInput = authBox.querySelector("#limosms_captcha");
        var captchaTokenInput = authBox.querySelector("#limosms_captcha_token");
        var captchaRefreshButton = authBox.querySelector("#limosms-refresh-captcha");
        var codeInput = authBox.querySelector("#limosms_code");
        var sendCodeButton = authBox.querySelector("#limosms-send-code");
        var verifyCodeButton = authBox.querySelector("#limosms-verify-code");
        var editMobileButton = authBox.querySelector("#limosms-edit-mobile");
        var messageBox = authBox.querySelector(".limosms-mobile-auth__message");

        var challengeToken = "";
        var sendCooldownTimer = null;

        var sendButtonDefaultText = sendCodeButton ? sendCodeButton.textContent.trim() : "دریافت کد تایید";
        var verifyButtonDefaultText = verifyCodeButton ? verifyCodeButton.textContent.trim() : "ورود به حساب";

        var captchaEnabled = Boolean( limosmsMobileAuth.captchaEnabled );
        var currentMode = authModeInput && authModeInput.value ? authModeInput.value : "login";

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

        function refreshCaptcha() {
            if ( ! captchaRefreshButton ) {
                return;
            }

            setButtonState(captchaRefreshButton, true, 'در حال تازه‌سازی...');

            postAjax({
                action: 'limosms_refresh_captcha',
                nonce: limosmsMobileAuth.nonce,
            })
                .then(function (data) {
                    if ( data && data.success && data.data ) {
                        if ( captchaInput ) {
                            captchaInput.value = '';
                        }
                        if ( captchaTokenInput ) {
                            captchaTokenInput.value = data.data.token || '';
                        }
                        var captchaQuestion = authBox.querySelector('.limosms-mobile-auth__captcha-question');
                        if ( captchaQuestion ) {
                            captchaQuestion.textContent = data.data.question || '';
                        }
                        setMessage('کپچا تازه شد. لطفا دوباره پاسخ را وارد کنید.', 'is-success');
                    } else {
                        setMessage(data && data.data && data.data.message ? data.data.message : 'خطا در تازه‌سازی کپچا.', 'is-error');
                    }
                })
                .catch(function () {
                    setMessage('خطا در ارتباط با سرور.', 'is-error');
                })
                .finally(function () {
                    setButtonState(captchaRefreshButton, false, 'تازه‌سازی کپچا');
                });
        }

        if ( captchaRefreshButton ) {
            captchaRefreshButton.addEventListener('click', function (event) {
                event.preventDefault();
                refreshCaptcha();
            });
        }

        function setMessage(message, type) {
            messageBox.textContent = message || "";
            messageBox.classList.remove("is-error", "is-success", "is-info");

            if (type) {
                messageBox.classList.add(type);
            }
        }

        function updateMode(mode) {
            currentMode = mode === "register" ? "register" : "login";

            if (authModeInput) {
                authModeInput.value = currentMode;
            }

            if (registrationFieldsContainer) {
                var shouldShowRegistrationFields = currentMode === "register";
                registrationFieldsContainer.hidden = !shouldShowRegistrationFields;
                registrationFieldsContainer.classList.toggle("is-hidden", !shouldShowRegistrationFields);
                registrationFieldsContainer.setAttribute("aria-hidden", shouldShowRegistrationFields ? "false" : "true");
            }

            registrationFieldInputs.forEach(function (input) {
                var shouldEnable = currentMode === "register";
                input.disabled = !shouldEnable;
                input.setAttribute("aria-hidden", shouldEnable ? "false" : "true");

                if (shouldEnable) {
                    if (input.getAttribute("data-required") === "1") {
                        input.setAttribute("required", "");
                    } else {
                        input.removeAttribute("required");
                    }
                } else {
                    input.removeAttribute("required");
                }
            });

            modeButtons.forEach(function (button) {
                var isActive = button.getAttribute("data-mode") === currentMode;
                button.classList.toggle("is-active", isActive);
                button.setAttribute("aria-pressed", isActive ? "true" : "false");
            });

            if (currentMode === "register") {
                setMessage("برای ثبت‌نام، اطلاعات تکمیلی را وارد کنید.", "is-info");
            } else {
                setMessage("", "");
            }
        }

        function setButtonState(button, disabled, text) {
            if (!button) {
                return;
            }

            button.disabled = !!disabled;

            if (typeof text === "string") {
                button.textContent = text;
            }
        }

        function normalizeDigits(value) {
            var map = {
                "۰": "0", "۱": "1", "۲": "2", "۳": "3", "۴": "4",
                "۵": "5", "۶": "6", "۷": "7", "۸": "8", "۹": "9",
                "٠": "0", "١": "1", "٢": "2", "٣": "3", "٤": "4",
                "٥": "5", "٦": "6", "٧": "7", "٨": "8", "٩": "9"
            };

            return String(value || "").replace(/[۰-۹٠-٩]/g, function (char) {
                return map[char] || char;
            });
        }

        function normalizeCountryCode(value) {
            var digits = normalizeDigits(value).replace(/[^\d]/g, "");
            if (!digits) {
                return "";
            }
            return "+" + digits.slice(0, 4);
        }

        function normalizeMobile(value, countryCode) {
            var phoneDigits = normalizeDigits(value).replace(/[^\d]/g, "");
            var countryCodeValue = normalizeCountryCode(countryCode || "").replace(/[^\d]/g, "");

            if (!phoneDigits) {
                return "";
            }

            if (countryCodeValue) {
                if (phoneDigits.indexOf(countryCodeValue) === 0) {
                    return "+" + phoneDigits;
                }

                if (phoneDigits.indexOf("0") === 0 && countryCodeValue === "98") {
                    return "+" + countryCodeValue + phoneDigits.substring(1);
                }

                return "+" + countryCodeValue + phoneDigits;
            }

            if (phoneDigits.indexOf("98") === 0) {
                return "+" + phoneDigits;
            }

            if (phoneDigits.length === 10 && phoneDigits.charAt(0) === "9") {
                return "+98" + phoneDigits;
            }

            return phoneDigits.startsWith("0") ? phoneDigits : "+" + phoneDigits;
        }

        function isValidMobile(value, countryCode) {
            var normalized = normalizeMobile(value, countryCode);
            if (!normalized) {
                return false;
            }

            var digits = normalized.replace(/[^\d]/g, "");

            if (digits.indexOf("98") === 0) {
                return /^98\d{9,10}$/.test(digits);
            }

            if (digits.indexOf("0") === 0) {
                return /^0\d{9,10}$/.test(digits);
            }

            return /^\+\d{1,4}\d{6,12}$/.test(normalized);
        }

        function normalizeCode(value) {
            return normalizeDigits(value).replace(/[^\d]/g, "");
        }

        function getOtpLength() {
            var len = Number(limosmsMobileAuth.otpLength || 6);
            return len > 0 ? len : 6;
        }

        function isValidCode(value) {
            var otpLength = getOtpLength();
            var code = normalizeCode(value);
            var pattern = new RegExp("^\\d{" + otpLength + "}$");
            return pattern.test(code);
        }

        function goToCodeStep(mobile) {
            mobileConfirmInput.value = mobile;
            mobileStep.hidden = true;
            codeStep.hidden = false;
            codeInput.value = "";
            codeInput.focus();
        }

        function goToMobileStep() {
            codeStep.hidden = true;
            mobileStep.hidden = false;
            codeInput.value = "";
            challengeToken = "";
            setMessage("", "");
            setButtonState(verifyCodeButton, false, verifyButtonDefaultText);
            mobileInput.focus();
        }

        function clearSendCooldown() {
            if (sendCooldownTimer) {
                window.clearInterval(sendCooldownTimer);
                sendCooldownTimer = null;
            }
        }

        function startSendCooldown(seconds) {
            var remaining = Number(seconds || limosmsMobileAuth.sendCooldown || 60);
            if (remaining < 1) {
                remaining = 60;
            }

            clearSendCooldown();
            setButtonState(sendCodeButton, true, "ارسال مجدد (" + remaining + ")");

            sendCooldownTimer = window.setInterval(function () {
                remaining -= 1;

                if (remaining <= 0) {
                    clearSendCooldown();
                    setButtonState(sendCodeButton, false, sendButtonDefaultText);
                    return;
                }

                setButtonState(sendCodeButton, true, "ارسال مجدد (" + remaining + ")");
            }, 1000);
        }

        function parseJsonResponse(response) {
            return response.text().then(function (text) {
                try {
                    return JSON.parse(text);
                } catch (error) {
                    return {
                        success: false,
                        data: {
                            message: "پاسخ سرور معتبر نیست."
                        }
                    };
                }
            });
        }

        function postAjax(data) {
            return fetch(limosmsMobileAuth.ajaxUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
                },
                body: new URLSearchParams(data).toString(),
                credentials: "same-origin"
            }).then(parseJsonResponse);
        }

        modeButtons.forEach(function (button) {
            button.addEventListener("click", function (event) {
                event.preventDefault();
                updateMode(button.getAttribute("data-mode") || "login");
            });
        });

        mobileInput.addEventListener("input", function () {
            this.value = normalizeDigits(this.value).replace(/[^\d]/g, "").slice(0, 12);
        });

        if (countryCodeInput) {
            countryCodeInput.addEventListener("input", function () {
                this.value = normalizeCountryCode(this.value).slice(0, 5);
            });
        }

        codeInput.addEventListener("input", function () {
            this.value = normalizeCode(this.value).slice(0, getOtpLength());
        });

        mobileInput.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                sendCodeButton.click();
            }
        });

        codeInput.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                verifyCodeButton.click();
            }
        });

        sendCodeButton.addEventListener("click", function (event) {
            event.preventDefault();

            if (sendCodeButton.disabled) {
                return;
            }

            var countryCode = countryCodeInput ? countryCodeInput.value : "";
            var mobile = normalizeMobile(mobileInput.value, countryCode);
            mobileInput.value = mobileInput.value;
            var captchaAnswer = captchaEnabled && captchaInput ? normalizeDigits(captchaInput.value).replace(/[^\d]/g, "") : "";
            var captchaToken = captchaEnabled && captchaTokenInput ? captchaTokenInput.value : "";

            if (!isValidMobile(mobileInput.value, countryCode)) {
                setMessage("شماره موبایل باید با کد کشور معتبر و شماره همراه وارد شود.", "is-error");
                mobileInput.focus();
                return;
            }

            if ( captchaEnabled ) {
                if ( ! captchaAnswer ) {
                    setMessage("لطفا پاسخ کپچا را وارد کنید.", "is-error");
                    if ( captchaInput ) {
                        captchaInput.focus();
                    }
                    return;
                }

                if ( ! captchaToken ) {
                    setMessage("خطا در کپچا. صفحه را دوباره بارگذاری کنید.", "is-error");
                    return;
                }
            }

            setMessage("در حال ارسال کد...", "is-info");
            setButtonState(sendCodeButton, true, "در حال ارسال...");

            var requestData = {
                action: "limosms_send_otp",
                nonce: limosmsMobileAuth.nonce,
                mobile: mobile,
                mode: currentMode
            };

            if ( captchaEnabled ) {
                requestData.captcha_answer = captchaAnswer;
                requestData.captcha_token = captchaToken;
            }

            postAjax(requestData)
                .then(function (data) {
                    if (!data || !data.success) {
                        var errorMessage =
                            data && data.data && data.data.message
                                ? data.data.message
                                : "ارسال کد انجام نشد.";
                        setMessage(errorMessage, "is-error");
                        setButtonState(sendCodeButton, false, sendButtonDefaultText);
                        return;
                    }

                    challengeToken =
                        data.data && data.data.challengeToken
                            ? data.data.challengeToken
                            : "";

                    if (!challengeToken) {
                        setMessage("پاسخ سرور نامعتبر است. دوباره تلاش کنید.", "is-error");
                        setButtonState(sendCodeButton, false, sendButtonDefaultText);
                        return;
                    }

                    setMessage("کد تایید ارسال شد.", "is-success");
                    goToCodeStep(mobile);

                    startSendCooldown(
                        Number(limosmsMobileAuth.sendCooldown || 60)
                    );
                })
                .catch(function () {
                    setMessage("خطا در ارتباط با سرور.", "is-error");
                    setButtonState(sendCodeButton, false, sendButtonDefaultText);
                });
        });

        verifyCodeButton.addEventListener("click", function (event) {
            event.preventDefault();

            if (verifyCodeButton.disabled) {
                return;
            }

            var mobile = normalizeMobile(mobileConfirmInput.value, countryCodeInput ? countryCodeInput.value : "");
            var code = normalizeCode(codeInput.value);
            var otpLength = getOtpLength();

            mobileConfirmInput.value = mobile;
            codeInput.value = code;

            if (!challengeToken) {
                setMessage("ابتدا کد تایید دریافت کنید.", "is-error");
                goToMobileStep();
                return;
            }

            if (!isValidMobile(mobile, countryCodeInput ? countryCodeInput.value : "")) {
                setMessage("شماره موبایل معتبر نیست.", "is-error");
                goToMobileStep();
                return;
            }

            if (!isValidCode(code)) {
                setMessage("کد تایید باید " + otpLength + " رقم باشد.", "is-error");
                codeInput.focus();
                return;
            }

            setMessage("در حال بررسی کد...", "is-info");
            setButtonState(verifyCodeButton, true, "در حال بررسی...");

            postAjax({
                action: "limosms_verify_otp",
                nonce: limosmsMobileAuth.nonce,
                mobile: mobile,
                code: code,
                challenge_token: challengeToken,
                mode: currentMode
            })
                .then(function (data) {
                    if (!data || !data.success) {
                        var errorMessage =
                            data && data.data && data.data.message
                                ? data.data.message
                                : "کد تایید صحیح نیست.";
                        setMessage(errorMessage, "is-error");
                        setButtonState(verifyCodeButton, false, verifyButtonDefaultText);
                        return;
                    }

                    var successMessage =
                        data.data && data.data.message
                            ? data.data.message
                            : "ورود با موفقیت انجام شد.";

                    var redirectUrl =
                        data.data && data.data.redirectUrl
                            ? data.data.redirectUrl
                            : limosmsMobileAuth.redirectUrl;

                    setMessage(successMessage, "is-success");
                    setButtonState(verifyCodeButton, false, verifyButtonDefaultText);

                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    }
                })
                .catch(function () {
                    setMessage("خطا در ارتباط با سرور.", "is-error");
                    setButtonState(verifyCodeButton, false, verifyButtonDefaultText);
                });
        });

        editMobileButton.addEventListener("click", function (event) {
            event.preventDefault();
            goToMobileStep();
        });

        updateMode(currentMode);

        window.addEventListener("beforeunload", function () {
            clearSendCooldown();
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initMobileAuth);
    } else {
        initMobileAuth();
    }
})();
