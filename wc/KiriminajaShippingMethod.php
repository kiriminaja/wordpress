<?php
/**
 * Create Shipping Method Kiriminaja
 * --------------------------------
 * Admin Setting
 */
add_action('woocommerce_shipping_init', 'kj_shippingMethod',99);
function kj_shippingMethod(){
    if (!class_exists('ShippingMethodController')) {
        class ShippingMethodController extends WC_Shipping_Method
        {
            public function __construct(){
                
                $this->id = 'kiriminaja';
                $this->method_title = __('Kiriminaja', 'plugin-wp');
                $this->method_description = __('Custom Shipping Method for Kiriminaja', 'plugin-wp');
                
                $this->init();
                $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
                $this->title = isset($this->settings['title']) ? $this->settings['title'] : __('Kiriminaja Shipping', 'plugin-wp');
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
                        'title' => __('Enable', 'plugin-wp'),
                        'type' => 'checkbox',
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title' => __('Title', 'plugin-wp'),
                        'type' => 'text',
                        'default' => __('Kiriminaja Shipping', 'plugin-wp')
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

                $settingRepo = (new \Inc\Repositories\SettingRepository())->getSettingByKey('origin_sub_district_id');
                if(!$settingRepo||$settingRepo->value === null){
                    wc_add_notice(__("Silahkan Input Terlebih dahulu Origin di Plugin Kiriminaja",'plugin-wp'), "error");
                    return;
                }

                /** convert unit weight */
                $cartAttributes = (new \Inc\Services\UtilServices\GetWCCartAttributeService([
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

                $kjPricing = (new \Inc\Repositories\KiriminajaApiRepository())->getPricing($payload);
                
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

                
                $validate = (new \Inc\Repositories\SettingRepository())->validateWhiteListExpedition($options);
                
                
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


add_filter('woocommerce_shipping_methods', 'kj_addShippingMethod');
function kj_addShippingMethod($methods){
    $methods[] =  'ShippingMethodController';
    return $methods;
}

add_filter( 'woocommerce_add_to_cart_validation', 'kj_add_the_date_validation', 10, 5 );
function kj_add_the_date_validation( $passed, $product_id ) { 
    
    $product = get_product( $product_id );

    $length = $product->get_length();
    $width = $product->get_width();
    $height = $product->get_height();
    
    $settingRepo = (new \Inc\Repositories\SettingRepository())->getSettingByKey('origin_sub_district_id');
    if(!$settingRepo||$settingRepo->value === null){
        wc_add_notice(__("Silahkan Input Terlebih dahulu Origin di Plugin Kiriminaja",'plugin-wp'), "error");
        $passed = false;
    }
    /**
     * Check Product Weight
     */
    if( empty($product->get_weight()) ){
        wc_add_notice(__("Maaf Produk ini Tidak Memiliki Berat untuk Pengiriman",'plugin-wp'), "error");
        $passed = false;
    }

    /**
     * Check Product Dimention
     */
    if ( empty($length) || empty($width) || empty($height)) {
        wc_add_notice(__('Maaf Produk ini Tidak Memiliki Dimension untuk Pengiriman', 'plugin-wp'), 'error');
        $passed = false;
    }

    return $passed;

}

add_filter( 'woocommerce_shipping_calculator_enable_country', '__return_false' );
add_filter( 'woocommerce_shipping_calculator_enable_city', '__return_false' );
add_filter( 'woocommerce_shipping_calculator_enable_state', '__return_false' );
add_filter( 'woocommerce_shipping_calculator_enable_postcode', '__return_false' );