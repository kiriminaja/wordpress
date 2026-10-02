<?php

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
$input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
$GLOBALS['current_user'] = $input['current_user'] ?? 7;
$GLOBALS['meta'] = $input['meta'] ?? array();
$GLOBALS['writes'] = array();
$GLOBALS['hooks'] = array();
function get_current_user_id() { return $GLOBALS['current_user']; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function get_user_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_user_meta( $id, $key, $value ) {
    $GLOBALS['writes'][] = array( $id, $key, $value );
    $GLOBALS['meta'][$id][$key] = $value;
}
function delete_user_meta( $id, $key ) {
    $GLOBALS['writes'][] = array( $id, $key, null );
    unset( $GLOBALS['meta'][$id][$key] );
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = array( $hook, $priority, $args ); }
final class DestinationSession {
    public $values;
    public function __construct( array $values ) { $this->values = $values; }
    public function get( $key, $default = null ) { return array_key_exists( $key, $this->values ) ? $this->values[$key] : $default; }
    public function set( $key, $value ) { $this->values[$key] = $value; }
}
final class DestinationCustomer {
    public function get_id() { return $GLOBALS['customer_id']; }
    public function get_shipping_address_1() { throw new RuntimeException( 'Mutable customer address read.' ); }
}
final class DestinationOrder {
    public function get_user_id() { return $GLOBALS['order_user']; }
    public function get_meta( $key, $single = true ) { return $GLOBALS['order_destination']; }
    public function get_shipping_address_1() { return $GLOBALS['order_address']['address_1']; }
    public function get_shipping_address_2() { return $GLOBALS['order_address']['address_2']; }
    public function get_shipping_city() { return $GLOBALS['order_address']['city']; }
    public function get_shipping_state() { return $GLOBALS['order_address']['state']; }
    public function get_shipping_postcode() { return $GLOBALS['order_address']['postcode']; }
    public function get_shipping_country() { return $GLOBALS['order_address']['country']; }
}
$GLOBALS['customer_id'] = $input['customer_id'] ?? 7;
$GLOBALS['order_user'] = $input['order_user'] ?? 7;
$GLOBALS['order_destination'] = $input['destination'] ?? null;
$GLOBALS['order_address'] = $input['order_address'] ?? $input['address'] ?? array();
$GLOBALS['wc'] = (object) array( 'customer' => new DestinationCustomer(), 'session' => new DestinationSession( $input['session'] ?? array() ) );
function WC() { return $GLOBALS['wc']; }
require ABSPATH . 'inc/Services/BuyerDestination.php';
require ABSPATH . 'inc/Services/CustomerDistrictService.php';
require ABSPATH . 'inc/Services/CustomerShippingDestinationService.php';
$service = new \KiriminAjaOfficial\Services\CustomerShippingDestinationService();
$result = array();
try {
    switch ( $input['operation'] ?? 'get' ) {
        case 'get': $result['destination'] = $service->get( ! empty( $input['object'] ) ? WC()->customer : ( $input['user_id'] ?? 7 ) ); break;
        case 'save': $service->save( $input['user_id'] ?? 7, $input['destination'], $input['address'] ); break;
        case 'sync': $service->syncCheckout( $input['destination'], $input['checkout_address'] ?? null ); break;
        case 'checkout': $result['destination'] = $service->forCheckout( WC()->session ); break;
        case 'hydrate': $service->hydrateSession(); break;
        case 'order': $service->orderProcessed( new DestinationOrder() ); break;
        case 'classic': $service->classicOrderProcessed( 100, array(), new DestinationOrder() ); break;
        case 'register': $service->register(); break;
    }
} catch ( InvalidArgumentException $error ) { $result['error'] = $error->getMessage(); }
$result['meta'] = $GLOBALS['meta'];
$result['writes'] = $GLOBALS['writes'];
$result['session'] = WC()->session->values;
$result['hooks'] = $GLOBALS['hooks'];
echo json_encode( $result, JSON_THROW_ON_ERROR );
