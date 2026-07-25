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
                <th class="col-row"><?php esc_html_e( 'ردیف', 'limosms' ); ?></th>
                <th class="col-mobile"><?php esc_html_e( 'شماره گیرنده', 'limosms' ); ?></th>
                <th class="col-message"><?php esc_html_e( 'متن پیامک', 'limosms' ); ?></th>
                <th class="col-status"><?php esc_html_e( 'وضعیت', 'limosms' ); ?></th>
                <th class="col-date"><?php esc_html_e( 'تاریخ ارسال', 'limosms' ); ?></th>
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

    <h4 style="text-align: center;">
        <?php esc_html_e( 'جهت مشاهده تمامی پیامک های ارسال شده به', 'limosms' ); ?>
        <a style="color: rebeccapurple; text-decoration: none;" target="_blank" rel="noopener noreferrer" href="https://panel.limosms.com/sms/getuseronemessage">
            <?php esc_html_e( 'پنل لیمو اس ام اس', 'limosms' ); ?>
        </a>
        <?php esc_html_e( 'مراجعه بفرمایید', 'limosms' ); ?>
    </h4>
</div>
