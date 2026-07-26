(function ($) {
    'use strict';

    function parsePhonesRaw(raw) {
        if (!raw) return [];
        return String(raw).split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    }

    function renderTags($container, phones) {
        $container.empty();
        phones.forEach(function (p) {
            const $chip = $('<div class="limosms-admin-phone-chip" data-phone="' + p + '"></div>');
            $chip.append($('<span class="limosms-phone-value"></span>').text(p));
            $chip.append($('<button type="button" class="limosms-remove-gravity-forms-admin-phone" aria-label="حذف">×</button>'));
            $container.append($chip);
        });
    }

    function updateHidden($hidden, phones) {
        $hidden.val(phones.join(','));
        $hidden.trigger('input');
    }

    $(document).ready(function () {
        const $entry = $('#limosms_gravity_forms_admin_phone_entry');
        const $addBtn = $('#limosms-add-gravity-forms-admin-phone');
        const $hidden = $('#limosms_gravity_forms_admin_phones');
        const $tags = $('#limosms-gravity-forms-admin-phones-tags');

        if (!$hidden.length || !$tags.length || !$entry.length) return;

        let phones = parsePhonesRaw($hidden.val());
        renderTags($tags, phones);

        function addPhone(raw) {
            const value = String(raw || '').trim();
            if (!value) return;
            // normalize Persian/Arabic digits to english
            const normalized = value.replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); });
            if (!/^09\d{9}$/.test(normalized)) {
                $entry.addClass('limosms-input-error');
                setTimeout(function () { $entry.removeClass('limosms-input-error'); }, 1200);
                return;
            }

            if (phones.indexOf(normalized) === -1) {
                phones.push(normalized);
                renderTags($tags, phones);
                updateHidden($hidden, phones);
            }
            $entry.val('');
        }

        $addBtn.on('click', function (e) {
            e.preventDefault();
            addPhone($entry.val());
        });

        $entry.on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                addPhone($entry.val());
            }
        });

        $tags.on('click', '.limosms-remove-gravity-forms-admin-phone', function (e) {
            e.preventDefault();
            const $chip = $(this).closest('.limosms-admin-phone-chip');
            const val = $chip.data('phone');
            phones = phones.filter(function (p) { return p !== val; });
            renderTags($tags, phones);
            updateHidden($hidden, phones);
        });
    });

})(jQuery);
