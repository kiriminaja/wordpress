<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="kj-wc-general-shipping">
    <?php 
    $kiriof_weight_unit = get_option('woocommerce_weight_unit');
    $kiriof_dimension_unit = get_option('woocommerce_dimension_unit');

        woocommerce_wp_text_input(
            array(
                'id' => '_kiriof_weight',
                'placeholder' => 'Weight',
                // Translators: %s weight unit
                'label' => sprintf( esc_html__( 'Weight (%s)', 'kiriminaja-official' ), esc_attr( $kiriof_weight_unit ) ),
                'desc_tip' => 'true',
                'description'=>__('Weight Form', 'kiriminaja-official'),
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
                'id' => '_kiriof_length',
                'desc_tip' => true,
                'description'=>__('Length Form', 'kiriminaja-official'),
                'placeholder' => 'Length',
                // Translators: %s length unit
                'label' => sprintf( __( 'Length (%s)', 'kiriminaja-official' ), esc_attr( $kiriof_dimension_unit ) ),
                'class' => 'input-text wc_input_decimal',
                'value' => get_post_meta($post->ID,'_length',true) ?? ''
            )
        );

        woocommerce_wp_text_input(
            array(
                'id' => '_kiriof_width',
                'desc_tip' => true,
                'description'=>__('Width Form', 'kiriminaja-official'),
                'placeholder' => 'Width',
                // Translators: %s width unit
                'label' => sprintf( __( 'Width (%s)', 'kiriminaja-official' ), esc_attr( $dimension_unit ) ),
                'class' => 'input-text wc_input_decimal',
                'value' => get_post_meta($post->ID,'_width',true) ?? ''
            )
        );

        woocommerce_wp_text_input(
            array(
                'id' => '_kiriof_height',
                'desc_tip' => true,
                'description'=>__('Height Form', 'kiriminaja-official'),
                'placeholder' => 'Height',
                // Translators: %s height unit
                'label' => sprintf( __( 'Height (%s)', 'kiriminaja-official' ), esc_attr( $kiriof_dimension_unit ) ),
                'class' => 'input-text wc_input_decimal',
                'value' => get_post_meta($post->ID,'_height',true) ?? ''
            )
        );
    ?>
</div>