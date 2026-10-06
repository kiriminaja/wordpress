<?php
/**
 * Billing address script configuration.
 *
 * Variables provided by CheckoutController::add_custom_select_options_field_and_script().
 *
 * @var string $field_key
 * @var bool   $kiriof_global_insurance
 * @var array  $kiriof_saved_destination_map
 * @var string $kiriof_saved_checkout_postcode
 * @var string $destination_id
 * @var string $destination_name
 * @var string $shipping_destination_id
 * @var string $shipping_destination_name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Use the checkout value (including posted/session values), never a forced ID
// default. Some Classic themes omit country inputs entirely.
$kiriof_wc = function_exists( 'WC' ) ? WC() : null;
$kiriof_checkout = $kiriof_wc && method_exists( $kiriof_wc, 'checkout' ) ? $kiriof_wc->checkout() : null;
$kiriof_customer = $kiriof_wc->customer ?? null;
$kiriof_countries = array();
foreach ( array( 'billing', 'shipping' ) as $kiriof_scope ) {
    $kiriof_getter = 'get_' . $kiriof_scope . '_country';
    $kiriof_countries[ $kiriof_scope ] = $kiriof_checkout && method_exists( $kiriof_checkout, 'get_value' )
        ? (string) $kiriof_checkout->get_value( $kiriof_scope . '_country' )
        : ( $kiriof_customer && method_exists( $kiriof_customer, $kiriof_getter ) ? (string) $kiriof_customer->$kiriof_getter() : '' );
}

return array(
    'billingCountry'          => $kiriof_countries['billing'],
    'shippingCountry'         => $kiriof_countries['shipping'],
    'savedDistrictByPostcode' => is_array( $kiriof_saved_destination_map ) ? $kiriof_saved_destination_map : array(),
    'savedCheckoutPostcode'   => (string) $kiriof_saved_checkout_postcode,
    'billingDistrict'         => array(
        'id'   => (string) $destination_id,
        'name' => (string) $destination_name,
    ),
    'shippingDistrict'        => array(
        'id'   => (string) $shipping_destination_id,
        'name' => (string) $shipping_destination_name,
    ),
    'storeApiNonce'           => wp_create_nonce( 'wc_store_api' ),
    'storeApiUpdateCustomerUrl' => rest_url( 'wc/store/v1/cart/update-customer' ),
    'globalInsurance'         => (bool) $kiriof_global_insurance,
    'globalInsuranceInt'      => $kiriof_global_insurance ? 1 : 0,
    'isCart'                  => is_cart(),
    'isCheckout'              => is_checkout(),
    'ajaxUrl'                 => admin_url( 'admin-ajax.php' ),
    'nonce'                   => wp_create_nonce( KIRIOF_NONCE ),
    'destinationNonce'        => wp_create_nonce( 'kiriof-destination' ),
    'updateCheckoutNonce'     => wp_create_nonce( 'kiriof-update-checkout' ),
    'fieldKey'                => (string) $field_key,
    'i18n'                    => array(
        'district'        => __( 'District', 'kiriminaja-official' ),
        'selectDistrict'  => __( 'Select District', 'kiriminaja-official' ),
        'districtWarning' => __( 'Please select your District to view shipping options.', 'kiriminaja-official' ),
        'selectOption'    => __( 'Select Option', 'kiriminaja-official' ),
    ),
);
