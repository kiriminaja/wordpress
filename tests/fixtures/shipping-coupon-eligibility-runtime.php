<?php
// Isolated WooCommerce runtime: coupon pricing must not need the carrier API.
define( 'ABSPATH', __DIR__ );
function __( $text, $domain = '' ) { return $text; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ); }
function sanitize_text_field( $value ) { return (string) $value; }
function get_transient( $key ) { return false; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $key ] ?? array(); }
function metadata_exists( $type, $id, $key ) { return false; }
function wc_get_price_decimals() { return 2; }
function WC() { return $GLOBALS['wc']; }
// Execute registered callbacks by priority, as WordPress does.
function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['hooks'][ $hook ][ $priority ][] = array( $callback, $accepted ); }
function add_action( $hook, $callback, $priority = 10, $accepted = 1 ) { add_filter( $hook, $callback, $priority, $accepted ); }
function apply_filters( $hook, $value, ...$args ) {
    $callbacks = $GLOBALS['hooks'][ $hook ] ?? array();
    ksort( $callbacks );
    foreach ( $callbacks as $group ) {
        foreach ( $group as list( $callback, $accepted ) ) {
            $value = call_user_func_array( $callback, array_slice( array_merge( array( $value ), $args ), 0, $accepted ) );
        }
    }
    return $value;
}
function do_action( $hook, ...$args ) {
    $callbacks = $GLOBALS['hooks'][ $hook ] ?? array();
    ksort( $callbacks );
    foreach ( $callbacks as $group ) {
        foreach ( $group as list( $callback, $accepted ) ) {
            call_user_func_array( $callback, array_slice( $args, 0, $accepted ) );
        }
    }
}
function wc_add_notice( $message, $type = 'success' ) { $GLOBALS['notices'][ $type ][] = $message; }
function wc_has_notice( $message, $type ) { return in_array( $message, $GLOBALS['notices'][ $type ] ?? array(), true ); }

class WC_Coupon {
    public $type = 'kiriof_fixed_shipping_discount';
    public $amount = 4000;
    public $free = false;
    public function get_id() { return 1; }
    public function get_code() { return 'delivery'; }
    public function get_discount_type() { return $this->type; }
    public function get_amount() { return $this->amount; }
    public function get_free_shipping() { return $this->free; }
}
class CouponSession {
    public $data = array();
    public function get( $key, $default = null ) { return $this->data[ $key ] ?? $default; }
    public function set( $key, $value ) { $this->data[ $key ] = $value; }
}
class CouponCart {
    public $coupons = array();
    public function get_coupons() { return $this->coupons; }
    public function get_cart() { return array( array( 'data' => new class { public function needs_shipping() { return true; } } ) ); }
    public function get_shipping_packages() { return array( 0 => array() ); }
    public function get_applied_coupons() { return array_map( static fn( $coupon ) => $coupon->get_code(), $this->coupons ); }
    public function apply_coupon( $coupon, $native_valid = true ) {
        // WC_Discounts::is_coupon_valid performs product/cart eligibility first,
        // then calls woocommerce_coupon_is_valid regardless of product validity.
        $product_valid = apply_filters( 'woocommerce_coupon_is_valid_for_product', false, null, $coupon, array() );
        $eligible = $product_valid || apply_filters( 'woocommerce_coupon_is_valid_for_cart', true, $coupon );
        if ( ! apply_filters( 'woocommerce_coupon_is_valid', $native_valid && $eligible, $coupon ) ) { return false; }
        $this->coupons = array( $coupon );
        do_action( 'woocommerce_applied_coupon', $coupon->get_code() );
        wc_add_notice( 'Coupon applied', 'success' );
        return true;
    }
    public function remove_coupon( $code ) {
        $this->coupons = array();
        do_action( 'woocommerce_removed_coupon', $code );
    }
    public function calculate_totals() {
        do_action( 'woocommerce_before_calculate_totals', $this );
        $wc = WC();
        check( false === $wc->session->get( 'shipping_for_package_0' ), 'Priority 5 invalidation must precede native totals at 20' );
        // Mock only the provider's raw quote. The real coupon service prices it.
        $raw = (object) array( 'courier' => 'grab_express' );
        $pricing = ( new \KiriminAjaOfficial\Services\ShippingDiscountCouponService() )->getAdjustedRatePricing( $raw, 10000 );
        $id = 'kiriminaja-instant:12:grab_express:instant';
        $rate = new WC_Shipping_Rate( $id, $pricing['cost'] );
        $wc->shipping->packages = array( 0 => array( 'rates' => array( $id => $rate ) ) );
        $wc->session->set( 'shipping_for_package_0', $wc->shipping->packages[0] );
        $wc->session->set( 'kiriof_shipping_coupon_rate_meta', array( $id => $pricing ) );
    }

}
class CouponShipping {
    public $packages = array();
    public function get_packages() { return $this->packages; }
    public function reset_shipping() { throw new RuntimeException( 'Must preserve chosen methods' ); }
}
class CouponWC {
    public $session;
    public $cart;
    public $shipping;
    public function shipping() { return $this->shipping; }
}
class WC_Shipping_Rate {
    public function __construct( private $id, private $cost ) {}
    public function get_id() { return $this->id; }
    public function get_cost() { return $this->cost; }
    public function get_label() { return 'Delivery'; }
}
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
require __DIR__ . '/../../inc/Services/CourierServiceCatalog.php';
require __DIR__ . '/../../inc/Services/ShippingDiscountCouponService.php';
class CouponRegionCache { const CRON_HOOK = 'fixture_coupon_regions'; }
class_alias( CouponRegionCache::class, 'KiriminAjaOfficial\\Services\\ShippingDiscountRegionCacheService' );
require __DIR__ . '/../../inc/Controllers/ShippingDiscountCouponController.php';
$wc = new CouponWC();
$wc->session = new CouponSession();
$wc->cart = new CouponCart();
$wc->shipping = new CouponShipping();
$GLOBALS['wc'] = $wc;
$coupon = new WC_Coupon();
$wc->cart->coupons = array( $coupon );
$service = new \KiriminAjaOfficial\Services\ShippingDiscountCouponService();
foreach ( array( 'jne', 'jnt', 'gosend', 'grab_express', 'GrabExpress', 'J&T Express' ) as $courier ) {
    $pricing = $service->getAdjustedRatePricing( (object) array( 'courier' => $courier ), 10000 );
    check( 6000.0 === $pricing['cost'], 'Known courier fixed discount: ' . $courier );
}
$rate = new WC_Shipping_Rate( 'kiriminaja-instant:12:grab_express:instant', 10000 );
check( 6000.0 === $service->getAdjustedRatePricing( $rate, 10000 )['cost'], 'Real WC rate identity' );
$wc->session->set( 'chosen_shipping_methods', array( $rate->get_id() ) );
$GLOBALS['meta']['_kiriof_coupon_couriers'] = array( 'GrabExpress' );
check( $service->validateCouponForCart( $coupon )['valid'], 'Instant instance selected and alias restriction' );
$GLOBALS['meta']['_kiriof_coupon_couriers'] = array( 'gosend' );
check( ! $service->validateCouponForCart( $coupon )['valid'], 'Reject other Instant courier' );
$GLOBALS['meta']['_kiriof_coupon_couriers'] = array( 'unknown-vendor' );
check( 10000.0 === $service->getAdjustedRatePricing( (object) array( 'courier' => 'jne' ), 10000 )['cost'], 'Unknown restriction must not become unrestricted' );
$GLOBALS['meta'] = array();
check( 10000.0 === $service->getAdjustedRatePricing( (object) array( 'courier' => 'unknown-vendor' ), 10000 )['cost'], 'Unknown vendor must not get discount' );
$wc->shipping->packages = array( 0 => array( 'rates' => array( $rate->get_id() => $rate ) ), 7 => array() );
$wc->session->set( 'chosen_shipping_methods', array( 'flat_rate:3' ) );
check( ! $service->validateCouponForCart( $coupon )['valid'], 'Explicit third party selection overrides availability' );
$wc->session->set( 'chosen_shipping_methods', array() );
check( $service->validateCouponForCart( $coupon )['valid'], 'Available rates fallback when no selection' );
$coupon->amount = 20000;
check( 0.0 === $service->getAdjustedRatePricing( (object) array( 'courier' => 'jne' ), 10000 )['cost'], 'Fixed discount capped at buyer base' );
$coupon->type = $service::PERCENTAGE_COUPON_TYPE;
$coupon->amount = 25;
check( 7500.0 === $service->getAdjustedRatePricing( (object) array( 'courier' => 'jne' ), 10000 )['cost'], 'Percentage discount' );
$wc->session->set( 'chosen_shipping_methods', array( $rate->get_id() ) );
$controller = ( new ReflectionClass( \KiriminAjaOfficial\Controllers\ShippingDiscountCouponController::class ) )->newInstanceWithoutConstructor();
$controller->register();
// WooCommerce registers cart totals at priority 20 before plugin callbacks.
array_unshift( $GLOBALS['hooks']['woocommerce_applied_coupon'][20], array( array( $wc->cart, 'calculate_totals' ), 0 ) );
add_action( 'woocommerce_removed_coupon', array( $wc->cart, 'calculate_totals' ), 20, 0 );
$coupon->type = $service::FIXED_COUPON_TYPE;
$coupon->amount = 4000;
$wc->cart->coupons = array();
$wc->shipping->packages = array( 0 => array( 'rates' => array( $rate->get_id() => $rate ) ) );
$wc->session->set( 'chosen_shipping_methods', array( 'flat_rate:3' ) );
check( ! $wc->cart->apply_coupon( $coupon ), 'Native apply must reject third-party choice even with available plugin rate' );
check( array() === $wc->cart->coupons && empty( $GLOBALS['notices']['success'] ), 'Rejected coupon cannot produce a native success notice' );
$wc->session->set( 'chosen_shipping_methods', array( $rate->get_id() ) );
$GLOBALS['meta']['_kiriof_coupon_couriers'] = array( 'gosend' );
check( ! $wc->cart->apply_coupon( $coupon ), 'Native apply must reject wrong courier before adding coupon' );
$GLOBALS['meta'] = array();
check( ! $wc->cart->apply_coupon( $coupon, false ), 'Do not override failed native validation (expiry, usage, spend, email)' );
$nativeCoupon = new WC_Coupon();
$nativeCoupon->type = 'percent';
check( false === apply_filters( 'woocommerce_coupon_is_valid', false, $nativeCoupon ), 'Native coupons preserve invalid status' );
foreach ( array( 0, -1 ) as $invalidAmount ) {
    $coupon->amount = $invalidAmount;
    check( ! $wc->cart->apply_coupon( $coupon ), 'Nonpositive custom shipping amount must be rejected' );
}
$coupon->amount = 4000;
foreach ( array( true, false, true, false ) as $applied ) {
    if ( $applied ) { check( $wc->cart->apply_coupon( $coupon ), 'Native apply succeeds' ); }
    else { $wc->cart->remove_coupon( $coupon->get_code() ); }
    // Assert after ALL callbacks, including applied-coupon postvalidation.
    $summary = ( new \KiriminAjaOfficial\Services\ShippingDiscountCouponService() )->getCurrentShippingDiscountSummary();
    check( ( $applied ? 4000.0 : 0.0 ) === (float) $summary['amount'], 'Fresh summary discount after apply/remove/reapply' );
    check( ( $applied ? 6000.0 : 10000.0 ) === $summary['current_cost'], 'Current native rate survives completed coupon hooks' );
    check( 10000.0 === $summary['original_cost'], 'Original rate base survives completed coupon hooks' );
    check( array( $rate->get_id() ) === $wc->session->get( 'chosen_shipping_methods' ), 'Native selection preserved after all callbacks' );
    check( ! empty( $wc->session->get( 'kiriof_shipping_coupon_rate_meta' ) ), 'Fresh metadata cannot be invalidated at priority 30' );
}
$wc->cart->coupons = array( $coupon );
$wc->shipping->packages = array();
$wc->session->set( 'kiriof_shipping_coupon_rate_meta', array( $rate->get_id() => array( 'discount_amount' => 4000 ) ) );
check( 0.0 === $service->getCurrentShippingDiscountTotal(), 'Missing current rate cannot use stale metadata' );
$coupon->free = true;
check( 0.0 === $service->getAdjustedRatePricing( $rate, 10000 )['cost'], 'Free shipping buyer reduction' );
check( 10000.0 === $service->getAdjustedRatePricing( new WC_Shipping_Rate( 'flat_rate:3', 10000 ), 10000 )['cost'], 'No third-party free discount' );
class CouponSettings {
    public function getCourierServiceSelection() { throw new RuntimeException( 'Coupon picker must not depend on merchant service selection' ); }
}
class_alias( CouponSettings::class, 'KiriminAjaOfficial\\Repositories\\SettingRepository' );
$optionsMethod = new ReflectionMethod( $controller, 'getCourierOptions' );
$options = $optionsMethod->invoke( $controller );
$ids = array_column( $options, 'id' );
check( in_array( 'jne', $ids, true ) && in_array( 'gosend', $ids, true ) && in_array( 'grab_express', $ids, true ), 'Local admin picker always includes Express and known Instant' );
$labels = array_column( $options, 'text', 'id' );
check( 'GoSend (instant)' === $labels['gosend'] && 'GrabExpress (instant)' === $labels['grab_express'], 'Instant restriction labels distinguish delivery type' );
echo "ok\n";
