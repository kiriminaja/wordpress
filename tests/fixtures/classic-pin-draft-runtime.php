<?php

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require ABSPATH . 'vendor/autoload.php';
$input = json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR );
if ( ! empty( $input['rest'] ) ) { define( 'REST_REQUEST', true ); }
$hook = '';
$hooks = array();
$calls = array();
function add_action( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $name ] = $priority; }
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { add_action( $name, $callback, $priority, $args ); }
function current_filter() { return $GLOBALS['hook']; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value ); }
function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wc_ship_to_billing_address_only() { return ! empty( $GLOBALS['input']['billing_only'] ); }
function WC() { return $GLOBALS['wc']; }
$wc = (object) array(
	'session' => new class( $input['session'] ?? array() ) {
		public array $data;
		public array $writes = array();
		public function __construct( array $data ) { $this->data = $data; }
		public function get( $key, $default = null ) { return $this->data[ $key ] ?? $default; }
		public function set( $key, $value ) { $this->data[ $key ] = $value; $this->writes[] = $key; }
		public function get_session_data() { return $this->data; }
	},
	'cart' => new class() {
		public function needs_shipping() { return empty( $GLOBALS['input']['virtual'] ); }
		public function get_shipping_packages() { return array( array() ); }
	},
	'countries' => new class() { public function get_states( $country ) { return array( 'YO' => 'Daerah Istimewa Yogyakarta' ); } },
);
$lookup = static function ( $postcode ) use ( &$calls, $input ) {
	$calls[] = $postcode;
	if ( ! empty( $input['throws'] ) ) { throw new RuntimeException( 'private upstream message' ); }
	return (object) array( 'status' => $input['status'] ?? 200, 'data' => $input['rows_by_postcode'][ $postcode ] ?? $input['rows'] ?? array( array( 'id' => 31554, 'text' => 'Sari Harjo, Ngaglik, Sleman, DI Yogyakarta, 55581' ) ) );
};
$sync = new \KiriminAjaOfficial\Services\ClassicDistrictAddressSync( $lookup );
$service = new \KiriminAjaOfficial\Services\ClassicPinDraftService( $sync, $lookup );
$service->register();
$errors = array();
foreach ( $input['saves'] ?? array() as $save ) {
	try { $service->save( $save ); } catch ( Throwable $error ) { $errors[] = $error->getCode(); }
}
$posted = $input['form'] ?? array();
$_POST = wp_slash( $posted );
if ( isset( $input['form'] ) ) {
	if ( 'final' === ( $input['mode'] ?? '' ) ) {
		$hook = $input['hook'] ?? 'woocommerce_checkout_process';
		if ( empty( $input['skip_process'] ) ) { $sync->process(); $service->process(); }
		$hook = 'woocommerce_checkout_posted_data';
		$posted = $sync->posted( $input['posted'] ?? $posted );
		$posted = $service->posted( $posted );
	} else {
		$hook = $input['hook'] ?? 'woocommerce_checkout_update_order_review';
		$_POST['post_data'] = wp_slash( http_build_query( $posted ) );
		$service->review( http_build_query( $posted ) );
	}
}
$reviews = array();
foreach ( $input['reviews'] ?? array() as $form ) {
	$hook = 'woocommerce_checkout_update_order_review';
	$_POST = array( 'post_data' => wp_slash( http_build_query( $form ) ) );
	$service->review( http_build_query( $form ) );
	$reviews[] = $wc->session->data;
}
$before = count( $wc->session->writes );
if ( ! empty( $input['repeat'] ) ) { $service->review( http_build_query( $input['form'] ) ); }
echo json_encode( array( 'session' => $wc->session->data, 'writes' => $wc->session->writes, 'repeat_writes' => array_slice( $wc->session->writes, $before ), 'errors' => $errors, 'calls' => $calls, 'post' => wp_unslash( $_POST ), 'posted' => $posted, 'hooks' => $hooks, 'reviews' => $reviews ), JSON_THROW_ON_ERROR );
