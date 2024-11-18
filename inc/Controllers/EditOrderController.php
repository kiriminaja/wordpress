<?php
namespace Inc\Controllers;

class EditOrderController{

    private $nonce = KJ_NONCE ;

    public function register(){
        add_action( 'woocommerce_admin_order_totals_after_total', array($this,'addKjOrderDetail'));
        add_filter( 'wc_order_is_editable', array($this,'kj_custom_order_status_editable'), 9999, 2 );

        /**
         * Override Edit Shipping Order Admin
         */
        add_action( 'woocommerce_saved_order_items', array($this,'kj_wc_save_order_items'), 10, 2 );

        /** Admin Billing Field && Shipping Field */
        add_filter( 'woocommerce_admin_billing_fields', array($this,'kj_admin_billing_fields'), 10, 1 );
        add_filter( 'woocommerce_admin_shipping_fields', array($this,'kj_admin_shipping_fields'), 10, 1 );
        
        /** Show COD fee and Insurance Fee di Admin */
        add_action( 'woocommerce_admin_order_totals_after_discount', array($this,'kj_add_cod_fee_insurance_fee'), 10 );

        // add_action( 'woocommerce_admin_order_data_after_billing_address', array($this,'kj_checkout_field_display_admin_order_meta'), 10, 1 );

        add_action('save_post_shop_order', array($this,'kj_save_post_shop_order'),10,3);
        add_action('woocommerce_new_order', array($this,'kj_wc_admin_create_order'),10,1);

        add_action( 'woocommerce_order_status_changed', array($this,'kj_order_status_change_action'), 10, 4 );

        /** Custom Button Shipping Admin Order */
        add_action('woocommerce_order_item_add_action_buttons',array($this,'kj_buttonShippingAdminOrder'),10, 2 );

        $this->ajax();
    }

    public function ajax(){
        add_action('wp_ajax_kiriminaja_expedition_by_pricing' , array($this,'kj_getExpeditionByPricing'));
        add_action('wp_ajax_kj_calculation_CodFeeAndInsuranceFee',array($this,'kj_calculationCodFeeAndInsuranceFee'));
    
        add_action('wp_ajax_check_product_item_order' , array($this,'kj_ajaxCheckProductItemOrder'));
    }

    public function addKjOrderDetail($order){
        /** This hook return when client side only, therefore cant do serverside php */
        
        $service = (new \Inc\Services\OrderEditPageServices\ShippingInfoServices())->wcOrderId($order)->call();
        if ($service->status !== 200){return;}
        
        $willBeReplaced = [
            '{$orderId}',
            '{$trackingUrl}',
            '{$kjOrderData}'
        ];
        $replaceWith = [
            $order,
            home_url().'/tracking?order_id='.$order,
            json_encode($service->data)
        ];

        $content = file_get_contents(plugin_dir_path(dirname(__FILE__,2)). 'templates/order/edit.php');
        echo str_replace(
        $willBeReplaced, $replaceWith, $content);
    
    }

    public function kj_custom_order_status_editable($allow_edit, $order){
        if ( $order->get_status() === 'processing' ) {
            $allow_edit = true;
        }
        return $allow_edit;
    }
    
    public function kj_getExpeditionByPricing(){
        
        $post = $_POST;
        
        if ( ! wp_verify_nonce( $post['nonce'], $this->nonce ) ) {
            die( __( 'Security check', 'kiriminaja' ) ); 
        }

        $order_id       = (int) $post['order_id'];
        $destination_id = (int) $post['destination_id'];

        $settingRepo = (new \Inc\Repositories\SettingRepository())->getSettingByKey('origin_sub_district_id');
        
        $order = wc_get_order( $order_id );

        /** convert unit weight */
        $cartAttributes = (new \Inc\Services\UtilServices\GetWCCartAttributeService([
            'wc_cart_contents' => $order->get_items()
        ]))->call();
        
        $payload = [
            'subdistrict_origin'        => (int) $settingRepo->value,
            'subdistrict_destination'   =>$destination_id,
            'weight'    => $cartAttributes->data['weight'],
            'length'    => $cartAttributes->data['length'],
            'width'     => $cartAttributes->data['width'],
            'height'    => $cartAttributes->data['height'],
            'insurance' => 1,
            'item_value'=> $cartAttributes->data['item_value'],
            'courier'   => null, // 'jne', 'pos', 'tiki', 'jet'
        ];

        $kjPricing = (new \Inc\Repositories\KiriminajaApiRepository())->getPricing($payload);
        
        $return = $this->filterOptions($kjPricing['data']);

        if( !$return ){
            wp_send_json_error( ['code'=>'404','message'=>'Expedition Not Found'] );
        }

        wp_send_json_success($return);
    }

    public function filterOptions($pricingData){
        $is_cod = !empty( WC()->session->get( 'kj_payment_method' ) ) ? true : false;

        $options = $pricingData->results ?? [];
        
        $validate = (new \Inc\Repositories\SettingRepository())->validateWhiteListExpedition($options);

        $options = $validate;
        

        $filteredOptions = [];
        foreach ($options as $option){
            if (!$is_cod || $is_cod && $option->cod){
                $filteredOptions[] = [
                    'key'=>$option->service.'_'.$option->service_type,
                    'value'=>$option->service_name.' (Rp'.(localMoneyFormat($option->cost)).')',
                    'cost'=>$option->cost
                ];    
            }
        }
        return $filteredOptions;
    }

    public function kj_wc_save_order_items($order_id, $items){

            $order = wc_get_order( $order_id );

            if( !isset( $items['shipping_method'] ) ) return;

            if( array_shift($items['shipping_method']) == 'kiriminaja' ){
                
                if(!empty($items['kj_expedition_name']))
                {
                    $expediton_cost = !$items['kj_expedition_cost'] ? 0 : $items['kj_expedition_cost'];    

                    $qty = 0; $subtotal = 0;
                    foreach( $order->get_items() as $item_id_shipping => $item ) {
                        $qty += (int) $item->get_quantity();
                        $subtotal += $item->get_total(); // Ambil harga item
                    }
    
                    $item_price = (int) $expediton_cost * $qty;

                    $calculate_data = $this->kj_calculationAdminOrder([
                        'order_id' => $order_id,
                        'kj_subdistrict'=>$items['kj_subdistrict'],
                        'kj_subdistrict_name'=>$items['kj_subdistrict_name'],
                        'kj_expedition'=>$items['kj_expedition'],
                        'kj_expedition_name'=>$items['kj_expedition_name'],
                        'kj_expedition_cost'=>$item_price,
                        'kj_insurancefee_hidden'=>$items['kj_insurancefee_hidden'],
                    ]);
        
                    foreach( $order->get_items('shipping') as $item_id_shipping => $item ) {
                        
                        $item->set_method_title( $items['kj_expedition_name'] ); 
                        $item->set_name( $items['kj_expedition_name'] );
        
                        $item->set_total( $item_price );
                        $item->calculate_taxes();
                        $item->save();
        
                    }   

                    $order_total = $subtotal + $items['kj_codfee_hidden'] ?? 0 + $items['kj_insurancefee_hidden'] ?? 0;
                    $order_total += $item_price;
                    
                    /* Update Total Order*/
                    update_post_meta($order_id,'_order_total', $order_total);
                                        
                    /* Simpan Di Post Meta */
                    update_post_meta($order_id,'_kj_subdistrict_id',$items['kj_subdistrict']);
                    update_post_meta($order_id,'_kj_subdistrict_name',$items['kj_subdistrict_name']);

                    update_post_meta($order_id,'_kj_expedition_code',$items['kj_expedition']);
                    update_post_meta($order_id,'_kj_expedition_name',$items['kj_expedition_name']);
                    update_post_meta($order_id,'_kj_expedition_cost',$items['kj_expedition_cost']);
    
                    update_post_meta($order_id,'_kj_insurance_fee',$items['kj_insurancefee_hidden']);
                    update_post_meta($order_id,'_kj_cod_fee',$items['kj_codfee_hidden']);
                }        
            } 
        
            $order->calculate_shipping();
            $order->calculate_totals();

    }

    public function kj_calculationAdminOrder($payload){

        $order = wc_get_order( $payload['order_id'] );
        $settingRepo = (new \Inc\Repositories\SettingRepository())->getSettingByKey('origin_sub_district_id');
        $transaction = (new \Inc\Repositories\TransactionRepository())->getTransactionByWCOrderId($payload['order_id']);

        $get_payment_method = $order->get_payment_method();
    
        $insurance = 0;
        foreach($order->get_meta_data() as $key => $value) {
            if( $value->get_data()['key'] == '_kj_insurance' ){
                $insurance = $value->get_data()['value'];
            }
        }

        $insurance = intval($insurance) == 0 ? empty($payload['kj_insurancefee_hidden']) ? 0 : $payload['kj_insurancefee_hidden'] : $insurance;

        $weight = 0; $length = 0;$width=0; $height=0;
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $weight += $product->get_weight();
            $length += $product->get_length();
            $width  += $product->get_width();
            $height += $product->get_height();
        }
    
        /** convert unit weight */
        $cartAttributes = (new \Inc\Services\UtilServices\GetWCCartAttributeService([
            'wc_cart_contents' => $order->get_items()
        ]))->call();

        $courier = explode('_',$payload['kj_expedition']);
    
        $pricingPayload = [
            'subdistrict_origin'        => (int) $settingRepo->value,
            'subdistrict_destination'   => (int) $payload['kj_subdistrict'],
            'weight'                    => (int) $cartAttributes->data['weight'],
            "length"                    => (int) $length ,
            "width"                     => (int) $width,
            "height"                    => (int) $height,
            'insurance'                 => (int) $insurance,
            'item_value'                => $order->get_subtotal(),
            'courier'                   => [$courier[0]]
        ];

        $kjPricing = (new \Inc\Repositories\KiriminajaApiRepository())->getPricing($pricingPayload);

        if( $kjPricing['data'] ){
            $result_pricing = $kjPricing['data']->results;
                      
            $data_shipping_selected = [];
            foreach($result_pricing as $row ){
                
                if( $row->service_type != $courier[1]){
                    continue;
                }

                $data_shipping_selected = $row;
            }
            

            $checkoutCalculation = $this->kj_checkoutCalculation(
                $order->get_subtotal(),
                $data_shipping_selected,
                $get_payment_method,
                $insurance
            );


            if( !empty($payload['action']) && $payload['action'] == 'kj_calculation_CodFeeAndInsuranceFee' ){
                return $checkoutCalculation;
            }

            /** Validasi Transaction*/
            if( empty($transaction) ) return true;

            $payloads = [
                'destination_sub_district_id' =>(int) $payload['kj_subdistrict'],
                'destination_sub_district' => (int) $payload['kj_subdistrict_name'],
                'service' =>$courier[0],
                'service_name' =>$courier[1],
                'shipping_cost' => $payload['kj_expedition_cost'],
                'insurance_cost' => !empty($insurance) ? $checkoutCalculation['insurance_amt'] : 0,
                'cod_fee' => ($order->get_payment_method() == 'cod' ) ? $checkoutCalculation['cod_amt'] : 0,
                'wp_wc_order_stat_order_id'=>(int) $payload['order_id'],
            ];

            $updateTransactionRepo = (new \Inc\Repositories\TransactionRepository())->updateTransaction($payloads);
            
            (new \Inc\Base\BaseInit())->logThis('update_save_post_shop_order',[$updateTransactionRepo]);

            return $updateTransactionRepo;
        }
    
    }
    
    public function kj_admin_calculateCOD($total_order,$data_pricing){
        $selected_expedition = $data_pricing;
        $cartTotal = $total_order;
        $ongkirFee = intval(@$selected_expedition->cost ?? 0);
        $insuranceFee = $this->kj_admin_calculateInsuranceFee($total_order,$data_pricing);
        $codRate = floatval(@$selected_expedition->setting->cod_fee ?? 0.0);
        $CODMinCost = intval(@$selected_expedition->setting->minimum_cod_fee ?? 0);
        
        $codFee=($cartTotal+$ongkirFee+$insuranceFee)*$codRate;
        $codFee = $codFee < $CODMinCost ? $CODMinCost : $codFee;
    
        return ceil($codFee);
    }
    
    public function kj_admin_calculateInsuranceFee($total_order,$data_pricing){
        $cartTotal = $total_order;
        $selected_expedition = $data_pricing;
        $insuranceRate = floatval(@$selected_expedition->setting->insurance_fee ?? 0.0);
        $insuranceAddCost = intval(@$selected_expedition->setting->insurance_add_cost ?? 0);
        $insuranceMinCost = intval(@$selected_expedition->setting->insurance_minimum_cost ?? 0);
        $ongkirFee = intval(@$selected_expedition->cost ?? 0);
        
        $insuranceFee = (($cartTotal+$ongkirFee)*$insuranceRate)+$insuranceAddCost;
        $insuranceFee = $insuranceFee < $insuranceMinCost ? $insuranceMinCost : $insuranceFee;
        
        return ceil($insuranceFee);
    }
    
    public function kj_checkoutCalculation($total_order,$data_pricing,$payment_method,$insurance){
        $cartTotal = $total_order;
        $is_cod = $payment_method;
        $is_insurance = $insurance;
        $selected_expedition = $data_pricing;
        $insurance_amt = $this->kj_admin_calculateInsuranceFee($total_order,$data_pricing);
        $cod_amt = $this->kj_admin_calculateCOD($total_order,$data_pricing);
        $ongkirFee = intval(intval(@$selected_expedition->cost ?? 0) - intval(@$selected_expedition->discount_amount ?? 0));
        $total_amt = $ongkirFee+$cod_amt+$insurance_amt+$cartTotal;
        return [
            'cart_total_amt' => $cartTotal,
            'cod_amt' => $cod_amt,
            'insurance_amt' => $insurance_amt,
            'ongkir_fee_amt' => $ongkirFee,
            'calc_total_amt' => $total_amt,
            'selected_expedition' => $selected_expedition,
        ];
    }

    public function kj_admin_billing_fields($billing_fields){
        unset($billing_fields['city']);
        unset($billing_fields['postcode']);
        unset($billing_fields['state']);
        unset($billing_fields['address_2']);
        unset($billing_fields['country']);

        $order = wc_get_order();
        $data  = $order->get_data(); // The Order data

        $transaction = (new \Inc\Repositories\TransactionRepository())->getTransactionByWCOrderId($order->get_id());

        $billing_first_name = $order->get_billing_first_name();

        $destination_area_id = empty($order->get_meta('_billing_kj_destination_area',true) ) ? !empty($transaction) ? $transaction->destination_sub_district_id : '' : $order->get_meta('_billing_kj_destination_area');
        $destination_area_name = empty($order->get_meta('_billing_kj_destination_name',true)) ? !empty($transaction) ? $transaction->destination_sub_district : '' : $order->get_meta('_billing_kj_destination_name');
        
        $billing_fields['address_1'] = array(
            'label' => __( 'Address', 'kiriminaja' ),
            'show' => false,
            'wrapper_class' => 'form-field-wide',
        );

        $billing_fields['kj_destination_area'] = array(
            'label' => __( 'Subdistrict', 'kiriminaja' ),
            'show'  => false,
            'wrapper_class' => 'form-field-wide',
            'style' => '',
            'type' => 'select',
            'options' => array($destination_area_id=>$destination_area_name)
        );

        $billing_fields['kj_destination_name'] = array(
            'label' => __( '', 'kiriminaja' ),
            'show'  => false,
            'wrapper_class' => 'form-field-wide',
            'style' => '',
            'type' => 'hidden',
        );

        $billing_fields['kj_insurance'] = array(
            'label' => __( 'Insurance', 'kiriminaja' ),
            'show'  => true,
            'wrapper_class' => 'form-field-wide',
            'style' => '',
            'type' => 'checkbox'
        );


        $ordered_fields = [
            'first_name' => $billing_fields['first_name'], 
            'last_name' => $billing_fields['last_name'],
            'email' => $billing_fields['email'],
            'phone' => $billing_fields['phone'],
            'company' => $billing_fields['company'],
            'kj_destination_area' => $billing_fields['kj_destination_area'],
            'kj_destination_name' => $billing_fields['kj_destination_name'],
            'address_1' => $billing_fields['address_1'], // First position
            'kj_insurance' => $billing_fields['kj_insurance'],
        ];

        return $ordered_fields;
    }

    public function kj_admin_shipping_fields($shipping_fields){
        
        unset($shipping_fields['city']);
        unset($shipping_fields['postcode']);
        unset($shipping_fields['state']);
        unset($shipping_fields['address_2']);
        unset($shipping_fields['country']);

        $order = wc_get_order();
        $data  = $order->get_data(); // The Order data
        $destination_area_id = $order->get_meta('_shipping_kj_destination_area',true);
        $destination_area_name = $order->get_meta('_shipping_kj_destination_name',true);
    
        $shipping_fields['address_1'] = array(
            'label' => __( 'Address', 'kiriminaja' ),
            'show' => false,
            'wrapper_class' => 'form-field-wide',
        );

        $shipping_fields['kj_destination_area'] = array(
            'label' => __( 'Subdistrict', 'kiriminaja' ),
            'show'  => false,
            'wrapper_class' => 'form-field-wide',
            'style' => '',
            'type' => 'select',
            'options' => array($destination_area_id=>$destination_area_name)
        );

        $shipping_fields['kj_destination_name'] = array(
            'label' => __( '', 'kiriminaja' ),
            'show'  => false,
            'wrapper_class' => 'form-field-wide',
            'style' => '',
            'type' => 'hidden',
        );

        $shipping_fields['kj_insurance'] = array(
            'label' => __( 'Insurance', 'kiriminaja' ),
            'show'  => true,
            'wrapper_class' => 'form-field-wide',
            'style' => '',
            'type' => 'checkbox'
        );

        
        $ordered_fields = [
            'first_name' => $shipping_fields['first_name'], 
            'last_name' => $shipping_fields['last_name'],
            'phone' => $shipping_fields['phone'],
            'company' => $shipping_fields['company'],
            'kj_destination_area' => $shipping_fields['kj_destination_area'],
            'kj_destination_name' => $shipping_fields['kj_destination_name'],
            'address_1' => $shipping_fields['address_1'], // First position
            'kj_insurance' => $shipping_fields['kj_insurance'],
        ];

        return $ordered_fields;

    }

    public function kj_add_cod_fee_insurance_fee($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
    
        $insurance = $order->get_meta('_kj_insurance_fee') ?? 0;
        $cod = $order->get_meta('_kj_cod_fee') ?? 0;
    
        $cod_style = empty($cod) ? 'none' : 'show';
        $insurance_style = empty($insurance) ? 'none' : 'show';

        $shipping_insurance = get_post_meta( $order_id, '_shipping_kj_insurance',true);
        $billing_insurance = get_post_meta( $order_id, '_billing_kj_insurance',true);

        $insurance_post = $shipping_insurance ?: $billing_insurance;
        
        $table = '';
        if( $order->get_payment_method() === 'cod' ){
            $table .= '<tr class="codfee" style="display:' . esc_attr($cod_style) . ';">
            <td class="label">' . esc_html__('Cod Fee', 'kiriminaja') . ':</td>
            <td width="1%"></td>
            <td class="total">' . wc_price($cod) . '</td>
            </tr>';
        }else{
            $table .= '<tr class="codfee" style="display:' . esc_attr($cod_style) . ';">
            <td class="label">' . esc_html__('Cod Fee', 'kiriminaja') . ':</td>
            <td width="1%"></td>
            <td class="total">' . wc_price(0) . '</td>
            </tr>';
        }

        if( $insurance_post === 'yes' ){
            $table .= '<tr class="insurancefee" style="display:' . esc_attr($insurance_style) . ';">
            <td class="label">' . esc_html__('Insurance Fee', 'kiriminaja') . ':</td>
            <td width="1%"></td>
            <td class="total">' . wc_price($insurance) . '</td>
            </tr>';
        }else{
            $table .= '<tr class="insurancefee" style="display:' . esc_attr($insurance_style) . ';">
            <td class="label">' . esc_html__('Insurance Fee', 'kiriminaja') . ':</td>
            <td width="1%"></td>
            <td class="total">' . wc_price(0) . '</td>
            </tr>';
        }
    
        echo $table;
    }    

    public function kj_calculationCodFeeAndInsuranceFee(){

        $get_calculate_pricing = $this->kj_calculationAdminOrder($_POST);

        if( empty($get_calculate_pricing) ){
            wp_send_json_error(['code'=>'404','message'=>'Calculation Failed']);
        }

        $cod_fee        = wc_price($get_calculate_pricing['cod_amt']) ?? 0;
        $insurance_fee  = wc_price($get_calculate_pricing['insurance_amt']) ?? 0;

        wp_send_json_success([
            'cod_fee' => $cod_fee,
            'insurance_fee' => $insurance_fee,
            'cod_fee_number'=>$get_calculate_pricing['cod_amt'],
            'insurance_fee_number'=>$get_calculate_pricing['insurance_amt'],
        ]);

    }

    public function kj_save_post_shop_order($post_id, $post, $update){
        try{
            if ( 'shop_order' !== $post->post_type || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
                return;
            }
           
            // Mendapatkan instance order WooCommerce
            $order = wc_get_order($post_id);

            if (!$order) {
                return;
            }

            $request = $_POST;

            if ( isset( $request['_shipping_kj_insurance'] ) && ! empty( $request['_shipping_kj_insurance'] ) ) {
                update_post_meta( $post_id, '_shipping_kj_insurance', sanitize_text_field( $request['_shipping_kj_insurance'] ) );
            }
            
            if ( isset( $request['_billing_kj_insurance'] ) && ! empty( $request['_billing_kj_insurance'] ) ) {
                update_post_meta( $post_id, '_billing_kj_insurance', sanitize_text_field( $request['_billing_kj_insurance'] ) );
            }

        } catch (\Throwable $th){
            ( new \Inc\Base\BaseInit() )->logThis( 'save_post_shop_order', [
                'error' => $th->getMessage(),
                'file'  => $th->getFile(),
                'line'  => $th->getLine(),
                'trace' => $th->getTraceAsString()
            ]);
        }
        
    }

    public function kj_wc_admin_create_order($order_id){
        try{

            if ( ! $order_id || 'shop_order' !== get_post_type( $order_id ) ) {
                return;
            }            
           
            $order = wc_get_order($order_id);
            if ( ! $order ) {
                return;
            }

            $transaction = (new \Inc\Repositories\TransactionRepository())->getTransactionByWCOrderId($order_id);
            
            /** Validasi Transaction dan status pesanan*/
            if ( ! $order || $order->get_status() !== 'processing' || ! empty( $transaction ) ) {
                return true;
            }

            $request = $_POST;

            $insurance = get_post_meta($order_id,'_kj_insurance_fee',true);
            $cod = get_post_meta( $order_id, '_kj_cod_fee', true );
            $expedition_code = get_post_meta( $order_id, '_kj_expedition_code',true);
            $shipping_insurance = get_post_meta( $order_id, '_shipping_kj_insurance',true);
            $billing_insurance = get_post_meta( $order_id, '_billing_kj_insurance',true);

            $request['kj_subdistrict_name'] = get_post_meta($order_id,'_kj_subdistrict_name',true) ?? '';
            $request['kj_codfee_hidden'] = $cod ?? 0 ;
            $request['kj_insurancefee_hidden'] = $insurance ?? 0;
            $request['kj_expedition'] = $expedition_code ?? '';
            $request['_shipping_kj_insurance'] = $shipping_insurance === 'yes';
            $request['_billing_kj_insurance'] = $billing_insurance === 'yes';            
            $request['kj_subdistrict'] = (int) get_post_meta( $order_id, '_kj_subdistrict_id',true) ?: 0;

            $payment_method = $order->get_payment_method();

            $insurance_post = $request['_shipping_kj_insurance'] ?: $request['_billing_kj_insurance'];

            $payloads = [
                'order_id'                  => $order_id,
                'checkout_post_data'        => $request,
                'kj_destination_area'       => $request['kj_subdistrict'],
                'kj_destination_area_name'  => $request['kj_subdistrict_name'],
                'kj_expedition'             => $request['kj_expedition'],
                'is_insurance'              => $insurance_post,
                'is_cod'                    => $payment_method === 'cod',
                'wc_cart_contents'          => $order->get_items()
            ];


            $createTransaction = (new \Inc\Services\CheckoutServices\CreateTransactionService($payloads))->call();
            (new \Inc\Base\BaseInit())->logThis('create_order_shop_order',[$createTransaction]);

        } catch (\Throwable $th){
            (new \Inc\Base\BaseInit())->logThis('create_order_shop_order',[
                'error'   => $th->getMessage(),
                'file'    => $th->getFile(),
                'line'    => $th->getLine(),
                'trace'   => $th->getTraceAsString()
            ]);
        }
        
    }

    public function kj_ajaxCheckProductItemOrder(){
        
        if ( empty( $_POST['nonce'] ) && !wp_verify_nonce( $_POST['nonce'], 'kj-nonce' ) ) {
            wp_send_json_error( ['code' => 403, 'message' => __('Invalid nonce', 'kiriminaja')] );
            wp_die();
        }

        if ( empty( $_POST['order_id'] ) || ! is_numeric( $_POST['order_id'] ) ) {
            wp_send_json_error( ['code' => 400, 'message' => __('Invalid order ID', 'kiriminaja')] );
            wp_die(); 
        }
        
        $order = wc_get_order( intval( $_POST['order_id'] ) );
        

        if ($order) {
            if( !empty($order->get_items()) ){
                if( !empty($order->get_items('shipping')) ){
                    wp_send_json_success( ['code'=>200,'shipping'=>true,'message' => __('Shipping Founded', 'kiriminaja')] );
                }else{
                    wp_send_json_success( ['code'=>200,'shipping'=>false,'message' => __('Data Order Product Item Founded', 'kiriminaja')] );
                }
            }else{
                wp_send_json_error(['code'=>201,'message' => __('Order Product Items Not Found', 'kiriminaja')]);
            }
        }else{
            wp_send_json_error( ['code' => 404, 'message' => __('Order not found', 'kiriminaja')] );
        }

        wp_die();
    }

    public function kj_order_status_change_action($order_id, $old_status, $new_status, $order){
        try {
            $transaction = (new \Inc\Repositories\TransactionRepository())->getTransactionByWCOrderId($order_id);
            
            if ( !empty( $transaction ) ) return true;
            
            $order = wc_get_order( $order_id );
        
            $insurance = get_post_meta($order_id,'_kj_insurance_fee',true) ?? 0;
            $cod = get_post_meta( $order_id, '_kj_cod_fee', true ) ?? 0;
            $expedition_code = get_post_meta( $order_id, '_kj_expedition_code',true);
            $shipping_insurance = get_post_meta( $order_id, '_shipping_kj_insurance',true);
            $billing_insurance = get_post_meta( $order_id, '_billing_kj_insurance',true);
    
            $kj_subdistrict_name = get_post_meta($order_id,'_kj_subdistrict_name',true) ?? '';
            $kj_codfee_hidden = $cod ?? 0 ;
            $kj_insurancefee_hidden = $insurance ?? 0;
            $kj_expedition = $expedition_code ?? '';
            $_shipping_kj_insurance = $shipping_insurance === 'yes';
            $_billing_kj_insurance = $billing_insurance === 'yes';            
            $kj_subdistrict = (int) get_post_meta( $order_id, '_kj_subdistrict_id',true) ?: 0;
    
            $payment_method = $order->get_payment_method();
    
            $insurance_post = $_shipping_kj_insurance ?: $_billing_kj_insurance;
            
            $payloads = [
                'order_id'                  => $order_id,
                'checkout_post_data'        => $order,
                'kj_destination_area'       => $kj_subdistrict,
                'kj_destination_area_name'  => $kj_subdistrict_name,
                'kj_expedition'             => $kj_expedition,
                'is_insurance'              => $insurance_post,
                'is_cod'                    => $payment_method === 'cod',
                'wc_cart_contents'          => $order->get_items()
            ];
            
            if ( 'processing' === $new_status ) {
                $createTransaction = (new \Inc\Services\CheckoutServices\CreateTransactionService($payloads))->call();
                
                (new \Inc\Base\BaseInit())->logThis('create_order_shop_order', [$createTransaction]);
            }
        } catch (Exception $e) {
            (new \Inc\Base\BaseInit())->logThis('change_order_woocommerce',[
                'error'   => $th->getMessage(),
                'file'    => $th->getFile(),
                'line'    => $th->getLine(),
                'trace'   => $th->getTraceAsString()
            ]);        
        }
    }

    public function kj_buttonShippingAdminOrder( $order ){
        echo '<button type="button" class="button add-order-shipping shippingkiriminaja" data-tip="Add Shipping">'.__('Add Shipping','kiriminaja').'</a>';
    }
    
}