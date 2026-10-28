<?php
namespace Automattic\WooCommerce\StoreApi\Exceptions {
	class RouteException extends \Exception {
		public function __construct( public string $error_code, string $message, int $status, public array $data = array() ) { parent::__construct( $message, $status ); }
	}
}
namespace {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	require ABSPATH . 'vendor/autoload.php';
	$input = json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR );
	$_POST = $input['posted'] ?? array();
	$hooks = array();
	function __( $message, $domain = '' ) { return $message; }
	function esc_html( $message ) { return htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' ); }
	function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][ $priority ][] = $callback; }
	function WC() { return $GLOBALS['wc']; }
	$method = $input['method'] ?? 'kiriminaja-official_jne_REG';
	$wc = (object) array(
		'cart' => new class( $input ) {
			public function __construct( private array $input ) {}
			public function needs_shipping() { return $this->input['needs_shipping'] ?? true; }
		},
		'session' => new class( $method ) {
			public function __construct( private string $method ) {}
			public function get( $key, $default ) { return array( $this->method ); }
		},
	);
	$order = new class( $input, $method ) {
		public function __construct( private array $input, private string $method ) {}
		public function get_items( $type ) { return array( new class( $this->method ) {
			public function __construct( private string $method ) {}
			public function get_method_id() { return $this->method; }
		} ); }
		public function get_billing_phone() { return $this->input['billing_phone'] ?? '081234567890'; }
		public function get_shipping_phone() { return $this->input['shipping_phone'] ?? ''; }
	};
	$request = new class( $input ) {
		public function __construct( private array $input ) {}
		public function get_method() { return $this->input['http_method'] ?? 'POST'; }
		public function get_param( $key ) { return $this->input['request'][ $key ] ?? null; }
	};
	$errors = new class() {
		public array $errors = array();
		public function add( $code, $message, $data ) { $this->errors[ $code ] = compact( 'message', 'data' ); }
		public function get_error_message( $code ) { return $this->errors[ $code ]['message'] ?? ''; }
	};
	$service = new \KiriminAjaOfficial\Services\CheckoutPhoneValidation();
	$service->register();
	$route = $input['route'] ?? 'blocks';
	$hook = 'blocks' === $route ? 'woocommerce_store_api_checkout_update_order_from_request' : ( 'classic_fields' === $route ? 'woocommerce_after_checkout_validation' : 'woocommerce_checkout_create_order' );
	$processed = false;
	add_action( $hook, static function () use ( &$processed, $errors ) { if ( empty( $errors->errors ) ) { $processed = true; } }, 10, 2 );
	ksort( $hooks[ $hook ] );
	$error = null;
	try {
		foreach ( $hooks[ $hook ] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( 'classic_fields' === $route ) {
					$callback( $input + array( 'billing_phone' => '081234567890', 'shipping_method' => array( $method ) ), $errors );
				} else {
					$callback( $order, 'blocks' === $route ? $request : $input );
				}
			}
		}
	} catch ( \Throwable $exception ) {
		$error = array( 'status' => $exception->getCode(), 'message' => $exception->getMessage(), 'code' => $exception->error_code ?? '', 'data' => $exception->data ?? array() );
	}
	echo json_encode( array( 'processed' => $processed, 'error' => $error, 'errors' => $errors->errors, 'billing_phone' => $order->get_billing_phone(), 'shipping_phone' => $order->get_shipping_phone() ), JSON_THROW_ON_ERROR );
}
