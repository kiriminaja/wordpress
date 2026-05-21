<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kiriminaja-official' ) );
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for displaying detail
$kiriof_pickup_number = isset( $_GET['pickup_number'] ) ? sanitize_text_field( wp_unslash( $_GET['pickup_number'] ) ) : '';

if ( empty( $kiriof_pickup_number ) ) {
    wp_safe_redirect( admin_url( 'admin.php?page=kiriminaja-request-pickup' ) );
    exit;
}

$kiriof_detail_service = ( new \KiriminAjaOfficial\Services\ShippingProcessServices\GetShippingProcessDetailService() )
    ->paymentId( $kiriof_pickup_number )
    ->call();

if ( $kiriof_detail_service->status !== 200 ) {
    wp_safe_redirect( admin_url( 'admin.php?page=kiriminaja-request-pickup' ) );
    exit;
}

$kiriof_payment_data      = $kiriof_detail_service->data['payment_data'];
$kiriof_transactions_data = $kiriof_detail_service->data['transactions_data'];
$kiriof_back_url          = admin_url( 'admin.php?page=kiriminaja-request-pickup' );

include __DIR__ . '/view/index.php';
