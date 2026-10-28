<?php
/** Isolated WooCommerce boundary for the production classic cart template. */
define( 'ABSPATH', __DIR__ );

$scenario = json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR );
$quantity_calls = array();
$quantity_filters = array();

function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_attr( $value ); }
function __( $value, $domain ) { return $value; }
function esc_html_e( $value, $domain ) { echo esc_html( $value ); }
function esc_attr_e( $value, $domain ) { echo esc_attr( $value ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function wp_kses_post( $value ) {
	// Model the post allowlist's exclusion of input/select controls, not all KSES rules.
	return strip_tags( $value, '<div><label><span><a><strong><p><button>' );
}
function do_action( $name, ...$args ) {}
function wc_get_cart_url() { return '/cart/'; }
function wc_get_cart_remove_url( $key ) { return '/cart/?remove_item=' . $key; }
function wc_get_formatted_cart_item_data( $item ) { return ''; }
function wc_coupons_enabled() { return false; }
function wc_wp_theme_get_element_class_name( $element ) { return ''; }
function wp_nonce_field( $action, $name ) {}
function WC() { return $GLOBALS['wc']; }
function apply_filters( $name, $value, ...$args ) {
	if ( 'woocommerce_cart_item_quantity' === $name ) {
		$GLOBALS['quantity_filters'][] = array(
			'key'      => $args[0],
			'quantity' => $args[1]['quantity'],
		);
		if ( ! empty( $GLOBALS['scenario']['filtered'] ) ) {
			return '<div class="theme-quantity"><button type="button" class="minus">-</button>' . $value . '<button type="button" class="plus">+</button><input type="hidden" name="theme_quantity_token" value="fixture" /></div>';
		}
	}
	return $value;
}
function woocommerce_quantity_input( $args, $product, $echo ) {
	$GLOBALS['quantity_calls'][] = array( 'args' => $args, 'echo' => $echo );
	$type = $args['min_value'] === $args['max_value'] ? 'hidden' : 'number';
	$html = '<div class="quantity"><label class="screen-reader-text" for="quantity_fixture">' . esc_html( $args['product_name'] ) . ' quantity</label><input id="quantity_fixture" type="' . $type . '" class="input-text qty text" name="' . esc_attr( $args['input_name'] ) . '" value="' . esc_attr( $args['input_value'] ) . '" min="' . esc_attr( $args['min_value'] ) . '" step="1"';
	if ( $args['max_value'] > 0 ) {
		$html .= ' max="' . esc_attr( $args['max_value'] ) . '"';
	}
	return $html . ' /></div>';
}

$product = new class {
	public function exists() { return true; }
	public function get_name() { return 'Fixture shirt'; }
	public function is_visible() { return false; }
	public function get_sku() { return 'fixture-shirt'; }
	public function get_image() { return ''; }
	public function backorders_require_notification() { return false; }
	public function is_sold_individually() { return ! empty( $GLOBALS['scenario']['sold_individually'] ); }
	public function get_max_purchase_quantity() { return $GLOBALS['scenario']['max_quantity'] ?? 8; }
};
$cart = new class( $product ) {
	private $product;
	public function __construct( $product ) { $this->product = $product; }
	public function get_cart() {
		return array(
			'fixture-shirt' => array(
				'data'       => $this->product,
				'product_id' => 42,
				'quantity'   => $GLOBALS['scenario']['quantity'] ?? 2,
			),
		);
	}
	public function get_product_price( $product ) { return 'Rp 100,000'; }
	public function get_product_subtotal( $product, $quantity ) { return 'Rp 200,000'; }
};
$GLOBALS['wc'] = (object) array( 'cart' => $cart );

ob_start();
require dirname( __DIR__, 2 ) . '/templates/woocommerce/cart/cart.php';
$html = ob_get_clean();
echo json_encode( array( 'html' => $html, 'calls' => $quantity_calls, 'filters' => $quantity_filters ), JSON_THROW_ON_ERROR );
