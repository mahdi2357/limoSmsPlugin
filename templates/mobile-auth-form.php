<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="limosms-mobile-auth">
    <div class="limosms-mobile-auth__wrap">
        <?php if ( ! empty( $title ) ) : ?>
            <h2 class="limosms-mobile-auth__title"><?php echo esc_html( $title ); ?></h2>
        <?php endif; ?>

        <div class="limosms-mobile-auth__message" aria-live="polite"></div>

        <form class="limosms-mobile-auth__form" method="post" action="">
            <div class="limosms-mobile-auth__step limosms-mobile-auth__step--mobile is-active" data-step="mobile">
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
