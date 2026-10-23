<?php
/** Standalone runtime fixture: WordPress/WooCommerce globals never enter PHPUnit. */
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'KIRIOF_NONCE', 'checkout-country-runtime' );

final class CheckoutCountrySession {
    public function __construct( private array $values ) {}
    public function get( $key, $default = null ) { return $this->values[ $key ] ?? $default; }
    public function set( $key, $value ): void { $this->values[ $key ] = $value; }
    public function all(): array { return $this->values; }
}

// Use the real class name so CustomerDistrictService exercises its WC_Customer path.
class WC_Customer {
    public array $metaReads = array();
    public function __construct( private array $meta ) {}
    public function get_meta( $key, $single = true ) {
        $this->metaReads[] = $key;
        return $this->meta[ $key ] ?? '';
    }
    public function update_meta_data( $key, $value ): void { $this->meta[ $key ] = $value; }
    public function save_meta_data(): void {}
}

final class CheckoutCountryCheckout {
    public array $reads = array();
    public function __construct( private array $values ) {}
    public function get_value( $key ) {
        $this->reads[] = $key;
        return $this->values[ $key ] ?? '';
    }
}

final class CheckoutCountryCart {
    public int $shippingReads = 0;
    public function __construct( private bool $needsShipping, private array $items ) {}
    public function needs_shipping(): bool {
        ++$this->shippingReads;
        return ( ( new ReflectionClass( \KiriminAjaOfficial\Controllers\CheckoutController::class ) )->newInstanceWithoutConstructor() )->kiriof_filter_cart_needs_shipping( $this->needsShipping );
    }
    public function get_cart(): array { return $this->items; }
}

final class CheckoutCountryProduct {
    public function __construct( private bool $needsShipping ) {}
    public function needs_shipping(): bool { return $this->needsShipping; }
}

final class CheckoutCountryShipping {
    public function get_packages(): array { return array(); }
}

final class CheckoutCountryWooCommerce {
    public CheckoutCountrySession $session;
    public WC_Customer $customer;
    public CheckoutCountryCart $cart;
    public CheckoutCountryShipping $shipping;
    public CheckoutCountryCheckout $checkout;
    public function __construct( array $input ) {
        $this->session = new CheckoutCountrySession( $input['session'] ?? array() );
        $this->customer = new WC_Customer( $input['customer_meta'] ?? array() );
        $items = array();
        foreach ( $input['products'] ?? array( $input['needs_shipping'] ?? true ) as $needsShipping ) {
            $items[] = array( 'data' => new CheckoutCountryProduct( $needsShipping ), 'quantity' => 1 );
        }
        $this->cart = new CheckoutCountryCart( $input['needs_shipping'] ?? true, $items );
        $this->shipping = new CheckoutCountryShipping();
        $this->checkout = new CheckoutCountryCheckout( $input['checkout_values'] ?? array() );
    }
    public function checkout(): CheckoutCountryCheckout { return $this->checkout; }
}

function WC() { return $GLOBALS['country_wc']; }
function did_action( $hook ) { return $GLOBALS['country_wp_loaded'] ? 1 : 0; }
function is_cart() { return false; }
function is_checkout() { return true; }
function sanitize_text_field( $value ) { return is_array( $value ) || is_object( $value ) ? '' : trim( strip_tags( (string) $value ) ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( $value ); }
function __( $value, $domain = '' ) { return $value; }
function esc_html__( $value, $domain = '' ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function get_current_user_id() { return 0; }
function absint( $value ) { return abs( (int) $value ); }
function wc_get_base_location() { return array( 'country' => 'ID' ); }
function wc_add_notice( $message, $type = 'success' ) { $GLOBALS['country_notices'][] = array( 'message' => $message, 'type' => $type ); }
function wp_verify_nonce( $nonce, $action ) {
    $GLOBALS['country_nonce_checks'][] = array( $nonce, $action );
    return 'valid-country-nonce' === $nonce && KIRIOF_NONCE === $action;
}

require_once ABSPATH . 'inc/Services/CustomerDistrictService.php';
require_once ABSPATH . 'inc/Controllers/CheckoutController.php';

$input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
$_POST = $input['post'] ?? array();
$GLOBALS['country_wc'] = new CheckoutCountryWooCommerce( $input );
$GLOBALS['country_wp_loaded'] = $input['wp_loaded'] ?? true;
if ( ! empty( $input['cart_unavailable'] ) ) {
    unset( $GLOBALS['country_wc']->cart );
}
$GLOBALS['country_notices'] = array();
$GLOBALS['country_nonce_checks'] = array();
// These country-field paths do not use repositories or the service factory.
// Keep this isolated fixture independent of their WordPress/API dependencies.
$controller = ( new ReflectionClass( \KiriminAjaOfficial\Controllers\CheckoutController::class ) )->newInstanceWithoutConstructor();
$result = array();

switch ( $input['action'] ) {
    case 'fields':
        $fields = array();
        foreach ( array( 'billing', 'shipping' ) as $group ) {
            foreach ( array( 'state', 'city', 'company', 'postcode', 'country', 'phone', 'email' ) as $field ) {
                $fields[ $group ][ $group . '_' . $field ] = array(
                    'label' => ucfirst( $field ),
                    'required' => 'company' !== $field,
                    'type' => in_array( $field, array( 'state', 'country' ), true ) ? $field : 'text',
                    'class' => array( 'form-row-wide', 'address-field', 'locale-' . $field ),
                    'validate' => array( $field ),
                    'custom_attributes' => array( 'data-locale' => $group . '-' . $field, 'aria-describedby' => $field . '-help' ),
                    'priority' => 50,
                );
            }
        }
        foreach ( $input['field_overrides'] ?? array() as $group => $overrides ) {
            foreach ( $overrides as $key => $attributes ) {
                if ( isset( $fields[ $group ][ $key ] ) ) {
                    $fields[ $group ][ $key ] = array_replace( $fields[ $group ][ $key ], $attributes );
                }
            }
        }
        foreach ( $input['unset_fields'] ?? array() as $group => $keys ) {
            foreach ( $keys as $key ) {
                unset( $fields[ $group ][ $key ] );
            }
        }
        $result['original_fields'] = $fields;
        $result['fields'] = $controller->kiriof_billing_fields( $fields );
        break;
    case 'normalize':
        $method = new ReflectionMethod( $controller, 'kiriof_normalize_classic_destination_post_data' );
        $method->invoke( $controller );
        break;
    case 'validate':
        $controller->kiriof_validateOrder();
        break;
    case 'shipping_filter':
        $result['needs_shipping'] = isset( WC()->cart ) ? WC()->cart->needs_shipping() : $controller->kiriof_filter_cart_needs_shipping( false );
        break;
    case 'fees':
        $controller->kiriof_shipping_method_update();
        break;
    case 'store_update':
        $controller->kiriof_store_api_update_checkout( $input['data'] ?? array() );
        break;
    case 'field_validation':
        $controller->kiriof_checkout_field_validation();
        break;
    case 'create_foreign_order':
        $order = new stdClass();
        $order->postcode = 'SW1A 1AA';
        $controller->afterCheckoutBeforeCreated( $order, array() );
        $result['postcode'] = $order->postcode;
        break;
    default:
        throw new InvalidArgumentException( 'Unknown fixture action' );
}

$result['post'] = $_POST;
$result['notices'] = $GLOBALS['country_notices'];
$result['nonce_checks'] = $GLOBALS['country_nonce_checks'];
$result['checkout_reads'] = WC()->checkout->reads;
$result['customer_meta_reads'] = WC()->customer->metaReads;
$result['session'] = WC()->session->all();
$result['shipping_reads'] = isset( WC()->cart ) ? WC()->cart->shippingReads : 0;
echo json_encode( $result, JSON_THROW_ON_ERROR );
