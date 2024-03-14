<?php

namespace Inc\Controllers;

class CheckoutController
{

    public function register()
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        if (is_plugin_active('woocommerce/woocommerce.php')) {
//            add_action('woocommerce_before_checkout_form', array($this, 'ts_add_order'));
            add_action('woocommerce_after_checkout_billing_form', array($this, 'add_custom_select_options_field_and_script'));
            // add_action('woocommerce_after_checkout_shipping_form', array($this, 'add_custom_select_options_field_and_script_shipping'));
            // add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );
//            add_action( 'woocommerce_review_order_before_shipping', array($this,'custom_shipping_content'));
            add_action('woocommerce_after_checkout_validation', array($this,'rei_after_checkout_validation'));
            /** After Checkout*/
            add_action( 'woocommerce_checkout_create_order', array($this,'afterCheckoutBeforeCreated'), 10, 2 );
            add_action( 'woocommerce_checkout_order_processed', array($this,'afterCheckoutAfterCreated'),10, 3);

            /** Expedition Ajax*/
            add_action('wp_ajax_kj-get-expedition-ajax', array($this,'getExpeditionOptionAjax'));
            add_action('wp_ajax_nopriv_kj-get-expedition-ajax', array($this,'getExpeditionOptionAjax'));
            
            /** Calculation Ajax*/
            add_action('wp_ajax_kj-checkout-calc', array($this,'getCheckoutCalculationAjax'));
            add_action('wp_ajax_nopriv_kj-checkout-calc', array($this,'getCheckoutCalculationAjax'));


            add_action( 'woocommerce_thankyou', array($this,'custom_content_thankyou'), 10, 1 );
        }
    }

    function rei_after_checkout_validation( $posted ) {

        // do all your logics here...
        // adding wc_add_notice with a second parameter of "error" will stop the form...
        // wc_add_notice( __( "OMG! You're not human!", 'woocommerce' ), 'error' );
        
        if ($_POST['billing_country'] === "ID"){
            if (empty($_POST['kj_destination_area'])) {
                wc_add_notice( __( "<strong>Kelurahan</strong> is a required field", 'woocommerce' ), 'error' );
            }
            if (empty($_POST['kj_expedition'])) {
                wc_add_notice( __( "<strong>Ekspedisi</strong> is a required field", 'woocommerce' ), 'error' );
            }
            if (empty($_POST['kj_checkout_token'])) {
                wc_add_notice( __( "<strong>Checkout Calculation</strong> is not finished yet", 'woocommerce' ), 'error' );
            }
        }
    }

    function custom_shipping_content() {
        echo '<tr class="shipping"><th>Custom Shipping</th><td>Nanti Custom Shipping Disini!</td></tr>';
    }

    function ts_add_order()
    {
        echo ('<h2>Testing! 3</h2>');
    }


    function add_custom_select_options_field_and_script()
    {
        require_once (plugin_dir_path(dirname(__FILE__,2)). 'templates/front/form-billing-address.php');
    }

    function add_custom_select_options_field_and_script_shipping()
    {
        require_once (plugin_dir_path(dirname(__FILE__,2)). 'templates/front/form-shipping-address.php');
    }

    function afterCheckoutAfterCreated( $order_id, $posted_data, $order ){
        /** if kj_field value is not exist or null then prevent*/
        if (!@$_SESSION["kj_expedition"]) { return; }
        
        /** Get data from session*/
        $kj_destination_area            = $_SESSION["kj_destination_area"];
        $kj_destination_area_name       = $_SESSION["kj_destination_area_name"];
        $kj_expedition                  = $_SESSION["kj_expedition"];
        $kj_checkout_token              = $_SESSION["kj_checkout_token"];
        $insurance                      = $_SESSION["billing_insurance"];
        $payment_method                 = $_SESSION["payment_method"];

        // remove all session variables
        session_unset();
        // destroy the session
        session_destroy();
        
        /** Store Transaction*/
        try {
            $createTransaction = (new \Inc\Services\CheckoutServices\CreateTransactionService([
                'order_id'                  => @$order_id,
                'checkout_post_data'        => @$posted_data,
                'kj_destination_area'       => @$kj_destination_area,
                'kj_destination_area_name'  => @$kj_destination_area_name,
                'kj_expedition'             => @$kj_expedition,
                'is_insurance'              => @$insurance === "1",
                'is_cod'                    => $payment_method === 'cod',
                'wc_cart_contents'          => WC()->cart->cart_contents,
            ]))->call();
            (new \Inc\Base\BaseInit())->logThis('afterCheckoutAfterCreated',[$createTransaction]);
        } catch (\Throwable $th){
            (new \Inc\Base\BaseInit())->logThis('afterCheckoutAfterCreated',[$th->getMessage()]);   
        }
    }
    
    function afterCheckoutBeforeCreated($order,$data ){
        /** if kj_field value is not exist or null then prevent*/
        if (!@$_POST['kj_expedition']) { return; }
        
        /** Store custom field value in session*/
        session_start();
        $_SESSION["kj_destination_area"]            = @$_POST['kj_destination_area'];
        $_SESSION["kj_destination_area_name"]       = @$_POST['kj_destination_area_name'];
        $_SESSION["kj_expedition"]                  = @$_POST['kj_expedition'];
        $_SESSION["kj_checkout_token"]              = @$_POST['kj_checkout_token'];
        $_SESSION["billing_insurance"]              = @$_POST['billing_insurance'];
        $_SESSION["payment_method"]                 = @$_POST['payment_method'];
        
    }
    
    function getExpeditionOptionAjax(){
        /**
        DELAYDEVNOTE
         * pricing payload
         */

        try {
            $service = (new \Inc\Services\CheckoutServices\OngkirPricingService([
                'destination_area_id'   => $_POST['data']['destination_area_id'],
                'is_cod'                => $_POST['data']['payment_method']==='cod',
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
        /**
        DELAYDEVNOTE
         * pricing payload
         */
        
        try {
            $service = (new \Inc\Services\CheckoutServices\CheckoutCalculationService([
                'destination_area_id'   => $_POST['data']['destination_area_id'],
                'expedition'            => $_POST['data']['expedition'],
                'is_insurance'          => $_POST['data']['insurance'] === "true",
                'is_cod'                => $_POST['data']['payment_method'] === 'cod',
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
        $transaction = (new \Inc\Repositories\TransactionRepository())->getTransactionByWCOrderId($order_id);
        require_once (plugin_dir_path(dirname(__FILE__,2)). 'templates/front/after-checkout-page.php');
    }
}
