<?php
/** Isolated customer order shipment rendering; no WordPress or API calls. */
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require_once ABSPATH . 'vendor/autoload.php';

function esc_html__( $text, $domain = '' ) { return esc_html( $text ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return esc_html( $url ); }
function wp_kses_post( $html ) { return $html; }
function kiriof_get_tracking_page_url( $args ) { return '/tracking?order_id=' . (int) $args['order_id']; }
function kiriof_helper() {
	return new class {
		public function formatServiceName( $courier, $service ) { return strtoupper( $courier ) . ' ' . $service; }
	};
}
class WC_Order {
	public function __construct( private array $data ) {}
	public function get_id() { return 504; }
	public function get_items( $type ) {
		return array( new class( $this->data['physical'] ?? true ) {
			public function __construct( private bool $physical ) {}
			public function get_product() {
				return new class( $this->physical ) {
					public function __construct( private bool $physical ) {}
					public function needs_shipping() { return $this->physical; }
				};
			}
		} );
	}
	public function get_shipping_methods() {
		return array_map( static fn( $item ) => new class( $item ) {
			public function __construct( private array $item ) {}
			public function get_method_id() { return $this->item['method']; }
			public function get_name() { return $this->item['name'] ?? ''; }
			public function get_meta( $key, $single = true ) {
				if ( ! in_array( $key, array( 'kiriof_instant_courier', 'kiriof_instant_service', 'kiriof_rate_service', 'kiriof_rate_service_type' ), true ) ) {
					throw new RuntimeException( 'Private metadata must not be read.' );
				}
				return $this->item['meta'][ $key ] ?? '';
			}
		}, $this->data['shipping'] ?? array() );
	}
}
$input = json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR );
$controller = ( new ReflectionClass( \KiriminAjaOfficial\Controllers\CheckoutController::class ) )->newInstanceWithoutConstructor();
ob_start();
$controller->kiriof_order_shipment_details( new WC_Order( $input ) );
echo json_encode( array( 'html' => ob_get_clean() ), JSON_THROW_ON_ERROR );
