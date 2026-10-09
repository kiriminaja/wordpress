<?php

use PHPUnit\Framework\TestCase;

/** Real bounded transport and repository, with only its HTTP client replaced. */
final class InstantBookingTransportRuntimeTest extends TestCase {
	private function run_fixture( int $http_status, string $body, string $mode = '' ): array {
		$fixture = <<<'PHP'
namespace {
	define( 'ABSPATH', ROOT );
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
		$text = strip_tags( $text );
		return trim( $remove_breaks ? preg_replace( '/[\\r\\n\\t ]+/', ' ', $text ) : $text );
	}
	function kiriof_log( ...$args ) { $GLOBALS['logs'][] = $args; }
	require ROOT . '/vendor/autoload.php';
	require ROOT . '/inc/Infrastructure/InstantDiagnosticRedactor.php';
	// Give the unchanged production transport a distinct name, so the repository's
	// literal new InstantApiTransport() resolves to its HTTP-client-only subclass.
	$source = file_get_contents( ROOT . '/inc/Infrastructure/InstantApiTransport.php' );
	eval( str_replace( array( '<?php', 'class InstantApiTransport extends Api' ), array( '', 'class DirectInstantApiTransport extends Api' ), $source ) );
}
namespace KiriminAjaOfficial\Infrastructure {
	class InstantApiTransport extends DirectInstantApiTransport {
		protected function url( $endpoint ): string { return ( 'insecure' === MODE ? 'http' : 'https' ) . '://example.invalid/' . $endpoint; }
		protected static function createClient( array $options ): \Psr\Http\Client\ClientInterface {
			if ( 'client_exception' === MODE ) { throw new \RuntimeException( 'private-token 123456 client failure' ); }
			return new class() implements \Psr\Http\Client\ClientInterface {
				public function sendRequest( \Psr\Http\Message\RequestInterface $request ): \Psr\Http\Message\ResponseInterface {
					$GLOBALS['calls'][] = array( $request->getMethod(), $request->getUri()->getPath() );
					if ( 'timeout' === MODE ) { throw new \RuntimeException( 'private-token 123456 timeout' ); }
					return new \Nyholm\Psr7\Response( HTTP_STATUS, array(), BODY );
				}

	public function test_observed_validation_rejection_without_result_is_definitive_and_diagnostic(): void {
		foreach ( array( 200, 400, 422 ) as $status ) {
			$fixture = $this->run_fixture( $status, '{"message":"Terdapat kesalahan pada data yang dikirimkan","errors":{"address_note":"address_note wajib diisi"},"status":false}' );
			$this->assertTrue( $fixture['response']['operation_rejected'] );
			$this->assertFalse( $fixture['response']['status'] );
			$this->assertTrue( $fixture['empty_object'] );
			$this->assertCount( 1, $fixture['calls'] );
			$this->assertStringNotContainsString( 'address_note wajib diisi', json_encode( $fixture['response'] ) );
			$summary = $fixture['diagnostics']['error_body'];
			$this->assertSame( array( 'address_note' ), $summary['validation_fields'] );
			$this->assertSame( array( 'address_note' => array( 'address_note wajib diisi' ) ), $summary['validation_messages'] );
			$this->assertSame( 'Terdapat kesalahan pada data yang dikirimkan', $summary['messages']['message'] );
		}
		$body = array( 'status'=>false, 'errors'=>array( 'packages.0.destination.address_note'=>array('address_note wajib diisi Private name Private address PIN 123456 private-token') ) );
		$fixture = $this->run_fixture( 422, json_encode( $body ) );
		$this->assertTrue( $fixture['response']['operation_rejected'] );
		$this->assertContains( 'packages.destination.address_note', $fixture['diagnostics']['error_body']['validation_fields'] );
		$redactions = array();
		foreach ( array( 'Private name', 'Private address', '123456', 'private-token' ) as $secret ) {
			$redactions[ $secret ] = str_contains( json_encode( $fixture ), $secret );
		}
		$this->assertSame( array_fill_keys( array_keys( $redactions ), false ), $redactions, 'Diagnostic redaction contract' );
		foreach ( array(
			array('status'=>true), array('status'=>'false'), array('result'=>null), array('result'=>array('order_id'=>'remote')), array('payment_id'=>'remote'), array('packages'=>array()),
			array('errors'=>array()), array('errors'=>array('address_note'=>'')), array('errors'=>array('address_note'=>123)), array('errors'=>array('address_note'=>array('payment_id'=>'remote'))),
			array('errors'=>array('unknown'=> 'error')), array('extra'=>'remote'), array('message'=>array('invalid')),
		) as $change ) {
			$fixture = $this->run_fixture( 400, json_encode( array_replace( array('status'=>false,'errors'=>array('address_note'=>'address_note wajib diisi')), $change ) ) );
			$this->assertArrayNotHasKey( 'operation_rejected', $fixture['response'], json_encode( $change ) );
			$this->assertFalse( $fixture['response']['status'] );
		}
		foreach ( array( 401, 429, 500 ) as $status ) {
			$this->assertArrayNotHasKey( 'operation_rejected', $this->run_fixture( $status, json_encode( $body ) )['response'] );
		}
	}

			};
		}
	}

}
namespace {
	\KiriminAja\Base\Config\Cache\Cache::setStore( new class() implements \KiriminAja\Contracts\CacheStoreContract {
		private array $values = array();
		public function get( string $key ): mixed { return $this->values[ $key ] ?? null; }
		public function put( string $key, mixed $value, int $expiry ): bool { $this->values[ $key ] = $value; return true; }
		public function remove( string $key ): bool { unset( $this->values[ $key ] ); return true; }
	} );
	\KiriminAja\Base\Config\KiriminAjaConfig::setApiTokenKey( 'private-token' );
	require ROOT . '/inc/Base/KiriminAjaApi.php';
	require ROOT . '/inc/Repositories/InstantDeliveryApiRepository.php';
	class BookingFixtureRepository extends \KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository {
		public function __construct() {}
	}
	$GLOBALS['calls'] = $GLOBALS['logs'] = array();
	$location = array( 'name' => 'Private name', 'phone' => '081234567890', 'address' => 'Private address', 'latitude' => -7.8, 'longitude' => 110.3 );
	$payload = array_merge( $location, array( 'payment_method' => 'credit', 'pin' => '123456', 'packages' => array( array( 'service' => 'gosend', 'destination' => $location ) ) ) );
	if ( 'invalid_local' === MODE ) { unset( $payload['latitude'] ); }
	$repository = new BookingFixtureRepository();
	$response = $repository->book( $payload );
	echo json_encode( array( 'response' => $response, 'diagnostics' => $repository->bookingDiagnostics(), 'calls' => $GLOBALS['calls'], 'logs' => $GLOBALS['logs'], 'object' => is_object( $response['data'] ), 'empty_object' => is_object( $response['data'] ) && is_object( $response['data']->result ?? null ) ) );
}
PHP;
		$code = 'namespace { define( "ROOT", ' . var_export( PLUGIN_DIR, true ) . ' ); define( "HTTP_STATUS", ' . $http_status . ' ); define( "BODY", ' . var_export( $body, true ) . ' ); define( "MODE", ' . var_export( $mode, true ) . ' ); }' . $fixture;
		exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_only_proven_pre_send_failures_allow_safe_retry(): void {
		foreach ( array( 'invalid_local', 'insecure', 'client_exception' ) as $mode ) {
			$fixture = $this->run_fixture( 200, '{}', $mode );
			$this->assertSame(
				array(
					'status' => false,
					'operation_not_submitted' => true,
					'calls' => array(),
					'logs' => array(),
				),
				array(
					'status' => $fixture['response']['status'],
					'operation_not_submitted' => $fixture['response']['operation_not_submitted'],
					'calls' => $fixture['calls'],
					'logs' => $fixture['logs'],
				)
			);
			if ( 'invalid_local' !== $mode ) {
				$this->assertFalse( $fixture['diagnostics']['submitted'] );
			}
			$this->assertSame(
				array(
					'redacts private-token' => false,
					'redacts 123456' => false,
				),
				array(
					'redacts private-token' => str_contains( json_encode( $fixture ), 'private-token' ),
					'redacts 123456' => str_contains( json_encode( $fixture ), '123456' ),
				)
			);
		}
	}

	public function test_timeout_after_send_starts_is_ambiguous(): void {
		$fixture = $this->run_fixture( 200, '{}', 'timeout' );
		$this->assertSame(
			array(
				'response' => array( 'status' => false, 'data' => 'Instant booking failed.' ),
				'submitted' => true,
				'code' => 'transport_exception',
				'calls count' => 1,
				'logs' => array(),
			),
			array(
				'response' => $fixture['response'],
				'submitted' => $fixture['diagnostics']['submitted'],
				'code' => $fixture['diagnostics']['code'],
				'calls count' => count( $fixture['calls'] ),
				'logs' => $fixture['logs'],
			)
		);
	}

	public function test_http_success_with_explicit_empty_negative_is_safely_preserved(): void {
		foreach ( array( array(200, '{}'), array(200, '[]'), array(400, '{}'), array(422, '[]') ) as $case ) {
			$fixture = $this->run_fixture( $case[0], '{"status":false,"code":400,"message":"PIN 123456 private-token","text":"Private address","result":' . $case[1] . '}' );
			$this->assertSame(
				array(
					'response' => array( 'status' => false, 'data' => array( 'status' => false, 'result' => array() ), 'operation_rejected' => true ),
					'object' => true,
					'empty_object' => true,
					'calls' => array( array( 'POST', '/api/mitra/v6.2/instant/request_pickup' ) ),
					'logs' => array(),
					'code' => $case[0] >= 400 ? 'http_failure' : 'transport_success',
					'http_status' => $case[0],
					'acknowledged' => false,
					'redacts 123456' => false,
					'redacts private-token' => false,
				),
				array(
					'response' => $fixture['response'],
					'object' => $fixture['object'],
					'empty_object' => $fixture['empty_object'],
					'calls' => $fixture['calls'],
					'logs' => $fixture['logs'],
					'code' => $fixture['diagnostics']['code'],
					'http_status' => $fixture['diagnostics']['http_status'],
					'acknowledged' => $fixture['diagnostics']['acknowledged'],
					'redacts 123456' => str_contains( json_encode( $fixture ), '123456' ),
					'redacts private-token' => str_contains( json_encode( $fixture ), 'private-token' ),
				)
			);
		}
	}

	public function test_http_failures_and_unproven_acknowledgements_stay_unknown(): void {
		foreach ( array(
			array( 400, '{"status":false,"code":400,"result":null}' ),
			array( 400, '{"status":false,"code":400,"result":{},"payment_id":"secret-remote"}' ),
			array( 400, '{"status":true,"result":{"payment":{"id":"secret-remote"}}}' ),
			array( 400, 'broken PIN 123456' ),
			array( 401, '{"status":false,"result":{}}' ),
			array( 429, '{"status":false,"result":{}}' ),
			array( 500, '{"status":false,"result":{}}' ),
			array( 200, '{"status":false,"code":400,"message":"PIN 123456","result":null}' ),
			array( 200, '{"status":false}' ),
			array( 200, '{"status":false,"result":{"payment_id":"remote-payment"}}' ),
			array( 200, '{"status":false,"result":{},"packages":[{"order_id":"remote-order"}]}' ),
			array( 200, '{"status":"false","result":{}}' ),
			array( 200, '{"status":false,"code":400.0,"result":{}}' ),
			array( 200, 'broken PIN 123456' ),
		) as $case ) {
			$fixture = $this->run_fixture( $case[0], $case[1] );
			$this->assertSame(
				array(
					'response' => array( 'status' => false, 'data' => 'Instant booking failed.' ),
					'calls count' => 1,
					'logs' => array(),
					'submitted' => true,
					'code' => $case[0] >= 400 ? 'http_failure' : ( str_starts_with( $case[1], 'broken' ) ? 'invalid_json' : 'transport_success' ),
					'redacts 123456' => false,
				),
				array(
					'response' => $fixture['response'],
					'calls count' => count( $fixture['calls'] ),
					'logs' => $fixture['logs'],
					'submitted' => $fixture['diagnostics']['submitted'],
					'code' => $fixture['diagnostics']['code'],
					'redacts 123456' => str_contains( json_encode( $fixture['diagnostics'] ), '123456' ),
				)
			);
		}
	}
	public function test_actual_upstream_explanation_is_safe_in_runtime_diagnostics(): void {
		$prose = 'Akun Anda belum aktif untuk layanan instant. payment_method is not available for this account. PIN is invalid.';
		$fixture = $this->run_fixture( 422, json_encode( array(
			'status' => false,
			'message' => $prose . ' Private name Private address 081234567890 -7.8 110.3 PIN "123456" token labeltoken private-token',
		) ) );
		$this->assertSame(
			array(
				'response' => array( 'status' => false, 'data' => 'Instant booking failed.' ),
				'contains $prose' => true,
			),
			array(
				'response' => $fixture['response'],
				'contains $prose' => str_contains( $fixture['diagnostics']['error_body']['messages']['message'], $prose ),
			)
		);
		$redactions = array();
		foreach ( array( 'Private name', 'Private address', '081234567890', '-7.8', '110.3', '123456', 'labeltoken', 'private-token' ) as $secret ) {
			$redactions[ $secret ] = str_contains( json_encode( $fixture ), $secret );
		}
		$this->assertSame( array_fill_keys( array_keys( $redactions ), false ), $redactions, 'Diagnostic redaction contract' );
		$this->assertSame( array(), $fixture['logs'] );
	}
}
