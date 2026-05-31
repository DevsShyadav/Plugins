<?php
/**
 * Mobile Scanner Module.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner\Scanners;

use RevenueLeakScanner\Revenue_Calculator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Scans mobile experience for revenue leaks.
 */
class Mobile_Scanner {

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
     * Run mobile scan.
     *
     * @return array Scan results.
     */
    public function scan() {
        $leaks = array();
        $total_impact = 0.0;
        $max_severity = 'low';

        // Check viewport meta tag
        $viewport_leak = $this->check_viewport();
        if ( $viewport_leak ) {
            $leaks[] = $viewport_leak;
            $total_impact += $viewport_leak['revenue_impact'];
        }

        // Check touch-friendly elements
        $touch_leak = $this->check_touch_targets();
        if ( $touch_leak ) {
            $leaks[] = $touch_leak;
            $total_impact += $touch_leak['revenue_impact'];
        }

        // Check mobile checkout experience
        $mobile_checkout_leak = $this->check_mobile_checkout();
        if ( $mobile_checkout_leak ) {
            $leaks[] = $mobile_checkout_leak;
            $total_impact += $mobile_checkout_leak['revenue_impact'];
        }

        // Check mobile search functionality
        $search_leak = $this->check_mobile_search();
        if ( $search_leak ) {
            $leaks[] = $search_leak;
            $total_impact += $search_leak['revenue_impact'];
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
     * Check viewport meta tag configuration.
     *
     * @return array|null
     */
    private function check_viewport() {
        $response = wp_remote_get( home_url( '/' ), array(
            'timeout'   => 10,
            'sslverify' => false,
        ) );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $has_viewport = (bool) preg_match( '/<meta[^>]*name=["\']viewport["\'][^>]*>/i', $body );

        if ( ! $has_viewport ) {
            $impact = $this->calculator->calculate_impact( 'mobile_ux', array(
                'issue_count'     => 5,
                'mobile_percent'  => 0.60,
            ) );

            return array(
                'severity'         => 'critical',
                'title'            => __( 'Missing Mobile Viewport Meta Tag', 'revenue-leak-scanner' ),
                'description'      => __( 'Your site is missing the viewport meta tag. This means your site will not render correctly on mobile devices, causing a terrible experience for 60%+ of your visitors.', 'revenue-leak-scanner' ),
                'revenue_impact'   => $impact,
                'confidence_score' => 95,
                'fix_suggestion'   => __( 'Add <meta name="viewport" content="width=device-width, initial-scale=1"> to your theme\'s header. Most modern themes include this by default.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => admin_url( 'themes.php' ),
                'affected_items'   => array( 'viewport_meta' ),
            );
        }

        return null;
    }

    /**
     * Check for touch target size issues.
     *
     * @return array|null
     */
    private function check_touch_targets() {
        // Check if add-to-cart buttons exist and page has proper mobile styling
        $shop_page_id = wc_get_page_id( 'shop' );
        if ( $shop_page_id <= 0 ) {
            return null;
        }

        $response = wp_remote_get( get_permalink( $shop_page_id ), array(
            'timeout'   => 10,
            'sslverify' => false,
        ) );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );

        // Check for common mobile UX issues in the HTML
        $issues = array();

        // Check if buttons are present
        if ( ! preg_match( '/add[_-]to[_-]cart|add-to-cart/i', $body ) ) {
            $issues[] = __( 'Add to cart buttons may not be visible', 'revenue-leak-scanner' );
        }

        // Check for text that's too small (inline styles with small font)
        if ( preg_match_all( '/font-size:\s*([0-9]+)px/i', $body, $matches ) ) {
            $small_fonts = array_filter( $matches[1], function( $size ) {
                return (int) $size < 12;
            } );
            if ( count( $small_fonts ) > 3 ) {
                $issues[] = __( 'Multiple elements with text smaller than 12px detected', 'revenue-leak-scanner' );
            }
        }

        if ( ! empty( $issues ) ) {
            $impact = $this->calculator->calculate_impact( 'mobile_ux', array(
                'issue_count'    => count( $issues ),
                'mobile_percent' => 0.60,
            ) );

            return array(
                'severity'         => 'medium',
                'title'            => __( 'Mobile Touch Target & Readability Issues', 'revenue-leak-scanner' ),
                'description'      => sprintf(
                    __( 'Mobile UX issues detected: %s. On mobile, buttons should be at least 44x44px and text at least 14px for comfortable interaction.', 'revenue-leak-scanner' ),
                    implode( '; ', $issues )
                ),
                'revenue_impact'   => $impact,
                'confidence_score' => $this->calculator->get_confidence_score( 'mobile_touch' ),
                'fix_suggestion'   => __( 'Ensure all clickable elements are at least 44x44 pixels on mobile. Use minimum 14px font size for body text. Test your store on actual mobile devices.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'medium',
                'fix_url'          => admin_url( 'customize.php' ),
                'affected_items'   => $issues,
            );
        }

        return null;
    }


    /**
     * Check mobile checkout experience.
     *
     * @return array|null
     */
    private function check_mobile_checkout() {
        $checkout_page_id = wc_get_page_id( 'checkout' );
        if ( $checkout_page_id <= 0 ) {
            return null;
        }

        $response = wp_remote_get( get_permalink( $checkout_page_id ), array(
            'timeout'   => 10,
            'sslverify' => false,
        ) );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $issues = array();

        // Check for autocomplete attributes on form fields
        if ( ! preg_match( '/autocomplete=/i', $body ) ) {
            $issues[] = __( 'Form fields missing autocomplete attributes (slows mobile form filling)', 'revenue-leak-scanner' );
        }

        // Check for proper input types (tel, email, etc.)
        if ( ! preg_match( '/type=["\']tel["\']/i', $body ) ) {
            $issues[] = __( 'Phone field not using type="tel" (no numeric keyboard on mobile)', 'revenue-leak-scanner' );
        }

        if ( ! empty( $issues ) ) {
            $metrics = $this->calculator->get_metrics();
            $impact = $metrics['monthly_revenue'] * 0.03;

            return array(
                'severity'         => 'medium',
                'title'            => __( 'Mobile Checkout Form Not Optimized', 'revenue-leak-scanner' ),
                'description'      => sprintf(
                    __( 'Your checkout form is missing mobile optimizations: %s. These small issues compound into significant friction on mobile devices.', 'revenue-leak-scanner' ),
                    implode( '; ', $issues )
                ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => $this->calculator->get_confidence_score( 'mobile_checkout', array( 'measured' => true ) ),
                'fix_suggestion'   => __( 'Add autocomplete attributes to all form fields, use appropriate input types (tel, email, number), and ensure the checkout is a single-column layout on mobile.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'medium',
                'fix_url'          => admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
                'affected_items'   => $issues,
            );
        }

        return null;
    }

    /**
     * Check mobile search functionality.
     *
     * @return array|null
     */
    private function check_mobile_search() {
        $response = wp_remote_get( home_url( '/' ), array(
            'timeout'   => 10,
            'sslverify' => false,
        ) );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );

        // Check if search form exists
        $has_search = (bool) preg_match( '/<form[^>]*role=["\']search["\']|<input[^>]*type=["\']search["\']/i', $body );

        if ( ! $has_search ) {
            $has_search = (bool) preg_match( '/class=["\'][^"\']*search[^"\']*["\']/i', $body );
        }

        if ( ! $has_search ) {
            $metrics = $this->calculator->get_metrics();
            $impact = $metrics['monthly_revenue'] * 0.04;

            return array(
                'severity'         => 'medium',
                'title'            => __( 'No Visible Search on Homepage', 'revenue-leak-scanner' ),
                'description'      => __( 'No prominent search functionality detected on your homepage. 30% of ecommerce visitors use site search, and they convert at 2-3x higher rates than browsers. This is especially critical on mobile.', 'revenue-leak-scanner' ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => $this->calculator->get_confidence_score( 'search' ),
                'fix_suggestion'   => __( 'Add a prominent search bar to your header/navigation. Consider using an enhanced search plugin like SearchWP or Relevanssi for better product search results.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => admin_url( 'widgets.php' ),
                'affected_items'   => array( 'site_search' ),
            );
        }

        return null;
    }
}
