<div class="limosms-test-sms-card">
    <form id="limosms-send-form" class="limosms-test-sms-form" novalidate>
        <div class="limosms-form-grid">
            <div class="limosms-form-group limosms-form-group-full">
                <label for="limosms-reciver-number" class="limosms-label">
                    شماره گیرنده
                </label>
                <input
                        type="text"
                        id="limosms-reciver-number"
                        name="reciverNumber"
                        class="regular-text"
                        placeholder="مثلاً 09123456789"
                        maxlength="11"
                        inputmode="numeric"
                        autocomplete="off"
                        required
                >
                <p class="description">شماره موبایل باید با 09 شروع شود و دقیقاً 11 رقم باشد.</p>
                <div class="limosms-field-error" data-for="reciverNumber"></div>
            </div>

            <div class="limosms-form-group">
                <label for="limosms-pattern-id" class="limosms-label">
                    انتخاب پترن
                </label>
                <select
                        id="limosms-pattern-id"
                        name="patternId"
                        class="limosms-select"
                        required
                >
                    <option value="">در حال بارگذاری الگوها...</option>
                </select>
                <p class="description">پترن موردنظر برای ارسال پیامک تست را انتخاب کنید.</p>
                <div class="limosms-field-error" data-for="patternId"></div>
            </div>

            <div class="limosms-form-group">
                <label for="limosms-message" class="limosms-label">
                    متن پیام / توکن‌ها
                </label>
                <input
                        type="text"
                        id="limosms-message"
                        name="message"
                        class="regular-text"
                        placeholder="مثلاً: علی,09123456789"
                        maxlength="16"
                        autocomplete="off"
                >
                <p class="description">در صورت نیاز، مقادیر توکن‌ها را با کاما از هم جدا کنید.</p>
                <div class="limosms-field-error" data-for="message"></div>
            </div>
        </div>

        <div class="limosms-form-actions">
            <button type="submit" class="button button-primary" disabled>
                ارسال پیام تست
            </button>
        </div>
    </form>
</div>
