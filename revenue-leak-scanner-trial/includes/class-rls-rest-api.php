<?php
/**
 * REST API Handler.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles REST API endpoints.
 */
class Rest_API {

    /**
     * Singleton instance.
     *
     * @var Rest_API|null
     */
    private static $instance = null;

    /**
     * API namespace.
     *
     * @var string
     */
    const NAMESPACE = 'rls/v1';

    /**
     * Get instance.
     *
     * @return Rest_API
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
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route( self::NAMESPACE, '/scan', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'start_scan' ),
            'permission_callback' => array( 'RevenueLeakScanner\\Security', 'verify_rest_request' ),
        ) );

        register_rest_route( self::NAMESPACE, '/results', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_results' ),
            'permission_callback' => array( 'RevenueLeakScanner\\Security', 'verify_rest_request' ),
        ) );

        register_rest_route( self::NAMESPACE, '/results/(?P<scan_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_scan_results' ),
            'permission_callback' => array( 'RevenueLeakScanner\\Security', 'verify_rest_request' ),
            'args'                => array(
                'scan_id' => array(
                    'validate_callback' => function ( $param ) {
                        return is_numeric( $param );
                    },
                ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/leaks/(?P<leak_id>\d+)/fix', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'mark_leak_fixed' ),
            'permission_callback' => array( 'RevenueLeakScanner\\Security', 'verify_rest_request' ),
            'args'                => array(
                'leak_id' => array(
                    'validate_callback' => function ( $param ) {
                        return is_numeric( $param );
                    },
                ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/history', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_history' ),
            'permission_callback' => array( 'RevenueLeakScanner\\Security', 'verify_rest_request' ),
        ) );

        register_rest_route( self::NAMESPACE, '/settings', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_settings' ),
                'permission_callback' => array( 'RevenueLeakScanner\\Security', 'verify_rest_request' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'save_settings' ),
                'permission_callback' => array( 'RevenueLeakScanner\\Security', 'verify_rest_request' ),
            ),
        ) );
    }

    /**
     * Start a new scan.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function start_scan( $request ) {
        if ( ! Security::rate_limit_check( 'scan', 3, 300 ) ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Rate limit exceeded.', 'revenue-leak-scanner' ),
            ), 429 );
        }

        $params = Security::sanitize_scan_params( $request->get_params() );

        $scanner = new Scanner();
        $results = $scanner->run_scan( $params );

        if ( $results['success'] ) {
            update_option( 'rls_first_scan', true );
        }

        return new \WP_REST_Response( $results, $results['success'] ? 200 : 500 );
    }

    /**
     * Get latest scan results.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_results( $request ) {
        $scanner = new Scanner();
        $results = $scanner->get_latest_results();

        if ( ! $results ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'No scan results found.', 'revenue-leak-scanner' ),
            ), 404 );
        }

        return new \WP_REST_Response( array(
            'success' => true,
            'data'    => $results,
        ) );
    }

    /**
     * Get specific scan results.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_scan_results( $request ) {
        $scan_id = absint( $request->get_param( 'scan_id' ) );
        $scan = Database::get_scan( $scan_id );

        if ( ! $scan ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Scan not found.', 'revenue-leak-scanner' ),
            ), 404 );
        }

        $leaks = Database::get_leaks( $scan_id );

        return new \WP_REST_Response( array(
            'success' => true,
            'data'    => array(
                'scan'  => $scan,
                'leaks' => $leaks,
            ),
        ) );
    }

    /**
     * Mark a leak as fixed.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function mark_leak_fixed( $request ) {
        $leak_id = absint( $request->get_param( 'leak_id' ) );

        $result = Database::mark_leak_fixed( $leak_id );
        Cache::flush_all();

        return new \WP_REST_Response( array(
            'success' => (bool) $result,
            'message' => $result
                ? __( 'Leak marked as fixed.', 'revenue-leak-scanner' )
                : __( 'Failed to update.', 'revenue-leak-scanner' ),
        ) );
    }

    /**
     * Get scan history.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_history( $request ) {
        $limit = absint( $request->get_param( 'limit' ) ) ?: 10;
        $history = Database::get_scan_history( $limit );
        $trends = Database::get_history_trend( 'total_revenue_loss', 30 );

        return new \WP_REST_Response( array(
            'success' => true,
            'data'    => array(
                'scans'  => $history,
                'trends' => $trends,
            ),
        ) );
    }

    /**
     * Get plugin settings.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_settings( $request ) {
        return new \WP_REST_Response( array(
            'success' => true,
            'data'    => array(
                'scan_frequency'  => get_option( 'rls_scan_frequency', 'weekly' ),
                'email_reports'   => get_option( 'rls_email_reports', 'yes' ),
                'report_email'    => get_option( 'rls_report_email', get_option( 'admin_email' ) ),
                'monthly_visitors' => get_option( 'rls_monthly_visitors', 0 ),
                'monthly_orders'  => get_option( 'rls_monthly_orders', 0 ),
                'avg_order_value' => get_option( 'rls_avg_order_value', 0 ),
                'scan_modules'    => get_option( 'rls_scan_modules', array() ),
            ),
        ) );
    }

    /**
     * Save plugin settings.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function save_settings( $request ) {
        $params = $request->get_params();

        $settings_map = array(
            'scan_frequency'  => 'rls_scan_frequency',
            'email_reports'   => 'rls_email_reports',
            'report_email'    => 'rls_report_email',
            'monthly_visitors' => 'rls_monthly_visitors',
            'monthly_orders'  => 'rls_monthly_orders',
            'avg_order_value' => 'rls_avg_order_value',
            'scan_modules'    => 'rls_scan_modules',
        );

        foreach ( $settings_map as $param_key => $option_key ) {
            if ( isset( $params[ $param_key ] ) ) {
                $value = $params[ $param_key ];

                if ( 'report_email' === $param_key ) {
                    $value = sanitize_email( $value );
                } elseif ( is_array( $value ) ) {
                    $value = array_map( 'sanitize_text_field', $value );
                } elseif ( is_numeric( $value ) ) {
                    $value = is_float( $value + 0 ) ? floatval( $value ) : absint( $value );
                } else {
                    $value = sanitize_text_field( $value );
                }

                update_option( $option_key, $value );
            }
        }

        Cache::flush_all();

        return new \WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Settings saved.', 'revenue-leak-scanner' ),
        ) );
    }
}
