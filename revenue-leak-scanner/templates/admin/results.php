<?php
/**
 * Results Template.
 *
 * @package RevenueLeakScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="rls-app" id="rls-results-app">
    <div class="rls-app__header">
        <div class="rls-app__header-left">
            <div class="rls-app__logo">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                    <rect width="32" height="32" rx="8" fill="url(#logo-gradient2)"/>
                    <path d="M8 22L12 14L16 18L20 10L24 16" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="24" cy="16" r="2" fill="#FF6B6B"/>
                    <defs>
                        <linearGradient id="logo-gradient2" x1="0" y1="0" x2="32" y2="32">
                            <stop stop-color="#667EEA"/>
                            <stop offset="1" stop-color="#764BA2"/>
                        </linearGradient>
                    </defs>
                </svg>
                <h1 class="rls-app__title"><?php esc_html_e( 'Scan Results', 'revenue-leak-scanner' ); ?></h1>
            </div>
        </div>
        <div class="rls-app__header-right">
            <div class="rls-filters">
                <select id="rls-filter-category" class="rls-select">
                    <option value=""><?php esc_html_e( 'All Categories', 'revenue-leak-scanner' ); ?></option>
                    <option value="checkout"><?php esc_html_e( 'Checkout', 'revenue-leak-scanner' ); ?></option>
                    <option value="product"><?php esc_html_e( 'Products', 'revenue-leak-scanner' ); ?></option>
                    <option value="performance"><?php esc_html_e( 'Performance', 'revenue-leak-scanner' ); ?></option>
                    <option value="mobile"><?php esc_html_e( 'Mobile', 'revenue-leak-scanner' ); ?></option>
                    <option value="seo"><?php esc_html_e( 'SEO', 'revenue-leak-scanner' ); ?></option>
                    <option value="trust"><?php esc_html_e( 'Trust', 'revenue-leak-scanner' ); ?></option>
                </select>
                <select id="rls-filter-severity" class="rls-select">
                    <option value=""><?php esc_html_e( 'All Severities', 'revenue-leak-scanner' ); ?></option>
                    <option value="critical"><?php esc_html_e( 'Critical', 'revenue-leak-scanner' ); ?></option>
                    <option value="high"><?php esc_html_e( 'High', 'revenue-leak-scanner' ); ?></option>
                    <option value="medium"><?php esc_html_e( 'Medium', 'revenue-leak-scanner' ); ?></option>
                    <option value="low"><?php esc_html_e( 'Low', 'revenue-leak-scanner' ); ?></option>
                </select>
            </div>
            <button type="button" class="rls-btn rls-btn--primary" id="rls-start-scan">
                <?php esc_html_e( 'New Scan', 'revenue-leak-scanner' ); ?>
            </button>
        </div>
    </div>

    <!-- Results Summary Bar -->
    <div class="rls-results-summary" id="rls-results-summary">
        <div class="rls-results-summary__item">
            <span class="rls-results-summary__value" id="rls-results-total-loss">$0</span>
            <span class="rls-results-summary__label"><?php esc_html_e( 'Total Monthly Loss', 'revenue-leak-scanner' ); ?></span>
        </div>
        <div class="rls-results-summary__item">
            <span class="rls-results-summary__value" id="rls-results-total-leaks">0</span>
            <span class="rls-results-summary__label"><?php esc_html_e( 'Total Issues', 'revenue-leak-scanner' ); ?></span>
        </div>
        <div class="rls-results-summary__item">
            <span class="rls-results-summary__value" id="rls-results-scan-date">-</span>
            <span class="rls-results-summary__label"><?php esc_html_e( 'Last Scanned', 'revenue-leak-scanner' ); ?></span>
        </div>
    </div>

    <!-- Leaks List -->
    <div class="rls-results-list" id="rls-results-list">
        <!-- Populated by JavaScript -->
        <div class="rls-loading" id="rls-results-loading">
            <div class="rls-loading__spinner"></div>
            <p><?php esc_html_e( 'Loading results...', 'revenue-leak-scanner' ); ?></p>
        </div>
    </div>
</div>
