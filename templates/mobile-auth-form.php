<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div
    class="limosms-mobile-auth limosms-mobile-auth--align-<?php echo esc_attr( $form_style['form_align'] ); ?> limosms-mobile-auth--dir-<?php echo esc_attr( $form_style['form_direction'] ); ?>"
    dir="<?php echo esc_attr( $form_style['form_direction'] ); ?>"
    style="background-color:<?php echo esc_attr( $form_style['background_color'] ); ?>;<?php echo ! empty( $form_style['background_image_url'] ) ? 'background-image:url(' . esc_url( $form_style['background_image_url'] ) . ');' : ''; ?>"
>
    <div class="limosms-mobile-auth__wrap" style="background-color:<?php echo esc_attr( $form_style['form_background_color'] ); ?>;--limosms-accent-color:<?php echo esc_attr( $form_style['accent_color'] ); ?>;">
        <?php if ( ! empty( $form_style['logo_url'] ) ) : ?>
            <div class="limosms-mobile-auth__logo">
                <img src="<?php echo esc_url( $form_style['logo_url'] ); ?>" alt="<?php esc_attr_e( 'لوگو', 'limosms' ); ?>" />
            </div>
        <?php endif; ?>
        <?php if ( ! empty( $title ) ) : ?>
            <h2 class="limosms-mobile-auth__title"><?php echo esc_html( $title ); ?></h2>
        <?php endif; ?>

        <div class="limosms-mobile-auth__subtitle">
            <?php esc_html_e( 'ورود و ثبت‌نام سریع و امن با کد تأیید', 'limosms' ); ?>
        </div>

        <div class="limosms-mobile-auth__message" aria-live="polite"></div>

        <form class="limosms-mobile-auth__form" method="post" action="">
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

                <div class="limosms-mobile-auth__field">
                    <label for="limosms_mobile"><?php esc_html_e( 'شماره موبایل خود را وارد کنید', 'limosms' ); ?></label>
                    <input
                            type="tel"
                            id="limosms_mobile"
                            name="mobile"
                            class="limosms-mobile-auth__input"
                            placeholder="<?php esc_attr_e( '0912...', 'limosms' ); ?>"
                            inputmode="numeric"
                            autocomplete="tel"
                            dir="ltr"
                    />
                </div>

                <div id="limosms_register_fields" class="limosms-mobile-auth__register-fields is-hidden" hidden aria-hidden="true">
                    <?php if ( ! empty( $registration_fields ) ) : ?>
                        <?php foreach ( $registration_fields as $field ) : ?>
                            <?php $field_key = isset( $field['key'] ) ? $field['key'] : ''; ?>
                            <?php if ( '' === $field_key ) continue; ?>
                            <div class="limosms-mobile-auth__field">
                                <label for="limosms_registration_<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $field['label'] ); ?><?php echo ! empty( $field['required'] ) ? ' *' : ''; ?></label>
                                <input
                                        type="<?php echo esc_attr( $field['type'] ); ?>"
                                        id="limosms_registration_<?php echo esc_attr( $field_key ); ?>"
                                        name="registration_fields[<?php echo esc_attr( $field_key ); ?>]"
                                        class="limosms-mobile-auth__input limosms-mobile-auth__registration-field"
                                        placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
                                        data-required="<?php echo ! empty( $field['required'] ) ? '1' : '0'; ?>"
                                        <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
                                        autocomplete="off"
                                />
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ( ! empty( $captcha['enabled'] ) ) : ?>
                    <div class="limosms-mobile-auth__field">
                        <label for="limosms_captcha"><?php esc_html_e( 'کپچا', 'limosms' ); ?></label>
                        <div class="limosms-mobile-auth__captcha-row">
                            <div class="limosms-mobile-auth__captcha-question"><?php echo esc_html( $captcha['question'] ); ?></div>
                            <button type="button" class="limosms-mobile-auth__button limosms-mobile-auth__button--secondary" id="limosms-refresh-captcha">
                                <?php esc_html_e( 'تازه‌سازی کپچا', 'limosms' ); ?>
                            </button>
                        </div>
                        <input
                                type="text"
                                id="limosms_captcha"
                                name="captcha_answer"
                                class="limosms-mobile-auth__input"
                                placeholder="<?php esc_attr_e( 'جواب را وارد کنید', 'limosms' ); ?>"
                                autocomplete="off"
                                dir="ltr"
                        />
                        <input type="hidden" id="limosms_captcha_token" name="captcha_token" value="<?php echo esc_attr( $captcha['token'] ); ?>" />
                    </div>
                <?php endif; ?>

                <button
                        type="button"
                        class="limosms-mobile-auth__button limosms-mobile-auth__button--primary"
                        id="limosms-send-code"
                >
                    <?php esc_html_e( 'دریافت کد تایید', 'limosms' ); ?>
                </button>
            </div>

            <div class="limosms-mobile-auth__step limosms-mobile-auth__step--code" data-step="code" hidden>
                <div class="limosms-mobile-auth__field">
                    <label for="limosms_code"><?php esc_html_e( 'کد دریافتی را وارد کنید', 'limosms' ); ?></label>
                    <input
                            type="text"
                            id="limosms_code"
                            name="code"
                            class="limosms-mobile-auth__input"
                            placeholder="<?php esc_attr_e( 'مثال: 123456', 'limosms' ); ?>"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            dir="ltr"
                    />
                </div>

                <input type="hidden" id="limosms_mobile_confirm" name="mobile_confirm" value="" />

                <div class="limosms-mobile-auth__actions">
                    <button
                            type="button"
                            class="limosms-mobile-auth__button limosms-mobile-auth__button--secondary"
                            id="limosms-edit-mobile"
                    >
                        <?php esc_html_e( 'ویرایش شماره', 'limosms' ); ?>
                    </button>

                    <button
                            type="button"
                            class="limosms-mobile-auth__button limosms-mobile-auth__button--primary"
                            id="limosms-verify-code"
                    >
                        <?php esc_html_e( 'ورود به حساب', 'limosms' ); ?>
                    </button>
                </div>
            </div>

            <?php wp_nonce_field( 'limosms_mobile_auth_nonce', 'limosms_mobile_auth_nonce' ); ?>
        </form>
    </div>
</div>
