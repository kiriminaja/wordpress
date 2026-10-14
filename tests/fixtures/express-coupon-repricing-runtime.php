<?php
/** Offline boundaries only: real settings, service factory, calculation, cache and coupon pricing. */
error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require ABSPATH . 'vendor/autoload.php';
class ExpressCouponDb {
    public $prefix = 'express_';
    public $last_error = '';
    public $rows = array( 'origin_whitelist_expedition_id' => 'ninja,jne', 'origin_whitelist_expedition_services' => '{"ninja":["STANDARD"],"jne":["REG"]}', 'origin_sub_district_id' => '123', 'origin_zip_code' => '55791' );
    public function prepare( $sql, $keys ) { return $keys; }
    public function get_row( $key ) { return isset( $this->rows[$key] ) ? (object) array( 'key' => $key, 'value' => $this->rows[$key] ) : null; }
    public function get_results( $keys ) { return array_values( array_filter( array_map( array( $this, 'get_row' ), $keys ) ) ); }
}
class ExpressCouponSession {
    public $values = array( 'destination_id' => 456, 'chosen_payment_method' => 'bacs', 'chosen_shipping_methods' => array( 'kiriminaja-official_ninja_STANDARD' ), 'kiriof_insurance' => 1 );
    public function get( $key, $default = null ) { return $this->values[$key] ?? $default; }
    public function set( $key, $value ) { $this->values[$key] = $value; }
}
class WC_Coupon {
    public function __construct( public $code = 'shipping' ) {}
    public function get_id() { return 'shipping' === $this->code ? 1 : 2; }
    public function get_code() { return $this->code; }
    public function get_discount_type() { return 'shipping' === $this->code ? $GLOBALS['coupon_type'] : 'fixed_cart'; }
    public function get_amount() { return $GLOBALS['coupon_amount']; }
    public function get_free_shipping() { return 'free' === $this->code; }
}
class ExpressCouponCart {
    public $coupons = array();
    public $contents = array();
    public function get_cart() { return $this->contents; }
    public function get_coupons() { return $this->coupons; }
    public function get_cart_contents_total() { return 100000; }
    public function get_discount_total() { return 0; }
    public function get_discount_tax() { return 0; }
    public function get_cart_hash() { return 'stable-product-cart'; }
}
class WC_Shipping_Rate {
    public function __construct( public $id, public $label, public $cost, public $meta_data ) {}
    public function get_id() { return $this->id; }
    public function get_method_id() { return 'kiriminaja-official'; }
    public function get_cost() { return $this->cost; }
    public function get_label() { return $this->label; }
    public function get_meta( $key, $single = true ) { return $this->meta_data[$key] ?? ''; }
    public function get_meta_data() { return $this->meta_data; }
    public function set_description( $value ) {}
    public function set_delivery_time( $value ) {}
}
class WC_Shipping_Method {
    public $rates = array();
    public function init_settings() { $this->settings = array(); }
    public function get_option( $key, $default = '' ) { return $default; }
    public function add_rate( $row ) { $this->rates[$row['id']] = new WC_Shipping_Rate( $row['id'], $row['label'], $row['cost'], $row['meta_data'] ); }
}
function WC() { return $GLOBALS['wc']; }
function __( $text, $domain = '' ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return strtolower( $value ); }
function wp_json_encode( $value ) { return json_encode( $value, JSON_THROW_ON_ERROR ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function wc_price( $value ) { return (string) $value; }
function wc_get_price_decimals() { return 0; }
function get_option( $key, $default = false ) { return array( 'woocommerce_weight_unit' => 'kg', 'woocommerce_dimension_unit' => 'cm' )[$key] ?? $default; }
function get_transient( $key ) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][$key] = $value; return true; }
function get_post_meta( $id, $key, $single = true ) { return '_kiriof_coupon_couriers' === $key ? array( 'ninja' ) : array(); }
function metadata_exists( ...$args ) { return false; }
function kiriof_log( ...$args ) {}
function wc_add_notice( ...$args ) { throw new RuntimeException( 'Unexpected notice' ); }
function wp_remote_post( ...$args ) { throw new RuntimeException( 'Network prohibited' ); }
function wp_remote_get( ...$args ) { throw new RuntimeException( 'Network prohibited' ); }
function kiriof_setting_repository() { return $GLOBALS['repo']; }
function kiriof_api_repository() { return $GLOBALS['api']; }
function kiriof_checkout_service_factory() { return $GLOBALS['factory']; }
function kiriof_helper() { return new class { public function formatServiceName( $courier, $service ) { return strtoupper( $courier ) . ' ' . $service; } }; }
$wpdb = new ExpressCouponDb();
$GLOBALS['coupon_type'] = 'kiriof_fixed_shipping_discount';
$GLOBALS['coupon_amount'] = 5000;
$GLOBALS['wc'] = (object) array( 'session' => new ExpressCouponSession(), 'cart' => new ExpressCouponCart() );
$product = new class { public function needs_shipping() { return true; } public function get_length() { return 1; } public function get_width() { return 2; } public function get_height() { return 3; } };
WC()->cart->contents = array( array( 'product_id' => 1, 'data' => $product, 'quantity' => 1, 'line_total' => 100000, 'line_subtotal' => 100000 ) );
$GLOBALS['repo'] = new \KiriminAjaOfficial\Repositories\SettingRepository();
$GLOBALS['api'] = new class extends \KiriminAjaOfficial\Repositories\KiriminajaApiRepository {
    public $calls = 0;
    public $payload;
    public $quote;
    public function __construct() {
        $this->quote = (object) array( 'status' => true, 'results' => array(
            (object) array( 'service' => 'ninja', 'service_type' => 'STANDARD', 'service_name' => 'Standard', 'cost' => 20000, 'discount_amount' => 2000, 'insurance' => 1250, 'force_insurance' => true, 'cod' => true, 'setting' => (object) array( 'cod_fee_amount' => 3250, 'minimum_cod_fee' => 2500 ) ),
            (object) array( 'service' => 'jne', 'service_type' => 'REG', 'service_name' => 'Regular', 'cost' => 16000, 'discount_amount' => 0, 'insurance' => 1000, 'cod' => true ),
        ) );
    }
    public function getPricing( $payload ) { ++$this->calls; $this->payload = $payload; return array( 'status' => true, 'data' => $this->quote ); }
};
$meta = new class extends \KiriminAjaOfficial\Repositories\WpPostMetaRepository {
    public function getRequiredRowsByPostIdsAndMetaKeys( $ids, $keys ) { return array_map( static fn( $key, $value ) => (object) array( 'post_id' => 1, 'meta_key' => $key, 'meta_value' => $value ), array( '_weight', '_length', '_width', '_height' ), array( 1, 1, 2, 3 ) ); }
};
$cod = new class extends \KiriminAjaOfficial\Repositories\CodFeeApiRepository { public function __construct() {} };
$GLOBALS['factory'] = new \KiriminAjaOfficial\Services\CheckoutServiceFactory( $GLOBALS['repo'], new \KiriminAjaOfficial\Repositories\TransactionRepository(), $meta, $GLOBALS['api'], $cod, new \KiriminAjaOfficial\Services\ShipmentLocationService( null, $GLOBALS['repo'] ) );
require ABSPATH . 'wc/KiriminajaShippingMethod.php';
kiriof_shipping_method();
$package = array( 'contents' => WC()->cart->contents, 'destination' => array( 'country' => 'ID', 'postcode' => '', 'address_1' => 'Jalan Indonesia 123' ) );
$service = new \KiriminAjaOfficial\Services\ShippingDiscountCouponService();
$result = array();
foreach ( array( 'none', 'fixed', 'removed', 'fixed_again', 'removed_again', 'percent', 'over_cap', 'free', 'free_removed' ) as $state ) {
    $GLOBALS['coupon_type'] = 'percent' === $state ? 'kiriof_percent_shipping_discount' : 'kiriof_fixed_shipping_discount';
    $GLOBALS['coupon_amount'] = 'over_cap' === $state ? 50000 : ( 'percent' === $state ? 25 : 5000 );
    WC()->cart->coupons = str_contains( $state, 'fixed' ) || in_array( $state, array( 'percent', 'over_cap' ), true ) ? array( 'shipping' => new WC_Coupon() ) : ( 'free' === $state ? array( 'free' => new WC_Coupon( 'free' ) ) : array() );
    $method = new Kiriof_Shipping_Method_Controller();
    $method->calculate_shipping( $package );
    $row = $method->rates['kiriminaja-official_ninja_STANDARD'];
    $result['states'][$state] = array( 'cost' => $row->get_cost(), 'ids' => array_keys( $method->rates ), 'meta' => WC()->session->get( 'kiriof_shipping_coupon_rate_meta' ), 'validation' => $service->validateCouponForCart( new WC_Coupon() ), 'native_adjusted' => $service->getAdjustedRatePricing( $row, 18000 ), 'api_calls' => $GLOBALS['api']->calls );
}
WC()->cart->coupons = array( 'shipping' => new WC_Coupon() );
$result['calculation'] = $GLOBALS['factory']->calculation( array( 'destination_area_id' => 456, 'expedition' => 'ninja_STANDARD', 'is_insurance' => true, 'is_cod' => false, 'wc_cart_contents' => WC()->cart->contents ) )->call();
$result['quote'] = json_decode( json_encode( $GLOBALS['api']->quote ), true );
$result['calculation'] = json_decode( json_encode( $result['calculation'] ), true );
$result['cached_quote'] = \KiriminAjaOfficial\Services\CheckoutServices\PricingCacheService::get( $GLOBALS['api']->payload );
$result['api_calls'] = $GLOBALS['api']->calls;
// Unrelated Instant metadata must survive stale Express cleanup, including early exits.
$instant = array( 'cost' => 999, 'original_cost' => 999 );
foreach ( array( 'empty_policy', 'country', 'address', 'destination', 'missing_origin', 'no_rates' ) as $case ) {
    $wpdb->rows['origin_whitelist_expedition_services'] = 'empty_policy' === $case ? '{}' : '{"ninja":["STANDARD"],"jne":["REG"]}';
    $wpdb->rows['origin_sub_district_id'] = 'missing_origin' === $case ? '0' : '123';
    $GLOBALS['repo']->clearCache();
    WC()->session->set( 'destination_id', 'destination' === $case ? 0 : 456 );
    WC()->session->set( 'kiriof_shipping_coupon_rate_meta', array( 'kiriminaja-official_ninja_STANDARD' => array( 'cost' => 1 ), 'kiriminaja-instant:3:gosend:instant' => $instant ) );
    $invalid = $package;
    if ( 'country' === $case ) { $invalid['destination']['country'] = 'US'; }
    if ( 'address' === $case ) { $invalid['destination']['address_1'] = 'tiny'; }
    if ( 'no_rates' === $case ) { $invalid['destination']['postcode'] = 'unique'; $GLOBALS['api']->quote->results = array(); }
    // Missing origin is expected to emit the existing checkout notice.
    if ( 'missing_origin' === $case ) { $invalid['origin'] = array( 'origin_sub_district_id' => 0 ); }
    try { ( new Kiriof_Shipping_Method_Controller() )->calculate_shipping( $invalid ); } catch ( RuntimeException $e ) { if ( 'missing_origin' !== $case ) { throw $e; } }
    $result['cleanup'][$case] = WC()->session->get( 'kiriof_shipping_coupon_rate_meta' );
}
echo json_encode( $result, JSON_THROW_ON_ERROR );
