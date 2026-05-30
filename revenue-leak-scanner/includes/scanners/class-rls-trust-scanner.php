<?php
/**
 * Trust Scanner Module.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner\Scanners;

use RevenueLeakScanner\Revenue_Calculator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Scans trust signals and social proof for revenue leaks.
 */
class Trust_Scanner {

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
     * Run trust scan.
     *
     * @return array Scan results.
     */
    public function scan() {
        $leaks = array();
        $total_impact = 0.0;
        $max_severity = 'low';

        // Check privacy policy page
        $privacy_leak = $this->check_privacy_policy();
        if ( $privacy_leak ) {
            $leaks[] = $privacy_leak;
            $total_impact += $privacy_leak['revenue_impact'];
        }

        // Check refund/return policy
        $refund_leak = $this->check_refund_policy();
        if ( $refund_leak ) {
            $leaks[] = $refund_leak;
            $total_impact += $refund_leak['revenue_impact'];
        }

        // Check contact information
        $contact_leak = $this->check_contact_info();
        if ( $contact_leak ) {
            $leaks[] = $contact_leak;
            $total_impact += $contact_leak['revenue_impact'];
        }

        // Check terms and conditions
        $terms_leak = $this->check_terms_page();
        if ( $terms_leak ) {
            $leaks[] = $terms_leak;
            $total_impact += $terms_leak['revenue_impact'];
        }

        // Check overall trust indicators
        $trust_leak = $this->check_trust_indicators();
        if ( $trust_leak ) {
            $leaks[] = $trust_leak;
            $total_impact += $trust_leak['revenue_impact'];
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
     * Check for privacy policy page.
     *
     * @return array|null
     */
    private function check_privacy_policy() {
        $privacy_page_id = get_option( 'wp_page_for_privacy_policy' );

        if ( ! $privacy_page_id || 'publish' !== get_post_status( $privacy_page_id ) ) {
            $metrics = $this->calculator->get_metrics();
            $impact = $metrics['monthly_revenue'] * 0.03;

            return array(
                'severity'         => 'medium',
                'title'            => __( 'Missing or Unpublished Privacy Policy', 'revenue-leak-scanner' ),
                'description'      => __( 'No published privacy policy page detected. 79% of consumers are concerned about data privacy. A visible privacy policy builds trust and is legally required in most jurisdictions (GDPR, CCPA).', 'revenue-leak-scanner' ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => $this->calculator->get_confidence_score( 'privacy_policy', array( 'measured' => true ) ),
                'fix_suggestion'   => __( 'Create a comprehensive privacy policy page using the WordPress privacy policy template. Link it in your footer and during checkout.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => admin_url( 'options-privacy.php' ),
                'affected_items'   => array( 'privacy_policy' ),
            );
        }

        return null;
    }

    /**
     * Check for refund/return policy.
     *
     * @return array|null
     */
    private function check_refund_policy() {
        $refund_page_id = wc_get_page_id( 'refund_returns' );

        // Also search for common refund page slugs
        $refund_page = get_page_by_path( 'refund-policy' );
        if ( ! $refund_page ) {
            $refund_page = get_page_by_path( 'return-policy' );
        }
        if ( ! $refund_page ) {
            $refund_page = get_page_by_path( 'returns' );
        }
        if ( ! $refund_page ) {
            $refund_page = get_page_by_path( 'refund-returns' );
        }

        $has_refund_page = ( $refund_page_id > 0 ) || ( $refund_page && 'publish' === $refund_page->post_status );

        if ( ! $has_refund_page ) {
            $metrics = $this->calculator->get_metrics();
            $impact = $metrics['monthly_revenue'] * 0.05;

            return array(
                'severity'         => 'high',
                'title'            => __( 'No Refund/Return Policy Page Found', 'revenue-leak-scanner' ),
                'description'      => __( 'No refund or return policy page detected. 67% of shoppers check the return policy before buying. A clear, generous return policy can increase conversions by 15-20%.', 'revenue-leak-scanner' ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => $this->calculator->get_confidence_score( 'refund_policy', array( 'measured' => true ) ),
                'fix_suggestion'   => __( 'Create a clear refund/return policy page. Highlight key points like "30-day returns" and "free return shipping" near the add-to-cart button on product pages.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => admin_url( 'post-new.php?post_type=page' ),
                'affected_items'   => array( 'refund_policy' ),
            );
        }

        return null;
    }

    /**
     * Check for visible contact information.
     *
     * @return array|null
     */
    private function check_contact_info() {
        $contact_page = get_page_by_path( 'contact' );
        if ( ! $contact_page ) {
            $contact_page = get_page_by_path( 'contact-us' );
        }

        $has_contact = $contact_page && 'publish' === $contact_page->post_status;

        if ( ! $has_contact ) {
            // Check homepage for contact info
            $response = wp_remote_get( home_url( '/' ), array(
                'timeout'   => 10,
                'sslverify' => false,
            ) );

            if ( ! is_wp_error( $response ) ) {
                $body = wp_remote_retrieve_body( $response );
                $has_phone = (bool) preg_match( '/\+?[\d\s\-\(\)]{10,}/i', $body );
                $has_email = (bool) preg_match( '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $body );

                if ( $has_phone || $has_email ) {
                    $has_contact = true;
                }
            }
        }

        if ( ! $has_contact ) {
            $metrics = $this->calculator->get_metrics();
            $impact = $metrics['monthly_revenue'] * 0.04;

            return array(
                'severity'         => 'medium',
                'title'            => __( 'No Contact Page or Visible Contact Info', 'revenue-leak-scanner' ),
                'description'      => __( 'No contact page or visible contact information found. 44% of consumers will leave a website if there is no contact information or phone number. Visible contact info builds essential trust.', 'revenue-leak-scanner' ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => $this->calculator->get_confidence_score( 'contact_info' ),
                'fix_suggestion'   => __( 'Create a Contact Us page with phone number, email, and physical address. Add contact info to your footer on every page. Consider adding live chat.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => admin_url( 'post-new.php?post_type=page' ),
                'affected_items'   => array( 'contact_page' ),
            );
        }

        return null;
    }


    /**
     * Check for terms and conditions page.
     *
     * @return array|null
     */
    private function check_terms_page() {
        $terms_page_id = wc_get_page_id( 'terms' );

        if ( $terms_page_id <= 0 || 'publish' !== get_post_status( $terms_page_id ) ) {
            $metrics = $this->calculator->get_metrics();
            $impact = $metrics['monthly_revenue'] * 0.02;

            return array(
                'severity'         => 'low',
                'title'            => __( 'Missing Terms & Conditions Page', 'revenue-leak-scanner' ),
                'description'      => __( 'No Terms & Conditions page is configured in WooCommerce. While not directly a conversion factor, it is legally important and can build credibility with cautious shoppers.', 'revenue-leak-scanner' ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => $this->calculator->get_confidence_score( 'terms' ),
                'fix_suggestion'   => __( 'Create a Terms & Conditions page and assign it in WooCommerce > Settings > Advanced > Page Setup.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => admin_url( 'admin.php?page=wc-settings&tab=advanced' ),
                'affected_items'   => array( 'terms_conditions' ),
            );
        }

        return null;
    }

    /**
     * Check overall trust indicators on the site.
     *
     * @return array|null
     */
    private function check_trust_indicators() {
        $response = wp_remote_get( home_url( '/' ), array(
            'timeout'   => 10,
            'sslverify' => false,
        ) );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $missing_signals = 0;
        $missing_items = array();

        // Check for trust badges / security seals
        $trust_keywords = array( 'trust', 'secure', 'verified', 'guarantee', 'badge', 'ssl', 'safe' );
        $has_trust_badge = false;
        foreach ( $trust_keywords as $keyword ) {
            if ( stripos( $body, $keyword ) !== false ) {
                $has_trust_badge = true;
                break;
            }
        }
        if ( ! $has_trust_badge ) {
            $missing_signals++;
            $missing_items[] = __( 'No trust badges or security seals', 'revenue-leak-scanner' );
        }

        // Check for testimonials/social proof
        $social_keywords = array( 'testimonial', 'review', 'rating', 'star', 'customer-say', 'customers say' );
        $has_social = false;
        foreach ( $social_keywords as $keyword ) {
            if ( stripos( $body, $keyword ) !== false ) {
                $has_social = true;
                break;
            }
        }
        if ( ! $has_social ) {
            $missing_signals++;
            $missing_items[] = __( 'No testimonials or social proof on homepage', 'revenue-leak-scanner' );
        }

        // Check for money-back guarantee messaging
        $guarantee_keywords = array( 'money-back', 'money back', 'satisfaction', 'guarantee', 'risk-free', 'risk free' );
        $has_guarantee = false;
        foreach ( $guarantee_keywords as $keyword ) {
            if ( stripos( $body, $keyword ) !== false ) {
                $has_guarantee = true;
                break;
            }
        }
        if ( ! $has_guarantee ) {
            $missing_signals++;
            $missing_items[] = __( 'No guarantee or risk-reversal messaging', 'revenue-leak-scanner' );
        }

        if ( $missing_signals >= 2 ) {
            $impact = $this->calculator->calculate_impact( 'trust_signals', array(
                'missing_count' => $missing_signals,
            ) );

            return array(
                'severity'         => $missing_signals >= 3 ? 'high' : 'medium',
                'title'            => sprintf(
                    __( '%d Trust Signals Missing from Homepage', 'revenue-leak-scanner' ),
                    $missing_signals
                ),
                'description'      => sprintf(
                    __( 'Your homepage is missing key trust signals: %s. Trust badges alone can increase conversions by up to 42%% (Baymard Institute).', 'revenue-leak-scanner' ),
                    implode( '; ', $missing_items )
                ),
                'revenue_impact'   => $impact,
                'confidence_score' => $this->calculator->get_confidence_score( 'trust_signals' ),
                'fix_suggestion'   => __( 'Add trust badges (payment icons, SSL seal, satisfaction guarantee), customer testimonials with photos, and clear risk-reversal messaging (money-back guarantee) to your homepage and product pages.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'medium',
                'fix_url'          => admin_url( 'customize.php' ),
                'affected_items'   => $missing_items,
            );
        }

        return null;
    }
}
