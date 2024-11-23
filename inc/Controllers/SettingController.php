<?php
namespace Inc\Controllers;

use Throwable;

class SettingController{


    public function register(){
        /** getIntegrationData*/
        add_action('wp_ajax_kj_get_integration_data', array($this,'getIntegrationData'));
        add_action('wp_ajax_nopriv_kj_get_integration_data', array($this,'getIntegrationData'));
        
        /** storeIntegrationData*/
        add_action('wp_ajax_kj_store_integration_data', array($this,'storeIntegrationData'));
        add_action('wp_ajax_nopriv_kj_store_integration_data', array($this,'storeIntegrationData'));
        
        /** storeIntegrationData*/
        add_action('wp_ajax_kj_disconnect_integration', array($this,'disconnectIntegration'));
        add_action('wp_ajax_nopriv_kj_disconnect_integration', array($this,'disconnectIntegration'));
        
        /** storeIntegrationData*/
        add_action('wp_ajax_kj_get_origin_data', array($this,'getOriginData'));
        add_action('wp_ajax_nopriv_kj_get_origin_data', array($this,'getOriginData'));
        
        /** storeIntegrationData*/
        add_action('wp_ajax_kj_store_origin_data', array($this,'storeOriginData'));
        add_action('wp_ajax_nopriv_kj_store_origin_data', array($this,'storeOriginData'));
        
        /** storeIntegrationData*/
        add_action('wp_ajax_kj_get_call_back_data', array($this,'getCallbackData'));
        add_action('wp_ajax_nopriv_kj_get_call_back_data', array($this,'getCallbackData'));
        
        /** storeCallbackData*/
        add_action('wp_ajax_kj_store_call_back_data', array($this,'storeCallbackData'));
        add_action('wp_ajax_nopriv_kj_store_call_back_data', array($this,'storeCallbackData'));

        /**storeWhitelistExpedition*/
        add_action('wp_ajax_kiriminaja_search_expedition', array($this,'storeWhitelistExpedition'));
        
    }
    function getIntegrationData() {
        try {
            $service = (new \Inc\Services\SettingService())->getIntegrationData();
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    function storeIntegrationData() {
        try {
            $service = (new \Inc\Services\SettingService())->processingSetupKey($_POST['data']['setup_key']);
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    
    function disconnectIntegration(){
        try {
            $service = (new \Inc\Services\SettingService())->disconnectIntegration();
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    
    function getOriginData(){
        try {
            $service = (new \Inc\Services\SettingService())->getOriginData();
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    
    function storeOriginData(){
        try {
            if( !isset($_POST['data']['origin_whitelist_expedition_id'])){
                $_POST['data']['origin_whitelist_expedition_id']  = '';
                $_POST['data']['origin_whitelist_expedition_name'] = '';
            }

            $service = (new \Inc\Services\SettingService())->storeOriginData($_POST['data'] ?? []);
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    
    function getCallbackData(){
        try {
            $service = (new \Inc\Services\SettingService())->getCallbackData();

            
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    
    function storeCallbackData(){
        try {
            $service = (new \Inc\Services\SettingService())->storeCallbackData($_POST['data'] ?? []);
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }

    function storeWhitelistExpedition(){
        try {
            $search = $_POST['data']['term'] ?? '';
            $kiriminajaExpedition = (new \Inc\Services\KiriminajaApiService())->get_couriers();
            
            if( !empty($kiriminajaExpedition ) ){
                $kiriminajaExpedition = array_filter($kiriminajaExpedition->data, function($item) use ($search){
                    return stripos($item->name, $search)!== false;
                });
                
                $kiriminajaExpedition = array_map(function($item){
                    return [
                        'id' => $item->code,
                        'text' => $item->name." ({$item->type})"
                    ];
                }, $kiriminajaExpedition);  
            }
            
            wp_send_json_success($kiriminajaExpedition);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
}