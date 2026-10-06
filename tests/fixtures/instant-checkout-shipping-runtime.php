<?php
namespace KiriminAjaOfficial\Services {
	class InstantCheckoutQuoteService {
		public $calls = array();
		public $mode = 'ok';
		public $rows = null;
		public function __construct( $settings, $locations ) {}
		public function quote( $package, $destination, $payment, $insurance ) {
			$this->calls[] = compact( 'package', 'destination', 'payment', 'insurance' );
			if ( 'throw' === $this->mode ) {
				throw new \RuntimeException( 'Private API failure secret address' );
			}
			if ( in_array( $this->mode, array( 'outside_instant_radius', 'recipient_invalid', 'services_disabled', 'items_invalid', 'secret_reason' ), true ) ) {
                return array( 'eligible' => false, 'code' => $this->mode, 'message' => 'Private API message', 'rates' => array() );
            }
			$rate = array( 'courier' => 'gosend', 'service' => 'instant', 'label' => 'GoSend Instant', 'cost' => 55000, 'shipping_costs' => 54000, 'admin_fee' => 1000, 'total_price' => 55000, 'estimation' => '1-2 hours', 'vehicle' => 'motor', 'quote_token' => 'opaque_token', 'expires' => time() + 120 );
			$rates = array( $rate );
			foreach ( array( 'service' => 'bad:service', 'courier' => 'UPPER', 'expires' => time() - 10, 'quote_token' => array( 'private' ), 'cost' => -1, 'vehicle' => 'car' ) as $field => $value ) {
				$invalid = $rate;
				$invalid[ $field ] = $value;
				$rates[] = $invalid;
			}
			return array( 'eligible' => true, 'code' => 'available', 'message' => 'Private API message', 'rates' => $this->rows ?? $rates, 'context' => array( 'address' => 'secret address', 'phone' => 'secret phone' ) );
		}
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	$GLOBALS['logs'] = array();
	function kiriof_log( ...$args ) { $GLOBALS['logs'][] = $args; }
	$GLOBALS['actions'] = array();
	$GLOBALS['filters'] = array();
	function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['actions'][ $hook ][] = $callback; }
	function add_filter( $hook, $callback ) { $GLOBALS['filters'][ $hook ][] = $callback; }
	function __( $text, $domain ) { return $text; }
	function absint( $value ) { return abs( (int) $value ); }
	function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
	function wc_price( $value ) { return '<span>Rp' . number_format( $value, 0, ',', '.' ) . '</span>'; }
	function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function WC() { return $GLOBALS['wc']; }
	function get_woocommerce_currency() { return $GLOBALS['currency'] ?? 'IDR'; }
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
		public $rates = array(); public $tax_status; public $submitted_rates = array();
		public $instance_settings_initialized = false;
		public function init_instance_settings() { $this->instance_settings_initialized = true; }
		public function init_settings() {}
		public function get_option($key, $default) { return $GLOBALS["instance_options"][$this->instance_id][$key] ?? $default; }
		public function process_admin_options() {}
		public function is_available($package) { return true; }
		public function add_rate($rate) { $this->submitted_rates[] = $rate; $this->rates[$rate["id"]] = new WC_Shipping_Rate($rate["id"], $rate["label"], $rate["cost"], array(), $this->id, $this->instance_id); foreach ($rate["meta_data"] as $key => $value) { $this->rates[$rate["id"]]->add_meta_data($key, $value); } }
	}' );
	class WC_Shipping_Rate {
		private $id, $label, $cost, $method_id, $instance_id;
		private $metadata = array();
		private $delivery_time = '';
		public function __construct( $id, $label, $cost, $taxes, $method_id, $instance_id ) {
			$this->id = $id; $this->label = $label; $this->cost = $cost;
			$this->method_id = $method_id; $this->instance_id = $instance_id;
		}
		public function add_meta_data( $key, $value ) { $this->metadata[$key] = $value; }
		public function set_delivery_time( $value ) { $this->delivery_time = $value; }
		public function get_delivery_time() { return $this->delivery_time; }
		public function get_id() { return $this->id; }
		public function get_label() { return $this->label; }
		public function get_cost() { return $this->cost; }
		public function get_method_id() { return $this->method_id; }
		public function get_instance_id() { return $this->instance_id; }
		public function get_meta_data() { return $this->metadata; }
	}
	function fixture_rates( $method ) {
		return array_map( function ( $rate ) {
			return array( 'id' => $rate->get_id(), 'label' => $rate->get_label(), 'cost' => $rate->get_cost(), 'method_id' => $rate->get_method_id(), 'instance_id' => $rate->get_instance_id(), 'meta_data' => $rate->get_meta_data(), 'delivery_time' => $rate->get_delivery_time() );
		}, array_values( $method->rates ) );
	}
	kiriof_instant_shipping_method();
	kiriof_instant_shipping_method();
	$registered = kiriof_register_instant_shipping_method( $before );
	require dirname( __DIR__, 2 ) . '/inc/Services/BuyerDestination.php';
	$quotes = new \KiriminAjaOfficial\Services\InstantCheckoutQuoteService( new \stdClass(), new \stdClass() );
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
	$insurance_result = array( 'calls' => count( $quotes->calls ), 'status' => $session->get( 'kiriof_instant_checkout_status' ), 'rates' => fixture_rates( $one ), 'request' => end( $quotes->calls ) );
	$GLOBALS['insurance'] = 'no';
	$session->set( 'kiriof_insurance', 1 );
	$one->calculate_shipping( $package );
	$explicit = array( 'calls' => count( $quotes->calls ), 'rates' => fixture_rates( $one ), 'request' => end( $quotes->calls ) );
	$session->set( 'kiriof_insurance', 0 );
	$one->calculate_shipping( $package );
	$two->calculate_shipping( $package );
	$success_status = $session->get( 'kiriof_instant_checkout_status' );
	$quotes->mode = 'throw';
	$one->calculate_shipping( $package );
	$result = array( 'before' => $before, 'registered' => $registered, 'available' => $available, 'missing' => $missing, 'cod' => $cod, 'insurance' => $insurance_result, 'explicit_calls' => $explicit, 'rates_one' => fixture_rates( $one ), 'rates_two' => fixture_rates( $two ), 'calls' => $quotes->calls, 'success_status' => $success_status, 'failure_status' => $session->get( 'kiriof_instant_checkout_status' ), 'chosen' => $session->get( 'chosen_shipping_methods' ), 'setting' => $GLOBALS['insurance'], 'fields' => $one->instance_form_fields, 'instance_settings_initialized' => $one->instance_settings_initialized );
	// Registration must not construct a service that requires dependencies.
	$zone = new $registered['kiriminaja-instant']( 33 );
	$result['zone'] = array( 'id' => $zone->instance_id, 'enabled' => $zone->enabled );
	$GLOBALS['instance_options'][44]['enabled'] = 'no';
	$disabled = new Kiriof_Instant_Shipping_Method_Controller( 44, $quotes );
	$before_disabled = count( $quotes->calls );
	$disabled->calculate_shipping( $package );
	$result['disabled'] = array( 'available' => $disabled->is_available( $package ), 'calls' => count( $quotes->calls ) - $before_disabled, 'rates' => fixture_rates( $disabled ) );
	$quotes->mode = 'ok';
	$valid = array( 'courier' => 'gosend', 'service' => 'instant', 'label' => 'GoSend Instant', 'cost' => 55000, 'shipping_costs' => 54000, 'admin_fee' => 1000, 'total_price' => 55000, 'estimation' => '1-2 hours', 'vehicle' => 'motor', 'quote_token' => 'opaque_token', 'expires' => time() + 120 );
	$uppercase = $valid;
	$uppercase['service'] = 'GO-INSTANT';
	$quotes->rows = array( $valid, $uppercase );
	$case_method = new Kiriof_Instant_Shipping_Method_Controller( 55, $quotes );
	$case_method->calculate_shipping( $package );
	$result['uppercase_rates'] = fixture_rates( $case_method );
	$result['invalid'] = array();
	$mutations = array(
		'missing_admin' => array( 'admin_fee', null ),
		'missing_total' => array( 'total_price', null ),
		'total_mismatch' => array( 'total_price', 55001 ),
		'raw_mismatch' => array( 'shipping_costs', 53000 ),
		'fraction_shipping' => array( 'shipping_costs', 54000.5 ),
		'boolean_shipping' => array( 'shipping_costs', true ),
		'negative_shipping' => array( 'shipping_costs', -1 ),
		'fraction_admin' => array( 'admin_fee', 1000.5 ),
		'boolean_admin' => array( 'admin_fee', true ),
		'negative_admin' => array( 'admin_fee', -1000 ),
		'string_total' => array( 'total_price', '55000' ),
		'fraction_cost' => array( 'cost', 55000.5 ),
		'string_cost' => array( 'cost', '55000' ),
		'wrong_vehicle' => array( 'vehicle', 'car' ),
		'empty_estimation' => array( 'estimation', '' ),
		'array_estimation' => array( 'estimation', array( '1-2 hours' ) ),
		'expired' => array( 'expires', time() - 10 ),
		'invalid_token' => array( 'quote_token', array( 'private' ) ),
		'invalid_service' => array( 'service', 'bad:service' ),
		'invalid_courier' => array( 'courier', 'UPPER' ),
	);
	foreach ( $mutations as $name => $mutation ) {
		$row = $valid;
		if ( null === $mutation[1] ) { unset( $row[$mutation[0]] ); } else { $row[$mutation[0]] = $mutation[1]; }
		$quotes->rows = array( $row );
		$method = new Kiriof_Instant_Shipping_Method_Controller( 66, $quotes );
		$method->calculate_shipping( $package );
		$status = $session->get( 'kiriof_instant_checkout_status' );
		$result['invalid'][$name] = array( 'rates' => fixture_rates( $method ), 'status' => $status['66:' . hash( 'sha256', json_encode( array_keys( $package['contents'] ) ) )] );
	}
	$zero = $valid;
	$zero['cost'] = $zero['shipping_costs'] = $zero['admin_fee'] = $zero['total_price'] = 0;
	$zero['label'] = '<b>GoSend Instant</b>';
	$quotes->rows = array( $zero );
	$method = new Kiriof_Instant_Shipping_Method_Controller( 77, $quotes );
	$method->calculate_shipping( $package );
	$result['zero_rates'] = fixture_rates( $method );
	$result['money'] = array( 'tax_status' => $one->tax_status, 'taxes' => $one->submitted_rates[0]['taxes'] );
	$GLOBALS['wc']->cart = new class { public $packages = array(); public function get_shipping_packages() { return $this->packages; } };
	foreach ( array( 'multi', 'currency', 'virtual' ) as $scenario ) {
		$GLOBALS['wc']->cart->packages = 'multi' === $scenario ? array( $package, $package ) : array( $package );
		$GLOBALS['currency'] = 'currency' === $scenario ? 'USD' : 'IDR';
		$guard_package = 'virtual' === $scenario ? array() : $package;
		$guard = new Kiriof_Instant_Shipping_Method_Controller( 88, $quotes );
		$before_calls = count( $quotes->calls );
		$before_status = $session->get( 'kiriof_instant_checkout_status' );
		$guard->calculate_shipping( $guard_package );
		$result['guards'][ $scenario ] = array( 'available' => $guard->is_available( $guard_package ), 'calls' => count( $quotes->calls ) - $before_calls, 'rates' => fixture_rates( $guard ), 'status' => $session->get( 'kiriof_instant_checkout_status' ), 'status_unchanged' => $before_status === $session->get( 'kiriof_instant_checkout_status' ) );
	}
	$GLOBALS['currency'] = 'IDR';
	$GLOBALS['wc']->cart->packages = array( $package );
	$valid['expires'] = time() + 90;
	$zero['expires'] = time() + 60;
	$zero['service'] = 'sameday';
	$quotes->rows = array( $valid, $zero );
	$guard = new Kiriof_Instant_Shipping_Method_Controller( 99, $quotes );
	$guard->calculate_shipping( $package );
	$result['earliest'] = array( 'rates' => fixture_rates( $guard ), 'status' => $session->get( 'kiriof_instant_checkout_status' )['99:' . hash( 'sha256', json_encode( array_keys( $package['contents'] ) ) )] );
	foreach ( array( 'outside_instant_radius', 'recipient_invalid', 'services_disabled', 'items_invalid', 'secret_reason' ) as $reason ) {
		$quotes->mode = $reason;
		$guard = new Kiriof_Instant_Shipping_Method_Controller( 100, $quotes );
		$guard->calculate_shipping( $package );
		$result['reasons'][ $reason ] = $session->get( 'kiriof_instant_checkout_status' )['100:' . hash( 'sha256', json_encode( array_keys( $package['contents'] ) ) )];
	}
	$result['logs'] = $GLOBALS['logs'];
	echo json_encode( $result );

}
