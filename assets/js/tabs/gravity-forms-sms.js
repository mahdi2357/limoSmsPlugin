(function ($) {
    'use strict';

    const DEBUG_MODE = false;

    let gravityFormsPatternsCache = {};
    let gravityFormsTabInitialized = false;
    let gravityFormsInitialState = null;

    function normalizeGravityFormsPatternMap(patternMap) {
        const normalized = {};
        Object.keys(patternMap || {}).sort(function (a, b) {
            return String(a).localeCompare(String(b), undefined, { numeric: true });
        }).forEach(function (key) {
            normalized[String(key)] = String(patternMap[key] || '');
        });
        return normalized;
    }

    function getGravityFormsCurrentState() {
        const state = {
            admin_phones: String($('#limosms_gravity_forms_admin_phones').val() || '').trim(),
            forms: {}
        };

        $('.limosms-form-card').each(function () {
            const card = $(this);
            const formId = String(card.data('form') || '').trim();
            const enabled = card.find('.limosms-gravity-form-enabled').is(':checked');
            const otpId = String(card.find('.limosms-gravity-otp-id').val() || '').trim();
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

            state.forms[formId] = {
                enabled: enabled ? 'yes' : 'no',
                otp_id: enabled ? otpId : '',
                pattern_text: enabled ? patternText : '',
                pattern_map: enabled ? normalizeGravityFormsPatternMap(patternMap) : {}
            };
        });

        return state;
    }

    function serializeGravityFormsState(state) {
        const normalized = {
            admin_phones: String(state && state.admin_phones ? state.admin_phones : '').trim(),
            forms: {}
        };
        Object.keys(state && state.forms ? state.forms : {}).sort().forEach(function (formId) {
            const formData = state.forms[formId] || {};
            normalized.forms[formId] = {
                enabled: formData.enabled === 'yes' ? 'yes' : 'no',
                otp_id: String(formData.otp_id || '').trim(),
                pattern_text: String(formData.pattern_text || '').trim(),
                pattern_map: normalizeGravityFormsPatternMap(formData.pattern_map || {})
            };
        });
        return JSON.stringify(normalized);
    }

    function toggleGravityFormsSaveWarning(isVisible) {
        if (window.LimoSMS && typeof window.LimoSMS.toggleSaveWarning === 'function') {
            window.LimoSMS.toggleSaveWarning(isVisible);
            return;
        }

        $('#limosms-gravity-forms-save-warning').toggle(isVisible);
    }

    function updateGravityFormsSaveButtonState() {
        const isDirty = gravityFormsInitialState && serializeGravityFormsState(getGravityFormsCurrentState()) !== serializeGravityFormsState(gravityFormsInitialState);
        if (isDirty) {
            enableSaveButton();
        } else {
            disableSaveButton();
        }
        toggleGravityFormsSaveWarning(isDirty);
        return isDirty;
    }

    function showNotification(message, type) {
        type = type || 'error';
        if (window.LimoSMS && typeof window.LimoSMS.showToast === 'function') {
            window.LimoSMS.showToast(message, type);
            return;
        }
        if (type === 'success') {
            // silently ignore success message when no toast available
            return;
        }
        alert(message);
    }

    function getGravityFormsSmsData() {
        return window.limosmsGravityFormsSmsData || {};
    }

    function normalizeToken(token) {
        return String(token || '').replace(/[{}]/g, '').trim();
    }

    function isSameToken(a, b) {
        return normalizeToken(a) === normalizeToken(b);
    }

    function getSavedMap(mappingContainer) {
        let savedMap = {};
        try {
            savedMap = JSON.parse(mappingContainer.attr('data-saved-map') || '{}');
        } catch (error) {
            // parse error suppressed in production
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
        $('#limosms-save-gravity-forms-settings').prop('disabled', false);
    }

    function disableSaveButton() {
        $('#limosms-save-gravity-forms-settings').prop('disabled', true);
    }

    function toggleFormFields(card, enabled, immediate = false) {
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

    function syncVisibleGravityFormCards(immediate = false) {
        $('.limosms-form-card').each(function () {
            const card = $(this);
            const enabled = card.find('.limosms-gravity-form-enabled').is(':checked');
            toggleFormFields(card, enabled, immediate);
        });
    }

    function refreshGravityFormsSmsTabState() {
        syncVisibleGravityFormCards(true);
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

    function getFormTokens(formId) {
        const data = getGravityFormsSmsData();
        const forms = data.forms || {};
        const tokens = data.tokens || {};

        // debug logs suppressed in production

        if (forms[formId] && forms[formId].tokens) {
            // tokens provided for this form
            return forms[formId].tokens;
        }

        // return global tokens
        return tokens || {};
        return tokens || {};
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

    function maybeInitGravityPatternSelect2(context) {
        const container = context ? $(context) : $(document);
        if (!$.fn.select2) {
            return;
        }

        const selects = container
            .filter('.limosms-pattern-selector')
            .add(container.find('.limosms-pattern-selector'));

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
        const selects = container.filter('.limosms-pattern-selector')
            .add(container.find('.limosms-pattern-selector'));

        selects.each(function () {
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

    function renderPatternMapping(card) {
        const textBox = card.find('.limosms-pattern-text');
        const formId = card.data('form') || '';
        const textValue = textBox.text() || '';
        const mappingContainer = card.find('.limosms-gravity-pattern-mapping-wrap');
        const savedMap = getSavedMap(mappingContainer);
        const variables = extractPatternVariables(textValue);

        // renderPatternMapping debug logs suppressed in production

        if (!variables.length) {
            mappingContainer.html('<div class="limosms-pattern-empty">متغیری در متن الگو یافت نشد.</div>');
            return;
        }

        const tokens = getFormTokens(formId);
        // debug logs suppressed in production
        const tokenEntries = Object.entries(tokens || {});

        if (!tokenEntries.length) {
            mappingContainer.html('<div class="limosms-pattern-empty">فیلدی برای این فرم پیدا نشد.</div>');
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

            html += '  <input type="text" class="limosms-token-search" placeholder="جستجوی فیلد...">';
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

            html += '  <input type="hidden" class="limosms-pattern-select" data-param="' + index + '" value="' + String(savedToken).replace(/"/g, '&quot;') + '">';
            html += '</div>';
        });

        mappingContainer.html(html);
        refreshUI(card);
    }

    function rebuildAllPatternMappings() {
        $('.limosms-form-card').each(function () {
            const card = $(this);
            const text = card.find('.limosms-pattern-text').text().trim();
            if (text) {
                renderPatternMapping(card);
            }
        });
    }

    function applyPatternToCard(card, patternCode, patternText) {
        card.find('.limosms-gravity-otp-id').val(String(patternCode || ''));
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

    function loadPatterns() {
        const data = getGravityFormsSmsData();
        if (!data.ajax_url || !data.nonce) {
            showNotification('اطلاعات AJAX موجود نیست.', 'error');
            return;
        }

        // loadPatterns: debug logs suppressed in production

        $.ajax({
            url: data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'limosms_gravity_forms_get_patterns',
                nonce: data.nonce
            },
            success: function (response) {
                // AJAX success response logging suppressed in production
                
                if (response && response.success) {
                    // response.data شامل {success, message, data: Array} است
                    let patterns = response.data;
                    
                    // اگر response.data خود یک object با data property باشد
                    if (patterns && typeof patterns === 'object' && patterns.data) {
                        patterns = patterns.data;
                    }
                    
                    // Patterns received - logging suppressed in production
                    
                    // اگر patterns خالی باشد
                    if (!Array.isArray(patterns) || patterns.length === 0) {
                        showNotification('هیچ الگویی در حساب شما موجود نیست.', 'error');
                        return;
                    }
                    
                    gravityFormsPatternsCache = {};

                    (patterns || []).forEach(function (pattern) {
                        const normalized = normalizePattern(pattern);
                        if (normalized) {
                            gravityFormsPatternsCache[normalized.patternCode] = normalized;
                        }
                    });

                    // Pattern cache logging suppressed in production

                    $('.limosms-gravity-pattern-selector').each(function () {
                        const selector = $(this);
                        const selectedValue = String(selector.data('saved') || '').trim();
                        const options = buildPatternOptions(patterns, selectedValue);
                        // Setting options on selector - suppressed logging in production

                        if ($.fn.select2 && selector.hasClass('select2-hidden-accessible')) {
                            selector.select2('destroy');
                        }

                        if (selector.data('customized')) {
                            selector.next('.limosms-custom-select').remove();
                            selector.removeData('customized').removeClass('limosms-native-hidden');
                        }

                        selector.html(options);
                    });

                    // تنظیم متن الگوهای ذخیره شده
                    $('.limosms-form-card').each(function () {
                        const card = $(this);
                        const patternText = card.find('.limosms-pattern-text').text().trim();
                        if (patternText) {
                            renderPatternMapping(card);
                        }
                    });

                    maybeInitGravityPatternSelect2('.limosms-gravity-pattern-selector');
                    initCustomPatternSelects('.limosms-gravity-pattern-selector');
                    showNotification('الگوها با موفقیت دریافت شدند.', 'success');
                } else {
                    const errorMsg = (response && response.data && response.data.message) ? response.data.message : 'دریافت الگوها ناموفق بود.';
                    // Error details suppressed in production
                    showNotification(errorMsg, 'error');
                }
            },
            error: function (xhr, status, error) {
                // AJAX error details suppressed in production
                showNotification('خطا در ارتباط با سرور: ' + error, 'error');
            },
            complete: function () {
                $('.limosms-gravity-pattern-selector').prop('disabled', false);
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

    function validateGravityFormsEvents() {
        let hasError = false;
        let errorMessage = '';

        $('.limosms-form-card').each(function () {
            const card = $(this);
            const formId = String(card.data('form') || '');
            const enabled = card.find('.limosms-gravity-form-enabled').is(':checked');
            const otpId = String(card.find('.limosms-gravity-otp-id').val() || '');
            const patternText = String(card.find('.limosms-pattern-text').text() || '');
            const patternInputs = card.find('.limosms-pattern-select');
            const hasVariables = /\{(\d+)\}/.test(patternText);

            if (!enabled) {
                return;
            }

            if (!otpId) {
                hasError = true;
                errorMessage = 'لطفاً برای فرم "' + formId + '" یک پترن انتخاب کنید.';
                return false;
            }

            if (hasVariables && !patternInputs.length) {
                hasError = true;
                errorMessage = 'برای فرم "' + formId + '" هیچ پارامتری پیدا نشد.';
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
                errorMessage = 'لطفاً تمام فیلدهای الگو را برای فرم "' + formId + '" تکمیل کنید.';
                return false;
            }
        });

        return {
            valid: !hasError,
            message: errorMessage
        };
    }

    function collectFormsPayload() {
        const forms = {};
        $('.limosms-form-card').each(function () {
            const card = $(this);
            const formId = card.data('form');
            forms[formId] = {
                enabled: card.find('.limosms-gravity-form-enabled').is(':checked') ? 'yes' : 'no',
                otp_id: card.find('.limosms-gravity-otp-id').val() || '',
                pattern_text: card.find('.limosms-pattern-text').text() || '',
                pattern_selector: card.find('.limosms-gravity-pattern-selector').val() || '',
                pattern_map: collectPatternMap(card)
            };
        });
        return forms;
    }

    function saveGravityFormsSettings(button) {
        const validation = validateGravityFormsEvents();
        if (!validation.valid) {
            showNotification(validation.message || 'لطفاً خطاهای فرم را اصلاح کنید.', 'error');
            return;
        }

        const data = getGravityFormsSmsData();
        if (!data.ajax_url || !data.nonce) {
            showNotification('اطلاعات لازم برای ذخیره‌سازی موجود نیست.');
            return;
        }

        const payload = collectFormsPayload();
        const adminPhones = $('#limosms_gravity_forms_admin_phones').val() || '';
        const originalText = button.text();
        button.prop('disabled', true).text('در حال ذخیره...');

        $.ajax({
            url: data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'limosms_save_gravity_forms_sms_settings',
                nonce: data.nonce,
                smsForms: JSON.stringify(payload),
                adminPhones: adminPhones
            },
            success: function (response) {
                if (response && response.success) {
                    showNotification('تنظیمات با موفقیت ذخیره شد.', 'success');
                    gravityFormsInitialState = getGravityFormsCurrentState();
                    disableSaveButton();
                } else {
                    showNotification((response && response.data) ? response.data.message || response.data : 'ذخیره تنظیمات با خطا مواجه شد.');
                }
            },
            error: function () {
                showNotification('خطا در ارتباط با سرور هنگام ذخیره تنظیمات');
            },
            complete: function () {
                updateGravityFormsSaveButtonState();
                button.text(originalText);
            }
        });
    }

    function bindEvents() {
        if (gravityFormsTabInitialized) {
            return;
        }
        gravityFormsTabInitialized = true;

        // جستجو در لیست فیلدها
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
        
        // دکمه مشاهده همه
        $(document)
            .off('click.limosmsToggleTokens', '.limosms-toggle-tokens-btn')
            .on('click.limosmsToggleTokens', '.limosms-toggle-tokens-btn', function (e) {
                e.preventDefault();

                const button = $(this);
                const container = button.closest('.limosms-mapping-row').find('.limosms-tokens-container').first();

                if (!container.length) {
                    return;
                }

                const expanded = !container.hasClass('expanded');
                container.toggleClass('expanded', expanded);
                container.css({
                    'max-height': expanded ? 'none' : '120px',
                    'overflow-y': expanded ? 'visible' : 'auto'
                });
                button.text(expanded ? 'بستن لیست' : 'مشاهده همه');
            });

        $(document).on('change', '.limosms-gravity-form-enabled', function () {
            const checkbox = $(this);
            const card = checkbox.closest('.limosms-form-card');
            toggleFormFields(card, checkbox.is(':checked'));
            refreshUI(card);
            updateGravityFormsSaveButtonState();
        });

        $(document).on('change', '.limosms-gravity-pattern-selector', function () {
            const select = $(this);
            const card = select.closest('.limosms-form-card');
            const value = String(select.val() || '').trim();

            let patternText = '';
            if (value && gravityFormsPatternsCache[value]) {
                patternText = gravityFormsPatternsCache[value].message;
                card.find('.limosms-gravity-otp-id').val(value);
            }

            card.find('.limosms-pattern-text').text(patternText);

            if (patternText) {
                renderPatternMapping(card);
            } else {
                card.find('.limosms-gravity-pattern-mapping-wrap').html('');
            }

            updateGravityFormsSaveButtonState();
        });

        $(document).on('click', '.limosms-token-chip', function (e) {
            e.preventDefault();
            const chip = $(this);
            const row = chip.closest('.limosms-mapping-row');
            const select = row.find('.limosms-pattern-select');

            row.find('.limosms-token-chip').removeClass('is-active');
            chip.addClass('is-active');
            select.val(chip.data('token'));

            refreshUI(chip.closest('.limosms-form-card'));
            updateGravityFormsSaveButtonState();
        });

        $(document).on('click', '#limosms-save-gravity-forms-settings', function (e) {
            e.preventDefault();
            saveGravityFormsSettings($(this));
        });
    }

    function init() {
        bindEvents();
        gravityFormsInitialState = getGravityFormsCurrentState();
        disableSaveButton();
        syncVisibleGravityFormCards(true);
        loadPatterns();
    }

    // نقطه ورود
    $(document).ready(function () {
        init();

        $(document).on('limosms:tab-loaded limosms_tab_loaded', function (event, activeTab) {
            if (!activeTab || activeTab === 'gravity-forms-sms') {
                init();
            }
        });

        if (typeof window.limosmLoadTabCallback !== 'function') {
            window.limosmLoadTabCallback = function (tabName) {
                if (tabName === 'gravity-forms-sms') {
                    init();
                }
            };
        }
    });

})(jQuery);
