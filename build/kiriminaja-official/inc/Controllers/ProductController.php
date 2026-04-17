<?php 
namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProductController{
    
    public function register(){
        self::hook();
    }

    private function hook(){
        /**
         * General product Tab Custom Field
         */
        add_action( 'woocommerce_product_options_general_product_data', [$this,'kiriof_custom_field_shipping_product'] ); 
        
        /**
         * save product custom field
         */ 
        add_action( 'woocommerce_process_product_meta', [$this,'kiriof_save_product_custom_fields'] );
        
        add_action('woocommerce_product_options_general_product_data', array($this,'kiriof_editproduct_nonce') );

    }

    public function kiriof_custom_field_shipping_product(){
        global $post;
        include_once KIRIOF_DIR .'templates/product/general-wc-tab-setting.php'; 
    }

    public function kiriof_editproduct_nonce() {
        wp_nonce_field( KIRIOF_NONCE, 'kiriof_product_nonce_field' );
    }

    public function kiriof_save_product_custom_fields($post_id){
       
        // Check for nonce security - fail early if missing or invalid
        if ( ! isset( $_POST['kiriof_product_nonce_field'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kiriof_product_nonce_field'] ) ), KIRIOF_NONCE ) ) {
            return;
        }

        // Sanitize and assign values from $_POST to $_POST['weight'], etc.
        if (empty($_POST['_weight'])) {
            $_POST['_weight'] = isset($_POST['_kiriof_weight']) ? sanitize_text_field( wp_unslash($_POST['_kiriof_weight'] )) : '';
        }

        if (empty($_POST['_length'])) {
            $_POST['_length'] = isset($_POST['_kiriof_length']) ? sanitize_text_field(wp_unslash( $_POST['_kiriof_length'])) : '';
        }

        if (empty($_POST['_width'])) {
            $_POST['_width'] = isset($_POST['_kiriof_width']) ? sanitize_text_field(wp_unslash($_POST['_kiriof_width'])) : '';
        }

        if (empty($_POST['_height'])) {
            $_POST['_height'] = isset($_POST['_kiriof_height']) ? sanitize_text_field(wp_unslash($_POST['_kiriof_height'])) : '';
        }


        /**
         * value is exist value
         * _weight
         * _length
         * _width
         * _height
         */
        // Sanitize and unslash inputs before saving
        if (isset($_POST['_kiriof_weight'])) {
            $_POST['_kiriof_weight'] = wp_unslash($_POST['_kiriof_weight']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $_POST['_weight'] = sanitize_text_field($_POST['_kiriof_weight']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

        }

        if (isset($_POST['_kiriof_length'])) {
            $_POST['_kiriof_length'] = wp_unslash($_POST['_kiriof_length']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $_POST['_length'] = sanitize_text_field($_POST['_kiriof_length']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

        }

        if (isset($_POST['_kiriof_width'])) {
            $_POST['_kiriof_width'] = wp_unslash($_POST['_kiriof_width']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $_POST['_width'] = sanitize_text_field($_POST['_kiriof_width']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

        }

        if (isset($_POST['_kiriof_height'])) {
            $_POST['_kiriof_height'] = wp_unslash($_POST['_kiriof_height']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $_POST['_height'] = sanitize_text_field($_POST['_kiriof_height']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

        }

        // Update Post Meta if the values are not empty
        if (!empty($_POST['_weight'])) {
            update_post_meta($post_id, '_weight', sanitize_text_field( wp_unslash( $_POST['_weight'] )) );
        }

        if (!empty($_POST['_length'])) {
            update_post_meta($post_id, '_length', sanitize_text_field( wp_unslash( $_POST['_length'])) );
        }

        if (!empty($_POST['_width'])) {
            update_post_meta($post_id, '_width', sanitize_text_field( wp_unslash( $_POST['_width'])) );
        }

        if (!empty($_POST['_height'])) {
            update_post_meta($post_id, '_height', sanitize_text_field( wp_unslash( $_POST['_height'])) );
        }
    }   

}