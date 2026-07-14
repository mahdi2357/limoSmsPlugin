<?php
/**
 * Sent SMS Tab Template.
 *
 * @package LimoSMS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="limosms-sent-sms-tab" class="limosms-pattern-container">
    <div class="limosms-pattern-header">
        <div class="limosms-header-title">
            <h2><?php esc_html_e( 'پیام‌های ارسال‌شده', 'limosms' ); ?></h2>
        </div>

        <div class="limosms-header-actions">
            <button type="button" id="limosms-refresh-sent-sms">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e( 'بروزرسانی لیست', 'limosms' ); ?>
            </button>
        </div>
    </div>

    <div class="limosms-table-card">
        <table class="limosms-custom-table">
            <thead>
            <tr>
                <th><?php esc_html_e( 'ردیف', 'limosms' ); ?></th>
                <th><?php esc_html_e( 'متن پیام', 'limosms' ); ?></th>
                <th><?php esc_html_e( 'تاریخ ارسال', 'limosms' ); ?></th>
                <th><?php esc_html_e( 'وضعیت ارسال', 'limosms' ); ?></th>
                <th><?php esc_html_e( 'وضعیت رسیدن', 'limosms' ); ?></th>
            </tr>
            </thead>

            <tbody id="limosms-sent-sms-table-body">
            <tr>
                <td colspan="5" style="text-align:center; padding:24px;">
                    <?php esc_html_e( 'در حال دریافت پیام‌ها...', 'limosms' ); ?>
                </td>
            </tr>
            </tbody>
        </table>

        <div class="limosms-pagination-wrap" id="limosms-sent-sms-pagination" style="display:none;">
            <div class="limosms-pagination-info">
                <?php esc_html_e( 'صفحه 1 از 1', 'limosms' ); ?>
            </div>

            <div class="limosms-pagination-buttons">
                <button type="button" class="limosms-page-btn prev" disabled>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                    <?php esc_html_e( 'قبلی', 'limosms' ); ?>
                </button>

                <div class="limosms-page-numbers"></div>

                <button type="button" class="limosms-page-btn next" disabled>
                    <?php esc_html_e( 'بعدی', 'limosms' ); ?>
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </button>
            </div>
        </div>
    </div>
</div>
