<?php

use PHPUnit\Framework\TestCase;

/** Real repository with an isolated SDK transport spy (no network or SDK facade). */
final class InstantLifecycleApiRuntimeTest extends TestCase {
	private function run_fixture( string $scenario ): array {
		$fixture = <<<'PHP'
namespace KiriminAja\Base\Api {
	class Api {
		public function get( string $endpoint, $data = null ): array { return $this->respond( 'GET', $endpoint, $data ); }
		public function delete( string $endpoint, $data = null ): array { return $this->respond( 'DELETE', $endpoint, $data ); }
		private function respond( $method, $endpoint, $data ): array {
			$GLOBALS['calls'][] = array( $method, $endpoint, $data );
			if ( $GLOBALS['throw'] ) { throw new \RuntimeException( 'Timeout private recipient 08123456789 address secret' ); }
			return $GLOBALS['transport'];
		}
	}
}
namespace KiriminAjaOfficial\Infrastructure {
	class InstantApiTransport extends \KiriminAja\Base\Api\Api {}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function kiriof_log( ...$args ) { $GLOBALS['logs'][] = $args; }
	require ROOT . '/vendor/autoload.php';
	require ROOT . '/inc/Base/KiriminAjaApi.php';
	require ROOT . '/inc/Repositories/InstantDeliveryApiRepository.php';
	class LifecycleFixtureRepository extends \KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository {
		public function __construct() {}
		public function get( $endpoint, $body = array(), $log_context = array() ) { throw new \RuntimeException( 'Inherited transport forbidden' ); }
	}
	$GLOBALS['calls'] = array();
	$GLOBALS['logs'] = array();
	$GLOBALS['throw'] = false;
	$repository = new LifecycleFixtureRepository();
	$tracking = array( 'status' => true, 'code' => 0, 'text' => 'loaded', 'result' => array( 'order_id' => 'order-1', 'status' => 105, 'driver' => array( 'name' => 'private driver' ), 'history' => array( array( 'status' => 105 ) ) ), 'extra' => array( 'full' => true ) );
	$cancel = array( 'status' => true, 'code' => 0, 'text' => 'void accepted', 'result' => array( 'payment_id' => 'payment-1', 'packages' => array( array( 'order_id' => 'order-1', 'service' => 'gosend', 'status' => 105 ) ) ), 'extra' => array( 'full' => true ) );
PHP;
		$code = 'namespace { define( "ROOT", ' . var_export( PLUGIN_DIR, true ) . ' ); }' . $fixture . "\n" . $scenario . "\n" . 'echo json_encode( array( "result" => $result, "calls" => $GLOBALS["calls"], "logs" => $GLOBALS["logs"] ) ); }';
		exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_tracking_uses_only_direct_instant_get_and_preserves_full_object_body(): void {
		foreach ( array( '$tracking', 'json_decode( json_encode( $tracking ) )' ) as $body ) {
			$fixture = $this->run_fixture( '$GLOBALS["transport"] = array( true, ' . $body . ' ); $response = $repository->tracking( "order-1" ); $result = array( "object" => is_object( $response["data"] ), "response" => $response, "expected" => $tracking );' );
			$this->assertTrue( $fixture['result']['object'] );
			$this->assertTrue( $fixture['result']['response']['status'] );
			$this->assertSame( $fixture['result']['expected'], $fixture['result']['response']['data'] );
			$this->assertSame( array( array( 'GET', 'api/mitra/v4/instant/tracking/order-1', null ) ), $fixture['calls'] );
			$this->assertSame( array(), $fixture['logs'] );
		}
	}

	public function test_cancellation_verifies_one_package_without_rewriting_remote_105(): void {
		foreach ( array( 'gosend', 'grab_express' ) as $service ) {
			$fixture = $this->run_fixture( '$cancel["code"] = "0"; $cancel["result"]["packages"][0]["service"] = ' . var_export( $service, true ) . '; $GLOBALS["transport"] = array( true, json_decode( json_encode( $cancel ) ) ); $response = $repository->cancel( "order-1" ); $result = array( "object" => is_object( $response["data"] ), "response" => $response, "expected" => $cancel );' );
			$this->assertTrue( $fixture['result']['object'] );
			$this->assertTrue( $fixture['result']['response']['status'] );
			$this->assertTrue( $fixture['result']['response']['operation_accepted'] );
			$this->assertSame( $fixture['result']['expected'], $fixture['result']['response']['data'] );
			$this->assertSame( 105, $fixture['result']['response']['data']['result']['packages'][0]['status'] );
			$this->assertArrayNotHasKey( 'remote_canceled', $fixture['result']['response'] );
			$this->assertSame( array( array( 'DELETE', 'api/mitra/v4/instant/pickup/void/order-1', null ) ), $fixture['calls'] );
			$this->assertSame( array(), $fixture['logs'] );
		}
	}

	public function test_tracking_not_found_is_only_the_documented_code_and_explicit_null_result(): void {
		foreach ( array( 2, '2' ) as $code ) {
			$fixture = $this->run_fixture( '$GLOBALS["transport"] = array( true, array( "status" => false, "code" => ' . var_export( $code, true ) . ', "result" => null, "text" => "private recipient" ) ); $result = $repository->tracking( "order-1" );' );
			$this->assertSame( array( 'status' => false, 'data' => 'Instant tracking data was not found.', 'not_found' => true ), $fixture['result'] );
			$this->assertCount( 1, $fixture['calls'] );
			$this->assertSame( array(), $fixture['logs'] );
		}
	}

	public function test_invalid_identifiers_never_reach_transport(): void {
		foreach ( array( '', ' ', ' order-1', 'order-1 ', '../order', 'order/1', 'order?1', 'order%2f1', "order\n1", str_repeat( 'a', 101 ) ) as $id ) {
			foreach ( array( 'tracking', 'cancel' ) as $method ) {
				$fixture = $this->run_fixture( '$result = $repository->' . $method . '( ' . var_export( $id, true ) . ' );' );
				$this->assertSame( array( 'status' => false, 'data' => 'Invalid Instant order ID.' ), $fixture['result'] );
				$this->assertSame( array(), $fixture['calls'] );
				$this->assertSame( array(), $fixture['logs'] );
			}
		}
		$fixture = $this->run_fixture( '$tracking["result"]["order_id"] = str_repeat( "a", 100 ); $tracking["code"] = "0"; $GLOBALS["transport"] = array( true, $tracking ); $result = $repository->tracking( str_repeat( "a", 100 ) );' );
		$this->assertTrue( $fixture['result']['status'] );
	}

	public function test_tracking_rejects_malformed_and_mismatched_bodies_without_pii_or_logs(): void {
		$mutations = array(
			'$tracking = "private recipient";', '$tracking = array();', '$tracking = array( $tracking );',
			'unset( $tracking["result"] );', '$tracking["result"] = null;', '$tracking["result"] = array( $tracking["result"] );',
			'$tracking["result"]["order_id"] = "other-order";', '$tracking["result"]["order_id"] = array( "order-1" );',
			'$tracking["status"] = "true";', '$tracking["status"] = 1;', '$tracking["status"] = false;',
			'unset( $tracking["code"] );', '$tracking["code"] = false;', '$tracking["code"] = "00";', '$tracking["code"] = 0.0;',
			'$tracking["code"] = 2;', '$tracking["code"] = 2; unset( $tracking["result"] );',
			'$tracking["code"] = "02"; $tracking["result"] = null;',
		);
		foreach ( $mutations as $mutation ) {
			$this->assert_sanitized_failure( 'tracking', '$tracking', $mutation );
		}
	}

	public function test_cancel_rejects_unverified_packages_and_malformed_responses(): void {
		$mutations = array(
			'$cancel = "private recipient";', 'unset( $cancel["result"] );', '$cancel["result"] = null;',
			'unset( $cancel["result"]["packages"] );', '$cancel["result"]["packages"] = array();',
			'$cancel["result"]["packages"][] = $cancel["result"]["packages"][0];',
			'$cancel["result"]["packages"] = array( "row" => $cancel["result"]["packages"][0] );',
			'$cancel["result"]["packages"][0] = "private recipient";',
			'$cancel["result"]["packages"][0]["order_id"] = "other-order";',
			'unset( $cancel["result"]["packages"][0]["order_id"] );',
			'$cancel["result"]["packages"][0]["service"] = "jne";',
			'unset( $cancel["result"]["packages"][0]["service"] );',
			'$cancel["status"] = false;', '$cancel["status"] = "true";', '$cancel["status"] = 1;',
			'unset( $cancel["code"] );', '$cancel["code"] = 2;', '$cancel["code"] = "00";', '$cancel["code"] = false;',
		);
		foreach ( $mutations as $mutation ) {
			$this->assert_sanitized_failure( 'cancel', '$cancel', $mutation );
		}
	}

	public function test_transport_errors_and_timeouts_are_sanitized_and_never_retried(): void {
		foreach ( array( 'tracking' => '$tracking', 'cancel' => '$cancel' ) as $method => $body ) {
			foreach ( array( '$GLOBALS["transport"][0] = false;', '$GLOBALS["transport"] = array( false, "private recipient 08123456789 address secret" );', '$GLOBALS["throw"] = true;' ) as $mutation ) {
				$this->assert_sanitized_failure( $method, $body, '', $mutation );
			}
		}
	}

	private function assert_sanitized_failure( string $method, string $body, string $mutation, string $transport_mutation = '' ): void {
		$fixture = $this->run_fixture( $mutation . ' $GLOBALS["transport"] = array( true, ' . $body . ' ); ' . $transport_mutation . ' $result = $repository->' . $method . '( "order-1" );' );
		$this->assertSame( array( 'status' => false, 'data' => 'tracking' === $method ? 'Instant tracking failed.' : 'Instant cancellation failed.' ), $fixture['result'] );
		$this->assertCount( 1, $fixture['calls'] );
		$this->assertSame( array(), $fixture['logs'] );
	}
}
