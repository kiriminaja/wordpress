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

        add_filter( 'woocommerce_general_settings', array( $this, 'injectWooCommerceGeneralSettings' ) );
        add_filter( 'woocommerce_get_settings_advanced', array( $this, 'injectWooCommerceAdvancedSettings' ) );
        add_action( 'woocommerce_admin_field_kiriof_area_select', array( $this, 'renderWooCommerceAreaSelectField' ) );
        add_action( 'woocommerce_admin_field_kiriof_pin_location', array( $this, 'renderWooCommercePinLocationField' ) );
        add_action( 'woocommerce_admin_field_kiriof_tracking_page_select', array( $this, 'renderWooCommerceTrackingPageSelectField' ) );
        add_action( 'woocommerce_update_options_general', array( $this, 'syncWooCommerceGeneralSettings' ) );
        add_action( 'woocommerce_update_options_advanced', array( $this, 'syncWooCommerceAdvancedSettings' ) );
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

            // Bust the couriers API cache so the updated whitelist is reflected immediately.
            ( new \KiriminAjaOfficial\Services\KiriminajaApiService() )->invalidateCouriersCache();

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

    public function injectWooCommerceGeneralSettings( $settings ) {
        if ( ! is_array( $settings ) ) {
            return $settings;
        }

        $origin = $this->getOriginSettingValues();

        $sender_fields = array(
            array(
                'title'    => __( 'Sender Name', 'kiriminaja-official' ),
                'id'       => 'kiriof_wc_origin_name',
                'type'     => 'text',
                'default'  => $origin['origin_name'] ?? '',
                'desc_tip' => __( 'Required by KiriminAja Official Plugin', 'kiriminaja-official' ),
            ),
            array(
                'title'    => __( 'Sender Phone', 'kiriminaja-official' ),
                'id'       => 'kiriof_wc_origin_phone',
                'type'     => 'text',
                'default'  => $origin['origin_phone'] ?? '',
                'desc_tip' => __( 'Required by KiriminAja Official Plugin', 'kiriminaja-official' ),
            ),
        );

        $area_field = array(
            array(
                'title'             => __( 'Area', 'kiriminaja-official' ),
                'id'                => 'kiriof_wc_origin_area',
                'type'              => 'kiriof_area_select',
                'default'           => $origin['origin_sub_district_id'] ?? '',
                'origin_area_name'  => $origin['origin_sub_district_name'] ?? '',
                'desc'              => __( 'Required by KiriminAja Official Plugin', 'kiriminaja-official' ),
            ),
        );

        $pin_location_field = array(
            array(
                'title'   => __( 'Pin Location', 'kiriminaja-official' ),
                'id'      => 'kiriof_wc_origin_pin_location',
                'type'    => 'kiriof_pin_location',
                'default' => '',
                'desc'    => __( 'Required by KiriminAja Official Plugin', 'kiriminaja-official' ),
            ),
        );

        $settings = $this->insertSettingsBeforeId( $settings, 'woocommerce_store_address', $sender_fields );
        $settings = $this->insertSettingsAfterId( $settings, 'woocommerce_store_address', $pin_location_field );
        $settings = $this->insertSettingsAfterId( $settings, 'woocommerce_default_country', $area_field );

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
                    <?php echo wc_help_tip( __( 'Required by KiriminAja Official Plugin', 'kiriminaja-official' ) ); ?>
                </label>
            </th>
            <td class="forminp">
                <select
                    id="<?php echo esc_attr( $field_id ); ?>"
                    name="<?php echo esc_attr( $field_id ); ?>"
                    class="<?php echo esc_attr( $class ); ?>"
                    style="<?php echo esc_attr( $css ); ?>"
                >
                    <option value=""><?php echo esc_html__( 'Select a page&hellip;', 'woocommerce' ); ?></option>
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
                    <?php echo wc_help_tip( __( 'Required by KiriminAja Official Plugin', 'kiriminaja-official' ) ); ?>
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

    public function syncWooCommerceGeneralSettings() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $posted_area_name = isset( $_POST['kiriof_wc_origin_area_name'] )
            ? sanitize_text_field( wp_unslash( $_POST['kiriof_wc_origin_area_name'] ) )
            : '';

        $origin = $this->getOriginSettingValues();
        $payload = array(
            'origin_name'              => isset( $_POST['kiriof_wc_origin_name'] ) ? sanitize_text_field( wp_unslash( $_POST['kiriof_wc_origin_name'] ) ) : ( $origin['origin_name'] ?? '' ),
            'origin_phone'             => isset( $_POST['kiriof_wc_origin_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['kiriof_wc_origin_phone'] ) ) : ( $origin['origin_phone'] ?? '' ),
            'origin_address'           => isset( $_POST['woocommerce_store_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['woocommerce_store_address'] ) ) : ( $origin['origin_address'] ?? '' ),
            'origin_zip_code'          => isset( $_POST['woocommerce_store_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_store_postcode'] ) ) : ( $origin['origin_zip_code'] ?? '' ),
            'origin_sub_district_id'   => isset( $_POST['kiriof_wc_origin_area'] ) ? sanitize_text_field( wp_unslash( $_POST['kiriof_wc_origin_area'] ) ) : ( $origin['origin_sub_district_id'] ?? '' ),
            'origin_sub_district_name' => '' !== $posted_area_name ? $posted_area_name : ( $origin['origin_sub_district_name'] ?? '' ),
            'origin_latitude'          => isset( $_POST['kiriof_wc_origin_latitude'] ) ? sanitize_text_field( wp_unslash( $_POST['kiriof_wc_origin_latitude'] ) ) : ( $origin['origin_latitude'] ?? '' ),
            'origin_longitude'         => isset( $_POST['kiriof_wc_origin_longitude'] ) ? sanitize_text_field( wp_unslash( $_POST['kiriof_wc_origin_longitude'] ) ) : ( $origin['origin_longitude'] ?? '' ),
        );

        update_option( 'kiriof_wc_origin_name', $payload['origin_name'] );
        update_option( 'kiriof_wc_origin_phone', $payload['origin_phone'] );
        update_option( 'kiriof_wc_origin_area', $payload['origin_sub_district_id'] );

        ( new \KiriminAjaOfficial\Repositories\SettingRepository() )->storeOriginMirrorData( $payload );
    }

    public function syncWooCommerceAdvancedSettings() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $page_id = isset( $_POST['kiriof_tracking_page_id'] ) ? absint( $_POST['kiriof_tracking_page_id'] ) : 0;
        if ( $page_id > 0 && ! $this->pageHasTrackingShortcode( $page_id ) ) {
            return;
        }

        update_option( 'kiriof_tracking_page_id', $page_id );
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
}
