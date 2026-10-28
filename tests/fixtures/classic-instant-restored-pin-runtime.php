<?php
/** Production quote/rate services with an offline API; native cache lifecycle modeled explicitly. */
$recovery_input = json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR );
$argv[1] = '{}';
ob_start();
require __DIR__ . '/instant-checkout-quote-runtime.php';
ob_end_clean();
$root = dirname( __DIR__, 2 );
require_once $root . '/inc/Services/ClassicShippingRateCache.php';
require_once $root . '/inc/Services/ClassicInstantRateRefresh.php';
$api->calls = 0;
$api->payloads = array();
$GLOBALS['wc']->session = new QuoteSession();
$GLOBALS['wc']->cart = new class( $package ) {
	public function __construct( private array $package ) {}
	public function needs_shipping() { return true; }
	public function get_shipping_packages() { return array( $this->package ); }
	public function get_cart_hash() { return 'fixture-cart'; }
	public function get_coupons() { return array(); }
};
$GLOBALS['wc']->customer = new class( $package['destination'] ) {
	public function __construct( private array $address ) {}
	public function __call( $name, $args ) { return $this->address[ preg_replace( '/\Aget_(?:shipping|billing)_/', '', $name ) ] ?? ''; }
};
$session = WC()->session;
$session->set( 'kiriof_buyer_destination', $destination );
$session->set( 'chosen_payment_method', 'bacs' );
$session->set( 'chosen_shipping_methods', array( 'kiriminaja-official_jnt_EZ' ) );
$session->set( 'shipping_for_package_0', array( 'rates' => array( 'kiriminaja-official_jnt_EZ' => 'existing-express' ) ) );
$GLOBALS['refresh_hook'] = '';
$GLOBALS['refresh_hooks'] = array();
function current_filter() { return $GLOBALS['refresh_hook']; }
function add_action( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['refresh_hooks'][ $name ][ $priority ][] = $callback; }
function add_filter( ...$args ) {}
function absint( $value ) { return abs( (int) $value ); }
class WC_Shipping_Method {
	public $id, $tax_status, $instance_id, $method_title, $method_description, $supports, $instance_form_fields, $enabled, $title;
	public array $rates = array();
	public function init_settings() {}
	public function get_option( $key, $default ) { return $default; }
	public function add_rate( $rate ) { $this->rates[ $rate['id'] ] = new WC_Shipping_Rate( $rate ); }
}
class WC_Shipping_Rate {
	public function __construct( public array $rate ) {}
	public function get_method_id() { return 'kiriminaja-instant'; }
	public function get_meta_data() { return $this->rate['meta_data']; }
	public function get_id() { return $this->rate['id']; }
}
require_once $root . '/inc/Services/CourierServiceCatalog.php';
require_once $root . '/inc/Services/ShippingDiscountCouponService.php';
function get_option( $key, $default = null ) { return $default; }
function get_post_meta( $id, $key, $single = true ) { return ''; }
function metadata_exists( ...$args ) { return false; }
function wc_get_price_decimals() { return 0; }
function wc_format_decimal( $value, $dp = false ) { return (string) round( $value ); }
require $root . '/wc/KiriminajaInstantShippingMethod.php';
kiriof_instant_shipping_method();
$quote_service = new \KiriminAjaOfficial\Services\InstantCheckoutQuoteService( $settings, $locations, $api );
$method = new Kiriof_Instant_Shipping_Method_Controller( 11, $quote_service );
$form = array( 'payment_method' => 'bacs', 'kiriof_buyer_destination_snapshot' => json_encode( $destination ) );
foreach ( $address as $key => $value ) { $form[ 'billing_' . $key ] = $value; }
if ( empty( $recovery_input['without_recovery'] ) ) {
	( new \KiriminAjaOfficial\Services\ClassicInstantRateRefresh() )->register();
}
$rounds = array();
foreach ( range( 1, 2 ) as $round ) {
	$GLOBALS['refresh_hook'] = 'woocommerce_checkout_update_order_review';
	$callbacks = $GLOBALS['refresh_hooks'][ $GLOBALS['refresh_hook'] ] ?? array();
	ksort( $callbacks );
	foreach ( $callbacks as $list ) { foreach ( $list as $callback ) { $callback( http_build_query( $form ) ); } }
	// Like WC_Shipping, an existing package cache bypasses calculate_shipping().
	if ( ! $session->get( 'shipping_for_package_0', false ) ) {
		$method->rates = array();
		$method->calculate_shipping( $package );
		$session->set( 'shipping_for_package_0', array( 'rates' => array( 'kiriminaja-official_jnt_EZ' => 'existing-express' ) + $method->rates ) );
	}
	$rounds[] = array( 'rates' => array_keys( $session->get( 'shipping_for_package_0' )['rates'] ), 'calls' => $api->calls );
}
echo json_encode( array( 'rounds' => $rounds, 'pin' => $session->get( 'kiriof_buyer_destination' ), 'original_pin' => $destination, 'chosen' => $session->get( 'chosen_shipping_methods' ), 'payloads' => $api->payloads ), JSON_THROW_ON_ERROR );
