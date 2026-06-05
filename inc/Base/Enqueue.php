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

        wp_enqueue_script( 'select2' );
        wp_enqueue_style( 'select2' );
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
            array( 'wp-util', 'jquery', 'select2' ),
            KIRIOF_VERSION,
            array( 'in_footer' => true )
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

        if ( $this->isBlockCheckoutPage() ) {
            wp_enqueue_script(
                'kiriof-block-checkout',
                $this->plugin_url . 'assets/wp/js/kiriof-block-checkout.js',
                array( 'wp-element', 'wp-plugins', 'wp-data', 'wc-blocks-checkout' ),
                KIRIOF_VERSION,
                array( 'in_footer' => true )
            );
        }
    }

    private function isBlockCheckoutPage() {
        $checkout_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'checkout' ) : 0;
        if ( $checkout_page_id > 0 && function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $checkout_page_id ) ) {
            return true;
        }
        if ( class_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' ) && method_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils', 'is_checkout_block_default' ) ) {
            if ( \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_checkout_block_default() ) {
                return true;
            }
        }
        if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
            return true;
        }
        return false;
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

        // Fallback: match by page slug in case $post is not set yet or the
        // shortcode is nested inside a block wrapper that bypasses has_shortcode.
        if ( is_page( 'tracking' ) ) {
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
        $page   = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS );
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $screen_id = $screen ? $screen->id : '';

        $is_plugin_page = in_array( $page, array(
            'kiriminaja-konfigurasi',
            'kiriminaja-transaction-process',
            'kiriminaja-request-pickup',
            'kiriminaja-request-pickup-detail',
        ), true );

        $is_order_screen = in_array( $screen_id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true );

        if ( ! $is_plugin_page && ! $is_order_screen ) {
            return;
        }

        // Heartbeat is already loaded by WP admin. We hook into it to push
        // a fresh nonce back to the client so long-idle pages don't 403.
        add_filter( 'heartbeat_received', array( $this, 'kiriof_heartbeat_nonce_refresh' ), 10, 2 );
        // Ensure the heartbeat script is present (it usually is in admin,
        // but an explicit enqueue is harmless and guarantees availability).
        wp_enqueue_script( 'heartbeat' );

        wp_enqueue_style( 'list-tables' );
        
        wp_enqueue_style( 'kiriof-style', $this->plugin_url . 'assets/admin/css/kj-admin-style.css', array(), KIRIOF_VERSION, 'all' );
        wp_enqueue_script( 'kiriof-script', $this->plugin_url . 'assets/admin/js/kj-admin-script.js', array( 'jquery', 'select2' ), KIRIOF_VERSION, true );
        
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

        if ( 'kiriminaja-transaction-process' === $page ) {
            wp_enqueue_style( 'woocommerce_admin_styles' );
            wp_enqueue_script( 'woocommerce_admin' );
            wp_enqueue_script( 'wc-backbone-modal' );
            wp_enqueue_script( 'wc-orders' );
        }

        /** print */
        wp_enqueue_style( 'kiriof-print-style', $this->plugin_url . 'assets/admin/css/print.min.css', array(), KIRIOF_VERSION );
        wp_enqueue_script( 'kiriof-print-script', $this->plugin_url . 'assets/admin/js/print.min.js', array(), KIRIOF_VERSION, true );
        
        /** Select 2 - use WooCommerce's bundled copy */
        wp_enqueue_script( 'select2' );

        // WooCommerce only registers the 'select2' style handle on the
        // frontend (WC_Frontend_Scripts). On admin pages it is missing,
        // so register it ourselves from WC's bundled CSS file.
        if ( ! wp_style_is( 'select2', 'registered' ) && defined( 'WC_PLUGIN_FILE' ) ) {
            // Always check for WC_VERSION in the global namespace
            $wc_version = defined('WC_VERSION') ? \WC_VERSION : null;
            if ( ! $wc_version && function_exists('get_plugin_data') ) {
                $plugin_data = get_plugin_data( WC_PLUGIN_FILE );
                $wc_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : ( defined('KIRIOF_VERSION') ? KIRIOF_VERSION : '1.0.0' );
            }
            if ( ! $wc_version ) {
                $wc_version = defined('KIRIOF_VERSION') ? KIRIOF_VERSION : '1.0.0';
            }
            wp_register_style(
                'select2',
                plugin_dir_url( WC_PLUGIN_FILE ) . 'assets/css/select2.css',
                array(),
                $wc_version
            );
        }
        wp_enqueue_style( 'select2' );

        // Override WP admin CSS rules that break Select2 rendering.
        wp_add_inline_style( 'select2', '
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
        /**
         * COD Adjustment JS — enqueued on the order edit screen and transaction process page.
         */
        if ( $is_order_screen || 'kiriminaja-transaction-process' === $page ) {
            wp_enqueue_script(
                'kiriof-cod-adjustment',
                $this->plugin_url . 'assets/js/kiriof-cod-adjustment.js',
                array( 'jquery', 'backbone', 'wc-backbone-modal' ),
                KIRIOF_VERSION,
                true
            );
            wp_localize_script(
                'kiriof-cod-adjustment',
                'kiriofCodAdj',
                array(
                    'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                    'nonce'         => wp_create_nonce( KIRIOF_NONCE ),
                    'hintMin'       => __( 'Minimum {min} to avoid COD Settlement deficit', 'kiriminaja-official' ),
                    'hintMax'       => __( 'Must not exceed {max}', 'kiriminaja-official' ),
                    'hintPayout'    => __( 'Estimated payout must not be negative', 'kiriminaja-official' ),
                    'processing'    => __( 'Processing…', 'kiriminaja-official' ),
                    'confirm'       => __( 'Confirm & Process', 'kiriminaja-official' ),
                    'cancelConfirm' => __( 'Are you sure you want to cancel this deficit COD order? This cannot be undone.', 'kiriminaja-official' ),
                    'hintCodInvalid' => __( 'Please correct the COD value.', 'kiriminaja-official' ),
                    'errorGeneral'  => __( 'An error occurred.', 'kiriminaja-official' ),
                )
            );
        }

        if ( 'kiriminaja-request-pickup' === $page || 'kiriminaja-request-pickup-detail' === $page ) {
            if ( ! wp_script_is( 'wc-qrcode', 'registered' ) && defined( 'WC_PLUGIN_FILE' ) ) {
                $wc_version = defined( 'WC_VERSION' ) ? \WC_VERSION : KIRIOF_VERSION;
                wp_register_script(
                    'wc-qrcode',
                    plugin_dir_url( WC_PLUGIN_FILE ) . 'assets/js/jquery-qrcode/jquery.qrcode.js',
                    array( 'jquery' ),
                    $wc_version,
                    true
                );
            }
            wp_enqueue_script( 'wc-qrcode' );
            wp_enqueue_script(
                'kiriof-qr-code-styling',
                $this->plugin_url . 'assets/lib/qr-code-styling/qr-code-styling.min.js',
                array(),
                KIRIOF_VERSION,
                true
            );
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
