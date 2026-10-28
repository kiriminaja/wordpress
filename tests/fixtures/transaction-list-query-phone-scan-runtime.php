<?php
/** Exercise the list-query fake with WooCommerce loaded, independent of test load order. */
require dirname( __DIR__ ) . '/bootstrap.php';
function wc_get_order( $id ) { throw new RuntimeException( 'An empty candidate scan must not hydrate orders.' ); }
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
require dirname( __DIR__ ) . '/TransactionListQueryRuntimeTest.php';

$wpdb = new TransactionListQueryWpdbFake();
$GLOBALS['wpdb'] = $wpdb;
$query = new \KiriminAjaOfficial\Queries\WordPressTransactionListQuery( $wpdb );
$counts = $query->getDeliveryCounts();
$total = ( new \KiriminAjaOfficial\Repositories\TransactionRepository() )->getCountTransactionProcessNew();
$page = $query->getPage( array( 'key' => '', 'month' => '', 'status' => 'all', 'cod' => '', 'courier' => '', 'print_status' => '' ), 9, 25 );
$status = $query->getStatusCounts();
echo json_encode( array(
	'counts' => $counts, 'total' => $total, 'page' => $page['page'],
	'phone_scans' => count( $wpdb->phone_queries ),
	'badge_queries_match' => $wpdb->badge_queries[0] === $wpdb->badge_queries[1],
	'clamped_sql' => $wpdb->list_queries[3],
	'issue_count' => $status['order-issue'],
), JSON_THROW_ON_ERROR );
