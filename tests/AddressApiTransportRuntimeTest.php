<?php

use Http\Client\Exception\NetworkException;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\TestCase;
use KiriminAjaOfficial\Infrastructure\BoundedResponseStream;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) );
}

/** Installed SDK request creation and Nyholm messages; no live HTTP requests. */
final class AddressApiTransportRuntimeTest extends TestCase {
	protected function tearDown(): void {
		\KiriminAja\Base\Config\Cache\Cache::resetStore();
	}

	private function transport( array $queue, array &$history, array &$options ): \KiriminAjaOfficial\Infrastructure\AddressApiTransport {
		\KiriminAja\Base\Config\Cache\Cache::setStore( new class() implements \KiriminAja\Contracts\CacheStoreContract {
			private array $values = array();
			public function get( string $key ): mixed { return $this->values[ $key ] ?? null; }
			public function put( string $key, mixed $value, int $expiry ): bool { $this->values[ $key ] = $value; return true; }
			public function remove( string $key ): bool { unset( $this->values[ $key ] ); return true; }
		} );
		\KiriminAja\Base\Config\KiriminAjaConfig::setApiTokenKey( 'private-token' );
		$client = new class( $queue, $history ) implements ClientInterface {
			private array $queue;
			private $history;
			public function __construct( array $queue, array &$history ) { $this->queue = $queue; $this->history =& $history; }
			public function sendRequest( RequestInterface $request ): ResponseInterface {
				$this->history[] = $request;
				$response = array_shift( $this->queue );
				if ( $response instanceof \Throwable ) { throw $response; }
				if ( ! $response instanceof ResponseInterface ) { throw new \RuntimeException( 'Unexpected retry.' ); }
				return $response;
			}
		};
		$transport = new class() extends \KiriminAjaOfficial\Infrastructure\AddressApiTransport {
			public static ClientInterface $mock_client;
			public static $options;
			protected static function createAddressClient( array $options ): ClientInterface { self::$options[] = $options; return self::$mock_client; }
			protected function url( $endpoint ): string { return 'https://example.invalid/' . $endpoint; }
		};
		$transport::$mock_client = $client;
		$transport::$options =& $options;
		return $transport;
	}

    public function test_address_timeout_and_sdk_child_contract_are_bounded(): void {
        $history = $options = array();
        $transport = $this->transport( array( new Response( 200, array(), '{"status":true,"results":[{"id":31483}]}' ) ), $history, $options );
        $this->assertSame( array( true, array( 'status' => true, 'results' => array( array( 'id' => 31483 ) ) ) ), $transport->post( 'api/mitra/kelurahan', array( 'kecamatan_id' => 548 ) ) );
        $this->assertCount( 1, $history );
        $this->assertSame( 8, $options[0][CURLOPT_TIMEOUT] );
        $this->assertSame( 3, $options[0][CURLOPT_CONNECTTIMEOUT] );
        $this->assertFalse( $options[0][CURLOPT_FOLLOWLOCATION] );
        $this->assertTrue( $options[0][CURLOPT_SSL_VERIFYPEER] );
        $this->assertSame( 'Bearer private-token', $history[0]->getHeaderLine( 'Authorization' ) );
        $this->assertSame( '{"kecamatan_id":548}', (string) $history[0]->getBody() );
    }

    public function test_address_get_uses_query_and_failure_is_silent_without_retry(): void {
        $history = $options = array();
        $transport = $this->transport( array( new Response( 200, array(), '{"status":true,"data":[]}' ), new Response( 500, array(), 'secret upstream failure' ) ), $history, $options );
        $this->assertSame( array( true, array( 'status' => true, 'data' => array() ) ), $transport->get( 'api/mitra/v6.1/addresses', array( 'search' => '55791' ) ) );
        $this->assertSame( 'search=55791', $history[0]->getUri()->getQuery() );
        $this->assertSame( '', (string) $history[0]->getBody() );
        ob_start();
        $failure = $transport->post( 'api/mitra/kelurahan', array( 'kecamatan_id' => 548 ) );
        $this->assertSame( '', ob_get_clean() );
        $this->assertFalse( $failure[0] );
        $this->assertStringNotContainsString( 'secret', $failure[1] );
        $this->assertCount( 2, $history );
    }
}
