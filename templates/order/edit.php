<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! wp_script_is( 'kiriof-order-edit', 'registered' ) ) {
    wp_register_script(
        'kiriof-order-edit',
        KIRIOF_URL . 'assets/admin/js/kj-order-edit.js',
        array( 'jquery' ),
        KIRIOF_VERSION,
        true
    );
}
wp_localize_script(
    'kiriof-order-edit',
    'kiriofOrderEdit',
    array(
        'orderId'     => (string) $orderId,
        'trackingUrl' => (string) $trackingUrl,
        'orderData'   => (string) $kiriofOrderData,
    )
);
wp_enqueue_script( 'kiriof-order-edit' );
