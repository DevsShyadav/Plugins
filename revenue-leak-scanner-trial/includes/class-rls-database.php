<?php
/**
 * Database Handler.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles all database operations.
 */
class Database {

    /**
     * Get WordPress database instance.
     *
     * @return \wpdb
     */
    private static function db() {
        global $wpdb;
        return $wpdb;
    }

    /**
     * Create a new scan record.
     *
     * @param string $scan_type Scan type (full, quick, category).
     * @return int|false Scan ID or false on failure.
     */
    public static function create_scan( $scan_type = 'full' ) {
        $db = self::db();

        $result = $db->insert(
            $db->prefix . 'rls_scans',
            array(
                'scan_type'  => sanitize_text_field( $scan_type ),
                'status'     => 'running',
                'started_at' => current_time( 'mysql' ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );

        return $result ? $db->insert_id : false;
    }

    /**
     * Update scan status and results.
     *
     * @param int    $scan_id     Scan ID.
     * @param string $status      New status.
     * @param array  $data        Additional data to update.
     * @return bool
     */
    public static function update_scan( $scan_id, $status, $data = array() ) {
        $db = self::db();

        $update_data = array(
            'status' => sanitize_text_field( $status ),
        );
        $format = array( '%s' );

        if ( isset( $data['total_leaks'] ) ) {
            $update_data['total_leaks'] = absint( $data['total_leaks'] );
            $format[] = '%d';
        }

        if ( isset( $data['total_revenue_loss'] ) ) {
            $update_data['total_revenue_loss'] = floatval( $data['total_revenue_loss'] );
            $format[] = '%f';
        }

        if ( isset( $data['scan_data'] ) ) {
            $update_data['scan_data'] = wp_json_encode( $data['scan_data'] );
            $format[] = '%s';
        }

        if ( 'completed' === $status ) {
            $update_data['completed_at'] = current_time( 'mysql' );
            $format[] = '%s';
        }

        return (bool) $db->update(
            $db->prefix . 'rls_scans',
            $update_data,
            array( 'id' => absint( $scan_id ) ),
            $format,
            array( '%d' )
        );
    }

    /**
     * Get a scan by ID.
     *
     * @param int $scan_id Scan ID.
     * @return object|null
     */
    public static function get_scan( $scan_id ) {
        $db = self::db();

        return $db->get_row(
            $db->prepare(
                "SELECT * FROM {$db->prefix}rls_scans WHERE id = %d",
                absint( $scan_id )
            )
        );
    }

    /**
     * Get the latest completed scan.
     *
     * @return object|null
     */
    public static function get_latest_scan() {
        $db = self::db();

        return $db->get_row(
            "SELECT * FROM {$db->prefix}rls_scans WHERE status = 'completed' ORDER BY completed_at DESC LIMIT 1"
        );
    }

    /**
     * Get scan history.
     *
     * @param int $limit Number of scans to retrieve.
     * @return array
     */
    public static function get_scan_history( $limit = 10 ) {
        $db = self::db();

        return $db->get_results(
            $db->prepare(
                "SELECT * FROM {$db->prefix}rls_scans WHERE status = 'completed' ORDER BY completed_at DESC LIMIT %d",
                absint( $limit )
            )
        );
    }

    /**
     * Save a leak item.
     *
     * @param array $leak_data Leak data.
     * @return int|false Leak ID or false.
     */
    public static function save_leak( $leak_data ) {
        $db = self::db();

        $data = array(
            'scan_id'          => absint( $leak_data['scan_id'] ),
            'category'         => sanitize_text_field( $leak_data['category'] ),
            'severity'         => sanitize_text_field( $leak_data['severity'] ),
            'title'            => sanitize_text_field( $leak_data['title'] ),
            'description'      => sanitize_textarea_field( $leak_data['description'] ),
            'revenue_impact'   => floatval( $leak_data['revenue_impact'] ),
            'confidence_score' => absint( $leak_data['confidence_score'] ),
            'affected_items'   => isset( $leak_data['affected_items'] ) ? wp_json_encode( $leak_data['affected_items'] ) : null,
            'fix_suggestion'   => isset( $leak_data['fix_suggestion'] ) ? sanitize_textarea_field( $leak_data['fix_suggestion'] ) : null,
            'fix_difficulty'   => sanitize_text_field( $leak_data['fix_difficulty'] ?? 'medium' ),
            'fix_url'          => isset( $leak_data['fix_url'] ) ? esc_url_raw( $leak_data['fix_url'] ) : null,
            'created_at'       => current_time( 'mysql' ),
        );

        $format = array( '%d', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s', '%s' );

        $result = $db->insert( $db->prefix . 'rls_leaks', $data, $format );

        return $result ? $db->insert_id : false;
    }

    /**
     * Get leaks for a scan.
     *
     * @param int    $scan_id  Scan ID.
     * @param string $category Optional category filter.
     * @param string $severity Optional severity filter.
     * @return array
     */
    public static function get_leaks( $scan_id, $category = '', $severity = '' ) {
        $db = self::db();

        $where = array( 'scan_id = %d' );
        $params = array( absint( $scan_id ) );

        if ( ! empty( $category ) ) {
            $where[] = 'category = %s';
            $params[] = sanitize_text_field( $category );
        }

        if ( ! empty( $severity ) ) {
            $where[] = 'severity = %s';
            $params[] = sanitize_text_field( $severity );
        }

        $where_clause = implode( ' AND ', $where );

        return $db->get_results(
            $db->prepare(
                "SELECT * FROM {$db->prefix}rls_leaks WHERE {$where_clause} ORDER BY revenue_impact DESC",
                ...$params
            )
        );
    }

    /**
     * Mark a leak as fixed.
     *
     * @param int $leak_id Leak ID.
     * @return bool
     */
    public static function mark_leak_fixed( $leak_id ) {
        $db = self::db();

        return (bool) $db->update(
            $db->prefix . 'rls_leaks',
            array(
                'is_fixed' => 1,
                'fixed_at' => current_time( 'mysql' ),
            ),
            array( 'id' => absint( $leak_id ) ),
            array( '%d', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Save store metric.
     *
     * @param string $key    Metric key.
     * @param mixed  $value  Metric value.
     * @param string $period Period (daily, weekly, monthly).
     * @return bool
     */
    public static function save_metric( $key, $value, $period = 'monthly' ) {
        $db = self::db();

        $result = $db->replace(
            $db->prefix . 'rls_store_metrics',
            array(
                'metric_key'   => sanitize_text_field( $key ),
                'metric_value' => is_array( $value ) ? wp_json_encode( $value ) : sanitize_text_field( (string) $value ),
                'period'       => sanitize_text_field( $period ),
                'recorded_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );

        return (bool) $result;
    }

    /**
     * Get store metric.
     *
     * @param string $key    Metric key.
     * @param string $period Period.
     * @return string|null
     */
    public static function get_metric( $key, $period = 'monthly' ) {
        $db = self::db();

        return $db->get_var(
            $db->prepare(
                "SELECT metric_value FROM {$db->prefix}rls_store_metrics WHERE metric_key = %s AND period = %s ORDER BY recorded_at DESC LIMIT 1",
                sanitize_text_field( $key ),
                sanitize_text_field( $period )
            )
        );
    }

    /**
     * Save history metric for trend tracking.
     *
     * @param int    $scan_id Scan ID.
     * @param string $key     Metric key.
     * @param float  $value   Metric value.
     * @return bool
     */
    public static function save_history( $scan_id, $key, $value ) {
        $db = self::db();

        return (bool) $db->insert(
            $db->prefix . 'rls_history',
            array(
                'scan_id'      => absint( $scan_id ),
                'metric_key'   => sanitize_text_field( $key ),
                'metric_value' => floatval( $value ),
                'recorded_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%f', '%s' )
        );
    }

    /**
     * Get history trend data.
     *
     * @param string $key   Metric key.
     * @param int    $limit Number of records.
     * @return array
     */
    public static function get_history_trend( $key, $limit = 30 ) {
        $db = self::db();

        return $db->get_results(
            $db->prepare(
                "SELECT metric_value, recorded_at FROM {$db->prefix}rls_history WHERE metric_key = %s ORDER BY recorded_at DESC LIMIT %d",
                sanitize_text_field( $key ),
                absint( $limit )
            )
        );
    }

    /**
     * Clean up old scan data.
     *
     * @param int $days_to_keep Number of days to retain.
     */
    public static function cleanup_old_data( $days_to_keep = 90 ) {
        $db = self::db();

        $cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_to_keep} days" ) );

        // Get old scan IDs
        $old_scans = $db->get_col(
            $db->prepare(
                "SELECT id FROM {$db->prefix}rls_scans WHERE created_at < %s",
                $cutoff_date
            )
        );

        if ( ! empty( $old_scans ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $old_scans ), '%d' ) );

            // Delete related leaks
            $db->query(
                $db->prepare(
                    "DELETE FROM {$db->prefix}rls_leaks WHERE scan_id IN ($placeholders)",
                    ...$old_scans
                )
            );

            // Delete related history
            $db->query(
                $db->prepare(
                    "DELETE FROM {$db->prefix}rls_history WHERE scan_id IN ($placeholders)",
                    ...$old_scans
                )
            );

            // Delete scans
            $db->query(
                $db->prepare(
                    "DELETE FROM {$db->prefix}rls_scans WHERE id IN ($placeholders)",
                    ...$old_scans
                )
            );
        }
    }
}
