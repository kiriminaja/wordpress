<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! wp_script_is( 'kiriof-form-shipping-address', 'registered' ) ) {
    wp_register_script(
        'kiriof-form-shipping-address',
        KIRIOF_URL . 'assets/wp/js/form-shipping-address.js',
        array( 'kiriof-script', 'jquery', 'select2', 'wp-util' ),
        KIRIOF_VERSION,
        true
    );
}
wp_localize_script(
    'kiriof-form-shipping-address',
    'kiriofFormShippingAddress',
    array( 'ajaxAction' => 'kiriminaja_subdistrict_search' )
);
wp_enqueue_script( 'kiriof-form-shipping-address' );
?>

<p class="form-row form-row-wide">
    <label for="custom_select_field"><?php esc_html_e('Kelurahan', 'kiriminaja-official'); ?> <span class="required">*</span></label>
    <select name="custom_select_field_shipping" id="custom_select_field_shipping" class="select2 custom_select_field_shipping" style="width: 100%;" required></select>
</p>
