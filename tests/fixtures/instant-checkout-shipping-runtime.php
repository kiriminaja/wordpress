<?php
namespace KiriminAjaOfficial\Services {
	class InstantCheckoutQuoteService {
		public $calls = array();
		public $mode = 'ok';
		public function quote( $package, $destination, $payment, $insurance ) {
			$this->calls[] = compact( 'package', 'destination', 'payment', 'insurance' );
			if ( 'throw' === $this->mode ) {
				throw new \RuntimeException( 'Private API failure secret address' );
			}
			$rate = array( 'courier' => 'gosend', 'service' => 'instant', 'label' => 'GoSend Instant', 'cost' => 12345, 'vehicle' => 'motor', 'quote_token' => 'opaque_token', 'expires' => time() + 300 );
			$rates = array( $rate );
			foreach ( array( 'service' => 'bad:service', 'courier' => 'UPPER', 'expires' => time() - 10, 'quote_token' => array( 'private' ), 'cost' => -1, 'vehicle' => 'car' ) as $field => $value ) {
				$invalid = $rate;
				$invalid[ $field ] = $value;
				$rates[] = $invalid;
			}
			return array( 'eligible' => true, 'code' => 'available', 'message' => 'Private API message', 'rates' => $rates, 'context' => array( 'address' => 'secret address', 'phone' => 'secret phone' ) );
		}
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	$GLOBALS['actions'] = array();
	$GLOBALS['filters'] = array();
	function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['actions'][ $hook ][] = $callback; }
	function add_filter( $hook, $callback ) { $GLOBALS['filters'][ $hook ][] = $callback; }
	function __( $text, $domain ) { return $text; }
	function absint( $value ) { return abs( (int) $value ); }
	function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function WC() { return $GLOBALS['wc']; }
	function kiriof_setting_repository() { return new class { public function getSettingByKey( $key ) { return (object) array( 'value' => $GLOBALS['insurance'] ); } }; }
	class FixtureSession {
		public $data = array();
		public function get( $key, $fallback = null ) { return $this->data[ $key ] ?? $fallback; }
		public function set( $key, $value ) { $this->data[ $key ] = $value; }
	}
	$session = new FixtureSession();
	$GLOBALS['insurance'] = 'no';
	$GLOBALS['wc'] = (object) array( 'session' => $session, 'customer' => new class {
		public function get_shipping_first_name() { return 'Customer'; }
		public function get_shipping_last_name() { return 'Name'; }
		public function get_shipping_phone() { return '081234'; }
		public function get_shipping_city() { return 'Customer city'; }
	} );
	require dirname( __DIR__, 2 ) . '/wc/KiriminajaInstantShippingMethod.php';
	$before = kiriof_register_instant_shipping_method( array( 'existing' => 'Existing' ) );
	// Runtime declaration ensures the first registration really runs without WooCommerce.
	eval( 'class WC_Shipping_Method {
		public $id, $instance_id, $method_title, $method_description, $supports, $instance_form_fields, $enabled, $title;
		public $rates = array();
		public function init_settings() {}
		public function get_option($key, $default) { return $default; }
		public function process_admin_options() {}
		public function is_available($package) { return true; }
		public function add_rate($rate) { $this->rates[] = $rate; }
	}' );
	kiriof_instant_shipping_method();
	kiriof_instant_shipping_method();
	$registered = kiriof_register_instant_shipping_method( $before );
	require dirname( __DIR__, 2 ) . '/inc/Services/BuyerDestination.php';
	$quotes = new \KiriminAjaOfficial\Services\InstantCheckoutQuoteService();
	$one = new Kiriof_Instant_Shipping_Method_Controller( 11, $quotes );
	$two = new Kiriof_Instant_Shipping_Method_Controller( 22, $quotes );
	$package = array( 'contents' => array( 'item' => array( 'quantity' => 1, 'data' => new class { public function needs_shipping() { return true; } } ) ), 'destination' => array( 'first_name' => 'Actual package', 'city' => 'Actual city' ), 'origin' => array( 'origin_latitude' => '-6.2', 'origin_longitude' => '106.8' ) );
	$session->set( 'chosen_shipping_methods', array( 'existing:3' ) );
	$available = array( $one->is_available( $package ), $one->is_available( array() ), count( $quotes->calls ) );
	$one->calculate_shipping( $package );
	$missing = array( 'calls' => count( $quotes->calls ), 'status' => $session->get( 'kiriof_instant_checkout_status' ) );
	$address = array( 'address_1' => 'Secret street', 'address_2' => '', 'city' => 'Actual city', 'state' => 'JK', 'postcode' => '12345', 'country' => 'ID' );
	$session->set( 'kiriof_buyer_destination', array( 'district_id' => '123', 'district_label' => 'District', 'postcode' => '12345', 'country' => 'ID', 'address_type' => 'shipping', 'version' => 2, 'destination_latitude' => '-6.3', 'destination_longitude' => '106.9', 'shipping_address' => $address ) );
	$session->set( 'chosen_payment_method', 'cod' );
	$one->calculate_shipping( $package );
	$cod = array( 'calls' => count( $quotes->calls ), 'status' => $session->get( 'kiriof_instant_checkout_status' ) );
	$session->set( 'chosen_payment_method', '' );
	$session->set( 'payment_method', 'bacs' );
	$GLOBALS['insurance'] = 'yes';
	$one->calculate_shipping( $package );
	$insurance_result = array( 'calls' => count( $quotes->calls ), 'status' => $session->get( 'kiriof_instant_checkout_status' ) );
	$GLOBALS['insurance'] = 'no';
	$session->set( 'kiriof_insurance', 1 );
	$one->calculate_shipping( $package );
	$explicit = count( $quotes->calls );
	$session->set( 'kiriof_insurance', 0 );
	$one->calculate_shipping( $package );
	$two->calculate_shipping( $package );
	$success_status = $session->get( 'kiriof_instant_checkout_status' );
	$quotes->mode = 'throw';
	$one->calculate_shipping( $package );
	echo json_encode( array( 'before' => $before, 'registered' => $registered, 'available' => $available, 'missing' => $missing, 'cod' => $cod, 'insurance' => $insurance_result, 'explicit_calls' => $explicit, 'rates_one' => $one->rates, 'rates_two' => $two->rates, 'calls' => $quotes->calls, 'success_status' => $success_status, 'failure_status' => $session->get( 'kiriof_instant_checkout_status' ), 'chosen' => $session->get( 'chosen_shipping_methods' ), 'setting' => $GLOBALS['insurance'], 'fields' => $one->instance_form_fields ) );
}
