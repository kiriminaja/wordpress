<?php

use PHPUnit\Framework\TestCase;

/** Real repository with an isolated SDK transport spy (no network or SDK facade). */
final class InstantLifecycleApiRuntimeTest extends TestCase {
	private function run_fixture( string $scenario ): array {
		// Reuse only an identical scenario's decoded result, not its SDK/global state.
		// Every cache miss still gets its own PHP process and fresh repository.
		static $snapshots = array();
		if ( isset( $snapshots[ $scenario ] ) ) {
			return $snapshots[ $scenario ];
		}
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
	class InstantApiTransport extends \KiriminAja\Base\Api\Api {
		public function diagnostics(): array { return $GLOBALS['diagnostics']; }
		public function errorResponse(): ?array { $GLOBALS['error_reads']++; return $GLOBALS['error_response']; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
	function kiriof_log( ...$args ) { $GLOBALS['logs'][] = $args; }
	require ROOT . '/vendor/autoload.php';
	\KiriminAja\Base\Config\Cache\Cache::setStore( new class implements \KiriminAja\Contracts\CacheStoreContract {
		private array $values = array();
		public function get( string $key ): mixed { return $this->values[ $key ] ?? null; }
		public function put( string $key, mixed $value, int $expiry ): bool { $this->values[ $key ] = $value; return true; }
		public function remove( string $key ): bool { unset( $this->values[ $key ] ); return true; }
	} );
	\KiriminAja\Base\Config\KiriminAjaConfig::setApiTokenKey( 'configured-secret-token' );
	require ROOT . '/inc/Base/KiriminAjaApi.php';
	require ROOT . '/inc/Repositories/InstantDeliveryApiRepository.php';
	class LifecycleFixtureRepository extends \KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository {
		public function __construct() {}
		public function get( $endpoint, $body = array(), $log_context = array() ) { throw new \RuntimeException( 'Inherited transport forbidden' ); }
	}
	$GLOBALS['calls'] = array();
	$GLOBALS['logs'] = array();
	$GLOBALS['throw'] = false;
	$GLOBALS['diagnostics'] = array( 'http_status' => 200, 'submitted' => true, 'elapsed_ms' => 7 );
	$GLOBALS['error_response'] = null;
	$GLOBALS['error_reads'] = 0;
	$repository = new LifecycleFixtureRepository();
	$tracking = array( 'status' => true, 'code' => 0, 'text' => 'loaded', 'result' => array( 'order_id' => 'order-1', 'status' => 105, 'driver' => array( 'name' => 'private driver' ), 'history' => array( array( 'status' => 105 ) ) ), 'extra' => array( 'full' => true ) );
	$cancel = array( 'status' => true, 'code' => 0, 'text' => 'void accepted', 'result' => array( 'payment_id' => 'payment-1', 'packages' => array( array( 'order_id' => 'order-1', 'service' => 'gosend', 'status' => 105 ) ) ), 'extra' => array( 'full' => true ) );
PHP;
		$code = 'namespace { define( "ROOT", ' . var_export( PLUGIN_DIR, true ) . ' ); }' . $fixture . "\n" . $scenario . "\n" . 'echo json_encode( array( "result" => $result, "calls" => $GLOBALS["calls"], "logs" => $GLOBALS["logs"], "diagnostics" => $repository->cancellationDiagnostics(), "error_reads" => $GLOBALS["error_reads"] ) ); }';
		// The SDK transport/cache are fakes; JSON and PCRE are built in. Composer
		// disables platform-check, so no host extensions or php.ini are needed.
		exec( kiriof_test_fixture_php_shell( array( '-r' ) ) . ' ' . escapeshellarg( $code ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return $snapshots[ $scenario ] = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_tracking_uses_only_direct_instant_get_and_preserves_full_object_body(): void {
		foreach ( array( '$tracking', 'json_decode( json_encode( $tracking ) )' ) as $body ) {
			$fixture = $this->run_fixture( '$GLOBALS["transport"] = array( true, ' . $body . ' ); $response = $repository->tracking( "order-1" ); $result = array( "object" => is_object( $response["data"] ), "response" => $response, "expected" => $tracking );' );
			$this->assertSame(
				array(
					'object' => true,
					'status' => true,
					'data' => $fixture['result']['expected'],
					'calls' => array( array( 'GET', 'api/mitra/v4/instant/tracking/order-1', null ) ),
					'logs' => array(),
				),
				array(
					'object' => $fixture['result']['object'],
					'status' => $fixture['result']['response']['status'],
					'data' => $fixture['result']['response']['data'],
					'calls' => $fixture['calls'],
					'logs' => $fixture['logs'],
				)
			);
		}
	}

	public function test_cancellation_verifies_one_package_without_rewriting_remote_105(): void {
		foreach ( array( 'gosend', 'grab_express' ) as $service ) {
			$fixture = $this->run_fixture( '$cancel["code"] = "0"; $cancel["result"]["packages"][0]["service"] = ' . var_export( $service, true ) . '; $GLOBALS["transport"] = array( true, json_decode( json_encode( $cancel ) ) ); $response = $repository->cancel( "order-1" ); $result = array( "object" => is_object( $response["data"] ), "response" => $response, "expected" => $cancel );' );
			$this->assertSame(
				array(
					'object' => true,
					'status' => true,
					'operation_accepted' => true,
					'data' => $fixture['result']['expected'],
					'remote package status' => 105,
					'remote_canceled present' => false,
					'calls' => array( array( 'DELETE', 'api/mitra/v4/instant/pickup/void/order-1', null ) ),
					'logs' => array(),
				),
				array(
					'object' => $fixture['result']['object'],
					'status' => $fixture['result']['response']['status'],
					'operation_accepted' => $fixture['result']['response']['operation_accepted'],
					'data' => $fixture['result']['response']['data'],
					'remote package status' => $fixture['result']['response']['data']['result']['packages'][0]['status'],
					'remote_canceled present' => array_key_exists( 'remote_canceled', $fixture['result']['response'] ),
					'calls' => $fixture['calls'],
					'logs' => $fixture['logs'],
				)
			);
		}
	}

	public function test_tracking_not_found_is_only_the_documented_code_and_explicit_null_result(): void {
		foreach ( array( 2, '2' ) as $code ) {
			$fixture = $this->run_fixture( '$GLOBALS["transport"] = array( true, array( "status" => false, "code" => ' . var_export( $code, true ) . ', "result" => null, "text" => "private recipient" ) ); $result = $repository->tracking( "order-1" );' );
			$this->assertSame(
				array(
					'result' => array( 'status' => false, 'data' => 'Instant tracking data was not found.', 'not_found' => true ),
					'calls count' => 1,
					'logs' => array(),
				),
				array(
					'result' => $fixture['result'],
					'calls count' => count( $fixture['calls'] ),
					'logs' => $fixture['logs'],
				)
			);
		}
	}

	public function test_invalid_identifiers_never_reach_transport(): void {
		foreach ( array( '', ' ', ' order-1', 'order-1 ', '../order', 'order/1', 'order?1', 'order%2f1', "order\n1", str_repeat( 'a', 101 ) ) as $id ) {
			foreach ( array( 'tracking', 'cancel' ) as $method ) {
				$fixture = $this->run_fixture( '$result = $repository->' . $method . '( ' . var_export( $id, true ) . ' );' );
				$this->assertSame(
					array(
						'result' => array( 'status' => false, 'data' => 'Invalid Instant order ID.' ),
						'calls' => array(),
						'logs' => array(),
					),
					array(
						'result' => $fixture['result'],
						'calls' => $fixture['calls'],
						'logs' => $fixture['logs'],
					)
				);
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

	public function test_cancel_classifies_only_strict_explicit_rejections(): void {
		foreach ( array( 1, -2, '2', '-12', '999999999999999999999999' ) as $code ) {
			foreach ( array( 'null', '(object) array()' ) as $result ) {
				$fixture = $this->run_fixture( '$GLOBALS["transport"] = array( true, array( "status" => false, "code" => ' . var_export( $code, true ) . ', "result" => ' . $result . ', "text" => "private PIN 123456 token recipient" ) ); $result = $repository->cancel( "order-1" );' );
				$this->assertSame( array( 'status' => false, 'data' => 'Instant cancellation failed.', 'operation_rejected' => true ), $fixture['result'] );
				$this->assertSame( array( 'reason' => 'api_rejection', 'http_status' => 200, 'submitted' => true, 'elapsed_ms' => 7, 'provider_code' => $code, 'provider_message' => 'private PIN [redacted] token [redacted]' ), $fixture['diagnostics'] );
				$this->assertSame( array(), $fixture['logs'] );
			}
		}
	}

	public function test_cancel_malformed_codes_and_contradictory_identities_remain_ambiguous(): void {
		$mutations = array(
			'unset( $body["code"] );', '$body["code"] = 0;', '$body["code"] = "0";', '$body["code"] = 0.0;',
			'$body["code"] = 2.0;', '$body["code"] = true;', '$body["code"] = false;', '$body["code"] = null;',
			'$body["code"] = "02";', '$body["code"] = "+2";', '$body["code"] = " 2";', '$body["code"] = "2 ";',
			'$body["code"] = "2.0";', '$body["code"] = "2e0";', '$body["code"] = "-0";', '$body["code"] = array( 2 );',
			'unset( $body["result"] );', '$body["result"] = array();', '$body["result"] = "";', '$body["result"] = false;',
			'$body["result"] = array( "order_id" => "private-order" );', '$body["result"] = array( "payment_id" => "private-payment" );',
			'$body["order_id"] = "private-order";', '$body["payment_id"] = "private-payment";', '$body["id"] = "private-id";',
			'$body["packages"] = array( array( "order_id" => "private-order" ) );', '$body["results"] = array( "payment_id" => "private-payment" );',
			'$body["status"] = 0;', '$body["status"] = "false";', '$body["status"] = true;',
		);
		foreach ( $mutations as $mutation ) {
			$fixture = $this->run_fixture( '$body = array( "status" => false, "code" => 2, "result" => null ); ' . $mutation . ' $GLOBALS["transport"] = array( true, $body ); $result = $repository->cancel( "order-1" );' );
			$this->assertSame( array( 'status' => false, 'data' => 'Instant cancellation failed.' ), $fixture['result'], $mutation );
			$this->assertSame( 'invalid_response', $fixture['diagnostics']['reason'], $mutation );
		}
	}

	public function test_cancel_supplied_http_400_preserves_actual_provider_reason_privately(): void {
		$fixture = $this->run_fixture( '
			$GLOBALS["diagnostics"]["http_status"] = 400;
			$GLOBALS["transport"] = array( false, "Instant network request failed." );
			$GLOBALS["error_response"] = array( "status" => false, "text" => "cannot cancel gosend order", "code" => 2, "method" => null, "result" => null );
			$result = $repository->cancel( "order-1" );' );
		$this->assertSame( array( 'status' => false, 'data' => 'Instant cancellation failed.', 'operation_rejected' => true ), $fixture['result'] );
		$this->assertSame( array( 'reason' => 'api_rejection', 'http_status' => 400, 'submitted' => true, 'elapsed_ms' => 7, 'provider_code' => 2, 'provider_message' => 'cannot cancel gosend order' ), $fixture['diagnostics'] );
		$this->assertSame( 1, $fixture['error_reads'] );
		$this->assertCount( 1, $fixture['calls'] );
		$this->assertSame( array(), $fixture['logs'] );
	}

	public function test_cancel_rejection_message_sources_are_redacted_and_bounded(): void {
		foreach ( array( 'text', 'message', 'statusMessage' ) as $field ) {
			foreach ( array( false, true ) as $summary ) {
				$message = "<b>cannot cancel</b> order-1 configured-secret-token Bearer another-secret person@example.test +628123456789 address 12345678\x00\r\n" . str_repeat( 'long ', 600 );
				$fixture = $this->run_fixture( '
					$GLOBALS["diagnostics"]["http_status"] = 400;
					$GLOBALS["transport"] = array( false, "private transport summary" );
					$GLOBALS["error_response"] = array( "status" => false, "code" => "2", "result" => null, ' . var_export( $field, true ) . ' => ' . var_export( $message, true ) . ', "unknown_private" => null );
					' . ( $summary ? '$GLOBALS["diagnostics"]["error_body"] = array( "messages" => array( ' . var_export( $field, true ) . ' => ' . var_export( $message, true ) . ', "unknown_private" => "never copy" ), "customer" => "never copy" );' : '' ) . '
					$result = $repository->cancel( "order-1" );' );
				$this->assertTrue( $fixture['result']['operation_rejected'] );
				$this->assertSame( '2', $fixture['diagnostics']['provider_code'] );
				$safe = $fixture['diagnostics']['provider_message'];
				$this->assertStringStartsWith( 'cannot cancel [redacted]', $safe );
				$this->assertLessThanOrEqual( 2048, strlen( $safe ) );
				foreach ( array( 'order-1', 'configured-secret-token', 'another-secret', 'person@example.test', '628123456789', '12345678', 'never copy', '<b>', "\x00", "\r", "\n" ) as $private ) {
					$this->assertStringNotContainsString( $private, $safe );
				}
				$this->assertCount( 6, $fixture['diagnostics'] );
				$this->assertSame( array(), $fixture['logs'] );
			}
		}
	}

	public function test_cancel_rejection_diagnostics_reset_and_do_not_fabricate_messages(): void {
		$fixture = $this->run_fixture( '
			$GLOBALS["transport"] = array( true, array( "status" => false, "code" => 2, "result" => null, "text" => "cannot cancel gosend order" ) );
			$repository->cancel( "order-1" );
			$first = $repository->cancellationDiagnostics();
			$GLOBALS["transport"] = array( true, array( "status" => false, "code" => str_repeat( "9", 65 ), "result" => null ) );
			$repository->cancel( "order-1" );
			$missing = $repository->cancellationDiagnostics();
			$GLOBALS["transport"] = array( true, $cancel );
			$repository->cancel( "order-1" );
			$accepted = $repository->cancellationDiagnostics();
			$result = array( $first, $missing, $accepted );' );
		$this->assertSame( 'cannot cancel gosend order', $fixture['result'][0]['provider_message'] );
		$this->assertSame( array( 'reason' => 'api_rejection', 'http_status' => 200, 'submitted' => true, 'elapsed_ms' => 7 ), $fixture['result'][1] );
		$this->assertSame( array( 'reason' => 'accepted', 'http_status' => 200, 'submitted' => true, 'elapsed_ms' => 7 ), $fixture['result'][2] );
	}

	public function test_cancel_prefers_transport_recognized_message_and_ignores_private_summary_fields(): void {
		$fixture = $this->run_fixture( '
			$GLOBALS["transport"] = array( true, array( "status" => false, "code" => str_repeat( "9", 64 ), "result" => null, "text" => "raw private explanation" ) );
			$GLOBALS["diagnostics"]["error_body"] = array( "messages" => array( "text" => "cannot cancel gosend order order-1", "unknown_private" => "never copy" ), "customer" => "never copy" );
			$result = $repository->cancel( "order-1" );' );
		$this->assertSame( str_repeat( '9', 64 ), $fixture['diagnostics']['provider_code'] );
		$this->assertSame( 'cannot cancel gosend order [redacted]', $fixture['diagnostics']['provider_message'] );
		$this->assertCount( 6, $fixture['diagnostics'] );
		$this->assertSame( array(), $fixture['logs'] );
	}

	public function test_cancel_http_errors_require_safe_status_and_structured_null_result(): void {
		foreach ( array( 200, 201, 204, 301, 400, 401, 403, 404, 408, 409, 422, 425, 429, 499, 500, 502, 503, 504 ) as $http ) {
			$rejectable = $http >= 400 && $http < 500 && ! in_array( $http, array( 408, 409, 425, 429 ), true );
			foreach ( array( 'null', '(object) array()', 'array( "payment_id" => "private-payment" )' ) as $body_result ) {
				$fixture = $this->run_fixture( '$GLOBALS["diagnostics"]["http_status"] = ' . $http . '; $GLOBALS["transport"] = array( false, "private failure" ); $GLOBALS["error_response"] = array( "status" => false, "code" => "2", "result" => ' . $body_result . ' ); $result = $repository->cancel( "order-1" );' );
				$rejected = $rejectable && 'null' === $body_result;
				$expected = array( 'status' => false, 'data' => 'Instant cancellation failed.' );
				if ( $rejected ) {
					$expected['operation_rejected'] = true;
				}
				$this->assertSame( $expected, $fixture['result'], (string) $http . $body_result );
				$this->assertSame( $rejected ? 'api_rejection' : 'transport_failure', $fixture['diagnostics']['reason'] );
				$this->assertSame( $rejectable ? 1 : 0, $fixture['error_reads'] );
				$this->assertCount( 1, $fixture['calls'] );
			}
		}
	}

	public function test_cancel_diagnostics_reset_whitelist_and_pre_submission_proof(): void {
		$fixture = $this->run_fixture( '
			$GLOBALS["diagnostics"] = array( "http_status" => 503, "submitted" => true, "elapsed_ms" => 12, "code" => "private token", "error_body" => array( "PIN" => "123456", "customer" => "recipient" ), "order_id" => "private-order" );
			$GLOBALS["transport"] = array( false, "private error" );
			$first = $repository->cancel( "order-1" );
			$first_diagnostics = $repository->cancellationDiagnostics();
			$GLOBALS["diagnostics"] = array( "http_status" => null, "submitted" => false, "elapsed_ms" => 3 );
			$second = $repository->cancel( "order-1" );
			$second_diagnostics = $repository->cancellationDiagnostics();
			$third = $repository->cancel( "../private-order" );
			$result = array( $first, $first_diagnostics, $second, $second_diagnostics, $third );' );
		$this->assertSame( array( 'reason' => 'transport_failure', 'http_status' => 503, 'submitted' => true, 'elapsed_ms' => 12 ), $fixture['result'][1] );
		$this->assertSame( array( 'status' => false, 'data' => 'Instant cancellation failed.', 'operation_rejected' => true ), $fixture['result'][2] );
		$this->assertSame( array( 'reason' => 'request_not_submitted', 'http_status' => null, 'submitted' => false, 'elapsed_ms' => 3 ), $fixture['result'][3] );
		$this->assertSame( array( 'reason' => 'invalid_identity', 'http_status' => null, 'submitted' => false, 'elapsed_ms' => 0 ), $fixture['diagnostics'] );
		$this->assertSame( array(), $fixture['logs'] );
		$this->assertStringNotContainsString( 'private', json_encode( array( $fixture['result'], $fixture['diagnostics'] ) ) );
	}

	public function test_cancel_diagnostics_cover_acceptance_identity_and_untrusted_fact_types(): void {
		$fixture = $this->run_fixture( '
			$GLOBALS["transport"] = array( true, $cancel );
			$repository->cancel( "order-1" );
			$accepted = $repository->cancellationDiagnostics();
			$cancel["result"]["packages"][0]["order_id"] = "other-order";
			$GLOBALS["transport"] = array( true, $cancel );
			$repository->cancel( "order-1" );
			$identity = $repository->cancellationDiagnostics();
			$GLOBALS["diagnostics"] = array( "http_status" => "422", "submitted" => "false", "elapsed_ms" => "private", "error_body" => "PIN 123456 token private" );
			$GLOBALS["transport"] = array( false, "private" );
			$response = $repository->cancel( "order-1" );
			$result = array( $accepted, $identity, $response );' );
		$this->assertSame( array( 'reason' => 'accepted', 'http_status' => 200, 'submitted' => true, 'elapsed_ms' => 7 ), $fixture['result'][0] );
		$this->assertSame( array( 'reason' => 'invalid_identity', 'http_status' => 200, 'submitted' => true, 'elapsed_ms' => 7 ), $fixture['result'][1] );
		$this->assertSame( array( 'status' => false, 'data' => 'Instant cancellation failed.' ), $fixture['result'][2] );
		$this->assertSame( 'transport_failure', $fixture['diagnostics']['reason'] );
		$this->assertNull( $fixture['diagnostics']['http_status'] );
		$this->assertTrue( $fixture['diagnostics']['submitted'] );
		$this->assertIsInt( $fixture['diagnostics']['elapsed_ms'] );
		$this->assertGreaterThanOrEqual( 0, $fixture['diagnostics']['elapsed_ms'] );
		$this->assertCount( 4, $fixture['diagnostics'] );
		$this->assertSame( array(), $fixture['logs'] );
	}

	public function test_cancel_exceptions_use_only_explicit_submission_facts(): void {
		foreach ( array( true, false ) as $submitted ) {
			$fixture = $this->run_fixture( '$GLOBALS["throw"] = true; $GLOBALS["diagnostics"]["submitted"] = ' . var_export( $submitted, true ) . '; $result = $repository->cancel( "order-1" );' );
			$this->assertSame( ! $submitted, $fixture['result']['operation_rejected'] ?? false );
			$this->assertSame( $submitted ? 'transport_failure' : 'request_not_submitted', $fixture['diagnostics']['reason'] );
			$this->assertSame( array(), $fixture['logs'] );
		}
	}

	private function assert_sanitized_failure( string $method, string $body, string $mutation, string $transport_mutation = '' ): void {
		$fixture = $this->run_fixture( $mutation . ' $GLOBALS["transport"] = array( true, ' . $body . ' ); ' . $transport_mutation . ' $result = $repository->' . $method . '( "order-1" );' );
		$this->assertSame(
			array(
				'result' => array( 'status' => false, 'data' => 'tracking' === $method ? 'Instant tracking failed.' : 'Instant cancellation failed.' ),
				'calls count' => 1,
				'logs' => array(),
			),
			array(
				'result' => $fixture['result'],
				'calls count' => count( $fixture['calls'] ),
				'logs' => $fixture['logs'],
			)
		);
	}
}
