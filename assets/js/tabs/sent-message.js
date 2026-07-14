(function ($) {
    'use strict';

    console.log('LimoSMS sent-message.js loaded');

    let allMessages = [];
    let currentPage = 1;
    const itemsPerPage = 10;
    let hasLoaded = false;
    let isLoading = false;

    const selectors = {
        tableBody: '#limosms-sent-sms-table-body',
        refreshBtn: '#limosms-refresh-sent-sms',
        paginationWrap: '#limosms-sent-sms-pagination',
        paginationInfo: '#limosms-sent-sms-pagination .limosms-pagination-info',
        pageNumbersWrap: '#limosms-sent-sms-pagination .limosms-page-numbers',
        prevButton: '#limosms-sent-sms-pagination .limosms-page-btn.prev',
        nextButton: '#limosms-sent-sms-pagination .limosms-page-btn.next',
    };

    function getConfig() {
        if (typeof window.limosmsSentMessage === 'undefined') {
            console.error('limosmsSentMessage is not defined.');
            return null;
        }

        return window.limosmsSentMessage;
    }

    function escapeHtml(text) {
        return String(text || '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[char];
        });
    }

    function renderEmptyRow(message) {
        const config = getConfig();
        const fallbackText = config ? config.emptyText : 'داده‌ای یافت نشد.';

        $(selectors.tableBody).html(
            '<tr><td colspan="5" style="text-align:center; padding:24px;">' +
            escapeHtml(message || fallbackText) +
            '</td></tr>'
        );
    }

    function renderPagination() {
        const totalPages = Math.ceil(allMessages.length / itemsPerPage);
        const $wrapper = $(selectors.paginationWrap);

        if (totalPages <= 1) {
            $wrapper.hide();
            return;
        }

        $wrapper.css('display', 'flex').show();
        $(selectors.paginationInfo).text('صفحه ' + currentPage + ' از ' + totalPages);

        let numbersHtml = '';
        for (let page = 1; page <= totalPages; page++) {
            numbersHtml += '<button type="button" class="page-number ' +
                (page === currentPage ? 'active' : '') + '" data-page="' + page + '">' +
                page + '</button>';
        }

        $(selectors.pageNumbersWrap).html(numbersHtml);
        $(selectors.prevButton).prop('disabled', currentPage === 1);
        $(selectors.nextButton).prop('disabled', currentPage === totalPages);
    }

    function renderRows() {
        const config = getConfig();
        const emptyText = config ? config.emptyText : 'داده‌ای یافت نشد.';
        const $tbody = $(selectors.tableBody);

        $tbody.empty();

        const startIndex = (currentPage - 1) * itemsPerPage;
        const paginated = allMessages.slice(startIndex, startIndex + itemsPerPage);

        if (!paginated.length) {
            renderEmptyRow(emptyText);
            $(selectors.paginationWrap).hide();
            return;
        }

        paginated.forEach(function (item, index) {
            const rowNumber = startIndex + index + 1;
            const messageText = escapeHtml(item.smsMessage || '-');
            const shortMessage = messageText.length > 150 ? messageText.substring(0, 150) + '...' : messageText;

            const sendDate = escapeHtml(item.sendDate || '-');
            const state = escapeHtml(item.state || '-');
            const status = escapeHtml(item.status || '-');

            $tbody.append(
                '<tr>' +
                '<td>' + rowNumber + '</td>' +
                '<td class="limosms-pattern-text" title="' + messageText + '">' + shortMessage + '</td>' +
                '<td>' + sendDate + '</td>' +
                '<td>' + state + '</td>' +
                '<td>' + status + '</td>' +
                '</tr>'
            );
        });

        renderPagination();
    }

    function normalizeResponse(data) {
        if (Array.isArray(data)) {
            return data;
        }

        if (data && Array.isArray(data.data)) {
            return data.data;
        }

        return [];
    }

    function loadMessages(forceRefresh) {
        const config = getConfig();

        if (!config) {
            renderEmptyRow('تنظیمات جاوااسکریپت تب بارگذاری نشده است.');
            return;
        }

        console.log('Loading sent messages...', { forceRefresh: forceRefresh });

        if (isLoading) {
            return;
        }

        if (hasLoaded && !forceRefresh) {
            renderRows();
            return;
        }

        isLoading = true;
        renderEmptyRow(config.loadingText);
        $(selectors.refreshBtn).prop('disabled', true);

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'limosms_get_sent_sms',
                nonce: config.nonce,
            },
        }).done(function (response) {
            console.log('Sent messages AJAX success:', response);

            if (!response || !response.success) {
                const message = response && response.data && response.data.message
                    ? response.data.message
                    : config.loadErrorText;

                renderEmptyRow(message);
                return;
            }

            allMessages = normalizeResponse(response.data);
            currentPage = 1;
            hasLoaded = true;
            renderRows();
        }).fail(function (xhr, status, error) {
            console.error('Sent messages AJAX error:', {
                status: status,
                error: error,
                responseText: xhr && xhr.responseText ? xhr.responseText : '',
            });

            renderEmptyRow(config.loadErrorText);
        }).always(function () {
            isLoading = false;
            $(selectors.refreshBtn).prop('disabled', false);
        });
    }

    function initTab() {
        if (!$(selectors.tableBody).length) {
            console.warn('Sent messages table body not found.');
            return;
        }

        console.log('Initializing sent-message tab');
        loadMessages(false);
    }

    $(document).on('click', selectors.refreshBtn, function () {
        loadMessages(true);
    });

    $(document).on('click', '#limosms-sent-sms-pagination .page-number', function () {
        const page = parseInt($(this).data('page'), 10);

        if (!page || page === currentPage) {
            return;
        }

        currentPage = page;
        renderRows();
    });

    $(document).on('click', selectors.prevButton, function () {
        if (currentPage > 1) {
            currentPage--;
            renderRows();
        }
    });

    $(document).on('click', selectors.nextButton, function () {
        const totalPages = Math.ceil(allMessages.length / itemsPerPage);

        if (currentPage < totalPages) {
            currentPage++;
            renderRows();
        }
    });

    $(document).ready(function () {
        const urlParams = new URLSearchParams(window.location.search);
        const currentTab = urlParams.get('tab') || 'connection-settings';

        console.log('Current tab:', currentTab);

        if (currentTab === 'sent-message') {
            initTab();
        }
    });

    $(document).on('limosms:tab-loaded', function (event, tab) {
        console.log('Tab loaded event:', tab);

        if (tab === 'sent-message') {
            initTab();
        }
    });

})(jQuery);
