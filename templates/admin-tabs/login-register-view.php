<?php
$settings   = get_option( 'limoo_sms_settings', array() );
$is_enabled = ! empty( $settings['login_register_otp_enabled'] ) && '1' === (string) $settings['login_register_otp_enabled'];
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
