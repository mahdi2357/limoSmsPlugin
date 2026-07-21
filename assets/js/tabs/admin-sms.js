(function ($) {
    'use strict';

    let patternsCache = [];
    let isLoadingPatterns = false;
    let hasLoadedPatterns = false;
    let initialAdminState = null;

    function normalizeAdminPatternMap(patternMap) {
        const normalized = {};
        Object.keys(patternMap || {}).sort(function (a, b) {
            return String(a).localeCompare(String(b), undefined, { numeric: true });
        }).forEach(function (key) {
            normalized[String(key)] = String(patternMap[key] || '');
        });
        return normalized;
    }

    function getAdminCurrentState() {
        const state = {
            admin_phones: String($('#limosms_admin_phones').val() || '').trim(),
            events: {}
        };

        $('.limosms-event-card').each(function () {
            const card = $(this);
            const eventKey = String(card.data('event') || '').trim();
            const enabled = card.find('.limosms-event-enabled').is(':checked');
            const otpId = String(card.find('.limosms-event-otp-id').val() || '').trim();
            const patternTitle = String(card.find('.limosms-event-pattern-title').val() || '').trim();
            const patternText = String(card.find('.limosms-pattern-text').text() || '').trim();
            const patternMap = {};

            card.find('.limosms-pattern-select').each(function () {
                const input = $(this);
                const paramIndex = String(input.data('param'));
                const tokenValue = String(input.val() || '').trim();
                if (paramIndex !== '' && tokenValue !== '') {
                    patternMap[paramIndex] = tokenValue;
                }
            });

            state.events[eventKey] = {
                enabled: enabled ? 'yes' : 'no',
                otp_id: enabled ? otpId : '',
                title: enabled ? patternTitle : '',
                pattern_text: enabled ? patternText : '',
                pattern_map: enabled ? normalizeAdminPatternMap(patternMap) : {}
            };
        });

        return state;
    }

    function serializeAdminState(state) {
        const normalized = {
            admin_phones: String(state.admin_phones || '').trim(),
            events: {}
        };

        Object.keys(state.events || {}).sort().forEach(function (eventKey) {
            const eventData = state.events[eventKey] || {};
            normalized.events[eventKey] = {
                enabled: eventData.enabled === 'yes' ? 'yes' : 'no',
                otp_id: String(eventData.otp_id || '').trim(),
                title: String(eventData.title || '').trim(),
                pattern_text: String(eventData.pattern_text || '').trim(),
                pattern_map: normalizeAdminPatternMap(eventData.pattern_map || {})
            };
        });

        return JSON.stringify(normalized);
    }

    function isAdminDirty() {
        if (!initialAdminState) {
            return false;
        }
        return serializeAdminState(getAdminCurrentState()) !== serializeAdminState(initialAdminState);
    }

    function setAdminSaveButtonState(isDirty) {
        $('#limosms-save-otp-settings').prop('disabled', !isDirty);
    }

    function updateAdminSaveButtonState() {
        const dirty = isAdminDirty();
        setAdminSaveButtonState(dirty);
        return dirty;
    }

    function setAdminPhonesError(message) {
        const field = $('#limosms_admin_phones');
        let errorContainer = field.siblings('.limosms-admin-phones-error');

        if (!field.length) {
            return;
        }

        if (!errorContainer.length) {
            errorContainer = $('<div class="limosms-admin-phones-error" style="color: #ef4444; font-size: 12px; margin-top: 5px; display: none;"></div>');
            field.after(errorContainer);
        }

        if (message) {
            field.css({
                'border-color': '#ef4444',
                'box-shadow': '0 0 0 1px rgba(239, 68, 68, 0.2)'
            });
            errorContainer.text(message).show();
        } else {
            field.css({
                'border-color': '',
                'box-shadow': ''
            });
            errorContainer.hide().text('');
        }
    }

    function validateAdminPhones(value) {
        const cleanValue = String(value || '').trim().replace(/^,+|,+$/g, '');

        if (!cleanValue) {
            return {
                valid: true,
                message: ''
            };
        }

        const normalized = cleanValue
            .replace(/[۰-۹]/g, function (digit) {
                return '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit);
            })
            .replace(/[٠-٩]/g, function (digit) {
                return '٠١٢٣٤٥٦٧٨٩'.indexOf(digit);
            });

        if (/[^0-9,]/.test(normalized)) {
            return {
                valid: false,
                message: 'فقط وارد کردن اعداد انگلیسی و کاما (,) مجاز است.'
            };
        }

        if (normalized.startsWith(',') || normalized.endsWith(',')) {
            return {
                valid: false,
                message: 'شماره تلفن نباید با کاما شروع یا تمام شود.'
            };
        }

        if (/,{2,}/.test(normalized)) {
            return {
                valid: false,
                message: 'لطفاً از وارد کردن کامای پشت سر هم خودداری کنید.'
            };
        }

        const phones = normalized.split(',');

        for (let index = 0; index < phones.length; index++) {
            const phone = phones[index];

            if (phone && !/^09\d{9}$/.test(phone)) {
                return {
                    valid: false,
                    message: 'هر شماره موبایل وارد شده باید با 09 شروع شده و ۱۱ رقم باشد.'
                };
            }
        }

        return {
            valid: true,
            message: ''
        };
    }

    function extractToastMessage(payload, fallback) {
        if (payload === null || payload === undefined || payload === '') {
            return fallback;
        }

        if (typeof payload === 'string') {
            const trimmed = payload.trim();
            return trimmed || fallback;
        }

        if (typeof payload === 'object') {
            if (typeof payload.message === 'string' && payload.message.trim()) {
                return payload.message.trim();
            }

            if (typeof payload.error === 'string' && payload.error.trim()) {
                return payload.error.trim();
            }

            if (payload.data) {
                return extractToastMessage(payload.data, fallback);
            }

            if (Array.isArray(payload)) {
                return payload.join(' ') || fallback;
            }
        }

        return String(payload) || fallback;
    }

    function showNotification(message, type) {
        const fallbackMessage = type === 'success'
            ? 'تنظیمات با موفقیت ذخیره شد.'
            : 'عملیات با خطا مواجه شد.';
        const safeMessage = extractToastMessage(message, fallbackMessage);

        if (window.LimoSMS && typeof window.LimoSMS.showToast === 'function') {
            window.LimoSMS.showToast(safeMessage, type || 'error');
            return;
        }

        window.alert(safeMessage);
    }

    function getPatternCode(pattern) {
        return pattern.id || pattern.pattern_id || pattern.otp_id || '';
    }

    function getPatternTitle(pattern) {
        return pattern.title || pattern.pattern_title || pattern.name || pattern.pattern_name || '';
    }

    function getPatternText(pattern) {
        return (
            pattern.pattern_text ||
            pattern.pattern ||
            pattern.message ||
            pattern.text ||
            pattern.content ||
            pattern.body ||
            pattern.sms ||
            ''
        );
    }

    function normalizePattern(pattern) {
        if (!pattern || typeof pattern !== 'object') {
            return null;
        }

        const id = String(getPatternCode(pattern) || '').trim();
        if (!id) {
            return null;
        }

        return {
            raw: pattern,
            id: id,
            title: String(getPatternTitle(pattern) || '').trim(),
            text: String(getPatternText(pattern) || '')
        };
    }

    function findPatternById(patternId) {
        patternId = String(patternId || '').trim();

        if (!patternId) {
            return null;
        }

        for (let index = 0; index < patternsCache.length; index++) {
            if (String(patternsCache[index].id) === patternId) {
                return patternsCache[index];
            }
        }

        return null;
    }

    function buildPatternOptionLabel(pattern) {
        const code = String(pattern.id || '');
        const title = String(pattern.title || '');

        if (code && title) {
            return code + ' | ' + title;
        }

        if (code) {
            return code;
        }

        if (title) {
            return title;
        }

        return 'بدون عنوان';
    }

    function buildPatternOptions(patterns, selectedValue) {
        let html = '<option value="">انتخاب الگو...</option>';

        patterns.forEach(function (pattern) {
            if (!pattern || typeof pattern !== 'object') {
                return;
            }

            const patternId = String(pattern.id || '').trim();
            const label = buildPatternOptionLabel(pattern);
            const selected = patternId && String(selectedValue || '') === patternId ? ' selected="selected"' : '';

            if (!patternId) {
                return;
            }

            html += '<option value="' + patternId.replace(/"/g, '&quot;') + '"' + selected + '>' + label + '</option>';
        });

        return html;
    }

    function maybeInitSelect2(context) {
        const container = context || $(document);
        if (!$.fn.select2) {
            return;
        }

        const selects = $(container)
            .filter('.limosms-pattern-selector')
            .add($(container).find('.limosms-pattern-selector'));

        selects.each(function () {
            const select = $(this);
            if (select.hasClass('select2-hidden-accessible')) {
                return;
            }

            select.select2({
                width: '100%',
                dir: 'rtl',
                placeholder: 'انتخاب الگو...',
                allowClear: true,
                templateResult: function (item) {
                    if (!item.id) {
                        return item.text;
                    }
                    return item.text;
                },
                templateSelection: function (item) {
                    if (!item.id) {
                        return item.text;
                    }
                    return item.text;
                },
                escapeMarkup: function (markup) {
                    return markup;
                }
            });
        });
    }

    function normalizeToken(token) {
        return String(token || '').replace(/[{}]/g, '');
    }

    function getSavedMap(mappingContainer) {
        try {
            const map = JSON.parse(mappingContainer.attr('data-saved-map') || '{}');
            return map && typeof map === 'object' ? map : {};
        } catch (error) {
            return {};
        }
    }

    function getSavedToken(savedMap, index) {
        const item = savedMap[index];

        if (!item) {
            return '';
        }

        return typeof item === 'object' && item !== null ? (item.token || '') : item;
    }

    function isSameToken(firstToken, secondToken) {
        return normalizeToken(firstToken) === normalizeToken(secondToken);
    }

    function enableSaveButton() {
        setAdminSaveButtonState(true);
    }

    function disableSaveButton() {
        setAdminSaveButtonState(false);
    }

    function toggleEventFields(card, enabled) {
        const fields = card.find('.limosms-field-pattern-id, .limosms-field-pattern-text, .limosms-field-pattern-map');

        if (enabled) {
            fields.stop(true, true).slideDown(200);
        } else {
            fields.stop(true, true).slideUp(200);
        }
    }

    function populateAllSelectors(patterns) {
        $('.limosms-pattern-selector').each(function () {
            const select = $(this);
            const card = select.closest('.limosms-event-card');
            const savedId = String(card.find('.limosms-event-otp-id').val() || '');
            const optionsHtml = buildPatternOptions(patterns, savedId);

            if ($.fn.select2 && select.hasClass('select2-hidden-accessible')) {
                select.select2('destroy');
            }

            select.html(optionsHtml);
            maybeInitSelect2(select);

            if (savedId) {
                select.trigger('change');
            }
        });
    }

    function loadAllPatterns(forceReload, callback) {
        if (isLoadingPatterns) {
            if (typeof callback === 'function') {
                callback();
            }
            return;
        }

        if (hasLoadedPatterns && !forceReload) {
            populateAllSelectors(patternsCache);
            if (typeof callback === 'function') {
                callback();
            }
            return;
        }

        isLoadingPatterns = true;

        $.ajax({
            url: limosms_ajax.url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'limosms_get_patterns',
                nonce: limosms_ajax.nonce
            }
        }).done(function (response) {
            let rawPatterns = [];

            if (!response || !response.success) {
                showNotification(
                    response && response.data && response.data.message
                        ? response.data.message
                        : 'دریافت لیست الگوها ناموفق بود.',
                    'error'
                );
                return;
            }

            if (response.data && Array.isArray(response.data.data)) {
                rawPatterns = response.data.data;
            } else if (Array.isArray(response.data)) {
                rawPatterns = response.data;
            } else if (response.data && Array.isArray(response.data.patterns)) {
                rawPatterns = response.data.patterns;
            }

            patternsCache = rawPatterns
                .map(normalizePattern)
                .filter(function (item) {
                    return item && item.id;
                });

            hasLoadedPatterns = true;
            populateAllSelectors(patternsCache);
            maybeInitSelect2($(document));
        }).fail(function () {
            showNotification('خطا در ارتباط با سرور هنگام دریافت الگوها.', 'error');
        }).always(function () {
            isLoadingPatterns = false;
            if (typeof callback === 'function') {
                callback();
            }
        });
    }

    function renderPatternMapping(card) {
        const textBox = card.find('.limosms-pattern-text');
        const eventKey = textBox.data('event');
        const textValue = textBox.text();
        const mappingContainer = card.find('.limosms-pattern-mapping');
        const savedMap = getSavedMap(mappingContainer);

        const matches = [...new Set(
            (textValue.match(/\{(\d+)\}/g) || []).map(function (match) {
                return parseInt(match.match(/\d+/)[0], 10);
            })
        )].sort(function (first, second) {
            return first - second;
        });

        const tokens = typeof limosmsTokens !== 'undefined' && limosmsTokens[eventKey]
            ? limosmsTokens[eventKey]
            : {};

        if (!matches.length) {
            mappingContainer.html('<div class="limosms-pattern-empty">متغیری یافت نشد.</div>');
            return;
        }

        if (!Object.keys(tokens).length) {
            mappingContainer.html('<div class="limosms-pattern-empty">توکنی برای این رویداد پیدا نشد.</div>');
            return;
        }

        let html = '';

        matches.forEach(function (index) {
            const savedToken = getSavedToken(savedMap, index);

            html += '<div class="limosms-mapping-row">';
            html += '  <div class="limosms-mapping-header">';
            html += '      <div>به جای پارامتر {' + index + '} قرار بگیرد</div>';
            html += '      <div class="limosms-selected-badge is-empty">انتخاب نشده</div>';
            html += '  </div>';

            html += '  <input type="text" class="limosms-token-search" placeholder="جستجوی توکن...">';
            html += '  <div class="limosms-tokens-container">';
            html += '      <div class="limosms-token-list" data-param="' + index + '">';

            Object.entries(tokens).forEach(function (entry) {
                const tokenValue = entry[0];
                const tokenLabel = entry[1];
                const isActive = savedToken && isSameToken(savedToken, tokenValue);

                html += '<button type="button" class="limosms-token-chip ' + (isActive ? 'is-active' : '') + '" data-token="' + String(tokenValue).replace(/"/g, '&quot;') + '">';
                html += tokenLabel;
                html += '</button>';
            });

            html += '      </div>';
            html += '  </div>';
            html += '  <button type="button" class="limosms-toggle-tokens-btn">مشاهده همه</button>';
            html += '  <input type="hidden" class="limosms-pattern-select" data-param="' + index + '" value="' + String(savedToken).replace(/"/g, '&quot;') + '">';
            html += '</div>';
        });

        mappingContainer.html(html);
        refreshUI(card);
    }

    function refreshUI(card) {
        const selected = card.find('.limosms-pattern-select').map(function () {
            return $(this).val();
        }).get().filter(Boolean);

        card.find('.limosms-token-chip').each(function () {
            const chip = $(this);
            const chipToken = chip.data('token');
            const isSelectedElsewhere = selected.some(function (selectedToken) {
                return isSameToken(selectedToken, chipToken);
            });
            const isActive = chip.hasClass('is-active');

            chip.toggleClass('is-disabled', isSelectedElsewhere && !isActive);
        });

        card.find('.limosms-mapping-row').each(function () {
            const row = $(this);
            const badge = row.find('.limosms-selected-badge');
            const active = row.find('.limosms-token-chip.is-active');

            if (active.length) {
                badge
                    .removeClass('is-empty')
                    .addClass('is-selected')
                    .html('<span class="dashicons dashicons-yes-alt"></span> ' + active.text());
            } else {
                badge
                    .removeClass('is-selected')
                    .addClass('is-empty')
                    .text('انتخاب نشده');
            }
        });
    }

    function updateSelectedPattern(card, patternId) {
        const textBox = card.find('.limosms-pattern-text');
        const titleInput = card.find('.limosms-event-pattern-title');
        const otpInput = card.find('.limosms-event-otp-id');
        const mappingContainer = card.find('.limosms-pattern-mapping');

        if (!patternId) {
            otpInput.val('');
            titleInput.val('');
            textBox.text('');
            mappingContainer.attr('data-saved-map', '{}');
            renderPatternMapping(card);
            return;
        }

        const pattern = findPatternById(patternId);

        if (!pattern) {
            otpInput.val(patternId);
            titleInput.val('');
            textBox.text('');
            mappingContainer.attr('data-saved-map', '{}');
            renderPatternMapping(card);
            return;
        }

        otpInput.val(pattern.id);
        titleInput.val(pattern.title || '');
        textBox.text(pattern.text || '');
        renderPatternMapping(card);
    }

    function initAdminSMSTab() {
        if (!$('.limosms-event-card').length) {
            return;
        }

        $('.limosms-event-card').each(function () {
            const card = $(this);
            toggleEventFields(card, card.find('.limosms-event-enabled').is(':checked'));

            const select = card.find('.limosms-pattern-selector');
            if (select.length && select.val()) {
                select.trigger('change');
            }
        });

        loadAllPatterns(false, function () {
            const phoneField = $('#limosms_admin_phones');
            if (phoneField.length) {
                const validation = validateAdminPhones(phoneField.val());
                setAdminPhonesError(validation.message);
            }

            initialAdminState = getAdminCurrentState();
            updateAdminSaveButtonState();
        });
    }

    $(document).on('keypress', '#limosms_admin_phones', function (event) {
        const charCode = event.which ? event.which : event.keyCode;
        const charStr = String.fromCharCode(charCode);

        if (charCode === 8 || charCode === 0) {
            return;
        }

        if (!/[0-9,۰-۹٠-٩]/.test(charStr)) {
            event.preventDefault();
            setAdminPhonesError('فقط وارد کردن اعداد و کاما (,) مجاز است.');
        }
    });

    // restrict entry input to digits only for the single-phone entry field
    $(document).on('keypress', '#limosms_admin_phone_entry', function (event) {
        const charCode = event.which ? event.which : event.keyCode;
        const charStr = String.fromCharCode(charCode);

        if (charCode === 8 || charCode === 0) {
            return;
        }

        if (!/[0-9۰-۹٠-٩]/.test(charStr)) {
            event.preventDefault();
        }
    });

    $(document).on('input', '#limosms_admin_phone_entry', function () {
        let value = this.value;

        value = value
            .replace(/[۰-۹]/g, function (digit) {
                return '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit);
            })
            .replace(/[٠-٩]/g, function (digit) {
                return '٠١٢٣٤٥٦٧٨٩'.indexOf(digit);
            });

        if (/[^0-9]/.test(value)) {
            value = value.replace(/[^0-9]/g, '');
        }

        if (this.value !== value) {
            this.value = value;
        }
    });

    $(document).on('input', '.limosms-token-search', function () {
        const query = $(this).val().toLowerCase().trim();
        const container = $(this).next('.limosms-tokens-container');

        container.find('.limosms-token-chip').each(function () {
            const chip = $(this);
            const btnText = chip.text().toLowerCase();
            const btnKey = String(chip.data('token') || '').toLowerCase();

            if (btnText.includes(query) || btnKey.includes(query)) {
                chip.show();
            } else {
                chip.hide();
            }
        });
    });

    $(document)
        .off('click.limosmsToggleTokens', '.limosms-toggle-tokens-btn')
        .on('click.limosmsToggleTokens', '.limosms-toggle-tokens-btn', function (event) {
            event.preventDefault();

            const button = $(this);
            const container = button.siblings('.limosms-tokens-container');

            if (!container.length) {
                return;
            }

            container.toggleClass('expanded');
            button.text(container.hasClass('expanded') ? 'بستن لیست' : 'مشاهده همه');
        });

    $(document).on('input', '#limosms_admin_phones', function () {
        let value = this.value;

        value = value
            .replace(/[۰-۹]/g, function (digit) {
                return '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit);
            })
            .replace(/[٠-٩]/g, function (digit) {
                return '٠١٢٣٤٥٦٧٨٩'.indexOf(digit);
            });

        if (/[^0-9,]/.test(value)) {
            value = value.replace(/[^0-9,]/g, '');
        }

        if (this.value !== value) {
            this.value = value;
        }

        const validation = validateAdminPhones(value);
        setAdminPhonesError(validation.message);
        updateAdminSaveButtonState();
    });

    $(document).on('blur', '#limosms_admin_phones', function () {
        const cleanValue = this.value.replace(/^,+|,+$/g, '');

        if (this.value !== cleanValue) {
            this.value = cleanValue;
        }

        const validation = validateAdminPhones(this.value);
        setAdminPhonesError(validation.message);
    });

    $(document).on('change', '.limosms-pattern-selector', function () {
        const select = $(this);
        const card = select.closest('.limosms-event-card');
        const patternId = String(select.val() || '');

        updateSelectedPattern(card, patternId);
        updateAdminSaveButtonState();
    });

    $(document).on('click', '.limosms-token-chip', function () {
        const chip = $(this);

        if (chip.hasClass('is-disabled')) {
            return;
        }

        const row = chip.closest('.limosms-mapping-row');
        row.find('.limosms-token-chip').removeClass('is-active');
        chip.addClass('is-active');
        row.find('.limosms-pattern-select').val(chip.data('token')).trigger('change');
    });

    $(document).on('change', '.limosms-pattern-select', function () {
        refreshUI($(this).closest('.limosms-event-card'));
        updateAdminSaveButtonState();
    });

    $(document).on('change', '.limosms-event-enabled', function () {
        const card = $(this).closest('.limosms-event-card');
        toggleEventFields(card, $(this).is(':checked'));
        updateAdminSaveButtonState();
    });

    $(document).on('click', '#limosms-save-otp-settings', function (event) {
        event.preventDefault();

        const phonesValidation = validateAdminPhones($('#limosms_admin_phones').val());

        if (!phonesValidation.valid) {
            setAdminPhonesError(phonesValidation.message);
            showNotification('لطفاً خطاهای فرم را اصلاح کنید.', 'error');

            if ($('#limosms_admin_phones').length) {
                $('html, body').animate({
                    scrollTop: $('#limosms_admin_phones').offset().top - 100
                }, 200);

                $('#limosms_admin_phones').trigger('focus');
            }

            return;
        }

        setAdminPhonesError('');

        const button = $(this);
        const originalText = button.text();
        const smsEvents = {};
        let hasError = false;
        let errorMessage = '';

        $('.limosms-event-card').each(function () {
            const card = $(this);
            const eventKey = card.data('event');

            if (!eventKey) {
                return;
            }

            const enabled = card.find('.limosms-event-enabled').is(':checked');
            const otpId = card.find('.limosms-event-otp-id').val() || '';
            const patternTitle = card.find('.limosms-event-pattern-title').val() || '';
            const patternText = card.find('.limosms-pattern-text').text() || '';
            const patternInputs = card.find('.limosms-pattern-select');
            const patternMap = {};
            const hasVariables = /\{(\d+)\}/g.test(patternText);

            if (enabled) {
                if (!otpId) {
                    hasError = true;
                    errorMessage = 'لطفاً برای رویداد "' + eventKey + '" یک پترن انتخاب کنید.';
                    return false;
                }

                if (hasVariables && !patternInputs.length) {
                    hasError = true;
                    errorMessage = 'برای رویداد "' + eventKey + '" هیچ پارامتری پیدا نشد.';
                    return false;
                }

                let hasEmptyToken = false;

                patternInputs.each(function () {
                    const paramIndex = $(this).data('param');
                    const siteToken = $(this).val();

                    if (paramIndex === undefined || paramIndex === null) {
                        return;
                    }

                    if (!siteToken) {
                        hasEmptyToken = true;
                        return false;
                    }

                    patternMap[paramIndex] = siteToken;
                });

                if (hasVariables && hasEmptyToken) {
                    hasError = true;
                    errorMessage = 'لطفاً تمام توکن‌های پترن را برای رویداد "' + eventKey + '" تکمیل کنید.';
                    return false;
                }
            }

            smsEvents[eventKey] = {
                enabled: enabled ? 'yes' : 'no',
                otp_id: enabled ? otpId : '',
                title: enabled ? patternTitle : '',
                pattern_text: enabled ? patternText : '',
                pattern_map: enabled ? patternMap : {}
            };
        });

        if (hasError) {
            showNotification(errorMessage, 'error');
            return;
        }

        button.prop('disabled', true).text('در حال ذخیره...');

        $.ajax({
            url: limosms_ajax.url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'limosms_save_admin_sms_settings',
                nonce: limosms_ajax.nonce,
                admin_phones: $('#limosms_admin_phones').val(),
                smsEvents: JSON.stringify(smsEvents)
            }
        }).done(function (response) {
            if (response && response.success) {
                showNotification(response.data.message, 'success');
                return;
            }

            showNotification(
                response && response.data && response.data.message
                    ? response.data.message
                    : 'ذخیره تنظیمات انجام نشد.',
                'error'
            );
        }).fail(function (xhr) {
            const responseMessage = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message
                : 'خطا در ارتباط با سرور. لطفا مجددا تلاش کنید.';

            showNotification(responseMessage, 'error');
        }).always(function () {
            button.prop('disabled', false).text(originalText);
            initialAdminState = getAdminCurrentState();
            updateAdminSaveButtonState();
        });
    });

    $(document).ready(function () {
        const urlParams = new URLSearchParams(window.location.search);
        const currentTab = urlParams.get('tab') || 'connection-settings';

        if (currentTab === 'admin-sms') {
            initAdminSMSTab();
        }
    });

    $(document).on('limosms:tab-loaded', function (event, activeTab) {
        if (activeTab === 'admin-sms') {
            initAdminSMSTab();
        }
    });

})(jQuery);
