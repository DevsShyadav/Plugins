<?php
/**
 * Cache Handler.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles caching operations for performance.
 */
class Cache {

    /**
     * Cache group identifier.
     *
     * @var string
     */
    const GROUP = 'rls_cache';

    /**
     * Default cache expiration (1 hour).
     *
     * @var int
     */
    const DEFAULT_EXPIRATION = 3600;

    /**
     * Get cached value.
     *
     * @param string $key Cache key.
     * @return mixed|false Cached value or false if not found.
     */
    public static function get( $key ) {
        $cached = wp_cache_get( $key, self::GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        // Fallback to transients for persistent cache
        $transient = get_transient( 'rls_' . $key );
        if ( false !== $transient ) {
            wp_cache_set( $key, $transient, self::GROUP, self::DEFAULT_EXPIRATION );
            return $transient;
        }

        return false;
    }

    /**
     * Set cache value.
     *
     * @param string $key        Cache key.
     * @param mixed  $value      Value to cache.
     * @param int    $expiration Expiration time in seconds.
     * @return bool
     */
    public static function set( $key, $value, $expiration = null ) {
        if ( null === $expiration ) {
            $expiration = self::DEFAULT_EXPIRATION;
        }

        wp_cache_set( $key, $value, self::GROUP, $expiration );
        set_transient( 'rls_' . $key, $value, $expiration );

        return true;
    }

    /**
     * Delete cache value.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public static function delete( $key ) {
        wp_cache_delete( $key, self::GROUP );
        delete_transient( 'rls_' . $key );

        return true;
    }

    /**
     * Flush all plugin cache.
     *
     * @return bool
     */
    public static function flush_all() {
        global $wpdb;

        wp_cache_flush();

        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_rls_%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_rls_%'"
        );

        return true;
    }

    /**
     * Get or set cached value using callback.
     *
     * @param string   $key        Cache key.
     * @param callable $callback   Callback to generate value.
     * @param int      $expiration Expiration time in seconds.
     * @return mixed
     */
    public static function remember( $key, $callback, $expiration = null ) {
        $cached = self::get( $key );

        if ( false !== $cached ) {
            return $cached;
        }

        $value = call_user_func( $callback );
        self::set( $key, $value, $expiration );

        return $value;
    }

    /**
     * Invalidate scan-related caches.
     *
     * @param int $scan_id Scan ID to invalidate.
     */
    public static function invalidate_scan( $scan_id ) {
        self::delete( 'latest_scan' );
        self::delete( 'scan_' . $scan_id );
        self::delete( 'scan_leaks_' . $scan_id );
        self::delete( 'dashboard_data' );
        self::delete( 'scan_history' );
    }
}
