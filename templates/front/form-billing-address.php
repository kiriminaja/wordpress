<?php
/**
 * Billing address fields and inline script orchestrator.
 *
 * Variables provided by CheckoutController::add_custom_select_options_field_and_script().
 *
 * @var string $field_key
 * @var bool   $kiriof_checkout_token
 * @var string $destination_name
 * @var string $shipping_destination_name
 * @var bool   $kiriof_global_insurance
 * @var array  $kiriof_saved_destination_map
 * @var string $kiriof_saved_checkout_postcode
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require __DIR__ . '/partials/form-billing-address-fields.php';

if ( is_checkout() || is_cart() ) {
    $kiriof_billing_address_config = require __DIR__ . '/partials/form-billing-address-config.php';
    $kiriof_billing_address_json   = wp_json_encode( $kiriof_billing_address_config );

    if ( false !== $kiriof_billing_address_json ) {
        wp_enqueue_script( 'kiriof-form-billing-address' );
        wp_add_inline_script(
            'kiriof-form-billing-address',
            'window.kiriofBillingAddressConfig = ' . $kiriof_billing_address_json . ';',
            'before'
        );
    }
}
