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
            
            // Check for nonce security      
            if ( isset($_POST['data']['nonce']) && ! wp_verify_nonce(  sanitize_text_field( wp_unslash($_POST['data']['nonce'])), KJ_NONCE ) ) {
                wp_send_json_error(['status'=>400,'message'=>wc_add_notice('Security Check Kiriminaja', "error")]);
                wp_die();
            }

            $setup_key = isset($_POST['data']['setup_key']) ? sanitize_text_field( wp_unslash($_POST['data']['setup_key'])) : '';
            $service = (new \Inc\Services\SettingService())->processingSetupKey($setup_key);

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

            // Check for nonce security      
            if ( isset($_POST['data']['nonce']) && ! wp_verify_nonce(  sanitize_text_field( wp_unslash($_POST['data']['nonce'])), KJ_NONCE ) ) {
                wp_send_json_error(['status'=>400,'message'=>wc_add_notice('Security Check Kiriminaja', "error")]);
                wp_die();
            }

            if( !isset($_POST['data']['origin_whitelist_expedition_id'])){
                $_POST['data']['origin_whitelist_expedition_id']  = '';
                $_POST['data']['origin_whitelist_expedition_name'] = '';
            }

            $data = isset($_POST['data']) ? wp_unslash($_POST['data']) : [];  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            
            $service = (new \Inc\Services\SettingService())->storeOriginData($data);

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
            
            if ( isset($_POST['data']['nonce']) && ! wp_verify_nonce(  sanitize_text_field( wp_unslash($_POST['data']['nonce'])), KJ_NONCE ) ) {
                wp_send_json_error(['status'=>400,'message'=>wc_add_notice('Security Check Kiriminaja', "error")]);
                wp_die();
            }

            $data = isset($_POST['data']) ? wp_unslash($_POST['data']) : [];  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

            $service = (new \Inc\Services\SettingService())->storeCallbackData($data);

            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }

    function storeWhitelistExpedition(){
        try {

            // Check for nonce security      
            if ( isset($_POST['data']['nonce']) && ! wp_verify_nonce(  sanitize_text_field( wp_unslash($_POST['data']['nonce'])), KJ_NONCE ) ) {
                wp_send_json_error(['status'=>400,'message'=>wc_add_notice('Security Check Kiriminaja', "error")]);
                wp_die();
            }

            $search = isset( $_POST['data']['term'] ) ? sanitize_text_field( wp_unslash( $_POST['data']['term'] )) : '';
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