<?php
/**
 * Plugin Name: Revenue Leak Scanner — Trial
 * Plugin URI: https://revenueleakscanner.com
 * Description: [24-HOUR TRIAL] Discover exactly where your WooCommerce store leaks money. Full access for 24 hours.
 * Version: 1.0.0-trial
 * Author: Revenue Leak Scanner
 * Author URI: https://revenueleakscanner.com
 * License: GPL v2 or later
 * Text Domain: revenue-leak-scanner
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RLS_VERSION', '1.0.0-trial' );
define( 'RLS_PLUGIN_FILE', __FILE__ );
define( 'RLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RLS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RLS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'RLS_IS_TRIAL', true );
define( 'RLS_TRIAL_HOURS', 24 );
define( 'RLS_BUY_URL', 'https://revenueleakscanner.com/#pricing' );

final class RevenueLeakScanner {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->check_requirements();
        $this->load_dependencies();
        $this->init_hooks();
        $this->start_trial_clock();
    }

    private function check_requirements() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    private function load_dependencies() {
        require_once RLS_PLUGIN_DIR . 'includes/class-rls-activator.php';
        require_once RLS_PLUGIN_DIR . 'includes/class-rls-deactivator.php';
        require_once RLS_PLUGIN_DIR . 'includes/class-rls-database.php';
        require_once RLS_PLUGIN_DIR . 'includes/class-rls-scanner.php';
        require_once RLS_PLUGIN_DIR . 'includes/class-rls-revenue-calculator.php';
        require_once RLS_PLUGIN_DIR . 'includes/class-rls-admin.php';
        require_once RLS_PLUGIN_DIR . 'includes/class-rls-ajax.php';
        require_once RLS_PLUGIN_DIR . 'includes/class-rls-rest-api.php';
        require_once RLS_PLUGIN_DIR . 'includes/class-rls-cache.php';
        require_once RLS_PLUGIN_DIR . 'includes/class-rls-security.php';
        require_once RLS_PLUGIN_DIR . 'includes/class-rls-trial.php';

        require_once RLS_PLUGIN_DIR . 'includes/scanners/class-rls-checkout-scanner.php';
        require_once RLS_PLUGIN_DIR . 'includes/scanners/class-rls-product-scanner.php';
        require_once RLS_PLUGIN_DIR . 'includes/scanners/class-rls-performance-scanner.php';
        require_once RLS_PLUGIN_DIR . 'includes/scanners/class-rls-mobile-scanner.php';
        require_once RLS_PLUGIN_DIR . 'includes/scanners/class-rls-seo-scanner.php';
        require_once RLS_PLUGIN_DIR . 'includes/scanners/class-rls-trust-scanner.php';
    }

    private function init_hooks() {
        register_activation_hook( RLS_PLUGIN_FILE, array( 'RevenueLeakScanner\\Activator', 'activate' ) );
        register_deactivation_hook( RLS_PLUGIN_FILE, array( 'RevenueLeakScanner\\Deactivator', 'deactivate' ) );

        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
    }

    private function start_trial_clock() {
        if ( false === get_option( 'rls_trial_started' ) ) {
            update_option( 'rls_trial_started', time() );
        }
    }

    public static function trial_remaining() {
        $started = (int) get_option( 'rls_trial_started', 0 );
        if ( ! $started ) return 0;
        return max( 0, ( $started + ( RLS_TRIAL_HOURS * 3600 ) ) - time() );
    }

    public static function is_trial_expired() {
        return self::trial_remaining() <= 0;
    }

    public function on_plugins_loaded() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        // Always init trial (for countdown display)
        Trial::get_instance();

        if ( is_admin() ) {
            Admin::get_instance();

            // Block scan if expired
            if ( ! self::is_trial_expired() ) {
                Ajax::get_instance();
            }
        }

        if ( ! self::is_trial_expired() ) {
            Rest_API::get_instance();
        }
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'revenue-leak-scanner', false, dirname( RLS_PLUGIN_BASENAME ) . '/languages' );
    }

    public function declare_hpos_compatibility() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', RLS_PLUGIN_FILE, true );
        }
    }

    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        printf( esc_html__( 'Revenue Leak Scanner requires %s to be installed and active.', 'revenue-leak-scanner' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' );
        echo '</p></div>';
    }
}

RevenueLeakScanner::get_instance();
