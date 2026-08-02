<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="limosms-mobile-auth limosms-mobile-auth--align-<?php echo esc_attr( $form_style['form_align'] ); ?> limosms-mobile-auth--dir-<?php echo esc_attr( $form_style['form_direction'] ); ?>"
    dir="<?php echo esc_attr( $form_style['form_direction'] ); ?>"
    style="background-color:<?php echo esc_attr( $form_style['background_color'] ); ?>;font-family:<?php echo esc_attr( $form_style['font_family'] ); ?>;<?php echo ! empty( $form_style['background_image_url'] ) ? 'background-image:url(' . esc_url( $form_style['background_image_url'] ) . ');' : ''; ?>">
    <div class="limosms-mobile-auth__wrap"
        style="background-color:<?php echo esc_attr( $form_style['form_background_color'] ); ?>;--limosms-accent-color:<?php echo esc_attr( $form_style['accent_color'] ); ?>;">
        <?php if ( ! empty( $form_style['logo_url'] ) ) : ?>
        <div class="limosms-mobile-auth__logo">
            <img class="logo-size" src="<?php echo esc_url( $form_style['logo_url'] ); ?>"
                alt="<?php esc_attr_e( 'لوگو', 'limosms' ); ?>" />
        </div>
        <?php endif; ?>
        <?php if ( ! empty( $title ) ) : ?>
        <h2 class="limosms-mobile-auth__title"><?php echo esc_html( $title ); ?></h2>
        <?php endif; ?>

        <div class="limosms-mobile-auth__subtitle">
            <?php esc_html_e( 'ورود و ثبت‌نام سریع و امن با کد تأیید', 'limosms' ); ?>
        </div>

        <div class="limosms-mobile-auth__message" aria-live="polite"></div>

        <div class="limosms-mobile-auth__tab-switch" role="tablist" aria-label="روش ورود">
            <button type="button" class="limosms-mobile-auth__tab-button is-active" data-auth-tab="otp">
                <?php esc_html_e( 'ورود با کد', 'limosms' ); ?>
            </button>
            <button type="button" class="limosms-mobile-auth__tab-button" data-auth-tab="password">
                <?php esc_html_e( 'ورود با رمز عبور', 'limosms' ); ?>
            </button>
            <button type="button" class="limosms-mobile-auth__tab-button" data-auth-tab="reset">
                <?php esc_html_e( 'بازیابی رمز عبور', 'limosms' ); ?>
            </button>
        </div>

        <?php if ( ! empty( $form_style['custom_css'] ) ) : ?>
        <style>
        <?php echo $form_style['custom_css'];
        ?>
        </style>
        <?php endif; ?>

        <form class="limosms-mobile-auth__form" method="post" action="">
            <div class="limosms-mobile-auth__panel limosms-mobile-auth__panel--active" data-auth-panel="otp">
                <div class="limosms-mobile-auth__step limosms-mobile-auth__step--mobile is-active" data-step="mobile">
                    <div class="limosms-mobile-auth__mode-switch" role="tablist" aria-label="حالت ورود">
                        <button type="button" class="limosms-mobile-auth__mode-button is-active" data-mode="login">
                            <?php esc_html_e( 'ورود', 'limosms' ); ?>
                        </button>
                        <button type="button" class="limosms-mobile-auth__mode-button" data-mode="register">
                            <?php esc_html_e( 'ثبت نام', 'limosms' ); ?>
                        </button>
                    </div>

                    <input type="hidden" id="limosms_auth_mode" name="mode" value="login" />

                    <div class="limosms-mobile-auth__phone-row">
                        <div class="limosms-mobile-auth__field limosms-mobile-auth__field--country-code">
                            <label for="limosms_country_code"><?php esc_html_e( 'کد کشور', 'limosms' ); ?></label>
                            <input type="text" id="limosms_country_code" name="country_code"
                                class="limosms-mobile-auth__input"
                                placeholder="<?php esc_attr_e( '+98', 'limosms' ); ?>" value="+98" inputmode="numeric"
                                autocomplete="tel-country-code" dir="ltr" />
                        </div>

                        <div class="limosms-mobile-auth__field limosms-mobile-auth__field--mobile">
                            <label
                                for="limosms_mobile"><?php esc_html_e( 'شماره موبایل خود را وارد کنید', 'limosms' ); ?></label>
                            <input type="tel" id="limosms_mobile" name="mobile" class="limosms-mobile-auth__input"
                                placeholder="<?php esc_attr_e( '9123456789', 'limosms' ); ?>" inputmode="numeric"
                                autocomplete="tel" dir="ltr" />
                        </div>
                    </div>

                    <div id="limosms_register_fields" class="limosms-mobile-auth__register-fields is-hidden" hidden
                        aria-hidden="true">
                        <?php if ( ! empty( $registration_fields ) ) : ?>
                        <?php foreach ( $registration_fields as $field ) : ?>
                        <?php $field_key = isset( $field['key'] ) ? $field['key'] : ''; ?>
                        <?php if ( '' === $field_key ) continue; ?>
                        <div class="limosms-mobile-auth__field">
                            <label
                                for="limosms_registration_<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $field['label'] ); ?><?php echo ! empty( $field['required'] ) ? ' *' : ''; ?></label>
                            <input type="<?php echo esc_attr( $field['type'] ); ?>"
                                id="limosms_registration_<?php echo esc_attr( $field_key ); ?>"
                                name="registration_fields[<?php echo esc_attr( $field_key ); ?>]"
                                class="limosms-mobile-auth__input limosms-mobile-auth__registration-field"
                                placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
                                data-required="<?php echo ! empty( $field['required'] ) ? '1' : '0'; ?>"
                                <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?> autocomplete="off" />
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ( ! empty( $captcha['enabled'] ) ) : ?>
                    <div class="limosms-mobile-auth__field">
                        <label for="limosms_captcha"><?php esc_html_e( 'کپچا', 'limosms' ); ?></label>
                        <div class="limosms-mobile-auth__captcha-row">
                            <div class="limosms-mobile-auth__captcha-question">
                                <?php echo esc_html( $captcha['question'] ); ?></div>
                            <button type="button"
                                class="limosms-mobile-auth__button limosms-mobile-auth__button--secondary"
                                id="limosms-refresh-captcha">
                                <?php esc_html_e( 'تازه‌سازی کپچا', 'limosms' ); ?>
                            </button>
                        </div>
                        <input type="text" id="limosms_captcha" name="captcha_answer" class="limosms-mobile-auth__input"
                            placeholder="<?php esc_attr_e( 'جواب را وارد کنید', 'limosms' ); ?>" autocomplete="off"
                            dir="ltr" />
                        <input type="hidden" id="limosms_captcha_token" name="captcha_token"
                            value="<?php echo esc_attr( $captcha['token'] ); ?>" />
                    </div>
                    <?php endif; ?>

                    <button type="button" class="limosms-mobile-auth__button limosms-mobile-auth__button--primary"
                        id="limosms-send-code">
                        <?php esc_html_e( 'دریافت کد تایید', 'limosms' ); ?>
                    </button>
                </div>

                <div class="limosms-mobile-auth__step limosms-mobile-auth__step--code" data-step="code" hidden>
                    <div class="limosms-mobile-auth__field">
                        <label for="limosms_code"><?php esc_html_e( 'کد دریافتی را وارد کنید', 'limosms' ); ?></label>
                        <input type="text" id="limosms_code" name="code" class="limosms-mobile-auth__input"
                            placeholder="<?php esc_attr_e( 'مثال: 123456', 'limosms' ); ?>" inputmode="numeric"
                            autocomplete="one-time-code" dir="ltr" />
                    </div>

                    <input type="hidden" id="limosms_mobile_confirm" name="mobile_confirm" value="" />

                    <div class="limosms-mobile-auth__actions">
                        <button type="button" class="limosms-mobile-auth__button limosms-mobile-auth__button--secondary"
                            id="limosms-edit-mobile">
                            <?php esc_html_e( 'ویرایش شماره', 'limosms' ); ?>
                        </button>

                        <button type="button" class="limosms-mobile-auth__button limosms-mobile-auth__button--primary"
                            id="limosms-verify-code">
                            <?php esc_html_e( 'ورود به حساب', 'limosms' ); ?>
                        </button>
                    </div>
                </div>

            </div>

            <div class="limosms-mobile-auth__panel" data-auth-panel="password">
                <div class="limosms-mobile-auth__field">
                    <label
                        for="limosms_identifier"><?php esc_html_e( 'شماره موبایل، ایمیل یا نام کاربری', 'limosms' ); ?></label>
                    <input type="text" id="limosms_identifier" name="identifier" class="limosms-mobile-auth__input"
                        placeholder="<?php esc_attr_e( 'مثال: 9123456789 یا example@email.com', 'limosms' ); ?>"
                        autocomplete="username" />
                </div>

                <div class="limosms-mobile-auth__field">
                    <label for="limosms_password"><?php esc_html_e( 'رمز عبور', 'limosms' ); ?></label>
                    <input type="password" id="limosms_password" name="password" class="limosms-mobile-auth__input"
                        placeholder="<?php esc_attr_e( 'رمز عبور خود را وارد کنید', 'limosms' ); ?>"
                        autocomplete="current-password" />
                </div>

                <label class="limosms-mobile-auth__checkbox">
                    <input type="checkbox" id="limosms_remember" name="remember" value="1" />
                    <span><?php esc_html_e( 'مرا به خاطر بسپار', 'limosms' ); ?></span>
                </label>

                <button type="button" class="limosms-mobile-auth__button limosms-mobile-auth__button--primary"
                    id="limosms-password-login">
                    <?php esc_html_e( 'ورود', 'limosms' ); ?>
                </button>
            </div>

            <div class="limosms-mobile-auth__panel" data-auth-panel="reset">
                <div class="limosms-mobile-auth__field">
                    <label
                        for="limosms_reset_mobile"><?php esc_html_e( 'شماره موبایل برای بازیابی رمز عبور', 'limosms' ); ?></label>
                    <input type="text" id="limosms_reset_mobile" name="reset_mobile" class="limosms-mobile-auth__input"
                        placeholder="<?php esc_attr_e( '9123456789', 'limosms' ); ?>" autocomplete="tel" />
                </div>

                <div class="limosms-mobile-auth__field limosms-mobile-auth__field--hidden" id="limosms_reset_code_field"
                    hidden>
                    <label for="limosms_reset_code"><?php esc_html_e( 'کد دریافتی', 'limosms' ); ?></label>
                    <input type="text" id="limosms_reset_code" name="reset_code" class="limosms-mobile-auth__input"
                        placeholder="<?php esc_attr_e( '123456', 'limosms' ); ?>" autocomplete="one-time-code" />
                </div>

                <div class="limosms-mobile-auth__field limosms-mobile-auth__field--hidden"
                    id="limosms_reset_password_field" hidden>
                    <label for="limosms_new_password"><?php esc_html_e( 'رمز عبور جدید', 'limosms' ); ?></label>
                    <input type="password" id="limosms_new_password" name="new_password"
                        class="limosms-mobile-auth__input"
                        placeholder="<?php esc_attr_e( 'حداقل 6 کاراکتر', 'limosms' ); ?>"
                        autocomplete="new-password" />
                </div>

                <div class="limosms-mobile-auth__field limosms-mobile-auth__field--hidden"
                    id="limosms_reset_confirm_field" hidden>
                    <label
                        for="limosms_confirm_password"><?php esc_html_e( 'تکرار رمز عبور جدید', 'limosms' ); ?></label>
                    <input type="password" id="limosms_confirm_password" name="confirm_password"
                        class="limosms-mobile-auth__input"
                        placeholder="<?php esc_attr_e( 'تکرار رمز عبور', 'limosms' ); ?>" autocomplete="new-password" />
                </div>

                <button type="button" class="limosms-mobile-auth__button limosms-mobile-auth__button--primary"
                    id="limosms-reset-request">
                    <?php esc_html_e( 'ارسال کد بازیابی', 'limosms' ); ?>
                </button>
                <button type="button" class="limosms-mobile-auth__button limosms-mobile-auth__button--secondary"
                    id="limosms-reset-confirm" hidden>
                    <?php esc_html_e( 'تغییر رمز عبور', 'limosms' ); ?>
                </button>
            </div>

            <?php wp_nonce_field( 'limosms_mobile_auth_nonce', 'limosms_mobile_auth_nonce' ); ?>
        </form>
    </div>
</div>