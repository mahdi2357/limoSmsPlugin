<?php
if (!defined('ABSPATH')) {
    exit;
}

$menu_items = [
        'connection-settings' => [
                'label' => 'تنظیمات اتصال',
                'icon' => 'admin-settings',
        ],
        'admin-sms' => [
                'label' => 'پیامک مدیر',
                'icon' => 'admin-users',
        ],
        'customer-sms' => [
                'label' => 'پیامک مشتری',
                'icon' => 'businessperson',
        ],
        'seller-sms' => [
                'label' => 'پیامک فروشنده',
                'icon' => 'businessman',
        ],
        'sent-message' => [
                'label'  => 'پیام های ارسال شده',
                'icon' => 'email-alt',
        ],
        'send-test-sms' => [
                'label' => 'ارسال پیامک تست',
                'icon' => 'email-alt',
        ],
        'sms-pattern-management' => [
                'label' => 'الگوهای پیامک',
                'icon' => 'text',
        ],

];

$tab_titles = [
        'connection-settings' => 'تنظیمات اتصال',
        'admin-sms' => 'پیامک مدیر',
        'customer-sms' => 'پیامک مشتری',
        'seller-sms' => 'پیامک فروشنده',
        'sent-message' => 'پیام های ارسال شده',
        'send-test-sms' => 'ارسال پیامک تست',
        'sms-pattern-management' => 'الگوهای پیامک',


        ];

$active_tab = isset($active_tab) && !empty($active_tab)
        ? sanitize_key($active_tab)
        : 'connection-settings';

if (!array_key_exists($active_tab, $menu_items)) {
    $active_tab = 'connection-settings';
}

$tab_file = LIMOSMS_PATH . 'templates/admin-tabs/' . $active_tab . '.php';
?>



<div class="limosms-wrapper">
    <div class="limosms-sidebar">
        <a class="limosms-logo-link" target="_blank" href="https://panel.limosms.com/" rel="noopener noreferrer">
            <img
                    class="limosms-logo"
                    src="<?php echo esc_url( LIMOSMS_URL . 'assets/images/logo.png' ); ?>"
                    alt="<?php echo esc_attr__( 'LimoSMS Logo', 'limosms' ); ?>"
            >
        </a>



        <ul>
            <?php foreach ($menu_items as $key => $item) : ?>
                <li class="<?php echo $active_tab === $key ? 'active' : ''; ?>">
                    <a
                            href="<?php echo esc_url(admin_url('admin.php?page=limosms&tab=' . $key)); ?>"
                            class="limosms-tab-link"
                            data-tab="<?php echo esc_attr($key); ?>"
                    >
                        <span class="dashicons dashicons-<?php echo esc_attr($item['icon']); ?>"></span>
                        <?php echo esc_html($item['label']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>



    <div class="limosms-content">


        <div class="content-header">
            <h1 id="limosms-page-title">
                <?php echo esc_html($tab_titles[$active_tab] ?? 'به لیمو SMS خوش آمدید'); ?>
            </h1>
        </div>
        <div class="limosms-content-box">

            <div id="limosms-tab-loading" style="display: none;">
                <div class="limosms-spinner"></div>
                <span>در حال بارگذاری...</span>
            </div>

            <div class="card-body" id="limosms-tab-content">
                <?php
                if (file_exists($tab_file)) {
                    include $tab_file;
                } else {
                    echo '<h2>' . esc_html__('به لیمو SMS خوش آمدید', 'limosms') . '</h2>';
                    echo '<p>' . esc_html__('فایل تب موردنظر پیدا نشد.', 'limosms') . '</p>';
                }
                ?>
            </div>

        </div>

    </div>

</div>
