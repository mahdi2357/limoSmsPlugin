jQuery(document).ready(function ($) {
    'use strict';

    if (typeof limosms_ajax === 'undefined') {
        console.error('LimoSMS ajax object not found');
        return;
    }

    window.LimoSMS = window.LimoSMS || {};

    window.LimoSMS.triggerTabLoaded = function (tab) {
        if (!tab) {
            return;
        }

        window.setTimeout(function () {
            $(document).trigger('limosms:tab-loaded', [tab]);
            $(document).trigger('limosms_tab_loaded', [tab]);
        }, 30);
    };

    window.LimoSMS.showToast = function (message, type, duration) {
        type = type || 'success';
        duration = duration || 4000;

        let container = document.querySelector('.limosms-toast-container');

        if (!container) {
            container = document.createElement('div');
            container.className = 'limosms-toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'limosms-toast limosms-toast--' + type;

        let icon = 'ℹ️';
        if (type === 'success') {
            icon = '✓';
        } else if (type === 'error') {
            icon = '✗';
        }

        toast.innerHTML =
            '<span class="limosms-toast__icon">' + icon + '</span>' +
            '<span class="limosms-toast__message"></span>';

        toast.querySelector('.limosms-toast__message').textContent = message;
        container.appendChild(toast);

        window.setTimeout(function () {
            toast.classList.add('is-show');
        }, 10);

        window.setTimeout(function () {
            toast.classList.remove('is-show');
            toast.classList.add('is-hide');

            toast.addEventListener('transitionend', function () {
                toast.remove();
            }, { once: true });
        }, duration);
    };

    const tabTitles = {
        'connection-settings': 'تنظیمات اتصال',
        'admin-sms': 'پیامک مدیر',
        'customer-sms': 'پیامک مشتری',
        'seller-sms': 'پیامک فروشنده',
        'sent-sms': 'پیام های ارسال شده',
        'send-test-sms': 'ارسال پیامک تست',
        'sms-pattern-management': 'الگوهای پیامک',
        'login-register': 'ورود و عضویت'
    };

    function setActiveTab(tab, $link) {
        $('.limosms-sidebar li').removeClass('active');

        if ($link && $link.length) {
            $link.parent().addClass('active');
            return;
        }

        $('.limosms-tab-link').each(function () {
            if ($(this).data('tab') === tab) {
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

    function fetchTabContent(tab, href, $link, callback) {
        $('#limosms-tab-loading').css('display', 'flex').hide().fadeIn(150);
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
                if (!response || response === '-1') {
                    $('#limosms-tab-content').html(
                        '<p style="padding:20px;color:red;">خطا در اعتبارسنجی درخواست. لطفاً صفحه را رفرش کنید.</p>'
                    );
                    return;
                }

                $('#limosms-tab-content').html(response);
                setActiveTab(tab, $link);
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

    $('.limosms-sidebar').on('click', '.limosms-tab-link', function (event) {
        event.preventDefault();

        const $link = $(this);
        const tab = $link.data('tab');
        const href = $link.attr('href') || '';

        if (!tab) {
            return;
        }

        fetchTabContent(tab, href, $link, function () {
            window.LimoSMS.triggerTabLoaded(tab);
        });
    });

    const urlParams = new URLSearchParams(window.location.search);
    const currentTab = urlParams.get('tab') || 'connection-settings';

    setActiveTab(currentTab);
    setPageTitle(currentTab);
    window.LimoSMS.triggerTabLoaded(currentTab);

    window.addEventListener('popstate', function () {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab') || 'connection-settings';

        fetchTabContent(tab, null, null, function () {
            window.LimoSMS.triggerTabLoaded(tab);
        });
    });
});
