(function ($) {
    'use strict';

    let allPatterns = [];
    let currentPage = 1;
    const itemsPerPage = 10;
    let hasLoadedPatterns = false;
    let isLoadingPatterns = false;

    function getElements() {
        return {
            tableBody: $('#limosms-patterns-table-body'),
            refreshButton: $('#limosms-refresh-patterns'),
            paginationWrap: $('.limosms-pagination-wrap'),
            paginationInfo: $('.limosms-pagination-info'),
            pageNumbersWrap: $('.limosms-page-numbers'),
            prevButton: $('.limosms-page-btn.prev'),
            nextButton: $('.limosms-page-btn.next')
        };
    }

    function showNotification(message, type) {
        if (window.LimoSMS && typeof window.LimoSMS.showToast === 'function') {
            window.LimoSMS.showToast(message, type || 'success');
            return;
        }

        window.alert(message);
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function normalizePatterns(responseData) {
        if (Array.isArray(responseData)) {
            return responseData;
        }

        if (responseData && Array.isArray(responseData.data)) {
            return responseData.data;
        }

        if (responseData && Array.isArray(responseData.patterns)) {
            return responseData.patterns;
        }

        if (responseData && responseData.data && Array.isArray(responseData.data.patterns)) {
            return responseData.data.patterns;
        }

        return [];
    }

    function getPatternCode(pattern) {
        return pattern.code ||
            pattern.pattern_code ||
            pattern.patternCode ||
            pattern.pattern_id ||
            pattern.patternId ||
            pattern.code_text ||
            pattern.text_code ||
            pattern.id ||
            '';
    }

    function getPatternText(pattern) {
        return pattern.text ||
            pattern.content ||
            pattern.pattern ||
            pattern.body ||
            pattern.message ||
            '-';
    }

    function renderEmptyRow(message) {
        const elements = getElements();

        if (!elements.tableBody.length) {
            return;
        }

        elements.tableBody.html(
            '<tr>' +
            '<td colspan="4" style="text-align:center; padding:24px;">' +
            escapeHtml(message) +
            '</td>' +
            '</tr>'
        );
    }

    function renderPaginationControls() {
        const elements = getElements();
        const totalPages = Math.ceil(allPatterns.length / itemsPerPage);

        if (!elements.paginationWrap.length) {
            return;
        }

        if (totalPages <= 1) {
            elements.paginationWrap.hide();
            return;
        }

        elements.paginationWrap.css('display', 'flex').show();
        elements.paginationInfo.text('صفحه ' + currentPage + ' از ' + totalPages);

        let pageNumbersHtml = '';

        for (let page = 1; page <= totalPages; page++) {
            pageNumbersHtml +=
                '<button type="button" class="page-number ' + (page === currentPage ? 'active' : '') + '" data-page="' + page + '">' +
                page +
                '</button>';
        }

        elements.pageNumbersWrap.html(pageNumbersHtml);
        elements.prevButton.prop('disabled', currentPage === 1);
        elements.nextButton.prop('disabled', currentPage === totalPages);
    }

    function renderTableRows() {
        const elements = getElements();

        if (!elements.tableBody.length) {
            return;
        }

        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const paginatedPatterns = allPatterns.slice(startIndex, endIndex);

        elements.tableBody.empty();

        if (!paginatedPatterns.length) {
            renderEmptyRow('هیچ الگویی یافت نشد.');

            if (elements.paginationWrap.length) {
                elements.paginationWrap.hide();
            }

            return;
        }

        paginatedPatterns.forEach(function (pattern, index) {
            const rowNumber = startIndex + index + 1;
            const rawCode = getPatternCode(pattern);
            const textValue = getPatternText(pattern);
            const shortText = textValue.length > 90 ? textValue.substring(0, 90) + '...' : textValue;

            elements.tableBody.append(
                '<tr>' +
                '<td>' + rowNumber + '</td>' +
                '<td><code class="limosms-pattern-code">' + escapeHtml(rawCode || '-') + '</code></td>' +
                '<td class="limosms-pattern-text">' + escapeHtml(shortText) + '</td>' +
                '<td>' +
                '<div class="limosms-actions">' +
                '<button type="button" class="limosms-action-btn copy limosms-copy-pattern-code" data-code="' + escapeHtml(rawCode) + '" title="کپی کد">' +
                '<span class="dashicons dashicons-admin-page"></span>' +
                '</button>' +
                '</div>' +
                '</td>' +
                '</tr>'
            );
        });

        renderPaginationControls();
    }

    function loadPatterns(forceReload) {
        const elements = getElements();

        if (!elements.tableBody.length) {
            return;
        }

        if (isLoadingPatterns) {
            return;
        }

        if (hasLoadedPatterns && !forceReload) {
            renderTableRows();
            return;
        }

        isLoadingPatterns = true;
        renderEmptyRow('در حال دریافت الگوها...');

        if (elements.refreshButton.length) {
            elements.refreshButton.prop('disabled', true);
        }

        $.ajax({
            url: limosmsPatternManagement.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'limosms_get_patterns',
                nonce: limosmsPatternManagement.nonce
            }
        }).done(function (response) {
            if (!response || !response.success) {
                const errorMessage = response && response.data && response.data.message
                    ? response.data.message
                    : 'دریافت لیست الگوها ناموفق بود.';

                renderEmptyRow(errorMessage);
                showNotification(errorMessage, 'error');
                return;
            }

            allPatterns = normalizePatterns(response.data);
            currentPage = 1;
            hasLoadedPatterns = true;
            renderTableRows();
        }).fail(function () {
            renderEmptyRow('خطا در دریافت اطلاعات.');
            showNotification('خطا در ارتباط با سرور.', 'error');
        }).always(function () {
            isLoadingPatterns = false;

            if (elements.refreshButton.length) {
                elements.refreshButton.prop('disabled', false);
            }
        });
    }

    function initPatternManagementTab() {
        const elements = getElements();

        if (!elements.tableBody.length) {
            return;
        }

        loadPatterns(false);
    }

    $(document).on('click', '#limosms-refresh-patterns', function () {
        loadPatterns(true);
    });

    $(document).on('click', '.limosms-copy-pattern-code', function () {
        const code = $(this).data('code');

        if (!code) {
            showNotification('کد الگو موجود نیست.', 'error');
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(String(code)).then(function () {
                showNotification('کد الگو کپی شد.', 'success');
            }).catch(function () {
                showNotification('کپی کد انجام نشد.', 'error');
            });

            return;
        }

        const tempInput = $('<input type="text">');
        $('body').append(tempInput);
        tempInput.val(String(code)).trigger('select');
        document.execCommand('copy');
        tempInput.remove();

        showNotification('کد الگو کپی شد.', 'success');
    });

    $(document).on('click', '.page-number', function () {
        const page = parseInt($(this).data('page'), 10);

        if (!page || page === currentPage) {
            return;
        }

        currentPage = page;
        renderTableRows();
    });

    $(document).on('click', '.limosms-page-btn.prev', function () {
        if (currentPage > 1) {
            currentPage--;
            renderTableRows();
        }
    });

    $(document).on('click', '.limosms-page-btn.next', function () {
        const totalPages = Math.ceil(allPatterns.length / itemsPerPage);

        if (currentPage < totalPages) {
            currentPage++;
            renderTableRows();
        }
    });

    $(document).ready(function () {
        const urlParams = new URLSearchParams(window.location.search);
        const currentTab = urlParams.get('tab') || 'connection-settings';

        if (currentTab === 'sms-pattern-management') {
            initPatternManagementTab();
        }
    });

    $(document).on('limosms:tab-loaded', function (event, tab) {
        if (tab === 'sms-pattern-management') {
            initPatternManagementTab();
        }
    });

})(jQuery);
