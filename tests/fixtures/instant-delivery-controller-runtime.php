<?php
/** Isolated WordPress boundary and typed service spies for the real controller. */
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'KIRIOF_NONCE', 'kiriof_ajax' );
	class ControllerResponse extends \RuntimeException {}
	function __( $text, $domain = '' ) { return $text; }
	function esc_html__( $text, $domain = '' ) { return esc_html( __( $text, $domain ) ); }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) { return array_map( 'wp_unslash', $value ); }
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
	function controller_slash( $value ) {
		if ( is_array( $value ) ) { return array_map( 'controller_slash', $value ); }
		return is_string( $value ) ? addslashes( $value ) : $value;
	}
	function current_user_can( $capability ) { $GLOBALS['events'][] = array( 'capability', $capability ); return $GLOBALS['input']['capable'] ?? true; }
	function wp_verify_nonce( $nonce, $action ) { $GLOBALS['events'][] = array( 'nonce', $nonce, $action ); return $nonce === 'valid:' . $action; }
	function wp_create_nonce( $action ) { return 'valid:' . $action; }
	function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
	function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
	function add_action( $hook, $callback ) { $GLOBALS['hooks'][] = array( $hook, get_class( $callback[0] ), $callback[1] ); }
	function nocache_headers() { $GLOBALS['events'][] = array( 'nocache' ); }
	function wp_send_json_success( $data ) { $GLOBALS['responses'][] = array( 'success' => true, 'data' => $data ); throw new ControllerResponse( 'json-success' ); }
	function wp_send_json_error( $data ) { $GLOBALS['responses'][] = array( 'success' => false, 'data' => $data ); throw new ControllerResponse( 'json-error' ); }
	function wp_die( $message ) { $GLOBALS['responses'][] = array( 'die' => $message ); throw new ControllerResponse( 'die' ); }
	function kiriof_checkout_service_factory() { return new \stdClass(); }
	function controller_spy( $method, $args ) {
		$GLOBALS['calls'][] = array( $method, $args );
		$GLOBALS['events'][] = array( 'service', $method );
		if ( isset( $GLOBALS['input']['service_error'] ) ) {
			if ( 'validation' === $GLOBALS['input']['service_error'] ) { throw new \InvalidArgumentException( 'Fixed validation message.' ); }
			throw new \RuntimeException( '<secret>remote token and PIN</secret>' );
		}
		return array( 'spy_result' => $method );
	}
}
namespace KiriminAjaOfficial\Repositories {
	class TransactionRepository {}
	class InstantDeliveryApiRepository {}
}
namespace KiriminAjaOfficial\Services {
	class InstantShipmentContext {}
	class InstantDispatchService {
		public array $dependencies;
		public function __construct( \KiriminAjaOfficial\Repositories\TransactionRepository $repository, \KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository $api, InstantShipmentContext $context ) { $this->dependencies = func_get_args(); }
		public function quote( array $ids ): array { return \controller_spy( 'quote', func_get_args() ); }
		public function dispatch( string $token, array $ids, string $method, string $pin ): array { return \controller_spy( 'dispatch', func_get_args() ); }
		public function refreshPayment( array $ids, string $payment_id ): array { return \controller_spy( 'refreshPayment', func_get_args() ); }
	}
	class InstantLabelService {
		public array $dependencies;
		public function __construct( \KiriminAjaOfficial\Repositories\TransactionRepository $repository ) { $this->dependencies = func_get_args(); }
		public function prepare( array $ids ): array { return \controller_spy( 'prepare', func_get_args() ); }
	}
}
namespace KiriminAjaOfficial\Controllers {
	// PHP resolves this before the global function; no web server is needed.
	function header( $value ) { $GLOBALS['headers'][] = $value; }
}
namespace {
	$input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
	$GLOBALS['input'] = $input;
	foreach ( array( 'events', 'calls', 'responses', 'hooks', 'headers' ) as $key ) { $GLOBALS[$key] = array(); }
	$root = dirname( __DIR__, 2 );
	$temp = sys_get_temp_dir() . '/kiriof-controller-' . bin2hex( random_bytes( 8 ) );
	mkdir( $temp . '/templates/instant', 0700, true );
	file_put_contents( $temp . '/templates/instant/labels.php', '<?php echo "LOCAL-TEMPLATE:" . json_encode( $labels );' );
	define( 'KIRIOF_DIR', $temp . '/' );
	require $root . '/inc/Controllers/InstantDeliveryController.php';
	require $root . '/inc/Init.php';
	$instantiate = new \ReflectionMethod( \KiriminAjaOfficial\Init::class, 'instantiate' );
	$controller = $instantiate->invoke( null, \KiriminAjaOfficial\Controllers\InstantDeliveryController::class );
	$dispatch = ( new \ReflectionProperty( $controller, 'dispatch_service' ) )->getValue( $controller );
	$label = ( new \ReflectionProperty( $controller, 'label_service' ) )->getValue( $controller );
	$composition = array(
		'controller' => get_class( $controller ),
		'dispatch' => get_class( $dispatch ),
		'label' => get_class( $label ),
		'dispatch_dependencies' => array_map( 'get_class', $dispatch->dependencies ),
		'label_dependencies' => array_map( 'get_class', $label->dependencies ),
		'shared_repository' => $dispatch->dependencies[0] === $label->dependencies[0],
		'listed_count' => count( array_keys( \KiriminAjaOfficial\Init::get_services(), get_class( $controller ), true ) ),
	);
	$controller->register();
	$_POST = array( 'data' => array( 'nonce' => 'valid:kiriof_ajax', 'order_ids' => '["KA-1",2]', 'confirmed' => 'yes', 'token' => 'quote-token', 'method' => 'credit', 'pin' => '1234', 'payment_id' => 'PAY-1' ) );
	if ( array_key_exists( 'data', $input ) ) { $_POST['data'] = $input['data']; }
	if ( isset( $input['fields'] ) ) { $_POST['data'] = array_replace( $_POST['data'], $input['fields'] ); }
	foreach ( $input['unset'] ?? array() as $key ) { unset( $_POST['data'][$key] ); }
	$_GET = $input['get'] ?? array( '_wpnonce' => 'valid:kiriof_instant_labels', 'oids' => 'KA-1,2' );
	// WordPress slashes incoming superglobals; preserve JSON escapes through wp_unslash.
	$_POST = controller_slash( $_POST );
	$_GET = controller_slash( $_GET );
	$GLOBALS['_kiriof_resi_print_nonce_checked'] = $input['legacy_bypass'] ?? false;
	$sentinel = '';
	ob_start();
	try { $controller->{$input['operation'] ?? 'quote'}(); } catch ( ControllerResponse $response ) { $sentinel = $response->getMessage(); }
	$html = ob_get_clean();
	unlink( $temp . '/templates/instant/labels.php' );
	rmdir( $temp . '/templates/instant' ); rmdir( $temp . '/templates' ); rmdir( $temp );
	echo json_encode( array( 'composition' => $composition, 'events' => $GLOBALS['events'], 'calls' => $GLOBALS['calls'], 'responses' => $GLOBALS['responses'], 'hooks' => $GLOBALS['hooks'], 'headers' => $GLOBALS['headers'], 'html' => $html, 'sentinel' => $sentinel ), JSON_THROW_ON_ERROR );
}
