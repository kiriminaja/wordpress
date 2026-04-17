<?php
namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EditOrderController{
    private $nonce = KIRIOF_NONCE ;
    public function register(){
        add_action( 'woocommerce_admin_order_totals_after_total', array($this,'addKiriofOrderDetail'));
        add_filter( 'wc_order_is_editable', array($this,'kiriof_custom_order_status_editable'), 9999, 2 );
    }
    public function addKiriofOrderDetail($order){        
        $service = (new \KiriminAjaOfficial\Services\OrderEditPageServices\ShippingInfoServices())->wcOrderId($order)->call();
        if ($service->status !== 200){return;}
        
        $orderId = esc_html($order);
        $trackingUrl = esc_url( home_url().'/tracking?order_id='.$order);
        $kiriofOrderData = wp_json_encode($service->data);
    
        include_once KIRIOF_DIR .'/templates/order/edit.php';
    
    }
    public function kiriof_custom_order_status_editable($allow_edit, $order){
        if ( $order->get_status() === 'processing' ) {
            $allow_edit = true;
        }
        return $allow_edit;
    }
    
    public function kiriof_getExpeditionByPricing(){
        // Check for nonce security - fail early
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), $this->nonce ) ) {
            wp_die( esc_html__( 'Security check failed', 'kiriminaja-official' ) );
        }
        $order_id       = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
        $destination_id = isset( $_POST['destination_id'] ) ? (int) $_POST['destination_id'] : 0;
        $settingRepo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('origin_sub_district_id');
        
        $order = wc_get_order( $order_id );
        
        $weight = 0; $width = 0; $height = 0; $length = 0;
        foreach( $order->get_items() as $item ){
            $product = $item->get_product();
            if ( ! $product ) { continue; }
            $weight += $product->get_weight() * $item->get_quantity(); 
            $length = max($length, $product->get_length());
            $width = max($width, $product->get_width());
            $height = max($height, $product->get_height());
        }
        $payload = [
            'subdistrict_origin'        => (int) $settingRepo->value,
            'subdistrict_destination'   =>$destination_id,
            'weight'    => $weight,
            'length'    => $length,
            'width'     => $width,
            'height'    => $height,
            'insurance' => 1,
            'item_value'=> $order->get_subtotal(),
            'courier'   => null, // 'jne', 'pos', 'tiki', 'jet'
        ];
        $kiriofPricing = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getPricing($payload);
        
        $return = $this->filterOptions($kiriofPricing['data']);
        if( !$return ){
            wp_send_json_error( ['code'=>'404','message'=>'Expedition Not Found'] );
        }
        wp_send_json_success($return);
    }
    public function filterOptions($pricingData){
        $is_cod = !empty( WC()->session->get( 'kiriof_payment_method' ) ) ? true : false;
        $options = $pricingData->results ?? [];
        
        $validate = (new \KiriminAjaOfficial\Repositories\SettingRepository())->validateWhiteListExpedition($options);
        $options = $validate;
        
        $filteredOptions = [];
        foreach ($options as $option){
            if (!$is_cod || $is_cod && $option->cod){
                $filteredOptions[] = [
                    'key'=>$option->service.'_'.$option->service_type,
                    'value'=>$option->service_name.' (Rp'.(kiriof_money_format($option->cost)).')',
                    'cost'=>$option->cost
                ];    
            }
        }
        return $filteredOptions;
    }
    public function kiriof_wc_save_order_items($order_id, $items){
        //code logic here WC Update
        $order = wc_get_order( $order_id );
        if( array_shift($items['shipping_method']) == 'kiriminaja-official' ){
            
            if(!empty($items['kiriof_expedition_name']))
            {
                $expediton_cost = !$items['kiriof_expedition_cost'] ? 0 : $items['kiriof_expedition_cost'];    
                
                $qty = 0;
                foreach( $order->get_items() as $item_id_shipping => $item ) {
                    $qty += (int) $item->get_quantity();
                }
                $item_price = (int) $expediton_cost * $qty;
                $calculate_data = $this->kiriof_calculationAdminOrder([
                    'order_id' => $order_id,
                    'kiriof_subdistrict'=>$items['kiriof_subdistrict'],
                    'kiriof_subdistrict_name'=>$items['kiriof_subdistrict_name'],
                    'kiriof_expedition'=>$items['kiriof_expedition'],
                    'kiriof_expedition_name'=>$items['kiriof_expedition_name'],
                    'kiriof_expedition_cost'=>$item_price,
                ]);
    
                foreach( $order->get_items('shipping') as $item_id_shipping => $item ) {
                    
                    $item->set_method_title( $items['kiriof_expedition_name'] ); 
                    $item->set_name( $items['kiriof_expedition_name'] );
    
                    $item->set_total( $item_price );
                    $item->calculate_taxes();
                    $item->save();
    
                }   
                
                /* Simpan Di Post Meta */
                update_post_meta($order_id,'_kiriof_subdistrict_id',$items['kiriof_subdistrict']);
                update_post_meta($order_id,'_kiriof_subdistrict_name',$items['kiriof_subdistrict_name']);
                update_post_meta($order_id,'_kiriof_expedition_code',$items['kiriof_expedition']);
                update_post_meta($order_id,'_kiriof_expedition_name',$items['kiriof_expedition_name']);
                update_post_meta($order_id,'_kiriof_expedition_cost',$items['kiriof_expedition_cost']);
                update_post_meta($order_id,'_kiriof_insurance_fee',$items['kiriof_insurancefee_hidden']);
                update_post_meta($order_id,'_kiriof_cod_fee',$items['kiriof_codfee_hidden']);
            }        
        } 
    
        $order->calculate_shipping();
        $order->calculate_totals();
    
    }
    public function kiriof_calculationAdminOrder($payload){
        $order = wc_get_order( $payload['order_id'] );
        $settingRepo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('origin_sub_district_id');
        $transaction = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransactionByWCOrderId($payload['order_id']);
        $get_payment_method = $order->get_payment_method();
    
        $insurance = 0;
        foreach($order->get_meta_data() as $key => $value) {
            if( $value->get_data()['key'] == '_kiriof_insurance' ){
                $insurance = $value->get_data()['value'];
            }
        }
        $weight = 0; $length = 0;$width=0; $height=0;
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product ) { continue; }
            $weight += $product->get_weight();
            $length += $product->get_length();
            $width  += $product->get_width();
            $height += $product->get_height();
        }
    
        $courier = explode('_',$payload['kiriof_expedition']);
    
        $pricingPayload = [
            'subdistrict_origin'        => (int) $settingRepo->value,
            'subdistrict_destination'   => (int) $payload['kiriof_subdistrict'],
            'weight'                    => (int) $weight,
            "length"                    => (int) $length ,
            "width"                     => (int) $width,
            "height"                    => (int) $height,
            'insurance'                 => (int) $insurance,
            'item_value'                => $order->get_subtotal(),
            'courier'                   => [$courier[0]]
        ];
        $kiriofPricing = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getPricing($pricingPayload);
    
        if( $kiriofPricing['data'] ){
            $result_pricing = $kiriofPricing['data']->results;
            
            $data_shipping_selected = [];
            foreach($result_pricing as $row ){
                if( $row->service_type != $courier[1]){
                    continue;
                }
                $data_shipping_selected = $row;
            }
    
            $checkoutCalculation = $this->kiriof_checkoutCalculation(
                $order->get_subtotal(),
                $data_shipping_selected,
                $get_payment_method,
                $insurance
            );
            if( !empty($payload['action']) && $payload['action'] == 'kiriof_calculation_CodFeeAndInsuranceFee' ){
                return $checkoutCalculation;
            }
            /** Validasi Transaction*/
            if( empty($transaction) ) return true;
            $payloads = [
                'destination_sub_district_id' =>(int) $payload['kiriof_subdistrict'],
                'destination_sub_district' => (int) $payload['kiriof_subdistrict_name'],
                'service' =>$courier[0],
                'service_name' =>$courier[1],
                'shipping_cost' => $payload['kiriof_expedition_cost'],
                'insurance_cost' => !empty($insurance) ? $checkoutCalculation['insurance_amt'] : 0,
                'cod_fee' => ($order->get_payment_method() == 'cod' ) ? $checkoutCalculation['cod_amt'] : 0,
                'wp_wc_order_stat_order_id'=>(int) $payload['order_id'],
            ];
            $updateTransactionRepo = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->updateTransaction($payloads);
            
            return $updateTransactionRepo;
        }
    
    }
    
    public function kiriof_admin_calculateCOD($total_order,$data_pricing){
        $selected_expedition = $data_pricing;
        $cartTotal = $total_order;
        $ongkirFee = intval(@$selected_expedition->cost ?? 0);
        $insuranceFee = $this->kiriof_admin_calculateInsuranceFee($total_order,$data_pricing);
        $codRate = floatval(@$selected_expedition->setting->cod_fee ?? 0.0);
        $CODMinCost = intval(@$selected_expedition->setting->minimum_cod_fee ?? 0);
        
        $codFee=($cartTotal+$ongkirFee+$insuranceFee)*$codRate;
        $codFee = $codFee < $CODMinCost ? $CODMinCost : $codFee;
    
        return ceil($codFee);
    }
    
    public function kiriof_admin_calculateInsuranceFee($total_order,$data_pricing){
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
    
    public function kiriof_checkoutCalculation($total_order,$data_pricing,$payment_method,$insurance){
        $cartTotal = $total_order;
        $is_cod = $payment_method;
        $is_insurance = $insurance;
        $selected_expedition = $data_pricing;
        $insurance_amt = $this->kiriof_admin_calculateInsuranceFee($total_order,$data_pricing);
        $cod_amt = $this->kiriof_admin_calculateCOD($total_order,$data_pricing);
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
    public function kiriof_admin_billing_fields($billing_fields){
        unset($billing_fields['city']);
        unset($billing_fields['postcode']);
        unset($billing_fields['state']);
        $order = wc_get_order();
        $data  = $order->get_data(); // The Order data
        $destination_area_id = $order->get_meta('_billing_kiriof_destination_area',true);
        $destination_area_name = $order->get_meta('_billing_kiriof_destination_name',true);
        
        $billing_fields['kiriof_destination_area'] = array(
            'label' => __( 'Subdistrict', 'kiriminaja-official' ),
            'show'  => false,
            'wrapper_class' => 'form-field-wide',
            'style' => '',
            'type' => 'select',
            'options' => array($destination_area_id=>$destination_area_name)
        );
        $billing_fields['kiriof_destination_name'] = array(
            'label' => '',
            'show'  => false,
            'wrapper_class' => 'form-field-wide',
            'style' => '',
            'type' => 'hidden',
        );
        $billing_fields['kiriof_insurance'] = array(
            'label' => __( 'Insurance', 'kiriminaja-official' ),
            'show'  => true,
            'wrapper_class' => 'form-field-wide',
            'style' => '',
            'type' => 'checkbox'
        );
        
     
        return $billing_fields;
    }
    public function kiriof_admin_shipping_fields($shipping_fields){
        
        unset($shipping_fields['city']);
        unset($shipping_fields['postcode']);
        unset($shipping_fields['state']);
        $order = wc_get_order();
        $data  = $order->get_data(); // The Order data
        $destination_area_id = $order->get_meta('_shipping_kiriof_destination_area',true);
        $destination_area_name = $order->get_meta('_shipping_kiriof_destination_name',true);
    
        $shipping_fields['kiriof_destination_area'] = array(
            'label' => __( 'Subdistrict', 'kiriminaja-official' ),
            'show'  => false,
            'wrapper_class' => 'form-field-wide',
            'style' => '',
            'type' => 'select',
            'options' => array($destination_area_id=>$destination_area_name)
        );
        $shipping_fields['kiriof_destination_name'] = array(
            'label' => '',
            'show'  => false,
            'wrapper_class' => 'form-field-wide',
            'style' => '',
            'type' => 'hidden',
        );
        $shipping_fields['kiriof_insurance'] = array(
            'label' => __( 'Insurance', 'kiriminaja-official' ),
            'show'  => true,
            'wrapper_class' => 'form-field-wide',
            'style' => '',
            'type' => 'checkbox'
        );
        return $shipping_fields;
    }
    public function kiriof_add_cod_fee_insurance_fee( $order_id ) {
        $order = wc_get_order( $order_id );
        $data  = $order->get_data(); // The Order data
        $insurance = $order->get_meta('_kiriof_insurance_fee') ?? 0;
        $cod = $order->get_meta('_kiriof_cod_fee') ?? 0;
        $cod_style = ( empty($cod) ) ? 'none' : 'show';
        $insurance_style = ( empty($insurance) ) ? 'none' : 'show';
        
        $table = '<tr class="codfee" style="display:'.$insurance_style.';">
            <td class="label">'.__('Cod Fee','kiriminaja-official').':</td>
            <td width="1%"></td>
            <td class="total">'.wc_price($cod).'</td>
        </tr>
        <tr class="insurancefee" style="display:'.$cod_style.';">
            <td class="label">'.__('Insurance Fee','kiriminaja-official').':</td>
            <td width="1%"></td>
            <td class="total">'.wc_price($insurance).'</td>
        </tr>';
        echo wp_kses_post( $table );
    }
    
}