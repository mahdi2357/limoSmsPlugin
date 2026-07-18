<div class="limoo-sms-card">
    <h2><?php esc_html_e( 'تنظیمات ورود و ثبت نام', 'limoo-sms' ); ?></h2>

    <label for="limoo-login-register-otp-enabled">
        <input
                type="checkbox"
                id="limoo-login-register-otp-enabled"
                name="limoo_sms_settings[login_register_otp_enabled]"
                value="1"
                <?php checked( $settings['login_register_otp_enabled'], '1' ); ?>
        />
        <?php esc_html_e( 'فعالسازی / غیرفعالسازی ورود و عضویت با رمز یکبار مصرف', 'limoo-sms' ); ?>
    </label>

    <p class="description">
        <?php esc_html_e( 'اگر فعال باشد کاربر حتما باید با کد ارسالی به شماره موبایلش وارد سایت شود', 'limoo-sms' ); ?>
    </p>
</div>
