<?php
/**
 * Security Handler.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles all security operations.
 */
class Security {

    /**
     * Verify nonce for AJAX requests.
     *
     * @param string $action Nonce action.
     * @param string $nonce_field Nonce field name.
     * @return bool
     */
    public static function verify_nonce( $action = 'rls_nonce', $nonce_field = 'nonce' ) {
        $nonce = '';

        if ( isset( $_REQUEST[ $nonce_field ] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST[ $nonce_field ] ) );
        } elseif ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
        }

        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $action ) ) {
            return false;
        }

        return true;
    }

    /**
     * Check if current user has required capability.
     *
     * @param string $capability Capability to check.
     * @return bool
     */
    public static function check_capability( $capability = 'manage_woocommerce' ) {
        return current_user_can( $capability );
    }

    /**
     * Verify AJAX request security.
     *
     * @param string $action Nonce action.
     * @param string $capability Required capability.
     * @return bool
     */
    public static function verify_ajax_request( $action = 'rls_nonce', $capability = 'manage_woocommerce' ) {
        if ( ! self::verify_nonce( $action ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Security verification failed.', 'revenue-leak-scanner' ) ),
                403
            );
            return false;
        }

        if ( ! self::check_capability( $capability ) ) {
            wp_send_json_error(
                array( 'message' => __( 'You do not have permission to perform this action.', 'revenue-leak-scanner' ) ),
                403
            );
            return false;
        }

        return true;
    }

    /**
     * Verify REST API request security.
     *
     * @param \WP_REST_Request $request REST request object.
     * @return bool|\WP_Error
     */
    public static function verify_rest_request( $request ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to access this endpoint.', 'revenue-leak-scanner' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Sanitize scan parameters.
     *
     * @param array $params Raw parameters.
     * @return array Sanitized parameters.
     */
    public static function sanitize_scan_params( $params ) {
        $sanitized = array();

        if ( isset( $params['scan_type'] ) ) {
            $allowed_types = array( 'full', 'quick', 'checkout', 'product', 'performance', 'mobile', 'seo', 'trust' );
            $sanitized['scan_type'] = in_array( $params['scan_type'], $allowed_types, true )
                ? $params['scan_type']
                : 'full';
        }

        if ( isset( $params['modules'] ) && is_array( $params['modules'] ) ) {
            $allowed_modules = array( 'checkout', 'product', 'performance', 'mobile', 'seo', 'trust' );
            $sanitized['modules'] = array_intersect( array_map( 'sanitize_text_field', $params['modules'] ), $allowed_modules );
        }

        return $sanitized;
    }

    /**
     * Rate limit check for scan operations.
     *
     * @param string $action Action identifier.
     * @param int    $max_attempts Maximum attempts allowed.
     * @param int    $time_window Time window in seconds.
     * @return bool True if within limits, false if rate limited.
     */
    public static function rate_limit_check( $action, $max_attempts = 5, $time_window = 300 ) {
        $user_id = get_current_user_id();
        $transient_key = "rls_rate_{$action}_{$user_id}";
        $attempts = get_transient( $transient_key );

        if ( false === $attempts ) {
            set_transient( $transient_key, 1, $time_window );
            return true;
        }

        if ( (int) $attempts >= $max_attempts ) {
            return false;
        }

        set_transient( $transient_key, (int) $attempts + 1, $time_window );
        return true;
    }

    /**
     * Sanitize and validate URL.
     *
     * @param string $url URL to validate.
     * @return string|false Sanitized URL or false if invalid.
     */
    public static function validate_url( $url ) {
        $url = esc_url_raw( $url );

        if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        // Only allow http and https protocols
        $parsed = wp_parse_url( $url );
        if ( ! in_array( $parsed['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
            return false;
        }

        return $url;
    }
}
