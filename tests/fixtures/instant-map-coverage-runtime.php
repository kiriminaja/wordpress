<?php
namespace Automattic\WooCommerce\StoreApi\Schemas\V1 {
    class CheckoutSchema { public const IDENTIFIER = 'checkout'; }
    class CartSchema { public const IDENTIFIER = 'cart'; }
}
namespace KiriminAjaOfficial\Services {
    class ShipmentLocationService {
        public function getDefaultLocation() {
            ++$GLOBALS['default_reads'];
            return (object) array( 'origin_latitude' => '-6.2', 'origin_longitude' => '106.8', 'origin_phone' => 'private', 'origin_address' => 'private' );
        }
        public function locationToOrigin( $location ) { return (array) $location; }
    }
}
namespace KiriminAjaOfficial\Base { class BaseInit {} }
namespace {
    define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
    define( 'ARRAY_A', 'ARRAY_A' );
    $input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
    $GLOBALS['input'] = $input;
    $GLOBALS['default_reads'] = 0;
    function WC() {
        if ( ! empty( $GLOBALS['input']['no_wc'] ) ) { return false; }
        return new class {
            public function shipping() {
                return new class { public function get_packages() { return $GLOBALS['input']['packages'] ?? array(); } };
            }
        };
    }
    function is_account_page() { return ! empty( $GLOBALS['input']['account'] ); }
    function apply_filters( $hook, $value ) { return $value; }
    function __( $text, $domain ) { return $text; }
    function wp_kses_post( $text ) { return $text; }
    function woocommerce_store_api_register_endpoint_data( $data ) {
        $GLOBALS['schemas'][] = array(
            'endpoint' => $data['endpoint'], 'namespace' => $data['namespace'], 'schema_type' => $data['schema_type'],
            'schema' => ( $data['schema_callback'] )(), 'data' => ( $data['data_callback'] )(),
        );
    }
    require ABSPATH . 'inc/Services/BuyerDestination.php';
    require ABSPATH . 'inc/Services/InstantDeliveryCoverage.php';
    require ABSPATH . 'inc/Services/InstantMapCoverageService.php';
    require ABSPATH . 'inc/Base/Enqueue.php';
    require ABSPATH . 'inc/Controllers/CheckoutController.php';
    $service = new \KiriminAjaOfficial\Services\InstantMapCoverageService();
    if ( 'origin' === ( $input['operation'] ?? '' ) ) {
        $result = array( 'coverage' => $service::fromOrigin( $input['origin'] ) );
    } else {
        $controller = ( new \ReflectionClass( \KiriminAjaOfficial\Controllers\CheckoutController::class ) )->newInstanceWithoutConstructor();
        $controller->kiriof_register_destination_schema();
        $controller->kiriof_register_coverage_schema();
        $result = array( 'schemas' => $GLOBALS['schemas'], 'coverage' => $service->checkoutCoverage(), 'map' => ( new \KiriminAjaOfficial\Base\Enqueue() )->map_checkout_config() );
    }
    $result['default_reads'] = $GLOBALS['default_reads'];
    echo json_encode( $result, JSON_THROW_ON_ERROR );
}
