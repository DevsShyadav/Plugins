<?php
/**
 * Checkout Scanner Module.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner\Scanners;

use RevenueLeakScanner\Revenue_Calculator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Scans checkout flow for revenue leaks.
 */
class Checkout_Scanner {

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
     * Run checkout scan.
     *
     * @return array Scan results.
     */
    public function scan() {
        $leaks = array();
        $total_impact = 0.0;
        $max_severity = 'low';

        // Check for guest checkout
        $guest_checkout_leak = $this->check_guest_checkout();
        if ( $guest_checkout_leak ) {
            $leaks[] = $guest_checkout_leak;
            $total_impact += $guest_checkout_leak['revenue_impact'];
        }

        // Check checkout fields
        $fields_leak = $this->check_checkout_fields();
        if ( $fields_leak ) {
            $leaks[] = $fields_leak;
            $total_impact += $fields_leak['revenue_impact'];
        }

        // Check payment methods
        $payment_leak = $this->check_payment_methods();
        if ( $payment_leak ) {
            $leaks[] = $payment_leak;
            $total_impact += $payment_leak['revenue_impact'];
        }

        // Check shipping options
        $shipping_leak = $this->check_shipping_options();
        if ( $shipping_leak ) {
            $leaks[] = $shipping_leak;
            $total_impact += $shipping_leak['revenue_impact'];
        }

        // Check coupon visibility
        $coupon_leak = $this->check_coupon_field();
        if ( $coupon_leak ) {
            $leaks[] = $coupon_leak;
            $total_impact += $coupon_leak['revenue_impact'];
        }

        // Check order notes field
        $notes_leak = $this->check_order_notes();
        if ( $notes_leak ) {
            $leaks[] = $notes_leak;
            $total_impact += $notes_leak['revenue_impact'];
        }

        // Check cart abandonment
        $abandonment_leak = $this->check_cart_abandonment();
        if ( $abandonment_leak ) {
            $leaks[] = $abandonment_leak;
            $total_impact += $abandonment_leak['revenue_impact'];
            $max_severity = 'critical';
        }

        // Check free shipping threshold
        $free_shipping_leak = $this->check_free_shipping_threshold();
        if ( $free_shipping_leak ) {
            $leaks[] = $free_shipping_leak;
            $total_impact += $free_shipping_leak['revenue_impact'];
        }

        if ( empty( $max_severity ) || 'low' === $max_severity ) {
            foreach ( $leaks as $leak ) {
                if ( 'critical' === $leak['severity'] ) {
                    $max_severity = 'critical';
                    break;
                } elseif ( 'high' === $leak['severity'] ) {
                    $max_severity = 'high';
                }
            }
        }

        return array(
            'leaks'        => $leaks,
            'total_impact' => $total_impact,
            'max_severity' => $max_severity,
        );
    }

    /**
     * Check if guest checkout is disabled.
     *
     * @return array|null Leak data or null.
     */
    private function check_guest_checkout() {
        $guest_enabled = get_option( 'woocommerce_enable_guest_checkout' );

        if ( 'yes' !== $guest_enabled ) {
            $impact = $this->calculator->calculate_impact( 'checkout_abandonment', array(
                'abandonment_rate' => 0.75, // Higher when no guest checkout
            ) );

            return array(
                'severity'         => 'high',
                'title'            => __( 'Guest Checkout is Disabled', 'revenue-leak-scanner' ),
                'description'      => __( 'Forcing account creation at checkout causes 34% of shoppers to abandon their cart. Enable guest checkout to reduce friction.', 'revenue-leak-scanner' ),
                'revenue_impact'   => $impact,
                'confidence_score' => $this->calculator->get_confidence_score( 'guest_checkout', array( 'measured' => true ) ),
                'fix_suggestion'   => __( 'Go to WooCommerce > Settings > Accounts & Privacy and enable "Allow customers to place orders without an account."', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => admin_url( 'admin.php?page=wc-settings&tab=account' ),
                'affected_items'   => array( 'checkout_page' ),
            );
        }

        return null;
    }

    /**
     * Check for excessive checkout fields.
     *
     * @return array|null Leak data or null.
     */
    private function check_checkout_fields() {
        $checkout = \WC()->checkout();
        if ( ! $checkout ) {
            return null;
        }

        $billing_fields = $checkout->get_checkout_fields( 'billing' );
        $shipping_fields = $checkout->get_checkout_fields( 'shipping' );
        $total_fields = count( $billing_fields ) + count( $shipping_fields );

        // More than 12 fields is excessive
        if ( $total_fields > 12 ) {
            $excess_fields = $total_fields - 12;
            $abandonment_increase = min( $excess_fields * 0.02, 0.20 );

            $impact = $this->calculator->calculate_impact( 'checkout_abandonment', array(
                'abandonment_rate' => 0.69 + $abandonment_increase,
            ) );

            return array(
                'severity'         => 'medium',
                'title'            => sprintf(
                    /* translators: %d: number of checkout fields */
                    __( 'Too Many Checkout Fields (%d fields)', 'revenue-leak-scanner' ),
                    $total_fields
                ),
                'description'      => sprintf(
                    /* translators: %d: number of fields, %d: excess count */
                    __( 'Your checkout has %1$d fields. Each unnecessary field increases abandonment by ~2%%. You have %2$d more fields than recommended.', 'revenue-leak-scanner' ),
                    $total_fields,
                    $excess_fields
                ),
                'revenue_impact'   => $impact,
                'confidence_score' => $this->calculator->get_confidence_score( 'checkout_fields', array( 'measured' => true ) ),
                'fix_suggestion'   => __( 'Remove optional fields like Company Name, Phone (if not needed for shipping), and Order Notes. Keep only essential fields.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'medium',
                'fix_url'          => admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
                'affected_items'   => array( 'checkout_fields' ),
            );
        }

        return null;
    }

    /**
     * Check payment method availability.
     *
     * @return array|null Leak data or null.
     */
    private function check_payment_methods() {
        $available_gateways = \WC()->payment_gateways()->get_available_payment_gateways();
        $gateway_count = count( $available_gateways );

        $has_digital_wallet = false;
        $has_bnpl = false;

        foreach ( $available_gateways as $gateway ) {
            $id = strtolower( $gateway->id );
            if ( strpos( $id, 'apple' ) !== false || strpos( $id, 'google' ) !== false || strpos( $id, 'paypal' ) !== false ) {
                $has_digital_wallet = true;
            }
            if ( strpos( $id, 'klarna' ) !== false || strpos( $id, 'afterpay' ) !== false || strpos( $id, 'affirm' ) !== false ) {
                $has_bnpl = true;
            }
        }

        $issues = array();

        if ( $gateway_count < 2 ) {
            $issues[] = __( 'Only 1 payment method available', 'revenue-leak-scanner' );
        }

        if ( ! $has_digital_wallet ) {
            $issues[] = __( 'No digital wallet (Apple Pay/Google Pay/PayPal) detected', 'revenue-leak-scanner' );
        }

        if ( ! empty( $issues ) ) {
            $metrics = $this->calculator->get_metrics();
            // Limited payment options lose ~6% of potential orders
            $impact = $metrics['monthly_revenue'] * 0.06;

            return array(
                'severity'         => 'medium',
                'title'            => __( 'Limited Payment Options', 'revenue-leak-scanner' ),
                'description'      => sprintf(
                    /* translators: %s: list of issues */
                    __( 'Payment issues found: %s. Offering multiple payment methods can increase conversions by 6-12%%.', 'revenue-leak-scanner' ),
                    implode( '; ', $issues )
                ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => $this->calculator->get_confidence_score( 'payment_methods' ),
                'fix_suggestion'   => __( 'Add PayPal, Stripe (with Apple Pay/Google Pay), or a Buy Now Pay Later option like Klarna or Afterpay.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'medium',
                'fix_url'          => admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
                'affected_items'   => $issues,
            );
        }

        return null;
    }

    /**
     * Check shipping option configuration.
     *
     * @return array|null Leak data or null.
     */
    private function check_shipping_options() {
        $shipping_zones = \WC_Shipping_Zones::get_zones();
        $has_free_shipping = false;
        $method_count = 0;

        foreach ( $shipping_zones as $zone ) {
            $zone_obj = new \WC_Shipping_Zone( $zone['id'] );
            $methods = $zone_obj->get_shipping_methods( true );
            $method_count += count( $methods );

            foreach ( $methods as $method ) {
                if ( 'free_shipping' === $method->id ) {
                    $has_free_shipping = true;
                }
            }
        }

        // Also check zone 0 (rest of world)
        $default_zone = new \WC_Shipping_Zone( 0 );
        $default_methods = $default_zone->get_shipping_methods( true );
        $method_count += count( $default_methods );

        if ( ! $has_free_shipping && $method_count > 0 ) {
            $metrics = $this->calculator->get_metrics();
            // No free shipping option loses ~18% of cart adds
            $impact = $metrics['monthly_revenue'] * 0.08;

            return array(
                'severity'         => 'high',
                'title'            => __( 'No Free Shipping Option Available', 'revenue-leak-scanner' ),
                'description'      => __( '79% of consumers say free shipping makes them more likely to buy. Not offering free shipping (even with a minimum threshold) can cause significant cart abandonment.', 'revenue-leak-scanner' ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => $this->calculator->get_confidence_score( 'shipping' ),
                'fix_suggestion'   => __( 'Add a free shipping option with a minimum order threshold (e.g., "Free shipping on orders over $50"). This also increases average order value.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
                'affected_items'   => array( 'shipping_zones' ),
            );
        }

        return null;
    }

    /**
     * Check if coupon field is prominently displayed.
     *
     * @return array|null Leak data or null.
     */
    private function check_coupon_field() {
        $coupons_enabled = get_option( 'woocommerce_enable_coupons' );

        if ( 'yes' === $coupons_enabled ) {
            // Having a visible coupon field causes "coupon hunting" behavior
            $metrics = $this->calculator->get_metrics();
            // ~8% of shoppers leave to find coupons and never return
            $impact = $metrics['monthly_revenue'] * 0.03;

            return array(
                'severity'         => 'low',
                'title'            => __( 'Visible Coupon Field May Cause Cart Abandonment', 'revenue-leak-scanner' ),
                'description'      => __( 'A prominently displayed coupon code field causes 27% of shoppers to leave the checkout to search for discount codes. 8% of them never return.', 'revenue-leak-scanner' ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => $this->calculator->get_confidence_score( 'coupon_field' ),
                'fix_suggestion'   => __( 'Consider collapsing the coupon field behind a toggle link, or auto-applying available discounts instead of requiring manual code entry.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => admin_url( 'admin.php?page=wc-settings&tab=general' ),
                'affected_items'   => array( 'coupon_field' ),
            );
        }

        return null;
    }

    /**
     * Check order notes field.
     *
     * @return array|null Leak data or null.
     */
    private function check_order_notes() {
        // Check if order notes is enabled (it is by default in WooCommerce)
        $checkout = \WC()->checkout();
        if ( ! $checkout ) {
            return null;
        }

        $fields = $checkout->get_checkout_fields( 'order' );

        if ( isset( $fields['order_comments'] ) ) {
            $metrics = $this->calculator->get_metrics();
            // Minor impact but adds visual clutter
            $impact = $metrics['monthly_revenue'] * 0.005;

            return array(
                'severity'         => 'low',
                'title'            => __( 'Order Notes Field Adds Checkout Friction', 'revenue-leak-scanner' ),
                'description'      => __( 'The order notes field adds visual clutter to checkout. Unless you receive useful information there, consider removing it to streamline the checkout process.', 'revenue-leak-scanner' ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => 40,
                'fix_suggestion'   => __( 'Add a filter to remove the order notes field: add_filter("woocommerce_enable_order_notes_field", "__return_false");', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => '',
                'affected_items'   => array( 'order_notes' ),
            );
        }

        return null;
    }

    /**
     * Check cart abandonment indicators.
     *
     * @return array|null Leak data or null.
     */
    private function check_cart_abandonment() {
        $metrics = $this->calculator->get_metrics();
        $abandonment_rate = $metrics['cart_abandonment_rate'] ?? 0.69;

        // If abandonment rate is above 70%, it's a critical issue
        if ( $abandonment_rate > 0.70 ) {
            $impact = $this->calculator->calculate_impact( 'checkout_abandonment', array(
                'abandonment_rate' => $abandonment_rate,
            ) );

            return array(
                'severity'         => 'critical',
                'title'            => sprintf(
                    /* translators: %s: abandonment percentage */
                    __( 'High Cart Abandonment Rate (%s%%)', 'revenue-leak-scanner' ),
                    round( $abandonment_rate * 100 )
                ),
                'description'      => sprintf(
                    /* translators: %s: percentage */
                    __( 'Your estimated cart abandonment rate is %s%%, which is above the industry average of 69%%. This means potential customers are adding items to their cart but leaving before purchase.', 'revenue-leak-scanner' ),
                    round( $abandonment_rate * 100 )
                ),
                'revenue_impact'   => $impact,
                'confidence_score' => $this->calculator->get_confidence_score( 'cart_abandonment' ),
                'fix_suggestion'   => __( 'Implement abandoned cart recovery emails, simplify checkout, add trust badges, offer guest checkout, and ensure fast page load times.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'hard',
                'fix_url'          => '',
                'affected_items'   => array( 'cart_page', 'checkout_page' ),
            );
        }

        return null;
    }

    /**
     * Check free shipping threshold optimization.
     *
     * @return array|null Leak data or null.
     */
    private function check_free_shipping_threshold() {
        $metrics = $this->calculator->get_metrics();
        $aov = $metrics['avg_order_value'] ?? 50;

        $shipping_zones = \WC_Shipping_Zones::get_zones();
        foreach ( $shipping_zones as $zone ) {
            $zone_obj = new \WC_Shipping_Zone( $zone['id'] );
            $methods = $zone_obj->get_shipping_methods( true );

            foreach ( $methods as $method ) {
                if ( 'free_shipping' === $method->id ) {
                    $min_amount = $method->get_option( 'min_amount', 0 );

                    // If threshold is more than 2x AOV, it's too high
                    if ( $min_amount > 0 && $min_amount > ( $aov * 2 ) ) {
                        $impact = $metrics['monthly_revenue'] * 0.04;

                        return array(
                            'severity'         => 'medium',
                            'title'            => __( 'Free Shipping Threshold Too High', 'revenue-leak-scanner' ),
                            'description'      => sprintf(
                                /* translators: %s: threshold amount, %s: AOV */
                                __( 'Your free shipping threshold ($%1$s) is more than 2x your average order value ($%2$s). Set it to 20-30%% above AOV for optimal results.', 'revenue-leak-scanner' ),
                                number_format( $min_amount, 2 ),
                                number_format( $aov, 2 )
                            ),
                            'revenue_impact'   => round( $impact, 2 ),
                            'confidence_score' => $this->calculator->get_confidence_score( 'shipping_threshold', array( 'measured' => true ) ),
                            'fix_suggestion'   => sprintf(
                                /* translators: %s: recommended threshold */
                                __( 'Lower your free shipping threshold to around $%s (20-30%% above your AOV) to encourage more orders to qualify.', 'revenue-leak-scanner' ),
                                number_format( $aov * 1.25, 2 )
                            ),
                            'fix_difficulty'   => 'easy',
                            'fix_url'          => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
                            'affected_items'   => array( 'free_shipping_threshold' ),
                        );
                    }
                }
            }
        }

        return null;
    }
}
