<?php
/**
 * Trial License Handler.
 *
 * Manages 24-hour trial activation, expiry, countdown, and self-destruct.
 *
 * @package RevenueLeakScanner
 */

namespace RevenueLeakScanner;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles trial license logic.
 */
class Trial {

    const TRIAL_DURATION = 86400; // 24 hours in seconds
    const OPTION_ACTIVATED = 'rls_trial_activated_at';
    const OPTION_STATUS = 'rls_trial_status';
    const OPTION_LICENSE = 'rls_license_key';

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->maybe_start_trial();
        add_action( 'admin_init', array( $this, 'check_expiry' ) );
        add_action( 'admin_bar_menu', array( $this, 'admin_bar_countdown' ), 999 );
        add_action( 'admin_notices', array( $this, 'show_trial_notice' ) );
        add_action( 'wp_ajax_rls_get_trial_time', array( $this, 'ajax_get_trial_time' ) );
        add_action( 'wp_ajax_rls_activate_license', array( $this, 'ajax_activate_license' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_trial_assets' ) );
    }

    private function maybe_start_trial() {
        if ( false === get_option( self::OPTION_ACTIVATED ) ) {
            update_option( self::OPTION_ACTIVATED, time() );
            update_option( self::OPTION_STATUS, 'active' );
        }
    }

    public static function is_pro() {
        $license = get_option( self::OPTION_LICENSE, '' );
        return ! empty( $license ) && 'valid' === get_option( 'rls_license_status', '' );
    }

    public static function is_active() {
        if ( self::is_pro() ) return true;
        return self::get_remaining_seconds() > 0;
    }

    public static function is_expired() {
        if ( self::is_pro() ) return false;
        return self::get_remaining_seconds() <= 0;
    }

    public static function get_remaining_seconds() {
        $activated_at = (int) get_option( self::OPTION_ACTIVATED, 0 );
        if ( 0 === $activated_at ) return 0;
        return max( 0, ( $activated_at + self::TRIAL_DURATION ) - time() );
    }

    public static function get_remaining_formatted() {
        $s = self::get_remaining_seconds();
        if ( $s <= 0 ) return '00:00:00';
        return sprintf( '%02d:%02d:%02d', floor($s/3600), floor(($s%3600)/60), $s%60 );
    }

    public function check_expiry() {
        if ( self::is_pro() ) return;
        if ( self::is_expired() && 'expired' !== get_option( self::OPTION_STATUS ) ) {
            update_option( self::OPTION_STATUS, 'expired' );
            if ( ! function_exists( 'deactivate_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            deactivate_plugins( RLS_PLUGIN_BASENAME );
            set_transient( 'rls_trial_expired_deactivated', true, 86400 );
        }
    }

    public function admin_bar_countdown( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_woocommerce' ) || self::is_pro() ) return;

        $remaining = self::get_remaining_seconds();
        if ( $remaining <= 0 ) {
            $title = '<span style="color:#EF4444;font-weight:700;">🔒 Trial Expired</span>';
        } else {
            $color = $remaining < 3600 ? '#EF4444' : ( $remaining < 14400 ? '#F59E0B' : '#10B981' );
            $title = '<span style="color:' . $color . ';font-weight:600;">⏱️ ' . esc_html( self::get_remaining_formatted() ) . '</span>';
        }

        $wp_admin_bar->add_node( array(
            'id'    => 'rls-trial-timer',
            'title' => $title,
            'href'  => admin_url( 'admin.php?page=revenue-leak-scanner' ),
        ) );
    }

    public function show_trial_notice() {
        if ( ! current_user_can( 'manage_woocommerce' ) || self::is_pro() ) return;

        $remaining = self::get_remaining_seconds();
        $screen = get_current_screen();
        if ( ! $screen ) return;
        $is_our_page = strpos( $screen->id, 'revenue-leak' ) !== false || strpos( $screen->id, 'rls-' ) !== false;
        if ( ! $is_our_page ) return;

        if ( $remaining <= 0 ) : ?>
            <div class="notice notice-error" style="padding:20px;border-left-color:#EF4444;">
                <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                    <span style="font-size:32px;">🔒</span>
                    <div style="flex:1;min-width:200px;">
                        <h3 style="margin:0 0 4px;font-size:16px;">Your 24-Hour Trial Has Expired</h3>
                        <p style="margin:0;color:#6B7280;font-size:13px;">Plugin will deactivate. Upgrade for lifetime access.</p>
                    </div>
                    <a href="https://revenueleakscanner.com/#pricing" target="_blank" style="padding:10px 20px;background:linear-gradient(135deg,#667EEA,#764BA2);color:#fff;font-weight:700;border-radius:8px;text-decoration:none;font-size:14px;">🚀 Upgrade — $19</a>
                </div>
            </div>
        <?php elseif ( $remaining < 14400 ) : ?>
            <div class="notice notice-warning" style="padding:16px;border-left-color:#F59E0B;">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <span style="font-size:24px;">⏰</span>
                    <div style="flex:1;min-width:200px;">
                        <p style="margin:0;font-weight:600;color:#92400E;">Trial expires in <span id="rls-notice-countdown" style="font-family:monospace;font-size:15px;"><?php echo esc_html( self::get_remaining_formatted() ); ?></span></p>
                        <p style="margin:4px 0 0;color:#78716C;font-size:12px;">Plugin auto-deactivates after expiry.</p>
                    </div>
                    <a href="https://revenueleakscanner.com/#pricing" target="_blank" style="padding:8px 16px;background:#F59E0B;color:#fff;font-weight:700;border-radius:8px;text-decoration:none;font-size:13px;">Upgrade — $19</a>
                </div>
            </div>
        <?php endif;
    }

    public function enqueue_trial_assets() {
        if ( ! current_user_can( 'manage_woocommerce' ) || self::is_pro() ) return;
        $remaining = self::get_remaining_seconds();
        if ( $remaining <= 0 ) return;

        $js = "
        (function(){
            var r={$remaining};
            if(r<=0)return;
            function u(){
                r--;
                if(r<=0){location.reload();return;}
                var h=Math.floor(r/3600),m=Math.floor((r%3600)/60),s=r%60;
                var f=String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
                var c=r<3600?'#EF4444':(r<14400?'#F59E0B':'#10B981');
                var b=document.querySelector('#wp-admin-bar-rls-trial-timer .ab-item span');
                if(b){b.style.color=c;b.textContent='⏱️ '+f;}
                var n=document.getElementById('rls-notice-countdown');
                if(n)n.textContent=f;
                var d=document.getElementById('rls-trial-timer-value');
                if(d)d.textContent=f;
                var p=document.getElementById('rls-trial-progress');
                if(p){p.style.width=(r/86400*100)+'%';if(r<3600)p.style.background='#EF4444';else if(r<14400)p.style.background='#F59E0B';}
            }
            setInterval(u,1000);
        })();";
        wp_add_inline_script( 'jquery', $js );
    }

    public function ajax_get_trial_time() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();
        wp_send_json_success( array(
            'remaining' => self::get_remaining_seconds(),
            'formatted' => self::get_remaining_formatted(),
            'is_active' => self::is_active(),
            'is_expired' => self::is_expired(),
        ) );
    }

    public function ajax_activate_license() {
        check_ajax_referer( 'rls_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();

        $key = sanitize_text_field( $_POST['license_key'] ?? '' );
        if ( empty( $key ) ) {
            wp_send_json_error( array( 'message' => 'Please enter a license key.' ) );
        }

        update_option( self::OPTION_LICENSE, $key );
        update_option( 'rls_license_status', 'valid' );
        update_option( self::OPTION_STATUS, 'pro' );

        wp_send_json_success( array( 'message' => 'License activated! Full access unlocked.' ) );
    }
}
