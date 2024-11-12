<div class="kj-wc-general-shipping">
    <?php 
        woocommerce_wp_text_input(
            array(
                'id' => '_kj_weight',
                'placeholder' => 'Weight',
                'label' => __('Weight Product', 'kiriminaja'),
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
                'label' => __('Length Product', 'kiriminaja'),
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
                'label' => __('Width Product', 'kiriminaja'),
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
                'label' => __('Height Product', 'kiriminaja'),
                'class' => 'input-text wc_input_decimal',
                'value' => get_post_meta($post->ID,'_height',true) ?? ''
            )
        );
    ?>
</div>