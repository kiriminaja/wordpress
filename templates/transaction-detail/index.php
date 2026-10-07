<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kiriminaja-official' ) );
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only order detail lookup; capability is checked above.
$kiriof_order_id_raw = isset( $_GET['id'] ) && is_string( $_GET['id'] ) ? wp_unslash( $_GET['id'] ) : '';
// Reject coerced/overflowing identifiers rather than opening a different order.
$kiriof_wc_order_id = preg_match( '/\A[1-9][0-9]*\z/', $kiriof_order_id_raw ) && (string) (int) $kiriof_order_id_raw === $kiriof_order_id_raw ? (int) $kiriof_order_id_raw : 0;

if ( 0 === $kiriof_wc_order_id ) {
	wp_safe_redirect( admin_url( 'admin.php?page=kiriminaja-transaction' ) );
	exit;
}

// id is exclusively the WooCommerce order ID. Never fall back to the internal
// transaction primary key: overlapping numeric IDs could select another order.
$kiriof_transaction_row = ( new \KiriminAjaOfficial\Repositories\TransactionRepository() )->getUniqueTransactionByWCOrderId( $kiriof_wc_order_id );

if ( ! $kiriof_transaction_row ) {
	wp_safe_redirect( admin_url( 'admin.php?page=kiriminaja-transaction' ) );
	exit;
}

try {
    $kiriof_transaction_detail_bootstrap = ( new \KiriminAjaOfficial\Services\TransactionDetailPageData() )->prepare( $kiriof_transaction_row );
} catch ( Throwable $kiriof_transaction_detail_error ) {
    kiriof_log(
        'error',
        'Transaction detail bootstrap failed.',
        array(
            'wc_order_id'    => $kiriof_wc_order_id,
            'transaction_id' => (int) ( $kiriof_transaction_row->id ?? 0 ),
            'message'        => $kiriof_transaction_detail_error->getMessage(),
            'file'           => $kiriof_transaction_detail_error->getFile(),
            'line'           => $kiriof_transaction_detail_error->getLine(),
        )
    );
    $kiriof_transaction_detail_bootstrap = ( new \KiriminAjaOfficial\Services\TransactionDetailPageData() )->prepareFallback(
        $kiriof_transaction_row,
        __( 'Some transaction details could not be loaded. Review the KiriminAja log for this transaction.', 'kiriminaja-official' )
    );
}

include __DIR__ . '/view/index.php';
