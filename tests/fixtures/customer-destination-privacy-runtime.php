<?php

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
$input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
$GLOBALS['meta'] = $input['meta'] ?? array();
$GLOBALS['hooks'] = array();
function __( $text, $domain ) { return $text; }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function get_user_by( $field, $email ) {
    foreach ( $GLOBALS['input']['users'] ?? array() as $id => $address ) {
        if ( $address === $email ) { return (object) array( 'ID' => $id ); }
    }
    return false;
}
function get_user_meta( $id, $key, $single ) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function delete_user_meta( $id, $key ) {
    if ( ! array_key_exists( $key, $GLOBALS['meta'][$id] ?? array() ) ) { return false; }
    unset( $GLOBALS['meta'][$id][$key] );
    return true;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = array( $hook, $priority, $args ); }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { add_filter( $hook, $callback, $priority, $args ); }
final class PrivacyOrder {
    public $meta;
    public $saves = 0;
    public function __construct( $meta ) { $this->meta = $meta; }
    public function get_meta( $key, $single ) { return $this->meta[$key] ?? ''; }
    public function delete_meta_data( $key ) { unset( $this->meta[$key] ); }
    public function save_meta_data() { ++$this->saves; }
}
require ABSPATH . 'inc/Services/BuyerDestination.php';
require ABSPATH . 'inc/Services/CustomerShippingDestinationService.php';
require ABSPATH . 'inc/Services/CustomerDestinationPrivacyService.php';
$service = new \KiriminAjaOfficial\Services\CustomerDestinationPrivacyService();
$order = new PrivacyOrder( $input['order_meta'] ?? array() );
$result = array();
switch ( $input['operation'] ?? 'export' ) {
    case 'register':
        $service->register();
        $result['exporters'] = array_keys( $service->exporters( array( 'existing' => array() ) ) );
        $result['erasers'] = array_keys( $service->erasers( array( 'existing' => array() ) ) );
        break;
    case 'export': $result['response'] = $service->exportUser( $input['email'] ?? '', $input['page'] ?? 1 ); break;
    case 'erase':
        $result['response'] = $service->eraseUser( $input['email'] ?? '', $input['page'] ?? 1 );
        $result['repeat'] = $service->eraseUser( $input['email'] ?? '', $input['page'] ?? 1 );
        break;
    case 'order':
        $result['response'] = $service->exportOrder( array( array( 'name' => 'Native', 'value' => 'Kept' ) ), $order );
        $service->eraseOrder( $order );
        $service->eraseOrder( $order );
        break;
}
$result['meta'] = $GLOBALS['meta'];
$result['order_meta'] = $order->meta;
$result['saves'] = $order->saves;
$result['hooks'] = $GLOBALS['hooks'];
echo json_encode( $result, JSON_THROW_ON_ERROR );
