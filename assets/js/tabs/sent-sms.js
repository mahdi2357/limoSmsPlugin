(function ($) {
    'use strict';

    let allSentSms = [];
    let currentPage = 1;
    const itemsPerPage = 10;
    let hasLoadedSentSms = false;
    let isLoadingSentSms = false;

    function getElements() {
        return {
            tableBody: $('#limosms-sent-sms-table-body'),
            paginationWrap: $('.limosms-sent-sms-pagination-wrap'),
            paginationInfo: $('.limosms-sent-sms-pagination-info'),
            pageNumbersWrap: $('.limosms-sent-sms-page-numbers'),
            prevButton: $('.limosms-sent-sms-page-btn.prev'),
            nextButton: $('.limosms-sent-sms-page-btn.next'),
            refreshButton: $('#limosms-refresh-sent-sms')
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

    function toSafeString(value) {
        if (value === null || typeof value === 'undefined') {
            return '-';
        }

        const str = String(value).trim();
        return str ? str : '-';
    }

    function normalizeSentSms(responseData) {
        if (Array.isArray(responseData)) {
            return responseData;
        }

        if (responseData && Array.isArray(responseData.data)) {
            return responseData.data;
        }

        if (responseData && Array.isArray(responseData.messages)) {
            return responseData.messages;
        }

        if (responseData && Array.isArray(responseData.sms)) {
            return responseData.sms;
        }

        if (responseData && responseData.data && Array.isArray(responseData.data.messages)) {
            return responseData.data.messages;
        }

        if (responseData && responseData.data && Array.isArray(responseData.data.sms)) {
            return responseData.data.sms;
        }

        return [];
    }

    function getMobile(item) {
        return toSafeString(
            item.mobile ||
            item.phone ||
            item.to ||
            item.receiver ||
            item.receptor ||
            item.recipient ||
            item.number
        );
    }

    function getMessage(item) {
        return toSafeString(
            item.message ||
            item.smsMessage ||
            item.text ||
            item.body ||
            item.content ||
            item.smsText ||
            item.sms_text
        );
    }

    function getStatus(item) {
        return toSafeString(
            item.status ||
            item.statusText ||
            item.status_text ||
            item.deliveryStatus ||
            item.delivery_status
        );
    }

    function getDate(item) {
        return toSafeString(
            item.date ||
            item.created_at ||
            item.createdAt ||
            item.sendDate ||
            item.send_date ||
            item.sent_at
        );
    }

    function getStatusClass(status) {
        const s = String(status || '').toLowerCase().trim();

        if (
            s.includes('نرسیده به گوشی') ||
            s.includes('pending') ||
            s.includes('در انتظار') ||
            s.includes('در صف')
        ) {
            return 'pending';
        }

        if (
            s.includes('مسدود شده اپراتور') ||
            s.includes('مسدود') ||
            s.includes('failed') ||
            s.includes('error') ||
            s.includes('خطا') ||
            s.includes('ناموفق')
        ) {
            return 'error';
        }

        if (
            s.includes('رسیده به گوشی') ||
            s.includes('delivered') ||
            s.includes('تحویل') ||
            s.includes('موفق')
        ) {
            return 'success';
        }

        return 'info';
    }

    function toPersianDigits(value) {
        const str = String(value ?? '');
        const en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        const fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

        return str.replace(/[0-9]/g, function (d) {
            return fa[en.indexOf(d)];
        });
    }

    function faText(value) {
        return toPersianDigits(toSafeString(value));
    }

    function pad2(value) {
        return String(value).padStart(2, '0');
    }

    function formatSentDate(value) {
        const rawValue = toSafeString(value);

        if (rawValue === '-') {
            return '-';
        }

        const normalizedValue = String(rawValue)
            .replace('T', ' ')
            .replace(/\.\d{1,6}(?=\s|Z|$)/, '')
            .replace(/Z$/, '');

        const date = new Date(normalizedValue);

        if (isNaN(date.getTime())) {
            return toPersianDigits(normalizedValue);
        }

        const year = date.getFullYear();
        const month = pad2(date.getMonth() + 1);
        const day = pad2(date.getDate());
        const hours = pad2(date.getHours());
        const minutes = pad2(date.getMinutes());

        return toPersianDigits(hours + ':' + minutes + ' - ' + year + '/' + month + '/' + day);
    }

    function renderEmptyRow(message) {
        const elements = getElements();

        if (!elements.tableBody.length) {
            return;
        }

        elements.tableBody.html(
            '<tr>' +
            '<td colspan="5" style="text-align:center; padding:24px;">' +
            escapeHtml(message) +
            '</td>' +
            '</tr>'
        );

        if (elements.paginationWrap.length) {
            elements.paginationWrap.hide();
        }
    }

    function renderPaginationControls() {
        const elements = getElements();
        const totalPages = Math.ceil(allSentSms.length / itemsPerPage);

        if (!elements.paginationWrap.length) {
            return;
        }

        if (totalPages <= 1) {
            elements.paginationWrap.hide();
            return;
        }

        elements.paginationWrap.css('display', 'flex').show();
        elements.paginationInfo.text('صفحه ' + faText(currentPage) + ' از ' + faText(totalPages));

        let html = '';

        html += '<button type="button" class="sent-sms-page-number ' + (currentPage === 1 ? 'active' : '') + '" data-page="1">' + faText(1) + '</button>';

        let start = Math.max(2, currentPage - 1);
        let end = Math.min(totalPages - 1, currentPage + 1);

        if (start > 2) {
            html += '<span class="sent-sms-page-dots">…</span>';
        }

        for (let p = start; p <= end; p++) {
            html += '<button type="button" class="sent-sms-page-number ' + (p === currentPage ? 'active' : '') + '" data-page="' + p + '">' + faText(p) + '</button>';
        }

        if (end < totalPages - 1) {
            html += '<span class="sent-sms-page-dots">…</span>';
        }

        if (totalPages > 1) {
            html += '<button type="button" class="sent-sms-page-number ' + (currentPage === totalPages ? 'active' : '') + '" data-page="' + totalPages + '">' + faText(totalPages) + '</button>';
        }

        elements.pageNumbersWrap.html(html);
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
        const paginatedItems = allSentSms.slice(startIndex, endIndex);

        elements.tableBody.empty();

        if (!paginatedItems.length) {
            renderEmptyRow('هیچ پیامکی یافت نشد.');
            return;
        }

        paginatedItems.forEach(function (item, index) {
            const rowNumber = startIndex + index + 1;
            const message = faText(getMessage(item));
            const shortMessage = message.length > 120 ? message.substring(0, 120) + '...' : message;
            const status = faText(getStatus(item));
            const statusClass = getStatusClass(status);
            const formattedDate = formatSentDate(getDate(item));

            elements.tableBody.append(
                '<tr>' +
                '<td class="col-row">' + faText(rowNumber) + '</td>' +
                '<td class="col-mobile">' + escapeHtml(faText(getMobile(item))) + '</td>' +
                '<td class="col-message limosms-pattern-text" title="' + escapeHtml(message) + '">' + escapeHtml(shortMessage) + '</td>' +
                '<td class="col-status"><span class="limosms-status-badge ' + statusClass + '">' + escapeHtml(status) + '</span></td>' +
                '<td class="col-date">' + escapeHtml(formattedDate) + '</td>' +
                '</tr>'
            );
        });

        renderPaginationControls();
    }

    function setRefreshLoadingState(isLoading) {
        const elements = getElements();

        if (!elements.refreshButton.length) {
            return;
        }

        elements.refreshButton.prop('disabled', isLoading);
    }

    function loadSentSms(forceReload) {
        const elements = getElements();

        if (!elements.tableBody.length || typeof limosmsSentSMS === 'undefined') {
            return;
        }

        if (isLoadingSentSms) {
            return;
        }

        if (hasLoadedSentSms && !forceReload) {
            renderTableRows();
            return;
        }

        isLoadingSentSms = true;
        setRefreshLoadingState(true);
        renderEmptyRow('در حال دریافت پیامک‌های ارسال‌شده...');

        $.ajax({
            url: limosmsSentSMS.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'limosms_get_sent_sms',
                nonce: limosmsSentSMS.nonce
            }
        }).done(function (response) {
            if (!response || !response.success) {
                const errorMessage = response && response.data && response.data.message
                    ? response.data.message
                    : 'دریافت پیامک‌های ارسال‌شده ناموفق بود.';

                renderEmptyRow(errorMessage);
                showNotification(errorMessage, 'error');
                return;
            }

            allSentSms = normalizeSentSms(response.data);
            currentPage = 1;
            hasLoadedSentSms = true;
            renderTableRows();
        }).fail(function () {
            renderEmptyRow('خطا در دریافت اطلاعات.');
            showNotification('خطا در ارتباط با سرور.', 'error');
        }).always(function () {
            isLoadingSentSms = false;
            setRefreshLoadingState(false);
        });
    }

    function initSentSmsTab() {
        const elements = getElements();

        if (!elements.tableBody.length) {
            return;
        }

        loadSentSms(false);
    }

    $(document).on('click', '.sent-sms-page-number', function () {
        const page = parseInt($(this).data('page'), 10);

        if (!page || page === currentPage) {
            return;
        }

        currentPage = page;
        renderTableRows();
    });

    $(document).on('click', '.limosms-sent-sms-page-btn.prev', function () {
        if (currentPage > 1) {
            currentPage--;
            renderTableRows();
        }
    });

    $(document).on('click', '.limosms-sent-sms-page-btn.next', function () {
        const totalPages = Math.ceil(allSentSms.length / itemsPerPage);

        if (currentPage < totalPages) {
            currentPage++;
            renderTableRows();
        }
    });

    $(document).on('click', '#limosms-refresh-sent-sms', function () {
        hasLoadedSentSms = false;
        loadSentSms(true);
    });

    $(document).ready(function () {
        const urlParams = new URLSearchParams(window.location.search);
        const currentTab = urlParams.get('tab') || 'connection-settings';

        if (currentTab === 'sent-sms') {
            initSentSmsTab();
        }
    });

    $(document).on('limosms:tab-loaded', function (event, tab) {
        if (tab === 'sent-sms') {
            initSentSmsTab();
        }
    });

})(jQuery);
