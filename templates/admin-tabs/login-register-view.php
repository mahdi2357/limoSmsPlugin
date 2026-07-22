<?php
$settings   = get_option( 'limoo_sms_settings', array() );
$is_enabled = ! empty( $settings['login_register_otp_enabled'] ) && '1' === (string) $settings['login_register_otp_enabled'];

$redirect_url  = isset( $settings['login_register_otp_redirect_url'] ) ? $settings['login_register_otp_redirect_url'] : '';
$default_role  = get_option( 'default_role', 'subscriber' );
$selected_role = isset( $settings['login_register_otp_role'] ) ? $settings['login_register_otp_role'] : $default_role;
$expiry_minutes = ! empty( $settings['login_register_otp_expiry_minutes'] ) ? absint( $settings['login_register_otp_expiry_minutes'] ) : 10;
$resend_seconds = ! empty( $settings['login_register_otp_resend_seconds'] ) ? absint( $settings['login_register_otp_resend_seconds'] ) : 60;
$max_attempts = ! empty( $settings['login_register_otp_max_attempts'] ) ? absint( $settings['login_register_otp_max_attempts'] ) : 5;
$lockout_minutes = ! empty( $settings['login_register_otp_lockout_minutes'] ) ? absint( $settings['login_register_otp_lockout_minutes'] ) : 15;
$roles = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array();
?>

<div class="limoo-login-register-card">
    <div class="limoo-login-register-card__header">
        <div>
            <h2 class="limoo-login-register-card__title">
                <?php esc_html_e( 'ورود و عضویت با کد تایید', 'limoo-sms' ); ?>
            </h2>
            <p class="limoo-login-register-card__subtitle">
                <?php esc_html_e( 'کاربران با کد یکبار مصرف وارد یا ثبت نام مي شوند.', 'limoo-sms' ); ?>
            </p>
        </div>
    </div>

    <form id="limoo-login-register-form" class="limoo-login-register-form">
        <div class="limoo-setting-row">
            <div class="limoo-setting-row__content">
                <label for="limoo-login-register-otp-enabled" class="limoo-setting-row__label">
                    <?php esc_html_e( 'فعال سازي ورود با OTP', 'limoo-sms' ); ?>
                </label>
                <p class="limoo-setting-row__description">
                    <?php esc_html_e( 'در صورت فعال بودن، ورود و عضويت با کد پيامکي انجام مي شود.', 'limoo-sms' ); ?>
                </p>
            </div>

            <label class="limoo-switch" for="limoo-login-register-otp-enabled">
                <input
                        type="checkbox"
                        id="limoo-login-register-otp-enabled"
                        name="login_register_otp_enabled"
                        value="1"
                        <?php checked( $is_enabled ); ?>
                />
                <span class="limoo-switch__slider"></span>
            </label>
        </div>

        <div
                id="limoo-otp-settings"
                class="limoo-otp-settings <?php echo $is_enabled ? 'is-visible' : ''; ?>"
                <?php echo $is_enabled ? '' : 'hidden'; ?>
        >
            <div class="limoo-setting-row">
                <div class="limoo-setting-row__content">
                    <label for="limoo-login-register-redirect-url" class="limoo-setting-row__label">
                        <?php esc_html_e( 'آدرس بازگشت پس از ورود', 'limoo-sms' ); ?>
                    </label>
                    <p class="limoo-setting-row__description">
                        <?php esc_html_e( 'آدرس صفحه‌ای را وارد کنید که پس از ورود یا ثبت نام با موفقیت به آن هدایت شود. خالی بگذارید تا به صفحه اصلی برود.', 'limoo-sms' ); ?>
                    </p>
                </div>
                <input
                        type="text"
                        id="limoo-login-register-redirect-url"
                        name="login_register_otp_redirect_url"
                        value="<?php echo esc_attr( $redirect_url ); ?>"
                        class="limoo-setting-row__input"
                        placeholder="<?php esc_attr_e( '/my-account', 'limo-sms' ); ?>"
                />
            </div>

            <div class="limoo-setting-row">
                <div class="limoo-setting-row__content">
                    <label for="limoo-login-register-role" class="limoo-setting-row__label">
                        <?php esc_html_e( 'نقش کاربر جدید', 'limo-sms' ); ?>
                    </label>
                    <p class="limoo-setting-row__description">
                        <?php esc_html_e( 'نقشی را که برای کاربران جدید ایجاد می‌شود انتخاب کنید.', 'limo-sms' ); ?>
                    </p>
                </div>
                <select id="limoo-login-register-role" name="login_register_otp_role" class="limoo-setting-row__input">
                    <?php foreach ( $roles as $role_key => $role_data ) : ?>
                        <option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $selected_role, $role_key ); ?>>
                            <?php echo esc_html( $role_data['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="limoo-setting-row">
                <div class="limoo-setting-row__content">
                    <label for="limoo-login-register-expiry" class="limoo-setting-row__label">
                        <?php esc_html_e( 'مدت اعتبار کد (دقیقه)', 'limo-sms' ); ?>
                    </label>
                    <p class="limoo-setting-row__description">
                        <?php esc_html_e( 'مدت زمان معتبر بودن کد تایید پس از ارسال.', 'limo-sms' ); ?>
                    </p>
                </div>
                <input
                        type="number"
                        min="1"
                        id="limoo-login-register-expiry"
                        name="login_register_otp_expiry_minutes"
                        value="<?php echo esc_attr( $expiry_minutes ); ?>"
                        class="limoo-setting-row__input"
                />
            </div>

            <div class="limoo-setting-row">
                <div class="limoo-setting-row__content">
                    <label for="limoo-login-register-resend" class="limoo-setting-row__label">
                        <?php esc_html_e( 'فاصله ارسال مجدد (ثانیه)', 'limo-sms' ); ?>
                    </label>
                    <p class="limoo-setting-row__description">
                        <?php esc_html_e( 'حداقل فاصله بین ارسال‌های دوباره کد به یک شماره تلفن.', 'limo-sms' ); ?>
                    </p>
                </div>
                <input
                        type="number"
                        min="10"
                        id="limoo-login-register-resend"
                        name="login_register_otp_resend_seconds"
                        value="<?php echo esc_attr( $resend_seconds ); ?>"
                        class="limoo-setting-row__input"
                />
            </div>

            <div class="limoo-setting-row">
                <div class="limoo-setting-row__content">
                    <label for="limoo-login-register-max-attempts" class="limoo-setting-row__label">
                        <?php esc_html_e( 'حداکثر تلاش‌های ناموفق', 'limo-sms' ); ?>
                    </label>
                    <p class="limoo-setting-row__description">
                        <?php esc_html_e( 'بعد از این تعداد تلاش ناموفق، کاربر باید دوباره کد دریافت کند.', 'limo-sms' ); ?>
                    </p>
                </div>
                <input
                        type="number"
                        min="1"
                        id="limoo-login-register-max-attempts"
                        name="login_register_otp_max_attempts"
                        value="<?php echo esc_attr( $max_attempts ); ?>"
                        class="limoo-setting-row__input"
                />
            </div>

            <div class="limoo-setting-row">
                <div class="limoo-setting-row__content">
                    <label for="limoo-login-register-lockout" class="limoo-setting-row__label">
                        <?php esc_html_e( 'قفل پس از شکست', 'limo-sms' ); ?>
                    </label>
                    <p class="limoo-setting-row__description">
                        <?php esc_html_e( 'مدت زمان قفل شدن ارسال/اعتبارسنجی پس از تلاش‌های ناموفق.', 'limo-sms' ); ?>
                    </p>
                </div>
                <input
                        type="number"
                        min="1"
                        id="limoo-login-register-lockout"
                        name="login_register_otp_lockout_minutes"
                        value="<?php echo esc_attr( $lockout_minutes ); ?>"
                        class="limoo-setting-row__input"
                />
            </div>
        </div>

        <div
                id="limoo-otp-shortcode-notice"
                class="limoo-otp-shortcode-notice <?php echo $is_enabled ? 'is-visible' : ''; ?>"
                <?php echo $is_enabled ? '' : 'hidden'; ?>
        >
            <div class="limoo-otp-shortcode-notice__icon" aria-hidden="true">✓</div>

            <div class="limoo-otp-shortcode-notice__content">
                <strong><?php esc_html_e( 'ورود و ثبت نام با پيامک فعال است.', 'limoo-sms' ); ?></strong>

                <p class="limoo-otp-shortcode-notice__description">
                    <?php esc_html_e( 'اين شورت کد را داخل برگه ورود، عضويت يا هر برگه دلخواه قرار دهيد.', 'limoo-sms' ); ?>
                </p>

                <div class="limoo-otp-shortcode-notice__shortcode-row">
                    <code
                            id="limoo-otp-shortcode-value"
                            class="limoo-otp-shortcode-notice__shortcode"
                            role="button"
                            tabindex="0"
                            data-shortcode="[limo_sms_auth]"
                            data-copied-text="<?php echo esc_attr__( 'کپی شد', 'limoo-sms' ); ?>"
                            title="<?php echo esc_attr__( 'برای کپی کلیک کنید', 'limoo-sms' ); ?>"
                            aria-label="<?php echo esc_attr__( 'برای کپی شورت‌کد کلیک کنید', 'limoo-sms' ); ?>"
                    >[limo_sms_auth]</code>

                </div>
            </div>
        </div>

        <div class="limoo-login-register-form__actions">
            <button type="submit" class="button button-primary" id="limoo-login-register-save">
                <?php esc_html_e( 'ذخيره تنظيمات', 'limoo-sms' ); ?>
            </button>
        </div>
    </form>
</div>
