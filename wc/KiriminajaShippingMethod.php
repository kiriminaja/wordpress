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
                if ($this->hasActiveFreeShippingCoupon()) {
                    if ( function_exists( 'WC' ) && WC() && isset( WC()->session ) && WC()->session ) {
                        WC()->session->set( 'kiriof_shipping_coupon_rate_meta', array() );
                    }
                    // Add a 0-cost rate so KiriminAja remains a valid shipping option
                    // rather than leaving the customer with no available shipping methods.
                    $this->add_rate( array(
                        'id'    => $this->id . '_free',
                        'label' => __( 'Free shipping', 'kiriminaja-official' ),
                        'cost'  => 0,
                    ) );
                    return;
                }

                $destination_id = WC()->session->get( 'shipping_destination_id' );
                if ( empty( $destination_id ) ) {
                    $destination_id = WC()->session->get( 'destination_id' );
                }
                // Fallback: read from customer additional fields in case the
                // session was not persisted between API requests.
                if ( empty( $destination_id ) ) {
                    try {
                        if ( isset( WC()->customer ) && is_object( WC()->customer ) ) {
                            $meta_keys = array(
                                'shipping_kiriminaja-official/kiriof_destination_area',
                                'kiriminaja-official/kiriof_destination_area',
                                '_wc_blocks_checkout_field_kiriminaja-official/kiriof_destination_area',
                                'additional_field_kiriminaja-official/kiriof_destination_area',
                            );
                            foreach ( $meta_keys as $mk ) {
                                $dest = WC()->customer->get_meta( $mk );
                                if ( ! empty( $dest ) ) {
                                    $destination_id = (int) $dest;
                                    kiriof_log( 'info', 'Fallback: found destination_id=' . $destination_id . ' from meta key: ' . $mk );
                                    break;
                                }
                            }
                            // Dump all customer meta if still not found
                            if ( empty( $destination_id ) ) {
                                $all_meta = WC()->customer->get_meta_data();
                                foreach ( $all_meta as $m ) {
                                    if ( stripos( $m->key, 'kiriof_destination_area' ) !== false ) {
                                        $destination_id = (int) $m->value;
                                        kiriof_log( 'info', 'Fallback: found via scan meta key=' . $m->key . ' value=' . $m->value );
                                        break;
                                    }
                                }
                            }
                        } else {
                            kiriof_log( 'warning', 'Fallback: WC()->customer not available' );
                        }
                    } catch ( \Exception $e ) {
                        kiriof_log( 'error', 'Fallback exception: ' . $e->getMessage() );
                    }
                }

                kiriof_log( 'info', 'calculate_shipping destination_id=' . var_export( $destination_id, true ) );
                $kiriof_insurance = WC()->session->get( 'kiriof_insurance' );

                if ( empty( $destination_id ) ) {
                    if ( function_exists( 'WC' ) && WC() && isset( WC()->session ) && WC()->session ) {
                        WC()->session->set( 'kiriof_shipping_coupon_rate_meta', array() );
                    }
                    return;
                }
                  
                
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
                    'insurance' => (int) $kiriof_insurance,
                    'item_value' => (int) ($cartAttributes->data['item_value'] ?? 0),
                    'courier' => "", // 'jne', 'pos', 'tiki', 'jet'
                ];

                $kiriofPricing = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getPricing($payload);
                kiriof_log( 'info', 'getPricing result keys=' . ( is_array( $kiriofPricing ) ? implode( ',', array_keys( $kiriofPricing ) ) : gettype( $kiriofPricing ) ) );
                if ( isset( $kiriofPricing['status'] ) ) {
                    kiriof_log( 'info', 'getPricing status=' . var_export( $kiriofPricing['status'], true ) );
                }
                if ( isset( $kiriofPricing['data'] ) && is_array( $kiriofPricing['data'] ) ) {
                    kiriof_log( 'info', 'getPricing data count=' . count( $kiriofPricing['data'] ) );
                }
                
                $res_pricing = $kiriofPricing['data']; //object
                $kiriofRateMetaMap = array();
                foreach($this->filterOptions($res_pricing, $quantity, $kiriof_insurance) as $row){
                    
                    $rowMeta    = $row['meta_data'] ?? [];
                    $origCost   = (float) ( $rowMeta['kiriof_shipping_coupon_original_cost'] ?? $row['cost'] );
                    $discAmount = (float) ( $rowMeta['kiriof_shipping_coupon_discount_amount'] ?? 0 );
                    $notice     = (string) ( $rowMeta['kiriof_shipping_coupon_notice'] ?? '' );
                    $badge      = (string) ( $rowMeta['kiriof_shipping_coupon_badge'] ?? '' );

                    // Only pass display-safe meta to the WC_Shipping_Rate object.
                    // Coupon pricing meta (original_cost, discount_amount, notice, badge)
                    // must NOT be included here: WooCommerce Block checkout (e.g. ShopVerse)
                    // reads ALL meta_data from WC_Shipping_Rate and renders each value as a
                    // sub-line in the Order Summary, causing numeric prices to appear janky.
                    // Those values are stored in the WC session (kiriof_shipping_coupon_rate_meta).
                    $rate = array(
                        'id'        => $this->id . '_' . $row['key'],
                        'label'     => $row['value'],
                        'cost'      => $row['cost'],
                        'meta_data' => array(
                            'kiriof_rate_eta'         => (string) ( $rowMeta['kiriof_rate_eta'] ?? '' ),
                            'kiriof_rate_description' => (string) ( $rowMeta['kiriof_rate_description'] ?? '' ),
                        ),
                    );

                    $kiriofRateMetaMap[ $rate['id'] ] = array(
                        'label'                   => (string) $rate['label'],
                        'cost'                    => (float) $rate['cost'],
                        'original_cost'           => $origCost,
                        'discount_amount'         => $discAmount,
                        'notice'                  => $notice,
                        'badge'                   => $badge,
                        'eta'                     => (string) ( $rowMeta['kiriof_rate_eta'] ?? '' ),
                        'description'             => (string) ( $rowMeta['kiriof_rate_description'] ?? '' ),
                        'formatted_cost'          => wp_strip_all_tags( wc_price( (float) $rate['cost'] ) ),
                        'formatted_original_cost' => wp_strip_all_tags( wc_price( $origCost ) ),
                    );

                    $this->add_rate($rate);
                }

                if ( function_exists( 'WC' ) && WC() && isset( WC()->session ) && WC()->session ) {
                    $existingRateMetaMap = (array) WC()->session->get( 'kiriof_shipping_coupon_rate_meta', array() );
                    WC()->session->set( 'kiriof_shipping_coupon_rate_meta', array_merge( $existingRateMetaMap, $kiriofRateMetaMap ) );
                }

            }

            public function filterOptions($pricingData, $quantity, $kiriof_insurance = null){

                $shippingDiscountService = new \KiriminAjaOfficial\Services\ShippingDiscountCouponService();

                $chosen_payment_method = WC()->session->get('chosen_payment_method') ?: WC()->session->get('kiriof_payment_method');

                /**
                 * Payment is only needed to decide whether we should restrict
                 * rates to COD-capable services. Classic checkout can calculate
                 * shipping during update_order_review before a payment radio is
                 * checked, so an empty payment method must still show regular
                 * non-COD shipping rates instead of hiding the whole list.
                 */
                $is_cod = $chosen_payment_method === 'cod';

                $options = $pricingData->results ?? [];

                
                $validate = (new \KiriminAjaOfficial\Repositories\SettingRepository())->validateWhiteListExpedition($options);
                
                
                $options = $validate;
                
                $filteredOptions = [];
                foreach ($options as $option){
                    if (!$is_cod || $is_cod && $option->cod){
                        
                        $shipping_cost = $option->cost - $option->discount_amount;
                        $shippingDiscountPricing = $shippingDiscountService->getAdjustedRatePricing($option, (float) $shipping_cost);

                        $filteredOptions[] = [
                            'key'=>$option->service.'_'.$option->service_type,
                            'value'=>$option->service_name,
                            'cost'=>$shippingDiscountPricing['cost'],
                            'meta_data'=>[
                                'kiriof_shipping_coupon_original_cost' => (float) $shippingDiscountPricing['original_cost'],
                                'kiriof_shipping_coupon_discount_amount' => (float) $shippingDiscountPricing['discount_amount'],
                                'kiriof_shipping_coupon_notice' => (string) $shippingDiscountPricing['notice'],
                                'kiriof_shipping_coupon_badge' => (string) $shippingDiscountPricing['badge'],
                                'kiriof_rate_eta' => $this->formatEta($option),
                                'kiriof_rate_description' => $this->formatRateDescription($option, $kiriof_insurance),
                            ],
                        ];    
                    }
                }

                // Sort by cost ascending to maintain consistent ordering across re-renders
                usort($filteredOptions, function($a, $b) {
                    return $a['cost'] <=> $b['cost'];
                });
                
                return $filteredOptions;
            }

            private function formatEta($option) {
                $eta = '';

                foreach ( array( 'etd', 'eta', 'estimation', 'estimation_day', 'lead_time' ) as $field ) {
                    if ( isset( $option->{$field} ) && '' !== (string) $option->{$field} ) {
                        $eta = (string) $option->{$field};
                        break;
                    }
                }

                $eta = trim( preg_replace( '/\s+/', ' ', $eta ) );
                if ( '' === $eta ) {
                    return '';
                }

                if ( preg_match( '/business day/i', $eta ) ) {
                    return $eta;
                }

                if ( preg_match( '/^\d+\s*(?:-|to)\s*\d+$/i', $eta ) ) {
                    return $eta . ' business days';
                }

                if ( preg_match( '/^\d+$/', $eta ) ) {
                    return '1' === $eta ? '1 business day' : $eta . ' business days';
                }

                return $eta;
            }

            private function formatRateDescription($option, $kiriof_insurance) {
                $parts = array();
                $service_type = isset( $option->service_type ) ? strtoupper( trim( (string) $option->service_type ) ) : '';
                $service_label = $this->describeServiceType( $service_type );

                if ( '' !== $service_label ) {
                    $parts[] = $service_label;
                }

                if ( ! empty( $kiriof_insurance ) ) {
                    $parts[] = __( 'Includes insurance', 'kiriminaja-official' );
                }

                return implode( ' • ', array_filter( $parts ) );
            }

            private function describeServiceType( $service_type ) {
                $service_type = strtoupper( preg_replace( '/[^A-Z]/', '', (string) $service_type ) );

                if ( '' === $service_type ) {
                    return '';
                }

                $map = array(
                    'JTR' => __( 'Cargo service', 'kiriminaja-official' ),
                    'REG' => __( 'Regular service', 'kiriminaja-official' ),
                    'OKE' => __( 'Economy service', 'kiriminaja-official' ),
                    'YES' => __( 'Next-day service', 'kiriminaja-official' ),
                    'SDS' => __( 'Same-day service', 'kiriminaja-official' ),
                    'ONS' => __( 'Overnight service', 'kiriminaja-official' ),
                );

                foreach ( $map as $prefix => $label ) {
                    if ( 0 === strpos( $service_type, $prefix ) ) {
                        return $label;
                    }
                }

                return ucwords( strtolower( $service_type ) ) . ' ' . __( 'service', 'kiriminaja-official' );
            }

            private function hasActiveFreeShippingCoupon(){
                if (!function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart) {
                    return false;
                }

                foreach (WC()->cart->get_coupons() as $coupon) {
                    if ($coupon && method_exists($coupon, 'get_free_shipping') && $coupon->get_free_shipping()) {
                        return true;
                    }
                }

                return false;
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
