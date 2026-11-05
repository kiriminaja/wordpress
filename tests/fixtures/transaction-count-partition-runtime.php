<?php
/** Isolate WordPress cache availability from other ParaTest worker fixtures. */

if ( 'cli' !== PHP_SAPI ) {
    exit;
}

require dirname( __DIR__ ) . '/bootstrap.php';

function get_transient( $key ) {
    return 'warm' === $GLOBALS['argv'][1] ? ( $GLOBALS['partition_transients'][ $key ] ?? false ) : false;
}

function set_transient( $key, $value, $ttl ) {
    $GLOBALS['partition_transients'][ $key ] = $value;
    return true;
}

function wc_get_order( $id ) {
    throw new RuntimeException( 'Empty phone candidates must not hydrate an order.' );
}

require dirname( __DIR__ ) . '/TransactionListQueryRuntimeTest.php';

$wpdb = new TransactionListQueryWpdbFake();
$query = new \KiriminAjaOfficial\Queries\WordPressTransactionListQuery( $wpdb );
$query->getPage( array( 'key' => '', 'month' => '', 'status' => 'all', 'cod' => '', 'courier' => '', 'print_status' => '', 'delivery_type' => 'instant' ), 1, 25 );
$query->getDeliveryCounts();
$before = count( $wpdb->phone_queries );
$query->getStatusCounts();

echo json_encode( array( 'phone_scans' => count( $wpdb->phone_queries ) - $before, 'status_queries' => $wpdb->status_queries ), JSON_THROW_ON_ERROR );
