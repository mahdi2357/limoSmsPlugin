<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="limosms-sent-sms-tab" class="limosms-sent-sms-container">
    <div class="limosms-table-card">
        <table class="limosms-custom-table">
            <thead>
            <tr>
                <th><?php esc_html_e( 'ردیف', 'limosms' ); ?></th>
                <th><?php esc_html_e( 'شماره گیرنده', 'limosms' ); ?></th>
                <th><?php esc_html_e( 'متن پیامک', 'limosms' ); ?></th>
                <th><?php esc_html_e( 'وضعیت', 'limosms' ); ?></th>
                <th><?php esc_html_e( 'تاریخ ارسال', 'limosms' ); ?></th>
            </tr>
            </thead>

            <tbody id="limosms-sent-sms-table-body">
            <tr>
                <td colspan="5" style="text-align: center; padding: 24px;">
                    <?php esc_html_e( 'در حال دریافت پیامک‌های ارسال‌شده...', 'limosms' ); ?>
                </td>
            </tr>
            </tbody>
        </table>

        <div class="limosms-sent-sms-pagination-wrap" style="display: none;">
            <div class="limosms-sent-sms-pagination-info">
                <?php esc_html_e( 'صفحه 1 از 1', 'limosms' ); ?>
            </div>

            <div class="limosms-pagination-buttons">
                <button type="button" class="limosms-sent-sms-page-btn prev" disabled>
                    <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    <?php esc_html_e( 'قبلی', 'limosms' ); ?>
                </button>

                <div class="limosms-sent-sms-page-numbers"></div>

                <button type="button" class="limosms-sent-sms-page-btn next" disabled>
                    <?php esc_html_e( 'بعدی', 'limosms' ); ?>
                    <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </div>
    <h4 style="text-align: center">
        جهت مشاهده تمامی پیامک های ارسال شده به
    <a style="text-decoration: none" target="_blank" href="https://panel.limosms.com/sms/getuseronemessage">
        پنل لیمو اس ام اس
    </a>
        مراجعه بفرمایید
    </h4>

</div>
