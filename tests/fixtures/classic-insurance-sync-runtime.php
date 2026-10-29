<?php
namespace KiriminAjaOfficial\Base {
	class BaseInit { public function logThis( $message, $data ) {} }
}
namespace KiriminAjaOfficial\Repositories {
	class SettingRepository {
		public function getSettingByKey( $key ) { return (object) array( 'value' => 'enable_insurance' === $key ? ( $GLOBALS['input']['global'] ?? 'no' ) : ( 'origin_zip_code' === $key ? '' : 123 ) ); }
		public function hasEnabledCourierServices() { return true; }
		public function getCourierServiceSelection() { return array( 'jnt' => array( 'EZ' ) ); }
		public function isCourierServiceEnabled( $courier, $service ) { return 'jnt' === $courier && 'EZ' === $service && empty( $GLOBALS['input']['disabled'] ); }
		public function validateWhiteListExpedition( $rows ) { return array_values( array_filter( $rows, fn( $row ) => $this->isCourierServiceEnabled( $row->service, $row->service_type ) ) ); }
	}
	class TransactionRepository {}
	class WpPostMetaRepository {}
	class KiriminajaApiRepository {
		public function getPricing( $payload ) {
			++$GLOBALS['calls'];
			$GLOBALS['pricing_payload'] = $payload;
			if ( ! empty( $GLOBALS['input']['pricing_failure'] ) ) { return array( 'status' => 503, 'message' => 'Unavailable' ); }
			return array( 'status' => 200, 'data' => (object) array( 'status' => true, 'results' => array( (object) array( 'service' => 'jnt', 'service_type' => 'EZ', 'cost' => 20000, 'discount_amount' => 0, 'insurance' => 1250, 'force_insurance' => ! empty( $GLOBALS['input']['carrier_force'] ) ) ) ) );
		}
	}
}
namespace KiriminAjaOfficial\Services\UtilServices {
	class GetWCCartAttributeService {
		public function __construct( $payload, $repository ) {}
		public function call() { return (object) array( 'status' => 200, 'data' => array( 'weight' => 1000, 'length' => 1, 'width' => 1, 'height' => 1, 'item_value' => 100000 ) ); }
	}
}
namespace {
	error_reporting( E_ALL & ~E_DEPRECATED );
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	require ABSPATH . 'vendor/autoload.php';
	$GLOBALS['input'] = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
	$GLOBALS['calls'] = 0;
	$GLOBALS['filter'] = '';
	if ( ! empty( $GLOBALS['input']['rest'] ) ) { define( 'REST_REQUEST', true ); }
	function WC() { return $GLOBALS['wc']; }
	function __( $text, $domain = '' ) { return $text; }
	function sanitize_text_field( $value ) { return (string) $value; }
	function wp_unslash( $value ) { return $value; }
	function current_filter() { return $GLOBALS['filter']; }
	function did_action( $hook ) { return 1; }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function get_transient( $key ) { return false; }
	function set_transient( $key, $value, $ttl ) { return true; }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
	$session = new class {
		public array $data = array();
		public function get( $key, $default = null ) { return $this->data[ $key ] ?? $default; }
		public function set( $key, $value ) { $this->data[ $key ] = $value; }
		public function get_session_data() { return $this->data; }
	};
	$cart = new class {
		public array $fees = array();
		public function needs_shipping() { return empty( $GLOBALS['input']['virtual'] ); }
		public function get_cart() { return array( array( 'line_subtotal' => 100000, 'line_total' => 1000, 'quantity' => 1, 'data' => new class { public function needs_shipping() { return empty( $GLOBALS['input']['virtual'] ); } } ) ); }
		public function get_cart_hash() { return 'coupon-cart'; }
		public function get_shipping_packages() { return array( array() ); }
		public function get_shipping_total() { return 0; }
		public function get_cart_contents_total() { return 1000; }
		public function get_discount_total() { return 99000; }
		public function get_discount_tax() { return 0; }
		public function get_coupons() { return array( '99-percent' => new class { public function get_free_shipping() { return false; } }, 'free-delivery' => new class { public function get_free_shipping() { return true; } } ); }
		public function add_fee( $name, $amount, $taxable ) { $this->fees[ $name ] = $amount; }
		public function calculate_totals( $controller ) {
			$this->fees = array();
			$controller->kiriof_shipping_method_update();
			return $this->get_cart_contents_total() + $this->get_shipping_total() + array_sum( $this->fees );
		}
	};
	$GLOBALS['wc'] = (object) array( 'cart' => $cart, 'session' => $session );
	$settings = new \KiriminAjaOfficial\Repositories\SettingRepository();
	$factory = new class( $settings ) extends \KiriminAjaOfficial\Services\CheckoutServiceFactory {
		private $settings;
		public function __construct( $settings ) { $this->settings = $settings; }
		public function calculation( array $payload ): \KiriminAjaOfficial\Services\CheckoutServices\CheckoutCalculationService {
			return new \KiriminAjaOfficial\Services\CheckoutServices\CheckoutCalculationService( $payload, $this->settings, new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository(), new \KiriminAjaOfficial\Repositories\WpPostMetaRepository() );
		}
	};
	$controller = new \KiriminAjaOfficial\Controllers\CheckoutController( $settings, new \KiriminAjaOfficial\Repositories\TransactionRepository(), new \KiriminAjaOfficial\Repositories\WpPostMetaRepository(), $factory );
	$session->data = ( $GLOBALS['input']['session'] ?? array() ) + array( 'kiriof_insurance' => 0, 'billing_insurance' => 0, 'destination_id' => 456, 'chosen_payment_method' => 'bacs', 'chosen_shipping_methods' => array( 'kiriminaja-official:jnt_EZ' ), 'shipping_for_package_0' => array( 'stale' ), 'kiriof_cached_insurance_amt' => 9999, 'kiriof_cached_cod_amt' => 777, 'kiriof_cached_fee_context' => array( 'stale' ) );
	$results = array();
	foreach ( $GLOBALS['input']['steps'] ?? array( array( 'post_data' => 'kiriof_insurance=0&kiriof_insurance=1' ) ) as $step ) {
		$GLOBALS['filter'] = ! empty( $step['final'] ) ? 'woocommerce_checkout_process' : ( $step['hook'] ?? 'woocommerce_checkout_update_order_review' );
		$calls_before = $GLOBALS['calls'];
		if ( ! empty( $step['final'] ) ) {
			parse_str( $step['post_data'], $_POST );
			if ( method_exists( $controller, 'kiriof_sync_classic_checkout_insurance' ) ) { $controller->kiriof_sync_classic_checkout_insurance(); }
			$_POST = array();
		} elseif ( method_exists( $controller, 'kiriof_sync_classic_review_insurance' ) ) {
			$controller->kiriof_sync_classic_review_insurance( $step['post_data'] );
		}
		$after_sync = $session->data;
		$sync_calls = $GLOBALS['calls'] - $calls_before;
		// Native WC updates the selected method after the review hook, before totals.
		if ( isset( $step['method'] ) ) { $session->set( 'chosen_shipping_methods', array( $step['method'] ) ); }
		$total = $cart->calculate_totals( $controller );
		$results[] = array( 'fees' => $cart->fees, 'total' => $total, 'session' => $session->data, 'after_sync' => $after_sync, 'sync_calls' => $sync_calls );
	}
	echo json_encode( array( 'steps' => $results, 'calls' => $GLOBALS['calls'], 'pricing_payload' => $GLOBALS['pricing_payload'] ?? null ), JSON_THROW_ON_ERROR );
}
