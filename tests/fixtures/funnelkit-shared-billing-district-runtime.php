<?php
/** Offline WooCommerce field collection and required validation, before any order creation. */
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require ABSPATH . 'vendor/autoload.php';
require __DIR__ . '/woocommerce-checkout-district-methods.php';
$input = json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR );
$hooks = array();
$hook = '';
$calls = array();
function add_action( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $name ][ $priority ][] = $callback; }
function add_filter( ...$args ) { add_action( ...$args ); }
function current_filter() { return $GLOBALS['hook']; }
function apply_filters( $name, $value, ...$args ) {
	$previous = $GLOBALS['hook'];
	$GLOBALS['hook'] = $name;
	$callbacks = $GLOBALS['hooks'][ $name ] ?? array();
	ksort( $callbacks );
	foreach ( $callbacks as $group ) { foreach ( $group as $callback ) { $value = $callback( $value, ...$args ); } }
	$GLOBALS['hook'] = $previous;
	return $value;
}
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function sanitize_title( $value ) { return $value; }
function wc_clean( $value ) { return is_array( $value ) ? array_map( 'wc_clean', $value ) : sanitize_text_field( $value ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value ); }
function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value ); }
function wc_ship_to_billing_address_only() { return false; }
function __( $value, $domain = '' ) { return $value; }
function _x( $value, ...$args ) { return $value; }
function esc_html( $value ) { return $value; }
function WC() { return $GLOBALS['wc']; }
class WP_Error {
	public array $errors = array();
	public function add( $key, $message, $data = null ) { $this->errors[ $key ][] = $message; }
}
$session = new class() {
	public array $data = array( 'kiriof_buyer_destination' => null, 'shipping_for_package_0' => array( 'rates' => 'unchanged' ), 'pin_context' => array( 'latitude' => '-6.3', 'longitude' => '106.9' ), 'destination_id' => '999' );
	public function get( $key, $default = null ) { return $this->data[ $key ] ?? $default; }
};
$wc = (object) array( 'session' => $session, 'cart' => new class() {
	public function get_cart() { return array( array( 'quantity' => 1, 'data' => new class() { public function needs_shipping() { return empty( $GLOBALS['input']['virtual'] ); } } ) ); }
	public function needs_shipping_address() { return true; }
}, 'countries' => new class() { public function get_states( $country ) { return array( 'YO' => 'DI Yogyakarta' ); } } );
$before_session = $session->data;
$sync = new \KiriminAjaOfficial\Services\ClassicDistrictAddressSync( static function ( $postcode ) use ( &$calls, $input ) {
	$calls[] = $postcode;
	if ( ! empty( $input['throws'] ) ) { throw new RuntimeException( 'Offline failure' ); }
	return (object) array( 'status' => $input['status'] ?? 200, 'data' => $input['rows'] ?? array( array( 'id' => 31554, 'text' => "Sari Harjo, Ngaglik, O'City, DI Yogyakarta, 55581", 'zip_code' => '55581' ) ) );
} );
$sync->register();
$form = array_replace( array( '_wfacp_post_id' => '123', 'wfacp_billing_same_as_shipping' => '0', 'ship_to_different_address' => 'on', 'billing_country' => '', 'billing_postcode' => '', 'shipping_country' => 'ID', 'shipping_postcode' => '55581', 'shipping_city' => "O'City", 'shipping_state' => 'YO', 'shipping_address_1' => 'Jalan Example 123', 'kiriof_shipping_destination_area' => '31554', 'kiriof_shipping_destination_area_name' => 'Untrusted, Client, City, Province, 55581', 'kiriof_destination_area' => '', 'kiriof_destination_area_name' => '', 'shipping_method' => array( 'kiriminaja-official_jnt_EZ' ) ), $input['override'] ?? array() );
foreach ( $input['omit'] ?? array() as $key ) { unset( $form[ $key ] ); }
$_POST = wp_slash( $form );
$_POST['woocommerce-process-checkout-nonce'] = 'verified-native-checkout';
// Models WC_Checkout::process_checkout's verified nonce boundary without creating an order.
if ( empty( $input['skip_process'] ) ) {
	$hook = 'woocommerce_checkout_process';
	$actions = $hooks[ $hook ];
	ksort( $actions );
	foreach ( $actions as $callbacks ) { foreach ( $callbacks as $callback ) { $callback(); } }
}
$pre_collection = wp_unslash( $_POST );
$sanitized_billing = null;
add_filter( 'woocommerce_process_checkout_field_kiriof_destination_area', static function ( $value ) use ( &$sanitized_billing ) { $sanitized_billing = $value; return $value; } );
// FunnelKit's earlier posted-data filters may replace hidden billing fields.
add_filter( 'woocommerce_checkout_posted_data', static function ( $data ) use ( $input ) {
	if ( ! empty( $input['builder_clears'] ) ) { $data['kiriof_destination_area'] = ''; $data['kiriof_destination_area_name'] = ''; }
	return $data;
}, 999 );
$checkout = new class() {
	use WooCommerceDistrictCheckoutMethods;
	public array $legacy_posted_data = array();
	public function is_registration_enabled() { return false; }
	public function maybe_skip_fieldset( $key, $data ) { return false; }
	public function get_checkout_fields( $scope = '' ) {
		$fields = array( 'billing' => array(), 'shipping' => array() );
		foreach ( array( 'billing', 'shipping' ) as $group ) {
			$key = 'billing' === $group ? 'kiriof_destination_area' : 'kiriof_shipping_destination_area';
			$fields[ $group ][ $key ] = array( 'type' => 'text', 'label' => 'Subdistrict', 'required' => true );
			$fields[ $group ][ $key . '_name' ] = array( 'type' => 'hidden' );
			foreach ( array( 'country', 'city', 'state', 'postcode', 'address_1' ) as $name ) { $fields[ $group ][ $group . '_' . $name ] = array( 'type' => 'text' ); }
		}
		return '' === $scope ? $fields : $fields[ $scope ];
	}
	public function validate( &$data ) { $errors = new WP_Error(); $this->validate_posted_data( $data, $errors ); return $errors->errors; }
};
$data = $checkout->get_posted_data();
$errors = $checkout->validate( $data );
// Exercise the production order destination accessor without invoking create_order/payment.
$controller = ( new ReflectionClass( \KiriminAjaOfficial\Controllers\CheckoutController::class ) )->newInstanceWithoutConstructor();
$accessor = new ReflectionMethod( $controller, 'kiriof_get_posted_text_field' );
$districts = array();
foreach ( array( 'kiriof_destination_area', 'kiriof_destination_area_name', 'kiriof_shipping_destination_area', 'kiriof_shipping_destination_area_name' ) as $key ) { $districts[ $key ] = $accessor->invoke( $controller, $key ); }
$destination = null;
if ( '31554' === $districts['kiriof_destination_area'] && empty( $errors ) ) {
	$order = new class( $data ) {
		public function __construct( private array $data ) {}
		public function __call( $method, $args ) { return $this->data[ substr( $method, 4 ) ] ?? ''; }
	};
	$destination = ( new ReflectionMethod( $controller, 'kiriof_classic_buyer_destination' ) )->invoke( $controller, $order );
}
echo json_encode( array( 'data' => $data, 'pre_collection' => $pre_collection, 'sanitized_billing' => $sanitized_billing, 'errors' => $errors, 'order_accessor' => $districts, 'destination' => $destination, 'calls' => $calls, 'session_before' => $before_session, 'session_after' => $session->data ), JSON_THROW_ON_ERROR );
