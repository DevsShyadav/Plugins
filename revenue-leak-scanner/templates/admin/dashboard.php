<?php
/**
 * Dashboard Template.
 *
 * @package RevenueLeakScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_welcome = isset( $_GET['welcome'] ) && '1' === $_GET['welcome'];
$has_first_scan = get_option( 'rls_first_scan', false );
?>
<div class="rls-app" id="rls-app">
    <div class="rls-app__header">
        <div class="rls-app__header-left">
            <div class="rls-app__logo">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                    <rect width="32" height="32" rx="8" fill="url(#logo-gradient)"/>
                    <path d="M8 22L12 14L16 18L20 10L24 16" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="24" cy="16" r="2" fill="#FF6B6B"/>
                    <defs>
                        <linearGradient id="logo-gradient" x1="0" y1="0" x2="32" y2="32">
                            <stop stop-color="#667EEA"/>
                            <stop offset="1" stop-color="#764BA2"/>
                        </linearGradient>
                    </defs>
                </svg>
                <h1 class="rls-app__title"><?php esc_html_e( 'Revenue Leak Scanner', 'revenue-leak-scanner' ); ?></h1>
            </div>
        </div>
        <div class="rls-app__header-right">
            <button type="button" class="rls-btn rls-btn--primary" id="rls-start-scan">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M8 1V15M1 8H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <?php esc_html_e( 'New Scan', 'revenue-leak-scanner' ); ?>
            </button>
        </div>
    </div>

    <?php if ( $is_welcome || ! $has_first_scan ) : ?>
    <!-- Welcome / First Run State -->
    <div class="rls-welcome" id="rls-welcome">
        <div class="rls-welcome__card">
            <div class="rls-welcome__icon">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
                    <circle cx="32" cy="32" r="30" stroke="url(#welcome-gradient)" stroke-width="2"/>
                    <path d="M20 38L26 28L32 34L38 22L44 32" stroke="url(#welcome-gradient)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="44" cy="32" r="3" fill="#FF6B6B">
                        <animate attributeName="r" values="3;4;3" dur="1.5s" repeatCount="indefinite"/>
                    </circle>
                    <defs>
                        <linearGradient id="welcome-gradient" x1="0" y1="0" x2="64" y2="64">
                            <stop stop-color="#667EEA"/>
                            <stop offset="1" stop-color="#764BA2"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            <h2 class="rls-welcome__title"><?php esc_html_e( 'Discover Where Your Store Leaks Money', 'revenue-leak-scanner' ); ?></h2>
            <p class="rls-welcome__desc"><?php esc_html_e( 'One click reveals exactly how much revenue you\'re losing and from where. Get actionable fixes with dollar amounts attached.', 'revenue-leak-scanner' ); ?></p>
            <button type="button" class="rls-btn rls-btn--primary rls-btn--lg" id="rls-first-scan">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="2"/>
                    <path d="M10 6V10L12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <?php esc_html_e( 'Run Your First Scan', 'revenue-leak-scanner' ); ?>
            </button>
            <p class="rls-welcome__time"><?php esc_html_e( 'Takes about 30 seconds', 'revenue-leak-scanner' ); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scanning Animation -->
    <div class="rls-scanning" id="rls-scanning" style="display: none;">
        <div class="rls-scanning__card">
            <div class="rls-scanning__animation">
                <div class="rls-scanning__pulse"></div>
                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" class="rls-scanning__icon">
                    <circle cx="24" cy="24" r="20" stroke="#667EEA" stroke-width="2" stroke-dasharray="4 4">
                        <animateTransform attributeName="transform" type="rotate" from="0 24 24" to="360 24 24" dur="3s" repeatCount="indefinite"/>
                    </circle>
                    <path d="M16 30L20 22L24 26L28 18L32 24" stroke="#667EEA" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3 class="rls-scanning__title"><?php esc_html_e( 'Scanning Your Store...', 'revenue-leak-scanner' ); ?></h3>
            <p class="rls-scanning__status" id="rls-scan-status"><?php esc_html_e( 'Analyzing checkout flow...', 'revenue-leak-scanner' ); ?></p>
            <div class="rls-scanning__progress">
                <div class="rls-scanning__progress-bar" id="rls-scan-progress"></div>
            </div>
        </div>
    </div>

    <!-- Dashboard Content (shown after scan) -->
    <div class="rls-dashboard" id="rls-dashboard" style="<?php echo $has_first_scan ? '' : 'display: none;'; ?>">
        <!-- Revenue Loss Summary Card -->
        <div class="rls-summary" id="rls-summary">
            <div class="rls-summary__main">
                <div class="rls-summary__loss">
                    <span class="rls-summary__label"><?php esc_html_e( 'Monthly Revenue Leaking', 'revenue-leak-scanner' ); ?></span>
                    <span class="rls-summary__amount" id="rls-total-loss">$0</span>
                    <span class="rls-summary__trend" id="rls-trend"></span>
                </div>
                <div class="rls-summary__stats">
                    <div class="rls-summary__stat">
                        <span class="rls-summary__stat-value" id="rls-leak-count">0</span>
                        <span class="rls-summary__stat-label"><?php esc_html_e( 'Issues Found', 'revenue-leak-scanner' ); ?></span>
                    </div>
                    <div class="rls-summary__stat">
                        <span class="rls-summary__stat-value" id="rls-critical-count">0</span>
                        <span class="rls-summary__stat-label"><?php esc_html_e( 'Critical', 'revenue-leak-scanner' ); ?></span>
                    </div>
                    <div class="rls-summary__stat">
                        <span class="rls-summary__stat-value" id="rls-fixed-count">0</span>
                        <span class="rls-summary__stat-label"><?php esc_html_e( 'Fixed', 'revenue-leak-scanner' ); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Breakdown -->
        <div class="rls-categories" id="rls-categories">
            <h3 class="rls-section__title"><?php esc_html_e( 'Leak Categories', 'revenue-leak-scanner' ); ?></h3>
            <div class="rls-categories__grid" id="rls-category-grid">
                <!-- Populated by JavaScript -->
            </div>
        </div>

        <!-- Revenue Trend Chart -->
        <div class="rls-chart-card">
            <h3 class="rls-section__title"><?php esc_html_e( 'Revenue Leak Trend', 'revenue-leak-scanner' ); ?></h3>
            <div class="rls-chart__container">
                <canvas id="rls-trend-chart" height="200"></canvas>
            </div>
        </div>

        <!-- Top Leaks List -->
        <div class="rls-leaks-card">
            <div class="rls-leaks-card__header">
                <h3 class="rls-section__title"><?php esc_html_e( 'Top Revenue Leaks', 'revenue-leak-scanner' ); ?></h3>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rls-results' ) ); ?>" class="rls-link">
                    <?php esc_html_e( 'View All', 'revenue-leak-scanner' ); ?> &rarr;
                </a>
            </div>
            <div class="rls-leaks__list" id="rls-top-leaks">
                <!-- Populated by JavaScript -->
            </div>
        </div>
    </div>
</div>
