<?php
namespace Inc\Controllers;

use Inc\Services\ShippingProcessServices\GetShippingProcessDetailService;

class ShippingProcessController{


    public function register(){
        /** getIntegrationData*/
        add_action('wp_ajax_kj_get_shipping_process_detail', array($this,'getShippingProcessDetail'));
        add_action('wp_ajax_nopriv_kj_get_shipping_process_detail', array($this,'getShippingProcessDetail'));
        
    }
    function getShippingProcessDetail() {
        try {
            $service = (new GetShippingProcessDetailService())->paymentId($_POST['data']['payment_id'])->call();
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,$e->getMessage()]);
        }
    }
}