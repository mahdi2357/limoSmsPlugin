<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$woocommerce_sms_enabled = LimoSMS_Connection_Settings::normalize_enabled_setting( get_option('limosms_woocommerce_sms_enabled', 'yes') );
$digits_sms_enabled = LimoSMS_Connection_Settings::normalize_enabled_setting( get_option('limosms_digits_sms_enabled', 'yes') );
$gravity_forms_sms_enabled = LimoSMS_Connection_Settings::normalize_enabled_setting( get_option('limosms_gravity_forms_sms_enabled', 'yes') );

$menu_items = array(
        'connection-settings'    => array(
                'label' => 'تنظیمات اتصال',
                'icon'  => 'admin-settings',
        ),
        'admin-sms'              => array(
                'label' => 'پیامک مدیر',
                'icon'  => 'admin-users',
                'enabled' => $woocommerce_sms_enabled === 'yes',
        ),
        'customer-sms'           => array(
                'label' => 'پیامک مشتری',
                'icon'  => 'businessperson',
                'enabled' => $woocommerce_sms_enabled === 'yes',
        ),
        'seller-sms'             => array(
                'label' => 'پیامک فروشنده',
                'icon'  => 'businessman',
                'enabled' => $woocommerce_sms_enabled === 'yes',
        ),
        'gravity-forms-sms'      => array(
                'label' => 'پیامک فرم های Gravity',
                'icon'  => 'feedback',
                'enabled' => $gravity_forms_sms_enabled === 'yes',
        ),
        'sent-sms'               => array(
                'label' => 'پیام های ارسال شده',
                'icon'  => 'email-alt',
                'enabled' => true,
        ),
        'send-test-sms'          => array(
                'label' => 'ارسال پیامک تست',
                'icon'  => 'email-alt',
                'enabled' => true,
        ),
        'sms-pattern-management' => array(
                'label' => 'الگوهای پیامک',
                'icon'  => 'text',
                'enabled' => true,
        ),
        'login-register' => array(
                'label' => 'ورود و عضویت',
                'icon'  => 'unlock',
                'enabled' => $digits_sms_enabled === 'yes',
        ),
);

$tab_titles = array(
        'connection-settings'    => 'تنظیمات اتصال',
        'admin-sms'              => 'پیامک مدیر',
        'customer-sms'           => 'پیامک مشتری',
        'seller-sms'             => 'پیامک فروشنده',
        'gravity-forms-sms'      => 'پیامک فرم های Gravity',
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

if ( isset( $menu_items[ $active_tab ]['enabled'] ) && ! $menu_items[ $active_tab ]['enabled'] ) {
    $active_tab = 'connection-settings';
}

$tab_file = LIMOSMS_PATH . 'templates/admin-tabs/' . $active_tab . '-view.php';

// expose connection status to JS so tabs can be gated when API is not configured/connected
$connection_tab_obj = new LimoSMS_Connection_Settings();
$connection_status = $connection_tab_obj->get_connection_status();
$is_connected = !empty($connection_status['success']);

?>

<div class="limosms-wrapper">
    <div class="limosms-sidebar">
        <a class="" target="_blank" href="https://panel.limosms.com/" rel="noopener noreferrer">
            <img class="limosms-logo" src="<?php echo esc_url( LIMOSMS_URL . 'assets/images/logo.png' ); ?>"
                alt="<?php echo esc_attr__( 'LimoSMS Logo', 'limosms' ); ?>">
        </a>

        <ul>
            <?php foreach ( $menu_items as $key => $item ) : ?>
            <?php if ( ! empty( $item['enabled'] ) || ! isset( $item['enabled'] ) ) : ?>
            <li class="<?php echo esc_attr( $active_tab === $key ? 'active' : '' ); ?>">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=limosms&tab=' . $key ) ); ?>"
                    class="limosms-tab-link" data-tab="<?php echo esc_attr( $key ); ?>">
                    <span class="dashicons dashicons-<?php echo esc_attr( $item['icon'] ); ?>"></span>
                    <?php echo esc_html( $item['label'] ); ?>
                </a>
            </li>
            <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="limosms-content">
        <div class="content-header">
            <h1 class="tab-title" id="limosms-page-title">
                <?php echo esc_html( $tab_titles[ $active_tab ] ?? 'به لیمو SMS خوش آمدید' ); ?>
            </h1>
        </div>

        <div id="limosms-unsaved-warning-fixed" class="limosms-unsaved-warning limosms-unsaved-warning-fixed" style="display:none;">
            تغییرات نیاز به ذخیره شدن دارند. لطفا ذخیره کنید.
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
<script>
    var limosms_connection_status = <?php echo $is_connected ? 'true' : 'false'; ?>;
</script>