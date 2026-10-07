<?php
namespace KiriminAjaOfficial\Base {
    class BaseInit { public function logThis( $message, $data ) {} }
}
namespace KiriminAjaOfficial\Repositories {
    class SettingRepository {
        public function getSettingByKey( $key ) { return (object) array( 'value' => 'enable_insurance' === $key ? $GLOBALS['input']['global_insurance'] : ( 'origin_zip_code' === $key ? '' : 123 ) ); }
        public function hasEnabledCourierServices() { return true; }
        public function isCourierServiceEnabled( $courier, $service ) { return 'jne' === strtolower( $courier ) && 'REG' === strtoupper( $service ) && empty( $GLOBALS['input']['disabled'] ); }
        public function validateWhiteListExpedition( $rows ) { return array_values( array_filter( $rows, function( $row ) { return $this->isCourierServiceEnabled( $row->service, $row->service_type ); } ) ); }
    }
    class WpPostMetaRepository {}
    class KiriminajaApiRepository {
        public function getPricing( $payload ) { ++$GLOBALS['api_calls']; $GLOBALS['api_payload'] = $payload; return array( 'status' => 200, 'data' => $GLOBALS['quote'] ); }
    }
}
namespace KiriminAjaOfficial\Services\UtilServices {
    class GetWCCartAttributeService {
        public function __construct( $payload, $repository ) {}
        public function call() { return (object) array( 'status' => 200, 'data' => array( 'weight' => 1000, 'length' => 1, 'width' => 2, 'height' => 3, 'item_value' => 100000 ) ); }
    }
}
namespace {
    // Production BaseService has legacy implicit-nullable signatures on PHP 8.4+.
    error_reporting( E_ALL & ~E_DEPRECATED );
    define( 'ABSPATH', __DIR__ );
    $GLOBALS['input'] = json_decode( $argv[1], true );
    $GLOBALS['api_calls'] = 0;
    $GLOBALS['api_payload'] = null;
    function sanitize_text_field( $value ) { return (string) $value; }
    function __( $text, $domain ) { return $text; }
    function wp_json_encode( $value ) { return json_encode( $value ); }
    function get_transient( $key ) { return false; }
    function set_transient( $key, $value, $ttl ) { return true; }
    function WC() { return $GLOBALS['wc']; }
    $coupon = new class { public function get_free_shipping() { return $GLOBALS['input']['free_coupon']; } };
    $GLOBALS['wc'] = (object) array( 'cart' => new class( $coupon ) {
        private $coupon;
        public function __construct( $coupon ) { $this->coupon = $coupon; }
        public function get_shipping_total() { return 0; }
        public function get_cart_contents_total() { return 100000; }
        public function get_coupons() { return array( 'free' => $this->coupon ); }
    } );
    $GLOBALS['quote'] = (object) array( 'status' => true, 'results' => array( (object) array(
        'service' => 'jne', 'service_type' => 'REG', 'service_name' => 'Regular',
        'cost' => 20000, 'discount_amount' => 2000, 'discount_percentage' => 10,
        'insurance' => 1250, 'force_insurance' => $GLOBALS['input']['force_insurance'], 'cod' => true,
        'setting' => (object) array( 'cod_fee_amount' => 3250.5, 'minimum_cod_fee' => 2500 ),
    ) ) );
    if ( ! empty( $GLOBALS['input']['missing_rate'] ) ) { $GLOBALS['quote']->results = array(); }
    require __DIR__ . '/../../inc/Utils/ServiceResponse.php';
    require __DIR__ . '/../../inc/Base/BaseService.php';
    require __DIR__ . '/../../inc/Services/CheckoutServices/PricingCacheService.php';
    require __DIR__ . '/../../inc/Services/CheckoutServices/CheckoutCalculationService.php';
    require __DIR__ . '/../../inc/Services/ShippingDiscountCouponService.php';
    $payload = array( 'destination_area_id' => 456, 'expedition' => $GLOBALS['input']['expedition'] ?? 'jne_REG', 'is_insurance' => $GLOBALS['input']['insurance'], 'is_cod' => $GLOBALS['input']['cod'], 'wc_cart_contents' => array( array( 'line_total' => 100000, 'line_subtotal' => 100000 ) ) );
    if ( array_key_exists( 'blocks', $GLOBALS['input'] ) ) { $payload['blocks_quote_validation'] = $GLOBALS['input']['blocks']; }
    if ( ! empty( $GLOBALS['input']['cached'] ) ) {
        \KiriminAjaOfficial\Services\CheckoutServices\PricingCacheService::put( array( 'subdistrict_origin' => 123, 'subdistrict_destination' => 456, 'weight' => 1000, 'length' => 1, 'width' => 2, 'height' => 3, 'insurance' => (int) $GLOBALS['input']['insurance'], 'item_value' => 100000, 'courier' => array( 'jne' ) ), $GLOBALS['quote'] );
    }
    $response = ( new \KiriminAjaOfficial\Services\CheckoutServices\CheckoutCalculationService( $payload, new \KiriminAjaOfficial\Repositories\SettingRepository(), new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository(), new \KiriminAjaOfficial\Repositories\WpPostMetaRepository() ) )->call();
    $adjusted = null;
    if ( 200 === $response->status && $GLOBALS['input']['free_coupon'] ) {
        $calc = $response->data['calculation_result'];
        $adjusted = ( new \KiriminAjaOfficial\Services\ShippingDiscountCouponService() )->getAdjustedRatePricing( $calc['selected_expedition'], (float) $calc['ongkir_fee_amt'] );
    }
    echo json_encode( array( 'status' => $response->status, 'data' => $response->data, 'api_calls' => $GLOBALS['api_calls'], 'api_payload' => $GLOBALS['api_payload'], 'adjusted' => $adjusted, 'raw_quote' => $GLOBALS['quote'] ) );
}
