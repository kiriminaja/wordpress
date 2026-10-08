<?php
namespace Automattic\WooCommerce\StoreApi\Exceptions {
	class RouteException extends \RuntimeException {
		public function __construct( $code, $message, $status ) { parent::__construct( $message, $status ); }
	}
}
namespace {
    function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	define( 'ABSPATH', __DIR__ );
	function __( $text, $domain ) { return ! empty( $GLOBALS['translated'] ) ? '<b>Shipping changed & retry</b>' : $text; }
	function wp_unslash( $value ) { return stripslashes( $value ); }
	$GLOBALS['hooks'] = array();
	$GLOBALS['review_logs'] = array();
	function kiriof_log( $level, $message, $context, $channel ) { $GLOBALS['review_logs'][] = compact( 'level', 'message', 'context', 'channel' ); }
	function add_action( $hook, $callback, $priority, $args ) { $GLOBALS['hooks'][$hook][$priority][] = $callback; }
	function WC() { return $GLOBALS['wc']; }
	class GuardRate {
		public function __construct( public string $id, public string $method, public int $instance, public array $meta ) {}
		public function get_id() { return $this->id; }
		public function get_method_id() { return $this->method; }
		public function get_instance_id() { return $this->instance; }
		public function get_meta_data() { return $this->meta; }
		public function get_meta( $key, $single = true ) { return $this->meta[$key] ?? ''; }
	}
	class GuardOrder {
		public array $writes = array();
		public function __construct( public array $lines ) {}
		public function get_items( $type ) { return $this->lines; }
	}
	class GuardSession {
		public function __construct( public array $chosen ) {}
		public function get( $key, $default ) { return $this->chosen; }
	}
	class GuardWC {
		public function __construct( public array $packages, public GuardSession $session ) {}
		public function shipping() { return $this; }
		public function get_packages() { return $this->packages; }
	}
	class GuardRequest {
		public function __construct( public $review ) {}
		public function get_method() { return $GLOBALS['guard_method'] ?? 'POST'; }
		public function get_param( $key ) { return array( 'kiriminaja-official' => array( 'shipping_selection' => $this->review ) ); }
	}
	require __DIR__ . '/../../inc/Services/CheckoutShippingSelectionGuard.php';
	require __DIR__ . '/../../inc/Init.php';
	$guard = new \KiriminAjaOfficial\Services\CheckoutShippingSelectionGuard();
	$guard->register();
	$config = json_decode( $argv[1], true );
	$GLOBALS['guard_method'] = 'patch' === $config['case'] ? 'PATCH' : 'POST';
	$instant = new GuardRate( 'kiriminaja-instant:7:gosend:instant', 'kiriminaja-instant', 7, array( 'kiriof_instant_courier' => 'gosend', 'kiriof_instant_service' => 'instant' ) );
	$express = new GuardRate( 'kiriminaja-official_jne_REG', 'kiriminaja-official', 2, array( 'kiriof_rate_service' => 'jne', 'kiriof_rate_service_type' => 'REG' ) );
	$other = new GuardRate( 'flat_rate:8', 'flat_rate', 8, array() );
	$case = $config['case'];
	$GLOBALS['translated'] = 'translated' === $case;
	if ( 'opaque' === $case ) { $instant->id .= ':opaque%20<tag>\\\"'; }
	$actual = in_array( $case, array( 'changed', 'terms', 'into-plugin', 'express', 'service', 'case', 'legacy' ), true ) ? clone $express : clone $instant;
	if ( in_array( $case, array( 'outside', 'away-plugin' ), true ) ) { $actual = clone $other; }
	$expected = in_array( $case, array( 'changed', 'terms', 'away-plugin' ), true ) ? $instant : $actual;
	if ( 'into-plugin' === $case ) { $expected = $other; }
	$review = array( 'version' => 1, 'packages' => array( array( 'package_id' => '3', 'rate_id' => $expected->id, 'price' => '20000', 'taxes' => '0', 'currency_minor_unit' => 0 ) ) );
	if ( in_array( $case, array( 'missing', 'outside' ), true ) ) { $review = null; }
	if ( 'translated' === $case ) { $review = null; }
	if ( 'malformed' === $case ) { $review['version'] = '1'; }
	if ( 'duplicate' === $case ) { $review['packages'][] = $review['packages'][0]; }
	if ( 'control' === $case ) { $review['packages'][0]['rate_id'] .= "\n"; }
	if ( 'overflow' === $case ) { $review['packages'][0]['rate_id'] = str_repeat( 'a', 257 ); }
	if ( 'negative' === $case ) { $review['packages'][0]['package_id'] = -1; }
	if ( 'outside-malformed' === $case ) { $actual = clone $other; $review = array( 'version' => 1, 'packages' => 'bad-shape' ); }
	if ( 'patch' === $case ) { $review = null; }
	$line = clone $actual;
	if ( 'service' === $case ) { $line->meta['kiriof_rate_service_type'] = 'YES'; }
	if ( 'case' === $case ) { $line->meta['kiriof_rate_service_type'] = 'reg'; }
	if ( 'instance' === $case ) { $line->instance = 99; }
	if ( 'legacy' === $case ) { $line->method = $actual->id; }
	if ( 'order-route' === $case ) { $line = clone $express; }
	$packages = array( 3 => array( 'rates' => 'missing-rate' === $case ? array() : array( $actual->id => $actual ) ) );
	$chosen = array( 3 => $actual->id );
	if ( ! empty( $config['stale_session_keys'] ) ) {
		// Woo updates current package keys in place; obsolete keys may survive.
		$chosen[0] = $instant->id;
		$chosen[12] = 'flat_rate:obsolete';
	}
	$lines = array( 42 => $line );
	if ( 'multi' === $case ) {
		$packages[9] = array( 'rates' => array( $other->id => $other ) );
		$chosen[9] = $other->id;
		$review['packages'][] = array( 'package_id' => 9, 'rate_id' => $other->id );
		$lines = array( 91 => clone $other, 42 => $line );
	}
	$GLOBALS['wc'] = new GuardWC( $packages, new GuardSession( $chosen ) );
	$order = new GuardOrder( $lines );
	$hook = $config['classic'] ? 'woocommerce_checkout_create_order' : 'woocommerce_store_api_checkout_update_order_from_request';
	// Simulate the real priority boundary: neither route may write before the review gate.
	add_action( $hook, function ( $order ) { $order->writes[] = 'express-validation'; }, 10, 2 );
	add_action( $hook, function ( $order ) { $order->writes[] = 'instant-validation'; }, 20, 2 );
	ksort( $GLOBALS['hooks'][$hook] );
	if ( $config['classic'] && null !== $review ) { $_POST['kiriof_shipping_selection'] = addslashes( json_encode( $review ) ); }
	$attempts = array();
	for ( $i = 0; $i < ( 'terms' === $case ? 2 : 1 ); ++$i ) {
		// The initial rejected-terms attempt precedes order hooks. Its unchanged review survives retry.
		if ( 'terms' === $case && 0 === $i ) { $attempts[] = array( 'status' => 'terms', 'writes' => $order->writes ); continue; }
		$status = 0; $message = '';
		try {
			foreach ( $GLOBALS['hooks'][$hook] as $callbacks ) { foreach ( $callbacks as $callback ) { $callback( $order, new GuardRequest( $review ) ); } }
		} catch ( \Throwable $error ) { $status = $error->getCode(); $message = $error->getMessage(); }
		$attempts[] = array( 'status' => $status, 'message' => $message, 'writes' => $order->writes );
	}
	echo json_encode( array( 'attempts' => $attempts, 'priorities' => array_keys( $GLOBALS['hooks'][$hook] ), 'registered' => in_array( \KiriminAjaOfficial\Services\CheckoutShippingSelectionGuard::class, \KiriminAjaOfficial\Init::get_services(), true ), 'chosen' => $GLOBALS['wc']->session->chosen, 'logs' => $GLOBALS['review_logs'] ) );
}
