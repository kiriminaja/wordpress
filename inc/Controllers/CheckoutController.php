<?php

namespace Inc\Controllers;

class CheckoutController
{

    private $key_destination_id     = 'destination_id';
    private $key_destination_name   = 'destination_name';

    private $key_shipping_destination_id = 'shipping_destination_id';
    private $key_shipping_destination_name = 'shipping_destination_name';
    
    //billing checkout key
    private $field_destination_key  = 'kj_destination_area';
    private $field_insurance_key    = 'kj_insurance';
 
    //shipping checkout key
    private $field_shipping_destination_key  = 'kj_shipping_destination_area';
    private $field_shipping_insurance_key  = 'kj_shipping_insurance';


    public function register()
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        if (is_plugin_active('woocommerce/woocommerce.php')) {

            //before total order checkout
            add_action('woocommerce_review_order_before_order_total',array($this,'kj_reviewOrderBeforeTotalOrder'));

            /** Add Custom field Checkout Sub District */
            add_action('woocommerce_after_checkout_billing_form', array($this, 'add_custom_select_options_field_and_script'));
            add_action('wp_footer', array($this, 'add_custom_select_options_field_and_script'));
            
            /** Validation Custom field Sub District */
            add_action( 'woocommerce_checkout_process', array($this,'kj_checkout_field_validation') );
            
            /** After Checkout*/
                /** Save custom field value as custom order metadata */
                add_action( 'woocommerce_checkout_create_order', array($this,'afterCheckoutBeforeCreated'), 10, 2 );
                /** After checkout Save custom field value as custom customer metadata */
                add_action( 'woocommerce_checkout_order_processed', array($this,'afterCheckoutAfterCreated'),10, 3);
            /** end After Checkout */

            /** Expedition Ajax*/
            add_action('wp_ajax_kj-get-expedition-ajax', array($this,'getExpeditionOptionAjax'));
            add_action('wp_ajax_nopriv_kj-get-expedition-ajax', array($this,'getExpeditionOptionAjax'));
                        
            /** Custom Page Woocommerce Thankyou */
            add_action( 'woocommerce_order_details_after_order_table_items', array($this,'kj_order_details') );
            
            /** remove Cache Shipping triger update_checkout */
            add_filter( 'woocommerce_cart_shipping_packages', array($this,'kj_shipping_rate_cache_invalidation'), 100 );

            /** Validate Shipping Kirimin aja */
            add_action('woocommerce_review_order_before_cart_contents', array($this,'kj_validateOrder'), 10);
            add_action('woocommerce_after_checkout_validation', array($this,'kj_validateOrder'), 10);
            
            /**
             * Remove Billing and shipping Fields
             */
            add_filter('woocommerce_checkout_fields', array($this,'kj_billing_fields'), 100);            

            add_filter('woocommerce_shipping_chosen_method', array($this,'kj_shipping_chosen_method'), 10, 2);
            
            add_filter( 'woocommerce_cart_needs_shipping', array($this,'kj_filter_cart_needs_shipping'));
        }
    }

    function kj_filter_cart_needs_shipping( $needs_shipping ) {
        if ( is_cart() ) {
            if( $needs_shipping && get_option( 'woocommerce_enable_shipping_calc' ) === 'no' ){
                WC()->session->set( 'destination_id', null );
            }
        }
        return $needs_shipping;
    }

    function kj_reviewOrderBeforeTotalOrder(){
        if( !is_checkout() ){
            return false;
        }
        
        $table = '<tr class="kj_cart_item_insurane" style="display:none;">
			<td class="kj-cart-insurance">
				<label for="kj_cart_insurance">'.__('Insurance','kiriminaja').'</label>											
            </td>
			<td class="kj-cart-insurance kj-cost-insurance"></td>
		</tr>
        <tr class="kj_cart_item_cod_fee" style="display:none;">
			<td class="kj-cod-fee">
				<label for="kj_cod_fee" style="display:block;margin:0;">'. __('COD Fee','kiriminaja').'</label>		
                <em style="font-size: 16px;font-weight: 300;">(incl. 11% VAT)</em>									
            </td>
			<td class="kj-cod-fee kj-cost-codfee"></td>
		</tr>';
        echo $table;
    }

    function rei_after_checkout_validation( $posted ) {
        
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


    function add_custom_select_options_field_and_script($checkout)
    {        
        $field_key = $this->field_destination_key;

        $dentination_id = WC()->session->get($this->key_destination_id);
        $dentination_name = WC()->session->get($this->key_destination_name);

        $shipping_dentination_id = WC()->session->get($this->key_shipping_destination_id);
        $shipping_dentination_name = WC()->session->get($this->key_shipping_destination_name);
        
        $kj_checkout_token = empty($dentination_id) ? false : true;

        require_once (plugin_dir_path(dirname(__FILE__,2)). 'templates/front/form-billing-address.php');
    }

    function kj_checkout_field_validation() {
        try {
           
            $field_key = $this->field_destination_key;
            
            if ( isset($_POST[$field_key]) && empty($_POST[$field_key]) ) {
                wc_add_notice( _e('<strong>Field Kelurahan</strong> is a required field.', 'kiriminaja'),'error' );
            }

            (new \Inc\Services\CheckoutServices\ValidationCodCalculationService([
                'shipping_method'   => WC()->session->get('chosen_shipping_methods'),
                'payment_method'    => WC()->session->get('chosen_payment_method'),
                'cart_total'        => WC()->cart->total
            ]))->call();
        
        }catch (\Throwable $th) {
            (new \Inc\Base\BaseInit())->logThis('kj_checkout_field_validation',[$th->getMessage()]);   
        }
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
        $payment_method                 = $_SESSION["payment_method"];
        $force_insurance                = $_SESSION["force_insurance"];
        
        if( $force_insurance == 1 ){
            $insurance = 1;
        }else{
            $insurance = $_SESSION["billing_insurance"];
        }

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
                'is_insurance'              => @$insurance,
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

        if (!@$_POST['shipping_method'][0]) { return; }
        
        
        if( empty($_POST['ship_to_different_address']) ){
            $destination_area = $_POST['kj_destination_area'];
            $destinasi_name = $_POST['kj_destination_area_name'];
            $insurance_post = $_POST[$this->field_insurance_key];
        }else{
            $destinasi_name = $_POST['kj_shipping_destination_area_name'];
            $insurance_post = $_POST['kj_shipping_insurance'];
            $destination_area = $_POST['kj_shipping_destination_area'];
        }

        /** Store custom field value in session*/
        session_start();
        $kj_filter_methods = substr(@$_POST['shipping_method'][0],11);//remove kiriminaja_

        $_SESSION["kj_destination_area"]            = $destination_area;
        $_SESSION["kj_destination_area_name"]       = $destinasi_name;
        $_SESSION["kj_expedition"]                  = $kj_filter_methods;
        $_SESSION["kj_checkout_token"]              = @$_POST['kj_checkout_token'];
        $_SESSION["billing_insurance"]              = isset($insurance_post) ? 1 : 0;
        $_SESSION["payment_method"]                 = @$_POST['payment_method'];
        $_SESSION["force_insurance"]                = intval( @$_POST['kj_force_insurance'] );

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
        if( isset($_POST['kj_destination_area']) && !empty($_POST['kj_destination_area']) ) $order->update_meta_data( '_billing_kj_destination_area', sanitize_text_field($_POST['kj_destination_area']) );
        if( isset($_POST['kj_destination_area_name']) && !empty($_POST['kj_destination_area_name']) ) $order->update_meta_data( '_billing_kj_destination_name', sanitize_text_field($_POST['kj_destination_area_name']) );

        //save meta Insurance billing woocommerce
        if( isset($data['kj_insurance']) && !empty($data['kj_insurance']) ) $order->update_meta_data( '_billing_kj_insurance', sanitize_text_field( ( $data['kj_insurance'] == true ) ? 'yes' : '' ) );
        
        //save meta subdistrict shipping woocommerce
        if( isset($_POST['kj_shipping_destination_area']) && !empty($_POST['kj_shipping_destination_area']) ) $order->update_meta_data( '_shipping_kj_destination_area', sanitize_text_field($_POST['kj_shipping_destination_area']) );
        if( isset($_POST['kj_shipping_destination_area_name']) && !empty($_POST['kj_shipping_destination_area_name']) ) $order->update_meta_data( '_shipping_kj_destination_name', sanitize_text_field($_POST['kj_shipping_destination_area_name']) );
        
        //save meta Insurance shipping woocommerce
        if( isset($data['kj_shipping_insurance']) && !empty($data['kj_shipping_insurance']) ) $order->update_meta_data( '_shipping_kj_insurance', sanitize_text_field( ( $data['kj_shipping_insurance'] == true ) ? 'yes' : '' ) );
        
        //flag order ppn
        $order->update_meta_data( '_kj_ppn', true );

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
        $paymentMethod = (new \Inc\Repositories\WpPostMetaRepository())->getRequiredRowsByPostIdAndMetaKey($order_id,'_payment_method_title');
        $locale = get_locale();
        
        echo '
        <section style="margin: 1rem 0 4rem 0" class="woocommerce-order-details">          
                <h2 class="woocommerce-order-details__title">Pembayaran</h2>            
                <table style="width: 100%; font-size: 1rem" class="woocommerce-table woocommerce-table--order-details shop_table order_details">            
                    <thead>
                        <tr>
                            <th class="" style="text-align: left">'.kjHelper()->tlThis('Order Number',$locale).'</th>
                            <th class="" style="text-align: right">'.@$transaction->wp_wc_order_stat_order_id.'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.kjHelper()->tlThis('Date',$locale).'</th>
                            <th class="" style="text-align: right">'.date('d F Y H:i',strtotime(@$transaction->created_at)).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.kjHelper()->tlThis('Payment Method',$locale).'</th>
                            <th class="" style="text-align: right">'.@$paymentMethod->meta_value.'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.kjHelper()->tlThis('Sub Total',$locale).'</th>
                            <th class="" style="text-align: right">Rp.'.localMoneyFormat(@$transaction->transaction_value).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.kjHelper()->tlThis('Shipping Fee',$locale).'</th>
                            <th class="" style="text-align: right">Rp.'.localMoneyFormat((@$transaction->shipping_cost ?? 0) + (@$transaction->insurance_cost ?? 0) + (@$transaction->cod_fee ?? 0)).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.kjHelper()->tlThis('Payment Total',$locale).'</th>
                            <th class="" style="text-align: right">Rp.'.localMoneyFormat((@$transaction->transaction_value ?? 0) + (@$transaction->shipping_cost ?? 0) + (@$transaction->insurance_cost ?? 0) + (@$transaction->cod_fee ?? 0)).'</th>
                        </tr>
                        <tr>
                            <th class="" style="text-align: left">'.kjHelper()->tlThis('Tracking',$locale).'</th>
                            <th class="" style="text-align: right"><a href="'.home_url().'/tracking?order_id='.@$transaction->wp_wc_order_stat_order_id.'" target="_blank">CLICK</a></th>
                        </tr>
                    </thead>
                </table>            
            </section>
        ';
        
    }

    public function kj_order_details($order){
        $transactionKiriminaja = (new \Inc\Repositories\TransactionRepository())->getTransactionByWCOrderNumber($order->get_id());

        $shipping_method_id = array_shift( $order->get_shipping_methods() )['method_id'];

        if( $shipping_method_id != 'kiriminaja' ){
            return false;
        }

        $html = '
            <tr>
				<th scope="row">'.__('Ekspedisi','kiriminaja').':</th>
				<td class="wc-block-order-confirmation-totals__total">'.$order->get_shipping_method().'</td>
			</tr>
            <tr>
				<th scope="row">'.__('Tracking','kiriminaja').':</th>
				<td class="wc-block-order-confirmation-totals__total"><a class="kj-button" href="'.home_url().'/tracking?order_id='.$order->get_id().'">'.__('Click','kiriminaja').'</a></td>
			</tr>';

        if( $order->get_meta('_'.$this->field_insurance_key) == true ){
            $html .= '
            <tr>
				<th scope="row">'.__('Insurance','kiriminaja').':</th>
				<td class="wc-block-order-confirmation-totals__total">'.wc_price($transactionKiriminaja->insurance_cost).'</td>
			</tr>';
        }

        if( $order->get_payment_method() == 'cod'){
            $html .= '
            <tr>
				<th scope="row">
                    <label for="kj_cod_fee" style="display:block;margin:0;">'. __('COD Fee:','kiriminaja').'</label>		
                    <em style="font-size: 16px;font-weight: 300;">(incl. 11% VAT)</em>		
                </th>
				<td class="wc-block-order-confirmation-totals__total">'.wc_price($transactionKiriminaja->cod_fee).'</td>
			</tr>';
        }
        
        echo  $html;

    }

    public function kj_shipping_rate_cache_invalidation( $packages ) {
        foreach ( $packages as &$package ) {
            $package['rate_cache'] = wp_rand();
        }
    
        return $packages;
    }

    public function kj_validateOrder($posted){
        $packages = WC()->shipping->get_packages();
        
        if( isset($_POST['billing_country']) ){
            
            if ($_POST['billing_country'] === "ID"){
                if (empty($_POST['kj_destination_area'])) {
                    wc_add_notice( __( "<strong>District</strong> is a required field", 'kiriminaja' ), 'error' );
                }
                if (empty($_POST['shipping_method'][0])) {
                    wc_add_notice( __( "<strong>Shipping</strong> is a required field", 'kiririminaja' ), 'error' );
                }
                if (empty($_POST['kj_checkout_token'])) {
                    wc_add_notice( __( "<strong>Checkout Calculation</strong> is not finished yet", 'kiriminaja' ), 'error' );
                }

            }
        }

        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        
        if( $chosen_methods != null ){
            $kj_filter_methods = substr($chosen_methods[0],0,10);//kiriminaja
        
            if ($kj_filter_methods == 'kiriminaja') {
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
                            $message = __("Berat Produk ".$_product->get_name()." Perlu di Setting",'kiriminaja');
                            $messageType = "error";
                            wc_add_notice($message, $messageType);
                        }
                    }
                    
                }
    
            }
        }

    }

    public function kj_billing_fields($fields){
        $fields_selected = array( 
            'city',
            'company', 
            'postcode', 
            'state'
        );

        /** Remove field Checkout */
        $fields = self::kj_remove_fields_checkout($fields,$fields_selected);

        /** Add field Subdistrict */
        $fields = self::kj_add_field_subdistrict( $fields );

        // add field insurance checkout
        $fields = self::kj_add_field_insurance( $fields );
    
        return $fields;
    }

    private function kj_remove_fields_checkout($fields,$fields_selected){
        foreach ($fields_selected as $field_key) {
            unset( $fields['billing']['billing_'.$field_key] );
            unset( $fields['shipping']['shipping_'.$field_key] );
        }
        return $fields;
    }

    private function kj_add_field_subdistrict( $fields ){
        $field_key = $this->field_destination_key;

        //billing session
        $destination_id     = WC()->session->get('destination_id') ?? '';
        $destination_name   = WC()->session->get('destination_name') ?? '';

        //add field billing District
        $fields['billing'][$field_key] = array(
            'label'     => __(esc_html__('District', 'kiriminaja'),'kiriminaja'),
            'required'  => true,
            'class'     => array('form-row-wide'),
            'clear'     => true,
            'type'      => 'select',
            'priority'  => 61,
            'options'   => array($destination_id => $destination_name)
        );

        //add field shipping District
        $fields['shipping'][$this->field_shipping_destination_key] = array(
            'label'     => __(esc_html__('District', 'kiriminaja'),'kiriminaja'),
            'required'  => true,
            'class'     => array('form-row-wide'),
            'clear'     => true,
            'type'      => 'select',
            'priority'  => 61,
            'options'   => array($destination_id => $destination_name)
        );

        return $fields;
    }

    private function kj_add_field_insurance( $fields ){
        $field_key = $this->field_insurance_key;

        $fields['billing'][$field_key] = array(
            'label'     => __(esc_html__('Insurance Shipping', 'kiriminaja'),'kiriminaja'),
            'required'  => false,
            'class'     => array('form-row-wide'),
            'clear'     => true,
            'type'      => 'checkbox',
            'priority'  => 62,
        );

        $fields['shipping'][$this->field_shipping_insurance_key] = array(
            'label'     => __(esc_html__('Insurance Shipping', 'kiriminaja'),'kiriminaja'),
            'required'  => false,
            'class'     => array('form-row-wide'),
            'clear'     => true,
            'type'      => 'checkbox',
            'priority'  => 62,
        );

        return $fields;
    }

    public function kj_beforeCheckoutForm(){
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
    public function kj_shipping_chosen_method($method, $available_methods) {
        if (isset($_POST['shipping_method'][0]) && array_key_exists($_POST['shipping_method'][0], $available_methods)) {
            return $_POST['shipping_method'][0];
        }
        return $method;
    }

}
