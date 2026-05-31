<?php
/**
 * Trial System — 24 Hour Trial with Auto-Destroy.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Trial {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_init', array( $this, 'check_and_destroy' ) );
        add_action( 'admin_bar_menu', array( $this, 'admin_bar_timer' ), 999 );
        add_action( 'admin_notices', array( $this, 'trial_notices' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'countdown_script' ) );
        add_filter( 'plugin_action_links_' . RLS_PLUGIN_BASENAME, array( $this, 'add_upgrade_link' ) );
    }

    public static function is_active() {
        return RevenueLeakScanner::trial_remaining() > 0;
    }

    public static function is_expired() {
        return RevenueLeakScanner::trial_remaining() <= 0;
    }

    public static function is_pro() {
        return false; // Trial version is never pro
    }

    public static function get_remaining_formatted() {
        $s = RevenueLeakScanner::trial_remaining();
        if ( $s <= 0 ) return '00:00:00';
        return sprintf( '%02d:%02d:%02d', floor( $s / 3600 ), floor( ( $s % 3600 ) / 60 ), $s % 60 );
    }

    /**
     * Auto-deactivate and delete plugin when expired.
     */
    public function check_and_destroy() {
        if ( ! self::is_expired() ) return;

        if ( ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Deactivate this plugin
        deactivate_plugins( RLS_PLUGIN_BASENAME );

        // Schedule self-delete
        if ( ! get_option( 'rls_trial_destroyed' ) ) {
            update_option( 'rls_trial_destroyed', true );
            // Delete plugin files
            if ( function_exists( 'delete_plugins' ) ) {
                delete_plugins( array( RLS_PLUGIN_BASENAME ) );
            }
        }

        // Show expired notice and redirect to buy page
        set_transient( 'rls_show_expired_redirect', true, 60 );
        if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'revenue-leak' ) !== false ) {
            wp_redirect( RLS_BUY_URL );
            exit;
        }
    }

    /**
     * Admin bar countdown timer.
     */
    public function admin_bar_timer( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;

        $remaining = RevenueLeakScanner::trial_remaining();

        if ( $remaining <= 0 ) {
            $title = '<span style="color:#EF4444;font-weight:700;">🔒 Trial Expired — <a href="' . esc_url( RLS_BUY_URL ) . '" style="color:#fff;text-decoration:underline;">Buy Pro $19</a></span>';
        } else {
            $color = $remaining < 3600 ? '#EF4444' : ( $remaining < 14400 ? '#F59E0B' : '#10B981' );
            $title = '<span style="color:' . $color . ';font-weight:700;">⏱️ <span id="rls-adminbar-time">' . esc_html( self::get_remaining_formatted() ) . '</span></span>';
        }

        $wp_admin_bar->add_node( array(
            'id'    => 'rls-trial-timer',
            'title' => $title,
            'href'  => admin_url( 'admin.php?page=revenue-leak-scanner' ),
        ) );
    }

    /**
     * Show trial notice on plugin pages.
     */
    public function trial_notices() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;

        // Show on all admin pages
        $remaining = RevenueLeakScanner::trial_remaining();

        if ( $remaining <= 0 ) : ?>
            <div class="notice notice-error" style="padding:20px;border-left:4px solid #EF4444;">
                <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                    <span style="font-size:28px;">🔒</span>
                    <div style="flex:1;min-width:200px;">
                        <h3 style="margin:0 0 4px;font-size:15px;color:#DC2626;">Your 24-Hour Trial Has Expired</h3>
                        <p style="margin:0;color:#6B7280;font-size:13px;">The plugin will be removed automatically. Get the Pro version for lifetime access.</p>
                    </div>
                    <a href="<?php echo esc_url( RLS_BUY_URL ); ?>" target="_blank" style="display:inline-flex;align-items:center;gap:6px;padding:12px 24px;background:linear-gradient(135deg,#667EEA,#764BA2);color:#fff;font-weight:700;border-radius:10px;text-decoration:none;font-size:14px;box-shadow:0 4px 12px rgba(102,126,234,0.3);">🚀 Get Pro Version — $19</a>
                </div>
            </div>
        <?php elseif ( $remaining < 14400 ) : // < 4 hours ?>
            <div class="notice notice-warning" style="padding:14px 20px;border-left:4px solid #F59E0B;">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <span style="font-size:22px;">⏰</span>
                    <div style="flex:1;">
                        <span style="font-weight:700;color:#92400E;">Trial expires in </span>
                        <span id="rls-notice-timer" style="font-family:monospace;font-size:16px;font-weight:800;color:#B45309;"><?php echo esc_html( self::get_remaining_formatted() ); ?></span>
                        <span style="color:#78716C;font-size:12px;margin-left:8px;">Plugin auto-deletes after expiry</span>
                    </div>
                    <a href="<?php echo esc_url( RLS_BUY_URL ); ?>" target="_blank" style="padding:8px 16px;background:#F59E0B;color:#fff;font-weight:700;border-radius:8px;text-decoration:none;font-size:13px;">Upgrade Now — $19</a>
                </div>
            </div>
        <?php endif;
    }

    /**
     * Live countdown JavaScript.
     */
    public function countdown_script() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;

        $remaining = RevenueLeakScanner::trial_remaining();
        if ( $remaining <= 0 ) return;

        $script = "
        (function(){
            var r={$remaining};
            function tick(){
                r--;
                if(r<=0){location.reload();return;}
                var h=Math.floor(r/3600),m=Math.floor((r%3600)/60),s=r%60;
                var t=String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
                var c=r<3600?'#EF4444':(r<14400?'#F59E0B':'#10B981');
                var el=document.getElementById('rls-adminbar-time');
                if(el){el.textContent=t;el.parentElement.style.color=c;}
                var n=document.getElementById('rls-notice-timer');
                if(n)n.textContent=t;
                var v=document.getElementById('rls-trial-timer-value');
                if(v)v.textContent=t;
                var p=document.getElementById('rls-trial-progress');
                if(p){p.style.width=(r/86400*100)+'%';}
            }
            setInterval(tick,1000);
        })();";

        wp_add_inline_script( 'jquery', $script );
    }

    /**
     * Add upgrade link to plugins page.
     */
    public function add_upgrade_link( $links ) {
        $upgrade = '<a href="' . esc_url( RLS_BUY_URL ) . '" target="_blank" style="color:#667EEA;font-weight:700;">🚀 Upgrade to Pro — $19</a>';
        array_unshift( $links, $upgrade );
        return $links;
    }
}
