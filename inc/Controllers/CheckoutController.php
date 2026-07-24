<?php
namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CheckoutController
{
    private const KIRIOF_MIN_ADDRESS_LENGTH = 10;

    private $key_destination_id     = 'destination_id';
    private $key_destination_name   = 'destination_name';
    private $key_shipping_destination_id = 'shipping_destination_id';
    private $key_shipping_destination_name = 'shipping_destination_name';
    
    //billing checkout key
    private $field_destination_key  = 'kiriof_destination_area';
    private $field_insurance_key    = 'kiriof_insurance';
 
    //shipping checkout key
    private $field_shipping_destination_key  = 'kiriof_shipping_destination_area';
    private $field_shipping_insurance_key  = 'kiriof_shipping_insurance';
    private $kiriof_virtual_cart_cleanup_printed = false;
    private $kiriof_classic_insurance_printed = false;

    private function kiriof_cart_needs_shipping(): bool {
        if ( ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->cart ) || ! WC()->cart || ! method_exists( WC()->cart, 'needs_shipping' ) ) {
            return true;
        }

        return WC()->cart->needs_shipping();
    }

    private function kiriof_order_needs_shipping( $order ): bool {
        if ( ! $order instanceof \WC_Order ) {
            return false;
        }

        foreach ( $order->get_items( 'line_item' ) as $item ) {
            $product = method_exists( $item, 'get_product' ) ? $item->get_product() : false;
            if ( $product && method_exists( $product, 'needs_shipping' ) && $product->needs_shipping() ) {
                return true;
            }
        }

        return false;
    }

    private function kiriof_clear_logistics_session(): void {
        if ( ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->session ) || ! WC()->session ) {
            return;
        }

        foreach ( array(
            'kiriof_chosen_shipping_methods',
            'chosen_shipping_methods',
            'kiriof_expedition',
            'destination_id',
            'shipping_destination_id',
            'destination_name',
            'shipping_destination_name',
            'kiriof_destination_area',
            'kiriof_destination_area_name',
            'kiriof_insurance',
            'billing_insurance',
            'force_insurance',
            'kiriof_force_insurance',
            'kiriof_cached_insurance_amt',
            'kiriof_cached_cod_amt',
            'kiriof_cached_fee_context',
            'kiriof_shipping_coupon_rate_meta',
        ) as $key ) {
            WC()->session->set( $key, null );
        }
    }

    public function register()
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            /** Add Custom field Checkout Sub District */
            add_action('woocommerce_after_checkout_billing_form', array($this, 'add_custom_select_options_field_and_script'), 9999);
            add_action('wp_footer', array($this, 'add_custom_select_options_field_and_script'));
            add_action('woocommerce_before_order_notes', array($this, 'kiriof_render_classic_insurance_field'), 5);

            // Block checkout: register District as additional field and expose a Store API
            // update callback so React/block themes (ShopVerse etc.) can refresh fees.
            add_action('woocommerce_blocks_loaded', array($this, 'kiriof_register_block_checkout_fields'));
            add_action('woocommerce_blocks_loaded', array($this, 'kiriof_register_store_api_update_callback'));

            /** Validation Custom field Sub District */
            add_action( 'woocommerce_checkout_process', array($this,'kiriof_checkout_field_validation') );
            
            /** After Checkout*/
                /** Save custom field value as custom order metadata */
                add_action( 'woocommerce_checkout_create_order', array($this,'afterCheckoutBeforeCreated'), 10, 2 );
                /** After checkout Save custom field value as custom customer metadata */
                add_action( 'woocommerce_checkout_order_processed', array($this,'afterCheckoutAfterCreated'),10, 3);
                /** Block checkout Store API does not reliably trigger the classic processed hook. */
                add_action( 'woocommerce_store_api_checkout_update_order_from_request', array($this,'afterStoreApiCheckoutUpdateOrderFromRequest'), 10, 2 );
                add_action( 'woocommerce_store_api_checkout_order_processed', array($this,'afterStoreApiCheckoutOrderProcessed'), 10, 1 );
            /** end After Checkout */
            /** Expedition Ajax*/
            add_action('wp_ajax_kiriof-get-expedition-ajax', array($this,'getExpeditionOptionAjax'));
            add_action('wp_ajax_nopriv_kiriof-get-expedition-ajax', array($this,'getExpeditionOptionAjax'));
            add_action('wp_ajax_kiriof-session-save', array($this,'kiriof_ajax_session_save'));
            add_action('wp_ajax_nopriv_kiriof-session-save', array($this,'kiriof_ajax_session_save'));
                        
            /** Tag fee items with _kiriof_fee_type meta so duplicate detection works reliably */
            add_action( 'woocommerce_checkout_create_order_fee_item', array($this,'kiriof_tag_fee_item_meta'), 10, 4 );

            /** Custom Page Woocommerce Thankyou */
            add_action( 'woocommerce_order_details_after_order_table_items', array($this,'kiriof_order_details') );
            add_action( 'woocommerce_order_details_after_order_table', array($this,'kiriof_order_shipment_details') );
            
            /** remove Cache Shipping triger update_checkout */
            add_filter( 'woocommerce_cart_shipping_packages', array($this,'kiriof_shipping_rate_cache_invalidation'), 100 );
            /** Validate Shipping Kirimin aja */
            add_action('woocommerce_review_order_before_cart_contents', array($this,'kiriof_validateOrder'), 10);
            add_action('woocommerce_after_checkout_validation', array($this,'kiriof_validateOrder'), 10, 2);
            
            /**
             * Remove Billing and shipping Fields
             */
            add_filter('woocommerce_checkout_fields', array($this,'kiriof_billing_fields'), 9999);            
            add_filter('woocommerce_shipping_chosen_method', array($this,'kiriof_shipping_chosen_method'), 10, 2);
            
            add_filter( 'woocommerce_cart_needs_shipping', array($this,'kiriof_filter_cart_needs_shipping'));
            
            add_action('woocommerce_checkout_before_customer_details', array($this,'kiriof_add_checkout_nonce_field' ) );
            
            add_action( 'woocommerce_cart_calculate_fees', array($this,'kiriof_shipping_method_update') );
            add_filter( 'render_block', array( $this, 'kiriof_render_block_checkout_shipping_discount_row' ), 20, 2 );

            // Phone is required for courier pickup — force it at the locale level so
            // block checkout also treats it as mandatory (shows "Phone" not "Phone (optional)").
            add_filter( 'woocommerce_get_country_locale', array( $this, 'kiriof_require_phone_locale' ), 9999 );
            add_action( 'wp_footer', array( $this, 'kiriof_block_checkout_require_phone_label' ) );

            /** Control COD availability based on KiriminAja Config tab */
            add_filter( 'woocommerce_available_payment_gateways', array($this,'kiriof_filter_cod_availability'), 10, 1 );
        }
    }
    function kiriof_shipping_method_update() {
        if ( ! $this->kiriof_cart_needs_shipping() ) {
            $this->kiriof_clear_logistics_session();
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce cart calculation, nonce handled by WC
        if ( isset( $_POST['shipping_method'] ) && is_array( $_POST['shipping_method'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce cart calculation, nonce handled by WC
            $shipping_methods = array_map( 'sanitize_text_field', wp_unslash( $_POST['shipping_method'] ) );
            WC()->session->set( 'kiriof_chosen_shipping_methods', $shipping_methods );
            WC()->session->set( 'chosen_shipping_methods', $shipping_methods );
        }

        // Add insurance + COD as WC cart fees (works on traditional AND block checkout)
        $this->kiriof_add_checkout_fees();
    }

    private function kiriof_add_checkout_fees() {
        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
        if ( empty( $chosen_methods ) || ! is_array( $chosen_methods ) ) {
            return;
        }

        // Find the exact KiriminAja method + courier.
        $kiriof_method = '';
        foreach ( $chosen_methods as $method ) {
            if ( strpos( $method, 'kiriminaja-official' ) === 0 ) {
                $kiriof_method = $method;
                break;
            }
        }
        if ( '' === $kiriof_method ) {
            return;
        }

        $chosen_payment = $this->kiriof_get_checkout_payment_method();
        $destination_id = (int) WC()->session->get( 'destination_id', 0 );
        if ( ! $destination_id ) {
            $destination_id = (int) WC()->session->get( 'shipping_destination_id', 0 );
        }
        if ( ! $destination_id ) {
            return;
        }

        $force_insurance = (bool) WC()->session->get( 'kiriof_insurance', 0 );
        $discountContext = $this->kiriof_get_cart_discount_context();
        $cache_context   = array(
            'shipping_method' => $kiriof_method,
            'destination_id'  => $destination_id,
            'payment_method'  => $chosen_payment,
            'insurance'       => $force_insurance ? 1 : 0,
            'coupon_codes'    => $discountContext['coupon_codes'],
            'discount_total'  => $discountContext['discount_total'],
            'discount_tax'    => $discountContext['discount_tax'],
        );
        $cached_context  = WC()->session->get( 'kiriof_cached_fee_context', array() );
        $cache_matches   = $this->kiriof_fee_cache_matches( $cached_context, $cache_context );

        // Read cached fees from session only if they match the current checkout
        // context. Courier changes can change force-insurance rules; payment
        // changes must immediately clear stale COD amounts.
        $insurance_amt = $cache_matches
            ? (float) WC()->session->get( 'kiriof_cached_insurance_amt', 0 )
            : 0;
        $cod_amt       = $cache_matches
            ? (float) WC()->session->get( 'kiriof_cached_cod_amt', 0 )
            : 0;

        if ( 'cod' !== $chosen_payment ) {
            $cod_amt = 0;
            WC()->session->set( 'kiriof_cached_cod_amt', 0 );
        }

        // Fallback: if cache is empty or for a different checkout context,
        // calculate fees directly. This keeps non-COD insurance fresh too.
        if ( ! $cache_matches || ( $insurance_amt <= 0 && ( 'cod' !== $chosen_payment || $cod_amt <= 0 ) ) ) {
            try {
                $service = (new \KiriminAjaOfficial\Services\CheckoutServices\CheckoutCalculationService(array(
                    'destination_area_id' => $destination_id,
                    'expedition'          => $this->kiriof_extract_expedition_from_method( $kiriof_method ),
                    'is_insurance'        => $force_insurance,
                    'is_cod'              => ( 'cod' === $chosen_payment ),
                    'wc_cart_contents'    => WC()->cart->get_cart(),
                )))->call();

                if ( 200 === $service->status && ! empty( $service->data['calculation_result'] ) ) {
                    $result        = $service->data['calculation_result'];
                    $insurance_amt = (float) ( $result['insurance_amt'] ?? 0 );
                    $cod_amt       = ( 'cod' === $chosen_payment ) ? (float) ( $result['cod_amt'] ?? 0 ) : 0;

                    WC()->session->set( 'kiriof_cached_insurance_amt', $insurance_amt );
                    WC()->session->set( 'kiriof_cached_cod_amt', $cod_amt );
                    WC()->session->set( 'kiriof_cached_fee_context', $cache_context );
                }
            } catch ( \Throwable $th ) {
                (new \KiriminAjaOfficial\Base\BaseInit())->logThis('kiriof_add_checkout_fees_fallback', array( $th->getMessage() ) );
                return;
            }
        }

        if ( $insurance_amt > 0 ) {
            WC()->cart->add_fee(
                __( 'Insurance', 'kiriminaja-official' ),
                $insurance_amt,
                false
            );
        }

        if ( 'cod' === $chosen_payment && $cod_amt > 0 ) {
            WC()->cart->add_fee(
                __( 'COD Fee', 'kiriminaja-official' ),
                $cod_amt,
                false
            );
        }
    }

    private function kiriof_fee_cache_matches( $cached_context, $cache_context ) {
        return is_array( $cached_context ) && $cached_context === $cache_context;
    }
    private function kiriof_extract_expedition_from_method( $shipping_method ) {
        $shipping_method = (string) $shipping_method;
        if ( 0 === strpos( $shipping_method, 'kiriminaja-official_' ) ) {
            return substr( $shipping_method, strlen( 'kiriminaja-official_' ) );
        }
        if ( 0 === strpos( $shipping_method, 'kiriminaja-official:' ) ) {
            return substr( $shipping_method, strlen( 'kiriminaja-official:' ) );
        }
        return $shipping_method;
    }

    private function kiriof_get_checkout_payment_method( $order = null ) {
        $payment_method = '';

        if ( $order instanceof \WC_Order ) {
            $payment_method = (string) $order->get_payment_method();
            if ( '' === $payment_method ) {
                $payment_method = (string) $order->get_meta( '_kiriof_checkout_payment_method', true );
            }
        }

        if ( '' === $payment_method ) {
            $payment_method = (string) WC()->session->get( 'chosen_payment_method', '' );
        }
        if ( '' === $payment_method ) {
            $payment_method = (string) WC()->session->get( 'kiriof_payment_method', '' );
        }
        if ( '' === $payment_method ) {
            $payment_method = (string) WC()->session->get( 'payment_method', '' );
        }

        return $payment_method;
    }

    private function kiriof_get_cart_discount_context() {
        $context = array(
            'coupon_codes'   => '',
            'discount_total' => 0,
            'discount_tax'   => 0,
        );

        if ( ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->cart ) || ! WC()->cart ) {
            return $context;
        }

        $coupon_codes = array_keys((array) WC()->cart->get_coupons());
        if ( is_array( $coupon_codes ) && ! empty( $coupon_codes ) ) {
            $coupon_codes = array_filter( array_map( 'sanitize_text_field', $coupon_codes ) );
            sort( $coupon_codes );
            $context['coupon_codes'] = implode( ',', $coupon_codes );
        }

        $context['discount_total'] = (float) WC()->cart->get_discount_total();
        $context['discount_tax']   = (float) WC()->cart->get_discount_tax();

        return $context;
    }

    private function kiriof_get_store_api_destination_field( $request ) {
        if ( ! $request instanceof \WP_REST_Request ) {
            return '';
        }

        $params = $request->get_params();
        $stack  = array( $params );

        while ( ! empty( $stack ) ) {
            $value = array_pop( $stack );
            if ( ! is_array( $value ) ) {
                continue;
            }

            foreach ( $value as $key => $item ) {
                if ( is_string( $key ) && false !== strpos( $key, 'kiriof_destination_area' ) && ! is_array( $item ) ) {
                    return sanitize_text_field( (string) $item );
                }
                if ( is_array( $item ) ) {
                    $stack[] = $item;
                }
            }
        }

        return '';
    }

    private function kiriof_resolve_destination_area( $destination_area, $destination_name, $order = null ) {
        $destination_area = (string) $destination_area;
        $destination_name = (string) $destination_name;

        if ( empty( $destination_area ) || is_numeric( $destination_area ) ) {
            return array( $destination_area, $destination_name );
        }

        $destination_name = $destination_area;
        $api_service      = new \KiriminAjaOfficial\Services\KiriminajaApiService();
        $order_postcode   = '';

        if ( $order instanceof \WC_Order ) {
            $order_postcode = $order->get_shipping_postcode();
            if ( empty( $order_postcode ) ) {
                $order_postcode = $order->get_billing_postcode();
            }
        }

        if ( ! empty( $order_postcode ) ) {
            $search_result = $api_service->sub_district_search( $order_postcode );
            if ( 200 === $search_result->status && ! empty( $search_result->data ) ) {
                foreach ( $search_result->data as $match ) {
                    $match = (object) $match;
                    if ( false !== stripos( (string) ($match->text ?? ''), $destination_area ) ) {
                        return array( (string) ($match->id ?? ''), (string) ($match->text ?? '') );
                    }
                }

                $firstMatch = (object) $search_result->data[0];
                return array( (string) ($firstMatch->id ?? ''), (string) ($firstMatch->text ?? '') );
            }
        }

        $search_result = $api_service->sub_district_search( $destination_area );
        if ( 200 === $search_result->status && ! empty( $search_result->data ) ) {
            $firstMatch = (object) $search_result->data[0];
            return array( (string) ($firstMatch->id ?? ''), (string) ($firstMatch->text ?? '') );
        }

        return array( $destination_area, $destination_name );
    }

    private function kiriof_extract_postcode_from_destination_name( $destination_name ): string {
        if ( preg_match( '/(?:^|,\s*)(\d{5})\s*$/', (string) $destination_name, $matches ) ) {
            return $matches[1];
        }

        return '';
    }

    private function kiriof_is_store_api_request() {
        if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
            return false;
        }

        $route = '';
        if ( isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
            $route = (string) $GLOBALS['wp']->query_vars['rest_route'];
        }
        if ( '' === $route && isset( $_SERVER['REQUEST_URI'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only used for route detection
            $route = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
        }

        return false !== strpos( $route, '/wc/store/' );
    }

    private function kiriof_get_store_api_selected_shipping_rate() {
        if ( ! $this->kiriof_is_store_api_request() ) {
            return '';
        }

        $route = '';
        if ( isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
            $route = (string) $GLOBALS['wp']->query_vars['rest_route'];
        }
        if ( '' === $route && isset( $_SERVER['REQUEST_URI'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only used for route detection
            $route = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
        }
        if ( false === strpos( $route, '/cart/select-shipping-rate' ) ) {
            return '';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce Store API request, nonce handled by WC.
        if ( isset( $_POST['rate_id'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce Store API request, nonce handled by WC.
            return sanitize_text_field( wp_unslash( $_POST['rate_id'] ) );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- php://input is required for JSON REST payloads.
        $raw_body = file_get_contents( 'php://input' );
        if ( empty( $raw_body ) ) {
            return '';
        }

        $payload = json_decode( $raw_body, true );
        if ( ! is_array( $payload ) || empty( $payload['rate_id'] ) ) {
            return '';
        }

        return sanitize_text_field( (string) $payload['rate_id'] );
    }

    private function kiriof_is_block_checkout_request() {
        if ( is_checkout() ) {
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
        }

        if ( is_cart() ) {
            $cart_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'cart' ) : 0;
            if ( $cart_page_id > 0 && function_exists( 'has_block' ) && has_block( 'woocommerce/cart', $cart_page_id ) ) {
                return true;
            }
            if ( class_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' ) && method_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils', 'is_cart_block_default' ) ) {
                if ( \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_cart_block_default() ) {
                    return true;
                }
            }
            if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
                return true;
            }
        }

        return false;
    }

    function kiriof_filter_cart_needs_shipping( $needs_shipping ) {
        if ( ! $needs_shipping ) {
            $this->kiriof_clear_logistics_session();
        }

        if ( is_cart() ) {
            if( $needs_shipping && get_option( 'woocommerce_enable_shipping_calc' ) === 'no' ){
                WC()->session->set( 'destination_id', null );
            }
        }
        return $needs_shipping;
    }
    function kiriof_add_checkout_nonce_field(){
        wp_nonce_field(KIRIOF_NONCE, 'checkout_kiriminaja_nonce_field');
    }
    function add_custom_select_options_field_and_script($checkout)
    {
        if ( ! is_cart() && ! is_checkout() ) {
            return;
        }

        if ( ! $this->kiriof_cart_needs_shipping() ) {
            $this->kiriof_clear_logistics_session();
            $this->kiriof_render_virtual_cart_district_cleanup();
            return;
        }

        $field_key = $this->field_destination_key;
        $destination_id = WC()->session->get($this->key_destination_id);
        $destination_name = WC()->session->get($this->key_destination_name);
        $shipping_destination_id = WC()->session->get($this->key_shipping_destination_id);
        $shipping_destination_name = WC()->session->get($this->key_shipping_destination_name);
        $kiriof_saved_destination_map = WC()->session->get( 'kiriof_destination_postcode_map', array() );
        $kiriof_saved_checkout_postcode = WC()->session->get( 'kiriof_checkout_postcode', '' );
        
        $insurance_setting        = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('enable_insurance');
        $kiriof_global_insurance   = ( $insurance_setting && 'yes' === $insurance_setting->value );
        $kiriof_checkout_token     = empty($destination_id) ? false : true;
        require_once (plugin_dir_path(dirname(__FILE__,2)). 'templates/front/form-billing-address.php');
    }

    private function kiriof_render_virtual_cart_district_cleanup(): void {
        if ( $this->kiriof_virtual_cart_cleanup_printed ) {
            return;
        }
        $this->kiriof_virtual_cart_cleanup_printed = true;
        ?>
        <style>
            .kiriof-virtual-cart-checkout .kiriof-block-district-field-wrapper,
            .kiriof-virtual-cart-checkout .kiriof-block-district-source-wrapper,
            .kiriof-virtual-cart-checkout .kiriof-block-district-select-wrapper,
            .kiriof-virtual-cart-checkout .kiriof-block-district-warning {
                display: none !important;
            }
        </style>
        <script>
        (function() {
            document.documentElement.classList.add('kiriof-virtual-cart-checkout');

            function kiriofHideVirtualCartDistrictFields() {
                var selectors = [
                    '[name*="kiriof_destination_area"]',
                    '[id*="kiriof_destination_area"]',
                    '.kiriof-block-district-source',
                    '.kiriof-block-district-select'
                ].join(',');

                document.querySelectorAll(selectors).forEach(function(field) {
                    if (!field || field.id === 'kiriof-block-district-mirror') {
                        return;
                    }

                    field.removeAttribute('required');
                    field.setAttribute('aria-required', 'false');
                    if ('value' in field) {
                        field.value = '';
                    }

                    var wrapper = field.closest(
                        '.kiriof-block-district-field-wrapper,' +
                        '.kiriof-block-district-source-wrapper,' +
                        '.kiriof-block-district-select-wrapper,' +
                        '.wc-block-components-text-input,' +
                        '.wc-block-components-address-form__state,' +
                        '.wc-block-components-combobox,' +
                        '.form-row,' +
                        'p'
                    );

                    if (wrapper && wrapper !== document.body) {
                        wrapper.style.display = 'none';
                        wrapper.setAttribute('hidden', 'hidden');
                    } else {
                        field.style.display = 'none';
                        field.setAttribute('hidden', 'hidden');
                    }
                });

                document.querySelectorAll('.kiriof-block-district-warning').forEach(function(warning) {
                    warning.style.display = 'none';
                    warning.setAttribute('hidden', 'hidden');
                });
            }

            kiriofHideVirtualCartDistrictFields();
            if (document.body && window.MutationObserver) {
                new MutationObserver(kiriofHideVirtualCartDistrictFields).observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        })();
        </script>
        <?php
    }
    function kiriof_checkout_field_validation() {
        try {
             // Verify Nonce - fail early if missing or invalid
            if ( ! isset( $_POST['checkout_kiriminaja_nonce_field'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['checkout_kiriminaja_nonce_field'] ) ), KIRIOF_NONCE ) ) {
                return;
            }

            if ( ! $this->kiriof_cart_needs_shipping() ) {
                $this->kiriof_clear_logistics_session();
                return;
            }

            $this->kiriof_normalize_classic_destination_post_data();
                
            $field_key = $this->field_destination_key;
            
            if ( isset($_POST[$field_key]) && empty($_POST[$field_key]) ) {
                wc_add_notice( esc_html__('<strong>Field Kelurahan</strong> is a required field.', 'kiriminaja-official'),'error' );
            }
            (new \KiriminAjaOfficial\Services\CheckoutServices\ValidationCodCalculationService([
                'shipping_method'   => WC()->session->get('chosen_shipping_methods'),
                'payment_method'    => WC()->session->get('chosen_payment_method'),
                'cart_total'        => WC()->cart->total,
                'shipping_packages' => WC()->shipping()->get_packages(),
            ]))->call();
        
        }catch (\Throwable $th) {
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('kiriof_checkout_field_validation',[$th->getMessage()]);   
        }
    }
    function add_custom_select_options_field_and_script_shipping()
    {
        require_once (plugin_dir_path(dirname(__FILE__,2)). 'templates/front/form-shipping-address.php');
    }

    private function kiriof_get_posted_text_field( string $key ): string {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Read-only checkout POST normalization; WooCommerce verifies checkout/update-order-review requests.
        if ( ! isset( $_POST[ $key ] ) || is_array( $_POST[ $key ] ) ) {
            // phpcs:enable WordPress.Security.NonceVerification.Missing
            return '';
        }

        $value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        return $value;
    }

    private function kiriof_set_posted_text_field_if_empty( string $key, string $value ): void {
        if ( '' === $value || '' !== $this->kiriof_get_posted_text_field( $key ) ) {
            return;
        }

        $_POST[ $key ] = $value;
    }

    private function kiriof_get_session_text_field( string $key ): string {
        if ( ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->session ) || ! WC()->session ) {
            return '';
        }

        return sanitize_text_field( (string) WC()->session->get( $key, '' ) );
    }

    private function kiriof_normalize_classic_destination_post_data(): void {
        $billing_destination = $this->kiriof_get_posted_text_field( $this->field_destination_key );
        $shipping_destination = $this->kiriof_get_posted_text_field( $this->field_shipping_destination_key );
        $billing_name = $this->kiriof_get_posted_text_field( 'kiriof_destination_area_name' );
        $shipping_name = $this->kiriof_get_posted_text_field( 'kiriof_shipping_destination_area_name' );

        $session_destination = $this->kiriof_get_session_text_field( 'kiriof_destination_area' );
        if ( '' === $session_destination ) {
            $session_destination = $this->kiriof_get_session_text_field( 'destination_id' );
        }
        if ( '' === $session_destination ) {
            $session_destination = $this->kiriof_get_session_text_field( 'shipping_destination_id' );
        }

        $session_name = $this->kiriof_get_session_text_field( 'kiriof_destination_area_name' );
        if ( '' === $session_name ) {
            $session_name = $this->kiriof_get_session_text_field( 'destination_name' );
        }
        if ( '' === $session_name ) {
            $session_name = $this->kiriof_get_session_text_field( 'shipping_destination_name' );
        }

        $destination = '' !== $shipping_destination ? $shipping_destination : $billing_destination;
        if ( '' === $destination ) {
            $destination = $session_destination;
        }

        $destination_name = '' !== $shipping_name ? $shipping_name : $billing_name;
        if ( '' === $destination_name ) {
            $destination_name = $session_name;
        }

        $this->kiriof_set_posted_text_field_if_empty( $this->field_destination_key, $destination );
        $this->kiriof_set_posted_text_field_if_empty( 'kiriof_destination_area_name', $destination_name );

        if ( '' !== $this->kiriof_get_posted_text_field( 'ship_to_different_address' ) ) {
            $this->kiriof_set_posted_text_field_if_empty( $this->field_shipping_destination_key, $destination );
            $this->kiriof_set_posted_text_field_if_empty( 'kiriof_shipping_destination_area_name', $destination_name );
        }

        if ( '' !== $destination && '' === $this->kiriof_get_posted_text_field( 'kiriof_checkout_token' ) ) {
            $_POST['kiriof_checkout_token'] = '1';
        }
    }

    private function kiriof_get_checkout_posted_address(): string {
        $billing_address = $this->kiriof_get_posted_text_field( 'billing_address_1' );
        $shipping_address = $this->kiriof_get_posted_text_field( 'shipping_address_1' );

        if ( '' !== $this->kiriof_get_posted_text_field( 'ship_to_different_address' ) ) {
            return '' !== $shipping_address ? $shipping_address : $billing_address;
        }

        return $billing_address;
    }

    private function kiriof_get_checkout_posted_address_length(): int {
        $address = $this->kiriof_get_checkout_posted_address();

        if ( function_exists( 'mb_strlen' ) ) {
            return mb_strlen( $address );
        }

        return strlen( $address );
    }

    private function kiriof_checkout_posted_address_is_too_short(): bool {
        return '' !== $this->kiriof_get_checkout_posted_address()
            && $this->kiriof_get_checkout_posted_address_length() < self::KIRIOF_MIN_ADDRESS_LENGTH;
    }

    private function kiriof_get_address_length_notice(): string {
        return sprintf(
            /* translators: %d: minimum checkout address length in characters. */
            esc_html__( 'Address length must be greater than %d', 'kiriminaja-official' ),
            self::KIRIOF_MIN_ADDRESS_LENGTH
        );
    }

    private function kiriof_add_address_length_notice( $errors = null ): void {
        $message = $this->kiriof_get_address_length_notice();

        if ( $errors instanceof \WP_Error ) {
            if ( ! in_array( $message, $errors->get_error_messages(), true ) ) {
                $errors->add( 'kiriof_address_length', $message );
            }
            return;
        }

        if ( function_exists( 'wc_has_notice' ) && wc_has_notice( $message, 'error' ) ) {
            return;
        }

        wc_add_notice( $message, 'error' );
    }

    private function kiriof_remove_shipping_method_required_errors( $errors ): void {
        if ( ! $errors instanceof \WP_Error ) {
            return;
        }

        foreach ( $errors->get_error_codes() as $code ) {
            foreach ( $errors->get_error_messages( $code ) as $message ) {
                $plain_message = wp_strip_all_tags( (string) $message );
                if (
                    false !== stripos( $plain_message, 'No shipping method has been selected' )
                    || false !== stripos( $plain_message, 'Shipping is a required field' )
                ) {
                    $errors->remove( $code );
                    break 2;
                }
            }
        }
    }

    public function afterStoreApiCheckoutOrderProcessed( $order ){
        if ( ! $order instanceof \WC_Order ) {
            return;
        }

        // Avoid duplicate inserts if Woo also fires the classic processed hook.
        $existing_transaction = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransactionByWCOrderId( $order->get_id() );
        if ( $existing_transaction ) {
            return;
        }

        $this->afterCheckoutAfterCreated( $order->get_id(), array(), $order );
    }

    public function afterStoreApiCheckoutUpdateOrderFromRequest( $order, $request ){
        if ( ! $order instanceof \WC_Order ) {
            return;
        }
        if ( ! $this->kiriof_order_needs_shipping( $order ) ) {
            $this->kiriof_clear_logistics_session();
            return;
        }

        $chosen_methods = WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array();
        $shipping_method = ( is_array( $chosen_methods ) && ! empty( $chosen_methods[0] ) )
            ? sanitize_text_field( $chosen_methods[0] )
            : '';

        if ( empty( $shipping_method ) || 0 !== strpos( $shipping_method, 'kiriminaja-official' ) ) {
            return;
        }

        $destination_area = (string) WC()->session->get( 'kiriof_destination_area', '' );
        if ( '' === $destination_area ) {
            $destination_area = (string) WC()->session->get( 'shipping_destination_id', WC()->session->get( 'destination_id', '' ) );
        }
        if ( '' === $destination_area ) {
            $destination_area = $this->kiriof_get_store_api_destination_field( $request );
        }

        $destination_name = (string) WC()->session->get( 'kiriof_destination_area_name', '' );
        if ( '' === $destination_name ) {
            $destination_name = (string) WC()->session->get( 'shipping_destination_name', WC()->session->get( 'destination_name', '' ) );
        }
        if ( '' === $destination_name && ! is_numeric( $destination_area ) ) {
            $destination_name = $destination_area;
        }

        list( $destination_area, $destination_name ) = $this->kiriof_resolve_destination_area(
            $destination_area,
            $destination_name,
            $order
        );

        $payment_method = $order->get_payment_method();
        if ( '' === $payment_method && $request instanceof \WP_REST_Request ) {
            $payment_method = sanitize_text_field( (string) $request->get_param( 'payment_method' ) );
        }
        if ( '' === $payment_method ) {
            $payment_method = $this->kiriof_get_checkout_payment_method( $order );
        }

        $insurance_setting = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('enable_insurance');
        $insurance = ( $insurance_setting && 'yes' === $insurance_setting->value )
            ? 1
            : (int) WC()->session->get( 'billing_insurance', WC()->session->get( 'kiriof_insurance', 0 ) );
        $woo_discount_amount = 0;
        $woo_discount_description = '';
        if ( function_exists( 'WC' ) && WC() && isset( WC()->cart ) && WC()->cart ) {
            $woo_discount_amount = (float) WC()->cart->get_discount_total() + (float) WC()->cart->get_discount_tax();
            $coupon_codes = array_keys((array) WC()->cart->get_coupons());
            if ( is_array( $coupon_codes ) && ! empty( $coupon_codes ) ) {
                $woo_discount_description = implode( ', ', array_filter( array_map( 'sanitize_text_field', $coupon_codes ) ) );
            }
        }

        $order->update_meta_data( '_kiriof_checkout_destination_area', $destination_area );
        $order->update_meta_data( '_kiriof_checkout_destination_area_name', $destination_name );
        $order->update_meta_data( '_kiriof_checkout_expedition', $this->kiriof_extract_expedition_from_method( $shipping_method ) );
        $order->update_meta_data( '_kiriof_checkout_token', '1' );
        $order->update_meta_data( '_kiriof_checkout_billing_insurance', $insurance );
        $order->update_meta_data( '_kiriof_checkout_payment_method', $payment_method );
        $order->update_meta_data( '_kiriof_checkout_force_insurance', WC()->session->get( 'force_insurance', 0 ) );
        $order->update_meta_data( '_kiriof_checkout_woocommerce_discount_amount', $woo_discount_amount );
        $order->update_meta_data( '_kiriof_checkout_woocommerce_discount_description', $woo_discount_description );

        if ( '' !== $destination_area ) {
            $order->update_meta_data( '_' . $this->field_destination_key, sanitize_text_field( $destination_area ) );
            $order->update_meta_data( '_billing_kiriof_destination_area', sanitize_text_field( $destination_area ) );
            $order->update_meta_data( '_shipping_kiriof_destination_area', sanitize_text_field( $destination_area ) );
        }
        if ( '' !== $destination_name ) {
            $order->update_meta_data( '_billing_kiriof_destination_name', sanitize_text_field( $destination_name ) );
            $order->update_meta_data( '_shipping_kiriof_destination_name', sanitize_text_field( $destination_name ) );
        }
        if ( ! empty( $insurance ) ) {
            $order->update_meta_data( '_' . $this->field_insurance_key, '1' );
        }
    }

    function afterCheckoutAfterCreated( $order_id, $posted_data, $order ){
        /** Resolve the order object: WC may pass null in some flows */
        if ( ! $order instanceof \WC_Order ) {
            $order = wc_get_order( $order_id );
        }
        if ( ! $this->kiriof_order_needs_shipping( $order ) ) {
            $this->kiriof_clear_logistics_session();
            return;
        }

        /**
         * Read checkout context from order meta first (set in afterCheckoutBeforeCreated),
         * then fall back to WC()->session for backwards compatibility.
         * WC()->session can be reset between create_order and checkout_order_processed,
         * so meta is the reliable source.
         */
        $kiriof_expedition            = $order ? (string) $order->get_meta( '_kiriof_checkout_expedition', true ) : '';
        $kiriof_destination_area      = $order ? (string) $order->get_meta( '_kiriof_checkout_destination_area', true ) : '';
        $kiriof_destination_area_name = $order ? (string) $order->get_meta( '_kiriof_checkout_destination_area_name', true ) : '';
        $kiriof_checkout_token        = $order ? (string) $order->get_meta( '_kiriof_checkout_token', true ) : '';
        $payment_method               = $order ? (string) $order->get_meta( '_kiriof_checkout_payment_method', true ) : '';
        $force_insurance              = $order ? $order->get_meta( '_kiriof_checkout_force_insurance', true ) : '';
        $billing_insurance_meta       = $order ? $order->get_meta( '_kiriof_checkout_billing_insurance', true ) : '';
        $woo_discount_amount          = $order ? $order->get_meta( '_kiriof_checkout_woocommerce_discount_amount', true ) : '';
        $woo_discount_description     = $order ? $order->get_meta( '_kiriof_checkout_woocommerce_discount_description', true ) : '';

        if ( '' === $kiriof_expedition ) {
            $kiriof_expedition = (string) WC()->session->get( 'kiriof_expedition', '' );
        }
        if ( '' === $kiriof_destination_area ) {
            $kiriof_destination_area = (string) WC()->session->get( 'kiriof_destination_area', '' );
        }
        if ( '' === $kiriof_destination_area_name ) {
            $kiriof_destination_area_name = (string) WC()->session->get( 'kiriof_destination_area_name', '' );
        }
        if ( '' === $kiriof_checkout_token ) {
            $kiriof_checkout_token = (string) WC()->session->get( 'kiriof_checkout_token', '' );
        }
        if ( '' === $payment_method ) {
            $payment_method = $this->kiriof_get_checkout_payment_method( $order );
        }
        if ( '' === $force_insurance ) {
            $force_insurance = WC()->session->get( 'force_insurance', '' );
        }
        if ( '' === (string) $woo_discount_amount ) {
            $woo_discount_amount = WC()->session->get( 'kiriof_woocommerce_discount_amount', 0 );
        }
        if ( '' === (string) $woo_discount_description ) {
            $woo_discount_description = WC()->session->get( 'kiriof_woocommerce_discount_description', '' );
        }

        list( $kiriof_destination_area, $kiriof_destination_area_name ) = $this->kiriof_resolve_destination_area(
            $kiriof_destination_area,
            $kiriof_destination_area_name,
            $order
        );

        /** if kiriof_field value is not exist or null then prevent */
        if ( empty( $kiriof_expedition ) ) {
            return;
        }

        if ( (int) $force_insurance === 1 ) {
            $insurance = 1;
        } else {
            $insurance = '' !== (string) $billing_insurance_meta
                ? $billing_insurance_meta
                : WC()->session->get( 'billing_insurance', '' );
        }
        // Clear stored values from WooCommerce session
        WC()->session->set( 'kiriof_destination_area', null );
        WC()->session->set( 'kiriof_destination_area_name', null );
        WC()->session->set( 'kiriof_expedition', null );
        WC()->session->set( 'kiriof_checkout_token', null );
        WC()->session->set( 'payment_method', null );
        WC()->session->set( 'force_insurance', null );
        WC()->session->set( 'billing_insurance', null );
        WC()->session->set( 'kiriof_woocommerce_discount_amount', null );
        WC()->session->set( 'kiriof_woocommerce_discount_description', null );
        /** Store Transaction*/
        try {
            $createTransaction = (new \KiriminAjaOfficial\Services\CheckoutServices\CreateTransactionService([
                'order_id'                  => @$order_id,
                'checkout_post_data'        => @$posted_data,
                'kiriof_destination_area'       => @$kiriof_destination_area,
                'kiriof_destination_area_name'  => @$kiriof_destination_area_name,
                'kiriof_expedition'             => @$kiriof_expedition,
                'is_insurance'              => @$insurance,
                'is_cod'                    => $payment_method === 'cod',
                'wc_cart_contents'          => WC()->cart->cart_contents,
                'woo_discount_amount'       => (float) $woo_discount_amount,
                'woo_discount_description'  => (string) $woo_discount_description,
                'destination_zipcode'       => $order ? (string) $order->get_meta( '_kiriof_checkout_postcode', true ) : '',
            ]))->call();
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('afterCheckoutAfterCreated',[$createTransaction]);
        } catch (\Throwable $th){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('afterCheckoutAfterCreated',[$th->getMessage()]);   
        }

        /** Clean up transient checkout meta now that the transaction row has been processed. */
        if ( $order instanceof \WC_Order ) {
            $order->delete_meta_data( '_kiriof_checkout_destination_area' );
            $order->delete_meta_data( '_kiriof_checkout_destination_area_name' );
            $order->delete_meta_data( '_kiriof_checkout_expedition' );
            $order->delete_meta_data( '_kiriof_checkout_token' );
            $order->delete_meta_data( '_kiriof_checkout_billing_insurance' );
            $order->delete_meta_data( '_kiriof_checkout_payment_method' );
            $order->delete_meta_data( '_kiriof_checkout_force_insurance' );
            $order->delete_meta_data( '_kiriof_checkout_woocommerce_discount_amount' );
            $order->delete_meta_data( '_kiriof_checkout_woocommerce_discount_description' );
            $order->delete_meta_data( '_kiriof_checkout_postcode' );
            $order->save();
        }
    }
    
    // phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce checkout flow verifies nonce before this hook runs.
    function afterCheckoutBeforeCreated($order,$data ){
        if ( $order instanceof \WC_Order && ! $this->kiriof_order_needs_shipping( $order ) ) {
            $this->kiriof_clear_logistics_session();
            return;
        }

        $this->kiriof_normalize_classic_destination_post_data();

        /**
         * Classic checkout posts kiriof fields directly. Block checkout submits via
         * Store API, so those POST fields may be absent; use values persisted by
         * kiriof_store_api_update_checkout() as the source of truth.
         */
        if ( isset( $_POST['shipping_method'][0] ) && ! empty( $_POST['shipping_method'][0] ) ) {
            $shipping_method = sanitize_text_field( wp_unslash( $_POST['shipping_method'][0] ) );
        } else {
            $chosen_methods  = WC()->session->get( 'chosen_shipping_methods', array() );
            $shipping_method = ( is_array( $chosen_methods ) && ! empty( $chosen_methods[0] ) )
                ? sanitize_text_field( $chosen_methods[0] )
                : '';
        }

        if ( empty( $shipping_method ) || 0 !== strpos( $shipping_method, 'kiriminaja-official' ) ) {
            return;
        }

        if ( empty($_POST['ship_to_different_address']) ){
            $destination_area = isset($_POST['kiriof_destination_area']) ? sanitize_text_field( wp_unslash($_POST['kiriof_destination_area'])) : '';
            $destinasi_name = isset($_POST['kiriof_destination_area_name']) ? sanitize_text_field(wp_unslash($_POST['kiriof_destination_area_name'])) : '';
            $insurance_post = isset($_POST[$this->field_insurance_key]) ? sanitize_text_field(wp_unslash($_POST[$this->field_insurance_key])) : '';
        }else{
            $destinasi_name = isset($_POST['kiriof_shipping_destination_area_name']) ? sanitize_text_field(wp_unslash($_POST['kiriof_shipping_destination_area_name'])) : '';
            $insurance_post = isset($_POST[$this->field_insurance_key]) ? sanitize_text_field(wp_unslash($_POST[$this->field_insurance_key])) : '';
            $destination_area = isset($_POST['kiriof_shipping_destination_area']) ? sanitize_text_field(wp_unslash($_POST['kiriof_shipping_destination_area'])) : '';
        }

        if ( '' === $destination_area ) {
            $destination_area = (string) WC()->session->get( 'shipping_destination_id', WC()->session->get( 'destination_id', '' ) );
        }
        if ( '' === $destinasi_name ) {
            $destinasi_name = (string) WC()->session->get( 'shipping_destination_name', WC()->session->get( 'destination_name', '' ) );
        }
        if ( '' === $insurance_post && (int) WC()->session->get( 'kiriof_insurance', 0 ) === 1 ) {
            $insurance_post = '1';
        }

            $woo_discount_amount = 0;
            $woo_discount_description = '';
            if ( function_exists( 'WC' ) && WC() && isset( WC()->cart ) && WC()->cart ) {
                $woo_discount_amount = (float) WC()->cart->get_discount_total() + (float) WC()->cart->get_discount_tax();
                $coupon_codes = array_keys((array) WC()->cart->get_coupons());
                if ( is_array( $coupon_codes ) && ! empty( $coupon_codes ) ) {
                    $woo_discount_description = implode( ', ', array_filter( array_map( 'sanitize_text_field', $coupon_codes ) ) );
                }
            }

            list( $destination_area, $destinasi_name ) = $this->kiriof_resolve_destination_area(
                $destination_area,
                $destinasi_name,
                $order
            );

            // Force insurance when global insurance setting is enabled
            $insurance_setting = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('enable_insurance');
            if ( $insurance_setting && 'yes' === $insurance_setting->value ) {
                $insurance_post = '1';
            }
            /** Store custom field value in WooCommerce session (not PHP session) */
            $kiriof_filter_methods = $this->kiriof_extract_expedition_from_method( $shipping_method );
            $kiriof_checkout_token_post = isset( $_POST['kiriof_checkout_token'] ) ? sanitize_text_field( wp_unslash( $_POST['kiriof_checkout_token'] ) ) : '1';
            $kiriof_payment_method_post = isset( $_POST['payment_method'] )
                ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) )
                : (string) WC()->session->get( 'kiriof_payment_method', WC()->session->get( 'chosen_payment_method', '' ) );
            $kiriof_force_insurance_post = isset( $_POST['kiriof_force_insurance'] ) ? intval( $_POST['kiriof_force_insurance'] ) : 0;
            $kiriof_billing_insurance_post = ! empty( $insurance_post ) ? 1 : 0;

            // Use WooCommerce session instead of PHP session
            WC()->session->set( 'kiriof_destination_area', $destination_area );
            WC()->session->set( 'kiriof_destination_area_name', $destinasi_name );
            WC()->session->set( 'kiriof_expedition', $kiriof_filter_methods );
            WC()->session->set( 'kiriof_checkout_token', $kiriof_checkout_token_post );
            WC()->session->set( 'billing_insurance', $kiriof_billing_insurance_post );
            WC()->session->set( 'payment_method', $kiriof_payment_method_post );
            WC()->session->set( 'force_insurance', $kiriof_force_insurance_post );
            WC()->session->set( 'kiriof_woocommerce_discount_amount', $woo_discount_amount );
            WC()->session->set( 'kiriof_woocommerce_discount_description', $woo_discount_description );

            /**
             * Persist on the order itself so afterCheckoutAfterCreated() can read these
             * even if WC()->session is reset between hooks during checkout completion.
             */
            $order->update_meta_data( '_kiriof_checkout_destination_area', $destination_area );
            $order->update_meta_data( '_kiriof_checkout_destination_area_name', $destinasi_name );
            $order->update_meta_data( '_kiriof_checkout_expedition', $kiriof_filter_methods );
            $order->update_meta_data( '_kiriof_checkout_token', $kiriof_checkout_token_post );
            $order->update_meta_data( '_kiriof_checkout_billing_insurance', $kiriof_billing_insurance_post );
            $order->update_meta_data( '_kiriof_checkout_payment_method', $kiriof_payment_method_post );
            $order->update_meta_data( '_kiriof_checkout_force_insurance', $kiriof_force_insurance_post );
            $order->update_meta_data( '_kiriof_checkout_woocommerce_discount_amount', $woo_discount_amount );
            $order->update_meta_data( '_kiriof_checkout_woocommerce_discount_description', $woo_discount_description );
            $kiriof_checkout_postcode = '';
            if ( empty( $_POST['ship_to_different_address'] ) ) {
                $kiriof_checkout_postcode = isset( $_POST['billing_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_postcode'] ) ) : '';
            } else {
                $kiriof_checkout_postcode = isset( $_POST['shipping_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_postcode'] ) ) : '';
            }
            $kiriof_checkout_postcode = trim( preg_replace( '/\s+/', '', (string) $kiriof_checkout_postcode ) );
            if ( '' === $kiriof_checkout_postcode ) {
                $kiriof_checkout_postcode = sanitize_text_field( (string) WC()->session->get( 'kiriof_checkout_postcode', '' ) );
                $kiriof_checkout_postcode = trim( preg_replace( '/\s+/', '', (string) $kiriof_checkout_postcode ) );
            }
            if ( '' === $kiriof_checkout_postcode ) {
                $kiriof_checkout_postcode = $this->kiriof_extract_postcode_from_destination_name( $destinasi_name );
            }
            if ( '' !== $kiriof_checkout_postcode ) {
                $order->update_meta_data( '_kiriof_checkout_postcode', $kiriof_checkout_postcode );
                if ( empty( $_POST['ship_to_different_address'] ) ) {
                    $order->set_billing_postcode( $kiriof_checkout_postcode );
                    if ( '' === (string) $order->get_shipping_postcode() ) {
                        $order->set_shipping_postcode( $kiriof_checkout_postcode );
                    }
                } else {
                    $order->set_shipping_postcode( $kiriof_checkout_postcode );
                }
            }
            /** 
             * save to custom order metadata 
             * field kelurahan 
             **/
            $field_key = $this->field_destination_key;
            if ( isset($destination_area) && ! empty($destination_area) ) {
                $order->update_meta_data( '_' . $field_key, sanitize_text_field($destination_area) );
            }
            if( isset($insurance_post) && !empty($insurance_post) ){
                $order->update_meta_data( '_' . $this->field_insurance_key, sanitize_text_field($insurance_post) );
            }

    /**
             * save to custom order metadata 
             * field kelurahan 
             **/
            $field_key = $this->field_destination_key;
            if ( isset($destination_area) && ! empty($destination_area) ) {
                $order->update_meta_data( '_' . $field_key, sanitize_text_field($destination_area) );
            }
            if( isset($insurance_post) && !empty($insurance_post) ){
                $order->update_meta_data( '_' . $this->field_insurance_key, sanitize_text_field($insurance_post) );
            }
            //save meta subdistrict billing woocommerce
            if( isset($_POST['kiriof_destination_area']) && !empty($_POST['kiriof_destination_area']) ) $order->update_meta_data( '_billing_kiriof_destination_area', sanitize_text_field(wp_unslash($_POST['kiriof_destination_area'])) );
            if( isset($_POST['kiriof_destination_area_name']) && !empty($_POST['kiriof_destination_area_name']) ) $order->update_meta_data( '_billing_kiriof_destination_name', sanitize_text_field(wp_unslash($_POST['kiriof_destination_area_name'])) );
            //save meta Insurance billing woocommerce
            if( isset($data['kiriof_insurance']) && !empty($data['kiriof_insurance']) ) $order->update_meta_data( '_billing_kiriof_insurance', sanitize_text_field( ( wp_unslash($data['kiriof_insurance']) == true ) ? 'yes' : '' ) );
            
            //save meta subdistrict shipping woocommerce
            if( isset($_POST['kiriof_shipping_destination_area']) && !empty($_POST['kiriof_shipping_destination_area']) ) $order->update_meta_data( '_shipping_kiriof_destination_area', sanitize_text_field(wp_unslash($_POST['kiriof_shipping_destination_area'])) );
            if( isset($_POST['kiriof_shipping_destination_area_name']) && !empty($_POST['kiriof_shipping_destination_area_name']) ) $order->update_meta_data( '_shipping_kiriof_destination_name', sanitize_text_field(wp_unslash($_POST['kiriof_shipping_destination_area_name'])) );

            if ( is_user_logged_in() ) {
                $district_service = new \KiriminAjaOfficial\Services\CustomerDistrictService();
                if ( isset( $_POST['kiriof_destination_area'] ) ) {
                    $district_service->save(
                        get_current_user_id(),
                        'billing',
                        wp_unslash( $_POST['kiriof_destination_area'] ),
                        isset( $_POST['kiriof_destination_area_name'] ) ? wp_unslash( $_POST['kiriof_destination_area_name'] ) : ''
                    );
                }
                if ( ! empty( $_POST['ship_to_different_address'] ) ) {
                    $district_service->save(
                        get_current_user_id(),
                        'shipping',
                        isset( $_POST['kiriof_shipping_destination_area'] ) ? wp_unslash( $_POST['kiriof_shipping_destination_area'] ) : '',
                        isset( $_POST['kiriof_shipping_destination_area_name'] ) ? wp_unslash( $_POST['kiriof_shipping_destination_area_name'] ) : ''
                    );
                }
            }
            
            //save meta Insurance shipping woocommerce
            if( isset($data['kiriof_shipping_insurance']) && !empty($data['kiriof_shipping_insurance']) ) $order->update_meta_data( '_shipping_kiriof_insurance', sanitize_text_field( ( wp_unslash($data['kiriof_shipping_insurance']) == true ) ? 'yes' : '' ) );
            
            //flag order ppn
            $order->update_meta_data( '_kiriof_ppn', true );
        // end nonce check security
    }
    // phpcs:enable WordPress.Security.NonceVerification.Missing
    
    function getExpeditionOptionAjax(){
        try {
            // Verify nonce - fail early if missing or invalid
            if ( ! isset( $_POST['checkout_kiriminaja_nonce_field'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['checkout_kiriminaja_nonce_field'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'msg' => 'Security Check Nonce Checkout' ) );
                wp_die();
            }
            $service = (new \KiriminAjaOfficial\Services\CheckoutServices\OngkirPricingService([
                'destination_area_id'   => isset($_POST['data']['destination_area_id']) ? sanitize_text_field( wp_unslash($_POST['data']['destination_area_id'])) :'',
                'is_cod'                => ( isset($_POST['data']['payment_method']) ? sanitize_text_field( wp_unslash($_POST['data']['payment_method'])) : '' ) === 'cod',
                'wc_cart_contents'      => WC()->cart->cart_contents,
            ]))->call();
                
            wp_send_json_success($service);
        }catch (\Throwable $th){
            wp_send_json_success([
                'status'    => 400,
                'message'   => $th->getMessage(),
                'data'      => []
            ]);
        }
        
    }
    
    function getCheckoutCalculationAjax(){
        try {
            // Verify nonce - fail early if missing or invalid
            if ( ! isset( $_POST['checkout_kiriminaja_nonce_field'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['checkout_kiriminaja_nonce_field'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'msg' => 'Security Check Nonce Checkout' ) );
                wp_die();
            }
            $service = (new \KiriminAjaOfficial\Services\CheckoutServices\CheckoutCalculationService([
                'destination_area_id'   => isset($_POST['data']['destination_area_id']) ? sanitize_text_field( wp_unslash($_POST['data']['destination_area_id'] )) : '',
                'expedition'            => isset($_POST['data']['expedition']) ? sanitize_text_field( wp_unslash( $_POST['data']['expedition'] )) : '',
                'is_insurance'          => ( isset($_POST['data']['insurance']) ? sanitize_text_field( wp_unslash( $_POST['data']['insurance'] )) : '' ) === "true",
                'is_cod'                => ( isset($_POST['data']['payment_method']) ? sanitize_text_field( wp_unslash( $_POST['data']['payment_method'])) : '') === 'cod',
                'wc_cart_contents'      => WC()->cart->cart_contents,
            ]))->call();
            wp_send_json_success($service);
        }catch (\Throwable $th){
            wp_send_json_success([
                'status'    => 400,
                'message'   => $th->getMessage(),
                'data'      => []
            ]);
        }
        
    }
    function custom_content_thankyou( $order_id ) {
        $transaction = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransactionByWCOrderId($order_id);
        $paymentMethod = (new \KiriminAjaOfficial\Repositories\WpPostMetaRepository())->getRequiredRowsByPostIdAndMetaKey($order_id,'_payment_method_title');
        $locale = get_locale();
        
        echo wp_kses_post( '
        <section style="margin: 1rem 0 4rem 0" class="woocommerce-order-details">          
                <h2 class="woocommerce-order-details__title">Pembayaran</h2>            
                <table style="width: 100%; font-size: 1rem" class="woocommerce-table woocommerce-table--order-details shop_table order_details">            
                    <thead>
                        <tr>
                            <th class="" style="text-align: left">'.esc_html( __( 'Order Number', 'kiriminaja-official' ) ).'</th>
                            <th class="" style="text-align: right">'.esc_html($transaction->wp_wc_order_stat_order_id).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.esc_html(__( 'Date', 'kiriminaja-official' )).'</th>
                            <th class="" style="text-align: right">'.esc_html( wp_date('d F Y H:i',strtotime( $transaction->created_at ) ) ).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.esc_html( __( 'Payment Method', 'kiriminaja-official' ) ).'</th>
                            <th class="" style="text-align: right">'.esc_html( $paymentMethod->meta_value ).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.esc_html(__( 'Sub Total', 'kiriminaja-official' )).'</th>
                            <th class="" style="text-align: right">Rp.'.esc_html( kiriof_money_format( $transaction->transaction_value ) ).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.esc_html( __( 'Shipping Fee', 'kiriminaja-official' ) ).'</th>
                            <th class="" style="text-align: right">Rp.'.esc_html( kiriof_money_format(($transaction->shipping_cost ?? 0) + ($transaction->insurance_cost ?? 0) + ($transaction->cod_fee ?? 0)) ).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.esc_html( __( 'Payment Total', 'kiriminaja-official' ) ).'</th>
                            <th class="" style="text-align: right">Rp.'.esc_html( kiriof_money_format(($transaction->transaction_value ?? 0) + ($transaction->shipping_cost ?? 0) + ($transaction->insurance_cost ?? 0) + ($transaction->cod_fee ?? 0)) ).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.esc_html( __( 'Tracking', 'kiriminaja-official' ) ).'</th>
                            <th class="" style="text-align: right"><a href="'.esc_url( kiriof_get_tracking_page_url( array( 'order_id' => $transaction->wp_wc_order_stat_order_id ) ) ).'" target="_blank">CLICK</a></th>
                        </tr>
                    </thead>
                </table>            
            </section>
        ' );
        
    }
    public function kiriof_tag_fee_item_meta( $fee, $fee_key, $order, $coupons ) {
        $name = $fee->get_name();
        if ( false !== stripos( $name, 'COD Fee' ) || false !== stripos( $name, 'Biaya COD' ) ) {
            $fee->add_meta_data( '_kiriof_fee_type', 'cod_fee', true );
        } elseif ( false !== stripos( $name, 'Insurance' ) || false !== stripos( $name, 'Asuransi' ) ) {
            $fee->add_meta_data( '_kiriof_fee_type', 'insurance', true );
        }
    }

    public function kiriof_order_details($order){
        if ( ! $this->kiriof_order_needs_shipping( $order ) ) {
            return false;
        }
        $transactionKiriminaja = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransactionByWCOrderNumber($order->get_id());
        $shipping_methods = $order->get_shipping_methods();
        $shipping_method = array_shift( $shipping_methods );
        $shipping_method_id = $shipping_method ? $shipping_method['method_id'] : '';
        if( $shipping_method_id != 'kiriminaja-official' ){
            return false;
        }
        $html = '';
        $transaction_shipping_cost  = $transactionKiriminaja ? (float) $transactionKiriminaja->shipping_cost : 0;
        $transaction_insurance_cost = $transactionKiriminaja ? (float) $transactionKiriminaja->insurance_cost : 0;
        $transaction_cod_fee        = $transactionKiriminaja ? (float) $transactionKiriminaja->cod_fee : 0;
        $shipping_discount          = max( 0, $transaction_shipping_cost - (float) $order->get_shipping_total() );
        $transaction_discount_amount = $transactionKiriminaja ? (float) $transactionKiriminaja->discount_amount : 0;
        $coupon_service              = new \KiriminAjaOfficial\Services\ShippingDiscountCouponService();
        $coupon_scopes               = $coupon_service->splitCouponCodesByScope( (array) $order->get_coupon_codes() );
        $has_shipping_coupon         = ! empty( $coupon_scopes['shipping'] );
        $is_platform_discount        = $transaction_discount_amount > 0 && ! $has_shipping_coupon;

        if( $shipping_discount > 0 ){
            $discount_label = $is_platform_discount
                ? __( 'Shipping Discount (from KiriminAja)', 'kiriminaja-official' )
                : __( 'Shipping Discount', 'kiriminaja-official' );
            $html .= '
            <tr>
				<th scope="row">'.esc_html__('Actual Shipping','kiriminaja-official').':</th>
				<td class="wc-block-order-confirmation-totals__total">'.wc_price($transaction_shipping_cost).'</td>
			</tr>
            <tr>
				<th scope="row">'.esc_html($discount_label).':</th>
				<td class="wc-block-order-confirmation-totals__total">-'.wp_kses_post( wc_price($shipping_discount) ).'</td>
			</tr>';
        }

        if( ! $this->kiriof_order_has_fee_item($order, 'Insurance') && $transaction_insurance_cost > 0 ){
            $html .= '
            <tr>
				<th scope="row">'.esc_html__('Insurance','kiriminaja-official').':</th>
				<td class="wc-block-order-confirmation-totals__total">'.wc_price($transaction_insurance_cost).'</td>
			</tr>';
        }
        if( ! $this->kiriof_order_has_fee_item($order, 'COD Fee') && $order->get_payment_method() == 'cod' && $transaction_cod_fee > 0 ){
            $html .= '
            <tr>
				<th scope="row">
                    <label for="kiriof_cod_fee" style="display:block;margin:0;">'. esc_html__('COD Fee:','kiriminaja-official').'</label>		
                    <em style="font-size: 16px;font-weight: 300;">(incl. 11% VAT)</em>		
                </th>
				<td class="wc-block-order-confirmation-totals__total">'.wc_price($transaction_cod_fee).'</td>
			</tr>';
        }
        
        echo  wp_kses_post( $html );
    }

    public function kiriof_order_shipment_details($order){
        if ( ! $this->kiriof_order_needs_shipping( $order ) ) {
            return false;
        }
        $shipping_methods = $order->get_shipping_methods();
        $shipping_method = array_shift( $shipping_methods );
        $shipping_method_id = $shipping_method ? $shipping_method['method_id'] : '';
        if( $shipping_method_id != 'kiriminaja-official' ){
            return false;
        }

        $shipping_label = $order->get_shipping_method();
        $tracking_url = kiriof_get_tracking_page_url( array( 'order_id' => $order->get_id() ) );

        $html = '
            <section class="kiriof-order-shipment-details" style="margin:1.5rem 0 0;">
                <h2 class="woocommerce-order-details__title">'.esc_html__('Shipment','kiriminaja-official').'</h2>
                <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                    <tbody>
                        <tr>
                            <th scope="row">'.esc_html__('Shipping Method','kiriminaja-official').':</th>
                            <td>'.esc_html($shipping_label).'</td>
                        </tr>
                        <tr>
                            <th scope="row">'.esc_html__('Tracking','kiriminaja-official').':</th>
                            <td><a class="kj-button" href="'.esc_url( $tracking_url ).'">'.esc_html__('Track Shipment','kiriminaja-official').'</a></td>
                        </tr>
                    </tbody>
                </table>
            </section>';

        echo wp_kses_post( $html );
    }

    private function kiriof_order_has_fee_item($order, $feeName){
        $expected_meta = ( 'Insurance' === $feeName ) ? 'insurance' : 'cod_fee';

        foreach ($order->get_items('fee') as $feeItem) {
            if ( $feeItem->get_meta( '_kiriof_fee_type' ) === $expected_meta ) {
                return true;
            }

            $itemName = trim( (string) $feeItem->get_name() );
            if ( false !== stripos( $itemName, $feeName ) ) {
                return true;
            }
        }

        return false;
    }
    public function kiriof_shipping_rate_cache_invalidation( $packages ) {
        foreach ( $packages as &$package ) {
            $package['rate_cache'] = $this->kiriof_get_shipping_rate_cache_key( $package );
        }
    
        return $packages;
    }

    private function kiriof_get_shipping_rate_cache_key( $package ) {
        $destination_id = WC()->session ? (int) WC()->session->get( 'shipping_destination_id', 0 ) : 0;
        if ( ! $destination_id && WC()->session ) {
            $destination_id = (int) WC()->session->get( 'destination_id', 0 );
        }

        $cart_hash = '';
        if ( function_exists( 'WC' ) && WC() && isset( WC()->cart ) && WC()->cart && method_exists( WC()->cart, 'get_cart_hash' ) ) {
            $cart_hash = (string) WC()->cart->get_cart_hash();
        }

        $courier_filter = array();
        try {
            $courier_filter = ( new \KiriminAjaOfficial\Repositories\SettingRepository() )->getWhitelistExpeditionIds();
        } catch ( \Throwable $th ) {
            $courier_filter = array();
        }

        return md5(
            wp_json_encode(
                array(
                    'cart_hash'        => $cart_hash,
                    'destination'      => isset( $package['destination'] ) ? $package['destination'] : array(),
                    'destination_id'   => $destination_id,
                    'insurance'        => WC()->session ? (int) WC()->session->get( 'kiriof_insurance', 0 ) : 0,
                    'payment_method'   => $this->kiriof_get_checkout_payment_method(),
                    'coupon_context'   => $this->kiriof_get_cart_discount_context(),
                    'courier_filter'   => $courier_filter,
                )
            )
        );
    }
    public function kiriof_validateOrder($posted = array(), $errors = null){
        $packages = WC()->shipping->get_packages();
        
        // Verify nonce - fail early if missing or invalid
        if ( ! isset( $_POST['checkout_kiriminaja_nonce_field'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['checkout_kiriminaja_nonce_field'] ) ), KIRIOF_NONCE ) ) {
            return;
        }

        if ( ! $this->kiriof_cart_needs_shipping() ) {
            $this->kiriof_clear_logistics_session();
            return;
        }

            $this->kiriof_normalize_classic_destination_post_data();
            $kiriof_address_too_short = $this->kiriof_checkout_posted_address_is_too_short();
            if ( $kiriof_address_too_short ) {
                $this->kiriof_remove_shipping_method_required_errors( $errors );
                $this->kiriof_add_address_length_notice( $errors );
            }
        
            if( isset($_POST['billing_country']) ){
                
                if ($_POST['billing_country'] === "ID"){
                    if (empty($_POST['kiriof_destination_area'])) {
                        wc_add_notice( __( "<strong>District</strong> is a required field", 'kiriminaja-official' ), 'error' );
                    }
                    if (empty($_POST['shipping_method'][0]) && ! $kiriof_address_too_short) {
                        wc_add_notice( __( "<strong>Shipping</strong> is a required field", 'kiriminaja-official' ), 'error' );
                    }
                    if (empty($_POST['kiriof_checkout_token'])) {
                        wc_add_notice( __( "<strong>Checkout Calculation</strong> is not finished yet", 'kiriminaja-official' ), 'error' );
                    }
    
                }
            }
    
            $chosen_methods = WC()->session->get('chosen_shipping_methods');
            
            if( $chosen_methods != null ){
                $kiriof_filter_methods = substr($chosen_methods[0],0,10);//kiriminaja
            
                if ($kiriof_filter_methods == 'kiriminaja-official') {
                    foreach ($packages as $i => $package) {
                                
                        $weight = 0;
                        foreach ($package['contents'] as $item_id => $values) {
                            $_product = $values['data'];
        
                            if( empty( $_product->get_weight() ) ){
                                $weight = 0;
                            }else{
                                $weight = $_product->get_weight() * $values['quantity'];
                            }
    
                            if( $weight == 0 ){
                                /* translators: %s: product name */
                                $message = sprintf(__("Berat Produk %s Perlu di Setting", 'kiriminaja-official'), $_product->get_name());
                                $messageType = "error";
                                wc_add_notice($message, $messageType);
                            }
                        }
                        
                    }
        
                }
            }
    }
    public function kiriof_billing_fields($fields){
        if ( ! $this->kiriof_cart_needs_shipping() ) {
            $this->kiriof_clear_logistics_session();
            return $fields;
        }

        $fields_selected = array( 
            'city',
            'company', 
            'postcode', 
            'state'
        );
        /** Remove field Checkout */
        $fields = self::kiriof_remove_fields_checkout($fields,$fields_selected);
        /** Add field Subdistrict */
        $fields = self::kiriof_add_field_subdistrict( $fields );
        // Phone is required for courier pickup coordination
        $fields = self::kiriof_require_phone_fields( $fields );
    
        return $fields;
    }
    private static function kiriof_require_phone_fields( $fields ) {
        foreach ( array( 'billing', 'shipping' ) as $group ) {
            $key = $group . '_phone';
            if ( isset( $fields[ $group ][ $key ] ) ) {
                $fields[ $group ][ $key ]['required'] = true;
            }
        }
        return $fields;
    }

    /**
     * Force phone to required in the WooCommerce country locale data.
     * The block checkout reads locale rules to decide whether to append " (optional)"
     * to the label — setting required:true here removes that suffix globally.
     *
     * @param array $locales
     * @return array
     */
    public function kiriof_require_phone_locale( $locales ) {
        foreach ( $locales as $country => $fields ) {
            $locales[ $country ]['phone']['required'] = true;
            $locales[ $country ]['phone']['hidden']   = false;
        }
        // Also set the 'default' locale used as fallback.
        if ( isset( $locales['default'] ) ) {
            $locales['default']['phone']['required'] = true;
            $locales['default']['phone']['hidden']   = false;
        }
        return $locales;
    }

    /**
     * Block checkout renders "(optional)" via JavaScript after the label text.
     * Inject a tiny inline script on the checkout page that strips any remaining
     * "(optional)" suffix from the phone label via a MutationObserver, as a
     * belt-and-suspenders fallback for themes that bypass the locale filter.
     */
    public function kiriof_block_checkout_require_phone_label() {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return;
        }
        ?>
        <script>
        (function() {
            var kiriofStripPhoneOptional = function() {
                document.querySelectorAll(
                    'label[for*="phone"], .wc-block-components-text-input label, .wc-block-components-address-form label'
                ).forEach(function(label) {
                    if (/phone/i.test(label.htmlFor || label.getAttribute('for') || '')) {
                        label.childNodes.forEach(function(node) {
                            if (node.nodeType === 3) { // Text node
                                node.textContent = node.textContent.replace(/\s*\(optional\)/i, '');
                            }
                        });
                        // Also handle span children used by some block themes.
                        label.querySelectorAll('span').forEach(function(span) {
                            if (/optional/i.test(span.textContent)) {
                                span.remove();
                            }
                        });
                    }
                });
            };
            // Run once on load and observe DOM changes from React re-renders.
            kiriofStripPhoneOptional();
            var observer = new MutationObserver(kiriofStripPhoneOptional);
            observer.observe(document.body, { childList: true, subtree: true });
        })();
        </script>
        <?php
    }
    private function kiriof_remove_fields_checkout($fields,$fields_selected){
        foreach ($fields_selected as $field_key) {
            unset( $fields['billing']['billing_'.$field_key] );
            unset( $fields['shipping']['shipping_'.$field_key] );
        }
        return $fields;
    }
    private function kiriof_add_field_subdistrict( $fields ){
        $field_key = $this->field_destination_key;
        $district_service = new \KiriminAjaOfficial\Services\CustomerDistrictService();
        $customer = isset( WC()->customer ) && WC()->customer instanceof \WC_Customer ? WC()->customer : get_current_user_id();
        $saved_billing = $district_service->get( $customer, 'billing' );
        $saved_shipping = $district_service->get( $customer, 'shipping' );
        //billing session
        $destination_id     = WC()->session->get('destination_id') ?: $saved_billing['id'];
        $destination_name   = WC()->session->get('destination_name') ?: $saved_billing['name'];
        $options = array( '' => esc_html__( 'Select Option', 'kiriminaja-official' ) );
        if ( ! empty( $destination_id ) ) {
            $options[ $destination_id ] = $destination_name;
        }

        //shipping session
        $shipping_dest_id   = WC()->session->get('shipping_destination_id') ?: $saved_shipping['id'];
        $shipping_dest_name = WC()->session->get('shipping_destination_name') ?: $saved_shipping['name'];
        $shipping_options   = array( '' => esc_html__( 'Select Option', 'kiriminaja-official' ) );
        if ( ! empty( $shipping_dest_id ) ) {
            $shipping_options[ $shipping_dest_id ] = $shipping_dest_name;
        }

        //add field billing District
        $fields['billing'][$field_key] = array(
            'label'     => esc_html__('District', 'kiriminaja-official'),
            'required'  => true,
            'class'     => array('form-row-wide'),
            'clear'     => true,
            'type'      => 'select',
            'priority'  => 61,
            'options'   => $options,
            'default'   => $destination_id,
        );
        //add field shipping District
        $fields['shipping'][$this->field_shipping_destination_key] = array(
            'label'     => esc_html__('District', 'kiriminaja-official'),
            'required'  => true,
            'class'     => array('form-row-wide'),
            'clear'     => true,
            'type'      => 'select',
            'priority'  => 61,
            'options'   => ! empty( $shipping_dest_id ) ? $shipping_options : $options,
            'default'   => ! empty( $shipping_dest_id ) ? $shipping_dest_id : $destination_id,
        );
        return $fields;
    }
    public function kiriof_render_classic_insurance_field(){
        if ( $this->kiriof_classic_insurance_printed || ! $this->kiriof_cart_needs_shipping() ) {
            return;
        }
        $this->kiriof_classic_insurance_printed = true;

        $insurance_setting = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('enable_insurance');
        $force_insurance   = ( $insurance_setting && 'yes' === $insurance_setting->value );
        $checked           = $force_insurance || (int) WC()->session->get( 'kiriof_insurance', 0 ) === 1;

        echo '<h3 id="kiriof-classic-insurance-field" class="kiriof-classic-insurance-field">';
        if ( $force_insurance ) {
            echo '<input type="hidden" name="' . esc_attr( $this->field_insurance_key ) . '" value="1">';
        }
        ?>
		<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
			<input
				id="<?php echo esc_attr( $this->field_insurance_key ); ?>"
				class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
				<?php checked( $checked, true ); ?>
				type="checkbox"
				name="<?php echo esc_attr( $this->field_insurance_key ); ?>"
				value="1"
				<?php disabled( $force_insurance, true ); ?>
			/>
			<span><?php esc_html_e( 'Add shipping insurance', 'kiriminaja-official' ); ?></span>
		</label>
		<?php
        echo '</h3>';
    }
    public function kiriof_beforeCheckoutForm(){
        WC()->session->set( 'chosen_shipping_methods', null );
    }
    /**
     * Get the chosen shipping method. 
     * Fixing Intermiten Issue Shipping Method not checked
     *
     * @param string $method Chosen shipping method.
     * @param array $available_methods Available shipping methods.
     * @return string
     */
    // phpcs:disable WordPress.Security.NonceVerification.Missing -- Read-only checkout method selection during WooCommerce checkout request.
    public function kiriof_shipping_chosen_method($method, $available_methods) {
        // Classic checkout: shipping_method[0] is POSTed explicitly.
        if (isset($_POST['shipping_method'][0]) && array_key_exists( sanitize_text_field( wp_unslash( $_POST['shipping_method'][0] )), $available_methods)) {
            $posted_method = sanitize_text_field( wp_unslash($_POST['shipping_method'][0]));
            WC()->session->set( 'kiriof_chosen_shipping_methods', array( $posted_method ) );
            WC()->session->set( 'chosen_shipping_methods', array( $posted_method ) );
            return $posted_method;
        }

        // Plugin AJAX fee updates post shipping_metode_id, not Woo's classic
        // shipping_method[0]. During the same request Woo recalculates totals and
        // may pass the previous/default method as $method; keep the just-posted
        // courier as the source of truth so the Store API cart GET reflects it.
        if ( isset( $_POST['shipping_metode_id'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The AJAX handler validates kiriof-update-checkout before cart totals are calculated.
            $posted_kiriof_method = sanitize_text_field( wp_unslash( $_POST['shipping_metode_id'] ) );
            if ( array_key_exists( $posted_kiriof_method, $available_methods ) ) {
                WC()->session->set( 'kiriof_chosen_shipping_methods', array( $posted_kiriof_method ) );
                WC()->session->set( 'chosen_shipping_methods', array( $posted_kiriof_method ) );
                return $posted_kiriof_method;
            }
        }

        // Block checkout select-shipping-rate requests carry the buyer-selected
        // rate_id in the Store API JSON body. Prefer it over the stale logged-in
        // session method Woo may pass as $method while the cart is recalculating.
        $store_api_method = $this->kiriof_get_store_api_selected_shipping_rate();
        if ( '' !== $store_api_method && array_key_exists( $store_api_method, $available_methods ) ) {
            WC()->session->set( 'kiriof_chosen_shipping_methods', array( $store_api_method ) );
            WC()->session->set( 'chosen_shipping_methods', array( $store_api_method ) );
            return $store_api_method;
        }

        $kiriof_chosen_methods = WC()->session->get( 'kiriof_chosen_shipping_methods', array() );
        if (
            is_array( $kiriof_chosen_methods )
            && ! empty( $kiriof_chosen_methods[0] )
            && array_key_exists( $kiriof_chosen_methods[0], $available_methods )
        ) {
            WC()->session->set( 'chosen_shipping_methods', array( $kiriof_chosen_methods[0] ) );
            return $kiriof_chosen_methods[0];
        }

        // Block checkout / Store API: WooCommerce passes the buyer-selected rate as
        // $method. Trust it and update the session so the cart reflects the real
        // selection instead of our stale session cache.
        if ( '' !== (string) $method && array_key_exists( (string) $method, $available_methods ) ) {
            WC()->session->set( 'kiriof_chosen_shipping_methods', array( (string) $method ) );
            WC()->session->set( 'chosen_shipping_methods', array( (string) $method ) );
            return $method;
        }

        return $method;
    }
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    /**
     * Control COD payment gateway availability based on the KiriminAja
     * Config tab settings: "Enable Cash on Delivery" and "Enable for
     * KiriminAja methods" (which populates enable_for_methods with
     * the wildcard kiriminaja-official).
     *
     * @param array $gateways WooCommerce available payment gateways.
     * @return array
     */
    public function kiriof_filter_cod_availability($gateways) {
        if ( ! $this->kiriof_cart_needs_shipping() ) {
            $this->kiriof_clear_logistics_session();
            if ( isset( $gateways['cod'] ) ) {
                unset( $gateways['cod'] );
            }
            return $gateways;
        }

        // Only affect the checkout page after virtual carts have removed COD.
        if (!is_checkout()) {
            return $gateways;
        }

        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        if (empty($chosen_methods) || !is_array($chosen_methods)) {
            return $gateways;
        }

        $is_kiriminaja_shipping = false;
        foreach ($chosen_methods as $method) {
            if (strpos($method, 'kiriminaja-official') === 0) {
                $is_kiriminaja_shipping = true;
                break;
            }
        }

        if (!$is_kiriminaja_shipping) {
            return $gateways;
        }

        $selected_rate_supports_cod = $this->kiriof_selected_shipping_rate_supports_cod( $chosen_methods );
        if ( false === $selected_rate_supports_cod ) {
            if ( isset( $gateways['cod'] ) ) {
                unset( $gateways['cod'] );
            }

            if ( WC()->session && 'cod' === WC()->session->get( 'chosen_payment_method' ) ) {
                WC()->session->set( 'chosen_payment_method', '' );
            }
            if ( WC()->session && 'cod' === WC()->session->get( 'payment_method' ) ) {
                WC()->session->set( 'payment_method', '' );
            }
            if ( WC()->session && 'cod' === WC()->session->get( 'kiriof_payment_method' ) ) {
                WC()->session->set( 'kiriof_payment_method', '' );
            }

            return $gateways;
        }

        $enable_cod_setting = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('enable_cod');
        $enable_cod = $enable_cod_setting ? $enable_cod_setting->value : 'yes';

        $cod_settings = get_option('woocommerce_cod_settings', array());
        $enable_for_methods = isset($cod_settings['enable_for_methods']) ? $cod_settings['enable_for_methods'] : array();
        if (!is_array($enable_for_methods)) {
            $enable_for_methods = array();
        }

        $cod_gateway_enabled = isset($cod_settings['enabled']) && 'yes' === $cod_settings['enabled'];
        $has_kiriminaja_wildcard = in_array('kiriminaja-official', $enable_for_methods, true);

        if ($enable_cod !== 'yes') {
            if (isset($gateways['cod'])) {
                unset($gateways['cod']);
            }
            return $gateways;
        }

        if ($cod_gateway_enabled && $has_kiriminaja_wildcard && !isset($gateways['cod'])) {
            $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
            if (isset($available_gateways['cod'])) {
                $gateways['cod'] = $available_gateways['cod'];
            }
        }

        return $gateways;
    }

    private function kiriof_selected_shipping_rate_supports_cod( $chosen_methods ) {
        if ( ! is_array( $chosen_methods ) || empty( $chosen_methods ) ) {
            return null;
        }

        $chosen_rate_ids = array();
        foreach ( $chosen_methods as $method ) {
            $method = (string) $method;
            if ( 0 === strpos( $method, 'kiriminaja-official' ) ) {
                $chosen_rate_ids[] = $method;
            }
        }

        if ( empty( $chosen_rate_ids ) ) {
            return null;
        }

        $session_rate_meta = WC()->session ? (array) WC()->session->get( 'kiriof_shipping_coupon_rate_meta', array() ) : array();
        foreach ( $chosen_rate_ids as $rate_id ) {
            if ( isset( $session_rate_meta[ $rate_id ]['cod_available'] ) ) {
                return 'yes' === (string) $session_rate_meta[ $rate_id ]['cod_available'];
            }
        }

        if ( ! function_exists( 'WC' ) || ! WC() || ! method_exists( WC(), 'shipping' ) ) {
            return null;
        }

        $shipping = WC()->shipping();
        if ( ! $shipping || ! method_exists( $shipping, 'get_packages' ) ) {
            return null;
        }

        $packages = $shipping->get_packages();
        if ( ! is_array( $packages ) ) {
            return null;
        }

        foreach ( $packages as $package ) {
            $rates = isset( $package['rates'] ) && is_array( $package['rates'] ) ? $package['rates'] : array();
            foreach ( $rates as $rate ) {
                if ( ! $rate instanceof \WC_Shipping_Rate ) {
                    continue;
                }

                if ( ! in_array( $rate->get_id(), $chosen_rate_ids, true ) ) {
                    continue;
                }

                $cod_available = $rate->get_meta_data()['kiriof_rate_cod_available'] ?? $rate->get_meta( 'kiriof_rate_cod_available' );
                if ( '' === (string) $cod_available ) {
                    return null;
                }

                return 'yes' === (string) $cod_available;
            }
        }

        return null;
    }

    public function kiriof_render_block_checkout_shipping_discount_row( $block_content, $block ) {
        unset( $block );
        return $block_content;
    }

    /**
     * Register District field for block checkout (ShopVerse etc.).
     * woocommerce_checkout_fields doesn't work with the checkout block —
     * fields must be explicitly registered via the Blocks API.
     */
    public function kiriof_register_block_checkout_fields() {
        $register_fn = null;
        $is_v2 = false;
        if ( function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
            $register_fn = 'woocommerce_register_additional_checkout_field';
            $is_v2 = true;
        } elseif ( function_exists( 'woocommerce_blocks_register_checkout_field' ) ) {
            $register_fn = 'woocommerce_blocks_register_checkout_field';
        } else {
            return;
        }

        if ( ! $this->kiriof_cart_needs_shipping() ) {
            $this->kiriof_clear_logistics_session();
            return;
        }

        $options = array(
            array( 'value' => '', 'label' => __( 'Select Option', 'kiriminaja-official' ) ),
        );
        if ( WC()->session ) {
            $destination_id   = WC()->session->get( 'destination_id', '' );
            $destination_name = WC()->session->get( 'destination_name', '' );
            if ( ! empty( $destination_id ) && ! empty( $destination_name ) ) {
                $options[] = array( 'value' => $destination_id, 'label' => $destination_name );
            }
        }

        if ( $is_v2 ) {
            // WooCommerce's Store API turns select options into a fixed enum.
            // District values are loaded dynamically after the buyer enters a postcode,
            // so registering this as a select causes checkout validation errors such as:
            // "Invalid kiriminaja-official/kiriof_destination_area provided." Register as
            // text for the schema, then the frontend swaps the input to a dynamic select.
            $register_fn( array(
                'id'           => 'kiriminaja-official/' . $this->field_destination_key,
                'label'        => __( 'District', 'kiriminaja-official' ),
                'location'     => 'address',
                'type'         => 'text',
                'required'     => false,
                'address_type' => array( 'shipping' ),
            ));
        } else {
            $register_fn(
                array(
                    'id'       => 'kiriminaja-official/' . $this->field_destination_key,
                    'label'    => __( 'District', 'kiriminaja-official' ),
                    'location' => 'address',
                    'type'     => 'select',
                    'required' => false,
                    'options'  => $options,
                ),
                array( 'address_type' => array( 'shipping' ) )
            );
        }
    }

    /**
     * Register a Store API cart/extensions callback for block checkout.
     *
     * React/block checkout does not use classic update_checkout fragments.
     * Calling extensionCartUpdate hits this callback, stores the selected
     * destination/payment/insurance choices in the WC session, and WooCommerce
     * recalculates the cart so Insurance and COD Fee render as native fees.
     */
    public function kiriof_register_store_api_update_callback() {
        if ( ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
            return;
        }

        woocommerce_store_api_register_update_callback( array(
            'namespace' => 'kiriminaja-official',
            'callback'  => array( $this, 'kiriof_store_api_update_checkout' ),
        ) );
    }

    /**
     * Store API callback for block checkout fee updates.
     *
     * @param array $data Data passed from extensionCartUpdate.
     */
    public function kiriof_store_api_update_checkout( $data ) {
        if ( ! is_array( $data ) ) {
            return;
        }
        if ( ! $this->kiriof_cart_needs_shipping() ) {
            $this->kiriof_clear_logistics_session();
            return;
        }

        $shipping_method  = isset( $data['shipping_metode_id'] ) ? sanitize_text_field( wp_unslash( $data['shipping_metode_id'] ) ) : '';
        $destination_id   = isset( $data['destination_id'] ) ? (int) $data['destination_id'] : 0;
        $destination_name = isset( $data['destination_name'] ) ? sanitize_text_field( wp_unslash( $data['destination_name'] ) ) : '';
        $payment_method   = isset( $data['payment_method'] ) ? sanitize_text_field( wp_unslash( $data['payment_method'] ) ) : '';
        $insurance        = ! empty( $data['insurance'] ) ? 1 : 0;
        $force_insurance  = ! empty( $data['force_insurance'] ) ? 1 : 0;
        $postcode         = isset( $data['postcode'] ) ? sanitize_text_field( wp_unslash( $data['postcode'] ) ) : '';
        $postcode         = trim( preg_replace( '/\s+/', '', (string) $postcode ) );

        if ( 'cod' === $payment_method ) {
            WC()->session->set( 'chosen_payment_method', $payment_method );
            WC()->session->set( 'payment_method', $payment_method );
            WC()->session->set( 'kiriof_payment_method', $payment_method );
        } else {
            WC()->session->set( 'chosen_payment_method', $payment_method );
            WC()->session->set( 'payment_method', $payment_method );
            WC()->session->set( 'kiriof_payment_method', '' );
        }

        if ( '' !== $shipping_method ) {
            WC()->session->set( 'kiriof_chosen_shipping_methods', array( $shipping_method ) );
            WC()->session->set( 'chosen_shipping_methods', array( $shipping_method ) );
            WC()->session->set( 'kiriof_expedition', $this->kiriof_extract_expedition_from_method( $shipping_method ) );
        } elseif ( $destination_id <= 0 ) {
            WC()->session->set( 'kiriof_chosen_shipping_methods', array() );
            WC()->session->set( 'chosen_shipping_methods', array() );
            WC()->session->set( 'kiriof_expedition', '' );
        }

        if ( $destination_id > 0 ) {
            WC()->session->set( 'destination_id', $destination_id );
            WC()->session->set( 'shipping_destination_id', $destination_id );
            WC()->session->set( 'kiriof_destination_area', $destination_id );
        } else {
            WC()->session->set( 'destination_id', '' );
            WC()->session->set( 'shipping_destination_id', '' );
            WC()->session->set( 'kiriof_destination_area', '' );
        }
        if ( '' !== $destination_name ) {
            WC()->session->set( 'destination_name', $destination_name );
            WC()->session->set( 'shipping_destination_name', $destination_name );
            WC()->session->set( 'kiriof_destination_area_name', $destination_name );
        } else {
            WC()->session->set( 'destination_name', '' );
            WC()->session->set( 'shipping_destination_name', '' );
            WC()->session->set( 'kiriof_destination_area_name', '' );
        }

        // Persist the district in customer additional fields so the Store API
        // response reflects the selected district.  The Store API builds the
        // shipping_address / billing_address additional_fields from customer
        // meta, NOT from WC()->session, so session-only storage leaves the
        // field empty in the cart response and can cause empty shipping rates.
        if ( isset( WC()->customer ) && is_object( WC()->customer ) ) {
            ( new \KiriminAjaOfficial\Services\CustomerDistrictService() )->save(
                WC()->customer,
                'shipping',
                $destination_id,
                $destination_name
            );
            ( new \KiriminAjaOfficial\Services\CustomerDistrictService() )->save(
                WC()->customer,
                'billing',
                $destination_id,
                $destination_name
            );
        }

        WC()->session->set( 'kiriof_insurance', $insurance );
        WC()->session->set( 'billing_insurance', $insurance );
        WC()->session->set( 'force_insurance', $force_insurance );
        WC()->session->set( 'kiriof_force_insurance', $force_insurance );
        WC()->session->set( 'kiriof_cached_insurance_amt', 0 );
        WC()->session->set( 'kiriof_cached_cod_amt', 0 );
        WC()->session->set( 'kiriof_cached_fee_context', array() );
        WC()->session->set( 'kiriof_shipping_coupon_rate_meta', array() );
        WC()->session->set( 'kiriof_checkout_postcode', $postcode );

        if ( $destination_id > 0 && '' !== $postcode ) {
            $saved_destination_map = (array) WC()->session->get( 'kiriof_destination_postcode_map', array() );
            $saved_destination_map[ $postcode ] = array(
                'destination_id'   => $destination_id,
                'destination_name' => $destination_name,
            );
            WC()->session->set( 'kiriof_destination_postcode_map', $saved_destination_map );
        }

        if (
            ( $destination_id > 0 || '' !== $shipping_method || '' !== $payment_method )
            && isset( WC()->cart )
            && is_object( WC()->cart )
            && method_exists( WC()->cart, 'calculate_totals' )
        ) {
            WC()->cart->calculate_totals();
        }
    }

    /**
     * AJAX handler for saving checkout session data (destination, insurance, payment).
     * Used as fallback when extensionCartUpdate is unavailable in block checkout.
     */
    public function kiriof_ajax_session_save() {
        try {
            check_ajax_referer( KIRIOF_NONCE, 'nonce' );

            $raw  = isset( $_POST['data'] ) ? sanitize_textarea_field( wp_unslash( $_POST['data'] ) ) : '';
            $data = json_decode( $raw, true );
            if ( ! is_array( $data ) ) {
                wp_send_json_error( array( 'msg' => 'Invalid data' ) );
                wp_die();
            }

            if ( ! $this->kiriof_cart_needs_shipping() ) {
                $this->kiriof_clear_logistics_session();
                wp_send_json_success( array( 'destination_id' => 0 ) );
                wp_die();
            }

            $shipping_method  = isset( $data['shipping_metode_id'] ) ? sanitize_text_field( $data['shipping_metode_id'] ) : '';
            $destination_id   = isset( $data['destination_id'] ) ? (int) $data['destination_id'] : 0;
            $destination_name = isset( $data['destination_name'] ) ? sanitize_text_field( $data['destination_name'] ) : '';
            $payment_method   = isset( $data['payment_method'] ) ? sanitize_text_field( $data['payment_method'] ) : '';
            $insurance        = ! empty( $data['insurance'] ) ? 1 : 0;
            $force_insurance  = ! empty( $data['force_insurance'] ) ? 1 : 0;
            $postcode         = isset( $data['postcode'] ) ? sanitize_text_field( $data['postcode'] ) : '';
            $postcode         = trim( preg_replace( '/\s+/', '', (string) $postcode ) );

            if ( 'cod' === $payment_method ) {
                WC()->session->set( 'chosen_payment_method', $payment_method );
                WC()->session->set( 'payment_method', $payment_method );
                WC()->session->set( 'kiriof_payment_method', $payment_method );
            } else {
                WC()->session->set( 'chosen_payment_method', $payment_method );
                WC()->session->set( 'payment_method', $payment_method );
                WC()->session->set( 'kiriof_payment_method', '' );
            }

            if ( '' !== $shipping_method ) {
                WC()->session->set( 'kiriof_chosen_shipping_methods', array( $shipping_method ) );
                WC()->session->set( 'chosen_shipping_methods', array( $shipping_method ) );
            }

            if ( $destination_id > 0 ) {
                WC()->session->set( 'destination_id', $destination_id );
                WC()->session->set( 'shipping_destination_id', $destination_id );
                WC()->session->set( 'kiriof_destination_area', $destination_id );
                WC()->session->set( 'destination_name', $destination_name );
                WC()->session->set( 'shipping_destination_name', $destination_name );
                WC()->session->set( 'kiriof_destination_area_name', $destination_name );
            }

            WC()->session->set( 'kiriof_insurance', $insurance );
            WC()->session->set( 'billing_insurance', $insurance );
            WC()->session->set( 'force_insurance', $force_insurance );
            WC()->session->set( 'kiriof_force_insurance', $force_insurance );
            WC()->session->set( 'kiriof_cached_insurance_amt', 0 );
            WC()->session->set( 'kiriof_cached_cod_amt', 0 );
            WC()->session->set( 'kiriof_cached_fee_context', array() );
            WC()->session->set( 'kiriof_shipping_coupon_rate_meta', array() );
            WC()->session->set( 'kiriof_checkout_postcode', $postcode );

            if ( $destination_id > 0 && '' !== $postcode ) {
                $saved_destination_map = (array) WC()->session->get( 'kiriof_destination_postcode_map', array() );
                $saved_destination_map[ $postcode ] = array(
                    'destination_id'   => $destination_id,
                    'destination_name' => $destination_name,
                );
                WC()->session->set( 'kiriof_destination_postcode_map', $saved_destination_map );
            }

            wp_send_json_success( array( 'destination_id' => $destination_id ) );
        } catch ( \Throwable $th ) {
            wp_send_json_error( array( 'msg' => $th->getMessage() ) );
        }
        wp_die();
    }
}
