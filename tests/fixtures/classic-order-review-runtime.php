<?php
/** Pure, isolated WC boundary: render both production templates, never book a shipment. */
namespace KiriminAjaOfficial\Services {
	class ShippingDiscountCouponService {
		public function getCurrentShippingDiscountTotal() { return 0; }
		public function isShippingCoupon( $coupon ) { return false; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	$input = json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR );
	$GLOBALS['hooks'] = array();
	function WC() { return $GLOBALS['wc']; }
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $value ) { return esc_html( $value ); }
	function __( $value, $domain ) { return $value; }
	function esc_html__( $value, $domain ) { return esc_html( $value ); }
	function esc_html_e( $value, $domain ) { echo esc_html( $value ); }
	function wp_kses_post( $value ) { return $value; }
	function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
	function absint( $value ) { return abs( (int) $value ); }
	function sanitize_title( $value ) { return preg_replace( '/[^a-z0-9_-]/', '-', strtolower( $value ) ); }
	function get_bloginfo( $key ) { return 'UTF-8'; }
	function get_option( $key ) { return 'yes'; }
	function is_cart() { return false; }
	function wc_tax_enabled() { return false; }
	function selected( $actual, $expected ) { echo $actual === $expected ? 'selected="selected"' : ''; }
	function checked( $actual, $expected, $echo = true ) { $result = $actual === $expected ? 'checked="checked"' : ''; if ( $echo ) { echo $result; } return $result; }
	function apply_filters( $name, $value, ...$args ) {
		if ( 'woocommerce_shipping_may_be_available_html' === $name && 'missing-api' === ( $GLOBALS['input']['scene'] ?? '' ) ) {
			return 'Shipping options are currently unavailable. Please contact us for assistance.';
		}
		return $value;
	}
	function do_action( $name, ...$args ) {
		$GLOBALS['hooks'][] = array( 'name' => $name, 'rate' => isset( $args[0]->id ) ? $args[0]->id : null );
	}
	function wc_price( $value ) { return '<span class="amount">Rp ' . number_format( $value, 0, '.', ',' ) . '</span>'; }
	function wc_get_formatted_cart_item_data( $item ) { return ''; }
	function wc_cart_totals_subtotal_html() { echo wc_price( 120000 ); }
	function wc_cart_totals_order_total_html() { echo '<strong>' . wc_price( 120000 + ( WC()->rates[0]->cost ?? 0 ) ) . '</strong>'; }
	function wc_cart_totals_shipping_method_label( $rate ) { return esc_html( $rate->get_label() ) . ': ' . wc_price( $rate->cost ); }
	function wc_cart_totals_shipping_html() {
		$available_methods = WC()->rates;
		$chosen_method = $available_methods[0]->id ?? '';
		$index = 0;
		$package_name = 'Shipping';
		$formatted_destination = 'Sleman, DI Yogyakarta, Indonesia';
		$has_calculated_shipping = ! empty( $available_methods );
		$show_shipping_calculator = false;
		$show_package_details = true;
		$package_details = 'Fixture coffee × 2';
		require dirname( __DIR__, 2 ) . '/templates/woocommerce/cart/cart-shipping.php';
	}
	class ReviewFixtureRate {
		public $id;
		public $cost;
		private $label;
		public function __construct( $id, $label, $cost ) { $this->id = $id; $this->label = $label; $this->cost = $cost; }
		public function get_label() { return $this->label; }
		public function get_meta( $key, $single = true ) { return ''; }
	}
	$product = new class {
		public function exists() { return true; }
		public function get_name() { return 'Fixture coffee'; }
	};
	$cart = new class( $product ) {
		private $product;
		public function __construct( $product ) { $this->product = $product; }
		public function get_cart() { return array( 'fixture-coffee' => array( 'data' => $this->product, 'quantity' => 2 ) ); }
		public function get_product_subtotal( $product, $quantity ) { return wc_price( 120000 ); }
		public function needs_shipping() { return true; }
		public function show_shipping() { return true; }
		public function get_coupons() { return array(); }
		public function get_fees() { return array(); }
	};
	$rates = array(
		new ReviewFixtureRate( 'kiriminaja-official:1:jne:reg', 'Fixture JNE Express REG', 15000 ),
		new ReviewFixtureRate( 'kiriminaja-instant:1:gosend:instant', 'Fixture GOSEND Instant', 20000 ),
	);
	if ( 'missing-api' === ( $input['scene'] ?? '' ) ) { $rates = array(); }
	if ( 'single' === ( $input['scene'] ?? '' ) ) { $rates = array( $rates[1] ); }
	$GLOBALS['wc'] = (object) array( 'cart' => $cart, 'rates' => $rates, 'session' => new class {
		public function get( $key, $default = null ) { return $default; }
	} );
	ob_start();
	require dirname( __DIR__, 2 ) . '/templates/woocommerce/checkout/review-order.php';
	$html = ob_get_clean();
	echo json_encode( array( 'html' => $html, 'hooks' => $GLOBALS['hooks'], 'rates' => array_map( static function ( $rate ) { return $rate->id; }, $rates ) ), JSON_THROW_ON_ERROR );
}
