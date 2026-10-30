<?php

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require ABSPATH . 'vendor/autoload.php';

$input = json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR );
if ( ! empty( $input['rest'] ) ) { define( 'REST_REQUEST', true ); }
$hooks = array();
$hook = '';
$calls = array();
function add_action( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $name ] = $priority; }
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { add_action( $name, $callback, $priority, $args ); }
function current_filter() { return $GLOBALS['hook']; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value ); }
function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value ); }
function wc_ship_to_billing_address_only() { return ! empty( $GLOBALS['input']['billing_only'] ); }
function WC() { return $GLOBALS['wc']; }
$wc = (object) array( 'countries' => new class() {
	public function get_states( $country ) { return $GLOBALS['input']['states'] ?? array( 'YO' => 'DI Yogyakarta', 'JK' => 'DKI Jakarta' ); }
} );
$service = new \KiriminAjaOfficial\Services\ClassicDistrictAddressSync(
	static function ( $postcode ) use ( &$calls, $input ) {
		$calls[] = $postcode;
		if ( ! empty( $input['throws'] ) ) { throw new RuntimeException( 'Unavailable' ); }
		return (object) array( 'status' => $input['status'] ?? 200, 'data' => $input['rows'] ?? array( array( 'id' => 31554, 'text' => 'Sari Harjo, Ngaglik, Sleman, DI Yogyakarta, 55581' ) ) );
	}
);
$service->register();
$form = $input['form'] ?? array();
$_POST = wp_slash( $input['post'] ?? $form );
$result = array();
if ( 'review' === ( $input['mode'] ?? '' ) ) {
	$hook = $input['hook'] ?? 'woocommerce_checkout_update_order_review';
	$_POST['post_data'] = wp_slash( http_build_query( $form ) );
	$service->sync( http_build_query( $form ) );
	parse_str( wp_unslash( $_POST['post_data'] ), $result );
} elseif ( 'checkout' === ( $input['mode'] ?? '' ) ) {
	$hook = $input['hook'] ?? 'woocommerce_checkout_process';
	if ( empty( $input['skip_process'] ) ) { $service->process(); }
	$hook = 'woocommerce_checkout_posted_data';
	$result = $service->posted( $input['posted'] ?? wp_unslash( $_POST ) );
} elseif ( isset( $input['sequence'] ) ) {
	foreach ( $input['sequence'] as $candidate ) { $result[] = $service->normalize( $candidate ); }
} else {
	$result = $service->normalize( $form );
	$service->normalize( $form );
}
echo json_encode( array( 'data' => $result, 'post' => $_POST, 'unslashed' => wp_unslash( $_POST ), 'calls' => $calls, 'hooks' => $hooks ), JSON_THROW_ON_ERROR );
