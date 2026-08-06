<?php 
namespace KiriminAjaOfficial\Controllers;

use KiriminAjaOfficial\Services\ShipmentLocationService;

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
        add_action( 'add_meta_boxes_product', array( $this, 'register_shipment_location_meta_box' ) );
        
        /**
         * save product custom field
         */ 
        add_action( 'woocommerce_process_product_meta', [$this,'kiriof_save_product_custom_fields'] );
        
        add_action( 'woocommerce_product_after_variable_attributes', [$this, 'render_variation_shipment_location_field'], 10, 3 );
        add_action( 'woocommerce_save_product_variation', [$this, 'save_variation_shipment_location_field'], 10, 2 );

        add_filter( 'manage_edit-product_columns', array( $this, 'kiriof_add_product_volumetric_column' ), 20 );
        add_action( 'manage_product_posts_custom_column', array( $this, 'kiriof_render_product_volumetric_column' ), 10, 2 );
        add_action( 'admin_head-edit.php', array( $this, 'kiriof_product_volumetric_column_styles' ) );

    }

    public function kiriof_custom_field_shipping_product(){
        global $post;
        include_once KIRIOF_DIR .'templates/product/general-wc-tab-setting.php'; 
    }

    public function register_shipment_location_meta_box() {
        add_meta_box(
            'kiriof-shipment-locations',
            __( 'Shipment Locations', 'kiriminaja-official' ),
            array( $this, 'render_shipment_location_meta_box' ),
            'product',
            'side',
            'high'
        );
    }

    public function render_shipment_location_meta_box( $post ) {
        $locations = ( new ShipmentLocationService() )->repository()->getAll( true );
        $value     = get_post_meta( $post->ID, ShipmentLocationService::META_KEY, true );

        wp_nonce_field( KIRIOF_NONCE, 'kiriof_product_nonce_field' );
        ?>
        <p><?php esc_html_e( 'This option is managed by the KiriminAja plugin and determines the origin used to calculate shipping and fulfill this product.', 'kiriminaja-official' ); ?></p>
        <p>
            <label for="<?php echo esc_attr( ShipmentLocationService::META_KEY ); ?>"><strong><?php esc_html_e( 'Shipment location', 'kiriminaja-official' ); ?></strong></label>
            <select class="widefat" id="<?php echo esc_attr( ShipmentLocationService::META_KEY ); ?>" name="<?php echo esc_attr( ShipmentLocationService::META_KEY ); ?>">
                <option value=""><?php esc_html_e( 'Use default shipment location', 'kiriminaja-official' ); ?></option>
                <?php foreach ( $locations as $location ) : ?>
                    <option value="<?php echo esc_attr( $location->id ); ?>" <?php selected( (string) $value, (string) $location->id ); ?>><?php echo esc_html( $location->name ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=general#kiriof-shipment-locations' ) ); ?>"><?php esc_html_e( 'Manage shipment locations', 'kiriminaja-official' ); ?></a>
        </p>
        <?php
    }

    public function render_variation_shipment_location_field($loop, $variation_data, $variation) {
        $this->render_location_select((int) $variation->ID, ShipmentLocationService::META_KEY . '[' . (int) $variation->ID . ']', true);
    }

    private function render_location_select($post_id, $field_id, $inherit) {
        $locations = (new ShipmentLocationService())->repository()->getAll(true);
        if (empty($locations)) {
            return;
        }
        $options = array('' => $inherit ? __('Use parent product location', 'kiriminaja-official') : __('Use default shipment location', 'kiriminaja-official'));
        foreach ($locations as $location) {
            $options[(string) $location->id] = $location->name;
        }
        woocommerce_wp_select(array(
            'id' => $field_id,
            'label' => __('Shipment location', 'kiriminaja-official'),
            'description' => __( 'This option is managed by the KiriminAja plugin. Select a location only to override the parent product location for this variation.', 'kiriminaja-official' ),
            'desc_tip' => true,
            'options' => $options,
            'value' => get_post_meta($post_id, ShipmentLocationService::META_KEY, true),
        ));
    }

    public function save_variation_shipment_location_field($variation_id, $loop) {
        // Nonce is supplied on the parent product edit form.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $locations = isset($_POST[ShipmentLocationService::META_KEY]) ? wp_unslash($_POST[ShipmentLocationService::META_KEY]) : array();
        $this->save_shipment_location_meta($variation_id, is_array($locations) && isset($locations[$variation_id]) ? $locations[$variation_id] : '');
    }

    private function save_shipment_location_meta($post_id, $value) {
        $location_id = absint($value);
        $location = $location_id ? (new ShipmentLocationService())->repository()->getById($location_id) : null;
        if ($location && (int) $location->is_active === 1) {
            update_post_meta($post_id, ShipmentLocationService::META_KEY, $location_id);
            return;
        }
        delete_post_meta($post_id, ShipmentLocationService::META_KEY);
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

        // Nonce and capability were verified above.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $shipment_location = isset($_POST[ShipmentLocationService::META_KEY]) ? wp_unslash($_POST[ShipmentLocationService::META_KEY]) : '';
        $this->save_shipment_location_meta($post_id, $shipment_location);

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

        $is_virtual = ( 0 === $total );
        $is_ready   = ( $configured >= $total );
        $label      = $is_virtual
            ? __( 'Virtual Product', 'kiriminaja-official' )
            : (
                $is_ready
                    ? __( 'All Product Configured', 'kiriminaja-official' )
                    : sprintf(
                        /* translators: %1$d: configured products, %2$d: total products */
                        __( '%1$d / %2$d Configured', 'kiriminaja-official' ),
                        $configured,
                        $total
                    )
            );
        $class_name = $is_virtual ? 'is-virtual' : ( $is_ready ? 'is-ready' : 'is-warning' );

        printf(
            '<span class="kiriof-volumetric-label %1$s">%2$s</span>',
            esc_attr( $class_name ),
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
            .kiriof-volumetric-label.is-virtual {
                background: #f0f6fc;
                color: #1d4f73;
                border: 1px solid #b8d6ec;
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

        return array_values(
            array_filter(
                array_unique( array_filter( $product_ids ) ),
                array( $this, 'kiriof_product_needs_volumetric_configuration' )
            )
        );
    }

    private function kiriof_product_has_volumetric_configuration( $post_id ) {
        if ( ! $this->kiriof_product_needs_volumetric_configuration( $post_id ) ) {
            return true;
        }

        $required_meta = array( '_weight', '_length', '_width', '_height' );
        $post_type = get_post_type( $post_id );
        $parent_id = ( 'product_variation' === $post_type ) ? (int) wp_get_post_parent_id( $post_id ) : 0;

        foreach ( $required_meta as $meta_key ) {
            $value = (float) get_post_meta( $post_id, $meta_key, true );
            if ( $value <= 0 && $parent_id > 0 ) {
                $value = (float) get_post_meta( $parent_id, $meta_key, true );
            }
            if ( $value <= 0 ) {
                return false;
            }
        }

        return true;
    }

    private function kiriof_product_needs_volumetric_configuration( $post_id ) {
        if ( function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $post_id );
            if ( $product && method_exists( $product, 'needs_shipping' ) && ! $product->needs_shipping() ) {
                return false;
            }
        }

        return true;
    }

}
