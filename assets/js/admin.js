jQuery(document).ready(function ($) {
    'use strict';

    if (typeof limosms_ajax === 'undefined') {
        if (window.LimoSMS && typeof window.LimoSMS.showToast === 'function') {
            window.LimoSMS.showToast('AJAX configuration missing for LimoSMS admin.', 'error');
        }
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

    function normalizeToastMessage(message, fallback) {
        if (message === null || message === undefined || message === '') {
            return fallback;
        }

        if (typeof message === 'string') {
            const trimmed = message.trim();
            return trimmed || fallback;
        }

        if (typeof message === 'object') {
            if (typeof message.message === 'string' && message.message.trim()) {
                return message.message.trim();
            }

            if (typeof message.error === 'string' && message.error.trim()) {
                return message.error.trim();
            }

            if (Array.isArray(message)) {
                return message.join(' ');
            }

            try {
                return JSON.stringify(message);
            } catch (error) {
                return fallback;
            }
        }

        return String(message);
    }

    window.LimoSMS.showToast = function (message, type, duration) {
        type = type || 'success';
        duration = duration || 4000;
        const fallbackMessage = type === 'error' ? 'عملیات با خطا مواجه شد.' : 'عملیات با موفقیت انجام شد.';
        const safeMessage = normalizeToastMessage(message, fallbackMessage);

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

        const messageNode = toast.querySelector('.limosms-toast__message');
        if (messageNode) {
            messageNode.textContent = safeMessage;
            messageNode.setAttribute('title', safeMessage);
        }
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

    // limosms_connection_status is output by PHP (true/false)
    function isConnectionAvailable() {
        return (typeof limosms_connection_status !== 'undefined') ? Boolean(limosms_connection_status) : true;
    }

    // connection availability logged suppressed in production

    setActiveTab(currentTab);
    setPageTitle(currentTab);

    // if connection is not available, ensure user stays on connection-settings and show overlay
    if (!isConnectionAvailable()) {
        if (currentTab !== 'connection-settings') {
            const $link = $('.limosms-tab-link[data-tab="connection-settings"]');
            fetchTabContent('connection-settings', $link.attr('href') || '', $link, function () {
                window.LimoSMS.triggerTabLoaded('connection-settings');
                // don't auto-show overlay on load; show lightweight toast when user interacts
            });
        } else {
            window.LimoSMS.triggerTabLoaded(currentTab);
            // don't auto-show overlay on initial load
        }
    } else {
        window.LimoSMS.triggerTabLoaded(currentTab);
    }

    window.addEventListener('popstate', function () {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab') || 'connection-settings';

        if (!isConnectionAvailable() && tab !== 'connection-settings') {
            // show toast instead of forcing overlay when user navigates via history
            if (window.LimoSMS && typeof window.LimoSMS.showToast === 'function') {
                window.LimoSMS.showToast('لطفا ابتدا کلید api را تنظیم کنید. جهت دریافت کلید api به پنل لیمو اس ام اس بخش دریافت کد دسترسی در منوی ویژه برنامه نویسان مراجعه فرمایید', 'error', 6000);
            }
            return;
        }

        fetchTabContent(tab, null, null, function () {
            window.LimoSMS.triggerTabLoaded(tab);
        });
    });

    // Override click handler to prevent navigation when disconnected
    $('.limosms-sidebar').off('click', '.limosms-tab-link');
    $('.limosms-sidebar').on('click', '.limosms-tab-link', function (event) {
        event.preventDefault();

        const $link = $(this);
        const tab = $link.data('tab');
        const href = $link.attr('href') || '';

        if (!tab) {
            return;
        }

        // tab click debug log suppressed in production

        if (!isConnectionAvailable() && tab !== 'connection-settings') {
            if (window.LimoSMS && typeof window.LimoSMS.showToast === 'function') {
                window.LimoSMS.showToast('لطفا ابتدا کلید api را تنظیم کنید. جهت دریافت کلید api به پنل لیمو اس ام اس بخش دریافت کد دسترسی در منوی ویژه برنامه نویسان مراجعه فرمایید', 'error', 6000);
            }
            return;
        }

        fetchTabContent(tab, href, $link, function () {
            window.LimoSMS.triggerTabLoaded(tab);
        });
    });

    function showConnectionRequiredOverlay() {
        if (document.querySelector('.limosms-connection-required-overlay')) {
            return;
        }

        const overlay = document.createElement('div');
        overlay.className = 'limosms-connection-required-overlay';

        const card = document.createElement('div');
        card.className = 'limosms-connection-required-card';

        const closeBtn = document.createElement('button');
        closeBtn.className = 'limosms-connection-required-close';
        closeBtn.innerHTML = '✕';
        closeBtn.addEventListener('click', function () {
            overlay.remove();
        });

        const title = document.createElement('h3');
        title.textContent = 'تنظیم کلید API الزامی است';

        const para = document.createElement('p');
        para.innerHTML = 'لطفا ابتدا کلید api را تنظیم کنید. جهت دریافت کلید api به پنل لیمو اس ام اس بخش دریافت کد دسترسی در منوی ویژه برنامه نویسان مراجعه فرمایید <a href="https://panel.limosms.com/Programmer/ApiAccess" target="_blank" rel="noopener noreferrer">مشاهده صفحه دریافت کلید API</a>';

        const actions = document.createElement('div');
        actions.className = 'limosms-connection-actions';

        const goToConnection = document.createElement('button');
        goToConnection.className = 'button button-primary';
        goToConnection.textContent = 'رفتن به تنظیمات اتصال';
        goToConnection.addEventListener('click', function () {
            const $link = $('.limosms-tab-link[data-tab="connection-settings"]');
            fetchTabContent('connection-settings', $link.attr('href') || '', $link, function () {
                window.LimoSMS.triggerTabLoaded('connection-settings');
                overlay.remove();
            });
        });

        const closeSecondary = document.createElement('button');
        closeSecondary.className = 'button';
        closeSecondary.textContent = 'بستن';
        closeSecondary.addEventListener('click', function () {
            overlay.remove();
        });

        actions.appendChild(closeSecondary);
        actions.appendChild(goToConnection);

        card.appendChild(closeBtn);
        card.appendChild(title);
        card.appendChild(para);
        card.appendChild(actions);

        overlay.appendChild(card);
        document.body.appendChild(overlay);
    }

    // expose helper to console for debugging
    if (window && window.LimoSMS) {
        window.LimoSMS.showConnectionRequiredOverlay = showConnectionRequiredOverlay;
    }

// slider banner
    const slider = document.querySelector('.limosms-slider-wrapper');

    if (!slider) {
        return;
    }

    const slides = slider.querySelectorAll('.limosms-slide');

    if (slides.length < 2) {
        return;
    }

    let currentSlide = 0;

    setInterval(function () {
        slides[currentSlide].classList.remove('active');
        currentSlide = (currentSlide + 1) % slides.length;
        slides[currentSlide].classList.add('active');
    }, 5000);

});
