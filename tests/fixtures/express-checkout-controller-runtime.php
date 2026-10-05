<?php
/** Real Express validator and controller; only external factory boundaries are fake. */
namespace KiriminAjaOfficial\Repositories {
    class TransactionRepository {
        public function getTransactionByWCOrderId( $id ) { return $GLOBALS['existing_transaction'] ?? null; }
    }
    class WpPostMetaRepository {}
}
namespace KiriminAjaOfficial\Services {
    class CustomerDistrictService {
        public function save( ...$args ) { ++$GLOBALS['customer_saves']; }
    }
}
namespace KiriminAjaOfficial\Base {
    class BaseInit { public function logThis( ...$args ) {} }
}
namespace Automattic\WooCommerce\StoreApi\Exceptions {
    class RouteException extends \Exception {
        public function __construct( public string $errorCode, string $message, public int $status ) { parent::__construct( $message ); }
    }
}
namespace {
    define( 'EXPRESS_CONTROLLER_INTEGRATION', true );
    define( 'REST_REQUEST', ! defined( 'CLASSIC_CONTROLLER_INTEGRATION' ) );
    $scenario = $argv[1] ?? 'success';
    require __DIR__ . '/express-checkout-validation-runtime.php';
    $GLOBALS['mode'] = 'valid';
    $GLOBALS['customer_saves'] = 0;
    $GLOBALS['transactions'] = array();
    $GLOBALS['wp'] = (object) array( 'query_vars' => array( 'rest_route' => '/wc/store/v1/checkout' ) );
    function wp_unslash( $value ) { return $value; }
    function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
    function add_action( $hook, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['hooks'][$hook][] = array( $callback, $accepted ); }
    function do_action( $hook, ...$args ) {
        foreach ( $GLOBALS['hooks'][$hook] ?? array() as [$callback, $accepted] ) { $callback( ...array_slice( $args, 0, $accepted ) ); }
    }
    class WP_REST_Request {
        public function __construct( private array $params = array() ) {}
        public function set_param( $key, $value ) { $this->params[$key] = $value; }
        public function get_params() { return $this->params; }
        public function get_param( $key ) { return $this->params[$key] ?? null; }
    }
    class WC_Order extends Order {
        public array $meta = array();
        public int $saves = 0;
        public function get_id() { return 42; }
        public function get_payment_method() { return 'bacs'; }
        public function get_meta( $key, $single = true ) { return $this->meta[$key] ?? ''; }
        public function update_meta_data( $key, $value ) { $this->meta[$key] = $value; }
        public function delete_meta_data( $key ) { unset( $this->meta[$key] ); }
        public function save() { ++$this->saves; }
        // Any repricing mutation is a test failure, not a silently accepted stub call.
        public function set_total( $value ) { throw new \LogicException( 'Order amount mutation' ); }
        public function calculate_totals( ...$args ) { throw new \LogicException( 'Order repricing' ); }
        public function get_items( $type ) {
            if ( 'shipping' === $type && in_array( $GLOBALS['scenario'], array( 'third-party', 'instant' ), true ) ) {
                return array( new class { public function get_method_id() { return 'instant' === $GLOBALS['scenario'] ? 'kiriminaja-instant' : 'flat_rate'; } } );
            }
            if ( 'line_item' === $type ) {
                return array( new class {
                    public function get_total() { return 100; }
                    public function get_product() { return new class { public function needs_shipping() { return true; } }; }
                } );
            }
            return parent::get_items( $type );
        }
    }
    $GLOBALS['scenario'] = $scenario;
    WC()->session = new class {
        public array $values = array( 'chosen_shipping_methods' => array( 'kiriminaja-official_jne_REG' ), 'billing_insurance' => 1, 'kiriof_expedition' => 'stale_REG', 'kiriof_destination_area' => '999', 'kiriof_destination_area_name' => 'Stale district' );
        public function get( $key, $default = null ) { return $this->values[$key] ?? $default; }
        public function set( $key, $value ) { $this->values[$key] = $value; }
    };
    WC()->cart = new class {
        public array $cart_contents = array();
        public function get_cart() { return array(); }
        public function get_discount_total() { return 0; }
        public function get_discount_tax() { return 0; }
        public function get_coupons() { return array(); }
    };
    WC()->customer = new \stdClass();
    require dirname( __DIR__, 2 ) . '/inc/Controllers/CheckoutController.php';
    $controller = new \KiriminAjaOfficial\Controllers\CheckoutController(
        new \KiriminAjaOfficial\Repositories\SettingRepository(),
        new \KiriminAjaOfficial\Repositories\TransactionRepository(),
        new \KiriminAjaOfficial\Repositories\WpPostMetaRepository(),
        new \KiriminAjaOfficial\Services\CheckoutServiceFactory()
    );
    add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $controller, 'afterStoreApiCheckoutUpdateOrderFromRequest' ), 10, 2 );
    add_action( 'woocommerce_store_api_checkout_order_processed', array( $controller, 'afterStoreApiCheckoutOrderProcessed' ) );
    $order = new WC_Order();
    $request = new WP_REST_Request( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => array(
        'district_id' => '123', 'district_label' => 'Buyer supplied label', 'postcode' => '12345', 'country' => 'ID', 'address_type' => 'shipping', 'version' => 1,
    ) ) ) ) );
    $result = array();
    if ( defined( 'CLASSIC_CONTROLLER_INTEGRATION' ) ) {
        $destination = $request->get_param( 'extensions' )['kiriminaja-official']['destination'];
        if ( 'clear' === $scenario ) { $destination['district_id'] = ''; $destination['district_label'] = ''; }
        if ( 'tamper' === $scenario ) { $destination['postcode'] = '99999'; }
        if ( in_array( $scenario, array( 'pin', 'pin-tamper', 'session-pin' ), true ) ) {
            $destination['version'] = 2;
            $destination['destination_latitude'] = '-6.2';
            $destination['destination_longitude'] = '106.8';
            foreach ( \KiriminAjaOfficial\Services\BuyerDestination::ADDRESS_FIELDS as $field ) {
                $getter = 'get_shipping_' . $field;
                $destination['shipping_address'][$field] = $order->$getter();
            }
            if ( 'pin-tamper' === $scenario ) { $destination['shipping_address']['address_1'] = 'Different street'; }
        }
        $_POST = array( 'kiriof_insurance' => 'unchecked' === $scenario ? '0' : '1' );
        if ( in_array( $scenario, array( 'billing', 'shipping', 'session-pin' ), true ) ) {
            $shipping = 'shipping' === $scenario;
            $_POST['ship_to_different_address'] = $shipping ? '1' : '0';
            $_POST[$shipping ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area'] = '123';
            $_POST[$shipping ? 'kiriof_shipping_destination_area_name' : 'kiriof_destination_area_name'] = 'Buyer label';
            // Opposite address is stale and must not win.
            $_POST[$shipping ? 'kiriof_destination_area' : 'kiriof_shipping_destination_area'] = '999';
            if ( 'session-pin' === $scenario ) { WC()->session->set( 'kiriof_buyer_destination', $destination ); }
        } else {
            $_POST['kiriof_buyer_destination_snapshot'] = json_encode( $destination );
        }
        if ( 'fee' === $scenario ) { $GLOBALS['mode'] = 'fee'; }
        add_action( 'woocommerce_checkout_create_order', array( $controller, 'afterCheckoutBeforeCreated' ), 10, 2 );
        add_action( 'woocommerce_checkout_order_processed', array( $controller, 'afterCheckoutAfterCreated' ), 10, 3 );
    }
    try {
        if ( defined( 'CLASSIC_CONTROLLER_INTEGRATION' ) ) {
            do_action( 'woocommerce_checkout_create_order', $order, array() );
        } else {
            do_action( 'woocommerce_store_api_checkout_update_order_from_request', $order, $request );
        }
        $result['validated_meta'] = $order->meta;
        $result['amount_before'] = $order->get_total();
        if ( 'failure' === $scenario || 'retry' === $scenario ) { $GLOBALS['transaction_status'] = 500; }
        if ( 'throw' === $scenario ) { $GLOBALS['transaction_throw'] = true; }
        if ( in_array( $scenario, array( 'pending', 'pending-conflict' ), true ) ) { $GLOBALS['existing_transaction'] = (object) array( 'status' => 'pending' ); }
        if ( 'pending-conflict' === $scenario ) { $GLOBALS['transaction_status'] = 503; }
        if ( defined( 'CLASSIC_CONTROLLER_INTEGRATION' ) ) { do_action( 'woocommerce_checkout_order_processed', 42, array(), $order ); }
        else { do_action( 'woocommerce_store_api_checkout_order_processed', $order ); }
    } catch ( \Throwable $error ) {
        $result['error'] = array( 'class' => get_class( $error ), 'code' => $error->errorCode ?? null, 'status' => $error->status ?? null, 'message' => $error->getMessage() );
        $result['failure_meta'] = $order->meta;
        $result['failure_session'] = WC()->session->values;
    }
    if ( 'retry' === $scenario ) {
        // A later process has only durable snapshots: transient context and session are gone.
        foreach ( array_keys( $order->meta ) as $key ) {
            if ( 0 === strpos( $key, '_kiriof_checkout_' ) ) { $order->delete_meta_data( $key ); }
        }
        WC()->session->values = array();
        $GLOBALS['transaction_status'] = 200;
        try { do_action( 'woocommerce_store_api_checkout_order_processed', $order ); }
        catch ( \Throwable $error ) { $result['retry_error'] = $error->getMessage(); }
    }
    $result['meta'] = $order->meta;
    $result['amount_after'] = $order->get_total();
    $result['calls'] = $GLOBALS['calls'];
    $result['transactions'] = $GLOBALS['transactions'];
    $result['customer_saves'] = $GLOBALS['customer_saves'];
    $result['session'] = WC()->session->values;
    $result['saves'] = $order->saves;
    echo json_encode( $result, JSON_THROW_ON_ERROR );
}
