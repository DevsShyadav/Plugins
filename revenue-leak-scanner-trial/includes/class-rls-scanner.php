<?php
/**
 * Main Scanner Orchestrator.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Orchestrates all scan modules and compiles results.
 */
class Scanner {

    /**
     * Revenue calculator instance.
     *
     * @var Revenue_Calculator
     */
    private $calculator;

    /**
     * Available scanner modules.
     *
     * @var array
     */
    private $modules = array();

    /**
     * Current scan ID.
     *
     * @var int
     */
    private $scan_id = 0;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->calculator = new Revenue_Calculator();
        $this->register_modules();
    }

    /**
     * Register all scanner modules.
     */
    private function register_modules() {
        $this->modules = array(
            'checkout'    => new Scanners\Checkout_Scanner( $this->calculator ),
            'product'     => new Scanners\Product_Scanner( $this->calculator ),
            'performance' => new Scanners\Performance_Scanner( $this->calculator ),
            'mobile'      => new Scanners\Mobile_Scanner( $this->calculator ),
            'seo'         => new Scanners\SEO_Scanner( $this->calculator ),
            'trust'       => new Scanners\Trust_Scanner( $this->calculator ),
        );
    }

    /**
     * Run a full scan.
     *
     * @param array $params Scan parameters.
     * @return array Scan results.
     */
    public function run_scan( $params = array() ) {
        $scan_type = $params['scan_type'] ?? 'full';
        $modules_to_run = $params['modules'] ?? array_keys( $this->modules );

        // Clear all caches before scanning to ensure fresh data
        Cache::flush_all();

        // Reinitialize calculator with fresh data
        $this->calculator = new Revenue_Calculator();

        // Create scan record
        $this->scan_id = Database::create_scan( $scan_type );

        if ( ! $this->scan_id ) {
            return array(
                'success' => false,
                'message' => __( 'Failed to create scan record.', 'revenue-leak-scanner' ),
            );
        }

        $all_leaks = array();
        $total_revenue_loss = 0.0;
        $category_summaries = array();

        // Run each module
        foreach ( $modules_to_run as $module_key ) {
            if ( ! isset( $this->modules[ $module_key ] ) ) {
                continue;
            }

            $module = $this->modules[ $module_key ];
            $module_results = $module->scan();

            if ( ! empty( $module_results['leaks'] ) ) {
                foreach ( $module_results['leaks'] as $leak ) {
                    $leak['scan_id'] = $this->scan_id;
                    $leak['category'] = $module_key;

                    // Save to database
                    $leak_id = Database::save_leak( $leak );
                    $leak['id'] = $leak_id;

                    $all_leaks[] = $leak;
                    $total_revenue_loss += $leak['revenue_impact'];
                }
            }

            $category_summaries[ $module_key ] = array(
                'leak_count'     => count( $module_results['leaks'] ?? array() ),
                'revenue_impact' => $module_results['total_impact'] ?? 0,
                'severity'       => $module_results['max_severity'] ?? 'low',
            );
        }

        // Sort leaks by revenue impact (highest first)
        usort( $all_leaks, function ( $a, $b ) {
            return $b['revenue_impact'] <=> $a['revenue_impact'];
        } );

        // Update scan record
        $scan_data = array(
            'metrics'      => $this->calculator->get_metrics(),
            'categories'   => $category_summaries,
            'scan_params'  => $params,
        );

        Database::update_scan( $this->scan_id, 'completed', array(
            'total_leaks'        => count( $all_leaks ),
            'total_revenue_loss' => $total_revenue_loss,
            'scan_data'          => $scan_data,
        ) );

        // Save history metrics
        Database::save_history( $this->scan_id, 'total_revenue_loss', $total_revenue_loss );
        Database::save_history( $this->scan_id, 'total_leaks', count( $all_leaks ) );

        // Invalidate caches
        Cache::invalidate_scan( $this->scan_id );

        $results = array(
            'success'            => true,
            'scan_id'            => $this->scan_id,
            'total_leaks'        => count( $all_leaks ),
            'total_revenue_loss' => $total_revenue_loss,
            'formatted_loss'     => $this->calculator->format_currency( $total_revenue_loss ),
            'leaks'              => $all_leaks,
            'categories'         => $category_summaries,
            'metrics'            => $this->calculator->get_metrics(),
            'scan_type'          => $scan_type,
            'completed_at'       => current_time( 'mysql' ),
        );

        /**
         * Fires after a scan is completed.
         *
         * @param array $results Scan results.
         * @param int   $scan_id Scan ID.
         */
        do_action( 'rls_scan_completed', $results, $this->scan_id );

        return $results;
    }

    /**
     * Get results from the latest scan.
     *
     * @return array|null
     */
    public function get_latest_results() {
        $cached = Cache::get( 'latest_scan_results' );
        if ( false !== $cached ) {
            return $cached;
        }

        $scan = Database::get_latest_scan();

        if ( ! $scan ) {
            return null;
        }

        $leaks = Database::get_leaks( $scan->id );
        $scan_data = json_decode( $scan->scan_data, true );

        $results = array(
            'scan_id'            => $scan->id,
            'total_leaks'        => $scan->total_leaks,
            'total_revenue_loss' => (float) $scan->total_revenue_loss,
            'formatted_loss'     => $this->calculator->format_currency( $scan->total_revenue_loss ),
            'leaks'              => $leaks,
            'categories'         => $scan_data['categories'] ?? array(),
            'metrics'            => $scan_data['metrics'] ?? array(),
            'scan_type'          => $scan->scan_type,
            'completed_at'       => $scan->completed_at,
        );

        Cache::set( 'latest_scan_results', $results, 1800 );

        return $results;
    }

    /**
     * Get scan comparison (current vs previous).
     *
     * @return array
     */
    public function get_comparison() {
        $history = Database::get_scan_history( 2 );

        if ( count( $history ) < 2 ) {
            return array(
                'has_comparison' => false,
                'message'        => __( 'Need at least 2 scans for comparison.', 'revenue-leak-scanner' ),
            );
        }

        $current = $history[0];
        $previous = $history[1];

        $leak_change = $current->total_leaks - $previous->total_leaks;
        $revenue_change = (float) $current->total_revenue_loss - (float) $previous->total_revenue_loss;

        return array(
            'has_comparison'  => true,
            'leak_change'     => $leak_change,
            'revenue_change'  => $revenue_change,
            'leak_trend'      => $leak_change <= 0 ? 'improving' : 'worsening',
            'revenue_trend'   => $revenue_change <= 0 ? 'improving' : 'worsening',
            'current_scan'    => $current,
            'previous_scan'   => $previous,
        );
    }
}
