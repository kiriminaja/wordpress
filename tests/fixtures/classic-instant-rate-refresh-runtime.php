<?php
namespace KiriminAjaOfficial\Services {
	function time() { return $GLOBALS['now']; }
}
namespace {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	require ABSPATH . 'vendor/autoload.php';
	$input = json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR );
	$GLOBALS['now'] = time();
	if ( ! empty( $input['rest'] ) ) { define( 'REST_REQUEST', true ); }
	$hooks = array();
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][ $priority ][] = $callback; }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function WC() { return $GLOBALS['wc']; }
	function current_filter() { return $GLOBALS['input']['hook'] ?? 'woocommerce_checkout_update_order_review'; }
	function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
	$address = array( 'address_1' => 'Jalan Nakulo 27 Sariharjo Ngaglik', 'address_2' => '', 'city' => 'Sleman', 'state' => 'YO', 'postcode' => '55581', 'country' => 'ID' );
	$pin = array( 'version' => 2, 'district_id' => '31554', 'district_label' => 'Sari Harjo', 'postcode' => '55581', 'country' => 'ID', 'address_type' => 'shipping', 'destination_latitude' => '-7.7400000', 'destination_longitude' => '110.3700000', 'shipping_address' => $address );
	$rate = new class( $input ) {
		public function __construct( private array $input ) {}
		public function get_method_id() { return 'kiriminaja-instant'; }
		public function get_meta_data() { return array( 'kiriof_instant_quote_expires' => $GLOBALS['now'] + ( ! empty( $this->input['expired'] ) ? -1 : 100 ), 'kiriof_instant_quote_token' => 'token' ); }
	};
	$statuses = array();
	if ( isset( $input['status'] ) ) {
		$statuses[ '11:' . hash( 'sha256', json_encode( array( empty( $input['unrelated_status'] ) ? 'item' : 'old-item' ) ) ) ] = array( 'code' => $input['status'], 'eligible' => false, 'updated' => $GLOBALS['now'] );
	}
	$session = new class( $pin, $rate, $input, $statuses ) {
		public array $data;
		public array $writes = array();
		public function __construct( $pin, $rate, $input, $statuses ) {
			$this->data = array(
				'kiriof_buyer_destination' => $pin,
				'kiriof_buyer_destination_coordinates' => array( 'latitude' => $pin['destination_latitude'], 'longitude' => $pin['destination_longitude'] ),
				'chosen_shipping_methods' => array( 'kiriminaja-official_jnt_EZ' ),
				'kiriof_instant_checkout_status' => $statuses,
				'kiriof_instant_checkout_quotes' => ! empty( $input['quotes_missing'] ) ? array() : array( array( 'expires' => $GLOBALS['now'] + 100, 'rates' => array( array( 'quote_token' => 'token' ) ) ) ),
				'shipping_for_package_0' => array( 'package_hash' => 'existing', 'rates' => ! empty( $input['instant'] ) ? array( $rate ) : array() ),
				'shipping_for_package_fake' => 'keep',
			);
		}
		public function get( $key, $default = null ) { return $this->data[ $key ] ?? $default; }
		public function set( $key, $value ) { $this->data[ $key ] = $value; $this->writes[] = $key; }
		public function get_session_data() { return $this->data; }
	};
	if ( ! empty( $input['no_cache'] ) ) { $session->data['shipping_for_package_0'] = false; }
	$package = array( 'contents' => array( 'item' => array( 'quantity' => 1 ) ), 'destination' => $address );
	$GLOBALS['wc'] = (object) array(
		'session' => $session,
		'cart' => new class( $package, $input ) {
			public function __construct( private array $package, private array $input ) {}
			public function needs_shipping() { return $this->input['physical'] ?? true; }
			public function get_shipping_packages() { return array( $this->package ); }
			public function get_cart_hash() { return 'cart-1'; }
		},
		'customer' => new class() {
			public function __call( $method, $args ) {
				return str_ends_with( $method, '_phone' ) ? '082243374043' : ( str_ends_with( $method, '_first_name' ) ? 'Yanuar' : '' );
			}
		},
	);
	$form = array( 'payment_method' => 'bacs', 'kiriof_buyer_destination_snapshot' => json_encode( $pin ) );
	foreach ( $address as $key => $value ) { $form[ 'billing_' . $key ] = $value; $form[ 'shipping_' . $key ] = $value; }
	if ( ! empty( $input['separate'] ) ) { $form['ship_to_different_address'] = '1'; }
	if ( ! empty( $input['mismatch'] ) ) { $form['billing_address_1'] = 'Different street address'; }
	if ( ! empty( $input['tamper'] ) ) { $changed_pin = $pin; $changed_pin['destination_latitude'] = '-7.7500000'; $form['kiriof_buyer_destination_snapshot'] = json_encode( $changed_pin ); }
	if ( ! empty( $input['clear'] ) ) { $form['kiriof_buyer_destination_snapshot'] = '{}'; }
	if ( ! empty( $input['cod'] ) ) { $form['payment_method'] = 'cod'; }
	$service = new \KiriminAjaOfficial\Services\ClassicInstantRateRefresh();
	$service->register();
	$before = $session->data;
	$steps = array();
	for ( $step = 0; $step < ( $input['repeat'] ?? 1 ); ++$step ) {
		$session->writes = array();
		$service->refresh( http_build_query( $form ) );
		$steps[] = array( 'invalidated' => in_array( 'shipping_for_package_0', $session->writes, true ), 'writes' => $session->writes );
		// Model WooCommerce storing the result of this refresh before another review.
		$session->data['shipping_for_package_0'] = $before['shipping_for_package_0'];
		$GLOBALS['now'] += $input['advance'] ?? 0;
	}
	echo json_encode( array( 'steps' => $steps, 'before' => $before, 'after' => $session->data, 'hooks' => array_keys( $hooks ) ), JSON_THROW_ON_ERROR );
}
