<?php
/** Isolated WP hook and WC rate boundary doubles; all ordering is production code. */
$root = dirname( __DIR__, 2 );
$wp_root = sys_get_temp_dir() . '/kiriof-rate-ordering-' . bin2hex( random_bytes( 8 ) );
mkdir( $wp_root . '/wp-admin/includes', 0777, true );
file_put_contents( $wp_root . '/wp-admin/includes/plugin.php', '<?php // is_plugin_active is supplied by the fixture.' );
register_shutdown_function( static function () use ( $wp_root ) {
	unlink( $wp_root . '/wp-admin/includes/plugin.php' );
	rmdir( $wp_root . '/wp-admin/includes' );
	rmdir( $wp_root . '/wp-admin' );
	rmdir( $wp_root );
} );
define( 'ABSPATH', $wp_root . '/' );
require_once $root . '/vendor/autoload.php';

$GLOBALS['rate_ordering_hooks'] = array();
$GLOBALS['rate_ordering_network_calls'] = 0;
function is_plugin_active( $plugin ) { return 'woocommerce/woocommerce.php' === $plugin; }
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return true; }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['rate_ordering_hooks'][$hook][] = compact( 'callback', 'priority', 'accepted_args' );
	return true;
}
function wp_remote_get( ...$args ) { ++$GLOBALS['rate_ordering_network_calls']; throw new RuntimeException( 'Ordering must not request quotes.' ); }
function wp_remote_post( ...$args ) { ++$GLOBALS['rate_ordering_network_calls']; throw new RuntimeException( 'Ordering must not request quotes.' ); }
function wp_remote_request( ...$args ) { ++$GLOBALS['rate_ordering_network_calls']; throw new RuntimeException( 'Ordering must not request quotes.' ); }
function WC() { return $GLOBALS['rate_ordering_wc']; }

/** Intentionally allows malformed raw costs to test the WC object boundary. */
class WC_Shipping_Rate {
	private $id;
	private $method_id;
	private $label;
	private $cost;
	private $taxes;
	private $meta_data;
	public function __construct( $id, $method_id, $label, $cost, $meta_data = array() ) {
		$this->id = $id;
		$this->method_id = $method_id;
		$this->label = $label;
		$this->cost = $cost;
		$this->taxes = array( 7 => 125.5 );
		$this->meta_data = $meta_data + array( 'description' => 'Keep this description', 'nested' => array( 'insurance' => 50 ) );
	}
	public function get_id() { return $this->id; }
	public function get_method_id() { return $this->method_id; }
	public function get_label() { return $this->label; }
	public function get_cost() { return $this->cost; }
	public function get_taxes() { return $this->taxes; }
	public function get_meta_data() { return $this->meta_data; }
}

$session = new class() {
	public array $data = array( 'chosen_shipping_methods' => array( 'shared-instant', 'shared-instant' ), 'kiriof_cached_cod_amt' => 987 );
	public int $writes = 0;
	public function get( $key, $default = null ) { return $this->data[$key] ?? $default; }
	public function set( $key, $value ) { ++$this->writes; $this->data[$key] = $value; }
};
$GLOBALS['rate_ordering_wc'] = (object) array( 'session' => $session );
$session_before = $session->data;
$_POST = array();

$controller = ( new ReflectionClass( \KiriminAjaOfficial\Controllers\CheckoutController::class ) )->newInstanceWithoutConstructor();
$controller->register();
$hooks = $GLOBALS['rate_ordering_hooks']['woocommerce_package_rates'] ?? array();
if ( 1 !== count( $hooks ) ) { throw new RuntimeException( 'Expected one registered woocommerce_package_rates callback.' ); }
$hook = $hooks[0];
$callback = $hook['callback'];
if ( ! is_callable( $callback ) ) { throw new RuntimeException( 'Package sorting callback is not callable.' ); }
$express = 'kiriminaja-official';
$instant = 'kiriminaja-instant';
$rate = static fn( $id, $method, $label, $cost, $meta = array() ) => new WC_Shipping_Rate( $id, $method, $label, $cost, $meta );
$cases = array(
	'combined' => array(
		'instant-expensive' => $rate( 'instant-expensive', $instant, 'GOJEK', 30000 ),
		'express-middle' => $rate( 'express-middle', $express, 'JNE REG', 20000 ),
		'instant-cheap' => $rate( 'instant-cheap', $instant, 'GRAB', 10000 ),
	),
	'ties' => array(
		'highest' => $rate( 'highest', $express, 'A', 20 ),
		'zulu' => $rate( 'zulu', $instant, 'Zulu', 10 ),
		'alpha-instant' => $rate( 'alpha-instant', $instant, 'alpha', 10 ),
		'beta' => $rate( 'beta', $express, 'Beta', 10 ),
		'alpha-express' => $rate( 'alpha-express', $express, 'ALPHA', '10.00' ),
		'alpha-second-instant' => $rate( 'alpha-second-instant', $instant, 'Alpha', 10.0 ),
		'lowest' => $rate( 'lowest', $express, 'Z', 0 ),
	),
	'raw_delivery' => array(
		'express-with-lower-total' => $rate( 'express-with-lower-total', $express, 'Express', 15000 ),
		'raw-instant' => $rate( 'raw-instant', $instant, 'Instant', 10000, array( 'admin_fee' => 20000, 'total_amount' => 30000 ) ),
	),
	'empty' => array(),
	'singleton' => array( 'only' => $rate( 'only', $instant, 'Only', 1 ) ),
);
foreach ( array( 'instant' => $instant, 'express' => $express ) as $name => $method ) {
	$cases[$name . '_only'] = array(
		$name . '-high' => $rate( $name . '-high', $method, 'A', 100 ),
		$name . '-free' => $rate( $name . '-free', $method, 'Z', 0 ),
		$name . '-low' => $rate( $name . '-low', $method, 'B', '25.50' ),
	);
}
$cases['mixed'] = array(
	'third-first' => $rate( 'third-first', 'flat_rate', 'Third first', 99999 ),
	'valid-high' => $rate( 'valid-high', $express, 'Express high', 30000 ),
	'wrong-object' => (object) array( 'method_id' => $instant, 'cost' => 0, 'label' => 'Not a WC rate' ),
	'negative' => $rate( 'negative', $instant, 'Negative', -1 ),
	'numeric-string' => $rate( 'numeric-string', $instant, 'String cost', '20000.50' ),
	'infinity' => $rate( 'infinity', $express, 'Infinite', INF ),
	'nan' => $rate( 'nan', $instant, 'NaN', NAN ),
	'third-middle' => $rate( 'third-middle', 'local_pickup', 'Pickup', 0 ),
	'valid-free' => $rate( 'valid-free', $instant, 'Free', 0 ),
	'not-numeric' => $rate( 'not-numeric', $express, 'Bad cost', 'not a number' ),
	'null-cost' => $rate( 'null-cost', $instant, 'Null cost', null ),
	'boolean-cost' => $rate( 'boolean-cost', $express, 'Boolean cost', false ),
	'valid-low' => $rate( 'valid-low', $express, 'Express low', 10000 ),
	'prefix-impostor' => $rate( 'prefix-impostor', 'kiriminaja-instant-extra', 'Not exact', 1 ),
	'null-entry' => null,
	'array-entry' => array( 'method_id' => $express, 'cost' => 0 ),
	'third-last' => $rate( 'third-last', 'kiriminaja-official-extra', 'Not exact either', 1 ),
);
$cases['package_one'] = array(
	'shared-express' => $rate( 'shared-express', $express, 'Express', 20 ),
	'shared-instant' => $rate( 'shared-instant', $instant, 'Instant', 10 ),
);
$cases['package_two'] = array(
	'shared-instant' => $rate( 'shared-instant', $instant, 'Instant', 40 ),
	'shared-express' => $rate( 'shared-express', $express, 'Express', 30 ),
);

$result = array(
	'registered_callback' => is_array( $callback ) && $callback[0] === $controller && 'kiriof_sort_package_rates' === $callback[1],
	'priority' => $hook['priority'],
	'accepted_args' => $hook['accepted_args'],
	'callback_matches_helper' => true,
	'cases' => array(),
);
$sorted_cases = array();
foreach ( $cases as $name => $rates ) {
	$before = serialize( $rates );
	$sorted = call_user_func( $callback, $rates, array( 'contents' => array(), 'destination' => array( 'country' => 'ID' ), 'package_id' => $name ) );
	$helper = \KiriminAjaOfficial\Services\CheckoutRatePresentation::sortPackageRates( $rates );
	$result['callback_matches_helper'] = $result['callback_matches_helper'] && $helper === $sorted;
	$identity = count( $rates ) === count( $sorted );
	$payload = true;
	foreach ( $rates as $key => $original ) {
		$identity = $identity && array_key_exists( $key, $sorted ) && $sorted[$key] === $original;
		$payload = $payload && array_key_exists( $key, $sorted ) && serialize( $sorted[$key] ) === serialize( $original );
	}
	$fixed = true;
	if ( 'mixed' === $name ) {
		$original_keys = array_keys( $rates );
		$sorted_keys = array_keys( $sorted );
		foreach ( $original_keys as $position => $key ) {
			if ( ! in_array( $key, array( 'valid-high', 'numeric-string', 'valid-free', 'valid-low' ), true ) ) {
				$fixed = $fixed && $key === $sorted_keys[$position] && $rates[$key] === $sorted[$key];
			}
		}
	}
	$result['cases'][$name] = array(
		'keys' => array_keys( $sorted ),
		'identity_preserved' => $identity,
		'payload_preserved' => $payload && $before === serialize( $rates ),
		'input_unchanged' => $before === serialize( $rates ),
		'idempotent' => $sorted === \KiriminAjaOfficial\Services\CheckoutRatePresentation::sortPackageRates( $sorted ),
		'fixed_slots' => $fixed,
	);
	$sorted_cases[$name] = $sorted;
}
$result['packages_independent'] = $sorted_cases['package_one']['shared-instant'] !== $sorted_cases['package_two']['shared-instant']
	&& 10 === $sorted_cases['package_one']['shared-instant']->get_cost()
	&& 40 === $sorted_cases['package_two']['shared-instant']->get_cost();
$result['chosen_method'] = $controller->kiriof_shipping_chosen_method( array_key_first( $sorted_cases['package_two'] ), $sorted_cases['package_two'], 'shared-instant' );
$result['network_calls'] = $GLOBALS['rate_ordering_network_calls'];
$result['session_writes'] = $session->writes;
$result['session_unchanged'] = $session_before === $session->data;
echo json_encode( $result, JSON_THROW_ON_ERROR );
