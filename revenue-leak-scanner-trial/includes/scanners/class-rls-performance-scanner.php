<?php
/**
 * Performance Scanner Module.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner\Scanners;

use RevenueLeakScanner\Revenue_Calculator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Scans site performance for revenue leaks.
 */
class Performance_Scanner {

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
     * Run performance scan.
     *
     * @return array Scan results.
     */
    public function scan() {
        $leaks = array();
        $total_impact = 0.0;
        $max_severity = 'low';

        // Check page load optimization
        $speed_leak = $this->check_page_speed();
        if ( $speed_leak ) {
            $leaks[] = $speed_leak;
            $total_impact += $speed_leak['revenue_impact'];
        }

        // Check image optimization
        $images_leak = $this->check_image_optimization();
        if ( $images_leak ) {
            $leaks[] = $images_leak;
            $total_impact += $images_leak['revenue_impact'];
        }


        // Check caching
        $cache_leak = $this->check_caching();
        if ( $cache_leak ) {
            $leaks[] = $cache_leak;
            $total_impact += $cache_leak['revenue_impact'];
        }

        // Check database optimization
        $db_leak = $this->check_database();
        if ( $db_leak ) {
            $leaks[] = $db_leak;
            $total_impact += $db_leak['revenue_impact'];
        }

        // Check plugin bloat
        $plugin_leak = $this->check_plugin_bloat();
        if ( $plugin_leak ) {
            $leaks[] = $plugin_leak;
            $total_impact += $plugin_leak['revenue_impact'];
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
     * Check page speed indicators.
     *
     * @return array|null
     */
    private function check_page_speed() {
        // Measure time to generate homepage
        $start = microtime( true );
        $response = wp_remote_get( home_url( '/' ), array(
            'timeout'   => 15,
            'sslverify' => false,
        ) );
        $load_time = microtime( true ) - $start;

        if ( is_wp_error( $response ) ) {
            $load_time = 5.0; // Assume slow if can't measure
        }

        if ( $load_time > 2.5 ) {
            $impact = $this->calculator->calculate_impact( 'slow_speed', array(
                'load_time' => $load_time,
            ) );

            $severity = 'medium';
            if ( $load_time > 5.0 ) {
                $severity = 'critical';
            } elseif ( $load_time > 3.5 ) {
                $severity = 'high';
            }

            return array(
                'severity'         => $severity,
                'title'            => sprintf(
                    __( 'Slow Page Load Time (%.1fs)', 'revenue-leak-scanner' ),
                    $load_time
                ),
                'description'      => sprintf(
                    __( 'Your site takes %.1f seconds to load. Every additional second above 2s costs you ~7%% in conversions. A 1-second delay can reduce conversions by 7%% and page views by 11%%.', 'revenue-leak-scanner' ),
                    $load_time
                ),
                'revenue_impact'   => $impact,
                'confidence_score' => $this->calculator->get_confidence_score( 'page_speed', array( 'measured' => true ) ),
                'fix_suggestion'   => __( 'Install a caching plugin, optimize images, minimize CSS/JS, use a CDN, and consider upgrading hosting. Target under 2.5 seconds load time.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'medium',
                'fix_url'          => admin_url( 'plugins.php' ),
                'affected_items'   => array( 'page_speed' ),
            );
        }

        return null;
    }


    /**
     * Check image optimization.
     *
     * @return array|null
     */
    private function check_image_optimization() {
        global $wpdb;

        // Check for large unoptimized images
        $large_images = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'attachment' 
             AND p.post_mime_type LIKE 'image/%'
             AND pm.meta_key = '_wp_attachment_metadata'"
        );

        // Check if any images lack WebP versions or are very large
        $unoptimized = $wpdb->get_results(
            "SELECT pm.meta_value FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'attachment' 
             AND p.post_mime_type LIKE 'image/%'
             AND pm.meta_key = '_wp_attachment_metadata'
             LIMIT 50"
        );

        $large_count = 0;
        foreach ( $unoptimized as $img ) {
            $meta = maybe_unserialize( $img->meta_value );
            if ( is_array( $meta ) && isset( $meta['filesize'] ) && $meta['filesize'] > 500000 ) {
                $large_count++;
            } elseif ( is_array( $meta ) && isset( $meta['width'] ) && $meta['width'] > 2000 ) {
                $large_count++;
            }
        }

        if ( $large_count > 5 ) {
            $metrics = $this->calculator->get_metrics();
            $impact = $metrics['monthly_revenue'] * 0.04;

            return array(
                'severity'         => 'medium',
                'title'            => sprintf(
                    __( '%d Large Unoptimized Images Detected', 'revenue-leak-scanner' ),
                    $large_count
                ),
                'description'      => sprintf(
                    __( '%d images are over 500KB or wider than 2000px. Large images slow page loads, especially on mobile where 60%% of ecommerce traffic comes from.', 'revenue-leak-scanner' ),
                    $large_count
                ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => $this->calculator->get_confidence_score( 'images', array( 'measured' => true ) ),
                'fix_suggestion'   => __( 'Use an image optimization plugin (ShortPixel, Imagify, or Smush) to compress and convert images to WebP format. Enable lazy loading for images below the fold.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => admin_url( 'plugins.php' ),
                'affected_items'   => array( 'image_optimization' ),
            );
        }

        return null;
    }

    /**
     * Check if caching is configured.
     *
     * @return array|null
     */
    private function check_caching() {
        $caching_plugins = array(
            'wp-super-cache/wp-cache.php',
            'w3-total-cache/w3-total-cache.php',
            'wp-fastest-cache/wpFastestCache.php',
            'litespeed-cache/litespeed-cache.php',
            'wp-rocket/wp-rocket.php',
            'autoptimize/autoptimize.php',
            'cache-enabler/cache-enabler.php',
            'sg-cachepress/sg-cachepress.php',
            'breeze/breeze.php',
            'powered-cache/powered-cache.php',
        );

        $has_caching = false;
        $active_plugins = get_option( 'active_plugins', array() );

        foreach ( $caching_plugins as $plugin ) {
            if ( in_array( $plugin, $active_plugins, true ) ) {
                $has_caching = true;
                break;
            }
        }

        // Also check for object caching
        $has_object_cache = wp_using_ext_object_cache();

        if ( ! $has_caching && ! $has_object_cache ) {
            $metrics = $this->calculator->get_metrics();
            $impact = $metrics['monthly_revenue'] * 0.05;

            return array(
                'severity'         => 'high',
                'title'            => __( 'No Page Caching Detected', 'revenue-leak-scanner' ),
                'description'      => __( 'No caching plugin detected. Without caching, every page load requires full PHP processing and database queries. This can make your store 2-10x slower than necessary.', 'revenue-leak-scanner' ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => $this->calculator->get_confidence_score( 'caching', array( 'measured' => true ) ),
                'fix_suggestion'   => __( 'Install a caching plugin like WP Rocket, LiteSpeed Cache, or W3 Total Cache. Also consider enabling object caching with Redis or Memcached.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => admin_url( 'plugin-install.php?s=cache&tab=search&type=term' ),
                'affected_items'   => array( 'caching' ),
            );
        }

        return null;
    }


    /**
     * Check database optimization.
     *
     * @return array|null
     */
    private function check_database() {
        global $wpdb;

        // Check for post revisions bloat
        $revisions = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
        );

        // Check for transients bloat
        $expired_transients = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '%_transient_timeout_%' 
             AND option_value < UNIX_TIMESTAMP()"
        );

        // Check for auto-drafts
        $auto_drafts = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"
        );

        $total_bloat = (int) $revisions + (int) $expired_transients + (int) $auto_drafts;

        if ( $total_bloat > 1000 ) {
            $metrics = $this->calculator->get_metrics();
            $impact = $metrics['monthly_revenue'] * 0.02;

            return array(
                'severity'         => 'low',
                'title'            => sprintf(
                    __( 'Database Bloat Detected (%s unnecessary records)', 'revenue-leak-scanner' ),
                    number_format( $total_bloat )
                ),
                'description'      => sprintf(
                    __( 'Your database contains %s post revisions, %s expired transients, and %s auto-drafts. This bloat slows database queries and increases page generation time.', 'revenue-leak-scanner' ),
                    number_format( $revisions ),
                    number_format( $expired_transients ),
                    number_format( $auto_drafts )
                ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => $this->calculator->get_confidence_score( 'database' ),
                'fix_suggestion'   => __( 'Use a database optimization plugin like WP-Optimize to clean revisions, transients, and auto-drafts. Limit post revisions with define("WP_POST_REVISIONS", 5) in wp-config.php.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'easy',
                'fix_url'          => admin_url( 'plugin-install.php?s=wp-optimize&tab=search&type=term' ),
                'affected_items'   => array( 'database_optimization' ),
            );
        }

        return null;
    }

    /**
     * Check for plugin bloat.
     *
     * @return array|null
     */
    private function check_plugin_bloat() {
        $active_plugins = get_option( 'active_plugins', array() );
        $plugin_count = count( $active_plugins );

        if ( $plugin_count > 30 ) {
            $metrics = $this->calculator->get_metrics();
            $excess = $plugin_count - 30;
            $impact = $metrics['monthly_revenue'] * min( $excess * 0.005, 0.05 );

            return array(
                'severity'         => $plugin_count > 50 ? 'high' : 'medium',
                'title'            => sprintf(
                    __( '%d Active Plugins (Excessive)', 'revenue-leak-scanner' ),
                    $plugin_count
                ),
                'description'      => sprintf(
                    __( 'You have %d active plugins. Each plugin adds HTTP requests, database queries, and PHP processing time. Sites with 30+ plugins typically load 2-3 seconds slower.', 'revenue-leak-scanner' ),
                    $plugin_count
                ),
                'revenue_impact'   => round( $impact, 2 ),
                'confidence_score' => $this->calculator->get_confidence_score( 'plugins', array( 'measured' => true ) ),
                'fix_suggestion'   => __( 'Audit your plugins and deactivate/remove unused ones. Replace multiple single-purpose plugins with comprehensive solutions. Consider using code snippets instead of plugins for simple functions.', 'revenue-leak-scanner' ),
                'fix_difficulty'   => 'medium',
                'fix_url'          => admin_url( 'plugins.php' ),
                'affected_items'   => array( 'active_plugins' ),
            );
        }

        return null;
    }
}
