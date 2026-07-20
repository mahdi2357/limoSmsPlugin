(function ($) {
    "use strict";

    const LimoSMSTestSMS = {
        initialized: false,

        init: function () {
            this.bindGlobalEvents();
            this.tryInitForm();
        },

        bindGlobalEvents: function () {
            $(document).on("limosms:tab-loaded", (e, activeTab) => {
                if (activeTab === "send-test-sms") {
                    this.tryInitForm();
                }
            });
        },

        tryInitForm: function () {
            const $form = $("#limosms-send-form");

            if (!$form.length) {
                return;
            }

            if ($form.data("limosmsInitialized")) {
                this.loadPatternOptions();
                this.validateForm(false);
                return;
            }

            $form.data("limosmsInitialized", true);

            this.bindFormEvents();
            this.loadPatternOptions();
            this.validateForm(false);
        },

        bindFormEvents: function () {
            $(document).on("input", '#limosms-send-form input[name="reciverNumber"]', (e) => {
                const $input = $(e.currentTarget);
                this.sanitizeReceiverInput($input);
                this.validateField("reciverNumber", true, false);
                this.validateForm(false);
            });

            $(document).on("blur", '#limosms-send-form input[name="reciverNumber"]', () => {
                this.validateField("reciverNumber", true, true);
                this.validateForm(false);
            });

            $(document).on("change", '#limosms-send-form select[name="patternId"]', () => {
                this.validateField("patternId", true, true);
                this.validateForm(false);
            });

            $(document).on("input", '#limosms-send-form input[name="message"]', () => {
                this.validateField("message", true, false);
                this.validateForm(false);
            });

            $(document).on("blur", '#limosms-send-form input[name="message"]', () => {
                this.validateField("message", true, true);
                this.validateForm(false);
            });

            $(document).on("submit", "#limosms-send-form", (e) => {
                e.preventDefault();
                this.submitForm();
            });
        },

        toEnglishDigits: function (str) {
            return String(str || "")
                .replace(/[۰-۹]/g, function (d) {
                    return "۰۱۲۳۴۵۶۷۸۹".indexOf(d);
                })
                .replace(/[٠-٩]/g, function (d) {
                    return "٠١٢٣٤٥٦٧٨٩".indexOf(d);
                });
        },

        sanitizeReceiverInput: function ($input) {
            let value = $input.val() || "";
            const maxLength = parseInt($input.attr("maxlength"), 10) || 11;

            value = this.toEnglishDigits(value);
            value = value.replace(/\D/g, "");

            if (value.length > maxLength) {
                value = value.substring(0, maxLength);
            }

            $input.val(value);
        },

        getField: function (name) {
            return $('#limosms-send-form [name="' + name + '"]');
        },

        getErrorBox: function (name) {
            return $('.limosms-field-error[data-for="' + name + '"]');
        },

        showFieldError: function (name, message) {
            const $field = this.getField(name);
            const $error = this.getErrorBox(name);

            $field.addClass("is-invalid");
            $error.text(message).addClass("is-visible");
        },

        clearFieldError: function (name) {
            const $field = this.getField(name);
            const $error = this.getErrorBox(name);

            $field.removeClass("is-invalid");
            $error.text("").removeClass("is-visible");
        },

        validateReceiverNumber: function (receiver) {
            receiver = String(receiver || "").trim();

            if (!receiver) {
                return {
                    valid: false,
                    message: "شماره گیرنده را وارد کنید."
                };
            }

            if (!receiver.startsWith("09")) {
                return {
                    valid: false,
                    message: "شماره موبایل باید با 09 شروع شود."
                };
            }

            if (receiver.length !== 11) {
                return {
                    valid: false,
                    message: "شماره موبایل باید دقیقاً 11 رقم باشد."
                };
            }

            if (!/^09\d{9}$/.test(receiver)) {
                return {
                    valid: false,
                    message: "فرمت شماره موبایل صحیح نیست."
                };
            }

            return {
                valid: true,
                message: ""
            };
        },

        validatePatternId: function (patternId) {
            patternId = String(patternId || "").trim();

            if (!patternId) {
                return {
                    valid: false,
                    message: "لطفاً یک پترن انتخاب کنید."
                };
            }

            return {
                valid: true,
                message: ""
            };
        },

        validateMessage: function (message) {
            message = String(message || "");

            if (message.length > 16) {
                return {
                    valid: false,
                    message: "متن پیام/توکن‌ها نباید بیشتر از 16 کاراکتر باشد."
                };
            }

            return {
                valid: true,
                message: ""
            };
        },

        validateField: function (name, updateUI = true, forceShow = false) {
            let result = { valid: true, message: "" };

            if (name === "reciverNumber") {
                result = this.validateReceiverNumber(this.getField(name).val());
            } else if (name === "patternId") {
                result = this.validatePatternId(this.getField(name).val());
            } else if (name === "message") {
                result = this.validateMessage(this.getField(name).val());
            }

            if (updateUI) {
                if (result.valid) {
                    this.clearFieldError(name);
                } else if (forceShow || this.getField(name).val()) {
                    this.showFieldError(name, result.message);
                } else {
                    this.clearFieldError(name);
                }
            }

            return result;
        },

        validateForm: function (showErrors = false) {
            const receiverValidation = this.validateField("reciverNumber", true, showErrors);
            const patternValidation = this.validateField("patternId", true, showErrors);
            const messageValidation = this.validateField("message", true, showErrors);

            const isValid =
                receiverValidation.valid &&
                patternValidation.valid &&
                messageValidation.valid;

            $("#limosms-send-form button[type='submit']").prop("disabled", !isValid);

            return isValid;
        },

        loadPatternOptions: function (selectedValue = "") {
            const $form = $("#limosms-send-form");
            if (!$form.length) {
                return;
            }

            const $select = $form.find('[name="patternId"]');
            $select.html('<option value="">در حال بارگذاری الگوها...</option>');
            // $select.prop("disabled", true);

            $.ajax({
                url: limosms_ajax.url,
                type: "POST",
                dataType: "json",
                data: {
                    action: "limosms_get_patterns",
                    nonce: limosms_ajax.nonce
                },
                success: (response) => {
                    if (
                        response &&
                        response.success &&
                        response.data &&
                        response.data.data &&
                        Array.isArray(response.data.data)
                    ) {
                        let html = '<option value="">انتخاب پترن</option>';

                        response.data.data.forEach(function (pattern) {
                            const patternId = String(pattern.id || "");
                            const patternText = String(pattern.message || "");
                            const isSelected = String(selectedValue) === patternId ? " selected" : "";

                            html += '<option value="' + this.escapeHtml(patternId) + '"' + isSelected + '>';
                            html += this.escapeHtml(patternId);

                            if (patternText) {
                                html += " - " + this.escapeHtml(patternText);
                            }

                            html += "</option>";
                        }, this);

                        $select.html(html);
                    } else {
                        $select.html('<option value="">پترنی یافت نشد</option>');
                    }

                    $select.prop("disabled", false);
                    this.validateForm(false);
                },
                error: () => {
                    $select.html('<option value="">خطا در بارگذاری الگوها</option>');
                    $select.prop("disabled", false);
                    this.validateForm(false);
                }
            });
        },

        submitForm: function () {
            const $form = $("#limosms-send-form");

            if (!$form.length) {
                return;
            }

            if (!this.validateForm(true)) {
                return;
            }

            const $button = $form.find('button[type="submit"]');
            const originalText = $button.text();

            $button.prop("disabled", true).text("در حال ارسال...");

            $.ajax({
                url: limosms_ajax.url,
                type: "POST",
                dataType: "json",
                data: {
                    action: "limosms_send_test_sms",
                    nonce: limosms_ajax.nonce,
                    reciverNumber: $.trim($form.find('[name="reciverNumber"]').val()),
                    patternId: $.trim($form.find('[name="patternId"]').val()),
                    message: $.trim($form.find('[name="message"]').val())
                },
                success: (response) => {
                    if (response && response.success) {
                        if (window.LimoSMS && typeof window.LimoSMS.showToast === "function") {
                            window.LimoSMS.showToast(
                                (response.data && response.data.message) ? response.data.message : "پیامک تست با موفقیت ارسال شد.",
                                "success"
                            );
                        }

                        $form[0].reset();
                        this.clearFieldError("reciverNumber");
                        this.clearFieldError("patternId");
                        this.clearFieldError("message");
                        this.validateForm(false);
                    } else {
                        const errorMessage =
                            response && response.data && response.data.message
                                ? response.data.message
                                : "ارسال پیامک تست ناموفق بود.";

                        if (window.LimoSMS && typeof window.LimoSMS.showToast === "function") {
                            window.LimoSMS.showToast(errorMessage, "error");
                        }
                    }
                },
                error: (xhr) => {
                    console.error("LimoSMS send test sms error:", xhr);

                    if (window.LimoSMS && typeof window.LimoSMS.showToast === "function") {
                        window.LimoSMS.showToast("خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.", "error");
                    }
                },
                complete: () => {
                    $button.text(originalText);
                    this.validateForm(false);
                }
            });
        },

        escapeHtml: function (str) {
            return String(str)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    };

    $(function () {
        LimoSMSTestSMS.init();
    });

})(jQuery);
