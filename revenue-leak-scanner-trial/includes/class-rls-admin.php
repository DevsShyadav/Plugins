<?php
/**
 * Admin Handler.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles admin pages and menu registration.
 */
class Admin {

    /**
     * Singleton instance.
     *
     * @var Admin|null
     */
    private static $instance = null;

    /**
     * Get instance.
     *
     * @return Admin
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
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_activation_redirect' ) );
        add_filter( 'plugin_action_links_' . RLS_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
    }

    /**
     * Register admin menu.
     */
    public function register_menu() {
        add_menu_page(
            __( 'Revenue Leak Scanner', 'revenue-leak-scanner' ),
            __( 'Revenue Leaks', 'revenue-leak-scanner' ),
            'manage_woocommerce',
            'revenue-leak-scanner',
            array( $this, 'render_dashboard' ),
            'dashicons-chart-line',
            56
        );

        add_submenu_page(
            'revenue-leak-scanner',
            __( 'Dashboard', 'revenue-leak-scanner' ),
            __( 'Dashboard', 'revenue-leak-scanner' ),
            'manage_woocommerce',
            'revenue-leak-scanner',
            array( $this, 'render_dashboard' )
        );

        add_submenu_page(
            'revenue-leak-scanner',
            __( 'Scan Results', 'revenue-leak-scanner' ),
            __( 'Scan Results', 'revenue-leak-scanner' ),
            'manage_woocommerce',
            'rls-results',
            array( $this, 'render_results' )
        );

        add_submenu_page(
            'revenue-leak-scanner',
            __( 'History', 'revenue-leak-scanner' ),
            __( 'History', 'revenue-leak-scanner' ),
            'manage_woocommerce',
            'rls-history',
            array( $this, 'render_history' )
        );

        add_submenu_page(
            'revenue-leak-scanner',
            __( 'Settings', 'revenue-leak-scanner' ),
            __( 'Settings', 'revenue-leak-scanner' ),
            'manage_woocommerce',
            'rls-settings',
            array( $this, 'render_settings' )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        // Match any page that belongs to our plugin
        if ( strpos( $hook, 'revenue-leak-scanner' ) === false && strpos( $hook, 'rls-' ) === false ) {
            return;
        }

        // Use filemtime for cache busting during development
        $css_ver = filemtime( RLS_PLUGIN_DIR . 'assets/css/admin.css' );
        $js_ver = filemtime( RLS_PLUGIN_DIR . 'assets/js/admin.js' );

        // Main CSS
        wp_enqueue_style(
            'rls-admin',
            RLS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $css_ver
        );

        // Chart.js from CDN (reliable)
        wp_enqueue_script(
            'rls-chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );

        // Main admin JS
        wp_enqueue_script(
            'rls-admin',
            RLS_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'rls-chartjs' ),
            $js_ver,
            true
        );

        // Localize script data
        wp_localize_script( 'rls-admin', 'rlsAdmin', array(
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'restUrl'       => rest_url( 'rls/v1/' ),
            'nonce'         => wp_create_nonce( 'rls_nonce' ),
            'restNonce'     => wp_create_nonce( 'wp_rest' ),
            'currency'      => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
            'isFirstScan'   => ! get_option( 'rls_first_scan' ),
            'strings'       => array(
                'scanning'      => __( 'Scanning your store...', 'revenue-leak-scanner' ),
                'scanComplete'  => __( 'Scan complete!', 'revenue-leak-scanner' ),
                'scanError'     => __( 'Scan failed. Please try again.', 'revenue-leak-scanner' ),
                'confirmScan'   => __( 'Start a new scan?', 'revenue-leak-scanner' ),
                'noLeaks'       => __( 'Great news! No significant revenue leaks detected.', 'revenue-leak-scanner' ),
                'fixApplied'    => __( 'Marked as fixed!', 'revenue-leak-scanner' ),
            ),
        ) );
    }


    /**
     * Handle activation redirect to dashboard.
     */
    public function handle_activation_redirect() {
        if ( get_transient( 'rls_activation_redirect' ) ) {
            delete_transient( 'rls_activation_redirect' );

            if ( ! isset( $_GET['activate-multi'] ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=revenue-leak-scanner&welcome=1' ) );
                exit;
            }
        }
    }

    /**
     * Add plugin action links.
     *
     * @param array $links Existing action links.
     * @return array Modified links.
     */
    public function plugin_action_links( $links ) {
        $custom_links = array(
            '<a href="' . admin_url( 'admin.php?page=revenue-leak-scanner' ) . '">' . __( 'Dashboard', 'revenue-leak-scanner' ) . '</a>',
            '<a href="' . admin_url( 'admin.php?page=rls-settings' ) . '">' . __( 'Settings', 'revenue-leak-scanner' ) . '</a>',
        );

        return array_merge( $custom_links, $links );
    }

    /**
     * Render dashboard page.
     */
    public function render_dashboard() {
        $this->render_page( 'dashboard' );
    }

    /**
     * Render results page.
     */
    public function render_results() {
        $this->render_page( 'results' );
    }

    /**
     * Render history page.
     */
    public function render_history() {
        $this->render_page( 'history' );
    }

    /**
     * Render settings page.
     */
    public function render_settings() {
        $this->render_page( 'settings' );
    }

    /**
     * Render an admin page template.
     *
     * @param string $page Page template name.
     */
    private function render_page( $page ) {
        $template = RLS_PLUGIN_DIR . "templates/admin/{$page}.php";

        if ( file_exists( $template ) ) {
            include $template;
        }
    }
}
