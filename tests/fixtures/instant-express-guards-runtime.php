<?php
namespace {
    error_reporting( E_ALL & ~E_DEPRECATED );
    define( 'ABSPATH', __DIR__ );
    define( 'KIRIOF_NONCE', 'test' );
    function __( $text, $domain = '' ) { return $text; }
    function current_user_can( $capability ) { return true; }
    function wp_verify_nonce( $nonce, $action ) { return true; }
    function sanitize_text_field( $value ) { return $value; }
    function wp_unslash( $value ) { return $value; }
    function kiriof_sanitize_recursive( $value ) { return $value; }
    function absint( $value ) { return abs( (int) $value ); }
    function kiriof_helper() { return new class { function dateConvertGMT( $value ) { return $value; } }; }
    function wp_send_json_error( $data, $status = null ) { throw new \RuntimeException( $data['message'] ); }
    function kiriof_log( ...$args ) {}
}
namespace KiriminAjaOfficial\Repositories {
    class TransactionRepository {
        public array $rows = array();
        public int $writes = 0;
        function getTransactionByOrderIds( $ids ) { return $this->rows; }
        function getTransctionByOrderIds( $ids ) { return $this->rows; }
        function getTransactionByOrderId( $id ) { return $this->rows[0]; }
        function getTransactionByWCOrderId( $id ) { return $this->rows[0]; }
        function updateTransactionByCallback( $data ) { ++$this->writes; }
        function updateTransactionCodValues( ...$args ) { ++$this->writes; }
    }
    class KiriminajaApiRepository {
        public int $calls = 0;
        function cancelShipment( ...$args ) { ++$this->calls; return array( 'status' => true ); }
        function getPrintAwb( ...$args ) { ++$this->calls; return array(); }
    }
}
namespace KiriminAjaOfficial\Base { class BaseInit { function logThis( ...$args ) {} } }
namespace {
    $root = dirname( __DIR__, 2 );
    foreach ( array( 'Utils/ServiceResponse', 'Base/BaseService', 'Services/TransactionDeliveryType', 'Contracts/TransactionPrintRepositoryInterface', 'Services/TransactionProcessServices/SendRequestPickupTransactionService', 'Services/TransactionProcessServices/CancelTransactionService', 'Controllers/TransactionProcessController', 'Controllers/CodAdjustmentController', 'Controllers/ShippingProcessController' ) as $file ) { require $root . '/inc/' . $file . '.php'; }
    $input = json_decode( $argv[1], true );
    $repo = new \KiriminAjaOfficial\Repositories\TransactionRepository();
    $repo->rows = array_map( static fn( $row ) => (object) array_merge( array( 'order_id' => 'KA-1', 'status' => 'new', 'awb' => 'AWB', 'is_deficit' => 1 ), $row ), $input['rows'] );
    $api = new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository();
    $operation = $input['operation'];
    $classes = array( 'pickup' => 'Services\\TransactionProcessServices\\SendRequestPickupTransactionService', 'cancel' => 'Services\\TransactionProcessServices\\CancelTransactionService', 'auto' => 'Controllers\\TransactionProcessController', 'origin' => 'Controllers\\TransactionProcessController', 'adjust' => 'Controllers\\CodAdjustmentController', 'deficit' => 'Controllers\\CodAdjustmentController', 'print' => 'Controllers\\ShippingProcessController' );
    $object = ( new \ReflectionClass( 'KiriminAjaOfficial\\' . $classes[$operation] ) )->newInstanceWithoutConstructor();
    $repo_property = in_array( $operation, array( 'pickup', 'auto', 'origin' ), true ) ? 'transactionRepository' : 'transaction_repository';
    ( new \ReflectionProperty( $object, $repo_property ) )->setValue( $object, $repo );
    if ( in_array( $operation, array( 'cancel', 'print' ), true ) ) { ( new \ReflectionProperty( $object, 'api_repository' ) )->setValue( $object, $api ); }
    $message = '';
    $status = null;
    try {
        if ( 'pickup' === $operation ) { $result = $object->orderIds( array( 'KA-1', 'KA-2' ) )->call(); $message = $result->message; $status = $result->status; }
        elseif ( 'cancel' === $operation ) { $result = $object->orderId( 'KA-1' )->reason( 'Cancel order' )->call(); $message = $result->message; $status = $result->status; }
        elseif ( 'auto' === $operation ) { $object->handleWcOrderCancelled( 1 ); }
        elseif ( 'origin' === $operation ) { $_POST = array( 'nonce' => 'test', 'order_id' => 'KA-1', 'location_id' => 1 ); $object->changeOrigin(); }
        elseif ( 'print' === $operation ) { $_POST = array( 'nonce' => 'test', 'oids' => array( 'KA-1' ) ); $object->previewResiPrint(); }
        else { $_POST = array( 'data' => array( 'nonce' => 'test', 'order_package_id' => 'KA-1', 'new_total_cod' => 50000 ) ); if ( 'adjust' === $operation ) { $object->handleAdjust(); } else { $object->handleCancelDeficit(); } }
    } catch ( \Throwable $error ) { $message = $error->getMessage(); }
    echo json_encode( array( 'message' => $message, 'status' => $status, 'calls' => $api->calls, 'writes' => $repo->writes ) );
}
