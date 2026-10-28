<?php
namespace KiriminAjaOfficial\Services {
	class InstantCheckoutQuoteService {
		public array $calls = array();
		public function quote( $package, $destination, $payment, $insurance ) {
			$this->calls[] = $package['destination'];
			$contact = $package['destination'];
			$eligible = '' !== $contact['first_name'] && '' !== $contact['phone'];
			return array( 'eligible' => $eligible, 'code' => $eligible ? 'available' : 'recipient_invalid', 'rates' => $eligible ? array( array( 'courier' => 'gosend', 'service' => 'instant', 'label' => 'GoSend Instant', 'cost' => 55000, 'shipping_costs' => 54000, 'admin_fee' => 1000, 'total_price' => 55000, 'estimation' => '1 hour', 'vehicle' => 'motor', 'quote_token' => 'quote_token', 'expires' => time() + 120 ) ) : array() );
		}
	}
	class ShippingDiscountCouponService {
		public function getAdjustedRatePricing( $courier, $cost ) { return array( 'cost' => $cost, 'original_cost' => $cost ); }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	$input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
	if ( ! empty( $input['rest'] ) ) { define( 'REST_REQUEST', true ); }
	$GLOBALS['hook'] = $input['hook'] ?? 'woocommerce_checkout_update_order_review';
	$GLOBALS['actions'] = array();
	function current_filter() { return $GLOBALS['hook']; }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['actions'][ $hook ][] = array( $callback, $priority, $args ); }
	function add_filter( ...$args ) {}
	function sanitize_text_field( $value ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( $value ) ) ); }
	function __( $value, $domain ) { return $value; }
	function absint( $value ) { return abs( (int) $value ); }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
	function wc_price( $value ) { return (string) $value; }
	function WC() { return $GLOBALS['wc']; }
	class SyncCustomer {
		public array $data = array();
		public array $writes = array();
		public int $saves = 0;
		public function __call( $method, $args ) {
			$key = substr( $method, 4 );
			if ( str_starts_with( $method, 'get_' ) ) { return $this->data[ $key ] ?? ''; }
			$this->writes[] = $key;
			$this->data[ $key ] = $args[0];
		}
		public function save() { ++$this->saves; }
	}
	class SyncSession {
		public array $data = array( 'shipping_for_package_0' => 'old', 'shipping_for_package_4' => 'old', 'shipping_for_package_fake' => 'keep', 'chosen_shipping_methods' => array( 'flat_rate:1' ), 'chosen_payment_method' => 'bacs', 'pin_context' => 'unchanged' );
		public array $writes = array();
		public function get_session_data() { return $this->data; }
		public function get( $key, $default = null ) { return $this->data[ $key ] ?? $default; }
		public function set( $key, $value ) { $this->data[ $key ] = $value; $this->writes[] = $key; }
	}
	class WC_Shipping_Method {
		public $id, $instance_id, $method_title, $method_description, $supports, $instance_form_fields, $enabled, $title, $tax_status;
		public array $rates = array();
		public function init_settings() {}
		public function get_option( $key, $default ) { return $default; }
		public function add_rate( $rate ) { $this->rates[ $rate['id'] ] = $rate; }
	}
	$root = dirname( __DIR__, 2 );
	foreach ( array( 'BuyerDestination', 'InstantCheckoutRecipient', 'ClassicShippingRateCache', 'ClassicCheckoutRecipientSync' ) as $name ) { require $root . '/inc/Services/' . $name . '.php'; }
	$customer = new SyncCustomer();
	$customer->data = $input['initial'] ?? array();
	$session = new SyncSession();
	$GLOBALS['wc'] = (object) array( 'customer' => $customer, 'session' => $session, 'cart' => new class( $input['physical'] ?? true ) {
		public function __construct( private bool $physical ) {}
		public function needs_shipping() { return $this->physical; }
		public function get_shipping_packages() { return array( array() ); }
	} );
	$service = new \KiriminAjaOfficial\Services\ClassicCheckoutRecipientSync();
	$service->register();
	$steps = array();
	foreach ( $input['forms'] as $form ) {
		$customer->writes = array();
		$session->writes = array();
		$service->sync( is_array( $form ) ? http_build_query( $form ) : $form );
		$steps[] = array( 'customer' => $customer->data, 'writes' => $customer->writes, 'invalidations' => $session->writes, 'recipient' => \KiriminAjaOfficial\Services\InstantCheckoutRecipient::resolve( array(), $customer ) );
	}
	$address = array( 'address_1' => 'Jalan Example number 123', 'address_2' => '', 'city' => 'Jakarta', 'state' => 'JK', 'postcode' => '12345', 'country' => 'ID' );
	$session->data['kiriof_buyer_destination'] = array( 'district_id' => '123', 'district_label' => 'District', 'postcode' => '12345', 'country' => 'ID', 'address_type' => 'shipping', 'version' => 2, 'destination_latitude' => '-6.3', 'destination_longitude' => '106.9', 'shipping_address' => $address );
	require $root . '/wc/KiriminajaInstantShippingMethod.php';
	kiriof_instant_shipping_method();
	$quotes = new \KiriminAjaOfficial\Services\InstantCheckoutQuoteService();
	$method = new Kiriof_Instant_Shipping_Method_Controller( 11, $quotes );
	$method->calculate_shipping( array( 'contents' => array( array( 'quantity' => 1, 'data' => new class { public function needs_shipping() { return true; } } ) ), 'destination' => $address ) );
	echo json_encode( array( 'steps' => $steps, 'saves' => $customer->saves, 'session' => $session->data, 'calls' => $quotes->calls, 'rates' => array_keys( $method->rates ), 'hooks' => array_keys( $GLOBALS['actions'] ), 'registration' => $GLOBALS['actions']['woocommerce_checkout_update_order_review'][0][1] ) );
}
