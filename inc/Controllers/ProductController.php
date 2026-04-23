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

        // Check for nonce security - fail early if missing or invalid.
        if ( ! isset( $_POST['kiriof_product_nonce_field'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kiriof_product_nonce_field'] ) ), KIRIOF_NONCE )
        ) {
            return;
        }

        // Capability check — only users who can edit this product may save its meta.
        if ( ! current_user_can( 'edit_post', (int) $post_id ) ) {
            return;
        }

        /**
         * Read a numeric dimension/weight value from $_POST as a non-negative float string.
         * Returns an empty string when the field is missing or not numeric.
         */
        $kiriof_read_numeric = static function ( $field_key ) {
            if ( ! isset( $_POST[ $field_key ] ) ) {
                return '';
            }
            $raw = sanitize_text_field( wp_unslash( $_POST[ $field_key ] ) );
            // Allow only digits, dot, comma and optional leading minus before normalising.
            $raw = preg_replace( '/[^0-9.,\-]/', '', $raw );
            if ( '' === $raw || ! is_numeric( str_replace( ',', '.', $raw ) ) ) {
                return '';
            }
            $value = (float) str_replace( ',', '.', $raw );
            if ( $value < 0 ) {
                return '';
            }
            return (string) $value;
        };

        $kiriof_weight = $kiriof_read_numeric( '_kiriof_weight' );
        $kiriof_length = $kiriof_read_numeric( '_kiriof_length' );
        $kiriof_width  = $kiriof_read_numeric( '_kiriof_width' );
        $kiriof_height = $kiriof_read_numeric( '_kiriof_height' );

        // Fall back to WooCommerce's own _weight/_length/_width/_height fields when the
        // KiriminAja-specific fields were not submitted.
        if ( '' === $kiriof_weight ) {
            $kiriof_weight = $kiriof_read_numeric( '_weight' );
        }
        if ( '' === $kiriof_length ) {
            $kiriof_length = $kiriof_read_numeric( '_length' );
        }
        if ( '' === $kiriof_width ) {
            $kiriof_width = $kiriof_read_numeric( '_width' );
        }
        if ( '' === $kiriof_height ) {
            $kiriof_height = $kiriof_read_numeric( '_height' );
        }

        if ( '' !== $kiriof_weight ) {
            update_post_meta( $post_id, '_weight', $kiriof_weight );
        }
        if ( '' !== $kiriof_length ) {
            update_post_meta( $post_id, '_length', $kiriof_length );
        }
        if ( '' !== $kiriof_width ) {
            update_post_meta( $post_id, '_width', $kiriof_width );
        }
        if ( '' !== $kiriof_height ) {
            update_post_meta( $post_id, '_height', $kiriof_height );
        }
    }

}