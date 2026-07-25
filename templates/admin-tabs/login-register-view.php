<?php
$settings   = get_option( 'limoo_sms_settings', array() );
$is_enabled = ! empty( $settings['login_register_otp_enabled'] ) && '1' === (string) $settings['login_register_otp_enabled'];
$activity_logs = get_option( 'limoo_sms_auth_logs', array() );
$registration_fields_config = isset( $settings['login_register_otp_registration_fields'] ) && is_array( $settings['login_register_otp_registration_fields'] ) ? $settings['login_register_otp_registration_fields'] : array();
$registration_field_options = array(
    'username' => array(
        'label' => __( 'نام کاربری', 'limoo-sms' ),
        'default_required' => false,
    ),
    'password' => array(
        'label' => __( 'رمز عبور', 'limoo-sms' ),
        'default_required' => false,
    ),
    'email' => array(
        'label' => __( 'ایمیل', 'limoo-sms' ),
        'default_required' => true,
    ),
    'first_name' => array(
        'label' => __( 'نام', 'limoo-sms' ),
        'default_required' => false,
    ),
    'last_name' => array(
        'label' => __( 'نام خانوادگی', 'limoo-sms' ),
        'default_required' => false,
    ),
    'address' => array(
        'label' => __( 'آدرس', 'limoo-sms' ),
        'default_required' => false,
    ),
    'city' => array(
        'label' => __( 'شهر', 'limoo-sms' ),
        'default_required' => false,
    ),
    'postcode' => array(
        'label' => __( 'کد پستی', 'limoo-sms' ),
        'default_required' => false,
    ),
);
$activity_logs = is_array( $activity_logs ) ? $activity_logs : array();
$activity_logs_total = count( $activity_logs );
$activity_logs_per_page = 10;
$activity_logs_page = isset( $_GET['limoo_logs_page'] ) ? max( 1, absint( wp_unslash( $_GET['limoo_logs_page'] ) ) ) : 1;
$activity_logs_total_pages = max( 1, (int) ceil( $activity_logs_total / $activity_logs_per_page ) );
$activity_logs_page = min( $activity_logs_page, $activity_logs_total_pages );
$activity_logs_start = ( $activity_logs_page - 1 ) * $activity_logs_per_page;
$activity_logs_visible = array_slice( $activity_logs, $activity_logs_start, $activity_logs_per_page );
$page_slug = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
$activity_logs_base_url = $page_slug ? admin_url( 'admin.php?page=' . rawurlencode( $page_slug ) ) : admin_url();

$redirect_url  = isset( $settings['login_register_otp_redirect_url'] ) ? $settings['login_register_otp_redirect_url'] : '';
$default_role  = get_option( 'default_role', 'subscriber' );
$selected_role = isset( $settings['login_register_otp_role'] ) ? $settings['login_register_otp_role'] : $default_role;
$expiry_minutes = ! empty( $settings['login_register_otp_expiry_minutes'] ) ? absint( $settings['login_register_otp_expiry_minutes'] ) : 10;
$resend_seconds = ! empty( $settings['login_register_otp_resend_seconds'] ) ? absint( $settings['login_register_otp_resend_seconds'] ) : 60;
$max_attempts = ! empty( $settings['login_register_otp_max_attempts'] ) ? absint( $settings['login_register_otp_max_attempts'] ) : 5;
$lockout_minutes = ! empty( $settings['login_register_otp_lockout_minutes'] ) ? absint( $settings['login_register_otp_lockout_minutes'] ) : 15;
$form_align = isset( $settings['login_register_otp_form_align'] ) ? $settings['login_register_otp_form_align'] : 'center';
$form_direction = isset( $settings['login_register_otp_form_direction'] ) ? $settings['login_register_otp_form_direction'] : 'rtl';
$form_font_family = isset( $settings['login_register_otp_font_family'] ) ? $settings['login_register_otp_font_family'] : 'Vazirmatn, Tahoma, Arial, sans-serif';
$logo_url = isset( $settings['login_register_otp_logo_url'] ) ? $settings['login_register_otp_logo_url'] : '';
$captcha_enabled = ! empty( $settings['login_register_otp_captcha_enabled'] ) && '1' === (string) $settings['login_register_otp_captcha_enabled'];
$disable_default_auth = ! empty( $settings['login_register_disable_default_auth'] ) && '1' === (string) $settings['login_register_disable_default_auth'];
$background_image_url = isset( $settings['login_register_otp_background_image_url'] ) ? $settings['login_register_otp_background_image_url'] : '';
$background_color = isset( $settings['login_register_otp_background_color'] ) ? $settings['login_register_otp_background_color'] : '#ffffff';
$form_background_color = isset( $settings['login_register_otp_form_background_color'] ) ? $settings['login_register_otp_form_background_color'] : '#ffffff';
$accent_color = isset( $settings['login_register_otp_accent_color'] ) ? $settings['login_register_otp_accent_color'] : '#2563eb';
$custom_css = isset( $settings['login_register_otp_custom_css'] ) ? $settings['login_register_otp_custom_css'] : '';
$selected_allowed_country_codes = isset( $settings['login_register_otp_allowed_country_codes'] ) && is_array( $settings['login_register_otp_allowed_country_codes'] ) ? $settings['login_register_otp_allowed_country_codes'] : array();
if ( empty( $selected_allowed_country_codes ) ) {
    $selected_allowed_country_codes = array( '98' );
}
$roles = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array();
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
                id="limoo-otp-settings"
                class="limoo-otp-settings <?php echo $is_enabled ? 'is-visible' : ''; ?>"
                <?php echo $is_enabled ? '' : 'hidden'; ?>
        >
            <div class="limoo-setting-row">
                <div class="limoo-setting-row__content">
                    <label for="limoo-login-register-disable-default-auth" class="limoo-setting-row__label">
                        <?php esc_html_e( 'غیرفعال کردن ورود و عضویت وردپرس و ووکامرس', 'limoo-sms' ); ?>
                    </label>
                    <p class="limoo-setting-row__description">
                        <?php esc_html_e( 'در صورت فعال بودن این گزینه و فعال بودن ورود با افزونه، ورود و ثبت‌نام پیش‌فرض وردپرس و ووکامرس مسدود می‌شود.', 'limoo-sms' ); ?>
                    </p>
                </div>

                <label class="limoo-switch" for="limoo-login-register-disable-default-auth">
                    <input
                            type="checkbox"
                            id="limoo-login-register-disable-default-auth"
                            name="login_register_disable_default_auth"
                            value="1"
                            <?php checked( $disable_default_auth ); ?>
                    />
                    <span class="limoo-switch__slider"></span>
                </label>
            </div>

            <div class="limoo-login-register-subtabs" role="tablist" aria-label="تنظیمات ورود و عضویت">
                <button type="button" class="limoo-login-register-subtab is-active" data-panel="general" role="tab" aria-selected="true">
                    <?php esc_html_e( 'تنظیمات عمومی', 'limoo-sms' ); ?>
                </button>
                <button type="button" class="limoo-login-register-subtab" data-panel="security" role="tab" aria-selected="false">
                    <?php esc_html_e( 'امنیت', 'limoo-sms' ); ?>
                </button>
                <button type="button" class="limoo-login-register-subtab" data-panel="style" role="tab" aria-selected="false">
                    <?php esc_html_e( 'استایل', 'limoo-sms' ); ?>
                </button>
            </div>

            <div id="limoo-login-register-panel-general" class="limoo-login-register-panel is-active" role="tabpanel">
                <div class="limoo-login-register-card__section">
                    <h3 class="limoo-login-register-card__section-title"><?php esc_html_e( 'فیلدهای ثبت‌نام', 'limoo-sms' ); ?></h3>
                    <p class="limoo-login-register-card__section-description"><?php esc_html_e( 'فیلدهایی که هنگام ثبت‌نام از کاربر گرفته شود را انتخاب کنید و مشخص کنید کدام‌ها اجباری هستند.', 'limoo-sms' ); ?></p>
                </div>

                <?php foreach ( $registration_field_options as $field_key => $field_option ) : ?>
                    <?php $field_config = isset( $registration_fields_config[ $field_key ] ) && is_array( $registration_fields_config[ $field_key ] ) ? $registration_fields_config[ $field_key ] : array(); ?>
                    <?php $is_enabled = ! empty( $field_config['enabled'] ) && '1' === (string) $field_config['enabled']; ?>
                    <?php $is_required = ! empty( $field_config['required'] ) && '1' === (string) $field_config['required']; ?>
                    <div class="limoo-setting-row">
                        <div class="limoo-setting-row__content">
                            <label class="limoo-setting-row__label" for="limoo-registration-field-<?php echo esc_attr( $field_key ); ?>-enabled"><?php echo esc_html( $field_option['label'] ); ?></label>
                            <p class="limoo-setting-row__description"><?php esc_html_e( 'فعال بودن این فیلد در فرم ثبت‌نام', 'limoo-sms' ); ?></p>
                        </div>
                        <div class="limoo-setting-row__controls">
                            <label class="limoo-switch" for="limoo-registration-field-<?php echo esc_attr( $field_key ); ?>-enabled">
                                <input type="checkbox" id="limoo-registration-field-<?php echo esc_attr( $field_key ); ?>-enabled" name="login_register_otp_registration_fields[<?php echo esc_attr( $field_key ); ?>][enabled]" value="1" <?php checked( $is_enabled ); ?> />
                                <span class="limoo-switch__slider"></span>
                            </label>
                            <label class="limoo-setting-row__inline-checkbox" for="limoo-registration-field-<?php echo esc_attr( $field_key ); ?>-required">
                                <input type="checkbox" id="limoo-registration-field-<?php echo esc_attr( $field_key ); ?>-required" name="login_register_otp_registration_fields[<?php echo esc_attr( $field_key ); ?>][required]" value="1" <?php checked( $is_required ); ?> />
                                <?php esc_html_e( 'اجباری', 'limoo-sms' ); ?>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content">
                        <label for="limoo-login-register-redirect-url" class="limoo-setting-row__label">
                            <?php esc_html_e( 'آدرس بازگشت پس از ورود', 'limoo-sms' ); ?>
                        </label>
                        <p class="limoo-setting-row__description">
                            <?php esc_html_e( 'آدرس صفحه‌ای را وارد کنید که پس از ورود یا ثبت نام با موفقیت به آن هدایت شود. خالی بگذارید تا به صفحه اصلی برود.', 'limoo-sms' ); ?>
                        </p>
                    </div>
                    <input
                            type="text"
                            id="limoo-login-register-redirect-url"
                            name="login_register_otp_redirect_url"
                            value="<?php echo esc_attr( $redirect_url ); ?>"
                            class="limoo-setting-row__input"
                            placeholder="<?php esc_attr_e( '/my-account', 'limo-sms' ); ?>"
                    />
                </div>

                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content">
                        <label for="limoo-login-register-allowed-countries" class="limoo-setting-row__label">
                            <?php esc_html_e( 'کشورهای مجاز برای ورود/ثبت‌نام', 'limoo-sms' ); ?>
                        </label>
                        <p class="limoo-setting-row__description">
                            <?php esc_html_e( 'کشورهایی که شماره موبایل آن‌ها مجاز است را انتخاب کنید. اگر هیچ کشور انتخاب نشود، فقط ایران در نظر گرفته می‌شود.', 'limoo-sms' ); ?>
                        </p>
                    </div>
                    <div class="limoo-country-selector">
                        <div class="limoo-country-selector__options">
                            <label class="limoo-country-selector__option">
                                <input type="checkbox" name="login_register_otp_allowed_country_codes[]" value="98" <?php checked( in_array( '98', $selected_allowed_country_codes, true ) ); ?> />
                                <span><?php esc_html_e( 'ایران (+98)', 'limoo-sms' ); ?></span>
                            </label>
                            <label class="limoo-country-selector__option">
                                <input type="checkbox" name="login_register_otp_allowed_country_codes[]" value="90" <?php checked( in_array( '90', $selected_allowed_country_codes, true ) ); ?> />
                                <span><?php esc_html_e( 'ترکیه (+90)', 'limoo-sms' ); ?></span>
                            </label>
                            <label class="limoo-country-selector__option">
                                <input type="checkbox" name="login_register_otp_allowed_country_codes[]" value="964" <?php checked( in_array( '964', $selected_allowed_country_codes, true ) ); ?> />
                                <span><?php esc_html_e( 'عراق (+964)', 'limoo-sms' ); ?></span>
                            </label>
                            <label class="limoo-country-selector__option">
                                <input type="checkbox" name="login_register_otp_allowed_country_codes[]" value="966" <?php checked( in_array( '966', $selected_allowed_country_codes, true ) ); ?> />
                                <span><?php esc_html_e( 'عربستان (+966)', 'limoo-sms' ); ?></span>
                            </label>
                            <label class="limoo-country-selector__option">
                                <input type="checkbox" name="login_register_otp_allowed_country_codes[]" value="971" <?php checked( in_array( '971', $selected_allowed_country_codes, true ) ); ?> />
                                <span><?php esc_html_e( 'امارات (+971)', 'limoo-sms' ); ?></span>
                            </label>
                            <label class="limoo-country-selector__option">
                                <input type="checkbox" name="login_register_otp_allowed_country_codes[]" value="1" <?php checked( in_array( '1', $selected_allowed_country_codes, true ) ); ?> />
                                <span><?php esc_html_e( 'آمریکا/کانادا (+1)', 'limoo-sms' ); ?></span>
                            </label>
                            <label class="limoo-country-selector__option">
                                <input type="checkbox" name="login_register_otp_allowed_country_codes[]" value="44" <?php checked( in_array( '44', $selected_allowed_country_codes, true ) ); ?> />
                                <span><?php esc_html_e( 'انگلیس (+44)', 'limoo-sms' ); ?></span>
                            </label>
                            <label class="limoo-country-selector__option">
                                <input type="checkbox" name="login_register_otp_allowed_country_codes[]" value="49" <?php checked( in_array( '49', $selected_allowed_country_codes, true ) ); ?> />
                                <span><?php esc_html_e( 'آلمان (+49)', 'limoo-sms' ); ?></span>
                            </label>
                        </div>
                        <p class="limoo-country-selector__hint">
                            <?php esc_html_e( 'در این بخش می‌توانید چند کشور را هم‌زمان برای ورود/ثبت‌نام فعال کنید. کاربر با کد کشور انتخاب‌شده می‌تواند شماره را وارد کند.', 'limoo-sms' ); ?>
                        </p>
                    </div>
                </div>

                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content">
                        <label for="limoo-login-register-role" class="limoo-setting-row__label">
                            <?php esc_html_e( 'نقش کاربر جدید', 'limo-sms' ); ?>
                        </label>
                        <p class="limoo-setting-row__description">
                            <?php esc_html_e( 'نقشی را که برای کاربران جدید ایجاد می‌شود انتخاب کنید.', 'limo-sms' ); ?>
                        </p>
                    </div>
                    <select id="limoo-login-register-role" name="login_register_otp_role" class="limoo-setting-row__input">
                        <?php foreach ( $roles as $role_key => $role_data ) : ?>
                            <option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $selected_role, $role_key ); ?>>
                                <?php echo esc_html( $role_data['name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content">
                        <label for="limoo-login-register-expiry" class="limoo-setting-row__label">
                            <?php esc_html_e( 'مدت اعتبار کد (دقیقه)', 'limo-sms' ); ?>
                        </label>
                        <p class="limoo-setting-row__description">
                            <?php esc_html_e( 'مدت زمان معتبر بودن کد تایید پس از ارسال.', 'limo-sms' ); ?>
                        </p>
                    </div>
                    <input
                            type="number"
                            min="1"
                            id="limoo-login-register-expiry"
                            name="login_register_otp_expiry_minutes"
                            value="<?php echo esc_attr( $expiry_minutes ); ?>"
                            class="limoo-setting-row__input"
                    />
                </div>

                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content">
                        <label for="limoo-login-register-resend" class="limoo-setting-row__label">
                            <?php esc_html_e( 'فاصله ارسال مجدد (ثانیه)', 'limo-sms' ); ?>
                        </label>
                        <p class="limoo-setting-row__description">
                            <?php esc_html_e( 'حداقل فاصله بین ارسال‌های دوباره کد به یک شماره تلفن.', 'limo-sms' ); ?>
                        </p>
                    </div>
                    <input
                            type="number"
                            min="10"
                            id="limoo-login-register-resend"
                            name="login_register_otp_resend_seconds"
                            value="<?php echo esc_attr( $resend_seconds ); ?>"
                            class="limoo-setting-row__input"
                    />
                </div>

                <div class="limoo-login-register-card__section">
                    <h3 class="limoo-login-register-card__section-title"><?php esc_html_e( 'لاگ فعالیت‌های ورود و تایید', 'limoo-sms' ); ?></h3>
                    <p class="limoo-login-register-card__section-description"><?php esc_html_e( 'آخرین تلاش‌های ارسال کد، ورود موفق و تلاش‌های ناموفق را در اینجا ببینید.', 'limoo-sms' ); ?></p>
                </div>

                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content limoo-setting-row__content--full">
                        <?php if ( empty( $activity_logs ) ) : ?>
                            <p class="limoo-setting-row__description"><?php esc_html_e( 'هنوز رویدادی ثبت نشده است.', 'limoo-sms' ); ?></p>
                        <?php else : ?>
                            <div class="limoo-activity-log-table-wrapper">
                                <table class="limoo-activity-log-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'زمان', 'limoo-sms' ); ?></th>
                                            <th><?php esc_html_e( 'نوع رویداد', 'limoo-sms' ); ?></th>
                                            <th><?php esc_html_e( 'موبایل', 'limoo-sms' ); ?></th>
                                            <th><?php esc_html_e( 'توضیح', 'limoo-sms' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $activity_logs_visible as $log ) : ?>
                                            <?php
                                            $type_label = array(
                                                'otp_send_success' => __( 'ارسال کد موفق', 'limoo-sms' ),
                                                'otp_send_failed'  => __( 'ارسال کد ناموفق', 'limoo-sms' ),
                                                'captcha_failed'   => __( 'کپچا ناموفق', 'limoo-sms' ),
                                                'verify_success'   => __( 'ورود موفق', 'limoo-sms' ),
                                                'verify_failed'    => __( 'تلاش ناموفق', 'limoo-sms' ),
                                                'rate_limited'     => __( 'محدودیت نرخ', 'limoo-sms' ),
                                                'verify_locked'    => __( 'قفل کاربر', 'limoo-sms' ),
                                            );
                                            $log_type = isset( $log['type'] ) ? (string) $log['type'] : '';
                                            $label = isset( $type_label[ $log_type ] ) ? $type_label[ $log_type ] : __( 'رویداد', 'limoo-sms' );
                                            $message = isset( $log['message'] ) ? $log['message'] : '';
                                            $mobile = isset( $log['mobile'] ) ? $log['mobile'] : '';
                                            $timestamp = isset( $log['timestamp'] ) ? gmdate( 'Y-m-d H:i:s', (int) $log['timestamp'] ) : '';
                                            $pill_class = 'limoo-activity-log-pill';
                                            if ( 'verify_success' === $log_type || 'otp_send_success' === $log_type ) {
                                                $pill_class .= ' limoo-activity-log-pill--success';
                                            } elseif ( 'verify_failed' === $log_type || 'otp_send_failed' === $log_type || 'captcha_failed' === $log_type ) {
                                                $pill_class .= ' limoo-activity-log-pill--danger';
                                            } else {
                                                $pill_class .= ' limoo-activity-log-pill--neutral';
                                            }
                                            ?>
                                            <tr>
                                                <td><?php echo esc_html( $timestamp ); ?></td>
                                                <td><span class="<?php echo esc_attr( $pill_class ); ?>"><?php echo esc_html( $label ); ?></span></td>
                                                <td><?php echo esc_html( $mobile ); ?></td>
                                                <td><?php echo esc_html( $message ); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ( $activity_logs_total_pages > 1 ) : ?>
                                <div class="limoo-activity-log-pagination" role="navigation" aria-label="Pagination">
                                    <?php if ( $activity_logs_page > 1 ) : ?>
                                        <a class="button button-secondary" href="<?php echo esc_url( add_query_arg( 'limoo_logs_page', max( 1, $activity_logs_page - 1 ), $activity_logs_base_url ) ); ?>"><?php esc_html_e( 'قبلی', 'limoo-sms' ); ?></a>
                                    <?php endif; ?>
                                    <span class="limoo-activity-log-pagination__info">
                                        <?php printf( esc_html__( 'صفحه %1$d از %2$d', 'limoo-sms' ), $activity_logs_page, $activity_logs_total_pages ); ?>
                                    </span>
                                    <?php if ( $activity_logs_page < $activity_logs_total_pages ) : ?>
                                        <a class="button button-secondary" href="<?php echo esc_url( add_query_arg( 'limoo_logs_page', $activity_logs_page + 1, $activity_logs_base_url ) ); ?>"><?php esc_html_e( 'بعدی', 'limoo-sms' ); ?></a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="limoo-login-register-panel-security" class="limoo-login-register-panel" role="tabpanel" hidden>
                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content">
                        <label for="limoo-login-register-captcha-enabled" class="limoo-setting-row__label">
                            <?php esc_html_e( 'فعال‌سازی کپچا', 'limoo-sms' ); ?>
                        </label>
                        <p class="limoo-setting-row__description">
                            <?php esc_html_e( 'در صورت فعال بودن، قبل از ارسال کد تایید یک کپچا ساده نمایش داده می‌شود.', 'limoo-sms' ); ?>
                        </p>
                    </div>
                    <label class="limoo-switch" for="limoo-login-register-captcha-enabled">
                        <input
                                type="checkbox"
                                id="limoo-login-register-captcha-enabled"
                                name="login_register_otp_captcha_enabled"
                                value="1"
                                <?php checked( $captcha_enabled ); ?>
                        />
                        <span class="limoo-switch__slider"></span>
                    </label>
                </div>

                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content">
                        <label for="limoo-login-register-max-attempts" class="limoo-setting-row__label">
                            <?php esc_html_e( 'حداکثر تلاش‌های ناموفق', 'limo-sms' ); ?>
                        </label>
                        <p class="limoo-setting-row__description">
                            <?php esc_html_e( 'بعد از این تعداد تلاش ناموفق، کاربر باید دوباره کد دریافت کند.', 'limo-sms' ); ?>
                        </p>
                    </div>
                    <input
                            type="number"
                            min="1"
                            id="limoo-login-register-max-attempts"
                            name="login_register_otp_max_attempts"
                            value="<?php echo esc_attr( $max_attempts ); ?>"
                            class="limoo-setting-row__input"
                    />
                </div>

                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content">
                        <label for="limoo-login-register-lockout" class="limoo-setting-row__label">
                            <?php esc_html_e( 'قفل پس از شکست', 'limo-sms' ); ?>
                        </label>
                        <p class="limoo-setting-row__description">
                            <?php esc_html_e( 'مدت زمان قفل شدن ارسال/اعتبارسنجی پس از تلاش‌های ناموفق.', 'limosms' ); ?>
                        </p>
                    </div>
                    <input
                            type="number"
                            min="1"
                            id="limoo-login-register-lockout"
                            name="login_register_otp_lockout_minutes"
                            value="<?php echo esc_attr( $lockout_minutes ); ?>"
                            class="limoo-setting-row__input"
                    />
                </div>
            </div>

            <div id="limoo-login-register-panel-style" class="limoo-login-register-panel" role="tabpanel" hidden>
                <div class="limoo-login-register-card__section">
                    <h3 class="limoo-login-register-card__section-title"><?php esc_html_e( 'شخصی‌سازی فرم', 'limosms' ); ?></h3>
                    <p class="limoo-login-register-card__section-description"><?php esc_html_e( 'برای هر سایت ظاهر فرم را تنظیم کنید.', 'limosms' ); ?></p>
                </div>

                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content">
                        <label class="limoo-login-register-inline-label"><?php esc_html_e( 'چیدمان فرم', 'limosms' ); ?></label>
                        <p class="limoo-setting-row__description"><?php esc_html_e( 'موقعیت بلوک فرم را مشخص کنید.', 'limosms' ); ?></p>
                    </div>
                    <select id="limoo-login-register-align" name="login_register_otp_form_align" class="limoo-setting-row__input">
                        <option value="left" <?php selected( $form_align, 'left' ); ?>><?php esc_html_e( 'چپ', 'limosms' ); ?></option>
                        <option value="center" <?php selected( $form_align, 'center' ); ?>><?php esc_html_e( 'وسط', 'limosms' ); ?></option>
                        <option value="right" <?php selected( $form_align, 'right' ); ?>><?php esc_html_e( 'راست', 'limosms' ); ?></option>
                    </select>
                </div>

                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content">
                        <label class="limoo-login-register-inline-label"><?php esc_html_e( 'جهت متن', 'limosms' ); ?></label>
                        <p class="limoo-setting-row__description"><?php esc_html_e( 'نمایش فرم را برای RTL یا LTR تنظیم کنید.', 'limosms' ); ?></p>
                    </div>
                    <select id="limoo-login-register-direction" name="login_register_otp_form_direction" class="limoo-setting-row__input">
                        <option value="rtl" <?php selected( $form_direction, 'rtl' ); ?>><?php esc_html_e( 'راست‌چین', 'limosms' ); ?></option>
                        <option value="ltr" <?php selected( $form_direction, 'ltr' ); ?>><?php esc_html_e( 'چپ‌چین', 'limosms' ); ?></option>
                    </select>
                </div>

                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content">
                        <label class="limoo-login-register-inline-label"><?php esc_html_e( 'فونت فرم', 'limosms' ); ?></label>
                        <p class="limoo-setting-row__description"><?php esc_html_e( 'فونت موردنظر برای متن‌های فرم را انتخاب کنید.', 'limosms' ); ?></p>
                    </div>
                    <select id="limoo-login-register-font-family" name="login_register_otp_font_family" class="limoo-setting-row__input">
                        <option value="Vazirmatn, Tahoma, Arial, sans-serif" <?php selected( $form_font_family, 'Vazirmatn, Tahoma, Arial, sans-serif' ); ?>>Vazirmatn / فارسی</option>
                        <option value="IRANSans, Tahoma, Arial, sans-serif" <?php selected( $form_font_family, 'IRANSans, Tahoma, Arial, sans-serif' ); ?>>IRANSans / فارسی</option>
                        <option value="Tahoma, Arial, sans-serif" <?php selected( $form_font_family, 'Tahoma, Arial, sans-serif' ); ?>>Tahoma</option>
                        <option value="Arial, sans-serif" <?php selected( $form_font_family, 'Arial, sans-serif' ); ?>>Arial</option>
                        <option value="Segoe UI, Tahoma, sans-serif" <?php selected( $form_font_family, 'Segoe UI, Tahoma, sans-serif' ); ?>>Segoe UI</option>
                        <option value="Yekan, Tahoma, Arial, sans-serif" <?php selected( $form_font_family, 'Yekan, Tahoma, Arial, sans-serif' ); ?>>Yekan / فارسی</option>
                        <option value="Times New Roman, serif" <?php selected( $form_font_family, 'Times New Roman, serif' ); ?>>Times New Roman</option>
                    </select>
                </div>

                <div class="limoo-setting-row limoo-setting-row--media">
                    <div class="limoo-setting-row__content">
                        <label class="limoo-login-register-inline-label"><?php esc_html_e( 'لوگو فرم', 'limosms' ); ?></label>
                        <p class="limoo-setting-row__description"><?php esc_html_e( 'لوگوی کوچک بالای فرم را تنظیم کنید.', 'limosms' ); ?></p>
                    </div>
                    <div class="limoo-media-field">
                        <input type="text" id="limoo-login-register-logo-url" name="login_register_otp_logo_url" value="<?php echo esc_attr( $logo_url ); ?>" class="limoo-setting-row__input" placeholder="https://" />
                        <button type="button" class="button limoo-media-upload-button" data-target="limoo-login-register-logo-url"><?php esc_html_e( 'انتخاب', 'limosms' ); ?></button>
                        <button type="button" class="button limoo-media-remove-button" data-target="limoo-login-register-logo-url"><?php esc_html_e( 'پاک کردن', 'limosms' ); ?></button>
                        <img data-preview="limoo-login-register-logo-url" src="<?php echo esc_url( $logo_url ); ?>" alt="" class="limoo-media-preview" <?php echo empty( $logo_url ) ? 'hidden' : ''; ?> />
                    </div>
                </div>

                <div class="limoo-setting-row limoo-setting-row--media">
                    <div class="limoo-setting-row__content">
                        <label class="limoo-login-register-inline-label"><?php esc_html_e( 'پس‌زمینه فرم', 'limosms' ); ?></label>
                        <p class="limoo-setting-row__description"><?php esc_html_e( 'یک تصویر پس‌زمینه برای بخش فرم انتخاب کنید.', 'limosms' ); ?></p>
                    </div>
                    <div class="limoo-media-field">
                        <input type="text" id="limoo-login-register-background-url" name="login_register_otp_background_image_url" value="<?php echo esc_attr( $background_image_url ); ?>" class="limoo-setting-row__input" placeholder="https://" />
                        <button type="button" class="button limoo-media-upload-button" data-target="limoo-login-register-background-url"><?php esc_html_e( 'انتخاب', 'limosms' ); ?></button>
                        <button type="button" class="button limoo-media-remove-button" data-target="limoo-login-register-background-url"><?php esc_html_e( 'پاک کردن', 'limosms' ); ?></button>
                        <img data-preview="limoo-login-register-background-url" src="<?php echo esc_url( $background_image_url ); ?>" alt="" class="limoo-media-preview" <?php echo empty( $background_image_url ) ? 'hidden' : ''; ?> />
                    </div>
                </div>

                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content">
                        <label for="limoo-login-register-background-color" class="limoo-setting-row__label"><?php esc_html_e( 'رنگ پس‌زمینه کلی', 'limosms' ); ?></label>
                    </div>
                    <input type="color" id="limoo-login-register-background-color" name="login_register_otp_background_color" value="<?php echo esc_attr( $background_color ); ?>" class="limoo-setting-row__input" />
                </div>

                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content">
                        <label for="limoo-login-register-form-background-color" class="limoo-setting-row__label"><?php esc_html_e( 'رنگ پس‌زمینه فرم', 'limosms' ); ?></label>
                    </div>
                    <input type="color" id="limoo-login-register-form-background-color" name="login_register_otp_form_background_color" value="<?php echo esc_attr( $form_background_color ); ?>" class="limoo-setting-row__input" />
                </div>

                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content">
                        <label for="limoo-login-register-accent-color" class="limoo-setting-row__label"><?php esc_html_e( 'رنگ تاکید', 'limosms' ); ?></label>
                    </div>
                    <input type="color" id="limoo-login-register-accent-color" name="login_register_otp_accent_color" value="<?php echo esc_attr( $accent_color ); ?>" class="limoo-setting-row__input" />
                </div>

                <div class="limoo-setting-row">
                    <div class="limoo-setting-row__content">
                        <label for="limoo-login-register-custom-css" class="limoo-setting-row__label"><?php esc_html_e( 'CSS سفارشی فرم', 'limosms' ); ?></label>
                        <p class="limoo-setting-row__description"><?php esc_html_e( 'کد CSS دلخواه خود را برای شخصی‌سازی فرم اینجا بنویسید.', 'limosms' ); ?></p>
                    </div>
                    <div class="limoo-setting-row__content limoo-setting-row__content--full">
                        <div class="limoo-custom-css-editor">
                            <div class="limoo-custom-css-editor__header">
                                <span><?php esc_html_e( 'ویرایشگر سبک سفارشی', 'limosms' ); ?></span>
                                <div class="limoo-custom-css-editor__dots" aria-hidden="true">
                                    <span class="limoo-custom-css-editor__dot"></span>
                                    <span class="limoo-custom-css-editor__dot"></span>
                                    <span class="limoo-custom-css-editor__dot"></span>
                                </div>
                            </div>
                            <textarea id="limoo-login-register-custom-css" name="login_register_otp_custom_css" class="limoo-custom-css-editor__textarea" rows="12" placeholder=".limosms-mobile-auth { ... }\n.limosms-mobile-auth__button { ... }"><?php echo esc_textarea( $custom_css ); ?></textarea>
                            <div class="limoo-custom-css-editor__hint"><?php esc_html_e( 'مثال: .limosms-mobile-auth { border-radius: 24px; }', 'limosms' ); ?></div>
                        </div>
                    </div>
                </div>
            </div>
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
