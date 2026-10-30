<?php
/** Production quote/rate services with an offline API; native cache lifecycle modeled explicitly. */
$recovery_input = json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR );
$argv[1] = '{}';
ob_start();
require __DIR__ . '/instant-checkout-quote-runtime.php';
ob_end_clean();
$root = dirname( __DIR__, 2 );
require_once $root . '/inc/Services/ClassicShippingRateCache.php';
require_once $root . '/inc/Services/ClassicDistrictAddressSync.php';
require_once $root . '/inc/Services/ClassicPinDraftService.php';
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
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['refresh_filters'][ $name ][ $priority ][] = $callback; }
function absint( $value ) { return abs( (int) $value ); }
function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value ); }
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
if ( isset( $recovery_input['funnelkit'] ) ) {
	require_once $root . '/inc/Services/ClassicCheckoutRecipientSync.php';
	$GLOBALS['wc']->customer = new class() {
		public array $data = array();
		public function __call( $name, $args ) {
			if ( str_starts_with( $name, 'set_' ) ) { $this->data[ substr( $name, 4 ) ] = $args[0]; return; }
			return $this->data[ substr( $name, 4 ) ] ?? '';
		}
	};
	$GLOBALS['wc']->countries = new class() { public function get_states( $country ) { return array( 'JK' => 'DKI Jakarta' ); } };
	$session->set( 'kiriof_buyer_destination', null );
	$session->set( 'chosen_payment_method', 'bacs' );
	$lookup = static fn( $postcode ) => (object) array( 'status' => 200, 'data' => array( array( 'id' => 42, 'text' => 'Village, District, Jakarta, DKI Jakarta, 12345', 'zip_code' => '12345' ) ) );
	$sync = new \KiriminAjaOfficial\Services\ClassicDistrictAddressSync( $lookup );
	$drafts = new \KiriminAjaOfficial\Services\ClassicPinDraftService( $sync, $lookup );
	$drafts->save( array( 'address_scope' => 'shipping', 'country' => 'ID', 'coordinates' => array( 'latitude' => '-6.3', 'longitude' => '106.9' ) ) );
	$form = array( '_wfacp_post_id' => '123', 'wfacp_billing_same_as_shipping' => '0', 'payment_method' => 'bacs', 'kiriof_shipping_destination_area' => '42', 'kiriof_shipping_destination_area_name' => 'Village, District, Jakarta, DKI Jakarta, 12345', 'shipping_first_name' => 'Shipping', 'shipping_last_name' => 'Recipient', 'billing_phone' => '081234567890' );
	foreach ( $address as $key => $value ) { $form[ 'shipping_' . $key ] = $value; }
	if ( ! empty( $recovery_input['different_billing'] ) ) {
		$form['wfacp_billing_same_as_shipping'] = '1';
		$form['billing_same_as_shipping'] = '1';
		foreach ( $address as $key => $value ) { $form[ 'billing_' . $key ] = $value; }
		$form['billing_address_1'] = 'Different billing street';
		$form['billing_first_name'] = 'Billing';
	}
	if ( ! empty( $recovery_input['native_checkbox'] ) ) { $form['ship_to_different_address'] = 'on'; }
	if ( ! empty( $recovery_input['hidden'] ) ) {
		$form['kiriof_shipping_derived_address'] = 'city,state,postcode';
		foreach ( array( 'city', 'state', 'postcode' ) as $field ) { $form[ 'shipping_' . $field ] = ''; }
	}
	foreach ( $recovery_input['override'] ?? array() as $key => $value ) { $form[ $key ] = $value; }
	foreach ( $recovery_input['omit'] ?? array() as $key ) { unset( $form[ $key ] ); }
	$sync->register();
	$drafts->register();
	( new \KiriminAjaOfficial\Services\ClassicCheckoutRecipientSync() )->register();
	$GLOBALS['refresh_hook'] = 'woocommerce_checkout_update_order_review';
	$_POST = array( 'post_data' => wp_slash( http_build_query( $form ) ) );
	// WC's review JS sends root aliases separately from the serialized form.
	foreach ( \KiriminAjaOfficial\Services\BuyerDestination::ADDRESS_FIELDS as $field ) {
		$alias = 'address_1' === $field ? 'address' : $field;
		$_POST[ $alias ] = wp_slash( $form[ 'billing_' . $field ] ?? '' );
		$_POST[ 's_' . $alias ] = wp_slash( $form[ 'billing_' . $field ] ?? '' );
	}
	$callbacks = $GLOBALS['refresh_hooks'][ $GLOBALS['refresh_hook'] ];
	ksort( $callbacks );
	foreach ( $callbacks as $list ) { foreach ( $list as $callback ) { $callback( http_build_query( $form ) ); } }
	// WC_AJAX::update_order_review runs these native setters AFTER the hook.
	foreach ( \KiriminAjaOfficial\Services\BuyerDestination::ADDRESS_FIELDS as $field ) {
		$alias = 'address_1' === $field ? 'address' : $field;
		$billing_setter = 'set_billing_' . $field;
		$shipping_setter = 'set_shipping_' . $field;
		WC()->customer->$billing_setter( wp_unslash( $_POST[ $alias ] ?? '' ) );
		WC()->customer->$shipping_setter( wp_unslash( $_POST[ 's_' . $alias ] ?? '' ) );
		$package['destination'][ $field ] = WC()->customer->{'get_shipping_' . $field}();
	}
	foreach ( \KiriminAjaOfficial\Services\InstantCheckoutRecipient::FIELDS as $field ) { unset( $package['destination'][ $field ] ); }
	$api->scenario = $recovery_input['courier'] ?? '';
	$method->rates = array();
	$method->calculate_shipping( $package );
	$final = null;
	if ( ! empty( $recovery_input['final'] ) ) {
		$_POST = wp_slash( $form );
		$GLOBALS['refresh_hook'] = 'woocommerce_checkout_process';
		$callbacks = $GLOBALS['refresh_hooks'][ $GLOBALS['refresh_hook'] ];
		ksort( $callbacks );
		foreach ( $callbacks as $list ) { foreach ( $list as $callback ) { $callback(); } }
		$GLOBALS['refresh_hook'] = 'woocommerce_checkout_posted_data';
		$final = $form;
		foreach ( $recovery_input['final_omit'] ?? array() as $key ) { unset( $final[ $key ] ); }
		foreach ( $recovery_input['final_override'] ?? array() as $key => $value ) { $final[ $key ] = $value; }
		$callbacks = $GLOBALS['refresh_filters'][ $GLOBALS['refresh_hook'] ];
		ksort( $callbacks );
		foreach ( $callbacks as $list ) { foreach ( $list as $callback ) { $final = $callback( $final ); } }
	}
	echo json_encode( array( 'rates' => array_keys( $method->rates ), 'calls' => $api->calls, 'destination' => $session->get( 'kiriof_buyer_destination' ), 'package' => $package['destination'], 'customer' => WC()->customer->data, 'post' => wp_unslash( $_POST ), 'payloads' => $api->payloads, 'logs' => $GLOBALS['logs'], 'final' => $final ), JSON_THROW_ON_ERROR );
	exit;
}
if ( ! empty( $recovery_input['draft_to_rate'] ) ) {
	$session->set( 'kiriof_buyer_destination', null );
	$session->set( 'kiriof_buyer_destination_coordinates', null );
	$session->set( 'kiriof_destination', '999' );
	$session->set( 'kiriof_destination_area', '42' );
	$lookup_calls = array();
	$lookup = static function ( $postcode ) use ( &$lookup_calls ) {
		$lookup_calls[] = $postcode;
		$changed = '12346' === $postcode;
		return (object) array( 'status' => 200, 'data' => array( array( 'id' => $changed ? 43 : 42, 'text' => 'Village, District, Jakarta, DKI Jakarta, ' . $postcode, 'zip_code' => $postcode ) ) );
	};
	$drafts = new \KiriminAjaOfficial\Services\ClassicPinDraftService( new \KiriminAjaOfficial\Services\ClassicDistrictAddressSync( $lookup ), $lookup );
	$drafts->save( array( 'address_scope' => 'billing', 'country' => 'ID', 'coordinates' => array( 'latitude' => '-6.3', 'longitude' => '106.9' ) ) );
	$method->calculate_shipping( $package );
	$before_review = array( 'destination' => $session->get( 'kiriof_buyer_destination' ), 'rates' => $method->rates, 'calls' => $api->calls );
	$rounds = array();
	foreach ( array( '42' => '12345', '43' => '12346' ) as $id => $postcode ) {
		$form = array( 'payment_method' => 'bacs', 'kiriof_destination_area' => (string) $id, 'kiriof_destination_area_name' => 'Untrusted client label' );
		$package['destination']['postcode'] = $postcode;
		foreach ( $package['destination'] as $key => $value ) { $form[ 'billing_' . $key ] = $value; }
		$GLOBALS['refresh_hook'] = 'woocommerce_checkout_update_order_review';
		$_POST = array( 'post_data' => wp_slash( http_build_query( $form ) ) );
		$drafts->review( http_build_query( $form ) );
		parse_str( wp_unslash( $_POST['post_data'] ), $review );
		$method->rates = array();
		$method->calculate_shipping( $package );
		$rounds[] = array(
			'destination' => $session->get( 'kiriof_buyer_destination' ),
			'snapshot' => json_decode( $review['kiriof_buyer_destination_snapshot'] ?? 'null', true ),
			'coordinates' => $session->get( 'kiriof_buyer_destination_coordinates' ),
			'rates' => array_map( static fn( $rate ) => $rate->rate, array_values( $method->rates ) ),
			'calls' => $api->calls,
			'cache' => $session->get( 'shipping_for_package_0' ),
		);
	}
	echo json_encode( array( 'before_review' => $before_review, 'rounds' => $rounds, 'lookup_calls' => $lookup_calls, 'legacy_district' => $session->get( 'kiriof_destination' ), 'payloads' => $api->payloads, 'logs' => $GLOBALS['logs'] ), JSON_THROW_ON_ERROR );
	exit;
}
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
