<?php
namespace Inc\Controllers;

class GeneralAjaxController{

    public function register(){
        add_action('wp_ajax_kiriminaja_subdistrict_search', array($this,'kiriminajaSubdistrictSearch'));
        add_action('wp_ajax_nopriv_kiriminaja_subdistrict_search', array($this,'kiriminajaSubdistrictSearch'));
                
       add_action('wp_ajax_getDestinationArea', array($this,'kj_getDestinationArea') );
       add_action('wp_ajax_nopriv_getDestinationArea', array($this,'kj_getDestinationArea') );

       add_action('wp_ajax_kj_get_data_after_update_checkout', array($this,'kj_getDataAfterUpdateCheckout'));
       add_action('wp_ajax_nopriv_kj_get_data_after_update_checkout', array($this,'kj_getDataAfterUpdateCheckout'));
    
    }
    
    function kiriminajaSubdistrictSearch() {
        $data = $_POST['data'];

        if( empty($data['search']) ){
            $data['search'] = sanitize_text_field($data['term']);
        }
        
        try {
            $kiriminajaSubDistrictSearch = (new \Inc\Services\KiriminajaApiService())->sub_district_search($data['search']);
            if ($kiriminajaSubDistrictSearch->status!==200){wp_send_json_success([]);}
            wp_send_json_success($kiriminajaSubDistrictSearch->data);
            wp_die();
        }catch (Throwable $e){
            wp_send_json_success([]);
            wp_die();
        }
    }
    
function kj_getDestinationArea(){

        // Check for nonce security      
        if ( ! wp_verify_nonce( $_POST['nonce'], 'kj-destination' ) ) {
            wp_send_json_success(['code'=>'401','msg'=>wc_add_notice('Security Check Kiriminaja', "error")]);
            wp_die();
        }
        
        $post = $_POST;
        
        if( is_checkout()){
            if( empty($post['country']) || $post['country'] != 'ID' ){
                wp_send_json_success(['code'=>'400','msg'=>wc_add_notice('Please Country/Region Indonesia', "error")]);
                wp_die();
            }
        }

        $destination_id = (int) $post['val'];

        $payment = !empty($post['payment_method']) ? $post['payment_method'] : null;
        
        if( !empty($post['different_address']) ){
            WC()->session->set( 'shipping_destination_id', $destination_id );
            WC()->session->set( 'shipping_destination_name', sanitize_text_field($post['text']) );
        }
        // Set the data (the value can be also an indexed array)
        WC()->session->set( 'destination_id', $destination_id );
        WC()->session->set( 'destination_name', sanitize_text_field($post['text']) );
        WC()->session->set( 'kj_payment_method', sanitize_text_field( $payment ) );
        WC()->session->set( 'kj_insurance', $_POST['insurance'] );

        WC()->cart->calculate_totals();

        wp_send_json_success(['code'=>'200','msg'=>'Success']);
        
    }

    public function kj_getDataAfterUpdateCheckout(){
        $post = $_POST;

        $check_shipping = $post['shipping_metode_id'] ?? '';
        $ex_shipping = explode('_',$check_shipping);

        $datas = [];
        if( !empty($post['shipping_metode_id']) && $ex_shipping[0] == 'kiriminaja' ){
            $insurance = empty($post['insurance']) ? 0 : 1;

            $payload =[
                'destination_area_id'   => (int)$post['destination_id'],
                'expedition'            => substr($post['shipping_metode_id'],11),
                'is_insurance'          => $insurance,
                'is_cod'                => $post['payment_method'] === 'cod',
                'wc_cart_contents'      => WC()->cart->cart_contents,
            ];

            $service = (new \Inc\Services\CheckoutServices\CheckoutCalculationService($payload))->call();
            
            if( !empty($service->data) ){
                
                if( !empty($post['payment_method'])  ){
                    $datas['cod_fee'] = wc_price($service->data['calculation_result']['cod_amt']) ??  0;
                    $datas['is_cod_amt'] = $service->data['calculation_result']['cod_amt'];
                }
    
                if( !empty($post['shipping_metode_id'])  ){
                    $datas['insurance_fee'] = wc_price($service->data['calculation_result']['insurance_amt']) ?? 0;
                    $datas['is_insurance'] = $service->data['calculation_result']['insurance_amt'];
                }

                if( !empty($post['payment_method']) || !empty($post['shipping_metode_id']) ){
                    $cod_amt = (float) $service->data['calculation_result']['cod_amt'] ?? 0;
                    $insurance_amt = (float) $service->data['calculation_result']['insurance_amt'] ?? 0;
                    $order_total = (float) WC()->cart->get_total('raw');

                    $datas['price_total'] = wc_price( $cod_amt + $insurance_amt + $order_total );
                }

                $datas['force_insurance'] = $service->data['calculation_result']['selected_expedition']->force_insurance == false ? 0 : 1;

                $datas['services'] = $service->data;
                
                WC()->cart->calculate_totals();
                
                wp_send_json_success( $datas );
            }else{
                
                WC()->cart->calculate_totals();

                wp_send_json_error( ['is_insurance'=>0,'is_cod_amt'=>0] );
            }
        }

        WC()->cart->calculate_totals();

        wp_send_json_error( ['is_insurance'=>0,'is_cod_amt'=>0] );

    }
}