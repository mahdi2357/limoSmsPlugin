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

        initCustomPatternSelect: function ($context) {
            const $container = $context && $context.length ? $context : $(document);

            $container.find('.limosms-pattern-selector').each(function () {
                const $select = $(this);

                if ($select.hasClass('select2-hidden-accessible') || $select.data('customized')) {
                    return;
                }

                const options = [];
                $select.find('option').each(function () {
                    const $opt = $(this);
                    const subtitle = $opt.attr('data-text') ? decodeURIComponent($opt.attr('data-text') || '') : '';
                    options.push({
                        value: $opt.attr('value') || '',
                        label: $opt.text() || '',
                        subtitle: subtitle,
                        disabled: $opt.prop('disabled')
                    });
                });

                const $wrapper = $('<div class="limosms-custom-select" aria-hidden="false"></div>');
                const $button = $('<button type="button" class="limosms-custom-select__button" aria-haspopup="listbox"></button>');
                const $list = $('<div class="limosms-custom-select__list" role="listbox"></div>');
                const $searchInput = $('<input type="text" class="limosms-custom-select__search" placeholder="جستجو...">');

                $list.append($searchInput);

                if (options.length === 0) {
                    $list.append('<div class="limosms-custom-select__noresults">هیچ گزینه‌ای نیست</div>');
                } else {
                    options.forEach(function (opt) {
                        const $item = $('<div class="limosms-custom-select__item" data-value="' + String(opt.value).replace(/"/g, '&quot;') + '"></div>');
                        const $label = $('<div class="limosms-custom-select__item-label"></div>').text(opt.label);
                        $item.append($label);

                        if (opt.subtitle) {
                            const $subtitle = $('<div class="limosms-custom-select__item-subtitle"></div>').text(opt.subtitle);
                            $item.append($subtitle);
                        }

                        if (opt.disabled) {
                            $item.attr('aria-disabled', 'true').addClass('is-disabled');
                        }
                        $list.append($item);
                    });
                }

                $searchInput.on('input', function () {
                    const query = String(this.value || '').toLowerCase().trim();
                    let visibleCount = 0;

                    $list.find('.limosms-custom-select__item').each(function () {
                        const $item = $(this);
                        const text = ($item.text() || '').toLowerCase();
                        if (!query || text.indexOf(query) !== -1) {
                            $item.show();
                            visibleCount++;
                        } else {
                            $item.hide();
                        }
                    });

                    $list.find('.limosms-custom-select__noresults').toggle(visibleCount === 0);
                });

                const $selectedOption = $select.find('option:selected');
                const selectedLabel = $selectedOption.length ? $selectedOption.text() : $select.attr('placeholder') || 'انتخاب پترن';
                $button.text(selectedLabel);

                $wrapper.append($button).append($list);
                $select.after($wrapper);
                $select.addClass('limosms-native-hidden');
                $select.data('customized', true);

                $button.on('click', function (e) {
                    e.preventDefault();
                    $list.toggle();
                });

                $list.on('click', '.limosms-custom-select__item', function () {
                    const $item = $(this);
                    if ($item.is('.is-disabled')) {
                        return;
                    }
                    const value = $item.data('value') || '';
                    $select.val(value).trigger('change');
                    $button.text($item.text());
                    $list.hide();
                    $list.find('.limosms-custom-select__item').removeClass('is-active');
                    $item.addClass('is-active');
                });

                $(document).on('click.limosmsTestPatternSelect', function (e) {
                    if (!$wrapper.is(e.target) && $wrapper.has(e.target).length === 0) {
                        $list.hide();
                    }
                });
            });
        },

        loadPatternOptions: function (selectedValue = "") {
            const $form = $("#limosms-send-form");
            if (!$form.length) {
                return;
            }

            const $select = $form.find('[name="patternId"]');
            const currentValue = $.trim($select.val() || selectedValue || "");
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
                            const rawId = String(pattern.id || pattern.pattern_id || pattern.patternCode || pattern.pattern_code || pattern.code || "").trim();
                            const patternId = rawId;
                            const patternText = String(pattern.message || pattern.pattern_text || pattern.text || "");
                            const title = String(pattern.title || pattern.patternTitle || pattern.name || "");
                            const label = patternId ? (title ? patternId + ' | ' + title : patternId) : 'بدون عنوان';
                            const isSelected = String(currentValue || selectedValue) === patternId ? " selected" : "";

                            html += '<option value="' + this.escapeHtml(patternId) + '" data-text="' + encodeURIComponent(patternText) + '" data-title="' + this.escapeHtml(title) + '"' + isSelected + '>';
                            html += this.escapeHtml(label);
                            html += "</option>";
                        }, this);

                        $select.html(html);

                        if ($.fn.select2 && $select.hasClass('select2-hidden-accessible')) {
                            $select.select2('destroy');
                        }

                        if (currentValue) {
                            $select.val(currentValue);
                        }

                        this.initCustomPatternSelect($form);
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
                    // AJAX error details suppressed in production
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
