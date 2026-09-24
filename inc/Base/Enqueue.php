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
        add_action( 'admin_footer', array( $this, 'renderOrderPreviewTemplate' ) );
        add_filter( 'script_loader_tag', array( $this, 'filter_module_script_tag' ), 10, 3 );
        /* WP */
        add_action('wp_enqueue_scripts', array($this,'enqueueWp'));
    }

	/**
	 * Enqueue the single Svelte entry shared by the internal KiriminAja workspace pages.
	 *
	 * @param string $workspace_script Absolute path to the generated entry.
	 * @param array  $dependencies    Legacy scripts required by the active route.
	 * @return void
	 */
	private function enqueue_workspace_script( string $workspace_script, array $dependencies = array() ): void {
		if ( ! file_exists( $workspace_script ) ) {
			return;
		}

		wp_enqueue_script(
			'kiriof-admin-workspace',
			$this->plugin_url . 'assets/admin/dist/kiriminaja-admin-workspace.js',
			$dependencies,
			(string) filemtime( $workspace_script ),
			true
		);
		wp_script_add_data( 'kiriof-admin-workspace', 'type', 'module' );
	}

	/**
	 * Enqueue styles emitted by the one shared workspace entry.
	 *
	 * @return void
	 */
	private function enqueue_workspace_style(): void {
		$workspace_style = KIRIOF_DIR . 'assets/admin/dist/kiriminaja-admin-workspace.css';
		$admin_list_style = KIRIOF_DIR . 'assets/admin/dist/kiriminaja-admin-list.css';
		$shadcn_style = KIRIOF_DIR . 'assets/admin/dist/kiriminaja-shadcn-onboarding.css';
		if ( ! file_exists( $workspace_style ) ) {
			return;
		}

		$dependencies = array();
		if ( file_exists( $shadcn_style ) ) {
			wp_enqueue_style(
				'kiriof-workspace-shadcn-style',
				$this->plugin_url . 'assets/admin/dist/kiriminaja-shadcn-onboarding.css',
				array(),
				(string) filemtime( $shadcn_style )
			);
			$dependencies[] = 'kiriof-workspace-shadcn-style';
		}
		if ( file_exists( $admin_list_style ) ) {
			wp_enqueue_style(
				'kiriof-workspace-admin-list-style',
				$this->plugin_url . 'assets/admin/dist/kiriminaja-admin-list.css',
				$dependencies,
				(string) filemtime( $admin_list_style )
			);
			$dependencies[] = 'kiriof-workspace-admin-list-style';
		}

		wp_enqueue_style(
			'kiriof-admin-workspace-style',
			$this->plugin_url . 'assets/admin/dist/kiriminaja-admin-workspace.css',
			$dependencies,
			(string) filemtime( $workspace_style )
		);
	}

    /**
     * WordPress versions before module script metadata support need an explicit
     * type attribute for the Vite ESM entries.
     *
     * @param string $tag    Script tag.
     * @param string $handle Script handle.
     * @param string $src    Script source URL.
     * @return string
     */
    public function filter_module_script_tag( string $tag, string $handle, string $src ): string {
        unset( $src );

        $module_handles = array(
			'kiriof-coupon-panels',
			'kiriof-admin-workspace',
			'kiriof-onboarding-progress',
			'kiriof-order-metabox',
        );

        if ( ! in_array( $handle, $module_handles, true ) || false !== strpos( $tag, ' type=' ) ) {
            return $tag;
        }

        return str_replace( '<script ', '<script type="module" ', $tag );
    }

    /**
     * Render WooCommerce's order preview template on the custom transactions page.
     *
     * WooCommerce normally prints this template only on its native order list
     * screens. The Transactions page uses the same preview button, so it needs
     * the template explicitly.
     *
     * @return void
     */
    public function renderOrderPreviewTemplate() {
        $page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS );

        if ( 'kiriminaja-transaction' !== $page ) {
            return;
        }

        if ( class_exists( '\Automattic\WooCommerce\Internal\Admin\Orders\ListTable' ) && function_exists( 'wc_get_container' ) ) {
            $list_table_class = '\Automattic\WooCommerce\Internal\Admin\Orders\ListTable';
            $list_table       = wc_get_container()->get( $list_table_class );

            if ( method_exists( $list_table, 'get_order_preview_template' ) ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $list_table->get_order_preview_template();
                return;
            }
        }

        if ( class_exists( 'WC_Admin_List_Table_Orders' ) ) {
            $orders_table = new \WC_Admin_List_Table_Orders();

            if ( method_exists( $orders_table, 'order_preview_template' ) ) {
                $orders_table->order_preview_template();
            }
        }
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
        wp_enqueue_style( 'kiriof-badge-style', $this->plugin_url . 'assets/admin/css/kj-badge.css', array( 'kiriof-style' ), KIRIOF_VERSION, 'all' );

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
            $this->plugin_url . 'assets/wp/js/kj-wp-script.js',
            array( 'wp-util', 'jquery', 'select2' ),
            KIRIOF_VERSION,
            array( 'in_footer' => true )
        );
        wp_register_script(
            'kiriof-form-billing-address',
            $this->plugin_url . 'assets/wp/js/form-billing-address.js',
            array( 'kiriof-script' ),
            KIRIOF_VERSION,
            array( 'in_footer' => true )
        );
        if ( function_exists( 'is_account_page' ) && is_account_page() && function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'edit-address' ) ) {
            wp_enqueue_script(
                'kiriof-account-address',
                KIRIOF_URL . 'assets/wp/js/account-address.js',
                array( 'kiriof-script', 'jquery', 'select2' ),
                KIRIOF_VERSION,
                array( 'in_footer' => true )
            );
            wp_localize_script(
                'kiriof-account-address',
                'kiriofAccountAddress',
                array(
                    'selectOption' => __( 'Select Option', 'kiriminaja-official' ),
                )
            );

			if ( $is_order_screen ) {
				$order_metabox_script = KIRIOF_DIR . 'assets/admin/dist/kiriminaja-order-metabox.js';
				if ( file_exists( $order_metabox_script ) ) {
					wp_enqueue_script( 'kiriof-order-metabox', $this->plugin_url . 'assets/admin/dist/kiriminaja-order-metabox.js', array( 'kiriof-cod-adjustment' ), (string) filemtime( $order_metabox_script ), true );
					wp_script_add_data( 'kiriof-order-metabox', 'type', 'module' );
				}
			}
        }

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
                $this->plugin_url . 'assets/wp/js/kj-tracking.js',
                array( 'jquery', 'kiriof-script' ),
                KIRIOF_VERSION,
                array( 'in_footer' => true )
            );

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
                $this->plugin_url . 'assets/wp/js/kiriof-block-checkout.js',
                array( 'kiriof-script', 'wp-element', 'wp-plugins', 'wp-data', 'wp-notices', 'wc-blocks-checkout' ),
                KIRIOF_VERSION,
                array( 'in_footer' => true )
            );
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
        $page   = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS );
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $screen_id = $screen ? $screen->id : '';

		if ( 'kiriminaja-onboarding' === $page || 'kiriminaja_page_kiriminaja-onboarding' === $screen_id ) {
			$this->enqueueOnboarding();
			return;
		}

        $is_plugin_page = in_array( $page, array(
            'kiriminaja-setting',
            'kiriminaja-transaction',
            'kiriminaja-transaction-detail',
            'kiriminaja-request-pickup',
            'kiriminaja-request-pickup-detail',
        ), true );

        $is_order_screen = in_array( $screen_id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true );

        $tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS );
        $is_wc_warehouses_settings = 'woocommerce_page_wc-settings' === $screen_id && 'kiriminaja_warehouses' === $tab;
        $is_wc_general_settings    = 'woocommerce_page_wc-settings' === $screen_id
                                     && ( 'general' === $tab || '' === $tab || null === $tab );

        if ( ! $is_plugin_page && ! $is_order_screen && ! $is_wc_warehouses_settings && ! $is_wc_general_settings ) {
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
        wp_enqueue_style( 'kiriof-badge-style', $this->plugin_url . 'assets/admin/css/kj-badge.css', array( 'kiriof-style' ), KIRIOF_VERSION, 'all' );



        $needs_leaflet = 'kiriminaja-setting' === $page || $is_wc_warehouses_settings || $is_wc_general_settings;

        if ( $needs_leaflet ) {
            wp_enqueue_style( 'kiriof-leaflet-style', $this->plugin_url . 'assets/lib/leaflet/leaflet.css', array(), '1.9.4' );
            wp_enqueue_script( 'kiriof-leaflet-script', $this->plugin_url . 'assets/lib/leaflet/leaflet.js', array(), '1.9.4', true );
        }

        $kiriof_script_dependencies = array( 'jquery', 'select2' );
        if ( $needs_leaflet ) {
            $kiriof_script_dependencies[] = 'kiriof-leaflet-script';
        }

        wp_enqueue_script( 'kiriof-script', $this->plugin_url . 'assets/admin/js/kj-admin-script.js', $kiriof_script_dependencies, KIRIOF_VERSION, true );
        
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

        if ( in_array( $page, array( 'kiriminaja-transaction', 'kiriminaja-transaction-detail' ), true ) ) {
            wp_enqueue_style( 'woocommerce_admin_styles' );
            // Load only the native order-preview scripts. Do not enqueue the
            // `woocommerce_admin` bundle: WC localizes its global only on native
            // screens, which causes a ReferenceError on this custom page.
            foreach ( array( 'wc-backbone-modal', 'wc-orders', 'wc-admin-order' ) as $order_script ) {
                if ( wp_script_is( $order_script, 'registered' ) ) {
                    wp_enqueue_script( $order_script );
                }
            }
            if ( wp_script_is( 'wc-backbone-modal', 'registered' ) ) {
                wp_enqueue_script( 'wc-backbone-modal' );
            }
			wp_enqueue_script( 'kiriof-pin-input', $this->plugin_url . 'assets/lib/pin-input/pin-input.js', array(), '0.2.0', true );
			wp_script_add_data( 'kiriof-pin-input', 'type', 'module' );
			wp_register_script(
				'kiriof-transaction-process',
				$this->plugin_url . 'assets/admin/js/kj-transaction-process.js',
				array( 'jquery', 'kiriof-script', 'kiriof-pin-input', 'wc-backbone-modal' ),
				KIRIOF_VERSION,
				true
            );
            wp_enqueue_script( 'kiriof-transaction-process' );

			$workspace_script = KIRIOF_DIR . 'assets/admin/dist/kiriminaja-admin-workspace.js';
			$this->enqueue_workspace_style();
			$this->enqueue_workspace_script( $workspace_script, array( 'kiriof-transaction-process' ) );
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
         * QR Code — use WooCommerce's bundled jquery-qrcode (handle: wc-qrcode)
         * for the "Scan to Pay" modal on the Request Pickup page.
         */
        /**
         * COD Adjustment JS — enqueued on the order edit screen and transaction process page.
         */
        if ( $is_order_screen || in_array( $page, array( 'kiriminaja-transaction', 'kiriminaja-transaction-detail' ), true ) ) {
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

        /**
         * Change Origin JS — enqueued on the transaction process page.
         */
        if ( 'kiriminaja-transaction' === $page || 'kiriminaja-transaction-detail' === $page ) {
            wp_enqueue_script(
                'kiriof-change-origin',
                $this->plugin_url . 'assets/js/kiriof-change-origin.js',
                array( 'jquery', 'select2', 'wp-util', 'underscore', 'backbone', 'wc-jquery-blockui', 'wc-backbone-modal' ),
                file_exists( KIRIOF_DIR . 'assets/js/kiriof-change-origin.js' ) ? filemtime( KIRIOF_DIR . 'assets/js/kiriof-change-origin.js' ) : KIRIOF_VERSION,
                true
            );
            wp_localize_script(
                'kiriof-change-origin',
                'kiriofChangeOrigin',
                array(
                    'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                    'previewNonce' => wp_create_nonce( 'woocommerce-preview-order' ),
                    'i18n' => array(
                        'selectLocation' => __( 'Please select a shipment location first.', 'kiriminaja-official' ),
                        'checkFailed'    => __( 'Shipping check failed.', 'kiriminaja-official' ),
                        'updateFailed'   => __( 'Failed to update the shipment origin.', 'kiriminaja-official' ),
                        'priceImpact'    => __( 'Order price impact', 'kiriminaja-official' ),
                        'previousCourier'=> __( 'Previous courier', 'kiriminaja-official' ),
                        'newCourier'     => __( 'New courier', 'kiriminaja-official' ),
                        'previousShipping' => __( 'Previous shipping', 'kiriminaja-official' ),
                        'newShipping'    => __( 'New shipping', 'kiriminaja-official' ),
                        'shippingDiscount' => __( 'Shipping discount', 'kiriminaja-official' ),
                        'courier'          => __( 'Courier', 'kiriminaja-official' ),
                        'orderTotal'     => __( 'Order total', 'kiriminaja-official' ),
                        'priceIncrease'  => __( 'increases', 'kiriminaja-official' ),
                        'priceDecrease'  => __( 'decreases', 'kiriminaja-official' ),
                        'noChange'       => __( 'No change', 'kiriminaja-official' ),
                        'checkingShipping' => __( 'Checking shipping route...', 'kiriminaja-official' ),
                        'replacementCourier' => __( 'Select a replacement courier with your consent.', 'kiriminaja-official' ),
                        'selectCourier' => __( 'Select courier', 'kiriminaja-official' ),
                        'replacementConsent' => __( 'I consent to use this replacement courier.', 'kiriminaja-official' ),
                        'orderBreakdown' => __( 'Order summary', 'kiriminaja-official' ),
                        'subTotal' => __( 'Sub Total', 'kiriminaja-official' ),
                        'shipping' => __( 'Shipping', 'kiriminaja-official' ),
                        'change' => __( 'Change', 'kiriminaja-official' ),
                        'collapse' => __( 'Collapse', 'kiriminaja-official' ),
                        'changeBlocked' => __( 'Change cannot be processed.', 'kiriminaja-official' ),
                        'refundRequired' => __( 'The adjusted order total would be below Rp0. Reconcile or refund the buyer {amount} before making this change.', 'kiriminaja-official' ),
                        'blocked' => __( 'Blocked', 'kiriminaja-official' ),
                    ),
                )
            );
        }

        if ( 'kiriminaja-request-pickup' === $page ) {
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
            wp_enqueue_script(
                'kiriof-request-pickup',
                $this->plugin_url . 'assets/admin/js/kj-request-pickup.js',
                array( 'jquery', 'kiriof-script', 'wc-qrcode', 'kiriof-qr-code-styling' ),
                KIRIOF_VERSION,
                true
            );

			$workspace_script = KIRIOF_DIR . 'assets/admin/dist/kiriminaja-admin-workspace.js';
			$this->enqueue_workspace_style();
			$this->enqueue_workspace_script( $workspace_script, array( 'kiriof-request-pickup' ) );
        }

		if ( 'kiriminaja-request-pickup-detail' === $page ) {
			$workspace_script = KIRIOF_DIR . 'assets/admin/dist/kiriminaja-admin-workspace.js';
			$this->enqueue_workspace_style();
			$this->enqueue_workspace_script( $workspace_script );
		}
   
    }

	private function enqueueOnboarding(): void {
		$progress_script = KIRIOF_DIR . 'assets/admin/dist/kiriminaja-onboarding-progress.js';
		if ( file_exists( $progress_script ) ) {
			$progress_style = KIRIOF_DIR . 'assets/admin/dist/kiriminaja-onboarding-progress.css';
			$shadcn_style   = KIRIOF_DIR . 'assets/admin/dist/kiriminaja-shadcn-onboarding.css';
			if ( file_exists( $shadcn_style ) ) {
				wp_enqueue_style(
					'kiriof-shadcn',
					$this->plugin_url . 'assets/admin/dist/kiriminaja-shadcn-onboarding.css',
					array(),
					(string) filemtime( $shadcn_style )
				);
			}
			if ( file_exists( $progress_style ) ) {
				wp_enqueue_style(
					'kiriof-onboarding-progress',
					$this->plugin_url . 'assets/admin/dist/kiriminaja-onboarding-progress.css',
					file_exists( $shadcn_style ) ? array( 'kiriof-shadcn' ) : array(),
					(string) filemtime( $progress_style )
				);
			}
			wp_enqueue_script(
				'kiriof-onboarding-progress',
				$this->plugin_url . 'assets/admin/dist/kiriminaja-onboarding-progress.js',
				array(),
				(string) filemtime( $progress_script ),
				true
			);
			wp_script_add_data( 'kiriof-onboarding-progress', 'type', 'module' );
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
