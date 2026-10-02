<?php
namespace KiriminAjaOfficial\Base {
    class BaseService {
        protected static function success( $data, $message = 'success' ) { return (object) array( 'status' => 200, 'message' => $message ); }
        protected static function error( $data, $message = 'error', $status = 400, $code = null ) { return (object) array( 'status' => $status, 'message' => $message, 'code' => $code ); }
    }
    class BaseInit { public function logThis( ...$args ) {} }
}
namespace KiriminAjaOfficial\Repositories {
    class SettingRepository { public function getSettingByKey( $key ) { throw new \RuntimeException( 'No settings needed after validation' ); } }
    class WpPostMetaRepository { public function getRequiredRowsByPostId( $id ) { throw new \RuntimeException( 'HPOS must use order getters' ); } }
    class TransactionRepository {
        public $rows = array(); public $attempts = 0; public $fail = true; public $race;
        public function getTransactionByWCOrderId( $id ) { return $this->rows[$id] ?? null; }
        public function createTransaction( $payload ) {
            ++$this->attempts;
            if ( $this->race ) { $race = $this->race; $this->race = null; $race(); }
            if ( $this->fail ) { return false; }
            $this->rows[$payload['wp_wc_order_stat_order_id']] = (object) $payload; return true;
        }
    }
}
namespace KiriminAjaOfficial\Services\CheckoutServices {
    class CodDeficitService { public function detect( $data ) { return array( 'isDeficit' => false, 'codMinimum' => 0 ); } }
}
namespace KiriminAjaOfficial\Services\KiriminAja {
    class GenerateOrderId { public $calls = 0; public function call() { return 'INVOICE-' . ++$this->calls; } }
}
namespace KiriminAjaOfficial\Services {
    class CheckoutServiceFactory { public function calculation( $data ) { throw new \RuntimeException( 'No repricing' ); } }
    class ShipmentLocationService { public function getDefaultLocation() { return array(); } public function locationToOrigin( $location ) { return array( 'location_id' => 4 ); } }
}
namespace {
    define( 'ABSPATH', __DIR__ );
    function __( $text, $domain ) { return $text; }
    function absint( $value ) { return abs( (int) $value ); }
    function wp_json_encode( $value ) { return json_encode( $value ); }
    function maybe_serialize( $value ) { return serialize( $value ); }
    function wp_cache_delete( $key, $group ) {}
    function add_option( $key, $value, $deprecated, $autoload ) { if ( isset( $GLOBALS['options'][$key] ) ) { return false; } if ( false !== $autoload ) { throw new \RuntimeException( 'Lease must not autoload' ); } $GLOBALS['options'][$key] = $value; return true; }
    function get_option( $key ) { return $GLOBALS['cached'][$key] ?? $GLOBALS['options'][$key] ?? false; }
    function wc_get_order( $id ) { return $GLOBALS['order']; }
    class DB {
        public $options = 'wp_options';
        public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
        public function get_var( $query ) { return isset( $GLOBALS['options'][$query[1][0]] ) ? serialize( $GLOBALS['options'][$query[1][0]] ) : null; }
        public function query( $query ) { list( $key, $value ) = $query[1]; if ( isset( $GLOBALS['options'][$key] ) && serialize( $GLOBALS['options'][$key] ) === $value ) { unset( $GLOBALS['options'][$key] ); return 1; } return 0; }
    }
    class Order {
        public $meta; public $notes = array(); public $total = 110;
        public function __construct() { $this->meta = array( '_kiriof_express_validated' => array( 'expedition' => 'jne_REG', 'destination_id' => 123, 'payment_method' => 'bacs', 'is_insurance' => false, 'validated_calculation' => array( 'calculation_result' => array( 'cart_total_amt' => 100, 'cart_total_after_discount' => 100, 'ongkir_fee_raw' => 10, 'calc_total_amt' => 110, 'insurance_amt' => 0, 'cod_amt' => 0, 'selected_expedition' => array( 'service' => 'jne', 'service_type' => 'REG', 'force_insurance' => false ) ), 'carts_attribute' => array( 'weight' => 1000, 'length' => 1, 'width' => 1, 'height' => 1 ) ) ) ); }
        public function get_meta( $key, $single = true ) { return $this->meta[$key] ?? ''; }
        public function update_meta_data( $key, $value ) { $this->meta[$key] = $value; }
        public function delete_meta_data( $key ) { unset( $this->meta[$key] ); }
        public function save_meta_data() {}
        public function read_meta_data( $force ) {}
        public function add_order_note( $text ) { $this->notes[] = $text; }
        public function get_items( $type ) { return array(); }
        public function get_total() { return $this->total; }
        public function get_total_fees() { return 0; }
        public function get_total_tax() { return 0; }
        public function get_payment_method() { return 'bacs'; }
        public function get_shipping_postcode() { return '12345'; }
        public function calculate_totals() { throw new \RuntimeException( 'No amount mutation' ); }
        public function save() { throw new \RuntimeException( 'No full order save' ); }
    }
    $GLOBALS['options'] = array(); $GLOBALS['cached'] = array(); $GLOBALS['wpdb'] = new DB(); $GLOBALS['order'] = new Order();
    require dirname( __DIR__, 2 ) . '/inc/Services/CheckoutServices/CreateTransactionService.php';
    $repo = new \KiriminAjaOfficial\Repositories\TransactionRepository(); $generator = new \KiriminAjaOfficial\Services\KiriminAja\GenerateOrderId();
    $make = function () use ( $repo, $generator ) { return new \KiriminAjaOfficial\Services\CheckoutServices\CreateTransactionService( array( 'order_id' => 42, 'blocks_validated' => true, 'wc_cart_contents' => array( array( 'quantity' => 1 ) ) ), $repo, new \KiriminAjaOfficial\Repositories\SettingRepository(), new \KiriminAjaOfficial\Repositories\WpPostMetaRepository(), new \KiriminAjaOfficial\Services\CheckoutServices\CodDeficitService(), new \KiriminAjaOfficial\Services\ShipmentLocationService(), $generator, new \KiriminAjaOfficial\Services\CheckoutServiceFactory() ); };
    $assert = function ( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } };
    $first = $make()->call(); $assert( 503 === $first->status && 110 === $GLOBALS['order']->total, 'Failed insert is visible and does not mutate amount' );
    $assert( 'transaction_save_failed' === $GLOBALS['order']->get_meta( '_kiriof_express_transaction_error' ) && 1 === count( $GLOBALS['order']->notes ), 'Fixed failure metadata and note' );
    $repo->fail = false; $race_status = null;
    $repo->race = function () use ( $make, &$race_status ) { $race_status = $make()->call()->status; };
    $assert( 200 === $make()->call()->status && 503 === $race_status, 'Concurrent worker cannot insert' );
    $assert( 200 === $make()->call()->status && 2 === $repo->attempts && 1 === $generator->calls && 1 === count( $repo->rows ), 'Retry and duplicate replay reuse one invoice and row' );
    $repo->rows[42]->shipping_cost = 99;
    $assert( 503 === $make()->call()->status && 2 === $repo->attempts, 'Inconsistent existing row fails closed' );
    $lock = '_kiriof_express_order_lock_42'; $old = array( 'owner' => 'old', 'expires' => time() - 1 ); $new = array( 'owner' => 'new', 'expires' => time() + 180 );
    $GLOBALS['options'][$lock] = $new; $GLOBALS['cached'][$lock] = $old;
    $assert( 503 === $make()->call()->status && $new === $GLOBALS['options'][$lock], 'Stale expired cache cannot delete a live lease' );
    $GLOBALS['cached'] = array(); $GLOBALS['options'][$lock] = $old; $repo->rows[42]->shipping_cost = 10;
    $assert( 200 === $make()->call()->status && ! isset( $GLOBALS['options'][$lock] ), 'Expired lease recovers and releases' );
    $GLOBALS['order']->total = 111;
    $assert( 503 === $make()->call()->status && 2 === $repo->attempts, 'Changed payable total rejects without insert' );
    echo json_encode( array( 'ok' => true, 'attempts' => $repo->attempts, 'invoices' => $generator->calls, 'rows' => count( $repo->rows ), 'race_status' => $race_status ) );
}
