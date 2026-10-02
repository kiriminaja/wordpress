<?php
/** Offline integration: real status service, Cart schema, factory and update callback. No Composer/BaseService autoload. */
namespace Automattic\WooCommerce\StoreApi\Schemas\V1 {
    class CartSchema { public const IDENTIFIER = 'cart'; }
}
namespace KiriminAjaOfficial\Repositories {
    class SettingRepository { public function getSettingByKey( $key ) { return null; } }
    class TransactionRepository {}
    class WpPostMetaRepository {}
    class CodFeeApiRepository {}
    class KiriminajaApiRepository {
        public array $calls = array();
        public function sub_district_search( $postcode ) {
            $this->calls[] = $postcode;
            return array( array( 'id' => 222, 'text' => 'Canonical district' ) );
        }
    }
}
namespace KiriminAjaOfficial\Services {
    class ShipmentLocationService {}
    // Only the transport boundary is replaced; the production factory resolves identity.
    class KiriminajaApiService {
        public function __construct( private \KiriminAjaOfficial\Repositories\KiriminajaApiRepository $repository ) {}
        public function sub_district_search( $postcode ) {
            return new \KiriminAjaOfficial\Utils\ServiceResponse( $this->repository->sub_district_search( $postcode ), 'success', 200 );
        }
    }
}
namespace {
    define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
    define( 'ARRAY_A', 'ARRAY_A' );
    error_reporting( E_ALL );
    set_error_handler( static function ( $severity, $message, $file, $line ) { throw new \ErrorException( $message, 0, $severity, $file, $line ); } );
    function __( $text, $domain = '' ) { return $text; }
    function wp_json_encode( $value ) { return json_encode( $value ); }
    function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
    function do_action( $hook, ...$args ) { $GLOBALS['actions'][] = array( $hook, $args ); }
    function WC() { return $GLOBALS['wc']; }
    function woocommerce_store_api_register_endpoint_data( $args ) { $GLOBALS['schema_registration'] = $args; }
    function woocommerce_store_api_register_update_callback( $args ) { $GLOBALS['update_registration'] = $args; }
    class StatusSession {
        public array $values = array();
        public function get( $key, $default = null ) { return $this->values[$key] ?? $default; }
        public function set( $key, $value ) { $this->values[$key] = $value; }
    }
    class StatusShipping {
        public array $packages = array();
        public function get_packages() { return $this->packages; }
    }
    class StatusWC {
        public StatusSession $session;
        public StatusShipping $shipping;
        public object $cart;
        public object $customer;
        public function __construct() {
            $this->session = new StatusSession();
            $this->shipping = new StatusShipping();
            $this->cart = new class { public function needs_shipping() { return true; } };
            $this->customer = new class {
                public function get_shipping_country() { return 'ID'; }
                public function get_shipping_first_name() { return 'Native recipient'; }
                public function get_shipping_last_name() { return 'Unchanged'; }
                public function get_shipping_phone() { return 'native-phone'; }
                public function __call( $method, $args ) {
                    if ( str_starts_with( $method, 'get_shipping_' ) ) { return ''; }
                    throw new \RuntimeException( 'Native customer mutation: ' . $method );
                }
            };
        }
        public function shipping() { return $this->shipping; }
    }
    class StatusRate {
        public function __construct( private string $method, private array $meta ) {}
        public function get_method_id() { return $this->method; }
        public function get_meta_data() { return $this->meta; }
    }
    require ABSPATH . 'inc/Utils/ServiceResponse.php';
    require ABSPATH . 'inc/Services/BuyerDestination.php';
    require ABSPATH . 'inc/Services/CheckoutServiceFactory.php';
    require ABSPATH . 'inc/Services/InstantCheckoutStatusService.php';
    require ABSPATH . 'inc/Controllers/CheckoutController.php';
    $GLOBALS['wc'] = new StatusWC();
    $now = time();
    $hash = hash( 'sha256', wp_json_encode( array( 'cart-item' ) ) );
    $package = array( 'contents' => array( 'cart-item' => array( 'quantity' => 1 ) ), 'rates' => array() );
    WC()->shipping->packages = array( $package );
    $service = new \KiriminAjaOfficial\Services\InstantCheckoutStatusService();
    $out = array( 'now' => $now, 'inactive' => $service->data() );
    $row = array( 'code' => 'quote_failed', 'updated' => $now, 'eligible' => true, 'expires' => $now + 600, 'message' => 'PRIVATE remote error', 'token' => 'PRIVATE token', 'recipient_phone' => 'PRIVATE phone', 'fingerprint' => 'PRIVATE fingerprint' );
    WC()->session->set( 'kiriof_instant_checkout_status', array( '11:' . $hash => $row ) );
    $out['matching'] = $service->data();
    $stale = $row; $stale['updated'] = $now - 181;
    WC()->session->set( 'kiriof_instant_checkout_status', array( '11:' . $hash => $stale ) );
    $out['stale'] = $service->data();
    WC()->session->set( 'kiriof_instant_checkout_status', array( '11:' . hash( 'sha256', wp_json_encode( array( 'old-item' ) ) ) => $row ) );
    $out['changed_contents'] = $service->data();
    WC()->session->set( 'kiriof_instant_checkout_status', array( '11:' . $hash => array_merge( $row, array( 'code' => 'PRIVATE unknown-code' ) ) ) );
    $out['unknown_code'] = $service->data();
    WC()->session->set( 'kiriof_instant_checkout_status', array( '11:' . $hash => array_merge( $row, array( 'code' => 'available' ) ) ) );
    $meta = array( 'kiriof_instant_quote_expires' => $now - 1, 'kiriof_instant_quote_token' => 'PRIVATE quote token', 'recipient_address' => 'PRIVATE address' );
    WC()->shipping->packages[0]['rates'] = array( new StatusRate( 'kiriminaja-instant', $meta ) );
    $out['expired'] = $service->data();
    WC()->shipping->packages[0]['rates'] = array(
        new StatusRate( 'flat_rate', array( 'kiriof_instant_quote_expires' => $now + 10 ) ),
        new StatusRate( 'kiriminaja-instant', array_merge( $meta, array( 'kiriof_instant_quote_expires' => $now + 600 ) ) ),
        new StatusRate( 'kiriminaja-instant', array_merge( $meta, array( 'kiriof_instant_quote_expires' => (string) ( $now + 300 ) ) ) ),
        new StatusRate( 'kiriminaja-instant', array_merge( $meta, array( 'kiriof_instant_quote_expires' => 'invalid' ) ) ),
    );
    $out['available'] = $service->data();
    $settings = new \KiriminAjaOfficial\Repositories\SettingRepository();
    $transactions = new \KiriminAjaOfficial\Repositories\TransactionRepository();
    $post_meta = new \KiriminAjaOfficial\Repositories\WpPostMetaRepository();
    $api = new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository();
    $factory = new \KiriminAjaOfficial\Services\CheckoutServiceFactory( $settings, $transactions, $post_meta, $api, new \KiriminAjaOfficial\Repositories\CodFeeApiRepository(), new \KiriminAjaOfficial\Services\ShipmentLocationService() );
    $controller = new \KiriminAjaOfficial\Controllers\CheckoutController( $settings, $transactions, $post_meta, $factory );
    $controller->kiriof_register_instant_status_schema();
    $registration = $GLOBALS['schema_registration'];
    $out['schema'] = array( 'endpoint' => $registration['endpoint'], 'namespace' => $registration['namespace'], 'schema_type' => $registration['schema_type'], 'fields' => ( $registration['schema_callback'] )(), 'data' => ( $registration['data_callback'] )() );
    $controller->kiriof_register_store_api_update_callback();
    $out['callback_namespace'] = $GLOBALS['update_registration']['namespace'];
    WC()->session->values = array(
        'chosen_shipping_methods' => array( 'kiriminaja-instant:11:gosend:instant' ),
        'kiriof_chosen_shipping_methods' => array( 'kiriminaja-instant:11:gosend:instant' ),
        'kiriof_instant_checkout_quotes' => array( 'PRIVATE cached quote' ),
        'kiriof_instant_checkout_status' => array( 'old-status' ),
        'shipping_for_package_0' => array( 'old-rate' ),
        'shipping_for_package_4' => array( 'old-rate' ),
        'shipping_for_package_99' => 'unrelated',
        'kiriof_cached_insurance_amt' => 100,
        'kiriof_cached_cod_amt' => 200,
        'kiriof_cached_fee_context' => 'old-context',
    );
    WC()->shipping->packages[4] = $package;
    $callback = $GLOBALS['update_registration']['callback'];
    $callback( array( 'action' => 'sync_checkout', 'refresh_instant' => true, 'shipping_metode_id' => 'flat_rate:old', 'destination' => array( 'version' => 2, 'address_type' => 'shipping', 'shipping_address' => array( 'address_1' => 'Native street', 'address_2' => '', 'city' => 'Jakarta', 'state' => 'JK', 'postcode' => '12345', 'country' => 'ID' ), 'district_id' => '222', 'district_label' => 'Buyer untrusted label', 'postcode' => '12345', 'country' => 'ID', 'destination_latitude' => '-6.2', 'destination_longitude' => '106.8' ), 'payment_method' => 'bacs' ) );
    $out['refresh'] = WC()->session->values;
    $out['lookup_calls'] = $api->calls;
    $out['native_recipient'] = array( WC()->customer->get_shipping_first_name(), WC()->customer->get_shipping_last_name(), WC()->customer->get_shipping_phone() );
    // No refresh flag must leave the quote and native package caches intact.
    WC()->session->set( 'kiriof_instant_checkout_quotes', array( 'preserved-quote' ) );
    WC()->session->set( 'kiriof_instant_checkout_status', array( 'preserved-status' ) );
    WC()->session->set( 'shipping_for_package_0', array( 'preserved-rate' ) );
    $callback( array( 'action' => 'sync_checkout', 'refresh_instant' => false ) );
    $out['without_refresh'] = WC()->session->values;
    echo json_encode( $out, JSON_THROW_ON_ERROR );
}
