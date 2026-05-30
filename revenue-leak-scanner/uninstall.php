<?php
/**
 * Uninstall handler.
 *
 * Removes all plugin data when the plugin is deleted via WordPress admin.
 *
 * @package RevenueLeakScanner
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove custom tables
$tables = array(
    $wpdb->prefix . 'rls_scans',
    $wpdb->prefix . 'rls_leaks',
    $wpdb->prefix . 'rls_history',
    $wpdb->prefix . 'rls_store_metrics',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Remove all plugin options
$options = array(
    'rls_db_version',
    'rls_scan_frequency',
    'rls_email_reports',
    'rls_report_email',
    'rls_currency',
    'rls_avg_order_value',
    'rls_monthly_visitors',
    'rls_monthly_orders',
    'rls_scan_modules',
    'rls_first_scan',
    'rls_onboarding_complete',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove all transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_rls_%'"
);
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_rls_%'"
);

// Clear scheduled events
$events = array( 'rls_scheduled_scan', 'rls_cleanup_old_scans' );
foreach ( $events as $event ) {
    $timestamp = wp_next_scheduled( $event );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, $event );
    }
}
