<?php
/**
 * Hidden billing address fields.
 *
 * @var bool   $kiriof_checkout_token
 * @var string $destination_name
 * @var string $shipping_destination_name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="kiriof_destination_area_group">    
    <div style="display: none">
        <input type="hidden" name="kiriof_checkout_token" value="<?php echo esc_attr($kiriof_checkout_token); ?>">
        <input type="hidden" name="kiriof_destination_area_name" value="<?php echo esc_attr($destination_name); ?>">
        <input type="hidden" name="kiriof_shipping_destination_area_name" value="<?php echo esc_attr($shipping_destination_name); ?>">
        <input type="hidden" name="kiriof_force_insurance" value="0">
    </div>

</div>
