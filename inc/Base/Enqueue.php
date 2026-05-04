<?php
namespace KiriminAjaOfficial\Base;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use \KiriminAjaOfficial\Base\BaseInit;
class Enqueue extends BaseInit{
    
    public function register(){
        /** enqueue js & CSS */
        /* admin */
        add_action('admin_enqueue_scripts', array($this,'enqueueAdmin'));
        /* WP */
        add_action('wp_enqueue_scripts', array($this,'enqueueWp'));
    }
    /** Add Enqueue CSS & JS*/
    function enqueueWp(){
        // Only load on pages where the plugin's UI actually runs: cart, checkout,
        // account pages, and anywhere the [kiriminaja_tracking] shortcode is used.
        if ( ! $this->shouldEnqueueFront() ) {
            return;
        }

        wp_enqueue_script(
            'kiriof-select2-script',
            $this->plugin_url . 'assets/lib/select2/select2.min.js',
            array( 'jquery' ),
            '4.1.0-rc.0',
            array( 'in_footer' => true, 'strategy' => 'defer' )
        );
        wp_enqueue_style( 'kiriof-select2-style', $this->plugin_url . 'assets/lib/select2/select2.min.css', array(), '4.1.0-rc.0' );
        wp_enqueue_style( 'kiriof-style', $this->plugin_url . 'assets/wp/css/kj-wp-style.css', array(), KIRIOF_VERSION, 'all' );

        // Tracking shortcode-specific styles. Loaded as a real stylesheet so the
        // rules are present in <head> by the time [kiriminaja-tracking-front-page]
        // (or its legacy alias [wp-tracking-front-page]) renders inside
        // the_content. wp_add_inline_style from the shortcode handler runs after
        // wp_head and would be silently dropped.
        global $post;
        if (
            $post instanceof \WP_Post
            && (
                has_shortcode( (string) $post->post_content, 'kiriminaja-tracking-front-page' )
                || has_shortcode( (string) $post->post_content, 'wp-tracking-front-page' )
            )
        ) {
            wp_enqueue_style(
                'kiriof-tracking-style',
                $this->plugin_url . 'assets/wp/css/kj-tracking.css',
                array( 'kiriof-style' ),
                KIRIOF_VERSION,
                'all'
            );
        }

        // Option 1: Manually enqueue the wp-util library.
        wp_enqueue_script( 'wp-util' );
        // Option 2: Make wp-util a dependency of your script (usually better).
        wp_enqueue_script(
            'kiriof-script',
            $this->plugin_url . 'assets/wp/js/kj-wp-script.js',
            array( 'wp-util', 'jquery' ),
            KIRIOF_VERSION,
            array( 'in_footer' => true, 'strategy' => 'defer' )
        );

        // Localize script to pass ajax URL and nonce
        wp_localize_script(
            'kiriof-script',
            'kiriofAjax',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( KIRIOF_NONCE ),
                'destination_nonce' => wp_create_nonce( 'kiriof-destination' ),
                'update_checkout_nonce' => wp_create_nonce( 'kiriof-update-checkout' ),
            )
        );
    }

    /**
     * Whether the frontend assets should be enqueued on the current request.
     * Restricts output to WooCommerce commerce pages and tracking shortcode pages
     * to satisfy Plugin Check EnqueuedScriptsScope / EnqueuedStylesScope rules.
     *
     * @return bool
     */
    private function shouldEnqueueFront() {
        // WooCommerce commerce pages.
        if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
            return true;
        }

        // Tracking shortcode page (used by the public tracking front page).
        // Accepts the legacy [wp-tracking-front-page] alias for backward
        // compatibility with pages created by older plugin versions.
        global $post;
        if (
            $post instanceof \WP_Post
            && (
                has_shortcode( (string) $post->post_content, 'kiriminaja-tracking-front-page' )
                || has_shortcode( (string) $post->post_content, 'wp-tracking-front-page' )
            )
        ) {
            return true;
        }

        /**
         * Filters whether to force-enqueue the KiriminAja frontend assets.
         *
         * Useful for themes or plugins with custom checkout templates that
         * need the shipping UI outside the standard WooCommerce pages.
         *
         * @param bool $enqueue Whether to enqueue the frontend assets. Default false.
         */
        return (bool) apply_filters( 'kiriof_enqueue_frontend_assets', false );
    }
    
    function enqueueAdmin(){
        $page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS );
        if ( ! in_array( $page, array(
            'kiriminaja-konfigurasi',
            'kiriminaja-transaction-process',
            'kiriminaja-request-pickup',
        ), true ) ) {
            return;
        }

        // Heartbeat is already loaded by WP admin. We hook into it to push
        // a fresh nonce back to the client so long-idle pages don't 403.
        add_filter( 'heartbeat_received', array( $this, 'kiriof_heartbeat_nonce_refresh' ), 10, 2 );
        // Ensure the heartbeat script is present (it usually is in admin,
        // but an explicit enqueue is harmless and guarantees availability).
        wp_enqueue_script( 'heartbeat' );
        
        wp_enqueue_style( 'kiriof-style', $this->plugin_url . 'assets/admin/css/kj-admin-style.css', array(), KIRIOF_VERSION, 'all' );
        wp_enqueue_script( 'kiriof-script', $this->plugin_url . 'assets/admin/js/kj-admin-script.js', array( 'jquery', 'kiriof-select2-script' ), KIRIOF_VERSION, true );
        
        // Localize script to pass ajax URL and nonce
        wp_localize_script(
            'kiriof-script',
            'kiriofAjax',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( KIRIOF_NONCE ),
                'destination_nonce' => wp_create_nonce( 'kiriof-destination' ),
                'update_checkout_nonce' => wp_create_nonce( 'kiriof-update-checkout' ),
            )
        );
        
        wp_enqueue_style( 'kiriof-grid-style', $this->plugin_url . 'assets/admin/css/bootstrap-grid.css', array(), KIRIOF_VERSION );
        wp_enqueue_style( 'kiriof-wc-app', $this->plugin_url . 'assets/admin/css/kj-wc-style/app.style.css', array(), KIRIOF_VERSION );
        wp_enqueue_style( 'kiriof-wc-app-custom', $this->plugin_url . 'assets/admin/css/kj-wc-style/app-custom.style.css', array(), KIRIOF_VERSION );
        wp_enqueue_style( 'kiriof-wc-3538', $this->plugin_url . 'assets/admin/css/kj-wc-style/3538.style.css', array(), KIRIOF_VERSION );
        wp_enqueue_style( 'kiriof-wc-5502', $this->plugin_url . 'assets/admin/css/kj-wc-style/5502.style.css', array(), KIRIOF_VERSION );
        wp_enqueue_style( 'kiriof-wc-8597', $this->plugin_url . 'assets/admin/css/kj-wc-style/8597.style.css', array(), KIRIOF_VERSION );

        /** print */
        wp_enqueue_style( 'kiriof-print-style', $this->plugin_url . 'assets/admin/css/print.min.css', array(), KIRIOF_VERSION );
        wp_enqueue_script( 'kiriof-print-script', $this->plugin_url . 'assets/admin/js/print.min.js', array(), KIRIOF_VERSION, true );
        
        /** Select 2 - bundled locally */
        wp_enqueue_style( 'kiriof-select2-style', $this->plugin_url . 'assets/lib/select2/select2.min.css', array(), '4.1.0-rc.0' );
        wp_enqueue_script( 'kiriof-select2-script', $this->plugin_url . 'assets/lib/select2/select2.min.js', array( 'jquery' ), '4.1.0-rc.0', true );

        // Override WP admin CSS rules that break Select2 rendering.
        wp_add_inline_style( 'kiriof-select2-style', '
            .select2-container .select2-selection--multiple .select2-selection__rendered > li { margin-bottom: 0; }
            .select2-container .select2-selection--multiple .select2-selection__choice__remove { min-height: auto; line-height: 1; }
            .select2-container .select2-search--inline .select2-search__field { border: none; box-shadow: none; background-color: transparent; }
        ' );

        /**
         * Leaflet - bundled locally for the store-address map picker on
         * the Settings page. Only loaded on kiriminaja-konfigurasi.
         */
        if ( 'kiriminaja-konfigurasi' === $page ) {
            wp_enqueue_style( 'kiriof-leaflet-style', $this->plugin_url . 'assets/lib/leaflet/leaflet.css', array(), '1.9.4' );
            wp_enqueue_script( 'kiriof-leaflet-script', $this->plugin_url . 'assets/lib/leaflet/leaflet.js', array(), '1.9.4', true );
        }

        /**
         * QR Code — use WooCommerce's bundled jquery-qrcode (handle: wc-qrcode)
         * for the "Scan to Pay" modal on the Request Pickup page.
         */
        if ( 'kiriminaja-request-pickup' === $page ) {
            wp_enqueue_script( 'wc-qrcode' );
        }
   
    }

    /**
     * Heartbeat API callback: returns a fresh nonce so long-idle admin pages
     * can keep making valid AJAX requests without a full page reload.
     *
     * The client-side listener (in the first inline script block of each
     * admin template) writes the returned value back into kiriofAjax.nonce,
     * which every AJAX call already references.
     *
     * @param array $response Heartbeat response data.
     * @param array $data     Heartbeat request data.
     * @return array
     */
    public function kiriof_heartbeat_nonce_refresh( $response, $data ) {
        if ( ! empty( $data['kiriof_nonce_check'] ) ) {
            $response['kiriof_new_nonce'] = wp_create_nonce( KIRIOF_NONCE );
        }
        return $response;
    }
}