<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kiriminaja-official' ) );
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for displaying detail
$kiriof_transaction_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

if ( 0 === $kiriof_transaction_id ) {
	wp_safe_redirect( admin_url( 'admin.php?page=kiriminaja-transaction' ) );
	exit;
}

$kiriof_transaction_row = ( new \KiriminAjaOfficial\Repositories\TransactionRepository() )->getTransactionById( $kiriof_transaction_id );

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
            'transaction_id' => $kiriof_transaction_id,
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
