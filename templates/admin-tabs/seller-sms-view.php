<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$events   = class_exists( 'LimoSMS_Seller_SMS_Events' ) ? LimoSMS_Seller_SMS_Events::get_events() : array();
$settings = class_exists( 'LimoSMS_Seller_SMS_Settings' ) ? LimoSMS_Seller_SMS_Settings::get_events_settings() : array();

$events   = is_array( $events ) ? $events : array();
$settings = is_array( $settings ) ? $settings : array();
?>

<div class="limosms-settings-wrapper limosms-seller-sms-settings">
    <?php foreach ( $events as $key => $event ) : ?>
        <?php
        $event_settings = ( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ) ? $settings[ $key ] : array();

        $enabled       = isset( $event_settings['enabled'] ) ? $event_settings['enabled'] : 'no';
        $pattern_id    = isset( $event_settings['otp_id'] ) ? $event_settings['otp_id'] : '';
        $pattern_title = isset( $event_settings['title'] ) ? $event_settings['title'] : '';
        $pattern_text  = isset( $event_settings['pattern_text'] ) ? $event_settings['pattern_text'] : '';
        $pattern_map   = ( isset( $event_settings['pattern_map'] ) && is_array( $event_settings['pattern_map'] ) )
                ? $event_settings['pattern_map']
                : array();

        $event_label = isset( $event['label'] ) ? $event['label'] : $key;
        ?>
        <div
                class="limosms-card limosms-event-card <?php echo ( 'yes' === $enabled ) ? 'is-active' : ''; ?>"
                data-event="<?php echo esc_attr( $key ); ?>"
        >
            <h3 class="limosms-event-title"><?php echo esc_html( $event_label ); ?></h3>

            <label class="limosms-event-toggle">
                <input
                        type="checkbox"
                        class="limosms-event-enabled"
                        data-event="<?php echo esc_attr( $key ); ?>"
                        <?php checked( $enabled, 'yes' ); ?>
                />
                <span>فعال باشد</span>
            </label>

            <div class="limosms-event-fields">
                <div class="limosms-field limosms-field-pattern-id">
                    <label
                            class="limosms-label"
                            for="limosms-seller-pattern-selector-<?php echo esc_attr( $key ); ?>"
                    >
                        انتخاب Pattern از لیموSMS
                    </label>

                    <select
                            id="limosms-seller-pattern-selector-<?php echo esc_attr( $key ); ?>"
                            class="limosms-pattern-selector"
                            data-event="<?php echo esc_attr( $key ); ?>"
                    >
                        <option value="">ابتدا لیست را دریافت کنید</option>
                    </select>

                    <input
                            type="hidden"
                            class="limosms-event-otp-id"
                            value="<?php echo esc_attr( $pattern_id ); ?>"
                    />

                    <input
                            type="hidden"
                            class="limosms-event-pattern-title"
                            value="<?php echo esc_attr( $pattern_title ); ?>"
                    />
                </div>

                <div class="limosms-field limosms-field-pattern-text">
                    <label class="limosms-label">متن Pattern</label>

                    <div class="limosms-pattern-box">
						<pre
                                class="limosms-pattern-text"
                                data-event="<?php echo esc_attr( $key ); ?>"
                        ><?php echo esc_html( $pattern_text ); ?></pre>
                    </div>
                </div>

                <div class="limosms-field limosms-field-pattern-map">
                    <label class="limosms-label">اتصال پارامترها به توکن‌ها</label>

                    <div
                            class="limosms-pattern-mapping"
                            data-event="<?php echo esc_attr( $key ); ?>"
                            data-saved-map="<?php echo esc_attr( wp_json_encode( $pattern_map ) ); ?>"
                    ></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <button
            type="button"
            id="limosms-save-seller-otp-settings"
            class="button button-primary"
            disabled
    >
        ذخیره تغییرات
    </button>
</div>

<script>
    window.limosmsTokens = window.limosmsTokens || {};
    <?php foreach ( $events as $k => $event ) : ?>
    window.limosmsTokens[<?php echo wp_json_encode( (string) $k ); ?>] = <?php echo wp_json_encode(
            ( isset( $event['tokens'] ) && is_array( $event['tokens'] ) ) ? $event['tokens'] : array()
    ); ?>;
    <?php endforeach; ?>
</script>
