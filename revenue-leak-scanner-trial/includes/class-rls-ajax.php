<?php
/**
 * AJAX Handler.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles all AJAX requests.
 */
class Ajax {

    /**
     * Singleton instance.
     *
     * @var Ajax|null
     */
    private static $instance = null;

    /**
     * Get instance.
     *
     * @return Ajax
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( 'wp_ajax_rls_run_scan', array( $this, 'run_scan' ) );
        add_action( 'wp_ajax_rls_get_results', array( $this, 'get_results' ) );
        add_action( 'wp_ajax_rls_mark_fixed', array( $this, 'mark_fixed' ) );
        add_action( 'wp_ajax_rls_get_dashboard_data', array( $this, 'get_dashboard_data' ) );
        add_action( 'wp_ajax_rls_save_settings', array( $this, 'save_settings' ) );
        add_action( 'wp_ajax_rls_dismiss_leak', array( $this, 'dismiss_leak' ) );
        add_action( 'wp_ajax_rls_get_history', array( $this, 'get_history' ) );
    }

    /**
     * Run a new scan.
     */
    public function run_scan() {
        Security::verify_ajax_request( 'rls_nonce' );

        if ( ! Security::rate_limit_check( 'scan', 3, 300 ) ) {
            wp_send_json_error( array(
                'message' => __( 'Too many scans. Please wait a few minutes before scanning again.', 'revenue-leak-scanner' ),
            ) );
        }

        $params = Security::sanitize_scan_params( $_POST );

        $scanner = new Scanner();
        $results = $scanner->run_scan( $params );

        if ( $results['success'] ) {
            update_option( 'rls_first_scan', true );
            wp_send_json_success( $results );
        } else {
            wp_send_json_error( $results );
        }
    }

    /**
     * Get latest scan results.
     */
    public function get_results() {
        Security::verify_ajax_request( 'rls_nonce' );

        $scanner = new Scanner();
        $results = $scanner->get_latest_results();

        if ( $results ) {
            wp_send_json_success( $results );
        } else {
            wp_send_json_error( array(
                'message' => __( 'No scan results found. Run your first scan to discover revenue leaks.', 'revenue-leak-scanner' ),
            ) );
        }
    }

    /**
     * Mark a leak as fixed.
     */
    public function mark_fixed() {
        Security::verify_ajax_request( 'rls_nonce' );

        $leak_id = isset( $_POST['leak_id'] ) ? absint( $_POST['leak_id'] ) : 0;

        if ( ! $leak_id ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid leak ID.', 'revenue-leak-scanner' ),
            ) );
        }

        $result = Database::mark_leak_fixed( $leak_id );

        if ( $result ) {
            Cache::flush_all();
            wp_send_json_success( array(
                'message' => __( 'Leak marked as fixed!', 'revenue-leak-scanner' ),
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to update leak status.', 'revenue-leak-scanner' ),
            ) );
        }
    }

    /**
     * Get dashboard data.
     */
    public function get_dashboard_data() {
        Security::verify_ajax_request( 'rls_nonce' );

        $scanner = new Scanner();
        $latest = $scanner->get_latest_results();
        $comparison = $scanner->get_comparison();
        $history = Database::get_scan_history( 10 );

        wp_send_json_success( array(
            'latest'     => $latest,
            'comparison' => $comparison,
            'history'    => $history,
            'has_scans'  => ! empty( $latest ),
        ) );
    }

    /**
     * Save plugin settings.
     */
    public function save_settings() {
        Security::verify_ajax_request( 'rls_nonce' );

        $settings = array(
            'rls_scan_frequency'  => sanitize_text_field( $_POST['scan_frequency'] ?? 'weekly' ),
            'rls_email_reports'   => sanitize_text_field( $_POST['email_reports'] ?? 'no' ),
            'rls_report_email'    => sanitize_email( $_POST['report_email'] ?? '' ),
            'rls_monthly_visitors' => absint( $_POST['monthly_visitors'] ?? 0 ),
            'rls_monthly_orders'  => absint( $_POST['monthly_orders'] ?? 0 ),
            'rls_avg_order_value' => floatval( $_POST['avg_order_value'] ?? 0 ),
        );

        if ( isset( $_POST['scan_modules'] ) && is_array( $_POST['scan_modules'] ) ) {
            $allowed_modules = array( 'checkout', 'product', 'performance', 'mobile', 'seo', 'trust' );
            $settings['rls_scan_modules'] = array_intersect(
                array_map( 'sanitize_text_field', $_POST['scan_modules'] ),
                $allowed_modules
            );
        }

        foreach ( $settings as $key => $value ) {
            update_option( $key, $value );
        }

        // Update scan schedule if frequency changed
        $old_frequency = get_option( 'rls_scan_frequency', 'weekly' );
        if ( $settings['rls_scan_frequency'] !== $old_frequency ) {
            $timestamp = wp_next_scheduled( 'rls_scheduled_scan' );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, 'rls_scheduled_scan' );
            }
            wp_schedule_event( time(), $settings['rls_scan_frequency'], 'rls_scheduled_scan' );
        }

        Cache::flush_all();

        wp_send_json_success( array(
            'message' => __( 'Settings saved successfully.', 'revenue-leak-scanner' ),
        ) );
    }

    /**
     * Dismiss a leak (mark as acknowledged).
     */
    public function dismiss_leak() {
        Security::verify_ajax_request( 'rls_nonce' );

        $leak_id = isset( $_POST['leak_id'] ) ? absint( $_POST['leak_id'] ) : 0;

        if ( ! $leak_id ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid leak ID.', 'revenue-leak-scanner' ),
            ) );
        }

        // Mark as fixed (dismissed)
        Database::mark_leak_fixed( $leak_id );

        wp_send_json_success( array(
            'message' => __( 'Leak dismissed.', 'revenue-leak-scanner' ),
        ) );
    }

    /**
     * Get scan history data.
     */
    public function get_history() {
        Security::verify_ajax_request( 'rls_nonce' );

        $limit = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 10;
        $history = Database::get_scan_history( $limit );

        $trend_data = Database::get_history_trend( 'total_revenue_loss', 30 );

        wp_send_json_success( array(
            'scans'  => $history,
            'trends' => $trend_data,
        ) );
    }
}
