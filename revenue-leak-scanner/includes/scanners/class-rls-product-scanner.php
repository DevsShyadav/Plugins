<?php
/**
 * Product Scanner Module.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner\Scanners;

use RevenueLeakScanner\Revenue_Calculator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Scans product pages for revenue leaks.
 */
class Product_Scanner {

    /**
     * Revenue calculator.
     *
     * @var Revenue_Calculator
     */
    private $calculator;

    /**
     * Constructor.
     *
     * @param Revenue_Calculator $calculator Revenue calculator instance.
     */
    public function __construct( Revenue_Calculator $calculator ) {
        $this->calculator = $calculator;
    }

    /**
     * Run product scan.
     *
     * @return array Scan results.
     */
    public function scan() {
        $leaks = array();
        $total_impact = 0.0;
        $max_severity = 'low';

        // Check products without images
        $images_leak = $this->check_missing_images();
        if ( $images_leak ) {
            $leaks[] = $images_leak;
            $total_impact += $images_leak['revenue_impact'];
        }

        // Check products without reviews
        $reviews_leak = $this->check_missing_reviews();
        if ( $reviews_leak ) {
            $leaks[] = $reviews_leak;
            $total_impact += $reviews_leak['revenue_impact'];
        }


        // Check products without descriptions
        $desc_leak = $this->check_missing_descriptions();
        if ( $desc_leak ) {
            $leaks[] = $desc_leak;
            $total_impact += $desc_leak['revenue_impact'];
        }

        // Check products without upsells/cross-sells
        $upsells_leak = $this->check_missing_upsells();
        if ( $upsells_leak ) {
            $leaks[] = $upsells_leak;
            $total_impact += $upsells_leak['revenue_impact'];
        }

        // Check out of stock products still visible
        $stock_leak = $this->check_out_of_stock();
        if ( $stock_leak ) {
            $leaks[] = $stock_leak;
            $total_impact += $stock_leak['revenue_impact'];
        }

        // Check product pricing issues
        $pricing_leak = $this->check_pricing_issues();
        if ( $pricing_leak ) {
            $leaks[] = $pricing_leak;
            $total_impact += $pricing_leak['revenue_impact'];
        }

        foreach ( $leaks as $leak ) {
            if ( 'critical' === $leak['severity'] ) {
                $max_severity = 'critical';
                break;
            } elseif ( 'high' === $leak['severity'] && 'critical' !== $max_severity ) {
                $max_severity = 'high';
            } elseif ( 'medium' === $leak['severity'] && ! in_array( $max_severity, array( 'critical', 'high' ), true ) ) {
                $max_severity = 'medium';
            }
        }

        return array(
            'leaks'        => $leaks,
            'total_impact' => $total_impact,
            'max_severity' => $max_severity,
        );
    }


    /**
     * Check for products without featured images.
     *
     * @return array|null
     */
    private function check_missing_images() {
        global $wpdb;

        $total_products = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'product' AND post_status = 'publish'"
        );

        $products_without_images = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
             WHERE p.post_type = 'product' AND p.post_status = 'publish'
             AND (pm.meta_value IS NULL OR pm.meta_value = '' OR pm.meta_value = '0')"
        );

        if ( (int) $products_without_images > 0 ) {
            // Get actual product names for detail view
            $affected_products = $wpdb->get_col(
                "SELECT p.post_title FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
                 WHERE p.post_type = 'product' AND p.post_status = 'publish'
                 AND (pm.meta_value IS NULL OR pm.meta_value = '' OR pm.meta_value = '0')
                 LIMIT 10"
            );

            $impact = $this->calculator->calculate_impact( 'missing_images', array(
                'count'          => (int) $products_without_images,
                'total_products' => (int) $total_products,
            ) );

            return array(
                'severity'         => (int) $products_without_images > 5 ? 'high' : 'medium',
                'title'            => sprintf(
                    __( '%d Products Missing Featured Images', 'revenue-leak-scanner' ),
                    $products_without_images
                ),
                'description'      => sprintf(
                    __( '%d of your %d products have no featured image. Products with images receive 94%% more views and significantly higher conversion rates.', 'revenue-leak-scanner' ),
                    $products_without_images,
                    $total_products
                ),
                'revenue_impact'   => $impact,
                'confidence_score' => $this->calculator->get_confidence_score( 'missing_images', array( 'measured' => true ) ),
                'fix_suggestion'   => __( 'Add high-quality product images to all products. Use multiple angles and lifestyle shots for best results.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'medium',
                'fix_url'          => admin_url( 'edit.php?post_type=product' ),
                'affected_items'   => $affected_products,
            );
        }

        return null;
    }


    /**
     * Check for products without reviews.
     *
     * @return array|null
     */
    private function check_missing_reviews() {
        global $wpdb;

        $total_products = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'product' AND post_status = 'publish'"
        );

        $products_without_reviews = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'product' AND post_status = 'publish' AND comment_count = 0"
        );

        if ( (int) $products_without_reviews > 0 && (int) $total_products > 0 ) {
            $percent = round( ( $products_without_reviews / $total_products ) * 100 );

            if ( $percent > 30 ) {
                // Get actual product names without reviews
                $affected_products = $wpdb->get_col(
                    "SELECT post_title FROM {$wpdb->posts} 
                     WHERE post_type = 'product' AND post_status = 'publish' AND comment_count = 0
                     LIMIT 10"
                );

                $impact = $this->calculator->calculate_impact( 'missing_reviews', array(
                    'count'          => (int) $products_without_reviews,
                    'total_products' => (int) $total_products,
                ) );

                return array(
                    'severity'         => $percent > 70 ? 'high' : 'medium',
                    'title'            => sprintf(
                        __( '%d Products Have Zero Reviews (%d%%)', 'revenue-leak-scanner' ),
                        $products_without_reviews,
                        $percent
                    ),
                    'description'      => sprintf(
                        __( '%d of your %d products (%d%%) have no reviews. Products with reviews see up to 270%% higher conversion rates. Social proof is one of the strongest purchase motivators.', 'revenue-leak-scanner' ),
                        $products_without_reviews,
                        $total_products,
                        $percent
                    ),
                    'revenue_impact'   => $impact,
                    'confidence_score' => $this->calculator->get_confidence_score( 'missing_reviews', array( 'measured' => true ) ),
                    'fix_suggestion'   => __( 'Send post-purchase review request emails, offer incentives for reviews, and import existing reviews from other platforms.', 'revenue-leak-scanner' ),
                    'fix_difficulty'   => 'medium',
                    'fix_url'          => admin_url( 'edit.php?post_type=product' ),
                    'affected_items'   => $affected_products,
                );
            }
        }

        return null;
    }


    /**
     * Check for products with short/missing descriptions.
     *
     * @return array|null
     */
    private function check_missing_descriptions() {
        global $wpdb;

        $total_products = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'product' AND post_status = 'publish'"
        );

        $products_short_desc = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'product' AND post_status = 'publish'
             AND (post_content = '' OR LENGTH(post_content) < 100)"
        );

        if ( (int) $products_short_desc > 0 && (int) $total_products > 0 ) {
            $percent = round( ( $products_short_desc / $total_products ) * 100 );

            if ( $percent > 20 ) {
                // Get actual product names with weak descriptions
                $affected_products = $wpdb->get_col(
                    "SELECT post_title FROM {$wpdb->posts} 
                     WHERE post_type = 'product' AND post_status = 'publish'
                     AND (post_content = '' OR LENGTH(post_content) < 100)
                     LIMIT 10"
                );

                $metrics = $this->calculator->get_metrics();
                $impact = $metrics['monthly_revenue'] * ( $percent / 100 ) * 0.05;

                return array(
                    'severity'         => $percent > 50 ? 'high' : 'medium',
                    'title'            => sprintf(
                        __( '%d Products Have Weak Descriptions', 'revenue-leak-scanner' ),
                        $products_short_desc
                    ),
                    'description'      => sprintf(
                        __( '%d products (%d%%) have descriptions shorter than 100 characters. Detailed product descriptions answer buyer questions and reduce purchase hesitation.', 'revenue-leak-scanner' ),
                        $products_short_desc,
                        $percent
                    ),
                    'revenue_impact'   => round( $impact, 2 ),
                    'confidence_score' => $this->calculator->get_confidence_score( 'descriptions' ),
                    'fix_suggestion'   => __( 'Write detailed product descriptions (300+ words) that cover features, benefits, specifications, and use cases. Include keywords for SEO.', 'revenue-leak-scanner' ),
                    'fix_difficulty'   => 'hard',
                    'fix_url'          => admin_url( 'edit.php?post_type=product' ),
                    'affected_items'   => $affected_products,
                );
            }
        }

        return null;
    }

    /**
     * Check for products without upsells/cross-sells.
     *
     * @return array|null
     */
    private function check_missing_upsells() {
        global $wpdb;

        $total_products = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'product' AND post_status = 'publish'"
        );

        $products_with_upsells = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
             WHERE meta_key IN ('_upsell_ids', '_crosssell_ids')
             AND meta_value != '' AND meta_value != 'a:0:{}'"
        );

        $without_upsells = max( 0, (int) $total_products - (int) $products_with_upsells );

        if ( $without_upsells > 0 && (int) $total_products > 0 ) {
            $percent = round( ( $without_upsells / $total_products ) * 100 );

            if ( $percent > 50 ) {
                // Get product names without upsells
                $affected_products = $wpdb->get_col(
                    "SELECT p.post_title FROM {$wpdb->posts} p
                     WHERE p.post_type = 'product' AND p.post_status = 'publish'
                     AND p.ID NOT IN (
                         SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                         WHERE meta_key IN ('_upsell_ids', '_crosssell_ids')
                         AND meta_value != '' AND meta_value != 'a:0:{}'
                     )
                     LIMIT 10"
                );

                $impact = $this->calculator->calculate_impact( 'missing_upsells', array(
                    'count'          => $without_upsells,
                    'total_products' => (int) $total_products,
                ) );

                return array(
                    'severity'         => 'high',
                    'title'            => sprintf(
                        __( '%d Products Missing Upsells/Cross-sells (%d%%)', 'revenue-leak-scanner' ),
                        $without_upsells,
                        $percent
                    ),
                    'description'      => sprintf(
                        __( '%d of %d products have no related product suggestions. Cross-sells and upsells can increase average order value by 10-30%%.', 'revenue-leak-scanner' ),
                        $without_upsells,
                        $total_products
                    ),
                    'revenue_impact'   => $impact,
                    'confidence_score' => $this->calculator->get_confidence_score( 'upsells', array( 'measured' => true ) ),
                    'fix_suggestion'   => __( 'Add related products, upsells, and cross-sells to each product. Consider using automatic product recommendations based on purchase history.', 'revenue-leak-scanner' ),
                    'fix_difficulty'   => 'medium',
                    'fix_url'          => admin_url( 'edit.php?post_type=product' ),
                    'affected_items'   => $affected_products,
                );
            }
        }

        return null;
    }


    /**
     * Check for out of stock products still visible.
     *
     * @return array|null
     */
    private function check_out_of_stock() {
        global $wpdb;

        $out_of_stock = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_stock_status' AND pm.meta_value = 'outofstock'
             AND p.post_type = 'product' AND p.post_status = 'publish'"
        );

        $hide_setting = get_option( 'woocommerce_hide_out_of_stock_items' );

        if ( (int) $out_of_stock > 3 && 'yes' !== $hide_setting ) {
            $metrics = $this->calculator->get_metrics();
            // Out of stock pages frustrate visitors, ~2% revenue impact per visible OOS item (capped)
            $impact = min( $metrics['monthly_revenue'] * 0.03, $metrics['monthly_revenue'] * ( $out_of_stock * 0.005 ) );

            return array(
                'severity'         => 'medium',
                'title'            => sprintf(
                    __( '%d Out-of-Stock Products Visible in Shop', 'revenue-leak-scanner' ),
                    $out_of_stock
                ),
                'description'      => sprintf(
                    __( '%d products are out of stock but still showing in your shop. This frustrates visitors who click through only to find they cannot purchase, leading to abandoned sessions.', 'revenue-leak-scanner' ),
                    $out_of_stock
                ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => $this->calculator->get_confidence_score( 'out_of_stock', array( 'measured' => true ) ),
                'fix_suggestion'   => __( 'Enable "Hide out of stock items from the catalog" in WooCommerce > Settings > Products > Inventory, or add back-in-stock notification functionality.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => admin_url( 'admin.php?page=wc-settings&tab=products&section=inventory' ),
                'affected_items'   => array( 'out_of_stock_products' ),
            );
        }

        return null;
    }

    /**
     * Check for pricing issues (no sale prices configured).
     *
     * @return array|null
     */
    private function check_pricing_issues() {
        global $wpdb;

        $total_products = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'product' AND post_status = 'publish'"
        );

        $products_on_sale = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_sale_price' AND meta_value != '' AND meta_value > 0
             AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish')"
        );

        if ( (int) $total_products > 5 && (int) $products_on_sale === 0 ) {
            $metrics = $this->calculator->get_metrics();
            $impact = $metrics['monthly_revenue'] * 0.04;

            return array(
                'severity'         => 'low',
                'title'            => __( 'No Products Currently on Sale', 'revenue-leak-scanner' ),
                'description'      => __( 'You have no products with sale prices. Strategic discounting creates urgency and can lift conversion rates by 10-15%. Consider running rotating sales on select products.', 'revenue-leak-scanner' ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => $this->calculator->get_confidence_score( 'pricing' ),
                'fix_suggestion'   => __( 'Set sale prices on 10-20% of your product catalog. Use scheduled sales to create urgency without permanently reducing margins.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => admin_url( 'edit.php?post_type=product' ),
                'affected_items'   => array( 'product_pricing' ),
            );
        }

        return null;
    }
}
