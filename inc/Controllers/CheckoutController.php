<?php
namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CheckoutController
{
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
    public function register()
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            //before total order checkout
            add_action('woocommerce_review_order_before_order_total',array($this,'kiriof_reviewOrderBeforeTotalOrder'), 9999);
            /** Add Custom field Checkout Sub District */
            add_action('woocommerce_after_checkout_billing_form', array($this, 'add_custom_select_options_field_and_script'), 9999);
            add_action('wp_footer', array($this, 'add_custom_select_options_field_and_script'));

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
            /** end After Checkout */
            /** Expedition Ajax*/
            add_action('wp_ajax_kiriof-get-expedition-ajax', array($this,'getExpeditionOptionAjax'));
            add_action('wp_ajax_nopriv_kiriof-get-expedition-ajax', array($this,'getExpeditionOptionAjax'));
                        
            /** Custom Page Woocommerce Thankyou */
            add_action( 'woocommerce_order_details_after_order_table_items', array($this,'kiriof_order_details') );
            
            /** remove Cache Shipping triger update_checkout */
            add_filter( 'woocommerce_cart_shipping_packages', array($this,'kiriof_shipping_rate_cache_invalidation'), 100 );
            /** Validate Shipping Kirimin aja */
            add_action('woocommerce_review_order_before_cart_contents', array($this,'kiriof_validateOrder'), 10);
            add_action('woocommerce_after_checkout_validation', array($this,'kiriof_validateOrder'), 10);
            
            /**
             * Remove Billing and shipping Fields
             */
            add_filter('woocommerce_checkout_fields', array($this,'kiriof_billing_fields'), 9999);            
            add_filter('woocommerce_shipping_chosen_method', array($this,'kiriof_shipping_chosen_method'), 10, 2);
            
            add_filter( 'woocommerce_cart_needs_shipping', array($this,'kiriof_filter_cart_needs_shipping'));
            
            add_action('woocommerce_checkout_before_customer_details', array($this,'kiriof_add_checkout_nonce_field' ) );
            
            add_action( 'woocommerce_cart_calculate_fees', array($this,'kiriof_shipping_method_update') );

            /** Control COD availability based on KiriminAja Config tab */
            add_filter( 'woocommerce_available_payment_gateways', array($this,'kiriof_filter_cod_availability'), 10, 1 );
        }
    }
    function kiriof_shipping_method_update() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce cart calculation, nonce handled by WC
        if ( isset( $_POST['shipping_method'] ) && is_array( $_POST['shipping_method'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce cart calculation, nonce handled by WC
            $shipping_methods = array_map( 'sanitize_text_field', wp_unslash( $_POST['shipping_method'] ) );
            WC()->session->set( 'chosen_shipping_methods', $shipping_methods );
        }

        // Add insurance + COD as WC cart fees (works on traditional AND block checkout)
        $this->kiriof_add_checkout_fees();
    }

    private function kiriof_add_checkout_fees() {
        // Classic checkout already renders AJAX-updated placeholder rows from
        // kiriof_reviewOrderBeforeTotalOrder(). Adding native WC fees there makes
        // Insurance/COD Fee appear twice. Native cart fees are only needed for
        // React/block checkout/Store API requests, which do not use classic order-review fragments.
        if ( ! $this->kiriof_should_use_native_checkout_fees() ) {
            return;
        }

        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
        if ( empty( $chosen_methods ) || ! is_array( $chosen_methods ) ) {
            return;
        }

        // Check if a KiriminAja shipping method is selected
        $is_kiriminaja = false;
        foreach ( $chosen_methods as $method ) {
            if ( strpos( $method, 'kiriminaja-official' ) === 0 ) {
                $is_kiriminaja = true;
                break;
            }
        }
        if ( ! $is_kiriminaja ) {
            return;
        }

        // Read cached fees from session (populated by kiriof_getDataAfterUpdateCheckout AJAX)
        $insurance_amt = (float) WC()->session->get( 'kiriof_cached_insurance_amt', 0 );
        $cod_amt       = (float) WC()->session->get( 'kiriof_cached_cod_amt', 0 );

        // Fallback: if session cache is empty (e.g., first load on block checkout
        // where the AJAX hasn't fired yet), calculate fees directly.
        if ( $insurance_amt <= 0 && $cod_amt <= 0 ) {
            // Find the exact KiriminAja method + courier
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

            $destination_id = (int) WC()->session->get( 'destination_id', 0 );
            if ( ! $destination_id ) {
                // Try to get from customer shipping address (works on block checkout too)
                $customer = WC()->customer;
                if ( $customer ) {
                    $destination_id = (int) WC()->session->get( 'shipping_destination_id',
                        (int) WC()->session->get( 'destination_id', 0 )
                    );
                }
            }
            if ( ! $destination_id ) {
                return;
            }

            $insurance_setting = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('enable_insurance');
            $force_insurance   = ( $insurance_setting && 'yes' === $insurance_setting->value );
            $chosen_payment    = WC()->session->get( 'chosen_payment_method', '' );

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
                    $cod_amt       = (float) ( $result['cod_amt'] ?? 0 );

                    // Cache for subsequent calls
                    WC()->session->set( 'kiriof_cached_insurance_amt', $insurance_amt );
                    WC()->session->set( 'kiriof_cached_cod_amt', $cod_amt );
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

        if ( $cod_amt > 0 ) {
            WC()->cart->add_fee(
                __( 'COD Fee', 'kiriminaja-official' ),
                $cod_amt,
                false
            );
        }
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

    private function kiriof_should_use_native_checkout_fees() {
        return $this->kiriof_is_block_checkout_request() || $this->kiriof_is_store_api_request();
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

    private function kiriof_is_block_checkout_request() {
        if ( ! function_exists( 'has_block' ) ) {
            return false;
        }

        if ( is_checkout() ) {
            $checkout_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'checkout' ) : 0;
            if ( $checkout_page_id > 0 && has_block( 'woocommerce/checkout', $checkout_page_id ) ) {
                return true;
            }
        }

        if ( is_cart() ) {
            $cart_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'cart' ) : 0;
            if ( $cart_page_id > 0 && has_block( 'woocommerce/cart', $cart_page_id ) ) {
                return true;
            }
        }

        return false;
    }

    function kiriof_filter_cart_needs_shipping( $needs_shipping ) {
        if ( is_cart() ) {
            if( $needs_shipping && get_option( 'woocommerce_enable_shipping_calc' ) === 'no' ){
                WC()->session->set( 'destination_id', null );
            }
        }
        return $needs_shipping;
    }
    function kiriof_reviewOrderBeforeTotalOrder(){
        if( !is_checkout() ){
            return false;
        }

        // Keep the traditional checkout placeholder rows from main branch.
        // The AJAX flow updates these rows immediately after the buyer changes
        // courier/payment/insurance. WC cart fees are also added separately for
        // block checkout compatibility, but classic themes still rely on these
        // rows for the live COD Fee/Insurance display before order submission.
        $table = '<tr class="kiriof_cart_item_insurane" style="display:none;">
			<td class="kj-cart-insurance">
				<label for="kiriof_cart_insurance">'.__('Insurance','kiriminaja-official').'</label>											
            </td>
			<td class="kj-cart-insurance kj-cost-insurance"></td>
		</tr>
        <tr class="kiriof_cart_item_cod_fee" style="display:none;">
			<td class="kj-cod-fee">
				<label for="kiriof_cod_fee" style="display:block;margin:0;">'. __('COD Fee','kiriminaja-official').'</label>		
                <em style="font-size: 16px;font-weight: 300;">(incl. 11% VAT)</em>									
            </td>
			<td class="kj-cod-fee kj-cost-codfee"></td>
		</tr>';
        echo wp_kses_post( $table );
    }
    function kiriof_add_checkout_nonce_field(){
        wp_nonce_field(KIRIOF_NONCE, 'checkout_kiriminaja_nonce_field');
    }
    function add_custom_select_options_field_and_script($checkout)
    {        
        $field_key = $this->field_destination_key;
        $destination_id = WC()->session->get($this->key_destination_id);
        $destination_name = WC()->session->get($this->key_destination_name);
        $shipping_destination_id = WC()->session->get($this->key_shipping_destination_id);
        $shipping_destination_name = WC()->session->get($this->key_shipping_destination_name);
        
        $insurance_setting        = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('enable_insurance');
        $kiriof_global_insurance   = ( $insurance_setting && 'yes' === $insurance_setting->value );
        $kiriof_checkout_token     = empty($destination_id) ? false : true;
        require_once (plugin_dir_path(dirname(__FILE__,2)). 'templates/front/form-billing-address.php');
    }
    function kiriof_checkout_field_validation() {
        try {
             // Verify Nonce - fail early if missing or invalid
            if ( ! isset( $_POST['checkout_kiriminaja_nonce_field'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['checkout_kiriminaja_nonce_field'] ) ), KIRIOF_NONCE ) ) {
                return;
            }
                
            $field_key = $this->field_destination_key;
            
            if ( isset($_POST[$field_key]) && empty($_POST[$field_key]) ) {
                wc_add_notice( esc_html__('<strong>Field Kelurahan</strong> is a required field.', 'kiriminaja-official'),'error' );
            }
            (new \KiriminAjaOfficial\Services\CheckoutServices\ValidationCodCalculationService([
                'shipping_method'   => WC()->session->get('chosen_shipping_methods'),
                'payment_method'    => WC()->session->get('chosen_payment_method'),
                'cart_total'        => WC()->cart->total
            ]))->call();
        
        }catch (\Throwable $th) {
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('kiriof_checkout_field_validation',[$th->getMessage()]);   
        }
    }
    function add_custom_select_options_field_and_script_shipping()
    {
        require_once (plugin_dir_path(dirname(__FILE__,2)). 'templates/front/form-shipping-address.php');
    }
    function afterCheckoutAfterCreated( $order_id, $posted_data, $order ){
        /** Resolve the order object: WC may pass null in some flows */
        if ( ! $order instanceof \WC_Order ) {
            $order = wc_get_order( $order_id );
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
            $payment_method = (string) WC()->session->get( 'payment_method', '' );
        }
        if ( '' === $force_insurance ) {
            $force_insurance = WC()->session->get( 'force_insurance', '' );
        }

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
            $order->save();
        }
    }
    
    function afterCheckoutBeforeCreated($order,$data ){
        /** if kiriof_field value is not exist or null then prevent*/
        if ( ! isset( $_POST['checkout_kiriminaja_nonce_field'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['checkout_kiriminaja_nonce_field'] ) ), KIRIOF_NONCE ) ) {
            return;
        }
            if (isset($_POST['shipping_method'][0]) && !empty($_POST['shipping_method'][0])) {
                $shipping_method = sanitize_text_field(
                    wp_unslash(
                        $_POST['shipping_method'][0]
                    )
                );
            } else {
                return;
            }
            
            if( empty($_POST['ship_to_different_address']) ){
                $destination_area = isset($_POST['kiriof_destination_area']) ? sanitize_text_field( wp_unslash($_POST['kiriof_destination_area'])) : '';
                $destinasi_name = isset($_POST['kiriof_destination_area_name']) ? sanitize_text_field(wp_unslash($_POST['kiriof_destination_area_name'])) : '';
                $insurance_post = isset($_POST[$this->field_insurance_key]) ? sanitize_text_field(wp_unslash($_POST[$this->field_insurance_key])) : '';
            }else{
                $destinasi_name = isset($_POST['kiriof_shipping_destination_area_name']) ? sanitize_text_field(wp_unslash($_POST['kiriof_shipping_destination_area_name'])) : '';
                $insurance_post = isset( $_POST['kiriof_shipping_insurance'] ) ? sanitize_text_field(wp_unslash($_POST['kiriof_shipping_insurance'])) : '';
                $destination_area = isset($_POST['kiriof_shipping_destination_area']) ? sanitize_text_field(wp_unslash($_POST['kiriof_shipping_destination_area'])) : '';
            }

            // Block checkout: resolve text district name to ID
            // Search by postcode first (more accurate), then by name
            if ( ! empty( $destination_area ) && ! is_numeric( $destination_area ) ) {
                $destinasi_name = $destination_area;
                $api_service    = new \KiriminAjaOfficial\Services\KiriminajaApiService();

                // Get postcode from order address
                $order_postcode = $order->get_shipping_postcode();
                if ( empty( $order_postcode ) ) {
                    $order_postcode = $order->get_billing_postcode();
                }

                if ( ! empty( $order_postcode ) ) {
                    $search_result = $api_service->sub_district_search( $order_postcode );
                    if ( 200 === $search_result->status && ! empty( $search_result->data ) ) {
                        $best_match = false;
                        foreach ( $search_result->data as $match ) {
                            if ( false !== stripos( $match->text, $destination_area ) ) {
                                $destination_area = (string) $match->id;
                                $destinasi_name   = $match->text;
                                $best_match = true;
                                break;
                            }
                        }
                        if ( ! $best_match && ! empty( $search_result->data ) ) {
                            $destination_area = (string) $search_result->data[0]->id;
                            $destinasi_name   = $search_result->data[0]->text;
                        }
                    }
                }

                // Fallback: search by district name
                if ( ! is_numeric( $destination_area ) ) {
                    $search_result = $api_service->sub_district_search( $destination_area );
                    if ( 200 === $search_result->status && ! empty( $search_result->data ) ) {
                        $destination_area = (string) $search_result->data[0]->id;
                        $destinasi_name   = $search_result->data[0]->text;
                    }
                }
            }

            // Force insurance when global insurance setting is enabled
            $insurance_setting = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('enable_insurance');
            if ( $insurance_setting && 'yes' === $insurance_setting->value ) {
                $insurance_post = '1';
            }
            /** Store custom field value in WooCommerce session (not PHP session) */
            $kiriof_filter_methods = substr( sanitize_text_field( wp_unslash( $_POST['shipping_method'][0] ) ), strlen( 'kiriminaja-official_' ) ); // remove kiriminaja-official_ prefix
            $kiriof_checkout_token_post = isset( $_POST['kiriof_checkout_token'] ) ? sanitize_text_field( wp_unslash( $_POST['kiriof_checkout_token'] ) ) : '';
            $kiriof_payment_method_post = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : '';
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
            
            //save meta Insurance shipping woocommerce
            if( isset($data['kiriof_shipping_insurance']) && !empty($data['kiriof_shipping_insurance']) ) $order->update_meta_data( '_shipping_kiriof_insurance', sanitize_text_field( ( wp_unslash($data['kiriof_shipping_insurance']) == true ) ? 'yes' : '' ) );
            
            //flag order ppn
            $order->update_meta_data( '_kiriof_ppn', true );
        // end nonce check security
    }
    
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
                            <th class="" style="text-align: left">'.esc_html( kiriof_helper()->tlThis('Order Number',$locale) ).'</th>
                            <th class="" style="text-align: right">'.esc_html($transaction->wp_wc_order_stat_order_id).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.esc_html(kiriof_helper()->tlThis('Date',$locale)).'</th>
                            <th class="" style="text-align: right">'.esc_html( wp_date('d F Y H:i',strtotime( $transaction->created_at ) ) ).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.esc_html( kiriof_helper()->tlThis('Payment Method',$locale) ).'</th>
                            <th class="" style="text-align: right">'.esc_html( $paymentMethod->meta_value ).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.esc_html(kiriof_helper()->tlThis('Sub Total',$locale)).'</th>
                            <th class="" style="text-align: right">Rp.'.esc_html( kiriof_money_format( $transaction->transaction_value ) ).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.esc_html( kiriof_helper()->tlThis('Shipping Fee',$locale) ).'</th>
                            <th class="" style="text-align: right">Rp.'.esc_html( kiriof_money_format(($transaction->shipping_cost ?? 0) + ($transaction->insurance_cost ?? 0) + ($transaction->cod_fee ?? 0)) ).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.esc_html( kiriof_helper()->tlThis('Payment Total',$locale) ).'</th>
                            <th class="" style="text-align: right">Rp.'.esc_html( kiriof_money_format(($transaction->transaction_value ?? 0) + ($transaction->shipping_cost ?? 0) + ($transaction->insurance_cost ?? 0) + ($transaction->cod_fee ?? 0)) ).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.esc_html( kiriof_helper()->tlThis('Tracking',$locale) ).'</th>
                            <th class="" style="text-align: right"><a href="'.esc_url( home_url().'/tracking?order_id='.$transaction->wp_wc_order_stat_order_id ).'" target="_blank">CLICK</a></th>
                        </tr>
                    </thead>
                </table>            
            </section>
        ' );
        
    }
    public function kiriof_order_details($order){
        $transactionKiriminaja = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransactionByWCOrderNumber($order->get_id());
        $shipping_method_id = array_shift( $order->get_shipping_methods() )['method_id'];
        if( $shipping_method_id != 'kiriminaja-official' ){
            return false;
        }
        $html = '
            <tr>
				<th scope="row">'.esc_html__('Ekspedisi','kiriminaja-official').':</th>
				<td class="wc-block-order-confirmation-totals__total">'.esc_html($order->get_shipping_method()).'</td>
			</tr>
            <tr>
				<th scope="row">'.esc_html__('Tracking','kiriminaja-official').':</th>
				<td class="wc-block-order-confirmation-totals__total"><a class="kj-button" href="'.esc_url( home_url('/tracking?order_id='.$order->get_id()) ).'">'.esc_html__('Click','kiriminaja-official').'</a></td>
			</tr>';
        if( $order->get_meta('_'.$this->field_insurance_key) == true || $transactionKiriminaja->insurance_cost > 0 ){
            $html .= '
            <tr>
				<th scope="row">'.esc_html__('Insurance','kiriminaja-official').':</th>
				<td class="wc-block-order-confirmation-totals__total">'.wc_price($transactionKiriminaja->insurance_cost).'</td>
			</tr>';
        }
        if( $order->get_payment_method() == 'cod'){
            $html .= '
            <tr>
				<th scope="row">
                    <label for="kiriof_cod_fee" style="display:block;margin:0;">'. esc_html__('COD Fee:','kiriminaja-official').'</label>		
                    <em style="font-size: 16px;font-weight: 300;">(incl. 11% VAT)</em>		
                </th>
				<td class="wc-block-order-confirmation-totals__total">'.wc_price($transactionKiriminaja->cod_fee).'</td>
			</tr>';
        }
        
        echo  wp_kses_post( $html );
    }
    public function kiriof_shipping_rate_cache_invalidation( $packages ) {
        foreach ( $packages as &$package ) {
            $package['rate_cache'] = wp_rand();
        }
    
        return $packages;
    }
    public function kiriof_validateOrder($posted){
        $packages = WC()->shipping->get_packages();
        
        // Verify nonce - fail early if missing or invalid
        if ( ! isset( $_POST['checkout_kiriminaja_nonce_field'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['checkout_kiriminaja_nonce_field'] ) ), KIRIOF_NONCE ) ) {
            return;
        }
        
            if( isset($_POST['billing_country']) ){
                
                if ($_POST['billing_country'] === "ID"){
                    if (empty($_POST['kiriof_destination_area'])) {
                        wc_add_notice( __( "<strong>District</strong> is a required field", 'kiriminaja-official' ), 'error' );
                    }
                    if (empty($_POST['shipping_method'][0])) {
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
        // add field insurance checkout
        $fields = self::kiriof_add_field_insurance( $fields );
    
        return $fields;
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
        //billing session
        $destination_id     = WC()->session->get('destination_id') ?? '';
        $destination_name   = WC()->session->get('destination_name') ?? '';
        $options = array( '' => esc_html__( 'Select Option', 'kiriminaja-official' ) );
        if ( ! empty( $destination_id ) ) {
            $options[ $destination_id ] = $destination_name;
        }

        //shipping session
        $shipping_dest_id   = WC()->session->get('shipping_destination_id') ?? '';
        $shipping_dest_name = WC()->session->get('shipping_destination_name') ?? '';
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
    private function kiriof_add_field_insurance( $fields ){
        $insurance_setting = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('enable_insurance');
        $force_insurance   = ( $insurance_setting && 'yes' === $insurance_setting->value );
        $default_val       = $force_insurance ? '1' : '0';

        $field_key = $this->field_insurance_key;
        $fields['billing'][$field_key] = array(
            'label'     => esc_html__('Insurance Shipping', 'kiriminaja-official'),
            'required'  => false,
            'class'     => array('form-row-wide'),
            'clear'     => true,
            'type'      => 'checkbox',
            'priority'  => 62,
            'default'   => $default_val,
            'custom_attributes' => $force_insurance ? array( 'disabled' => 'disabled' ) : array(),
        );
        $fields['shipping'][$this->field_shipping_insurance_key] = array(
            'label'     => esc_html__('Insurance Shipping', 'kiriminaja-official'),
            'required'  => false,
            'class'     => array('form-row-wide'),
            'clear'     => true,
            'type'      => 'checkbox',
            'priority'  => 62,
            'default'   => $default_val,
            'custom_attributes' => $force_insurance ? array( 'disabled' => 'disabled' ) : array(),
        );
        return $fields;
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
    public function kiriof_shipping_chosen_method($method, $available_methods) {
        // Verify nonce - fail early if missing or invalid
        if ( ! isset( $_POST['checkout_kiriminaja_nonce_field'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['checkout_kiriminaja_nonce_field'] ) ), KIRIOF_NONCE ) ) {
            return $method;
        }
        if (isset($_POST['shipping_method'][0]) && array_key_exists( sanitize_text_field( wp_unslash( $_POST['shipping_method'][0] )), $available_methods)) {
            return sanitize_text_field( wp_unslash($_POST['shipping_method'][0]));
        }
        return $method;
    }

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
        // Only affect the checkout page
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

    /**
     * Inject Insurance + COD fee HTML into the block checkout fee block.
     * Block checkout renders fees via checkout-order-summary-fee-block.
     * render_block filter fires during server-side rendering of the block.
     */
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
                'required'     => true,
                'address_type' => array( 'billing', 'shipping' ),
            ));
        } else {
            $register_fn(
                array(
                    'id'       => 'kiriminaja-official/' . $this->field_destination_key,
                    'label'    => __( 'District', 'kiriminaja-official' ),
                    'location' => 'address',
                    'type'     => 'select',
                    'required' => true,
                    'options'  => $options,
                ),
                array( 'address_type' => array( 'billing', 'shipping' ) )
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

        $shipping_method = isset( $data['shipping_metode_id'] ) ? sanitize_text_field( wp_unslash( $data['shipping_metode_id'] ) ) : '';
        $destination_id  = isset( $data['destination_id'] ) ? (int) $data['destination_id'] : 0;
        $payment_method  = isset( $data['payment_method'] ) ? sanitize_text_field( wp_unslash( $data['payment_method'] ) ) : '';
        $insurance       = ! empty( $data['insurance'] ) ? 1 : 0;

        if ( '' !== $shipping_method ) {
            WC()->session->set( 'chosen_shipping_methods', array( $shipping_method ) );
        }

        if ( $destination_id > 0 ) {
            WC()->session->set( 'destination_id', $destination_id );
            WC()->session->set( 'shipping_destination_id', $destination_id );
        }

        if ( '' !== $payment_method ) {
            WC()->session->set( 'chosen_payment_method', $payment_method );
            WC()->session->set( 'kiriof_payment_method', $payment_method );
        }

        WC()->session->set( 'kiriof_insurance', $insurance );
        WC()->session->set( 'kiriof_cached_insurance_amt', 0 );
        WC()->session->set( 'kiriof_cached_cod_amt', 0 );
    }
}