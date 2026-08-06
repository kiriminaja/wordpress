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

		add_action( 'wp_ajax_kiriof_enable_shipping_method', array( $this, 'enableShippingMethod' ) );

        /** storeInsuranceData*/
        add_action('wp_ajax_kiriof_store_insurance_data', array($this,'storeInsuranceData'));

        add_filter( 'woocommerce_general_settings', array( $this, 'injectWooCommerceGeneralSettings' ) );
        add_filter( 'woocommerce_get_settings_advanced', array( $this, 'injectWooCommerceAdvancedSettings' ) );
        add_action( 'woocommerce_admin_field_kiriof_area_select', array( $this, 'renderWooCommerceAreaSelectField' ) );
        add_action( 'woocommerce_admin_field_kiriof_pin_location', array( $this, 'renderWooCommercePinLocationField' ) );
        add_action( 'woocommerce_admin_field_kiriof_shipment_locations', array( $this, 'renderWooCommerceShipmentLocationsField' ) );
        add_action( 'woocommerce_admin_field_kiriof_tracking_page_select', array( $this, 'renderWooCommerceTrackingPageSelectField' ) );
        add_action( 'woocommerce_update_options_general', array( $this, 'syncWooCommerceGeneralSettings' ) );
        add_action( 'woocommerce_update_options_advanced', array( $this, 'syncWooCommerceAdvancedSettings' ) );
        add_action( 'admin_post_kiriof_download_plugin_logs', array( $this, 'downloadPluginLogs' ) );
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
				$courier_settings = ( new \KiriminAjaOfficial\Repositories\SettingRepository() )->getSettingByArray(
					array( 'origin_whitelist_expedition_id', 'origin_whitelist_expedition_name' )
				);
				$data['origin_whitelist_expedition_id']   = array();
				$data['origin_whitelist_expedition_name'] = array();
				foreach ( $courier_settings as $courier_setting ) {
					if ( 'origin_whitelist_expedition_id' === $courier_setting->key && ! empty( $courier_setting->value ) ) {
						$data['origin_whitelist_expedition_id'] = array_map( 'trim', explode( ',', $courier_setting->value ) );
					}
					if ( 'origin_whitelist_expedition_name' === $courier_setting->key && ! empty( $courier_setting->value ) ) {
						$data['origin_whitelist_expedition_name'] = array_map( 'trim', explode( ',', $courier_setting->value ) );
					}
				}
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

	public function enableShippingMethod() {
		try {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
			}

			if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
				wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
			}

			$registered = ( new \KiriminAjaOfficial\Services\WooCommerceShippingMethodRegistrationService() )->register();
			$steps      = ( new \KiriminAjaOfficial\Services\OnboardingSetupStateService() )->get_steps();
			$shipping   = $steps['shipping'];

			if ( ! $registered || ! $shipping['shipping_ready'] ) {
				wp_send_json_error( array( 'status' => 400, 'message' => __( 'KiriminAja shipping could not be enabled.', 'kiriminaja-official' ) ) );
			}

			wp_send_json_success(
				array(
					'status'          => 200,
					'locations_ready' => (bool) $shipping['locations_ready'],
				)
			);
		} catch ( Throwable $e ) {
			wp_send_json_error( array( 'status' => 400, 'message' => $e->getMessage() ) );
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

    public function injectWooCommerceGeneralSettings( $settings ) {
        if ( ! is_array( $settings ) ) {
            return $settings;
        }

        $locations_field = array(
            array(
                'title'    => __( 'Shipment Locations', 'kiriminaja-official' ),
                'id'       => 'kiriof_wc_shipment_locations',
                'type'     => 'kiriof_shipment_locations',
                'desc_tip' => __( 'Each location is an independent fulfillment origin. The default location is used when a product has no explicit assignment.', 'kiriminaja-official' ),
            ),
        );

        $settings = $this->insertSettingsBeforeId( $settings, 'woocommerce_store_address', $locations_field );

        foreach ( array( 'woocommerce_store_address', 'woocommerce_store_address2', 'woocommerce_store_postcode', 'woocommerce_store_city', 'woocommerce_default_country' ) as $removed_id ) {
            $settings = array_values(
                array_filter(
                    $settings,
                    static function ( $field ) use ( $removed_id ) {
                        return ! is_array( $field ) || ! isset( $field['id'] ) || $field['id'] !== $removed_id;
                    }
                )
            );
        }

        return $settings;
    }

    public function injectWooCommerceAdvancedSettings( $settings ) {
        if ( ! is_array( $settings ) ) {
            return $settings;
        }

        $section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_SPECIAL_CHARS );
        if ( ! empty( $section ) ) {
            return $settings;
        }

        $tracking_page_field = array(
            array(
                'title'    => __( 'Tracking page', 'kiriminaja-official' ),
                'desc'     => __( 'Page contents: [kiriminaja-tracking-front-page]', 'kiriminaja-official' ),
                'id'       => 'kiriof_tracking_page_id',
                'type'     => 'kiriof_tracking_page_select',
                'default'  => kiriof_get_tracking_page_id(),
                'class'    => 'wc-enhanced-select-nostd',
                'css'      => 'min-width:300px;',
                'desc_tip' => __( 'Required by KiriminAja Official Plugin', 'kiriminaja-official' ),
            ),
        );

        return $this->insertSettingsAfterId( $settings, 'woocommerce_myaccount_page_id', $tracking_page_field );
    }

    public function renderWooCommerceTrackingPageSelectField( $value ) {
        $field_id       = isset( $value['id'] ) ? sanitize_key( $value['id'] ) : 'kiriof_tracking_page_id';
        $selected_id    = absint( get_option( $field_id, kiriof_get_tracking_page_id() ) );
        $tracking_pages = $this->getTrackingShortcodePages();
        $description    = isset( $value['desc'] ) ? (string) $value['desc'] : '';
        $css            = isset( $value['css'] ) ? (string) $value['css'] : 'min-width:300px;';
        $class          = isset( $value['class'] ) ? (string) $value['class'] : 'wc-enhanced-select-nostd';
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_id ); ?>">
                    <?php echo esc_html( $value['title'] ?? __( 'Tracking page', 'kiriminaja-official' ) ); ?>
                    <?php echo wp_kses_post( wc_help_tip( __( 'Required by KiriminAja Official Plugin', 'kiriminaja-official' ) ) ); ?>
                </label>
            </th>
            <td class="forminp">
                <select
                    id="<?php echo esc_attr( $field_id ); ?>"
                    name="<?php echo esc_attr( $field_id ); ?>"
                    class="<?php echo esc_attr( $class ); ?>"
                    style="<?php echo esc_attr( $css ); ?>"
                >
                    <option value=""><?php echo esc_html__( 'Select a page&hellip;', 'kiriminaja-official' ); ?></option>
                    <?php foreach ( $tracking_pages as $tracking_page ) : ?>
                        <option value="<?php echo esc_attr( $tracking_page->ID ); ?>" <?php selected( $selected_id, (int) $tracking_page->ID ); ?>>
                            <?php echo esc_html( $tracking_page->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ( '' !== $description ) : ?>
                    <p class="description"><?php echo esc_html( $description ); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    public function renderWooCommerceAreaSelectField( $value ) {
        $origin      = $this->getOriginSettingValues();
        $field_id    = isset( $value['id'] ) ? sanitize_key( $value['id'] ) : 'kiriof_wc_origin_area';
        $area_id     = (string) get_option( $field_id, $origin['origin_sub_district_id'] ?? '' );
        $area_name   = isset( $value['origin_area_name'] ) ? (string) $value['origin_area_name'] : ( $origin['origin_sub_district_name'] ?? '' );
        $description = isset( $value['desc'] ) ? (string) $value['desc'] : '';
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $value['title'] ?? __( 'Area', 'kiriminaja-official' ) ); ?></label>
            </th>
            <td class="forminp">
                <select
                    id="<?php echo esc_attr( $field_id ); ?>"
                    name="<?php echo esc_attr( $field_id ); ?>"
                    class="wc-enhanced-select kiriof-wc-origin-area-select"
                    style="width:350px"
                >
                    <?php if ( '' !== $area_id && '' !== $area_name ) : ?>
                        <option selected value="<?php echo esc_attr( $area_id ); ?>"><?php echo esc_html( $area_name ); ?></option>
                    <?php endif; ?>
                </select>
                <input type="hidden" id="kiriof_wc_origin_area_name" name="kiriof_wc_origin_area_name" value="<?php echo esc_attr( $area_name ); ?>">
                <?php if ( '' !== $description ) : ?>
                    <p class="description"><?php echo esc_html( $description ); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php

        $inline_script = <<<'JS'
jQuery(function($){
    var $field = $('#kiriof_wc_origin_area');
    var $row = $field.closest('tr');
    var $country = $('#woocommerce_default_country');
    var select2 = $.fn.selectWoo || $.fn.select2;
    if (!$field.length || !select2) {
        return;
    }
    function kiriofSelectedCountryIsIndonesia() {
        var value = String($country.val() || '');
        return value === 'ID' || value.indexOf('ID:') === 0;
    }
    function kiriofToggleAreaField() {
        if (!$country.length || kiriofSelectedCountryIsIndonesia()) {
            $row.show();
            return;
        }
        $row.hide();
        $field.val(null).trigger('change');
        $('#kiriof_wc_origin_area_name').val('');
    }
    function kiriofExtractPostcode(item) {
        var postcode = item.postcode || item.zipcode || item.zip_code || item.postal_code || item.kode_pos || item.kodepos || '';
        if (!postcode && item.text) {
            var match = String(item.text).match(/\b\d{5}\b/);
            postcode = match ? match[0] : '';
        }
        return String(postcode || '').replace(/\s+/g, '').trim();
    }
    if ($field.data('select2') || $field.data('selectWoo')) {
        select2.call($field, 'destroy');
    }
    select2.call($field, {
        width: '350px',
        minimumInputLength: 3,
        placeholder: 'Select Option',
        allowClear: true,
        ajax: {
            url: (window.kiriofAjax && kiriofAjax.ajaxurl) ? kiriofAjax.ajaxurl : window.ajaxurl,
            dataType: 'json',
            type: 'POST',
            delay: 250,
            data: function(params) {
                return {
                    data: params,
                    nonce: (window.kiriofAjax && kiriofAjax.nonce) ? kiriofAjax.nonce : '',
                    action: 'kiriminaja_subdistrict_search'
                };
            },
            processResults: function(response) {
                return {
                    results: $.map(response.data || [], function(item) {
                        return {
                            text: item.text,
                            id: item.id,
                            postcode: kiriofExtractPostcode(item)
                        };
                    })
                };
            },
            cache: true
        }
    });
    $field.on('select2:select', function(event) {
        var selected = event.params && event.params.data ? event.params.data : {};
        var postcode = kiriofExtractPostcode(selected);
        if (postcode) {
            $('#woocommerce_store_postcode, [name="woocommerce_store_postcode"]').val(postcode).trigger('input').trigger('change');
        }
        $('#kiriof_wc_origin_area_name').val($field.find('option:selected').text() || '');
    });
    $field.on('change', function() {
        $('#kiriof_wc_origin_area_name').val($field.find('option:selected').text() || '');
    });
    $field.on('select2:clear', function() {
        $('#kiriof_wc_origin_area_name').val('');
    });
    $country.on('change', kiriofToggleAreaField);
    kiriofToggleAreaField();
});
JS;
        wp_add_inline_script( 'kiriof-script', $inline_script );
    }

    public function renderWooCommercePinLocationField( $value ) {
        $origin      = $this->getOriginSettingValues();
        $latitude    = $origin['origin_latitude'] ?? '';
        $longitude   = $origin['origin_longitude'] ?? '';
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label>
                    <?php echo esc_html( $value['title'] ?? __( 'Pin Location', 'kiriminaja-official' ) ); ?>
                    <?php echo wp_kses_post( wc_help_tip( __( 'Required by KiriminAja Official Plugin', 'kiriminaja-official' ) ) ); ?>
                </label>
            </th>
            <td class="forminp">
                <input type="hidden" id="kiriof_wc_origin_latitude" name="kiriof_wc_origin_latitude" value="<?php echo esc_attr( $latitude ); ?>">
                <input type="hidden" id="kiriof_wc_origin_longitude" name="kiriof_wc_origin_longitude" value="<?php echo esc_attr( $longitude ); ?>">
                <div style="position:relative;width:350px;max-width:100%">
                    <div id="kiriof-wc-origin-map" style="width:100%;height:280px;border:1px solid #ddd;border-radius:4px;z-index:0"></div>
                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-100%);z-index:401;pointer-events:none">
                        <svg width="30" height="40" viewBox="0 0 30 40" aria-hidden="true"><path d="M15 0C6.716 0 0 6.716 0 15c0 10.969 13.5 24.138 14.094 24.72a1.25 1.25 0 0 0 1.812 0C16.5 39.138 30 25.969 30 15 30 6.716 23.284 0 15 0zm0 22.5a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" fill="#E74C3C"/><circle cx="15" cy="15" r="4" fill="white"/></svg>
                    </div>
                    <button type="button" id="kiriof-wc-use-my-location" class="button" style="position:absolute;top:8px;right:8px;z-index:401"><?php echo esc_html( __( 'My Location', 'kiriminaja-official' ) ); ?></button>
                </div>
                <span id="kiriof-wc-map-coords" class="woocommerce-help-tip" data-tip="" style="display:inline-block;margin-top:8px"></span>
                <p class="description" id="kiriof-wc-map-error" style="margin-top:4px;color:#d63638;display:none"></p>
            </td>
        </tr>
        <?php

        $inline_script = <<<'JS'
jQuery(function($){
    if (typeof L === 'undefined' || !document.getElementById('kiriof-wc-origin-map')) {
        return;
    }
    var $lat = $('#kiriof_wc_origin_latitude');
    var $lng = $('#kiriof_wc_origin_longitude');
    var $coords = $('#kiriof-wc-map-coords');
    var $error = $('#kiriof-wc-map-error');
    var defaultLat = parseFloat($lat.val()) || -6.2088;
    var defaultLng = parseFloat($lng.val()) || 106.8456;
    var map = L.map('kiriof-wc-origin-map').setView([defaultLat, defaultLng], 15);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    setTimeout(function(){ map.invalidateSize(); }, 200);
    function showError(message) {
        $error.text(message).show();
        setTimeout(function(){ $error.fadeOut(); }, 5000);
    }
    function updateCoordinates(lat, lng) {
        if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            showError('Invalid coordinates');
            return;
        }
        $lat.val(lat.toFixed(7));
        $lng.val(lng.toFixed(7));
        $coords.attr('data-tip', lat.toFixed(7) + ', ' + lng.toFixed(7));
        $error.hide();
    }
    map.on('moveend', function(){
        var center = map.getCenter();
        updateCoordinates(center.lat, center.lng);
    });
    updateCoordinates(defaultLat, defaultLng);
    $('#kiriof-wc-use-my-location').on('click', function(){
        var $button = $(this);
        $error.hide();
        if (!navigator.geolocation) {
            showError('Geolocation is not supported by this browser.');
            return;
        }
        $button.prop('disabled', true);
        navigator.geolocation.getCurrentPosition(function(position){
            map.setView([position.coords.latitude, position.coords.longitude], 17);
            $button.prop('disabled', false);
        }, function(error){
            $button.prop('disabled', false);
            var messages = ['Permission denied', 'Location unavailable', 'Timeout'];
            showError(messages[error.code - 1] || 'Unknown error');
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
    });
});
JS;
        wp_add_inline_script( 'kiriof-script', $inline_script );
    }

    public function renderWooCommerceShipmentLocationsField( $value ) {
        $service   = new \KiriminAjaOfficial\Services\ShipmentLocationService();
        $locations = $service->repository()->getAll();

        if ( empty( $locations ) ) {
            $service->seedDefaultFromGlobalOrigin();
            $locations = $service->repository()->getAll();
        }
        ?>
        <tr valign="top" class="kiriof-wc-shipment-locations" id="kiriof-shipment-locations">
            <th scope="row" class="titledesc"></th>
            <td class="forminp forminp-kiriof_shipment_locations">
                <p class="description"><?php esc_html_e( 'Each location is an independent fulfillment origin. The default location is used when a product has no explicit assignment.', 'kiriminaja-official' ); ?></p>
                <table class="wc-shipping-zones widefat kiriof-wc-locations-table">
                    <thead>
                        <tr>
                            <th class="wc-shipping-zone-sort kiriof-wc-location-default-col"><?php esc_html_e( 'Default', 'kiriminaja-official' ); ?></th>
                            <th class="wc-shipping-zone-name"><?php esc_html_e( 'Sender', 'kiriminaja-official' ); ?></th>
                            <th class="wc-shipping-zone-region"><?php esc_html_e( 'Area / City', 'kiriminaja-official' ); ?></th>
                            <th class="wc-shipping-zone-postcodes"><?php esc_html_e( 'Phone', 'kiriminaja-official' ); ?></th>
                            <th class="kiriof-wc-location-status-col"><?php esc_html_e( 'Status', 'kiriminaja-official' ); ?></th>
                            <th class="wc-shipping-zone-actions"></th>
                        </tr>
                    </thead>
                    <tbody class="kiriof-wc-locations-rows">
                        <?php
                        foreach ( $locations as $location ) {
                            $this->renderShipmentLocationRow( (int) $location->id, $location );
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6">
                                <button type="button" class="button kiriof-wc-locations-add"><?php esc_html_e( 'Add Shipment Location', 'kiriminaja-official' ); ?></button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <div class="kiriof-wc-location-new-source" style="display:none;">
                    <?php $this->renderShipmentLocationRow( 0, null, true ); ?>
                </div>
                <style>
                    .kiriof-wc-locations-table { max-width: 960px; }
                    .kiriof-wc-locations-table th { font-weight: 600; }
                    .kiriof-wc-location-editor-row > td { background: #f6f7f7; padding: 0 0 16px; }
                    .kiriof-wc-location-card { border: 0; background: transparent; margin: 0; max-width: 960px; }
                    .kiriof-wc-location-card__body th { width: 150px; padding: 10px 14px 0; }
                    .kiriof-wc-location-card__body td { padding: 10px 14px 0; }
                    .kiriof-wc-location-summary .dashicons-yes-alt { color: #46b450; }
                    .kiriof-wc-location-summary .dashicons-marker { color: #c3c4c7; }
                    .kiriof-wc-location-editor-row { display: none; }
                </style>
            </td>
        </tr>
        <?php
        $this->renderShipmentLocationsInlineScript();
    }

    private function renderShipmentLocationRow( $id, $location, $use_store_fallback = false ) {
        $key        = $id > 0 ? (string) $id : 'new';
        $prefix     = 'kiriof_locations[' . $key . ']';
        if ( ! $location && $use_store_fallback ) {
            $fallback_address = (string) get_option( 'woocommerce_store_address' );
            $fallback_zip     = (string) get_option( 'woocommerce_store_postcode', '' );
            $fallback_address_2     = (string) get_option( 'woocommerce_store_address2', '' );
            $fallback_city          = (string) get_option( 'woocommerce_store_city', '' );
            $fallback_country_state = (string) get_option( 'woocommerce_default_country', '' );
        } else {
            $fallback_address = '';
            $fallback_zip     = '';
            $fallback_address_2     = '';
            $fallback_city          = '';
            $fallback_country_state = '';
        }
        $name       = $location ? (string) $location->name : '';
        $phone      = $location ? (string) $location->phone : '';
        $address    = $location ? (string) $location->address : $fallback_address;
        $area_id    = $location ? (string) $location->sub_district_id : '';
        $area_name  = $location ? (string) $location->sub_district_name : '';
        $zip        = $location ? (string) $location->zip_code : $fallback_zip;
        $address_2  = $location ? (string) $location->address_2 : $fallback_address_2;
        $city       = $location ? (string) $location->city : $fallback_city;
        $country    = $location ? (string) $location->country : '';
        $state      = $location ? (string) $location->state : '';
        if ( '' === $country && '' !== $fallback_country_state ) {
            $country = $fallback_country_state;
            if ( false !== strpos( $fallback_country_state, ':' ) ) {
                list( $country, $state ) = explode( ':', $fallback_country_state, 2 );
            }
        }
        $lat        = $location ? (string) $location->latitude : '';
        $lng        = $location ? (string) $location->longitude : '';
        $is_default = $location && 1 === (int) $location->is_default;
        $is_active  = ! $location || 1 === (int) $location->is_active;
        $title      = $id > 0 ? ( '' !== $name ? $name : __( 'Shipment Location', 'kiriminaja-official' ) ) : __( 'Add Shipment Location', 'kiriminaja-official' );
        $region     = trim( $area_name . ( '' !== $area_name && '' !== $city ? ', ' : '' ) . $city );
        if ( '' === $region ) {
            $region = '&mdash;';
        }
        ?>
        <?php if ( $id > 0 ) : ?>
        <tr class="kiriof-wc-location-summary">
            <td class="kiriof-wc-location-default-col" style="text-align:center;">
                <label style="display:inline-block;">
                    <input type="radio" name="kiriof_default_location_id" value="<?php echo esc_attr( $id ); ?>" <?php checked( $is_default ); ?> />
                </label>
            </td>
            <td class="wc-shipping-zone-name">
                <strong><?php echo esc_html( $title ); ?></strong>
                <?php if ( $is_default ) : ?>
                    <span class="dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Default', 'kiriminaja-official' ); ?>"></span>
                <?php endif; ?>
            </td>
            <td><?php echo esc_html( $region ); ?></td>
            <td><?php echo '' !== $phone ? esc_html( $phone ) : '&mdash;'; ?></td>
            <td>
                <?php if ( $is_active ) : ?>
                    <span class="kiriof-wc-location-status-active"><?php esc_html_e( 'Active', 'kiriminaja-official' ); ?></span>
                <?php else : ?>
                    <span class="kiriof-wc-location-status-inactive"><?php esc_html_e( 'Inactive', 'kiriminaja-official' ); ?></span>
                <?php endif; ?>
            </td>
            <td class="wc-shipping-zone-actions">
                <button type="button" class="kiriof-wc-location-actions-toggle" title="<?php esc_attr_e( 'Actions', 'kiriminaja-official' ); ?>">
                    <span class="dashicons dashicons-ellipsis"></span>
                </button>
                <ul class="kiriof-wc-location-actions-menu" style="display:none;">
                    <li><a href="#" class="kiriof-wc-location-edit"><?php esc_html_e( 'Edit', 'kiriminaja-official' ); ?></a></li>
                    <li><a href="#" class="kiriof-wc-location-delete"><?php esc_html_e( 'Delete', 'kiriminaja-official' ); ?></a></li>
                </ul>
            </td>
        </tr>
        <?php endif; ?>
        <tr class="kiriof-wc-location-row kiriof-wc-location-editor-row">
            <td colspan="6">
                <div class="kiriof-wc-location-card">
                    <table class="form-table kiriof-wc-location-card__body">
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Sender Name', 'kiriminaja-official' ); ?></label></th>
                            <td>
                                <input type="text" class="regular-text" name="<?php echo esc_attr( $prefix . '[name]' ); ?>" value="<?php echo esc_attr( $name ); ?>" />
                                <?php if ( $id > 0 ) : ?>
                                    <label style="margin-left:16px;">
                                        <input type="checkbox" name="<?php echo esc_attr( $prefix . '[is_active]' ); ?>" value="1" <?php checked( $is_active ); ?> />
                                        <?php esc_html_e( 'Active', 'kiriminaja-official' ); ?>
                                    </label>
                                <?php else : ?>
                                    <input type="hidden" name="<?php echo esc_attr( $prefix . '[is_active]' ); ?>" value="1" />
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Sender Phone', 'kiriminaja-official' ); ?></label></th>
                            <td><input type="text" class="regular-text" name="<?php echo esc_attr( $prefix . '[phone]' ); ?>" value="<?php echo esc_attr( $phone ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Address', 'kiriminaja-official' ); ?></label></th>
                            <td><textarea rows="3" cols="50" class="regular-text" name="<?php echo esc_attr( $prefix . '[address]' ); ?>"><?php echo esc_textarea( $address ); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Address line 2', 'kiriminaja-official' ); ?></label></th>
                            <td><input type="text" class="regular-text" name="<?php echo esc_attr( $prefix . '[address_2]' ); ?>" value="<?php echo esc_attr( $address_2 ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'City', 'kiriminaja-official' ); ?></label></th>
                            <td><input type="text" class="regular-text" name="<?php echo esc_attr( $prefix . '[city]' ); ?>" value="<?php echo esc_attr( $city ); ?>" /></td>
                        </tr>
                        <tr class="kiriof-wc-location-area-row">
                            <th scope="row"><label><?php esc_html_e( 'Area', 'kiriminaja-official' ); ?></label></th>
                            <td>
                                <select class="kiriof-wc-origin-area-select" name="<?php echo esc_attr( $prefix . '[sub_district_id]' ); ?>">
                                    <?php if ( '' !== $area_id && '0' !== $area_id ) : ?>
                                        <option value="<?php echo esc_attr( $area_id ); ?>" selected="selected"><?php echo esc_html( $area_name ); ?></option>
                                    <?php endif; ?>
                                </select>
                                <input type="hidden" class="kiriof-wc-location-area-name" name="<?php echo esc_attr( $prefix . '[sub_district_name]' ); ?>" value="<?php echo esc_attr( $area_name ); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Zip', 'kiriminaja-official' ); ?></label></th>
                            <td><input type="text" class="kiriof-wc-location-zip" size="8" name="<?php echo esc_attr( $prefix . '[zip_code]' ); ?>" value="<?php echo esc_attr( $zip ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Country / State', 'kiriminaja-official' ); ?></label></th>
                            <td>
                                <select class="regular-text kiriof-wc-location-country" name="<?php echo esc_attr( $prefix . '[country_state]' ); ?>">
                                    <?php echo $this->renderCountryStateOptions( $country, $state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Pin Location', 'kiriminaja-official' ); ?></label></th>
                            <td>
                                <div class="kiriof-wc-origin-map" style="width:100%;max-width:440px;height:180px;" data-lat="<?php echo esc_attr( $lat ); ?>" data-lng="<?php echo esc_attr( $lng ); ?>"></div>
                                <input type="hidden" class="kiriof-wc-location-latitude" name="<?php echo esc_attr( $prefix . '[latitude]' ); ?>" value="<?php echo esc_attr( $lat ); ?>" />
                                <input type="hidden" class="kiriof-wc-location-longitude" name="<?php echo esc_attr( $prefix . '[longitude]' ); ?>" value="<?php echo esc_attr( $lng ); ?>" />
                                <button type="button" class="button kiriof-wc-origin-my-location"><?php esc_html_e( 'My Location', 'kiriminaja-official' ); ?></button>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"></th>
                            <td>
                                <?php if ( $id > 0 ) : ?>
                                    <label class="kiriof-wc-location-remove-label" style="color:#b32d2e;">
                                        <input type="checkbox" class="kiriof-wc-location-remove" name="<?php echo esc_attr( $prefix . '[remove]' ); ?>" value="1" />
                                        <?php esc_html_e( 'Remove', 'kiriminaja-official' ); ?>
                                    </label>
                                <?php endif; ?>
                                <button type="button" class="button kiriof-wc-location-close"><?php esc_html_e( 'Close', 'kiriminaja-official' ); ?></button>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
        <?php
    }

    private function renderCountryStateOptions( $country, $state ) {
        $selected  = $country . ( '' !== $state ? ':' . $state : '' );
        $html      = '';
        $countries = function_exists( 'WC' ) && WC() && WC()->countries ? WC()->countries->get_countries() : array();

        foreach ( $countries as $code => $label ) {
            $states = function_exists( 'WC' ) && WC() && WC()->countries ? WC()->countries->get_states( $code ) : array();
            if ( is_array( $states ) && ! empty( $states ) ) {
                $html .= '<optgroup label="' . esc_attr( $label ) . '">';
                $html .= '<option value="' . esc_attr( $code ) . '"' . selected( $selected, $code, false ) . '>' . esc_html( $label ) . '</option>';
                foreach ( $states as $state_code => $state_label ) {
                    $value = $code . ':' . $state_code;
                    $html .= '<option value="' . esc_attr( $value ) . '"' . selected( $selected, $value, false ) . '>' . esc_html( $state_label ) . '</option>';
                }
                $html .= '</optgroup>';
            } else {
                $html .= '<option value="' . esc_attr( $code ) . '"' . selected( $selected, $code, false ) . '>' . esc_html( $label ) . '</option>';
            }
        }

        return $html;
    }

    private function renderShipmentLocationsInlineScript() {
        $inline_script = <<<JS
jQuery(function ($) {
    var select2 = $.fn.selectWoo || $.fn.select2;

    function kiriofExtractPostcode(item) {
        if (!item || typeof item !== 'object') {
            return '';
        }
        if (item.postcode) {
            return String(item.postcode);
        }
        if (item.postal_code) {
            return String(item.postal_code);
        }
        if (item.data && item.data.postcode) {
            return String(item.data.postcode);
        }
        return '';
    }

    $('.kiriof-wc-location-editor-row').each(function () {
        var $row  = $(this);
        var $area = $row.find('.kiriof-wc-origin-area-select');
        var $name = $row.find('.kiriof-wc-location-area-name');
        var $zip  = $row.find('.kiriof-wc-location-zip');
        var $country = $row.find('.kiriof-wc-location-country');
        var $areaRow = $row.find('.kiriof-wc-location-area-row');
        var initialized = false;

        function rowIsIndonesia() {
            if ($country.length) {
                var value = String($country.val() || '');
                return value === 'ID' || value.indexOf('ID:') === 0;
            }
            return !$('#woocommerce_default_country').length || kiriofSelectedCountryIsIndonesia();
        }

        function toggleAreaRow() {
            if ($areaRow.length) {
                $areaRow.toggle(rowIsIndonesia());
            }
        }

        toggleAreaRow();
        $country.on('change', toggleAreaRow);

        function initWidgets() {
            if (initialized) {
                return;
            }
            initialized = true;

            if (select2 && $area.length) {
                select2.call($area, {
                    width: '100%',
                    minimumInputLength: 3,
                    placeholder: 'Select Option',
                    allowClear: true,
                    ajax: {
                        url: window.kiriofAjax ? kiriofAjax.ajaxurl : window.ajaxurl,
                        dataType: 'json',
                        delay: 300,
                        data: function (params) {
                            return {
                                action: 'kiriminaja_subdistrict_search',
                                nonce: window.kiriofAjax ? kiriofAjax.nonce : '',
                                term: params.term
                            };
                        },
                        processResults: function (response) {
                            var results = response && response.data && response.data.results ? response.data.results : [];
                            return { results: results };
                        },
                        cache: true
                    }
                });

                $area.on('change', function () {
                    var $option = $area.find('option:selected');
                    $name.val($option.length ? $option.text() : '');
                });
                $area.on('select2:select', function (event) {
                    var postcode = kiriofExtractPostcode(event.params && event.params.data ? event.params.data : null);
                    if (postcode) {
                        $zip.val(postcode);
                    }
                });
                $area.on('select2:clear', function () {
                    $name.val('');
                });
            }

            var $map = $row.find('.kiriof-wc-origin-map');
            if ($map.length && window.L) {
                var rawLat = $map.data('lat');
                var rawLng = $map.data('lng');
                var hasPin = rawLat !== undefined && rawLat !== '' && rawLng !== undefined && rawLng !== '';
                var lat = hasPin ? parseFloat(rawLat) : -6.2;
                var lng = hasPin ? parseFloat(rawLng) : 106.817;
                var map = L.map($map[0]).setView([lat, lng], hasPin ? 15 : 11);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);
                var marker = hasPin ? L.marker([lat, lng]).addTo(map) : null;

                var setPin = function (la, ln) {
                    $row.find('.kiriof-wc-location-latitude').val(la);
                    $row.find('.kiriof-wc-location-longitude').val(ln);
                    if (marker) {
                        marker.setLatLng([la, ln]);
                    } else {
                        marker = L.marker([la, ln]).addTo(map);
                    }
                    map.setView([la, ln], 15);
                };

                map.on('click', function (event) {
                    setPin(event.latlng.lat, event.latlng.lng);
                });

                $row.find('.kiriof-wc-origin-my-location').on('click', function (event) {
                    event.preventDefault();
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(function (position) {
                            setPin(position.coords.latitude, position.coords.longitude);
                        });
                    }
                });
            }
        }

        var $summary = $row.prev('.kiriof-wc-location-summary');

        function openEditor() {
            $('.kiriof-wc-location-editor-row:visible').not($row).each(function () {
                $(this).hide().prev('.kiriof-wc-location-summary').show();
            });
            $row.show();
            if ($summary.length) {
                $summary.hide();
            }
            initWidgets();
        }

        function closeEditor() {
            $row.hide();
            if ($summary.length) {
                $summary.show();
            }
        }

        if ($summary.length) {
            $summary.find('.kiriof-wc-location-edit').on('click', function (event) {
                event.preventDefault();
                openEditor();
            });
            $summary.find('.kiriof-wc-location-delete').on('click', function (event) {
                event.preventDefault();
                $row.find('.kiriof-wc-location-remove').prop('checked', true);
                $summary.css('opacity', '0.5');
                $summary.find('.kiriof-wc-location-actions-menu').hide();
            });
        }

        $row.find('.kiriof-wc-location-close').on('click', function (event) {
            event.preventDefault();
            closeEditor();
        });

        $row.data('openEditor', openEditor);
    });

    $('.kiriof-wc-locations-add').on('click', function (event) {
        event.preventDefault();
        var $editor = $('.kiriof-wc-location-new-source .kiriof-wc-location-editor-row');
        if (!$editor.length) {
            return;
        }
        $('.kiriof-wc-locations-rows').append($editor);
        var openEditor = $editor.data('openEditor');
        if (typeof openEditor === 'function') {
            openEditor();
        } else {
            $editor.show();
        }
        $(this).prop('disabled', true);
    });

    // Kebab actions-menu toggle
    $('body').on('click', '.kiriof-wc-location-actions-toggle', function (e) {
        e.stopPropagation();
        var $menu = $(this).siblings('.kiriof-wc-location-actions-menu');
        $('.kiriof-wc-location-actions-menu').not($menu).hide();
        $menu.toggle();
    });

    // Kebab menu: clicking edit closes the menu first
    $('body').on('click', '.kiriof-wc-location-actions-menu .kiriof-wc-location-edit', function () {
        $(this).closest('.kiriof-wc-location-actions-menu').hide();
    });

    $(document).on('click', function () {
        $('.kiriof-wc-location-actions-menu').hide();
    });
});
JS;
        wp_add_inline_script( 'kiriof-script', $inline_script );
    }

    public function syncWooCommerceGeneralSettings() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        if ( ! $this->verifyWooCommerceSettingsNonce() ) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce settings nonce verified above.
        $service    = new \KiriminAjaOfficial\Services\ShipmentLocationService();
        $repository = $service->repository();

        $posted     = isset( $_POST['kiriof_locations'] ) ? wp_unslash( $_POST['kiriof_locations'] ) : array();
        $default_id = isset( $_POST['kiriof_default_location_id'] ) ? absint( $_POST['kiriof_default_location_id'] ) : 0;

        if ( is_array( $posted ) ) {
            foreach ( $posted as $key => $raw ) {
                if ( ! is_array( $raw ) ) {
                    continue;
                }

                $data = array(
                    'name'              => isset( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : '',
                    'phone'             => isset( $raw['phone'] ) ? sanitize_text_field( $raw['phone'] ) : '',
                    'address'           => isset( $raw['address'] ) ? sanitize_textarea_field( $raw['address'] ) : '',
                    'sub_district_id'   => isset( $raw['sub_district_id'] ) ? sanitize_text_field( $raw['sub_district_id'] ) : '',
                    'sub_district_name' => isset( $raw['sub_district_name'] ) ? sanitize_text_field( $raw['sub_district_name'] ) : '',
                    'zip_code'          => isset( $raw['zip_code'] ) ? sanitize_text_field( $raw['zip_code'] ) : '',
                    'address_2'         => isset( $raw['address_2'] ) ? sanitize_text_field( $raw['address_2'] ) : '',
                    'city'              => isset( $raw['city'] ) ? sanitize_text_field( $raw['city'] ) : '',
                    'latitude'          => isset( $raw['latitude'] ) ? sanitize_text_field( $raw['latitude'] ) : '',
                    'longitude'         => isset( $raw['longitude'] ) ? sanitize_text_field( $raw['longitude'] ) : '',
                    'is_active'         => ! empty( $raw['is_active'] ) ? 1 : 0,
                );

                $raw_country_state = isset( $raw['country_state'] ) ? sanitize_text_field( $raw['country_state'] ) : '';
                $data['country']   = $raw_country_state;
                $data['state']     = '';
                if ( false !== strpos( $raw_country_state, ':' ) ) {
                    list( $data['country'], $data['state'] ) = explode( ':', $raw_country_state, 2 );
                }

                if ( 'new' === (string) $key ) {
                    if ( '' !== $data['name'] || '' !== $data['address'] ) {
                        $repository->insert( $data );
                    }
                    continue;
                }

                $location_id = absint( $key );
                if ( $location_id <= 0 ) {
                    continue;
                }
                if ( ! empty( $raw['remove'] ) ) {
                    $repository->delete( $location_id );
                    continue;
                }
                $repository->update( $location_id, $data );
            }
        }

        if ( $default_id > 0 ) {
            $repository->setDefault( $default_id );
        }
        $repository->ensureDefaultExists();

        $default = $repository->getDefault();
        if ( $default ) {
            $payload = array(
                'origin_name'              => (string) $default->name,
                'origin_phone'             => (string) $default->phone,
                'origin_address'           => (string) $default->address,
                'origin_zip_code'          => (string) $default->zip_code,
                'origin_sub_district_id'   => (string) $default->sub_district_id,
                'origin_sub_district_name' => (string) $default->sub_district_name,
                'origin_latitude'          => (string) $default->latitude,
                'origin_longitude'         => (string) $default->longitude,
            );

            update_option( 'kiriof_wc_origin_name', $payload['origin_name'] );
            update_option( 'kiriof_wc_origin_phone', $payload['origin_phone'] );
            update_option( 'kiriof_wc_origin_area', $payload['origin_sub_district_id'] );
            update_option( 'woocommerce_store_address', $payload['origin_address'] );
            update_option( 'woocommerce_store_postcode', $payload['origin_zip_code'] );
            update_option( 'woocommerce_store_address2', (string) $default->address_2 );
            update_option( 'woocommerce_store_city', (string) $default->city );
            $default_country_state = (string) $default->country . ( '' !== (string) $default->state ? ':' . (string) $default->state : '' );
            update_option( 'woocommerce_default_country', $default_country_state );

            ( new \KiriminAjaOfficial\Repositories\SettingRepository() )->storeOriginMirrorData( $payload );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public function syncWooCommerceAdvancedSettings() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        if ( ! $this->verifyWooCommerceSettingsNonce() ) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce settings nonce verified above.
        $page_id = isset( $_POST['kiriof_tracking_page_id'] ) ? absint( $_POST['kiriof_tracking_page_id'] ) : 0;
        if ( $page_id > 0 && ! $this->pageHasTrackingShortcode( $page_id ) ) {
            return;
        }

        update_option( 'kiriof_tracking_page_id', $page_id );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    private function verifyWooCommerceSettingsNonce() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- This method performs the WooCommerce settings nonce verification.
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        $verified = '' !== $nonce && wp_verify_nonce( $nonce, 'woocommerce-settings' );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        return $verified;
    }

    private function getOriginSettingValues() {
        $rows = ( new \KiriminAjaOfficial\Repositories\SettingRepository() )->getSettingByArray(
            array(
                'origin_name',
                'origin_phone',
                'origin_address',
                'origin_latitude',
                'origin_longitude',
                'origin_sub_district_id',
                'origin_sub_district_name',
                'origin_zip_code',
            )
        );

        $values = array();
        foreach ( (array) $rows as $row ) {
            if ( isset( $row->key ) ) {
                $values[ $row->key ] = isset( $row->value ) ? sanitize_text_field( (string) $row->value ) : '';
            }
        }

        return $values;
    }

    private function getTrackingShortcodePages() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_type = 'page'
               AND post_status NOT IN ('trash', 'auto-draft')
               AND (
                   post_content LIKE '%[kiriminaja-tracking-front-page%'
                   OR post_content LIKE '%[wp-tracking-front-page%'
               )
             ORDER BY post_title ASC, ID ASC"
        );
    }

    private function pageHasTrackingShortcode( $page_id ) {
        $page = get_post( absint( $page_id ) );
        if ( ! $page instanceof \WP_Post || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
            return false;
        }

        $content = (string) $page->post_content;
        return false !== strpos( $content, '[kiriminaja-tracking-front-page' )
            || false !== strpos( $content, '[wp-tracking-front-page' );
    }

    private function insertSettingsBeforeId( array $settings, $target_id, array $insert ) {
        return $this->insertSettingsNearId( $settings, (string) $target_id, $insert, 'before' );
    }

    private function insertSettingsAfterId( array $settings, $target_id, array $insert ) {
        return $this->insertSettingsNearId( $settings, (string) $target_id, $insert, 'after' );
    }

    private function insertSettingsNearId( array $settings, $target_id, array $insert, $position ) {
        $output   = array();
        $inserted = false;

        foreach ( $settings as $setting ) {
            if ( 'before' === $position && isset( $setting['id'] ) && $target_id === $setting['id'] ) {
                $output   = array_merge( $output, $insert );
                $inserted = true;
            }

            $output[] = $setting;

            if ( 'after' === $position && isset( $setting['id'] ) && $target_id === $setting['id'] ) {
                $output   = array_merge( $output, $insert );
                $inserted = true;
            }
        }

        return $inserted ? $output : array_merge( $settings, $insert );
    }

    public function downloadPluginLogs() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'kiriminaja-official' ), 403 );
        }

        check_admin_referer( 'kiriof_download_plugin_logs' );

        $files = $this->getPluginLogFiles();
        $filename = 'kiriminaja-plugin-logs-' . gmdate( 'Ymd-His' ) . '.log';

        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        echo "KiriminAja Plugin Logs\n";
        echo "Generated at: " . esc_html( current_time( 'mysql' ) ) . "\n";
        echo "Sources: " . esc_html( implode( ', ', $this->getPluginLogSources() ) ) . "\n\n";

        if ( empty( $files ) ) {
            echo "No KiriminAja plugin logs found.\n";
            exit;
        }

        foreach ( $files as $file ) {
            echo "\n===== " . esc_html( basename( $file ) ) . " =====\n";
            $content = file_get_contents( $file );
            if ( false === $content ) {
                echo "Unable to read log file.\n";
                continue;
            }
            echo esc_html( $this->redactLogContent( $content ) );
            echo "\n";
        }

        exit;
    }

    private function getPluginLogSources(): array {
        return array(
            'kiriminaja',
            'kiriminaja_api',
            'kiriminaja_debug',
            'kiriminaja_import',
            'kiriminaja_payment',
            'kiriminaja_print',
            'kiriminaja_request_pickup',
            'kiriminaja_settings',
            'kiriminaja_shipping',
            'kiriminaja_webhook',
            'shipping_discount_coupon',
        );
    }

    private function getPluginLogFiles(): array {
        $upload_dir = wp_upload_dir();
        $log_dir = defined( 'WC_LOG_DIR' ) ? WC_LOG_DIR : trailingslashit( $upload_dir['basedir'] ) . 'wc-logs/';
        $log_dir_real = realpath( $log_dir );
        if ( false === $log_dir_real || ! is_dir( $log_dir_real ) ) {
            return array();
        }

        $files = array();
        foreach ( $this->getPluginLogSources() as $source ) {
            foreach ( glob( trailingslashit( $log_dir_real ) . $source . '-*.log' ) ?: array() as $file ) {
                $real_file = realpath( $file );
                if ( false === $real_file || 0 !== strpos( $real_file, $log_dir_real ) || ! is_readable( $real_file ) ) {
                    continue;
                }
                $files[ $real_file ] = filemtime( $real_file ) ?: 0;
            }
        }

        arsort( $files );
        return array_keys( $files );
    }

    private function redactLogContent( string $content ): string {
        $patterns = array(
            '/(authorization|api_key|setup_key|token|pin)(["\'\s:=]+)([^,"\'\s}]+)/i',
            '/(Bearer\s+)[A-Za-z0-9._\-]+/i',
        );

        return preg_replace( $patterns, '$1$2[redacted]', $content ) ?? $content;
    }
}
