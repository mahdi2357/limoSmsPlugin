jQuery(document).ready(function ($) {
    'use strict';

    if (typeof limosms_ajax === 'undefined') {
        console.error('LimoSMS ajax object not found');
        return;
    }

    window.LimoSMS = window.LimoSMS || {};

    /*
    =========================
    ابزار عمومی رویداد تب‌ها
    =========================
    */
    window.LimoSMS.triggerTabLoaded = function (tab) {
        if (!tab) {
            return;
        }
        window.setTimeout(function () {
            $(document).trigger('limosms:tab-loaded', [tab]);
        }, 50); // افزودن تاخیر کوچک جهت اطمینان از تزریق کامل DOM
    };

    /*
    =========================
    نمایش پیام توست
    =========================
    */
    window.LimoSMS.showToast = function (message, type = 'success', duration = 4000) {
        let container = document.querySelector('.limosms-toast-container');

        if (!container) {
            container = document.createElement('div');
            container.className = 'limosms-toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `limosms-toast limosms-toast--${type}`;

        let icon = 'ℹ️';
        if (type === 'success') icon = '✓';
        if (type === 'error') icon = '✗';

        toast.innerHTML = `
            <span class="limosms-toast__icon">${icon}</span>
            <span class="limosms-toast__message">${message}</span>
        `;

        container.appendChild(toast);

        window.setTimeout(function () {
            toast.classList.add('is-show');
        }, 10);

        window.setTimeout(function () {
            toast.classList.replace('is-show', 'is-hide');
            toast.addEventListener('transitionend', function () {
                toast.remove();
            });
        }, duration);
    };

    /*
    =========================
    عنوان تب‌ها
    =========================
    */
    const tabTitles = {
        'connection-settings': 'تنظیمات اتصال',
        'sms-pattern-management': 'الگوهای پیامک',
        'send-test-sms': 'ارسال پیامک تست',
        'admin-sms': 'پیامک مدیر',
        'customer-sms': 'پیامک مشتری'
    };

    function setActiveTab(tab, $link) {
        $('.limosms-sidebar li').removeClass('active');

        if ($link && $link.length) {
            $link.parent().addClass('active');
            return;
        }

        $('.limosms-tab-link').each(function () {
            const href = $(this).attr('href') || '';
            if (href.indexOf('tab=' + tab) !== -1) {
                $(this).parent().addClass('active');
                return false;
            }
        });
    }

    function setPageTitle(tab) {
        if (tabTitles[tab]) {
            $('#limosms-page-title').text(tabTitles[tab]);
        }
    }

    /*
    =========================
    تابع کمکی لود محتوای تب با Ajax
    =========================
    */
    function fetchTabContent(tab, href, callback) {
        $('#limosms-tab-loading')
            .css('display', 'flex')
            .hide()
            .fadeIn(150);

        $('.limosms-sidebar li').addClass('is-loading');

        $.ajax({
            url: limosms_ajax.url,
            type: 'POST',
            data: {
                action: 'limosms_load_tab',
                tab: tab,
                nonce: limosms_ajax.nonce
            },
            success: function (response) {
                $('#limosms-tab-content').html(response);
                setActiveTab(tab);
                setPageTitle(tab);

                if (href) {
                    window.history.pushState({}, '', href);
                }

                if (typeof callback === 'function') {
                    callback();
                }
            },
            error: function () {
                $('#limosms-tab-content').html(
                    '<p style="padding:20px;color:red;">خطا در دریافت اطلاعات. لطفاً دوباره تلاش کنید.</p>'
                );
            },
            complete: function () {
                $('#limosms-tab-loading').fadeOut(150);
                $('.limosms-sidebar li').removeClass('is-loading');
            }
        });
    }

    /*
    =========================
    تغییر تب‌های سایدبار (کلیک)
    =========================
    */
    $('.limosms-sidebar').on('click', '.limosms-tab-link', function (event) {
        event.preventDefault();

        const href = $(this).attr('href') || '';
        const url = new URL(href, window.location.origin);
        const tab = url.searchParams.get('tab');

        if (!tab) {
            return;
        }

        fetchTabContent(tab, href, function () {
            window.LimoSMS.triggerTabLoaded(tab);
        });
    });

    /*
    =========================
    تب پیشفرض هنگام لود صفحه (Refresh)
    =========================
    */
    const urlParams = new URLSearchParams(window.location.search);
    const currentTab = urlParams.get('tab') || 'connection-settings';

    // در زمان رفرش اولیه صفحه، محتوا را با Ajax دریافت و سپس سیگنال آماده بودن صادر می‌کنیم
    fetchTabContent(currentTab, null, function () {
        window.LimoSMS.triggerTabLoaded(currentTab);
    });
});
