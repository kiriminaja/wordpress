<?php
/** Isolated renderer for the actual Classic shipping template. */
define( 'ABSPATH', __DIR__ );
require_once dirname( __DIR__, 2 ) . '/inc/Services/CourierLogoAssets.php';
require_once dirname( __DIR__, 2 ) . '/inc/Services/RateChoicePresentation.php';
$input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
function is_cart() { return ! empty( $GLOBALS['input']['cart'] ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_html__( $value, $domain ) { return esc_html( $value ); }
function esc_html_e( $value, $domain ) { echo esc_html( $value ); }
function __( $value, $domain ) { return $value; }
function wp_kses_post( $value ) { return $value; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function wc_price( $value ) { return ( $GLOBALS['input']['price_prefix_html'] ?? '' ) . '<span class="amount">Rp ' . $value . '</span>'; }
function wc_cart_totals_shipping_method_label( $rate ) { return esc_html( $rate->get_label() ) . ': ' . wc_price( $rate->cost ); }
function get_bloginfo( $key ) { return 'UTF-8'; }
function apply_filters( $name, $value ) { return $value; }
function get_option( $name ) { return 'yes'; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_title( $value ) { return preg_replace( '/[^a-z0-9_-]/', '-', strtolower( $value ) ); }
function checked( $actual, $expected, $echo = true ) { $value = $actual === $expected ? 'checked="checked"' : ''; if ( $echo ) { echo $value; } return $value; }
function selected( $actual, $expected ) { echo $actual === $expected ? 'selected="selected"' : ''; }
function do_action( $name, $rate, $index ) { $GLOBALS['hooks'][] = array( $rate->id, $index ); }
function WC() { return $GLOBALS['wc']; }
function wc_prices_include_tax() { return ! empty( $GLOBALS['input']['prices_include_tax'] ); }
$GLOBALS['wc'] = (object) array( 'session' => new class {
	public function get( $key, $default = null ) { return $GLOBALS['input']['session'][ $key ] ?? $default; }
}, 'cart' => new class {
	public function display_prices_including_tax() { return ! empty( $GLOBALS['input']['including_tax'] ); }
}, 'countries' => new class {
	public function inc_tax_or_vat() { return '(incl. VAT)'; }
	public function ex_tax_or_vat() { return '(excl. VAT)'; }
} );
class Kiriof_Presentation_Test_Rate {
	public $id;
	public $cost;
	private $row;
	public function __construct( $row ) { $this->row = $row; $this->id = $row['id']; $this->cost = $row['cost'] ?? 20000; }
	public function get_label() { return $this->row['label']; }
	public function get_cost() { return $this->row['getter_cost'] ?? $this->cost; }
	public function get_taxes() { return $this->row['taxes'] ?? array(); }
	public function get_meta( $key, $single = true ) { return $this->row['meta'][ $key ] ?? ''; }
	public function get_description() { return $this->row['description'] ?? ''; }
	public function get_delivery_time() { return $this->row['delivery_time'] ?? ''; }
}
$available_methods = array_map( static function ( $row ) { return new Kiriof_Presentation_Test_Rate( $row ); }, $input['rates'] );
$index = $input['index'] ?? 0;
$chosen_method = $input['chosen'] ?? '';
$package_name = 'Package ' . ( $index + 1 );
$formatted_destination = 'Jakarta';
$has_calculated_shipping = true;
$show_shipping_calculator = false;
$show_package_details = false;
$GLOBALS['hooks'] = array();
ob_start();
require dirname( __DIR__, 2 ) . '/templates/woocommerce/cart/cart-shipping.php';
$html = ob_get_clean();
echo json_encode( array( 'html' => $html, 'hooks' => $GLOBALS['hooks'] ), JSON_THROW_ON_ERROR );
