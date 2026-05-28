<?php
namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Throwable;
class SettingController{
    public function register(){
        /** getIntegrationData*/
        add_action('wp_ajax_kiriof_get_integration_data', array($this,'getIntegrationData'));
        
        /** storeIntegrationData*/
        add_action('wp_ajax_kiriof_store_integration_data', array($this,'storeIntegrationData'));
        
        /** storeIntegrationData*/
        add_action('wp_ajax_kiriof_disconnect_integration', array($this,'disconnectIntegration'));
        
        /** storeIntegrationData*/
        add_action('wp_ajax_kiriof_get_origin_data', array($this,'getOriginData'));
        
        /** storeIntegrationData*/
        add_action('wp_ajax_kiriof_store_origin_data', array($this,'storeOriginData'));
        
        /** storeIntegrationData*/
        add_action('wp_ajax_kiriof_get_call_back_data', array($this,'getCallbackData'));
        
        /** storeCallbackData*/
        add_action('wp_ajax_kiriof_store_call_back_data', array($this,'storeCallbackData'));
        /**storeWhitelistExpedition*/
        add_action('wp_ajax_kiriminaja_search_expedition', array($this,'storeWhitelistExpedition'));

        /** getConfigData*/
        add_action('wp_ajax_kiriof_get_config_data', array($this,'getConfigData'));

        /** storeConfigData*/
        add_action('wp_ajax_kiriof_store_config_data', array($this,'storeConfigData'));

        /** getProfileData*/
        add_action('wp_ajax_kiriof_get_profile_data', array($this,'getProfileData'));

        /** getCourierWhitelist*/
        add_action('wp_ajax_kiriof_get_courier_whitelist', array($this,'getCourierWhitelist'));

        /** storeCourierWhitelist*/
        add_action('wp_ajax_kiriof_store_courier_whitelist', array($this,'storeCourierWhitelist'));

        /** storeInsuranceData*/
        add_action('wp_ajax_kiriof_store_insurance_data', array($this,'storeInsuranceData'));
    }
    function getIntegrationData() {
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            // Check for nonce security - fail early
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $service = (new \KiriminAjaOfficial\Services\SettingService())->getIntegrationData();
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    function storeIntegrationData() {
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            // Check for nonce security - fail early
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $setup_key = isset($_POST['data']['setup_key']) ? sanitize_text_field( wp_unslash($_POST['data']['setup_key'])) : '';
            $service = (new \KiriminAjaOfficial\Services\SettingService())->processingSetupKey($setup_key);
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    
    function disconnectIntegration(){
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            // Check for nonce security - fail early
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $service = (new \KiriminAjaOfficial\Services\SettingService())->disconnectIntegration();
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    
    function getOriginData(){
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            // Check for nonce security - fail early
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $service = (new \KiriminAjaOfficial\Services\SettingService())->getOriginData();
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    
    function storeOriginData(){
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            // Check for nonce security - fail early
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $data = isset( $_POST['data'] ) && is_array( $_POST['data'] )
                ? map_deep( wp_unslash( $_POST['data'] ), 'sanitize_text_field' )
                : array();
            if ( ! isset( $data['origin_whitelist_expedition_id'] ) ) {
                $data['origin_whitelist_expedition_id']   = '';
                $data['origin_whitelist_expedition_name'] = '';
            }

            $service = (new \KiriminAjaOfficial\Services\SettingService())->storeOriginData($data);
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    
    function getCallbackData(){
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            // Check for nonce security - fail early
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $service = (new \KiriminAjaOfficial\Services\SettingService())->getCallbackData();
            
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    
    function storeCallbackData(){
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            // Check for nonce security - fail early
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $data = isset( $_POST['data'] ) && is_array( $_POST['data'] )
                ? map_deep( wp_unslash( $_POST['data'] ), 'sanitize_text_field' )
                : array();
            $service = (new \KiriminAjaOfficial\Services\SettingService())->storeCallbackData($data);
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    function storeWhitelistExpedition(){
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            // Check for nonce security - fail early
            // Select2 AJAX sends nonce as a top-level POST field, not nested inside data[].
            $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( empty( $nonce ) && isset( $_POST['data']['nonce'] ) ) {
                $nonce = sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) );
            }
            if ( ! wp_verify_nonce( $nonce, KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $search = isset( $_POST['data']['term'] ) ? sanitize_text_field( wp_unslash( $_POST['data']['term'] )) : '';
            $kiriminajaExpedition = (new \KiriminAjaOfficial\Services\KiriminajaApiService())->get_couriers();
            
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
    function getConfigData() {
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $repo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByArray(['enable_cod']);
            $response = [];
            foreach ($repo as $repoItem) {
                $response[$repoItem->key] = sanitize_text_field($repoItem->value);
            }
            // Default to 'yes' if key doesn't exist yet
            if (!isset($response['enable_cod'])) {
                $response['enable_cod'] = 'yes';
            }
            wp_send_json_success(['status' => 200, 'data' => $response]);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }

    function storeConfigData() {
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $data = isset( $_POST['data'] ) && is_array( $_POST['data'] )
                ? map_deep( wp_unslash( $_POST['data'] ), 'sanitize_text_field' )
                : array();
            $service = (new \KiriminAjaOfficial\Services\SettingService())->storeConfigData($data);
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    function getProfileData() {
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $service = (new \KiriminAjaOfficial\Services\KiriminajaApiService())->getProfile();
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    function getCourierWhitelist() {
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }

            // Fetch all couriers from API
            $couriers_service = (new \KiriminAjaOfficial\Services\KiriminajaApiService())->get_couriers();
            if ($couriers_service->status !== 200) {
                wp_send_json_error($couriers_service);
            }

            // Fetch current whitelist from DB
            $wl_repo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByArray([
                'origin_whitelist_expedition_id',
            ]);

            $whitelist_ids = array();
            foreach ($wl_repo as $row) {
                if ('origin_whitelist_expedition_id' === $row->key && ! empty( $row->value ) ) {
                    $whitelist_ids = array_map( 'trim', explode( ',', $row->value ) );
                }
            }

            wp_send_json_success(array(
                'status'  => 200,
                'data'    => array(
                    'couriers'       => $couriers_service->data,
                    'whitelist_ids'  => $whitelist_ids,
                ),
            ));
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    function storeCourierWhitelist() {
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $data = isset( $_POST['data'] ) && is_array( $_POST['data'] )
                ? map_deep( wp_unslash( $_POST['data'] ), 'sanitize_text_field' )
                : array();

            $whitelist_ids   = isset( $data['whitelist_ids'] ) ? sanitize_text_field( (string) $data['whitelist_ids'] ) : '';
            $whitelist_names = isset( $data['whitelist_names'] ) ? sanitize_text_field( (string) $data['whitelist_names'] ) : '';

            (new \KiriminAjaOfficial\Repositories\SettingRepository())->storeCourierWhitelist(array(
                'origin_whitelist_expedition_id'  => $whitelist_ids,
                'origin_whitelist_expedition_name'=> $whitelist_names,
            ));

            wp_send_json_success(['status' => 200, 'message' => 'Saved']);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
    function storeInsuranceData() {
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
                wp_die();
            }
            if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
                wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
                wp_die();
            }
            $data = isset( $_POST['data'] ) && is_array( $_POST['data'] )
                ? map_deep( wp_unslash( $_POST['data'] ), 'sanitize_text_field' )
                : array();
            $service = (new \KiriminAjaOfficial\Services\SettingService())->storeInsuranceData($data);
            if ($service->status!==200){ wp_send_json_error($service);}
            wp_send_json_success($service);
        }catch (Throwable $e){
            wp_send_json_error(['status'=>400,'message'=>$e->getMessage()]);
        }
    }
}