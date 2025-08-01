<div class="kj-wc-general-shipping">
    <?php 
    $weight_unit = get_option('woocommerce_weight_unit');
    $dimension_unit = get_option('woocommerce_dimension_unit');

        woocommerce_wp_text_input(
            array(
                'id' => '_kj_weight',
                'placeholder' => 'Weight',
                // Translators: %s weight unit
                'label' => sprintf( esc_html__( 'Weight (%s)', 'kiriminaja' ), esc_attr( $weight_unit ) ),
                'desc_tip' => 'true',
                'description'=>__('Weight Form', 'kiriminaja'),
                'type' => 'number',
                'custom_attributes' => array(
                    'min' => 0
                ),
                'value' => get_post_meta($post->ID,'_weight',true) ?? 0,
                'class' => 'short wc_input_decimal'
            ), 
        );

        woocommerce_wp_text_input(
            array(
                'id' => '_kj_length',
                'desc_tip' => true,
                'description'=>__('Length Form', 'kiriminaja'),
                'placeholder' => 'Length',
                // Translators: %s length unit
                'label' => sprintf( __( 'Length (%s)', 'kiriminaja' ), esc_attr( $dimension_unit ) ),
                'class' => 'input-text wc_input_decimal',
                'value' => get_post_meta($post->ID,'_length',true) ?? ''
            )
        );

        woocommerce_wp_text_input(
            array(
                'id' => '_kj_width',
                'desc_tip' => true,
                'description'=>__('Width Form', 'kiriminaja'),
                'placeholder' => 'Width',
                // Translators: %s width unit
                'label' => sprintf( __( 'Width (%s)', 'kiriminaja' ), esc_attr( $dimension_unit ) ),
                'class' => 'input-text wc_input_decimal',
                'value' => get_post_meta($post->ID,'_width',true) ?? ''
            )
        );

        woocommerce_wp_text_input(
            array(
                'id' => '_kj_height',
                'desc_tip' => true,
                'description'=>__('Height Form', 'kiriminaja'),
                'placeholder' => 'Height',
                // Translators: %s height unit
                'label' => sprintf( __( 'Height (%s)', 'kiriminaja' ), esc_attr( $dimension_unit ) ),
                'class' => 'input-text wc_input_decimal',
                'value' => get_post_meta($post->ID,'_height',true) ?? ''
            )
        );
    ?>
</div>