<?php
namespace inc\Pages;

use Inc\Controllers\ShippingMethodController;
/**
 * Create Automatically Page Generate
 */
class AdminPost
{
    public function register(){
        if(KJ_CHECK_WOOCOMMERCE()){
            if( empty(self::checkPageExist('checkout')) ){
                self::createPageKiriminaja();
            }else{
                self::updatePage(self::checkPageExist('checkout')->ID,'[woocommerce_checkout]');
                self::setPageCheckoutWoocommerce(self::checkPageExist('checkout')->ID);
            }

            if( empty(self::checkPageExist('tracking')) ){
                self::createPageKiriminajaTracking('tracking');
            }

            if( empty(self::checkPageExist('cart')) ){
                self::createPageCartKiriminaja();
            }else{
                self::updatePage(self::checkPageExist('cart')->ID,'[woocommerce_cart]');
                self::setPageCartWoocommerce(self::checkPageExist('cart')->ID);
            }
            
            self::setLegacyWoocommerceKiriminaja();
            self::setShippingCalculateCartWoocommerce();
            self::setShippingCodEnabled();            
        }
    }

    private function createPageKiriminaja(){
        $kj_page_checkout = array(
            'post_title'    => wp_strip_all_tags( 'Checkout' ),
            'post_content'  => '[woocommerce_checkout]',
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_type'     => 'page',
        );
        
        #create page ID
        $pageID = wp_insert_post( $kj_page_checkout );

        self::setPageCheckoutWoocommerce( $pageID );     
    }

    private function createPageKiriminajaTracking(){
        $kj_page = array(
            'post_title'    => wp_strip_all_tags( 'Tracking' ),
            'post_content'  => '[wp-tracking-front-page]',
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_type'     => 'page',
        );
        
        #create page ID
        $pageID = wp_insert_post( $kj_page );
    }

    private function createPageCartKiriminaja(){
        $kj_page = array(
            'post_title'    => wp_strip_all_tags( 'Cart' ),
            'post_content'  => '[woocommerce_cart]',
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_type'     => 'page',
        );
        
        #create page ID
        $pageID = wp_insert_post( $kj_page );

        self::setPageCartWoocommerce($pageID);
    } 	

    /**
     * @return boolean
     */
    private function setPageCheckoutWoocommerce($pageID){
        #set Checkout Page Woocommerce
        update_option( 'woocommerce_checkout_page_id', $pageID );
    }

    private function setPageCartWoocommerce($pageID){
        #set Cart Page Woocommerce
        update_option( 'woocommerce_cart_page_id', $pageID );
    }

    private function setShippingCodEnabled(){
        $key_woo_cod = 'woocommerce_cod_settings';
        $arr_cod = get_option($key_woo_cod); //array
        
        //set anabled is yes
        $arr_cod['enabled'] = 'yes';

        update_option($key_woo_cod,$arr_cod);
        
    }

    /**
     * @return object
     */
    private function checkPageExist($slug){
        return get_page_by_path($slug);
    }

    private function updatePage($pageID,$content){
        $args = array(
            'ID'           => $pageID,
            'post_content' => $content,
        );
      
        wp_update_post( $args );
    }

    /** set Legacy Woocommerce Kiriminaja */
    private function setLegacyWoocommerceKiriminaja(){
        global $wpdb;

        #set Legacy Woocommerce Kiriminaja
        $data   = array( 'option_value'=>'no');
        $where  = array( 'option_name' => 'woocommerce_custom_orders_table_enabled' );
        $wpdb->update( $wpdb->prefix . 'options', $data, $where );

        // update_option( 'woocommerce_custom_orders_table_enabled','no');
        
    }

    /** Set Shipping Woocommerce Calculate Shipping Cart */
    private function setShippingCalculateCartWoocommerce(){
        update_option('woocommerce_enable_shipping_calc','yes');
    }
}