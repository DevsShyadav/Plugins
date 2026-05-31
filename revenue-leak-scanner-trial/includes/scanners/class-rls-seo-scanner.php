<?php
/**
 * SEO Scanner Module.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner\Scanners;

use RevenueLeakScanner\Revenue_Calculator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Scans SEO for revenue leak indicators.
 */
class SEO_Scanner {

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
     * Run SEO scan.
     *
     * @return array Scan results.
     */
    public function scan() {
        $leaks = array();
        $total_impact = 0.0;
        $max_severity = 'low';

        // Check meta descriptions
        $meta_leak = $this->check_meta_descriptions();
        if ( $meta_leak ) {
            $leaks[] = $meta_leak;
            $total_impact += $meta_leak['revenue_impact'];
        }

        // Check permalink structure
        $permalink_leak = $this->check_permalinks();
        if ( $permalink_leak ) {
            $leaks[] = $permalink_leak;
            $total_impact += $permalink_leak['revenue_impact'];
        }

        // Check for missing alt tags
        $alt_leak = $this->check_image_alt_tags();
        if ( $alt_leak ) {
            $leaks[] = $alt_leak;
            $total_impact += $alt_leak['revenue_impact'];
        }

        // Check SSL
        $ssl_leak = $this->check_ssl();
        if ( $ssl_leak ) {
            $leaks[] = $ssl_leak;
            $total_impact += $ssl_leak['revenue_impact'];
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
     * Check product meta descriptions.
     *
     * @return array|null
     */
    private function check_meta_descriptions() {
        global $wpdb;

        $total_products = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"
        );

        // Check for Yoast SEO meta descriptions
        $with_meta = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_yoast_wpseo_metadesc' AND meta_value != ''
             AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish')"
        );

        // Also check Rank Math
        if ( (int) $with_meta === 0 ) {
            $with_meta = $wpdb->get_var(
                "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
                 WHERE meta_key = 'rank_math_description' AND meta_value != ''
                 AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish')"
            );
        }

        $without_meta = max( 0, (int) $total_products - (int) $with_meta );

        if ( $without_meta > 0 && (int) $total_products > 0 ) {
            $percent = round( ( $without_meta / $total_products ) * 100 );

            if ( $percent > 40 ) {
                $impact = $this->calculator->calculate_impact( 'seo_issues', array(
                    'issue_count'     => min( intval( $percent / 20 ), 5 ),
                    'organic_percent' => 0.40,
                ) );

                return array(
                    'severity'         => $percent > 80 ? 'high' : 'medium',
                    'title'            => sprintf(
                        __( '%d Products Missing SEO Meta Descriptions (%d%%)', 'revenue-leak-scanner' ),
                        $without_meta,
                        $percent
                    ),
                    'description'      => sprintf(
                        __( '%d of %d products (%d%%) have no custom meta description. Google uses meta descriptions in search results — compelling descriptions increase click-through rates by 5-10%%.', 'revenue-leak-scanner' ),
                        $without_meta,
                        $total_products,
                        $percent
                    ),
                    'revenue_impact'   => $impact,
                    'confidence_score' => $this->calculator->get_confidence_score( 'seo_meta' ),
                    'fix_suggestion'   => __( 'Install an SEO plugin (Yoast SEO or Rank Math) and write unique, compelling meta descriptions for each product that include key benefits and a call-to-action.', 'revenue-leak-scanner' ),
                    'fix_difficulty'   => 'hard',
                    'fix_url'          => admin_url( 'edit.php?post_type=product' ),
                    'affected_items'   => array( 'meta_descriptions' ),
                );
            }
        }

        return null;
    }

    /**
     * Check permalink structure.
     *
     * @return array|null
     */
    private function check_permalinks() {
        $structure = get_option( 'permalink_structure' );

        if ( empty( $structure ) || '/?p=%post_id%' === $structure ) {
            $metrics = $this->calculator->get_metrics();
            $impact = $metrics['monthly_revenue'] * 0.05;

            return array(
                'severity'         => 'high',
                'title'            => __( 'Non-SEO-Friendly Permalink Structure', 'revenue-leak-scanner' ),
                'description'      => __( 'Your site is using plain permalinks (?p=123). SEO-friendly URLs with descriptive slugs improve search rankings and click-through rates. This is one of the easiest SEO fixes with significant impact.', 'revenue-leak-scanner' ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => 90,
                'fix_suggestion'   => __( 'Go to Settings > Permalinks and select "Post name" structure. Be careful: changing permalinks on an existing site requires proper 301 redirects.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => admin_url( 'options-permalink.php' ),
                'affected_items'   => array( 'permalinks' ),
            );
        }

        return null;
    }


    /**
     * Check for missing image alt tags.
     *
     * @return array|null
     */
    private function check_image_alt_tags() {
        global $wpdb;

        $total_images = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
        );

        $images_without_alt = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
             WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'
             AND (pm.meta_value IS NULL OR pm.meta_value = '')"
        );

        if ( (int) $images_without_alt > 10 && (int) $total_images > 0 ) {
            $percent = round( ( $images_without_alt / $total_images ) * 100 );

            if ( $percent > 30 ) {
                $metrics = $this->calculator->get_metrics();
                $impact = $metrics['monthly_revenue'] * 0.02;

                return array(
                    'severity'         => 'low',
                    'title'            => sprintf(
                        __( '%d Images Missing Alt Text (%d%%)', 'revenue-leak-scanner' ),
                        $images_without_alt,
                        $percent
                    ),
                    'description'      => sprintf(
                        __( '%d of %d images (%d%%) have no alt text. Alt text helps with image SEO, accessibility, and can drive traffic through Google Image Search.', 'revenue-leak-scanner' ),
                        $images_without_alt,
                        $total_images,
                        $percent
                    ),
                    'revenue_impact'   => round( $impact, 2 ),
                    'confidence_score' => $this->calculator->get_confidence_score( 'image_alt' ),
                    'fix_suggestion'   => __( 'Add descriptive alt text to all product and content images. Include relevant keywords naturally. This also improves accessibility compliance.', 'revenue-leak-scanner' ),
                    'fix_difficulty'   => 'medium',
                    'fix_url'          => admin_url( 'upload.php' ),
                    'affected_items'   => array( 'image_alt_tags' ),
                );
            }
        }

        return null;
    }

    /**
     * Check SSL certificate.
     *
     * @return array|null
     */
    private function check_ssl() {
        $site_url = get_site_url();

        if ( strpos( $site_url, 'https://' ) !== 0 ) {
            $metrics = $this->calculator->get_metrics();
            $impact = $metrics['monthly_revenue'] * 0.15;

            return array(
                'severity'         => 'critical',
                'title'            => __( 'Site Not Using HTTPS (No SSL)', 'revenue-leak-scanner' ),
                'description'      => __( 'Your site is not using HTTPS. Google penalizes non-HTTPS sites in rankings, and browsers show "Not Secure" warnings that destroy trust. For an ecommerce store, this is critical — customers will not enter payment information on an insecure site.', 'revenue-leak-scanner' ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => 95,
                'fix_suggestion'   => __( 'Install an SSL certificate (many hosts offer free Let\'s Encrypt certificates), then update WordPress URL settings and add HTTP to HTTPS redirects.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'medium',
                'fix_url'          => admin_url( 'options-general.php' ),
                'affected_items'   => array( 'ssl_certificate' ),
            );
        }

        return null;
    }
}
