<?php

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
function __( $text, $domain = '' ) { return $text . ( $GLOBALS['translation_suffix'] ?? '' ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function WC() { return $GLOBALS['wc']; }
function add_filter( $hook, $callback, $priority = 10 ) { $GLOBALS['hooks'][] = array( $hook, $callback[1], $priority ); }
function wc_get_logger() { throw new RuntimeException( 'Status must not log.' ); }
function wp_remote_post() { throw new RuntimeException( 'Status must not request quotes.' ); }
class WC_Shipping_Zones {
	public static function get_zone_matching_package( $package ) {
		return new class {
			public function get_shipping_methods( $enabled ) {
				return array( (object) array( 'id' => 'kiriminaja-instant', 'enabled' => $GLOBALS['enabled'] ? 'yes' : 'no' ) );
			}
		};
	}
}
require ABSPATH . 'inc/Services/InstantCheckoutStatusService.php';
require ABSPATH . 'inc/Init.php';
$shipping = new class {
	public array $packages = array();
	public function get_packages() { return $this->packages; }
	public function calculate_shipping() { throw new RuntimeException( 'Status must not calculate shipping.' ); }
};
$session = new class {
	public array $values = array();
	public function get( $key, $default = null ) { return $this->values[ $key ] ?? $default; }
	public function set( $key, $value ) { throw new RuntimeException( 'Status must not mutate session.' ); }
};
$GLOBALS['wc'] = new class( $shipping, $session ) {
	public function __construct( public object $shipping, public object $session ) {}
	public function shipping() { return $this->shipping; }
};
$GLOBALS['enabled'] = true;
$shipping->packages = array( array( 'contents' => array( 'item' => array() ), 'rates' => array() ) );
$key = '11:' . hash( 'sha256', wp_json_encode( array( 'item' ) ) );
$service = new KiriminAjaOfficial\Services\InstantCheckoutStatusService();
$service->register();
$existing = array( '.woocommerce-checkout-review-order-table' => '<table>native</table>', '.wfacp-order-summary' => '<div>FunnelKit</div>' );
$result = array( 'hooks' => $GLOBALS['hooks'], 'registered' => in_array( get_class( $service ), KiriminAjaOfficial\Init::get_services(), true ) );
foreach ( array( 'inactive', 'destination_required', 'recipient_invalid', 'cod_not_supported', 'cod_unsupported', 'quote_failed', 'available', 'PRIVATE\"><script>alert(1)</script>' ) as $code ) {
	$session->values = array( 'kiriof_instant_checkout_status' => array( $key => array( 'code' => $code, 'message' => 'PRIVATE raw provider message <script>', 'updated' => time(), 'token' => 'PRIVATE-token', 'coordinates' => 'PRIVATE-coordinates' ) ) );
	$result['states'][ $code ] = $service->classic_fragments( $existing );
}
$session->values['kiriof_instant_checkout_status'][ $key ]['code'] = 'quote_failed';
$shipping->packages[0]['rates'][] = new class {
	public function get_method_id() { return 'kiriminaja-instant'; }
	public function get_meta_data() { return array( 'kiriof_instant_quote_expires' => time() + 300, 'token' => 'PRIVATE-rate-token' ); }
};
$result['native_available'] = $service->classic_fragments( $existing );
$GLOBALS['enabled'] = false;
$result['disabled'] = $service->classic_fragments( $existing );
$GLOBALS['enabled'] = true;
$shipping->packages[0]['rates'] = array();
$session->values['kiriof_instant_checkout_status'][ $key ]['updated'] = time() - 181;
$result['stale'] = $service->classic_fragments( $existing );
$session->values['kiriof_instant_checkout_status'][ $key ]['updated'] = time();
$GLOBALS['translation_suffix'] = ' <script>" & unsafe</script>';
$result['escaped'] = $service->classic_fragments( $existing );
$result['existing'] = $existing;
echo json_encode( $result, JSON_THROW_ON_ERROR );
