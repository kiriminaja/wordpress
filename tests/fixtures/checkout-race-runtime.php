<?php
/** Isolated runtime fixture for checkout selection races. */
namespace KiriminAjaOfficial\Repositories {
    class SettingRepository {
        public function getSettingByKey( $key ) { return null; }
    }
}
namespace Automattic\WooCommerce\StoreApi\Exceptions {
    class RouteException extends \Exception {
        public function __construct( public string $errorCode, string $message, public int $status ) { parent::__construct( $message ); }
    }
}
namespace Automattic\WooCommerce\StoreApi\Schemas\V1 {
    class CheckoutSchema { public const IDENTIFIER = 'checkout'; }
}
namespace KiriminAjaOfficial\Services {
    class CheckoutServiceFactory {
        public function districtSearch( string $postcode ): \KiriminAjaOfficial\Utils\ServiceResponse {
            return new \KiriminAjaOfficial\Utils\ServiceResponse( array(), 'lookup result' );
        }
        public function createTransaction( $data ) {
            $GLOBALS['transaction_data'] = $data;
            return new class { public function call() { return true; } };
        }
    }
    class CustomerDistrictService { public function save( ...$args ) {} }
}
namespace KiriminAjaOfficial\Base {
    class BaseInit { public function logThis( ...$args ) {} }
}
namespace {
    define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
    define( 'REST_REQUEST', true );
    define( 'ARRAY_A', 'ARRAY_A' );
    require ABSPATH . 'inc/Utils/ServiceResponse.php';
    function __( $text, $domain = '' ) { return $text; }
    class CheckoutRaceSession {
        public function __construct( public array $values ) {}
        public function get( $key, $default = null ) { return $this->values[$key] ?? $default; }
        public function set( $key, $value ) { $this->values[$key] = $value; }
    }
    class CheckoutRaceCart {
        public array $cart_contents = array();
        public function needs_shipping() { return true; }
        public function get_discount_total() { return 0; }
        public function get_discount_tax() { return 0; }
        public function get_coupons() { return array(); }
    }
    class WC_Order {
        public array $meta;
        public function __construct( array $meta ) { $this->meta = $meta; }
        public function get_items( $type ) {
            if ( 'shipping' === $type ) {
                return array_map( static fn( $method ) => new class( $method ) {
                    public function __construct( private string $method ) {}
                    public function get_method_id() { return $this->method; }
                }, $GLOBALS['fixture_input']['order_methods'] ?? array() );
            }
            return array( new class {
                public function get_product() { return new class { public function needs_shipping() { return true; } }; }
            } );
        }
        public function get_payment_method() { return 'bacs'; }
        public function get_shipping_postcode() { return $GLOBALS['fixture_input']['order_postcode'] ?? '12345'; }
        public function get_shipping_country() { return $GLOBALS['fixture_input']['order_country'] ?? 'ID'; }
        public function get_meta( $key, $single = true ) { return $this->meta[$key] ?? ''; }
        public function delete_meta_data( $key ) { unset( $this->meta[$key] ); }
        public function save() {}

        public function update_meta_data( $key, $value ) { $this->meta[$key] = $value; }
    }
    class WP_REST_Request {
        public function __construct( private array $params ) {}
        public function get_params() { return $this->params; }
        public function get_param( $key ) { return $this->params[$key] ?? null; }
    }
    function WC() { return $GLOBALS['race_wc']; }
    function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
    function wp_unslash( $value ) { return stripslashes( $value ); }
    function woocommerce_store_api_register_endpoint_data( $data ) {
        $GLOBALS['registered_schema'] = array( 'endpoint' => $data['endpoint'], 'namespace' => $data['namespace'], 'schema' => ( $data['schema_callback'] )(), 'data' => ( $data['data_callback'] )() );
    }
    require ABSPATH . 'inc/Services/BuyerDestination.php';
    require ABSPATH . 'inc/Controllers/CheckoutController.php';
    $input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
    $GLOBALS['fixture_input'] = $input;
    $_POST = $input['post'] ?? array();
    $GLOBALS['wp'] = (object) array( 'query_vars' => array( 'rest_route' => $input['route'] ?? '/wc/store/v1/cart' ) );
    $GLOBALS['race_wc'] = (object) array( 'session' => new CheckoutRaceSession( $input['session'] ?? array() ), 'cart' => new CheckoutRaceCart() );
    $controller = ( new \ReflectionClass( \KiriminAjaOfficial\Controllers\CheckoutController::class ) )->newInstanceWithoutConstructor();
    $property = new \ReflectionProperty( $controller, 'setting_repository' );
    $property->setValue( $controller, new \KiriminAjaOfficial\Repositories\SettingRepository() );
    ( new \ReflectionProperty( $controller, 'checkout_service_factory' ) )->setValue( $controller, new \KiriminAjaOfficial\Services\CheckoutServiceFactory() );
    if ( isset( $input['customer_country'] ) ) {
        WC()->customer = new class( $input['customer_country'] ) {
            public function __construct( private string $country ) {}
            public function get_shipping_country() { return $this->country; }
        };
    }
    $result = array();
    try {
    switch ( $input['operation'] ) {
        case 'choose':
            foreach ( $input['selections'] as $selection ) {
                $result['methods'][] = $controller->kiriof_shipping_chosen_method( $selection['method'], array_fill_keys( $selection['available'], true ) );
            }
            break;
        case 'sync':
            $controller->kiriof_store_api_update_checkout( $input['data'] );
            break;
        case 'schema':
            $controller->kiriof_register_destination_schema();
            $result['schema'] = $GLOBALS['registered_schema'];
            break;
        case 'normalize':
            $result['destination'] = \KiriminAjaOfficial\Services\BuyerDestination::normalize( $input['destination'] );
            break;
        case 'processed':
            $order = new WC_Order( $input['meta'] ?? array() );
            $controller->afterCheckoutAfterCreated( 1, array(), $order );
            $result['meta'] = $order->meta;
            $result['transaction'] = $GLOBALS['transaction_data'] ?? null;
            break;
        case 'order':
            $order = new WC_Order( $input['meta'] ?? array() );
            $controller->afterStoreApiCheckoutUpdateOrderFromRequest( $order, new WP_REST_Request( $input['params'] ?? array() ) );
            $result['meta'] = $order->meta;
            break;
        default:
            throw new \InvalidArgumentException( 'Unknown operation' );
    }
    } catch ( \Throwable $error ) {
        $result['error'] = array( 'class' => get_class( $error ), 'message' => $error->getMessage(), 'code' => $error->errorCode ?? null, 'status' => $error->status ?? null );
    }
    $result['session'] = WC()->session->values;
    echo json_encode( $result, JSON_THROW_ON_ERROR );
}
