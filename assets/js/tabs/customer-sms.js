(function ($) {
    'use strict';

    const DEBUG_MODE = false;

    let customerPatternsCache = {};
    let customerTabInitialized = false;
    let customerInitialState = null;

    function normalizeCustomerPatternMap(patternMap) {
        const normalized = {};
        Object.keys(patternMap || {}).sort(function (a, b) {
            return String(a).localeCompare(String(b), undefined, { numeric: true });
        }).forEach(function (key) {
            normalized[String(key)] = String(patternMap[key] || '');
        });
        return normalized;
    }

    function getCustomerCurrentState() {
        const state = {};

        $('.limosms-event-card').each(function () {
            const card = $(this);
            const eventKey = String(card.data('event') || '').trim();
            const enabled = card.find('.limosms-customer-event-enabled').is(':checked');
            const otpId = String(card.find('.limosms-customer-otp-id').val() || '').trim();
            const patternText = String(card.find('.limosms-pattern-text').text() || '').trim();
            const patternMap = {};

            card.find('.limosms-pattern-select').each(function () {
                const input = $(this);
                const param = String(input.data('param'));
                const value = String(input.val() || '').trim();
                if (param !== '' && value !== '') {
                    patternMap[param] = value;
                }
            });

            state[eventKey] = {
                enabled: enabled ? 'yes' : 'no',
                otp_id: enabled ? otpId : '',
                pattern_text: enabled ? patternText : '',
                pattern_map: enabled ? normalizeCustomerPatternMap(patternMap) : {}
            };
        });

        return state;
    }

    function serializeCustomerState(state) {
        const normalized = {};
        Object.keys(state || {}).sort().forEach(function (eventKey) {
            const eventData = state[eventKey] || {};
            normalized[eventKey] = {
                enabled: eventData.enabled === 'yes' ? 'yes' : 'no',
                otp_id: String(eventData.otp_id || '').trim(),
                pattern_text: String(eventData.pattern_text || '').trim(),
                pattern_map: normalizeCustomerPatternMap(eventData.pattern_map || {})
            };
        });
        return JSON.stringify(normalized);
    }

    function updateCustomerSaveButtonState() {
        const isDirty = customerInitialState && serializeCustomerState(getCustomerCurrentState()) !== serializeCustomerState(customerInitialState);
        if (isDirty) {
            enableSaveButton();
        } else {
            disableSaveButton();
        }
        return isDirty;
    }

    function debugLog(label, payload) {
        if (!DEBUG_MODE || !window.console) {
            return;
        }
        console.groupCollapsed('%c[LimoSMS Debug] ' + label, 'color:#2271b1;font-weight:bold;');
        if (typeof payload !== 'undefined') {
            console.log(payload);
        }
        console.groupEnd();
    }

    function debugWarn(label, payload) {
        if (!DEBUG_MODE || !window.console) {
            return;
        }
        console.groupCollapsed('%c[LimoSMS Warn] ' + label, 'color:#dba617;font-weight:bold;');
        if (typeof payload !== 'undefined') {
            console.warn(payload);
        }
        console.groupEnd();
    }

    function debugError(label, payload) {
        if (!DEBUG_MODE || !window.console) {
            return;
        }
        console.group('%c[LimoSMS Error] ' + label, 'color:#fff;background:#d63638;padding:2px 6px;font-weight:bold;');
        if (typeof payload !== 'undefined') {
            console.error(payload);
        }
        console.groupEnd();
    }

    function showNotification(message, type) {
        type = type || 'error';
        if (window.LimoSMS && typeof window.LimoSMS.showToast === 'function') {
            window.LimoSMS.showToast(message, type);
            return;
        }
        if (type === 'success') {
            // success silently ignored if toast unavailable
            return;
        }
        alert(message);
    }

    function getCustomerSmsData() {
        return window.limosmsCustomerSmsData || {};
    }

    function normalizeToken(token) {
        return String(token || '').replace(/[{}]/g, '').trim();
    }

    function isSameToken(a, b) {
        return normalizeToken(a) === normalizeToken(b);
    }

    function normalizeEventKey(eventKey) {
        const key = String(eventKey || '').trim();
        const aliasMap = {
            pending_order: 'order_pending',
            processing_order: 'order_processing',
            on_hold_order: 'order_on_hold',
            completed_order: 'order_completed',
            cancelled_order: 'order_cancelled',
            refunded_order: 'order_refunded',
            failed_order: 'order_failed',
            order_pending: 'order_pending',
            order_processing: 'order_processing',
            order_on_hold: 'order_on_hold',
            order_completed: 'order_completed',
            order_cancelled: 'order_cancelled',
            order_refunded: 'order_refunded',
            order_failed: 'order_failed'
        };
        return aliasMap[key] || key;
    }

    function getEventKeyCandidates(eventKey) {
        const original = String(eventKey || '').trim();
        const normalized = normalizeEventKey(original);
        const candidates = [original, normalized];

        if (original.indexOf('order_') === 0) {
            candidates.push(original.replace(/^order_/, '') + '_order');
        }
        if (original.indexOf('_order') !== -1) {
            const parts = original.split('_');
            if (parts.length >= 2) {
                candidates.push('order_' + parts[0]);
            }
        }
        return Array.from(new Set(candidates.filter(Boolean)));
    }

    function getSavedMap(mappingContainer) {
        let savedMap = {};
        try {
            savedMap = JSON.parse(mappingContainer.attr('data-saved-map') || '{}');
        } catch (error) {
            debugError('Failed to parse saved map JSON', error);
            savedMap = {};
        }
        return savedMap && typeof savedMap === 'object' ? savedMap : {};
    }

    function getSavedToken(savedMap, index) {
        const item = savedMap[index];
        if (!item) {
            return '';
        }
        if (typeof item === 'object' && item !== null) {
            return item.token || '';
        }
        return item;
    }

    function enableSaveButton() {
        $('#limosms-save-customer-settings').prop('disabled', false);
    }

    function disableSaveButton() {
        $('#limosms-save-customer-settings').prop('disabled', true);
    }

    function toggleEventFields(card, enabled, immediate = false) {
        const fields = card.find('.limosms-field-pattern-id, .limosms-field-pattern-text, .limosms-field-pattern-map');

        if (enabled) {
            if (immediate) {
                fields.show();
            } else {
                fields.stop(true, true).slideDown(200);
            }
            card.addClass('is-active');
        } else {
            if (immediate) {
                fields.hide();
            } else {
                fields.stop(true, true).slideUp(200);
            }
            card.removeClass('is-active');
        }
    }

    function syncVisibleCustomerCards(immediate = false) {
        $('.limosms-event-card').each(function () {
            const card = $(this);
            const enabled = card.find('.limosms-customer-event-enabled').is(':checked');
            toggleEventFields(card, enabled, immediate);
        });
    }

    function refreshCustomerSmsTabState() {
        syncVisibleCustomerCards(true);
        rebuildAllPatternMappings();
    }

    function extractPatternVariables(patternText) {
        const matches = patternText.match(/\{(\d+)\}/g) || [];
        const indexes = matches.map(function (match) {
            const number = match.match(/\d+/);
            return number ? parseInt(number[0], 10) : null;
        }).filter(function (value) {
            return value !== null && !isNaN(value);
        });

        return Array.from(new Set(indexes)).sort(function (a, b) {
            return a - b;
        });
    }

    function getFallbackOrderTokens() {
        return {
            order_id: 'شناسه سفارش',
            order_number: 'شماره سفارش',
            order_parent_id: 'شماره سفارش اصلی',
            order_status: 'وضعیت سفارش',
            order_total: 'مبلغ سفارش',
            order_date: 'تاریخ سفارش',
            transaction_id: 'شماره تراکنش',
            customer_note: 'توضیحات مشتری',
            payment_method: 'روش پرداخت',
            shipping_method: 'روش ارسال',
            payment_url: 'لینک پرداخت سفارش',
            billing_first_name: 'نام مشتری',
            billing_last_name: 'نام خانوادگی مشتری',
            billing_phone: 'شماره تلفن مشتری',
            billing_mobile: 'شماره موبایل مشتری',
            billing_email: 'ایمیل مشتری',
            billing_company: 'نام شرکت',
            billing_country: 'کشور',
            billing_state: 'ایالت/استان',
            billing_city: 'شهر',
            billing_address_1: 'آدرس 1',
            billing_address_2: 'آدرس 2',
            billing_postcode: 'کد پستی',
            shipping_first_name: 'نام مشتری (حمل و نقل)',
            shipping_last_name: 'نام خانوادگی مشتری (حمل و نقل)',
            shipping_company: 'نام شرکت (حمل و نقل)',
            shipping_country: 'کشور (حمل و نقل)',
            shipping_state: 'ایالت/استان (حمل و نقل)',
            shipping_city: 'شهر (حمل و نقل)',
            shipping_address_1: 'آدرس 1 (حمل و نقل)',
            shipping_address_2: 'آدرس 2 (حمل و نقل)',
            shipping_postcode: 'کد پستی (حمل و نقل)',
            order_items: 'محصولات سفارش',
            order_items_full: 'محصولات سفارش با نام کامل متغیر',
            order_items_with_qty: 'محصولات سفارش بهمراه تعداد',
            order_items_count: 'تعداد محصولات سفارش',
            product_id: 'آیدی محصول',
            product_url: 'لینک محصول',
            product_sku: 'شناسه محصول',
            product_name: 'عنوان محصول',
            product_name_with_attr: 'عنوان محصول با متغیر',
            product_stock_quantity: 'موجودی انبار',
            tracking_code: 'کد رهگیری پستی',
            tracking_url: 'آدرس اینترنتی رهگیری پستی'
        };
    }

    function getTokensFromEventObject(eventObject) {
        if (!eventObject || typeof eventObject !== 'object') {
            return {};
        }
        if (eventObject.tokens && typeof eventObject.tokens === 'object' && Object.keys(eventObject.tokens).length) {
            return eventObject.tokens;
        }
        if (eventObject.available_tokens && typeof eventObject.available_tokens === 'object' && Object.keys(eventObject.available_tokens).length) {
            return eventObject.available_tokens;
        }
        return {};
    }

    function getEventTokens(eventKey) {
        const data = getCustomerSmsData();
        const candidates = getEventKeyCandidates(eventKey);
        const events = data.events || {};
        const tokensRoot = data.tokens || {};

        // بررسی کاندیداهای کلید رویداد در آبجکت events
        for (let i = 0; i < candidates.length; i++) {
            const key = candidates[i];
            if (events[key]) {
                const tokensFromEvent = getTokensFromEventObject(events[key]);
                if (Object.keys(tokensFromEvent).length) {
                    return tokensFromEvent;
                }
            }
            if (tokensRoot[key] && typeof tokensRoot[key] === 'object' && Object.keys(tokensRoot[key]).length) {
                return tokensRoot[key];
            }
        }

        // اگر در رویداد خاصی یافت نشد، از توکن‌های عمومی ارسال شده از PHP استفاده کن
        if (tokensRoot.common && typeof tokensRoot.common === 'object' && Object.keys(tokensRoot.common).length) {
            return tokensRoot.common;
        }

        // در نهایت استفاده از Fallback محلی جاوااسکریپت
        return getFallbackOrderTokens();
    }

    function updateSelectedBadge(row) {
        const badge = row.find('.limosms-selected-badge');
        const activeChip = row.find('.limosms-token-chip.is-active');

        if (activeChip.length) {
            badge
                .removeClass('is-empty')
                .addClass('is-selected')
                .html('<span class="dashicons dashicons-yes-alt"></span> ' + activeChip.text().trim());
        } else {
            badge
                .removeClass('is-selected')
                .addClass('is-empty')
                .text('انتخاب نشده');
        }
    }

    function refreshUI(card) {
        const selectedTokens = card.find('.limosms-pattern-select').map(function () {
            return $(this).val();
        }).get().filter(Boolean);

        card.find('.limosms-token-chip').each(function () {
            const chip = $(this);
            const chipToken = chip.data('token');
            const usedElsewhere = selectedTokens.some(function (token) {
                return isSameToken(token, chipToken);
            });
            chip.toggleClass('is-disabled', usedElsewhere && !chip.hasClass('is-active'));
        });

        card.find('.limosms-mapping-row').each(function () {
            updateSelectedBadge($(this));
        });
    }

    function normalizePattern(pattern) {
        if (!pattern || typeof pattern !== 'object') {
            return null;
        }
        const rawCode = pattern.patternCode ?? pattern.pattern_code ?? pattern.patternId ?? pattern.pattern_id ?? pattern.code ?? pattern.id ?? '';
        const rawMessage = pattern.message ?? pattern.text ?? pattern.pattern ?? pattern.body ?? '';
        const rawTitle = pattern.title ?? pattern.pattern_title ?? pattern.patternTitle ?? pattern.patternName ?? pattern.name ?? pattern.pattern_name ?? '';

        if (rawCode === '' || rawCode === null || typeof rawCode === 'undefined') {
            return null;
        }
        return {
            patternCode: String(rawCode).trim(),
            message: String(rawMessage || '').trim(),
            patternTitle: String(rawTitle || '').trim()
        };
    }

    function buildPatternOptionLabel(pattern) {
        const code = String(pattern.patternCode || '');
        const title = String(pattern.patternTitle || '');

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

    function renderPatternMapping(card) {
        const textBox = card.find('.limosms-pattern-text');
        const eventKey = card.data('event') || textBox.data('event') || '';
        const textValue = textBox.text() || '';
        const mappingContainer = card.find('.limosms-customer-pattern-mapping-wrap');
        const savedMap = getSavedMap(mappingContainer);
        const variables = extractPatternVariables(textValue);

        if (!variables.length) {
            mappingContainer.html('<div class="limosms-pattern-empty">متغیری در متن الگو یافت نشد.</div>');
            return;
        }

        const tokens = getEventTokens(eventKey);
        const tokenEntries = Object.entries(tokens || {});

        if (!tokenEntries.length) {
            mappingContainer.html('<div class="limosms-pattern-empty">توکنی برای این رویداد پیدا نشد.</div>');
            return;
        }

        let html = '';
        variables.forEach(function (index) {
            const savedToken = getSavedToken(savedMap, index);

            html += '<div class="limosms-mapping-row">';
            html += '  <div class="limosms-mapping-header">';
            html += '      <div>به جای پارامتر {' + index + '} قرار بگیرد</div>';
            html += '      <div class="limosms-selected-badge is-empty">انتخاب نشده</div>';
            html += '  </div>';

            // --- تغییرات جدید از اینجا ---
            html += '  <input type="text" class="limosms-token-search" placeholder="جستجوی توکن...">';
            html += '  <div class="limosms-tokens-container">';
            html += '      <div class="limosms-token-list" data-param="' + index + '">';

            tokenEntries.forEach(function (entry) {
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
            // --- پایان تغییرات ---

            html += '  <input type="hidden" class="limosms-pattern-select" data-param="' + index + '" value="' + String(savedToken).replace(/"/g, '&quot;') + '">';
            html += '</div>';
        });

        mappingContainer.html(html);
        refreshUI(card);
    }

    function rebuildAllPatternMappings() {
        $('.limosms-event-card').each(function () {
            const card = $(this);
            const text = card.find('.limosms-pattern-text').text().trim();
            if (text) {
                renderPatternMapping(card);
            }
        });
    }

    function applyPatternToCard(card, patternCode, patternText) {
        card.find('.limosms-customer-otp-id').val(String(patternCode || ''));
        card.find('.limosms-pattern-text').text(patternText || '');
        renderPatternMapping(card);
    }

    function buildPatternOptions(patterns, selectedValue) {
        let html = '<option value="">انتخاب الگو...</option>';
        (patterns || []).forEach(function (pattern) {
            const normalized = normalizePattern(pattern);
            if (!normalized) {
                return;
            }
            const code = normalized.patternCode;
            const message = normalized.message;
            const title = normalized.patternTitle;
            const label = buildPatternOptionLabel(normalized);
            const selected = String(selectedValue || '') === String(code) ? ' selected="selected"' : '';

            html += '<option value="' + code.replace(/"/g, '&quot;') + '" data-text="' + encodeURIComponent(message) + '" data-title="' + title.replace(/"/g, '&quot;') + '"' + selected + '>';
            html += label;
            html += '</option>';
        });
        return html;
    }

    function maybeInitSelect2(context) {
        const container = context || $(document);
        if (!$.fn.select2) {
            return;
        }

        const selects = $(container)
            .filter('.limosms-customer-pattern-selector')
            .add($(container).find('.limosms-customer-pattern-selector'));

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
                dropdownParent: select.parent(),
                dropdownCssClass: 'limosms-select2',
                templateResult: function (item) {
                    if (!item.id) {
                        return item.text;
                    }
                    const element = item.element ? $(item.element) : null;
                    const title = element ? element.data('title') || '' : '';
                    if (title) {
                        return item.id + ' | ' + title;
                    }
                    return item.text;
                },
                templateSelection: function (item) {
                    if (!item.id) {
                        return item.text;
                    }
                    const element = item.element ? $(item.element) : null;
                    const title = element ? element.data('title') || '' : '';
                    if (title) {
                        return item.id + ' | ' + title;
                    }
                    return item.text;
                },
                escapeMarkup: function (markup) {
                    return markup;
                }
            });
        });
    }

    function initCustomPatternSelects(context) {
        const container = context ? $(context) : $(document);

        container.find('.limosms-pattern-selector').each(function () {
            const select = $(this);

            if (select.hasClass('select2-hidden-accessible')) {
                return;
            }

            if (select.data('customized')) {
                return;
            }

            const options = [];
            select.find('option').each(function () {
                const opt = $(this);
                options.push({ value: opt.attr('value') || '', label: opt.text() || '', disabled: opt.prop('disabled') });
            });

            const wrapper = $('<div class="limosms-custom-select" aria-hidden="false"></div>');
            const button = $('<button type="button" class="limosms-custom-select__button" aria-haspopup="listbox"></button>');
            const list = $('<div class="limosms-custom-select__list" role="listbox"></div>');

            const searchInput = $('<input type="text" class="limosms-custom-select__search" placeholder="جستجو...">');
            list.append(searchInput);

            if (options.length === 0) {
                list.append('<div class="limosms-custom-select__noresults">هیچ گزینه‌ای نیست</div>');
            } else {
                options.forEach(function (opt) {
                    const item = $('<div class="limosms-custom-select__item" data-value="' + opt.value.replace(/"/g, '&quot;') + '"></div>');
                    item.text(opt.label);
                    if (opt.disabled) {
                        item.attr('aria-disabled', 'true').addClass('is-disabled');
                    }
                    list.append(item);
                });
            }

            searchInput.on('input', function () {
                const q = String(this.value || '').toLowerCase().trim();
                let visible = 0;
                list.find('.limosms-custom-select__item').each(function () {
                    const it = $(this);
                    const text = (it.text() || '').toLowerCase();
                    if (!q || text.indexOf(q) !== -1) {
                        it.show();
                        visible++;
                    } else {
                        it.hide();
                    }
                });
                list.find('.limosms-custom-select__noresults').toggle(visible === 0);
            });

            const selectedOption = select.find('option:selected');
            const selectedLabel = selectedOption.length ? selectedOption.text() : select.attr('placeholder') || 'انتخاب الگو...';
            button.text(selectedLabel);

            wrapper.append(button).append(list);
            select.after(wrapper);

            select.addClass('limosms-native-hidden');
            select.data('customized', true);

            button.on('click', function (e) {
                e.preventDefault();
                list.toggle();
            });

            list.on('click', '.limosms-custom-select__item', function () {
                const item = $(this);
                if (item.is('.is-disabled')) {
                    return;
                }
                const val = item.data('value') || '';
                select.val(val).trigger('change');
                button.text(item.text());
                list.hide();
                list.find('.limosms-custom-select__item').removeClass('is-active');
                item.addClass('is-active');
            });

            $(document).on('click.limosmsCustomSelect', function (e) {
                if (!wrapper.is(e.target) && wrapper.has(e.target).length === 0) {
                    list.hide();
                }
            });
        });
    }

    function loadAllPatterns(callback) {
        const data = getCustomerSmsData();
        if (!data.ajax_url || !data.nonce) {
            debugError('Missing ajax_url or nonce in limosmsCustomerSmsData', data);
            if (typeof callback === 'function') {
                callback();
            }
            return;
        }

        $.ajax({
            url: data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'limosms_customer_get_patterns',
                nonce: data.nonce
            },
            beforeSend: function () {
                $('.limosms-customer-pattern-selector').prop('disabled', true);
            },
            success: function (response) {
                if (!response || !response.success) {
                    showNotification((response && response.data) ? response.data : 'خطا در دریافت الگوها');
                    return;
                }

                customerPatternsCache = {};
                (response.data || []).forEach(function (pattern) {
                    const normalized = normalizePattern(pattern);
                    if (normalized && normalized.patternCode) {
                        customerPatternsCache[normalized.patternCode] = normalized;
                    }
                });

                $('.limosms-event-card').each(function () {
                    const card = $(this);
                    const select = card.find('.limosms-customer-pattern-selector');
                    const savedPatternCode = String(
                        card.find('.limosms-customer-otp-id').val() || select.data('selected') || ''
                    ).trim();
                    const optionsHtml = buildPatternOptions(response.data || [], savedPatternCode);

                    if ($.fn.select2 && select.hasClass('select2-hidden-accessible')) {
                        select.select2('destroy');
                    }

                    select.html(optionsHtml).val(savedPatternCode);
                    maybeInitSelect2(select);

                    select.trigger('change');

                    const selectedOption = select.find('option:selected');
                    const selectedText = selectedOption.length ? decodeURIComponent(selectedOption.data('text') || '') : '';
                    card.find('.limosms-pattern-text').text(selectedText);

                    if (selectedText) {
                        renderPatternMapping(card);
                    } else {
                        card.find('.limosms-customer-pattern-mapping-wrap').html('');
                    }
                });

                maybeInitSelect2($(document));
                if (typeof initCustomPatternSelects === 'function') {
                    initCustomPatternSelects($(document));
                }
                if (typeof callback === 'function') {
                    callback();
                }
            },
            error: function () {
                showNotification('خطا در ارتباط با سرور برای دریافت الگوها');
            },
            complete: function () {
                $('.limosms-customer-pattern-selector').prop('disabled', false);
            }
        });
    }

    function collectPatternMap(card) {
        const patternMap = {};
        card.find('.limosms-pattern-select').each(function () {
            const input = $(this);
            patternMap[input.data('param')] = input.val() || '';
        });
        return patternMap;
    }

    function validateCustomerEvents() {
        let hasError = false;
        let errorMessage = '';

        $('.limosms-event-card').each(function () {
            const card = $(this);
            const eventKey = String(card.data('event') || '');
            const enabled = card.find('.limosms-customer-event-enabled').is(':checked');
            const otpId = String(card.find('.limosms-customer-otp-id').val() || '');
            const patternText = String(card.find('.limosms-pattern-text').text() || '');
            const patternInputs = card.find('.limosms-pattern-select');
            const hasVariables = /\{(\d+)\}/.test(patternText);

            if (!enabled) {
                return;
            }

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
                if (!String($(this).val() || '').trim()) {
                    hasEmptyToken = true;
                    return false;
                }
            });

            if (hasVariables && hasEmptyToken) {
                hasError = true;
                errorMessage = 'لطفاً تمام توکن‌های پترن را برای رویداد "' + eventKey + '" تکمیل کنید.';
                return false;
            }
        });

        return {
            valid: !hasError,
            message: errorMessage
        };
    }

    function collectEventsPayload() {
        const events = {};
        $('.limosms-event-card').each(function () {
            const card = $(this);
            const eventKey = card.data('event');
            events[eventKey] = {
                enabled: card.find('.limosms-customer-event-enabled').is(':checked') ? 'yes' : 'no',
                otp_id: card.find('.limosms-customer-otp-id').val() || '',
                pattern_text: card.find('.limosms-pattern-text').text() || '',
                pattern_map: collectPatternMap(card)
            };
        });
        return events;
    }

    function saveCustomerSettings(button) {
        const validation = validateCustomerEvents();
        if (!validation.valid) {
            showNotification(validation.message || 'لطفاً خطاهای فرم را اصلاح کنید.', 'error');
            return;
        }

        const data = getCustomerSmsData();
        if (!data.ajax_url || !data.nonce) {
            showNotification('اطلاعات لازم برای ذخیره‌سازی موجود نیست.');
            return;
        }

        const payload = collectEventsPayload();
        const originalText = button.text();
        button.prop('disabled', true).text('در حال ذخیره...');

        $.ajax({
            url: data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'limosms_save_customer_sms_settings',
                nonce: data.nonce,
                smsEvents: JSON.stringify(payload)
            },
            success: function (response) {
                if (response && response.success) {
                    showNotification('تنظیمات با موفقیت ذخیره شد.', 'success');
                    customerInitialState = getCustomerCurrentState();
                    disableSaveButton();
                } else {
                    showNotification((response && response.data) ? response.data : 'ذخیره تنظیمات با خطا مواجه شد.');
                }
            },
            error: function () {
                showNotification('خطا در ارتباط با سرور هنگام ذخیره تنظیمات');
            },
            complete: function () {
                updateCustomerSaveButtonState();
                button.text(originalText);
            }
        });
    }

    function bindEvents() {
        if (customerTabInitialized) {
            return;
        }
        customerTabInitialized = true;

        // فعال‌سازی جستجوی زنده در لیست توکن‌ها
        $(document).on('input', '.limosms-token-search', function () {
            const query = $(this).val().toLowerCase().trim();
            const container = $(this).next('.limosms-tokens-container');

            container.find('.limosms-token-chip').each(function () {
                const btnText = $(this).text().toLowerCase();
                const btnKey = $(this).data('token').toLowerCase();
                if (btnText.includes(query) || btnKey.includes(query)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
        
        // فعال‌سازی دکمه "مشاهده همه"
        $(document)
            .off('click.limosmsToggleTokens', '.limosms-toggle-tokens-btn')
            .on('click.limosmsToggleTokens', '.limosms-toggle-tokens-btn', function (e) {
                e.preventDefault();

                const button = $(this);
                const container = button.siblings('.limosms-tokens-container');

                if (!container.length) {
                    return;
                }

                container.toggleClass('expanded');
                button.text(container.hasClass('expanded') ? 'بستن لیست' : 'مشاهده همه');
            });


        $(document).on('change', '.limosms-customer-event-enabled', function () {
            const checkbox = $(this);
            const card = checkbox.closest('.limosms-event-card');
            toggleEventFields(card, checkbox.is(':checked'));
            refreshUI(card);
            updateCustomerSaveButtonState();
        });

        $(document).on('change', '.limosms-customer-pattern-selector', function () {
            const select = $(this);
            const card = select.closest('.limosms-event-card');
            const value = String(select.val() || '').trim();

            let patternText = '';
            if (value && customerPatternsCache[value]) {
                patternText = customerPatternsCache[value].message || '';
            } else {
                const selectedOption = select.find('option:selected');
                patternText = selectedOption.length ? decodeURIComponent(selectedOption.data('text') || '') : '';
            }

            applyPatternToCard(card, value, patternText);
            updateCustomerSaveButtonState();
        });

        $(document).on('click', '.limosms-token-chip', function () {
            const chip = $(this);
            if (chip.hasClass('is-disabled')) {
                return;
            }
            const row = chip.closest('.limosms-mapping-row');
            const hiddenInput = row.find('.limosms-pattern-select');

            row.find('.limosms-token-chip').removeClass('is-active');
            chip.addClass('is-active');

            hiddenInput.val(chip.data('token')).trigger('change');
        });

        $(document).on('change', '.limosms-pattern-select', function () {
            const input = $(this);
            const card = input.closest('.limosms-event-card');
            refreshUI(card);
            updateCustomerSaveButtonState();
        });

        $(document).on('click', '#limosms-save-customer-settings', function (e) {
            e.preventDefault();
            saveCustomerSettings($(this));
        });
    }

    function initCustomerSmsTab() {
        bindEvents();
        syncVisibleCustomerCards(true);
        loadAllPatterns(function () {
            customerInitialState = getCustomerCurrentState();
            updateCustomerSaveButtonState();
        });
    }

    // اتصال به رویداد سراسری تغییر تب Ajax افزونه LimoSMS
    $(document).on('limosms:tab-loaded', function (event, activeTab) {
        if (activeTab === 'customer-sms') {
            initCustomerSmsTab();
        }
    });

    // لود اولیه در صورت حضور مستقیم در تب مشتری هنگام رندر صفحه
    $(document).ready(function () {
        if ($('#limosms-save-customer-settings').length) {
            initCustomerSmsTab();
        }
    });

    window.LimoSMSCustomerSms = {
        reloadPatterns: loadAllPatterns,
        rerenderMappings: rebuildAllPatternMappings,
        renderPatternMapping: renderPatternMapping,
        getEventTokens: getEventTokens,
        normalizeEventKey: normalizeEventKey
    };

})(jQuery);
