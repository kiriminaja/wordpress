<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect parameter.
$kiriof_pickup_number = isset( $_GET['pickup_number'] ) ? sanitize_text_field( wp_unslash( $_GET['pickup_number'] ) ) : '';
$kiriof_transaction_url = admin_url( 'admin.php?page=kiriminaja-transaction' );
if ( '' !== $kiriof_pickup_number ) {
	$kiriof_transaction_url = add_query_arg( 'key', 'pid:' . $kiriof_pickup_number, $kiriof_transaction_url );
}
wp_safe_redirect( $kiriof_transaction_url );
exit;
