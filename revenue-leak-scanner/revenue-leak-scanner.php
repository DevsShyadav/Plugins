<?php
/**
 * Plugin Name: Revenue Leak Scanner
 * Plugin URI: https://devsarun.io/plugin/rev
 * Description: Discover exactly where your WooCommerce store is leaking money. One-click scan reveals revenue leaks with dollar amounts and fix priorities.
 * Version: 1.0.0
 * Author: Revenue Leak Scanner
 * Author URI: https://devsarun.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: revenue-leak-scanner
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 8.5
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'RLS_VERSION', '1.0.0' );
define( 'RLS_PLUGIN_FILE', __FILE__ );
define( 'RLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RLS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RLS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class using singleton pattern.
 */
final class RevenueLeakScanner {

    /**
     * Plugin instance.
     *
     * @var RevenueLeakScanner|null
     */
    private static $instance = null;

    /**
     * Get plugin instance.
     *
     * @return RevenueLeakScanner
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
        $this->check_requirements();
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Check if WooCommerce is active (used during activation).
     *
     * @return bool
     */
    public static function is_woocommerce_active() {
        if ( class_exists( 'WooCommerce' ) ) {
            return true;
        }

        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( 'woocommerce/woocommerce.php' );
    }

    /**
     * Check plugin requirements.
     * Note: We do NOT prevent activation without WooCommerce.
     * Instead we show a friendly admin notice.
     */
    private function check_requirements() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    /**
     * Load plugin dependencies.
     */
    private function load_dependencies() {
        // Core classes
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

        // Scanner modules
        require_once RLS_PLUGIN_DIR . 'includes/scanners/class-rls-checkout-scanner.php';
        require_once RLS_PLUGIN_DIR . 'includes/scanners/class-rls-product-scanner.php';
        require_once RLS_PLUGIN_DIR . 'includes/scanners/class-rls-performance-scanner.php';
        require_once RLS_PLUGIN_DIR . 'includes/scanners/class-rls-mobile-scanner.php';
        require_once RLS_PLUGIN_DIR . 'includes/scanners/class-rls-seo-scanner.php';
        require_once RLS_PLUGIN_DIR . 'includes/scanners/class-rls-trust-scanner.php';
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        register_activation_hook( RLS_PLUGIN_FILE, array( 'RevenueLeakScanner\\Activator', 'activate' ) );
        register_deactivation_hook( RLS_PLUGIN_FILE, array( 'RevenueLeakScanner\\Deactivator', 'deactivate' ) );

        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Declare HPOS compatibility
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
    }

    /**
     * Actions to run after all plugins are loaded.
     */
    public function on_plugins_loaded() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        // Pro version — no trial, no restrictions, full access always
        if ( is_admin() ) {
            Admin::get_instance();
            Ajax::get_instance();
        }

        Rest_API::get_instance();
    }

    /**
     * Load plugin text domain.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'revenue-leak-scanner', false, dirname( RLS_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Declare High-Performance Order Storage compatibility.
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', RLS_PLUGIN_FILE, true );
        }
    }

    /**
     * Show WooCommerce missing notice.
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %s: WooCommerce plugin link */
                    esc_html__( 'Revenue Leak Scanner requires %s to be installed and active.', 'revenue-leak-scanner' ),
                    '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }
}

// Initialize the plugin
RevenueLeakScanner::get_instance();
