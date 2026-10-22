<?php
namespace KiriminAjaOfficial\Base {
    class BaseInit { public function logThis( ...$args ) {} }
}
namespace {
    define( 'ABSPATH', __DIR__ );
    require dirname( __DIR__, 2 ) . '/inc/Contracts/TransactionPrintRepositoryInterface.php';
    require dirname( __DIR__, 2 ) . '/inc/Repositories/TransactionRepository.php';
    class UniqueLookupDatabase {
        public $prefix = 'wp_'; public $last_error = ''; public $rows = array(); public $queries = array(); public $error = '';
        public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
        public function get_results( $query ) { $this->queries[] = $query; $this->last_error = $this->error; return $this->rows; }
    }
    $wpdb = new UniqueLookupDatabase();
    $repo = new \KiriminAjaOfficial\Repositories\TransactionRepository();
    $assert = function ( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } };
    foreach ( array( 0, -1, '', 'invalid', '42invalid', 1.5, true, null, array(), '92233720368547758070' ) as $id ) {
        $assert( false === $repo->getUniqueTransactionByWCOrderId( $id ), 'Invalid positive order ID must fail closed' );
    }
    $assert( array() === $wpdb->queries, 'Invalid IDs must not query' );
    $assert( null === $repo->getUniqueTransactionByWCOrderId( 42 ), 'Zero rows means absent' );
    $row = (object) array( 'order_id' => 'KA-1', 'wp_wc_order_stat_order_id' => 42 );
    $wpdb->rows = array( $row );
    $assert( $row === $repo->getUniqueTransactionByWCOrderId( '42' ), 'Single row is returned unchanged' );
    $wpdb->rows = array( $row, clone $row );
    $assert( false === $repo->getUniqueTransactionByWCOrderId( 42 ), 'Even identical duplicate rows are ambiguous' );
    $wpdb->rows = array( $row ); $wpdb->error = 'Database unavailable';
    $assert( false === $repo->getUniqueTransactionByWCOrderId( 42 ), 'Database error fails closed despite a row' );
    foreach ( $wpdb->queries as $query ) {
        $assert( 'SELECT * FROM wp_kiriminaja_transactions WHERE wp_wc_order_stat_order_id = %d LIMIT 2' === $query[0] && array( 42 ) === $query[1], 'Prepared bounded lookup uses the WooCommerce order ID' );
    }
    echo json_encode( array( 'ok' => true, 'queries' => count( $wpdb->queries ) ) );
}
