<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="limosms-mobile-auth limosms-mobile-auth--logged-in">
    <div class="limosms-mobile-auth__wrap">
        <h2 class="limosms-mobile-auth__title">
            <?php esc_html_e( 'شما وارد حساب کاربری خود شده‌اید', 'limosms' ); ?>
        </h2>

        <div class="limosms-mobile-auth__message is-success">
            <?php
            echo esc_html(
                sprintf(
                    __( 'خوش آمدید %s', 'limosms' ),
                    wp_get_current_user()->display_name
                )
            );
            ?>
        </div>

        <a class="limosms-mobile-auth__button limosms-mobile-auth__button--primary" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
            <?php esc_html_e( '  حساب کاربری', 'limosms' ); ?>
        </a>
    </div>
</div>
