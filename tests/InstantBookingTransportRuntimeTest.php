<?php

use PHPUnit\Framework\TestCase;

/** Real bounded transport and repository, with only its HTTP client replaced. */
final class InstantBookingTransportRuntimeTest extends TestCase {
	private function run_fixture( int $http_status, string $body ): array {
		$fixture = <<<'PHP'
namespace {
	define( 'ABSPATH', ROOT );
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function kiriof_log( ...$args ) { $GLOBALS['logs'][] = $args; }
	require ROOT . '/vendor/autoload.php';
	// Give the unchanged production transport a distinct name, so the repository's
	// literal new InstantApiTransport() resolves to its HTTP-client-only subclass.
	$source = file_get_contents( ROOT . '/inc/Infrastructure/InstantApiTransport.php' );
	eval( str_replace( array( '<?php', 'class InstantApiTransport extends Api' ), array( '', 'class DirectInstantApiTransport extends Api' ), $source ) );
}
namespace KiriminAjaOfficial\Infrastructure {
	class InstantApiTransport extends DirectInstantApiTransport {
		protected function url( $endpoint ): string { return 'https://example.invalid/' . $endpoint; }
		protected static function createClient( array $options ): \Psr\Http\Client\ClientInterface {
			return new class() implements \Psr\Http\Client\ClientInterface {
				public function sendRequest( \Psr\Http\Message\RequestInterface $request ): \Psr\Http\Message\ResponseInterface {
					$GLOBALS['calls'][] = array( $request->getMethod(), $request->getUri()->getPath() );
					return new \Nyholm\Psr7\Response( HTTP_STATUS, array(), BODY );
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
	$response = ( new BookingFixtureRepository() )->book( $payload );
	echo json_encode( array( 'response' => $response, 'calls' => $GLOBALS['calls'], 'logs' => $GLOBALS['logs'], 'object' => is_object( $response['data'] ), 'empty_object' => is_object( $response['data'] ) && is_object( $response['data']->result ?? null ) ) );
}
PHP;
		$code = 'namespace { define( "ROOT", ' . var_export( PLUGIN_DIR, true ) . ' ); define( "HTTP_STATUS", ' . $http_status . ' ); define( "BODY", ' . var_export( $body, true ) . ' ); }' . $fixture;
		exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_http_success_with_explicit_empty_negative_is_safely_preserved(): void {
		foreach ( array( '{}', '[]' ) as $result ) {
			$fixture = $this->run_fixture( 200, '{"status":false,"code":400,"message":"PIN 123456 private-token","text":"Private address","result":' . $result . '}' );
			$this->assertSame( array( 'status' => false, 'data' => array( 'status' => false, 'result' => array() ), 'operation_rejected' => true ), $fixture['response'] );
			$this->assertTrue( $fixture['object'] );
			$this->assertTrue( $fixture['empty_object'] );
			$this->assertSame( array( array( 'POST', '/api/mitra/v6.2/instant/request_pickup' ) ), $fixture['calls'] );
			$this->assertSame( array(), $fixture['logs'] );
			$this->assertStringNotContainsString( '123456', json_encode( $fixture ) );
			$this->assertStringNotContainsString( 'private-token', json_encode( $fixture ) );
		}
	}

	public function test_http_failures_and_unproven_acknowledgements_stay_unknown(): void {
		foreach ( array(
			array( 400, '{"status":false,"code":400,"result":{}}' ),
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
			$this->assertSame( array( 'status' => false, 'data' => 'Instant booking failed.' ), $fixture['response'] );
			$this->assertCount( 1, $fixture['calls'] );
			$this->assertSame( array(), $fixture['logs'] );
		}
	}
}
