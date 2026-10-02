<?php
namespace KiriminAjaOfficial\Repositories {
    class SettingRepository {
        public function isCourierServiceEnabled( $a, $b ) { return 'disabled' !== $GLOBALS['mode']; }
        public function getSettingByKey( $key ) { return (object) array( 'value' => 'disconnected' === $GLOBALS['mode'] ? '' : 'connected' ); }
    }
}
namespace KiriminAjaOfficial\Services {
    class CheckoutServiceFactory {
        public function districtSearch( $postcode ) { return (object) array( 'status' => 200, 'data' => array( array( 'id' => 123, 'text' => 'Verified district' ) ) ); }
        public function createTransaction( $data ) {
            $GLOBALS['transactions'][] = $data;
            return new class { public function call() {
                if ( ! empty( $GLOBALS['transaction_throw'] ) ) { throw new \RuntimeException( 'private upstream secret' ); }
                return (object) array( 'status' => $GLOBALS['transaction_status'] ?? 200 );
            } };
        }
        public function calculation( $payload ) { ++$GLOBALS['calls']; return new class {
            public function call() { return (object) array( 'status' => 200, 'data' => array( 'calculation_result' => array( 'selected_expedition' => (object) array( 'service' => 'jne', 'service_type' => 'REG', 'force_insurance' => false, 'api_key' => 'private-selected-secret' ), 'cart_total_amt' => 100, 'cart_total_after_discount' => 100, 'insurance_amt' => 2, 'cod_amt' => 0, 'ongkir_fee_amt' => 10, 'ongkir_fee_raw' => 10, 'payload' => array( 'api_key' => 'private-calc-secret' ) ), 'carts_attribute' => array( 'weight' => 1000, 'length' => 1, 'width' => 1, 'height' => 1, 'private_token' => 'private-attribute-secret' ) ) ); }
        }; }
    }
    class ShippingDiscountCouponService { public function getAdjustedRatePricing( $selected, $cost ) { return array( 'cost' => 'coupon' === $GLOBALS['mode'] ? 5 : $cost ); } }
    class CourierServiceCatalog { public static function isSupportedCourier( $code, $row, $type ) { return true; } }
}
namespace {
    define( 'ABSPATH', __DIR__ );
    $GLOBALS['mode'] = $argv[1] ?? 'valid'; $GLOBALS['calls'] = 0;
    function __( $text, $domain ) { return $text; }
    function wp_json_encode( $data ) { return json_encode( $data ); }
    class Line {
        public function get_method_id() { return 'kiriminaja-official'; }
        public function get_instance_id() { return 4; }
        public function get_meta( $key, $single = true ) { return array( 'kiriof_rate_service' => 'jne', 'kiriof_rate_service_type' => 'REG', 'kiriof_rate_cod_available' => 'no' )[$key] ?? ''; }
        public function get_total() { return 'price' === $GLOBALS['mode'] ? 9 : ( 'coupon' === $GLOBALS['mode'] ? 5 : 10 ); }
    }
    class Fee { public function get_meta( $key, $single = true ) { return 'insurance'; } public function get_name() { return 'Insurance'; } public function get_total() { return 'fee' === $GLOBALS['mode'] ? 3 : 2; } }
    class Order {
        public function get_items( $type ) {
            if ( 'shipping' === $type ) { return array( new Line() ); }
            if ( 'fee' === $type ) { return 'duplicate' === $GLOBALS['mode'] ? array( new Fee(), new Fee() ) : array( new Fee() ); }
            return array( new class { public function get_total() { return 100; } } );
        }
        public function get_total_fees() { return 'fee' === $GLOBALS['mode'] ? 3 : 2; }
        public function get_total_tax() { return 'tax' === $GLOBALS['mode'] ? 7 : 0; }
        public function get_currency() { return 'USD'; }
        public function get_shipping_address_1() { return 'Main street number 123'; }
        public function get_shipping_address_2() { return ''; }
        public function get_shipping_city() { return 'Jakarta'; }
        public function get_shipping_state() { return 'JK'; }
        public function get_shipping_postcode() { return '12345'; }
        public function get_shipping_country() { return 'ID'; }
        public function get_total() { if ( 'tax' === $GLOBALS['mode'] ) { return 119; } return 'total' === $GLOBALS['mode'] ? 113 : ( 'coupon' === $GLOBALS['mode'] ? 107 : 112 ); }
    }
    class Rate extends Line { public function get_id() { return 'kiriminaja-official_jne_REG'; } public function get_cost() { return 'coupon' === $GLOBALS['mode'] ? 5 : 10; } public function get_meta_data() { return array( 'kiriof_rate_service' => 'jne', 'kiriof_rate_service_type' => 'REG', 'kiriof_rate_cod_available' => 'no' ); } }
    class WC_Shipping_Zones {
        public static function get_zone_matching_package( $p ) { return new class { public function get_shipping_methods( $enabled ) { return array( new class { public $id = 'kiriminaja-official'; public $enabled = 'yes'; public function get_instance_id() { return 'zone' === $GLOBALS['mode'] ? 5 : 4; } } ); } }; }
    }
    class Woo {
        public $session; public $cart; public $customer;
        public function __construct() {
            $this->session = new class { public function get( $key, $default ) { return array( 'unknown' === $GLOBALS['mode'] ? 'kiriminaja-official_unknown' : 'kiriminaja-official_jne_REG' ); } };
            $this->cart = new class { public function get_cart() { return array(); } };
        }
        public function shipping() { return new class { public function get_packages() {
            $destination = array( 'address_1' => 'Main street number 123', 'address_2' => '', 'city' => 'Jakarta', 'state' => 'JK', 'postcode' => '12345', 'country' => 'ID' );
            if ( 'street' === $GLOBALS['mode'] ) { $destination['address_1'] = 'Tampered street'; }
            if ( 'country' === $GLOBALS['mode'] ) { $destination['country'] = 'US'; }
            if ( 'missing-field' === $GLOBALS['mode'] ) { unset( $destination['address_2'] ); }
            return array( array( 'destination' => $destination, 'rates' => array( 'kiriminaja-official_jne_REG' => new Rate() ) ) );
        } }; }
    }
    function WC() { static $woo; return $woo ?? ( $woo = new Woo() ); }
    require dirname( __DIR__, 2 ) . '/inc/Services/BuyerDestination.php';
    require dirname( __DIR__, 2 ) . '/inc/Services/ExpressCheckoutValidationService.php';
    if ( defined( 'EXPRESS_CONTROLLER_INTEGRATION' ) ) { return; }
    try {
        $service = new \KiriminAjaOfficial\Services\ExpressCheckoutValidationService( new \KiriminAjaOfficial\Repositories\SettingRepository(), new \KiriminAjaOfficial\Services\CheckoutServiceFactory() );
        $data = $service->validate( new Order(), 123, 'cod' === $GLOBALS['mode'] ? 'cod' : 'bacs', true );
        echo json_encode( array( 'ok' => true, 'calls' => $GLOBALS['calls'], 'snapshot' => $data ) );
    } catch ( \Throwable $e ) { echo json_encode( array( 'ok' => false, 'calls' => $GLOBALS['calls'], 'error' => $e->getMessage() ) ); }
}
