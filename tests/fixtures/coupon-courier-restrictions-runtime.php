<?php
// Isolated controller runtime; no WordPress database or carrier API is loaded.
define( 'ABSPATH', __DIR__ );
function __( $text, $domain = '' ) { return $text; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return esc_attr( $value ); }
function esc_html__( $text, $domain = '' ) { return esc_html( $text ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( $value ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function checked( $value, $expected = true, $echo = true ) {
	$result = (string) $value === (string) $expected ? 'checked="checked"' : '';
	if ( $echo ) { echo $result; }
	return $result;
}
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $key ] = $value; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $key ] ); }
function current_user_can( $capability, $id ) { return 'edit_post' === $capability && 42 === $id && $GLOBALS['can_edit']; }
function wp_verify_nonce( $nonce, $action ) { return 'verified-native-nonce' === $nonce && 'update-coupon_42' === $action; }
function get_transient( $key ) {
	// Cached Instant duplicates must not create duplicate choices. Forbidden rows
	// exercise the real catalog's filtering, not a fake courier-options helper.
	return 'kiriof_couriers_all_v1' === $key ? array(
		array( 'code' => 'gosend', 'name' => 'GoSend', 'type' => 'instant' ),
		array( 'code' => 'grab_express', 'name' => 'GrabExpress', 'type' => 'instant' ),
		array( 'code' => 'borzo', 'name' => 'Borzo', 'type' => 'instant' ),
		array( 'code' => 'ninja_inter', 'name' => 'International', 'type' => 'international' ),
	) : false;
}
function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['hooks'][ $hook ][ $priority ][] = array( $callback, $accepted ); }
function add_action( $hook, $callback, $priority = 10, $accepted = 1 ) { add_filter( $hook, $callback, $priority, $accepted ); }
function do_action( $hook, ...$args ) {
	$callbacks = $GLOBALS['hooks'][ $hook ] ?? array();
	ksort( $callbacks );
	foreach ( $callbacks as $group ) {
		foreach ( $group as list( $callback, $accepted ) ) {
			call_user_func_array( $callback, array_slice( $args, 0, $accepted ) );
		}
	}
}
class WC_Coupon {
	public function get_id() { return 42; }
	public function get_discount_type() { return 'kiriof_fixed_shipping_discount'; }
	public function get_amount() { return '10'; }
}
$root = dirname( __DIR__, 2 );
require $root . '/inc/Services/CourierServiceCatalog.php';
// register() only references this unrelated cron constant; no region work runs.
class CouponRegionCache {
	public const CRON_HOOK = 'kiriof_test_unused_region_refresh';
}
class_alias( CouponRegionCache::class, 'KiriminAjaOfficial\\Services\\ShippingDiscountRegionCacheService' );
require $root . '/inc/Services/ShippingDiscountCouponService.php';
require $root . '/inc/Controllers/ShippingDiscountCouponController.php';
// None of the exercised paths needs the region repository or its database.
$controller = ( new ReflectionClass( \KiriminAjaOfficial\Controllers\ShippingDiscountCouponController::class ) )->newInstanceWithoutConstructor();
$key = \KiriminAjaOfficial\Services\ShippingDiscountCouponService::META_COURIERS;
$GLOBALS['can_edit'] = true;
$GLOBALS['meta'] = array();
$result = array();
if ( 'render' === ( $argv[1] ?? '' ) ) {
	$render = new ReflectionMethod( $controller, 'renderCourierRestrictionFields' );
	foreach ( array( 'all' => array(), 'selected' => array( 'grab_express', 'gosend', 'ninja' ) ) as $scope => $saved ) {
		$GLOBALS['meta'][ $key ] = $saved;
		ob_start();
		$render->invoke( $controller, 42 );
		$result[ $scope ] = ob_get_clean();
	}
} else {
	$controller->register();
	$coupon = new WC_Coupon();
	// WooCommerce owns nonce verification outside woocommerce_coupon_options_save.
	// Model that boundary explicitly; the plugin must not invent a second nonce.
	$native_save = static function () use ( $coupon ) {
		if ( wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'update-coupon_42' ) ) {
			do_action( 'woocommerce_coupon_options_save', 42, $coupon );
		}
	};
	$_POST = array( '_wpnonce' => 'verified-native-nonce', $key . '_scope' => 'selected', $key => array( 'grab_express', 'gosend', 'ninja', 'grab_express', '', 'unknown', 'borzo', 'ninja_inter' ) );
	$native_save();
	$result['selected'] = $GLOBALS['meta'][ $key ];
	$service = new \KiriminAjaOfficial\Services\ShippingDiscountCouponService();
	$allows = new ReflectionMethod( $service, 'couponAllowsCourier' );
	foreach ( array( 'grab_express', 'gosend', 'ninja', 'jne' ) as $code ) {
		$result['allowed'][] = $allows->invoke( $service, $coupon, $code );
	}
	$_POST[ $key . '_scope' ] = 'all';
	$native_save();
	$result['all'] = $GLOBALS['meta'][ $key ];
	$GLOBALS['meta'][ $key ] = array( 'ninja' );
	$GLOBALS['can_edit'] = false;
	$_POST[ $key . '_scope' ] = 'selected';
	$native_save();
	$result['denied_capability'] = $GLOBALS['meta'][ $key ];
	$GLOBALS['can_edit'] = true;
	$_POST['_wpnonce'] = 'invalid';
	$native_save();
	$result['invalid_nonce'] = $GLOBALS['meta'][ $key ];
}
echo json_encode( $result, JSON_THROW_ON_ERROR );
