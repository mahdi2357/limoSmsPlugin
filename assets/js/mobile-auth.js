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
        var mobileConfirmInput = authBox.querySelector("#limosms_mobile_confirm");
        var codeInput = authBox.querySelector("#limosms_code");
        var sendCodeButton = authBox.querySelector("#limosms-send-code");
        var verifyCodeButton = authBox.querySelector("#limosms-verify-code");
        var editMobileButton = authBox.querySelector("#limosms-edit-mobile");
        var messageBox = authBox.querySelector(".limosms-mobile-auth__message");

        var challengeToken = "";
        var sendCooldownTimer = null;

        var sendButtonDefaultText = sendCodeButton ? sendCodeButton.textContent.trim() : "دریافت کد تایید";
        var verifyButtonDefaultText = verifyCodeButton ? verifyCodeButton.textContent.trim() : "ورود به حساب";

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
            messageBox.textContent = message || "";
            messageBox.classList.remove("is-error", "is-success", "is-info");

            if (type) {
                messageBox.classList.add(type);
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

        function normalizeMobile(value) {
            var mobile = normalizeDigits(value).replace(/[^\d]/g, "");

            if (mobile.indexOf("98") === 0) {
                mobile = "0" + mobile.substring(2);
            }

            if (mobile.length === 10 && mobile.charAt(0) === "9") {
                mobile = "0" + mobile;
            }

            return mobile;
        }

        function isValidMobile(value) {
            return /^09\d{9}$/.test(normalizeMobile(value));
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

        mobileInput.addEventListener("input", function () {
            this.value = normalizeMobile(this.value).slice(0, 11);
        });

        mobileConfirmInput.addEventListener("input", function () {
            this.value = normalizeMobile(this.value).slice(0, 11);
        });

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

            var mobile = normalizeMobile(mobileInput.value);
            mobileInput.value = mobile;

            if (!isValidMobile(mobile)) {
                setMessage("شماره موبایل باید با 09 شروع شود و 11 رقم باشد.", "is-error");
                mobileInput.focus();
                return;
            }

            setMessage("در حال ارسال کد...", "is-info");
            setButtonState(sendCodeButton, true, "در حال ارسال...");

            postAjax({
                action: "limosms_send_otp",
                nonce: limosmsMobileAuth.nonce,
                mobile: mobile
            })
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

            var mobile = normalizeMobile(mobileConfirmInput.value);
            var code = normalizeCode(codeInput.value);
            var otpLength = getOtpLength();

            mobileConfirmInput.value = mobile;
            codeInput.value = code;

            if (!challengeToken) {
                setMessage("ابتدا کد تایید دریافت کنید.", "is-error");
                goToMobileStep();
                return;
            }

            if (!isValidMobile(mobile)) {
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
                challenge_token: challengeToken
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
