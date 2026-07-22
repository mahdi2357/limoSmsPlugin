<div id="limosms-pattern-management-tab" class="limosms-pattern-container">
    <div class="limosms-pattern-header">
        <h2 class="limosms-pattern-heading">
            <?php esc_html_e( 'جهت ثبت الگوی جدید به', 'limosms' ); ?>
            <a href="https://panel.limosms.com/Programmer/PatternMessage" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e( 'پنل لیمو اس ام اس', 'limosms' ); ?>
            </a>
            <?php esc_html_e( 'مراجعه بفرمایید', 'limosms' ); ?>
        </h2>
    </div>

    <div class="limosms-table-card">
        <div class="limosms-table-responsive">
            <table class="limosms-custom-table">
                <colgroup>
                    <col class="col-row" />
                    <col class="col-code" />
                    <col class="col-title" />
                    <col class="col-text" />
                    <col class="col-actions" />
                </colgroup>

                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ردیف', 'limosms' ); ?></th>
                        <th><?php esc_html_e( 'کد الگو', 'limosms' ); ?></th>
                        <th><?php esc_html_e( 'عنوان', 'limosms' ); ?></th>
                        <th><?php esc_html_e( 'متن پیام', 'limosms' ); ?></th>
                        <th><?php esc_html_e( 'عملیات', 'limosms' ); ?></th>
                    </tr>
                </thead>

                <tbody id="limosms-patterns-table-body">
                    <tr>
                        <td colspan="5" class="limosms-table-loading">
                            <?php esc_html_e( 'در حال دریافت الگوها...', 'limosms' ); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="limosms-pagination-wrap" style="display: none;">
            <div class="limosms-pagination-info">
                <?php esc_html_e( 'صفحه 1 از 1', 'limosms' ); ?>
            </div>

            <div class="limosms-pagination-buttons">
                <button type="button" class="limosms-page-btn prev" disabled>
                    <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    <?php esc_html_e( 'قبلی', 'limosms' ); ?>
                </button>

                <div class="limosms-page-numbers"></div>

                <button type="button" class="limosms-page-btn next" disabled>
                    <?php esc_html_e( 'بعدی', 'limosms' ); ?>
                    <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </div>
</div>
