<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create Shipping Method Kiriminaja
 * --------------------------------
 * Admin Setting
 */
add_action('woocommerce_shipping_init', 'kiriof_shipping_method',99);
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Required for WooCommerce action callback
function kiriof_shipping_method(){
    if (!class_exists('Kiriof_Shipping_Method_Controller')) {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- WooCommerce shipping method class
        class Kiriof_Shipping_Method_Controller extends WC_Shipping_Method
        {
            public function __construct(){
                
                $this->id = 'kiriminaja-official';
                $this->method_title = __('Kiriminaja', 'kiriminaja-official');
                $this->method_description = __('Custom Shipping Method for Kiriminaja', 'kiriminaja-official');
                
                $this->init();
                $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
                $this->title = isset($this->settings['title']) ? $this->settings['title'] : __('Kiriminaja Shipping', 'kiriminaja-official');
            }
    
            /**
            * Load the settings API
            */
            function init(){
                $this->initFormFields();
                $this->init_settings();
                add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            }
    
            function initFormFields(){
                $this->form_fields = array(
                        'enabled' => array(
                        'title' => __('Enable', 'kiriminaja-official'),
                        'type' => 'checkbox',
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title' => __('Title', 'kiriminaja-official'),
                        'type' => 'text',
                        'default' => __('Kiriminaja Shipping', 'kiriminaja-official')
                    ),
                );
            }
    
            public function calculate_shipping( $package = array() ){
                $country = $package["destination"]["country"];
                $destination_id = WC()->session->get( 'destination_id' );
                $kj_insurance = WC()->session->get( 'kj_insurance' );
                  
                
                $length = 0;
                $width = 0;
                $height = 0;
                $quantity = 0;
                foreach ($package['contents'] as $item_id => $values) {
                    $_product = $values['data'];
                    $quantity += $values['quantity'];
                    $length += (int) $_product->get_length();
                    $width += (int) $_product->get_width();
                    $height += (int) $_product->get_height();
                }

                $settingRepo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('origin_sub_district_id');
                if(!$settingRepo||$settingRepo->value === null){
                    wc_add_notice(__("Silahkan Input Terlebih dahulu Origin di Plugin Kiriminaja",'kiriminaja-official'), "error");
                    return;
                }

                /** convert unit weight */
                $cartAttributes = (new \KiriminAjaOfficial\Services\UtilServices\GetWCCartAttributeService([
                    'wc_cart_contents' => WC()->cart->get_cart()
                ]))->call();

                $payload = [
                    'subdistrict_origin' => (int) $settingRepo->value,
                    'subdistrict_destination'=>$destination_id,
                    'weight' => $cartAttributes->data['weight'],
                    'length' => $cartAttributes->data['length'],
                    'width' =>  $cartAttributes->data['width'],
                    'height' => $cartAttributes->data['height'],
                    'insurance' => (int) $kj_insurance,
                    'item_value' => WC()->cart->cart_contents_total,
                    'courier' => "", // 'jne', 'pos', 'tiki', 'jet'
                ];

                $kjPricing = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getPricing($payload);
                
                $res_pricing = $kjPricing['data']; //object
                foreach($this->filterOptions($res_pricing,$quantity) as $row){
                    
                    $rate= array(
                        'id' => $this->id.'_'.$row['key'],
                        'label' => $row['value'],
                        'cost' => $row['cost'],
                    );

                    $this->add_rate($rate);
                }

            }

            public function filterOptions($pricingData,$quantity){

                $chosen_payment_method = WC()->session->get('chosen_payment_method');

                /** Validation Payment Method */
                if( is_checkout() ){
                    if(!$chosen_payment_method){
                        return [];
                    }
                }

                $is_cod = $chosen_payment_method === 'cod';

                $options = $pricingData->results ?? [];

                
                $validate = (new \KiriminAjaOfficial\Repositories\SettingRepository())->validateWhiteListExpedition($options);
                
                
                $options = $validate;
                
                $filteredOptions = [];
                foreach ($options as $option){
                    if (!$is_cod || $is_cod && $option->cod){
                        
                        $shipping_cost = $option->cost - $option->discount_amount;

                        $filteredOptions[] = [
                            'key'=>$option->service.'_'.$option->service_type,
                            'value'=>$option->service_name,
                            'cost'=>$shipping_cost
                        ];    
                    }
                }
                
                return $filteredOptions;
            }
            
        }
    }
}


add_filter('woocommerce_shipping_methods', 'kiriof_add_shipping_method');
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Required for WooCommerce filter callback
function kiriof_add_shipping_method($methods){
    $methods[] =  'Kiriof_Shipping_Method_Controller';
    return $methods;
}

add_filter( 'woocommerce_add_to_cart_validation', 'kiriof_add_date_validation', 10, 5 );
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Required for WooCommerce filter callback
function kiriof_add_date_validation( $passed, $product_id ) { 
    
    $product = wc_get_product( $product_id );

    $length = $product->get_length();
    $width = $product->get_width();
    $height = $product->get_height();
    
    $settingRepo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('origin_sub_district_id');
    if(!$settingRepo||$settingRepo->value === null){
        wc_add_notice(__("Silahkan Input Terlebih dahulu Origin di Plugin Kiriminaja",'kiriminaja-official'), "error");
        $passed = false;
    }
    /**
     * Check Product Weight
     */
    if( empty($product->get_weight()) ){
        wc_add_notice(__("Maaf Produk ini Tidak Memiliki Berat untuk Pengiriman",'kiriminaja-official'), "error");
        $passed = false;
    }

    /**
     * Check Product Dimention
     */
    if ( empty($length) || empty($width) || empty($height)) {
        wc_add_notice(__('Maaf Produk ini Tidak Memiliki Dimension untuk Pengiriman', 'kiriminaja-official'), 'error');
        $passed = false;
    }

    return $passed;

}

add_filter( 'woocommerce_shipping_calculator_enable_country', '__return_false' );
add_filter( 'woocommerce_shipping_calculator_enable_city', '__return_false' );
add_filter( 'woocommerce_shipping_calculator_enable_state', '__return_false' );
add_filter( 'woocommerce_shipping_calculator_enable_postcode', '__return_false' );