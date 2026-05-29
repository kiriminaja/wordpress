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

        add_filter( 'manage_edit-product_columns', array( $this, 'kiriof_add_product_volumetric_column' ), 20 );
        add_action( 'manage_product_posts_custom_column', array( $this, 'kiriof_render_product_volumetric_column' ), 10, 2 );
        add_action( 'admin_head-edit.php', array( $this, 'kiriof_product_volumetric_column_styles' ) );

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
            // Nonce verified above in kiriof_save_product_custom_fields().
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( ! isset( $_POST[ $field_key ] ) ) {
                return '';
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
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

    public function kiriof_add_product_volumetric_column( $columns ) {
        $updated_columns = array();

        foreach ( $columns as $key => $label ) {
            $updated_columns[ $key ] = $label;

            if ( 'name' === $key ) {
                $updated_columns['kiriof_volumetric'] = __( 'Volumetric', 'kiriminaja-official' );
            }
        }

        if ( ! isset( $updated_columns['kiriof_volumetric'] ) ) {
            $updated_columns['kiriof_volumetric'] = __( 'Volumetric', 'kiriminaja-official' );
        }

        return $updated_columns;
    }

    public function kiriof_render_product_volumetric_column( $column, $post_id ) {
        if ( 'kiriof_volumetric' !== $column ) {
            return;
        }

        $product_ids = $this->kiriof_get_product_volumetric_ids( (int) $post_id );
        $total       = count( $product_ids );
        $configured  = 0;

        foreach ( $product_ids as $product_id ) {
            if ( $this->kiriof_product_has_volumetric_configuration( (int) $product_id ) ) {
                $configured++;
            }
        }

        $is_ready = ( $total > 0 && $configured >= $total );
        $label    = $is_ready
            ? __( 'All Product Configured', 'kiriminaja-official' )
            : sprintf(
                /* translators: %1$d: configured products, %2$d: total products */
                __( '%1$d / %2$d Configured', 'kiriminaja-official' ),
                $configured,
                $total
            );

        printf(
            '<span class="kiriof-volumetric-label %1$s">%2$s</span>',
            esc_attr( $is_ready ? 'is-ready' : 'is-warning' ),
            esc_html( $label )
        );
    }

    public function kiriof_product_volumetric_column_styles() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || 'edit-product' !== $screen->id ) {
            return;
        }
        ?>
        <style>
            .wp-list-table .column-kiriof_volumetric { width: 180px; }
            .kiriof-volumetric-label {
                display: inline-flex;
                align-items: center;
                max-width: 160px;
                padding: 2px 8px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 600;
                line-height: 1.6;
                text-align: center;
                white-space: normal;
            }
            .kiriof-volumetric-label.is-ready {
                background: #edfaef;
                color: #007017;
                border: 1px solid #b7e5be;
            }
            .kiriof-volumetric-label.is-warning {
                background: #fcf0f1;
                color: #8a2424;
                border: 1px solid #f4cccc;
            }
        </style>
        <?php
    }

    private function kiriof_get_product_volumetric_ids( $post_id ) {
        $product_ids = array( (int) $post_id );

        if ( function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $post_id );

            if ( $product && $product->is_type( 'variable' ) ) {
                $product_ids = array_map( 'intval', $product->get_children() );
            }
        }

        return array_values( array_unique( array_filter( $product_ids ) ) );
    }

    private function kiriof_product_has_volumetric_configuration( $post_id ) {
        $required_meta = array( '_weight', '_length', '_width', '_height' );

        foreach ( $required_meta as $meta_key ) {
            if ( (float) get_post_meta( $post_id, $meta_key, true ) <= 0 ) {
                return false;
            }
        }

        return true;
    }

}
