<?php
/**
 * Revenue Calculator.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calculates dollar amounts for revenue leaks based on store data.
 */
class Revenue_Calculator {

    /**
     * Store metrics cache.
     *
     * @var array
     */
    private $metrics = array();

    /**
     * Constructor - loads store metrics.
     */
    public function __construct() {
        $this->load_metrics();
    }

    /**
     * Load store metrics from WooCommerce data.
     * Uses smart fallbacks so calculations never return $0.
     */
    private function load_metrics() {
        // Clear any stale cached zeros first
        $this->metrics = array(
            'avg_order_value'       => $this->get_average_order_value(),
            'monthly_orders'        => $this->get_monthly_orders(),
            'monthly_revenue'       => $this->get_monthly_revenue(),
            'monthly_visitors'      => $this->get_monthly_visitors(),
            'conversion_rate'       => 0,
            'cart_abandonment_rate' => $this->get_cart_abandonment_rate(),
            'returning_customer_rate' => $this->get_returning_customer_rate(),
        );

        // CRITICAL: If monthly_revenue is still 0, compute from orders × AOV
        if ( $this->metrics['monthly_revenue'] <= 0 && $this->metrics['monthly_orders'] > 0 ) {
            $this->metrics['monthly_revenue'] = $this->metrics['monthly_orders'] * $this->metrics['avg_order_value'];
        }

        // If STILL 0 (no orders at all), use a reasonable estimate based on products
        if ( $this->metrics['monthly_revenue'] <= 0 ) {
            $this->metrics['monthly_revenue'] = $this->estimate_revenue_from_store();
        }

        // Ensure monthly_orders is never 0 for calculations
        if ( $this->metrics['monthly_orders'] <= 0 ) {
            // Estimate: even small stores get at least a few orders
            $this->metrics['monthly_orders'] = max( 1, intval( $this->metrics['monthly_revenue'] / $this->metrics['avg_order_value'] ) );
        }

        $this->metrics['conversion_rate'] = $this->get_conversion_rate();
    }

    /**
     * Estimate monthly revenue from store data when no orders exist.
     * Uses product count and average price as a baseline estimate.
     *
     * @return float Estimated monthly revenue.
     */
    private function estimate_revenue_from_store() {
        global $wpdb;

        // Check manual settings first
        $stored_orders = (int) get_option( 'rls_monthly_orders', 0 );
        $stored_aov = (float) get_option( 'rls_avg_order_value', 0 );
        if ( $stored_orders > 0 && $stored_aov > 0 ) {
            return $stored_orders * $stored_aov;
        }

        // Count published products and get average price
        $product_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"
        );

        if ( $product_count <= 0 ) {
            // Absolute minimum fallback: assume small store doing $2000/month
            return 2000.0;
        }

        $avg_price = (float) $wpdb->get_var(
            "SELECT AVG(CAST(meta_value AS DECIMAL(10,2))) FROM {$wpdb->postmeta}
             WHERE meta_key = '_regular_price' AND meta_value != '' AND meta_value > 0
             AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish')"
        );

        if ( $avg_price <= 0 ) {
            $avg_price = 50.0;
        }

        // Conservative estimate: stores with products typically get at least
        // 0.5-2 orders per product per month on average
        $estimated_monthly_orders = max( 10, intval( $product_count * 0.8 ) );
        $estimated_revenue = $estimated_monthly_orders * $avg_price;

        // Cap between reasonable bounds
        return max( 1000.0, min( $estimated_revenue, 100000.0 ) );
    }

    /**
     * Get all calculated metrics.
     *
     * @return array
     */
    public function get_metrics() {
        return $this->metrics;
    }

    /**
     * Calculate revenue impact for a specific leak type.
     *
     * @param string $leak_type   Type of leak.
     * @param array  $leak_data   Additional data for calculation.
     * @return float Monthly revenue impact in dollars.
     */
    public function calculate_impact( $leak_type, $leak_data = array() ) {
        $method = "calculate_{$leak_type}_impact";

        if ( method_exists( $this, $method ) ) {
            return $this->$method( $leak_data );
        }

        return $this->calculate_generic_impact( $leak_data );
    }

    /**
     * Calculate checkout abandonment revenue impact.
     *
     * @param array $data Leak data including abandonment rate.
     * @return float
     */
    private function calculate_checkout_abandonment_impact( $data ) {
        $abandonment_rate = $data['abandonment_rate'] ?? 0.67;
        $industry_avg = 0.69; // Industry average abandonment rate

        // Only count the excess above industry average as "fixable"
        $excess_abandonment = max( 0, $abandonment_rate - 0.55 ); // 55% is best-in-class

        $potential_orders = $this->metrics['monthly_orders'] * ( $excess_abandonment / ( 1 - $abandonment_rate ) );
        $impact = $potential_orders * $this->metrics['avg_order_value'] * 0.3; // Conservative 30% recovery rate

        return round( $impact, 2 );
    }

    /**
     * Calculate slow page speed revenue impact.
     *
     * @param array $data Leak data including load time.
     * @return float
     */
    private function calculate_slow_speed_impact( $data ) {
        $load_time = $data['load_time'] ?? 3.0;
        $ideal_time = 2.0; // seconds

        if ( $load_time <= $ideal_time ) {
            return 0.0;
        }

        // Every additional second reduces conversions by ~7%
        $extra_seconds = $load_time - $ideal_time;
        $conversion_loss_percent = min( $extra_seconds * 0.07, 0.50 ); // Cap at 50%

        $lost_orders = $this->metrics['monthly_orders'] * $conversion_loss_percent;
        $impact = $lost_orders * $this->metrics['avg_order_value'];

        return round( $impact, 2 );
    }

    /**
     * Calculate missing reviews revenue impact.
     *
     * @param array $data Leak data including products without reviews count.
     * @return float
     */
    private function calculate_missing_reviews_impact( $data ) {
        $products_without_reviews = $data['count'] ?? 0;
        $total_products = $data['total_products'] ?? 1;

        // Products with reviews convert 270% better (Spiegel Research Center)
        $percent_without = $products_without_reviews / max( $total_products, 1 );
        $potential_conversion_lift = 0.12; // Conservative 12% lift
        $affected_revenue = $this->metrics['monthly_revenue'] * $percent_without;
        $impact = $affected_revenue * $potential_conversion_lift;

        return round( $impact, 2 );
    }

    /**
     * Calculate missing product images revenue impact.
     *
     * @param array $data Leak data.
     * @return float
     */
    private function calculate_missing_images_impact( $data ) {
        $products_affected = $data['count'] ?? 0;
        $total_products = $data['total_products'] ?? 1;

        $percent_affected = $products_affected / max( $total_products, 1 );
        // Products with multiple images get 9% more conversions
        $impact = $this->metrics['monthly_revenue'] * $percent_affected * 0.09;

        return round( $impact, 2 );
    }

    /**
     * Calculate mobile UX issues revenue impact.
     *
     * @param array $data Leak data.
     * @return float
     */
    private function calculate_mobile_ux_impact( $data ) {
        $issue_count = $data['issue_count'] ?? 0;
        $mobile_traffic_percent = $data['mobile_percent'] ?? 0.60;

        // Each mobile UX issue reduces mobile conversions by ~3%
        $conversion_loss = min( $issue_count * 0.03, 0.40 );
        $mobile_revenue = $this->metrics['monthly_revenue'] * $mobile_traffic_percent;
        $impact = $mobile_revenue * $conversion_loss;

        return round( $impact, 2 );
    }

    /**
     * Calculate missing trust signals revenue impact.
     *
     * @param array $data Leak data.
     * @return float
     */
    private function calculate_trust_signals_impact( $data ) {
        $missing_signals = $data['missing_count'] ?? 0;

        // Trust badges increase conversions by up to 42% (Baymard Institute)
        // Each missing signal ~5% impact
        $conversion_loss = min( $missing_signals * 0.05, 0.25 );
        $impact = $this->metrics['monthly_revenue'] * $conversion_loss;

        return round( $impact, 2 );
    }

    /**
     * Calculate SEO issues revenue impact.
     *
     * @param array $data Leak data.
     * @return float
     */
    private function calculate_seo_issues_impact( $data ) {
        $issue_count = $data['issue_count'] ?? 0;
        $organic_traffic_percent = $data['organic_percent'] ?? 0.40;

        // Each major SEO issue can reduce organic traffic by ~5%
        $traffic_loss = min( $issue_count * 0.05, 0.40 );
        $organic_revenue = $this->metrics['monthly_revenue'] * $organic_traffic_percent;
        $impact = $organic_revenue * $traffic_loss;

        return round( $impact, 2 );
    }

    /**
     * Calculate missing upsells/cross-sells revenue impact.
     *
     * @param array $data Leak data.
     * @return float
     */
    private function calculate_missing_upsells_impact( $data ) {
        $products_without = $data['count'] ?? 0;
        $total_products = $data['total_products'] ?? 1;

        $percent_without = $products_without / max( $total_products, 1 );
        // Upsells/cross-sells typically add 10-30% to AOV
        $potential_aov_increase = 0.15;
        $impact = $this->metrics['monthly_revenue'] * $percent_without * $potential_aov_increase;

        return round( $impact, 2 );
    }

    /**
     * Calculate generic impact for unknown leak types.
     *
     * @param array $data Leak data.
     * @return float
     */
    private function calculate_generic_impact( $data ) {
        $severity_multiplier = array(
            'critical' => 0.08,
            'high'     => 0.05,
            'medium'   => 0.03,
            'low'      => 0.01,
        );

        $severity = $data['severity'] ?? 'medium';
        $multiplier = $severity_multiplier[ $severity ] ?? 0.03;

        return round( $this->metrics['monthly_revenue'] * $multiplier, 2 );
    }

    /**
     * Get average order value from WooCommerce data.
     *
     * @return float
     */
    private function get_average_order_value() {
        $cached = Cache::get( 'avg_order_value' );
        if ( false !== $cached ) {
            return (float) $cached;
        }

        global $wpdb;

        $aov = $wpdb->get_var(
            "SELECT AVG(total_amount) FROM (
                SELECT 
                    CASE 
                        WHEN EXISTS (SELECT 1 FROM {$wpdb->prefix}wc_orders LIMIT 1)
                        THEN (SELECT AVG(total_amount) FROM {$wpdb->prefix}wc_orders WHERE status IN ('wc-completed', 'wc-processing') AND date_created_gmt >= DATE_SUB(NOW(), INTERVAL 30 DAY))
                        ELSE NULL
                    END as total_amount
            ) as subquery"
        );

        // Fallback to post meta if HPOS table doesn't exist
        if ( null === $aov ) {
            $aov = $wpdb->get_var(
                "SELECT AVG(meta_value) FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_order_total' 
                 AND post_id IN (
                    SELECT ID FROM {$wpdb->posts} 
                    WHERE post_type = 'shop_order' 
                    AND post_status IN ('wc-completed', 'wc-processing')
                    AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 )"
            );
        }

        $aov = $aov ? (float) $aov : 50.0; // Default $50 if no data

        // Also check stored option
        $stored = get_option( 'rls_avg_order_value', 0 );
        if ( $stored > 0 && $aov <= 0 ) {
            $aov = (float) $stored;
        }

        Cache::set( 'avg_order_value', $aov, 3600 );

        return $aov;
    }

    /**
     * Get monthly revenue.
     *
     * @return float
     */
    private function get_monthly_revenue() {
        $cached = Cache::get( 'monthly_revenue' );
        if ( false !== $cached ) {
            return (float) $cached;
        }

        global $wpdb;

        $revenue = $wpdb->get_var(
            "SELECT SUM(meta_value) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_order_total' 
             AND post_id IN (
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'shop_order' 
                AND post_status IN ('wc-completed', 'wc-processing')
                AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             )"
        );

        // Try HPOS table
        if ( null === $revenue || 0 == $revenue ) {
            $revenue = $wpdb->get_var(
                "SELECT SUM(total_amount) FROM {$wpdb->prefix}wc_orders 
                 WHERE status IN ('wc-completed', 'wc-processing') 
                 AND date_created_gmt >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
        }

        $revenue = $revenue ? (float) $revenue : 0.0;

        // Also check ALL TIME revenue if last 30 days is empty (new store)
        if ( $revenue <= 0 ) {
            $revenue = (float) $wpdb->get_var(
                "SELECT SUM(meta_value) FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_order_total' 
                 AND post_id IN (
                    SELECT ID FROM {$wpdb->posts} 
                    WHERE post_type = 'shop_order' 
                    AND post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending')
                 )"
            );
            $revenue = $revenue ? (float) $revenue : 0.0;
        }

        // Try HPOS all-time if still 0
        if ( $revenue <= 0 ) {
            $revenue = (float) $wpdb->get_var(
                "SELECT SUM(total_amount) FROM {$wpdb->prefix}wc_orders 
                 WHERE status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending')"
            );
            $revenue = $revenue ? (float) $revenue : 0.0;
        }

        Cache::set( 'monthly_revenue', $revenue, 1800 );

        return $revenue;
    }

    /**
     * Get monthly orders count.
     *
     * @return int
     */
    private function get_monthly_orders() {
        $cached = Cache::get( 'monthly_orders' );
        if ( false !== $cached ) {
            return (int) $cached;
        }

        global $wpdb;

        $orders = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'shop_order' 
             AND post_status IN ('wc-completed', 'wc-processing')
             AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Try HPOS table
        if ( null === $orders || 0 == $orders ) {
            $orders = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders 
                 WHERE status IN ('wc-completed', 'wc-processing') 
                 AND date_created_gmt >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
        }

        $orders = $orders ? (int) $orders : 0;

        // Try ALL statuses if completed/processing gives 0
        if ( $orders <= 0 ) {
            global $wpdb;
            $orders = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} 
                 WHERE post_type = 'shop_order' 
                 AND post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending')
                 AND post_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)"
            );
        }

        // Fallback to stored value
        $stored = get_option( 'rls_monthly_orders', 0 );
        if ( $stored > 0 && $orders <= 0 ) {
            $orders = (int) $stored;
        }

        Cache::set( 'monthly_orders', $orders, 1800 );

        return $orders;
    }

    /**
     * Get estimated monthly visitors.
     *
     * @return int
     */
    private function get_monthly_visitors() {
        $stored = get_option( 'rls_monthly_visitors', 0 );

        if ( $stored > 0 ) {
            return (int) $stored;
        }

        // Estimate from orders and typical conversion rate (2-3%)
        $orders = $this->get_monthly_orders();
        $estimated_visitors = $orders > 0 ? intval( $orders / 0.025 ) : 1000;

        return $estimated_visitors;
    }

    /**
     * Get store conversion rate.
     *
     * @return float
     */
    private function get_conversion_rate() {
        $visitors = $this->get_monthly_visitors();
        $orders = $this->metrics['monthly_orders'] ?? $this->get_monthly_orders();

        if ( $visitors <= 0 ) {
            return 0.025; // Default 2.5%
        }

        return min( $orders / $visitors, 1.0 );
    }

    /**
     * Get cart abandonment rate.
     *
     * @return float
     */
    private function get_cart_abandonment_rate() {
        // Check if we have WooCommerce session data or analytics
        $cached = Cache::get( 'cart_abandonment_rate' );
        if ( false !== $cached ) {
            return (float) $cached;
        }

        // Use industry average if no data available
        $rate = 0.69; // 69% industry average

        Cache::set( 'cart_abandonment_rate', $rate, 86400 );

        return $rate;
    }

    /**
     * Get returning customer rate.
     *
     * @return float
     */
    private function get_returning_customer_rate() {
        global $wpdb;

        $total_customers = $wpdb->get_var(
            "SELECT COUNT(DISTINCT meta_value) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_customer_user' AND meta_value > 0
             AND post_id IN (
                SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' 
                AND post_status IN ('wc-completed', 'wc-processing')
             )"
        );

        $repeat_customers = $wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT meta_value, COUNT(*) as order_count 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_customer_user' AND meta_value > 0
                AND post_id IN (
                    SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' 
                    AND post_status IN ('wc-completed', 'wc-processing')
                )
                GROUP BY meta_value 
                HAVING order_count > 1
            ) as repeat_buyers"
        );

        if ( $total_customers > 0 ) {
            return round( $repeat_customers / $total_customers, 2 );
        }

        return 0.27; // Default industry average
    }

    /**
     * Get confidence score for a calculation.
     *
     * @param string $leak_type Leak type.
     * @param array  $data      Available data.
     * @return int Score 0-100.
     */
    public function get_confidence_score( $leak_type, $data = array() ) {
        $base_score = 50;

        // More data = higher confidence
        if ( $this->metrics['monthly_orders'] > 100 ) {
            $base_score += 20;
        } elseif ( $this->metrics['monthly_orders'] > 30 ) {
            $base_score += 10;
        }

        if ( $this->metrics['monthly_revenue'] > 0 ) {
            $base_score += 10;
        }

        if ( isset( $data['measured'] ) && $data['measured'] ) {
            $base_score += 15;
        }

        return min( $base_score, 95 );
    }

    /**
     * Format currency value.
     *
     * @param float $amount Amount to format.
     * @return string Formatted currency string.
     */
    public function format_currency( $amount ) {
        if ( function_exists( 'wc_price' ) ) {
            return wc_price( $amount );
        }

        return '$' . number_format( $amount, 2 );
    }
}
