<?php
/**
 * Settings Template.
 *
 * @package RevenueLeakScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = array(
    'scan_frequency'   => get_option( 'rls_scan_frequency', 'weekly' ),
    'email_reports'    => get_option( 'rls_email_reports', 'yes' ),
    'report_email'     => get_option( 'rls_report_email', get_option( 'admin_email' ) ),
    'monthly_visitors' => get_option( 'rls_monthly_visitors', 0 ),
    'monthly_orders'   => get_option( 'rls_monthly_orders', 0 ),
    'avg_order_value'  => get_option( 'rls_avg_order_value', 0 ),
    'scan_modules'     => get_option( 'rls_scan_modules', array( 'checkout', 'product', 'performance', 'mobile', 'seo', 'trust' ) ),
);
?>
<div class="rls-app" id="rls-settings-app">
    <div class="rls-app__header">
        <div class="rls-app__header-left">
            <div class="rls-app__logo">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                    <rect width="32" height="32" rx="8" fill="url(#logo-gradient4)"/>
                    <path d="M8 22L12 14L16 18L20 10L24 16" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="24" cy="16" r="2" fill="#FF6B6B"/>
                    <defs>
                        <linearGradient id="logo-gradient4" x1="0" y1="0" x2="32" y2="32">
                            <stop stop-color="#667EEA"/>
                            <stop offset="1" stop-color="#764BA2"/>
                        </linearGradient>
                    </defs>
                </svg>
                <h1 class="rls-app__title"><?php esc_html_e( 'Settings', 'revenue-leak-scanner' ); ?></h1>
            </div>
        </div>
    </div>

    <form id="rls-settings-form" class="rls-settings">
        <!-- Store Metrics Section -->
        <div class="rls-settings__section">
            <div class="rls-settings__section-header">
                <h3><?php esc_html_e( 'Store Metrics', 'revenue-leak-scanner' ); ?></h3>
                <p class="rls-settings__section-desc"><?php esc_html_e( 'Help us calculate more accurate revenue impact estimates. We auto-detect what we can from WooCommerce, but manual values improve accuracy.', 'revenue-leak-scanner' ); ?></p>
            </div>
            <div class="rls-settings__fields">
                <div class="rls-field">
                    <label for="rls-monthly-visitors" class="rls-field__label"><?php esc_html_e( 'Monthly Visitors (estimated)', 'revenue-leak-scanner' ); ?></label>
                    <input type="number" id="rls-monthly-visitors" name="monthly_visitors" class="rls-field__input" value="<?php echo esc_attr( $settings['monthly_visitors'] ); ?>" min="0" placeholder="e.g., 10000">
                    <span class="rls-field__hint"><?php esc_html_e( 'Check Google Analytics for this number. Leave at 0 to auto-estimate.', 'revenue-leak-scanner' ); ?></span>
                </div>
                <div class="rls-field">
                    <label for="rls-monthly-orders" class="rls-field__label"><?php esc_html_e( 'Monthly Orders (override)', 'revenue-leak-scanner' ); ?></label>
                    <input type="number" id="rls-monthly-orders" name="monthly_orders" class="rls-field__input" value="<?php echo esc_attr( $settings['monthly_orders'] ); ?>" min="0" placeholder="Auto-detected from WooCommerce">
                    <span class="rls-field__hint"><?php esc_html_e( 'Leave at 0 to auto-detect from WooCommerce orders.', 'revenue-leak-scanner' ); ?></span>
                </div>
                <div class="rls-field">
                    <label for="rls-avg-order-value" class="rls-field__label"><?php esc_html_e( 'Average Order Value (override)', 'revenue-leak-scanner' ); ?></label>
                    <input type="number" id="rls-avg-order-value" name="avg_order_value" class="rls-field__input" value="<?php echo esc_attr( $settings['avg_order_value'] ); ?>" min="0" step="0.01" placeholder="Auto-detected from WooCommerce">
                    <span class="rls-field__hint"><?php esc_html_e( 'Leave at 0 to auto-detect from WooCommerce orders.', 'revenue-leak-scanner' ); ?></span>
                </div>
            </div>
        </div>

        <!-- Scan Settings Section -->
        <div class="rls-settings__section">
            <div class="rls-settings__section-header">
                <h3><?php esc_html_e( 'Scan Settings', 'revenue-leak-scanner' ); ?></h3>
                <p class="rls-settings__section-desc"><?php esc_html_e( 'Configure how and when scans run automatically.', 'revenue-leak-scanner' ); ?></p>
            </div>
            <div class="rls-settings__fields">
                <div class="rls-field">
                    <label for="rls-scan-frequency" class="rls-field__label"><?php esc_html_e( 'Auto-Scan Frequency', 'revenue-leak-scanner' ); ?></label>
                    <select id="rls-scan-frequency" name="scan_frequency" class="rls-field__select">
                        <option value="daily" <?php selected( $settings['scan_frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'revenue-leak-scanner' ); ?></option>
                        <option value="weekly" <?php selected( $settings['scan_frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'revenue-leak-scanner' ); ?></option>
                        <option value="monthly" <?php selected( $settings['scan_frequency'], 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'revenue-leak-scanner' ); ?></option>
                    </select>
                </div>
                <div class="rls-field">
                    <label class="rls-field__label"><?php esc_html_e( 'Scan Modules', 'revenue-leak-scanner' ); ?></label>
                    <div class="rls-field__checkboxes">
                        <?php
                        $modules = array(
                            'checkout'    => __( 'Checkout Flow', 'revenue-leak-scanner' ),
                            'product'     => __( 'Product Pages', 'revenue-leak-scanner' ),
                            'performance' => __( 'Performance', 'revenue-leak-scanner' ),
                            'mobile'      => __( 'Mobile UX', 'revenue-leak-scanner' ),
                            'seo'         => __( 'SEO', 'revenue-leak-scanner' ),
                            'trust'       => __( 'Trust Signals', 'revenue-leak-scanner' ),
                        );
                        foreach ( $modules as $key => $label ) :
                        ?>
                        <label class="rls-checkbox">
                            <input type="checkbox" name="scan_modules[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $settings['scan_modules'], true ) ); ?>>
                            <span class="rls-checkbox__mark"></span>
                            <span class="rls-checkbox__label"><?php echo esc_html( $label ); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Reports Section -->
        <div class="rls-settings__section">
            <div class="rls-settings__section-header">
                <h3><?php esc_html_e( 'Email Reports', 'revenue-leak-scanner' ); ?></h3>
                <p class="rls-settings__section-desc"><?php esc_html_e( 'Get scan results delivered to your inbox.', 'revenue-leak-scanner' ); ?></p>
            </div>
            <div class="rls-settings__fields">
                <div class="rls-field">
                    <label class="rls-toggle">
                        <input type="checkbox" name="email_reports" value="yes" <?php checked( $settings['email_reports'], 'yes' ); ?>>
                        <span class="rls-toggle__slider"></span>
                        <span class="rls-toggle__label"><?php esc_html_e( 'Send email reports after each scan', 'revenue-leak-scanner' ); ?></span>
                    </label>
                </div>
                <div class="rls-field">
                    <label for="rls-report-email" class="rls-field__label"><?php esc_html_e( 'Report Email Address', 'revenue-leak-scanner' ); ?></label>
                    <input type="email" id="rls-report-email" name="report_email" class="rls-field__input" value="<?php echo esc_attr( $settings['report_email'] ); ?>">
                </div>
            </div>
        </div>

        <div class="rls-settings__actions">
            <button type="submit" class="rls-btn rls-btn--primary"><?php esc_html_e( 'Save Settings', 'revenue-leak-scanner' ); ?></button>
            <span class="rls-settings__saved" id="rls-save-indicator" style="display: none;"><?php esc_html_e( 'Settings saved!', 'revenue-leak-scanner' ); ?></span>
        </div>
    </form>
</div>
