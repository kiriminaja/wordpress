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
        $this->enqueueViteClient();
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
        if ( $this->isTrackingPage() ) {
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
            $this->assetUrl( 'assets/wp/js/kj-wp-script.js' ),
            $this->scriptDeps( array( 'wp-util', 'jquery', 'select2' ) ),
            KIRIOF_VERSION,
            array( 'in_footer' => true )
        );
        $this->markScriptAsModule( 'kiriof-script' );
        wp_register_script(
            'kiriof-form-billing-address',
            $this->assetUrl( 'assets/wp/js/form-billing-address.js' ),
            $this->scriptDeps( array( 'kiriof-script' ) ),
            KIRIOF_VERSION,
            array( 'in_footer' => true )
        );
        $this->markScriptAsModule( 'kiriof-form-billing-address' );

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

        if ( $this->isTrackingPage() ) {
            wp_enqueue_script(
                'kiriof-tracking-script',
                $this->assetUrl( 'assets/wp/js/kj-tracking.js' ),
                $this->scriptDeps( array( 'jquery', 'kiriof-script' ) ),
                KIRIOF_VERSION,
                array( 'in_footer' => true )
            );
            $this->markScriptAsModule( 'kiriof-tracking-script' );

            wp_localize_script(
                'kiriof-tracking-script',
                'kiriofTracking',
                array(
                    'i18n' => array(
                        'orderNumber' => __( 'Nomor Order', 'kiriminaja-official' ),
                        'awbNumber'   => __( 'Nomor Resi', 'kiriminaja-official' ),
                        'courier'     => __( 'Kurir', 'kiriminaja-official' ),
                        'notFound'    => __( 'Order tidak ditemukan', 'kiriminaja-official' ),
                    ),
                )
            );
        }

        if ( $this->isBlockCartOrCheckoutPage() ) {
            wp_enqueue_script(
                'kiriof-block-checkout',
                $this->assetUrl( 'assets/wp/js/kiriof-block-checkout.js' ),
                $this->scriptDeps( array( 'kiriof-script', 'wp-element', 'wp-plugins', 'wp-data', 'wp-notices', 'wc-blocks-checkout' ) ),
                KIRIOF_VERSION,
                array( 'in_footer' => true )
            );
            $this->markScriptAsModule( 'kiriof-block-checkout' );
        }
    }

    private function isBlockCartOrCheckoutPage() {
        $checkout_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'checkout' ) : 0;
        if ( $checkout_page_id > 0 && function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $checkout_page_id ) ) {
            return true;
        }
        $cart_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'cart' ) : 0;
        if ( $cart_page_id > 0 && function_exists( 'has_block' ) && has_block( 'woocommerce/cart', $cart_page_id ) ) {
            return true;
        }
        if ( class_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' ) && method_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils', 'is_checkout_block_default' ) ) {
            if ( \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_checkout_block_default() ) {
                return true;
            }
        }
        if ( class_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' ) && method_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils', 'is_cart_block_default' ) ) {
            if ( \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_cart_block_default() ) {
                return true;
            }
        }
        if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
            return true;
        }
        return false;
    }

    private function isTrackingPage() {
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

        $tracking_page_id = function_exists( 'kiriof_get_tracking_page_id' ) ? kiriof_get_tracking_page_id() : 0;
        if ( $tracking_page_id > 0 && is_page( $tracking_page_id ) ) {
            return true;
        }

        return false;
    }

    /**
     * Whether the frontend assets should be enqueued on the current request.
     * Restricts output to KiriminAja checkout/cart UI and tracking pages
     * to satisfy Plugin Check EnqueuedScriptsScope / EnqueuedStylesScope rules.
     *
     * @return bool
     */
    private function shouldEnqueueFront() {
        // Checkout/cart pages render KiriminAja shipping fields and fee refresh logic.
        if ( function_exists( 'is_cart' ) && is_cart() ) {
            return true;
        }

        if ( function_exists( 'is_checkout' ) && is_checkout() ) {
            return true;
        }

        // Tracking shortcode page (used by the public tracking front page).
        // Accepts the legacy [wp-tracking-front-page] alias for backward
        // compatibility with pages created by older plugin versions.
        if ( $this->isTrackingPage() ) {
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
        $this->enqueueViteClient();
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

        $tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS );
        $is_wc_general_settings = 'woocommerce_page_wc-settings' === $screen_id && ( empty( $tab ) || 'general' === $tab );

        if ( ! $is_plugin_page && ! $is_order_screen && ! $is_wc_general_settings ) {
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
        wp_enqueue_script( 'kiriof-script', $this->assetUrl( 'assets/admin/js/kj-admin-script.js' ), $this->scriptDeps( array( 'jquery', 'select2' ) ), KIRIOF_VERSION, true );
        $this->markScriptAsModule( 'kiriof-script' );
        
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
            wp_enqueue_script( 'kiriof-pin-input', $this->plugin_url . 'assets/lib/pin-input/pin-input.js', array(), '0.2.0', true );
            wp_script_add_data( 'kiriof-pin-input', 'type', 'module' );
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
        if ( 'kiriminaja-konfigurasi' === $page || $is_wc_general_settings ) {
            wp_enqueue_style( 'kiriof-leaflet-style', $this->plugin_url . 'assets/lib/leaflet/leaflet.css', array(), '1.9.4' );
            wp_enqueue_script( 'kiriof-leaflet-script', $this->plugin_url . 'assets/lib/leaflet/leaflet.js', array(), '1.9.4', true );
        }

        /**
         * COD Adjustment JS — enqueued on the order edit screen and transaction process page.
         */
        if ( $is_order_screen || 'kiriminaja-transaction-process' === $page ) {
            wp_enqueue_script(
                'kiriof-cod-adjustment',
                $this->assetUrl( 'assets/js/kiriof-cod-adjustment.js' ),
                $this->scriptDeps( array( 'jquery', 'backbone', 'wc-backbone-modal' ) ),
                KIRIOF_VERSION,
                true
            );
            $this->markScriptAsModule( 'kiriof-cod-adjustment' );
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

    private function assetUrl( $path ) {
        if ( defined( 'KIRIOF_DEV_MODE' ) && KIRIOF_DEV_MODE ) {
            return 'http://localhost:5173/' . ltrim( $this->sourceEntryPath( $path ), '/' );
        }

        return $this->plugin_url . ltrim( $path, '/' );
    }

    private function sourceEntryPath( $path ) {
        $entries = array(
            'assets/wp/js/kj-wp-script.js'        => 'client/src/storefront-classic/entries/wp-script.ts',
            'assets/wp/js/form-billing-address.js' => 'client/src/storefront-classic/entries/form-billing-address.ts',
            'assets/wp/js/kj-tracking.js'         => 'client/src/storefront-classic/entries/tracking.ts',
            'assets/wp/js/kiriof-block-checkout.js' => 'client/src/storefront-block/entries/block-checkout.ts',
            'assets/admin/js/kj-admin-script.js'  => 'client/src/admin/entries/admin-script.ts',
            'assets/js/kiriof-cod-adjustment.js'  => 'client/src/admin/entries/cod-adjustment.ts',
        );

        return $entries[ $path ] ?? $path;
    }

    private function scriptDeps( $deps ) {
        return ( defined( 'KIRIOF_DEV_MODE' ) && KIRIOF_DEV_MODE ) ? array() : $deps;
    }

    private function markScriptAsModule( $handle ) {
        wp_script_add_data( $handle, 'type', 'module' );
    }

    private function enqueueViteClient() {
        if ( ! defined( 'KIRIOF_DEV_MODE' ) || ! KIRIOF_DEV_MODE || wp_script_is( 'kiriof-vite-client', 'enqueued' ) ) {
            return;
        }

        wp_enqueue_script(
            'kiriof-vite-client',
            'http://localhost:5173/@vite/client',
            array(),
            KIRIOF_VERSION,
            false
        );
        wp_script_add_data( 'kiriof-vite-client', 'type', 'module' );
    }
}
