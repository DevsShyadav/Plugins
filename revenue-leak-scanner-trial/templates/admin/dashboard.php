<?php
/**
 * Dashboard Template - Premium Redesign.
 *
 * @package RevenueLeakScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_welcome = isset( $_GET['welcome'] ) && '1' === $_GET['welcome'];
$has_first_scan = get_option( 'rls_first_scan', false );
$trial_active = \RevenueLeakScanner\Trial::is_active();
$trial_expired = \RevenueLeakScanner\Trial::is_expired();
$trial_remaining = \RevenueLeakScanner\Trial::get_remaining_formatted();
$trial_seconds = \RevenueLeakScanner\Trial::get_remaining_seconds();
$is_pro = \RevenueLeakScanner\Trial::is_pro();
?>
<div class="rls-app" id="rls-app">

    <?php if ( ! $is_pro ) : ?>
    <!-- Trial Timer Bar -->
    <div class="rls-trial-bar" style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;margin-bottom:20px;border-radius:12px;<?php echo $trial_expired ? 'background:#FEF2F2;border:1px solid #FECACA;' : 'background:linear-gradient(135deg,#EEF2FF,#F5F3FF);border:1px solid #E0E7FF;'; ?>">
        <div style="display:flex;align-items:center;gap:12px;">
            <span style="font-size:20px;"><?php echo $trial_expired ? '🔒' : '⏱️'; ?></span>
            <div>
                <?php if ( $trial_expired ) : ?>
                    <div style="font-weight:700;color:#DC2626;font-size:14px;">Trial Expired</div>
                    <div style="color:#6B7280;font-size:12px;">Upgrade for lifetime access to all features</div>
                <?php else : ?>
                    <div style="font-weight:600;color:#4338CA;font-size:13px;">24-Hour Trial Active</div>
                    <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                        <span id="rls-trial-timer-value" style="font-family:monospace;font-size:20px;font-weight:800;color:#1F2937;"><?php echo esc_html( $trial_remaining ); ?></span>
                        <span style="color:#6B7280;font-size:11px;">remaining</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <?php if ( ! $trial_expired ) : ?>
            <div style="width:120px;height:6px;background:#E5E7EB;border-radius:99px;overflow:hidden;">
                <div id="rls-trial-progress" style="height:100%;width:<?php echo round( ( $trial_seconds / 86400 ) * 100 ); ?>%;background:linear-gradient(90deg,#667EEA,#764BA2);border-radius:99px;transition:width 1s;"></div>
            </div>
            <?php endif; ?>
            <a href="https://revenueleakscanner.com/#pricing" target="_blank" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:linear-gradient(135deg,#667EEA,#764BA2);color:#fff;font-weight:700;font-size:12px;border-radius:8px;text-decoration:none;white-space:nowrap;">🚀 Upgrade — $19</a>
        </div>
    </div>

    <!-- License Key Input (collapsible) -->
    <div style="margin-bottom:16px;text-align:right;">
        <button onclick="document.getElementById('rls-license-box').classList.toggle('hidden')" style="background:none;border:none;color:#667EEA;font-size:12px;font-weight:600;cursor:pointer;">🔑 Enter License Key</button>
        <div id="rls-license-box" class="hidden" style="margin-top:8px;display:flex;gap:8px;justify-content:flex-end;">
            <input type="text" id="rls-license-input" placeholder="Enter your license key" style="padding:8px 12px;border:1px solid #E5E7EB;border-radius:6px;font-size:13px;width:250px;">
            <button onclick="activateLicense()" style="padding:8px 14px;background:#10B981;color:#fff;font-weight:600;font-size:12px;border:none;border-radius:6px;cursor:pointer;">Activate</button>
        </div>
    </div>
    <script>
    function activateLicense(){
        var key=document.getElementById('rls-license-input').value.trim();
        if(!key){alert('Enter a license key');return;}
        jQuery.post(rlsAdmin.ajaxUrl,{action:'rls_activate_license',nonce:rlsAdmin.nonce,license_key:key},function(r){
            if(r.success){alert('✅ '+r.data.message);location.reload();}
            else alert('❌ '+(r.data?.message||'Invalid key'));
        });
    }
    </script>
    <?php endif; ?>

    <!-- Header -->
    <div class="rls-app__header">
        <div class="rls-app__header-left">
            <div class="rls-app__logo">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                    <rect width="32" height="32" rx="8" fill="url(#logo-gradient)"/>
                    <path d="M8 22L12 14L16 18L20 10L24 16" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="24" cy="16" r="2" fill="#FF6B6B"/>
                    <defs><linearGradient id="logo-gradient" x1="0" y1="0" x2="32" y2="32"><stop stop-color="#667EEA"/><stop offset="1" stop-color="#764BA2"/></linearGradient></defs>
                </svg>
                <h1 class="rls-app__title"><?php esc_html_e( 'Revenue Leak Scanner', 'revenue-leak-scanner' ); ?></h1>
            </div>
            <span class="rls-app__subtitle"><?php esc_html_e( 'Find exactly where your store loses money', 'revenue-leak-scanner' ); ?></span>
        </div>
        <div class="rls-app__header-right">
            <span class="rls-app__last-scan" id="rls-last-scan-time"></span>
            <button type="button" class="rls-btn rls-btn--primary" id="rls-start-scan">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M8 5v3l2 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <?php esc_html_e( 'Run New Scan', 'revenue-leak-scanner' ); ?>
            </button>
        </div>
    </div>

    <?php if ( $is_welcome || ! $has_first_scan ) : ?>
    <!-- Welcome State -->
    <div class="rls-welcome" id="rls-welcome">
        <div class="rls-welcome__card">
            <div class="rls-welcome__badge"><?php esc_html_e( 'Get Started', 'revenue-leak-scanner' ); ?></div>
            <h2 class="rls-welcome__title"><?php esc_html_e( 'Your Store is Leaking Revenue', 'revenue-leak-scanner' ); ?></h2>
            <p class="rls-welcome__desc"><?php esc_html_e( 'Most WooCommerce stores lose $500-$5,000/month from preventable issues. One scan reveals exactly where — with dollar amounts for each problem.', 'revenue-leak-scanner' ); ?></p>
            <div class="rls-welcome__features">
                <div class="rls-welcome__feature"><span class="rls-welcome__feature-icon">🛒</span><span>Checkout abandonment analysis</span></div>
                <div class="rls-welcome__feature"><span class="rls-welcome__feature-icon">📦</span><span>Product page optimization</span></div>
                <div class="rls-welcome__feature"><span class="rls-welcome__feature-icon">⚡</span><span>Performance bottlenecks</span></div>
                <div class="rls-welcome__feature"><span class="rls-welcome__feature-icon">📱</span><span>Mobile UX problems</span></div>
                <div class="rls-welcome__feature"><span class="rls-welcome__feature-icon">🔍</span><span>SEO revenue leaks</span></div>
                <div class="rls-welcome__feature"><span class="rls-welcome__feature-icon">🛡️</span><span>Trust signal gaps</span></div>
            </div>
            <button type="button" class="rls-btn rls-btn--primary rls-btn--lg" id="rls-first-scan">
                <?php esc_html_e( 'Scan My Store Now', 'revenue-leak-scanner' ); ?>
            </button>
            <p class="rls-welcome__time"><?php esc_html_e( 'Takes ~30 seconds. No impact on site speed.', 'revenue-leak-scanner' ); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scanning Animation -->
    <div class="rls-scanning" id="rls-scanning" style="display: none;">
        <div class="rls-scanning__card">
            <div class="rls-scanning__animation">
                <div class="rls-scanning__pulse"></div>
                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" class="rls-scanning__icon">
                    <circle cx="24" cy="24" r="20" stroke="#667EEA" stroke-width="2" stroke-dasharray="4 4"><animateTransform attributeName="transform" type="rotate" from="0 24 24" to="360 24 24" dur="3s" repeatCount="indefinite"/></circle>
                    <path d="M16 30L20 22L24 26L28 18L32 24" stroke="#667EEA" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3 class="rls-scanning__title"><?php esc_html_e( 'Scanning Your Store...', 'revenue-leak-scanner' ); ?></h3>
            <p class="rls-scanning__status" id="rls-scan-status"><?php esc_html_e( 'Analyzing checkout flow...', 'revenue-leak-scanner' ); ?></p>
            <div class="rls-scanning__progress"><div class="rls-scanning__progress-bar" id="rls-scan-progress"></div></div>
            <div class="rls-scanning__modules" id="rls-scan-modules">
                <div class="rls-scanning__module" data-module="checkout"><span>🛒</span> Checkout</div>
                <div class="rls-scanning__module" data-module="product"><span>📦</span> Products</div>
                <div class="rls-scanning__module" data-module="performance"><span>⚡</span> Performance</div>
                <div class="rls-scanning__module" data-module="mobile"><span>📱</span> Mobile</div>
                <div class="rls-scanning__module" data-module="seo"><span>🔍</span> SEO</div>
                <div class="rls-scanning__module" data-module="trust"><span>🛡️</span> Trust</div>
            </div>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div class="rls-dashboard" id="rls-dashboard" style="<?php echo $has_first_scan ? '' : 'display: none;'; ?>">

        <!-- Hero Summary -->
        <div class="rls-hero" id="rls-hero">
            <div class="rls-hero__main">
                <div class="rls-hero__loss">
                    <span class="rls-hero__label"><?php esc_html_e( 'Your Store is Losing', 'revenue-leak-scanner' ); ?></span>
                    <span class="rls-hero__amount" id="rls-total-loss">$0</span>
                    <span class="rls-hero__period"><?php esc_html_e( 'per month', 'revenue-leak-scanner' ); ?></span>
                </div>
                <div class="rls-hero__trend" id="rls-trend"></div>
            </div>
            <div class="rls-hero__metrics">
                <div class="rls-hero__metric">
                    <div class="rls-hero__metric-value" id="rls-leak-count">0</div>
                    <div class="rls-hero__metric-label"><?php esc_html_e( 'Issues Found', 'revenue-leak-scanner' ); ?></div>
                </div>
                <div class="rls-hero__metric rls-hero__metric--critical">
                    <div class="rls-hero__metric-value" id="rls-critical-count">0</div>
                    <div class="rls-hero__metric-label"><?php esc_html_e( 'Critical', 'revenue-leak-scanner' ); ?></div>
                </div>
                <div class="rls-hero__metric rls-hero__metric--high">
                    <div class="rls-hero__metric-value" id="rls-high-count">0</div>
                    <div class="rls-hero__metric-label"><?php esc_html_e( 'High Priority', 'revenue-leak-scanner' ); ?></div>
                </div>
                <div class="rls-hero__metric rls-hero__metric--fixed">
                    <div class="rls-hero__metric-value" id="rls-fixed-count">0</div>
                    <div class="rls-hero__metric-label"><?php esc_html_e( 'Fixed', 'revenue-leak-scanner' ); ?></div>
                </div>
            </div>
        </div>

        <!-- Category Tabs -->
        <div class="rls-tabs" id="rls-tabs">
            <div class="rls-tabs__header">
                <button class="rls-tabs__btn rls-tabs__btn--active" data-tab="all"><?php esc_html_e( 'All Issues', 'revenue-leak-scanner' ); ?> <span class="rls-tabs__count" id="rls-tab-count-all">0</span></button>
                <button class="rls-tabs__btn" data-tab="checkout">🛒 <?php esc_html_e( 'Checkout', 'revenue-leak-scanner' ); ?> <span class="rls-tabs__count" id="rls-tab-count-checkout">0</span></button>
                <button class="rls-tabs__btn" data-tab="product">📦 <?php esc_html_e( 'Products', 'revenue-leak-scanner' ); ?> <span class="rls-tabs__count" id="rls-tab-count-product">0</span></button>
                <button class="rls-tabs__btn" data-tab="performance">⚡ <?php esc_html_e( 'Speed', 'revenue-leak-scanner' ); ?> <span class="rls-tabs__count" id="rls-tab-count-performance">0</span></button>
                <button class="rls-tabs__btn" data-tab="mobile">📱 <?php esc_html_e( 'Mobile', 'revenue-leak-scanner' ); ?> <span class="rls-tabs__count" id="rls-tab-count-mobile">0</span></button>
                <button class="rls-tabs__btn" data-tab="seo">🔍 <?php esc_html_e( 'SEO', 'revenue-leak-scanner' ); ?> <span class="rls-tabs__count" id="rls-tab-count-seo">0</span></button>
                <button class="rls-tabs__btn" data-tab="trust">🛡️ <?php esc_html_e( 'Trust', 'revenue-leak-scanner' ); ?> <span class="rls-tabs__count" id="rls-tab-count-trust">0</span></button>
            </div>
        </div>

        <!-- Leak Cards (Detailed) -->
        <div class="rls-leak-cards" id="rls-leak-cards">
            <!-- Populated by JavaScript with detailed expandable cards -->
        </div>

        <!-- Bottom Section: Chart + Store Health -->
        <div class="rls-bottom-grid">
            <!-- Revenue Trend Chart -->
            <div class="rls-chart-card">
                <h3 class="rls-section__title"><?php esc_html_e( 'Revenue Leak Trend', 'revenue-leak-scanner' ); ?></h3>
                <div class="rls-chart__container">
                    <canvas id="rls-trend-chart" height="220"></canvas>
                </div>
            </div>

            <!-- Store Health Score -->
            <div class="rls-health-card" id="rls-health-card">
                <h3 class="rls-section__title"><?php esc_html_e( 'Store Health Score', 'revenue-leak-scanner' ); ?></h3>
                <div class="rls-health__score-ring">
                    <svg viewBox="0 0 120 120" class="rls-health__svg">
                        <circle cx="60" cy="60" r="52" fill="none" stroke="#E5E7EB" stroke-width="8"/>
                        <circle cx="60" cy="60" r="52" fill="none" stroke="url(#health-gradient)" stroke-width="8" stroke-linecap="round" stroke-dasharray="327" stroke-dashoffset="327" id="rls-health-ring" transform="rotate(-90 60 60)"/>
                        <defs><linearGradient id="health-gradient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#10B981"/><stop offset="1" stop-color="#059669"/></linearGradient></defs>
                    </svg>
                    <div class="rls-health__score-value" id="rls-health-score">--</div>
                </div>
                <div class="rls-health__breakdown" id="rls-health-breakdown">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="rls-quick-actions">
            <h3 class="rls-section__title"><?php esc_html_e( 'Quick Actions', 'revenue-leak-scanner' ); ?></h3>
            <div class="rls-quick-actions__grid">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rls-results' ) ); ?>" class="rls-quick-action">
                    <span class="rls-quick-action__icon">📋</span>
                    <span class="rls-quick-action__text"><?php esc_html_e( 'View Full Report', 'revenue-leak-scanner' ); ?></span>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rls-history' ) ); ?>" class="rls-quick-action">
                    <span class="rls-quick-action__icon">📈</span>
                    <span class="rls-quick-action__text"><?php esc_html_e( 'Scan History', 'revenue-leak-scanner' ); ?></span>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rls-settings' ) ); ?>" class="rls-quick-action">
                    <span class="rls-quick-action__icon">⚙️</span>
                    <span class="rls-quick-action__text"><?php esc_html_e( 'Settings', 'revenue-leak-scanner' ); ?></span>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings' ) ); ?>" class="rls-quick-action">
                    <span class="rls-quick-action__icon">🛒</span>
                    <span class="rls-quick-action__text"><?php esc_html_e( 'WooCommerce Settings', 'revenue-leak-scanner' ); ?></span>
                </a>
            </div>
        </div>
    </div>
</div>
