<?php
namespace KiriminAjaOfficial\Repositories {
    class SettingRepository {
        public function getCourierServiceSelection() { return 'disabled' === $GLOBALS['mode'] ? array() : array( 'gosend' => array( 'instant' ) ); }
        public function isCourierServiceEnabled( ...$args ) { return true; }
    }
}
namespace KiriminAjaOfficial\Services {
    class ShipmentLocationService {}
    class CheckoutServiceFactory {
        public function districtSearch( $postcode ) { return (object) array( 'status' => 200, 'data' => array( array( 'id' => 123, 'text' => 'Authoritative district' ) ) ); }
    }
}
namespace {
    define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
    define( 'KIRIOF_NONCE', 'test' );
    function __( $text, $domain = '' ) { return $text; }
    function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
    function sanitize_textarea_field( $value ) { return $value; }
    function wp_unslash( $value ) { return $value; }
    function check_ajax_referer( $action, $key ) { if ( 'valid' !== ( $_POST[$key] ?? '' ) ) { throw new \RuntimeException( 'Invalid nonce' ); } }
    function wp_send_json_error( $data ) { $GLOBALS['response'] = array( 'success' => false, 'data' => $data ); }
    function wp_send_json_success( $data ) { $GLOBALS['response'] = array( 'success' => true, 'data' => $data ); }
    function wp_die() { throw new \RuntimeException( 'fixture-stop' ); }
    function wp_verify_nonce( ...$args ) { return true; }
    function is_checkout() { return false; }
    function WC() { return $GLOBALS['wc']; }
    require ABSPATH . 'inc/Services/BuyerDestination.php';
    require ABSPATH . 'inc/Services/InstantCheckoutQuoteService.php';
    require ABSPATH . 'inc/Controllers/CheckoutController.php';
    require ABSPATH . 'inc/Controllers/GeneralAjaxController.php';
    $GLOBALS['mode'] = $argv[1];
    $address = array( 'address_1' => 'Main street 10', 'address_2' => '', 'city' => 'Jakarta', 'state' => 'JK', 'postcode' => '12345', 'country' => 'ID' );
    $destination = array( 'district_id' => '123', 'district_label' => 'District', 'postcode' => '12345', 'country' => 'ID', 'address_type' => 'shipping', 'version' => 2, 'destination_latitude' => '-6.2', 'destination_longitude' => '106.8', 'shipping_address' => $address );
    $session = new class {
        public array $writes = array();
        public array $values = array( 'destination_id' => '123', 'shipping_destination_id' => '123', 'chosen_shipping_methods' => array( 'legacy' ), 'kiriof_insurance' => 1, 'chosen_payment_method' => 'cod' );
        public function get( $key, $default = null ) { return $this->values[$key] ?? $default; }
        public function set( $key, $value ) { $this->writes[] = $key; $this->values[$key] = $value; }
    };
    $GLOBALS['wc'] = (object) array( 'session' => $session, 'cart' => new class { public function needs_shipping() { return true; } } );
    $controller = ( new \ReflectionClass( \KiriminAjaOfficial\Controllers\CheckoutController::class ) )->newInstanceWithoutConstructor();
    foreach ( array( 'setting_repository' => new \KiriminAjaOfficial\Repositories\SettingRepository(), 'checkout_service_factory' => new \KiriminAjaOfficial\Services\CheckoutServiceFactory() ) as $key => $value ) {
        $property = new \ReflectionProperty( $controller, $key ); $property->setValue( $controller, $value );
    }
    if ( 'mismatch' === $GLOBALS['mode'] ) { $session->values['destination_id'] = '999'; }
    if ( 'shipping-mismatch' === $GLOBALS['mode'] ) { $session->values['shipping_destination_id'] = '999'; }
    if ( 'tamper' === $GLOBALS['mode'] ) { $address['address_1'] = 'Other street'; }
    if ( 'clear' === $GLOBALS['mode'] ) { $destination = array_merge( $destination, array( 'version' => 1, 'district_id' => '', 'district_label' => '' ) ); unset( $destination['destination_latitude'], $destination['destination_longitude'], $destination['shipping_address'] ); }
    $data = array( 'action' => 'sync_classic_pin', 'address_scope' => 'shipping-mismatch' === $GLOBALS['mode'] ? 'shipping' : 'billing', 'effective_address' => $address, 'destination' => $destination, 'insurance' => false, 'payment_method' => 'bacs', 'shipping_metode_id' => 'other' );
    if ( in_array( $GLOBALS['mode'], array( 'legacy-same', 'legacy-changed' ), true ) ) {
        $session->values['kiriof_buyer_destination'] = $destination;
        $data = array( 'destination_id' => 'legacy-same' === $GLOBALS['mode'] ? 123 : 999, 'postcode' => '12345' );
    }
    $_POST = array( 'nonce' => 'nonce' === $GLOBALS['mode'] ? 'invalid' : 'valid', 'data' => json_encode( $data ) );
    if ( in_array( $GLOBALS['mode'], array( 'district-same', 'district-changed' ), true ) ) {
        $session->values['kiriof_buyer_destination'] = $destination;
        $_POST = array( 'nonce' => 'valid', 'val' => 'district-same' === $GLOBALS['mode'] ? 123 : 999, 'postcode' => '12345' );
        $controller = new \KiriminAjaOfficial\Controllers\GeneralAjaxController( new \KiriminAjaOfficial\Services\CheckoutServiceFactory() );
        $controller->kiriof_getDestinationArea();
    } else {
    try { $controller->kiriof_ajax_session_save(); } catch ( \RuntimeException $error ) {}
    }
    // The fixture's wp_die throws inside the controller catch; successful writes remain authoritative.
    echo json_encode( array( 'writes' => $session->writes, 'session' => $session->values ) );
}
