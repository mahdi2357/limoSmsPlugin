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
                <?php esc_html_e( 'کاربران با کد یکبار مصرف وارد یا ثبت‌نام می‌کنند.', 'limoo-sms' ); ?>
            </p>
        </div>
    </div>

    <form id="limoo-login-register-form" class="limoo-login-register-form">
        <div class="limoo-setting-row">
            <div class="limoo-setting-row__content">
                <label for="limoo-login-register-otp-enabled" class="limoo-setting-row__label">
                    <?php esc_html_e( 'فعال‌سازی ورود با OTP', 'limoo-sms' ); ?>
                </label>
                <p class="limoo-setting-row__description">
                    <?php esc_html_e( 'در صورت فعال بودن، ورود و عضویت با کد پیامکی انجام می‌شود.', 'limoo-sms' ); ?>
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

        <div class="limoo-login-register-form__actions">
            <button type="submit" class="button button-primary" id="limoo-login-register-save">
                <?php esc_html_e( 'ذخیره تنظیمات', 'limoo-sms' ); ?>
            </button>
        </div>
    </form>
</div>
