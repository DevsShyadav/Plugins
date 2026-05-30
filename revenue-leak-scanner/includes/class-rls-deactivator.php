<?php
/**
 * Plugin Deactivator.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin deactivation.
 */
class Deactivator {

    /**
     * Run deactivation tasks.
     */
    public static function deactivate() {
        self::clear_scheduled_events();
        self::clear_transients();

        flush_rewrite_rules();
    }

    /**
     * Clear scheduled cron events.
     */
    private static function clear_scheduled_events() {
        $events = array(
            'rls_scheduled_scan',
            'rls_cleanup_old_scans',
        );

        foreach ( $events as $event ) {
            $timestamp = wp_next_scheduled( $event );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $event );
            }
        }
    }

    /**
     * Clear plugin transients.
     */
    private static function clear_transients() {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_rls_%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_rls_%'"
        );
    }
}
