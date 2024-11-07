<?php 
namespace inc\Controllers;

class ProductController{
    
    public function register(){
        self::hook();
    }

    private function hook(){
        /**
         * General product Tab Custom Field
         */
        add_action( 'woocommerce_product_options_general_product_data', [$this,'kj_custom_field_shipping_product'] ); 
        
        /**
         * save product custom field
         */ 
        add_action( 'woocommerce_process_product_meta', [$this,'kj_save_product_custom_fields'] );
    
    }

    public function kj_custom_field_shipping_product(){
        global $post;
        include_once KJ_DIR .'templates/product/general-wc-tab-setting.php'; 
    }

    public function kj_save_product_custom_fields($post_id){
       
        /**
         * value Empty
         */
        if( empty($_POST['_weight']) ) $_POST['_weight'] = $_POST['_kj_weight'];
        if( empty($_POST['_length']) ) $_POST['_length'] = $_POST['_kj_length'];
        if( empty($_POST['_width']) ) $_POST['_width'] = $_POST['_kj_width'];
        if( empty($_POST['_height']) ) $_POST['_height'] = $_POST['_kj_height'];

        /**
         * value is exist value
         * _weight
         * _length
         * _width
         * _height
         */
        if( $_POST['_weight'] != $_POST['_kj_weight'] ) $_POST['_weight'] = $_POST['_kj_weight'];
        if ( $_POST['_length'] != $_POST['_kj_length'] ) $_POST['_length'] = $_POST['_kj_length'];
        if ( $_POST['_width'] != $_POST['_kj_width'] ) $_POST['_width'] = $_POST['_kj_width'];
        if ( $_POST['_height'] != $_POST['_kj_height'] ) $_POST['_height'] = $_POST['_kj_height'];
        
        /** Update Post Meta */
        if ( !empty($_POST['_weight']) ) update_post_meta($post_id, '_weight', $_POST['_weight']);
        if ( !empty($_POST['_length']) ) update_post_meta($post_id, '_length', $_POST['_length']);
        if ( !empty($_POST['_width']) ) update_post_meta($post_id, '_width', $_POST['_width']);
        if ( !empty($_POST['_height']) ) update_post_meta($post_id, '_height', $_POST['_height']);
    }   

}