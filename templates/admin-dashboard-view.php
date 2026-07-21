<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$menu_items = array(
        'connection-settings'    => array(
                'label' => 'تنظیمات اتصال',
                'icon'  => 'admin-settings',
        ),
        'admin-sms'              => array(
                'label' => 'پیامک مدیر',
                'icon'  => 'admin-users',
        ),
        'customer-sms'           => array(
                'label' => 'پیامک مشتری',
                'icon'  => 'businessperson',
        ),
        'seller-sms'             => array(
                'label' => 'پیامک فروشنده',
                'icon'  => 'businessman',
        ),
        'sent-sms'               => array(
                'label' => 'پیام های ارسال شده',
                'icon'  => 'email-alt',
        ),
        'send-test-sms'          => array(
                'label' => 'ارسال پیامک تست',
                'icon'  => 'email-alt',
        ),
        'sms-pattern-management' => array(
                'label' => 'الگوهای پیامک',
                'icon'  => 'text',
        ),
        'login-register' => array(
                'label' => 'ورود و عضویت',
                'icon'  => 'unlock',
        ),
);

$tab_titles = array(
        'connection-settings'    => 'تنظیمات اتصال',
        'admin-sms'              => 'پیامک مدیر',
        'customer-sms'           => 'پیامک مشتری',
        'seller-sms'             => 'پیامک فروشنده',
        'sent-sms'               => 'پیام های ارسال شده',
        'send-test-sms'          => 'ارسال پیامک تست',
        'sms-pattern-management' => 'الگوهای پیامک',
        'login-register'         => 'ورود و عضویت',
);

$active_tab = isset( $active_tab ) && ! empty( $active_tab )
        ? sanitize_key( $active_tab )
        : 'connection-settings';

if ( ! array_key_exists( $active_tab, $menu_items ) ) {
    $active_tab = 'connection-settings';
}

$tab_file = LIMOSMS_PATH . 'templates/admin-tabs/' . $active_tab . '-view.php';
?>

<div class="limosms-wrapper">
    <div class="limosms-sidebar">
        <a class="limosms-logo-link" target="_blank" href="https://panel.limosms.com/" rel="noopener noreferrer">
            <img class="limosms-logo" src="<?php echo esc_url( LIMOSMS_URL . 'assets/images/logo.png' ); ?>"
                alt="<?php echo esc_attr__( 'LimoSMS Logo', 'limosms' ); ?>">
        </a>

        <ul>
            <?php foreach ( $menu_items as $key => $item ) : ?>
            <li class="<?php echo esc_attr( $active_tab === $key ? 'active' : '' ); ?>">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=limosms&tab=' . $key ) ); ?>"
                    class="limosms-tab-link" data-tab="<?php echo esc_attr( $key ); ?>">
                    <span class="dashicons dashicons-<?php echo esc_attr( $item['icon'] ); ?>"></span>
                    <?php echo esc_html( $item['label'] ); ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="limosms-content">
        <div class="content-header">
            <h1 id="limosms-page-title">
                <?php echo esc_html( $tab_titles[ $active_tab ] ?? 'به لیمو SMS خوش آمدید' ); ?>
            </h1>
        </div>

        <div class="limosms-content-box">
            <div id="limosms-tab-loading" style="display: none;">
                <div class="limosms-spinner"></div>
                <span><?php esc_html_e( 'در حال بارگذاری...', 'limosms' ); ?></span>
            </div>

            <div class="card-body" id="limosms-tab-content">
                <?php
                if ( file_exists( $tab_file ) ) {
                    include $tab_file;
                } else {
                    echo '<h2>' . esc_html__( 'به لیمو SMS خوش آمدید', 'limosms' ) . '</h2>';
                    echo '<p>' . esc_html__( 'فایل تب موردنظر پیدا نشد.', 'limosms' ) . '</p>';
                }
                ?>
            </div>
        </div>
    </div>

    <div class="advertising-area">
        <a href="https://panel.limosms.com/bulk/Index" target="_blank" class="no-focus-outline"
            rel="noopener noreferrer">
            <img class="advertising-img" src="https://limosms.com/wp-content/uploads/2025/05/300-x-250.gif"
                alt="<?php echo esc_attr__( 'LimoSMS Banner', 'limosms' ); ?>">
        </a>
        <br>

        <div class="limosms-slider-container">
            <div class="limosms-slider-wrapper">
                <a href="https://panel.limosms.com/VoiceSms/SendVoiceMessage" target="_blank"
                    class="limosms-slide active" rel="noopener noreferrer">
                    <img class="advertising-img"
                        src="<?php echo esc_url( LIMOSMS_URL . 'assets/images/banner.webp' ); ?>"
                        alt="<?php echo esc_attr__( 'LimoSMS Voice Banner', 'limosms' ); ?>">
                </a>

                <a href="https://panel.limosms.com/Payment/PayWithBank" target="_blank" class="limosms-slide"
                    rel="noopener noreferrer">
                    <img class="advertising-img"
                        src="<?php echo esc_url( LIMOSMS_URL . 'assets/images/banner1.webp' ); ?>"
                        alt="<?php echo esc_attr__( 'LimoSMS SMS Banner', 'limosms' ); ?>">
                </a>

                <a href="https://panel.limosms.com/User/ContactUs" target="_blank" class="limosms-slide"
                    rel="noopener noreferrer">
                    <img class="advertising-img"
                        src="<?php echo esc_url( LIMOSMS_URL . 'assets/images/banner2.png' ); ?>"
                        alt="<?php echo esc_attr__( 'LimoSMS OTP Banner', 'limosms' ); ?>">
                </a>
            </div>
        </div>


    </div>

    <a href="https://panel.limosms.com/Ticket/GetUserTicket" target="_blank" class="limosms-support-btn"
        rel="noopener noreferrer">
        <span class="limosms-support-icon">💬</span>
        ارتباط با کارشناسان
    </a>
</div>