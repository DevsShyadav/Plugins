<?php
/**
 * Plugin Activator.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin activation.
 */
class Activator {

    /**
     * Run activation tasks.
     */
    public static function activate() {
        self::check_requirements();
        self::create_tables();
        self::set_default_options();
        self::schedule_events();

        // Set activation flag for redirect
        set_transient( 'rls_activation_redirect', true, 30 );

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Check minimum requirements.
     */
    private static function check_requirements() {
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( RLS_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'Revenue Leak Scanner requires PHP 7.4 or higher.', 'revenue-leak-scanner' ),
                'Plugin Activation Error',
                array( 'back_link' => true )
            );
        }

        if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
            deactivate_plugins( RLS_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'Revenue Leak Scanner requires WordPress 6.0 or higher.', 'revenue-leak-scanner' ),
                'Plugin Activation Error',
                array( 'back_link' => true )
            );
        }
    }

    /**
     * Create custom database tables.
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        // Scan results table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rls_scans (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scan_type varchar(50) NOT NULL DEFAULT 'full',
            status varchar(20) NOT NULL DEFAULT 'pending',
            total_leaks int(11) NOT NULL DEFAULT 0,
            total_revenue_loss decimal(12,2) NOT NULL DEFAULT 0.00,
            scan_data longtext DEFAULT NULL,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY scan_type (scan_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Individual leak items table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rls_leaks (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scan_id bigint(20) unsigned NOT NULL,
            category varchar(50) NOT NULL,
            severity varchar(20) NOT NULL DEFAULT 'medium',
            title varchar(255) NOT NULL,
            description text NOT NULL,
            revenue_impact decimal(12,2) NOT NULL DEFAULT 0.00,
            confidence_score int(3) NOT NULL DEFAULT 50,
            affected_items text DEFAULT NULL,
            fix_suggestion text DEFAULT NULL,
            fix_difficulty varchar(20) NOT NULL DEFAULT 'medium',
            fix_url varchar(500) DEFAULT NULL,
            is_fixed tinyint(1) NOT NULL DEFAULT 0,
            fixed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY scan_id (scan_id),
            KEY category (category),
            KEY severity (severity),
            KEY is_fixed (is_fixed),
            KEY revenue_impact (revenue_impact)
        ) $charset_collate;";

        // Scan history / trends table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rls_history (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scan_id bigint(20) unsigned NOT NULL,
            metric_key varchar(100) NOT NULL,
            metric_value decimal(12,2) NOT NULL DEFAULT 0.00,
            recorded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY scan_id (scan_id),
            KEY metric_key (metric_key),
            KEY recorded_at (recorded_at)
        ) $charset_collate;";

        // Store configuration and benchmarks
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rls_store_metrics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            metric_key varchar(100) NOT NULL,
            metric_value text NOT NULL,
            period varchar(20) NOT NULL DEFAULT 'monthly',
            recorded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY metric_period (metric_key, period),
            KEY recorded_at (recorded_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ( $sql as $query ) {
            dbDelta( $query );
        }

        update_option( 'rls_db_version', RLS_VERSION );
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options() {
        $defaults = array(
            'rls_scan_frequency'     => 'weekly',
            'rls_email_reports'      => 'yes',
            'rls_report_email'       => get_option( 'admin_email' ),
            'rls_currency'           => get_woocommerce_currency(),
            'rls_avg_order_value'    => 0,
            'rls_monthly_visitors'   => 0,
            'rls_monthly_orders'     => 0,
            'rls_scan_modules'       => array(
                'checkout',
                'product',
                'performance',
                'mobile',
                'seo',
                'trust',
            ),
            'rls_first_scan'         => false,
            'rls_onboarding_complete' => false,
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                update_option( $key, $value );
            }
        }
    }

    /**
     * Schedule cron events.
     */
    private static function schedule_events() {
        if ( ! wp_next_scheduled( 'rls_scheduled_scan' ) ) {
            wp_schedule_event( time(), 'weekly', 'rls_scheduled_scan' );
        }

        if ( ! wp_next_scheduled( 'rls_cleanup_old_scans' ) ) {
            wp_schedule_event( time(), 'daily', 'rls_cleanup_old_scans' );
        }
    }
}
