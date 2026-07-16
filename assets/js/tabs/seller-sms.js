(function ($) {
    'use strict';

    let sellerPatterns = [];
    let sellerDirty = false;
    let sellerLoadingPatterns = false;
    let sellerHasLoadedPatterns = false;
    let sellerEventsBound = false;
    let sellerInitialized = false;
    let sellerInitObserver = null;

    function getSellerRoot() {
        return $('.limosms-seller-sms-settings').first();
    }

    function getSellerCards() {
        return getSellerRoot().find('.limosms-event-card');
    }

    function parseJSONSafe(value, fallback) {
        if (!value) {
            return fallback;
        }

        try {
            return JSON.parse(value);
        } catch (error) {
            return fallback;
        }
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showNotification(message, type) {
        if (window.LimoSMS && typeof window.LimoSMS.showToast === 'function') {
            window.LimoSMS.showToast(message, type || 'error');
            return;
        }

        window.alert(message);
    }

    function setSellerDirty(state) {
        sellerDirty = !!state;

        const button = getSellerRoot().find('#limosms-save-seller-otp-settings');
        if (button.length) {
            button.prop('disabled', !sellerDirty);
        }
    }

    function getEventTokens(eventKey) {
        if (
            typeof window.limosmsTokens !== 'undefined' &&
            window.limosmsTokens &&
            typeof window.limosmsTokens[eventKey] !== 'undefined'
        ) {
            return window.limosmsTokens[eventKey];
        }

        return {};
    }

    function getSavedMap(container) {
        const raw = container.attr('data-saved-map');
        const parsed = parseJSONSafe(raw, {});
        return parsed && typeof parsed === 'object' ? parsed : {};
    }

    function normalizeToken(token) {
        return String(token || '').replace(/[{}]/g, '').trim();
    }

    function isSameToken(firstToken, secondToken) {
        return normalizeToken(firstToken) === normalizeToken(secondToken);
    }

    function getPatternCode(pattern) {
        return pattern.id || pattern.otp_id || pattern.pattern_id || '';
    }

    function getPatternTitle(pattern) {
        return pattern.title || pattern.name || pattern.pattern_title || pattern.pattern_name || '';
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

    function extractPatternParameters(pattern) {
        if (Array.isArray(pattern.parameters) && pattern.parameters.length) {
            return pattern.parameters;
        }

        if (Array.isArray(pattern.params) && pattern.params.length) {
            return pattern.params;
        }

        const text = String(getPatternText(pattern) || '');
        const matches = text.match(/\{(\d+)\}/g) || [];

        return Array.from(new Set(matches))
            .map(function (match) {
                return parseInt(match.replace(/[{}]/g, ''), 10);
            })
            .filter(function (item) {
                return !Number.isNaN(item);
            })
            .sort(function (a, b) {
                return a - b;
            });
    }

    function normalizePatternItem(item) {
        if (!item || typeof item !== 'object') {
            return null;
        }

        const id = String(getPatternCode(item) || '').trim();
        if (!id) {
            return null;
        }

        return {
            raw: item,
            id: id,
            title: String(getPatternTitle(item) || '').trim(),
            text: String(getPatternText(item) || ''),
            parameters: extractPatternParameters(item)
        };
    }

    function findPatternById(patternId) {
        patternId = String(patternId || '').trim();

        if (!patternId) {
            return null;
        }

        for (let index = 0; index < sellerPatterns.length; index++) {
            if (String(sellerPatterns[index].id) === patternId) {
                return sellerPatterns[index];
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

    function getSavedToken(savedMap, index) {
        const item = savedMap[index];

        if (!item) {
            return '';
        }

        return typeof item === 'object' && item !== null ? (item.token || '') : item;
    }

    function getTokenLabel(tokens, tokenKey) {
        if (!tokenKey) {
            return '';
        }

        if (tokens && typeof tokens[tokenKey] !== 'undefined') {
            return String(tokens[tokenKey] || '');
        }

        return String(tokenKey || '');
    }

    function updateBadgeState(row, tokens) {
        const selectedValue = String(row.find('.limosms-pattern-select').val() || '');
        const badge = row.find('.limosms-selected-badge');

        if (!badge.length) {
            return;
        }

        badge.removeClass('is-empty is-selected');

        if (!selectedValue) {
            badge.addClass('is-empty').text('انتخاب نشده');
            return;
        }

        badge.addClass('is-selected').text(getTokenLabel(tokens, selectedValue));
    }

    function refreshTokenChipState(card) {
        card.find('.limosms-mapping-row').each(function () {
            const row = $(this);
            const hiddenInput = row.find('.limosms-pattern-select');
            const currentValue = String(hiddenInput.val() || '');
            const selectedValues = card.find('.limosms-pattern-select').map(function () {
                return String($(this).val() || '');
            }).get().filter(Boolean);

            row.find('.limosms-token-chip').each(function () {
                const chip = $(this);
                const tokenValue = String(chip.attr('data-token') || '');
                const usedElsewhere = selectedValues.some(function (selectedValue) {
                    return isSameToken(selectedValue, tokenValue) && !isSameToken(currentValue, tokenValue);
                });

                chip.toggleClass('is-active', isSameToken(currentValue, tokenValue));
                chip.toggleClass('is-disabled', usedElsewhere);
                chip.prop('disabled', usedElsewhere);
                chip.attr('aria-pressed', isSameToken(currentValue, tokenValue) ? 'true' : 'false');
            });

            updateBadgeState(row, getEventTokens(card.data('event')));
        });
    }

    function buildMappingUI(eventKey, pattern, mappingContainer) {
        const tokens = getEventTokens(eventKey);
        const savedMap = getSavedMap(mappingContainer);

        mappingContainer.empty();

        if (!pattern) {
            mappingContainer.html('<p class="limosms-pattern-empty">ابتدا یک Pattern انتخاب کنید.</p>');
            return;
        }

        const parameters = Array.isArray(pattern.parameters) ? pattern.parameters : [];

        if (!parameters.length) {
            mappingContainer.html('<p class="limosms-pattern-empty">برای این Pattern پارامتری یافت نشد.</p>');
            return;
        }

        if (!Object.keys(tokens).length) {
            mappingContainer.html('<p class="limosms-pattern-empty">توکنی برای این رویداد پیدا نشد.</p>');
            return;
        }

        parameters.forEach(function (param) {
            const paramIndex = String(param);
            const selectedToken = String(getSavedToken(savedMap, paramIndex) || '');
            const row = $('<div class="limosms-mapping-row"></div>');
            const header = $('<div class="limosms-mapping-header"></div>');
            const label = $('<div class="limosms-mapping-label">به جای پارامتر {' + escapeHtml(paramIndex) + '} قرار بگیرد</div>');
            const badge = $('<div class="limosms-selected-badge is-empty">انتخاب نشده</div>');
            const tokenList = $('<div class="limosms-token-list" data-param="' + escapeHtml(paramIndex) + '"></div>');
            const hiddenInput = $('<input type="hidden" class="limosms-pattern-select" data-param-index="' + escapeHtml(paramIndex) + '">');

            hiddenInput.val(selectedToken);

            Object.keys(tokens).forEach(function (tokenKey) {
                const chip = $('<button type="button" class="limosms-token-chip"></button>');

                chip.attr('data-token', tokenKey);
                chip.text(tokens[tokenKey]);

                if (selectedToken && isSameToken(selectedToken, tokenKey)) {
                    chip.addClass('is-active').attr('aria-pressed', 'true');
                } else {
                    chip.attr('aria-pressed', 'false');
                }

                tokenList.append(chip);
            });

            header.append(label).append(badge);
            row.append(header).append(tokenList).append(hiddenInput);
            mappingContainer.append(row);

            updateBadgeState(row, tokens);
        });

        refreshTokenChipState(mappingContainer.closest('.limosms-event-card'));
    }

    function updateEventPatternUI(card, pattern) {
        const eventKey = card.data('event');
        const text = card.find('.limosms-pattern-text');
        const map = card.find('.limosms-pattern-mapping');
        const otpId = card.find('.limosms-event-otp-id');
        const title = card.find('.limosms-event-pattern-title');

        if (!pattern) {
            otpId.val('');
            title.val('');
            text.text('');
            map.attr('data-saved-map', '{}');
            buildMappingUI(eventKey, null, map);
            return;
        }

        otpId.val(pattern.id || '');
        title.val(pattern.title || '');
        text.text(pattern.text || '');

        const currentMap = parseJSONSafe(map.attr('data-saved-map'), {});
        map.attr('data-saved-map', JSON.stringify(currentMap || {}));

        buildMappingUI(eventKey, pattern, map);
    }

    function syncEventCardState(card) {
        const isEnabled = card.find('.limosms-event-enabled').is(':checked');
        card.toggleClass('is-active', isEnabled);
    }

    function populatePatternSelectors() {
        getSellerCards().each(function () {
            const card = $(this);
            const selector = card.find('.limosms-pattern-selector');
            const savedPatternId = String(card.find('.limosms-event-otp-id').val() || '');
            let html = '<option value="">انتخاب الگو...</option>';

            sellerPatterns.forEach(function (pattern) {
                html += '<option value="' + escapeHtml(pattern.id) + '">' + escapeHtml(buildPatternOptionLabel(pattern)) + '</option>';
            });

            selector.html(html);

            if (savedPatternId) {
                selector.val(savedPatternId);
                updateEventPatternUI(card, findPatternById(savedPatternId));
            } else {
                updateEventPatternUI(card, null);
            }

            syncEventCardState(card);
        });
    }

    function loadPatterns(forceReload, callback) {
        if (sellerLoadingPatterns) {
            return;
        }

        if (sellerHasLoadedPatterns && !forceReload) {
            populatePatternSelectors();

            if (typeof callback === 'function') {
                callback();
            }

            return;
        }

        sellerLoadingPatterns = true;

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

            sellerPatterns = rawPatterns
                .map(normalizePatternItem)
                .filter(function (item) {
                    return item && item.id;
                });

            sellerHasLoadedPatterns = true;
            populatePatternSelectors();

            if (typeof callback === 'function') {
                callback();
            }
        }).fail(function () {
            showNotification('خطا در ارتباط با سرور هنگام دریافت الگوها.', 'error');
        }).always(function () {
            sellerLoadingPatterns = false;
        });
    }

    function collectSellerEventsData() {
        const result = {};

        getSellerCards().each(function () {
            const card = $(this);
            const eventKey = String(card.data('event') || '');

            if (!eventKey) {
                return;
            }

            const enabled = card.find('.limosms-event-enabled').is(':checked');
            const otpId = String(card.find('.limosms-event-otp-id').val() || '');
            const title = String(card.find('.limosms-event-pattern-title').val() || '');
            const patternText = String(card.find('.limosms-pattern-text').text() || '');
            const patternMap = {};

            card.find('.limosms-pattern-select').each(function () {
                const input = $(this);
                const paramIndex = String(input.data('param-index'));
                const tokenValue = String(input.val() || '');

                if (paramIndex !== '' && tokenValue !== '') {
                    patternMap[paramIndex] = tokenValue;
                }
            });

            result[eventKey] = {
                enabled: enabled ? 'yes' : 'no',
                otp_id: enabled ? otpId : '',
                title: enabled ? title : '',
                pattern_text: enabled ? patternText : '',
                pattern_map: enabled ? patternMap : {}
            };
        });

        return result;
    }

    function validateSellerEvents() {
        let hasError = false;
        let errorMessage = '';

        getSellerCards().each(function () {
            const card = $(this);
            const eventKey = String(card.data('event') || '');

            if (!eventKey) {
                return;
            }

            const enabled = card.find('.limosms-event-enabled').is(':checked');
            const otpId = String(card.find('.limosms-event-otp-id').val() || '');
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
                if (!String($(this).val() || '')) {
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

    function persistCurrentMaps() {
        getSellerCards().each(function () {
            const card = $(this);
            const currentMap = {};

            card.find('.limosms-pattern-select').each(function () {
                const idx = String($(this).data('param-index'));
                const val = String($(this).val() || '');

                if (idx !== '' && val !== '') {
                    currentMap[idx] = val;
                }
            });

            card.find('.limosms-pattern-mapping').attr('data-saved-map', JSON.stringify(currentMap));
        });
    }

    function saveSellerSettings() {
        const validation = validateSellerEvents();

        if (!validation.valid) {
            showNotification(validation.message, 'error');
            return;
        }

        const smsEvents = collectSellerEventsData();
        const button = getSellerRoot().find('#limosms-save-seller-otp-settings');

        $.ajax({
            url: limosms_ajax.url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'limosms_save_seller_sms_settings',
                nonce: limosms_ajax.nonce,
                smsEvents: JSON.stringify(smsEvents)
            },
            beforeSend: function () {
                button.prop('disabled', true).addClass('updating-message');
            }
        }).done(function (response) {
            if (!response || !response.success) {
                showNotification(
                    response && response.data && response.data.message
                        ? response.data.message
                        : 'ذخیره تنظیمات انجام نشد.',
                    'error'
                );
                return;
            }

            persistCurrentMaps();
            setSellerDirty(false);

            showNotification(
                response.data && response.data.message
                    ? response.data.message
                    : 'تنظیمات با موفقیت ذخیره شد.',
                'success'
            );
        }).fail(function (xhr) {
            const responseMessage = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message
                : 'خطا در ارتباط با سرور. لطفا مجددا تلاش کنید.';

            showNotification(responseMessage, 'error');
        }).always(function () {
            button.removeClass('updating-message');

            if (sellerDirty) {
                button.prop('disabled', false);
            }
        });
    }

    function bindSellerEvents() {
        if (sellerEventsBound) {
            return;
        }

        sellerEventsBound = true;

        $(document).on('change', '.limosms-seller-sms-settings .limosms-event-enabled', function () {
            const card = $(this).closest('.limosms-event-card');
            syncEventCardState(card);
            setSellerDirty(true);
        });

        $(document).on('change', '.limosms-seller-sms-settings .limosms-pattern-selector', function () {
            const selector = $(this);
            const patternId = String(selector.val() || '');
            const card = selector.closest('.limosms-event-card');
            const map = card.find('.limosms-pattern-mapping');

            map.attr('data-saved-map', '{}');

            if (!patternId) {
                updateEventPatternUI(card, null);
                setSellerDirty(true);
                return;
            }

            updateEventPatternUI(card, findPatternById(patternId));
            setSellerDirty(true);
        });

        $(document).on('click', '.limosms-seller-sms-settings .limosms-token-chip', function (event) {
            event.preventDefault();

            const chip = $(this);

            if (chip.hasClass('is-disabled') || chip.prop('disabled')) {
                return;
            }

            const row = chip.closest('.limosms-mapping-row');
            const hiddenInput = row.find('.limosms-pattern-select');
            const currentValue = String(hiddenInput.val() || '');
            const nextValue = String(chip.attr('data-token') || '');

            if (isSameToken(currentValue, nextValue)) {
                hiddenInput.val('').trigger('change');
                return;
            }

            hiddenInput.val(nextValue).trigger('change');
        });

        $(document).on('change', '.limosms-seller-sms-settings .limosms-pattern-select', function () {
            const card = $(this).closest('.limosms-event-card');
            refreshTokenChipState(card);
            setSellerDirty(true);
        });

        $(document).on('click', '.limosms-seller-sms-settings #limosms-save-seller-otp-settings', function (event) {
            event.preventDefault();
            saveSellerSettings();
        });
    }

    function canInitSeller() {
        return getSellerRoot().length > 0 && getSellerCards().length > 0;
    }

    function initSellerSMSTab(forceReloadPatterns) {
        if (!canInitSeller()) {
            return false;
        }

        bindSellerEvents();

        getSellerCards().each(function () {
            syncEventCardState($(this));
        });

        setSellerDirty(false);
        loadPatterns(!!forceReloadPatterns);

        sellerInitialized = true;
        return true;
    }

    function tryInitSeller(forceReloadPatterns) {
        return initSellerSMSTab(forceReloadPatterns);
    }

    function startSellerObserver() {
        if (sellerInitObserver || !window.MutationObserver) {
            return;
        }

        sellerInitObserver = new MutationObserver(function () {
            if (sellerInitialized) {
                return;
            }

            if (canInitSeller()) {
                tryInitSeller(false);
            }
        });

        sellerInitObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    function bootstrapSellerSMS() {
        bindSellerEvents();

        if (!tryInitSeller(false)) {
            startSellerObserver();

            let retries = 0;
            const retryInterval = window.setInterval(function () {
                retries++;

                if (tryInitSeller(false) || retries >= 20) {
                    window.clearInterval(retryInterval);
                }
            }, 500);
        }
    }

    $(document).ready(function () {
        bootstrapSellerSMS();
    });

    $(window).on('load', function () {
        bootstrapSellerSMS();
    });

    $(document).on('limosms:tab-loaded', function (event, activeTab) {
        if (!activeTab || activeTab === 'seller-sms') {
            sellerInitialized = false;
            bootstrapSellerSMS();
        }
    });

    $(document).on('click', '.limosms-seller-sms-settings .limosms-token-chip', function (event) {
        event.preventDefault();
        const chip = $(this);

        if (chip.hasClass('is-disabled') || chip.prop('disabled')) {
            return;
        }

        // روش امن: به جای closest به دنبال کانتینر والد خاص بگرد که پایداری بیشتری دارد
        // و سپس hidden input مربوط به همین ردیف را پیدا کن
        const tokenList = chip.closest('.limosms-token-list');
        const row = tokenList.closest('.limosms-mapping-row');
        const hiddenInput = row.find('.limosms-pattern-select');

        const currentValue = String(hiddenInput.val() || '');
        const nextValue = String(chip.attr('data-token') || '');

        if (isSameToken(currentValue, nextValue)) {
            hiddenInput.val('').trigger('change');
            return;
        }

        hiddenInput.val(nextValue).trigger('change');
    });


})(jQuery);
